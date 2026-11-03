<?php

namespace App\Settings\LacaTools;

use App\Features\ProjectManagement\ProjectKeyManager;
use App\Security\RequestSigner;

class ThemePackageEndpoint
{
    private const ALLOWED_SLUGS = ['lacadev-client'];

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('laca/v1', '/theme-package/(?P<slug>[a-zA-Z0-9_\-]+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'downloadPackage'],
            'permission_callback' => '__return_true', // Authentication xử lý trong callback
            'args'                => [
                'slug' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);
    }

    public function downloadPackage(\WP_REST_Request $request): \WP_REST_Response
    {
        $slug = (string) $request->get_param('slug');
        if (!in_array($slug, self::ALLOWED_SLUGS, true)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Gói theme không hợp lệ.'], 404);
        }

        $projectId = null;
        $sigHeader = $request->get_header('x_laca_signature') ?: $request->get_header('x-laca-signature');
        $pubId     = $request->get_header('x_laca_project') ?: $request->get_header('x-laca-project');

        // Luồng 1: Xác thực qua chữ ký HMAC-SHA256 (Chuẩn mới Phase 7)
        if (!empty($sigHeader) && !empty($pubId)) {
            $projectId = ProjectKeyManager::findProjectIdByPublicId($pubId);
            if (!$projectId) {
                return new \WP_REST_Response(['success' => false, 'message' => 'Project không tồn tại hoặc đã ngừng hoạt động.'], 401);
            }

            $key = ProjectKeyManager::getKey($projectId, ProjectKeyManager::KEY_TRACKER);
            $verify = RequestSigner::verify($request, $key);
            if (!$verify['valid']) {
                return new \WP_REST_Response(['success' => false, 'message' => $verify['message'] ?: 'Chữ ký HMAC không hợp lệ.'], 401);
            }
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

            $legacyKey = (string) (
                $request->get_header('x_laca_key')
                ?: $request->get_header('x-laca-key')
                ?: $request->get_header('x_laca_tracker_key')
                ?: $request->get_header('x-laca-tracker-key')
                ?: $request->get_param('key')
                ?: $request->get_param('secret_key')
                ?: ''
            );

            if (empty($legacyKey)) {
                return new \WP_REST_Response(['success' => false, 'message' => 'Thiếu thông tin xác thực.'], 401);
            }

            global $wpdb;
            $foundId = $wpdb->get_var($wpdb->prepare(
                "SELECT pm.post_id FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE (pm.meta_key = '_tracker_secret_key' OR pm.meta_key = '_laca_key_tracker')
                   AND pm.meta_value = %s AND p.post_status = 'publish'
                 LIMIT 1",
                $legacyKey
            ));

            if (!$foundId) {
                return new \WP_REST_Response(['success' => false, 'message' => 'Secret key không đúng.'], 401);
            }

            $projectId = (int) $foundId;
            update_post_meta($projectId, '_laca_legacy_auth_last_seen', time());
        }

        $projectId = (int) $projectId;

        // Xác định đường dẫn file zip gói cập nhật
        $packagesDir = (string) get_option('laca_theme_packages_dir', '');
        if ($packagesDir === '') {
            $packagesDir = ABSPATH . 'theme-updates/private';
        }

        $cleanSlug = basename($slug);
        $filePath  = rtrim($packagesDir, '/') . '/' . $cleanSlug . '.zip';
        $realFile  = realpath($filePath);
        $realDir   = realpath($packagesDir);

        if (!$realFile || !$realDir || !str_starts_with($realFile, $realDir) || !is_file($realFile) || !is_readable($realFile)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Gói theme không tồn tại trên máy chủ.'], 404);
        }

        // Ghi nhận log đã tải gói theme
        $history = get_post_meta($projectId, '_laca_theme_downloaded', true);
        $history = is_array($history) ? $history : [];
        $history[] = [
            'slug'          => $cleanSlug,
            'downloaded_at' => current_time('mysql'),
            'ip'            => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'auth_mode'     => !empty($sigHeader) ? 'hmac' : 'legacy',
        ];
        if (count($history) > 50) {
            $history = array_slice($history, -50);
        }
        update_post_meta($projectId, '_laca_theme_downloaded', $history);

        // Stream file zip về client
        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($realFile) . '"');
        header('Content-Length: ' . (string) filesize($realFile));
        header('Content-Transfer-Encoding: binary');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        readfile($realFile);
        exit;
    }
}
