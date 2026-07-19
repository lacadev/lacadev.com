<?php

namespace App\Settings\LacaTools;

/**
 * BlockCatalogHub
 *
 * Hub đọc + cache (transient) danh mục Gutenberg block từ client.lacadev.com
 * (nguồn duy nhất, cấu hình tại Laca Admin → 🗂️ Block Catalog Source), rồi
 * phục vụ lại cho site khách hàng qua REST API — xác thực bằng
 * `_tracker_secret_key` của project (giống hệt kênh tracker log), để site
 * khách tự browse danh mục + biết block nào đã cài / có bản mới / đang chờ
 * duyệt, mà không cần biết gì về client.lacadev.com.
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
     * client.lacadev.com có thể vô hình với mọi site khách tới 6 tiếng.
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
        $secretKey = sanitize_text_field((string) ($request->get_param('secret_key') ?? ''));
        if (empty($secretKey)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Thiếu secret_key.'], 400);
        }

        global $wpdb;
        $projectId = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            '_tracker_secret_key',
            $secretKey
        ));

        if (!$projectId) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Secret key không đúng.'], 401);
        }

        $catalog = $this->getCachedCatalog();
        if ($catalog === null) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Chưa lấy được danh mục từ client.lacadev.com. Kiểm tra cấu hình tại Laca Admin → 🗂️ Block Catalog Source.',
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

        $response = wp_remote_get($url, [
            'headers' => [
                'X-Laca-Catalog-Key' => $key,
                'Accept'             => 'application/json',
            ],
            'timeout' => 15,
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
