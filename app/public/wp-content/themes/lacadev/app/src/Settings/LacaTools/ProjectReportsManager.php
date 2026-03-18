<?php

namespace App\Settings\LacaTools;

/**
 * ProjectReportsManager
 *
 * Cung cấp dữ liệu thống kê project cho chart.js widget:
 *  - Doughnut: số project theo trạng thái
 *  - Bar: số project tạo theo tháng (12 tháng gần nhất)
 */
class ProjectReportsManager
{
    /** CPT slug của project — điều chỉnh nếu khác */
    private const POST_TYPE = 'project';

    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'localizeChartData']);
    }

    /**
     * Inject dữ liệu chart vào JS.
     * Chỉ enqueue khi có canvas element (widget tồn tại).
     */
    public function localizeChartData(): void
    {
        if (! current_user_can('edit_posts')) {
            return;
        }

        $primary = carbon_get_theme_option('primary_color_ad') ?: '#2ea2cc';

        wp_localize_script('theme-admin-js-bundle', 'lacaProjectCharts', [
            'primary'  => sanitize_hex_color($primary),
            'byStatus' => $this->getByStatus(),
            'byMonth'  => $this->getByMonth(),
        ]);
    }

    /**
     * Đếm project theo post_status / meta trạng thái.
     */
    private function getByStatus(): array
    {
        global $wpdb;

        $pt = self::POST_TYPE;

        // Statuses WordPress chuẩn + custom statuses
        $raw = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_status AS `key`, COUNT(*) AS `count`
                 FROM {$wpdb->posts}
                 WHERE post_type = %s
                   AND post_status NOT IN ('auto-draft','trash','inherit')
                 GROUP BY post_status
                 ORDER BY `count` DESC",
                $pt
            )
        );

        // Label map (thêm custom status nếu cần)
        $labels = [
            'publish'     => __('Đã Publish', 'lacadev'),
            'draft'       => __('Bản Nháp',   'lacadev'),
            'private'     => __('Private',    'lacadev'),
            'pending'     => __('Chờ Duyệt',  'lacadev'),
            'in_progress' => __('Đang Làm',   'lacadev'),
            'done'        => __('Hoàn Thành', 'lacadev'),
            'cancelled'   => __('Đã Hủy',     'lacadev'),
        ];

        $result = [];
        foreach ($raw as $row) {
            $result[] = [
                'key'   => $row->key,
                'label' => $labels[$row->key] ?? ucfirst($row->key),
                'count' => (int) $row->count,
            ];
        }

        // Fallback khi CPT chưa có dữ liệu
        if (empty($result)) {
            $result = [
                ['key' => 'publish', 'label' => 'Publish', 'count' => 0],
                ['key' => 'draft',   'label' => 'Draft',   'count' => 0],
            ];
        }

        return $result;
    }

    /**
     * Đếm project tạo trong 12 tháng gần nhất.
     */
    private function getByMonth(): array
    {
        global $wpdb;

        $pt = self::POST_TYPE;

        $raw = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE_FORMAT(post_date, '%%Y-%%m') AS `ym`,
                        COUNT(*) AS `count`
                 FROM {$wpdb->posts}
                 WHERE post_type = %s
                   AND post_status NOT IN ('auto-draft','trash','inherit')
                   AND post_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                 GROUP BY `ym`
                 ORDER BY `ym` ASC",
                $pt
            )
        );

        // Xây dựng 12 tháng đầy đủ (điền 0 nếu không có)
        $map = [];
        foreach ($raw as $row) {
            $map[$row->ym] = (int) $row->count;
        }

        $result   = [];
        $now      = new \DateTime();
        $iterator = (clone $now)->modify('-11 months');

        for ($i = 0; $i < 12; $i++) {
            $ym    = $iterator->format('Y-m');
            $label = $iterator->format('n/Y');   // VD: "1/2025"

            // Short month label for chart
            $monthLabels = ['1' => 'T1','2' => 'T2','3' => 'T3','4' => 'T4',
                            '5' => 'T5','6' => 'T6','7' => 'T7','8' => 'T8',
                            '9' => 'T9','10'=>'T10','11'=>'T11','12'=>'T12'];

            $result[] = [
                'month' => ($monthLabels[$iterator->format('n')] ?? $label) . '/' . $iterator->format('y'),
                'count' => $map[$ym] ?? 0,
            ];
            $iterator->modify('+1 month');
        }

        return $result;
    }
}
