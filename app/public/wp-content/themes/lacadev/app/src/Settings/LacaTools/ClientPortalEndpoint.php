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
            // Fallback: handover checklist
            $checklist  = (array) carbon_get_post_meta($projectId, 'handover_checklist');
            $checkItems = ['backup', 'ssl', 'speed_test', 'mobile_test', 'seo_basic', 'training', 'payment_done', 'handover_doc'];
            $doneCount  = count(array_intersect($checklist, $checkItems));
            $progress   = $status === 'done' ? 100 : ($doneCount > 0 ? (int) round($doneCount / count($checkItems) * 100) : $this->estimateProgress($status));
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

        // Logs (chỉ lấy 20 gần nhất, loại trừ nội dung nhạy cảm)
        $rawLogs = ProjectLog::getByProject($projectId, 20);
        $logs    = array_map(function ($log) {
            return [
                'id'         => (int) $log['id'],
                'date'       => date('d/m/Y', strtotime($log['log_date'])),
                'type'       => $log['log_type'],
                'type_label' => ProjectLog::getTypeLabel($log['log_type']),
                'content'    => esc_html($log['log_content']),
                'by'         => esc_html($log['log_by']),
                'is_auto'    => (bool) $log['is_auto'],
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
                'type'       => $alert['alert_type'],
                'type_label' => ProjectAlert::getTypeLabel($alert['alert_type']),
                'level'      => $alert['alert_level'],
                'message'    => esc_html($alert['alert_msg']),
                'date'       => date('d/m/Y', strtotime($alert['created_at'])),
            ];
        }, $rawAlerts)));

        return [
            'id'           => $projectId,
            'name'         => esc_html($post->post_title),
            'status'       => $status,
            'status_info'  => $statusInfo,
            'progress'     => $progress,
            'domain'       => esc_html($domainName),
            'live_url'     => esc_url($liveUrl),
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
            'alerts'       => $alerts,
            'log_count'    => ProjectLog::countByProject($projectId),
            'tasks'        => $tasks,
        ];
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
