<?php

if (!defined('ABSPATH')) {
    exit;
}

class Laca_Bot_Tools {

    /**
     * Get a guide of what's on the site (Post Types, etc.)
     */
    public static function get_site_guide() {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $post_types = get_post_types(['public' => true], 'objects');
        $pt_list = [];
        foreach ($post_types as $slug => $obj) {
            if (in_array($slug, ['attachment', 'revision', 'nav_menu_item'])) {
                continue;
            }
            $pt_list[] = [
                'name' => $obj->labels->name,
                'slug' => $slug,
                'description' => $obj->description ?: "Chứa các nội dung thuộc loại " . $obj->labels->name,
                'edit_url' => admin_url("edit.php?post_type=$slug")
            ];
        }

        // Navigation menus giúp AI hiểu cấu trúc luồng điều hướng của website
        $menus = [];
        $locations = get_nav_menu_locations();
        $registered_menus = wp_get_nav_menus();
        foreach ($registered_menus as $menu) {
            $items = wp_get_nav_menu_items($menu->term_id);
            $menu_items = [];
            if (!empty($items)) {
                foreach ($items as $item) {
                    $menu_items[] = [
                        'title' => $item->title,
                        'url' => $item->url,
                        'type' => $item->object,
                        'parent_id' => (int) $item->menu_item_parent
                    ];
                }
            }

            $menu_locations = [];
            foreach ($locations as $location_key => $location_menu_id) {
                if ((int) $location_menu_id === (int) $menu->term_id) {
                    $menu_locations[] = $location_key;
                }
            }

            $menus[] = [
                'name' => $menu->name,
                'slug' => $menu->slug,
                'locations' => $menu_locations,
                'items' => $menu_items
            ];
        }

        // Các trang chính thường nằm trong hành trình người dùng
        $key_pages = [];
        $front_id = (int) get_option('page_on_front');
        if ($front_id) {
            $key_pages[] = [
                'label' => 'Trang chủ',
                'id' => $front_id,
                'title' => get_the_title($front_id),
                'link' => get_permalink($front_id)
            ];
        }
        $posts_id = (int) get_option('page_for_posts');
        if ($posts_id) {
            $key_pages[] = [
                'label' => 'Trang blog',
                'id' => $posts_id,
                'title' => get_the_title($posts_id),
                'link' => get_permalink($posts_id)
            ];
        }

        // Một số plugin tính năng quan trọng để AI nắm rõ mô hình kinh doanh / luồng xử lý
        $feature_plugins = [];
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            $feature_plugins[] = 'WooCommerce (Cửa hàng / Thanh toán)';
        }
        if (is_plugin_active('contact-form-7/wp-contact-form-7.php')) {
            $feature_plugins[] = 'Contact Form 7 (Form liên hệ)';
        }
        if (is_plugin_active('elementor/elementor.php')) {
            $feature_plugins[] = 'Elementor (Page Builder)';
        }
        if (is_plugin_active('learnpress/learnpress.php') || is_plugin_active('tutor/tutor.php')) {
            $feature_plugins[] = 'LMS (Khóa học trực tuyến)';
        }

        $seo_plugins = [];
        if (is_plugin_active('rank-math/rank-math.php')) {
            $seo_plugins[] = 'RankMath SEO';
        } elseif (is_plugin_active('wordpress-seo/wp-seo.php')) {
            $seo_plugins[] = 'Yoast SEO';
        }

        return [
            'site_name' => get_bloginfo('name'),
            'site_description' => get_bloginfo('description'),
            'public_post_types' => $pt_list,
            'menus' => $menus,
            'key_pages' => $key_pages,
            'seo_plugins' => $seo_plugins,
            'feature_plugins' => $feature_plugins,
            'dna' => self::get_site_dna(),
            'instructions' => "Quản trị viên có thể quản lý các nội dung này trong menu bên trái. Các menu, trang chủ, blog và plugin tính năng cho biết luồng điều hướng và quy trình kinh doanh chính trên website."
        ];
    }

    /**
     * Search site content (Public & Admin)
     */
    public static function search_site_content($args) {
        $keyword = $args['keyword'] ?? '';
        $post_type = $args['post_type'] ?? 'any';

        if (empty($keyword)) {
            return ['error' => 'Keyword is required'];
        }

        $query_args = [
            's' => $keyword,
            'post_type' => $post_type,
            'posts_per_page' => 10,
            'post_status' => 'publish'
        ];

        $query = new WP_Query($query_args);
        $results = [];

        $is_admin = current_user_can('manage_options');

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $item = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'type' => get_post_type(),
                    'link' => get_the_permalink(),
                    'excerpt' => wp_trim_words(get_the_excerpt(), 40, '...')
                ];

                // Đính kèm taxonomy để AI hiểu rõ hơn ngữ cảnh nội dung (chủ đề, danh mục, thẻ...)
                $tax_info = [];
                $taxonomies = get_object_taxonomies(get_post_type(), 'objects');
                foreach ($taxonomies as $tax_slug => $tax_obj) {
                    $terms = get_the_terms(get_the_ID(), $tax_slug);
                    if (!empty($terms) && !is_wp_error($terms)) {
                        $tax_info[$tax_slug] = wp_list_pluck($terms, 'name');
                    }
                }
                if (!empty($tax_info)) {
                    $item['taxonomies'] = $tax_info;
                }

                if ($is_admin) {
                    $item['edit_link'] = get_edit_post_link(get_the_ID());
                }

                $results[] = $item;
            }
            wp_reset_postdata();
        }

        return [
            'found' => count($results),
            'results' => $results
        ];
    }

    /**
     * Find SEO issues (Placeholder for now)
     */
    public static function admin_find_seo_issues() {
        // Simple logic: find posts with low SEO score if RankMath is active
        $results = [];
        if (is_plugin_active('rank-math/rank-math.php')) {
            global $wpdb;
            $low_scores = $wpdb->get_results("
                SELECT post_id, meta_value as score 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = 'rank_math_seo_score' 
                AND CAST(meta_value AS UNSIGNED) < 50 
                LIMIT 5
            ");

            foreach ($low_scores as $row) {
                $results[] = [
                    'id' => $row->post_id,
                    'title' => get_the_title($row->post_id),
                    'score' => $row->score,
                    'edit_link' => get_edit_post_link($row->post_id)
                ];
            }
        }

        return [
            'status' => 'success',
            'plugin_detected' => is_plugin_active('rank-math/rank-math.php') ? 'RankMath' : 'None',
            'low_score_items' => $results,
            'message' => empty($results) ? 'Không tìm thấy bài viết nào có điểm SEO quá thấp (dưới 50).' : 'Dưới đây là danh sách bài viết cần tối ưu SEO.'
        ];
    }

    /**
     * Analyze site nature (Site DNA)
     */
    public static function get_site_dna() {
        $dna = [
            'type' => 'general',
            'strengths' => [],
            'post_counts' => []
        ];

        // Check CPTs
        $post_types = ['post', 'page', 'product', 'service', 'project', 'tutor_course', 'portfolio'];
        foreach ($post_types as $pt) {
            $count = (int) wp_count_posts($pt)->publish;
            if ($count > 0) {
                $dna['post_counts'][$pt] = $count;
            }
        }

        // Detect niche
        if (isset($dna['post_counts']['product'])) {
            $dna['type'] = 'ecommerce';
            $dna['strengths'][] = 'Bán hàng trực tuyến (WooCommerce)';
        } elseif (isset($dna['post_counts']['tutor_course'])) {
            $dna['type'] = 'education';
            $dna['strengths'][] = 'Đào tạo trực tuyến (LMS)';
        } elseif (isset($dna['post_counts']['project']) || isset($dna['post_counts']['portfolio'])) {
            $dna['type'] = 'portfolio';
            $dna['strengths'][] = 'Trưng bày dự án / Showcase';
        } elseif (($dna['post_counts']['post'] ?? 0) > 20) {
            $dna['type'] = 'blog';
            $dna['strengths'][] = 'Tin tức / Blog chuyên sâu';
        }

        // Active Theme
        $theme = wp_get_theme();
        $dna['theme'] = $theme->get('Name');

        return $dna;
    }

    /**
     * Build a high-level business profile for the site.
     * This is used so the AI can quickly understand what the site does.
     */
    public static function get_business_profile() {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $dna = self::get_site_dna();
        $profile = [
            'business_type' => $dna['type'],
            'strengths' => $dna['strengths'],
            'main_offerings' => [],
            'example_items' => [],
            'contact' => [
                'phone' => get_option('laca_bot_contact_phone'),
                'email' => get_option('laca_bot_contact_email')
            ]
        ];

        // WooCommerce products
        if (is_plugin_active('woocommerce/woocommerce.php')) {
            $product_cats = get_terms([
                'taxonomy' => 'product_cat',
                'hide_empty' => true,
                'number' => 10
            ]);
            foreach ($product_cats as $cat) {
                $profile['main_offerings'][] = [
                    'type' => 'product_category',
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'description' => $cat->description,
                    'count' => (int) $cat->count
                ];
            }

            $products = get_posts([
                'post_type' => 'product',
                'posts_per_page' => 5,
                'post_status' => 'publish'
            ]);
            foreach ($products as $product) {
                $profile['example_items'][] = [
                    'id' => $product->ID,
                    'title' => get_the_title($product),
                    'link' => get_permalink($product)
                ];
            }
        }

        // Service-style pages, typically under /services/ or with title containing "Dịch vụ" / "Service"
        $service_pages = [];
        $services_root = get_page_by_path('services');
        if ($services_root instanceof WP_Post) {
            $children = get_pages([
                'child_of' => $services_root->ID,
                'sort_column' => 'menu_order',
                'post_status' => 'publish'
            ]);
            foreach ($children as $page) {
                $service_pages[] = $page;
            }
        }

        if (empty($service_pages)) {
            $service_pages = get_posts([
                'post_type' => 'page',
                'posts_per_page' => 6,
                'post_status' => 'publish',
                's' => 'dịch vụ'
            ]);
        }

        if (!empty($service_pages)) {
            foreach ($service_pages as $page) {
                $profile['main_offerings'][] = [
                    'type' => 'service_page',
                    'id' => $page->ID,
                    'title' => get_the_title($page),
                    'link' => get_permalink($page),
                    'excerpt' => wp_trim_words(wp_strip_all_tags(get_post_field('post_content', $page->ID)), 40, '...')
                ];
            }
        }

        // Fallback: popular categories/tags from posts when this is a blog
        if ($dna['type'] === 'blog' && empty($profile['main_offerings'])) {
            $cats = get_categories([
                'orderby' => 'count',
                'order' => 'DESC',
                'number' => 5
            ]);
            foreach ($cats as $cat) {
                $profile['main_offerings'][] = [
                    'type' => 'blog_category',
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'count' => (int) $cat->count
                ];
            }
        }

        return $profile;
    }
}
