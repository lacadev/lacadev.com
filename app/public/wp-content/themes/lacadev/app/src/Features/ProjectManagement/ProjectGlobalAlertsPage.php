<?php

namespace App\Features\ProjectManagement;

use App\Models\ProjectAlert;

/**
 * Trang admin tổng hợp tất cả alerts chưa xử lý từ mọi dự án.
 */
class ProjectGlobalAlertsPage
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('wp_ajax_laca_global_alerts_load', [$this, 'ajaxLoad']);
        add_action('wp_ajax_laca_global_alerts_bulk_resolve', [$this, 'ajaxBulkResolve']);
        add_action('wp_ajax_laca_global_alerts_count', [$this, 'ajaxCount']);
    }

    public function addMenuPage(): void
    {
        $count = ProjectAlert::countAllActive();
        $badge = $count > 0
            ? ' <span class="awaiting-mod count-' . $count . '"><span class="pending-count">' . $count . '</span></span>'
            : '';

        add_submenu_page(
            'edit.php?post_type=project',
            'Tất cả Cảnh báo',
            'Tất cả Cảnh báo' . $badge,
            'edit_posts',
            'laca-global-alerts',
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die('Bạn không có quyền xem trang này.');
        }

        $nonce = wp_create_nonce('laca_global_alerts');

        // Filter từ query string
        $filterProject = absint($_GET['filter_project'] ?? 0);
        $filterLevel   = sanitize_key($_GET['filter_level'] ?? '');
        $filterType    = sanitize_key($_GET['filter_type'] ?? '');
        $page          = max(1, absint($_GET['alerts_page'] ?? 1));

        $filters = [];
        if ($filterProject) $filters['project_id']  = $filterProject;
        if ($filterLevel)   $filters['alert_level'] = $filterLevel;
        if ($filterType)    $filters['alert_type']  = $filterType;

        $result   = ProjectAlert::getAllActiveFiltered($filters, 30, $page);
        $alerts   = $result['items'];
        $total    = $result['total'];
        $maxPages = max(1, (int) ceil($total / 30));

        // Lấy danh sách projects để filter
        $projects = get_posts([
            'post_type'      => 'project',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $this->renderStyles();
        ?>
        <div class="wrap laca-global-alerts-wrap">
            <h1 class="wp-heading-inline">Tất cả Cảnh báo</h1>
            <?php if ($total > 0) : ?>
                <span class="laca-total-badge"><?php echo $total; ?> chưa xử lý</span>
            <?php endif; ?>

            <hr class="wp-header-end">

            <!-- Bộ lọc -->
            <form method="get" class="laca-filter-bar">
                <input type="hidden" name="post_type" value="project">
                <input type="hidden" name="page" value="laca-global-alerts">

                <select name="filter_project">
                    <option value="">-- Tất cả dự án --</option>
                    <?php foreach ($projects as $p) : ?>
                        <option value="<?php echo $p->ID; ?>" <?php selected($filterProject, $p->ID); ?>>
                            <?php echo esc_html($p->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="filter_level">
                    <option value="">-- Mọi mức độ --</option>
                    <option value="critical" <?php selected($filterLevel, 'critical'); ?>>Critical</option>
                    <option value="warning"  <?php selected($filterLevel, 'warning'); ?>>Warning</option>
                    <option value="info"     <?php selected($filterLevel, 'info'); ?>>Info</option>
                </select>

                <select name="filter_type">
                    <option value="">-- Mọi loại --</option>
                    <option value="plugin_update"  <?php selected($filterType, 'plugin_update'); ?>>Plugin update</option>
                    <option value="security"       <?php selected($filterType, 'security'); ?>>Bảo mật</option>
                    <option value="ssl_expiry"     <?php selected($filterType, 'ssl_expiry'); ?>>SSL</option>
                    <option value="domain_expiry"  <?php selected($filterType, 'domain_expiry'); ?>>Domain</option>
                    <option value="hosting_expiry" <?php selected($filterType, 'hosting_expiry'); ?>>Hosting</option>
                    <option value="bug"            <?php selected($filterType, 'bug'); ?>>Lỗi</option>
                    <option value="other"          <?php selected($filterType, 'other'); ?>>Khác</option>
                </select>

                <?php submit_button('Lọc', 'secondary', 'submit', false); ?>

                <?php if ($filterProject || $filterLevel || $filterType) : ?>
                    <a href="?post_type=project&page=laca-global-alerts" class="button">Xoá bộ lọc</a>
                <?php endif; ?>
            </form>

            <?php if (empty($alerts)) : ?>
                <div class="laca-empty-state">
                    <p>Không có cảnh báo nào<?php echo ($filterProject || $filterLevel || $filterType) ? ' phù hợp bộ lọc' : ''; ?>.</p>
                </div>
            <?php else : ?>

                <!-- Bulk actions -->
                <form id="laca-bulk-form">
                    <div class="laca-bulk-bar">
                        <label>
                            <input type="checkbox" id="laca-check-all"> Chọn tất cả
                        </label>
                        <button type="button" class="button" id="laca-bulk-resolve"
                            data-nonce="<?php echo esc_attr($nonce); ?>">
                            Đánh dấu đã xử lý
                        </button>
                        <span id="laca-bulk-msg"></span>
                    </div>

                    <table class="wp-list-table widefat fixed striped laca-alerts-table">
                        <thead>
                            <tr>
                                <td class="laca-col-check"><input type="checkbox" id="laca-check-all-head"></td>
                                <th>Dự án</th>
                                <th>Nội dung</th>
                                <th class="laca-col-type">Loại</th>
                                <th class="laca-col-level">Mức độ</th>
                                <th class="laca-col-time">Thời gian</th>
                                <th class="laca-col-action">Hành động</th>
                            </tr>
                        </thead>
                        <tbody id="laca-alerts-tbody">
                            <?php foreach ($alerts as $alert) : ?>
                                <?php $this->renderRow($alert, $nonce); ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>

                <!-- Phân trang -->
                <?php if ($maxPages > 1) : ?>
                    <div class="laca-pagination">
                        <?php for ($i = 1; $i <= $maxPages; $i++) : ?>
                            <?php
                            $url = add_query_arg([
                                'post_type'      => 'project',
                                'page'           => 'laca-global-alerts',
                                'alerts_page'    => $i,
                                'filter_project' => $filterProject ?: '',
                                'filter_level'   => $filterLevel,
                                'filter_type'    => $filterType,
                            ], admin_url('edit.php'));
                            ?>
                            <a href="<?php echo esc_url($url); ?>"
                               class="button <?php echo $i === $page ? 'button-primary' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

        <script>
        (function() {
            // Chọn tất cả
            ['laca-check-all', 'laca-check-all-head'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('change', function() {
                    document.querySelectorAll('.laca-alert-check').forEach(function(cb) {
                        cb.checked = el.checked;
                    });
                });
            });

            // Bulk resolve
            var btn = document.getElementById('laca-bulk-resolve');
            if (btn) btn.addEventListener('click', function() {
                var checked = Array.from(document.querySelectorAll('.laca-alert-check:checked'))
                    .map(function(cb) { return cb.value; });

                if (!checked.length) {
                    document.getElementById('laca-bulk-msg').textContent = 'Chưa chọn alert nào.';
                    return;
                }

                var fd = new FormData();
                fd.append('action', 'laca_global_alerts_bulk_resolve');
                fd.append('nonce', btn.dataset.nonce);
                checked.forEach(function(id) { fd.append('alert_ids[]', id); });

                fetch(ajaxurl, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            checked.forEach(function(id) {
                                var row = document.querySelector('[data-alert-id="' + id + '"]');
                                if (row) row.remove();
                            });
                            document.getElementById('laca-bulk-msg').textContent = res.data.message;
                        }
                    });
            });

            // Auto-refresh đếm badge mỗi 60s
            setInterval(function() {
                var fd = new FormData();
                fd.append('action', 'laca_global_alerts_count');
                fd.append('nonce', '<?php echo esc_js($nonce); ?>');
                fetch(ajaxurl, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            var badge = document.querySelector('.laca-total-badge');
                            if (badge) badge.textContent = res.data.count + ' chưa xử lý';
                        }
                    });
            }, 60000);
        })();
        </script>
        <?php
    }

    private function renderRow(array $alert, string $nonce): void
    {
        $levelClass = ProjectAlert::getLevelClass($alert['alert_level']);
        $typeLabel  = ProjectAlert::getTypeLabel($alert['alert_type']);
        $timeAgo    = human_time_diff(strtotime($alert['created_at']), current_time('timestamp')) . ' trước';
        $projectUrl = get_edit_post_link($alert['project_id']);
        ?>
        <tr data-alert-id="<?php echo absint($alert['id']); ?>">
            <td><input type="checkbox" class="laca-alert-check" value="<?php echo absint($alert['id']); ?>"></td>
            <td>
                <a href="<?php echo esc_url((string) $projectUrl); ?>">
                    <?php echo esc_html($alert['project_name'] ?? 'Dự án #' . $alert['project_id']); ?>
                </a>
            </td>
            <td class="laca-alert-msg"><?php echo nl2br(esc_html($alert['alert_msg'])); ?></td>
            <td><span class="laca-type-badge"><?php echo esc_html($typeLabel); ?></span></td>
            <td><span class="laca-level-badge <?php echo esc_attr($levelClass); ?>"><?php echo esc_html(ucfirst($alert['alert_level'])); ?></span></td>
            <td><time title="<?php echo esc_attr($alert['created_at']); ?>"><?php echo esc_html($timeAgo); ?></time></td>
            <td>
                <button type="button" class="button button-small laca-resolve-btn"
                    data-id="<?php echo absint($alert['id']); ?>"
                    data-nonce="<?php echo esc_attr($nonce); ?>">
                    Xử lý
                </button>
            </td>
        </tr>
        <?php
    }

    private function renderStyles(): void
    {
        echo '<style>
        .laca-global-alerts-wrap { max-width: 1200px; }
        .laca-total-badge { margin-left: 8px; background: #f29b9b; color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 12px; vertical-align: middle; }
        .laca-filter-bar { display: flex; gap: 8px; align-items: center; margin: 16px 0; flex-wrap: wrap; }
        .laca-filter-bar select { min-width: 160px; }
        .laca-empty-state { padding: 32px; text-align: center; color: #8b95a5; background: #fafbfc; border: 1px solid #e8ecf0; border-radius: 6px; }
        .laca-bulk-bar { display: flex; gap: 12px; align-items: center; padding: 8px 0; margin-bottom: 8px; }
        .laca-alerts-table .laca-col-check { width: 32px; }
        .laca-alerts-table .laca-col-type  { width: 140px; }
        .laca-alerts-table .laca-col-level { width: 90px; }
        .laca-alerts-table .laca-col-time  { width: 110px; color: #8b95a5; }
        .laca-alerts-table .laca-col-action { width: 80px; }
        .laca-alert-msg { white-space: pre-wrap; font-size: 13px; }
        .laca-type-badge { display: inline-block; background: #eef3fe; color: #5a6577; padding: 2px 8px; border-radius: 4px; font-size: 12px; }
        .laca-level-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
        .laca-alert--critical { background: #fef0f0; color: #c0392b; }
        .laca-alert--warning  { background: #fef7ea; color: #b7770d; }
        .laca-alert--info     { background: #eef3fe; color: #2c5282; }
        .laca-pagination { margin-top: 16px; display: flex; gap: 4px; }
        </style>';

        // Resolve đơn lẻ
        echo '<script>
        document.addEventListener("click", function(e) {
            if (!e.target.classList.contains("laca-resolve-btn")) return;
            var btn = e.target;
            var id  = btn.dataset.id;
            var fd  = new FormData();
            fd.append("action", "laca_global_alerts_bulk_resolve");
            fd.append("nonce", btn.dataset.nonce);
            fd.append("alert_ids[]", id);
            fetch(ajaxurl, { method: "POST", body: fd })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        var row = document.querySelector("[data-alert-id=\'" + id + "\']");
                        if (row) row.style.opacity = "0.4";
                    }
                });
        });
        </script>';
    }

    // ── AJAX handlers ────────────────────────────────────────────────────────

    public function ajaxBulkResolve(): void
    {
        check_ajax_referer('laca_global_alerts', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Không đủ quyền.']);
        }

        $ids     = array_map('absint', (array) ($_POST['alert_ids'] ?? []));
        $updated = ProjectAlert::bulkResolve($ids);

        wp_send_json_success(['message' => "Đã xử lý {$updated} cảnh báo."]);
    }

    public function ajaxCount(): void
    {
        check_ajax_referer('laca_global_alerts', 'nonce');
        wp_send_json_success(['count' => ProjectAlert::countAllActive()]);
    }

    public function ajaxLoad(): void
    {
        check_ajax_referer('laca_global_alerts', 'nonce');

        $filters = [
            'project_id'  => absint($_POST['filter_project'] ?? 0) ?: null,
            'alert_level' => sanitize_key($_POST['filter_level'] ?? ''),
            'alert_type'  => sanitize_key($_POST['filter_type'] ?? ''),
        ];
        $filters = array_filter($filters);

        $page   = max(1, absint($_POST['alerts_page'] ?? 1));
        $result = ProjectAlert::getAllActiveFiltered($filters, 30, $page);

        wp_send_json_success($result);
    }
}
