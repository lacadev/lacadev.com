<?php

namespace App\Models;

use App\Databases\ProjectAlertTable;

/**
 * Model xử lý CRUD cho bảng wp_laca_project_alerts
 *
 * Alert types: plugin_update | ssl_expiry | domain_expiry | hosting_expiry | bug | security | other
 * Alert levels: info | warning | critical
 */
class ProjectAlert
{
    /**
     * Thêm một cảnh báo mới
     *
     * @param array $data {
     *     @type int    $project_id  (bắt buộc)
     *     @type string $alert_type  (mặc định 'other')
     *     @type string $alert_level (mặc định 'info')
     *     @type string $alert_msg   (bắt buộc)
     * }
     * @return int|false
     */
    public static function add(array $data)
    {
        global $wpdb;

        if (empty($data['project_id']) || empty($data['alert_msg'])) {
            return false;
        }

        $allowedTypes  = ['plugin_update', 'ssl_expiry', 'domain_expiry', 'hosting_expiry', 'bug', 'security', 'other'];
        $allowedLevels = ['info', 'warning', 'critical'];

        $type  = in_array($data['alert_type'] ?? '', $allowedTypes, true) ? $data['alert_type'] : 'other';
        $level = in_array($data['alert_level'] ?? '', $allowedLevels, true) ? $data['alert_level'] : 'info';

        $inserted = $wpdb->insert(
            ProjectAlertTable::getTableName(),
            [
                'project_id'  => absint($data['project_id']),
                'alert_type'  => $type,
                'alert_level' => $level,
                'alert_msg'   => self::stripEmoji(sanitize_textarea_field($data['alert_msg'])),
                'is_resolved' => 0,
                'created_at'  => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s']
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Lấy tất cả cảnh báo CHƯA xử lý của một dự án
     *
     * @param int $projectId
     * @return array
     */
    public static function getActive(int $projectId): array
    {
        global $wpdb;
        $table = ProjectAlertTable::getTableName();

        return $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$table}
                 WHERE project_id = %d AND is_resolved = 0
                 ORDER BY FIELD(alert_level, 'critical', 'warning', 'info'), created_at DESC",
                $projectId
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Lấy tất cả cảnh báo critical chưa xử lý (dùng cho Dashboard)
     *
     * @return array
     */
    public static function getAllActiveCritical(): array
    {
        global $wpdb;
        $table = ProjectAlertTable::getTableName();

        return $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT a.*, p.post_title AS project_name
                 FROM {$table} a
                 LEFT JOIN {$wpdb->posts} p ON p.ID = a.project_id
                 WHERE a.is_resolved = 0
                 ORDER BY FIELD(a.alert_level, 'critical', 'warning', 'info'), a.created_at DESC
                 LIMIT %d",
                50
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Đánh dấu đã xử lý (resolve)
     *
     * @param int $alertId
     * @param int $projectId Dùng để xác thực quyền sở hữu
     * @return bool
     */
    public static function resolve(int $alertId, int $projectId = 0): bool
    {
        global $wpdb;

        $user    = wp_get_current_user();
        $where   = ['id' => absint($alertId)];
        $formats = ['%d'];

        if ($projectId > 0) {
            $where['project_id'] = absint($projectId);
            $formats[]           = '%d';
        }

        $result = $wpdb->update(
            ProjectAlertTable::getTableName(),
            [
                'is_resolved' => 1,
                'resolved_at' => current_time('mysql'),
                'resolved_by' => $user->exists() ? sanitize_text_field($user->display_name) : 'System',
            ],
            $where,
            ['%d', '%s', '%s'],
            $formats
        );

        return $result !== false && $result > 0;
    }

    /**
     * Xoá một cảnh báo
     */
    public static function delete(int $alertId, int $projectId = 0): bool
    {
        global $wpdb;
        $where = ['id' => absint($alertId)];
        if ($projectId > 0) {
            $where['project_id'] = absint($projectId);
        }

        $result = $wpdb->delete(ProjectAlertTable::getTableName(), $where, array_fill(0, count($where), '%d'));
        return $result !== false && $result > 0;
    }

    /**
     * Đếm số cảnh báo chưa xử lý của một dự án
     *
     * @param int $projectId 0 = đếm toàn bộ
     */
    public static function countActive(int $projectId = 0): int
    {
        global $wpdb;
        $table = ProjectAlertTable::getTableName();

        if ($projectId > 0) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "SELECT COUNT(*) FROM {$table} WHERE project_id = %d AND is_resolved = 0",
                    $projectId
                )
            );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_resolved = 0");
    }

    /**
     * Tránh trùng lặp: kiểm tra alert cùng type + project chưa resolve chưa
     */
    public static function existsActive(int $projectId, string $alertType): bool
    {
        global $wpdb;
        $table = ProjectAlertTable::getTableName();

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT COUNT(*) FROM {$table}
                 WHERE project_id = %d AND alert_type = %s AND is_resolved = 0",
                $projectId,
                $alertType
            )
        );

        return $count > 0;
    }

    /**
     * Dedup theo nội dung: kiểm tra alert cùng msg + project chưa resolve.
     * Dùng để tránh tracker bot gửi trùng cùng một cảnh báo nhiều lần.
     */
    public static function existsActiveByMsg(int $projectId, string $msg): bool
    {
        global $wpdb;
        $table = ProjectAlertTable::getTableName();

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT COUNT(*) FROM {$table}
                 WHERE project_id = %d AND alert_msg = %s AND is_resolved = 0",
                $projectId,
                sanitize_textarea_field($msg)
            )
        );

        return $count > 0;
    }

    /**
     * Lấy TẤT CẢ alerts chưa resolve, có tên project, hỗ trợ filter + phân trang
     *
     * @param array $filters { project_id?, alert_level?, alert_type? }
     * @param int   $perPage
     * @param int   $page
     * @return array { items: array, total: int }
     */
    public static function getAllActiveFiltered(array $filters = [], int $perPage = 30, int $page = 1): array
    {
        global $wpdb;
        $table  = ProjectAlertTable::getTableName();
        $where  = ['a.is_resolved = 0'];
        $params = [];

        if (!empty($filters['project_id'])) {
            $where[]  = 'a.project_id = %d';
            $params[] = absint($filters['project_id']);
        }
        if (!empty($filters['alert_level'])) {
            $where[]  = 'a.alert_level = %s';
            $params[] = sanitize_key($filters['alert_level']);
        }
        if (!empty($filters['alert_type'])) {
            $where[]  = 'a.alert_type = %s';
            $params[] = sanitize_key($filters['alert_type']);
        }

        $whereClause = implode(' AND ', $where);
        $offset      = ($page - 1) * $perPage;

        // Đếm tổng
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $countSql = "SELECT COUNT(*) FROM {$table} a WHERE {$whereClause}";
        $total    = empty($params) ? (int) $wpdb->get_var($countSql) : (int) $wpdb->get_var($wpdb->prepare($countSql, ...$params)); // phpcs:ignore

        // Lấy dữ liệu
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $dataSql = "SELECT a.*, p.post_title AS project_name
                    FROM {$table} a
                    LEFT JOIN {$wpdb->posts} p ON p.ID = a.project_id
                    WHERE {$whereClause}
                    ORDER BY FIELD(a.alert_level, 'critical', 'warning', 'info'), a.created_at DESC
                    LIMIT %d OFFSET %d";

        $allParams   = array_merge($params, [$perPage, $offset]);
        $items       = (array) $wpdb->get_results($wpdb->prepare($dataSql, ...$allParams), ARRAY_A); // phpcs:ignore

        return ['items' => $items ?: [], 'total' => $total];
    }

    /**
     * Đếm tổng alerts chưa resolve toàn hệ thống (dùng cho badge menu)
     */
    public static function countAllActive(): int
    {
        return self::countActive(0);
    }

    /**
     * Bulk resolve: đánh dấu nhiều alert đã xử lý
     *
     * @param  int[] $alertIds
     * @return int   Số rows cập nhật
     */
    public static function bulkResolve(array $alertIds): int
    {
        global $wpdb;
        if (empty($alertIds)) {
            return 0;
        }

        $ids   = array_map('absint', $alertIds);
        $in    = implode(',', $ids);
        $table = ProjectAlertTable::getTableName();
        $user  = wp_get_current_user();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET is_resolved = 1,
                 resolved_at = %s,
                 resolved_by = %s
             WHERE id IN ({$in}) AND is_resolved = 0",
            current_time('mysql'),
            $user->exists() ? sanitize_text_field($user->display_name) : 'System'
        ));
    }

    /**
     * Label thân thiện cho alert type
     */
    public static function getTypeLabel(string $type): string
    {
        $labels = [
            'plugin_update'   => 'Plugin update',
            'ssl_expiry'      => 'SSL sắp hết hạn',
            'domain_expiry'   => 'Domain sắp hết hạn',
            'hosting_expiry'  => 'Hosting sắp hết hạn',
            'bug'             => 'Lỗi website',
            'security'        => 'Bảo mật',
            'other'           => 'Khác',
        ];
        return $labels[$type] ?? ucfirst($type);
    }

    /**
     * CSS class cho badge theo level
     */
    public static function getLevelClass(string $level): string
    {
        return match ($level) {
            'critical' => 'laca-alert--critical',
            'warning'  => 'laca-alert--warning',
            default    => 'laca-alert--info',
        };
    }

    /**
     * Loại bỏ emoji và ký tự 4-byte UTF-8 để tránh lỗi với DB charset utf8 (non-mb4).
     * Giữ nguyên tiếng Việt và ký tự Latin có dấu (≤ 3-byte).
     */
    public static function stripEmoji(string $text): string
    {
        // Xoá supplementary multilingual plane (U+10000–U+10FFFF) = emoji, flags...
        return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text) ?? $text;
    }
}
