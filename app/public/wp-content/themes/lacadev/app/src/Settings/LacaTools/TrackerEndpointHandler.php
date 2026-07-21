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

        $secretKey = $parameters['secret_key'] ?? '';
        $logs      = $parameters['logs'] ?? [];

        if (empty($secretKey) || empty($logs) || !is_array($logs)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Dữ liệu không hợp lệ.'], 400);
        }

        // Tìm Project có secret_key tương ứng — lọc post_status = 'publish'
        // để 1 project đã xoá/lưu trữ (postmeta không tự bị xoá khi vào
        // thùng rác) không thể tiếp tục xác thực và ghi log/bắn cảnh báo vô
        // thời hạn sau khi đã ngừng hợp tác với khách hàng đó.
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
                $blockName = sanitize_key((string) ($meta['block_name'] ?? ''));
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

        $pending[] = [
            'block_name'   => $blockName,
            'requested_at' => current_time('mysql'),
            'status'       => $alreadyInstalled ? 'auto_approved' : 'pending',
            'reason'       => '',
        ];
        update_post_meta($projectId, '_pending_block_sync_requests', $pending);

        $msg = $alreadyInstalled
            ? "🔄 Site khách yêu cầu cập nhật block đã cài: {$blockName}"
            : "🧩 Site khách yêu cầu đồng bộ block mới: {$blockName}";

        $this->createClientRequestAlert($projectId, $msg, 'info', 'other');

        if ($alreadyInstalled) {
            // Đẩy ngay — do App\PostTypes\Concerns\BlockSyncSender lắng nghe
            do_action('laca_block_sync_auto_approved', $projectId, $blockName);
        }
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
