<?php

namespace App\Features\ProjectManagement\Api;

use App\Models\ProjectLog;
use App\Models\ProjectAlert;

/**
 * ClientWebhook
 *
 * REST API endpoint nhận báo cáo thay đổi từ các site client (chạy lacadev-client)
 * về server lacadev master và ghi log/cảnh báo vào CPT `project` tương ứng.
 *
 * Endpoint: POST /wp-json/laca/v1/client-report
 *
 * Authentication:
 *   - Client gửi kèm header `X-Laca-Signature` = HMAC-SHA256(body_json, secret_key).
 *   - Secret key được lưu trong `_tracker_secret_key` của post project tương ứng.
 *   - Project được nhận dạng qua field `_live_url` khớp với `site_url` trong payload.
 *
 * Payload JSON từ ClientTracker\Tracker:
 * {
 *   "event":      string,   // upgrader_complete | plugin_activated | plugin_deactivated
 *                           // theme_switched | fim_changes
 *   "site_url":   string,
 *   "site_name":  string,
 *   "timestamp":  int,
 *   "data":       object,
 *   "signature":  string    // (dự phòng, ưu tiên dùng header)
 * }
 */
class ClientWebhook
{
    /** REST namespace + route */
    const ROUTE_NAMESPACE = 'laca/v1';
    const ROUTE_PATH      = '/client-report';

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    // =========================================================================
    // ROUTE REGISTRATION
    // =========================================================================

    public function registerRoutes(): void
    {
        register_rest_route(self::ROUTE_NAMESPACE, self::ROUTE_PATH, [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleReport'],
            'permission_callback' => '__return_true', // Xác thực riêng qua HMAC bên trong
        ]);
    }

    // =========================================================================
    // HANDLER
    // =========================================================================

    /**
     * Xử lý báo cáo từ client.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleReport(\WP_REST_Request $request): \WP_REST_Response
    {
        $body = $request->get_body();

        // --- 1. Parse payload ---
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Payload không hợp lệ.',
            ], 400);
        }

        $event    = sanitize_text_field($payload['event']     ?? '');
        $siteUrl  = sanitize_url($payload['site_url']         ?? '');
        $siteName = sanitize_text_field($payload['site_name'] ?? '');
        $data     = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if (empty($event) || empty($siteUrl)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Thiếu trường bắt buộc: event, site_url.',
            ], 400);
        }

        // --- 2. Tìm Project theo site_url ---
        $projectId = $this->findProjectBySiteUrl($siteUrl);
        if (!$projectId) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Không tìm thấy project khớp với site_url.',
            ], 404);
        }

        // --- 3. Xác thực HMAC signature ---
        $secretKey = (string) get_post_meta($projectId, '_tracker_secret_key', true);
        if (!$this->verifySignature($body, $secretKey, $request)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Xác thực thất bại.',
            ], 401);
        }

        // --- 4. Map event → log content + type, tạo alert nếu cần ---
        [$logContent, $logType, $alertInfo] = $this->mapEventToLog($event, $data, $siteName);

        if (empty($logContent)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Không thể tạo log từ event này.',
            ], 422);
        }

        // --- 5. Ghi ProjectLog ---
        $logId = ProjectLog::add([
            'project_id'  => $projectId,
            'log_content' => $logContent,
            'log_type'    => $logType,
            'is_auto'     => 1,
            'log_by'      => 'LacaDev Client Tracker',
        ]);

        // --- 6. Tạo Alert nếu cần (security events / fim) ---
        if (!empty($alertInfo) && class_exists('\App\Models\ProjectAlert')) {
            $this->maybeCreateAlert($projectId, $alertInfo['message'], $alertInfo['level']);
        }

        return new \WP_REST_Response([
            'success'  => (bool) $logId,
            'log_id'   => $logId ?: null,
            'message'  => $logId ? 'Đã ghi nhận báo cáo.' : 'Ghi log thất bại.',
        ], $logId ? 200 : 500);
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Tìm project ID theo URL website (field `_live_url` của Carbon Fields).
     *
     * @param  string $siteUrl URL cần tìm (đã normalize, bỏ trailing slash).
     * @return int|null
     */
    private function findProjectBySiteUrl(string $siteUrl): ?int
    {
        global $wpdb;

        // Normalize: bỏ trailing slash để so sánh linh hoạt
        $normalized = rtrim($siteUrl, '/');

        // Carbon Fields lưu với prefix '_' → meta_key = '_live_url'
        $id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT post_id
             FROM {$wpdb->postmeta}
             WHERE meta_key = %s
               AND (meta_value = %s OR meta_value = %s)
             LIMIT 1",
            '_live_url',
            $normalized,
            $normalized . '/'
        ));

        if ($id && get_post_type($id) === 'project') {
            return $id;
        }

        return null;
    }

    /**
     * Xác thực HMAC-SHA256 signature.
     *
     * Ưu tiên: header `X-Laca-Signature`, fallback sang field `signature` trong payload.
     *
     * @param  string             $body      Raw request body.
     * @param  string             $secretKey Secret key của project.
     * @param  \WP_REST_Request   $request   Request object.
     * @return bool
     */
    private function verifySignature(string $body, string $secretKey, \WP_REST_Request $request): bool
    {
        if (empty($secretKey)) {
            return false; // Chưa cấu hình secret — từ chối
        }

        $received = $request->get_header('x_laca_signature');

        // Fallback: đọc từ payload nếu header không có
        if (empty($received)) {
            $decoded  = json_decode($body, true);
            $received = $decoded['signature'] ?? '';
        }

        if (empty($received)) {
            return false;
        }

        $expected = hash_hmac('sha256', $body, $secretKey);

        return hash_equals($expected, $received);
    }

    /**
     * Chuyển đổi event + data thành nội dung log có ý nghĩa.
     *
     * @param  string $event    Tên event.
     * @param  array  $data     Dữ liệu kèm theo.
     * @param  string $siteName Tên site client.
     * @return array{ 0: string, 1: string, 2: array|null }
     *         [ logContent, logType, alertInfo|null ]
     */
    private function mapEventToLog(string $event, array $data, string $siteName): array
    {
        $site = $siteName ?: 'Client site';

        switch ($event) {
            case 'upgrader_complete':
                $type    = $data['type']   ?? 'unknown';
                $action  = $data['action'] ?? 'unknown';
                $items   = implode(', ', (array) ($data['items'] ?? [])) ?: '(không có)';
                $content = sprintf(
                    '[%s] %s — %s: %s',
                    strtoupper($type),
                    $action === 'install' ? 'Cài mới' : 'Cập nhật',
                    $site,
                    $items
                );
                return [$content, 'deployment', null];

            case 'plugin_activated':
                $content = sprintf('[Plugin] Kích hoạt: %s — %s', $data['plugin'] ?? '', $site);
                return [$content, 'deployment', null];

            case 'plugin_deactivated':
                $content = sprintf('[Plugin] Vô hiệu hoá: %s — %s', $data['plugin'] ?? '', $site);
                return [$content, 'note', null];

            case 'theme_switched':
                $content = sprintf(
                    '[Theme] Chuyển từ "%s" sang "%s" — %s',
                    $data['old_theme'] ?? '?',
                    $data['new_theme'] ?? '?',
                    $site
                );
                return [$content, 'deployment', null];

            case 'fim_changes':
                $added    = (array) ($data['added']    ?? []);
                $modified = (array) ($data['modified'] ?? []);
                $deleted  = (array) ($data['deleted']  ?? []);

                $summary = sprintf(
                    '[FIM] Thay đổi file — %s: +%d thêm, ~%d sửa, -%d xoá',
                    $site,
                    count($added),
                    count($modified),
                    count($deleted)
                );

                // Liệt kê tối đa 5 file đầu tiên trong log
                $details = [];
                if (!empty($added)) {
                    $details[] = 'Thêm: ' . implode(', ', array_slice($added, 0, 5));
                }
                if (!empty($modified)) {
                    $details[] = 'Sửa: ' . implode(', ', array_slice($modified, 0, 5));
                }
                if (!empty($deleted)) {
                    $details[] = 'Xoá: ' . implode(', ', array_slice($deleted, 0, 5));
                }

                $content = $summary . (count($details) ? "\n" . implode("\n", $details) : '');

                // Tạo alert nếu có file bị sửa hoặc xoá (có thể là xâm nhập)
                $alertInfo = null;
                if (!empty($modified) || !empty($deleted)) {
                    $alertInfo = [
                        'message' => $summary,
                        'level'   => (count($modified) + count($deleted) > 10) ? 'critical' : 'warning',
                    ];
                }

                return [$content, 'bug_fix', $alertInfo];

            default:
                $content = sprintf('[%s] Event không xác định — %s', strtoupper($event), $site);
                return [$content, 'note', null];
        }
    }

    /**
     * Tạo cảnh báo bảo mật với dedup theo nội dung + khoảng thời gian 1 giờ.
     *
     * @param int    $projectId ID của project.
     * @param string $message   Nội dung cảnh báo.
     * @param string $level     'warning' | 'critical'
     */
    private function maybeCreateAlert(int $projectId, string $message, string $level = 'warning'): void
    {
        if (!class_exists('\App\Models\ProjectAlert')) {
            return;
        }

        if (ProjectAlert::existsActiveByMsg($projectId, $message)) {
            return;
        }

        global $wpdb;

        if (method_exists('\App\Databases\ProjectAlertTable', 'getTableName')) {
            $table = \App\Databases\ProjectAlertTable::getTableName();
            $recentCount = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                 WHERE project_id = %d AND is_resolved = 0
                   AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                   AND alert_msg = %s",
                $projectId,
                $message
            ));

            if ($recentCount > 0) {
                return;
            }
        }

        $alertId = ProjectAlert::add([
            'project_id'  => $projectId,
            'alert_type'  => 'security',
            'alert_level' => $level,
            'alert_msg'   => $message,
        ]);

        if ($alertId !== false) {
            do_action('laca_project_alert_notify', $projectId, $level, $message);
        }
    }
}
