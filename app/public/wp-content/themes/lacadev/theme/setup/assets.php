<?php
/**
 * Asset helpers.
 *
 * @package WPEmergeTheme
 */

use WPEmergeTheme\Facades\Theme;
use WPEmergeTheme\Facades\Assets;
use App\Contracts\AssetHandles;

/**
 * Enhanced asset loading with performance optimizations
 */
function app_action_theme_enqueue_assets()
{
    $version = wp_get_theme()->get('Version');
    $theme_root_dir = dirname(get_template_directory());
    $theme_root_uri = dirname(get_template_directory_uri());
    
    $dist_path = $theme_root_dir . '/dist/';
    $dist_url  = $theme_root_uri . '/dist/';

    /**
     * Enqueue the built-in comment-reply script for singular pages.
     */
    if (is_singular()) {
        wp_enqueue_script('comment-reply');
    }

    /**
     * Critical JS (inline or very small) - load in head for critical functionality
     */
    if (file_exists($dist_path . 'critical.js')) {
        wp_enqueue_script(AssetHandles::CRITICAL_JS, $dist_url . 'critical.js', [], $version, false);
    }

    $vendor_chunks = [
        AssetHandles::VENDORS_JS      => 'vendors.js',
        'theme-vendor-gsap-js'  => 'vendor-gsap.js',
        'theme-vendor-swiper-js'=> 'vendor-swiper.js',
        'theme-vendor-swal-js'  => 'vendor-swal.js',
        'theme-vendor-836-js'   => '836.js',
    ];
    $vendors_deps = [];
    foreach ($vendor_chunks as $handle => $file) {
        if (file_exists($dist_path . $file)) {
            wp_enqueue_script($handle, $dist_url . $file, [], $version, true);
            $vendors_deps[] = $handle;
        }
    }

    /**
     * Main JavaScript bundle (deferred)
     */
    Assets::enqueueScript(AssetHandles::THEME_JS, $dist_url . 'theme.js', $vendors_deps, true);

    /**
     * Conditional assets based on page type
     */
    if (is_home() || is_archive() || is_search()) {
        if (file_exists($dist_path . 'archive.js')) {
            wp_enqueue_script(AssetHandles::ARCHIVE_JS, $dist_url . 'archive.js', [AssetHandles::THEME_JS], $version, true);
        }
    }

    if (is_single() && comments_open()) {
        if (file_exists($dist_path . 'comments.js')) {
            wp_enqueue_script(AssetHandles::COMMENTS_JS, $dist_url . 'comments.js', [AssetHandles::THEME_JS], $version, true);
        }
    }

    /**
     * Enqueue styles with preload optimization
     */
    Assets::enqueueStyle(AssetHandles::THEME_CSS, $dist_url . 'styles/theme.css');

    /**
     * Conditional CSS based on page type
     */
    if (is_single()) {
        if (file_exists($dist_path . 'styles/single.css')) {
            wp_enqueue_style(AssetHandles::SINGLE_CSS, $dist_url . 'styles/single.css', [AssetHandles::THEME_CSS], $version);
        }
    }

    /**
     * Enqueue theme's style.css file to allow overrides for the bundled styles.
     */
    Assets::enqueueStyle(AssetHandles::THEME_STYLES, get_template_directory_uri() . '/style.css');

    /**
     * Localize script with minimal data
     */
    wp_localize_script(AssetHandles::THEME_JS, 'themeData', [
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
        AssetHandles::ADMIN_CSS,
        $template_dir . '/dist/styles/admin.css'
    );
    Assets::enqueueStyle(
        AssetHandles::EDITOR_CSS,
        $template_dir . '/dist/styles/editor.css'
    );

    /**
     * Admin vendor chunks — load in <head> (false) để đảm bảo Swal/Chart available trước admin.js
     */
    $admin_deps = [];
    $theme_root = dirname(get_template_directory());
    $base_uri   = dirname(get_template_directory_uri());
    $admin_theme_dist_path = $theme_root . '/dist/';
    $admin_theme_dist_url  = $base_uri . '/dist/';
    $admin_version = wp_get_theme()->get('Version');

    $admin_vendor_chunks = [
        AssetHandles::VENDORS_JS       => 'vendors.js',
        'theme-vendor-swal-js'   => 'vendor-swal.js',
        'theme-vendor-chart-js'  => 'vendor-chart.js',
    ];
    foreach ($admin_vendor_chunks as $handle => $file) {
        if (file_exists($admin_theme_dist_path . $file)) {
            wp_enqueue_script($handle, $admin_theme_dist_url . $file, [], $admin_version, false);
            $admin_deps[] = $handle;
        }
    }

    /**
     * Enqueue scripts.
     */
    Assets::enqueueScript(
        AssetHandles::ADMIN_JS,
        $template_dir . '/dist/admin.js',
        $admin_deps,
        true
    );

    /**
     * Localize admin script data with nonce for AJAX requests and i18n strings
     */
    wp_localize_script(AssetHandles::ADMIN_JS, 'ajaxurl_params', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('update_post_thumbnail'),  // Must match backend check_ajax_referer
        'attachmentNonce' => wp_create_nonce('laca_get_attachment_url'),
    ]);

    /**
     * Localize i18n strings for admin JavaScript
     */
    wp_localize_script(AssetHandles::ADMIN_JS, 'adminI18n', [
        // Thumbnail removal
        'removeThumbnailTitle' => __('Remove Thumbnail?', 'laca'),
        'removeThumbnailText' => __('Are you sure you want to remove this featured image?', 'laca'),
        'removeThumbnailConfirm' => __('Yes, remove it', 'laca'),
        'removeThumbnailCancel' => __('Cancel', 'laca'),
        'removedTitle' => __('Removed!', 'laca'),
        'removedText' => __('Featured image has been removed.', 'laca'),
        'errorTitle' => __('Error!', 'laca'),
        'failedRemove' => __('Failed to remove thumbnail.', 'laca'),
        
        // UI labels
        'chooseImage' => __('Choose image', 'laca'),
        'setFeaturedImage' => __('Set featured image', 'laca'),
    ]);

    /**
     * Localize project chart data — chỉ inject trên trang Dashboard (index.php).
     * Dữ liệu được đọc từ custom post type 'project' nếu đã đăng ký.
     */
    $current_screen = get_current_screen();
    if ($current_screen && $current_screen->id === 'dashboard' && post_type_exists('project')) {
        global $wpdb;

        // byStatus: đếm project theo meta _project_status (Carbon Fields)
        $status_labels = [
            'pending'     => '🕐 Chờ làm',
            'in_progress' => '🔨 Đang làm',
            'done'        => '✅ Đã xong',
            'maintenance' => '🔧 Đang bảo trì',
            'paused'      => '⏸️ Tạm dừng',
        ];

        $status_rows = $wpdb->get_results("
            SELECT
                COALESCE(pm.meta_value, 'pending') AS `key`,
                COUNT(*) AS `count`
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm
                ON p.ID = pm.post_id AND pm.meta_key = '_project_status'
            WHERE p.post_type = 'project'
              AND p.post_status NOT IN ('trash','auto-draft','inherit')
            GROUP BY `key`
        ");

        $by_status = [];
        foreach ($status_rows as $row) {
            $by_status[] = [
                'key'   => $row->key,
                'label' => $status_labels[$row->key] ?? ucfirst($row->key),
                'count' => (int) $row->count,
            ];
        }

        // byMonth: đếm project tạo mới trong 12 tháng gần nhất
        $month_rows = $wpdb->get_results("
            SELECT
                DATE_FORMAT(post_date, '%Y-%m') AS ym,
                COUNT(*) AS cnt
            FROM {$wpdb->posts}
            WHERE post_type = 'project'
              AND post_status NOT IN ('trash','auto-draft','inherit')
              AND post_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY ym
            ORDER BY ym ASC
        ");

        // Lấp đầy các tháng còn thiếu
        $month_map = [];
        foreach ($month_rows as $r) {
            $month_map[$r->ym] = (int) $r->cnt;
        }
        $by_month = [];
        for ($i = 11; $i >= 0; $i--) {
            $ym    = date('Y-m', strtotime("-{$i} months"));
            $label = 'T' . (int) date('n', strtotime("-{$i} months"));
            $by_month[] = [
                'month' => $label,
                'count' => $month_map[$ym] ?? 0,
            ];
        }

        wp_localize_script(AssetHandles::ADMIN_JS, 'lacaProjectCharts', [
            'primary'  => '#18181b',
            'byStatus' => $by_status,
            'byMonth'  => $by_month,
        ]);
    }
}

/**
 * Preload critical assets in admin_head
 */
add_action('admin_head', function() {
    $theme_root_uri = dirname(get_template_directory_uri());
    $dist_url = $theme_root_uri . '/dist/';
    
    // Preload important fonts
    $fonts = [
        'fonts/BeVietnamPro-Regular.bbe77399f9.ttf',
        'fonts/BeVietnamPro-SemiBold.fbc3f74acb.ttf',
        'fonts/Quicksand-Regular.61504eaec8.ttf',
    ];

    foreach ($fonts as $font) {
        echo '<link rel="preload" href="' . $dist_url . $font . '" as="font" type="font/ttf" crossorigin>' . "\n";
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
    $resolveLogoUrl = static function ($rawValue): string {
        // Carbon Fields image can return ID, URL string, or array.
        if (empty($rawValue)) {
            return '';
        }

        if (is_numeric($rawValue)) {
            $url = wp_get_attachment_image_url((int) $rawValue, 'full');
            return $url ?: '';
        }

        if (is_array($rawValue)) {
            if (!empty($rawValue['url']) && is_string($rawValue['url'])) {
                return esc_url_raw($rawValue['url']);
            }

            if (!empty($rawValue['id']) && is_numeric($rawValue['id'])) {
                $url = wp_get_attachment_image_url((int) $rawValue['id'], 'full');
                return $url ?: '';
            }

            if (!empty($rawValue['value']) && is_numeric($rawValue['value'])) {
                $url = wp_get_attachment_image_url((int) $rawValue['value'], 'full');
                return $url ?: '';
            }

            return '';
        }

        if (is_string($rawValue)) {
            if (filter_var($rawValue, FILTER_VALIDATE_URL)) {
                return esc_url_raw($rawValue);
            }

            if (ctype_digit($rawValue)) {
                $url = wp_get_attachment_image_url((int) $rawValue, 'full');
                return $url ?: '';
            }
        }

        return '';
    };

    // Ô ghi đè trong Theme Options theo ngôn ngữ đang xem (vd login_user_label_en),
    // rồi ô chung; trống thì rơi về câu __() — dịch bằng file .po của theme.
    $login_lang = strpos(determine_locale(), 'en') === 0 ? 'en' : 'vi';
    $pickLoginText = static function (string $key, string $default) use ($login_lang): string {
        $value = carbon_get_theme_option("{$key}_{$login_lang}");
        if (empty($value)) {
            $value = carbon_get_theme_option($key);
        }
        return !empty($value) ? (string) $value : $default;
    };

    $login_logo_raw = carbon_get_theme_option('login_logo');
    $login_logo_url = $resolveLogoUrl($login_logo_raw);
    if (empty($login_logo_url)) {
        $login_logo_url = $resolveLogoUrl(carbon_get_theme_option('logo'));
    }

    $loginStrings = [
        'userLabel'       => $pickLoginText('login_user_label', __('Ai đang ghé trạm?', 'laca')),
        'userPlaceholder' => $pickLoginText('login_user_placeholder', __('Điền tên hoặc email vào đây nhé', 'laca')),
        'passLabel'       => $pickLoginText('login_password_label', __('Chìa khóa', 'laca')),
        'passPlaceholder' => $pickLoginText('login_password_placeholder', __('Nhập chìa khóa mở cửa', 'laca')),
        // Chữ thuần, xuống dòng bằng \n — JS tự chèn <br> (không dùng innerHTML)
        'welcomeText'     => wp_strip_all_tags($pickLoginText('login_welcome_text', __("Chào mừng về Trạm Laca!\nCắm sạc, pha trà và bắt đầu nào!", 'laca'))),
        'forgetPwd'       => $pickLoginText('login_forgot_label', __('Rớt chìa khoá?', 'laca')),
        'backToBlog'      => $pickLoginText('login_back_label', __('← Rời khỏi Trạm', 'laca')),
        'langVi'          => _x('Vie', 'nút chọn tiếng Việt ở trang đăng nhập', 'laca'),
        'langEn'          => _x('Eng', 'nút chọn tiếng Anh ở trang đăng nhập', 'laca'),
    ];

    /**
     * Enqueue scripts.
     */
    Assets::enqueueScript(
        AssetHandles::LOGIN_JS,
        $template_dir . '/dist/login.js',
        [],
        true
    );

    wp_localize_script(AssetHandles::LOGIN_JS, 'loginI18n', [
        'logoUrl' => $login_logo_url,
        'strings' => $loginStrings,
        'homeUrl' => home_url('/'),
    ]);

    /**
     * Enqueue styles.
     */
    Assets::enqueueStyle(
        AssetHandles::LOGIN_CSS,
        $template_dir . '/dist/styles/login.css'
    );

    // Force override login logo in case theme CSS uses !important.
    if (!empty($login_logo_url)) {
        $safe_logo_url = esc_url_raw($login_logo_url);
        $login_logo_css = "#login h1 a{background-image:url('{$safe_logo_url}') !important;}";
        wp_add_inline_style(AssetHandles::LOGIN_CSS, $login_logo_css);
    }
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
        AssetHandles::EDITOR_JS,
        $template_dir . '/dist/editor.js',
        [],
        true
    );

    /**
    * Enqueue styles.
    */
    Assets::enqueueStyle(
        AssetHandles::EDITOR_CSS,
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
    wp_add_inline_style(AssetHandles::EDITOR_CSS, $custom_css);
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
    // Scripts to defer (non-critical, loaded in footer)
    // NOTE: admin vendor chunks (swal, chart) are NOT deferred — loaded in <head> blocking
    $defer_scripts = [
        AssetHandles::VENDORS_JS,
        'theme-vendor-gsap-js',
        'theme-vendor-swiper-js',
        'theme-vendor-swal-js',
        'theme-vendor-836-js',
        AssetHandles::THEME_JS,
        AssetHandles::ADMIN_JS,
        AssetHandles::LOGIN_JS,
        AssetHandles::EDITOR_JS,
        AssetHandles::ARCHIVE_JS,
        AssetHandles::COMMENTS_JS,
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
        AssetHandles::SINGLE_CSS,
        'fontawesome',
        'google-fonts'
    ];

    // If critical CSS file exists (inlined in header), load main bundle asynchronously
    if (file_exists(lacaDistDir('styles/critical.css'))) {
        $non_critical_styles[] = AssetHandles::THEME_CSS;
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
    $theme_root_dir = dirname(get_template_directory());
    $theme_root_uri = dirname(get_template_directory_uri());
    
    $dist_path = $theme_root_dir . '/dist/';
    $dist_url  = $theme_root_uri . '/dist/';
    
    // 1. Preload Critical JS
    if (file_exists($dist_path . 'critical.js')) {
        echo '<link rel="preload" href="' . $dist_url . 'critical.js" as="script">' . "\n";
    }

    // 2. Preload Main CSS Bundle (if not using Critical CSS inline)
    if (!file_exists($dist_path . 'styles/critical.css')) {
         echo '<link rel="preload" href="' . $dist_url . 'styles/theme.css" as="style">' . "\n";
    }

    // 3. Preload important fonts (Agent Skills: Performance)
    $fonts = [
        'fonts/BeVietnamPro-Regular.bbe77399f9.ttf',
        'fonts/BeVietnamPro-SemiBold.fbc3f74acb.ttf',
        'fonts/Quicksand-Regular.61504eaec8.ttf',
    ];

    foreach ($fonts as $font) {
        echo '<link rel="preload" href="' . $dist_url . $font . '" as="font" type="font/ttf" crossorigin>' . "\n";
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
