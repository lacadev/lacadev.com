<?php

namespace App\Databases;

/**
 * Quản lý custom table wp_laca_project_alerts
 * Lưu trữ các cảnh báo của dự án:
 * plugin cần update, SSL/domain sắp hết hạn, lỗi bảo mật, ...
 */
class ProjectAlertTable
{
    const TABLE_NAME    = 'laca_project_alerts';
    const TABLE_VERSION = '1.1.0';
    const VERSION_KEY   = 'laca_project_alert_table_version';

    public static function getTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    public static function install(): void
    {
        global $wpdb;

        $currentVersion = get_option(self::VERSION_KEY, '0');
        if (version_compare($currentVersion, self::TABLE_VERSION, '>=')) {
            return;
        }

        $tableName      = self::getTableName();
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id  BIGINT(20) UNSIGNED NOT NULL,
            alert_type  VARCHAR(50) NOT NULL DEFAULT 'note'
                        COMMENT 'plugin_update | ssl_expiry | domain_expiry | hosting_expiry | bug | security | other',
            alert_level VARCHAR(20) NOT NULL DEFAULT 'info'
                        COMMENT 'info | warning | critical',
            alert_msg   TEXT NOT NULL,
            is_resolved TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            resolved_at DATETIME NULL DEFAULT NULL,
            resolved_by VARCHAR(150) NULL DEFAULT NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY project_id  (project_id),
            KEY alert_type  (alert_type),
            KEY alert_level (alert_level),
            KEY is_resolved (is_resolved),
            KEY project_active_created (project_id, is_resolved, created_at)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::VERSION_KEY, self::TABLE_VERSION);
    }

    public static function uninstall(): void
    {
        global $wpdb;
        $tableName = self::getTableName();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$tableName}");
        delete_option(self::VERSION_KEY);
    }
}
