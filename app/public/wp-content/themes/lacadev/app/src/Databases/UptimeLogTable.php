<?php

namespace App\Databases;

/**
 * Quản lý custom table wp_laca_uptime_log
 * Lưu trữ nhật ký kiểm tra uptime, mã phản hồi HTTP và thời gian phản hồi (ms)
 * của từng website trong hệ thống.
 */
class UptimeLogTable
{
    public const TABLE_NAME    = 'laca_uptime_log';
    public const TABLE_VERSION = '1.0.0';
    public const VERSION_KEY   = 'laca_uptime_log_table_version';

    /**
     * Lấy tên table có prefix của WordPress
     */
    public static function getTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Tạo bảng nếu chưa tồn tại hoặc cần upgrade
     */
    public static function install(): void
    {
        global $wpdb;

        $currentVersion = get_option(self::VERSION_KEY, '0');
        if (version_compare((string) $currentVersion, self::TABLE_VERSION, '>=')) {
            return;
        }

        $tableName      = self::getTableName();
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$tableName} (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id    BIGINT(20) UNSIGNED NOT NULL,
            url           VARCHAR(255) NOT NULL,
            status        VARCHAR(20) NOT NULL DEFAULT 'up' COMMENT 'up | down',
            http_code     INT NULL,
            response_ms   INT UNSIGNED NOT NULL DEFAULT 0,
            error_message TEXT NULL,
            checked_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY   (id),
            KEY project_id (project_id),
            KEY status     (status),
            KEY checked_at (checked_at)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::VERSION_KEY, self::TABLE_VERSION);
    }

    /**
     * Ghi một lượt kiểm tra uptime vào database
     */
    public static function log(
        int $projectId,
        string $url,
        string $status,
        ?int $httpCode = null,
        int $responseMs = 0,
        ?string $errorMessage = null
    ): int|false {
        global $wpdb;

        $inserted = $wpdb->insert(
            self::getTableName(),
            [
                'project_id'    => $projectId,
                'url'           => esc_url_raw($url),
                'status'        => $status === 'down' ? 'down' : 'up',
                'http_code'     => $httpCode,
                'response_ms'   => max(0, $responseMs),
                'error_message' => $errorMessage !== null ? sanitize_text_field($errorMessage) : null,
                'checked_at'    => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%d', '%d', '%s', '%s']
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Dọn dẹp logs cũ hơn $days ngày (mặc định 30 ngày)
     */
    public static function purgeOldLogs(int $days = 30): int
    {
        global $wpdb;
        $table = self::getTableName();
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE checked_at < %s", $cutoff));
        return (int) $deleted;
    }

    /**
     * Thống kê tỷ lệ uptime (%) và thời gian phản hồi trung bình trong N giờ gần nhất
     *
     * @return array{percentage: float, avg_ms: int, total_checks: int, up_checks: int, latest_ms: int, latest_status: string}
     */
    public static function getUptimeStats(int $projectId, int $hours = 24): array
    {
        global $wpdb;
        $table  = self::getTableName();
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($hours * HOUR_IN_SECONDS));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT status, response_ms FROM {$table} WHERE project_id = %d AND checked_at >= %s ORDER BY checked_at DESC",
                $projectId,
                $cutoff
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return [
                'percentage'    => 100.0,
                'avg_ms'        => 0,
                'total_checks'  => 0,
                'up_checks'     => 0,
                'latest_ms'     => 0,
                'latest_status' => 'unknown',
            ];
        }

        $totalChecks = count($rows);
        $upChecks    = 0;
        $totalMs     = 0;
        $latestMs    = (int) ($rows[0]['response_ms'] ?? 0);
        $latestStatus = (string) ($rows[0]['status'] ?? 'up');

        foreach ($rows as $row) {
            if ($row['status'] === 'up') {
                $upChecks++;
            }
            $totalMs += (int) $row['response_ms'];
        }

        $percentage = round(($upChecks / $totalChecks) * 100, 1);
        $avgMs      = (int) round($totalMs / $totalChecks);

        return [
            'percentage'    => $percentage,
            'avg_ms'        => $avgMs,
            'total_checks'  => $totalChecks,
            'up_checks'     => $upChecks,
            'latest_ms'     => $latestMs,
            'latest_status' => $latestStatus,
        ];
    }

    /**
     * Lấy lịch sử log gần nhất của một project
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getHistory(int $projectId, int $limit = 50): array
    {
        global $wpdb;
        $table = self::getTableName();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (array) $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$table} WHERE project_id = %d ORDER BY checked_at DESC LIMIT %d",
                $projectId,
                $limit
            ),
            ARRAY_A
        ) ?: [];
    }
}
