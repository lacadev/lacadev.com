<?php

namespace App\Settings\LacaTools;

class Security
{
    protected $currentUser;

	protected $errorMessage = '';

    public function __construct()
    {
        $this->currentUser = wp_get_current_user();

        // Enhance website security
        if (get_option('_disable_rest_api') === 'yes') {
            $this->disableRestApi();
        }

        if (get_option('_disable_wp_embed') === 'yes') {
            $this->disableWpEmbed();
        }

        if (get_option('_disable_wp_cron') === 'yes') {
            $this->disableWpCron();
        }

        if (get_option('_disable_x_pingback') === 'yes') {
            $this->disableXPingback();
        }
    }

    public function disableRestApi()
    {
        add_filter( 'rest_authentication_errors', function( $result ) {
            if ( true === $result || is_wp_error( $result ) ) {
                return $result;
            }

            // Check if the user is logged in
            if ( ! is_user_logged_in() ) {
                return new WP_Error( 'rest_not_logged_in',  __('You are not logged in', 'laca'), array( 'status' => 401 ) );
            }

            return $result;
        });
    }

    public function disableXmlRpc()
    {
        add_filter( 'wp_xmlrpc_server_class', '__return_false' );
        add_filter('xmlrpc_enabled', '__return_false');
        add_filter('pre_update_option_enable_xmlrpc', '__return_false');
        add_filter('pre_option_enable_xmlrpc', '__return_zero');
    }

    public function disableWpEmbed()
    {
        add_action('init', function() {
            wp_deregister_script('wp-embed');
            remove_action('wp_head', 'wp_oembed_add_discovery_links');
            remove_action('wp_head', 'wp_oembed_add_host_js');
        });
    }

    public function disableXPingback()
    {
        add_filter('wp_headers', function($headers) {
            if (isset($headers['X-Pingback'])) {
                unset($headers['X-Pingback']);
            }
            return $headers;
        });
    }

    /**
     * Giới hạn số lượng post revision và tăng autosave interval
     */
    public function optimizeDatabaseQueries()
    {
        if (!defined('WP_POST_REVISIONS')) {
            define('WP_POST_REVISIONS', 3);
        }
        if (!defined('AUTOSAVE_INTERVAL')) {
            define('AUTOSAVE_INTERVAL', 300); // 5 phút
        }
        if (function_exists('wp_cache_set')) {
            wp_cache_set('performance_optimized', true, 'theme', 3600);
        }
    }

    /**
     * Log các truy vấn SQL chậm để phát hiện truy vấn bất thường
     */
    public function optimizeSqlQueries($query)
    {
        if (strpos($query, 'SELECT') === 0) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $start_time = microtime(true);
                register_shutdown_function(function () use ($start_time, $query) {
                    $execution_time = microtime(true) - $start_time;
                    if ($execution_time > 0.5) {
                        error_log("Slow query detected: {$execution_time}s - {$query}");
                    }
                });
            }
        }
        return $query;
    }

    /**
     * Tăng memory limit và bật garbage collection
     */
    public function optimizeMemoryUsage()
    {
        if (function_exists('ini_get') && ini_get('memory_limit') < 256) {
            ini_set('memory_limit', '256M');
        }
        if (function_exists('gc_enable')) {
            gc_enable();
        }
    }

    /**
     * Dọn dẹp bộ nhớ cuối trang
     */
    public function cleanupMemory()
    {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * Đặt cache header bảo vệ trang admin và user login
     */
    public function setCacheHeaders()
    {
        if (!is_admin() && !is_user_logged_in()) {
            if (preg_match('/\.(css|js|png|jpg|jpeg|gif|webp|svg|woff|woff2|ttf|eot|ico)$/', $_SERVER['REQUEST_URI'])) {
                header('Cache-Control: public, max-age=31536000, immutable');
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
                header('Pragma: public');
            } else {
                header('Cache-Control: public, max-age=3600, must-revalidate');
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
                header('Vary: Accept-Encoding');
            }
        }
    }

    /**
     * Bật gzip để bảo vệ dữ liệu truyền tải
     */
    public function enableCompression()
    {
        if (!is_admin()) {
            if (function_exists('gzencode') && !ob_get_contents()) {
                ob_start('ob_gzhandler');
            }
        }
    }

    /**
     * Giám sát hiệu suất, phát hiện bất thường
     */
    public function addPerformanceMonitoring()
    {
        if (!is_admin()) {
            ?>
            <script>
                // Helper function to evaluate and log Web Vitals with detailed feedback
                function logWebVital(name, value, unit, thresholds) {
                    const { good, poor } = thresholds;
                    let rating, color, emoji, rangeInfo;
                    
                    if (value <= good) {
                        rating = 'TỐT ✓';
                        color = '#0cce6b';
                        emoji = '✓';
                        rangeInfo = `(0 - ${good}${unit})`;
                    } else if (value <= poor) {
                        rating = 'CẦN CẢI THIỆN ⚠';
                        color = '#ffa400';
                        emoji = '⚠';
                        rangeInfo = `(${good}${unit} - ${poor}${unit})`;
                    } else {
                        rating = 'KÉM ✗';
                        color = '#ff4e42';
                        emoji = '✗';
                        rangeInfo = `(> ${poor}${unit})`;
                    }
                    
                    console.log(
                        `%c${emoji} ${name}: ${value.toFixed(2)}${unit} - ${rating} ${rangeInfo}`,
                        `color: ${color}; font-weight: bold; font-size: 12px;`
                    );
                }

                // Core Web Vitals monitoring with detailed evaluation
                if ('PerformanceObserver' in window) {
                    // Largest Contentful Paint (LCP)
                    // Good: ≤2500ms | Needs Improvement: ≤4000ms | Poor: >4000ms
                    new PerformanceObserver((entryList) => {
                        for (const entry of entryList.getEntries()) {
                            logWebVital('LCP', entry.startTime, 'ms', { good: 2500, poor: 4000 });
                        }
                    }).observe({ type: 'largest-contentful-paint', buffered: true });

                    // Cumulative Layout Shift (CLS)
                    // Good: ≤0.1 | Needs Improvement: ≤0.25 | Poor: >0.25
                    let clsScore = 0;
                    new PerformanceObserver((entryList) => {
                        for (const entry of entryList.getEntries()) {
                            if (!entry.hadRecentInput) {
                                clsScore += entry.value;
                                logWebVital('CLS', clsScore, '', { good: 0.1, poor: 0.25 });
                            }
                        }
                    }).observe({ type: 'layout-shift', buffered: true });

                    // First Input Delay (FID)
                    // Good: ≤100ms | Needs Improvement: ≤300ms | Poor: >300ms
                    new PerformanceObserver((entryList) => {
                        for (const entry of entryList.getEntries()) {
                            const fid = entry.processingStart - entry.startTime;
                            logWebVital('FID', fid, 'ms', { good: 100, poor: 300 });
                        }
                    }).observe({ type: 'first-input', buffered: true });
                }
                
                // Performance marks
                if ('performance' in window && 'mark' in performance) {
                    performance.mark('theme-loaded');
                    
                    // Log page load timing
                    window.addEventListener('load', () => {
                        setTimeout(() => {
                            const perfData = performance.getEntriesByType('navigation')[0];
                            if (perfData) {
                                console.log('%c📊 Page Load Metrics:', 'color: #4285f4; font-weight: bold; font-size: 14px;');
                                console.log(`  DOM Content Loaded: ${perfData.domContentLoadedEventEnd.toFixed(2)}ms`);
                                console.log(`  Page Load Complete: ${perfData.loadEventEnd.toFixed(2)}ms`);
                                console.log(`  DNS Lookup: ${(perfData.domainLookupEnd - perfData.domainLookupStart).toFixed(2)}ms`);
                                console.log(`  TCP Connection: ${(perfData.connectEnd - perfData.connectStart).toFixed(2)}ms`);
                            }
                        }, 0);
                    });
                }
            </script>
            <?php
        }
    }
}