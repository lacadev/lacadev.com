<?php

namespace App\Settings\LacaTools;

class Security
{
    protected $currentUser;

    protected $errorMessage = '';

    public function __construct()
    {
        add_action('carbon_fields_fields_registered', [$this, 'applyOptions']);
    }

    public function applyOptions(): void
    {
        if (carbon_get_theme_option('disable_rest_api') === 'yes') {
            $this->disableRestApi();
        }

        if (carbon_get_theme_option('disable_xml_rpc') === 'yes') {
            $this->disableXmlRpc();
        }

        if (carbon_get_theme_option('disable_wp_embed') === 'yes') {
            $this->disableWpEmbed();
        }

        if (carbon_get_theme_option('disable_x_pingback') === 'yes') {
            $this->disableXPingback();
        }

        if (carbon_get_theme_option('enable_remove_wordpress_bloat') === 'yes') {
            $this->removeWordPressBloat();
        }

        if (carbon_get_theme_option('enable_optimize_database_queries') === 'yes') {
            $this->optimizeDatabaseQueries();
        }

        if (carbon_get_theme_option('enable_optimize_memory_usage') === 'yes') {
            $this->optimizeMemoryUsage();
        }
    }

    public function disableRestApi()
    {
        add_filter('rest_authentication_errors', function ($result) {
            if (true === $result || is_wp_error($result)) {
                return $result;
            }
            if (!is_user_logged_in()) {
                return new \WP_Error('rest_not_logged_in', __('You are not logged in', 'laca'), ['status' => 401]);
            }
            return $result;
        });
    }

    public function disableXmlRpc()
    {
        add_filter('xmlrpc_enabled', '__return_false');
        add_filter('wp_xmlrpc_server_class', '__return_false');
    }

    public function disableWpEmbed()
    {
        add_action('init', function () {
            wp_deregister_script('wp-embed');
            remove_action('wp_head', 'wp_oembed_add_discovery_links');
            remove_action('wp_head', 'wp_oembed_add_host_js');
        });
    }

    public function disableXPingback()
    {
        add_filter('wp_headers', function (array $headers): array {
            unset($headers['X-Pingback']);
            return $headers;
        });
    }

    /**
     * Loại bỏ bloat WordPress: RSD link, wlwmanifest, shortlink, generator tag,
     * adjacent posts links, REST API link header.
     */
    public function removeWordPressBloat()
    {
        add_action('init', function () {
            remove_action('wp_head', 'rsd_link');
            remove_action('wp_head', 'wlwmanifest_link');
            remove_action('wp_head', 'wp_shortlink_wp_head');
            remove_action('wp_head', 'wp_generator');
            remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);
            remove_action('wp_head', 'rest_output_link_wp_head', 10);
            remove_action('template_redirect', 'rest_output_link_header', 11);
            remove_action('wp_head', 'wp_oembed_add_discovery_links');
        });
    }

    /**
     * Giới hạn post revision (giữ tối đa 3) và tăng autosave interval lên 5 phút.
     */
    public function optimizeDatabaseQueries()
    {
        if (!defined('WP_POST_REVISIONS')) {
            define('WP_POST_REVISIONS', 3);
        }
        if (!defined('AUTOSAVE_INTERVAL')) {
            define('AUTOSAVE_INTERVAL', 300);
        }
    }

    /**
     * Tăng memory limit lên 256MB và bật PHP garbage collection.
     */
    public function optimizeMemoryUsage()
    {
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '256M');
        }
        if (function_exists('gc_enable')) {
            gc_enable();
        }
    }
}
