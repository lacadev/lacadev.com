<?php

namespace App\Settings\LacaTools;

use App\Features\ProjectManagement\ProjectKeyManager;
use App\Security\RequestSigner;

class ThemePackageEndpoint
{
    private const ALLOWED_SLUGS      = ['lacadev-client'];
    private const RATE_LIMIT_MAX     = 10;
    private const RATE_LIMIT_WINDOW  = HOUR_IN_SECONDS;

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('laca/v1', '/theme-package/(?P<slug>[a-zA-Z0-9_\-]+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'downloadPackage'],
            'permission_callback' => '__return_true',
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
            return new \WP_REST_Response(['success' => false, 'message' => __('Gói theme không hợp lệ.', 'laca')], 404);
        }

        // Không bao giờ chấp nhận key qua query string (key / secret_key).
        $projectId = null;
        $sigHeader = $request->get_header('x_laca_signature') ?: $request->get_header('x-laca-signature');
        $pubId     = $request->get_header('x_laca_project') ?: $request->get_header('x-laca-project');

        // Luồng 1: Xác thực qua chữ ký HMAC-SHA256
        if (!empty($sigHeader) && !empty($pubId)) {
            $projectId = ProjectKeyManager::findProjectIdByPublicId($pubId);
            if (!$projectId) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Project không tồn tại hoặc đã ngừng hoạt động.', 'laca'),
                ], 401);
            }

            $key    = ProjectKeyManager::getKey($projectId, ProjectKeyManager::KEY_TRACKER);
            $verify = RequestSigner::verify($request, $key);
            if (!$verify['valid']) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $verify['message'] ?: __('Chữ ký HMAC không hợp lệ.', 'laca'),
                ], 401);
            }

            update_post_meta((int) $projectId, '_laca_signed_auth_last_seen', time());
        } else {
            // Luồng 2: Legacy qua header (chỉ khi laca_allow_legacy_auth === 'yes')
            if (!$this->isLegacyAuthAllowed()) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Legacy authentication đã bị tắt. Yêu cầu ký HMAC qua header X-Laca-*.', 'laca'),
                ], 401);
            }

            $legacyKey = (string) (
                $request->get_header('x_laca_key')
                ?: $request->get_header('x-laca-key')
                ?: $request->get_header('x_laca_tracker_key')
                ?: $request->get_header('x-laca-tracker-key')
                ?: ''
            );

            if ($legacyKey === '') {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Thiếu thông tin xác thực trong header.', 'laca'),
                ], 401);
            }

            global $wpdb;
            $foundId = $wpdb->get_var($wpdb->prepare(
                "SELECT pm.post_id FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_tracker_secret_key'
                   AND pm.meta_value = %s AND p.post_status = 'publish'
                 LIMIT 1",
                $legacyKey
            ));

            if (!$foundId) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Secret key không đúng.', 'laca'),
                ], 401);
            }

            $projectId = (int) $foundId;
            update_post_meta($projectId, '_laca_legacy_auth_last_seen', time());
        }

        $projectId = (int) $projectId;
        $clientIp  = sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

        // Rate limit: tối đa 10 lượt tải / giờ cho mỗi project + IP
        $rlKey   = 'laca_pkg_rl_' . md5($projectId . '|' . $clientIp);
        $rlCount = (int) get_transient($rlKey);
        if ($rlCount >= self::RATE_LIMIT_MAX) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Đã vượt giới hạn tải gói cập nhật (tối đa 10 lần mỗi giờ).', 'laca'),
            ], 429);
        }
        set_transient($rlKey, $rlCount + 1, self::RATE_LIMIT_WINDOW);

        // Xác định đường dẫn file zip gói cập nhật
        $packagesDir = (string) get_option('laca_theme_packages_dir', '');
        if ($packagesDir === '') {
            $packagesDir = ABSPATH . 'theme-updates/private';
        }

        $cleanSlug = basename($slug);
        $filePath  = rtrim($packagesDir, '/') . '/' . $cleanSlug . '.zip';
        $realFile  = realpath($filePath);
        $realDir   = realpath($packagesDir);

        if (
            !$realFile
            || !$realDir
            || !str_starts_with($realFile, trailingslashit($realDir))
            || !is_file($realFile)
            || !is_readable($realFile)
        ) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Gói theme không tồn tại trên máy chủ.', 'laca'),
            ], 404);
        }

        $sha256 = $this->getCachedPackageSha256($realFile);

        // Ghi nhận log đã tải gói theme
        $history = get_post_meta($projectId, '_laca_theme_downloaded', true);
        $history = is_array($history) ? $history : [];
        $history[] = [
            'slug'          => $cleanSlug,
            'downloaded_at' => current_time('mysql'),
            'ip'            => $clientIp,
            'auth_mode'     => !empty($sigHeader) ? 'hmac' : 'legacy',
        ];
        if (count($history) > 50) {
            $history = array_slice($history, -50);
        }
        update_post_meta($projectId, '_laca_theme_downloaded', $history);

        // Stream file zip về client kèm X-Laca-Package-Sha256
        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($realFile) . '"');
        header('Content-Length: ' . (string) filesize($realFile));
        header('Content-Transfer-Encoding: binary');
        if ($sha256 !== '') {
            header('X-Laca-Package-Sha256: ' . $sha256);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        readfile($realFile);
        exit;
    }

    private function isLegacyAuthAllowed(): bool
    {
        $direct = get_option('laca_allow_legacy_auth', null);
        if ($direct !== null) {
            return $direct === 'yes' || $direct === true || $direct === '1';
        }

        $cfRaw = get_option('_laca_allow_legacy_auth', null);
        if ($cfRaw !== null) {
            return $cfRaw === 'yes' || $cfRaw === true || $cfRaw === '1';
        }

        return true;
    }

    private function getCachedPackageSha256(string $realFile): string
    {
        $mtime    = (int) (@filemtime($realFile) ?: 0);
        $cacheKey = 'laca_pkg_sha_' . md5($realFile);
        $cached   = get_transient($cacheKey);

        if (is_array($cached) && ($cached['mtime'] ?? -1) === $mtime && !empty($cached['sha256'])) {
            return (string) $cached['sha256'];
        }

        $sha = (string) (hash_file('sha256', $realFile) ?: '');
        if ($sha !== '') {
            set_transient($cacheKey, [
                'mtime'  => $mtime,
                'sha256' => $sha,
            ], DAY_IN_SECONDS);
        }

        return $sha;
    }
}
