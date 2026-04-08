<?php

namespace App\Settings\LacaTools;

class Optimize
{
    protected $currentUser;

    protected $errorMessage = '';

    public function __construct()
    {
        // carbon_get_theme_option() requires CF to be fully booted.
        // Defer all option reads until carbon_fields_fields_registered fires.
        add_action('carbon_fields_fields_registered', [$this, 'applyOptions']);
    }

    public function applyOptions(): void
    {
        if (carbon_get_theme_option('disable_use_jquery_migrate') === 'yes') {
            $this->disableUseJqueryMigrate();
        }

        if (carbon_get_theme_option('disable_gutenberg_css') === 'yes') {
            $this->disableGutenbergCss();
        }

        if (carbon_get_theme_option('disable_classic_css') === 'yes') {
            $this->disableClassicCss();
        }

        if (carbon_get_theme_option('disable_emoji') === 'yes') {
            $this->disableEmoji();
        }

        if (carbon_get_theme_option('enable_instant_page') === 'yes') {
            $this->enableInstantPage();
        }

        if (carbon_get_theme_option('enable_smooth_scroll') === 'yes') {
            $this->enableSmoothScroll();
        }

        if (carbon_get_theme_option('remove_comments') === 'yes') {
            $this->removeHtmlComments();
        }

        if (carbon_get_theme_option('enable_advanced_resource_hints') === 'yes') {
            $this->enableAdvancedResourceHints();
        }

        if (carbon_get_theme_option('enable_optimize_images') === 'yes') {
            $this->optimizeImageAttributes();
        }

        if (carbon_get_theme_option('enable_optimize_content_images') === 'yes') {
            $this->optimizeContentImages();
        }

        if (carbon_get_theme_option('enable_register_service_worker') === 'yes') {
            $this->registerServiceWorker();
        }
    }

    public function disableUseJqueryMigrate()
    {
        add_action('wp_default_scripts', function ($scripts) {
            if (!is_admin() && isset($scripts->registered['jquery'])) {
                $script = $scripts->registered['jquery'];
                if ($script->deps) {
                    $script->deps = array_diff($script->deps, ['jquery-migrate']);
                }
            }
        });
    }

    public function disableGutenbergCss()
    {
        add_action('wp_enqueue_scripts', function () {
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wp-block-library-theme');
            wp_dequeue_style('wc-blocks-style');
        });
    }

    public function disableClassicCss()
    {
        add_action('wp_enqueue_scripts', function () {
            wp_dequeue_style('classic-theme-styles');
        });
    }

    public function disableEmoji()
    {
        add_action('init', function () {
            remove_action('wp_head', 'print_emoji_detection_script', 7);
            remove_action('admin_print_scripts', 'print_emoji_detection_script');
            remove_action('wp_print_styles', 'print_emoji_styles');
            remove_action('admin_print_styles', 'print_emoji_styles');
            remove_filter('the_content_feed', 'wp_staticize_emoji');
            remove_filter('comment_text_rss', 'wp_staticize_emoji');
            remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        });
    }

    public function enableInstantPage()
    {
        add_action('wp_enqueue_scripts', function () {
            wp_enqueue_script('instantpage', get_template_directory_uri() . '/dist/instantpage.js', array(), '5.7.0', true);
        });
    }

    public function enableSmoothScroll()
    {
        add_action('wp_enqueue_scripts', function () {
            wp_enqueue_script('smooth-scroll', get_template_directory_uri() . '/dist/smooth-scroll.min.js', array(), '1.4.16', true);
        });
    }

    /**
     * Xoá HTML comments khỏi output (trừ IE conditionals).
     */
    public function removeHtmlComments()
    {
        add_action('template_redirect', function () {
            if (is_admin() || is_feed()) return;
            ob_start(function (string $html): string {
                return preg_replace('/<!--(?!\[if)(?!-->).*?-->/s', '', $html) ?: $html;
            });
        });
    }

    /**
     * Thêm preconnect / dns-prefetch cho Google Fonts và CDN thông dụng.
     */
    public function enableAdvancedResourceHints()
    {
        add_action('wp_head', function () {
            echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
            echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
            echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
            echo '<link rel="dns-prefetch" href="//fonts.gstatic.com">' . "\n";
        }, 1);
    }

    /**
     * Tự động thêm loading="lazy", alt rỗng (nếu chưa có), width & height vào <img> trong the_post_thumbnail.
     */
    public function optimizeImageAttributes()
    {
        add_filter('wp_get_attachment_image_attributes', function (array $attr, \WP_Post $attachment): array {
            if (!isset($attr['loading'])) {
                $attr['loading'] = 'lazy';
            }
            if (!isset($attr['alt']) || $attr['alt'] === '') {
                $attr['alt'] = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true) ?: '';
            }
            return $attr;
        }, 10, 2);
    }

    /**
     * Tự động thêm loading="lazy" vào <img> bên trong nội dung bài viết.
     */
    public function optimizeContentImages()
    {
        add_filter('the_content', function (string $content): string {
            if (is_admin() || empty($content)) return $content;
            return preg_replace_callback(
                '/<img(?![^>]*loading=)[^>]*>/i',
                fn($m) => str_replace('<img', '<img loading="lazy"', $m[0]),
                $content
            ) ?: $content;
        });
    }

    /**
     * Đăng ký Service Worker (file sw.js phải tồn tại ở root theme).
     */
    public function registerServiceWorker()
    {
        add_action('wp_footer', function () {
            $swUrl = get_template_directory_uri() . '/dist/sw.js';
            echo '<script>if("serviceWorker"in navigator){navigator.serviceWorker.register(' . wp_json_encode($swUrl) . ').catch(()=>{})};</script>' . "\n";
        }, 99);
    }
}
