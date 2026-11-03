<?php

namespace App\Features\ProjectManagement;

use App\Models\ProjectAlert;
use App\Models\ProjectLog;

/**
 * Laca Projects CRM hub.
 *
 * The project CPT remains the source of truth. This class adds a focused CRM
 * shell around the existing project list, alerts, finance, operations, and
 * portal workflows.
 */
class LacaProjectsHub
{
    public const MENU_SLUG = 'laca-projects';

    private const POST_TYPE = 'project';

    /**
     * @var array<string,array{label:string,icon:string,items:array<int,array{key:string,label:string,url:string}>}>
     */
    private const NAVIGATION = [
        'overview' => [
            'label' => 'Tổng quan',
            'icon' => 'dashicons-dashboard',
            'items' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => 'admin.php?page=laca-projects'],
                ['key' => 'notifications', 'label' => 'Notifications', 'url' => 'admin.php?page=laca-project-notifications-center'],
                ['key' => 'actions', 'label' => 'Action center', 'url' => 'admin.php?page=laca-project-actions'],
                ['key' => 'pipeline', 'label' => 'Pipeline', 'url' => 'admin.php?page=laca-project-pipeline'],
                ['key' => 'projects', 'label' => 'Tất cả dự án', 'url' => 'edit.php?post_type=project'],
                ['key' => 'new-project', 'label' => 'Thêm dự án', 'url' => 'post-new.php?post_type=project'],
                ['key' => 'health', 'label' => 'Health score', 'url' => 'admin.php?page=laca-project-health'],
            ],
        ],
        'crm' => [
            'label' => 'CRM',
            'icon' => 'dashicons-groups',
            'items' => [
                ['key' => 'clients', 'label' => 'Khách hàng', 'url' => 'admin.php?page=laca-project-clients'],
            ],
        ],
        'delivery' => [
            'label' => 'Delivery',
            'icon' => 'dashicons-update',
            'items' => [
                ['key' => 'issues', 'label' => 'Issues', 'url' => 'admin.php?page=laca-project-issues'],
                ['key' => 'updates', 'label' => 'Updates', 'url' => 'admin.php?page=laca-project-updates'],
                ['key' => 'fleet', 'label' => 'Fleet', 'url' => 'admin.php?page=laca-project-fleet'],
                ['key' => 'renewals', 'label' => 'Renewals', 'url' => 'admin.php?page=laca-project-renewals'],
                ['key' => 'alerts', 'label' => 'Cảnh báo', 'url' => 'admin.php?page=laca-global-alerts'],
                ['key' => 'operations', 'label' => 'Vận hành', 'url' => 'admin.php?page=laca-project-operations'],
            ],
        ],
        'finance' => [
            'label' => 'Tài chính',
            'icon' => 'dashicons-money-alt',
            'items' => [
                ['key' => 'finance', 'label' => 'Thanh toán', 'url' => 'admin.php?page=laca-project-finance'],
                ['key' => 'reports', 'label' => 'Reports', 'url' => 'admin.php?page=laca-project-reports'],
            ],
        ],
        'portal' => [
            'label' => 'Client Portal',
            'icon' => 'dashicons-admin-links',
            'items' => [
                ['key' => 'portal', 'label' => 'Portal links', 'url' => 'admin.php?page=laca-project-portal'],
            ],
        ],
    ];

    public function register(): void
    {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [$this, 'registerMenu'], 5);
        add_action('admin_menu', [$this, 'registerSubPages'], 20);
        add_action('admin_head', [$this, 'printStyles']);
        add_action('all_admin_notices', [$this, 'renderDock']);
        add_filter('admin_body_class', [$this, 'filterAdminBodyClass']);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('Laca Projects', 'laca'),
            __('Laca Projects', 'laca'),
            'edit_posts',
            self::MENU_SLUG,
            [$this, 'renderDashboard'],
            'dashicons-portfolio',
            4
        );
    }

    public function registerSubPages(): void
    {
        add_submenu_page(
            self::MENU_SLUG,
            __('Dashboard', 'laca'),
            __('Dashboard', 'laca'),
            'edit_posts',
            self::MENU_SLUG,
            [$this, 'renderDashboard']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Action center', 'laca'),
            __('Action center', 'laca'),
            'edit_posts',
            'laca-project-actions',
            [$this, 'renderActionCenter']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Notifications', 'laca'),
            __('Notifications', 'laca'),
            'edit_posts',
            'laca-project-notifications-center',
            [$this, 'renderNotifications']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Pipeline', 'laca'),
            __('Pipeline', 'laca'),
            'edit_posts',
            'laca-project-pipeline',
            [$this, 'renderPipeline']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Khách hàng', 'laca'),
            __('Khách hàng', 'laca'),
            'edit_posts',
            'laca-project-clients',
            [$this, 'renderClients']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Issues', 'laca'),
            __('Issues', 'laca'),
            'edit_posts',
            'laca-project-issues',
            [$this, 'renderIssues']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Updates', 'laca'),
            __('Updates', 'laca'),
            'edit_posts',
            'laca-project-updates',
            [$this, 'renderUpdates']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Fleet', 'laca'),
            __('Fleet', 'laca'),
            'edit_posts',
            'laca-project-fleet',
            [$this, 'renderFleet']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Renewals', 'laca'),
            __('Renewals', 'laca'),
            'edit_posts',
            'laca-project-renewals',
            [$this, 'renderRenewals']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Vận hành', 'laca'),
            __('Vận hành', 'laca'),
            'edit_posts',
            'laca-project-operations',
            [$this, 'renderOperations']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Health score', 'laca'),
            __('Health score', 'laca'),
            'edit_posts',
            'laca-project-health',
            [$this, 'renderHealth']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Thanh toán', 'laca'),
            __('Thanh toán', 'laca'),
            'edit_posts',
            'laca-project-finance',
            [$this, 'renderFinance']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Reports', 'laca'),
            __('Reports', 'laca'),
            'edit_posts',
            'laca-project-reports',
            [$this, 'renderReports']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Portal links', 'laca'),
            __('Portal links', 'laca'),
            'edit_posts',
            'laca-project-portal',
            [$this, 'renderPortal']
        );
    }

    public function renderDashboard(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Bạn không có quyền xem trang này.', 'laca'));
        }

        $projects = $this->getProjects();
        $stats = $this->getStats($projects);
        $finance = $this->getFinanceReport($projects);
        $alertReport = $this->getAlertReport();
        $trackerReport = $this->getTrackerReport($projects);
        $alerts = class_exists(ProjectAlert::class) ? ProjectAlert::getAllActiveCritical() : [];
        $logs = class_exists(ProjectLog::class) ? ProjectLog::getRecent(12) : [];
        $clientUpdates = $this->getClientUpdates($logs);
        $riskProjects = $this->getRiskProjects($projects, 8);
        $projectRows = $this->getProjectSummaryRows($projects, 10);
        $chartData = $this->getDashboardChartData($projects, $finance, $alertReport);
        $trackerSilent = $trackerReport['stale'] + $trackerReport['missing'];

        $channelStatus = class_exists(\App\Settings\LacaTools\ProjectNotificationHandler::class)
            ? (new \App\Settings\LacaTools\ProjectNotificationHandler())->getChannelStatusReport()
            : [];
        $channelFailedCount = 0;
        foreach ($channelStatus as $channelResult) {
            if (empty($channelResult['ok'])) {
                $channelFailedCount++;
            }
        }

        ?>
        <div class="wrap laca-projects-wrap">
            <?php $this->renderHeader('Laca Projects', 'Báo cáo nhanh về doanh thu, công nợ, lỗi và cập nhật từ website khách hàng.'); ?>
            <?php $this->renderIntroCallout(__('Dashboard', 'laca'), __('Nhìn nhanh sức khỏe toàn bộ dự án/website đang quản lý: doanh thu, công nợ, cảnh báo, tracker và log cập nhật mới nhất. Nhấn vào một dòng để xem chi tiết dự án.', 'laca')); ?>
            <?php $this->renderChartDataScript($chartData); ?>

            <div class="laca-projects-overview">
                <?php $this->renderMetric('Doanh thu dự kiến', $this->formatMoney($finance['total_build']), $stats['total'] . ' dự án', 'finance'); ?>
                <?php $this->renderMetric('Đã thu', $this->formatMoney($finance['total_paid']), $finance['collection_rate'] . '% đã thu', 'success'); ?>
                <?php $this->renderMetric('Còn phải thu', $this->formatMoney($finance['outstanding']), $finance['unpaid_count'] . ' dự án còn công nợ', 'warning'); ?>
                <?php $this->renderMetric('Bảo trì / năm', $this->formatMoney($finance['maintenance_yearly']), $stats['maintenance'] . ' dự án maintenance', 'info'); ?>
                <?php $this->renderMetric('Cảnh báo active', (string) $alertReport['total'], $alertReport['critical'] . ' critical', $alertReport['critical'] > 0 ? 'danger' : 'neutral'); ?>
                <?php $this->renderMetric('Tracker online', $trackerReport['online'] . '/' . $trackerReport['total'], $trackerSilent . ' site im lặng', $trackerSilent > 0 ? 'warning' : 'success'); ?>
                <?php $this->renderMetric('Kênh thông báo', $channelFailedCount > 0 ? $channelFailedCount . ' lỗi' : 'Ổn định', $channelFailedCount > 0 ? 'Kiểm tra LacaDev PM & Bots' : 'Email/Zalo/Telegram/Slack', $channelFailedCount > 0 ? 'danger' : 'success'); ?>
            </div>

            <div class="laca-projects-report-grid">
                <section class="laca-projects-panel laca-projects-panel--chart">
                    <div class="laca-projects-panel__head">
                        <h2>Tài chính</h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-project-finance')); ?>">Xem thanh toán</a>
                    </div>
                    <div class="laca-projects-chart">
                        <canvas id="laca-projects-finance-chart" height="180"></canvas>
                    </div>
                    <div class="laca-projects-progress laca-progress" aria-label="<?php echo esc_attr__('Tỷ lệ đã thu', 'laca'); ?>" style="<?php echo esc_attr('--laca-progress:' . min(100, $finance['collection_rate']) . '%;'); ?>">
                        <span class="laca-progress__bar"></span>
                    </div>
                    <dl class="laca-projects-kv">
                        <div><dt>Đã thu</dt><dd><?php echo esc_html($this->formatMoney($finance['total_paid'])); ?></dd></div>
                        <div><dt>Công nợ</dt><dd><?php echo esc_html($this->formatMoney($finance['outstanding'])); ?></dd></div>
                        <div><dt>Quá hạn/chưa thu</dt><dd><?php echo esc_html((string) $finance['unpaid_count']); ?> dự án</dd></div>
                    </dl>
                </section>

                <section class="laca-projects-panel laca-projects-panel--chart">
                    <div class="laca-projects-panel__head">
                        <h2>Lỗi & cảnh báo</h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-global-alerts')); ?>">Xem tất cả</a>
                    </div>
                    <div class="laca-projects-chart">
                        <canvas id="laca-projects-alert-chart" height="180"></canvas>
                    </div>
                    <dl class="laca-projects-kv">
                        <div><dt>Critical</dt><dd><?php echo esc_html((string) $alertReport['critical']); ?></dd></div>
                        <div><dt>Warning</dt><dd><?php echo esc_html((string) $alertReport['warning']); ?></dd></div>
                        <div><dt>Lỗi website/bảo mật</dt><dd><?php echo esc_html((string) $alertReport['website_issues']); ?></dd></div>
                    </dl>
                </section>

                <section class="laca-projects-panel laca-projects-panel--chart">
                    <div class="laca-projects-panel__head">
                        <h2>Trạng thái dự án</h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-project-pipeline')); ?>">Mở Pipeline</a>
                    </div>
                    <div class="laca-projects-chart">
                        <canvas id="laca-projects-status-chart" height="180"></canvas>
                    </div>
                    <?php $this->renderKeyValueList($this->getStatusBreakdown($projects)); ?>
                </section>
            </div>

            <div class="laca-projects-grid">
                <section class="laca-projects-panel">
                    <div class="laca-projects-panel__head">
                        <h2>Updates từ website</h2>
                        <span class="laca-projects-muted"><?php echo esc_html((string) $clientUpdates['auto_count']); ?> report tự động</span>
                    </div>
                    <?php if ($clientUpdates['items'] === []): ?>
                        <p class="laca-projects-empty">Chưa có cập nhật tự động gần đây từ website khách hàng.</p>
                    <?php else: ?>
                        <div class="laca-projects-activity">
                            <?php foreach (array_slice($clientUpdates['items'], 0, 5) as $log): ?>
                                <a href="<?php echo esc_url(get_edit_post_link((int) $log['project_id'], '')); ?>"
                                   data-laca-project-detail
                                   data-title="<?php echo esc_attr($log['project_name'] ?? 'Dự án #' . $log['project_id']); ?>"
                                   data-meta="<?php echo esc_attr($this->logTypeLabel((string) $log['log_type']) . ' - ' . $this->formatLogDate($log)); ?>"
                                   data-message="<?php echo esc_attr((string) $log['log_content']); ?>"
                                   data-url="<?php echo esc_url(get_edit_post_link((int) $log['project_id'], '')); ?>">
                                    <strong><?php echo esc_html($log['project_name'] ?? 'Dự án #' . $log['project_id']); ?></strong>
                                    <span><?php echo esc_html($this->logTypeLabel((string) $log['log_type'])); ?> - <?php echo esc_html($this->formatLogDate($log)); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="laca-projects-panel">
                    <div class="laca-projects-panel__head">
                        <h2>Cần xử lý</h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-project-actions')); ?>">Mở Action center</a>
                    </div>
                    <?php if ($riskProjects === []): ?>
                        <p class="laca-projects-empty">Chưa có dự án cần xử lý ngay.</p>
                    <?php else: ?>
                        <table class="widefat striped laca-projects-table">
                            <thead><tr><th>Dự án</th><th>Vấn đề</th><th>Mức độ</th></tr></thead>
                            <tbody>
                                <?php foreach ($riskProjects as $row): ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url(get_edit_post_link($row['id'], '')); ?>" data-laca-project-detail data-title="<?php echo esc_attr($row['title']); ?>" data-meta="Risk score <?php echo esc_attr((string) $row['score']); ?>" data-message="<?php echo esc_attr($row['reason']); ?>" data-url="<?php echo esc_url(get_edit_post_link($row['id'], '')); ?>"><?php echo esc_html($row['title']); ?></a></td>
                                        <td><?php echo esc_html($row['reason']); ?></td>
                                        <td><span class="laca-projects-badge"><?php echo esc_html((string) $row['score']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>

                <section class="laca-projects-panel">
                    <div class="laca-projects-panel__head">
                        <h2>Cảnh báo mới</h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-global-alerts')); ?>">Xem tất cả</a>
                    </div>
                    <?php if ($alerts === []): ?>
                        <p class="laca-projects-empty">Không có cảnh báo active.</p>
                    <?php else: ?>
                        <div class="laca-projects-list">
                            <?php foreach (array_slice($alerts, 0, 6) as $alert): ?>
                                <a class="laca-projects-list__item" href="<?php echo esc_url(get_edit_post_link((int) $alert['project_id'], '')); ?>"
                                   data-laca-project-detail
                                   data-title="<?php echo esc_attr($alert['project_name'] ?? 'Dự án #' . $alert['project_id']); ?>"
                                   data-meta="<?php echo esc_attr(ProjectAlert::getTypeLabel((string) $alert['alert_type']) . ' - ' . (string) $alert['alert_level']); ?>"
                                   data-message="<?php echo esc_attr((string) $alert['alert_msg']); ?>"
                                   data-url="<?php echo esc_url(get_edit_post_link((int) $alert['project_id'], '')); ?>">
                                    <strong><?php echo esc_html($alert['project_name'] ?? 'Dự án #' . $alert['project_id']); ?></strong>
                                    <span><?php echo esc_html(ProjectAlert::getTypeLabel((string) $alert['alert_type'])); ?> - <?php echo esc_html($alert['alert_msg']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="laca-projects-panel laca-projects-panel--wide">
                    <div class="laca-projects-panel__head">
                        <h2>Cập nhật từ website khách hàng</h2>
                        <span class="laca-projects-muted">Log mới nhất từ tracker/client portal</span>
                    </div>
                    <?php $this->renderLogsTable($logs); ?>
                </section>

                <section class="laca-projects-panel laca-projects-panel--wide">
                    <div class="laca-projects-panel__head">
                        <h2>Project summary</h2>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=project')); ?>">Mở danh sách dự án</a>
                    </div>
                    <?php if ($projectRows === []): ?>
                        <p class="laca-projects-empty">Chưa có project.</p>
                    <?php else: ?>
                        <table class="widefat striped laca-projects-table">
                            <thead><tr><th>Dự án</th><th>Khách hàng</th><th>Trạng thái</th><th>Tài chính</th><th>Alerts</th><th>Hạn gần nhất</th></tr></thead>
                            <tbody>
                                <?php foreach ($projectRows as $row): ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url(get_edit_post_link($row['id'], '')); ?>"><?php echo esc_html($row['title']); ?></a></td>
                                        <td><?php echo esc_html($row['client']); ?></td>
                                        <td><?php echo esc_html($row['status']); ?></td>
                                        <td><?php echo esc_html($row['finance']); ?></td>
                                        <td><?php echo esc_html((string) $row['alerts']); ?></td>
                                        <td><?php echo esc_html($row['expiry']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </section>
            </div>
        </div>
        <?php
    }

    public function renderActionCenter(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Bạn không có quyền xem trang này.', 'laca'));
        }

        $projects = $this->getProjects();
        $groups = $this->getActionCenterGroups($projects);
        $total = array_sum(array_map('count', $groups));

        ?>
        <div class="wrap laca-projects-wrap">
            <?php $this->renderHeader('Action center', 'Danh sách việc cần xử lý được gom theo mức ưu tiên từ cảnh báo, công nợ, gia hạn và cập nhật dự án.'); ?>
            <?php $this->renderIntroCallout(__('Action center', 'laca'), __('Việc cần xử lý ngay, tự động gom theo mức ưu tiên: issue nghiêm trọng, công nợ, gia hạn sắp đến hạn và dự án lâu chưa có cập nhật.', 'laca')); ?>
            <div class="laca-projects-overview laca-projects-overview--compact">
                <?php $this->renderMetric('Tổng việc cần xử lý', (string) $total, 'Tự tổng hợp từ CRM'); ?>
                <?php $this->renderMetric('Cần xử lý ngay', (string) count($groups['urgent']), 'Critical/warning issues'); ?>
                <?php $this->renderMetric('Công nợ', (string) count($groups['finance']), 'Dự án chưa thu đủ'); ?>
                <?php $this->renderMetric('Gia hạn 30 ngày', (string) count($groups['renewals']), 'Domain/hosting'); ?>
            </div>

            <div class="laca-projects-grid">
                <section class="laca-projects-panel">
                    <div class="laca-projects-panel__head">
                        <h2>Cần xử lý ngay</h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-project-issues')); ?>">Mở Issues</a>
                    </div>
                    <?php $this->renderActionItems($groups['urgent']); ?>
                </section>

                <section class="laca-projects-panel">
                    <div class="laca-projects-panel__head">
                        <h2>Công nợ</h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-project-finance')); ?>">Mở thanh toán</a>
                    </div>
                    <?php $this->renderActionItems($groups['finance']); ?>
                </section>

                <section class="laca-projects-panel">
                    <div class="laca-projects-panel__head">
                        <h2>Gia hạn</h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-project-renewals')); ?>">Mở Renewals</a>
                    </div>
                    <?php $this->renderActionItems($groups['renewals']); ?>
                </section>

                <section class="laca-projects-panel">
                    <div class="laca-projects-panel__head">
                        <h2>Ít cập nhật</h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-project-health')); ?>">Mở Health</a>
                    </div>
                    <?php $this->renderActionItems($groups['stale']); ?>
                </section>
            </div>
        </div>
        <?php
    }

    public function renderNotifications(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Bạn không có quyền xem trang này.', 'laca'));
        }

        $projects = $this->getProjects();
        $groups = $this->getActionCenterGroups($projects);
        $logs = $this->getProjectLogs(200);
        $clientRequests = $this->getClientRequestLogs($logs);
        $urgentCount = count($groups['urgent']) + count($clientRequests);
        $total = $urgentCount + count($groups['finance']) + count($groups['renewals']) + count($groups['stale']);

        ?>
        <div class="wrap laca-projects-wrap">
            <?php $this->renderHeader('Notifications', 'Trung tâm thông báo gom yêu cầu khách hàng, cảnh báo, công nợ, gia hạn và dự án cần chăm sóc.'); ?>
            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0 16px"><p style="margin:0 0 6px;font-weight:600;color:#0369a1">🔧 Notifications</p><p style="margin:0;font-size:13px;color:#374151">Tổng hợp yêu cầu gửi từ khách hàng qua Client Portal cùng issue, công nợ và gia hạn đang cần chăm sóc — nơi tổng hợp việc cần phản hồi khách, không phải log gửi Email/Zalo/Telegram/Slack.</p></div>
            <div class="laca-projects-overview laca-projects-overview--compact">
                <?php $this->renderMetric('Tổng thông báo', (string) $total, 'Từ CRM và client portal'); ?>
                <?php $this->renderMetric('Yêu cầu khách', (string) count($clientRequests), 'Client Portal'); ?>
                <?php $this->renderMetric('Cần xử lý', (string) count($groups['urgent']), 'Issues critical/warning'); ?>
                <?php $this->renderMetric('Công nợ & gia hạn', (string) (count($groups['finance']) + count($groups['renewals'])), 'Finance + renewals'); ?>
            </div>

            <div class="laca-projects-grid">
                <section class="laca-projects-panel">
                    <div class="laca-projects-panel__head">
                        <h2>Yêu cầu từ khách hàng</h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-project-updates')); ?>">Mở Updates</a>
                    </div>
                    <?php $this->renderClientRequestItems($clientRequests); ?>
                </section>

                <section class="laca-projects-panel">
                    <div class="laca-projects-panel__head">
                        <h2>Issues cần xử lý</h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-project-issues')); ?>">Mở Issues</a>
                    </div>
                    <?php $this->renderActionItems($groups['urgent']); ?>
                </section>

                <section class="laca-projects-panel">
                    <div class="laca-projects-panel__head">
                        <h2>Công nợ</h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-project-finance')); ?>">Mở Thanh toán</a>
                    </div>
                    <?php $this->renderActionItems($groups['finance']); ?>
                </section>

                <section class="laca-projects-panel">
                    <div class="laca-projects-panel__head">
                        <h2>Gia hạn & chăm sóc</h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-project-renewals')); ?>">Mở Renewals</a>
                    </div>
                    <?php $this->renderActionItems(array_merge($groups['renewals'], $groups['stale'])); ?>
                </section>
            </div>
        </div>
        <?php
    }

    public function renderPipeline(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Bạn không có quyền xem trang này.', 'laca'));
        }

        $columns = $this->getPipelineColumns($this->getProjects());

        ?>
        <div class="wrap laca-projects-wrap">
            <?php $this->renderHeader('Pipeline', 'Board theo trạng thái để nhìn nhanh project đang ở đâu, ai phụ trách thanh toán và có rủi ro gì.'); ?>
            <?php $this->renderIntroCallout(__('Pipeline', 'laca'), __('Bảng Kanban theo trạng thái dự án, giúp nhìn nhanh dự án nào đang ở giai đoạn nào, tình hình tài chính và cảnh báo. Bấm vào thẻ để xem chi tiết.', 'laca')); ?>
            <div class="laca-projects-board">
                <?php foreach ($columns as $column): ?>
                    <section class="laca-projects-column">
                        <div class="laca-projects-column__head">
                            <h2><?php echo esc_html($column['label']); ?></h2>
                            <span><?php echo esc_html((string) count($column['items'])); ?></span>
                        </div>
                        <?php if ($column['items'] === []): ?>
                            <p class="laca-projects-empty">Chưa có project.</p>
                        <?php else: ?>
                            <div class="laca-projects-cards">
                                <?php foreach ($column['items'] as $item): ?>
                                    <a class="laca-projects-card"
                                       href="<?php echo esc_url(get_edit_post_link($item['id'], '')); ?>"
                                       data-laca-project-detail
                                       data-title="<?php echo esc_attr($item['title']); ?>"
                                       data-meta="<?php echo esc_attr($column['label'] . ' - ' . $item['client']); ?>"
                                       data-message="<?php echo esc_attr('Tài chính: ' . $item['finance'] . "\nAlerts: " . $item['alerts'] . "\nHạn gần nhất: " . $item['expiry']); ?>"
                                       data-url="<?php echo esc_url(get_edit_post_link($item['id'], '')); ?>">
                                        <strong><?php echo esc_html($item['title']); ?></strong>
                                        <span><?php echo esc_html($item['client']); ?></span>
                                        <dl>
                                            <div><dt>Tài chính</dt><dd><?php echo esc_html($item['finance']); ?></dd></div>
                                            <div><dt>Alerts</dt><dd><?php echo esc_html((string) $item['alerts']); ?></dd></div>
                                            <div><dt>Hạn gần nhất</dt><dd><?php echo esc_html($item['expiry']); ?></dd></div>
                                        </dl>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public function renderClients(): void
    {
        $projects = $this->getProjects();
        $clientRows = $this->getClientRows($projects);

        $this->renderSimpleTablePage(
            'Khách hàng',
            'CRM khách hàng tổng hợp theo tên/email, doanh thu, công nợ và danh sách website đang chăm sóc.',
            ['Khách hàng', 'Liên hệ', 'Dự án', 'Doanh thu', 'Công nợ', 'Cập nhật cuối'],
            $clientRows,
            $this->getIntroCalloutHtml(__('Khách hàng', 'laca'), __('Danh bạ khách hàng tổng hợp theo tên/email: số dự án đang chăm sóc, doanh thu và công nợ của từng khách.', 'laca'))
        );
    }

    public function renderIssues(): void
    {
        $alerts = class_exists(ProjectAlert::class)
            ? ProjectAlert::getAllActiveFiltered([], 100, 1)
            : ['items' => [], 'total' => 0];
        $report = $this->getAlertReport();

        ?>
        <div class="wrap laca-projects-wrap">
            <?php $this->renderHeader('Issues', 'Tập trung các lỗi, cảnh báo bảo mật và cảnh báo vận hành chưa xử lý.'); ?>
            <?php $this->renderIntroCallout(__('Issues', 'laca'), __('Toàn bộ lỗi, cảnh báo bảo mật và cảnh báo vận hành đang active trên các website khách hàng, sắp xếp theo mức độ nghiêm trọng.', 'laca')); ?>
            <div class="laca-projects-overview laca-projects-overview--compact">
                <?php $this->renderMetric('Active issues', (string) $report['total'], 'Tổng cảnh báo chưa resolve'); ?>
                <?php $this->renderMetric('Critical', (string) $report['critical'], 'Cần xử lý ngay'); ?>
                <?php $this->renderMetric('Warning', (string) $report['warning'], 'Cần kiểm tra'); ?>
                <?php $this->renderMetric('Website/security', (string) $report['website_issues'], 'Lỗi và bảo mật từ site khách'); ?>
            </div>
            <section class="laca-projects-panel laca-card">
                <?php if (($alerts['items'] ?? []) === []): ?>
                    <p class="laca-projects-empty"><?php echo esc_html__('Không có issue active.', 'laca'); ?></p>
                <?php else: ?>
                    <div class="laca-table-wrap">
                        <table class="widefat striped laca-projects-table laca-table">
                            <thead><tr><th><?php echo esc_html__('Dự án', 'laca'); ?></th><th><?php echo esc_html__('Loại', 'laca'); ?></th><th><?php echo esc_html__('Mức độ', 'laca'); ?></th><th><?php echo esc_html__('Nội dung', 'laca'); ?></th><th><?php echo esc_html__('Ngày', 'laca'); ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($alerts['items'] as $alert): ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url(get_edit_post_link((int) $alert['project_id'], '')); ?>"><?php echo esc_html($alert['project_name'] ?? 'Dự án #' . $alert['project_id']); ?></a></td>
                                        <td><?php echo esc_html(ProjectAlert::getTypeLabel((string) $alert['alert_type'])); ?></td>
                                        <td><span class="laca-badge laca-badge--<?php echo (string) ($alert['alert_level'] ?? '') === 'critical' ? 'danger' : 'warning'; ?>"><?php echo esc_html((string) $alert['alert_level']); ?></span></td>
                                        <td><?php echo esc_html(wp_trim_words((string) $alert['alert_msg'], 26)); ?></td>
                                        <td><?php echo esc_html($this->formatDateTime((string) $alert['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <?php
            global $wpdb;
            $logTable = \App\Databases\ProjectLogTable::getTableName();
            $clientRequests = $wpdb->get_results($wpdb->prepare(
                "SELECT l.*, p.post_title as project_name
                 FROM {$logTable} l
                 INNER JOIN {$wpdb->posts} p ON p.ID = l.project_id
                 WHERE l.log_type = %s
                 ORDER BY l.id DESC
                 LIMIT 20",
                'client_request'
            ));
            ?>
            <section class="laca-projects-panel laca-projects-panel--spaced laca-card">
                <div class="laca-projects-panel__head">
                    <h2><?php echo esc_html__('Yêu cầu hỗ trợ từ khách hàng', 'laca'); ?></h2>
                    <span class="laca-projects-meta"><?php echo esc_html(sprintf(__('%d yêu cầu gần nhất', 'laca'), count($clientRequests))); ?></span>
                </div>
                <?php if (empty($clientRequests)): ?>
                    <p class="laca-projects-empty"><?php echo esc_html__('Chưa có yêu cầu hỗ trợ nào từ site khách.', 'laca'); ?></p>
                <?php else: ?>
                    <div class="laca-table-wrap">
                        <table class="widefat striped laca-projects-table laca-table">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('Dự án', 'laca'); ?></th>
                                    <th><?php echo esc_html__('Loại', 'laca'); ?></th>
                                    <th><?php echo esc_html__('Mức độ', 'laca'); ?></th>
                                    <th><?php echo esc_html__('Tiêu đề & Nội dung', 'laca'); ?></th>
                                    <th><?php echo esc_html__('Trạng thái', 'laca'); ?></th>
                                    <th><?php echo esc_html__('Ngày gửi', 'laca'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientRequests as $req):
                                    $meta = !empty($req->meta) ? json_decode($req->meta, true) : [];
                                    $reqStatus = $meta['status'] ?? 'pending';
                                    $statusLabels = [
                                        'pending'    => __('Chờ xử lý', 'laca'),
                                        'processing' => __('Đang xử lý', 'laca'),
                                        'resolved'   => __('Đã xử lý', 'laca'),
                                    ];
                                ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url(get_edit_post_link((int) $req->project_id, '')); ?>"><strong><?php echo esc_html($req->project_name ?: '#' . $req->project_id); ?></strong></a></td>
                                        <td><?php echo esc_html($meta['category_label'] ?? __('Hỗ trợ', 'laca')); ?></td>
                                        <td><span class="laca-projects-badge"><?php echo esc_html($meta['priority_label'] ?? __('Bình thường', 'laca')); ?></span></td>
                                        <td>
                                            <strong><?php echo esc_html($meta['title'] ?? wp_trim_words($req->log_content, 8)); ?></strong>
                                            <div class="laca-projects-desc"><?php echo esc_html(wp_trim_words($meta['description'] ?? $req->log_content, 20)); ?></div>
                                            <?php if (!empty($meta['attachment_url'])): ?>
                                                <div class="laca-projects-attachment"><a href="<?php echo esc_url($meta['attachment_url']); ?>" target="_blank">📎 <?php echo esc_html__('Xem ảnh đính kèm', 'laca'); ?></a></div>
                                            <?php endif; ?>
                                            <?php if (!empty($meta['admin_reply'])): ?>
                                                <div class="laca-projects-reply">💬 <?php echo esc_html($meta['admin_reply']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="laca-badge laca-badge--<?php echo $reqStatus === 'resolved' ? 'success' : ($reqStatus === 'processing' ? 'warning' : 'danger'); ?>">
                                                <?php echo esc_html($statusLabels[$reqStatus] ?? $reqStatus); ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html($this->formatDateTime((string) $req->created_at)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }

    public function renderUpdates(): void
    {
        $logs = $this->getProjectLogs(100);
        $clientUpdates = $this->getClientUpdates($logs);
        $typeCounts = $this->getLogTypeCounts($logs);

        ?>
        <div class="wrap laca-projects-wrap">
            <?php $this->renderHeader('Updates', 'Theo dõi cập nhật tự động, deploy, bảo mật và thay đổi từ website khách hàng.'); ?>
            <?php $this->renderIntroCallout(__('Updates', 'laca'), __('Danh sách report tự động (deploy, cập nhật plugin/core, bảo mật, sửa lỗi...) mà tracker/site khách gửi về, giúp biết website nào vừa có thay đổi.', 'laca')); ?>
            <div class="laca-projects-overview laca-projects-overview--compact">
                <?php $this->renderMetric('Tổng updates', (string) count($logs), '100 log mới nhất'); ?>
                <?php $this->renderMetric('Report tự động', (string) $clientUpdates['auto_count'], 'Gửi từ tracker/client site'); ?>
                <?php $this->renderMetric('Deploy/update', (string) (($typeCounts['deployment'] ?? 0) + ($typeCounts['plugin_update'] ?? 0) + ($typeCounts['core_update'] ?? 0)), 'Plugin, core, deploy'); ?>
                <?php $this->renderMetric('Security/bug', (string) (($typeCounts['security'] ?? 0) + ($typeCounts['bug_fix'] ?? 0) + ($typeCounts['file_changed'] ?? 0)), 'Sự kiện cần kiểm tra'); ?>
            </div>
            <section class="laca-projects-panel">
                <?php $this->renderLogsTable($logs); ?>
            </section>
        </div>
        <?php
    }

    public function renderFleet(): void
    {
        (new FleetPage())->render();
    }

    public function renderRenewals(): void
    {
        $rows = $this->getRenewalRows($this->getProjects(), 120);

        $this->renderSimpleTablePage(
            'Renewals',
            'Theo dõi domain, hosting và phí bảo trì cần gia hạn trong 120 ngày.',
            ['Dự án', 'Khách hàng', 'Hạng mục', 'Ngày đến hạn', 'Còn lại', 'Giá trị'],
            $rows,
            $this->getIntroCalloutHtml(__('Renewals', 'laca'), __('Danh sách domain, hosting và phí bảo trì sắp đến hạn trong 120 ngày tới — dùng để chủ động nhắc khách gia hạn trước khi hết hạn.', 'laca'))
        );
    }

    public function renderOperations(): void
    {
        $rows = [];
        foreach ($this->getProjects() as $project) {
            $id = (int) $project->ID;
            $rows[] = [
                '<a href="' . esc_url(get_edit_post_link($id, '')) . '">' . esc_html($project->post_title) . '</a>',
                esc_html($this->meta($id, 'domain_name') ?: '—'),
                esc_html($this->formatDate($this->meta($id, 'domain_expiry'))),
                esc_html($this->formatDate($this->meta($id, 'hosting_expiry'))),
                esc_html($this->getTrackerLastSeenLabel($id)),
                esc_html((string) ProjectAlert::countActive($id)),
            ];
        }

        $this->renderSimpleTablePage(
            'Vận hành',
            'Theo dõi domain, hosting, tracker heartbeat, cảnh báo và trạng thái vận hành.',
            ['Dự án', 'Domain', 'Hết hạn domain', 'Hết hạn hosting', 'Heartbeat', 'Cảnh báo'],
            $rows,
            $this->getIntroCalloutHtml(__('Vận hành', 'laca'), __('Theo dõi tình trạng vận hành từng website: domain/hosting còn hạn bao lâu, tracker heartbeat có còn hoạt động và số cảnh báo đang active.', 'laca'))
        );
    }

    public function renderFinance(): void
    {
        $rows = [];
        foreach ($this->getProjects() as $project) {
            $id = (int) $project->ID;
            $build = $this->moneyToInt($this->meta($id, 'price_build'));
            $paid = $this->getPaidAmount($id);
            $rows[] = [
                '<a href="' . esc_url(get_edit_post_link($id, '')) . '">' . esc_html($project->post_title) . '</a>',
                esc_html($this->meta($id, 'client_name') ?: '—'),
                esc_html($this->formatMoney($build)),
                esc_html($this->formatMoney($paid)),
                esc_html($this->paymentLabel($this->meta($id, 'payment_status') ?: 'pending')),
            ];
        }

        $this->renderSimpleTablePage(
            'Thanh toán',
            'Theo dõi báo giá, số tiền đã thanh toán và trạng thái công nợ.',
            ['Dự án', 'Khách hàng', 'Giá build', 'Đã thanh toán', 'Trạng thái'],
            $rows,
            $this->getIntroCalloutHtml(__('Thanh toán', 'laca'), __('Theo dõi giá trị hợp đồng, số tiền khách đã thanh toán và trạng thái công nợ của từng dự án.', 'laca'))
        );
    }

    public function renderHealth(): void
    {
        $rows = $this->getHealthRows($this->getProjects());

        $this->renderSimpleTablePage(
            'Health score',
            'Chấm điểm sức khoẻ dự án dựa trên cảnh báo, công nợ, hạn dịch vụ, cập nhật và heartbeat tracker.',
            ['Dự án', 'Khách hàng', 'Score', 'Trạng thái', 'Điểm trừ chính', 'Heartbeat', 'Cập nhật cuối'],
            $rows,
            $this->getIntroCalloutHtml(__('Health score', 'laca'), __('Điểm sức khỏe từng dự án được tính tự động dựa trên cảnh báo, công nợ, hạn dịch vụ, cập nhật và heartbeat tracker — điểm càng thấp càng cần quan tâm.', 'laca'))
        );
    }

    public function renderReports(): void
    {
        $projects = $this->getProjects();
        $finance = $this->getFinanceReport($projects);
        $month = current_time('Y-m');
        $monthlyPaid = $this->getPaidAmountForMonth($projects, $month);
        $logs = $this->getProjectLogs(300);
        $monthLogs = array_values(array_filter($logs, fn(array $log): bool => str_starts_with((string) ($log['created_at'] ?? $log['log_date'] ?? ''), $month)));
        $alertReport = $this->getAlertReport();
        $monthlyServiceRows = $this->getMonthlyServiceReportRows($monthLogs);

        ?>
        <div class="wrap laca-projects-wrap">
            <?php $this->renderHeader('Reports', 'Báo cáo tổng hợp doanh thu, công nợ, vận hành và cập nhật trong tháng.'); ?>
            <?php $this->renderIntroCallout(__('Reports', 'laca'), __('Báo cáo tổng hợp theo tháng: doanh thu đã thu, công nợ còn lại, số lượng cập nhật/bảo trì và cơ cấu trạng thái dự án — dùng để tổng kết định kỳ.', 'laca')); ?>
            <div class="laca-projects-overview">
                <?php $this->renderMetric('Đã thu tháng này', $this->formatMoney($monthlyPaid), date_i18n('m/Y')); ?>
                <?php $this->renderMetric('Tổng đã thu', $this->formatMoney($finance['total_paid']), $finance['collection_rate'] . '% tổng giá trị'); ?>
                <?php $this->renderMetric('Còn phải thu', $this->formatMoney($finance['outstanding']), $finance['unpaid_count'] . ' dự án'); ?>
                <?php $this->renderMetric('Updates tháng này', (string) count($monthLogs), 'Từ ProjectLog'); ?>
                <?php $this->renderMetric('Issues active', (string) $alertReport['total'], $alertReport['critical'] . ' critical'); ?>
            </div>
            <div class="laca-projects-grid">
                <section class="laca-projects-panel">
                    <div class="laca-projects-panel__head">
                        <h2>Cơ cấu trạng thái</h2>
                    </div>
                    <?php $this->renderKeyValueList($this->getStatusBreakdown($projects)); ?>
                </section>
                <section class="laca-projects-panel">
                    <div class="laca-projects-panel__head">
                        <h2>Loại cập nhật gần đây</h2>
                    </div>
                    <?php $this->renderKeyValueList($this->getReadableLogTypeCounts($monthLogs)); ?>
                </section>
                <section class="laca-projects-panel laca-projects-panel--wide laca-card">
                    <div class="laca-projects-panel__head">
                        <h2><?php echo esc_html__('Báo cáo chăm sóc website tháng này', 'laca'); ?></h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=laca-project-updates')); ?>">Mở Updates</a>
                    </div>
                    <?php if ($monthlyServiceRows === []): ?>
                        <p class="laca-projects-empty"><?php echo esc_html__('Chưa có hoạt động bảo trì/cập nhật trong tháng này.', 'laca'); ?></p>
                    <?php else: ?>
                        <div class="laca-table-wrap">
                            <table class="widefat striped laca-projects-table laca-table">
                                <thead><tr><th><?php echo esc_html__('Dự án', 'laca'); ?></th><th><?php echo esc_html__('Cập nhật', 'laca'); ?></th><th><?php echo esc_html__('Sửa lỗi / yêu cầu', 'laca'); ?></th><th><?php echo esc_html__('Bảo trì / task', 'laca'); ?></th><th><?php echo esc_html__('Gần nhất', 'laca'); ?></th></tr></thead>
                                <tbody>
                                    <?php foreach ($monthlyServiceRows as $row): ?>
                                        <tr>
                                            <td><a href="<?php echo esc_url(get_edit_post_link((int) $row['project_id'], '')); ?>"><?php echo esc_html($row['project_name']); ?></a></td>
                                            <td><?php echo esc_html((string) $row['updates']); ?></td>
                                            <td><?php echo esc_html((string) $row['fixes']); ?></td>
                                            <td><?php echo esc_html((string) $row['maintenance']); ?></td>
                                            <td><?php echo esc_html($this->formatDateTime((string) $row['latest'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="laca-projects-panel laca-projects-panel--wide">
                    <div class="laca-projects-panel__head">
                        <h2>Project summary</h2>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=project')); ?>">Mở danh sách dự án</a>
                    </div>
                    <?php $this->renderProjectSummaryTable($this->getProjectSummaryRows($projects, 20)); ?>
                </section>
            </div>
        </div>
        <?php
    }

    public function renderPortal(): void
    {
        $portalUrl = $this->getClientPortalPageUrl();
        $rows = [];
        foreach ($this->getProjects() as $project) {
            $id = (int) $project->ID;
            $key = (string) (get_post_meta($id, '_portal_alias', true) ?: get_post_meta($id, '_tracker_secret_key', true));
            $url = $portalUrl && $key ? add_query_arg('key', $key, $portalUrl) : '';
            $rows[] = [
                '<a href="' . esc_url(get_edit_post_link($id, '')) . '">' . esc_html($project->post_title) . '</a>',
                esc_html($this->meta($id, 'client_name') ?: '—'),
                $url ? '<input class="laca-projects-copy" type="text" readonly value="' . esc_attr($url) . '">' : esc_html__('Chưa có portal page hoặc key', 'laca'),
                $url ? '<a class="button button-small" target="_blank" href="' . esc_url($url) . '">Mở</a>' : '',
            ];
        }

        $this->renderSimpleTablePage(
            'Portal links',
            'Link theo dõi tiến độ dành cho khách hàng.',
            ['Dự án', 'Khách hàng', 'Portal URL', ''],
            $rows,
            $this->getIntroCalloutHtml(__('Portal links', 'laca'), __('Link Client Portal riêng cho từng dự án để gửi cho khách hàng theo dõi tiến độ, không cần đăng nhập vào wp-admin.', 'laca'))
        );
    }

    public function filterAdminBodyClass(string $classes): string
    {
        if (!$this->isHubRequest()) {
            return $classes;
        }

        return trim($classes . ' laca-projects-dock-active');
    }

    public function printStyles(): void
    {
        // Hub layout styles have been migrated to resources/styles/admin/skins/_project-hub.scss
    }

    private function getIntroCalloutHtml(string $title, string $description): string
    {
        return sprintf(
            '<div class="laca-alert laca-alert--info laca-projects-intro" role="status"><span class="dashicons dashicons-info laca-alert__icon"></span><div class="laca-alert__content"><div class="laca-alert__title">🔧 %s</div><div class="laca-alert__description">%s</div></div></div>',
            esc_html($title),
            esc_html($description)
        );
    }

    private function renderIntroCallout(string $title, string $description): void
    {
        echo $this->getIntroCalloutHtml($title, $description);
    }

    public function renderDock(): void
    {
        if (!$this->isHubRequest()) {
            return;
        }

        $current = $this->getCurrentNavKey();
        ?>
        <nav class="laca-projects-dock" aria-label="<?php echo esc_attr__('Laca Projects', 'laca'); ?>">
            <a class="laca-projects-dock__brand" href="<?php echo esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)); ?>">
                <span class="laca-projects-dock__mark" aria-hidden="true">LP</span>
                <span class="laca-projects-dock__brand-copy">
                    <span class="laca-projects-dock__title"><?php echo esc_html__('Laca Projects', 'laca'); ?></span>
                    <span class="laca-projects-dock__subtitle"><?php echo esc_html__('CRM & delivery hub', 'laca'); ?></span>
                </span>
            </a>
            <?php foreach (self::NAVIGATION as $group): ?>
                <section class="laca-projects-dock__group">
                    <h2 class="laca-projects-dock__group-title">
                        <span class="dashicons <?php echo esc_attr($group['icon']); ?>" aria-hidden="true"></span>
                        <span><?php echo esc_html($group['label']); ?></span>
                        <span class="laca-projects-dock__group-count"><?php echo esc_html((string) count($group['items'])); ?></span>
                    </h2>
                    <div class="laca-projects-dock__items">
                        <?php foreach ($group['items'] as $item): ?>
                            <a class="laca-projects-dock__item<?php echo $current === $item['key'] ? ' is-active' : ''; ?>"
                               href="<?php echo esc_url(admin_url($item['url'])); ?>"
                               <?php echo $current === $item['key'] ? 'aria-current="page"' : ''; ?>>
                                <?php echo esc_html($item['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    private function renderHeader(string $title, string $description): void
    {
        ?>
        <div class="laca-page-header laca-projects-header">
            <div class="laca-page-header__content">
                <h1 class="laca-page-header__title"><?php echo esc_html($title); ?></h1>
                <p class="laca-page-header__description"><?php echo esc_html($description); ?></p>
            </div>
            <div class="laca-page-header__actions">
                <a class="laca-btn laca-btn--solid laca-btn--sm button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=project')); ?>"><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('Thêm dự án', 'laca'); ?></a>
            </div>
        </div>
        <?php
    }

    private function renderStat(string $label, string $value): void
    {
        echo '<div class="laca-projects-stat"><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong></div>';
    }

    /**
     * @param array<string,mixed> $data
     */
    private function renderChartDataScript(array $data): void
    {
        echo '<script>window.lacaProjectsHubCharts = ' . wp_json_encode($data) . ';</script>';
    }

    private function renderMetric(string $label, string $value, string $note = '', string $tone = 'neutral'): void
    {
        ?>
        <div class="laca-projects-metric laca-projects-metric--<?php echo esc_attr($tone); ?>">
            <span><?php echo esc_html($label); ?></span>
            <strong><?php echo esc_html($value); ?></strong>
            <?php if ($note !== ''): ?>
                <small><?php echo esc_html($note); ?></small>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param array<int,array<string,mixed>> $logs
     */
    private function renderLogsTable(array $logs): void
    {
        if ($logs === []) {
            echo '<p class="laca-projects-empty">' . esc_html__('Chưa có nhật ký dự án.', 'laca') . '</p>';
            return;
        }

        ?>
        <div class="laca-table-wrap">
            <table class="widefat striped laca-projects-table laca-table">
                <thead><tr><th><?php echo esc_html__('Ngày', 'laca'); ?></th><th><?php echo esc_html__('Dự án', 'laca'); ?></th><th><?php echo esc_html__('Loại', 'laca'); ?></th><th><?php echo esc_html__('Nội dung', 'laca'); ?></th></tr></thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html($this->formatLogDate($log)); ?></td>
                            <td><a href="<?php echo esc_url(get_edit_post_link((int) $log['project_id'], '')); ?>"><?php echo esc_html($log['project_name'] ?? ''); ?></a></td>
                            <td><?php echo esc_html($this->logTypeLabel((string) $log['log_type'])); ?></td>
                            <td><?php echo esc_html(wp_trim_words((string) $log['log_content'], 20)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * @param array<string,string|int> $items
     */
    private function renderKeyValueList(array $items): void
    {
        if ($items === []) {
            echo '<p class="laca-projects-empty">' . esc_html__('Chưa có dữ liệu.', 'laca') . '</p>';
            return;
        }

        echo '<dl class="laca-projects-kv">';
        foreach ($items as $label => $value) {
            echo '<div><dt>' . esc_html((string) $label) . '</dt><dd>' . esc_html((string) $value) . '</dd></div>';
        }
        echo '</dl>';
    }

    /**
     * @param array<int,array{id:int,title:string,client:string,status:string,finance:string,alerts:int,expiry:string}> $rows
     */
    private function renderProjectSummaryTable(array $rows): void
    {
        if ($rows === []) {
            echo '<p class="laca-projects-empty">' . esc_html__('Chưa có project.', 'laca') . '</p>';
            return;
        }

        ?>
        <div class="laca-table-wrap">
            <table class="widefat striped laca-projects-table laca-table">
                <thead><tr><th><?php echo esc_html__('Dự án', 'laca'); ?></th><th><?php echo esc_html__('Khách hàng', 'laca'); ?></th><th><?php echo esc_html__('Trạng thái', 'laca'); ?></th><th><?php echo esc_html__('Tài chính', 'laca'); ?></th><th><?php echo esc_html__('Alerts', 'laca'); ?></th><th><?php echo esc_html__('Hạn gần nhất', 'laca'); ?></th></tr></thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><a href="<?php echo esc_url(get_edit_post_link($row['id'], '')); ?>"><?php echo esc_html($row['title']); ?></a></td>
                            <td><?php echo esc_html($row['client']); ?></td>
                            <td><?php echo esc_html($row['status']); ?></td>
                            <td><?php echo esc_html($row['finance']); ?></td>
                            <td><?php echo esc_html((string) $row['alerts']); ?></td>
                            <td><?php echo esc_html($row['expiry']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * @param array<int,array{project:string,type:string,message:string,due:string,priority:int,url:string}> $items
     */
    private function renderActionItems(array $items): void
    {
        if ($items === []) {
            echo '<p class="laca-projects-empty">' . esc_html__('Không có việc cần xử lý.', 'laca') . '</p>';
            return;
        }

        ?>
        <div class="laca-projects-actions">
            <?php foreach (array_slice($items, 0, 8) as $item): ?>
                <a class="laca-projects-action"
                   href="<?php echo esc_url($item['url']); ?>"
                   data-laca-project-detail
                   data-title="<?php echo esc_attr($item['project']); ?>"
                   data-meta="<?php echo esc_attr($item['type'] . ' - priority ' . $item['priority']); ?>"
                   data-message="<?php echo esc_attr($item['message'] . "\n" . $item['due']); ?>"
                   data-url="<?php echo esc_url($item['url']); ?>">
                    <span class="laca-projects-action__meta">
                        <strong><?php echo esc_html($item['project']); ?></strong>
                        <em><?php echo esc_html($item['type']); ?> - <?php echo esc_html($item['due']); ?></em>
                    </span>
                    <span class="laca-projects-action__message"><?php echo esc_html($item['message']); ?></span>
                    <span class="laca-projects-badge"><?php echo esc_html((string) $item['priority']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * @param array<int,array<string,mixed>> $items
     */
    private function renderClientRequestItems(array $items): void
    {
        if ($items === []) {
            echo '<p class="laca-projects-empty">' . esc_html__('Chưa có yêu cầu mới từ client portal.', 'laca') . '</p>';
            return;
        }

        ?>
        <div class="laca-projects-actions">
            <?php foreach (array_slice($items, 0, 8) as $item): ?>
                <?php $url = get_edit_post_link((int) $item['project_id'], ''); ?>
                <a class="laca-projects-action"
                   href="<?php echo esc_url($url); ?>"
                   data-laca-project-detail
                   data-title="<?php echo esc_attr($item['project_name'] ?? 'Client request'); ?>"
                   data-meta="<?php echo esc_attr('Client Portal - ' . $this->formatLogDate($item)); ?>"
                   data-message="<?php echo esc_attr((string) ($item['log_content'] ?? '')); ?>"
                   data-url="<?php echo esc_url($url); ?>">
                    <span class="laca-projects-action__meta">
                        <strong><?php echo esc_html($item['project_name'] ?? 'Dự án #' . $item['project_id']); ?></strong>
                        <em><?php echo esc_html($this->formatLogDate($item)); ?></em>
                    </span>
                    <span class="laca-projects-action__message"><?php echo esc_html(wp_trim_words((string) ($item['log_content'] ?? ''), 24)); ?></span>
                    <span class="laca-projects-badge"><?php echo esc_html__('Client', 'laca'); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * @param string[] $headers
     * @param array<int,array<int,string>> $rows
     */
    private function renderSimpleTablePage(string $title, string $description, array $headers, array $rows, string $intro = ''): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Bạn không có quyền xem trang này.', 'laca'));
        }

        ?>
        <div class="wrap laca-projects-wrap">
            <?php $this->renderHeader($title, $description); ?>
            <?php echo $intro; ?>
            <section class="laca-projects-panel laca-card">
                <?php if ($rows === []): ?>
                    <p class="laca-projects-empty"><?php echo esc_html__('Chưa có dữ liệu.', 'laca'); ?></p>
                <?php else: ?>
                    <div class="laca-table-wrap">
                        <table class="widefat striped laca-projects-table laca-table">
                            <thead>
                                <tr>
                                    <?php foreach ($headers as $header): ?>
                                        <th><?php echo esc_html($header); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $cell): ?>
                                            <td><?php echo wp_kses_post($cell); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <?php
    }

    /**
     * @return \WP_Post[]
     */
    private function getProjects(): array
    {
        return get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);
    }

    /**
     * @param \WP_Post[] $projects
     * @return array{total:int,active:int,maintenance:int,alerts:int,expiring:int,unpaid:int}
     */
    private function getStats(array $projects): array
    {
        $active = 0;
        $maintenance = 0;
        $unpaid = 0;

        foreach ($projects as $project) {
            $id = (int) $project->ID;
            if ($this->meta($id, 'project_status') === 'in_progress') {
                $active++;
            }
            if ($this->meta($id, 'project_status') === 'maintenance') {
                $maintenance++;
            }
            if (($this->meta($id, 'payment_status') ?: 'pending') !== 'paid' && $this->moneyToInt($this->meta($id, 'price_build')) > 0) {
                $unpaid++;
            }
        }

        return [
            'total' => count($projects),
            'active' => $active,
            'maintenance' => $maintenance,
            'alerts' => class_exists(ProjectAlert::class) ? ProjectAlert::countAllActive() : 0,
            'expiring' => count($this->getExpiringProjects($projects, 30)),
            'unpaid' => $unpaid,
        ];
    }

    /**
     * @param \WP_Post[] $projects
     * @return array{total:int,online:int,stale:int,missing:int}
     */
    private function getTrackerReport(array $projects): array
    {
        $report = [
            'total' => count($projects),
            'online' => 0,
            'stale' => 0,
            'missing' => 0,
        ];

        foreach ($projects as $project) {
            $age = $this->getTrackerAgeDays((int) $project->ID);
            if ($age === null) {
                $report['missing']++;
            } elseif ($age <= 2) {
                $report['online']++;
            } else {
                $report['stale']++;
            }
        }

        return $report;
    }

    /**
     * @param \WP_Post[] $projects
     * @return array{total_build:int,total_paid:int,outstanding:int,maintenance_yearly:int,unpaid_count:int,collection_rate:int}
     */
    private function getFinanceReport(array $projects): array
    {
        $totalBuild = 0;
        $totalPaid = 0;
        $maintenanceYearly = 0;
        $unpaidCount = 0;
        $outstanding = 0;

        foreach ($projects as $project) {
            $id = (int) $project->ID;
            $build = $this->moneyToInt($this->meta($id, 'price_build'));
            $paid = $this->getPaidAmount($id);

            $totalBuild += $build;
            $totalPaid += $paid;
            $maintenanceYearly += $this->moneyToInt($this->meta($id, 'price_maintenance_yearly'));

            // Cộng dồn nợ CỦA TỪNG DỰ ÁN — không được trừ tổng aggregate
            // (totalBuild - totalPaid), vì 1 dự án trả dư (paid > build, vd
            // phát sinh thêm scope chưa cập nhật lại price_build) sẽ âm thầm
            // triệt tiêu công nợ thật của 1 dự án KHÁC đang thiếu tiền.
            if ($build > 0 && $paid < $build) {
                $unpaidCount++;
                $outstanding += $build - $paid;
            }
        }

        return [
            'total_build' => $totalBuild,
            'total_paid' => $totalPaid,
            'outstanding' => $outstanding,
            'maintenance_yearly' => $maintenanceYearly,
            'unpaid_count' => $unpaidCount,
            'collection_rate' => $totalBuild > 0 ? (int) round(min(100, ($totalPaid / $totalBuild) * 100)) : 0,
        ];
    }

    /**
     * @return array{total:int,critical:int,warning:int,info:int,website_issues:int}
     */
    private function getAlertReport(): array
    {
        $report = [
            'total' => 0,
            'critical' => 0,
            'warning' => 0,
            'info' => 0,
            'website_issues' => 0,
        ];

        if (!class_exists(ProjectAlert::class)) {
            return $report;
        }

        // Đếm bằng SQL GROUP BY (ProjectAlert::getActiveLevelCounts()) chứ
        // không suy ra từ 1 trang kết quả giới hạn — trước đây dùng
        // getAllActiveFiltered([], 200, 1) rồi đếm thủ công qua $result['items'],
        // nên nếu tổng alert đang hoạt động vượt quá 200, breakdown
        // critical/warning/info không còn cộng khớp với 'total' hiển thị.
        $counts = ProjectAlert::getActiveLevelCounts();
        $report['critical']       = $counts['critical'];
        $report['warning']        = $counts['warning'];
        $report['info']           = $counts['info'];
        $report['website_issues'] = $counts['website_issues'];
        $report['total']          = $counts['critical'] + $counts['warning'] + $counts['info'];

        return $report;
    }

    /**
     * @param array<int,array<string,mixed>> $logs
     * @return array{auto_count:int,items:array<int,array<string,mixed>>}
     */
    private function getClientUpdates(array $logs): array
    {
        $items = array_values(array_filter($logs, static function (array $log): bool {
            return !empty($log['is_auto']) || in_array((string) ($log['log_type'] ?? ''), [
                'deployment',
                'plugin_update',
                'plugin_activate',
                'plugin_deactivate',
                'core_update',
                'theme_switch',
                'file_changed',
                'bug_fix',
                'security',
                'maintenance_summary',
            ], true);
        }));

        return [
            'auto_count' => count(array_filter($logs, static fn(array $log): bool => !empty($log['is_auto']))),
            'items' => $items,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $logs
     * @return array<int,array<string,mixed>>
     */
    private function getClientRequestLogs(array $logs): array
    {
        return array_values(array_filter($logs, static function (array $log): bool {
            return (string) ($log['log_type'] ?? '') === 'client_request';
        }));
    }

    /**
     * @param \WP_Post[] $projects
     * @return array<int,array{id:int,title:string,reason:string,score:int}>
     */
    private function getRiskProjects(array $projects, int $limit): array
    {
        $rows = [];

        foreach ($projects as $project) {
            $id = (int) $project->ID;
            $reasons = [];
            $score = 0;
            $alerts = class_exists(ProjectAlert::class) ? ProjectAlert::countActive($id) : 0;
            $build = $this->moneyToInt($this->meta($id, 'price_build'));
            $paid = $this->getPaidAmount($id);
            $expiry = $this->getNearestExpiry($id);

            if ($alerts > 0) {
                $score += min(50, $alerts * 12);
                $reasons[] = $alerts . ' cảnh báo active';
            }

            if ($build > 0 && $paid < $build) {
                $score += 20;
                $reasons[] = 'Còn phải thu ' . $this->formatMoney($build - $paid);
            }

            if ($expiry !== null && $expiry['days_left'] <= 30) {
                $score += $expiry['days_left'] < 0 ? 35 : 18;
                $reasons[] = $expiry['label'];
            }

            if ($score <= 0) {
                continue;
            }

            $rows[] = [
                'id' => $id,
                'title' => $project->post_title,
                'reason' => implode(' - ', array_slice($reasons, 0, 2)),
                'score' => min(100, $score),
            ];
        }

        usort($rows, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($rows, 0, $limit);
    }

    /**
     * @param \WP_Post[] $projects
     * @return array<int,array{id:int,title:string,client:string,status:string,finance:string,alerts:int,expiry:string}>
     */
    private function getProjectSummaryRows(array $projects, int $limit): array
    {
        $rows = [];

        foreach (array_slice($projects, 0, $limit) as $project) {
            $id = (int) $project->ID;
            $build = $this->moneyToInt($this->meta($id, 'price_build'));
            $paid = $this->getPaidAmount($id);
            $expiry = $this->getNearestExpiry($id);

            $rows[] = [
                'id' => $id,
                'title' => $project->post_title,
                'client' => $this->meta($id, 'client_name') ?: '—',
                'status' => $this->statusLabel($this->meta($id, 'project_status') ?: 'pending'),
                'finance' => $this->formatMoney($paid) . ' / ' . $this->formatMoney($build),
                'alerts' => class_exists(ProjectAlert::class) ? ProjectAlert::countActive($id) : 0,
                'expiry' => $expiry['label'] ?? '—',
            ];
        }

        return $rows;
    }

    /**
     * @param \WP_Post[] $projects
     * @return array<int,array<int,string>>
     */
    private function getClientRows(array $projects): array
    {
        $clients = [];

        foreach ($projects as $project) {
            $id = (int) $project->ID;
            $name = trim($this->meta($id, 'client_name') ?: 'Chưa đặt tên');
            $email = trim($this->meta($id, 'client_email') ?: '');
            $phone = trim($this->meta($id, 'client_phone') ?: '');
            $key = strtolower($email !== '' ? $email : $name);

            if (!isset($clients[$key])) {
                $clients[$key] = [
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'projects' => [],
                    'total' => 0,
                    'paid' => 0,
                    'latest' => '',
                ];
            }

            $build = $this->moneyToInt($this->meta($id, 'price_build'));
            $paid = $this->getPaidAmount($id);

            $clients[$key]['projects'][] = [
                'id' => $id,
                'title' => $project->post_title,
                'status' => $this->statusLabel($this->meta($id, 'project_status') ?: 'pending'),
            ];
            $clients[$key]['total'] += $build;
            $clients[$key]['paid'] += $paid;
            $clients[$key]['latest'] = max((string) $clients[$key]['latest'], (string) $project->post_modified);
        }

        uasort($clients, static fn(array $a, array $b): int => strcmp((string) $b['latest'], (string) $a['latest']));

        $rows = [];
        foreach ($clients as $client) {
            $projectLinks = array_map(static function (array $project): string {
                return '<a href="' . esc_url(get_edit_post_link((int) $project['id'], '')) . '">' . esc_html($project['title']) . '</a>';
            }, array_slice($client['projects'], 0, 4));

            $more = count($client['projects']) > 4 ? ' +' . (count($client['projects']) - 4) : '';
            $contact = trim(($client['phone'] ? $client['phone'] . ' ' : '') . ($client['email'] ?: ''));

            $rows[] = [
                esc_html($client['name']),
                esc_html($contact !== '' ? $contact : '—'),
                implode('<br>', $projectLinks) . esc_html($more),
                esc_html($this->formatMoney((int) $client['total'])),
                esc_html($this->formatMoney(max(0, (int) $client['total'] - (int) $client['paid']))),
                esc_html($client['latest'] ? $this->formatDate((string) $client['latest']) : '—'),
            ];
        }

        return $rows;
    }

    /**
     * @param \WP_Post[] $projects
     * @param array{total_build:int,total_paid:int,outstanding:int,maintenance_yearly:int,unpaid_count:int,collection_rate:int} $finance
     * @param array{total:int,critical:int,warning:int,info:int,website_issues:int} $alertReport
     * @return array<string,mixed>
     */
    private function getDashboardChartData(array $projects, array $finance, array $alertReport): array
    {
        $statusCounts = $this->getStatusBreakdown($projects);

        return [
            'finance' => [
                'labels' => ['Đã thu', 'Còn phải thu'],
                'values' => [$finance['total_paid'], $finance['outstanding']],
            ],
            'alerts' => [
                'labels' => ['Critical', 'Warning', 'Info'],
                'values' => [$alertReport['critical'], $alertReport['warning'], $alertReport['info']],
            ],
            'status' => [
                'labels' => array_keys($statusCounts),
                'values' => array_values($statusCounts),
            ],
        ];
    }

    /**
     * @param \WP_Post[] $projects
     * @return array<int,array{key:string,label:string,items:array<int,array{id:int,title:string,client:string,finance:string,alerts:int,expiry:string}>}>
     */
    private function getPipelineColumns(array $projects): array
    {
        $columns = [
            'pending' => ['key' => 'pending', 'label' => 'Chờ làm', 'items' => []],
            'in_progress' => ['key' => 'in_progress', 'label' => 'Đang làm', 'items' => []],
            'maintenance' => ['key' => 'maintenance', 'label' => 'Bảo trì', 'items' => []],
            'paused' => ['key' => 'paused', 'label' => 'Tạm dừng', 'items' => []],
            'done' => ['key' => 'done', 'label' => 'Đã xong', 'items' => []],
        ];

        foreach ($projects as $project) {
            $id = (int) $project->ID;
            $status = $this->meta($id, 'project_status') ?: 'pending';
            if (!isset($columns[$status])) {
                $status = 'pending';
            }

            $build = $this->moneyToInt($this->meta($id, 'price_build'));
            $paid = $this->getPaidAmount($id);
            $expiry = $this->getNearestExpiry($id);

            $columns[$status]['items'][] = [
                'id' => $id,
                'title' => $project->post_title,
                'client' => $this->meta($id, 'client_name') ?: '—',
                'finance' => $this->formatMoney($paid) . ' / ' . $this->formatMoney($build),
                'alerts' => class_exists(ProjectAlert::class) ? ProjectAlert::countActive($id) : 0,
                'expiry' => $expiry['label'] ?? '—',
            ];
        }

        return array_values($columns);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getProjectLogs(int $limit = 100): array
    {
        global $wpdb;

        if (!class_exists('\App\Databases\ProjectLogTable')) {
            return class_exists(ProjectLog::class) ? ProjectLog::getRecent($limit) : [];
        }

        $table = \App\Databases\ProjectLogTable::getTableName();

        return $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT l.*, p.post_title AS project_name
                 FROM {$table} l
                 LEFT JOIN {$wpdb->posts} p ON p.ID = l.project_id
                 ORDER BY l.created_at DESC, l.log_date DESC
                 LIMIT %d",
                absint($limit)
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * @param array<int,array<string,mixed>> $logs
     * @return array<string,int>
     */
    private function getLogTypeCounts(array $logs): array
    {
        $counts = [];

        foreach ($logs as $log) {
            $type = sanitize_key((string) ($log['log_type'] ?? 'note'));
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param array<int,array<string,mixed>> $logs
     * @return array<int,array{project_id:int,project_name:string,updates:int,fixes:int,maintenance:int,latest:string}>
     */
    private function getMonthlyServiceReportRows(array $logs): array
    {
        $rows = [];

        foreach ($logs as $log) {
            $projectId = (int) ($log['project_id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }

            if (!isset($rows[$projectId])) {
                $projectName = (string) ($log['project_name'] ?? '');
                if ($projectName === '') {
                    $projectName = get_the_title($projectId) ?: 'Dự án #' . $projectId;
                }

                $rows[$projectId] = [
                    'project_id' => $projectId,
                    'project_name' => $projectName,
                    'updates' => 0,
                    'fixes' => 0,
                    'maintenance' => 0,
                    'latest' => (string) ($log['created_at'] ?? $log['log_date'] ?? ''),
                ];
            }

            $type = (string) ($log['log_type'] ?? 'note');
            if (in_array($type, ['deployment', 'plugin_update', 'plugin_activate', 'plugin_deactivate', 'core_update', 'theme_switch'], true)) {
                $rows[$projectId]['updates']++;
            } elseif (in_array($type, ['bug_fix', 'client_request'], true)) {
                $rows[$projectId]['fixes']++;
            } else {
                $rows[$projectId]['maintenance']++;
            }

            $rows[$projectId]['latest'] = max($rows[$projectId]['latest'], (string) ($log['created_at'] ?? $log['log_date'] ?? ''));
        }

        usort($rows, static fn(array $a, array $b): int => strcmp($b['latest'], $a['latest']));

        return array_values($rows);
    }

    /**
     * @param array<int,array<string,mixed>> $logs
     * @return array<string,int>
     */
    private function getReadableLogTypeCounts(array $logs): array
    {
        $counts = [];

        foreach ($this->getLogTypeCounts($logs) as $type => $count) {
            $counts[$this->logTypeLabel($type)] = $count;
        }

        arsort($counts);

        return array_slice($counts, 0, 8, true);
    }

    /**
     * @param \WP_Post[] $projects
     * @return array<string,array<int,array{project:string,type:string,message:string,due:string,priority:int,url:string}>>
     */
    private function getActionCenterGroups(array $projects): array
    {
        $groups = [
            'urgent' => [],
            'finance' => [],
            'renewals' => [],
            'stale' => [],
        ];

        if (class_exists(ProjectAlert::class)) {
            $alerts = ProjectAlert::getAllActiveFiltered([], 80, 1);
            foreach (($alerts['items'] ?? []) as $alert) {
                $level = (string) ($alert['alert_level'] ?? 'info');
                $groups['urgent'][] = [
                    'project' => (string) ($alert['project_name'] ?? 'Dự án #' . $alert['project_id']),
                    'type' => ProjectAlert::getTypeLabel((string) ($alert['alert_type'] ?? 'other')),
                    'message' => wp_trim_words((string) ($alert['alert_msg'] ?? ''), 18),
                    'due' => $this->formatDateTime((string) ($alert['created_at'] ?? '')),
                    'priority' => $level === 'critical' ? 100 : ($level === 'warning' ? 75 : 45),
                    'url' => (string) get_edit_post_link((int) ($alert['project_id'] ?? 0), ''),
                ];
            }
        }

        foreach ($projects as $project) {
            $id = (int) $project->ID;
            $build = $this->moneyToInt($this->meta($id, 'price_build'));
            $paid = $this->getPaidAmount($id);
            if ($build > 0 && $paid < $build) {
                $groups['finance'][] = [
                    'project' => $project->post_title,
                    'type' => 'Công nợ',
                    'message' => 'Còn phải thu ' . $this->formatMoney($build - $paid),
                    'due' => $this->paymentLabel($this->meta($id, 'payment_status') ?: 'pending'),
                    'priority' => $paid <= 0 ? 80 : 60,
                    'url' => (string) get_edit_post_link($id, ''),
                ];
            }
        }

        foreach ($this->getExpiringProjects($projects, 30) as $row) {
            $groups['renewals'][] = [
                'project' => $row['title'],
                'type' => $row['service'],
                'message' => $row['service'] . ' hết hạn ngày ' . $row['date_label'],
                'due' => $row['days_label'],
                'priority' => $row['days_left'] < 0 ? 95 : ($row['days_left'] <= 7 ? 80 : 55),
                'url' => (string) get_edit_post_link($row['id'], ''),
            ];
        }

        $lastActivity = $this->getLastActivityMap();
        foreach ($projects as $project) {
            $id = (int) $project->ID;
            $status = $this->meta($id, 'project_status') ?: 'pending';
            if (!in_array($status, ['in_progress', 'maintenance', 'pending'], true)) {
                continue;
            }

            $last = $lastActivity[$id] ?? '';
            $days = $last !== '' ? (int) floor((current_time('timestamp') - strtotime($last)) / DAY_IN_SECONDS) : 999;
            if ($days < 30) {
                continue;
            }

            $groups['stale'][] = [
                'project' => $project->post_title,
                'type' => $this->statusLabel($status),
                'message' => $last === '' ? 'Chưa có nhật ký dự án' : 'Đã ' . $days . ' ngày chưa có cập nhật',
                'due' => $last === '' ? 'Chưa có log' : $this->formatDateTime($last),
                'priority' => $days >= 60 ? 60 : 40,
                'url' => (string) get_edit_post_link($id, ''),
            ];
        }

        foreach ($groups as $key => $items) {
            usort($items, static fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);
            $groups[$key] = $items;
        }

        return $groups;
    }

    /**
     * @param \WP_Post[] $projects
     * @return array<int,array<int,string>>
     */
    private function getRenewalRows(array $projects, int $days): array
    {
        $rows = [];

        foreach ($this->getExpiringProjects($projects, $days) as $row) {
            $priceKey = $row['service'] === 'Domain' ? 'domain_price' : 'hosting_price';
            $rows[] = [
                '<a href="' . esc_url(get_edit_post_link($row['id'], '')) . '">' . esc_html($row['title']) . '</a>',
                esc_html($this->meta($row['id'], 'client_name') ?: '—'),
                esc_html($row['service']),
                esc_html($row['date_label']),
                esc_html($row['days_label']),
                esc_html($this->formatMoney($this->moneyToInt($this->meta($row['id'], $priceKey)))),
            ];
        }

        return $rows;
    }

    /**
     * @param \WP_Post[] $projects
     * @return array<int,array<int,string>>
     */
    private function getHealthRows(array $projects): array
    {
        $lastActivity = $this->getLastActivityMap();
        $rows = [];

        foreach ($projects as $project) {
            $id = (int) $project->ID;
            $health = $this->calculateProjectHealth($id, $lastActivity[$id] ?? '');

            $rows[] = [
                '<a href="' . esc_url(get_edit_post_link($id, '')) . '">' . esc_html($project->post_title) . '</a>',
                esc_html($this->meta($id, 'client_name') ?: '—'),
                '<span class="laca-projects-badge">' . esc_html((string) $health['score']) . '</span>',
                esc_html($health['status']),
                esc_html($health['reason']),
                esc_html($this->getTrackerLastSeenLabel($id)),
                esc_html($this->formatDateTime($lastActivity[$id] ?? '')),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return (int) strip_tags($a[2]) <=> (int) strip_tags($b[2]);
        });

        return $rows;
    }

    /**
     * @return array<int,string>
     */
    private function getLastActivityMap(): array
    {
        global $wpdb;

        if (!class_exists('\App\Databases\ProjectLogTable')) {
            return [];
        }

        // Lấy log MỚI NHẤT CỦA TỪNG PROJECT bằng GROUP BY thay vì lấy 500
        // dòng log mới nhất TOÀN HỆ THỐNG rồi giữ dòng đầu tiên gặp mỗi
        // project — cách cũ khiến 1 vài project ồn ào (tracker heartbeat,
        // deploy liên tục) chiếm hết 500 dòng đó, làm project khác ÍT ồn ào
        // hơn nhưng vẫn có log thật bị coi nhầm là "chưa có log", kéo theo
        // health score và Action Center báo sai cho đúng project đó.
        $table = \App\Databases\ProjectLogTable::getTableName();

        $rows = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT project_id, COALESCE(MAX(created_at), MAX(log_date)) AS last_activity
             FROM {$table}
             GROUP BY project_id",
            ARRAY_A
        );

        $map = [];
        foreach ((array) $rows as $row) {
            $projectId = (int) ($row['project_id'] ?? 0);
            if ($projectId <= 0) {
                continue;
            }
            $map[$projectId] = (string) ($row['last_activity'] ?? '');
        }

        return $map;
    }

    /**
     * @return array{score:int,status:string,reason:string}
     */
    private function calculateProjectHealth(int $projectId, string $lastActivity): array
    {
        $score = 100;
        $reasons = [];
        $alerts = class_exists(ProjectAlert::class) ? ProjectAlert::countActive($projectId) : 0;
        $build = $this->moneyToInt($this->meta($projectId, 'price_build'));
        $paid = $this->getPaidAmount($projectId);
        $expiry = $this->getNearestExpiry($projectId);

        if ($alerts > 0) {
            $score -= min(50, $alerts * 15);
            $reasons[] = $alerts . ' cảnh báo';
        }

        if ($build > 0 && $paid < $build) {
            $score -= 15;
            $reasons[] = 'Còn công nợ';
        }

        if ($expiry !== null && $expiry['days_left'] <= 30) {
            $score -= $expiry['days_left'] < 0 ? 30 : 15;
            $reasons[] = $expiry['days_left'] < 0 ? 'Quá hạn gia hạn' : 'Sắp gia hạn';
        }

        if ($lastActivity !== '') {
            $daysSinceLog = floor((current_time('timestamp') - strtotime($lastActivity)) / DAY_IN_SECONDS);
            if ($daysSinceLog >= 30) {
                $score -= 10;
                $reasons[] = 'Ít cập nhật';
            }
        } else {
            $score -= 8;
            $reasons[] = 'Chưa có log';
        }

        $trackerAge = $this->getTrackerAgeDays($projectId);
        if ($trackerAge === null) {
            $score -= 8;
            $reasons[] = 'Chưa có heartbeat';
        } elseif ($trackerAge > 7) {
            $score -= 25;
            $reasons[] = 'Tracker im lặng';
        } elseif ($trackerAge > 2) {
            $score -= 12;
            $reasons[] = 'Heartbeat chậm';
        }

        $score = max(0, min(100, (int) $score));

        return [
            'score' => $score,
            'status' => $score >= 80 ? 'Ổn định' : ($score >= 55 ? 'Cần theo dõi' : 'Rủi ro'),
            'reason' => $reasons === [] ? 'Không có vấn đề lớn' : implode(' - ', array_slice($reasons, 0, 3)),
        ];
    }

    /**
     * @param \WP_Post[] $projects
     * @return array<int,array{id:int,title:string,service:string,date_label:string,days_label:string,days_left:int}>
     */
    private function getExpiringProjects(array $projects, int $days): array
    {
        $rows = [];
        $today = new \DateTimeImmutable(current_time('Y-m-d'));

        foreach ($projects as $project) {
            foreach ([
                'Domain' => $this->meta((int) $project->ID, 'domain_expiry'),
                'Hosting' => $this->meta((int) $project->ID, 'hosting_expiry'),
            ] as $service => $date) {
                if (!$date) {
                    continue;
                }

                try {
                    $expires = new \DateTimeImmutable($date);
                } catch (\Exception) {
                    continue;
                }

                $left = (int) $today->diff($expires)->format('%r%a');
                if ($left > $days) {
                    continue;
                }

                $rows[] = [
                    'id' => (int) $project->ID,
                    'title' => $project->post_title,
                    'service' => $service,
                    'date_label' => $expires->format('d/m/Y'),
                    'days_label' => $left >= 0 ? '+' . $left . ' ngày' : $left . ' ngày',
                    'days_left' => $left,
                ];
            }
        }

        usort($rows, static fn(array $a, array $b): int => $a['days_left'] <=> $b['days_left']);

        return $rows;
    }

    /**
     * @return array{label:string,days_left:int}|null
     */
    private function getNearestExpiry(int $projectId): ?array
    {
        $today = new \DateTimeImmutable(current_time('Y-m-d'));
        $nearest = null;

        foreach ([
            'Domain' => $this->meta($projectId, 'domain_expiry'),
            'Hosting' => $this->meta($projectId, 'hosting_expiry'),
        ] as $service => $date) {
            if ($date === '') {
                continue;
            }

            try {
                $expires = new \DateTimeImmutable($date);
            } catch (\Exception) {
                continue;
            }

            $left = (int) $today->diff($expires)->format('%r%a');
            if ($nearest === null || $left < $nearest['days_left']) {
                $nearest = [
                    'label' => sprintf('%s %s (%s)', $service, $expires->format('d/m/Y'), $left >= 0 ? '+' . $left . ' ngày' : $left . ' ngày'),
                    'days_left' => $left,
                ];
            }
        }

        return $nearest;
    }

    private function meta(int $projectId, string $key): string
    {
        return (string) get_post_meta($projectId, '_' . $key, true);
    }

    private function formatDate(string $date): string
    {
        if ($date === '') {
            return '—';
        }

        $timestamp = strtotime($date);
        return $timestamp ? date_i18n('d/m/Y', $timestamp) : '—';
    }

    private function formatDateTime(string $date): string
    {
        if ($date === '') {
            return '—';
        }

        $timestamp = strtotime($date);
        return $timestamp ? date_i18n('d/m/Y H:i', $timestamp) : '—';
    }

    private function getTrackerLastSeenLabel(int $projectId): string
    {
        $lastSeen = (string) get_post_meta($projectId, '_tracker_last_seen_at', true);
        if ($lastSeen === '') {
            return 'Chưa có heartbeat';
        }

        $timestamp = strtotime($lastSeen);
        if (!$timestamp) {
            return 'Dữ liệu không hợp lệ';
        }

        return sprintf('%s trước', human_time_diff($timestamp, current_time('timestamp')));
    }

    private function getTrackerAgeDays(int $projectId): ?int
    {
        $lastSeen = (string) get_post_meta($projectId, '_tracker_last_seen_at', true);
        if ($lastSeen === '') {
            return null;
        }

        $timestamp = strtotime($lastSeen);
        if (!$timestamp) {
            return null;
        }

        return (int) floor(max(0, current_time('timestamp') - $timestamp) / DAY_IN_SECONDS);
    }

    private function moneyToInt(string $value): int
    {
        return (int) preg_replace('/[^0-9]/', '', $value);
    }

    private function formatMoney(int $amount): string
    {
        return $amount > 0 ? number_format($amount, 0, ',', '.') . ' đ' : '—';
    }

    /**
     * @param array<string,mixed> $log
     */
    private function formatLogDate(array $log): string
    {
        $raw = (string) ($log['created_at'] ?? $log['log_date'] ?? '');
        $timestamp = strtotime($raw);

        return $timestamp ? date_i18n('d/m/Y H:i', $timestamp) : '—';
    }

    private function logTypeLabel(string $type): string
    {
        return [
            'note' => 'Ghi chú',
            'plugin_update' => 'Cập nhật plugin',
            'plugin_activate' => 'Kích hoạt plugin',
            'plugin_deactivate' => 'Tắt plugin',
            'core_update' => 'Cập nhật WordPress',
            'theme_switch' => 'Đổi theme',
            'file_changed' => 'Thay đổi file',
            'deployment' => 'Deploy',
            'bug_fix' => 'Sửa lỗi',
            'security' => 'Bảo mật',
            'client_request' => 'Yêu cầu khách hàng',
            'maintenance_summary' => 'Báo cáo bảo trì',
            'task_done' => 'Hoàn thành task',
        ][$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    private function getPaidAmount(int $projectId): int
    {
        $total = 0;
        for ($i = 0; $i < 100; $i++) {
            // Carbon Fields lưu key đầy đủ dạng root|field|hàng|value_index|property
            $metaKey = "_payment_history|pay_amount|{$i}|0|value";
            if (!metadata_exists('post', $projectId, $metaKey)) {
                break;
            }
            $total += $this->moneyToInt((string) get_post_meta($projectId, $metaKey, true));
        }

        return $total;
    }

    /**
     * @param \WP_Post[] $projects
     */
    private function getPaidAmountForMonth(array $projects, string $month): int
    {
        $total = 0;

        foreach ($projects as $project) {
            $projectId = (int) $project->ID;
            for ($i = 0; $i < 100; $i++) {
                $amountKey = "_payment_history|pay_amount|{$i}|0|value";
                $dateKey = "_payment_history|pay_date|{$i}|0|value";
                if (!metadata_exists('post', $projectId, $amountKey)) {
                    break;
                }

                $date = (string) get_post_meta($projectId, $dateKey, true);
                if ($date !== '' && str_starts_with($date, $month)) {
                    $total += $this->moneyToInt((string) get_post_meta($projectId, $amountKey, true));
                }
            }
        }

        return $total;
    }

    private function statusLabel(string $status): string
    {
        return [
            'pending' => 'Chờ làm',
            'in_progress' => 'Đang làm',
            'done' => 'Đã xong',
            'maintenance' => 'Đang bảo trì',
            'paused' => 'Tạm dừng',
        ][$status] ?? $status;
    }

    /**
     * @param \WP_Post[] $projects
     * @return array<string,int>
     */
    private function getStatusBreakdown(array $projects): array
    {
        $counts = [];

        foreach ($projects as $project) {
            $status = $this->statusLabel($this->meta((int) $project->ID, 'project_status') ?: 'pending');
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }

        return $counts;
    }

    private function paymentLabel(string $status): string
    {
        return [
            'pending' => 'Chưa thanh toán',
            'partial' => 'Đã thanh toán một phần',
            'paid' => 'Đã thanh toán đủ',
            'overdue' => 'Quá hạn',
        ][$status] ?? $status;
    }

    private function getClientPortalPageUrl(): string
    {
        $pages = get_posts([
            'post_type' => 'page',
            'posts_per_page' => 1,
            'meta_key' => '_wp_page_template',
            'meta_value' => 'page_templates/template-client-portal.php',
            'post_status' => 'publish',
        ]);

        return $pages ? (string) get_permalink($pages[0]->ID) : '';
    }

    private function isHubRequest(): bool
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->post_type === self::POST_TYPE) {
            return true;
        }

        $page = sanitize_key(wp_unslash($_GET['page'] ?? ''));
        return in_array($page, [
            self::MENU_SLUG,
            'laca-project-notifications-center',
            'laca-project-actions',
            'laca-project-pipeline',
            'laca-project-clients',
            'laca-project-issues',
            'laca-project-updates',
            'laca-project-fleet',
            'laca-project-renewals',
            'laca-project-operations',
            'laca-project-health',
            'laca-project-finance',
            'laca-project-reports',
            'laca-project-portal',
            'laca-global-alerts',
        ], true);
    }

    private function getCurrentNavKey(): string
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && $screen->post_type === self::POST_TYPE) {
            if ($screen->base === 'post' && ($screen->action ?? '') === 'add') {
                return 'new-project';
            }
            return 'projects';
        }

        return match (sanitize_key(wp_unslash($_GET['page'] ?? ''))) {
            self::MENU_SLUG => 'dashboard',
            'laca-project-notifications-center' => 'notifications',
            'laca-project-actions' => 'actions',
            'laca-project-pipeline' => 'pipeline',
            'laca-project-clients' => 'clients',
            'laca-project-issues' => 'issues',
            'laca-project-updates' => 'updates',
            'laca-project-fleet' => 'fleet',
            'laca-project-renewals' => 'renewals',
            'laca-project-operations' => 'operations',
            'laca-project-health' => 'health',
            'laca-project-finance' => 'finance',
            'laca-project-reports' => 'reports',
            'laca-project-portal' => 'portal',
            'laca-global-alerts' => 'alerts',
            default => '',
        };
    }
}
