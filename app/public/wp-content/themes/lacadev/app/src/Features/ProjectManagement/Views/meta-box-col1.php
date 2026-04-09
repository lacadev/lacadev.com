<?php
/**
 * View: Project Meta Box — Cột 1 (Alerts + Tracker + Client Portal)
 *
 * Variables available (từ renderLogsMetaBox()):
 *
 * @var int           $projectId
 * @var array         $alerts
 * @var string        $secretKey
 * @var string        $portalAlias
 * @var string        $endpoint
 */

use App\Models\ProjectAlert;
use App\Models\ProjectLog;
?>
<!-- ALERTS & TRACKER SECTION -->
<div class="laca-pm-col" style="display:flex; flex-direction:column; gap:20px;">
    <div>
        <h3 style="margin-top:0">Cảnh báo đang hoạt động</h3>
        <div class="laca-pm-list">
            <?php if (empty($alerts)): ?>
                <p style="color:#2e7d52; font-weight:600;">Không có cảnh báo nào.</p>
            <?php else: ?>
                <?php foreach ($alerts as $a): ?>
                    <div class="laca-pm-item" id="alert-<?php echo $a['id']; ?>">
                        <div class="laca-pm-meta">
                            <span class="laca-pm-badge laca-pm-alert-<?php echo esc_attr($a['alert_level']); ?>">
                                <?php echo ProjectAlert::getTypeLabel($a['alert_type']); ?>
                            </span>
                            <span><?php echo date('d/m/y H:i', strtotime($a['created_at'])); ?></span>
                        </div>
                        <div style="margin: 6px 0;"><?php echo nl2br(esc_html($a['alert_msg'])); ?></div>
                        <div style="text-align: right;">
                            <a class="laca-resolve-btn" data-id="<?php echo $a['id']; ?>">✓ Đánh dấu đã xử lý</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- LOGS SECTION -->
    <hr style="border:0; border-top:1px dashed #ddd; margin:18px 0 14px;">
    <h3 style="margin-top:0">Lịch sử & Nhật ký</h3>

    <?php
    // Block: Việc chưa hoàn thành
    $tasks          = \App\Features\ProjectManagement\Ajax\TaskAjaxHandler::getTaskList($projectId);
    $pendingTasks   = array_values(array_filter($tasks, fn($t) => !($t['done'] ?? false)));
    $pendingPlugins = get_post_meta($projectId, '_pending_plugin_updates', true) ?: [];
    if (!is_array($pendingPlugins)) $pendingPlugins = [];

    if (!empty($pendingTasks) || !empty($pendingPlugins)):
    ?>
    <div id="laca-pending-tasks-block" style="margin-bottom:16px; padding:10px 14px; background:#fff8e1; border-left:3px solid #f5a623; border-radius:4px;">
        <strong style="font-size:13px; color:#7c5b00;">Việc chưa hoàn thành</strong>

        <?php if (!empty($pendingTasks)): ?>
        <ul style="margin:8px 0 0 0; padding-left:16px; font-size:13px; color:#444;">
            <?php foreach ($pendingTasks as $pt): ?>
            <li style="margin-bottom:3px;"><?php echo esc_html($pt['name']); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <?php if (!empty($pendingPlugins)): ?>
        <div style="margin-top:<?php echo !empty($pendingTasks) ? '10px' : '6px'; ?>;">
            <strong style="font-size:12px; color:#7c5b00;">Plugin cần cập nhật:</strong>
            <ul style="margin:4px 0 0 0; padding-left:16px; font-size:12px; color:#555;">
                <?php foreach ($pendingPlugins as $pp): ?>
                <li style="margin-bottom:2px;">
                    <?php echo esc_html($pp['name'] ?? $pp['slug'] ?? ''); ?>
                    <?php if (!empty($pp['current_version']) && !empty($pp['new_version'])): ?>
                    <span style="color:#999;">(<?php echo esc_html($pp['current_version']); ?> → <strong><?php echo esc_html($pp['new_version']); ?></strong>)</span>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="laca-pm-list" id="laca-log-list">
        <?php if (empty($logs)): ?>
            <p style="color:#888;">Chưa có nhật ký nào.</p>
        <?php else: ?>
            <?php foreach ($logs as $l): ?>
                <div class="laca-pm-item" id="log-<?php echo $l['id']; ?>">
                    <div class="laca-pm-meta">
                        <span style="font-weight:600;color:#0073aa;">
                            <?php echo ProjectLog::getTypeLabel($l['log_type']); ?>
                            <?php if ($l['is_auto']) echo '<span style="color:#e67e22;font-size:10px;">(Auto)</span>'; ?>
                        </span>
                        <span>
                            <?php echo date('d/m/y', strtotime($l['log_date'])); ?>
                            bởi <?php echo esc_html($l['log_by']); ?>
                        </span>
                    </div>
                    <div style="margin: 6px 0;"><?php echo nl2br(esc_html($l['log_content'])); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
