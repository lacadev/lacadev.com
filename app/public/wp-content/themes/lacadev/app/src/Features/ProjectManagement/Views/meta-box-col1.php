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
 * @var string|null $projectPublicId
 * @var array<string,string>|null $projectKeys
 * @var string|null $clientConfigJson
 * @var int|null    $legacyAuthLastSeen
 */

use App\Models\ProjectAlert;
use App\Features\ProjectManagement\ProjectKeyManager;

$pendingTasks = $pendingTasks ?? [];
$pendingPlugins = $pendingPlugins ?? [];
$clientPortalUrl = $clientPortalUrl ?? '';
$clientPortalAliasUrl = $clientPortalAliasUrl ?? '';
$trackerHealth = is_array($trackerHealth ?? null) ? $trackerHealth : [];
$trackerLastSeenMeta = is_array($trackerLastSeenMeta ?? null) ? $trackerLastSeenMeta : [];
$projectPublicId = $projectPublicId ?? ProjectKeyManager::getPublicId($projectId);
$projectKeys = is_array($projectKeys ?? null) ? $projectKeys : ProjectKeyManager::ensureKeys($projectId);
$clientConfigJson = $clientConfigJson ?? (string) wp_json_encode(ProjectKeyManager::getClientConfigPayload($projectId), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$legacyAuthLastSeen = isset($legacyAuthLastSeen) ? (int) $legacyAuthLastSeen : (int) get_post_meta($projectId, '_laca_legacy_auth_last_seen', true);
?>

<div class="laca-pm-col laca-project-workspace__side">
    <?php
    $uptimeStats24h = class_exists(\App\Databases\UptimeLogTable::class) ? \App\Databases\UptimeLogTable::getUptimeStats($projectId, 24) : null;
    $uptimeStats7d  = class_exists(\App\Databases\UptimeLogTable::class) ? \App\Databases\UptimeLogTable::getUptimeStats($projectId, 168) : null;
    ?>
    <section class="laca-project-panel" style="margin-bottom:16px;">
        <div class="laca-project-panel__header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <h3 style="margin:0;"><?php echo esc_html__('Uptime Monitor', 'laca'); ?></h3>
                <p style="margin:4px 0 0;font-size:12px;color:#64748b;"><?php echo esc_html__('Theo dõi thời gian hoạt động & độ trễ phản hồi.', 'laca'); ?></p>
            </div>
            <?php if ($uptimeStats24h && $uptimeStats24h['total_checks'] > 0): ?>
                <span class="laca-badge <?php echo $uptimeStats24h['percentage'] >= 99 ? 'laca-badge--success' : 'laca-badge--warning'; ?>">
                    <?php echo esc_html((string) $uptimeStats24h['percentage']); ?>%
                </span>
            <?php endif; ?>
        </div>
        <div style="padding:12px;display:grid;grid-template-columns:1fr 1fr;gap:8px;background:#f8fafc;border-radius:6px;margin-top:8px;">
            <div style="border-right:1px solid #e2e8f0;padding-right:8px;">
                <span style="font-size:11px;color:#64748b;display:block;"><?php echo esc_html__('Uptime 24 giờ', 'laca'); ?></span>
                <strong style="font-size:14px;color:#0f172a;"><?php echo $uptimeStats24h && $uptimeStats24h['total_checks'] > 0 ? esc_html($uptimeStats24h['percentage'] . '%') : '—'; ?></strong>
                <small style="display:block;font-size:11px;color:#64748b;"><?php echo $uptimeStats24h && $uptimeStats24h['total_checks'] > 0 ? esc_html($uptimeStats24h['avg_ms'] . 'ms') : ''; ?></small>
            </div>
            <div>
                <span style="font-size:11px;color:#64748b;display:block;"><?php echo esc_html__('Uptime 7 ngày', 'laca'); ?></span>
                <strong style="font-size:14px;color:#0f172a;"><?php echo $uptimeStats7d && $uptimeStats7d['total_checks'] > 0 ? esc_html($uptimeStats7d['percentage'] . '%') : '—'; ?></strong>
                <small style="display:block;font-size:11px;color:#64748b;"><?php echo $uptimeStats7d && $uptimeStats7d['total_checks'] > 0 ? esc_html($uptimeStats7d['avg_ms'] . 'ms') : ''; ?></small>
            </div>
        </div>
    </section>

    <?php if ($legacyAuthLastSeen > 0): ?>
        <div class="notice notice-warning inline" style="margin:0 0 16px;padding:12px;border-left:4px solid #f59e0b;background:#fffbeb;border-radius:4px;">
            <strong style="color:#b45309;display:block;margin-bottom:4px;">⚠️ <?php echo esc_html__('Client chưa nâng cấp bảo mật', 'laca'); ?></strong>
            <p style="margin:0;font-size:13px;color:#92400e;">
                <?php
                echo esc_html(sprintf(
                    __('Website này vừa kết nối bằng Secret Key không ký (xác thực cũ) vào %s. Vui lòng sao chép khối cấu hình bảo mật mới bên dưới và dán vào Laca Admin trên website khách.', 'laca'),
                    date_i18n('d/m/Y H:i', $legacyAuthLastSeen)
                ));
                ?>
            </p>
        </div>
    <?php endif; ?>
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
                <h3><?php echo esc_html__('Kết nối & Khóa bảo mật (HMAC)', 'laca'); ?></h3>
                <p><?php echo esc_html__('Quản lý Public ID và 4 khóa riêng biệt theo mục đích, ký xác thực HMAC chống giả mạo.', 'laca'); ?></p>
            </div>
        </div>

        <div style="margin-bottom:14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:10px 12px;">
            <p style="margin:0 0 8px;font-size:13px;font-weight:600;color:#166534;">
                ⚡ <?php echo esc_html__('Cấu hình nhanh cho website khách', 'laca'); ?>
            </p>
            <p style="margin:0 0 10px;font-size:12px;color:#15803d;">
                <?php echo esc_html__('Bấm nút bên dưới để sao chép toàn bộ endpoint và 4 khóa, sau đó dán vào ô "Dán cấu hình từ Hub" trong Laca Admin → 📡 Tracker trên site khách.', 'laca'); ?>
            </p>
            <button type="button" class="button button-primary js-copy-client-config" style="width:100%;display:flex;align-items:center;justify-content:center;gap:6px;height:34px;">
                <span class="dashicons dashicons-clipboard"></span>
                <strong><?php echo esc_html__('Sao chép toàn bộ cấu hình (JSON)', 'laca'); ?></strong>
            </button>
            <details style="margin-top:8px;">
                <summary style="font-size:11px;color:#166534;cursor:pointer;"><?php echo esc_html__('Xem nội dung cấu hình JSON', 'laca'); ?></summary>
                <textarea readonly class="laca-client-config-json" rows="6" style="width:100%;margin-top:4px;font-family:monospace;font-size:11px;background:#fff;border:1px solid #86efac;border-radius:4px;"><?php echo esc_textarea($clientConfigJson); ?></textarea>
            </details>
        </div>

        <div class="laca-form-group laca-copyable-input">
            <label><?php echo esc_html__('Endpoint URL', 'laca'); ?></label>
            <input type="text" readonly value="<?php echo esc_url($endpoint); ?>">
        </div>

        <div class="laca-form-group laca-copyable-input">
            <label><?php echo esc_html__('Public Project ID', 'laca'); ?></label>
            <input type="text" readonly value="<?php echo esc_attr($projectPublicId); ?>" id="laca_public_project_id">
        </div>

        <div class="laca-keys-list" style="margin-top:14px;border-top:1px solid #e5e7eb;padding-top:12px;">
            <h4 style="margin:0 0 10px;font-size:13px;color:#374151;text-transform:uppercase;letter-spacing:0.5px;">
                <?php echo esc_html__('Khóa bảo mật riêng biệt (4 Keys)', 'laca'); ?>
            </h4>

            <?php
            $keyDefinitions = [
                ProjectKeyManager::KEY_TRACKER => [
                    'title' => __('Tracker Key (Gửi log & crash)', 'laca'),
                    'desc'  => __('Xác thực HMAC khi site khách gửi log, lỗi và heartbeat về hub.', 'laca'),
                ],
                ProjectKeyManager::KEY_CATALOG => [
                    'title' => __('Block Catalog Key (Chợ block & Sync)', 'laca'),
                    'desc'  => __('Ký HMAC khi tải danh mục block hoặc đồng bộ file block hai chiều.', 'laca'),
                ],
                ProjectKeyManager::KEY_REMOTE => [
                    'title' => __('Remote Update Key (Cập nhật từ xa)', 'laca'),
                    'desc'  => __('Ký HMAC khi Hub gửi lệnh cập nhật plugin/theme/core sang website khách.', 'laca'),
                ],
                ProjectKeyManager::KEY_PORTAL => [
                    'title' => __('Client Portal Key (Portal khách hàng)', 'laca'),
                    'desc'  => __('Khóa xác thực dành riêng cho khách hàng mở xem Client Portal.', 'laca'),
                ],
            ];

            foreach ($keyDefinitions as $kType => $kMeta):
                $kVal = $projectKeys[$kType] ?? '';
            ?>
                <div class="laca-key-row" style="margin-bottom:12px;padding:8px 10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;" data-key-type="<?php echo esc_attr($kType); ?>">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <strong style="font-size:12px;color:#1f2937;"><?php echo esc_html($kMeta['title']); ?></strong>
                        <span style="font-size:11px;color:#6b7280;font-family:monospace;"><?php echo esc_html($kType); ?></span>
                    </div>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <input type="password" readonly class="laca-masked-key-input" value="<?php echo esc_attr($kVal); ?>" style="flex:1;font-family:monospace;font-size:12px;background:#fff;" id="laca_key_<?php echo esc_attr($kType); ?>">
                        <button type="button" class="button button-small js-toggle-key-view" title="<?php esc_attr_e('Hiện 1 lần', 'laca'); ?>">
                            <?php echo esc_html__('Hiện', 'laca'); ?>
                        </button>
                        <button type="button" class="button button-small js-copy-single-key" title="<?php esc_attr_e('Sao chép khóa', 'laca'); ?>">
                            <?php echo esc_html__('Sao chép', 'laca'); ?>
                        </button>
                        <button type="button" class="button button-small js-regenerate-single-key" style="color:#dc2626;" title="<?php esc_attr_e('Tạo lại key mới', 'laca'); ?>">
                            <?php echo esc_html__('Tạo lại', 'laca'); ?>
                        </button>
                    </div>
                    <small style="display:block;margin-top:4px;color:#6b7280;font-size:11px;line-height:1.4;">
                        <?php echo esc_html($kMeta['desc']); ?>
                    </small>
                </div>
            <?php endforeach; ?>
        </div>

        <details class="laca-project-tracker-fallback" style="margin-top:12px;">
            <summary><?php echo esc_html__('Site khách KHÔNG chạy theme lacadev-client? Dùng MU-plugin dự phòng', 'laca'); ?></summary>
            <p class="laca-project-state laca-project-state--warning" style="margin-top:6px;">
                <?php echo esc_html__('Chỉ dùng cách này khi site khách không phải theme lacadev-client (ví dụ site có sẵn của khách, theme khác). MU-plugin đã tự động tích hợp chữ ký HMAC theo Tracker Key.', 'laca'); ?>
            </p>
            <div class="laca-project-actions">
                <button type="button" class="button" id="btn_download_tracker"><?php echo esc_html__('Tải MU-plugin', 'laca'); ?></button>
                <button type="button" class="button" id="btn_view_tracker_code"><?php echo esc_html__('Xem code PHP', 'laca'); ?></button>
            </div>
        </details>
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
