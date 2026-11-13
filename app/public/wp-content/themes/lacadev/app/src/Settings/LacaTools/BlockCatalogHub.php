<?php

namespace App\Settings\LacaTools;

/**
 * BlockCatalogHub
 *
 * Hub đọc + cache (transient) danh mục Gutenberg block từ clients.lacadev.com
 * (nguồn duy nhất, cấu hình tại Laca Admin → 🗂️ Block Catalog Source), rồi
 * phục vụ lại cho site khách hàng qua REST API — xác thực bằng
 * `_tracker_secret_key` của project (giống hệt kênh tracker log), để site
 * khách tự browse danh mục + biết block nào đã cài / có bản mới / đang chờ
 * duyệt, mà không cần biết gì về clients.lacadev.com.
 *
 * Endpoint: GET /wp-json/laca/v1/blocks-catalog?secret_key=...
 */
class BlockCatalogHub
{
    private const CACHE_KEY = 'laca_block_catalog_cache';
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('wp_ajax_laca_refresh_block_catalog', [$this, 'ajaxRefreshCatalog']);
    }

    /**
     * Cho phép admin bấm "Làm mới ngay" thay vì chờ cache 6h tự hết hạn —
     * trước đây không có cách nào bust cache thủ công, nên block mới thêm ở
     * clients.lacadev.com có thể vô hình với mọi site khách tới 6 tiếng.
     */
    public function ajaxRefreshCatalog(): void
    {
        check_ajax_referer('laca_refresh_block_catalog', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Không có quyền'], 403);
        }

        delete_transient(self::CACHE_KEY);
        $fresh = $this->fetchFromSource();

        if ($fresh === null) {
            wp_send_json_error(['message' => 'Không lấy được danh mục — kiểm tra lại Catalog Endpoint URL/Key.']);
        }

        set_transient(self::CACHE_KEY, $fresh, self::CACHE_TTL);

        wp_send_json_success(['message' => 'Đã làm mới — tìm thấy ' . count($fresh) . ' block.', 'count' => count($fresh)]);
    }

    public function registerRoutes(): void
    {
        register_rest_route('laca/v1', '/blocks-catalog', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'handleGetCatalog'],
            'permission_callback' => '__return_true', // Xác thực bằng secret_key trong callback
        ]);
    }

    public function handleGetCatalog(\WP_REST_Request $request): \WP_REST_Response
    {
        $projectId = null;
        $sigHeader = $request->get_header('x_laca_signature') ?: $request->get_header('x-laca-signature');
        $pubId     = $request->get_header('x_laca_project') ?: $request->get_header('x-laca-project');

        // Luồng 1: Xác thực bằng chữ ký HMAC-SHA256 (key catalog, không cần query string)
        if (!empty($sigHeader) && !empty($pubId)) {
            $projectId = \App\Features\ProjectManagement\ProjectKeyManager::findProjectIdByPublicId($pubId);
            if (!$projectId) {
                return new \WP_REST_Response(['success' => false, 'message' => 'Project không tồn tại hoặc đã ngừng hoạt động.'], 401);
            }

            $key = \App\Features\ProjectManagement\ProjectKeyManager::getKey(
                $projectId,
                \App\Features\ProjectManagement\ProjectKeyManager::KEY_CATALOG
            );

            $verify = \App\Security\RequestSigner::verify($request, $key);
            if (!$verify['valid']) {
                return new \WP_REST_Response(['success' => false, 'message' => $verify['message']], 401);
            }
        } else {
            // Luồng 2: Legacy fallback qua secret_key query hoặc X-Laca-Key
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
                    'message' => 'Legacy authentication đã bị tắt. Yêu cầu ký HMAC (X-Laca-Signature) bằng Catalog Key.',
                ], 401);
            }

            $secretKey = $request->get_header('X-Laca-Key');
            if (empty($secretKey)) {
                $secretKey = (string) ($request->get_param('secret_key') ?? '');
            }
            $secretKey = sanitize_text_field($secretKey);

            if (empty($secretKey)) {
                return new \WP_REST_Response(['success' => false, 'message' => 'Thiếu chữ ký hoặc secret_key.'], 401);
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
                return new \WP_REST_Response(['success' => false, 'message' => 'Secret key không đúng.'], 401);
            }

            update_post_meta((int) $projectId, '_laca_legacy_auth_last_seen', time());
        }

        $catalog = $this->getCachedCatalog();
        if ($catalog === null) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Chưa lấy được danh mục từ clients.lacadev.com. Kiểm tra cấu hình tại Laca Admin → 🗂️ Block Catalog Source.',
            ], 503);
        }

        $projectId = (int) $projectId;

        $installedVersions = get_post_meta($projectId, '_block_sync_versions', true) ?: [];
        $installedVersions = is_array($installedVersions) ? $installedVersions : [];

        $pending = get_post_meta($projectId, '_pending_block_sync_requests', true) ?: [];
        $pending = is_array($pending) ? $pending : [];

        // Chỉ trả trạng thái request MỚI NHẤT cho mỗi block (không cần cả lịch sử)
        $latestRequestByBlock = [];
        foreach ($pending as $req) {
            $name = (string) ($req['block_name'] ?? '');
            if ($name === '') {
                continue;
            }
            $latestRequestByBlock[$name] = [
                'status'       => $req['status'] ?? 'pending',
                'reason'       => $req['reason'] ?? '',
                'requested_at' => $req['requested_at'] ?? '',
            ];
        }

        return new \WP_REST_Response([
            'success'   => true,
            'blocks'    => $catalog,
            'installed' => $installedVersions,
            'requests'  => $latestRequestByBlock,
        ], 200);
    }

    /**
     * @return array<int,array<string,mixed>>|null null nếu chưa cấu hình
     *         hoặc lấy thất bại và chưa từng có cache trước đó.
     */
    private function getCachedCatalog(): ?array
    {
        $cached = get_transient(self::CACHE_KEY);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $fresh = $this->fetchFromSource();
        if ($fresh === null) {
            return null;
        }

        set_transient(self::CACHE_KEY, $fresh, self::CACHE_TTL);

        return $fresh;
    }

    private function fetchFromSource(): ?array
    {
        // Carbon Fields lưu option của simple field với tiền tố "_"
        $url = trim((string) get_option('_laca_catalog_source_url', ''));
        $key = trim((string) get_option('_laca_catalog_source_key', ''));

        if (empty($url) || empty($key)) {
            return null;
        }

        $headers = \App\Security\RequestSigner::sign('GET', $url, '', $key, 'lacadev-hub');
        $headers['Accept'] = 'application/json';
        // TODO: Gỡ header fallback X-Laca-Catalog-Key sau khi nguồn production clients.lacadev.com nâng cấp lên >= 3.9.
        $headers['X-Laca-Catalog-Key'] = $key;

        $host      = strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));
        $sslVerify = ($host === '' || !str_ends_with($host, '.local'));

        $response = wp_remote_get($url, [
            'headers'   => $headers,
            'timeout'   => 15,
            'sslverify' => $sslVerify,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['success']) || !isset($body['blocks']) || !is_array($body['blocks'])) {
            return null;
        }

        return $body['blocks'];
    }
}
