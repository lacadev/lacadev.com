<?php

namespace App\Features\ProjectManagement;

/**
 * Trang Fleet: Quản lý toàn bộ website khách hàng trong hệ thống
 *
 * Theo dõi trạng thái heartbeat, phiên bản WP/PHP/theme, sao lưu gần nhất,
 * số lượng plugin cần cập nhật, phương thức xác thực và hỗ trợ cập nhật hàng loạt.
 */
class FleetPage
{
    private const LATEST_THEME_URL   = 'https://lacadev.com/theme-updates/lacadev-client.json';
    private const THEME_CACHE_KEY    = 'laca_fleet_latest_theme_ver';
    private const THEME_CACHE_TTL    = HOUR_IN_SECONDS;

    public function render(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Bạn không có quyền truy cập trang này.', 'laca'));
        }

        $projects       = $this->getFleetProjects();
        $latestThemeVer = self::getLatestParentThemeVersion();
        $stats          = $this->calculateStats($projects, $latestThemeVer);

        ?>
        <div class="wrap laca-projects-wrap laca-fleet-page">
            <div class="laca-projects-header">
                <div>
                    <h1><?php echo esc_html__('Fleet Management', 'laca'); ?></h1>
                    <p><?php echo esc_html__('Quản lý tập trung toàn bộ website khách hàng, theo dõi sao lưu, phiên bản và cập nhật từ xa.', 'laca'); ?></p>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="laca-projects-overview laca-projects-overview--compact" style="margin-bottom:20px;">
                <div class="laca-projects-metric">
                    <span><?php echo esc_html__('Tổng số website', 'laca'); ?></span>
                    <strong><?php echo esc_html((string) $stats['total']); ?></strong>
                    <small><?php echo esc_html__('Website có live URL', 'laca'); ?></small>
                </div>
                <div class="laca-projects-metric laca-projects-metric--success">
                    <span><?php echo esc_html__('Đang hoạt động tốt', 'laca'); ?></span>
                    <strong><?php echo esc_html((string) $stats['healthy']); ?></strong>
                    <small><?php echo esc_html__('Heartbeat < 2 giờ', 'laca'); ?></small>
                </div>
                <div class="laca-projects-metric <?php echo $stats['theme_outdated'] > 0 ? 'laca-projects-metric--warning' : ''; ?>">
                    <span><?php echo esc_html__('Cần update theme', 'laca'); ?></span>
                    <strong><?php echo esc_html((string) $stats['theme_outdated']); ?></strong>
                    <small><?php echo esc_html(sprintf(__('Bản mới nhất: %s', 'laca'), $latestThemeVer)); ?></small>
                </div>
                <div class="laca-projects-metric <?php echo $stats['backup_warning'] > 0 ? 'laca-projects-metric--danger' : ''; ?>">
                    <span><?php echo esc_html__('Cảnh báo sao lưu', 'laca'); ?></span>
                    <strong><?php echo esc_html((string) $stats['backup_warning']); ?></strong>
                    <small><?php echo esc_html__('Quá hạn hoặc thất bại', 'laca'); ?></small>
                </div>
            </div>

            <!-- Bulk Runner Panel (Hidden by default) -->
            <div id="laca-fleet-runner-panel" class="laca-card" style="display:none;margin-bottom:20px;border-left:4px solid #2271b1;">
                <div class="laca-card__header" style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #e2e8f0;">
                    <strong id="laca-fleet-runner-title" style="font-size:14px;"><?php echo esc_html__('Đang thực hiện cập nhật hàng loạt...', 'laca'); ?></strong>
                    <div>
                        <button type="button" id="laca-fleet-btn-stop" class="button button-link-delete" style="font-weight:600;margin-right:8px;">
                            🛑 <?php echo esc_html__('Dừng tiến trình', 'laca'); ?>
                        </button>
                        <button type="button" id="laca-fleet-btn-close" class="button button-secondary" style="display:none;">
                            <?php echo esc_html__('Đóng bảng điều khiển', 'laca'); ?>
                        </button>
                    </div>
                </div>
                <div class="laca-card__body" style="padding:16px;">
                    <div style="margin-bottom:12px;">
                        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px;color:#64748b;">
                            <span id="laca-fleet-progress-label"><?php echo esc_html__('Tiến độ: 0%', 'laca'); ?></span>
                            <span id="laca-fleet-progress-count">0 / 0</span>
                        </div>
                        <div style="background:#e2e8f0;border-radius:9999px;height:10px;overflow:hidden;">
                            <div id="laca-fleet-progress-bar" style="background:#2271b1;height:100%;width:0%;transition:width 0.3s ease;"></div>
                        </div>
                    </div>
                    <div id="laca-fleet-console-log" style="background:#0f172a;color:#f8fafc;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:12px;line-height:1.6;padding:12px;border-radius:6px;max-height:220px;overflow-y:auto;white-space:pre-wrap;"></div>
                </div>
            </div>

            <!-- Filter & Bulk Actions Bar -->
            <div class="laca-card" style="margin-bottom:16px;">
                <div class="laca-card__body" style="padding:12px 16px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;">
                    <!-- Bulk Actions -->
                    <div style="display:flex;align-items:center;gap:8px;">
                        <select id="laca-fleet-bulk-action" class="laca-input" style="min-width:200px;">
                            <option value=""><?php echo esc_html__('— Hành động hàng loạt —', 'laca'); ?></option>
                            <option value="update_theme"><?php echo esc_html__('Cập nhật theme cha', 'laca'); ?></option>
                            <option value="update_plugin"><?php echo esc_html__('Cập nhật plugin', 'laca'); ?></option>
                        </select>
                        <button type="button" id="laca-fleet-btn-apply" class="button button-primary">
                            <?php echo esc_html__('Áp dụng', 'laca'); ?>
                        </button>
                        <span id="laca-fleet-selected-count" style="font-size:12px;color:#64748b;margin-left:4px;">
                            <?php echo esc_html__('(Đã chọn 0 website)', 'laca'); ?>
                        </span>
                    </div>

                    <!-- Filters -->
                    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;">
                        <input type="text" id="laca-fleet-filter-search" class="laca-input" placeholder="<?php echo esc_attr__('Tìm theo tên, domain...', 'laca'); ?>" style="max-width:180px;">

                        <select id="laca-fleet-filter-auth" class="laca-input">
                            <option value="all"><?php echo esc_html__('Xác thực: Tất cả', 'laca'); ?></option>
                            <option value="signed"><?php echo esc_html__('Đã ký (signed)', 'laca'); ?></option>
                            <option value="legacy"><?php echo esc_html__('Legacy auth', 'laca'); ?></option>
                            <option value="none"><?php echo esc_html__('Chưa thấy', 'laca'); ?></option>
                        </select>

                        <select id="laca-fleet-filter-theme" class="laca-input">
                            <option value="all"><?php echo esc_html__('Theme: Tất cả', 'laca'); ?></option>
                            <option value="outdated"><?php echo esc_html__('Cần update', 'laca'); ?></option>
                            <option value="updated"><?php echo esc_html__('Mới nhất', 'laca'); ?></option>
                        </select>

                        <select id="laca-fleet-filter-backup" class="laca-input">
                            <option value="all"><?php echo esc_html__('Sao lưu: Tất cả', 'laca'); ?></option>
                            <option value="warning"><?php echo esc_html__('Cảnh báo / Quá hạn', 'laca'); ?></option>
                            <option value="ok"><?php echo esc_html__('Tốt', 'laca'); ?></option>
                            <option value="unknown"><?php echo esc_html__('Chưa rõ', 'laca'); ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Fleet Table -->
            <section class="laca-projects-panel" style="padding:0;overflow:hidden;">
                <?php if (empty($projects)): ?>
                    <div style="padding:40px;text-align:center;color:#64748b;">
                        <p style="font-size:15px;margin-bottom:8px;"><?php echo esc_html__('Chưa có website nào trong hệ thống hoặc chưa cấu hình live URL.', 'laca'); ?></p>
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=project')); ?>" class="button button-primary">
                            <?php echo esc_html__('Thêm dự án mới', 'laca'); ?>
                        </a>
                    </div>
                <?php else: ?>
                    <table class="widefat striped laca-projects-table laca-fleet-table" style="border:none;margin:0;">
                        <thead>
                            <tr>
                                <td style="width:36px;padding-left:12px;vertical-align:middle;">
                                    <input type="checkbox" id="laca-fleet-select-all" title="<?php echo esc_attr__('Chọn tất cả', 'laca'); ?>">
                                </td>
                                <th><?php echo esc_html__('Website', 'laca'); ?></th>
                                <th><?php echo esc_html__('Heartbeat gần nhất', 'laca'); ?></th>
                                <th><?php echo esc_html__('WP / PHP', 'laca'); ?></th>
                                <th><?php echo esc_html__('Theme cha / child', 'laca'); ?></th>
                                <th><?php echo esc_html__('Sao lưu gần nhất', 'laca'); ?></th>
                                <th><?php echo esc_html__('Uptime', 'laca'); ?></th>
                                <th><?php echo esc_html__('Plugin chờ update', 'laca'); ?></th>
                                <th><?php echo esc_html__('Cách xác thực', 'laca'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="laca-fleet-tbody">
                            <?php foreach ($projects as $item): ?>
                                <?php
                                $projectId = $item['id'];
                                $title     = $item['title'];
                                $liveUrl   = $item['live_url'];
                                $editUrl   = get_edit_post_link($projectId);

                                // Heartbeat info
                                $lastSeenAt = $item['last_seen_at'];
                                $heartbeatBadge = $this->renderHeartbeatBadge($lastSeenAt);

                                // Version info
                                $meta       = $item['meta'];
                                $wpVer      = !empty($meta['wp_version']) ? (string) $meta['wp_version'] : '—';
                                $phpVer     = !empty($meta['php_version']) ? (string) $meta['php_version'] : '—';
                                $parentVer  = !empty($meta['theme_version_parent']) ? (string) $meta['theme_version_parent'] : '';
                                $childVer   = !empty($meta['theme_version_child']) ? (string) $meta['theme_version_child'] : '';

                                // Theme comparison
                                $isThemeOutdated = false;
                                if ($latestThemeVer !== '?' && $parentVer !== '' && version_compare($parentVer, $latestThemeVer, '<')) {
                                    $isThemeOutdated = true;
                                }

                                // Backup info
                                $backupData    = $item['backup'];
                                $backupSummary = $this->formatBackupInfo($backupData);

                                // Auth mode
                                $authMode = $this->determineAuthMode($item['signed_auth_last_seen'], $item['legacy_auth_last_seen']);

                                // Pending plugins
                                $pendingCount = $item['pending_plugins_count'];
                                ?>
                                <tr class="laca-fleet-row"
                                    data-project-id="<?php echo esc_attr((string) $projectId); ?>"
                                    data-title="<?php echo esc_attr(mb_strtolower($title)); ?>"
                                    data-url="<?php echo esc_attr(mb_strtolower($liveUrl)); ?>"
                                    data-auth="<?php echo esc_attr($authMode['key']); ?>"
                                    data-theme="<?php echo $isThemeOutdated ? 'outdated' : 'updated'; ?>"
                                    data-backup="<?php echo esc_attr($backupSummary['filter_key']); ?>">
                                    <td style="width:36px;padding-left:12px;vertical-align:middle;">
                                        <input type="checkbox" class="laca-fleet-select-item"
                                               value="<?php echo esc_attr((string) $projectId); ?>"
                                               data-title="<?php echo esc_attr($title); ?>"
                                               data-url="<?php echo esc_url($liveUrl); ?>">
                                    </td>
                                    <td>
                                        <strong>
                                            <a href="<?php echo esc_url($liveUrl); ?>" target="_blank" rel="noopener" style="text-decoration:none;">
                                                <?php echo esc_html($title); ?> ↗
                                            </a>
                                        </strong>
                                        <br>
                                        <small style="color:#64748b;">
                                            <a href="<?php echo esc_url($editUrl ?: '#'); ?>" style="color:#64748b;text-decoration:none;">
                                                ⚙️ <?php echo esc_html__('Cấu hình', 'laca'); ?>
                                            </a>
                                            &bull;
                                            <span style="word-break:break-all;"><?php echo esc_html(preg_replace('#^https?://#', '', rtrim($liveUrl, '/'))); ?></span>
                                        </small>
                                    </td>
                                    <td>
                                        <?php echo $heartbeatBadge; // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                    </td>
                                    <td>
                                        <span>WP <?php echo esc_html($wpVer); ?></span><br>
                                        <small style="color:#64748b;">PHP <?php echo esc_html($phpVer); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($parentVer === ''): ?>
                                            <span style="color:#94a3b8;">—</span>
                                        <?php elseif ($isThemeOutdated): ?>
                                            <span class="laca-badge laca-badge--danger" title="<?php echo esc_attr(sprintf(__('Phiên bản mới nhất là %s', 'laca'), $latestThemeVer)); ?>">
                                                v<?php echo esc_html($parentVer); ?> ⬇ (Mới: <?php echo esc_html($latestThemeVer); ?>)
                                            </span>
                                        <?php else: ?>
                                            <span class="laca-badge laca-badge--success">
                                                v<?php echo esc_html($parentVer); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($childVer !== ''): ?>
                                            <br><small style="color:#64748b;">Child: v<?php echo esc_html($childVer); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $backupSummary['html']; // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                    </td>
                                    <td>
                                        <span style="color:#94a3b8;" title="<?php echo esc_attr__('Sẽ được kích hoạt ở Phase 10', 'laca'); ?>">—</span>
                                    </td>
                                    <td>
                                        <?php if ($pendingCount > 0): ?>
                                            <span class="laca-badge laca-badge--warning">
                                                <?php echo esc_html(sprintf(_n('%d plugin', '%d plugins', $pendingCount, 'laca'), $pendingCount)); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="laca-badge laca-badge--success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $authMode['badge']; // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </div>

        <script>
        (function($) {
            'use strict';

            const config = {
                ajaxUrl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
                nonce:   <?php echo wp_json_encode(wp_create_nonce('laca_project_manager')); ?>,
                i18n: {
                    selectAtLeastOne: <?php echo wp_json_encode(__('Vui lòng chọn ít nhất một website.', 'laca')); ?>,
                    selectAction:     <?php echo wp_json_encode(__('Vui lòng chọn hành động cần thực hiện.', 'laca')); ?>,
                    confirmAction:    <?php echo wp_json_encode(__('Bạn có chắc chắn muốn thực hiện "%action%" cho %count% website đã chọn?', 'laca')); ?>,
                    running:          <?php echo wp_json_encode(__('Đang thực hiện cập nhật hàng loạt...', 'laca')); ?>,
                    stopping:         <?php echo wp_json_encode(__('Đã nhận lệnh dừng. Đang hoàn tất site hiện tại và dừng các site tiếp theo...', 'laca')); ?>,
                    finished:         <?php echo wp_json_encode(__('Đã hoàn tất tiến trình cập nhật hàng loạt.', 'laca')); ?>,
                    stopped:          <?php echo wp_json_encode(__('Tiến trình đã được dừng bởi người dùng.', 'laca')); ?>,
                    timeoutError:     <?php echo wp_json_encode(__('Timeout: Quá thời gian 120s chờ phản hồi từ site khách.', 'laca')); ?>,
                    networkError:     <?php echo wp_json_encode(__('Lỗi kết nối HTTP.', 'laca')); ?>
                }
            };

            let isRunning = false;
            let shouldStop = false;

            // Update selection count
            function updateSelectedCount() {
                const count = $('.laca-fleet-select-item:checked').length;
                $('#laca-fleet-selected-count').text('(' + count + ' website được chọn)');
            }

            // Select all
            $('#laca-fleet-select-all').on('change', function() {
                const checked = $(this).prop('checked');
                $('.laca-fleet-row:visible .laca-fleet-select-item').prop('checked', checked);
                updateSelectedCount();
            });

            $(document).on('change', '.laca-fleet-select-item', function() {
                updateSelectedCount();
                const totalVisible = $('.laca-fleet-row:visible .laca-fleet-select-item').length;
                const checkedVisible = $('.laca-fleet-row:visible .laca-fleet-select-item:checked').length;
                $('#laca-fleet-select-all').prop('checked', totalVisible > 0 && totalVisible === checkedVisible);
            });

            // Filtering
            function applyFilters() {
                const query    = ($('#laca-fleet-filter-search').val() || '').trim().toLowerCase();
                const authVal  = $('#laca-fleet-filter-auth').val();
                const themeVal = $('#laca-fleet-filter-theme').val();
                const backVal  = $('#laca-fleet-filter-backup').val();

                $('.laca-fleet-row').each(function() {
                    const $row   = $(this);
                    const title  = $row.data('title') || '';
                    const url    = $row.data('url') || '';
                    const auth   = $row.data('auth') || '';
                    const theme  = $row.data('theme') || '';
                    const backup = $row.data('backup') || '';

                    let matchSearch = (query === '' || title.indexOf(query) !== -1 || url.indexOf(query) !== -1);
                    let matchAuth   = (authVal === 'all' || auth === authVal);
                    let matchTheme  = (themeVal === 'all' || theme === themeVal);
                    let matchBackup = (backVal === 'all' || backup === backVal);

                    if (matchSearch && matchAuth && matchTheme && matchBackup) {
                        $row.show();
                    } else {
                        $row.hide();
                    }
                });

                updateSelectedCount();
            }

            $('#laca-fleet-filter-search').on('input', applyFilters);
            $('#laca-fleet-filter-auth, #laca-fleet-filter-theme, #laca-fleet-filter-backup').on('change', applyFilters);

            // Log helper
            function appendLog(line) {
                const $console = $('#laca-fleet-console-log');
                const timeStr  = new Date().toLocaleTimeString();
                $console.append('[' + timeStr + '] ' + line + '\n');
                $console.scrollTop($console[0].scrollHeight);
            }

            // Stop button
            $('#laca-fleet-btn-stop').on('click', function() {
                if (!isRunning) return;
                shouldStop = true;
                appendLog('⚠️ ' + config.i18n.stopping);
                $(this).prop('disabled', true).text('Đang dừng...');
            });

            // Close runner panel
            $('#laca-fleet-btn-close').on('click', function() {
                if (isRunning) return;
                $('#laca-fleet-runner-panel').slideUp();
            });

            // Bulk Apply button
            $('#laca-fleet-btn-apply').on('click', async function() {
                if (isRunning) return;

                const action = $('#laca-fleet-bulk-action').val();
                if (!action) {
                    alert(config.i18n.selectAction);
                    return;
                }

                const selectedItems = [];
                $('.laca-fleet-select-item:checked').each(function() {
                    const $item = $(this);
                    selectedItems.push({
                        id:    $item.val(),
                        title: $item.data('title'),
                        url:   $item.data('url')
                    });
                });

                if (selectedItems.length === 0) {
                    alert(config.i18n.selectAtLeastOne);
                    return;
                }

                const actionText = $('#laca-fleet-bulk-action option:selected').text();
                const confirmMsg = config.i18n.confirmAction
                    .replace('%action%', actionText)
                    .replace('%count%', selectedItems.length);

                if (!confirm(confirmMsg)) {
                    return;
                }

                // Start process
                isRunning  = true;
                shouldStop = false;

                $('#laca-fleet-btn-apply').prop('disabled', true);
                $('#laca-fleet-btn-stop').prop('disabled', false).show().text('🛑 ' + <?php echo wp_json_encode(__('Dừng tiến trình', 'laca')); ?>);
                $('#laca-fleet-btn-close').hide();
                $('#laca-fleet-runner-title').text(config.i18n.running + ' (' + actionText + ')');
                $('#laca-fleet-console-log').empty();
                $('#laca-fleet-runner-panel').slideDown();

                appendLog('🚀 Bắt đầu cập nhật cho ' + selectedItems.length + ' website...');

                const total = selectedItems.length;

                for (let i = 0; i < total; i++) {
                    if (shouldStop) {
                        appendLog('🛑 ' + config.i18n.stopped);
                        break;
                    }

                    const item = selectedItems[i];
                    const index = i + 1;

                    // Update progress
                    const progressPercent = Math.round((i / total) * 100);
                    $('#laca-fleet-progress-bar').css('width', progressPercent + '%');
                    $('#laca-fleet-progress-label').text('Tiến độ: ' + progressPercent + '%');
                    $('#laca-fleet-progress-count').text(index + ' / ' + total);

                    appendLog('▶️ [' + index + '/' + total + '] Đang gửi lệnh tới: ' + item.title + ' (' + item.url + ')...');

                    // Call AJAX with 120s timeout
                    await new Promise(function(resolve) {
                        let timedOut = false;
                        const timer = setTimeout(function() {
                            timedOut = true;
                            appendLog('❌ [' + item.title + '] ' + config.i18n.timeoutError);
                            resolve();
                        }, 125000);

                        $.ajax({
                            url: config.ajaxUrl,
                            type: 'POST',
                            timeout: 120000,
                            data: {
                                action: 'laca_remote_update',
                                nonce: config.nonce,
                                project_id: item.id,
                                update_action: action,
                                update_slug: action === 'update_theme' ? 'lacadev-client/theme' : ''
                            },
                            success: function(res) {
                                if (timedOut) return;
                                clearTimeout(timer);

                                if (res && res.success) {
                                    const msg = res.data && res.data.message ? res.data.message : 'Thành công';
                                    appendLog('✅ [' + item.title + '] ' + msg);
                                } else {
                                    const errMsg = res && res.data && res.data.message ? res.data.message : 'Thất bại không rõ nguyên nhân';
                                    appendLog('❌ [' + item.title + '] Thất bại: ' + errMsg);
                                }
                                resolve();
                            },
                            error: function(xhr, status, error) {
                                if (timedOut) return;
                                clearTimeout(timer);
                                appendLog('❌ [' + item.title + '] ' + config.i18n.networkError + ' (' + status + ' ' + error + ')');
                                resolve();
                            }
                        });
                    });
                }

                // Finish
                const finalPercent = shouldStop ? Math.round(($('.laca-fleet-progress-count').text().split('/')[0] || 0) / total * 100) : 100;
                $('#laca-fleet-progress-bar').css('width', finalPercent + '%');
                $('#laca-fleet-progress-label').text('Hoàn tất: ' + finalPercent + '%');

                if (!shouldStop) {
                    appendLog('🎉 ' + config.i18n.finished);
                }

                isRunning = false;
                $('#laca-fleet-btn-apply').prop('disabled', false);
                $('#laca-fleet-btn-stop').hide();
                $('#laca-fleet-btn-close').show();
            });

        })(jQuery);
        </script>
        <?php
    }

    /**
     * Lấy phiên bản theme cha mới nhất từ CDN publish (cache 1h)
     */
    public static function getLatestParentThemeVersion(): string
    {
        $cached = get_transient(self::THEME_CACHE_KEY);
        if ($cached !== false && is_string($cached)) {
            return $cached;
        }

        $response = wp_remote_get(self::LATEST_THEME_URL, [
            'timeout'   => 5,
            'sslverify' => true,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            // Lưu cache ngắn 5 phút để tránh gọi liên tục khi mất mạng
            set_transient(self::THEME_CACHE_KEY, '?', 5 * MINUTE_IN_SECONDS);
            return '?';
        }

        $body    = wp_remote_retrieve_body($response);
        $data    = json_decode($body, true);
        $version = isset($data['version']) && is_string($data['version']) ? trim($data['version']) : '?';

        set_transient(self::THEME_CACHE_KEY, $version, self::THEME_CACHE_TTL);
        return $version;
    }

    /**
     * Lấy danh sách các project có cấu hình live_url
     *
     * @return array<int, array<string, mixed>>
     */
    private function getFleetProjects(): array
    {
        $posts = get_posts([
            'post_type'      => 'project',
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $results = [];

        foreach ($posts as $post) {
            $liveUrl = trim((string) (carbon_get_post_meta($post->ID, 'live_url') ?: get_post_meta($post->ID, '_live_url', true)));
            if (empty($liveUrl)) {
                continue;
            }

            $backup = get_post_meta($post->ID, '_tracker_last_backup', true);
            if (!is_array($backup)) {
                $backup = [];
            }

            $meta = get_post_meta($post->ID, '_tracker_last_seen_meta', true);
            if (!is_array($meta)) {
                $meta = [];
            }

            $pendingCount = (int) get_post_meta($post->ID, '_plugins_pending_count', true);
            if ($pendingCount === 0) {
                $pendingUpdates = get_post_meta($post->ID, '_pending_plugin_updates', true);
                if (is_array($pendingUpdates)) {
                    $pendingCount = count($pendingUpdates);
                }
            }

            $results[] = [
                'id'                     => $post->ID,
                'title'                  => get_the_title($post),
                'live_url'               => $liveUrl,
                'last_seen_at'           => (string) get_post_meta($post->ID, '_tracker_last_seen_at', true),
                'signed_auth_last_seen'  => (int) get_post_meta($post->ID, '_laca_signed_auth_last_seen', true),
                'legacy_auth_last_seen'  => (int) get_post_meta($post->ID, '_laca_legacy_auth_last_seen', true),
                'backup'                 => $backup,
                'meta'                   => $meta,
                'pending_plugins_count'  => $pendingCount,
            ];
        }

        return $results;
    }

    /**
     * @param array<int, array<string, mixed>> $projects
     * @return array{total: int, healthy: int, theme_outdated: int, backup_warning: int}
     */
    private function calculateStats(array $projects, string $latestThemeVer): array
    {
        $stats = [
            'total'          => count($projects),
            'healthy'        => 0,
            'theme_outdated' => 0,
            'backup_warning' => 0,
        ];

        $now = time();
        $maxBackupDays = function_exists('carbon_get_theme_option') ? (int) carbon_get_theme_option('laca_backup_max_age_days') : 7;
        if ($maxBackupDays <= 0) {
            $maxBackupDays = 7;
        }

        foreach ($projects as $item) {
            // Heartbeat
            if (!empty($item['last_seen_at'])) {
                $ts = strtotime($item['last_seen_at']);
                if ($ts > 0 && ($now - $ts) < 2 * HOUR_IN_SECONDS) {
                    $stats['healthy']++;
                }
            }

            // Theme outdated
            $parentVer = !empty($item['meta']['theme_version_parent']) ? (string) $item['meta']['theme_version_parent'] : '';
            if ($latestThemeVer !== '?' && $parentVer !== '' && version_compare($parentVer, $latestThemeVer, '<')) {
                $stats['theme_outdated']++;
            }

            // Backup warning
            $b = $item['backup'];
            if (!empty($b)) {
                $status = $b['status'] ?? 'unknown';
                $lastAt = $b['last_backup_at'] ?? '';
                if ($status === 'failed') {
                    $stats['backup_warning']++;
                } elseif (!empty($lastAt)) {
                    $bts = strtotime($lastAt);
                    if ($bts > 0 && ($now - $bts) > ($maxBackupDays * DAY_IN_SECONDS)) {
                        $stats['backup_warning']++;
                    }
                }
            }
        }

        return $stats;
    }

    private function renderHeartbeatBadge(string $lastSeenAt): string
    {
        if (empty($lastSeenAt)) {
            return '<span class="laca-badge laca-badge--subtle" title="' . esc_attr__('Chưa nhận được heartbeat nào', 'laca') . '">'
                . esc_html__('Chưa thấy', 'laca') . '</span>';
        }

        $ts   = strtotime($lastSeenAt);
        $diff = time() - $ts;

        if ($diff < 2 * HOUR_IN_SECONDS) {
            $class = 'laca-badge--success';
        } elseif ($diff < 24 * HOUR_IN_SECONDS) {
            $class = 'laca-badge--warning';
        } else {
            $class = 'laca-badge--danger';
        }

        $formatted = human_time_diff($ts) . ' ' . __('trước', 'laca');
        return sprintf(
            '<span class="laca-badge %s" title="%s">%s</span>',
            esc_attr($class),
            esc_attr($lastSeenAt),
            esc_html($formatted)
        );
    }

    /**
     * @param array<string, mixed> $backup
     * @return array{html: string, filter_key: string}
     */
    private function formatBackupInfo(array $backup): array
    {
        if (empty($backup)) {
            return [
                'html'       => '<span class="laca-badge laca-badge--subtle">' . esc_html__('Chưa rõ', 'laca') . '</span>',
                'filter_key' => 'unknown',
            ];
        }

        $plugin = !empty($backup['plugin']) ? (string) $backup['plugin'] : 'none';
        $status = !empty($backup['status']) ? (string) $backup['status'] : 'unknown';
        $lastAt = !empty($backup['last_backup_at']) ? (string) $backup['last_backup_at'] : '';

        $maxDays = function_exists('carbon_get_theme_option') ? (int) carbon_get_theme_option('laca_backup_max_age_days') : 7;
        if ($maxDays <= 0) {
            $maxDays = 7;
        }

        $statusBadge = '';
        $filterKey   = 'unknown';

        if ($status === 'success') {
            if (!empty($lastAt)) {
                $ts = strtotime($lastAt);
                if ($ts > 0 && (time() - $ts) > ($maxDays * DAY_IN_SECONDS)) {
                    $statusBadge = '<span class="laca-badge laca-badge--warning">' . esc_html__('Quá hạn', 'laca') . '</span>';
                    $filterKey   = 'warning';
                } else {
                    $statusBadge = '<span class="laca-badge laca-badge--success">' . esc_html__('Tốt', 'laca') . '</span>';
                    $filterKey   = 'ok';
                }
            } else {
                $statusBadge = '<span class="laca-badge laca-badge--success">' . esc_html__('Tốt', 'laca') . '</span>';
                $filterKey   = 'ok';
            }
        } elseif ($status === 'failed') {
            $statusBadge = '<span class="laca-badge laca-badge--danger">' . esc_html__('Lỗi', 'laca') . '</span>';
            $filterKey   = 'warning';
        } else {
            $statusBadge = '<span class="laca-badge laca-badge--subtle">' . esc_html__('Chưa rõ', 'laca') . '</span>';
            $filterKey   = 'unknown';
        }

        $dateHtml = '';
        if (!empty($lastAt)) {
            $ts = strtotime($lastAt);
            if ($ts > 0) {
                $dateHtml = '<br><small style="color:#64748b;">' . esc_html(wp_date('d/m/Y H:i', $ts)) . ' (' . human_time_diff($ts) . ' ' . esc_html__('trước', 'laca') . ')</small>';
            }
        }

        $pluginHtml = '<span>' . esc_html($plugin !== 'none' ? $plugin : __('Không phát hiện', 'laca')) . '</span> ' . $statusBadge;

        return [
            'html'       => $pluginHtml . $dateHtml,
            'filter_key' => $filterKey,
        ];
    }

    /**
     * @return array{key: string, badge: string}
     */
    private function determineAuthMode(int $signedLastSeen, int $legacyLastSeen): array
    {
        if ($signedLastSeen > 0 && $signedLastSeen >= $legacyLastSeen) {
            return [
                'key'   => 'signed',
                'badge' => '<span class="laca-badge laca-badge--success" title="' . esc_attr(sprintf(__('Ký HMAC lần cuối: %s', 'laca'), wp_date('d/m/Y H:i', $signedLastSeen))) . '">signed</span>',
            ];
        }

        if ($legacyLastSeen > 0) {
            return [
                'key'   => 'legacy',
                'badge' => '<span class="laca-badge laca-badge--warning" title="' . esc_attr(sprintf(__('Dùng Legacy key lần cuối: %s', 'laca'), wp_date('d/m/Y H:i', $legacyLastSeen))) . '">legacy</span>',
            ];
        }

        return [
            'key'   => 'none',
            'badge' => '<span class="laca-badge laca-badge--subtle">' . esc_html__('chưa thấy', 'laca') . '</span>',
        ];
    }
}
