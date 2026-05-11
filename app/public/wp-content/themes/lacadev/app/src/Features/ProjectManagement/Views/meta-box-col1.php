<?php
/**
 * View: Project Workspace — side column.
 *
 * Variables available from renderLogsMetaBox():
 *
 * @var int    $projectId
 * @var array  $alerts
 * @var array  $pendingTasks
 * @var array  $pendingPlugins
 * @var string $secretKey
 * @var string $portalAlias
 * @var string $endpoint
 * @var string $clientPortalUrl
 * @var string $clientPortalAliasUrl
 * @var array  $trackerHealth
 * @var array  $trackerLastSeenMeta
 */

use App\Models\ProjectAlert;

$pendingTasks = $pendingTasks ?? [];
$pendingPlugins = $pendingPlugins ?? [];
$clientPortalUrl = $clientPortalUrl ?? '';
$clientPortalAliasUrl = $clientPortalAliasUrl ?? '';
$trackerHealth = is_array($trackerHealth ?? null) ? $trackerHealth : [];
$trackerLastSeenMeta = is_array($trackerLastSeenMeta ?? null) ? $trackerLastSeenMeta : [];
?>

<div class="laca-pm-col laca-project-workspace__side">
    <section class="laca-project-panel">
        <div class="laca-project-panel__header">
            <div>
                <h3><?php echo esc_html__('Cảnh báo cần xử lý', 'laca'); ?></h3>
                <p><?php echo esc_html__('Các vấn đề còn mở từ website khách hàng hoặc tracker.', 'laca'); ?></p>
            </div>
        </div>

        <div class="laca-pm-list laca-alert-list">
            <?php if (empty($alerts)): ?>
                <p class="laca-project-state laca-project-state--ok"><?php echo esc_html__('Không có cảnh báo nào.', 'laca'); ?></p>
            <?php else: ?>
                <?php foreach ($alerts as $alert): ?>
                    <div class="laca-pm-item laca-alert-item" id="alert-<?php echo esc_attr($alert['id']); ?>">
                        <div class="laca-pm-meta">
                            <span class="laca-pm-badge laca-pm-alert-<?php echo esc_attr($alert['alert_level']); ?>">
                                <?php echo esc_html(ProjectAlert::getTypeLabel($alert['alert_type'])); ?>
                            </span>
                            <span><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($alert['created_at']))); ?></span>
                        </div>
                        <div class="laca-alert-item__message"><?php echo nl2br(esc_html($alert['alert_msg'])); ?></div>
                        <button type="button" class="button button-small laca-resolve-btn" data-id="<?php echo esc_attr($alert['id']); ?>">
                            <?php echo esc_html__('Đã xử lý', 'laca'); ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="laca-project-panel laca-project-panel--tracker-health">
        <div class="laca-project-panel__header">
            <div>
                <h3><?php echo esc_html__('Client health', 'laca'); ?></h3>
                <p><?php echo esc_html__('Heartbeat, hàng đợi gửi log và thông tin môi trường website khách.', 'laca'); ?></p>
            </div>
            <span class="laca-project-health-pill laca-project-health-pill--<?php echo esc_attr((string) ($trackerHealth['tone'] ?? 'warning')); ?>">
                <?php echo esc_html((string) ($trackerHealth['headline'] ?? __('Chưa có dữ liệu', 'laca'))); ?>
            </span>
        </div>

        <dl class="laca-project-health-list">
            <div>
                <dt><?php echo esc_html__('Heartbeat gần nhất', 'laca'); ?></dt>
                <dd><?php echo esc_html((string) ($trackerHealth['note'] ?? __('Chưa nhận heartbeat', 'laca'))); ?></dd>
            </div>
            <div>
                <dt><?php echo esc_html__('WordPress / PHP', 'laca'); ?></dt>
                <dd>
                    <?php
                    $wpVersion = (string) ($trackerLastSeenMeta['wp_version'] ?? '—');
                    $phpVersion = (string) ($trackerLastSeenMeta['php_version'] ?? '—');
                    echo esc_html($wpVersion . ' / ' . $phpVersion);
                    ?>
                </dd>
            </div>
            <div>
                <dt><?php echo esc_html__('Theme', 'laca'); ?></dt>
                <dd><?php echo esc_html((string) ($trackerLastSeenMeta['theme'] ?? '—')); ?></dd>
            </div>
        </dl>
    </section>

    <?php if (!empty($pendingTasks) || !empty($pendingPlugins)): ?>
        <section class="laca-project-panel laca-project-panel--pending" id="laca-pending-tasks-block">
            <div class="laca-project-panel__header">
                <div>
                    <h3><?php echo esc_html__('Việc chưa hoàn thành', 'laca'); ?></h3>
                    <p><?php echo esc_html__('Những mục cần xử lý trước khi báo cáo hoặc bàn giao.', 'laca'); ?></p>
                </div>
            </div>

            <?php if (!empty($pendingTasks)): ?>
                <ul class="laca-pending-list" id="laca-pending-list">
                    <?php foreach ($pendingTasks as $pendingTask): ?>
                        <li><?php echo esc_html($pendingTask['name'] ?? ''); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if (!empty($pendingPlugins)): ?>
                <div class="laca-pending-plugins">
                    <strong><?php echo esc_html__('Plugin cần cập nhật', 'laca'); ?></strong>
                    <ul>
                        <?php foreach ($pendingPlugins as $pendingPlugin): ?>
                            <li>
                                <?php echo esc_html($pendingPlugin['name'] ?? $pendingPlugin['slug'] ?? ''); ?>
                                <?php if (!empty($pendingPlugin['current_version']) && !empty($pendingPlugin['new_version'])): ?>
                                    <span><?php echo esc_html($pendingPlugin['current_version']); ?> → <?php echo esc_html($pendingPlugin['new_version']); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="laca-project-panel">
        <div class="laca-project-panel__header">
            <div>
                <h3><?php echo esc_html__('Client Portal', 'laca'); ?></h3>
                <p><?php echo esc_html__('Link theo dõi tiến độ, bảo trì và cập nhật dành cho khách hàng.', 'laca'); ?></p>
            </div>
        </div>

        <?php if ($clientPortalUrl): ?>
            <?php $displayPortalUrl = $clientPortalAliasUrl ?: $clientPortalUrl; ?>
            <div class="laca-form-group laca-copyable-input">
                <label for="client_portal_url2"><?php echo esc_html__('Link portal', 'laca'); ?></label>
                <input type="text" readonly value="<?php echo esc_url($displayPortalUrl); ?>" id="client_portal_url2">
            </div>
            <div class="laca-project-actions">
                <a href="<?php echo esc_url($displayPortalUrl); ?>" target="_blank" rel="noopener" class="button">
                    <?php echo esc_html__('Xem portal', 'laca'); ?>
                </a>
            </div>
        <?php else: ?>
            <p class="laca-project-state laca-project-state--warning"><?php echo esc_html__('Client Portal chưa cấu hình. Tạo một page dùng template Client Portal để gửi link cho khách.', 'laca'); ?></p>
        <?php endif; ?>
    </section>

    <section class="laca-project-panel">
        <div class="laca-project-panel__header">
            <div>
                <h3><?php echo esc_html__('Auto Activity Tracker', 'laca'); ?></h3>
                <p><?php echo esc_html__('Cài MU-plugin vào website khách để tự động gửi log, lỗi và cập nhật.', 'laca'); ?></p>
            </div>
        </div>

        <div class="laca-form-group laca-copyable-input">
            <label><?php echo esc_html__('Endpoint URL', 'laca'); ?></label>
            <input type="text" readonly value="<?php echo esc_url($endpoint); ?>">
        </div>
        <div class="laca-form-group laca-copyable-input">
            <label><?php echo esc_html__('Secret Key', 'laca'); ?></label>
            <input type="text" readonly value="<?php echo esc_attr($secretKey); ?>">
        </div>
        <div class="laca-project-actions">
            <button type="button" class="button button-primary" id="btn_download_tracker"><?php echo esc_html__('Tải MU-plugin', 'laca'); ?></button>
            <button type="button" class="button" id="btn_view_tracker_code"><?php echo esc_html__('Xem code PHP', 'laca'); ?></button>
        </div>
    </section>

    <section class="laca-project-panel laca-project-panel--remote">
        <div class="laca-project-panel__header">
            <div>
                <h3><?php echo esc_html__('Cập nhật từ xa', 'laca'); ?></h3>
                <p><?php echo esc_html__('Kiểm tra plugin chờ cập nhật hoặc gửi lệnh update đến website khách.', 'laca'); ?></p>
            </div>
            <button type="button" class="button button-secondary" id="btn_load_pending">
                <?php echo esc_html__('Tải plugin chờ update', 'laca'); ?>
            </button>
        </div>

        <div id="pending_plugins_list" class="laca-pending-table">
            <table>
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Plugin', 'laca'); ?></th>
                        <th><?php echo esc_html__('Hiện tại', 'laca'); ?></th>
                        <th><?php echo esc_html__('Bản mới', 'laca'); ?></th>
                        <th><?php echo esc_html__('Hành động', 'laca'); ?></th>
                    </tr>
                </thead>
                <tbody id="pending_plugins_tbody">
                    <tr><td colspan="4"><?php echo esc_html__('Chưa có dữ liệu', 'laca'); ?></td></tr>
                </tbody>
            </table>
        </div>
        <div id="pending_plugins_empty" class="laca-project-state laca-project-state--ok">
            <?php echo esc_html__('Không có plugin nào cần cập nhật.', 'laca'); ?>
        </div>

        <details class="laca-project-details">
            <summary><?php echo esc_html__('Gửi lệnh thủ công', 'laca'); ?></summary>
            <div class="laca-remote-form">
                <select id="remote_update_action" aria-label="<?php echo esc_attr__('Loại cập nhật', 'laca'); ?>">
                    <option value="update_plugin"><?php echo esc_html__('Cập nhật Plugin', 'laca'); ?></option>
                    <option value="update_theme"><?php echo esc_html__('Cập nhật Theme', 'laca'); ?></option>
                    <option value="update_core"><?php echo esc_html__('Cập nhật WordPress Core', 'laca'); ?></option>
                </select>
                <input type="text" id="remote_update_slug" placeholder="<?php echo esc_attr__('slug, ví dụ: woocommerce/woocommerce.php', 'laca'); ?>">
                <button type="button" class="button button-primary" id="btn_remote_update">
                    <?php echo esc_html__('Gửi lệnh', 'laca'); ?>
                </button>
            </div>
            <p class="laca-project-help"><?php echo wp_kses_post(__('Với <code>update_core</code>, bỏ trống slug. Plugin slug dạng <code>folder/file.php</code>, theme slug dạng <code>folder-name</code>.', 'laca')); ?></p>
        </details>
        <div id="remote_update_msg" class="laca-project-message"></div>
    </section>
</div>
