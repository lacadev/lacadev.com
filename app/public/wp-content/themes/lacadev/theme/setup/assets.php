<?php
/**
 * Asset helpers.
 *
 * @package WPEmergeTheme
 */

use WPEmergeTheme\Facades\Theme;
use WPEmergeTheme\Facades\Assets;

/**
 * Enhanced asset loading with performance optimizations
 */
function app_action_theme_enqueue_assets()
{
    $template_dir = Theme::uri();
    $version = wp_get_theme()->get('Version');

    /**
     * Enqueue the built-in comment-reply script for singular pages.
     */
    if (is_singular()) {
        wp_enqueue_script('comment-reply');
    }

    /**
     * Critical JS (inline or very small) - load in head for critical functionality
     */
    if (file_exists(get_template_directory() . '/dist/critical.js')) {
        wp_enqueue_script('theme-critical-js', $template_dir . '/dist/critical.js', [], $version, false);
    }

    /**
     * Vendors bundle (contains all node_modules dependencies)
     * Only exists in production build (yarn build), not in dev mode (yarn dev)
     * 
     * NOTE: dist/ is in the theme root directory (parent of /theme/ subdirectory)
     * So we need to go up one level from get_template_directory()
     */
    $vendors_deps = [];
    $theme_root = dirname(get_template_directory());  // Go up one level to theme root
    $vendors_path = $theme_root . '/dist/vendors.js';
    
    // Build URL manually: get base theme URI and go up one level, then add /dist/vendors.js
    // This avoids the /theme/ subdirectory issue
    $base_uri = get_template_directory_uri();  // e.g. http://lacadev.local/wp-content/themes/lacadev/theme
    $theme_uri = dirname($base_uri);           // e.g. http://lacadev.local/wp-content/themes/lacadev
    $vendors_url = $theme_uri . '/dist/vendors.js';
    
    if (file_exists($vendors_path)) {
        wp_enqueue_script('theme-vendors-js', $vendors_url, [], $version, true);
        $vendors_deps = ['theme-vendors-js'];
    }

    /**
     * Main JavaScript bundle (deferred)
     */
    Assets::enqueueScript('theme-js-bundle', $template_dir . '/dist/theme.js', [], true);

    /**
     * Conditional assets based on page type
     */
    if (is_home() || is_archive() || is_search()) {
        if (file_exists(get_template_directory() . '/dist/archive.js')) {
            wp_enqueue_script('theme-archive-js', $template_dir . '/dist/archive.js', ['theme-js-bundle'], $version, true);
        }
    }

    if (is_single() && comments_open()) {
        if (file_exists(get_template_directory() . '/dist/comments.js')) {
            wp_enqueue_script('theme-comments-js', $template_dir . '/dist/comments.js', ['theme-js-bundle'], $version, true);
        }
    }

    /**
     * Enqueue styles with preload optimization
     */
    Assets::enqueueStyle('theme-css-bundle', $template_dir . '/dist/styles/theme.css');

    /**
     * Conditional CSS based on page type
     */
    if (is_single()) {
        if (file_exists(get_template_directory() . '/dist/styles/single.css')) {
            wp_enqueue_style('theme-single-css', $template_dir . '/dist/styles/single.css', ['theme-css-bundle'], $version);
        }
    }

    /**
     * Enqueue theme's style.css file to allow overrides for the bundled styles.
     */
    Assets::enqueueStyle('theme-styles', get_template_directory_uri() . '/style.css');

    /**
     * Localize script with minimal data
     */
    wp_localize_script('theme-js-bundle', 'themeData', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('theme_nonce'),
        'isHome' => is_home(),
        'isMobile' => wp_is_mobile(),
        'currentUrl' => get_permalink(),
    ]);
}

/**
 * Enqueue admin assets.
 *
 * @return void
 */
function app_action_admin_enqueue_assets()
{
    $template_dir = Theme::uri();

    /**
     * Enqueue styles.
     */
    Assets::enqueueStyle(
        'theme-admin-css-bundle',
        $template_dir . '/dist/styles/admin.css'
    );
    Assets::enqueueStyle(
        'theme-editor-css-bundle',
        $template_dir . '/dist/styles/editor.css'
    );

    /**
     * Enqueue vendors.js if exists (same fix as frontend)
     * CRITICAL: Load in head (false) to ensure it's available before admin.js
     */
    $admin_deps = [];
    $theme_root = dirname(get_template_directory());
    $vendors_path = $theme_root . '/dist/vendors.js';
    
    if (file_exists($vendors_path)) {
        $base_uri = get_template_directory_uri();
        $theme_uri = dirname($base_uri);
        $vendors_url = $theme_uri . '/dist/vendors.js';
        
        // Load in <head> without defer to ensure Swal is available
        wp_enqueue_script('theme-vendors-js', $vendors_url, [], wp_get_theme()->get('Version'), false);
        $admin_deps = ['theme-vendors-js'];
    }

    /**
     * Enqueue scripts.
     */
    Assets::enqueueScript(
        'theme-admin-js-bundle',
        $template_dir . '/dist/admin.js',
        $admin_deps,
        true
    );

    /**
     * Localize admin script data with nonce for AJAX requests and i18n strings
     */
    wp_localize_script('theme-admin-js-bundle', 'ajaxurl_params', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('update_post_thumbnail'),  // Must match backend check_ajax_referer
    ]);

    /**
     * Localize i18n strings for admin JavaScript
     */
    wp_localize_script('theme-admin-js-bundle', 'adminI18n', [
        // Thumbnail removal
        'removeThumbnailTitle' => __('Remove Thumbnail?', 'lacadev'),
        'removeThumbnailText' => __('Are you sure you want to remove this featured image?', 'lacadev'),
        'removeThumbnailConfirm' => __('Yes, remove it', 'lacadev'),
        'removeThumbnailCancel' => __('Cancel', 'lacadev'),
        'removedTitle' => __('Removed!', 'lacadev'),
        'removedText' => __('Featured image has been removed.', 'lacadev'),
        'errorTitle' => __('Error!', 'lacadev'),
        'failedRemove' => __('Failed to remove thumbnail.', 'lacadev'),
        
        // UI labels
        'chooseImage' => __('Choose image', 'lacadev'),
        'setFeaturedImage' => __('Set featured image', 'lacadev'),
    ]);

    // Enqueue front-end styles in admin area
    //  Assets::enqueueStyle('theme-css-bundle', $template_dir . '/dist/styles/theme.css');
}

/**
 * Preload critical assets in admin_head
 */
add_action('admin_head', function() {
    $template_dir = get_template_directory_uri();
    
    // Preload important fonts
    $fonts = [
        'dist/fonts/BeVietnamPro-Regular.bbe77399f9.ttf',
        'dist/fonts/BeVietnamPro-SemiBold.fbc3f74acb.ttf',
        'dist/fonts/Quicksand-Regular.61504eaec8.ttf',
    ];

    foreach ($fonts as $font) {
        echo '<link rel="preload" href="' . $template_dir . '/' . $font . '" as="font" type="font/ttf" crossorigin>' . "\n";
    }
}, 1);

/**
 * Enqueue login assets.
 *
 * @return void
 */
function app_action_login_enqueue_assets()
{
    $template_dir = Theme::uri();

    /**
     * Enqueue scripts.
     */
    Assets::enqueueScript(
        'theme-login-js-bundle',
        $template_dir . '/dist/login.js',
        [],
        true
    );

    /**
     * Enqueue styles.
     */
    Assets::enqueueStyle(
        'theme-login-css-bundle',
        $template_dir . '/dist/styles/login.css'
    );
}

/**
 * Enqueue editor assets.
 *
 * @return void
 */
function app_action_editor_enqueue_assets()
{
    $template_dir = Theme::uri();

    /**
     * Enqueue scripts.
     */
    Assets::enqueueScript(
        'theme-editor-js-bundle',
        $template_dir . '/dist/editor.js',
        [],
        true
    );

    /**
    * Enqueue styles.
    */
    Assets::enqueueStyle(
        'theme-editor-css-bundle',
        $template_dir . '/dist/styles/editor.css'
    );

    // Support for block editor styles (classic and modern)
    add_editor_style($template_dir . '/dist/styles/editor.css');

    // Inject theme colors and fonts as CSS variables for the editor
    $primary_color = getOption('primary_color');
    $secondary_color = getOption('secondary_color');
    $bg_color = getOption('bg_color');
    
    $primary_color_dark = getOption('primary_color_dark');
    $secondary_color_dark = getOption('secondary_color_dark');
    $bg_color_dark = getOption('bg_color_dark');

    $custom_css = "
        :root, .editor-styles-wrapper {
            --primary-color: {$primary_color};
            --secondary-color: {$secondary_color};
            --bg-color: {$bg_color};
            --primary-color-dark: {$primary_color_dark};
            --secondary-color-dark: {$secondary_color_dark};
            --bg-color-dark: {$bg_color_dark};
            font-family: 'Quicksand', sans-serif !important;
        }
    ";
    wp_add_inline_style('theme-editor-css-bundle', $custom_css);
}

/**
 * Add favicon proxy.
 *
 * @return void
 * @link WPEmergeTheme\Assets\Assets::addFavicon()
 */
function app_action_add_favicon()
{
    Assets::addFavicon();
}

/**
 * Advanced script optimization with defer/async/preload
 */
add_filter('script_loader_tag', function ($tag, $handle, $src) {
    // Scripts to defer (non-critical)
    // NOTE: theme-vendors-js is NOT deferred - it must load blocking to ensure Swal/dependencies are available
    $defer_scripts = [
        'theme-js-bundle',
        'theme-admin-js-bundle',
        'theme-login-js-bundle',
        'theme-editor-js-bundle',
        'theme-archive-js',
        'theme-comments-js'
    ];

    // Scripts to async (tracking, analytics)
    $async_scripts = [
        'google-analytics',
        'facebook-pixel',
        'hotjar'
    ];

    if (in_array($handle, $defer_scripts)) {
        return str_replace('<script ', '<script defer ', $tag);
    }

    if (in_array($handle, $async_scripts)) {
        return str_replace('<script ', '<script async ', $tag);
    }

    return $tag;
}, 10, 3);

/**
 * Advanced style optimization
 */
add_filter('style_loader_tag', function ($tag, $handle, $href) {
    // Non-critical styles to load asynchronously
    $non_critical_styles = [
        'theme-single-css',
        'fontawesome',
        'google-fonts'
    ];

    // If critical CSS file exists (inlined in header), load main bundle asynchronously
    if (file_exists(get_template_directory() . '/dist/styles/critical.css')) {
        $non_critical_styles[] = 'theme-css-bundle';
    }

    if (in_array($handle, $non_critical_styles)) {
        // Load non-critical CSS asynchronously
        return '<link rel="preload" href="' . $href . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'" id="' . $handle . '">' .
            '<noscript><link rel="stylesheet" href="' . $href . '"></noscript>';
    }

    return $tag;
}, 10, 3);

/**
 * FIXED: Preload critical assets in wp_head (Agent Skills: Performance)
 */
add_action('wp_head', function() {
    $template_dir = get_template_directory_uri();
    
    // 1. Preload Critical JS
    if (file_exists(get_template_directory() . '/dist/critical.js')) {
        echo '<link rel="preload" href="' . $template_dir . '/dist/critical.js" as="script">' . "\n";
    }

    // 2. Preload Main CSS Bundle (if not using Critical CSS inline)
    // If critical.css exists, we async load the main bundle (handled in style_loader_tag above)
    // If NOT, we should preload it here to prioritize it
    if (!file_exists(get_template_directory() . '/dist/styles/critical.css')) {
         echo '<link rel="preload" href="' . $template_dir . '/dist/styles/theme.css" as="style">' . "\n";
    }

    // 3. Preload important fonts (Agent Skills: Performance)
    // Preload Regular and SemiBold weights to prevent Flash of Unstyled Text (FOUT)
    $fonts = [
        'dist/fonts/BeVietnamPro-Regular.bbe77399f9.ttf',
        'dist/fonts/BeVietnamPro-SemiBold.fbc3f74acb.ttf',
        'dist/fonts/Quicksand-Regular.61504eaec8.ttf',
    ];

    foreach ($fonts as $font) {
        echo '<link rel="preload" href="' . $template_dir . '/' . $font . '" as="font" type="font/ttf" crossorigin>' . "\n";
    }
}, 1);

/**
 * Enhanced resource hints for performance
 */
add_filter('wp_resource_hints', function ($hints, $relation_type) {
    if ('preconnect' === $relation_type) {
        $hints[] = 'https://fonts.gstatic.com';
        $hints[] = 'https://ajax.googleapis.com';
    }

    if ('dns-prefetch' === $relation_type) {
        $hints[] = '//fonts.googleapis.com';
        $hints[] = '//cdnjs.cloudflare.com';
    }

    if ('prefetch' === $relation_type && (is_home() || is_front_page())) {
        // Prefetch likely next pages
        $hints[] = get_permalink(get_option('page_for_posts'));
    }

    return $hints;
}, 10, 2);

// Hook vào action để enqueue assets thông qua function có sẵn thay vì thêm action mới
add_action('wp_enqueue_scripts', 'app_action_theme_enqueue_assets');
add_action('admin_enqueue_scripts', 'app_action_admin_enqueue_assets');
add_action('login_enqueue_scripts', 'app_action_login_enqueue_assets');
add_action('enqueue_block_editor_assets', 'app_action_editor_enqueue_assets'); // For Gutenberg editor
