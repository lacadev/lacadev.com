<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Filter Projects by Category (AJAX)
 */
add_action('wp_ajax_nopriv_laca_filter_projects', 'laca_ajax_filter_projects');
add_action('wp_ajax_laca_filter_projects', 'laca_ajax_filter_projects');

function laca_ajax_filter_projects() {
    $category_id        = isset($_POST['category']) ? absint($_POST['category']) : 0;
    $allowed_categories = isset($_POST['allowed_categories']) ? sanitize_text_field($_POST['allowed_categories']) : '';
    $order_by           = isset($_POST['order_by']) ? sanitize_text_field($_POST['order_by']) : 'date';
    $count_desktop      = isset($_POST['count_desktop']) ? absint($_POST['count_desktop']) : 6;
    $count_mobile       = isset($_POST['count_mobile']) ? absint($_POST['count_mobile']) : 4;
    
    $max_count = max($count_desktop, $count_mobile);

    $args = [
        'post_type'      => 'project',
        'post_status'    => 'publish',
        'posts_per_page' => $max_count,
    ];

    if ($order_by === 'rand') {
        $args['orderby'] = 'rand';
    } elseif ($order_by === 'hand_made') {
        $args['meta_key'] = '_is_real';
        $args['meta_value'] = 'yes';
        $args['orderby'] = 'date';
        $args['order'] = 'DESC';
    } else {
        $args['orderby'] = 'date';
        $args['order']   = 'DESC';
    }

    // Filter logic
    if ($category_id > 0) {
        // Specific tab selected
        $args['tax_query'] = [
            [
                'taxonomy' => 'project_cat',
                'field'    => 'term_id',
                'terms'    => $category_id,
            ],
        ];
    } elseif (!empty($allowed_categories)) {
        // "All" tab clicked, but restricted by block settings
        $cat_array = array_map('intval', explode(',', $allowed_categories));
        $args['tax_query'] = [
            [
                'taxonomy' => 'project_cat',
                'field'    => 'term_id',
                'terms'    => $cat_array,
            ],
        ];
    }

    $query = new WP_Query($args);
    $html  = '';

    if ($query->have_posts()) {
        $index = 0;
        while ($query->have_posts()) {
            $query->the_post();
            $index++;
            
            $quick_view_img_url = getPostMetaImageUrl( 'quick_view_img', get_the_ID(), null, null );
            
            // Fallback if the helper above doesn't work as expected for this specific field type
            if ( ! $quick_view_img_url ) {
                $quick_view_img_id = carbon_get_post_meta( get_the_ID(), 'quick_view_img' );
                if ( is_numeric( $quick_view_img_id ) ) {
                    $quick_view_img_url = wp_get_attachment_image_url( $quick_view_img_id, 'full' );
                } else {
                    $quick_view_img_url = $quick_view_img_id;
                }
            }
            
            $item_class = 'laca-project-block__item';
            if ($index > $count_mobile) $item_class .= ' hidden-on-mobile';
            if ($index > $count_desktop) $item_class .= ' hidden-on-desktop';

            ob_start();
            ?>
            <div class="<?php echo esc_attr($item_class); ?>">
                <a href="<?php the_permalink(); ?>" class="laca-project-block__card-link">
                    <div class="laca-project-block__image-wrap">
                        <?php if (function_exists('theResponsivePostThumbnail')) : ?>
                            <?php theResponsivePostThumbnail('large', ['alt' => esc_attr(get_the_title()), 'class' => 'laca-project-block__img']); ?>
                        <?php else : ?>
                            <?php the_post_thumbnail('large', ['class' => 'laca-project-block__img']); ?>
                        <?php endif; ?>
                        
                        <?php if ($quick_view_img_url) : ?>
                            <div class="laca-project-block__hover-img-wrap">
                                <img src="<?php echo esc_url($quick_view_img_url); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" class="laca-project-block__hover-img" loading="lazy">
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
            <?php
            $html .= ob_get_clean();
        }
        wp_reset_postdata();
    } else {
        $html = '<p>' . __('No projects found.', 'laca') . '</p>';
    }

    laca_send_json_success(['html' => $html]);
}

/**
 * Custom JSON response helpers with proper Vietnamese encoding
 */
function laca_send_json_success($data = null, $status_code = null) {
    $response = ['success' => true];
    if (isset($data)) {
        $response['data'] = $data;
    }
    
    status_header($status_code ?: 200);
    header('Content-Type: application/json; charset=utf-8');
    echo wp_json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    wp_die('', '', ['response' => null]);
}

function laca_send_json_error($data = null, $status_code = null) {
    $response = ['success' => false];
    if (isset($data)) {
        $response['data'] = $data;
    }
    
    status_header($status_code ?: 400);
    header('Content-Type: application/json; charset=utf-8');
    echo wp_json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    wp_die('', '', ['response' => null]);
}

/**
 * Get Real IP Address
 */
function lacadev_get_real_ip() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    return filter_var(trim($ip), FILTER_VALIDATE_IP) ?: 'unknown';
}

/**
 * Rate limiting helper for AJAX requests
 */
function lacadev_check_rate_limit($action_name, $limit = 20, $period = 60) {
    $ip = lacadev_get_real_ip();
    $transient_key = 'rate_limit_' . $action_name . '_' . md5($ip);
    $request_count = get_transient($transient_key);
    
    if ($request_count === false) {
        set_transient($transient_key, 1, $period);
        return true;
    }
    
    if ($request_count >= $limit) {
        wp_send_json_error([
            'message' => __('Quá nhiều requests. Vui lòng thử lại sau.', 'laca')
        ], 429);
        exit;
    }
    
    set_transient($transient_key, $request_count + 1, $period);
    return true;
}

/**
 * Improve search: ONLY search in title, accent-insensitive (Vietnamese support)
 */
function lacadev_improve_search_relevance($search, $wp_query) {
    global $wpdb;
    
    if (empty($wp_query->query_vars['s'])) {
        return $search;
    }
    
    $search_term = $wpdb->esc_like(trim($wp_query->query_vars['s']));
    
    // Search ONLY in post_title with accent-insensitive collation 
    // Secure query with $wpdb->prepare to prevent SQLi
    $search = $wpdb->prepare(" AND ({$wpdb->posts}.post_title COLLATE utf8mb4_unicode_ci LIKE %s)", '%' . $search_term . '%');
    
    return $search;
}

/**
 * AJAX Search Handler
 */
add_action('wp_ajax_nopriv_ajax_search', 'mms_ajax_search');
add_action('wp_ajax_ajax_search', 'mms_ajax_search');

function mms_ajax_search() {
    // Rate limiting: 20 requests per minute
    lacadev_check_rate_limit('ajax_search', 20, 60);
    
    // Security check
    check_ajax_referer('theme_search_nonce', 'nonce');

    // Get search query
    $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    // Add search relevance filter (title-only, accent-insensitive)
    add_filter('posts_search', 'lacadev_improve_search_relevance', 10, 2);
    
    $post_types = get_post_types(['public' => true]);
    unset($post_types['attachment']); // Remove standard attachment from search

    // 1 Query to Rule Them All
    $query = new WP_Query([
        'post_type'      => array_keys($post_types),
        'posts_per_page' => 10, // Giới hạn tổng record tránh overload
        's'              => $search_query,
        'post_status'    => 'publish',
        'no_found_rows'  => true, // Tăng cường hiệu năng, bỏ qua đếm SQL_CALC_FOUND_ROWS
        'orderby'        => 'date',
        'order'          => 'DESC'
    ]);

    // Bỏ filter sau khi đã query
    remove_filter('posts_search', 'lacadev_improve_search_relevance', 10);

    if (!$query->have_posts()) {
        $html = '<div class="search-results__empty">';
        $html .= '<p>Không tìm thấy kết quả nào cho "<strong>' . esc_html($search_query) . '</strong>"</p>';
        $html .= '</div>';
        echo $html;
        wp_die();
    }

    // Grouping records by post type using PHP (no extra queries)
    $grouped_results = [];
    foreach ($query->posts as $post) {
        $grouped_results[$post->post_type][] = $post;
    }

    $html = '';
    // Custom label ordering
    $preferred_order = ['product', 'post', 'page'];
    uksort($grouped_results, function ($a, $b) use ($preferred_order) {
        $pos_a = array_search($a, $preferred_order);
        $pos_b = array_search($b, $preferred_order);
        if ($pos_a !== false && $pos_b !== false) return $pos_a <=> $pos_b;
        if ($pos_a !== false) return -1;
        if ($pos_b !== false) return 1;
        return strcmp($a, $b);
    });

    // Render grouped views correctly
    foreach ($grouped_results as $type => $posts) {
        $post_type_obj = get_post_type_object($type);
        $type_label = $post_type_obj->labels->name ?? ucfirst($type);
        $html .= lacadev_render_search_section($type_label, $posts);
    }

    echo $html;
    wp_die();
}

/**
 * Html Partial cho mỗi section
 */
function lacadev_render_search_section($title, $posts) {
    ob_start();
    ?>
    <div class="search-results__section">
        <h3 class="search-results__title">
            <strong><?php echo esc_html($title); ?> liên quan</strong> 
            <span class="search-results__count">(<?php echo count($posts); ?> kết quả)</span>:
        </h3>
        <div class="search-results__list">
            <?php foreach ($posts as $p): ?>
                <a href="<?php echo esc_url(get_permalink($p->ID)); ?>" class="search-results__item">
                    <div class="search-results__image">
                        <?php echo getResponsivePostThumbnail($p->ID, 'mobile', ['alt' => get_the_title($p->ID)]); ?>
                    </div>
                    <div class="search-results__content">
                        <h4 class="search-results__item-title"><?php echo esc_html(get_the_title($p->ID)); ?></h4>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// =============================================================================
// AJAX HANDLERS - CUSTOM SORT, THUMBNAIL, CONTACT FORM, LOAD PAGE
// =============================================================================

if (!defined('ABSPATH')) {
    exit;
}

// -----------------------------------------------------------------------------
// AJAX: Update Custom Sort Order
// -----------------------------------------------------------------------------
/**
 * Cập nhật thứ tự sắp xếp (menu_order) cho các post qua Ajax.
 *
 * @action wp_ajax_update_custom_sort_order
 */
add_action('wp_ajax_update_custom_sort_order', 'updateCustomSortOrder');
function updateCustomSortOrder() {
    // Basic permissions check
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    // Kiểm tra nonce để bảo vệ CSRF
    check_ajax_referer('update_custom_sort_order', 'nonce');
    
    // Kiểm tra tham số đầu vào
    if (empty($_POST['post_ids']) || empty($_POST['current_page'])) {
        wp_send_json_error(['message' => 'Missing parameters.']);
    }

    $postIds = array_map('absint', wp_unslash($_POST['post_ids']));
    $currentPage = absint(wp_unslash($_POST['current_page']));
    $order = (($currentPage - 1) * count($postIds)) + 1;

    // Cập nhật menu_order cho từng post
    foreach ($postIds as $postId) {
        wp_update_post([
            'ID'         => $postId,
            'menu_order' => $order,
        ]);
        $order++;
    }

    wp_send_json_success();
}

// -----------------------------------------------------------------------------
// AJAX: Update Post Thumbnail ID
// -----------------------------------------------------------------------------
/**
 * Cập nhật thumbnail (ảnh đại diện) cho post qua Ajax.
 *
 * @action wp_ajax_update_post_thumbnail_id
 */
add_action('wp_ajax_update_post_thumbnail_id', 'updatePostThumbnailId');

function updatePostThumbnailId() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    // Kiểm tra nonce để bảo vệ CSRF
    check_ajax_referer('update_post_thumbnail', 'nonce');
    
    // Kiểm tra các tham số post_id và attachment_id
    if (empty($_POST['post_id']) || empty($_POST['attachment_id'])) {
        wp_send_json_error(['message' => 'Missing parameters.']);
    }

    $postId = absint(wp_unslash($_POST['post_id']));
    $attachmentId = absint(wp_unslash($_POST['attachment_id']));

    // Cập nhật _thumbnail_id bằng hàm update_post_meta
    if (update_post_meta($postId, '_thumbnail_id', $attachmentId)) {
        wp_send_json_success(['message' => 'Thumbnail updated.']);
    } else {
        wp_send_json_error(['message' => 'Failed to update thumbnail.']);
    }
}

// -----------------------------------------------------------------------------
// AJAX: Remove Post Thumbnail
// -----------------------------------------------------------------------------
/**
 * Xóa thumbnail (ảnh đại diện) cho post qua Ajax.
 *
 * @action wp_ajax_remove_post_thumbnail
 */
add_action('wp_ajax_remove_post_thumbnail', 'removePostThumbnail');

function removePostThumbnail() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Unauthorized access.']);
    }

    // Kiểm tra nonce để bảo vệ CSRF
    check_ajax_referer('update_post_thumbnail', 'nonce');
    
    // Kiểm tra tham số post_id
    if (empty($_POST['post_id'])) {
        wp_send_json_error(['message' => 'Missing post ID.']);
    }

    $postId = absint(wp_unslash($_POST['post_id']));

    // Xóa thumbnail bằng hàm delete_post_thumbnail
    if (delete_post_thumbnail($postId)) {
        wp_send_json_success(['message' => 'Thumbnail removed.']);
    } else {
        wp_send_json_error(['message' => 'Failed to remove thumbnail.']);
    }
}

// -----------------------------------------------------------------------------
// AJAX: Check submission status for returning users
// -----------------------------------------------------------------------------
add_action('wp_ajax_laca_check_submission_status', 'lacadev_check_submission_status');
add_action('wp_ajax_nopriv_laca_check_submission_status', 'lacadev_check_submission_status');

function lacadev_check_submission_status() {
    $ip = lacadev_get_real_ip();
    $last_submission = get_transient('laca_contact_' . md5($ip));
    
    if ($last_submission) {
        wp_send_json_success([
            'submitted' => true,
            'time' => date_i18n('H:i - d/m/Y', $last_submission),
            'message' => sprintf(__('Bạn đã gửi lời nhắn vào lúc %s. Bạn có muốn gửi thêm nội dung khác?', 'laca'), date_i18n('H:i - d/m/Y', $last_submission))
        ]);
    }
    
    wp_send_json_success(['submitted' => false]);
}

// -----------------------------------------------------------------------------
// AJAX: Form liên hệ (Contact Form) — đã được chuyển sang App\Features\ContactForm
// -----------------------------------------------------------------------------

/**
 * Get Admin Email Template
 * Email gửi cho admin khi có liên hệ mới
 */
function lacadev_get_admin_email_template($name, $email, $phone, $message, $site_name) {
    $current_time = date_i18n('H:i - d/m/Y');
    $current_year = date('Y');
    $site_url = get_bloginfo('url');
    
    return "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'></head>
    <body style='margin:0;padding:40px 20px;background:#ffffff;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#111111;line-height:1.6'>
        <div style='max-width:540px;margin:0 auto;border:1px solid #e5e5e5;padding:40px'>
            <div style='margin-bottom:30px'>
                <h1 style='margin:0 0 5px;font-size:20px;font-weight:600;letter-spacing:-0.5px'>Thông báo liên hệ mới</h1>
                <p style='margin:0;font-size:13px;color:#666666'>{$current_time}</p>
            </div>
            
            <div style='margin-bottom:30px;padding-bottom:30px;border-bottom:1px solid #eeeeee'>
                <p style='margin:0 0 10px;font-size:14px'><strong>Người gửi:</strong> {$name}</p>
                <p style='margin:0 0 10px;font-size:14px'><strong>Số điện thoại:</strong> <a href='tel:{$phone}' style='color:#111111;text-decoration:none'>{$phone}</a></p>
                " . (!empty($email) ? "<p style='margin:0;font-size:14px'><strong>Email:</strong> <a href='mailto:{$email}' style='color:#111111;text-decoration:underline'>{$email}</a></p>" : "") . "
            </div>

            <div style='margin-bottom:40px'>
                <p style='margin:0;white-space:pre-wrap;font-size:15px;line-height:1.7;color:#333333'>" . nl2br(esc_html($message)) . "</p>
            </div>

            " . (!empty($email) ? "<div>
                <a href='mailto:{$email}?subject=Re: Liên hệ từ {$site_name}' style='display:inline-block;background:#111111;color:#ffffff;text-decoration:none;padding:12px 24px;font-size:14px;font-weight:500'>Trả lời email</a>
            </div>" : "<div>
                <a href='tel:{$phone}' style='display:inline-block;background:#111111;color:#ffffff;text-decoration:none;padding:12px 24px;font-size:14px;font-weight:500'>Gọi điện thoại</a>
            </div>") . "
            
            <div style='margin-top:40px;padding-top:20px;border-top:1px solid #eeeeee'>
                <p style='margin:0;font-size:12px;color:#999999'>Hệ thống {$site_name} &mdash; <a href='{$site_url}' style='color:#999999;text-decoration:none'>{$site_url}</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
}

/**
 * Get Customer Confirmation Email Template
 * Email xác nhận gửi cho khách hàng
 */
function lacadev_get_customer_email_template($name, $email, $phone, $message) {
    $current_year = date('Y');
    $first_name = explode(' ', trim($name))[0];
    $site_name = get_bloginfo('name');
    $site_url = get_bloginfo('url');
    
    // Thông tin tác giả
    $author_name = 'La cà dev';
    $author_title = 'WordPress Developer';
    $author_phone = '0776.41.00.43';
    $author_email = carbon_get_theme_option('email') ?: get_option('admin_email');
    
    return "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'></head>
    <body style='margin:0;padding:40px 20px;background:#ffffff;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#111111;line-height:1.6'>
        <div style='max-width:540px;margin:0 auto;border:1px solid #e5e5e5;padding:40px'>
            
            <div style='margin-bottom:30px'>
                <h1 style='margin:0 0 5px;font-size:20px;font-weight:600;letter-spacing:-0.5px'>Đã nhận lời nhắn</h1>
                <p style='margin:0;font-size:13px;color:#666666'>Cảm ơn bạn đã liên hệ với {$site_name}</p>
            </div>
            
            <div style='margin-bottom:30px'>
                <p style='margin:0 0 15px;font-size:15px'>Chào <strong>{$first_name}</strong>,</p>
                <p style='margin:0;font-size:15px;color:#444444'>Tôi đã nhận được tin nhắn cùng số điện thoại <strong>{$phone}</strong> của bạn.</p>
                <p style='margin:10px 0 0;font-size:15px;color:#444444'>Tôi sẽ xem xét và phản hồi trong vòng 24 giờ.</p>
            </div>
            
            <div style='margin-bottom:30px;padding:25px;background:#fafafa;border:1px solid #eeeeee'>
                <p style='margin:0 0 10px;font-size:12px;color:#888888;text-transform:uppercase;letter-spacing:0.5px'>Tóm tắt nội dung</p>
                <p style='margin:0;font-size:14px;color:#555555'>\"" . mb_substr(strip_tags($message), 0, 100) . (mb_strlen($message) > 100 ? '...' : '') . "\"</p>
            </div>
            
            <div style='margin-bottom:40px'>
                <p style='margin:0 0 15px;font-size:15px'>Trân trọng,</p>
                <p style='margin:0 0 2px;font-size:15px;font-weight:600'>{$author_name}</p>
                <p style='margin:0;font-size:13px;color:#666666'>{$author_title}</p>
                <div style='margin-top:12px'>
                    <a href='mailto:{$author_email}' style='color:#111111;text-decoration:underline;font-size:13px;margin-right:15px'>{$author_email}</a>
                    <a href='tel:{$author_phone}' style='color:#111111;text-decoration:underline;font-size:13px'>{$author_phone}</a>
                </div>
            </div>
            
            <div style='margin-top:40px;padding-top:20px;border-top:1px solid #eeeeee'>
                <p style='margin:0;font-size:12px;color:#999999'>&copy; {$current_year} {$site_name}. Đây là email xác nhận tự động.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

// -----------------------------------------------------------------------------
// AJAX: Load Page Content
// -----------------------------------------------------------------------------
/**
 * Tải nội dung trang qua Ajax (dùng cho các yêu cầu động).
 *
 * @action wp_ajax_nopriv_get_page
 * @action wp_ajax_get_page
 */
add_action('wp_ajax_nopriv_get_page', 'ajaxGetPage');
add_action('wp_ajax_get_page', 'ajaxGetPage');

function ajaxGetPage() {
    // Security check
    check_ajax_referer('theme_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    
    if (!$post_id) {
        wp_send_json_error(['message' => 'Missing Post ID']);
    }

    // Setup global post data
    global $post;
    $post = get_post($post_id);
    
    if (!$post || ($post->post_status !== 'publish' && !current_user_can('read_post', $post_id))) {
        wp_send_json_error(['message' => 'Post not found or unauthorized']);
    }
    
    setup_postdata($post);

    ob_start();
    // Load the page template. Note: This will load 'page.php' from the theme root.
    // If you need a specific part, use get_template_part('template-parts/content', 'page');
    get_template_part('page'); 
    $content = ob_get_clean();
    
    wp_reset_postdata();
    wp_send_json_success($content);
}

// -----------------------------------------------------------------------------
// AJAX: Load More Search Results
// -----------------------------------------------------------------------------
/**
 * Load more search results for specific post type
 *
 * @action wp_ajax_nopriv_load_more_search
 * @action wp_ajax_load_more_search
 */
add_action('wp_ajax_nopriv_load_more_search', 'lacadev_load_more_search');
add_action('wp_ajax_load_more_search', 'lacadev_load_more_search');

function lacadev_load_more_search() {
    // Security check
    check_ajax_referer('theme_search_nonce', 'nonce');
    
    // Get parameters
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
    $search_query = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $paged = isset($_POST['paged']) ? absint($_POST['paged']) : 1;
    
    if (empty($post_type) || empty($search_query)) {
        wp_send_json_error(['message' => 'Missing parameters']);
        return;
    }
    
    // Add search filter (title-only, accent-insensitive)
    add_filter('posts_search', 'lacadev_improve_search_relevance', 10, 2);
    
    // Query posts
    $query = new WP_Query([
        'post_type' => $post_type,
        'posts_per_page' => 8,
        's' => $search_query,
        'post_status' => 'publish',
        'paged' => $paged,
    ]);
    
    // Remove search filter
    remove_filter('posts_search', 'lacadev_improve_search_relevance', 10);
    
    if (!$query->have_posts()) {
        wp_send_json_error(['message' => 'No more posts']);
        return;
    }
    
    // Map post_type to template part
    // Pages use 'post' template since loop-page.php doesn't exist
    $template_slug = $post_type;
    if ($post_type === 'page') {
        $template_slug = 'post';
    }
    
    // Generate HTML
    ob_start();
    while ($query->have_posts()) {
        $query->the_post();
        get_template_part('template-parts/loop', $template_slug);
    }
    wp_reset_postdata();
    $html = ob_get_clean();
    
    // Return response
    wp_send_json_success([
        'html' => $html,
        'has_more' => $paged < $query->max_num_pages,
        'next_page' => $paged + 1,
        'max_pages' => $query->max_num_pages,
    ]);
}
