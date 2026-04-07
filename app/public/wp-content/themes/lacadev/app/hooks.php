<?php
/**
 * Declare all your actions and filters here.
 *
 * @package WPEmergeTheme
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ------------------------------------------------------------------------
 * WordPress
 * ------------------------------------------------------------------------
 */

/**
 * Assets
 */
add_action('wp_enqueue_scripts', 'app_action_theme_enqueue_assets');
add_action('admin_enqueue_scripts', 'app_action_admin_enqueue_assets');
add_action('login_enqueue_scripts', 'app_action_login_enqueue_assets');
add_action('enqueue_block_editor_assets', 'app_action_editor_enqueue_assets');
add_action('wp_head', 'app_action_add_favicon', 5);
add_action('login_head', 'app_action_add_favicon', 5);
add_action('admin_head', 'app_action_add_favicon', 5);
add_filter('upload_dir', 'app_filter_fix_upload_dir_url_schema');

/**
 * Content
 */
add_filter('excerpt_more', 'app_filter_excerpt_more');
add_filter('excerpt_length', 'app_filter_excerpt_length', 999);
add_filter('the_content', 'app_filter_fix_shortcode_empty_paragraphs');

// Attach all suitable hooks from `the_content` on `app_content`.
add_filter('app_content', 'do_shortcode', 9);
add_filter('app_content', 'app_filter_fix_shortcode_empty_paragraphs', 10);
add_filter('app_content', 'wptexturize', 10);
add_filter('app_content', 'wpautop', 10);
add_filter('app_content', 'shortcode_unautop', 10);
add_filter('app_content', 'prepend_attachment', 10);
add_filter('app_content', 'wp_make_content_images_responsive', 10);
add_filter('app_content', 'convert_smilies', 20);

/**
 * Login
 */
add_filter('login_headerurl', 'app_filter_login_headerurl');
if (version_compare(get_bloginfo('version'), '5.2', '<')) {
    add_filter('login_headertext', 'app_filter_login_headertext');
}
add_filter('login_headertext', 'app_filter_login_headertext');
add_filter('login_message', 'app_login_google_admin_message');

/**
 * ------------------------------------------------------------------------
 * External Libraries and Plugins.
 * ------------------------------------------------------------------------
 */

/**
 * Carbon Fields
 */
// add_action( 'after_setup_theme', 'app_bootstrap_carbon_fields', 100 );
add_action('carbon_fields_register_fields', 'app_bootstrap_carbon_fields_register_fields');

/**
 * Pages/Posts list table: Add Thumbnail column
 */
function app_add_featured_image_column($cols) {
    if (is_array($cols)) {
        $cols = insertArrayAtPosition($cols, ['featured_image' => 'Image'], 1);
    }
    return $cols;
}
add_filter('manage_page_posts_columns', 'app_add_featured_image_column', 9999);
add_filter('manage_post_posts_columns', 'app_add_featured_image_column', 9999);

function app_render_featured_image_column($column, $postId) {
    if ($column !== 'featured_image') {
        return;
    }
    
    // Generate nonce for CSRF protection
    $nonce = wp_create_nonce('update_post_thumbnail');
    $nonce_attr = esc_attr($nonce);
    $post_id_attr = absint($postId);
    
    $thumbnailUrl = get_the_post_thumbnail_url($postId, 'thumbnail');
    
    if ($thumbnailUrl) {
        // Has thumbnail - show image with remove button (same as Service)
        echo "<div class='thumbnail-wrap'>";
        echo "<a href='javascript:void(0)' data-trigger-change-thumbnail-id data-post-id='{$post_id_attr}' data-nonce='{$nonce_attr}'>";
        echo "<img src='" . esc_url($thumbnailUrl) . "' class='thumbnail-preview' alt='Thumbnail'/>";
        echo "</a>";
        // Remove button (X)
        echo "<a class='remove-thumbnail' href='javascript:void(0)' data-trigger-remove-thumbnail data-post-id='{$post_id_attr}' data-nonce='{$nonce_attr}' title='Remove thumbnail'>
                <svg viewBox='0 0 12 12'>
                    <path d='M11 1L1 11M1 1l10 10' stroke='currentColor' stroke-width='2' stroke-linecap='round'/>
                </svg>
            </a>";
        echo "</div>";
    } else {
        // No thumbnail - show WordPress-style "Set featured image" link (same as Service)
        echo "<a href='javascript:void(0)' data-trigger-change-thumbnail-id data-post-id='{$post_id_attr}' data-nonce='{$nonce_attr}'>";
        echo "<div class='no-image-text'>Choose image</div>";
        echo "</a>";
    }
}
add_action('manage_page_posts_custom_column', 'app_render_featured_image_column', 10, 2);
add_action('manage_post_posts_custom_column', 'app_render_featured_image_column', 10, 2);

/**
 * ============================================================================
 * PROJECT MANAGER — DB TABLES & AUTO-GENERATE TRACKER KEY
 * ============================================================================
 */

/**
 * Tạo custom DB tables khi activate theme lần đầu
 * Cũng chạy trên `init` để upgrade version nếu cần
 */
add_action('after_switch_theme', 'laca_install_project_manager_tables');
add_action('init', 'laca_install_project_manager_tables', 5);

function laca_install_project_manager_tables(): void
{
    \App\Databases\ProjectLogTable::install();
    \App\Databases\ProjectAlertTable::install();
    \App\Databases\ContactFormTable::install();
}

/**
 * ============================================================================
 * CONTACT FORM — Frontend AJAX + Shortcode
 * ============================================================================
 */
add_action('init', function () {
    if (class_exists('\App\Features\ContactForm\ContactFormAjaxHandler')) {
        (new \App\Features\ContactForm\ContactFormAjaxHandler())->init();
    }
}, 10);

/**
 * Khởi tạo Handler xử lý Notifications & Cron (Project Manager)
 * và Export PDF Quote
 */
add_action('init', function() {
    if (class_exists('\App\Settings\LacaTools\ProjectNotificationHandler')) {
        (new \App\Settings\LacaTools\ProjectNotificationHandler())->init();
    }
    
    if (class_exists('\App\Settings\LacaTools\ProjectPdfExporter')) {
        (new \App\Settings\LacaTools\ProjectPdfExporter())->init();
    }
    
    if (class_exists('\App\Settings\LacaTools\TrackerEndpointHandler')) {
        (new \App\Settings\LacaTools\TrackerEndpointHandler())->init();
    }

    if (class_exists('\App\Settings\LacaTools\ClientPortalEndpoint')) {
        (new \App\Settings\LacaTools\ClientPortalEndpoint())->init();
    }

    if (class_exists('\App\Settings\LacaTools\ProjectTrackerGenerator')) {
        (new \App\Settings\LacaTools\ProjectTrackerGenerator())->init();
    }
});

/**
 * Auto-generate Tracker Secret Key khi tạo/lưu project lần đầu
 * Hook: save_post_project — chỉ chạy cho post_type=project
 */
add_action('save_post_project', 'laca_auto_generate_tracker_key', 20, 2);

function laca_auto_generate_tracker_key(int $postId, \WP_Post $post): void
{
    // Bỏ qua autosave, revision, trash
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if ($post->post_status === 'trash' || $post->post_status === 'auto-draft') {
        return;
    }
    if (!current_user_can('edit_post', $postId)) {
        return;
    }

    // Chỉ tạo mới nếu chưa có key
    $existingKey = get_post_meta($postId, '_tracker_secret_key', true);
    if (empty($existingKey)) {
        $secretKey = bin2hex(random_bytes(32)); // 64-char hex, cryptographically secure
        update_post_meta($postId, '_tracker_secret_key', $secretKey);
    }

    // Cập nhật tracker endpoint URL (readonly, hiển thị cho dev copy)
    $endpoint = rest_url('laca/v1/projects/' . $postId . '/log');
    update_post_meta($postId, '_tracker_endpoint', esc_url_raw($endpoint));
}

/**
 * Enqueue nonce cho AJAX của Project Manager (admin only)
 */
add_action('admin_enqueue_scripts', 'laca_project_manager_admin_scripts');

function laca_project_manager_admin_scripts(string $hook): void
{
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'project') {
        return;
    }

    // Inline script cung cấp nonce cho AJAX
    $data = [
        'ajaxurl'   => admin_url('admin-ajax.php'),
        'nonce'     => wp_create_nonce('laca_project_manager'),
        'post_id'   => (int) (get_the_ID() ?: ($_GET['post'] ?? 0)),
        'i18n'      => [
            'confirm_delete' => __('Bạn có chắc muốn xoá?', 'laca'),
            'resolving'      => __('Đang xử lý...', 'laca'),
            'deleted'        => __('Đã xoá', 'laca'),
            'resolved'       => __('Đã đánh dấu xử lý', 'laca'),
        ],
    ];
    wp_add_inline_script(
        'jquery',
        'var lacaProjectManager = ' . wp_json_encode($data) . ';',
        'before'
    );
}

/**
 * Thêm CSS cho admin list view của project
 */
add_action('admin_head', 'laca_project_list_admin_styles');

function laca_project_list_admin_styles(): void
{
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'project') {
        return;
    }
    ?>
    <style>
        .column-laca_status  { width: 120px; }
        .column-laca_expiry  { width: 160px; }
        .column-laca_alerts  { width: 80px; text-align: center; }
        .column-laca_client  { width: 150px; }
        .column-laca_domain  { width: 150px; }
        .wp-list-table .column-laca_expiry { font-size: 12px; line-height: 1.4; }
    </style>
    <?php
}
