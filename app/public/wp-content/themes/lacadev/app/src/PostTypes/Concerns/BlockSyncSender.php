<?php

namespace App\PostTypes\Concerns;

use App\Contracts\AssetHandles;
use App\Settings\LacaTools\SiteHealthChecker;

/**
 * Trait BlockSyncSender
 *
 * Xử lý toàn bộ logic Block Sync từ lacadev.com đến client sites.
 * Sử dụng trong: App\PostTypes\Project
 */
trait BlockSyncSender
{
    // =========================================================================
    // HOOKS REGISTRATION
    // =========================================================================

    public function registerBlockSyncHooks(): void
    {
        add_action('add_meta_boxes', [$this, 'registerBlockSyncMetaBox']);
        add_action('wp_ajax_laca_push_blocks', [$this, 'ajaxPushBlocks']);
        add_action('wp_ajax_laca_fetch_client_blocks', [$this, 'ajaxFetchClientBlocks']);
        add_action('wp_ajax_laca_approve_block_request', [$this, 'ajaxApproveBlockRequest']);
        add_action('wp_ajax_laca_reject_block_request', [$this, 'ajaxRejectBlockRequest']);
        add_action('wp_ajax_laca_rollback_block', [$this, 'ajaxRollbackBlock']);
        // Site khách yêu cầu cập nhật 1 block ĐÃ cài — tự động duyệt (xem
        // TrackerEndpointHandler::handleBlockSyncRequest()), lắng nghe ở đây
        // để đẩy ngay mà không cần admin thao tác.
        add_action('laca_block_sync_auto_approved', [$this, 'handleAutoApprovedRequest'], 10, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueueBlockSyncAssets']);
    }

    public function enqueueBlockSyncAssets(string $hook): void
    {
        global $post;
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        if (!$post || $post->post_type !== 'project') {
            return;
        }

        // SweetAlert2 is bundled in admin JS (window.Swal set in admin/index.js).
        // Do NOT load from CDN — use the locally-bundled version instead.
        wp_enqueue_script(AssetHandles::ADMIN_JS);

        // Inline script cho Block Sync UI — runs after admin bundle which exposes window.Swal
        wp_add_inline_script(AssetHandles::ADMIN_JS, $this->getBlockSyncInlineScript(), 'after');
    }

    // =========================================================================
    // META BOX
    // =========================================================================

    public function registerBlockSyncMetaBox(): void
    {
        add_meta_box(
            'laca_block_sync_manager',
            'Block Sync Manager',
            [$this, 'renderBlockSyncMetaBox'],
            'project',
            'normal',
            'high'
        );
    }

    public function renderBlockSyncMetaBox(int|\WP_Post $post): void
    {
        $postId           = is_int($post) ? $post : $post->ID;
        $installedMeta    = get_post_meta($postId, '_block_sync_versions', true) ?: [];
        $installedMeta    = is_array($installedMeta) ? $installedMeta : [];
        $history          = get_post_meta($postId, '_block_sync_history', true) ?: [];
        $history          = is_array($history) ? $history : [];
        $availableBlocks  = $this->getAvailableBlocks();
        $nonce            = wp_create_nonce('laca_block_sync');
        $blockDir         = $this->resolveBlockDir();
        $pendingRequests  = $this->getPendingBlockRequests($postId);
        ?>
        <div id="laca-block-sync-wrap" style="margin:-6px -12px -12px">

            <div style="padding:12px 16px 0">
                <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:12px 14px;margin:0 0 12px"><p style="margin:0;font-size:13px;color:#374151">Khu vực này để đẩy từng block Gutenberg riêng lẻ từ <strong>client.lacadev.com</strong> xuống site của khách hàng này. Nếu bảng bên dưới xuất hiện <strong>yêu cầu đang chờ duyệt</strong> — tức khách hàng đã tự chọn block trên trang Block Marketplace của họ — bấm <strong>Duyệt &amp; Đẩy</strong> để đẩy ngay xuống site khách, hoặc <strong>Từ chối</strong> kèm lý do để khách hàng nhìn thấy vì sao yêu cầu không được duyệt. Hệ thống sẽ tự cảnh báo nếu phát hiện site khách đã tự sửa block trước khi ghi đè, và sau khi đẩy thành công sẽ tự kiểm tra site khách còn hoạt động bình thường không. Nếu bản mới có vấn đề, mỗi block đã đẩy đều có nút <strong>⏪ Rollback</strong> để quay lại phiên bản trước đó.</p></div>
            </div>

            <?php if (!empty($pendingRequests)): ?>
            <!-- Yêu cầu đồng bộ block đang chờ duyệt (từ site khách hàng) -->
            <div style="padding:12px 16px;background:#fffbeb;border-bottom:1px solid #fde68a">
                <p style="margin:0 0 10px;font-weight:600;color:#92400e">
                    🔔 Yêu cầu đồng bộ block đang chờ duyệt (<?php echo count($pendingRequests); ?>)
                </p>
                <?php foreach ($pendingRequests as $req): ?>
                <?php
                    $reqBlockName   = $req['block_name'];
                    $isUpdateReq    = isset($installedMeta[$reqBlockName]);
                ?>
                <div class="laca-block-request-row"
                     data-block="<?php echo esc_attr($reqBlockName); ?>"
                     data-post-id="<?php echo esc_attr($postId); ?>"
                     data-nonce="<?php echo esc_attr($nonce); ?>"
                     data-is-update="<?php echo $isUpdateReq ? '1' : '0'; ?>"
                     style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 0;border-top:1px solid #fde68a">
                    <div>
                        <strong><?php echo esc_html($reqBlockName); ?></strong>
                        <?php if ($isUpdateReq): ?>
                            <span style="background:#fd7e1422;color:#fd7e14;padding:1px 6px;border-radius:10px;font-size:10px;font-weight:600;margin-left:6px">CẬP NHẬT — GHI ĐÈ BẢN ĐANG CÀI</span>
                        <?php else: ?>
                            <span style="background:#0ea5e922;color:#0369a1;padding:1px 6px;border-radius:10px;font-size:10px;font-weight:600;margin-left:6px">BLOCK MỚI</span>
                        <?php endif; ?>
                        <br>
                        <small style="color:#78716c">Yêu cầu lúc <?php echo esc_html($req['requested_at']); ?></small>
                    </div>
                    <div style="display:flex;gap:6px;flex-shrink:0">
                        <button type="button" class="button button-primary laca-approve-block-request-btn">✅ Duyệt &amp; Đẩy</button>
                        <button type="button" class="button laca-reject-block-request-btn">✕ Từ chối</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Header toolbar -->
            <div style="
                display:flex; align-items:center; justify-content:space-between;
                padding:12px 16px; background:#f8f9fa; border-bottom:1px solid #e2e4e7;
            ">
                <div style="display:flex;gap:8px;align-items:center">
                    <label style="display:flex;gap:6px;align-items:center;font-size:13px;cursor:pointer">
                        <input type="checkbox" id="laca-select-all-blocks"> Chọn tất cả
                    </label>
                    <button
                        type="button"
                        id="laca-push-blocks-btn"
                        class="button button-primary"
                        style="display:flex;gap:6px;align-items:center"
                        data-post-id="<?php echo esc_attr($postId); ?>"
                        data-nonce="<?php echo esc_attr($nonce); ?>"
                    >
                        <span class="dashicons dashicons-upload" style="margin-top:3px"></span>
                        Push Selected
                    </button>
                    <button
                        type="button"
                        id="laca-sync-outdated-btn"
                        class="button"
                        data-post-id="<?php echo esc_attr($postId); ?>"
                        data-nonce="<?php echo esc_attr($nonce); ?>"
                        title="Push tất cả blocks đang cũ hơn lacadev"
                    >
                        🔄 Sync Outdated
                    </button>
                    <button
                        type="button"
                        id="laca-refresh-status-btn"
                        class="button"
                        data-post-id="<?php echo esc_attr($postId); ?>"
                        data-nonce="<?php echo esc_attr($nonce); ?>"
                        title="Tải lại trạng thái từ client"
                    >
                        ↻ Refresh
                    </button>
                </div>
                <span id="laca-sync-status-text" style="font-size:12px;color:#888"></span>
            </div>

            <!-- Block table -->
            <table class="widefat fixed striped" style="border:none;border-radius:0">
                <thead>
                    <tr style="background:#f0f0f1">
                        <th style="width:36px; padding:10px 12px">☐</th>
                        <th style="padding:10px 12px">Block Name</th>
                        <th style="padding:10px 12px; width:120px">Phiên bản</th>
                        <th style="padding:10px 12px; width:140px">Client hiện có</th>
                        <th style="padding:10px 12px; width:120px">Trạng thái</th>
                    </tr>
                </thead>
                <tbody id="laca-blocks-table-body">
                    <?php foreach ($availableBlocks as $block): ?>
                    <?php
                        $name          = $block['name'];
                        $localVersion  = $block['version'];
                        $clientVersion = $installedMeta[$name] ?? null;
                        $badge         = $this->getVersionBadge($localVersion, $clientVersion);
                    ?>
                    <tr data-block="<?php echo esc_attr($name); ?>"
                        data-local-ver="<?php echo esc_attr($localVersion); ?>"
                        data-client-ver="<?php echo esc_attr($clientVersion ?? ''); ?>">
                        <td style="padding:10px 12px">
                            <input type="checkbox"
                                class="laca-block-checkbox"
                                value="<?php echo esc_attr($name); ?>">
                        </td>
                        <td style="padding:10px 12px">
                            <strong><?php echo esc_html($block['title']); ?></strong>
                            <br>
                            <small style="color:#888;font-family:monospace"><?php echo esc_html($name); ?></small>
                        </td>
                        <td style="padding:10px 12px">
                            <code><?php echo esc_html($localVersion); ?></code>
                        </td>
                        <td class="laca-client-ver" style="padding:10px 12px">
                            <?php if ($clientVersion): ?>
                                <code><?php echo esc_html($clientVersion); ?></code>
                            <?php else: ?>
                                <span style="color:#999">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="laca-status-badge" style="padding:10px 12px">
                            <?php echo $badge; ?>
                            <?php $blockHistory = $history[$name] ?? []; ?>
                            <?php if (count($blockHistory) >= 2): ?>
                                <br>
                                <button type="button" class="button-link laca-rollback-btn"
                                    data-block="<?php echo esc_attr($name); ?>"
                                    data-post-id="<?php echo esc_attr($postId); ?>"
                                    data-nonce="<?php echo esc_attr($nonce); ?>"
                                    title="Quay lại phiên bản trước đó trên site khách"
                                    style="font-size:11px;color:#6b7280;text-decoration:underline;margin-top:2px">⏪ Rollback về v<?php echo esc_html($blockHistory[1]['version']); ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($availableBlocks)): ?>
                    <tr>
                        <td colspan="5" style="padding:16px;text-align:center;color:#888">
                            Không tìm thấy blocks trong <code><?php echo esc_html($blockDir); ?></code>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // =========================================================================
    // AJAX: Push blocks to client
    // =========================================================================

    public function ajaxPushBlocks(): void
    {
        check_ajax_referer('laca_block_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Không có quyền'], 403);
        }

        $postId     = absint($_POST['post_id'] ?? 0);
        $blockNames = array_map('sanitize_key', (array) ($_POST['blocks'] ?? []));

        if (!$postId || get_post_type($postId) !== 'project') {
            wp_send_json_error(['message' => 'Project không hợp lệ'], 400);
        }

        if (empty($blockNames)) {
            wp_send_json_error(['message' => 'Chưa chọn block nào'], 400);
        }

        $apiKey      = carbon_get_post_meta($postId, 'sync_api_key');
        $endpointUrl = carbon_get_post_meta($postId, 'sync_endpoint_url');

        if (empty($apiKey) || empty($endpointUrl)) {
            wp_send_json_error(['message' => 'Chưa cấu hình API Key hoặc Endpoint URL'], 400);
        }

        $results       = [];
        $installedMeta = get_post_meta($postId, '_block_sync_versions', true) ?: [];

        foreach ($blockNames as $blockName) {
            $result = $this->pushSingleBlock($postId, $blockName, $apiKey, $endpointUrl);

            if ($result['success']) {
                $installedMeta[$blockName] = $result['version'];
            }

            $results[$blockName] = $result;
        }

        // Lưu versions đã sync thành công
        update_post_meta($postId, '_block_sync_versions', $installedMeta);

        // Post-deploy smoke test — kiểm tra 1 lần cho site này nếu có ít
        // nhất 1 block push thành công (không cần check lặp lại mỗi block).
        if (class_exists(SiteHealthChecker::class) && in_array(true, array_column($results, 'success'), true)) {
            (new SiteHealthChecker())->checkAfterDeploy($postId, 'đẩy block Gutenberg');
        }

        wp_send_json_success(['results' => $results]);
    }

    // =========================================================================
    // AJAX: Fetch installed versions from client
    // =========================================================================

    public function ajaxFetchClientBlocks(): void
    {
        check_ajax_referer('laca_block_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Không có quyền'], 403);
        }

        $postId = absint($_POST['post_id'] ?? 0);

        if (!$postId || get_post_type($postId) !== 'project') {
            wp_send_json_error(['message' => 'Project không hợp lệ'], 400);
        }

        $apiKey      = carbon_get_post_meta($postId, 'sync_api_key');
        $endpointUrl = carbon_get_post_meta($postId, 'sync_endpoint_url');

        if (empty($apiKey) || empty($endpointUrl)) {
            wp_send_json_error(['message' => 'Chưa cấu hình API Key hoặc Endpoint URL'], 400);
        }

        // Gọi GET /status endpoint
        // endpointUrl là .../sync-block → status là .../sync-block/status
        $statusUrl = rtrim($endpointUrl, '/') . '/status';
        $response  = wp_remote_get($statusUrl, [
            'headers' => [
                'X-Laca-Key' => $apiKey,
                'Accept'     => 'application/json',
            ],
            'timeout'   => 15,
            'sslverify' => !defined('WP_DEBUG') || !WP_DEBUG,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Không kết nối được client: ' . $response->get_error_message()]);
        }

        $code    = wp_remote_retrieve_response_code($response);
        // Strip UTF-8 BOM (\xEF\xBB\xBF) + whitespace — BOM là lý do phổ biến nhất
        // khiến json_decode báo "Syntax error" dù body nhìn có vẻ đúng
        $rawBody = wp_remote_retrieve_body($response);
        $rawBody = ltrim($rawBody, "\xEF\xBB\xBF"); // Strip UTF-8 BOM
        $rawBody = trim($rawBody);
        $body    = json_decode($rawBody, true);

        if ($code === 401) {
            wp_send_json_error(['message' => 'API Key không hợp lệ — kiểm tra lại API Key trong tab 🧩 Block Sync']);
        }

        if ($code !== 200) {
            wp_send_json_error(['message' => "Server client trả về HTTP {$code}. Kiểm tra URL endpoint."]);
        }

        // Dùng array_key_exists thay isset để xử lý đúng khi installed = [] (mảng rỗng)
        if (!is_array($body) || !array_key_exists('installed', $body)) {
            $jsonErr = json_last_error_msg();
            $preview = mb_substr(strip_tags($rawBody), 0, 200);
            wp_send_json_error(['message' => "Response không hợp lệ. JSON error: {$jsonErr}. URL gọi: {$statusUrl}. Body: {$preview}"]);
        }

        // Cập nhật meta
        update_post_meta($postId, '_block_sync_versions', $body['installed']);

        wp_send_json_success(['installed' => $body['installed']]);
    }

    // =========================================================================
    // AJAX: Duyệt / Từ chối yêu cầu đồng bộ block từ site khách
    // =========================================================================

    public function ajaxApproveBlockRequest(): void
    {
        check_ajax_referer('laca_block_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Không có quyền'], 403);
        }

        $postId    = absint($_POST['post_id'] ?? 0);
        $blockName = sanitize_key($_POST['block'] ?? '');
        $force     = !empty($_POST['force']);

        if (!$postId || get_post_type($postId) !== 'project' || empty($blockName)) {
            wp_send_json_error(['message' => 'Dữ liệu không hợp lệ'], 400);
        }

        $result = $this->approveAndPushBlock($postId, $blockName, $force);

        if (!$result['success']) {
            wp_send_json_error([
                'message'  => $result['message'],
                'conflict' => !empty($result['conflict']),
            ]);
        }

        wp_send_json_success(['message' => $result['message'], 'version' => $result['version']]);
    }

    public function ajaxRejectBlockRequest(): void
    {
        check_ajax_referer('laca_block_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Không có quyền'], 403);
        }

        $postId    = absint($_POST['post_id'] ?? 0);
        $blockName = sanitize_key($_POST['block'] ?? '');
        $reason    = sanitize_text_field($_POST['reason'] ?? '');

        if (!$postId || get_post_type($postId) !== 'project' || empty($blockName)) {
            wp_send_json_error(['message' => 'Dữ liệu không hợp lệ'], 400);
        }

        $this->updateRequestStatus($postId, $blockName, 'rejected', $reason);

        wp_send_json_success(['message' => 'Đã từ chối yêu cầu.']);
    }

    /**
     * Quay lại phiên bản NGAY TRƯỚC ĐÓ của 1 block trên site khách, dùng
     * snapshot đã lưu trong _block_sync_history (xem recordChecksumAndHistory()).
     * Bấm rollback là hành động chủ động của admin nên không cần kiểm tra
     * xung đột — push thẳng bằng pushBlockFiles() có sẵn.
     */
    public function ajaxRollbackBlock(): void
    {
        check_ajax_referer('laca_block_sync', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Không có quyền'], 403);
        }

        $postId    = absint($_POST['post_id'] ?? 0);
        $blockName = sanitize_key($_POST['block'] ?? '');

        if (!$postId || get_post_type($postId) !== 'project' || empty($blockName)) {
            wp_send_json_error(['message' => 'Dữ liệu không hợp lệ'], 400);
        }

        $apiKey      = carbon_get_post_meta($postId, 'sync_api_key');
        $endpointUrl = carbon_get_post_meta($postId, 'sync_endpoint_url');

        if (empty($apiKey) || empty($endpointUrl)) {
            wp_send_json_error(['message' => 'Chưa cấu hình API Key hoặc Endpoint URL']);
        }

        $history = get_post_meta($postId, '_block_sync_history', true) ?: [];
        $history = is_array($history) ? $history : [];
        $entries = $history[$blockName] ?? [];

        if (count($entries) < 2) {
            wp_send_json_error(['message' => 'Không có phiên bản trước đó để quay lại.']);
        }

        // [0] = bản hiện tại vừa push, [1] = bản ngay trước đó — mục tiêu rollback
        $previous = $entries[1];

        $result = $this->pushBlockFiles($blockName, $previous['version'], $previous['files'], $apiKey, $endpointUrl);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']]);
        }

        $installedMeta             = get_post_meta($postId, '_block_sync_versions', true) ?: [];
        $installedMeta             = is_array($installedMeta) ? $installedMeta : [];
        $installedMeta[$blockName] = $previous['version'];
        update_post_meta($postId, '_block_sync_versions', $installedMeta);

        $this->recordChecksumAndHistory($postId, $blockName, $previous['version'], $previous['files']);

        if (class_exists(SiteHealthChecker::class)) {
            (new SiteHealthChecker())->checkAfterDeploy($postId, "rollback block \"{$blockName}\"");
        }

        wp_send_json_success([
            'message' => "Đã quay lại {$blockName} về phiên bản {$previous['version']}.",
            'version' => $previous['version'],
        ]);
    }

    /**
     * Site khách yêu cầu cập nhật 1 block ĐÃ cài — tự động duyệt, đẩy ngay
     * không cần admin thao tác (xem TrackerEndpointHandler::handleBlockSyncRequest()).
     *
     * Nếu phát hiện xung đột (khách đã tự sửa block trước đó) thì KHÔNG tự
     * ghi đè — không có admin nào đứng đó để quyết định — mà hạ trạng thái
     * request về lại 'pending' kèm lý do, để nó hiện lại trong danh sách chờ
     * duyệt thủ công, admin sẽ thấy cảnh báo xung đột khi bấm Duyệt & Đẩy.
     */
    public function handleAutoApprovedRequest(int $projectId, string $blockName): void
    {
        $result = $this->approveAndPushBlock($projectId, $blockName);

        if (!$result['success'] && !empty($result['conflict'])) {
            $this->updateRequestStatus(
                $projectId,
                $blockName,
                'pending',
                'Tự động phát hiện site khách đã tự sửa block này — cần admin duyệt thủ công và xác nhận ghi đè.'
            );
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * @return array<int,array{block_name:string,requested_at:string,status:string,reason:string}>
     */
    private function getPendingBlockRequests(int $postId): array
    {
        $pending = get_post_meta($postId, '_pending_block_sync_requests', true) ?: [];
        if (!is_array($pending)) {
            return [];
        }

        return array_values(array_filter(
            $pending,
            static fn($req) => in_array($req['status'] ?? '', ['pending', 'auto_approved'], true)
        ));
    }

    /**
     * Đổi trạng thái 1 request (khớp theo block_name, chỉ áp dụng cho request
     * đang pending/auto_approved — không đụng tới request đã quyết định trước đó).
     */
    private function updateRequestStatus(int $postId, string $blockName, string $status, string $reason): void
    {
        $pending = get_post_meta($postId, '_pending_block_sync_requests', true) ?: [];
        if (!is_array($pending)) {
            return;
        }

        foreach ($pending as &$req) {
            if (($req['block_name'] ?? '') === $blockName
                && in_array($req['status'] ?? '', ['pending', 'auto_approved'], true)
            ) {
                $req['status']     = $status;
                $req['reason']     = $reason;
                $req['decided_at'] = current_time('mysql');
            }
        }
        unset($req);

        update_post_meta($postId, '_pending_block_sync_requests', $pending);
    }

    /**
     * Lấy file block từ client.lacadev.com (qua BlockCatalogHub đã cấu hình
     * global — xem Laca Admin → 🗂️ Block Catalog Source), rồi đẩy xuống
     * đúng site khách của project này bằng pushBlockFiles() có sẵn. Dùng
     * chung cho cả duyệt thủ công (ajaxApproveBlockRequest) lẫn tự động
     * duyệt (handleAutoApprovedRequest).
     */
    private function approveAndPushBlock(int $postId, string $blockName, bool $force = false): array
    {
        $apiKey      = carbon_get_post_meta($postId, 'sync_api_key');
        $endpointUrl = carbon_get_post_meta($postId, 'sync_endpoint_url');

        if (empty($apiKey) || empty($endpointUrl)) {
            $msg = 'Chưa cấu hình API Key hoặc Endpoint URL cho project này (tab 🧩 Block Sync).';
            $this->updateRequestStatus($postId, $blockName, 'rejected', $msg);
            return ['success' => false, 'message' => $msg, 'version' => null];
        }

        $remote = $this->fetchBlockFilesFromCatalog($blockName);
        if ($remote === null) {
            return [
                'success' => false,
                'message' => 'Không lấy được block từ client.lacadev.com — kiểm tra cấu hình tại Laca Admin → 🗂️ Block Catalog Source.',
                'version' => null,
            ];
        }

        if (!$force && $this->detectConflict($postId, $blockName, $apiKey, $endpointUrl)) {
            return [
                'success'  => false,
                'conflict' => true,
                'message'  => "Site khách đã tự sửa đổi block \"{$blockName}\" kể từ lần đồng bộ trước — ghi đè sẽ làm mất các thay đổi đó.",
                'version'  => null,
            ];
        }

        $result = $this->pushBlockFiles($blockName, $remote['version'], $remote['files'], $apiKey, $endpointUrl);

        if ($result['success']) {
            $installedMeta             = get_post_meta($postId, '_block_sync_versions', true) ?: [];
            $installedMeta             = is_array($installedMeta) ? $installedMeta : [];
            $installedMeta[$blockName] = $result['version'];
            update_post_meta($postId, '_block_sync_versions', $installedMeta);

            $this->recordChecksumAndHistory($postId, $blockName, $result['version'], $remote['files']);
            $this->updateRequestStatus($postId, $blockName, 'approved', '');

            if (class_exists(SiteHealthChecker::class)) {
                (new SiteHealthChecker())->checkAfterDeploy($postId, "duyệt block \"{$blockName}\"");
            }
        }

        return $result;
    }

    /**
     * Gọi BlockCatalogHub (global, xem app/src/Settings/LacaTools/BlockCatalogHub.php)
     * để lấy toàn bộ file nguồn của 1 block từ client.lacadev.com.
     *
     * @return array{version:string,files:array<string,string>}|null
     */
    private function fetchBlockFilesFromCatalog(string $blockName): ?array
    {
        // Carbon Fields lưu option của simple field với tiền tố "_"
        $catalogUrl = trim((string) get_option('_laca_catalog_source_url', ''));
        $catalogKey = trim((string) get_option('_laca_catalog_source_key', ''));

        if (empty($catalogUrl) || empty($catalogKey)) {
            return null;
        }

        $filesUrl = rtrim($catalogUrl, '/') . '/' . rawurlencode($blockName) . '/files';

        $response = wp_remote_get($filesUrl, [
            'headers'   => [
                'X-Laca-Catalog-Key' => $catalogKey,
                'Accept'             => 'application/json',
            ],
            'timeout'   => 30,
            'sslverify' => !WP_DEBUG,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['success']) || empty($body['files']) || !is_array($body['files'])) {
            return null;
        }

        return [
            'version' => (string) ($body['version'] ?? '1.0.0'),
            'files'   => $body['files'],
        ];
    }

    /**
     * Phát hiện site khách đã tự sửa block này kể từ lần push gần nhất, TRƯỚC
     * KHI ghi đè — hỏi client checksum hiện tại của block (qua endpoint
     * status có sẵn, mở rộng thêm ?block=) rồi so với checksum đã lưu lúc
     * push thành công lần trước ($_block_sync_checksums). Không chặn nếu:
     * (a) block này chưa từng push (không có gì để so), hoặc (b) không hỏi
     * được client (lỗi mạng) — tránh chặn nhầm khi không chắc chắn có xung đột.
     */
    private function detectConflict(int $postId, string $blockName, string $apiKey, string $endpointUrl): bool
    {
        $storedChecksums = get_post_meta($postId, '_block_sync_checksums', true) ?: [];
        $storedChecksums = is_array($storedChecksums) ? $storedChecksums : [];
        $storedChecksum  = $storedChecksums[$blockName] ?? null;

        if ($storedChecksum === null) {
            return false;
        }

        $remoteChecksum = $this->getRemoteChecksum($blockName, $apiKey, $endpointUrl);
        if ($remoteChecksum === null) {
            return false;
        }

        return $remoteChecksum !== $storedChecksum;
    }

    private function getRemoteChecksum(string $blockName, string $apiKey, string $endpointUrl): ?string
    {
        $statusUrl = rtrim($endpointUrl, '/') . '/status?block=' . rawurlencode($blockName);

        $response = wp_remote_get($statusUrl, [
            'headers'   => [
                'X-Laca-Key' => $apiKey,
                'Accept'     => 'application/json',
            ],
            'timeout'   => 15,
            'sslverify' => !WP_DEBUG,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return (is_array($body) && !empty($body['checksum'])) ? (string) $body['checksum'] : null;
    }

    /**
     * Phải cùng thuật toán với BlockSyncReceiver::computeChecksum() phía
     * client (hash trên nội dung ĐÃ giải mã base64 của từng file, theo path
     * tương đối đã sắp xếp) để 2 bên so sánh trực tiếp được.
     *
     * @param array<string,string> $files
     */
    private function computeChecksumFromFiles(array $files): string
    {
        $hashes = [];
        foreach ($files as $relativePath => $base64Content) {
            $content                = base64_decode($base64Content, true);
            $hashes[$relativePath]  = $content !== false ? md5($content) : '';
        }
        ksort($hashes);

        return md5((string) wp_json_encode($hashes));
    }

    /**
     * Sau mỗi lần push thành công: chốt checksum mới (dùng để phát hiện xung
     * đột ở lần push kế tiếp) + chèn snapshot vào lịch sử (dùng cho Rollback),
     * giữ tối đa 3 bản gần nhất/block để tránh phình postmeta quá lớn.
     *
     * @param array<string,string> $files
     */
    private function recordChecksumAndHistory(int $postId, string $blockName, string $version, array $files): void
    {
        $checksums             = get_post_meta($postId, '_block_sync_checksums', true) ?: [];
        $checksums             = is_array($checksums) ? $checksums : [];
        $checksums[$blockName] = $this->computeChecksumFromFiles($files);
        update_post_meta($postId, '_block_sync_checksums', $checksums);

        $history                = get_post_meta($postId, '_block_sync_history', true) ?: [];
        $history                = is_array($history) ? $history : [];
        $history[$blockName]    = $history[$blockName] ?? [];
        array_unshift($history[$blockName], [
            'version'   => $version,
            'files'     => $files,
            'pushed_at' => current_time('mysql'),
        ]);
        $history[$blockName] = array_slice($history[$blockName], 0, 3);
        update_post_meta($postId, '_block_sync_history', $history);
    }

    private function pushSingleBlock(int $postId, string $blockName, string $apiKey, string $endpointUrl): array
    {
        $blockDir = $this->resolveBlockDir() . "/{$blockName}";

        if (!is_dir($blockDir)) {
            return [
                'success' => false,
                'message' => "Thư mục block không tồn tại: {$blockName}",
                'version' => null,
            ];
        }

        // Đọc version từ block.json
        $blockJsonPath = "{$blockDir}/block.json";
        $version       = '1.0.0';
        if (file_exists($blockJsonPath)) {
            $blockJson = json_decode(file_get_contents($blockJsonPath), true);
            $version   = $blockJson['version'] ?? '1.0.0';
        }

        // Encode tất cả files
        $files = [];
        $this->encodeDirectoryFiles($blockDir, $blockDir, $files);

        if (empty($files)) {
            return [
                'success' => false,
                'message' => "Không tìm thấy files trong block: {$blockName}",
                'version' => null,
            ];
        }

        $result = $this->pushBlockFiles($blockName, $version, $files, $apiKey, $endpointUrl);

        // Push thủ công (chọn tay/Sync Outdated) do chính admin chủ động bấm —
        // không cần kiểm tra xung đột như nhánh yêu cầu từ khách, nhưng vẫn
        // ghi lại checksum + lịch sử để nút Rollback hoạt động nhất quán cho
        // MỌI đường push, không riêng gì đường duyệt yêu cầu khách hàng.
        if ($result['success']) {
            $this->recordChecksumAndHistory($postId, $blockName, $version, $files);
        }

        return $result;
    }

    /**
     * Gửi $files (đã encode base64 sẵn) tới client endpoint — retry với
     * exponential backoff (max 3 lần). Tách riêng khỏi pushSingleBlock() để
     * dùng chung được cho cả block đọc từ local disk (hub) lẫn block lấy từ
     * xa qua BlockCatalogHub (client.lacadev.com) — xem approveAndPushBlock().
     *
     * @param array<string,string> $files
     */
    private function pushBlockFiles(string $blockName, string $version, array $files, string $apiKey, string $endpointUrl): array
    {
        // POST đến client endpoint — retry với exponential backoff (max 3 lần)
        $payload = json_encode([
            'block_name' => $blockName,
            'version'    => $version,
            'files'      => $files,
        ]);

        $response    = null;
        $lastError   = '';
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($attempt > 1) {
                // Exponential backoff: 1s, 2s (blocking — admin context, short delays ok)
                sleep(min(2 ** ($attempt - 2), 4));
            }

            $response = wp_remote_post($endpointUrl, [
                'headers' => [
                    'X-Laca-Key'   => $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'body'    => $payload,
                'timeout' => 30,
                'sslverify' => !WP_DEBUG,
            ]);

            if (!is_wp_error($response)) {
                $code = wp_remote_retrieve_response_code($response);
                // Retry on server errors (5xx) but not client errors (4xx)
                if ($code < 500) {
                    break;
                }
                $lastError = "HTTP {$code} (attempt {$attempt})";
            } else {
                $lastError = $response->get_error_message() . " (attempt {$attempt})";
                $response  = null;
            }
        }

        if ($response === null || is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Lỗi kết nối sau ' . $maxAttempts . ' lần thử: ' . $lastError,
                'version' => null,
            ];
        }

        $code    = wp_remote_retrieve_response_code($response);
        $rawBody = wp_remote_retrieve_body($response);

        // Strip UTF-8 BOM (\xEF\xBB\xBF) và whitespace trước/sau JSON.
        // BOM vô hình nhưng làm json_decode() trả về null.
        $cleanBody = preg_replace('/^\xEF\xBB\xBF/', '', $rawBody);
        $body      = json_decode(trim($cleanBody), true);


        if ($code === 401) {
            return [
                'success' => false,
                'message' => 'API Key không hợp lệ (401)',
                'version' => null,
            ];
        }

        if ($code !== 200 || empty($body['success'])) {
            $msg = $body['message'] ?? ('HTTP ' . $code . ' | body: ' . substr($rawBody, 0, 120));
            return [
                'success' => false,
                'message' => "Client từ chối: {$msg}",
                'version' => null,
            ];
        }

        return [
            'success' => true,
            'message' => $body['message'] ?? 'Thành công',
            'version' => $version,
        ];
    }

    /**
     * Đệ quy đọc tất cả files trong thư mục, encode base64.
     * $files sẽ được populate dạng ['relative/path' => 'base64_content'].
     */
    private function encodeDirectoryFiles(string $baseDir, string $currentDir, array &$files): void
    {
        $items = scandir($currentDir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            // Bỏ qua các file dev không cần thiết
            if (in_array($item, ['node_modules', '.git', '.DS_Store'], true)) {
                continue;
            }

            $path         = "{$currentDir}/{$item}";
            $relativePath = ltrim(str_replace($baseDir, '', $path), '/');

            if (is_dir($path)) {
                $this->encodeDirectoryFiles($baseDir, $path, $files);
            } elseif (is_file($path)) {
                // Bỏ map files (debug only, to nặng)
                if (str_ends_with($item, '.map')) {
                    continue;
                }
                $files[$relativePath] = base64_encode(file_get_contents($path));
            }
        }
    }

    /**
     * Lấy danh sách tất cả blocks có block.json trong block-gutenberg/.
     */
    /**
     * Resolve đường dẫn filesystem đến thư mục block-gutenberg.
     *
     * get_template_directory() đôi khi trỏ tới subfolder '/theme' trên một số hosting.
     * Fallback: leo lên dirname() nếu không tìm thấy block-gutenberg ngay bên dưới.
     */
    private function resolveBlockDir(): string
    {
        // Ưu tiên 1: block-gutenberg nằm ngay trong template directory
        $candidate = get_template_directory() . '/block-gutenberg';
        if (is_dir($candidate)) {
            return $candidate;
        }

        // Ưu tiên 2: template directory là subfolder (ví dụ: /lacadev/theme),
        // thực tế block-gutenberg nằm cạnh nó tại /lacadev/block-gutenberg
        $parentCandidate = dirname(get_template_directory()) . '/block-gutenberg';
        if (is_dir($parentCandidate)) {
            return $parentCandidate;
        }

        // Fallback cuối: trả về candidate gốc (metabox sẽ hiển thị message debug)
        return $candidate;
    }

    private function getAvailableBlocks(): array
    {
        $blocks   = [];
        $blockDir = $this->resolveBlockDir();

        $files = glob("{$blockDir}/*/block.json");
        if (empty($files)) {
            return $blocks;
        }

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }
            $blocks[] = [
                'name'    => basename(dirname($file)),
                'title'   => $data['title'] ?? basename(dirname($file)),
                'version' => $data['version'] ?? '1.0.0',
            ];
        }

        usort($blocks, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $blocks;
    }

    private function getVersionBadge(string $localVersion, ?string $clientVersion): string
    {
        if ($clientVersion === null) {
            return '<span style="
                background:#6c757d22; color:#6c757d;
                padding:2px 8px; border-radius:12px;
                font-size:11px; font-weight:600;
            ">🆕 Chưa có</span>';
        }

        if (version_compare($localVersion, $clientVersion, '>')) {
            return '<span style="
                background:#fd7e1422; color:#fd7e14;
                padding:2px 8px; border-radius:12px;
                font-size:11px; font-weight:600;
            ">⚠️ Cũ hơn</span>';
        }

        return '<span style="
            background:#19875422; color:#198754;
            padding:2px 8px; border-radius:12px;
            font-size:11px; font-weight:600;
        ">✅ Đồng bộ</span>';
    }

    // =========================================================================
    // INLINE JS
    // =========================================================================

    private function getBlockSyncInlineScript(): string
    {
        return <<<'JS'
document.addEventListener('DOMContentLoaded', function () {

    // Select All checkbox
    const selectAll = document.getElementById('laca-select-all-blocks');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.laca-block-checkbox')
                .forEach(cb => cb.checked = this.checked);
        });
    }

    // --- Helper: collect selected blocks ---
    function getSelectedBlocks() {
        return [...document.querySelectorAll('.laca-block-checkbox:checked')]
            .map(cb => cb.value);
    }

    // --- Helper: collect outdated blocks ---
    function getOutdatedBlocks() {
        return [...document.querySelectorAll('#laca-blocks-table-body tr')]
            .filter(tr => {
                const local  = tr.dataset.localVer;
                const client = tr.dataset.clientVer;
                if (!client) return true; // Chưa có = cần push
                return local !== client;  // Khác version = cần update
            })
            .map(tr => tr.dataset.block);
    }

    // --- Push Flow ---
    async function runPush(blocks) {
        if (!blocks.length) {
            Swal.fire({ icon: 'warning', title: 'Chưa chọn block nào', timer: 2000, showConfirmButton: false });
            return;
        }

        const postId = document.getElementById('laca-push-blocks-btn').dataset.postId;
        const nonce  = document.getElementById('laca-push-blocks-btn').dataset.nonce;

        // Hiện progress
        Swal.fire({
            title: '📦 Đang đẩy blocks...',
            html:  `<p id="swal-progress-text">Chuẩn bị...</p>
                    <ul id="swal-results" style="text-align:left;list-style:none;padding:0;max-height:200px;overflow-y:auto"></ul>`,
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading(),
        });

        const fd = new FormData();
        fd.append('action',  'laca_push_blocks');
        fd.append('nonce',   nonce);
        fd.append('post_id', postId);
        blocks.forEach(b => fd.append('blocks[]', b));

        document.getElementById('swal-progress-text').textContent = `Đang push ${blocks.length} block(s)...`;

        try {
            const res  = await fetch(ajaxurl, { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.success) {
                Swal.fire({ icon: 'error', title: 'Lỗi', text: data.data?.message || 'Có lỗi xảy ra' });
                return;
            }

            const results = data.data.results;
            const list    = document.getElementById('swal-results');
            let successCount = 0, failCount = 0;

            for (const [blockName, result] of Object.entries(results)) {
                const li = document.createElement('li');
                li.style.cssText = 'padding:4px 0; border-bottom:1px solid #f0f0f0';
                if (result.success) {
                    successCount++;
                    li.innerHTML = `✅ <strong>${blockName}</strong> → ${result.version}`;
                    // Cập nhật bảng
                    const row = document.querySelector(`tr[data-block="${blockName}"]`);
                    if (row) {
                        row.dataset.clientVer = result.version;
                        row.querySelector('.laca-client-ver').innerHTML = `<code>${result.version}</code>`;
                        row.querySelector('.laca-status-badge').innerHTML =
                            `<span style="background:#19875422;color:#198754;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">✅ Đồng bộ</span>`;
                    }
                } else {
                    failCount++;
                    li.innerHTML = `❌ <strong>${blockName}</strong>: ${result.message}`;
                }
                list.prepend(li);
            }

            Swal.fire({
                icon:  failCount === 0 ? 'success' : (successCount > 0 ? 'warning' : 'error'),
                title: failCount === 0 ? '✅ Hoàn tất!' : `⚠️ ${successCount} thành công, ${failCount} lỗi`,
                html:  list.outerHTML,
                confirmButtonText: 'Đóng',
            });

        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Lỗi mạng', text: e.message });
        }
    }

    // --- Push Selected ---
    document.getElementById('laca-push-blocks-btn')?.addEventListener('click', function () {
        runPush(getSelectedBlocks());
    });

    // --- Sync Outdated ---
    document.getElementById('laca-sync-outdated-btn')?.addEventListener('click', function () {
        const outdated = getOutdatedBlocks();
        if (!outdated.length) {
            Swal.fire({ icon: 'success', title: 'Tất cả đã đồng bộ!', timer: 2000, showConfirmButton: false });
            return;
        }
        Swal.fire({
            icon: 'question',
            title: `Sync ${outdated.length} block(s) lỗi thời?`,
            text: outdated.join(', '),
            showCancelButton: true,
            confirmButtonText: 'Sync ngay',
        }).then(r => { if (r.isConfirmed) runPush(outdated); });
    });

    // --- Refresh Status ---
    document.getElementById('laca-refresh-status-btn')?.addEventListener('click', async function () {
        const postId = this.dataset.postId;
        const nonce  = this.dataset.nonce;
        const statusEl = document.getElementById('laca-sync-status-text');

        statusEl.textContent = '⏳ Đang tải...';
        this.disabled = true;

        const fd = new FormData();
        fd.append('action',  'laca_fetch_client_blocks');
        fd.append('nonce',   nonce);
        fd.append('post_id', postId);

        try {
            const res  = await fetch(ajaxurl, { method: 'POST', body: fd });
            const data = await res.json();

            if (!data.success) {
                statusEl.textContent = '❌ ' + (data.data?.message || 'Lỗi');
                return;
            }

            const installed = data.data.installed;
            document.querySelectorAll('#laca-blocks-table-body tr').forEach(row => {
                const block   = row.dataset.block;
                const version = installed[block] || null;
                row.dataset.clientVer = version || '';

                const localVer    = row.dataset.localVer;
                const clientVerEl = row.querySelector('.laca-client-ver');
                const badgeEl     = row.querySelector('.laca-status-badge');

                clientVerEl.innerHTML = version ? `<code>${version}</code>` : '<span style="color:#999">—</span>';

                if (!version) {
                    badgeEl.innerHTML = `<span style="background:#6c757d22;color:#6c757d;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">🆕 Chưa có</span>`;
                } else if (localVer !== version) {
                    badgeEl.innerHTML = `<span style="background:#fd7e1422;color:#fd7e14;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">⚠️ Cũ hơn</span>`;
                } else {
                    badgeEl.innerHTML = `<span style="background:#19875422;color:#198754;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600">✅ Đồng bộ</span>`;
                }
            });

            statusEl.textContent = `✅ Cập nhật ${new Date().toLocaleTimeString('vi-VN')}`;
        } catch (e) {
            statusEl.textContent = '❌ Lỗi: ' + e.message;
        } finally {
            this.disabled = false;
        }
    });

    // --- Duyệt / Từ chối yêu cầu đồng bộ block từ site khách ---
    document.querySelectorAll('.laca-approve-block-request-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const row      = this.closest('.laca-block-request-row');
            const block    = row.dataset.block;
            const postId   = row.dataset.postId;
            const nonce    = row.dataset.nonce;
            const isUpdate = row.dataset.isUpdate === '1';

            const confirmResult = await Swal.fire({
                icon: 'question',
                title: `Duyệt &amp; đẩy "${block}"?`,
                html: isUpdate
                    ? 'Block này <strong>đã được cài</strong> ở site khách — duyệt sẽ <strong>ghi đè</strong> bản đang chạy bằng bản mới nhất từ client.lacadev.com.'
                    : 'Block sẽ được đẩy xuống site khách ngay sau khi duyệt.',
                showCancelButton: true,
                confirmButtonText: 'Duyệt & Đẩy',
            });
            if (!confirmResult.isConfirmed) return;

            async function doApprove(force) {
                Swal.fire({ title: '📦 Đang đẩy...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });

                const fd = new FormData();
                fd.append('action', 'laca_approve_block_request');
                fd.append('nonce', nonce);
                fd.append('post_id', postId);
                fd.append('block', block);
                if (force) fd.append('force', '1');

                try {
                    const res  = await fetch(ajaxurl, { method: 'POST', body: fd });
                    const data = await res.json();

                    if (!data.success) {
                        if (data.data?.conflict) {
                            const retry = await Swal.fire({
                                icon: 'warning',
                                title: '⚠️ Site khách đã tự sửa block này!',
                                text: (data.data.message || '') + ' Bạn có chắc muốn GHI ĐÈ, mất hết các thay đổi đó của khách?',
                                showCancelButton: true,
                                confirmButtonText: 'Vẫn ghi đè',
                                confirmButtonColor: '#dc3545',
                            });
                            if (retry.isConfirmed) await doApprove(true);
                            return;
                        }
                        Swal.fire({ icon: 'error', title: 'Lỗi', text: data.data?.message || 'Có lỗi xảy ra' });
                        return;
                    }

                    await Swal.fire({ icon: 'success', title: '✅ Đã đẩy thành công!', text: data.data?.message || '', timer: 2200, showConfirmButton: false });
                    location.reload();
                } catch (e) {
                    Swal.fire({ icon: 'error', title: 'Lỗi mạng', text: e.message });
                }
            }

            await doApprove(false);
        });
    });

    // --- Rollback về phiên bản trước đó ---
    document.querySelectorAll('.laca-rollback-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const block  = this.dataset.block;
            const postId = this.dataset.postId;
            const nonce  = this.dataset.nonce;

            const confirmResult = await Swal.fire({
                icon: 'warning',
                title: `Quay lại "${block}" về phiên bản trước?`,
                text: 'Site khách sẽ được ghi đè bằng phiên bản CŨ HƠN.',
                showCancelButton: true,
                confirmButtonText: 'Quay lại',
            });
            if (!confirmResult.isConfirmed) return;

            Swal.fire({ title: '⏪ Đang quay lại...', allowOutsideClick: false, showConfirmButton: false, didOpen: () => Swal.showLoading() });

            const fd = new FormData();
            fd.append('action', 'laca_rollback_block');
            fd.append('nonce', nonce);
            fd.append('post_id', postId);
            fd.append('block', block);

            try {
                const res  = await fetch(ajaxurl, { method: 'POST', body: fd });
                const data = await res.json();

                if (!data.success) {
                    Swal.fire({ icon: 'error', title: 'Lỗi', text: data.data?.message || 'Có lỗi xảy ra' });
                    return;
                }

                await Swal.fire({ icon: 'success', title: '✅ Đã quay lại!', text: data.data?.message || '', timer: 2200, showConfirmButton: false });
                location.reload();
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Lỗi mạng', text: e.message });
            }
        });
    });

    document.querySelectorAll('.laca-reject-block-request-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const row    = this.closest('.laca-block-request-row');
            const block  = row.dataset.block;
            const postId = row.dataset.postId;
            const nonce  = row.dataset.nonce;

            const { value: reason, isConfirmed } = await Swal.fire({
                icon: 'warning',
                title: `Từ chối "${block}"?`,
                input: 'text',
                inputLabel: 'Lý do (sẽ hiện lại cho site khách, có thể để trống)',
                inputPlaceholder: 'VD: Block chưa phù hợp với site này',
                showCancelButton: true,
                confirmButtonText: 'Từ chối',
            });
            if (!isConfirmed) return;

            const fd = new FormData();
            fd.append('action', 'laca_reject_block_request');
            fd.append('nonce', nonce);
            fd.append('post_id', postId);
            fd.append('block', block);
            fd.append('reason', reason || '');

            try {
                const res  = await fetch(ajaxurl, { method: 'POST', body: fd });
                const data = await res.json();

                if (!data.success) {
                    Swal.fire({ icon: 'error', title: 'Lỗi', text: data.data?.message || 'Có lỗi xảy ra' });
                    return;
                }

                await Swal.fire({ icon: 'success', title: 'Đã từ chối', timer: 1600, showConfirmButton: false });
                location.reload();
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Lỗi mạng', text: e.message });
            }
        });
    });
});
JS;
    }
}
