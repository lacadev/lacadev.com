<?php

namespace App\Settings\LacaTools;

/**
 * ManagementExperience Class
 * Handles the administrative UI/UX enhancements for clients.
 */
class ManagementExperience
{
    const VIEW_COUNT_META = '_gm_view_count';

    public function __construct()
    {
        if (!is_admin()) {
            return;
        }

        // Styles & Scripts
        add_action('admin_head', [$this, 'renderWidgetStyles']);
        $this->addDashboardSummaryWidget();
        $this->addContentTrackerWidget();
        $this->addSiteHealthWidget();
        $this->addMediaLibraryWidget();
        $this->addTodoWidget();
        $this->addQuickSearchWidget();

        add_action('pre_get_posts', [$this, 'filterDetachedMedia']);

        add_action('wp_ajax_lacadev_quick_search', [$this, 'ajaxQuickSearch']);

        // Delay features that depend on theme options until Carbon Fields is ready
        add_action('init', function() {
            // List table enhancements
            $this->addViewsColumn();
            $this->enrichProductList();
            $this->enrichPostList();
            
            // Duplication support
            $this->enableDuplication();
        });
        
        // Navigation & UX
        $this->addClientHelpMenu();
        add_action('admin_menu', [$this, 'registerUnattachedMediaMenu']);
        
        // Background Jobs
        add_filter('cron_schedules', [$this, 'addWeeklyCronSchedule']);
        add_action('init', [$this, 'scheduleWeeklyDeepAudit']);
        add_action('lacadev_weekly_deep_audit', [$this, 'executeDeepAudit']);

        // General cleanup for non-admins
        $this->simplifyMerchantAdmin();
    }

    /**
     * Adds a weekly cron schedule if not exists.
     */
    public function addWeeklyCronSchedule($schedules)
    {
        if (!isset($schedules['weekly_midnight'])) {
            $schedules['weekly_midnight'] = [
                'interval' => 7 * 24 * 60 * 60,
                'display'  => __('Chủ nhật hàng tuần', 'laca')
            ];
        }
        return $schedules;
    }

    /**
     * Schedules the deep audit event.
     */
    public function scheduleWeeklyDeepAudit()
    {
        if (!wp_next_scheduled('lacadev_weekly_deep_audit')) {
            // Schedule for next Sunday at 00:00
            $start_time = strtotime('next sunday 00:00:00');
            wp_schedule_event($start_time, 'weekly_midnight', 'lacadev_weekly_deep_audit');
        }
    }

    /**
     * Adds a "At a Glance" style widget with actionable business data.
     */
    public function addDashboardSummaryWidget()
    {
        add_action('wp_dashboard_setup', function () {
            wp_add_dashboard_widget(
                'lacadev_management_hub',
                '🚀 LacaDev Business Hub',
                [$this, 'renderDashboardWidget']
            );
        });
    }

    /**
     * Adds a "Content Tracker" widget for new, top, and SEO status.
     */
    public function addContentTrackerWidget()
    {
        add_action('wp_dashboard_setup', function () {
            wp_add_dashboard_widget(
                'lacadev_content_tracker',
                '📈 Báo cáo Nội dung',
                [$this, 'renderContentTrackerWidget']
            );
        });
    }

    /**
     * Adds a "Site Health" widget: SSL, PHP/WP version, Draft, Scheduled, Trash.
     */
    public function addSiteHealthWidget()
    {
        add_action('wp_dashboard_setup', function () {
            wp_add_dashboard_widget(
                'lacadev_site_health',
                '🩺 Tình trạng Website',
                [$this, 'renderSiteHealthWidget']
            );
        });
    }

    /**
     * Adds a "Media Library" widget for total files/size and orphan count.
     */
    public function addMediaLibraryWidget()
    {
        add_action('wp_dashboard_setup', function () {
            wp_add_dashboard_widget(
                'lacadev_media_insights',
                '🖼️ Thư viện Media',
                [$this, 'renderMediaLibraryWidget']
            );
        });
    }

    /**
     * Adds a "To-do" widget: pending comments, orders, low stock, scheduled posts.
     */
    public function addTodoWidget()
    {
        add_action('wp_dashboard_setup', function () {
            wp_add_dashboard_widget(
                'lacadev_todo_widget',
                '✅ Việc cần làm',
                [$this, 'renderTodoWidget']
            );
        });
    }

/**
 * Performs the deep audit of all content.
 * Runs in background to avoid process timeouts.
 */
public function executeDeepAudit()
{
    // Ensure only one runner at a time
    if (get_transient('lacadev_deep_audit_lock')) {
        return;
    }
    set_transient('lacadev_deep_audit_lock', true, 2 * HOUR_IN_SECONDS);

    $post_types = $this->getDashboardPostTypes();
    $results = [];
    $offset = 0;

    global $wpdb;

    // We do optimized sweeps for different rule sets
    // 1. Missing Featured Images
    $cpt_for_images = array_diff((array)$post_types, ['page']);
    if (!empty($cpt_for_images)) {
        $cpt_in = implode(',', array_fill(0, count($cpt_for_images), '%s'));
        $missing_thumb = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title, p.post_type
            FROM $wpdb->posts p
            LEFT JOIN $wpdb->postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
            WHERE p.post_type IN ($cpt_in)
            AND p.post_status = 'publish'
            AND (pm.meta_value IS NULL OR pm.meta_value = '' OR pm.meta_value = '0')
        ", $cpt_for_images));

        foreach ($missing_thumb as $p) {
            $results[$p->ID] = [
                'ID' => $p->ID,
                'post_title' => $p->post_title,
                'post_type' => $p->post_type,
                'errors' => ['⚠️ Thiếu ảnh đại diện']
            ];
        }
    }

    // 2. Content Quality & Broken Images
    $post_types_in = implode(',', array_fill(0, count((array)$post_types), '%s'));
    
    // We only fetch posts that HAVE content or are likely candidates (char_length < 800 or contains <img)
    $candidates = $wpdb->get_results($wpdb->prepare("
        SELECT ID, post_title, post_type, post_content
        FROM $wpdb->posts
        WHERE post_type IN ($post_types_in)
        AND post_status = 'publish'
        AND (CHAR_LENGTH(post_content) < 800 OR post_content LIKE '%<img %')
    ", $post_types));

    foreach ($candidates as $p) {
        $errs = [];
        
        // Quality check
        $word_count = str_word_count(strip_tags($p->post_content));
        if ($word_count > 0 && $word_count < 100) {
            $errs[] = '📝 Nội dung quá ngắn';
        }

        // Broken image regex
        if (preg_match('/<img[^>]+src=["\']\s*["\']/', $p->post_content) || strpos($p->post_content, 'src=""') !== false) {
            $errs[] = '🖼️ Ảnh nội dung bị vỡ';
        }

        if (!empty($errs)) {
            if (isset($results[$p->ID])) {
                $results[$p->ID]['errors'] = array_unique(array_merge($results[$p->ID]['errors'], $errs));
            } else {
                $results[$p->ID] = [
                    'ID' => $p->ID,
                    'post_title' => $p->post_title,
                    'post_type' => $p->post_type,
                    'errors' => $errs
                ];
            }
        }

        // Prevent memory overload in huge loops
        if ($offset % 50 === 0) {
            if (function_exists('gc_collect_cycles')) gc_collect_cycles();
        }
        $offset++;
    }

    $final_report = [
        'last_updated' => current_time('mysql'),
        'issues' => array_values($results)
    ];

    update_option('lacadev_deep_health_report', $final_report, false);
    delete_transient('lacadev_deep_audit_lock');
}

/**
 * Audits content health, prioritizing the deep background report.
 */
protected function auditContentHealth($post_types)
{
    $report = get_option('lacadev_deep_health_report');
    
    // If we have a deep report, use it
    if ($report && isset($report['issues'])) {
        return (array)$report['issues'];
    }

    // Fallback: trigger an immediate audit if it's the first time
    if (!get_transient('lacadev_deep_audit_lock')) {
        $this->executeDeepAudit();
        $report = get_option('lacadev_deep_health_report');
        return (isset($report['issues']) && is_array($report['issues'])) ? $report['issues'] : [];
    }

    return [];
}

    /**
     * Registers a submenu under 'Media' for Unattached media.
     */
    public function registerUnattachedMediaMenu()
    {
        add_submenu_page(
            'upload.php',
            'Media Không Dùng',
            'Media Không Dùng',
            'manage_options',
            'upload.php?detached=1&mode=list'
        );
    }

    /**
     * Consolidates all dashboard widget styles into one place.
     */
    public function renderWidgetStyles()
    {
        // Only output on dashboard or specific settings pages
        $screen = get_current_screen();
        if ($screen && $screen->id !== 'dashboard' && $screen->id !== 'toplevel_page_laca-admin') {
            // return; // Keep it simple for now and output on all admin if needed, 
            // but dashboard is safer.
        }
        ?>
        <style id="lacadev-dashboard-styles">
            /* Shared Dashboard Variables & Base */
            :root {
                --laca-primary: #2271b1;
                --laca-bg-soft: #f6f7f7;
                --laca-border: #e2e8f0;
                --laca-text-main: #1d2327;
                --laca-text-muted: #646970;
                --laca-radius: 10px;
                --laca-shadow-sm: 0 2px 8px rgba(0,0,0,0.05);
            }

            /* Business Hub Widget */
            .lacadev-dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-bottom: 20px; }
            .lacadev-dashboard-grid .stat-item { background: #f8f9fa; padding: 12px 8px; border-radius: var(--laca-radius); border: 1px solid var(--laca-border); text-align: center; transition: all 0.2s; }
            .stat-item:hover { transform: translateY(-2px); border-color: var(--laca-primary); background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
            .lacadev-dashboard-grid .stat-value { display: block; font-size: 22px; font-weight: 800; color: var(--laca-text-main); line-height: 1.2; }
            .lacadev-dashboard-grid .stat-label { font-size: 9px; text-transform: uppercase; color: var(--laca-text-muted); letter-spacing: 0.5px; font-weight: 700; display: block; margin-top: 4px; }
            .lacadev-actions-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
            .lacadev-btn-quick { display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid #dcdcde; padding: 10px; border-radius: var(--laca-radius); text-decoration: none; color: #2c3338; font-weight: 600; transition: all 0.2s; font-size: 13px; }
            .lacadev-btn-quick:hover { background: #f0f6fb; border-color: var(--laca-primary); color: var(--laca-primary); }
            .lacadev-btn-quick span { margin-right: 8px; font-size: 16px; }
            .hub-section-title { font-size: 13px; font-weight: 700; margin: 15px 0 10px; color: var(--laca-text-main); border-bottom: 2px solid #f1f1f1; padding-bottom: 5px; }
            .stat-maintenance--on { color: #d63638; }
            .stat-maintenance--off { color: #00a32a; }

            /* Site Health Widget */
            .laca-health-list { margin: 0; padding: 0; list-style: none; }
            .laca-health-list li { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f1; font-size: 13px; }
            .laca-health-list li:last-child { border-bottom: none; }
            .laca-health-list .health-label { color: var(--laca-text-muted); }
            .laca-health-list .health-value { font-weight: 600; color: var(--laca-text-main); }
            .laca-health-list .health-ok { color: #00a32a; }
            .laca-health-list .health-warn { color: #dba617; }
            .laca-health-list .health-link { margin-left: 8px; font-size: 11px; }

            /* Todo Widget */
            .laca-todo-list { margin: 0; padding: 0; list-style: none; }
            .laca-todo-list li { margin-bottom: 8px; }
            .laca-todo-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; background: var(--laca-bg-soft); border-radius: var(--laca-radius); text-decoration: none; color: var(--laca-text-main); font-size: 13px; font-weight: 600; border: 1px solid var(--laca-border); transition: all 0.2s; }
            .laca-todo-item:hover { background: #fff; border-color: var(--laca-primary); color: var(--laca-primary); }
            .laca-todo-item span:first-child { display: flex; align-items: center; gap: 8px; }
            .laca-todo-badge { background: var(--laca-primary); color: #fff; font-size: 11px; padding: 2px 8px; border-radius: var(--laca-radius); }
            .laca-todo-empty { padding: 20px 0; text-align: center; color: var(--laca-text-muted); font-size: 13px; }

            /* Quick Search Widget */
            .laca-quick-search-wrap { position: relative; }
            .laca-quick-search-input { width: 100%; padding: 12px 14px; font-size: 14px; border: 1px solid #dcdcde; border-radius: 8px; box-sizing: border-box; }
            .laca-quick-search-input:focus { border-color: var(--laca-primary); outline: none; }
            .laca-quick-search-results { margin-top: 12px; max-height: 320px; overflow-y: auto; }
            .laca-quick-search-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; border-bottom: 1px solid #f0f0f1; text-decoration: none; color: var(--laca-text-main); font-size: 13px; transition: background 0.15s; }
            .laca-quick-search-item:hover { background: var(--laca-bg-soft); }
            .laca-quick-search-item:last-child { border-bottom: none; }
            .laca-quick-search-item .item-title { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; }
            .laca-quick-search-item .item-meta { font-size: 11px; color: var(--laca-text-muted); margin-left: 10px; flex-shrink: 0; }
            .laca-quick-search-loading, .laca-quick-search-empty { padding: 20px; text-align: center; color: var(--laca-text-muted); font-size: 13px; }

            /* Content Tracker Widget */
            /* Content Report Dropdown UI */
            .laca-widget-header-row { padding: 10px 12px; border-bottom: 1px solid #dcdcde; background: #fff; display: flex; align-items: center; }
            .laca-report-select { border: 1px solid #dcdcde; background: #fff; color: #2c3338; padding: 4px 24px 4px 8px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; min-width: 180px; width: 100%; box-shadow: none !important; }
            .laca-report-select:focus { border-color: var(--laca-primary); color: var(--laca-primary); }
            
            .laca-tab-content { display: none; padding: 0; }
            .laca-tab-content.active { display: block; }
            .laca-post-list { margin: 0; padding: 0; list-style: none; }
            .laca-post-list li { padding: 10px 0; border-bottom: 1px solid #f0f0f1; display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
            .laca-post-list li:last-child { border-bottom: none; }
            .laca-post-info { flex: 1; min-width: 0; }
            .laca-post-link { text-decoration: none; color: var(--laca-primary); font-weight: 600; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 13px; margin-bottom: 3px; }
            .laca-post-link:hover { color: #135e96; }
            .laca-post-meta { font-size: 11px; color: var(--laca-text-muted); display: block; }
            .laca-badge { font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 700; color: #fff; }
            .badge-views { background: var(--laca-primary); }
            .badge-seo { background: #d63638; }
            .badge-date { background: #f0f0f1; color: var(--laca-text-muted); }
            .laca-issue-tag { display: inline-block; background: #fff2f2; color: #d63638; padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; border: 1px solid #ffccca; margin-right: 5px; vertical-align: middle; }
            .laca-pagination-row { display: flex !important; justify-content: center; align-items: center; gap: 15px; padding: 15px 0 !important; border-bottom: none !important; }
            .laca-page-btn { background: #fff; border: 1px solid #dcdcde; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: 600; color: #2c3338; }
            .laca-page-btn:hover:not(:disabled) { background: #f0f6fb; border-color: var(--laca-primary); color: var(--laca-primary); }
            .laca-page-btn:disabled { opacity: 0.5; cursor: default; }
            .laca-page-info { font-size: 11px; color: var(--laca-text-muted); font-weight: 600; }

            /* List Table Columns */
            .column-laca_views { width: 90px !important; }
            .laca-views-col { display: flex; align-items: center; gap: 5px; color: #50575e; }
            .laca-views-col .dashicons { font-size: 17px; width: 17px; height: 17px; color: #999; }
            .laca-views-col strong { font-family: monospace; font-size: 13px; }
        </style>
        <?php
    }

    /**
     * Renders the dashboard widget content.
     */
    public function renderDashboardWidget()
    {
        $posts_count = (int) wp_count_posts()->publish;
        $pages_count = wp_count_posts('page')->publish;

        $args = [
            'public'   => true,
            '_builtin' => false,
        ];
        $post_types = get_post_types($args, 'objects');
        $cpt_stats = '';
        foreach ($post_types as $post_type) {
            if (class_exists('WooCommerce') && $post_type->name === 'product') {
                continue;
            }
            $count = (int) wp_count_posts($post_type->name)->publish;
            $cpt_stats .= "
                <div class='stat-item'>
                    <span class='stat-value'>" . esc_html($count) . "</span>
                    <span class='stat-label'>" . esc_html($post_type->label) . "</span>
                </div>
            ";
        }

        $woo_stats = '';
        if (class_exists('WooCommerce')) {
            $products_count = (int) wp_count_posts('product')->publish;
            $orders_count = (int) wc_get_orders(['status' => 'completed', 'return' => 'count']);
            $woo_stats = "
                <div class='stat-item'>
                    <span class='stat-value'>" . esc_html($products_count) . "</span>
                    <span class='stat-label'>Sản phẩm</span>
                </div>
                <div class='stat-item'>
                    <span class='stat-value'>" . esc_html($orders_count) . "</span>
                    <span class='stat-label'>Đơn hàng</span>
                </div>
            ";
        }

        $maintenance_status = (get_option('_is_maintenance') === 'yes')
            ? '<span class="stat-maintenance--on">🔴 Đầy Bật</span>'
            : '<span class="stat-maintenance--off">🟢 Đã Tắt</span>';

        // Styles extracted to renderWidgetStyles()
        ?>
        <div class="lacadev-dashboard-grid">
            <div class="stat-item">
                <span class="stat-value"><?php echo esc_html($posts_count); ?></span>
                <span class="stat-label">Bài viết</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo esc_html($pages_count); ?></span>
                <span class="stat-label">Trang</span>
            </div>
            <?php echo $cpt_stats; // Already escaped in loop ?>
            <?php echo $woo_stats; // Already escaped in loop ?>
            <div class="stat-item">
                <span class="stat-value"><?php echo $maintenance_status; // Escaped content within status ?></span>
                <span class="stat-label">Bảo trì</span>
            </div>
        </div>

        <div class="hub-section-title">Thao tác nhanh</div>
        <div class="lacadev-actions-list">
            <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" class="lacadev-btn-quick">
                <span>🌐</span> Xem site
            </a>
            <a href="<?php echo esc_url(admin_url('post-new.php')); ?>" class="lacadev-btn-quick">
                <span>📝</span> Viết bài mới
            </a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_status=draft&post_type=post')); ?>" class="lacadev-btn-quick">
                <span>📋</span> Bản nháp
            </a>
            <a href="<?php echo esc_url(admin_url('edit.php?post_status=future&post_type=post')); ?>" class="lacadev-btn-quick">
                <span>📅</span> Đã lên lịch
            </a>
            <?php
            $cpt_types = get_post_types(['public' => true, '_builtin' => false], 'objects');
            foreach ($cpt_types as $pt) {
                if (class_exists('WooCommerce') && $pt->name === 'product') {
                    continue;
                }
                ?>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . $pt->name)); ?>" class="lacadev-btn-quick">
                    <span>➕</span> Thêm <?php echo esc_html($pt->labels->singular_name); ?>
                </a>
            <?php } ?>
            <?php if (class_exists('WooCommerce')) : ?>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=product')); ?>" class="lacadev-btn-quick">
                    <span>🎁</span> Thêm sản phẩm
                </a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=shop_order&status=wc-processing')); ?>" class="lacadev-btn-quick">
                    <span>🚩</span> Đơn hàng mới
                </a>
            <?php endif; ?>
            <a href="<?php echo esc_url(admin_url('upload.php?detached=1&mode=list')); ?>" class="lacadev-btn-quick">
                <span>🖼️</span> Media không dùng
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=app-theme-options.php')); ?>" class="lacadev-btn-quick">
                <span>⚙️</span> Cấu hình Theme
            </a>
        </div>
        <?php
    }

    /**
     * Renders the Site Health widget: SSL, PHP/WP version, Draft, Scheduled, Trash.
     */
    public function renderSiteHealthWidget()
    {
        $is_ssl = is_ssl() || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $php_ver = PHP_VERSION;
        $wp_ver = get_bloginfo('version');

        $all_types = $this->getDashboardPostTypes();
        
        // Count Drafts & Trash & Scheduled in one pass where possible or bulk
        $draft_total = 0;
        $trash_total = 0;
        foreach ($all_types as $pt) {
            $counts = wp_count_posts($pt);
            $draft_total += (int) ($counts->draft ?? 0);
            $trash_total += (int) ($counts->trash ?? 0);
        }

        $scheduled = new \WP_Query([
            'post_type'      => $all_types,
            'post_status'    => 'future',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        ]);
        $scheduled_count = $scheduled->found_posts;

        ?>
        <ul class="laca-health-list">
            <li>
                <span class="health-label">🔒 SSL (HTTPS)</span>
                <span class="health-value <?php echo $is_ssl ? 'health-ok' : 'health-warn'; ?>">
                    <?php echo $is_ssl ? 'Bật' : 'Tắt'; ?>
                </span>
            </li>
            <li>
                <span class="health-label">⚙️ WordPress</span>
                <span class="health-value"><?php echo esc_html($wp_ver); ?></span>
            </li>
            <li>
                <span class="health-label">🐘 PHP</span>
                <span class="health-value"><?php echo esc_html($php_ver); ?></span>
            </li>
            <li>
                <span class="health-label">📝 Bản nháp</span>
                <span class="health-value">
                    <?php echo esc_html($draft_total); ?>
                    <?php if ($draft_total > 0) : ?>
                        <a class="health-link" href="<?php echo esc_url(admin_url('edit.php?post_status=draft')); ?>">Xem</a>
                    <?php endif; ?>
                </span>
            </li>
            <li>
                <span class="health-label">📅 Đã lên lịch</span>
                <span class="health-value">
                    <?php echo esc_html($scheduled_count); ?>
                    <?php if ($scheduled_count > 0) : ?>
                        <a class="health-link" href="<?php echo esc_url(admin_url('edit.php?post_status=future')); ?>">Xem</a>
                    <?php endif; ?>
                </span>
            </li>
            <li>
                <span class="health-label">🗑️ Thùng rác</span>
                <span class="health-value">
                    <?php echo esc_html($trash_total); ?>
                    <?php if ($trash_total > 0) : ?>
                        <a class="health-link" href="<?php echo esc_url(admin_url('edit.php?post_status=trash')); ?>">Dọn</a>
                    <?php endif; ?>
                </span>
            </li>
        </ul>
        <?php
    }

    /**
     * Renders the Media Library widget: Total files, Total Size, Orphan count.
     */
    public function renderMediaLibraryWidget()
    {
        $counts = wp_count_attachments();
        $total_files = array_sum((array) $counts);

        // Get total size (cached for 1 hour to avoid perf impact)
        $total_size_raw = get_transient('lacadev_media_total_size');
        if (false === $total_size_raw) {
            global $wpdb;
            $upload_dir = wp_upload_dir();
            $base_dir = $upload_dir['basedir'];
            
            // Total size of all files in wp_postmeta with _wp_attached_file
            $files = $wpdb->get_col("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file'");
            $total_size_raw = 0;
            foreach ($files as $file) {
                $path = $base_dir . '/' . $file;
                if (file_exists($path)) {
                    $total_size_raw += @filesize($path);
                }
            }
            set_transient('lacadev_media_total_size', $total_size_raw, HOUR_IN_SECONDS);
        }
        $total_size = size_format($total_size_raw);

        $used_ids = $this->getCommonlyUsedMediaIds();

        // Orphan count (Unattached media)
        $query_args = [
            'post_type'      => 'attachment',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'post_parent'    => 0,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        ];
        if (!empty($used_ids)) {
            $query_args['post__not_in'] = $used_ids;
        }
        
        $orphans = new \WP_Query($query_args);
        $orphan_count = $orphans->found_posts;

        ?>
        <ul class="laca-health-list laca-media-list">
            <li>
                <span class="health-label">📊 Tổng số file</span>
                <span class="health-value"><?php echo esc_html($total_files); ?></span>
            </li>
            <li>
                <span class="health-label">💾 Tổng dung lượng</span>
                <span class="health-value"><?php echo esc_html($total_size); ?></span>
            </li>
            <li>
                <span class="health-label">🖼️ Media không sử dụng</span>
                <span class="health-value">
                    <?php echo esc_html($orphan_count); ?>
                    <?php if ($orphan_count > 0) : ?>
                        <a class="health-link" href="<?php echo esc_url(admin_url('upload.php?detached=1&mode=list')); ?>">Xem</a>
                    <?php endif; ?>
                </span>
            </li>
        </ul>
        <div class="hub-section-title" style="margin-top: 15px; font-size: 11px;">Mẹo: Dọn dẹp media không dùng giúp web nhẹ hơn.</div>
        <?php
    }

    /**
     * Filters the media library to hide "used" media (Logo, Featured, etc.) from the Detached view.
     */
    public function filterDetachedMedia($query)
    {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'attachment') {
            return;
        }

        // Check for 'detached' or 'post_parent' = 0
        if ($query->get('detached') || $query->get('post_parent') === 0) {
            $used_ids = $this->getCommonlyUsedMediaIds();
            if (!empty($used_ids)) {
                $query->set('post__not_in', $used_ids);
            }
        }
    }

    /**
     * Helper to collect media IDs that are used globally but have no post_parent.
     */
    protected function getCommonlyUsedMediaIds()
    {
        global $wpdb;
        $used_ids = [];

        // 1. Theme Options (Carbon Fields store them either in options or meta)
        // Check commonly used keys identified in theme-options.php
        $option_keys = ['logo', 'logo_dark', 'default_image', 'site_icon'];
        foreach ($option_keys as $key) {
            $val = carbon_get_theme_option($key);
            if (!empty($val) && is_numeric($val)) {
                $used_ids[] = (int) $val;
            }
        }
        
        // Site Icon (WP default)
        $site_icon = get_option('site_icon');
        if($site_icon) $used_ids[] = (int)$site_icon;

        // 2. Featured Images and common meta keys across all posts
        $meta_keys = ['_thumbnail_id', 'about_image', 'quick_view_img', '_site_icon'];
        $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
        $meta_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders) AND meta_value > 0",
            $meta_keys
        ));
        if (!empty($meta_ids)) {
            $used_ids = array_merge($used_ids, array_map('intval', $meta_ids));
        }

        // 3. Specific block IDs if heavy usage (e.g. About block), but thumbnail_id covers most "linked" cases.

        return array_unique(array_filter($used_ids));
    }

    /**
     * Renders the To-do widget: pending comments, orders, low stock, scheduled posts.
     */
    public function renderTodoWidget()
    {
        $items = [];

        $pending_comments = (int) wp_count_comments()->moderated;
        if ($pending_comments > 0) {
            $items[] = [
                'label' => 'Bình luận chờ duyệt',
                'count' => (int) $pending_comments,
                'url'   => esc_url(admin_url('edit-comments.php?comment_status=moderated')),
                'icon'  => '💬',
            ];
        }

        if (class_exists('WooCommerce')) {
            $new_orders = wc_get_orders(['status' => ['wc-processing', 'wc-on-hold'], 'return' => 'count']);
            if ($new_orders > 0) {
                $items[] = [
                    'label' => 'Đơn hàng mới cần xử lý',
                    'count' => (int) $new_orders,
                    'url'   => esc_url(admin_url('edit.php?post_type=shop_order&status=wc-processing')),
                    'icon'  => '🚩',
                ];
            }

            global $wpdb;
            $threshold = (int) get_option('woocommerce_notify_low_stock_amount', 2);
            $low_stock = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
                INNER JOIN {$wpdb->postmeta} pm_manage ON p.ID = pm_manage.post_id AND pm_manage.meta_key = '_manage_stock' AND pm_manage.meta_value = 'yes'
                WHERE p.post_type = 'product' AND p.post_status = 'publish'
                AND CAST(pm_stock.meta_value AS SIGNED) <= %d AND CAST(pm_stock.meta_value AS SIGNED) > 0",
                $threshold
            ));
            if ($low_stock > 0) {
                $items[] = [
                    'label' => 'Sản phẩm sắp hết hàng',
                    'count' => (int) $low_stock,
                    'url'   => esc_url(admin_url('edit.php?post_type=product&stock_status=lowstock')),
                    'icon'  => '📦',
                ];
            }
        }

        $all_types = (array) $this->getDashboardPostTypes();
        
        // Bulk get scheduled counts to avoid N+1
        global $wpdb;
        $type_placeholders = implode(',', array_fill(0, count($all_types), '%s'));
        $now = current_time('mysql');
        $future = date('Y-m-d H:i:s', strtotime('+7 days', current_time('timestamp')));
        
        $scheduled_counts = $wpdb->get_results($wpdb->prepare(
            "SELECT post_type, COUNT(*) as count 
             FROM {$wpdb->posts} 
             WHERE post_type IN ({$type_placeholders}) 
             AND post_status = 'future' 
             AND post_date BETWEEN %s AND %s 
             GROUP BY post_type",
            array_merge($all_types, [$now, $future])
        ), OBJECT_K);

        foreach ($all_types as $pt) {
            // Count Drafts (cached)
            $counts = wp_count_posts($pt);
            $draft_pt = (int) ($counts->draft ?? 0);
            if ($draft_pt > 0) {
                $pt_obj = get_post_type_object($pt);
                $label = $pt_obj ? $pt_obj->labels->singular_name : $pt;
                $items[] = [
                    'label' => $label . ' bản nháp',
                    'count' => $draft_pt,
                    'url'   => esc_url(admin_url('edit.php?post_status=draft&post_type=' . $pt)),
                    'icon'  => '📋',
                ];
            }

            // Scheduled posts from bulk query
            if (isset($scheduled_counts[$pt])) {
                $count = (int) $scheduled_counts[$pt]->count;
                $pt_obj = get_post_type_object($pt);
                $label = $pt_obj ? $pt_obj->labels->singular_name : $pt;
                $items[] = [
                    'label' => $label . ' lên lịch (7 ngày)',
                    'count' => $count,
                    'url'   => esc_url(admin_url('edit.php?post_status=future&post_type=' . $pt)),
                    'icon'  => '📅',
                ];
            }
        }

        // Styles extracted to renderWidgetStyles()
        if (empty($items)) : ?>
            <div class="laca-todo-empty">Không có việc cần làm ngay.</div>
        <?php else : ?>
            <ul class="laca-todo-list">
                <?php foreach ($items as $item) : ?>
                    <li>
                        <a href="<?php echo esc_url($item['url']); ?>" class="laca-todo-item">
                            <span><span><?php echo esc_html($item['icon']); ?></span> <?php echo esc_html($item['label']); ?></span>
                            <span class="laca-todo-badge"><?php echo (int) $item['count']; ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php
    }

    /**
     * Adds a "Quick Search" widget with AJAX search for posts, pages, CPTs.
     */
    public function addQuickSearchWidget()
    {
        add_action('wp_dashboard_setup', function () {
            wp_add_dashboard_widget(
                'lacadev_quick_search',
                '🔍 Tìm kiếm nhanh',
                [$this, 'renderQuickSearchWidget']
            );
        });
    }

    /**
     * AJAX handler for quick search.
     */
    public function ajaxQuickSearch()
    {
        check_ajax_referer('lacadev_quick_search', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $term = isset($_POST['search_keyword']) ? sanitize_text_field(wp_unslash($_POST['search_keyword'])) : '';
        $term = trim($term);

        if (strlen($term) < 2) {
            wp_send_json_success(['items' => [], 'message' => 'Nhập ít nhất 2 ký tự']);
        }

        global $wpdb;
        $post_types = $this->getSearchablePostTypes();
        
        // Ensure theme CPTs are ALWAYS included in the query regardless of filter/registration state
        foreach (['service', 'project'] as $cpt) {
            if (!in_array($cpt, $post_types, true)) {
                $post_types[] = $cpt;
            }
        }

        $type_placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $like = '%' . $wpdb->esc_like($term) . '%';
        $statuses = ['publish', 'draft', 'future', 'private'];
        $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));

        // Build query to strictly search post_title only
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_type, post_status, post_date
             FROM {$wpdb->posts}
             WHERE post_type IN ({$type_placeholders})
             AND post_title LIKE %s
             AND post_status IN ({$status_placeholders})
             ORDER BY post_title ASC
             LIMIT 15",
            array_merge($post_types, [$like], $statuses)
        ));

        $items = [];
        if ($results) {
            foreach ($results as $row) {
                $pt_obj = get_post_type_object($row->post_type);
                $pt_label = $pt_obj ? $pt_obj->labels->singular_name : $row->post_type;
                
                $status = $row->post_status;
                $status_labels = ['draft' => 'Bản nháp', 'future' => 'Lên lịch', 'private' => 'Riêng tư', 'publish' => ''];
                $status_label = isset($status_labels[$status]) ? $status_labels[$status] : $status;
                $status_label = $status_label ? ' (' . $status_label . ')' : '';

                $items[] = [
                    'id'       => (int) $row->ID,
                    'title'    => $this->decodeTitleForDisplay($row->post_title),
                    'edit_url' => get_edit_post_link((int) $row->ID, 'raw'),
                    'post_type' => $pt_label,
                    'status'   => $status_label,
                    'date'     => date_i18n('d/m/Y', strtotime($row->post_date)),
                ];
            }
        }

        wp_send_json_success(['items' => $items]);
    }

    /**
     * Renders the Quick Search widget.
     */
    public function renderQuickSearchWidget()
    {
        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('lacadev_quick_search');

        // Styles extracted to renderWidgetStyles()
        ?>
        <div class="laca-quick-search-wrap">
            <input type="text" class="laca-quick-search-input" placeholder="Tìm theo tiêu đề (bài viết, trang, CPT)..." autocomplete="off">
            <div class="laca-quick-search-results"></div>
        </div>
        <script>
        (function() {
            var input = document.querySelector('.laca-quick-search-input');
            var results = document.querySelector('.laca-quick-search-results');
            var timer = null;
            var lastTerm = '';

            function renderItems(items) {
                results.innerHTML = '';
                if (!items || items.length === 0) {
                    var empty = document.createElement('div');
                    empty.className = 'laca-quick-search-empty';
                    empty.textContent = 'Không tìm thấy kết quả.';
                    results.appendChild(empty);
                    return;
                }
                items.forEach(function(item) {
                    var a = document.createElement('a');
                    a.href = item.edit_url;
                    a.className = 'laca-quick-search-item';
                    a.target = '_blank';
                    var titleSpan = document.createElement('span');
                    titleSpan.className = 'item-title';
                    titleSpan.textContent = item.title || '';
                    var metaSpan = document.createElement('span');
                    metaSpan.className = 'item-meta';
                    metaSpan.textContent = (item.post_type || '') + (item.status || '') + ' · ' + (item.date || '');
                    a.appendChild(titleSpan);
                    a.appendChild(metaSpan);
                    results.appendChild(a);
                });
            }

            function doSearch() {
                var term = input.value.trim();
                if (term === lastTerm) return;
                lastTerm = term;

                if (term.length < 2) {
                    results.innerHTML = '<div class="laca-quick-search-empty">Nhập ít nhất 2 ký tự để tìm kiếm.</div>';
                    return;
                }

                results.innerHTML = '<div class="laca-quick-search-loading">Đang tìm...</div>';

                var formData = new FormData();
                formData.append('action', 'lacadev_quick_search');
                formData.append('nonce', '<?php echo esc_js($nonce); ?>');
                formData.append('search_keyword', term);

                fetch('<?php echo esc_url($ajax_url); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.data.items) {
                        renderItems(data.data.items);
                    } else {
                        results.innerHTML = '<div class="laca-quick-search-empty">' + (data.data && data.data.message ? data.data.message : 'Không tìm thấy kết quả.') + '</div>';
                    }
                })
                .catch(function() {
                    results.innerHTML = '<div class="laca-quick-search-empty">Lỗi tìm kiếm. Vui lòng thử lại.</div>';
                });
            }

            input.addEventListener('input', function() {
                clearTimeout(timer);
                var term = input.value.trim();
                if (term.length === 0) {
                    lastTerm = '';
                    results.innerHTML = '<div class="laca-quick-search-empty">Nhập từ khóa theo tiêu đề để tìm...</div>';
                    return;
                }
                timer = setTimeout(doSearch, 300);
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(timer);
                    doSearch();
                }
            });

            results.innerHTML = '<div class="laca-quick-search-empty">Nhập từ khóa theo tiêu đề để tìm...</div>';
        })();
        </script>
        <?php
    }

    /**
     * Returns post types to use in dashboard widgets. Auto-includes all public CPTs when not configured.
     */
    protected function getDashboardPostTypes()
    {
        $configured = carbon_get_theme_option('dashboard_widget_post_types');
        
        // 1. Get all public, menu-visible CPTs (Auto-discovery)
        $discoverable = get_post_types([
            'public'       => true,
            'show_in_menu' => true,
        ], 'names');

        // 2. Filter out standard system types to avoid clutter
        $excluded = apply_filters('lacadev_dashboard_post_types_excluded', [
            'attachment', 'revision', 'nav_menu_item', 'custom_css', 
            'customize_changeset', 'oembed_cache', 'user_request', 
            'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation'
        ]);
        
        $available = array_diff($discoverable, $excluded);

        if (!empty($configured)) {
            $types = (array) $configured;
        } else {
            // Default: Include 'post' and 'page' explicitly if available
            $types = array_values($available);
        }

        // 3. Ensure theme-core CPTs are included if they exist on the site
        foreach (['service', 'project'] as $core_cpt) {
            if (post_type_exists($core_cpt) && !in_array($core_cpt, $types, true)) {
                $types[] = $core_cpt;
            }
        }

        // 4. Apply final filter for advanced project-specific needs
        return array_values(apply_filters('lacadev_dashboard_post_types', $types));
    }

    /**
     * Returns all post types that can be searched. Force-includes theme CPTs service and project when they exist.
     */
    protected function getSearchablePostTypes()
    {
        $all_types = array_keys(get_post_types([], 'names'));
        $exclude = [
            'attachment', 'revision', 'nav_menu_item', 'custom_css', 
            'customize_changeset', 'oembed_cache', 'user_request', 
            'wp_block', 'wp_template', 'wp_template_part', 
            'wp_global_styles', 'wp_navigation', 'action_monitor'
        ];
        $types = array_values(array_diff($all_types, $exclude));
        
        // Ensure theme CPTs are always included when registered (avoids registration timing/capability quirks)
        foreach (['service', 'project'] as $cpt) {
            if (post_type_exists($cpt) && !in_array($cpt, $types, true)) {
                $types[] = $cpt;
            } elseif (!in_array($cpt, $types, true)) {
                // Since this is specifically requested by the user, we should ensure it's in the array 
                // even if post_type_exists somehow fails, as it might just not be fully registered yet 
                // in the current AJAX context
                $types[] = $cpt;
            }
        }
        
        return apply_filters('lacadev_searchable_post_types', $types);
    }

    /**
     * Filters search to match only post_title (not content/excerpt) by appending to posts_where.
     */
    public function filterSearchByTitleOnly($where, $query)
    {
        if (empty($GLOBALS['lacadev_search_title_only'])) {
            return $where;
        }
        global $wpdb;
        $term = $GLOBALS['lacadev_search_title_only'];
        $like = '%' . $wpdb->esc_like($term) . '%';
        $where .= " AND ({$wpdb->posts}.post_title LIKE '" . esc_sql($like) . "')";
        return $where;
    }

    /**
     * Decodes HTML entities in title so &amp; displays as &.
     */
    protected function decodeTitleForDisplay($title)
    {
        if (!is_string($title) || $title === '') {
            return $title;
        }
        $decoded = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        $decoded = htmlspecialchars_decode($decoded, ENT_QUOTES);
        return $decoded;
    }

    /**
     * Renders the Content Tracker widget.
     */
    public function renderContentTrackerWidget()
    {
        $allow_post_types = $this->getDashboardPostTypes();
        if (empty($allow_post_types)) {
            $allow_post_types = ['post'];
        }

        $limit = (int) carbon_get_theme_option('dashboard_widget_limit');
        if ($limit <= 0) {
            $limit = 5;
        }

        $paged_limit = 100; // Fetch more for frontend pagination

        $new_posts = new \WP_Query([
            'post_type'      => $allow_post_types,
            'posts_per_page' => $paged_limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post_status'    => 'publish',
            'no_found_rows'  => false, // Changed to false for pagination
        ]);

        global $wpdb;
        $post_types_in = implode(',', array_fill(0, count((array)$allow_post_types), '%s'));
        $updated_posts = $wpdb->get_results($wpdb->prepare("
            SELECT SQL_CALC_FOUND_ROWS ID, post_title, post_modified, post_type, post_date
            FROM $wpdb->posts
            WHERE post_type IN ($post_types_in)
            AND post_status = 'publish'
            AND post_modified != post_date
            ORDER BY post_modified DESC
            LIMIT %d
        ", array_merge((array)$allow_post_types, [$paged_limit])));
        $updated_posts_found_rows = $wpdb->get_var("SELECT FOUND_ROWS()");


        $view_posts = new \WP_Query([
            'post_type'      => $allow_post_types,
            'posts_per_page' => $paged_limit,
            'meta_key'       => self::VIEW_COUNT_META,
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'post_status'    => 'publish',
            'no_found_rows'  => false, // Changed to false for pagination
        ]);

        $seo_meta_key = '';
        if (defined('RANK_MATH_VERSION')) {
            $seo_meta_key = 'rank_math_seo_score';
        } elseif (defined('WPSEO_VERSION')) {
            $seo_meta_key = '_yoast_wpseo_linkdex';
        }

        $low_seo_posts = null;
        if ($seo_meta_key) {
            $low_seo_posts = new \WP_Query([
                'post_type'      => $allow_post_types,
                'posts_per_page' => $paged_limit,
                'meta_key'       => $seo_meta_key,
                'orderby'        => 'meta_value_num',
                'order'          => 'ASC',
                'post_status'    => 'publish',
                'no_found_rows'  => false, // Changed to false for pagination
                'meta_query'     => [
                    ['key' => $seo_meta_key, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'],
                ],
            ]);
        }

        ?>
        <!-- Styles extracted to renderWidgetStyles() -->
        <!-- UI Dropdown Filter -->
        <div class="laca-widget-header-row">
            <select class="laca-report-select">
                <option value="laca-new">Mới nhất</option>
                <option value="laca-updated">Cập nhật</option>
                <option value="laca-views">Xem nhiều</option>
            <?php 
                $health_report = get_option('lacadev_deep_health_report');
                $health_raw = isset($health_report['issues']) ? $health_report['issues'] : [];
                $health_count = count($health_raw);
                $last_scan = isset($health_report['last_updated']) ? $health_report['last_updated'] : '';
                $health_label = ($health_count > 0) ? "Nội dung lỗi ($health_count)" : "Nội dung lỗi";
            ?>
            <option value="laca-health" <?php echo ($health_count > 0) ? 'style="color:#d63638;font-weight:700;"' : ''; ?>>
                <?php echo esc_html($health_label); ?>
            </option>
                <?php if ($seo_meta_key) : ?>
                    <option value="laca-seo">Cần tối ưu SEO</option>
                <?php endif; ?>
            </select>
        </div>

        <div id="laca-new" class="laca-tab-content active" data-per-page="<?php echo $limit; ?>">
            <ul class="laca-post-list laca-paged-list">
                <?php if ($new_posts->have_posts()) : foreach ($new_posts->posts as $index => $p) : 
                    $pt_obj = get_post_type_object($p->post_type);
                    $pt_label = $pt_obj ? $pt_obj->labels->singular_name : $p->post_type;
                ?>
                    <li class="laca-list-item" <?php echo ($index >= $limit) ? 'style="display:none;"' : ''; ?>>
                        <div class="laca-post-info">
                            <a href="<?php echo esc_url(get_edit_post_link($p->ID)); ?>" class="laca-post-link">
                                <?php echo esc_html($this->decodeTitleForDisplay($p->post_title)); ?>
                            </a>
                            <span class="laca-post-meta">
                                <span class="laca-badge badge-date">Mới</span>
                                <?php echo esc_html($pt_label); ?> · <?php echo esc_html(get_the_date('d/m/Y', $p->ID)); ?>
                            </span>
                        </div>
                    </li>
                <?php endforeach; wp_reset_postdata(); ?>
                    <?php if ($new_posts->found_posts > $limit) : ?>
                        <li class="laca-pagination-row">
                            <button class="laca-page-btn prev" disabled>« Trước</button>
                            <span class="laca-page-info">Trang 1 / <?php echo ceil($new_posts->found_posts / $limit); ?></span>
                            <button class="laca-page-btn next">Sau »</button>
                        </li>
                    <?php endif; ?>
                <?php else : ?>
                    <li>Chưa có nội dung.</li>
                <?php endif; ?>
            </ul>
        </div>

        <div id="laca-updated" class="laca-tab-content" data-per-page="<?php echo $limit; ?>">
            <ul class="laca-post-list laca-paged-list">
                <?php if ($updated_posts) : foreach ($updated_posts as $index => $upost) : ?>
                    <?php
                    $pt_obj = get_post_type_object($upost->post_type);
                    $pt_label = $pt_obj ? $pt_obj->labels->singular_name : $upost->post_type;
                    ?>
                    <li class="laca-list-item" <?php echo ($index >= $limit) ? 'style="display:none;"' : ''; ?>>
                        <div class="laca-post-info">
                            <a href="<?php echo esc_url(get_edit_post_link($upost->ID)); ?>" class="laca-post-link">
                                <?php echo esc_html($this->decodeTitleForDisplay($upost->post_title)); ?>
                            </a>
                            <span class="laca-post-meta">
                                <span class="laca-badge badge-date">Cập nhật</span>
                                <?php echo esc_html($pt_label); ?> · <?php echo esc_html(mysql2date('d/m/Y', $upost->post_modified)); ?>
                            </span>
                        </div>
                    </li>
                <?php endforeach; ?>
                    <?php if ($updated_posts_found_rows > $limit) : ?>
                        <li class="laca-pagination-row">
                            <button class="laca-page-btn prev" disabled>« Trước</button>
                            <span class="laca-page-info">Trang 1 / <?php echo ceil($updated_posts_found_rows / $limit); ?></span>
                            <button class="laca-page-btn next">Sau »</button>
                        </li>
                    <?php endif; ?>
                <?php else : ?>
                    <li>Chưa có thay đổi.</li>
                <?php endif; ?>
            </ul>
        </div>

        <div id="laca-health" class="laca-tab-content" data-per-page="<?php echo $limit; ?>">
            <?php if ($last_scan) : ?>
                <div class="laca-scan-info" style="font-size:10px;color:#888;margin-bottom:8px;padding-left:4px;">
                    🕒 Quét toàn diện gần nhất: <?php echo date_i18n('H:i d/m/Y', strtotime($last_scan)); ?>
                </div>
            <?php endif; ?>
            <ul class="laca-post-list laca-paged-list">
                <?php 
                if (!empty($health_raw)) : 
                    foreach ($health_raw as $index => $issue) : 
                    $pt_obj = get_post_type_object($issue['post_type']);
                    $pt_label = $pt_obj ? $pt_obj->labels->singular_name : $issue['post_type'];
                    $hidden = ($index >= $limit) ? 'style="display:none;"' : '';
                ?>
                    <li class="laca-list-item" <?php echo $hidden; ?>>
                        <div class="laca-post-info">
                            <a href="<?php echo esc_url(get_edit_post_link($issue['ID'])); ?>" class="laca-post-link">
                                <?php echo esc_html($this->decodeTitleForDisplay($issue['post_title'])); ?>
                            </a>
                            <span class="laca-post-meta">
                                <?php 
                                    $errs = isset($issue['errors']) ? (array)$issue['errors'] : [];
                                    foreach ($errs as $err_label) : 
                                ?>
                                    <span class="laca-issue-tag" style="background:#fcf0f1;color:#d63638;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:600;"><?php echo esc_html($err_label); ?></span>
                                <?php endforeach; ?>
                                <span class="laca-text-muted" style="font-size:11px;"><?php echo esc_html($pt_label); ?></span>
                            </span>
                        </div>
                    </li>
                <?php endforeach; ?>
                    <?php if ($health_count > $limit) : ?>
                        <li class="laca-pagination-row">
                            <button class="laca-page-btn prev" disabled>« Trước</button>
                            <span class="laca-page-info">Trang 1 / <?php echo ceil($health_count / $limit); ?></span>
                            <button class="laca-page-btn next">Sau »</button>
                        </li>
                    <?php endif; ?>
                <?php else : ?>
                    <li>🎉 Tuyệt vời! Không tìm thấy vấn đề nào.</li>
                <?php endif; ?>
            </ul>
        </div>

        <div id="laca-views" class="laca-tab-content" data-per-page="<?php echo $limit; ?>">
            <ul class="laca-post-list laca-paged-list">
                <?php if ($view_posts->have_posts()) : foreach ($view_posts->posts as $index => $vp) : 
                    $views = get_post_meta($vp->ID, self::VIEW_COUNT_META, true) ?: 0;
                    $pt_obj = get_post_type_object($vp->post_type);
                    $pt_label = $pt_obj ? $pt_obj->labels->singular_name : $vp->post_type;
                ?>
                    <li class="laca-list-item" <?php echo ($index >= $limit) ? 'style="display:none;"' : ''; ?>>
                        <div class="laca-post-info">
                            <a href="<?php echo esc_url(get_edit_post_link($vp->ID)); ?>" class="laca-post-link">
                                <?php echo esc_html($this->decodeTitleForDisplay($vp->post_title)); ?>
                            </a>
                            <span class="laca-post-meta">
                                <span class="laca-badge badge-views"><?php echo (int) $views; ?> xem</span>
                                <?php echo esc_html($pt_label); ?>
                            </span>
                        </div>
                    </li>
                <?php endforeach; wp_reset_postdata(); ?>
                    <?php if ($view_posts->found_posts > $limit) : ?>
                        <li class="laca-pagination-row">
                            <button class="laca-page-btn prev" disabled>« Trước</button>
                            <span class="laca-page-info">Trang 1 / <?php echo ceil($view_posts->found_posts / $limit); ?></span>
                            <button class="laca-page-btn next">Sau »</button>
                        </li>
                    <?php endif; ?>
                <?php else : ?>
                    <li>Chưa ghi nhận lượt xem.</li>
                <?php endif; ?>
            </ul>
        </div>

        <?php if ($seo_meta_key) : ?>
        <div id="laca-seo" class="laca-tab-content" data-per-page="<?php echo $limit; ?>">
            <ul class="laca-post-list laca-paged-list">
                <?php if ($low_seo_posts && $low_seo_posts->have_posts()) : foreach ($low_seo_posts->posts as $index => $sp) : 
                    $score = get_post_meta($sp->ID, $seo_meta_key, true);
                    $pt_obj = get_post_type_object($sp->post_type);
                    $pt_label = $pt_obj ? $pt_obj->labels->singular_name : $sp->post_type;
                ?>
                    <li class="laca-list-item" <?php echo ($index >= $limit) ? 'style="display:none;"' : ''; ?>>
                        <div class="laca-post-info">
                            <a href="<?php echo esc_url(get_edit_post_link($sp->ID)); ?>" class="laca-post-link">
                                <?php echo esc_html($this->decodeTitleForDisplay($sp->post_title)); ?>
                            </a>
                            <span class="laca-post-meta">
                                <span class="laca-badge badge-seo"><?php echo (int) $score; ?>đ</span>
                                <?php echo esc_html($pt_label); ?> · Cần tối ưu SEO
                            </span>
                        </div>
                    </li>
                <?php endforeach; wp_reset_postdata(); ?>
                    <?php if ($low_seo_posts->found_posts > $limit) : ?>
                        <li class="laca-pagination-row">
                            <button class="laca-page-btn prev" disabled>« Trước</button>
                            <span class="laca-page-info">Trang 1 / <?php echo ceil($low_seo_posts->found_posts / $limit); ?></span>
                            <button class="laca-page-btn next">Sau »</button>
                        </li>
                    <?php endif; ?>
                <?php else : ?>
                    <li>Mọi thứ đều ổn!</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var select = document.querySelector('.laca-report-select');
                var contents = document.querySelectorAll('.laca-tab-content');
                if (select) {
                    select.addEventListener('change', function() {
                        contents.forEach(c => c.classList.remove('active'));
                        document.getElementById(this.value).classList.add('active');
                    });
                }

                // Generalized Pagination logic for all tabs
                document.querySelectorAll('.laca-tab-content').forEach(function(wrap) {
                    var perPage = parseInt(wrap.getAttribute('data-per-page') || 5);
                    var curPage = 1;
                    
                    var nextBtn = wrap.querySelector('.next');
                    var prevBtn = wrap.querySelector('.prev');
                    var pageInfo = wrap.querySelector('.laca-page-info');
                    var items = wrap.querySelectorAll('.laca-list-item');

                    function update() {
                        var maxPage = Math.ceil(items.length / perPage);
                        var start = (curPage - 1) * perPage;
                        var end = start + perPage;

                        items.forEach(function(item, idx) {
                            item.style.setProperty('display', (idx >= start && idx < end) ? 'flex' : 'none', 'important');
                        });

                        if (prevBtn) prevBtn.disabled = (curPage <= 1);
                        if (nextBtn) nextBtn.disabled = (curPage >= maxPage);
                        if (pageInfo) pageInfo.innerText = 'Trang ' + curPage + ' / ' + (maxPage || 1);
                    }

                    if (nextBtn) nextBtn.addEventListener('click', function(e) { 
                        e.preventDefault(); 
                        var max = Math.ceil(items.length / perPage);
                        if (curPage < max) { curPage++; update(); } 
                    });
                    if (prevBtn) prevBtn.addEventListener('click', function(e) { 
                        e.preventDefault(); 
                        if (curPage > 1) { curPage--; update(); } 
                    });
                    
                    update();
                });
            });
        </script>
        <?php
    }

    /**
     * Adds a "Views" column to all dashboard post types.
     */
    public function addViewsColumn()
    {
        $post_types = $this->getDashboardPostTypes();
        if (!in_array('page', $post_types)) $post_types[] = 'page';

        foreach ($post_types as $post_type) {
            // Header
            add_filter("manage_{$post_type}_posts_columns", function ($columns) {
                // Try to find a good position: after featured_image, lacadev_thumb, or cb
                $pos = 1;
                if (isset($columns['featured_image'])) $pos = array_search('featured_image', array_keys($columns)) + 1;
                elseif (isset($columns['lacadev_thumb'])) $pos = array_search('lacadev_thumb', array_keys($columns)) + 1;
                elseif (isset($columns['cb'])) $pos = 1;

                return insertArrayAtPosition($columns, ['laca_views' => 'Lượt xem'], $pos);
            }, 10000); // High priority to run after AbstractPostType

            // Sortable
            add_filter("manage_edit-{$post_type}_sortable_columns", function ($sortable) {
                $sortable['laca_views'] = 'laca_views';
                return $sortable;
            });
        }

        // Data rendering for all post types (common hook)
        add_action('manage_posts_custom_column', function ($column, $post_id) {
            if ($column === 'laca_views') {
                $views = get_post_meta($post_id, '_gm_view_count', true) ?: 0;
                echo '<div class="laca-views-col">';
                echo '<span class="dashicons dashicons-visibility"></span>';
                echo '<strong>' . number_format_i18n($views) . '</strong>';
                echo '</div>';
            }
        }, 10, 2);
        
        // Data rendering specifically for pages
        add_action('manage_pages_custom_column', function ($column, $post_id) {
            if ($column === 'laca_views') {
                $views = get_post_meta($post_id, '_gm_view_count', true) ?: 0;
                echo '<div class="laca-views-col">';
                echo '<span class="dashicons dashicons-visibility"></span>';
                echo '<strong>' . number_format_i18n($views) . '</strong>';
                echo '</div>';
            }
        }, 10, 2);

        // Sorting logic
        add_action('pre_get_posts', function ($query) {
            if (!is_admin() || !$query->is_main_query() || $query->get('orderby') !== 'laca_views') {
                return;
            }
            $query->set('meta_key', '_gm_view_count');
            $query->set('orderby', 'meta_value_num');
        });
    }

    /**
     * Enriches WooCommerce product list with helpful columns.
     */
    public function enrichProductList()
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_filter('manage_edit-product_columns', function ($columns) {
            $new_columns = [];
            foreach ($columns as $key => $value) {
                if ($key === 'name') {
                    $new_columns['lacadev_thumb'] = 'Ảnh';
                }
                $new_columns[$key] = $value;
                if ($key === 'cb') {
                    $new_columns['lacadev_id'] = 'ID';
                }
            }
            return $new_columns;
        }, 20);

        add_action('manage_product_posts_custom_column', function ($column, $post_id) {
            if ($column === 'lacadev_id') {
                echo '<span style="color: #999; font-family: monospace;">#' . esc_html($post_id) . '</span>';
            }
            if ($column === 'lacadev_thumb') {
                echo get_the_post_thumbnail($post_id, [40, 40], ['style' => 'border-radius: 4px; border: 1px solid #ddd;']);
            }
        }, 10, 2);
    }

    /**
     * Enriches Post list with more data visibility.
     */
    public function enrichPostList()
    {
        add_filter('manage_post_posts_columns', function ($columns) {
            $columns['post_id_val'] = 'ID';
            return $columns;
        });

        add_action('manage_post_posts_custom_column', function ($column, $post_id) {
            if ($column === 'post_id_val') {
                echo '<span style="color: #999;">' . esc_html($post_id) . '</span>';
            }
        }, 10, 2);
    }

    /**
     * Enable post/product duplication.
     */
    public function enableDuplication()
    {
        $add_duplicate_link = function ($actions, $post) {
            if (current_user_can('edit_posts')) {
                $actions['duplicate'] = '<a href="' . wp_nonce_url(admin_url('admin-post.php?action=lacadev_duplicate_post&post=' . $post->ID), 'lacadev_duplicate_post_nonce') . '" title="Sao chép nội dung này">Sao chép</a>';
            }
            return $actions;
        };

        add_filter('post_row_actions', $add_duplicate_link, 10, 2);
        add_filter('page_row_actions', $add_duplicate_link, 10, 2);

        add_action('admin_post_lacadev_duplicate_post', function () {
            if (!isset($_GET['post']) || !current_user_can('edit_posts')) {
                wp_die('No post to duplicate!');
            }

            check_admin_referer('lacadev_duplicate_post_nonce');

            $post_id = absint($_GET['post']);
            $post = get_post($post_id);

            if ($post) {
                $args = [
                    'post_author'    => get_current_user_id(),
                    'post_content'   => $post->post_content,
                    'post_excerpt'   => $post->post_excerpt,
                    'post_status'    => 'draft',
                    'post_title'     => $post->post_title . ' (Bản sao)',
                    'post_type'      => $post->post_type,
                    'post_parent'    => $post->post_parent,
                    'menu_order'     => $post->menu_order
                ];

                $new_post_id = wp_insert_post($args);

                $taxonomies = get_object_taxonomies($post->post_type);
                foreach ($taxonomies as $taxonomy) {
                    $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'slugs']);
                    wp_set_object_terms($new_post_id, $terms, $taxonomy);
                }

                $meta = get_post_custom($post_id);
                foreach ($meta as $key => $values) {
                    foreach ($values as $value) {
                        add_post_meta($new_post_id, $key, $value);
                    }
                }

                wp_redirect(admin_url('edit.php?post_type=' . $post->post_type));
                exit;
            }
        });
    }

    /**
     * Adds a dedicated Help menu.
     */
    public function addClientHelpMenu()
    {
        add_action('admin_menu', function () {
            add_menu_page(
                'Hướng dẫn sử dụng',
                'HD Sử dụng',
                'read',
                'lacadev-help',
                [$this, 'renderHelpPage'],
                'dashicons-format-video',
                2
            );
        });
    }

    public function renderHelpPage()
    {
        $page_title = carbon_get_theme_option('help_page_title') ?: 'Hướng dẫn quản trị Website Professional';
        $page_intro = carbon_get_theme_option('help_page_intro') ?: 'Chào mừng bạn đến với hệ thống quản trị website nâng cao. Hệ thống đã được tối ưu để bạn quản lý nội dung dễ dàng nhất.';
        $blocks = carbon_get_theme_option('help_page_blocks');
        
        $phone = carbon_get_theme_option('help_support_phone') ?: (defined('AUTHOR') ? AUTHOR['phone_number'] : '');
        $email = carbon_get_theme_option('help_support_email') ?: (defined('AUTHOR') ? AUTHOR['email'] : '');
        $website = carbon_get_theme_option('help_support_website') ?: (defined('AUTHOR') ? AUTHOR['website'] : '');

        ?>
        <style>
            .laca-help-wrap { padding: 20px; }
            .laca-help-header { display: flex; align-items: center; gap: 12px; font-weight: 800; margin-bottom: 20px; }
            .laca-help-header span { font-size: 36px; }
            .laca-help-intro { font-size: 16px; color: var(--laca-text-muted); max-width: 800px; line-height: 1.6; margin-bottom: 30px; }
            .laca-help-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
            .laca-help-card { background: #fff; padding: 25px; border-radius: var(--laca-radius); box-shadow: var(--laca-shadow-sm); border-top: 4px solid var(--laca-primary); transition: transform 0.2s; }
            .laca-help-card:hover { transform: translateY(-3px); }
            .laca-help-card h3 { margin-top: 0; font-size: 18px; color: var(--laca-text-main); }
            .laca-help-card-content { line-height: 1.7; color: #3c434a; }
            .laca-help-footer { margin-top: 40px; background: #1d2327; padding: 30px; border-radius: var(--laca-radius); color: #fff; }
            .laca-help-footer h3 { margin-top: 0; color: #72aee6; }
            .laca-help-footer-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px; }
            .laca-help-footer a { color: #72aee6; text-decoration: none; }
            .laca-help-footer a:hover { text-decoration: underline; }
        </style>
        <div class="wrap laca-help-wrap">
            <h1 class="laca-help-header">
                <span>📖</span> 
                <?php echo esc_html($page_title); ?>
            </h1>
            <p class="laca-help-intro"><?php echo nl2br(esc_html($page_intro)); ?></p>
            
            <div class="laca-help-grid">
                <?php if (!empty($blocks)) : ?>
                    <?php foreach ($blocks as $block) : ?>
                        <div class="laca-help-card" style="border-top-color: <?php echo esc_attr($block['border_color'] ?: '#2271b1'); ?>;">
                            <h3><?php echo esc_html($block['title']); ?></h3>
                            <div class="laca-help-card-content">
                                <?php echo wpautop(wp_kses_post($block['content'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="laca-help-card">
                        <h3>📝 Hướng dẫn mặc định</h3>
                        <p>Vui lòng vào <strong>Laca Admin > Quản trị & HD Sử dụng</strong> để cập nhật nội dung hướng dẫn cho khách hàng.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="laca-help-footer">
                <h3>📞 Hỗ trợ kỹ thuật LacaDev</h3>
                <p>Mọi vấn đề về vận hành hoặc yêu cầu nâng cấp, vui lòng liên hệ:</p>
                <div class="laca-help-footer-grid">
                    <div><strong>Hotline/Zalo:</strong><br><?php echo esc_html($phone); ?></div>
                    <div><strong>Email:</strong><br><?php echo esc_html($email); ?></div>
                    <div><strong>Website:</strong><br><a href="<?php echo esc_url($website); ?>" target="_blank"><?php echo esc_html($website); ?></a></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Simplifies the admin for non-developer roles.
     */
    public function simplifyMerchantAdmin()
    {
        add_action('admin_head', function () {
            if (current_user_can('manage_options') && !in_array(wp_get_current_user()->user_login, ['lacadev'])) {
                echo '<style>
                    #toplevel_page_laca-admin { display: none !important; }
                    #menu-settings, #menu-tools, #menu-plugins { display: none !important; }
                    .update-nag, .notice-warning, .notice-info.is-dismissible { display: none !important; }
                    #contextual-help-link-wrap { display: none !important; }
                    #wp-admin-bar-updates, #wp-admin-bar-comments, #wp-admin-bar-new-content { display: none !important; }
                </style>';
            }
        });
    }
}

