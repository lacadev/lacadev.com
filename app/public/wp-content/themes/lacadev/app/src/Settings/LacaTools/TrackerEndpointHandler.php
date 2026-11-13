<?php

namespace App\Settings\LacaTools;

use App\Models\ProjectLog;
use App\Models\ProjectAlert;

class TrackerEndpointHandler
{
    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('laca/v1', '/tracker/log', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleIncomingLog'],
            'permission_callback' => '__return_true', // Authentication sẽ được xử lý riêng qua Secret Key
        ]);
    }

    public function handleIncomingLog(\WP_REST_Request $request)
    {
        $parameters = $request->get_json_params() ?: $request->get_body_params();
        $logs       = $parameters['logs'] ?? [];

        if (empty($logs) || !is_array($logs)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Dữ liệu không hợp lệ.'], 400);
        }

        $projectId = null;
        $sigHeader = $request->get_header('x_laca_signature') ?: $request->get_header('x-laca-signature');
        $pubId     = $request->get_header('x_laca_project') ?: $request->get_header('x-laca-project');

        // Luồng 1: Xác thực qua chữ ký HMAC-SHA256 (Chuẩn mới Phase 7)
        if (!empty($sigHeader) && !empty($pubId)) {
            $projectId = \App\Features\ProjectManagement\ProjectKeyManager::findProjectIdByPublicId($pubId);
            if (!$projectId) {
                return new \WP_REST_Response(['success' => false, 'message' => 'Project không tồn tại hoặc đã ngừng hoạt động.'], 401);
            }

            $key = \App\Features\ProjectManagement\ProjectKeyManager::getKey(
                $projectId,
                \App\Features\ProjectManagement\ProjectKeyManager::KEY_TRACKER
            );

            $verify = \App\Security\RequestSigner::verify($request, $key);
            if (!$verify['valid']) {
                return new \WP_REST_Response(['success' => false, 'message' => $verify['message']], 401);
            }

            // Ghi nhận lần cuối client dùng xác thực có ký HMAC hợp lệ
            update_post_meta((int) $projectId, '_laca_signed_auth_last_seen', time());
        } else {
            // Luồng 2: Xác thực qua Secret Key truyền thống (Legacy Auth)
            $cfVal = function_exists('carbon_get_theme_option') ? carbon_get_theme_option('laca_allow_legacy_auth') : null;
            if ($cfVal !== null && $cfVal !== '') {
                $allowLegacy = ($cfVal === 'yes' || $cfVal === true || $cfVal === '1');
            } else {
                $raw = get_option('_laca_allow_legacy_auth', null);
                if ($raw === null) {
                    $raw = get_option('laca_allow_legacy_auth', 'yes');
                }
                $allowLegacy = ($raw === 'yes' || $raw === true || $raw === '1');
            }
            if (!$allowLegacy) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Legacy authentication đã bị tắt. Yêu cầu ký HMAC qua header X-Laca-*.',
                ], 401);
            }

            $secretKey = $parameters['secret_key'] ?? '';
            if (empty($secretKey)) {
                return new \WP_REST_Response(['success' => false, 'message' => 'Thiếu chữ ký hoặc secret key.'], 401);
            }

            global $wpdb;
            $projectId = $wpdb->get_var($wpdb->prepare(
                "SELECT pm.post_id FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_status = 'publish'
                 LIMIT 1",
                '_tracker_secret_key',
                $secretKey
            ));

            if (!$projectId) {
                return new \WP_REST_Response(['success' => false, 'message' => 'Secret key không đúng.'], 401);
            }

            // Ghi nhận lần cuối client dùng legacy auth để hiển thị badge cảnh báo
            update_post_meta((int) $projectId, '_laca_legacy_auth_last_seen', time());
        }

        $projectId = (int) $projectId;

        $insertedCount = 0;
        foreach ($logs as $log) {
            $type    = sanitize_key($log['type']    ?? 'other');
            // Dùng wp_strip_all_tags thay sanitize_textarea_field để giữ Unicode emoji & tiếng Việt
            $content = trim(wp_strip_all_tags($log['content'] ?? ''));
            // Loại bỏ emoji (ký tự 4-byte, U+10000 trở lên) để DB utf8 lưu đúng
            $content = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $content) ?? $content;
            $level   = sanitize_key($log['level']   ?? 'info');
            $meta    = $this->sanitizeMeta($log['meta'] ?? []);
            $meta    = is_array($meta) ? $meta : [];

            if (!empty($log['request_id'])) {
                $meta['request_id'] = sanitize_text_field((string) $log['request_id']);
            }

            if (!empty($log['attachments']) && is_array($log['attachments'])) {
                $meta['attachments'] = $this->sanitizeMeta($log['attachments']);
            }

            if (empty($content)) {
                continue;
            }

            if ($type === 'heartbeat') {
                $this->recordHeartbeat((int) $projectId, $content, $meta);
                continue;
            }

            if ($type === 'theme_update_result') {
                $resultState = sanitize_key((string) ($meta['result'] ?? ''));
                $validStates = ['success', 'failed', 'integrity_failed', 'rolled_back'];
                if (!in_array($resultState, $validStates, true)) {
                    continue;
                }

                $stateRecord = [
                    'from'        => sanitize_text_field((string) ($meta['from'] ?? '')),
                    'to'          => sanitize_text_field((string) ($meta['to'] ?? '')),
                    'result'      => $resultState,
                    'error'       => sanitize_text_field((string) ($meta['error'] ?? '')),
                    'sha256'      => sanitize_text_field((string) ($meta['sha256'] ?? '')),
                    'duration_ms' => absint($meta['duration_ms'] ?? 0),
                    'source'      => sanitize_key((string) ($meta['source'] ?? 'dashboard')),
                    'snapshot'    => sanitize_file_name((string) ($meta['snapshot'] ?? '')),
                    'at'          => time(),
                ];

                update_post_meta($projectId, '_laca_theme_update_state', $stateRecord);

                $history = get_post_meta($projectId, '_laca_theme_update_history', true);
                $history = is_array($history) ? $history : [];
                array_unshift($history, $stateRecord);
                $history = array_slice($history, 0, 20);
                update_post_meta($projectId, '_laca_theme_update_history', $history);

                if (in_array($resultState, ['success', 'rolled_back'], true) && $stateRecord['to'] !== '') {
                    $seenMeta = get_post_meta($projectId, '_tracker_last_seen_meta', true);
                    if (is_array($seenMeta)) {
                        $seenMeta['theme_version_parent'] = $stateRecord['to'];
                        update_post_meta($projectId, '_tracker_last_seen_meta', $seenMeta);
                    }
                }

                if (in_array($resultState, ['integrity_failed', 'failed'], true)) {
                    $this->createAlert($projectId, $content, 'critical');
                }

                $logId = ProjectLog::add([
                    'project_id'  => $projectId,
                    'log_content' => $content,
                    'log_type'    => 'deployment',
                    'is_auto'     => 1,
                    'log_by'      => 'LacaDev Tracker Bot',
                    'meta'        => $stateRecord,
                ]);
                if ($logId) {
                    $insertedCount++;
                }
                continue;
            }

            // Map event type → log_type của database
            $deploymentTypes = [
                'deployment',
                'plugin_update', 'theme_update', 'core_update',
                'plugin_install', 'plugin_activate', 'plugin_deactivate', 'plugin_delete',
                'block_sync',
            ];
            $securityTypes         = ['file_changed', 'code_edit', 'file_suspicious'];
            $warningTypes          = ['update_pending'];
            $clientRequestTypes    = ['client_request'];
            $maintenanceTypes      = ['maintenance_summary'];
            $blockSyncRequestTypes = ['block_sync_request'];

            if (in_array($type, $deploymentTypes, true)) {
                $logType = 'deployment';
            } elseif (in_array($type, $securityTypes, true)) {
                $logType = 'bug_fix';
                // Tạo Alert security cho file bị thay đổi / file đáng ngờ
                $alertLevel = ($type === 'file_suspicious' || $level === 'critical') ? 'critical' : 'warning';
                $this->createAlert($projectId, $content, $alertLevel);
            } elseif (in_array($type, $warningTypes, true)) {
                $logType = 'note';
                // Tạo Alert warning cho update pending — dedup theo type + project
                $this->createAlertByType($projectId, $content, 'warning', 'plugin_update');
                // Lưu structured plugin list để UI remote update có thể đọc
                $pendingPlugins = $log['plugins'] ?? [];
                if (!empty($pendingPlugins) && is_array($pendingPlugins)) {
                    // Validate từng item: chỉ giữ slug, name, current_version, new_version
                    $clean = [];
                    foreach ($pendingPlugins as $p) {
                        if (empty($p['slug'])) continue;
                        $clean[] = [
                            'slug'            => sanitize_text_field($p['slug'] ?? ''),
                            'name'            => sanitize_text_field($p['name'] ?? $p['slug']),
                            'current_version' => sanitize_text_field($p['current_version'] ?? $p['current'] ?? ''),
                            'new_version'     => sanitize_text_field($p['new_version'] ?? $p['new'] ?? ''),
                        ];
                    }

                    if (!empty($clean)) {
                        update_post_meta($projectId, '_pending_plugin_updates', $clean);
                    }
                }
            } elseif (in_array($type, $clientRequestTypes, true)) {
                $logType = 'client_request';
                $requestType = sanitize_key((string) ($log['request_type'] ?? 'request'));
                $alertType = $requestType === 'bug' ? 'bug' : 'other';
                $alertLevel = $requestType === 'bug' || $level === 'warning' ? 'warning' : 'info';
                $this->createClientRequestAlert($projectId, $content, $alertLevel, $alertType);
            } elseif (in_array($type, $maintenanceTypes, true)) {
                $logType = 'maintenance_summary';
            } elseif (in_array($type, $blockSyncRequestTypes, true)) {
                $logType = 'client_request';
                $blockName = self::sanitizeBlockRelativePath((string) ($meta['block_name'] ?? ''));
                if (!empty($blockName)) {
                    $this->handleBlockSyncRequest((int) $projectId, $blockName);
                }
            } else {
                $logType = 'note';
            }

            // Cảnh báo tự động khi xóa plugin
            if ($type === 'plugin_delete') {
                $this->createAlert($projectId, $content, 'warning');
            }

            $logId = ProjectLog::add([
                'project_id'  => $projectId,
                'log_content' => $content,
                'log_type'    => $logType,
                'is_auto'     => 1,
                'log_by'      => 'LacaDev Tracker Bot',
                'meta'        => $meta,
            ]);

            if ($logId) {
                $insertedCount++;
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => "Đã ghi nhận {$insertedCount} sự kiện.",
        ], 200);

    }

    /**
     * Tạo cảnh báo lên hệ thống chính nếu phát hiện sửa code / file đáng ngờ.
     * Double dedup: theo nội dung + theo khoảng thời gian 1 giờ.
     */
    private function createAlert(int $projectId, string $msg, string $level = 'warning'): void
    {
        if (!class_exists('\App\Models\ProjectAlert')) {
            return;
        }

        // Dedup kiểu 1: theo nội dung tin nhắn
        if (ProjectAlert::existsActiveByMsg($projectId, $msg)) {
            return;
        }

        // Dedup kiểu 2: không tạo thêm alert cùng nội dung trong 1 giờ
        global $wpdb;
        $table = \App\Databases\ProjectAlertTable::getTableName();
        $recentCount = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE project_id = %d AND is_resolved = 0
             AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             AND alert_msg = %s",
            $projectId,
            ProjectAlert::stripEmoji(sanitize_textarea_field($msg))
        ));

        if ($recentCount > 0) {
            return;
        }

        $alertId = ProjectAlert::add([
            'project_id'  => $projectId,
            'alert_type'  => 'security',
            'alert_level' => $level,
            'alert_msg'   => $msg,
        ]);

        if ($alertId !== false) {
            do_action('laca_project_alert_notify', (int) $projectId, $level, $msg);
        }
    }

    /**
     * Tạo alert với dedup theo alert_type + project (dùng cho update_pending).
     * Nếu đã có alert cùng type chưa resolve → skip.
     */
    private function createAlertByType(int $projectId, string $msg, string $level, string $alertType): void
    {
        if (!class_exists('\App\Models\ProjectAlert')) {
            return;
        }

        // Dedup theo NỘI DUNG cụ thể — KHÔNG theo alert_type chung chung.
        // Trước đây dùng existsActive($projectId, $alertType): nếu project đã
        // có 1 alert 'plugin_update' chưa resolve (vd "Plugin A cần update"),
        // MỌI cảnh báo plugin_update khác sau đó (vd "Plugin B cần update")
        // bị chặn luôn cho tới khi alert cũ được resolve — admin không bao
        // giờ biết có thêm plugin khác cần cập nhật.
        if (ProjectAlert::existsActiveByMsg($projectId, $msg)) {
            return;
        }

        $alertId = ProjectAlert::add([
            'project_id'  => $projectId,
            'alert_type'  => $alertType,
            'alert_level' => $level,
            'alert_msg'   => $msg,
        ]);

        if ($alertId !== false) {
            do_action('laca_project_alert_notify', (int) $projectId, $level, $msg);
        }
    }

    /**
     * Site khách yêu cầu đồng bộ 1 Gutenberg block từ clients.lacadev.com.
     *
     * Nếu block ĐÃ được cài trên project này (`_block_sync_versions`) —
     * coi đây là yêu cầu "cập nhật lên bản mới", tự động duyệt luôn (không
     * cần admin thao tác) vì bản chất đã được duyệt từ lần đầu, chỉ là bump
     * version. Nếu block CHƯA từng có — đây là yêu cầu block mới, phải vào
     * hàng chờ để admin hub duyệt thủ công trong "Block Sync Manager".
     */
    private function handleBlockSyncRequest(int $projectId, string $blockName): void
    {
        // Rate-limit theo project + block, ĐỘC LẬP với trạng thái request —
        // endpoint /tracker/log chỉ xác thực bằng secret_key, không đi qua
        // giới hạn 5 phút/lần ở UI Block Marketplace, nên ai có secret_key
        // (hoặc 1 client bị lỗi) có thể gửi lặp lại liên tục để ép hub tự
        // động push block nhiều lần / phình to _pending_block_sync_requests
        // vô hạn. Chặn ngay tại đây, không phụ thuộc trạng thái request.
        $rateLimitKey = 'laca_block_sync_req_' . md5($projectId . '|' . $blockName);
        if (get_transient($rateLimitKey)) {
            return;
        }
        set_transient($rateLimitKey, 1, 5 * MINUTE_IN_SECONDS);

        $installedVersions = get_post_meta($projectId, '_block_sync_versions', true) ?: [];
        $installedVersions = is_array($installedVersions) ? $installedVersions : [];
        $alreadyInstalled  = isset($installedVersions[$blockName]);

        $pending = get_post_meta($projectId, '_pending_block_sync_requests', true) ?: [];
        $pending = is_array($pending) ? $pending : [];

        // Chống trùng: bỏ qua nếu đã có request CHƯA xử lý xong cho đúng
        // block này (kể cả 'auto_approved' — không chỉ 'pending', vì request
        // có thể đang được BlockSyncSender xử lý ngay lúc này).
        foreach ($pending as $req) {
            if (($req['block_name'] ?? '') === $blockName && in_array($req['status'] ?? '', ['pending', 'auto_approved'], true)) {
                return;
            }
        }

        $canAutoApprove = $alreadyInstalled && $this->canAutoApproveBlockSync($projectId, $blockName);

        $pending[] = [
            'block_name'   => $blockName,
            'requested_at' => current_time('mysql'),
            'status'       => $canAutoApprove ? 'auto_approved' : 'pending',
            'reason'       => '',
        ];
        update_post_meta($projectId, '_pending_block_sync_requests', $pending);

        $msg = $canAutoApprove
            ? "🔄 Site khách yêu cầu cập nhật block đã cài (tự động duyệt): {$blockName}"
            : "🧩 Site khách yêu cầu đồng bộ block (chờ duyệt): {$blockName}";

        $this->createClientRequestAlert($projectId, $msg, 'info', 'other');

        if ($canAutoApprove) {
            // Đẩy ngay — do App\PostTypes\Concerns\BlockSyncSender lắng nghe
            do_action('laca_block_sync_auto_approved', $projectId, $blockName);
        }
    }

    /**
     * Chỉ tự động duyệt (auto-approve) khi `requires` của block thoả mãn và block trên site khách không bị `local_modified`.
     */
    private function canAutoApproveBlockSync(int $projectId, string $blockName): bool
    {
        // 1. Kiểm tra requires từ cache catalog (nếu có)
        $catalog = get_transient('laca_block_catalog_cache');
        if (is_array($catalog)) {
            foreach ($catalog as $item) {
                $itemPath = (string) ($item['path'] ?? $item['name'] ?? '');
                if ($itemPath !== $blockName && (string) ($item['name'] ?? '') !== $blockName) {
                    continue;
                }

                $requires = is_array($item['requires'] ?? null) ? $item['requires'] : [];
                if (!empty($requires['functions']) || !empty($requires['ajax'])) {
                    // Không thể chắc chắn hàm/ajax tồn tại từ phía Hub trước khi push -> để pending
                    return false;
                }

                if (!empty($requires['parent']) && is_string($requires['parent'])) {
                    $updateState = get_post_meta($projectId, '_laca_theme_update_state', true);
                    $seenMeta    = get_post_meta($projectId, '_tracker_last_seen_meta', true);
                    $parentVer   = '';
                    if (is_array($updateState) && !empty($updateState['to'])) {
                        $parentVer = (string) $updateState['to'];
                    } elseif (is_array($seenMeta) && !empty($seenMeta['theme_version_parent'])) {
                        $parentVer = (string) $seenMeta['theme_version_parent'];
                    }

                    if ($parentVer === '') {
                        return false;
                    }

                    $constraint = trim($requires['parent']);
                    if (preg_match('/^(>=|<=|>|<|==|=)\s*(.+)$/', $constraint, $m)) {
                        $op  = $m[1] === '=' ? '==' : $m[1];
                        $ver = trim($m[2]);
                    } else {
                        $op  = '>=';
                        $ver = $constraint;
                    }

                    if (!version_compare($parentVer, $ver, $op)) {
                        return false;
                    }
                }
                break;
            }
        }

        // 2. Kiểm tra trạng thái local_modified trên site khách trước khi auto-approve
        $endpointUrl = function_exists('carbon_get_post_meta')
            ? (string) carbon_get_post_meta($projectId, 'sync_endpoint_url')
            : (string) get_post_meta($projectId, '_sync_endpoint_url', true);
        if ($endpointUrl === '') {
            return false;
        }

        $statusUrl  = rtrim($endpointUrl, '/') . '/status?block=' . rawurlencode($blockName);
        $keyCatalog = \App\Features\ProjectManagement\ProjectKeyManager::getKey(
            $projectId,
            \App\Features\ProjectManagement\ProjectKeyManager::KEY_CATALOG
        );
        $publicId = \App\Features\ProjectManagement\ProjectKeyManager::getPublicId($projectId);

        $headers = ['Accept' => 'application/json'];
        if (!empty($keyCatalog) && !empty($publicId)) {
            $headers = array_merge(
                $headers,
                \App\Security\RequestSigner::sign('GET', $statusUrl, '', $keyCatalog, $publicId)
            );
        } else {
            $apiKey = function_exists('carbon_get_post_meta')
                ? (string) carbon_get_post_meta($projectId, 'sync_api_key')
                : (string) get_post_meta($projectId, '_sync_api_key', true);
            if ($apiKey !== '') {
                $headers['X-Laca-Key'] = $apiKey;
            }
        }

        $host      = strtolower(rtrim((string) parse_url($statusUrl, PHP_URL_HOST), '.'));
        $sslVerify = ($host === '' || !str_ends_with($host, '.local'));

        $response = wp_remote_get($statusUrl, [
            'headers'   => $headers,
            'timeout'   => 10,
            'sslverify' => $sslVerify,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return false;
        }

        if (!empty($body['local_modified'])) {
            return false;
        }

        $storedChecksums = get_post_meta($projectId, '_block_sync_checksums', true);
        if (is_array($storedChecksums) && isset($storedChecksums[$blockName]) && isset($body['checksum'])) {
            if ((string) $body['checksum'] !== (string) $storedChecksums[$blockName]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Làm sạch "block relative path" — tên block phẳng (không "/") hoặc
     * "{bucket}/{block}" (đúng 1 dấu "/"). Dùng thay cho sanitize_key() vì
     * sanitize_key() xoá sạch dấu "/" và sẽ phá path có bucket.
     */
    private static function sanitizeBlockRelativePath(string $raw): string
    {
        $raw = strtolower(trim($raw));
        $segments = array_filter(explode('/', $raw), static fn ($s) => $s !== '');
        $segments = array_map(static fn ($s) => preg_replace('/[^a-z0-9_-]/', '', $s), $segments);
        $segments = array_filter($segments, static fn ($s) => $s !== '' && $s !== '.' && $s !== '..');
        $segments = array_slice($segments, 0, 2);

        return implode('/', $segments);
    }

    private function createClientRequestAlert(int $projectId, string $msg, string $level, string $alertType): void
    {
        if (!class_exists('\App\Models\ProjectAlert')) {
            return;
        }

        if (ProjectAlert::existsActiveByMsg($projectId, $msg)) {
            return;
        }

        $alertId = ProjectAlert::add([
            'project_id'  => $projectId,
            'alert_type'  => $alertType,
            'alert_level' => $level,
            'alert_msg'   => $msg,
        ]);

        if ($alertId !== false) {
            do_action('laca_project_alert_notify', (int) $projectId, $level, $msg);
        }
    }

    private function recordHeartbeat(int $projectId, string $content, array $meta): void
    {
        update_post_meta($projectId, '_tracker_last_seen_at', current_time('mysql'));
        update_post_meta($projectId, '_tracker_last_seen_message', sanitize_text_field($content));

        if (!empty($meta)) {
            update_post_meta($projectId, '_tracker_last_seen_meta', $meta);
        }

        if (isset($meta['plugins_pending_update'])) {
            update_post_meta($projectId, '_plugins_pending_count', absint($meta['plugins_pending_update']));
        }

        if (isset($meta['backup']) && is_array($meta['backup'])) {
            $backupData = [
                'plugin'         => sanitize_text_field((string) ($meta['backup']['plugin'] ?? 'none')),
                'last_backup_at' => sanitize_text_field((string) ($meta['backup']['last_backup_at'] ?? '')),
                'status'         => sanitize_text_field((string) ($meta['backup']['status'] ?? 'unknown')),
                'received_at'    => current_time('mysql'),
            ];
            update_post_meta($projectId, '_tracker_last_backup', $backupData);
            $this->checkBackupWarning($projectId, $backupData);
        }
    }

    /**
     * Kiểm tra trạng thái backup và sinh cảnh báo khi quá hạn hoặc không rõ quá 7 ngày
     *
     * @param int $projectId
     * @param array{plugin: string, last_backup_at: string, status: string, received_at: string} $backupData
     */
    private function checkBackupWarning(int $projectId, array $backupData): void
    {
        $status       = $backupData['status'] ?? 'unknown';
        $lastBackupAt = $backupData['last_backup_at'] ?? '';
        $plugin       = $backupData['plugin'] ?? 'none';

        $maxDays = function_exists('carbon_get_theme_option') ? (int) carbon_get_theme_option('laca_backup_max_age_days') : 7;
        if ($maxDays <= 0) {
            $maxDays = 7;
        }

        $now = time();
        $isWarning = false;
        $warningMsg = '';

        if ($status === 'unknown') {
            $unknownSince = (int) get_post_meta($projectId, '_laca_backup_unknown_since', true);
            if ($unknownSince === 0) {
                update_post_meta($projectId, '_laca_backup_unknown_since', $now);
            } elseif (($now - $unknownSince) >= 7 * DAY_IN_SECONDS) {
                $isWarning   = true;
                $projectName = get_the_title($projectId) ?: "#{$projectId}";
                /* translators: %s: tên dự án */
                $warningMsg  = sprintf(__('Dự án %s không xác định được trạng thái sao lưu trong hơn 7 ngày.', 'laca'), $projectName);
            }
        } else {
            // Đã phát hiện được backup rõ ràng -> xóa baseline unknown
            delete_post_meta($projectId, '_laca_backup_unknown_since');

            if (!empty($lastBackupAt)) {
                $backupTime = strtotime($lastBackupAt);
                if ($backupTime > 0) {
                    $ageDays = ($now - $backupTime) / DAY_IN_SECONDS;
                    if ($ageDays > $maxDays) {
                        $isWarning   = true;
                        $daysDiff    = (int) floor($ageDays);
                        $projectName = get_the_title($projectId) ?: "#{$projectId}";
                        /* translators: 1: tên dự án, 2: số ngày, 3: tên plugin sao lưu, 4: ngày sao lưu */
                        $warningMsg = sprintf(
                            __('Dự án %1$s chưa có bản sao lưu mới trong %2$d ngày qua (Plugin: %3$s, Lần cuối: %4$s).', 'laca'),
                            $projectName,
                            $daysDiff,
                            $plugin,
                            $lastBackupAt
                        );
                    }
                }
            } elseif ($status === 'failed') {
                $isWarning   = true;
                $projectName = get_the_title($projectId) ?: "#{$projectId}";
                /* translators: 1: tên dự án, 2: tên plugin sao lưu */
                $warningMsg = sprintf(
                    __('Dự án %1$s có bản sao lưu gần nhất thất bại (Plugin: %2$s).', 'laca'),
                    $projectName,
                    $plugin
                );
            }
        }

        if (!$isWarning) {
            return;
        }

        // Dedup 1 lần/ngày/project
        $lastAlertSent = (int) get_post_meta($projectId, '_laca_backup_alert_last_sent', true);
        if ($lastAlertSent > 0 && ($now - $lastAlertSent) < DAY_IN_SECONDS) {
            return;
        }

        $this->createAlertByType($projectId, $warningMsg, 'warning', 'backup');
        update_post_meta($projectId, '_laca_backup_alert_last_sent', $now);
    }

    private function sanitizeMeta(mixed $value): array|string|int|float|bool|null
    {
        if (is_array($value)) {
            $clean = [];

            foreach ($value as $key => $item) {
                $cleanKey = is_int($key) ? $key : sanitize_key((string) $key);
                if ($cleanKey === '') {
                    continue;
                }

                $clean[$cleanKey] = $this->sanitizeMeta($item);
            }

            return $clean;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            return esc_url_raw($value);
        }

        return sanitize_text_field((string) $value);
    }
}
