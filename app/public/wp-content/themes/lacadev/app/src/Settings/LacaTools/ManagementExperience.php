<?php

namespace App\Settings\LacaTools;

/**
 * ManagementExperience Class
 * Handles the administrative UI/UX enhancements for clients.
 */
class ManagementExperience
{
    public function __construct()
    {
        if (!is_admin()) {
            return;
        }

        // Dashboard enhancements
        $this->addDashboardSummaryWidget();
        $this->addContentTrackerWidget();
        
        // List table enhancements
        $this->enrichProductList();
        $this->enrichPostList();
        
        // Navigation & UX
        $this->addClientHelpMenu();
        
        // General cleanup for non-admins
        $this->simplifyMerchantAdmin();

        // Duplication support
        $this->enableDuplication();
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
     * Renders the dashboard widget content.
     */
    public function renderDashboardWidget()
    {
        $posts_count = wp_count_posts()->publish;
        $pages_count = wp_count_posts('page')->publish;
        
        // Lấy tất cả CPT đang có (public)
        $args = [
            'public'   => true,
            '_builtin' => false, // Chỉ lấy các CPT tự tạo, bỏ qua post, page, attachment
        ];
        $post_types = get_post_types($args, 'objects');

        $cpt_stats = '';
        foreach ($post_types as $post_type) {
            // Bỏ qua product nếu có WooCommerce vì đã xử lý riêng hoặc muốn gộp chung tùy ý
            if (class_exists('WooCommerce') && $post_type->name === 'product') continue;
            
            $count = wp_count_posts($post_type->name)->publish;
            $cpt_stats .= "
                <div class='stat-item'>
                    <span class='stat-value'>{$count}</span>
                    <span class='stat-label'>{$post_type->label}</span>
                </div>
            ";
        }
        
        $woo_stats = '';
        if (class_exists('WooCommerce')) {
            $products_count = wp_count_posts('product')->publish;
            $orders_count = wc_get_orders(['status' => 'completed', 'return' => 'count']);
            $woo_stats = "
                <div class='stat-item'>
                    <span class='stat-value'>{$products_count}</span>
                    <span class='stat-label'>Sản phẩm</span>
                </div>
                <div class='stat-item'>
                    <span class='stat-value'>{$orders_count}</span>
                    <span class='stat-label'>Đơn hàng</span>
                </div>
            ";
        }

        $maintenance_status = (get_option('_is_maintenance') === 'yes') ? '<span style="color: #d63638;">🔴 Đang Bật</span>' : '<span style="color: #27ae60;">🟢 Đang Tắt</span>';
        
        ?>
        <style>
            .lacadev-dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-bottom: 20px; }
            .lacadev-dashboard-grid .stat-item { background: #f8f9fa; padding: 12px 8px; border-radius: 12px; border: 1px solid #e2e8f0; text-align: center; transition: all 0.2s; }
            .stat-item:hover { transform: translateY(-2px); border-color: var(--primary-color-ad, #2271b1); background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
            .lacadev-dashboard-grid .stat-value { display: block; font-size: 22px; font-weight: 800; color: var(--primary-color-ad, #1d2327); line-height: 1.2; }
            .lacadev-dashboard-grid .stat-label { font-size: 9px; text-transform: uppercase; color: #646970; letter-spacing: 0.5px; font-weight: 700; display: block; margin-top: 4px; }
            .lacadev-actions-list { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
            .lacadev-btn-quick { display: flex; align-items: center; justify-content: center; background: #fff; border: 1px solid #dcdcde; padding: 10px; border-radius: 10px; text-decoration: none; color: #2c3338; font-weight: 600; transition: all 0.2s; font-size: 13px; }
            .lacadev-btn-quick:hover { background: var(--bg-color-ad, #f0f6fb); border-color: var(--primary-color-ad, #2271b1); color: var(--primary-color-ad, #2271b1); }
            .lacadev-btn-quick span { margin-right: 8px; font-size: 16px; }
            .hub-section-title { font-size: 13px; font-weight: 700; margin: 15px 0 10px; color: #1d2327; border-bottom: 2px solid #f1f1f1; padding-bottom: 5px; }
        </style>
        
        <div class="lacadev-dashboard-grid">
            <div class="stat-item">
                <span class="stat-value"><?php echo $posts_count; ?></span>
                <span class="stat-label">Bài viết</span>
            </div>
            <div class="stat-item">
                <span class="stat-value"><?php echo $pages_count; ?></span>
                <span class="stat-label">Trang</span>
            </div>
            <?php echo $cpt_stats; ?>
            <?php echo $woo_stats; ?>
            <div class="stat-item">
                <span class="stat-value"><?php echo $maintenance_status; ?></span>
                <span class="stat-label">Bảo trì</span>
            </div>
        </div>

        <div class="hub-section-title">Thao tác nhanh</div>
        <div class="lacadev-actions-list">
            <a href="<?php echo admin_url('post-new.php'); ?>" class="lacadev-btn-quick">
                <span>📝</span> Viết bài mới
            </a>
            <?php if (class_exists('WooCommerce')) : ?>
                <a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" class="lacadev-btn-quick">
                    <span>🎁</span> Thêm sản phẩm mới
                </a>
            <?php endif; ?>
            <a href="<?php echo admin_url('admin.php?page=app-theme-options.php'); ?>" class="lacadev-btn-quick">
                <span>⚙️</span> Cấu hình Theme
            </a>
        </div>
        <?php
    }

    /**
     * Renders the Content Tracker widget.
     */
    public function renderContentTrackerWidget()
    {
        $allow_post_types = carbon_get_theme_option('dashboard_widget_post_types');
        if (empty($allow_post_types)) {
            $allow_post_types = ['post'];
        }
        
        $limit = (int)carbon_get_theme_option('dashboard_widget_limit');
        if ($limit <= 0) {
            $limit = 5;
        }

        // 1. Mới xuất bản
        $new_posts = new \WP_Query([
            'post_type' => $allow_post_types,
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'post_status' => 'publish'
        ]);

        // 2. Mới cập nhật
        global $wpdb;
        $post_types_in = "'" . implode("','", array_map('esc_sql', (array)$allow_post_types)) . "'";
        $updated_posts = $wpdb->get_results("
            SELECT ID, post_title, post_modified, post_type 
            FROM $wpdb->posts 
            WHERE post_type IN ($post_types_in) 
            AND post_status = 'publish' 
            AND post_modified != post_date
            ORDER BY post_modified DESC 
            LIMIT $limit
        ");

        // 3. Đọc nhiều
        $view_posts = new \WP_Query([
            'post_type' => $allow_post_types,
            'posts_per_page' => $limit,
            'meta_key' => '_gm_view_count',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'post_status' => 'publish'
        ]);

        // 4. SEO thấp
        $seo_meta_key = '';
        if (defined('RANK_MATH_VERSION')) {
            $seo_meta_key = 'rank_math_seo_score';
        } elseif (defined('WPSEO_VERSION')) {
            $seo_meta_key = '_yoast_wpseo_linkdex';
        }

        $low_seo_posts = null;
        if ($seo_meta_key) {
            $low_seo_posts = new \WP_Query([
                'post_type' => $allow_post_types,
                'posts_per_page' => $limit,
                'meta_key' => $seo_meta_key,
                'orderby' => 'meta_value_num',
                'order' => 'ASC',
                'post_status' => 'publish',
                'meta_query' => [
                    ['key' => $seo_meta_key, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC']
                ]
            ]);
        }

        ?>
        <style>
            .laca-tabs { display: flex; border-bottom: 1px solid #dcdcde; margin: -12px -12px 12px -12px; background: #fff; border-top-left-radius: 2px; border-top-right-radius: 2px; }
            .laca-tab { padding: 10px 15px; cursor: pointer; color: #646970; font-weight: 600; border-bottom: 2px solid transparent; transition: all 0.2s; font-size: 12px; }
            .laca-tab:hover { color: #2271b1; background: #f6f7f7; }
            .laca-tab.active { border-bottom: 2px solid var(--primary-color-ad, #2271b1); color: var(--primary-color-ad, #1d2327); background: #fff; }
            .laca-tab-content { display: none; padding: 0; }
            .laca-tab-content.active { display: block; }
            .laca-post-list { margin: 0; padding: 0; list-style: none; }
            .laca-post-list li { padding: 10px 0; border-bottom: 1px solid #f0f0f1; display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
            .laca-post-list li:last-child { border-bottom: none; }
            .laca-post-info { flex: 1; min-width: 0; }
            .laca-post-link { text-decoration: none; color: #2271b1; font-weight: 600; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 13px; margin-bottom: 3px; }
            .laca-post-link:hover { color: #135e96; }
            .laca-post-meta { font-size: 11px; color: #646970; display: block; }
            .laca-badge { font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 700; color: #fff; }
            .badge-views { background: #2271b1; }
            .badge-seo { background: #d63638; }
            .badge-date { background: #f0f0f1; color: #646970; }
        </style>

        <div class="laca-tabs">
            <div class="laca-tab active" data-target="laca-new">Mới nhất</div>
            <div class="laca-tab" data-target="laca-updated">Cập nhật</div>
            <div class="laca-tab" data-target="laca-views">Xem nhiều</div>
            <?php if ($seo_meta_key): ?>
                <div class="laca-tab" data-target="laca-seo">Cần tối ưu SEO</div>
            <?php endif; ?>
        </div>

        <!-- Tab: Mới tạo -->
        <div id="laca-new" class="laca-tab-content active">
            <ul class="laca-post-list">
                <?php if ($new_posts->have_posts()) : while ($new_posts->have_posts()) : $new_posts->the_post(); ?>
                    <li>
                        <div class="laca-post-info">
                            <a href="<?php echo get_edit_post_link(); ?>" class="laca-post-link"><?php the_title(); ?></a>
                            <span class="laca-post-meta"><?php echo get_the_date('d/m/Y'); ?></span>
                        </div>
                        <span class="laca-badge badge-date">NEW</span>
                    </li>
                <?php endwhile; wp_reset_postdata(); else : ?>
                    <li>Chưa có nội dung.</li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Tab: Cập nhật -->
        <div id="laca-updated" class="laca-tab-content">
            <ul class="laca-post-list">
                <?php if ($updated_posts) : foreach ($updated_posts as $upost) : ?>
                    <li>
                        <div class="laca-post-info">
                            <a href="<?php echo get_edit_post_link($upost->ID); ?>" class="laca-post-link"><?php echo esc_html($upost->post_title); ?></a>
                            <span class="laca-post-meta">Sửa lỗi/Cập nhật nội dung</span>
                        </div>
                        <span class="laca-badge badge-date"><?php echo human_time_diff(strtotime($upost->post_modified), current_time('timestamp')); ?></span>
                    </li>
                <?php endforeach; else : ?>
                    <li>Chưa có thay đổi nào.</li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Tab: Views -->
        <div id="laca-views" class="laca-tab-content">
            <ul class="laca-post-list">
                <?php if ($view_posts->have_posts()) : while ($view_posts->have_posts()) : $view_posts->the_post(); ?>
                    <?php $views = get_post_meta(get_the_ID(), '_gm_view_count', true) ?: 0; ?>
                    <li>
                        <div class="laca-post-info">
                            <a href="<?php echo get_edit_post_link(); ?>" class="laca-post-link"><?php the_title(); ?></a>
                            <span class="laca-post-meta">Lượt xem tích lũy</span>
                        </div>
                        <span class="laca-badge badge-views">👁️ <?php echo number_format($views); ?></span>
                    </li>
                <?php endwhile; wp_reset_postdata(); else : ?>
                    <li>Chưa ghi nhận lượt xem.</li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Tab: SEO -->
        <?php if ($seo_meta_key): ?>
        <div id="laca-seo" class="laca-tab-content">
            <ul class="laca-post-list">
                <?php if ($low_seo_posts && $low_seo_posts->have_posts()) : while ($low_seo_posts->have_posts()) : $low_seo_posts->the_post(); ?>
                    <?php $score = get_post_meta(get_the_ID(), $seo_meta_key, true); ?>
                    <li>
                        <div class="laca-post-info">
                            <a href="<?php echo get_edit_post_link(); ?>" class="laca-post-link"><?php the_title(); ?></a>
                            <span class="laca-post-meta">Cần cải thiện các tiêu chí SEO</span>
                        </div>
                        <span class="laca-badge badge-seo"><?php echo $score; ?>đ</span>
                    </li>
                <?php endwhile; wp_reset_postdata(); else : ?>
                    <li>Mọi thứ đều ổn!</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var tabs = document.querySelectorAll('.laca-tab');
                var contents = document.querySelectorAll('.laca-tab-content');
                tabs.forEach(function(tab) {
                    tab.addEventListener('click', function() {
                        tabs.forEach(t => t.classList.remove('active'));
                        contents.forEach(c => c.classList.remove('active'));
                        this.classList.add('active');
                        document.getElementById(this.getAttribute('data-target')).classList.add('active');
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Enriches WooCommerce product list with helpful columns.
     */
    public function enrichProductList()
    {
        if (!class_exists('WooCommerce')) return;

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
                echo "<span style='color: #999; font-family: monospace;'>#{$post_id}</span>";
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
            $columns['word_count'] = 'Độ dài';
            $columns['post_id_val'] = 'ID';
            return $columns;
        });

        add_action('manage_post_posts_custom_column', function ($column, $post_id) {
            if ($column === 'word_count') {
                $content = get_post_field('post_content', $post_id);
                $word_count = str_word_count(strip_tags($content));
                $color = ($word_count < 300) ? '#d63638' : '#2271b1';
                echo "<span style='color: {$color}; font-weight: 600;'>{$word_count} từ</span>";
            }
            if ($column === 'post_id_val') {
                echo "<span style='color: #999;'>{$post_id}</span>";
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
        <div class="wrap">
            <h1 style="display: flex; align-items: center; gap: 12px; font-weight: 800;">
                <span style="font-size: 36px;">📖</span> 
                <?php echo esc_html($page_title); ?>
            </h1>
            <p style="font-size: 16px; color: #646970; max-width: 800px;"><?php echo nl2br(esc_html($page_intro)); ?></p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-top: 30px;">
                <?php if (!empty($blocks)) : ?>
                    <?php foreach ($blocks as $block) : ?>
                        <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-top: 4px solid <?php echo esc_attr($block['border_color'] ?: '#2271b1'); ?>;">
                            <h3 style="margin-top: 0; font-size: 18px;"><?php echo esc_html($block['title']); ?></h3>
                            <div style="line-height: 1.7; color: #3c434a;">
                                <?php echo wpautop(wp_kses_post($block['content'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-top: 4px solid #2271b1;">
                        <h3 style="margin-top: 0;">📝 Hướng dẫn mặc định</h3>
                        <p>Vui lòng vào <strong>Laca Admin > Quản trị & HD Sử dụng</strong> để cập nhật nội dung hướng dẫn cho khách hàng.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 40px; background: #1d2327; padding: 30px; border-radius: 12px; color: #fff;">
                <h3 style="margin-top: 0; color: #72aee6;">📞 Hỗ trợ kỹ thuật LacaDev</h3>
                <p>Mọi vấn đề về vận hành hoặc yêu cầu nâng cấp, vui lòng liên hệ:</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 20px;">
                    <div><strong>Hotline/Zalo:</strong><br><?php echo esc_html($phone); ?></div>
                    <div><strong>Email:</strong><br><?php echo esc_html($email); ?></div>
                    <div><strong>Website:</strong><br><a href="<?php echo esc_url($website); ?>" style="color: #72aee6; text-decoration: none;" target="_blank"><?php echo esc_html($website); ?></a></div>
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
