<?php

namespace App\Settings\LacaTools;

use App\Models\ProjectLog;
use App\Models\ProjectAlert;

/**
 * Client Portal REST API
 *
 * Cung cấp endpoint công khai cho khách hàng xem tiến độ dự án.
 * Authentication: qua `_tracker_secret_key` (không cần đăng nhập WP).
 *
 * Endpoints:
 *   GET  /laca/v1/portal/project   ?key=SECRET_KEY
 *   POST /laca/v1/portal/request
 */
class ClientPortalEndpoint
{
    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('laca/v1', '/portal/project', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'getProjectData'],
            'permission_callback' => '__return_true',
            'args'                => [
                'key' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route('laca/v1', '/portal/request', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'submitClientRequest'],
            'permission_callback' => '__return_true',
            'args'                => [
                'key' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'request_type' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_key',
                ],
                'message' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
                'contact_name' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'contact_email' => [
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_email',
                ],
            ],
        ]);
    }

    /**
     * Trả về thông tin dự án, logs và alerts cho client portal.
     */
    public function getProjectData(\WP_REST_Request $request): \WP_REST_Response
    {
        $secretKey = $request->get_param('key');

        if (empty($secretKey)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Thiếu secret key.'], 400);
        }

        // Tìm project có secret key tương ứng
        $projectId = $this->findProjectByKey($secretKey);
        if (!$projectId) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Secret key không hợp lệ.'], 401);
        }

        $post = get_post($projectId);
        if (!$post || $post->post_status !== 'publish') {
            return new \WP_REST_Response(['success' => false, 'message' => 'Dự án không tồn tại.'], 404);
        }

        // Thông tin cơ bản (chỉ những gì client được xem)
        $projectData = $this->buildProjectData($projectId, $post);

        return new \WP_REST_Response([
            'success' => true,
            'project' => $projectData,
        ], 200);
    }

    /**
     * Cho khách gửi yêu cầu/lỗi từ client portal.
     */
    public function submitClientRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $secretKey = (string) $request->get_param('key');
        $message   = trim((string) $request->get_param('message'));

        if ($secretKey === '' || $message === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'Thiếu nội dung yêu cầu.'], 400);
        }

        $projectId = $this->findProjectByKey($secretKey);
        if (!$projectId) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Secret key không hợp lệ.'], 401);
        }

        $post = get_post($projectId);
        if (!$post || $post->post_status !== 'publish') {
            return new \WP_REST_Response(['success' => false, 'message' => 'Dự án không tồn tại.'], 404);
        }

        $rateKey = 'laca_portal_request_' . md5($secretKey . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        if (get_transient($rateKey)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Bạn vừa gửi yêu cầu. Vui lòng thử lại sau ít phút.',
            ], 429);
        }

        $requestType = sanitize_key((string) ($request->get_param('request_type') ?: 'request'));
        $allowedTypes = ['request', 'bug', 'content', 'maintenance', 'billing'];
        if (!in_array($requestType, $allowedTypes, true)) {
            $requestType = 'request';
        }

        $contactName  = sanitize_text_field((string) $request->get_param('contact_name'));
        $contactEmail = sanitize_email((string) $request->get_param('contact_email'));
        $attachmentsResult = $this->handleRequestAttachments($request, $projectId);
        if (is_wp_error($attachmentsResult)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $attachmentsResult->get_error_message(),
            ], 400);
        }

        $attachments = $attachmentsResult;
        $typeLabels   = [
            'request'     => 'Yêu cầu hỗ trợ',
            'bug'         => 'Báo lỗi',
            'content'     => 'Nội dung cần cập nhật',
            'maintenance' => 'Bảo trì',
            'billing'     => 'Thanh toán',
        ];

        $parts = [
            '[' . ($typeLabels[$requestType] ?? 'Yêu cầu hỗ trợ') . ']',
            $message,
        ];
        if ($contactName !== '') {
            $parts[] = 'Người gửi: ' . $contactName;
        }
        if ($contactEmail !== '') {
            $parts[] = 'Email: ' . $contactEmail;
        }
        if ($attachments !== []) {
            $parts[] = 'Ảnh đính kèm: ' . count($attachments);
        }

        $logId = ProjectLog::add([
            'project_id'  => $projectId,
            'log_type'    => 'client_request',
            'log_content' => implode("\n", $parts),
            'log_by'      => $contactName !== '' ? $contactName : 'Client Portal',
            'is_auto'     => true,
            'meta'        => [
                'source' => 'client_portal',
                'request_type' => $requestType,
                'contact_name' => $contactName,
                'contact_email' => $contactEmail,
                'attachments' => $attachments,
            ],
        ]);

        if (!$logId) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Không thể ghi nhận yêu cầu.'], 500);
        }

        ProjectAlert::add([
            'project_id'  => $projectId,
            'alert_type'  => $requestType === 'bug' ? 'bug' : 'other',
            'alert_level' => $requestType === 'bug' ? 'warning' : 'info',
            'alert_msg'   => sprintf(
                'Client Portal: %s - %s%s',
                $typeLabels[$requestType] ?? 'Yêu cầu hỗ trợ',
                wp_trim_words($message, 22),
                $attachments !== [] ? ' (' . count($attachments) . ' ảnh)' : ''
            ),
        ]);

        set_transient($rateKey, 1, 5 * MINUTE_IN_SECONDS);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Yêu cầu đã được gửi. Đội ngũ LacaDev sẽ kiểm tra và phản hồi.',
            'log_id' => (int) $logId,
        ], 201);
    }

    /**
     * @return array<int,array{id:int,url:string,name:string}>|\WP_Error
     */
    private function handleRequestAttachments(\WP_REST_Request $request, int $projectId)
    {
        $fileParams = $request->get_file_params();
        $files = $this->normalizeUploadedFiles($fileParams['attachments'] ?? null);

        if ($files === []) {
            return [];
        }

        if (count($files) > 4) {
            return new \WP_Error('too_many_attachments', 'Bạn chỉ có thể đính kèm tối đa 4 ảnh.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        $uploaded = [];

        foreach ($files as $index => $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                return new \WP_Error('upload_error', 'Có lỗi khi tải ảnh đính kèm lên. Vui lòng thử lại.');
            }

            if ((int) ($file['size'] ?? 0) > 5 * MB_IN_BYTES) {
                return new \WP_Error('upload_too_large', 'Mỗi ảnh đính kèm phải nhỏ hơn 5MB.');
            }

            $mime = (string) ($file['type'] ?? '');
            if (!in_array($mime, $allowedMimes, true)) {
                $checked = wp_check_filetype_and_ext((string) ($file['tmp_name'] ?? ''), (string) ($file['name'] ?? ''));
                $mime = (string) ($checked['type'] ?? $mime);
            }

            if (!in_array($mime, $allowedMimes, true)) {
                return new \WP_Error('invalid_attachment_type', 'Chỉ hỗ trợ ảnh JPG, PNG hoặc WebP.');
            }

            $handled = wp_handle_upload($file, [
                'test_form' => false,
                'mimes' => [
                    'jpg|jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                ],
                'unique_filename_callback' => static function (string $dir, string $name, string $ext) use ($projectId, $index): string {
                    $base = sanitize_title(pathinfo($name, PATHINFO_FILENAME));
                    return 'portal-request-' . $projectId . '-' . gmdate('Ymd-His') . '-' . $index . '-' . $base . $ext;
                },
            ]);

            if (!empty($handled['error'])) {
                return new \WP_Error('upload_failed', 'Không thể lưu ảnh đính kèm. Vui lòng thử lại.');
            }

            $attachmentId = wp_insert_attachment([
                'post_mime_type' => $mime,
                'post_title' => sanitize_text_field(pathinfo((string) ($file['name'] ?? ''), PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit',
                'post_parent' => $projectId,
            ], $handled['file']);

            if (!is_wp_error($attachmentId) && $attachmentId) {
                $metadata = wp_generate_attachment_metadata((int) $attachmentId, $handled['file']);
                if (is_array($metadata)) {
                    wp_update_attachment_metadata((int) $attachmentId, $metadata);
                }
            } else {
                $attachmentId = 0;
            }

            $uploaded[] = [
                'id' => (int) $attachmentId,
                'url' => esc_url_raw((string) ($handled['url'] ?? '')),
                'name' => sanitize_file_name((string) ($file['name'] ?? '')),
            ];
        }

        return $uploaded;
    }

    /**
     * @param mixed $files
     * @return array<int,array{name:string,type:string,tmp_name:string,error:int,size:int}>
     */
    private function normalizeUploadedFiles($files): array
    {
        if (!is_array($files) || $files === []) {
            return [];
        }

        if (!is_array($files['name'] ?? null)) {
            return [$files];
        }

        $normalized = [];
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            $normalized[] = [
                'name' => (string) ($files['name'][$i] ?? ''),
                'type' => (string) ($files['type'][$i] ?? ''),
                'tmp_name' => (string) ($files['tmp_name'][$i] ?? ''),
                'error' => (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($files['size'][$i] ?? 0),
            ];
        }

        return array_values(array_filter($normalized, static fn(array $file): bool => ($file['name'] ?? '') !== ''));
    }

    private function findProjectByKey(string $key): ?int
    {
        global $wpdb;

        // Cache key để tránh query nhiều lần
        $cacheKey = 'laca_portal_key_' . md5($key);
        $cached   = wp_cache_get($cacheKey, 'laca_portal');
        if ($cached !== false) {
            return (int) $cached ?: null;
        }

        // 1. Ưu tiên tìm theo alias dễ nhớ (_portal_alias)
        $projectId = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = %s AND meta_value = %s
             LIMIT 1",
            '_portal_alias',
            $key
        ));

        // 2. Fallback: tìm theo secret key gốc
        if (!$projectId) {
            $projectId = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = %s AND meta_value = %s
                 LIMIT 1",
                '_tracker_secret_key',
                $key
            ));
        }

        wp_cache_set($cacheKey, $projectId ?: 0, 'laca_portal', 300); // Cache 5 phút

        return $projectId ? (int) $projectId : null;
    }

    /**
     * Build dữ liệu dự án (chỉ expose thông tin an toàn cho client).
     */
    private function buildProjectData(int $projectId, \WP_Post $post): array
    {
        // Thông tin hiển thị cho client (KHÔNG bao gồm mật khẩu, FTP, etc.)
        $status = carbon_get_post_meta($projectId, 'project_status') ?: 'pending';

        $statusLabels = [
            'pending'     => ['label' => 'Chờ bắt đầu',  'color' => '#f0ad4e', 'icon' => '🕐'],
            'in_progress' => ['label' => 'Đang thực hiện', 'color' => '#3b82f6', 'icon' => '🔨'],
            'done'        => ['label' => 'Hoàn thành',    'color' => '#10b981', 'icon' => '✅'],
            'maintenance' => ['label' => 'Bảo trì',       'color' => '#8b5cf6', 'icon' => '🔧'],
            'paused'      => ['label' => 'Tạm dừng',      'color' => '#6b7280', 'icon' => '⏸️'],
        ];

        $statusInfo = $statusLabels[$status] ?? ['label' => $status, 'color' => '#6b7280', 'icon' => '📋'];

        // Tính % hoàn thành từ task list (ưu tiên) hoặc handover checklist
        $rawTasks    = json_decode(get_post_meta($projectId, '_laca_task_list', true) ?: '[]', true);
        if (!is_array($rawTasks)) $rawTasks = [];

        if (!empty($rawTasks)) {
            $taskDone  = count(array_filter($rawTasks, fn($t) => (bool)($t['done'] ?? false)));
            $progress  = $status === 'done' ? 100 : (int) round($taskDone / count($rawTasks) * 100);
        } else {
            $progress = $status === 'done' ? 100 : $this->estimateProgress($status);
        }

        // Build safe task list for client
        $tasks = array_values(array_map(fn($t) => [
            'id'          => $t['id'] ?? '',
            'name'        => $t['name'] ?? '',
            'description' => $t['description'] ?? '',
            'image_url'   => $t['image_url'] ?? '',
            'done'        => (bool) ($t['done'] ?? false),
            'source'      => $t['source'] ?? 'manual',
            'category'    => $t['category'] ?? (($t['source'] ?? '') === 'page' ? 'page' : 'other'),
        ], $rawTasks));


        // Timeline
        $dateStart           = carbon_get_post_meta($projectId, 'date_start') ?: '';
        $dateHandover        = carbon_get_post_meta($projectId, 'date_handover') ?: '';
        $dateActualHandover  = carbon_get_post_meta($projectId, 'date_actual_handover') ?: '';

        // Domain & live URL (chỉ domain name, không có pass)
        $domainName = carbon_get_post_meta($projectId, 'domain_name') ?: '';
        $liveUrl    = carbon_get_post_meta($projectId, 'live_url') ?: '';

        // Bảo trì
        $maintenanceEnd  = carbon_get_post_meta($projectId, 'maintenance_end') ?: '';
        $maintenanceType = carbon_get_post_meta($projectId, 'maintenance_type') ?: 'none';

        // Logs công khai cho client portal: ẩn security/internal-sensitive.
        $rawLogs = array_values(array_filter(
            ProjectLog::getByProject($projectId, 50),
            fn($log) => $this->isPublicLog((string) ($log['log_type'] ?? ''))
        ));
        $logs    = array_map(function ($log) {
            $meta = !empty($log['meta']) ? json_decode((string) $log['meta'], true) : [];
            if (!is_array($meta)) {
                $meta = [];
            }

            return [
                'id'         => (int) $log['id'],
                'date'       => date('d/m/Y', strtotime($log['log_date'])),
                'time'       => !empty($log['created_at']) ? date('H:i', strtotime($log['created_at'])) : '',
                'type'       => sanitize_key((string) $log['log_type']),
                'type_label' => $this->getPublicLogTypeLabel((string) $log['log_type']),
                'category'   => $this->getPublicLogCategory((string) $log['log_type']),
                'content'    => $this->sanitizePublicLogContent((string) $log['log_content']),
                'by'         => sanitize_text_field((string) $log['log_by']),
                'is_auto'    => (bool) $log['is_auto'],
                'attachments' => $this->extractPublicAttachments($meta['attachments'] ?? []),
            ];
        }, $rawLogs);

        // Alerts active (chỉ level info + warning, ẩn critical security alerts)
        $rawAlerts  = ProjectAlert::getActive($projectId);
        $alerts     = array_values(array_filter(array_map(function ($alert) {
            // Không hiện security alerts ở portal
            if ($alert['alert_type'] === 'security') {
                return null;
            }
            return [
                'id'         => (int) $alert['id'],
                'type'       => sanitize_key((string) $alert['alert_type']),
                'type_label' => ProjectAlert::getTypeLabel($alert['alert_type']),
                'level'      => sanitize_key((string) $alert['alert_level']),
                'message'    => sanitize_textarea_field((string) $alert['alert_msg']),
                'date'       => date('d/m/Y', strtotime($alert['created_at'])),
            ];
        }, $rawAlerts)));

        return [
            'id'           => $projectId,
            'name'         => sanitize_text_field($post->post_title),
            'status'       => $status,
            'status_info'  => $statusInfo,
            'progress'     => $progress,
            'domain'       => sanitize_text_field($domainName),
            'live_url'     => esc_url_raw($liveUrl),
            'dates'        => [
                'start'           => $dateStart ? date('d/m/Y', strtotime($dateStart)) : '',
                'handover'        => $dateHandover ? date('d/m/Y', strtotime($dateHandover)) : '',
                'actual_handover' => $dateActualHandover ? date('d/m/Y', strtotime($dateActualHandover)) : '',
            ],
            'maintenance'  => [
                'type'    => $maintenanceType,
                'end'     => $maintenanceEnd ? date('d/m/Y', strtotime($maintenanceEnd)) : '',
            ],
            'logs'         => $logs,
            'service_report' => $this->buildServiceReport($logs),
            'monthly_report' => $this->buildMonthlyReport($logs),
            'recent_requests' => $this->buildRecentRequests($logs),
            'alerts'       => $alerts,
            'log_count'    => count($logs),
            'tasks'        => $tasks,
        ];
    }

    private function isPublicLog(string $type): bool
    {
        return !in_array($type, ['security', 'file_changed'], true);
    }

    private function getPublicLogCategory(string $type): string
    {
        return match ($type) {
            'deployment', 'plugin_update', 'plugin_activate', 'plugin_deactivate', 'core_update', 'theme_switch' => 'update',
            'bug_fix', 'client_request' => 'fix',
            'task_done' => 'task',
            default => 'maintenance',
        };
    }

    private function getPublicLogTypeLabel(string $type): string
    {
        return [
            'note' => 'Bảo trì',
            'plugin_update' => 'Cập nhật plugin',
            'plugin_activate' => 'Kích hoạt plugin',
            'plugin_deactivate' => 'Tắt plugin',
            'core_update' => 'Cập nhật WordPress',
            'theme_switch' => 'Đổi giao diện',
            'deployment' => 'Triển khai',
            'bug_fix' => 'Sửa lỗi',
            'client_request' => 'Yêu cầu khách hàng',
            'task_done' => 'Hoàn thành task',
        ][$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    private function sanitizePublicLogContent(string $content): string
    {
        $content = preg_replace('/\b[A-Fa-f0-9]{24,}\b/', '[ẩn mã bảo mật]', $content) ?? $content;
        $content = preg_replace('/(password|token|secret|key)\s*[:=]\s*\S+/i', '$1: [ẩn]', $content) ?? $content;

        return trim($content);
    }

    /**
     * @param mixed $attachments
     * @return array<int,array{id:int,url:string,name:string}>
     */
    private function extractPublicAttachments($attachments): array
    {
        if (!is_array($attachments)) {
            return [];
        }

        $items = [];
        foreach ($attachments as $attachment) {
            if (!is_array($attachment) || empty($attachment['url'])) {
                continue;
            }

            $items[] = [
                'id' => (int) ($attachment['id'] ?? 0),
                'url' => esc_url_raw((string) $attachment['url']),
                'name' => sanitize_file_name((string) ($attachment['name'] ?? 'attachment')),
            ];
        }

        return $items;
    }

    /**
     * @param array<int,array<string,mixed>> $logs
     */
    private function buildServiceReport(array $logs): array
    {
        $counts = [
            'total' => count($logs),
            'updates' => 0,
            'fixes' => 0,
            'maintenance' => 0,
            'tasks' => 0,
        ];

        foreach ($logs as $log) {
            $category = (string) ($log['category'] ?? 'maintenance');
            if ($category === 'update') {
                $counts['updates']++;
            } elseif ($category === 'fix') {
                $counts['fixes']++;
            } elseif ($category === 'task') {
                $counts['tasks']++;
            } else {
                $counts['maintenance']++;
            }
        }

        return [
            'total' => $counts['total'],
            'updates' => $counts['updates'],
            'fixes' => $counts['fixes'],
            'maintenance' => $counts['maintenance'],
            'tasks' => $counts['tasks'],
            'latest_date' => $logs[0]['date'] ?? '',
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $logs
     */
    private function buildMonthlyReport(array $logs): array
    {
        $month = current_time('m/Y');
        $items = array_values(array_filter($logs, static function (array $log) use ($month): bool {
            return (string) ($log['date'] ?? '') !== '' && str_ends_with((string) $log['date'], $month);
        }));

        return [
            'month' => $month,
            'total' => count($items),
            'updates' => count(array_filter($items, static fn(array $log): bool => ($log['category'] ?? '') === 'update')),
            'fixes' => count(array_filter($items, static fn(array $log): bool => ($log['category'] ?? '') === 'fix')),
            'maintenance' => count(array_filter($items, static fn(array $log): bool => ($log['category'] ?? '') === 'maintenance')),
            'tasks' => count(array_filter($items, static fn(array $log): bool => ($log['category'] ?? '') === 'task')),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $logs
     * @return array<int,array<string,mixed>>
     */
    private function buildRecentRequests(array $logs): array
    {
        return array_slice(array_values(array_filter($logs, static function (array $log): bool {
            return ($log['type'] ?? '') === 'client_request';
        })), 0, 5);
    }

    /**
     * Ước tính % tiến độ dựa trên trạng thái project.
     */
    private function estimateProgress(string $status): int
    {
        return match ($status) {
            'pending'     => 5,
            'in_progress' => 55,
            'maintenance' => 90,
            'paused'      => 40,
            'done'        => 100,
            default       => 0,
        };
    }

}
