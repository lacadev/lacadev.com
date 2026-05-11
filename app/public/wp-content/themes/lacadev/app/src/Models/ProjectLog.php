<?php

namespace App\Models;

use App\Databases\ProjectLogTable;

/**
 * Model xử lý CRUD cho bảng wp_laca_project_logs
 * 
 * Log types:
 *  - note          : Ghi chú thủ công
 *  - plugin_update : Cập nhật plugin (có version cũ/mới)
 *  - plugin_activate / plugin_deactivate
 *  - core_update   : Cập nhật WordPress core
 *  - theme_switch  : Thay đổi theme
 *  - file_changed  : File code thay đổi bất thường
 *  - deployment    : Deploy code mới
 *  - bug_fix       : Sửa lỗi
 *  - security      : Sự kiện bảo mật
 *  - client_request: Yêu cầu từ khách hàng
 *  - maintenance_summary: Báo cáo bảo trì định kỳ từ website khách
 */
class ProjectLog
{
    /**
     * Thêm một mục nhật ký mới
     *
     * @param array $data {
     *     @type int    $project_id  ID bài viết project (bắt buộc)
     *     @type string $log_date    Ngày log, format Y-m-d (mặc định hôm nay)
     *     @type string $log_type    Loại log (mặc định 'note')
     *     @type string $log_content Nội dung (bắt buộc)
     *     @type string $log_by      Người thực hiện (mặc định tên user hiện tại)
     *     @type bool   $is_auto     true nếu ghi tự động từ tracker
     *     @type array  $meta        Metadata bổ sung (JSON)
     * }
     * @return int|false ID của bản ghi mới hoặc false nếu lỗi
     */
    public static function add(array $data)
    {
        global $wpdb;

        if (empty($data['project_id']) || empty($data['log_content'])) {
            return false;
        }

        $user    = wp_get_current_user();
        $logBy   = !empty($data['log_by'])
            ? sanitize_text_field($data['log_by'])
            : ($user->exists() ? $user->display_name : 'Auto Tracker');

        $meta = !empty($data['meta']) && is_array($data['meta'])
            ? wp_json_encode($data['meta'])
            : null;

        $inserted = $wpdb->insert(
            ProjectLogTable::getTableName(),
            [
                'project_id'  => absint($data['project_id']),
                'log_date'    => !empty($data['log_date'])
                    ? sanitize_text_field($data['log_date'])
                    : current_time('Y-m-d'),
                'log_type'    => sanitize_key($data['log_type'] ?? 'note'),
                'log_content' => sanitize_textarea_field($data['log_content']),
                'log_by'      => $logBy,
                'is_auto'     => !empty($data['is_auto']) ? 1 : 0,
                'meta'        => $meta,
                'created_at'  => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Lấy tất cả nhật ký của một dự án, sắp xếp mới nhất trước
     *
     * @param int $projectId
     * @param int $limit     0 = lấy tất cả
     * @param int $offset
     * @return array
     */
    public static function getByProject(int $projectId, int $limit = 0, int $offset = 0): array
    {
        global $wpdb;

        $table = ProjectLogTable::getTableName();
        $limit = absint($limit);
        $sql   = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT * FROM {$table} WHERE project_id = %d ORDER BY log_date DESC, created_at DESC",
            $projectId
        );

        if ($limit > 0) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $sql .= $wpdb->prepare(' LIMIT %d OFFSET %d', $limit, absint($offset));
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    /**
     * Lấy N nhật ký gần nhất (dùng cho Dashboard Widget)
     *
     * @param int $limit
     * @return array
     */
    public static function getRecent(int $limit = 10): array
    {
        global $wpdb;
        $table = ProjectLogTable::getTableName();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT l.*, p.post_title AS project_name
                 FROM {$table} l
                 LEFT JOIN {$wpdb->posts} p ON p.ID = l.project_id
                 ORDER BY l.log_date DESC, l.created_at DESC
                 LIMIT %d",
                absint($limit)
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Xoá một mục nhật ký theo ID
     *
     * @param int $logId
     * @param int $projectId Dùng để xác thực quyền sở hữu
     * @return bool
     */
    public static function delete(int $logId, int $projectId = 0): bool
    {
        global $wpdb;
        $where = ['id' => absint($logId)];
        if ($projectId > 0) {
            $where['project_id'] = absint($projectId);
        }

        $result = $wpdb->delete(ProjectLogTable::getTableName(), $where, ['%d', '%d']);
        return $result !== false && $result > 0;
    }

    /**
     * Đếm tổng số nhật ký của một dự án
     *
     * @param int $projectId
     * @return int
     */
    public static function countByProject(int $projectId): int
    {
        global $wpdb;
        $table = ProjectLogTable::getTableName();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT COUNT(*) FROM {$table} WHERE project_id = %d",
                $projectId
            )
        );
    }

    /**
     * Lấy nhãn thân thiện cho log_type
     */
    public static function getTypeLabel(string $type): string
    {
        $labels = [
            'note'               => '📝 Ghi chú',
            'plugin_update'      => '🔌 Cập nhật plugin',
            'plugin_activate'    => '✅ Kích hoạt plugin',
            'plugin_deactivate'  => '⛔ Tắt plugin',
            'core_update'        => '⚡ Cập nhật WordPress',
            'theme_switch'       => '🎨 Đổi theme',
            'file_changed'       => '📄 Thay đổi file',
            'deployment'         => '🚀 Deploy',
            'bug_fix'            => '🐛 Sửa lỗi',
            'security'           => '🔒 Bảo mật',
            'client_request'     => '👤 Yêu cầu khách hàng',
            'maintenance_summary' => '📊 Báo cáo bảo trì',
        ];

        return $labels[$type] ?? '📋 ' . ucfirst($type);
    }
}
