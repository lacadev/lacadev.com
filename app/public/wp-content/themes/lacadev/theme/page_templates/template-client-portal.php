<?php
/**
 * Template Name: Client Portal
 *
 *
 * App Layout: layouts/app.php
 *
 * Customer-facing project progress and maintenance portal.
 *
 * @package WPEmergeTheme
 */

// Khởi tạo dữ liệu
$secretKey    = sanitize_text_field(wp_unslash($_GET['key'] ?? ''));
$error        = '';
$requestError = '';
$notice       = '';
$project      = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['laca_portal_action'] ?? '') === 'client_request') {
    $secretKey = sanitize_text_field(wp_unslash($_POST['key'] ?? $secretKey));

    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['laca_portal_nonce'] ?? '')), 'laca_portal_request')) {
        $requestError = 'Phiên gửi yêu cầu đã hết hạn. Vui lòng tải lại trang và thử lại.';
    } else {
        $request = new WP_REST_Request('POST', '/laca/v1/portal/request');
        $request->set_param('key', $secretKey);
        $request->set_param('request_type', sanitize_key(wp_unslash($_POST['request_type'] ?? 'request')));
        $request->set_param('message', sanitize_textarea_field(wp_unslash($_POST['message'] ?? '')));
        $request->set_param('contact_name', sanitize_text_field(wp_unslash($_POST['contact_name'] ?? '')));
        $request->set_param('contact_email', sanitize_email(wp_unslash($_POST['contact_email'] ?? '')));
        if (!empty($_FILES['attachments'])) {
            $request->set_file_params([
                'attachments' => $_FILES['attachments'],
            ]);
        }

        $response = rest_do_request($request);
        $responseData = $response->get_data();

        if ($response->get_status() >= 200 && $response->get_status() < 300 && !empty($responseData['success'])) {
            $notice = $responseData['message'] ?? 'Yêu cầu đã được gửi.';
        } else {
            $requestError = $responseData['message'] ?? 'Không thể gửi yêu cầu. Vui lòng thử lại.';
        }
    }
}

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
    <?php
    $serviceReport = $project['service_report'] ?? [
        'total' => count($project['logs'] ?? []),
        'updates' => 0,
        'fixes' => 0,
        'maintenance' => 0,
        'tasks' => 0,
        'latest_date' => '',
    ];
    $monthlyReport = $project['monthly_report'] ?? [
        'month' => date_i18n('m/Y'),
        'total' => 0,
        'updates' => 0,
        'fixes' => 0,
        'maintenance' => 0,
        'tasks' => 0,
    ];
    $totalTasks = count($project['tasks'] ?? []);
    $doneTasks  = count(array_filter($project['tasks'] ?? [], static fn($task): bool => !empty($task['done'])));
    $taskPct    = $totalTasks > 0 ? (int) round(($doneTasks / $totalTasks) * 100) : 0;
    $latestLog  = $project['logs'][0] ?? null;

    $overviewMetrics = [
        [
            'label' => 'Tiến độ tổng',
            'value' => (int) $project['progress'] . '%',
            'meta'  => $project['status_info']['label'] ?? 'Đang theo dõi',
            'tone'  => 'accent',
        ],
        [
            'label' => 'Tổng hoạt động',
            'value' => (int) $serviceReport['total'],
            'meta'  => !empty($serviceReport['latest_date']) ? 'Mới nhất ' . $serviceReport['latest_date'] : 'Chưa có cập nhật',
            'tone'  => 'default',
        ],
        [
            'label' => 'Sửa lỗi / yêu cầu',
            'value' => (int) $serviceReport['fixes'],
            'meta'  => (int) count($project['recent_requests'] ?? []) . ' yêu cầu gần đây',
            'tone'  => 'default',
        ],
        [
            'label' => 'Task hoàn thành',
            'value' => $doneTasks . '/' . $totalTasks,
            'meta'  => $totalTasks > 0 ? $taskPct . '% checklist hoàn tất' : 'Chưa có checklist',
            'tone'  => 'default',
        ],
    ];

    $monthlyMetrics = [
        ['label' => 'Tổng hoạt động', 'value' => (int) $monthlyReport['total']],
        ['label' => 'Cập nhật', 'value' => (int) $monthlyReport['updates']],
        ['label' => 'Sửa lỗi / yêu cầu', 'value' => (int) $monthlyReport['fixes']],
        ['label' => 'Bảo trì / task', 'value' => (int) $monthlyReport['maintenance'] + (int) $monthlyReport['tasks']],
    ];

    $serviceMetrics = [
        ['label' => 'Cập nhật hệ thống', 'value' => (int) $serviceReport['updates']],
        ['label' => 'Sửa lỗi / yêu cầu', 'value' => (int) $serviceReport['fixes']],
        ['label' => 'Bảo trì', 'value' => (int) $serviceReport['maintenance']],
        ['label' => 'Task hoàn thành', 'value' => (int) $serviceReport['tasks']],
    ];
    ?>

    <?php if ($notice): ?>
    <div class="cp-notice cp-notice--success">
        <?php echo esc_html($notice); ?>
    </div>
    <?php endif; ?>

    <section class="cp-shell">
        <section class="cp-overview cp-card">
            <div class="cp-overview__head">
                <div class="cp-overview__identity">
                    <span class="cp-kicker">Client Portal</span>
                    <h1 class="cp-project-name"><?php echo esc_html($project['name']); ?></h1>
                    <?php if (!empty($project['domain']) || !empty($project['live_url'])): ?>
                    <div class="cp-project-domain">
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
                    <?php echo esc_html($project['status_info']['icon']); ?>
                    <?php echo esc_html($project['status_info']['label']); ?>
                </span>
            </div>

            <div class="cp-overview__meta">
                <?php if (!empty($serviceReport['latest_date'])): ?>
                <div class="cp-meta-chip">
                    <span class="cp-meta-chip__label">Cập nhật gần nhất</span>
                    <strong><?php echo esc_html($serviceReport['latest_date']); ?></strong>
                </div>
                <?php endif; ?>
                <?php if (!empty($project['maintenance']['end'])): ?>
                <div class="cp-meta-chip">
                    <span class="cp-meta-chip__label">Bảo trì đến</span>
                    <strong><?php echo esc_html($project['maintenance']['end']); ?></strong>
                </div>
                <?php endif; ?>
                <?php if (!empty($project['dates']['handover'])): ?>
                <div class="cp-meta-chip">
                    <span class="cp-meta-chip__label">Dự kiến bàn giao</span>
                    <strong><?php echo esc_html($project['dates']['handover']); ?></strong>
                </div>
                <?php endif; ?>
            </div>

            <div class="cp-progress">
                <div class="cp-progress__head">
                    <span class="cp-progress__label">Tiến độ hoàn thành</span>
                    <strong class="cp-progress__value"><?php echo (int) $project['progress']; ?>%</strong>
                </div>
                <div class="cp-progress__track">
                    <div class="cp-progress__fill" style="--cp-progress:<?php echo (int) $project['progress']; ?>%"></div>
                </div>
            </div>

            <div class="cp-metric-grid">
                <?php foreach ($overviewMetrics as $metric): ?>
                <article class="cp-metric cp-metric--<?php echo esc_attr($metric['tone']); ?>">
                    <span class="cp-metric__label"><?php echo esc_html($metric['label']); ?></span>
                    <strong class="cp-metric__value"><?php echo esc_html((string) $metric['value']); ?></strong>
                    <span class="cp-metric__meta"><?php echo esc_html((string) $metric['meta']); ?></span>
                </article>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="cp-layout">
            <div class="cp-layout__main">
                <section class="cp-card cp-card--timeline">
                    <div class="cp-card__title">Nhật ký cập nhật & bảo trì</div>
                    <?php if (empty($project['logs'])): ?>
                        <div class="cp-empty-state">
                            Chưa có nhật ký cập nhật công khai cho dự án này.
                        </div>
                    <?php else: ?>
                        <div class="cp-timeline">
                            <?php foreach ($project['logs'] as $log): ?>
                                <article class="cp-log cp-log--<?php echo esc_attr($log['category'] ?? 'maintenance'); ?>">
                                    <div class="cp-log__marker" aria-hidden="true"></div>
                                    <div class="cp-log__body">
                                        <div class="cp-log__head">
                                            <span class="cp-log__type"><?php echo esc_html($log['type_label']); ?></span>
                                            <time><?php echo esc_html(trim($log['date'] . ' ' . ($log['time'] ?? ''))); ?></time>
                                        </div>
                                        <div class="cp-log__content"><?php echo nl2br(esc_html($log['content'])); ?></div>
                                        <?php if (!empty($log['attachments']) && is_array($log['attachments'])): ?>
                                            <div class="cp-attachment-grid">
                                                <?php foreach ($log['attachments'] as $attachment): ?>
                                                    <?php if (empty($attachment['url'])) { continue; } ?>
                                                    <a href="<?php echo esc_url($attachment['url']); ?>" target="_blank" rel="noopener" class="cp-attachment-thumb">
                                                        <img src="<?php echo esc_url($attachment['url']); ?>" alt="<?php echo esc_attr($attachment['name'] ?? 'Attachment'); ?>" loading="lazy">
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($log['by'])): ?>
                                            <div class="cp-log__by">Thực hiện bởi <?php echo esc_html($log['by']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if (!empty($project['tasks'])): ?>
                <section class="cp-card">
                    <div class="cp-section-head">
                        <div>
                            <div class="cp-card__title">Checklist công việc</div>
                            <p class="cp-section-note"><?php echo esc_html($doneTasks . '/' . $totalTasks); ?> hạng mục đã hoàn tất</p>
                        </div>
                        <div class="cp-section-badge"><?php echo esc_html($taskPct); ?>%</div>
                    </div>

                    <div class="cp-task-progress">
                        <div class="cp-task-progress__header">
                            <span><?php echo esc_html($taskPct); ?>% công việc hoàn thành</span>
                            <span class="cp-task-progress__remaining"><?php echo esc_html((string) max($totalTasks - $doneTasks, 0)); ?> còn lại</span>
                        </div>
                        <div class="cp-task-bar">
                            <div class="cp-task-bar-fill" style="--cp-task-progress:<?php echo esc_attr((string) $taskPct); ?>%"></div>
                        </div>
                    </div>

                    <?php
                    $cpCatIcons = ['bug' => 'BUG', 'page' => 'PAGE', 'content' => 'CONTENT', 'seo' => 'SEO', 'feature' => 'FEATURE', 'other' => 'TASK'];
                    foreach ($project['tasks'] as $task):
                        $cat    = $task['category'] ?? (($task['source'] ?? '') === 'page' ? 'page' : 'other');
                        $icon   = $cpCatIcons[$cat] ?? 'TASK';
                        $isDone = !empty($task['done']);
                        $hasDesc  = !empty($task['description']);
                        $hasImage = !empty($task['image_url']);
                    ?>
                    <article class="cp-task-row">
                        <div class="cp-task-main">
                            <span class="cp-task-dot cp-task-dot--<?php echo $isDone ? 'done' : 'pending'; ?>">
                                <?php echo $isDone ? '✓' : ''; ?>
                            </span>
                            <div class="cp-task-body">
                                <div class="cp-task-head">
                                    <span class="cp-task-tag"><?php echo esc_html($icon); ?></span>
                                    <span class="cp-task-name<?php echo $isDone ? ' cp-task-name--done' : ''; ?>"><?php echo esc_html($task['name']); ?></span>
                                </div>
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
                    </article>
                    <?php endforeach; ?>
                </section>
                <?php endif; ?>
            </div>

            <aside class="cp-layout__side">
                <section class="cp-card cp-card--monthly">
                    <div class="cp-card__title">Báo cáo tháng <?php echo esc_html($monthlyReport['month']); ?></div>
                    <div class="cp-stat-grid">
                        <?php foreach ($monthlyMetrics as $metric): ?>
                        <article class="cp-stat">
                            <span class="cp-stat__label"><?php echo esc_html($metric['label']); ?></span>
                            <strong class="cp-stat__value"><?php echo esc_html((string) $metric['value']); ?></strong>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="cp-card cp-card--report">
                    <div class="cp-card__title">Báo cáo chăm sóc website</div>
                    <div class="cp-report-hero">
                        <div>
                            <div class="cp-report-hero__label">Tổng hoạt động đã ghi nhận</div>
                            <div class="cp-report-hero__value"><?php echo (int) $serviceReport['total']; ?></div>
                        </div>
                        <?php if (!empty($serviceReport['latest_date'])): ?>
                        <div class="cp-report-hero__latest">
                            Cập nhật gần nhất
                            <strong><?php echo esc_html($serviceReport['latest_date']); ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="cp-stat-grid cp-stat-grid--compact">
                        <?php foreach ($serviceMetrics as $metric): ?>
                        <article class="cp-stat">
                            <span class="cp-stat__label"><?php echo esc_html($metric['label']); ?></span>
                            <strong class="cp-stat__value"><?php echo esc_html((string) $metric['value']); ?></strong>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <?php
                $dateStart    = $project['dates']['start'];
                $dateHandover = $project['dates']['handover'];
                $dateActual   = $project['dates']['actual_handover'];
                if ($dateStart || $dateHandover || $dateActual || !empty($project['maintenance']['end'])):
                ?>
                <section class="cp-card">
                    <div class="cp-card__title">Mốc thời gian</div>
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
                        <?php if (!empty($project['maintenance']['end'])): ?>
                        <div class="cp-date-item">
                            <div class="cp-date-item__label">Bảo trì đến</div>
                            <div class="cp-date-item__value"><?php echo esc_html($project['maintenance']['end']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (!empty($project['alerts'])): ?>
                <section class="cp-card">
                    <div class="cp-card__title">Thông báo dự án <span class="cp-card__title-count"><?php echo esc_html((string) count($project['alerts'])); ?></span></div>
                    <?php foreach ($project['alerts'] as $alert): ?>
                    <div class="cp-alert cp-alert--<?php echo esc_attr($alert['level']); ?>">
                        <div class="cp-alert__body">
                            <div class="cp-alert__type"><?php echo esc_html($alert['type_label']); ?></div>
                            <div class="cp-alert__msg"><?php echo nl2br(esc_html($alert['message'])); ?></div>
                            <div class="cp-alert__date"><?php echo esc_html($alert['date']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </section>
                <?php endif; ?>

                <section class="cp-card cp-card--request">
                    <div class="cp-section-head">
                        <div>
                            <div class="cp-card__title">Gửi yêu cầu hỗ trợ</div>
                            <p class="cp-section-note">Gửi lỗi, cập nhật nội dung hoặc yêu cầu bảo trì trực tiếp từ portal.</p>
                        </div>
                    </div>
                    <?php if ($requestError): ?>
                    <div class="cp-notice cp-notice--error">
                        <?php echo esc_html($requestError); ?>
                    </div>
                    <?php endif; ?>
                    <form class="cp-request-form" method="post" action="" enctype="multipart/form-data">
                        <input type="hidden" name="laca_portal_action" value="client_request">
                        <input type="hidden" name="key" value="<?php echo esc_attr($secretKey); ?>">
                        <?php wp_nonce_field('laca_portal_request', 'laca_portal_nonce'); ?>
                        <div class="cp-request-grid">
                            <label>
                                <span>Loại yêu cầu</span>
                                <select name="request_type">
                                    <option value="request">Yêu cầu hỗ trợ</option>
                                    <option value="bug">Báo lỗi website</option>
                                    <option value="content">Cập nhật nội dung</option>
                                    <option value="maintenance">Bảo trì</option>
                                    <option value="billing">Thanh toán</option>
                                </select>
                            </label>
                            <label>
                                <span>Email liên hệ</span>
                                <input type="email" name="contact_email" placeholder="email@example.com">
                            </label>
                        </div>
                        <label>
                            <span>Nội dung</span>
                            <textarea name="message" rows="5" placeholder="Mô tả ngắn gọn lỗi, yêu cầu cập nhật hoặc nội dung cần hỗ trợ..." required></textarea>
                        </label>
                        <label class="cp-upload-field">
                            <span>Hình ảnh đính kèm</span>
                            <input type="file" name="attachments[]" accept="image/jpeg,image/png,image/webp" multiple>
                            <small>Tối đa 4 ảnh, mỗi ảnh tối đa 5MB. Hỗ trợ JPG, PNG, WebP.</small>
                        </label>
                        <label>
                            <span>Người gửi</span>
                            <input type="text" name="contact_name" placeholder="Tên của bạn">
                        </label>
                        <button type="submit">Gửi yêu cầu</button>
                    </form>

                    <?php if (!empty($project['recent_requests'])): ?>
                        <div class="cp-recent-requests">
                            <strong>Yêu cầu gần đây</strong>
                            <?php foreach ($project['recent_requests'] as $requestLog): ?>
                                <article class="cp-recent-request">
                                    <span><?php echo esc_html($requestLog['date']); ?></span>
                                    <p><?php echo esc_html(wp_trim_words($requestLog['content'], 18)); ?></p>
                                    <?php if (!empty($requestLog['attachments']) && is_array($requestLog['attachments'])): ?>
                                        <div class="cp-attachment-grid cp-attachment-grid--compact">
                                            <?php foreach ($requestLog['attachments'] as $attachment): ?>
                                                <?php if (empty($attachment['url'])) { continue; } ?>
                                                <a href="<?php echo esc_url($attachment['url']); ?>" target="_blank" rel="noopener" class="cp-attachment-thumb">
                                                    <img src="<?php echo esc_url($attachment['url']); ?>" alt="<?php echo esc_attr($attachment['name'] ?? 'Attachment'); ?>" loading="lazy">
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </aside>
        </div>
    </section>

<?php endif; ?>
</main>
