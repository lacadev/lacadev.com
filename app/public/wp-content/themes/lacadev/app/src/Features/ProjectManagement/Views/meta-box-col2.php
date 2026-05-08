<?php
/**
 * View: Project Workspace — main column.
 *
 * Variables available from renderLogsMetaBox():
 *
 * @var int   $projectId
 * @var array $logs
 * @var array $tasks
 * @var int   $totalTask
 * @var int   $doneTask
 * @var int   $progress
 */

use App\Models\ProjectLog;

if (!isset($tasks)) {
    $tasks = \App\Features\ProjectManagement\Ajax\TaskAjaxHandler::getTaskList($projectId);
}

$totalTask = $totalTask ?? count($tasks);
$doneTask  = $doneTask ?? count(array_filter($tasks, fn($task) => $task['done'] ?? false));
$progress  = $progress ?? ($totalTask > 0 ? (int) round($doneTask / $totalTask * 100) : 0);
$catLabels = [
    'bug'     => __('Bug / Lỗi', 'laca'),
    'page'    => __('Trang giao diện', 'laca'),
    'content' => __('Nội dung', 'laca'),
    'seo'     => __('SEO', 'laca'),
    'feature' => __('Tính năng', 'laca'),
    'other'   => __('Khác', 'laca'),
];
?>

<div class="laca-pm-col laca-project-workspace__main">
    <section class="laca-project-panel laca-project-panel--tasks">
        <div class="laca-project-panel__header">
            <div>
                <h3><?php echo esc_html__('Task & tiến độ', 'laca'); ?></h3>
                <p><?php echo esc_html__('Checklist nội bộ để quản lý phạm vi, lỗi và việc cần bàn giao.', 'laca'); ?></p>
            </div>
            <button type="button" class="button" id="btn_sync_pages" title="<?php echo esc_attr__('Import trang từ tab Design Scope', 'laca'); ?>">
                <?php echo esc_html__('Sync trang', 'laca'); ?>
            </button>
        </div>

        <div class="laca-project-progress" style="<?php echo esc_attr('--laca-progress:' . $progress . '%;'); ?>">
            <div class="laca-project-progress__meta">
                <span id="task_progress_label"><?php echo esc_html(sprintf('%d/%d task hoàn thành', $doneTask, $totalTask)); ?></span>
                <strong id="task_progress_pct"><?php echo esc_html($progress . '%'); ?></strong>
            </div>
            <div class="laca-project-progress__track">
                <div id="task_progress_bar" class="laca-project-progress__bar"></div>
            </div>
        </div>

        <div class="laca-pm-list laca-task-list" id="task_list_container">
            <?php if (empty($tasks)): ?>
                <p class="laca-project-empty"><?php echo esc_html__('Chưa có task nào. Sync trang từ Design Scope hoặc thêm task thủ công bên dưới.', 'laca'); ?></p>
            <?php else: ?>
                <?php foreach ($tasks as $task): ?>
                    <?php
                    $cat = $task['category'] ?? (($task['source'] ?? '') === 'page' ? 'page' : 'other');
                    ?>
                    <div class="laca-task-item <?php echo !empty($task['done']) ? 'task-done' : ''; ?>" data-id="<?php echo esc_attr($task['id']); ?>">
                        <input
                            type="checkbox"
                            class="task-checkbox"
                            data-id="<?php echo esc_attr($task['id']); ?>"
                            aria-label="<?php echo esc_attr(sprintf(__('Đánh dấu hoàn thành: %s', 'laca'), $task['name'] ?? '')); ?>"
                            <?php checked(!empty($task['done'])); ?>
                        >
                        <div class="laca-task-item__content">
                            <span class="task-name"><?php echo esc_html($task['name'] ?? ''); ?></span>
                            <span class="laca-task-item__meta"><?php echo esc_html($catLabels[$cat] ?? $catLabels['other']); ?></span>
                            <?php if (!empty($task['demo_url'])): ?>
                                <a href="<?php echo esc_url($task['demo_url']); ?>" target="_blank" rel="noopener" class="laca-task-item__link">
                                    <?php echo esc_html__('Mẫu giao diện', 'laca'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        <a class="task-delete-btn" data-id="<?php echo esc_attr($task['id']); ?>" title="<?php echo esc_attr__('Xoá task', 'laca'); ?>" aria-label="<?php echo esc_attr__('Xoá task', 'laca'); ?>">×</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="laca-task-add">
            <select id="new_task_category" aria-label="<?php echo esc_attr__('Loại task', 'laca'); ?>">
                <option value="bug"><?php echo esc_html__('Bug / Lỗi', 'laca'); ?></option>
                <option value="page"><?php echo esc_html__('Trang giao diện', 'laca'); ?></option>
                <option value="content"><?php echo esc_html__('Nội dung', 'laca'); ?></option>
                <option value="seo"><?php echo esc_html__('SEO', 'laca'); ?></option>
                <option value="feature"><?php echo esc_html__('Tính năng', 'laca'); ?></option>
                <option value="other" selected><?php echo esc_html__('Khác', 'laca'); ?></option>
            </select>
            <input type="text" id="new_task_name" placeholder="<?php echo esc_attr__('Tên task, ví dụ: Fix menu mobile...', 'laca'); ?>">
            <button type="button" class="button button-primary" id="btn_add_task"><?php echo esc_html__('Thêm', 'laca'); ?></button>
        </div>
    </section>

    <section class="laca-project-panel laca-project-panel--logs">
        <div class="laca-project-panel__header">
            <div>
                <h3><?php echo esc_html__('Nhật ký cập nhật & bảo trì', 'laca'); ?></h3>
                <p><?php echo esc_html__('Dòng thời gian các thay đổi từ tracker, task và ghi chú vận hành.', 'laca'); ?></p>
            </div>
        </div>

        <div class="laca-pm-list laca-log-list" id="laca-log-list">
            <?php if (empty($logs)): ?>
                <p class="laca-project-empty"><?php echo esc_html__('Chưa có nhật ký nào.', 'laca'); ?></p>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <div class="laca-pm-item laca-log-item" id="log-<?php echo esc_attr($log['id']); ?>">
                        <div class="laca-pm-meta">
                            <span class="laca-log-item__type">
                                <?php echo esc_html(ProjectLog::getTypeLabel($log['log_type'])); ?>
                                <?php if (!empty($log['is_auto'])): ?>
                                    <span class="laca-project-chip"><?php echo esc_html__('Auto', 'laca'); ?></span>
                                <?php endif; ?>
                            </span>
                            <span><?php echo esc_html(date_i18n('d/m/Y', strtotime($log['log_date']))); ?> <?php echo esc_html__('bởi', 'laca'); ?> <?php echo esc_html($log['log_by']); ?></span>
                        </div>
                        <div class="laca-log-item__content"><?php echo nl2br(esc_html($log['log_content'])); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>
