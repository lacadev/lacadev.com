<?php

namespace App\PostTypes;

use App\Models\ProjectLog;
use App\Models\ProjectAlert;
use App\PostTypes\Concerns\BlockSyncSender;
use App\PostTypes\Concerns\HasEncryption;
use App\PostTypes\Concerns\HasBrandColors;
use App\PostTypes\Concerns\HasCurrencyFormat;
use App\PostTypes\Concerns\HasPortalAlias;
use App\Features\ProjectManagement\Ajax\LogAjaxHandler;
use App\Features\ProjectManagement\Ajax\TaskAjaxHandler;
use App\Features\ProjectManagement\Ajax\RemoteAjaxHandler;
use App\Features\ProjectManagement\ProjectAdminColumns;
use App\Features\ProjectManagement\LacaProjectsHub;
use App\Features\ProjectManagement\ProjectPaymentService;

class Project extends \App\Abstracts\AbstractPostType
{
    use BlockSyncSender;
    use HasEncryption;
    use HasBrandColors;
    use HasCurrencyFormat;
    use HasPortalAlias;

    public function __construct()
    {
        $this->showThumbnailOnList = true;
        $this->supports            = ['title', 'thumbnail'];
        $this->menuIcon            = 'dashicons-portfolio';
        $this->post_type           = 'project';
        $this->singularName        = __('Laca Project', 'laca');
        $this->pluralName          = __('Laca Projects', 'laca');
        $this->showInMenu          = class_exists(LacaProjectsHub::class) ? LacaProjectsHub::MENU_SLUG : true;
        $this->titlePlaceHolder    = __('Tên dự án / website', 'laca');
        $this->slug                = 'projects';
        parent::__construct();

        // Admin list columns
        (new ProjectAdminColumns())->register();

        // AJAX handlers
        new LogAjaxHandler();
        new TaskAjaxHandler();
        new RemoteAjaxHandler();

        // Meta box Project Workspace
        add_action('add_meta_boxes', [$this, 'registerLogsMetaBox']);

        // Carbon Fields: mã hóa mật khẩu
        add_filter('carbon_fields_post_meta_value_save', [$this, 'encryptPasswordsOnSave'], 10, 4);
        add_filter('carbon_fields_post_meta_value_load', [$this, 'decryptPasswordsOnLoad'], 10, 4);

        // Carbon Fields: format tiền tệ
        add_filter('carbon_fields_post_meta_value_save', [$this, 'formatCurrencyOnSave'], 11, 4);
        add_filter('carbon_fields_post_meta_value_load', [$this, 'formatCurrencyOnLoad'], 11, 4);
        add_action('admin_footer', [$this, 'addCurrencyFormatterScript']);

        // Carbon Fields: chuẩn hoá HEX màu thương hiệu
        add_filter('carbon_fields_post_meta_value_save', [$this, 'normalizeBrandColorsOnSave'], 12, 4);
        add_filter('carbon_fields_post_meta_value_load', [$this, 'normalizeBrandColorsOnLoad'], 12, 4);

        // Payment status tự động (priority 9999: sau khi CF save xong)
        (new ProjectPaymentService())->register();

        // Portal alias
        add_action('save_post_project', [$this, 'savePortalAlias'], 10, 1);

        // Block Sync
        $this->registerBlockSyncHooks();
    }

    // =========================================================================
    // CARBON FIELDS META BOXES
    // =========================================================================

    public function metaFields(): void
    {
        (new \App\Features\ProjectManagement\ProjectFields($this->post_type))->register();
    }

    // =========================================================================
    // NATIVE META BOX (LOGS & ALERTS)
    // =========================================================================

    public function registerLogsMetaBox(): void
    {
        add_meta_box(
            'laca_project_logs_alerts',
            __('Project Workspace', 'laca'),
            [$this, 'renderLogsMetaBox'],
            'project',
            'normal',
            'high'
        );
    }

    public function renderLogsMetaBox(\WP_Post $post): void
    {
        if (!class_exists(\App\Models\ProjectLog::class) || !class_exists(\App\Models\ProjectAlert::class)) {
            echo '<p>Các bảng DB chưa được tạo. Vui lòng kích hoạt lại theme.</p>';
            return;
        }

        $projectId   = $post->ID;
        $logs        = ProjectLog::getByProject($projectId);
        $alerts      = ProjectAlert::getActive($projectId);
        $tasks       = \App\Features\ProjectManagement\Ajax\TaskAjaxHandler::getTaskList($projectId);
        $totalTask   = count($tasks);
        $doneTask    = count(array_filter($tasks, fn($task) => $task['done'] ?? false));
        $progress    = $totalTask > 0 ? (int) round($doneTask / $totalTask * 100) : 0;

        $pendingTasks   = array_values(array_filter($tasks, fn($task) => !($task['done'] ?? false)));
        $pendingPlugins = get_post_meta($projectId, '_pending_plugin_updates', true) ?: [];
        if (!is_array($pendingPlugins)) {
            $pendingPlugins = [];
        }

        $secretKey = get_post_meta($projectId, '_tracker_secret_key', true);
        if (empty($secretKey)) {
            $secretKey = wp_generate_password(24, false);
            update_post_meta($projectId, '_tracker_secret_key', $secretKey);
        }

        $portalAlias = get_post_meta($projectId, '_portal_alias', true);
        $endpoint    = rest_url('laca/v1/tracker/log');
        $portalPages = get_posts([
            'post_type'      => 'page',
            'posts_per_page' => 1,
            'meta_key'       => '_wp_page_template',
            'meta_value'     => 'page_templates/template-client-portal.php',
            'post_status'    => 'publish',
        ]);
        $portalUrl = !empty($portalPages) ? get_permalink($portalPages[0]->ID) : '';
        $clientPortalUrl = $portalUrl ? add_query_arg('key', $secretKey, $portalUrl) : '';
        $clientPortalAliasUrl = ($portalUrl && $portalAlias) ? add_query_arg('key', $portalAlias, $portalUrl) : '';
        $latestLog = !empty($logs) ? $logs[0] : null;
        $trackerLastSeenAt = (string) get_post_meta($projectId, '_tracker_last_seen_at', true);
        $trackerLastSeenMeta = get_post_meta($projectId, '_tracker_last_seen_meta', true);
        $trackerLastSeenMeta = is_array($trackerLastSeenMeta) ? $trackerLastSeenMeta : [];
        $trackerHealth = $this->getTrackerHealthSummary($trackerLastSeenAt, $trackerLastSeenMeta);
        $workspaceStatus = !empty($alerts) ? __('Cần xử lý', 'laca') : $trackerHealth['status'];

        wp_nonce_field('laca_project_manager', 'laca_pm_nonce');
        ?>
        <div class="laca-pm-wrap laca-logs-container laca-project-workspace" data-project-id="<?php echo esc_attr($projectId); ?>">
            <div class="laca-project-workspace__header">
                <div>
                    <h2><?php echo esc_html__('Workspace dự án', 'laca'); ?></h2>
                    <p><?php echo esc_html__('Theo dõi tiến độ, cảnh báo, lịch sử bảo trì và các thao tác từ xa của website khách hàng.', 'laca'); ?></p>
                </div>
                <span class="laca-project-workspace__status">
                    <?php echo esc_html($workspaceStatus); ?>
                </span>
            </div>

            <div class="laca-project-summary-grid" aria-label="<?php echo esc_attr__('Tổng quan nhanh dự án', 'laca'); ?>">
                <div class="laca-project-summary-card">
                    <span class="laca-project-summary-card__label"><?php echo esc_html__('Cảnh báo', 'laca'); ?></span>
                    <strong><?php echo esc_html((string) count($alerts)); ?></strong>
                    <small><?php echo empty($alerts) ? esc_html__('Không có cảnh báo mở', 'laca') : esc_html__('Đang chờ xử lý', 'laca'); ?></small>
                </div>
                <div class="laca-project-summary-card">
                    <span class="laca-project-summary-card__label"><?php echo esc_html__('Tiến độ task', 'laca'); ?></span>
                    <strong><?php echo esc_html($progress . '%'); ?></strong>
                    <small><?php echo esc_html(sprintf('%d/%d task hoàn thành', $doneTask, $totalTask)); ?></small>
                </div>
                <div class="laca-project-summary-card">
                    <span class="laca-project-summary-card__label"><?php echo esc_html__('Portal khách hàng', 'laca'); ?></span>
                    <strong><?php echo $clientPortalUrl ? esc_html__('Sẵn sàng', 'laca') : esc_html__('Chưa cấu hình', 'laca'); ?></strong>
                    <small><?php echo $clientPortalAliasUrl ? esc_html__('Đang dùng alias thân thiện', 'laca') : esc_html__('Dùng secret key mặc định', 'laca'); ?></small>
                </div>
                <div class="laca-project-summary-card">
                    <span class="laca-project-summary-card__label"><?php echo esc_html__('Nhật ký mới nhất', 'laca'); ?></span>
                    <strong>
                        <?php echo $latestLog ? esc_html(date_i18n('d/m/Y', strtotime($latestLog['log_date']))) : esc_html__('Chưa có', 'laca'); ?>
                    </strong>
                    <small>
                        <?php echo $latestLog ? esc_html(ProjectLog::getTypeLabel($latestLog['log_type'])) : esc_html__('Chưa nhận log từ tracker', 'laca'); ?>
                    </small>
                </div>
                <div class="laca-project-summary-card laca-project-summary-card--tracker">
                    <span class="laca-project-summary-card__label"><?php echo esc_html__('Tracker heartbeat', 'laca'); ?></span>
                    <strong><?php echo esc_html($trackerHealth['headline']); ?></strong>
                    <small><?php echo esc_html($trackerHealth['note']); ?></small>
                </div>
            </div>

            <div class="laca-project-workspace__grid">
                <?php include __DIR__ . '/../Features/ProjectManagement/Views/meta-box-col2.php'; ?>
                <?php include __DIR__ . '/../Features/ProjectManagement/Views/meta-box-col1.php'; ?>
            </div>
        </div>
        <?php
    }

    /**
     * @param array<string,mixed> $meta
     * @return array{status:string,headline:string,note:string,tone:string}
     */
    private function getTrackerHealthSummary(string $lastSeenAt, array $meta): array
    {
        if ($lastSeenAt === '') {
            return [
                'status' => __('Chưa có heartbeat', 'laca'),
                'headline' => __('Chưa kết nối', 'laca'),
                'note' => __('Chưa nhận heartbeat từ website khách hàng.', 'laca'),
                'tone' => 'warning',
            ];
        }

        $timestamp = strtotime($lastSeenAt);
        if (!$timestamp) {
            return [
                'status' => __('Cần kiểm tra', 'laca'),
                'headline' => __('Dữ liệu lỗi', 'laca'),
                'note' => __('Thời gian heartbeat không hợp lệ.', 'laca'),
                'tone' => 'warning',
            ];
        }

        $ageSeconds = max(0, current_time('timestamp') - $timestamp);
        $ageLabel = sprintf(__('%s trước', 'laca'), human_time_diff($timestamp, current_time('timestamp')));
        $failed = (int) ($meta['tracker_health']['failed'] ?? 0);
        $retry = (int) ($meta['tracker_health']['retry'] ?? 0);

        if ($ageSeconds > 7 * DAY_IN_SECONDS) {
            $tone = 'danger';
            $status = __('Tracker im lặng', 'laca');
            $headline = __('Mất kết nối', 'laca');
        } elseif ($ageSeconds > 2 * DAY_IN_SECONDS || $failed > 0) {
            $tone = 'warning';
            $status = __('Cần kiểm tra', 'laca');
            $headline = __('Có rủi ro', 'laca');
        } else {
            $tone = 'ok';
            $status = __('Ổn định', 'laca');
            $headline = __('Online', 'laca');
        }

        $note = $ageLabel;
        if ($failed > 0 || $retry > 0) {
            $note .= sprintf(__(' · %d lỗi, %d retry', 'laca'), $failed, $retry);
        }

        return compact('status', 'headline', 'note', 'tone');
    }
}
