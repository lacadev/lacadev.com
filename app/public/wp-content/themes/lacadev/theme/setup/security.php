<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Headers & Hardening
 * 
 * @package LacaDev
 */




/**
 * Hardening mặc định: luôn tắt XML-RPC và xoá wp_generator
 */
add_filter('xmlrpc_enabled', '__return_false');
remove_action('wp_head', 'wp_generator');

add_filter('wp_xmlrpc_server_class', function ($class) {
    if (!apply_filters('xmlrpc_enabled', true)) {
        if (!class_exists('Laca_XmlRpc_Disabled_Server', false)) {
            class Laca_XmlRpc_Disabled_Server extends \wp_xmlrpc_server
            {
                public function serve_request()
                {
                    $this->error(new \IXR_Error(405, 'XML-RPC services are disabled on this site.'));
                }
            }
        }
        return 'Laca_XmlRpc_Disabled_Server';
    }
    return $class;
});

/**
 * Add HTTP Security Headers
 */
add_action('send_headers', function() {
    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN');
    
    // Prevent MIME-sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // XSS Protection (legacy browsers)
    header('X-XSS-Protection: 1; mode=block');
    
    if ( ! is_admin() ) {
        // Content Security Policy
        // Lưu ý: không dùng nonce trong script-src vì khi có nonce, trình duyệt sẽ
        // vô hiệu hóa 'unsafe-inline', chặn các inline script của plugin bên thứ 3.
        $csp  = "default-src 'self'; ";
        $csp .= "script-src 'self' 'unsafe-inline' https://www.google.com https://www.gstatic.com https://www.googletagmanager.com https://www.google-analytics.com https://images.dmca.com https://apis.google.com blob:; ";
        $csp .= "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; ";
        $csp .= "font-src 'self' https://fonts.gstatic.com data:; ";
        $csp .= "connect-src 'self' https://www.google.com https://www.gstatic.com https://www.youtube.com https://www.google-analytics.com https://stats.g.doubleclick.net https://apis.google.com ws: wss:; ";
        $csp .= "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com https://docs.google.com https://www.google.com https://www.gstatic.com; ";
        $csp .= "media-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com; ";
        $csp .= "img-src 'self' data: https: http:; ";
        $csp .= "worker-src 'self' blob:; ";
        $csp .= "frame-ancestors 'self';";

        header( "Content-Security-Policy: {$csp}" );

        // Permissions Policy
        header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
    }
});

/**
 * Remove WP version from RSS feeds
 */
add_filter('the_generator', '__return_empty_string');

/**
 * Disable file editing in admin
 */
if (get_option('_hide_theme_editor') === 'yes' && !defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}

/**
 * Sanitize Uploaded Filenames
 * Prevent special characters and ensure safe filenames
 */
add_filter('sanitize_file_name', function($filename) {
    $info = pathinfo($filename);
    $ext  = empty($info['extension']) ? '' : '.' . $info['extension'];
    $name = basename($filename, $ext);

    // Remove accents and special chars
    $name = remove_accents($name);
    
    // Ensure only safe characters remain
    $name = preg_replace('/[^A-Za-z0-9-]/', '-', $name);
    
    // Prevent multiple dashes
    $name = preg_replace('/-+/', '-', $name);
    
    // Trim dashes from beginning and end
    $name = trim($name, '-');

    // Add short unique hash (4 chars) to prevent duplication but keep it short
    // Example: image-name-a1b2.jpg
    $short_hash = substr(md5(uniqid()), 0, 4);
    
    return strtolower($name . '-' . $short_hash . $ext);
});
