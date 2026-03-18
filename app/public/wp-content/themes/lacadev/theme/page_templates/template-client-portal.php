<?php
/**
 * Template Name: Client Portal
 * 
 *
 * App Layout: layouts/app.php
 *
 * This is the template that is used for displaying 404 errors.
 *
 * @package WPEmergeTheme
 */

// Khởi tạo dữ liệu
$secretKey = sanitize_text_field($_GET['key'] ?? '');
$error     = '';
$project   = null;

if (!empty($secretKey)) {
    // Gọi REST nội bộ để lấy dữ liệu (dùng WP_REST_Request nội bộ để tránh HTTP roundtrip)
    $restRequest = new WP_REST_Request('GET', '/laca/v1/portal/project');
    $restRequest->set_param('key', $secretKey);
    $restResponse = rest_do_request($restRequest);

    if ($restResponse->get_status() === 200) {
        $data    = $restResponse->get_data();
        $project = $data['project'] ?? null;
    } elseif ($restResponse->get_status() === 401) {
        $error = 'Secret key không hợp lệ. Vui lòng kiểm tra lại link bạn nhận được.';
    } else {
        $error = 'Không tìm thấy dự án. Vui lòng liên hệ LacaDev.';
    }
}

// Page title
$pageTitle = $project ? esc_html($project['name']) . ' — Theo dõi tiến độ' : 'Theo dõi tiến độ dự án';

// Thêm class vào body để CSS variables hoạt động và page có background rõ ràng
add_filter('body_class', function($classes) {
    $classes[] = 'has-client-portal';
    return $classes;
});
?>

<main class="cp-main">

<?php if (!$project && !$error): ?>
    <!-- HERO: Chưa có key -->
    <div class="cp-hero">
        <div class="cp-hero__icon">🔗</div>
        <div class="cp-hero__title">Theo dõi tiến độ dự án</div>
        <div class="cp-hero__desc">
            Nhập mã theo dõi dự án của bạn để xem tiến độ, nhật ký cập nhật và thông báo từ đội ngũ LacaDev.
        </div>
        <form class="cp-key-form" method="get" action="">
            <input type="text" name="key" placeholder="Nhập mã theo dõi dự án..." required autocomplete="off">
            <button type="submit">Xem ngay →</button>
        </form>
    </div>

<?php elseif ($error): ?>
    <!-- ERROR -->
    <div class="cp-error">
        <span class="cp-error__icon">⚠️</span>
        <span><?php echo esc_html($error); ?></span>
    </div>
    <form class="cp-key-form cp-key-form--retry" method="get" action="">
        <input type="text" name="key" placeholder="Thử lại với mã khác..." required>
        <button type="submit">Thử lại</button>
    </form>

<?php elseif ($project): ?>
    <!-- PROJECT DATA -->

    <!-- 1. STATUS + PROGRESS CARD -->
    <div class="cp-card">
        <div class="cp-status-header">
            <div>
                <div class="cp-project-name"><?php echo esc_html($project['name']); ?></div>
                <?php if (!empty($project['domain']) || !empty($project['live_url'])): ?>
                <div class="cp-project-domain">
                    🌐
                    <?php if (!empty($project['live_url'])): ?>
                        <a href="<?php echo esc_url($project['live_url']); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html($project['domain'] ?: $project['live_url']); ?>
                        </a>
                    <?php else: ?>
                        <?php echo esc_html($project['domain']); ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <span class="cp-status-badge" data-status="<?php echo esc_attr($project['status'] ?? ''); ?>" style="background-color:<?php echo esc_attr($project['status_info']['color']); ?>">
                <?php echo $project['status_info']['icon']; ?>
                <?php echo esc_html($project['status_info']['label']); ?>
            </span>
        </div>

        <div class="cp-progress-wrap">
            <div class="cp-progress-header">
                <span class="cp-progress-label">Tiến độ hoàn thành</span>
            <span class="cp-progress-pct"><?php echo (int)$project['progress']; ?><span class="cp-pct-symbol">%</span></span>
            </div>
            <div class="cp-progress-bar">
                <div class="cp-progress-fill" style="--cp-progress:<?php echo (int)$project['progress']; ?>%"></div>
            </div>
        </div>
    </div>

    <!-- 2. DATES CARD -->
    <?php
    $dateStart    = $project['dates']['start'];
    $dateHandover = $project['dates']['handover'];
    $dateActual   = $project['dates']['actual_handover'];
    if ($dateStart || $dateHandover || $dateActual):
    ?>
    <div class="cp-card">
        <div class="cp-card__title">📅 Mốc thời gian</div>
        <div class="cp-dates-grid">
            <?php if ($dateStart): ?>
            <div class="cp-date-item">
                <div class="cp-date-item__label">Ngày bắt đầu</div>
                <div class="cp-date-item__value"><?php echo esc_html($dateStart); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($dateHandover): ?>
            <div class="cp-date-item">
                <div class="cp-date-item__label">Dự kiến bàn giao</div>
                <div class="cp-date-item__value"><?php echo esc_html($dateHandover); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($dateActual): ?>
            <div class="cp-date-item">
                <div class="cp-date-item__label">Bàn giao thực tế</div>
                <div class="cp-date-item__value cp-date-item__value--success"><?php echo esc_html($dateActual); ?></div>
            </div>
            <?php endif; ?>
            <?php if ($project['maintenance']['end']): ?>
            <div class="cp-date-item">
                <div class="cp-date-item__label">Bảo trì đến</div>
                <div class="cp-date-item__value"><?php echo esc_html($project['maintenance']['end']); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 3. ALERTS CARD -->
    <?php if (!empty($project['alerts'])): ?>
    <div class="cp-card">
        <div class="cp-card__title">⚠️ Thông báo dự án (<?php echo count($project['alerts']); ?>)</div>
        <?php foreach ($project['alerts'] as $alert): ?>
        <div class="cp-alert cp-alert--<?php echo esc_attr($alert['level']); ?>">
            <span class="cp-alert__icon">
                <?php echo match($alert['level']) {
                    'critical' => '🔴',
                    'warning'  => '🟡',
                    default    => '🔵',
                }; ?>
            </span>
            <div class="cp-alert__body">
                <div class="cp-alert__type"><?php echo esc_html($alert['type_label']); ?></div>
                <div class="cp-alert__msg"><?php echo nl2br(esc_html($alert['message'])); ?></div>
                <div class="cp-alert__date"><?php echo esc_html($alert['date']); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 3.5 TASKS CARD -->
    <?php if (!empty($project['tasks'])): ?>
    <?php
    $totalTasks = count($project['tasks']);
    $doneTasks  = count(array_filter($project['tasks'], fn($t) => $t['done']));
    $taskPct    = $totalTasks > 0 ? (int) round($doneTasks / $totalTasks * 100) : 0;
    ?>
    <div class="cp-card">
        <div class="cp-card__title">✅ Checklist công việc (<?php echo $doneTasks; ?>/<?php echo $totalTasks; ?>)</div>
        <!-- Mini progress bar -->
        <div class="cp-task-progress">
            <div class="cp-task-progress__header">
                <span><?php echo $taskPct; ?>% công việc hoàn thành</span>
                <span class="cp-task-progress__remaining"><?php echo $totalTasks - $doneTasks; ?> còn lại</span>
            </div>
            <div class="cp-task-bar">
                <div class="cp-task-bar-fill" style="--cp-task-progress:<?php echo $taskPct; ?>%"></div>
            </div>
        </div>
        <?php
        $cpCatIcons = ['bug'=>'🐛','page'=>'🖼️','content'=>'📝','seo'=>'🔍','feature'=>'⭐','other'=>'📌'];
        foreach ($project['tasks'] as $idx => $task):
            $cat    = $task['category'] ?? (($task['source'] ?? '') === 'page' ? 'page' : 'other');
            $icon   = $cpCatIcons[$cat] ?? '📌';
            $isDone = !empty($task['done']);
            $taskId = $task['id'] ?? '';
            $hasDesc  = !empty($task['description']);
            $hasImage = !empty($task['image_url']);
            $hasExtra = $hasDesc || $hasImage;
            $cmtId    = 'cp-cmt-' . $idx;
        ?>
        <div class="cp-task-row<?php echo $hasExtra ? ' cp-task-row--expandable' : ''; ?>" data-task-id="<?php echo esc_attr($taskId); ?>">
            <div class="cp-task-main">
                <span class="cp-task-dot cp-task-dot--<?php echo $isDone ? 'done' : 'pending'; ?>">
                    <?php echo $isDone ? '✓' : ''; ?>
                </span>
                <div class="cp-task-body">
                    <span class="cp-task-name<?php echo $isDone ? ' cp-task-name--done' : ''; ?>"><?php echo $icon . ' ' . esc_html($task['name']); ?></span>
                    <?php if ($hasDesc): ?>
                    <div class="cp-task-desc"><?php echo nl2br(esc_html($task['description'])); ?></div>
                    <?php endif; ?>
                    <?php if ($hasImage): ?>
                    <div class="cp-task-image">
                        <img src="<?php echo esc_url($task['image_url']); ?>" alt="<?php echo esc_attr($task['name']); ?>" loading="lazy">
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
        <?php endforeach; ?>

    </div>
    <?php endif; ?>

<?php endif; ?>
</main>