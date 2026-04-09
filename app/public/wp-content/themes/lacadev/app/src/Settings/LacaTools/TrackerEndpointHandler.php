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

        // Tìm Project có secret_key tương ứng
        global $wpdb;
        $projectId = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
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

            if (empty($content)) {
                continue;
            }

            // Map event type → log_type của database
            $deploymentTypes = [
                'plugin_update', 'theme_update', 'core_update',
                'plugin_install', 'plugin_activate', 'plugin_deactivate', 'plugin_delete',
            ];
            $securityTypes   = ['file_changed', 'code_edit', 'file_suspicious'];
            $warningTypes    = ['update_pending'];

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
                            'current_version' => sanitize_text_field($p['current_version'] ?? ''),
                            'new_version'     => sanitize_text_field($p['new_version'] ?? ''),
                        ];
                    }
                    if (!empty($clean)) {
                        update_post_meta($projectId, '_pending_plugin_updates', $clean);
                    }
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

        ProjectAlert::add([
            'project_id'  => $projectId,
            'alert_type'  => 'security',
            'alert_level' => $level,
            'alert_msg'   => $msg,
        ]);
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

        // Dedup: không tạo nếu đã có alert cùng type + project chưa resolve
        if (ProjectAlert::existsActive($projectId, $alertType)) {
            return;
        }

        ProjectAlert::add([
            'project_id'  => $projectId,
            'alert_type'  => $alertType,
            'alert_level' => $level,
            'alert_msg'   => $msg,
        ]);
    }
}
