<?php

namespace App\Features\ProjectManagement\Ajax;

use App\Models\ProjectLog;
use App\Settings\LacaTools\SiteHealthChecker;

/**
 * AJAX Handler: Remote Update (Cập nhật plugin/theme/core từ xa)
 *
 * Đăng ký tại project.php __construct():
 *   new RemoteAjaxHandler();
 */
class RemoteAjaxHandler
{
    public function __construct()
    {
        add_action('wp_ajax_laca_remote_update',       [$this, 'remoteUpdate']);
        add_action('wp_ajax_laca_get_pending_updates', [$this, 'getPendingUpdates']);
    }

    // -------------------------------------------------------------------------

    /**
     * AJAX: Gửi lệnh Remote Update đến site client
     *
     * POST fields: nonce, project_id, update_action (update_plugin|update_theme|update_core), update_slug
     */
    public function remoteUpdate(): void
    {
        check_ajax_referer('laca_project_manager', 'nonce');

        $projectId = absint($_POST['project_id'] ?? 0);
        $action    = sanitize_key($_POST['update_action'] ?? '');
        $slug      = sanitize_text_field($_POST['update_slug'] ?? '');

        if (!$projectId || get_post_type($projectId) !== 'project' || !current_user_can('edit_post', $projectId)) {
            wp_send_json_error(['message' => 'Không hợp lệ'], 403);
        }

        $allowedActions = ['update_plugin', 'update_theme', 'update_core'];
        if (!in_array($action, $allowedActions, true)) {
            wp_send_json_error(['message' => 'Action không hợp lệ.'], 400);
        }

        // Lấy thông tin site từ project meta
        $siteUrl   = (string) carbon_get_post_meta($projectId, 'live_url');
        $secretKey = (string) get_post_meta($projectId, '_tracker_secret_key', true);

        if (empty($siteUrl) || empty($secretKey)) {
            wp_send_json_error(['message' => 'Project chưa có live_url hoặc secret key.']);
        }

        $endpoint = trailingslashit($siteUrl) . 'wp-json/laca/v1/remote-update';

        $response = wp_remote_post($endpoint, [
            'body'    => wp_json_encode([
                'secret_key' => $secretKey,
                'action'     => $action,
                'slug'       => $slug,
            ]),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Lỗi kết nối: ' . $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        $msg  = $body['message'] ?? 'Không có phản hồi.';

        if ($code >= 200 && $code < 300 && !empty($body['success'])) {
            ProjectLog::add([
                'project_id'  => $projectId,
                'log_type'    => 'deployment',
                'log_content' => $msg,
                'log_by'      => wp_get_current_user()->display_name ?: 'Admin',
                'is_auto'     => 1,
            ]);

            // Post-deploy smoke test — kiểm tra ngay site khách còn sống
            // không sau khi vừa update, thay vì đợi khách hàng tự báo lỗi.
            if (class_exists(SiteHealthChecker::class)) {
                (new SiteHealthChecker())->checkAfterDeploy($projectId, "remote update ({$action})");
            }

            wp_send_json_success(['message' => $msg]);
        } else {
            ProjectLog::add([
                'project_id'  => $projectId,
                'log_type'    => 'other',
                'log_content' => 'Remote update thất bại: ' . $msg,
                'log_by'      => wp_get_current_user()->display_name ?: 'Admin',
                'is_auto'     => 1,
            ]);
            wp_send_json_error(['message' => $msg]);
        }
    }

    // -------------------------------------------------------------------------

    /**
     * AJAX: Lấy danh sách plugin đang chờ cập nhật từ post meta
     */
    public function getPendingUpdates(): void
    {
        check_ajax_referer('laca_project_manager', 'nonce');

        $projectId = absint($_POST['project_id'] ?? 0);
        if (!$projectId || get_post_type($projectId) !== 'project' || !current_user_can('edit_post', $projectId)) {
            wp_send_json_error(['message' => 'Không hợp lệ'], 403);
        }

        $pendingPlugins = get_post_meta($projectId, '_pending_plugin_updates', true);
        if (!is_array($pendingPlugins)) {
            $pendingPlugins = [];
        }

        wp_send_json_success(['plugins' => $pendingPlugins]);
    }
}
