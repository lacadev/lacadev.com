<?php

namespace App\Settings\LacaTools;

use App\Models\ProjectLog;
use App\Models\ProjectAlert;
use App\Notifications\NotificationBus;
use App\Features\ProjectManagement\ProjectKeyManager;
use App\Security\RequestSigner;

/**
 * ClientRequestHandler
 *
 * Tiếp nhận yêu cầu hỗ trợ và báo cáo tháng định kỳ từ website khách hàng (Client).
 * Xác thực HMAC-SHA256 chuẩn Phase 7 qua Tracker Key.
 */
class ClientRequestHandler
{
    public const LOG_TYPE_CLIENT_REQUEST   = 'client_request';
    public const LOG_TYPE_MONTHLY_SUMMARY  = 'monthly_summary';

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('wp_ajax_laca_update_client_request', [$this, 'handleAdminUpdateAjax']);
    }

    public function registerRoutes(): void
    {
        // 1. Gửi yêu cầu hỗ trợ mới (POST)
        register_rest_route('laca/v1', '/client/requests', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleCreateRequest'],
            'permission_callback' => '__return_true',
        ]);

        // 2. Lấy danh sách yêu cầu của site hiện tại (GET)
        register_rest_route('laca/v1', '/client/requests', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'handleGetRequests'],
            'permission_callback' => '__return_true',
        ]);

        // 3. Tiếp nhận báo cáo tháng (POST)
        register_rest_route('laca/v1', '/client/monthly-summary', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleMonthlySummary'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Xác thực request từ Client qua HMAC-SHA256 (hoặc legacy key nếu cho phép)
     *
     * @return int|\WP_Error Project ID nếu hợp lệ, WP_Error nếu thất bại
     */
    private function authenticateRequest(\WP_REST_Request $request)
    {
        $sigHeader = $request->get_header('x_laca_signature') ?: $request->get_header('x-laca-signature');
        $pubId     = $request->get_header('x_laca_project') ?: $request->get_header('x-laca-project');

        // Luồng 1: Xác thực qua chữ ký HMAC-SHA256 (chuẩn chính)
        if (!empty($sigHeader) && !empty($pubId)) {
            $projectId = ProjectKeyManager::findProjectIdByPublicId($pubId);
            if (!$projectId) {
                return new \WP_Error('invalid_project', __('Project không tồn tại hoặc đã ngừng hoạt động.', 'laca'), ['status' => 401]);
            }

            $key = ProjectKeyManager::getKey($projectId, ProjectKeyManager::KEY_TRACKER);
            $verify = RequestSigner::verify($request, $key);
            if (!$verify['valid']) {
                return new \WP_Error('signature_verification_failed', $verify['message'], ['status' => 401]);
            }

            update_post_meta((int) $projectId, '_laca_signed_auth_last_seen', time());
            return (int) $projectId;
        }

        // Luồng 2: Xác thực qua Secret Key truyền thống (nếu bật Legacy Auth)
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
            return new \WP_Error('legacy_disabled', __('Yêu cầu xác thực chữ ký số HMAC-SHA256 qua header X-Laca-*.', 'laca'), ['status' => 401]);
        }

        $params    = $request->get_json_params() ?: $request->get_body_params();
        $secretKey = $params['secret_key'] ?? $request->get_param('secret_key');

        if (empty($secretKey)) {
            return new \WP_Error('missing_auth', __('Thiếu thông tin xác thực HMAC hoặc secret key.', 'laca'), ['status' => 401]);
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
            return new \WP_Error('invalid_secret', __('Secret key không đúng.', 'laca'), ['status' => 401]);
        }

        update_post_meta((int) $projectId, '_laca_legacy_auth_last_seen', time());
        return (int) $projectId;
    }

    /**
     * Xử lý tạo yêu cầu hỗ trợ mới từ site khách (POST /laca/v1/client/requests)
     */
    public function handleCreateRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $auth = $this->authenticateRequest($request);
        if (is_wp_error($auth)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $auth->get_error_message(),
            ], (int) $auth->get_error_data()['status']);
        }

        $projectId = (int) $auth;
        $params    = $request->get_json_params() ?: $request->get_body_params();

        $title       = sanitize_text_field((string) ($params['title'] ?? ''));
        $description = sanitize_textarea_field((string) ($params['description'] ?? ''));
        $category    = sanitize_key((string) ($params['category'] ?? 'other'));
        $priority    = sanitize_key((string) ($params['priority'] ?? 'normal'));
        $attachUrl   = esc_url_raw((string) ($params['attachment_url'] ?? ''));
        $contactName = sanitize_text_field((string) ($params['contact_name'] ?? ''));

        if (empty($title)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Vui lòng nhập tiêu đề yêu cầu hỗ trợ.', 'laca'),
            ], 400);
        }

        if (empty($description)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Vui lòng nhập nội dung mô tả chi tiết.', 'laca'),
            ], 400);
        }

        $categoryLabels = [
            'bug'     => __('Báo lỗi', 'laca'),
            'content' => __('Sửa nội dung', 'laca'),
            'feature' => __('Tính năng mới', 'laca'),
            'other'   => __('Khác', 'laca'),
        ];
        $catLabel = $categoryLabels[$category] ?? __('Hỗ trợ', 'laca');

        $priorityLabels = [
            'low'    => __('Thấp', 'laca'),
            'normal' => __('Bình thường', 'laca'),
            'high'   => __('Cao', 'laca'),
            'urgent' => __('Khẩn cấp', 'laca'),
        ];
        $priLabel = $priorityLabels[$priority] ?? __('Bình thường', 'laca');

        // 1. Lưu vào bảng ProjectLog
        $contentLines = [
            sprintf('[%s - %s] %s', $catLabel, $priLabel, $title),
            '',
            $description,
        ];
        if (!empty($attachUrl)) {
            $contentLines[] = '';
            $contentLines[] = sprintf(__('Ảnh đính kèm: %s', 'laca'), $attachUrl);
        }

        $metaData = [
            'source'         => 'client_support_widget',
            'title'          => $title,
            'category'       => $category,
            'category_label' => $catLabel,
            'priority'       => $priority,
            'priority_label' => $priLabel,
            'description'    => $description,
            'attachment_url' => $attachUrl,
            'contact_name'   => $contactName,
            'status'         => 'pending', // pending, processing, resolved
            'admin_reply'    => '',
            'resolved_at'    => null,
        ];

        $logId = ProjectLog::add([
            'project_id'  => $projectId,
            'log_type'    => self::LOG_TYPE_CLIENT_REQUEST,
            'log_content' => implode("\n", $contentLines),
            'log_by'      => !empty($contactName) ? $contactName : __('Khách hàng', 'laca'),
            'is_auto'     => false,
            'meta'        => $metaData,
        ]);

        if (!$logId) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Không thể lưu yêu cầu hỗ trợ vào hệ thống.', 'laca'),
            ], 500);
        }

        // 2. Tạo Alert trong ProjectAlert
        $alertLevel = match ($priority) {
            'urgent' => 'critical',
            'high'   => 'warning',
            default  => 'info',
        };

        $alertMsg = sprintf(
            /* translators: 1: loại yêu cầu, 2: mức ưu tiên, 3: tiêu đề */
            __('Yêu cầu hỗ trợ [%1$s - %2$s]: %3$s', 'laca'),
            $catLabel,
            $priLabel,
            $title
        );

        $alertId = ProjectAlert::add([
            'project_id'  => $projectId,
            'alert_type'  => 'client_request',
            'alert_level' => $alertLevel,
            'alert_msg'   => $alertMsg,
        ]);

        // Cập nhật id alert vào log meta để khi resolve alert tự đóng
        if ($alertId) {
            $metaData['alert_id'] = (int) $alertId;
            global $wpdb;
            $wpdb->update(
                \App\Databases\ProjectLogTable::getTableName(),
                ['meta' => wp_json_encode($metaData, JSON_UNESCAPED_UNICODE)],
                ['id' => (int) $logId]
            );
        }

        // 3. Phát sự kiện qua NotificationBus
        if (class_exists(NotificationBus::class)) {
            NotificationBus::emit(NotificationBus::EVENT_CLIENT_REQUEST, [
                'request_id'     => (int) $logId,
                'title'          => $title,
                'category'       => $category,
                'category_label' => $catLabel,
                'priority'       => $priority,
                'priority_label' => $priLabel,
                'description'    => $description,
                'attachment_url' => $attachUrl,
                'contact_name'   => $contactName,
                'alert_id'       => $alertId,
            ], $projectId);
        }

        return new \WP_REST_Response([
            'success'    => true,
            'message'    => __('Yêu cầu hỗ trợ đã được gửi thành công tới đội ngũ kỹ thuật LacaDev.', 'laca'),
            'request_id' => (int) $logId,
        ], 201);
    }

    /**
     * Lấy danh sách yêu cầu của site khách hiện tại (GET /laca/v1/client/requests)
     */
    public function handleGetRequests(\WP_REST_Request $request): \WP_REST_Response
    {
        $auth = $this->authenticateRequest($request);
        if (is_wp_error($auth)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $auth->get_error_message(),
            ], (int) $auth->get_error_data()['status']);
        }

        $projectId = (int) $auth;
        $limit     = min(50, max(1, (int) ($request->get_param('limit') ?: 20)));

        global $wpdb;
        $table = \App\Databases\ProjectLogTable::getTableName();
        $rows  = $wpdb->get_results($wpdb->prepare(
            "SELECT id, project_id, log_type, log_content, log_by, meta, created_at
             FROM {$table}
             WHERE project_id = %d AND log_type = %s
             ORDER BY id DESC
             LIMIT %d",
            $projectId,
            self::LOG_TYPE_CLIENT_REQUEST,
            $limit
        ));

        $items = [];
        foreach ($rows as $r) {
            $meta = !empty($r->meta) ? json_decode($r->meta, true) : [];
            $items[] = [
                'id'             => (int) $r->id,
                'title'          => (string) ($meta['title'] ?? wp_trim_words($r->log_content, 10)),
                'category'       => (string) ($meta['category'] ?? 'other'),
                'category_label' => (string) ($meta['category_label'] ?? __('Hỗ trợ', 'laca')),
                'priority'       => (string) ($meta['priority'] ?? 'normal'),
                'priority_label' => (string) ($meta['priority_label'] ?? __('Bình thường', 'laca')),
                'status'         => (string) ($meta['status'] ?? 'pending'), // pending, processing, resolved
                'description'    => (string) ($meta['description'] ?? $r->log_content),
                'attachment_url' => (string) ($meta['attachment_url'] ?? ''),
                'admin_reply'    => (string) ($meta['admin_reply'] ?? ''),
                'created_at'     => (string) $r->created_at,
                'resolved_at'    => $meta['resolved_at'] ?? null,
            ];
        }

        return new \WP_REST_Response([
            'success'  => true,
            'requests' => $items,
        ], 200);
    }

    /**
     * Tiếp nhận báo cáo tháng từ site khách (POST /laca/v1/client/monthly-summary)
     */
    public function handleMonthlySummary(\WP_REST_Request $request): \WP_REST_Response
    {
        $auth = $this->authenticateRequest($request);
        if (is_wp_error($auth)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => $auth->get_error_message(),
            ], (int) $auth->get_error_data()['status']);
        }

        $projectId = (int) $auth;
        $params    = $request->get_json_params() ?: $request->get_body_params();

        $month = sanitize_text_field((string) ($params['month'] ?? gmdate('Y-m', strtotime('-1 month'))));
        $data  = [
            'month'                 => $month,
            'posts_new_count'       => (int) ($params['posts_new_count'] ?? 0),
            'pages_new_count'       => (int) ($params['pages_new_count'] ?? 0),
            'security_events_count' => (int) ($params['security_events_count'] ?? 0),
            'ga4'                   => is_array($params['ga4'] ?? null) ? $params['ga4'] : null,
            'gsc'                   => is_array($params['gsc'] ?? null) ? $params['gsc'] : null,
            'received_at'           => current_time('mysql'),
        ];

        // Lưu metadata báo cáo tháng vào post meta của Project
        update_post_meta($projectId, '_laca_monthly_summary_' . $month, $data);
        update_post_meta($projectId, '_laca_last_monthly_summary', $data);

        // Ghi nhận nhật ký
        $content = sprintf(
            /* translators: 1: tháng, 2: bài viết mới, 3: trang mới, 4: sự kiện bảo mật */
            __('📊 Đã nhận báo cáo tháng %1$s: %2$d bài mới, %3$d trang mới, %4$d sự kiện bảo mật.', 'laca'),
            $month,
            $data['posts_new_count'],
            $data['pages_new_count'],
            $data['security_events_count']
        );

        ProjectLog::add([
            'project_id'  => $projectId,
            'log_type'    => self::LOG_TYPE_MONTHLY_SUMMARY,
            'log_content' => $content,
            'log_by'      => 'Client Auto Report',
            'is_auto'     => true,
            'meta'        => $data,
        ]);

        // Nếu project có bật "Tự gửi báo cáo tháng", kích hoạt gửi email
        $autoSend = carbon_get_post_meta($projectId, 'auto_send_monthly_report');
        if ($autoSend === true || $autoSend === 'yes' || $autoSend === '1') {
            if (class_exists(ProjectPdfExporter::class)) {
                $exporter = new ProjectPdfExporter();
                if (method_exists($exporter, 'sendMonthlyReportEmail')) {
                    $exporter->sendMonthlyReportEmail($projectId, $month);
                }
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => __('Đã tiếp nhận báo cáo tháng thành công.', 'laca'),
            'month'   => $month,
        ], 200);
    }

    /**
     * AJAX cho Admin trên Hub cập nhật trạng thái và trả lời yêu cầu hỗ trợ
     */
    public function handleAdminUpdateAjax(): void
    {
        check_ajax_referer('laca_project_admin_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Bạn không có quyền thực hiện thao tác này.', 'laca')], 403);
        }

        $requestId = (int) ($_POST['request_id'] ?? 0);
        $status    = sanitize_key((string) ($_POST['status'] ?? 'pending'));
        $reply     = sanitize_textarea_field((string) ($_POST['admin_reply'] ?? ''));

        if ($requestId <= 0) {
            wp_send_json_error(['message' => __('Yêu cầu không hợp lệ.', 'laca')], 400);
        }

        global $wpdb;
        $table = \App\Databases\ProjectLogTable::getTableName();
        $row   = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $requestId));

        if (!$row) {
            wp_send_json_error(['message' => __('Không tìm thấy yêu cầu hỗ trợ.', 'laca')], 404);
        }

        $meta = !empty($row->meta) ? json_decode($row->meta, true) : [];
        $meta['status']      = $status;
        $meta['admin_reply'] = $reply;
        if ($status === 'resolved') {
            $meta['resolved_at'] = current_time('mysql');
            // Nếu có alert liên quan, tự động resolve
            if (!empty($meta['alert_id']) && class_exists(ProjectAlert::class)) {
                ProjectAlert::resolve((int) $meta['alert_id']);
            }
        }

        $wpdb->update(
            $table,
            ['meta' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE)],
            ['id' => $requestId]
        );

        wp_send_json_success([
            'message' => __('Đã cập nhật yêu cầu hỗ trợ thành công.', 'laca'),
            'status'  => $status,
        ]);
    }
}
