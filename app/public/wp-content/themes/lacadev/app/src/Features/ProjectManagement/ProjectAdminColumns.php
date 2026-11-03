<?php

namespace App\Features\ProjectManagement;

use App\Models\ProjectAlert;

/**
 * ProjectAdminColumns
 *
 * Quản lý toàn bộ giao diện cột trong danh sách Project của WordPress Admin:
 * - Định nghĩa cột hiển thị
 * - Render nội dung từng cột
 * - Cột có thể sắp xếp
 * - Dropdown filter theo trạng thái
 * - Xử lý WP_Query filter
 */
class ProjectAdminColumns
{
    /** @var array<string, string> Label + màu badge cho từng trạng thái */
    private const STATUS_LABELS = [
        'pending'     => ['Chờ làm',  '#f0ad4e'],
        'in_progress' => ['Đang làm', '#5bc0de'],
        'done'        => ['Đã xong',  '#5cb85c'],
        'maintenance' => ['Bảo trì',  '#d9534f'],
        'paused'      => ['Tạm dừng', '#999'],
    ];

    /** @var array<string, string> Cấu hình các trạng thái cho dropdown filter */
    private const STATUS_OPTIONS = [
        ''            => 'Tất cả trạng thái',
        'pending'     => 'Chờ làm',
        'in_progress' => 'Đang làm',
        'done'        => 'Đã xong',
        'maintenance' => 'Bảo trì',
        'paused'      => 'Tạm dừng',
    ];

    // -----------------------------------------------------------------------

    public function register(): void
    {
        add_filter('manage_project_posts_columns',        [$this, 'addColumns']);
        add_action('manage_project_posts_custom_column',  [$this, 'renderColumn'], 10, 2);
        add_filter('manage_edit-project_sortable_columns', [$this, 'sortableColumns']);
        add_action('restrict_manage_posts',               [$this, 'addStatusFilter']);
        add_filter('parse_query',                         [$this, 'filterByStatus']);
    }

    // -----------------------------------------------------------------------

    public function addColumns(array $columns): array
    {
        return [
            'cb'             => $columns['cb'] ?? '',
            'featured_image' => __('Ảnh', 'laca'),
            'title'          => __('Tên dự án', 'laca'),
            'laca_client'    => __('Khách hàng', 'laca'),
            'laca_status'    => __('Trạng thái', 'laca'),
            'laca_domain'    => __('Domain', 'laca'),
            'laca_expiry'    => __('Hết hạn', 'laca'),
            'laca_alerts'    => __('Cảnh báo', 'laca'),
            'date'           => $columns['date'] ?? '',
        ];
    }

    public function renderColumn(string $column, int $postId): void
    {
        match ($column) {
            'laca_client'  => $this->renderClient($postId),
            'laca_status'  => $this->renderStatus($postId),
            'laca_domain'  => $this->renderDomain($postId),
            'laca_expiry'  => $this->renderExpiry($postId),
            'laca_alerts'  => $this->renderAlerts($postId),
            default        => null,
        };
    }

    public function sortableColumns(array $columns): array
    {
        $columns['laca_expiry'] = 'domain_expiry';
        return $columns;
    }

    public function addStatusFilter(): void
    {
        global $typenow;
        if ($typenow !== 'project') {
            return;
        }

        $currentStatus = sanitize_key($_GET['laca_status'] ?? '');

        echo '<select name="laca_status">';
        foreach (self::STATUS_OPTIONS as $value => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($value),
                selected($currentStatus, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    public function filterByStatus(\WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if (($query->get('post_type') !== 'project') || empty($_GET['laca_status'])) {
            return;
        }

        $status = sanitize_key($_GET['laca_status']);
        if (empty($status)) {
            return;
        }

        $meta   = $query->get('meta_query', []);
        $meta[] = [
            'key'     => '_project_status',
            'value'   => $status,
            'compare' => '=',
        ];
        $query->set('meta_query', $meta);
    }

    // -----------------------------------------------------------------------
    // Private render helpers
    // -----------------------------------------------------------------------

    private function renderClient(int $postId): void
    {
        $name  = esc_html(carbon_get_post_meta($postId, 'client_name') ?: '—');
        $phone = esc_html(carbon_get_post_meta($postId, 'client_phone') ?: '');

        echo '<strong>' . $name . '</strong>';
        if ($phone) {
            echo '<br><small style="color:#888;">' . $phone . '</small>';
        }
    }

    private function renderStatus(int $postId): void
    {
        $status = carbon_get_post_meta($postId, 'project_status') ?: 'pending';
        $info   = self::STATUS_LABELS[$status] ?? [$status, '#666'];

        echo '<span style="
            display:inline-block;padding:3px 8px;border-radius:12px;
            background:' . esc_attr($info[1]) . '22;
            color:' . esc_attr($info[1]) . ';
            font-size:12px;font-weight:600;white-space:nowrap;">'
            . esc_html($info[0])
            . '</span>';

        $legacyLastSeen = (int) get_post_meta($postId, '_laca_legacy_auth_last_seen', true);
        if ($legacyLastSeen > 0) {
            echo '<div style="margin-top:4px;"><span style="
                display:inline-block;padding:2px 6px;border-radius:10px;
                background:#fff3cd;color:#856404;border:1px solid #ffeeba;
                font-size:11px;font-weight:600;white-space:nowrap;"
                title="' . esc_attr(sprintf(__('Xác thực không ký lần cuối: %s', 'laca'), date_i18n('d/m/Y H:i', $legacyLastSeen))) . '">'
                . esc_html__('⚠️ Client chưa nâng cấp', 'laca')
                . '</span></div>';
        }
    }

    private function renderDomain(int $postId): void
    {
        $domain  = esc_html(carbon_get_post_meta($postId, 'domain_name') ?: '—');
        $liveUrl = esc_url(carbon_get_post_meta($postId, 'live_url') ?: '');

        if ($liveUrl) {
            echo '<a href="' . $liveUrl . '" target="_blank" title="Mở website">' . $domain . ' ↗</a>';
        } else {
            echo $domain;
        }
    }

    private function renderExpiry(int $postId): void
    {
        $today = new \DateTime();

        foreach ([
            '🌐 Domain'   => carbon_get_post_meta($postId, 'domain_expiry'),
            '🖥️ Hosting' => carbon_get_post_meta($postId, 'hosting_expiry'),
        ] as $label => $expiry) {
            if (!$expiry) {
                continue;
            }

            $expiryDate = new \DateTime($expiry);
            $daysLeft   = (int) $today->diff($expiryDate)->format('%r%a');
            $color      = $daysLeft < 7 ? '#d9534f' : ($daysLeft < 30 ? '#f0ad4e' : '#5cb85c');

            echo '<div style="font-size:12px;margin-bottom:2px;">'
                . esc_html($label) . ': '
                . '<span style="color:' . esc_attr($color) . ';font-weight:600;">'
                . esc_html($expiryDate->format('d/m/Y'))
                . ' <small>(' . ($daysLeft >= 0 ? '+' . $daysLeft : $daysLeft) . 'd)</small>'
                . '</span></div>';
        }
    }

    private function renderAlerts(int $postId): void
    {
        $count = ProjectAlert::countActive($postId);

        if ($count > 0) {
            echo '<span style="
                background:#d9534f;color:#fff;
                border-radius:50%;width:22px;height:22px;
                display:inline-flex;align-items:center;justify-content:center;
                font-size:12px;font-weight:700;">'
                . esc_html($count)
                . '</span>';
        } else {
            echo '<span style="color:#5cb85c;">✓</span>';
        }
    }
}
