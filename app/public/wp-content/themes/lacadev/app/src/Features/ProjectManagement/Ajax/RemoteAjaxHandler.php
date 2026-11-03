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
        $publicId  = \App\Features\ProjectManagement\ProjectKeyManager::getPublicId($projectId);
        $keyRemote = \App\Features\ProjectManagement\ProjectKeyManager::getKey(
            $projectId,
            \App\Features\ProjectManagement\ProjectKeyManager::KEY_REMOTE
        );
        if (empty($keyRemote)) {
            $keyRemote = (string) get_post_meta($projectId, '_tracker_secret_key', true);
        }

        if (empty($siteUrl) || empty($keyRemote)) {
            wp_send_json_error(['message' => 'Project chưa có live_url hoặc remote update key.']);
        }

        $endpoint = trailingslashit($siteUrl) . 'wp-json/laca/v1/remote-update';

        // Lấy tracker key (legacy secret_key) cho các client cũ chưa cấu hình remote key
        $trackerKey = \App\Features\ProjectManagement\ProjectKeyManager::getKey(
            $projectId,
            \App\Features\ProjectManagement\ProjectKeyManager::KEY_TRACKER
        ) ?: (string) get_post_meta($projectId, '_tracker_secret_key', true);

        // Kiểm tra setting laca_allow_legacy_auth
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

        $bodyData = [
            'action' => $action,
            'slug'   => $slug,
        ];
        // Chỉ gửi tracker key vào body.secret_key khi legacy auth đang bật (hỗ trợ client cũ)
        if ($allowLegacy && !empty($trackerKey)) {
            $bodyData['secret_key'] = $trackerKey;
        }

        $bodyJson = (string) wp_json_encode($bodyData);

        // Chữ ký luôn dùng remote key
        $sigHeaders = \App\Security\RequestSigner::sign(
            'POST',
            '/wp-json/laca/v1/remote-update',
            $bodyJson,
            $keyRemote,
            $publicId
        );

        $postArgs = [
            'body'    => $bodyJson,
            'headers' => array_merge([
                'Content-Type' => 'application/json',
            ], $sigHeaders),
            'timeout' => 30,
        ];

        $response = wp_remote_post($endpoint, $postArgs);

        // Dự phòng khi site client dùng plain permalink hoặc route 404
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 404) {
            $fallbackEndpoint = trailingslashit($siteUrl) . '?rest_route=/laca/v1/remote-update';
            $response = wp_remote_post($fallbackEndpoint, $postArgs);
        }

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
