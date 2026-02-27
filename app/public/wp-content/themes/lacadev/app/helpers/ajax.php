<?php
if (!defined('ABSPATH')) {
    exit;
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
// AJAX: Gửi form liên hệ (Contact Form)
// -----------------------------------------------------------------------------
add_action('wp_ajax_nopriv_laca_contact_submit', 'lacadev_handle_contact_submit');
add_action('wp_ajax_laca_contact_submit', 'lacadev_handle_contact_submit');

function lacadev_handle_contact_submit() {
    // 1. Security check
    check_ajax_referer('laca_contact_nonce', 'nonce');

    // 2. Rate Limiting Check
    $ip = lacadev_get_real_ip();
    $transient_key = 'laca_contact_' . md5($ip);
    $last_submission = get_transient($transient_key);
    
    // If they haven't explicitly confirmed they want to resubmit
    if ($last_submission && !isset($_POST['resubmit_confirmed'])) {
         laca_send_json_error([
             'code' => 'recently_submitted',
             'time' => date_i18n('H:i - d/m/Y', $last_submission),
             'message' => sprintf(__('Bạn vừa gửi tin nhắn vào lúc %s. Đợi một chút rồi gửi tiếp nhé!', 'laca'), date_i18n('H:i - d/m/Y', $last_submission))
         ], 429);
    }

    // 3. Verify reCAPTCHA v3
    $recaptcha_response = isset($_POST['recaptcha_response']) ? sanitize_text_field($_POST['recaptcha_response']) : '';
    $secret_key = carbon_get_theme_option('recaptcha_secret_key');
    $score_threshold = (float) carbon_get_theme_option('recaptcha_score') ?: 0.5;

    if (!empty($secret_key)) {
        // Debug: Check if token is empty
        if (empty($recaptcha_response)) {
            laca_send_json_error([
                'message' => __('Token reCAPTCHA không được gửi. Vui lòng thử lại.', 'laca'),
                'debug' => [
                    'token_empty' => true,
                    'secret_key_length' => strlen($secret_key)
                ]
            ], 400);
        }

        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
            'body' => [
                'secret'   => $secret_key,
                'response' => $recaptcha_response,
                'remoteip' => $ip
            ],
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
             laca_send_json_error([
                 'message' => __('Không thể kết nối với Google reCAPTCHA.', 'laca'),
                 'debug' => [
                     'error' => $response->get_error_message()
                 ]
             ], 500);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Debug: Log full response for troubleshooting
        if (!$data['success']) {
            $error_codes = isset($data['error-codes']) ? $data['error-codes'] : [];
            $readable_errors = [];
            
            // Translate error codes
            foreach ($error_codes as $code) {
                switch ($code) {
                    case 'missing-input-secret':
                        $readable_errors[] = 'Secret Key bị thiếu';
                        break;
                    case 'invalid-input-secret':
                        $readable_errors[] = 'Secret Key không đúng';
                        break;
                    case 'missing-input-response':
                        $readable_errors[] = 'Token reCAPTCHA bị thiếu';
                        break;
                    case 'invalid-input-response':
                        $readable_errors[] = 'Token reCAPTCHA không hợp lệ hoặc đã hết hạn';
                        break;
                    case 'bad-request':
                        $readable_errors[] = 'Request không hợp lệ';
                        break;
                    case 'timeout-or-duplicate':
                        $readable_errors[] = 'Token đã được sử dụng hoặc timeout';
                        break;
                    default:
                        $readable_errors[] = $code;
                }
            }
            
            laca_send_json_error([
                'message' => __('Xác thực reCAPTCHA thất bại: ', 'laca') . implode(', ', $readable_errors),
                'debug' => [
                    'error_codes' => $error_codes,
                    'hostname' => isset($data['hostname']) ? $data['hostname'] : '',
                    'token_length' => strlen($recaptcha_response)
                ]
            ], 403);
        }
        
        if ($data['score'] < $score_threshold) {
            laca_send_json_error([
                'message' => sprintf(__('Hệ thống nghi ngờ bạn là bot (Điểm: %s/%s).', 'laca'), $data['score'], $score_threshold),
                'debug' => [
                    'score' => $data['score'],
                    'threshold' => $score_threshold,
                    'action' => isset($data['action']) ? $data['action'] : ''
                ]
            ], 403);
        }
    }

    // 4. Sanitize Input
    $name    = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $email   = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
    $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

    if (empty($name) || empty($email) || empty($message)) {
        laca_send_json_error([
            'message' => __('Vui lòng điền đầy đủ các thông tin bắt buộc.', 'laca')
        ], 400);
    }

    if (!is_email($email)) {
        laca_send_json_error([
            'message' => __('Địa chỉ email không hợp lệ.', 'laca')
        ], 400);
    }

    // 5. Prepare Email Data
    $admin_email = carbon_get_theme_option('email') ?: get_option('admin_email');
    $site_name = get_bloginfo('name');
    $author_name = 'Hà Duy An';
    
    // 6. Send Email to Admin (Notification)
    $admin_subject = sprintf('[%s] Lời nhắn mới từ %s', $site_name, $name);
    if ($subject) {
        $admin_subject .= ': ' . $subject;
    }

    $admin_headers = [
        'Content-Type: text/html; charset=UTF-8',
        'Reply-To: ' . $name . ' <' . $email . '>'
    ];

    $admin_body = lacadev_get_admin_email_template($name, $email, $subject, $message, $site_name);
    $admin_sent = wp_mail($admin_email, $admin_subject, $admin_body, $admin_headers);

    // 7. Send Confirmation Email to Customer
    $customer_subject = sprintf('Cảm ơn bạn đã liên hệ với %s!', $site_name);
    
    $customer_headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $author_name . ' <' . $admin_email . '>',
        'Reply-To: ' . $admin_email
    ];

    $customer_body = lacadev_get_customer_email_template($name, $email, $subject, $message);
    $customer_sent = wp_mail($email, $customer_subject, $customer_body, $customer_headers);

    // 8. Handle Results
    $sent = ($admin_sent && $customer_sent);

    if ($sent) {
        // Both emails sent successfully
        set_transient($transient_key, time(), 30 * MINUTE_IN_SECONDS);
        laca_send_json_success([
            'message' => __('Lời nhắn đã được gửi thành công! Vui lòng kiểm tra email để xác nhận.', 'laca')
        ], 200);
    } elseif ($admin_sent && !$customer_sent) {
        // Admin email OK, customer confirmation failed (still success)
        set_transient($transient_key, time(), 30 * MINUTE_IN_SECONDS);
        laca_send_json_success([
            'message' => __('Lời nhắn đã được gửi. Email xác nhận có thể bị lỗi, nhưng tôi sẽ phản hồi sớm!', 'laca')
        ], 200);
    } else {
        // Failed to send admin email (critical)
        laca_send_json_error([
            'message' => __('Đã có lỗi xảy ra khi gửi email. Vui lòng thử lại sau hoặc liên hệ: 0776.41.00.43', 'laca'),
            'debug' => [
                'admin_sent' => $admin_sent,
                'customer_sent' => $customer_sent
            ]
        ], 500);
    }
}

/**
 * Get Admin Email Template
 * Email gửi cho admin khi có liên hệ mới
 */
function lacadev_get_admin_email_template($name, $email, $subject, $message, $site_name) {
    $subject_text = $subject ?: __('Không có tiêu đề', 'laca');
    $current_time = date_i18n('H:i - d/m/Y');
    $current_year = date('Y');
    $site_url = get_bloginfo('url');
    
    return "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'></head>
    <body style='margin:0;padding:20px;background:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial,sans-serif'>
        <div style='max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1)'>
            <div style='background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);padding:30px 20px;text-align:center'>
                <h1 style='margin:0;color:#fff;font-size:24px;font-weight:600'>📬 Lời Nhắn Mới</h1>
                <p style='margin:10px 0 0;color:rgba(255,255,255,0.9);font-size:14px'>{$current_time}</p>
            </div>
            <div style='padding:30px 20px'>
                <div style='background:#f8f9fa;border-left:4px solid #667eea;padding:20px;margin-bottom:25px;border-radius:8px'>
                    <h3 style='margin:0 0 15px;color:#333;font-size:16px'>👤 Thông Tin Người Gửi</h3>
                    <p style='margin:8px 0'><strong>Tên:</strong> {$name}</p>
                    <p style='margin:8px 0'><strong>Email:</strong> <a href='mailto:{$email}' style='color:#667eea;text-decoration:none'>{$email}</a></p>
                    <p style='margin:8px 0'><strong>Tiêu đề:</strong> {$subject_text}</p>
                </div>
                <div style='margin-bottom:25px'>
                    <h3 style='margin:0 0 15px;color:#333;font-size:16px'>💬 Nội Dung Tin Nhắn</h3>
                    <div style='background:#f9fafb;padding:20px;border-radius:8px;border:1px solid #e5e7eb'>
                        <p style='margin:0;white-space:pre-wrap;color:#1f2937;line-height:1.8'>" . nl2br(esc_html($message)) . "</p>
                    </div>
                </div>
                <div style='text-align:center;margin:30px 0'>
                    <a href='mailto:{$email}?subject=Re: {$subject_text}' style='display:inline-block;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:15px'>✉️ Trả Lời Ngay</a>
                </div>
            </div>
            <div style='background:#f8f9fa;padding:20px;text-align:center;border-top:1px solid #e5e7eb'>
                <p style='margin:0 0 8px;font-size:12px;color:#6b7280'>Email này được gửi tự động từ hệ thống <strong>{$site_name}</strong></p>
                <p style='margin:0;font-size:12px;color:#9ca3af'>Tin nhắn từ form liên hệ tại <a href='{$site_url}' style='color:#667eea;text-decoration:none'>{$site_url}</a></p>
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
function lacadev_get_customer_email_template($name, $email, $subject, $message) {
    $subject_text = $subject ?: __('tin nhắn của bạn', 'laca');
    $current_year = date('Y');
    $first_name = explode(' ', trim($name))[0];
    $site_name = get_bloginfo('name');
    $site_url = get_bloginfo('url');
    
    // Thông tin tác giả
    $author_name = 'Hà Duy An';
    $author_title = 'WordPress Developer & Content Creator';
    $author_phone = '0776.41.00.43';
    $author_email = carbon_get_theme_option('email') ?: get_option('admin_email');
    $author_location = 'Đà Nẵng, Việt Nam';
    
    return "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'></head>
    <body style='margin:0;padding:20px;background:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial,sans-serif'>
        <div style='max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.1)'>
            
            <div style='background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);padding:40px 20px;text-align:center'>
                <div style='width:80px;height:80px;background:rgba(255,255,255,0.2);backdrop-filter:blur(10px);border-radius:50%;margin:0 auto 20px;display:inline-flex;align-items:center;justify-content:center;font-size:40px'>✅</div>
                <h1 style='margin:0;color:#fff;font-size:26px;font-weight:700;letter-spacing:-0.5px'>Đã Nhận Lời Nhắn!</h1>
                <p style='margin:12px 0 0;color:rgba(255,255,255,0.95);font-size:15px'>Cảm ơn bạn đã ghé trạm {$site_name}</p>
            </div>
            
            <div style='padding:35px 25px'>
                <p style='margin:0 0 8px;font-size:17px;color:#1f2937'>Chào <strong style='color:#667eea'>{$first_name}</strong>,</p>
                <p style='margin:0 0 25px;font-size:16px;color:#4b5563;line-height:1.7'>Cảm ơn bạn đã tin tưởng và dành thời gian gửi lời nhắn. Tôi rất vui khi được kết nối với bạn! 🎉</p>
                
                <div style='background:linear-gradient(to right,#ecfdf5,#d1fae5);border:2px solid #10b981;padding:20px;border-radius:12px;margin:25px 0;text-align:center'>
                    <div style='font-size:32px;margin-bottom:10px'>🎯</div>
                    <h3 style='margin:0 0 8px;color:#065f46;font-size:16px;font-weight:700'>Lời Nhắn Đã Được Gửi Thành Công</h3>
                    <p style='margin:0;color:#047857;font-size:14px'>Tôi thường phản hồi trong vòng <strong>24 giờ</strong></p>
                </div>
                
                <div style='background:#f9fafb;padding:20px;border-radius:10px;border:1px solid #e5e7eb;margin:25px 0'>
                    <h3 style='margin:0 0 15px;color:#374151;font-size:15px;font-weight:600'>📋 Tóm Tắt Tin Nhắn</h3>
                    <p style='margin:8px 0;color:#6b7280;font-size:14px'><strong>Tiêu đề:</strong> {$subject_text}</p>
                    <p style='margin:8px 0;color:#6b7280;font-size:14px'><strong>Nội dung:</strong> \"" . mb_substr(strip_tags($message), 0, 100) . (mb_strlen($message) > 100 ? '...' : '') . "\"</p>
                </div>
                
                <div style='background:linear-gradient(135deg,#1e293b 0%,#334155 100%);padding:25px;border-radius:12px;margin:30px 0;color:#fff'>
                    <div style='text-align:center;margin-bottom:20px'>
                        <div style='width:70px;height:70px;background:rgba(255,255,255,0.1);border:3px solid rgba(255,255,255,0.2);border-radius:50%;margin:0 auto 15px;display:inline-flex;align-items:center;justify-content:center;font-size:32px;font-weight:700'>HDA</div>
                        <h3 style='margin:0 0 5px;font-size:20px;font-weight:700'>{$author_name}</h3>
                        <p style='margin:0;color:rgba(255,255,255,0.8);font-size:14px'>{$author_title}</p>
                    </div>
                    <div style='border-top:1px solid rgba(255,255,255,0.1);padding-top:20px'>
                        <div style='margin:12px 0'>
                            <p style='margin:0;color:rgba(255,255,255,0.6);font-size:12px;text-transform:uppercase;letter-spacing:0.5px'>📧 Email</p>
                            <a href='mailto:{$author_email}' style='color:#fff;text-decoration:none;font-size:15px;font-weight:500;display:block;margin-top:5px'>{$author_email}</a>
                        </div>
                        <div style='margin:12px 0'>
                            <p style='margin:0;color:rgba(255,255,255,0.6);font-size:12px;text-transform:uppercase;letter-spacing:0.5px'>📱 Điện Thoại</p>
                            <a href='tel:{$author_phone}' style='color:#fff;text-decoration:none;font-size:15px;font-weight:500;display:block;margin-top:5px'>{$author_phone}</a>
                        </div>
                        <div style='margin:12px 0'>
                            <p style='margin:0;color:rgba(255,255,255,0.6);font-size:12px;text-transform:uppercase;letter-spacing:0.5px'>📍 Địa Điểm</p>
                            <span style='color:#fff;font-size:15px;font-weight:500;display:block;margin-top:5px'>{$author_location}</span>
                        </div>
                    </div>
                </div>
                
                <div style='background:#fefce8;border:1px solid #fde047;padding:18px;border-radius:8px;margin:25px 0'>
                    <h3 style='margin:0 0 10px;color:#854d0e;font-size:15px;font-weight:600'>⏰ Tiếp Theo Là Gì?</h3>
                    <ul style='margin:8px 0;padding-left:20px;color:#713f12;font-size:14px;line-height:1.8'>
                        <li>Tôi sẽ xem xét tin nhắn trong vòng <strong>24 giờ</strong></li>
                        <li>Phản hồi sẽ gửi đến: <strong>{$email}</strong></li>
                        <li>Nếu gấp, gọi: <strong>{$author_phone}</strong></li>
                    </ul>
                </div>
                
                <div style='text-align:center;margin:30px 0 20px'>
                    <p style='margin:0 0 20px;color:#6b7280;font-size:15px'>Trong khi chờ, khám phá thêm:</p>
                    <div style='display:inline-block'>
                        <a href='{$site_url}/ky-su/tram-code/' style='display:inline-block;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:600;font-size:14px;margin:5px'>💻 Trạm Code</a>
                        <a href='{$site_url}/la-ca-co-gi/' style='display:inline-block;background:#f3f4f6;color:#374151;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:600;font-size:14px;margin:5px;border:2px solid #e5e7eb'>🎯 Dịch Vụ</a>
                    </div>
                </div>
                
                <div style='margin-top:35px;padding-top:25px;border-top:2px solid #f3f4f6'>
                    <p style='margin:0 0 15px;color:#4b5563;font-size:15px;line-height:1.7'>Một lần nữa, cảm ơn bạn đã liên hệ. Tôi mong chờ được trao đổi với bạn!</p>
                    <p style='margin:0;color:#1f2937;font-size:16px;font-weight:600'>Thân ái,<br/><span style='color:#667eea;font-size:18px'>{$author_name}</span></p>
                </div>
            </div>
            
            <div style='background:#f8f9fa;padding:25px 20px;text-align:center;border-top:1px solid #e5e7eb'>
                <p style='margin:0 0 12px;color:#374151;font-size:14px;font-weight:600'>{$site_name}</p>
                <p style='margin:0 0 15px;color:#6b7280;font-size:13px'>🚀 WordPress • 🎨 Design • ✈️ Travelling</p>
                <p style='margin:15px 0 0;color:#9ca3af;font-size:11px'>© {$current_year} {$site_name}. Email tự động - Không trả lời.</p>
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
