<?php

namespace App\Settings\LacaTools;

class ProjectTrackerGenerator
{
    public function init(): void
    {
        add_action('wp_ajax_laca_get_tracker_code', [$this, 'generateCodeAjax']);
    }

    /**
     * Trả về đoạn code PHP để client cài dưới dạng mu-plugin
     */
    public function generateCodeAjax(): void
    {
        check_ajax_referer('laca_project_manager', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Không có quyền']);
        }

        $projectId = absint($_POST['project_id'] ?? 0);
        if (!$projectId) {
            wp_send_json_error(['message' => 'Lỗi ID dự án']);
        }

        $secretKey = get_post_meta($projectId, '_tracker_secret_key', true);
        if (empty($secretKey)) {
            wp_send_json_error(['message' => 'Dự án này chưa có Secret Key. Vui lòng F5 trang lấy lại mã.']);
        }

        $endpoint = rest_url('laca/v1/tracker/log');
        $code     = $this->getMuPluginTemplate($endpoint, $secretKey);

        wp_send_json_success(['code' => $code]);
    }

    private function getMuPluginTemplate(string $endpoint, string $secretKey): string
    {
        return <<<PHP
<?php
/**
 * Plugin Name: LacaDev Project Tracker
 * Description: Tự động log cập nhật plugin/theme/core, kích hoạt/tắt/xóa plugin, phát hiện file lạ và gửi daily update digest về LacaDev CMS.
 * Version: 2.0.0
 * Network: false
 * Author: MOOMS.DEV
 */

if (!defined('ABSPATH')) {
    exit;
}

// ──────────────────────────────────────────────
// CẤU HÌNH (được điền sẵn từ lacadev.com)
// ──────────────────────────────────────────────
define('LACA_TRACKER_ENDPOINT',   '{$endpoint}');
define('LACA_TRACKER_SECRET_KEY', '{$secretKey}');

// ──────────────────────────────────────────────
// MAIN CLASS
// ──────────────────────────────────────────────
class LacaDev_MU_Tracker {

    /** Extension file đáng ngờ trong uploads / mu-plugins */
    const SUSPICIOUS_EXTS = ['php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar'];

    /** Option key lưu baseline filemtime */
    const OPT_BASELINE = '_laca_mu_tracker_baseline';

    /** Option để theo dõi các plugin đã biết là cần update (tránh gửi alert trùng) */
    const OPT_KNOWN_UPDATES = '_laca_mu_tracker_known_updates';

    /** Cron hooks */
    const CRON_HOURLY = 'laca_mu_tracker_hourly';
    const CRON_DAILY  = 'laca_mu_tracker_daily';

    public function __construct() {
        // --- Event hooks: gửi log tức thì ---
        add_action('upgrader_process_complete', [\$this, 'onUpgraderComplete'], 20, 2);
        add_action('delete_plugin',             [\$this, 'onDeletePlugin']);
        add_action('deleted_plugin',            [\$this, 'afterDeletePlugin'], 10, 2);
        add_action('activated_plugin',          [\$this, 'onActivatePlugin']);
        add_action('deactivated_plugin',        [\$this, 'onDeactivatePlugin']);

        // --- Phát hiện plugin cần update NGAY KHI WP check (không đợi cron) ---
        add_filter('set_site_transient_update_plugins', [\$this, 'onUpdateTransientSet']);

        // --- Cron hàng giờ: quét file lạ ---
        add_action(self::CRON_HOURLY, [\$this, 'runHourlyScan']);
        if (!wp_next_scheduled(self::CRON_HOURLY)) {
            wp_schedule_event(time(), 'hourly', self::CRON_HOURLY);
        }

        // --- Cron hàng ngày: update digest + file integrity ---
        add_action(self::CRON_DAILY, [\$this, 'runDailyDigest']);
        if (!wp_next_scheduled(self::CRON_DAILY)) {
            \$nextRun = strtotime('tomorrow 01:00:00 UTC'); // 8:00 sáng GMT+7
            wp_schedule_event(\$nextRun, 'daily', self::CRON_DAILY);
        }
    }

    // ──────────────────────────────────────────
    // EVENT HOOKS
    // ──────────────────────────────────────────

    public function onUpgraderComplete(\$upgrader, \$options): void {
        \$action = \$options['action'] ?? '';
        \$type   = \$options['type']   ?? '';

        if (!\$action || !\$type || !in_array(\$action, ['update', 'install'], true)) {
            return;
        }

        \$logs = [];

        if (\$type === 'plugin') {
            \$plugins = (array) (\$options['plugins'] ?? []);
            if (\$action === 'install' && !empty(\$upgrader->new_plugin_data)) {
                \$name    = \$upgrader->new_plugin_data['Name']    ?? 'Không rõ';
                \$version = \$upgrader->new_plugin_data['Version'] ?? '';
                \$logs[]  = [
                    'type'    => 'plugin_install',
                    'content' => "Cài mới plugin: {\$name}" . (\$version ? " v{\$version}" : ''),
                    'level'   => 'info',
                ];
            } else {
                foreach (\$plugins as \$plugin) {
                    \$data    = get_plugin_data(WP_PLUGIN_DIR . '/' . \$plugin, false, false);
                    \$name    = \$data['Name']    ?? \$plugin;
                    \$version = \$data['Version'] ?? '';
                    \$logs[]  = [
                        'type'    => 'plugin_update',
                        'content' => "Cập nhật plugin: {\$name}" . (\$version ? " → v{\$version}" : ''),
                        'level'   => 'info',
                    ];
                }
            }
        } elseif (\$type === 'theme') {
            \$themes = (array) (\$options['themes'] ?? []);
            foreach (\$themes as \$theme) {
                \$data    = wp_get_theme(\$theme);
                \$name    = \$data->get('Name')    ?: \$theme;
                \$version = \$data->get('Version') ?: '';
                \$logs[]  = [
                    'type'    => 'theme_update',
                    'content' => "Cập nhật theme: {\$name}" . (\$version ? " → v{\$version}" : ''),
                    'level'   => 'info',
                ];
            }
        } elseif (\$type === 'core') {
            \$wpVersion = get_bloginfo('version');
            \$logs[]    = [
                'type'    => 'core_update',
                'content' => "Cập nhật WordPress Core → v{\$wpVersion}",
                'level'   => 'info',
            ];
        }

        if (!empty(\$logs)) {
            delete_option(self::OPT_BASELINE); // reset baseline sau update
            \$this->send(\$logs);
        }
    }

    public function onDeletePlugin(string \$pluginFile): void {
        \$data = get_plugin_data(WP_PLUGIN_DIR . '/' . \$pluginFile, false, false);
        set_transient('_laca_mu_deleting', \$data['Name'] ?? \$pluginFile, 60);
    }

    public function afterDeletePlugin(string \$pluginFile, bool \$deleted): void {
        if (!\$deleted) return;
        \$name = get_transient('_laca_mu_deleting') ?: \$pluginFile;
        delete_transient('_laca_mu_deleting');
        \$this->send([[
            'type'    => 'plugin_delete',
            'content' => "⚠️ Đã xóa plugin: {\$name}",
            'level'   => 'warning',
        ]]);
    }

    public function onActivatePlugin(string \$pluginFile): void {
        \$data    = get_plugin_data(WP_PLUGIN_DIR . '/' . \$pluginFile, false, false);
        \$name    = \$data['Name']    ?? \$pluginFile;
        \$version = \$data['Version'] ?? '';
        \$this->send([[
            'type'    => 'plugin_activate',
            'content' => "✅ Kích hoạt plugin: {\$name}" . (\$version ? " v{\$version}" : ''),
            'level'   => 'info',
        ]]);
    }

    public function onDeactivatePlugin(string \$pluginFile): void {
        \$data = get_plugin_data(WP_PLUGIN_DIR . '/' . \$pluginFile, false, false);
        \$name = \$data['Name'] ?? \$pluginFile;
        \$this->send([[
            'type'    => 'plugin_deactivate',
            'content' => "🔴 Tắt plugin: {\$name}",
            'level'   => 'warning',
        ]]);
    }

    // ──────────────────────────────────────────
    // REAL-TIME UPDATE DETECTION
    // ──────────────────────────────────────────

    /**
     * Chạy ngay khi WP lưu kết quả check update mới từ wordpress.org.
     * So sánh với lần check trước, gửi alert ngay nếu có plugin mới cần update.
     */
    public function onUpdateTransientSet(\$value): mixed {
        if (empty(\$value->response) || !is_array(\$value->response)) {
            delete_option(self::OPT_KNOWN_UPDATES);
            return \$value;
        }

        \$currentKeys = array_keys(\$value->response);
        sort(\$currentKeys);

        \$knownKeys = (array) get_option(self::OPT_KNOWN_UPDATES, []);
        sort(\$knownKeys);

        \$newlyFound = array_diff(\$currentKeys, \$knownKeys);

        if (!empty(\$newlyFound)) {
            \$logs    = [];
            \$plugins = [];
            foreach (\$newlyFound as \$pluginFile) {
                \$data       = get_plugin_data(WP_PLUGIN_DIR . '/' . \$pluginFile, false, false);
                \$name       = \$data['Name']    ?? \$pluginFile;
                \$current    = \$data['Version'] ?? '?';
                \$newVersion = \$value->response[\$pluginFile]->new_version ?? '?';
                \$plugins[] = [
                    'slug'            => \$pluginFile,
                    'name'            => \$name,
                    'current_version' => \$current,
                    'new_version'     => \$newVersion,
                ];
                \$logs[] = [
                    'type'    => 'update_pending',
                    'content' => "⚠️ Plugin cần update: {\$name}\n  Hiện tại: {\$current} → Bản mới: {\$newVersion}",
                    'level'   => 'warning',
                    'plugins' => [[
                        'slug'            => \$pluginFile,
                        'name'            => \$name,
                        'current_version' => \$current,
                        'new_version'     => \$newVersion,
                    ]],
                ];
            }
            if (!empty(\$logs)) {
                \$this->send(\$logs);
            }
        }

        update_option(self::OPT_KNOWN_UPDATES, \$currentKeys, false);
        return \$value;
    }

    // ──────────────────────────────────────────
    // CRON HOURLY — quét file lạ
    // ──────────────────────────────────────────

    public function runHourlyScan(): void {
        \$found = [];

        // 1. Root: file PHP lạ + file html/js bất thường
        \$this->scanRoot(rtrim(ABSPATH, '/'), \$found);

        // 2. uploads & mu-plugins: đệ quy tìm PHP
        \$watchDirs = ['wp-content/uploads', 'wp-content/mu-plugins'];
        foreach (\$watchDirs as \$rel) {
            \$abs = rtrim(ABSPATH, '/') . '/' . \$rel;
            if (is_dir(\$abs)) {
                \$this->scanRecursive(\$abs, \$rel . '/', \$found);
            }
        }

        if (!\$found) return;

        \$list = implode("\n", array_map(fn(\$f) => "  - {\$f}", \$found));
        \$this->send([[
            'type'    => 'file_suspicious',
            'content' => "⚠️ Phát hiện file đáng ngờ:\n{\$list}",
            'level'   => 'critical',
        ]]);
    }

    private function scanRoot(string \$absRoot, array &\$found): void {
        \$allowed = [
            'wp-config.php', 'wp-config-sample.php', '.htaccess', 'index.php',
            'wp-activate.php', 'wp-blog-header.php', 'wp-comments-post.php',
            'wp-cron.php', 'wp-links-opml.php', 'wp-load.php', 'wp-login.php',
            'wp-mail.php', 'wp-settings.php', 'wp-signup.php', 'wp-trackback.php',
            'xmlrpc.php', 'readme.html', 'license.txt', '.user.ini', 'php.ini',
            'robots.txt', 'sitemap.xml', 'sitemap_index.xml',
        ];

        // File PHP lạ ở root
        foreach (glob(\$absRoot . '/*.php') ?: [] as \$f) {
            if (!in_array(basename(\$f), \$allowed, true)) {
                \$found[] = '/' . basename(\$f) . ' [PHP lạ ở root]';
            }
        }

        // File html/htm/js lạ ở root
        foreach (array_merge(glob(\$absRoot . '/*.html') ?: [], glob(\$absRoot . '/*.htm') ?: []) as \$f) {
            if (!in_array(basename(\$f), ['readme.html'], true)) {
                \$found[] = '/' . basename(\$f) . ' [file lạ ở root]';
            }
        }

        // Kiểm tra shell pattern trong .htaccess & wp-config.php
        foreach (['.htaccess', 'wp-config.php'] as \$file) {
            \$full = \$absRoot . '/' . \$file;
            if (file_exists(\$full)) {
                \$this->detectShellPatterns(\$full, \$file, \$found);
            }
        }
    }

    private function scanRecursive(string \$absDir, string \$relPrefix, array &\$found): void {
        try {
            \$it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(\$absDir, FilesystemIterator::SKIP_DOTS)
            );
            foreach (\$it as \$file) {
                if (!\$file->isFile()) continue;
                if (in_array(strtolower(\$file->getExtension()), self::SUSPICIOUS_EXTS, true)) {
                    \$found[] = \$relPrefix . \$it->getSubPathname();
                }
            }
        } catch (UnexpectedValueException) {
            // Permission denied — bỏ qua
        }
    }

    private function detectShellPatterns(string \$path, string \$label, array &\$found): void {
        \$content = @file_get_contents(\$path, false, null, 0, 51200);
        if (!\$content) return;

        \$patterns = [
            'eval(base64_decode', 'eval(gzinflate', 'eval(\$_POST', 'eval(\$_GET',
            'assert(\$_', 'system(\$_', 'passthru(\$_', 'shell_exec(\$_',
            'FilesMan', 'c99shell', 'r57shell',
        ];

        foreach (\$patterns as \$p) {
            if (stripos(\$content, \$p) !== false) {
                \$found[] = "{\$label} [pattern đáng ngờ: '{\$p}']";
                break;
            }
        }
    }

    // ──────────────────────────────────────────
    // CRON DAILY — update digest + file integrity
    // ──────────────────────────────────────────

    public function runDailyDigest(): void {
        \$logs = [];

        // Plugin updates pending
        wp_update_plugins();
        \$pluginUpdates = get_site_transient('update_plugins');
        if (!empty(\$pluginUpdates->response)) {
            \$lines   = [];
            \$plugins = [];
            foreach (\$pluginUpdates->response as \$file => \$data) {
                \$info    = get_plugin_data(WP_PLUGIN_DIR . '/' . \$file, false, false);
                \$name    = \$info['Name']    ?? \$file;
                \$current = \$info['Version'] ?? '?';
                \$new     = \$data->new_version ?? '?';
                \$lines[] = "  - {\$name}: {\$current} → {\$new}";
                \$plugins[] = [
                    'slug'            => \$file,
                    'name'            => \$name,
                    'current_version' => \$current,
                    'new_version'     => \$new,
                ];
            }
            \$cnt    = count(\$lines);
            \$logs[] = [
                'type'    => 'update_pending',
                'content' => "📦 {\$cnt} plugin chờ update:\n" . implode("\n", \$lines),
                'level'   => 'warning',
                'plugins' => \$plugins,
            ];
        }

        // Theme updates pending
        wp_update_themes();
        \$themeUpdates = get_site_transient('update_themes');
        if (!empty(\$themeUpdates->response)) {
            \$lines = [];
            foreach (\$themeUpdates->response as \$slug => \$data) {
                \$theme   = wp_get_theme(\$slug);
                \$name    = \$theme->get('Name') ?: \$slug;
                \$current = \$theme->get('Version') ?: '?';
                \$new     = \$data['new_version'] ?? '?';
                \$lines[] = "  - {\$name}: {\$current} → {\$new}";
            }
            \$cnt    = count(\$lines);
            \$logs[] = [
                'type'    => 'update_pending',
                'content' => "🎨 {\$cnt} theme chờ update:\n" . implode("\n", \$lines),
                'level'   => 'warning',
            ];
        }

        // Core update pending
        wp_version_check();
        \$coreUpdates = get_site_transient('update_core');
        foreach ((\$coreUpdates->updates ?? []) as \$upd) {
            if ((\$upd->response ?? '') === 'upgrade') {
                \$logs[] = [
                    'type'    => 'update_pending',
                    'content' => "🔄 WordPress Core: " . get_bloginfo('version') . " → {\$upd->version}",
                    'level'   => 'warning',
                ];
                break;
            }
        }

        // File integrity: theme + plugins active
        \$modifiedFiles = \$this->checkFileIntegrity();
        if (!empty(\$modifiedFiles)) {
            \$list   = implode("\n", array_map(fn(\$f) => "  - {\$f}", \$modifiedFiles));
            \$logs[] = [
                'type'    => 'file_changed',
                'content' => "📝 File theme/plugin bị thay đổi:\n{\$list}",
                'level'   => 'critical',
            ];
        }

        if (!\$logs) return;
        \$this->send(\$logs);
    }

    private function checkFileIntegrity(): array {
        \$baseline = get_option(self::OPT_BASELINE, []);
        \$current  = [];
        \$changed  = [];

        // Thu thập file cần theo dõi: theme active + plugins active
        \$activeTheme = get_stylesheet_directory();
        \$themeSlug   = get_stylesheet();
        foreach (array_merge(glob(\$activeTheme . '/*.php') ?: [], glob(\$activeTheme . '/*.css') ?: []) as \$f) {
            \$label           = "themes/{\$themeSlug}/" . basename(\$f);
            \$current[\$label] = filemtime(\$f);
        }

        foreach ((array) get_option('active_plugins', []) as \$rel) {
            \$abs = WP_PLUGIN_DIR . '/' . \$rel;
            if (file_exists(\$abs)) {
                \$current['plugins/' . \$rel] = filemtime(\$abs);
            }
        }

        // So với baseline
        if (!empty(\$baseline)) {
            foreach (\$current as \$label => \$mtime) {
                if (!isset(\$baseline[\$label])) {
                    \$changed[] = "{\$label} [mới — " . date('d/m H:i', \$mtime) . "]";
                } elseif (\$baseline[\$label] !== \$mtime) {
                    \$changed[] = "{\$label} [sửa — " . date('d/m H:i', \$mtime) . "]";
                }
            }
        }

        // Lưu baseline mới
        update_option(self::OPT_BASELINE, \$current, false);

        return \$changed;
    }

    // ──────────────────────────────────────────
    // SENDER
    // ──────────────────────────────────────────

    private function send(array \$logs): void {
        if (!LACA_TRACKER_ENDPOINT || !LACA_TRACKER_SECRET_KEY || empty(\$logs)) return;

        wp_remote_post(LACA_TRACKER_ENDPOINT, [
            'body'    => wp_json_encode([
                'secret_key' => LACA_TRACKER_SECRET_KEY,
                'site_url'   => get_bloginfo('url'),
                'logs'       => \$logs,
            ], JSON_UNESCAPED_UNICODE),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 8,
            'blocking' => false,
        ]);
    }

}

new LacaDev_MU_Tracker();
PHP;
    }
}
