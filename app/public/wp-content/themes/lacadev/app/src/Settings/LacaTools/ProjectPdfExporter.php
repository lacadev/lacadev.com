<?php

namespace App\Settings\LacaTools;

class ProjectPdfExporter
{
    public function init(): void
    {
        // Bắt request xuất PDF
        add_action('template_redirect', [$this, 'handleExportRequest']);
        add_action('template_redirect', [$this, 'handleMonthlyReportExportRequest']);
        add_action('admin_post_laca_send_monthly_report', [$this, 'handleAdminPostSendMonthlyReport']);
        
        // Thêm meta box hành động báo giá vào màn hình edit Project
        add_action('add_meta_boxes', [$this, 'registerActionMetaBox']);
        add_action('admin_notices', [$this, 'renderAdminNotice']);
    }

    public function renderAdminNotice(): void
    {
        if (isset($_GET['laca_report_sent']) && $_GET['laca_report_sent'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Đã gửi email báo cáo tháng thành công tới khách hàng.', 'laca') . '</p></div>';
        }
    }

    public function registerActionMetaBox(): void
    {
        add_meta_box(
            'laca_project_pdf_actions',
            __('📄 Báo giá & Báo cáo', 'laca'),
            [$this, 'renderActionMetaBox'],
            'project',
            'side',
            'high'
        );
    }

    public function renderActionMetaBox(\WP_Post $post): void
    {
        if ($post->post_status === 'auto-draft') {
            echo '<p>' . esc_html__('Vui lòng Lưu nháp hoặc Đăng dự án trước khi xuất báo giá.', 'laca') . '</p>';
            return;
        }

        $exportUrl = home_url('/?laca_export_quote=' . $post->ID);
        echo '<a href="' . esc_url($exportUrl) . '" target="_blank" class="laca-btn laca-btn--solid laca-btn--md laca-pdf-export-btn button button-primary">' . esc_html__('Xuất PDF Báo Giá', 'laca') . '</a>';
        echo '<div class="laca-alert laca-alert--info laca-pdf-alert" role="status"><div class="laca-alert__content"><p class="laca-alert__description">' . esc_html__('PDF tạo ra là bản báo giá chính thức dùng để gửi cho khách hàng xem và duyệt trước khi triển khai.', 'laca') . '</p></div></div>';

        $reportUrl = home_url('/?laca_export_monthly_report=' . $post->ID);
        $sendNonce = wp_create_nonce('laca_send_monthly_report_' . $post->ID);
        $sendUrl   = admin_url('admin-post.php?action=laca_send_monthly_report&project_id=' . $post->ID . '&_wpnonce=' . $sendNonce);

        echo '<div class="laca-pdf-meta-section">';
        echo '<h4 class="laca-pdf-meta-title">📊 ' . esc_html__('Báo cáo vận hành tháng', 'laca') . '</h4>';
        echo '<div class="laca-pdf-btn-row">';
        echo '<a href="' . esc_url($reportUrl) . '" target="_blank" class="laca-btn laca-btn--outline laca-btn--sm button button-secondary">' . esc_html__('Xem trước', 'laca') . '</a>';
        echo '<a href="' . esc_url($sendUrl) . '" class="laca-btn laca-btn--outline laca-btn--sm button button-secondary" onclick="return confirm(\'' . esc_js(__('Bạn có chắc muốn gửi email báo cáo tháng này cho khách hàng?', 'laca')) . '\');">' . esc_html__('Gửi ngay', 'laca') . '</a>';
        echo '</div>';
        echo '<p class="laca-pdf-help">' . esc_html__('Tự động tổng hợp uptime, bảo mật, sao lưu và số liệu truy cập website.', 'laca') . '</p>';
        echo '</div>';
    }

    public function handleExportRequest(): void
    {
        if (!isset($_GET['laca_export_quote'])) {
            return;
        }

        if (!current_user_can('edit_posts')) {
            wp_die('Bạn không có quyền xem báo giá này.');
        }

        $projectId = absint($_GET['laca_export_quote']);
        $post = get_post($projectId);

        if (!$post || $post->post_type !== 'project') {
            wp_die('Không tìm thấy dự án.');
        }

        $this->renderQuoteHtml($post);
        exit;
    }

    private function renderQuoteHtml(\WP_Post $post): void
    {
        $pid = $post->ID;
        
        // Data Extraction
        $clientName  = carbon_get_post_meta($pid, 'client_name') ?: 'Khách hàng';
        $clientPhone = carbon_get_post_meta($pid, 'client_phone') ?: '';
        $clientEmail = carbon_get_post_meta($pid, 'client_email') ?: '';
        
        $estimatedDays = carbon_get_post_meta($pid, 'estimated_days') ?: 'Theo thoả thuận';
        $demoDesignUrl = carbon_get_post_meta($pid, 'demo_design_url') ?: '';

        // Features & Specs mapping
        $platformList = carbon_get_post_meta($pid, 'platform') ?: [];
        $builderList  = carbon_get_post_meta($pid, 'builder') ?: [];
        $featuresList = carbon_get_post_meta($pid, 'features') ?: [];
        $customFeats  = carbon_get_post_meta($pid, 'custom_features') ?: [];

        $platformLabels = [
            'wordpress' => 'WordPress', 'woocommerce' => 'WooCommerce', 'landing_page' => 'Landing Page',
            'shopify' => 'Shopify', 'ecommerce' => 'E-commerce', 'blog' => 'Blog',
            'portfolio' => 'Portfolio', 'saas' => 'SaaS', 'booking' => 'Booking'
        ];
        $builderLabels = [
            'bricks' => 'Bricks Builder', 'gutenberg' => 'Gutenberg', 'elementor' => 'Elementor',
            'flatsome' => 'Flatsome', 'none' => 'Code thuần'
        ];
        $featureLabels = [
            'landing_page' => 'Landing Page', 'multi_language' => 'Multi-language', 'booking' => 'Booking System',
            'payment' => 'Payment Gateway', 'flash_sale' => 'Flash Sale', 'seo' => 'SEO Optimized',
            'speed' => 'High Speed', 'membership' => 'Membership', 'chat' => 'Live Chat'
        ];

        $getLabels = function($arr, $labels) {
            if (!$arr) return [];
            return array_map(function($key) use ($labels) { return $labels[$key] ?? $key; }, $arr);
        };

        $platformsStr = implode(', ', $getLabels($platformList, $platformLabels)) ?: 'Tùy chỉnh';
        $buildersStr  = implode(', ', $getLabels($builderList, $builderLabels)) ?: 'Cơ bản';
        
        $allFeatures = $getLabels($featuresList, $featureLabels);
        if (is_array($customFeats)) {
            foreach ($customFeats as $cf) {
                if (!empty($cf['name'])) $allFeatures[] = $cf['name'];
            }
        }
        $featuresStr = implode(', ', $allFeatures) ?: 'Các tính năng tiêu chuẩn';

        // Finance & Infrastructure
        $buildPrice    = carbon_get_post_meta($pid, 'price_build') ?: 0;
        $mainFee       = carbon_get_post_meta($pid, 'price_maintenance_yearly') ?: 0;
        $domainName    = carbon_get_post_meta($pid, 'domain_name') ?: '';
        $hostName      = carbon_get_post_meta($pid, 'hosting_provider') ?: '';
        $hostPrice     = carbon_get_post_meta($pid, 'hosting_price') ?: 0;

        // Maintenance
        $maintTypeArr = [
            'none' => 'Không có bảo trì',
            'free' => 'Bảo trì miễn phí',
            'paid' => 'Bảo trì có phí'
        ];
        $maintTypeVal  = carbon_get_post_meta($pid, 'maintenance_type') ?: 'free';
        $maintType     = $maintTypeArr[$maintTypeVal] ?? 'Theo hợp đồng';
        $maintTime     = carbon_get_post_meta($pid, 'maintenance_response_time') ?: '24 giờ';
        $maintScope    = carbon_get_post_meta($pid, 'maintenance_scope') ?: 'Hỗ trợ kỹ thuật, khắc phục lỗi phát sinh do hệ thống. Đảm bảo website hoạt động ổn định.';

        $formatPrice = function($val) {
            if (!$val || $val == '0') return 'Miễn phí';
            if (!is_numeric($val)) return esc_html($val);
            return number_format((float)$val, 0, ',', '.') . ' VNĐ';
        };

        $totalVal = (float)$buildPrice + (float)$hostPrice;

        $siteName = get_bloginfo('name');
        $siteEmail = get_option('admin_email');
        
        ?>
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <title>Báo giá - <?php echo esc_html($post->post_title); ?></title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
                :root {
                    --text: #111827;
                    --text-light: #4b5563;
                    --text-lighter: #9ca3af;
                    --border: #e5e7eb;
                }
                body {
                    font-family: 'Inter', sans-serif;
                    color: var(--text);
                    line-height: 1.6;
                    margin: 0;
                    padding: 0;
                    background: #f9fafb;
                    font-size: 14px;
                }
                .document {
                    max-width: 800px;
                    margin: 40px auto;
                    background: #fff;
                    padding: 60px;
                    border: 1px solid var(--border);
                    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
                }
                .flex-row { display: flex; justify-content: space-between; align-items: flex-start; }
                
                .header { border-bottom: 2px solid var(--text); padding-bottom: 24px; margin-bottom: 40px; }
                .title-area h1 { margin: 0 0 8px 0; font-size: 28px; font-weight: 700; letter-spacing: -0.5px; }
                .title-area .date { color: var(--text-light); }
                .brand-area { text-align: right; }
                .brand-area strong { font-size: 16px; }
                .brand-area p { margin: 4px 0 0; color: var(--text-light); }

                .section { margin-bottom: 36px; }
                h3.sec-title { font-size: 16px; margin: 0 0 16px 0; padding-bottom: 8px; border-bottom: 1px solid var(--border); text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-light); }
                
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
                .info-box h4 { margin: 0 0 8px 0; font-size: 15px; color: var(--text); }
                .info-box p { margin: 0 0 4px; color: var(--text-light); }
                .info-box strong { color: var(--text); font-weight: 500; }
                .info-box a { color: #2563eb; text-decoration: none; }

                .dl-list { margin: 0; padding: 0; }
                .dl-list li { list-style: none; margin-bottom: 8px; display: flex; }
                .dl-list li strong { width: 180px; flex-shrink: 0; color: var(--text); font-weight: 500; }
                .dl-list li span { color: var(--text-light); }
                
                table.minimal-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                table.minimal-table th { text-align: left; padding: 12px 8px; border-bottom: 2px solid var(--border); font-weight: 600; color: var(--text); }
                table.minimal-table td { padding: 16px 8px; border-bottom: 1px solid var(--border); vertical-align: top; }
                table.minimal-table td small { display: block; color: var(--text-light); margin-top: 4px; }
                .text-right { text-align: right !important; }
                
                .total-row td { font-weight: 700; font-size: 16px; padding-top: 20px; border-bottom: none; }
                .total-price { font-size: 20px; }

                .footer { margin-top: 60px; text-align: center; color: var(--text-lighter); font-size: 13px; }

                @media print {
                    body { background: #fff; }
                    .document { margin: 0; box-shadow: none; border: none; padding: 0; max-width: 100%; }
                    @page { margin: 1.5cm; }
                }
            </style>
        </head>
        <body>
            <div class="document">
                <div class="flex-row header">
                    <div class="title-area">
                        <h1>BÁO GIÁ DỊCH VỤ</h1>
                        <div class="date">Ngày lập: <?php echo date('d/m/Y'); ?> | Dự án: <strong><?php echo esc_html($post->post_title); ?></strong></div>
                    </div>
                    <div class="brand-area">
                        <strong><?php echo esc_html($siteName); ?></strong>
                        <p><?php echo esc_html($siteEmail); ?></p>
                    </div>
                </div>

                <div class="section info-grid">
                    <div class="info-box">
                        <h4 style="color: var(--text-lighter); text-transform: uppercase; font-size: 12px; letter-spacing: 1px; margin-bottom: 12px;">Thông tin khách hàng</h4>
                        <p><strong><?php echo esc_html($clientName); ?></strong></p>
                        <?php if ($clientPhone) echo "<p>Phone: " . esc_html($clientPhone) . "</p>"; ?>
                        <?php if ($clientEmail) echo "<p>Email: " . esc_html($clientEmail) . "</p>"; ?>
                    </div>
                    <div class="info-box">
                        <h4 style="color: var(--text-lighter); text-transform: uppercase; font-size: 12px; letter-spacing: 1px; margin-bottom: 12px;">Chi tiết lập trình</h4>
                        <p><strong>Thời gian dự kiến:</strong> <?php echo esc_html($estimatedDays); ?> ngày làm việc<br><small style="color: #9ca3af;">(không kể Thứ 7, Chủ Nhật)</small></p>
                        <?php if ($demoDesignUrl): ?>
                            <p style="margin-top: 8px;"><strong>Mẫu / Design:</strong> <a href="<?php echo esc_url($demoDesignUrl); ?>" target="_blank">Xem mẫu tham khảo</a></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="section">
                    <h3 class="sec-title">1. Công nghệ & Chức năng</h3>
                    <ul class="dl-list">
                        <li><strong>Nền tảng:</strong> <span><?php echo esc_html($platformsStr); ?></span></li>
                        <li><strong>Công cụ xây dựng:</strong> <span><?php echo esc_html($buildersStr); ?></span></li>
                        <li><strong>Tính năng chính:</strong> <span><?php echo esc_html($featuresStr); ?></span></li>
                    </ul>
                </div>

                <div class="section">
                    <h3 class="sec-title">2. Bảng giá chi phí</h3>
                    <table class="minimal-table">
                        <thead>
                            <tr>
                                <th>Hạng mục thực hiện</th>
                                <th class="text-right">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>Thiết kế & Lập trình Website</strong>
                                    <small>Chi phí xây dựng hệ thống theo yêu cầu tính năng tiêu chuẩn đã đề ra.</small>
                                </td>
                                <td class="text-right"><?php echo $formatPrice($buildPrice); ?></td>
                            </tr>
                            <?php if ($domainName): ?>
                            <tr>
                                <td>
                                    <strong>Tên miền (Domain)</strong>
                                    <small><?php echo esc_html($domainName); ?> (duy trì gia hạn hàng năm)</small>
                                </td>
                                <td class="text-right">Khách tự quản lý</td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($hostName): ?>
                            <tr>
                                <td>
                                    <strong>Hạ tầng Máy chủ (Hosting/VPS)</strong>
                                    <small>Gói tự chọn: <?php echo esc_html($hostName); ?> (duy trì gia hạn hàng năm)</small>
                                </td>
                                <td class="text-right"><?php echo $formatPrice($hostPrice); ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td>TỔNG CỘNG</td>
                                <td class="text-right total-price"><?php echo $formatPrice($totalVal); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="section">
                    <h3 class="sec-title">3. Cam kết Bảo hành & Bảo trì</h3>
                    <ul class="dl-list">
                        <li><strong>Chính sách:</strong> <span><?php echo esc_html($maintType); ?></span></li>
                        <li><strong>Phí bảo trì (Sau năm đầu):</strong> <span><?php echo $formatPrice($mainFee); ?> / năm</span></li>
                        <li><strong>Thời gian phản hồi:</strong> <span><?php echo esc_html($maintTime); ?></span></li>
                        <li style="display: block; margin-top: 12px;">
                            <strong>Phạm vi cam kết:</strong>
                            <div style="margin-top: 6px; color: var(--text-light); line-height: 1.6;"><?php echo wpautop(wp_kses_post($maintScope)); ?></div>
                        </li>
                    </ul>
                </div>

                <div class="footer">
                    <p>Báo giá này có giá trị trong vòng 15 ngày kể từ ngày ban hành.</p>
                    <p>Cảm ơn quý khách đã tin tưởng và đồng hành cùng <?php echo esc_html($siteName); ?>.</p>
                </div>
            </div>
            <script>window.onload = function() { window.print(); }; </script>
        </body>
        </html>
        <?php
    }

    public function handleMonthlyReportExportRequest(): void
    {
        if (!isset($_GET['laca_export_monthly_report'])) {
            return;
        }

        if (!current_user_can('edit_posts')) {
            wp_die(__('Bạn không có quyền xem báo cáo này.', 'laca'));
        }

        $projectId = absint($_GET['laca_export_monthly_report']);
        $post      = get_post($projectId);

        if (!$post || $post->post_type !== 'project') {
            wp_die(__('Không tìm thấy dự án.', 'laca'));
        }

        $month = sanitize_text_field((string) ($_GET['month'] ?? ''));
        $this->renderMonthlyReportHtml($post, $month);
        exit;
    }

    public function handleAdminPostSendMonthlyReport(): void
    {
        $projectId = absint($_GET['project_id'] ?? 0);
        check_admin_referer('laca_send_monthly_report_' . $projectId);

        if (!current_user_can('edit_posts')) {
            wp_die(__('Bạn không có quyền thực hiện thao tác này.', 'laca'));
        }

        $month = sanitize_text_field((string) ($_GET['month'] ?? ''));
        $sent  = $this->sendMonthlyReportEmail($projectId, $month);

        $redirect = add_query_arg([
            'post'             => $projectId,
            'action'           => 'edit',
            'laca_report_sent' => $sent ? '1' : '0',
        ], admin_url('post.php'));

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Render trang HTML báo cáo vận hành tháng (Printable / PDF)
     */
    public function renderMonthlyReportHtml(\WP_Post $post, string $month = ''): void
    {
        $pid = $post->ID;
        if (empty($month)) {
            $month = gmdate('Y-m', strtotime('-1 month'));
        }

        $clientName  = (string) (carbon_get_post_meta($pid, 'client_name') ?: __('Quý khách hàng', 'laca'));
        $clientEmail = (string) (carbon_get_post_meta($pid, 'client_email') ?: '');
        $liveUrl     = (string) (carbon_get_post_meta($pid, 'live_url') ?: get_permalink($pid));
        $siteName    = get_bloginfo('name') ?: 'LacaDev';

        // Lấy số liệu Uptime 30 ngày
        $uptimeStats = class_exists(\App\Databases\UptimeLogTable::class)
            ? \App\Databases\UptimeLogTable::getUptimeStats($pid, 30)
            : ['percentage' => 100.0, 'avg_ms' => 0];
        $uptimePct = (float) ($uptimeStats['percentage'] ?? ($uptimeStats['uptime_percentage'] ?? 100.0));
        $avgMs     = (int) ($uptimeStats['avg_ms'] ?? ($uptimeStats['avg_response_ms'] ?? 0));

        // Lấy số liệu sao lưu
        $lastBackup = get_post_meta($pid, '_tracker_last_backup', true);
        $backupText = !empty($lastBackup['time'])
            ? gmdate('d/m/Y H:i', (int) $lastBackup['time'])
            : __('Chưa có dữ liệu', 'laca');

        // Lấy dữ liệu báo cáo client gửi về
        $summary = get_post_meta($pid, '_laca_monthly_summary_' . $month, true);
        if (empty($summary) || !is_array($summary)) {
            $summary = get_post_meta($pid, '_laca_last_monthly_summary', true) ?: [];
        }

        $postsNew  = (int) ($summary['posts_new_count'] ?? 0);
        $pagesNew  = (int) ($summary['pages_new_count'] ?? 0);
        $secEvents = (int) ($summary['security_events_count'] ?? 0);
        $ga4Data   = $summary['ga4'] ?? null;
        $gscData   = $summary['gsc'] ?? null;

        // Đếm cập nhật/deploy trong tháng
        global $wpdb;
        $logTable    = \App\Databases\ProjectLogTable::getTableName();
        $deployCount = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$logTable}
             WHERE project_id = %d AND log_type IN ('deployment', 'plugin_update', 'core_update')
               AND created_at LIKE %s",
            $pid,
            $month . '%'
        ));

        ?>
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html(sprintf(__('Báo cáo vận hành tháng %1$s - %2$s', 'laca'), $month, $post->post_title)); ?></title>
            <style>
                :root {
                    --primary: #0284c7;
                    --text: #0f172a;
                    --text-light: #475569;
                    --border: #e2e8f0;
                    --bg-light: #f8fafc;
                    --success: #16a34a;
                    --warning: #d97706;
                    --danger: #dc2626;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    background: #f1f5f9;
                    color: var(--text);
                    margin: 0;
                    padding: 24px;
                    line-height: 1.5;
                }
                .report-page {
                    max-width: 860px;
                    margin: 0 auto;
                    background: #ffffff;
                    padding: 40px;
                    border-radius: 10px;
                    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
                }
                .header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    border-bottom: 2px solid var(--border);
                    padding-bottom: 20px;
                    margin-bottom: 28px;
                }
                .brand {
                    font-size: 24px;
                    font-weight: 800;
                    color: var(--primary);
                    letter-spacing: -0.5px;
                }
                .meta-badge {
                    background: var(--bg-light);
                    border: 1px solid var(--border);
                    padding: 6px 14px;
                    border-radius: 9999px;
                    font-size: 13px;
                    font-weight: 600;
                    color: var(--text-light);
                }
                .grid-kpi {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 16px;
                    margin-bottom: 28px;
                }
                .kpi-card {
                    background: var(--bg-light);
                    border: 1px solid var(--border);
                    border-radius: 8px;
                    padding: 16px;
                    text-align: center;
                }
                .kpi-label {
                    font-size: 12px;
                    font-weight: 600;
                    color: var(--text-light);
                    text-transform: uppercase;
                    margin-bottom: 6px;
                }
                .kpi-val {
                    font-size: 24px;
                    font-weight: 800;
                    color: var(--text);
                }
                .kpi-val.success { color: var(--success); }
                .section-title {
                    font-size: 16px;
                    font-weight: 700;
                    margin: 24px 0 12px;
                    padding-left: 10px;
                    border-left: 4px solid var(--primary);
                }
                .table-data {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                .table-data th, .table-data td {
                    padding: 10px 12px;
                    border: 1px solid var(--border);
                    font-size: 13px;
                    text-align: left;
                }
                .table-data th {
                    background: var(--bg-light);
                    font-weight: 600;
                    color: var(--text-light);
                }
                .footer {
                    margin-top: 36px;
                    padding-top: 16px;
                    border-top: 1px solid var(--border);
                    font-size: 12px;
                    color: var(--text-light);
                    display: flex;
                    justify-content: space-between;
                }
                @media print {
                    body { background: #fff; padding: 0; }
                    .report-page { box-shadow: none; padding: 20px 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="report-page">
                <div class="header">
                    <div>
                        <div class="brand"><?php echo esc_html($siteName); ?></div>
                        <div style="font-size: 13px; color: var(--text-light); margin-top: 4px;">
                            <?php echo esc_html(sprintf(__('Báo cáo vận hành website định kỳ — Tháng %s', 'laca'), $month)); ?>
                        </div>
                    </div>
                    <div class="meta-badge">
                        <?php echo esc_html($post->post_title); ?>
                    </div>
                </div>

                <div class="grid-kpi">
                    <div class="kpi-card">
                        <div class="kpi-label"><?php echo esc_html__('Uptime 30 ngày', 'laca'); ?></div>
                        <div class="kpi-val success"><?php echo esc_html(number_format($uptimePct, 1)); ?>%</div>
                        <small style="font-size: 11px; color: var(--text-light);"><?php echo esc_html(sprintf(__('Độ trễ %dms', 'laca'), $avgMs)); ?></small>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label"><?php echo esc_html__('Cập nhật/Deploy', 'laca'); ?></div>
                        <div class="kpi-val"><?php echo esc_html((string) $deployCount); ?></div>
                        <small style="font-size: 11px; color: var(--text-light);"><?php echo esc_html__('Lần nâng cấp hệ thống', 'laca'); ?></small>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label"><?php echo esc_html__('Nội dung mới', 'laca'); ?></div>
                        <div class="kpi-val"><?php echo esc_html((string) ($postsNew + $pagesNew)); ?></div>
                        <small style="font-size: 11px; color: var(--text-light);"><?php echo esc_html(sprintf(__('%1$d bài / %2$d trang', 'laca'), $postsNew, $pagesNew)); ?></small>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label"><?php echo esc_html__('An ninh bảo mật', 'laca'); ?></div>
                        <div class="kpi-val success"><?php echo esc_html__('An toàn', 'laca'); ?></div>
                        <small style="font-size: 11px; color: var(--text-light);"><?php echo esc_html(sprintf(__('%d sự kiện ghi nhận', 'laca'), $secEvents)); ?></small>
                    </div>
                </div>

                <h3 class="section-title"><?php echo esc_html__('1. Tình trạng Hạ tầng & Kỹ thuật', 'laca'); ?></h3>
                <table class="table-data">
                    <tr>
                        <td width="30%"><strong><?php echo esc_html__('Địa chỉ website', 'laca'); ?></strong></td>
                        <td><a href="<?php echo esc_url($liveUrl); ?>" target="_blank"><?php echo esc_html($liveUrl); ?></a></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Bản sao lưu gần nhất', 'laca'); ?></strong></td>
                        <td><?php echo esc_html($backupText); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php echo esc_html__('Khách hàng đại diện', 'laca'); ?></strong></td>
                        <td><?php echo esc_html($clientName . ($clientEmail ? " ({$clientEmail})" : '')); ?></td>
                    </tr>
                </table>

                <?php if (!empty($ga4Data) || !empty($gscData)): ?>
                <h3 class="section-title"><?php echo esc_html__('2. Hiệu suất Truy cập & Tìm kiếm (Google)', 'laca'); ?></h3>
                <table class="table-data">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Chỉ số', 'laca'); ?></th>
                            <th><?php echo esc_html__('Kết quả trong tháng', 'laca'); ?></th>
                            <th><?php echo esc_html__('Nguồn dữ liệu', 'laca'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($ga4Data['sessions'])): ?>
                        <tr>
                            <td><?php echo esc_html__('Lượt truy cập (Sessions)', 'laca'); ?></td>
                            <td><strong><?php echo esc_html(number_format((int) $ga4Data['sessions'])); ?></strong></td>
                            <td>Google Analytics 4</td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($ga4Data['users'])): ?>
                        <tr>
                            <td><?php echo esc_html__('Người dùng (Users)', 'laca'); ?></td>
                            <td><strong><?php echo esc_html(number_format((int) $ga4Data['users'])); ?></strong></td>
                            <td>Google Analytics 4</td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($gscData['clicks'])): ?>
                        <tr>
                            <td><?php echo esc_html__('Lượt nhấp từ Google (Clicks)', 'laca'); ?></td>
                            <td><strong><?php echo esc_html(number_format((int) $gscData['clicks'])); ?></strong></td>
                            <td>Google Search Console</td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($gscData['impressions'])): ?>
                        <tr>
                            <td><?php echo esc_html__('Lượt hiển thị tìm kiếm (Impressions)', 'laca'); ?></td>
                            <td><strong><?php echo esc_html(number_format((int) $gscData['impressions'])); ?></strong></td>
                            <td>Google Search Console</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <div class="footer">
                    <div><?php echo esc_html(sprintf(__('Báo cáo được lập tự động bởi hệ thống %s.', 'laca'), $siteName)); ?></div>
                    <div><?php echo esc_html(sprintf(__('Ngày xuất: %s', 'laca'), current_time('d/m/Y H:i'))); ?></div>
                </div>
            </div>
            <script>window.onload = function() { window.print(); };</script>
        </body>
        </html>
        <?php
    }

    /**
     * Gửi email báo cáo tháng tới khách hàng
     */
    public function sendMonthlyReportEmail(int $projectId, string $month = ''): bool
    {
        $post = get_post($projectId);
        if (!$post || $post->post_type !== 'project') {
            return false;
        }

        if (empty($month)) {
            $month = gmdate('Y-m', strtotime('-1 month'));
        }

        $clientEmail = (string) carbon_get_post_meta($projectId, 'monthly_report_email');
        if (empty($clientEmail)) {
            $clientEmail = (string) carbon_get_post_meta($projectId, 'client_email');
        }
        if (empty($clientEmail)) {
            $clientEmail = (string) (get_post_meta($projectId, 'monthly_report_email', true) ?: get_post_meta($projectId, '_monthly_report_email', true));
        }
        if (empty($clientEmail)) {
            $clientEmail = (string) (get_post_meta($projectId, 'client_email', true) ?: get_post_meta($projectId, '_client_email', true));
        }

        if (empty($clientEmail) || !is_email($clientEmail)) {
            return false;
        }

        $siteName    = get_bloginfo('name') ?: 'LacaDev';
        $reportUrl   = home_url('/?laca_export_monthly_report=' . $projectId . '&month=' . urlencode($month));
        $uptimeStats = class_exists(\App\Databases\UptimeLogTable::class)
            ? \App\Databases\UptimeLogTable::getUptimeStats($projectId, 30)
            : ['percentage' => 100.0, 'avg_ms' => 0];
        $uptimePct   = (float) ($uptimeStats['percentage'] ?? ($uptimeStats['uptime_percentage'] ?? 100.0));
        $avgMs       = (int) ($uptimeStats['avg_ms'] ?? ($uptimeStats['avg_response_ms'] ?? 0));

        $subject = sprintf(
            /* translators: 1: tên thương hiệu, 2: tên dự án, 3: tháng */
            __('[%1$s] Báo cáo vận hành website %2$s — Tháng %3$s', 'laca'),
            $siteName,
            $post->post_title,
            $month
        );

        $message = '<html><body style="font-family:sans-serif;color:#1e293b;line-height:1.6;padding:20px;">';
        $message .= '<div style="max-width:600px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:24px;">';
        $message .= '<h2 style="color:#0284c7;margin-top:0;">' . esc_html($subject) . '</h2>';
        $message .= '<p>' . sprintf(
            /* translators: 1: tên dự án, 2: tháng */
            esc_html__('Kính gửi Quý khách hàng, hệ thống %1$s xin gửi tới quý khách tóm tắt kết quả vận hành website %2$s trong tháng %3$s:', 'laca'),
            esc_html($siteName),
            '<strong>' . esc_html($post->post_title) . '</strong>',
            esc_html($month)
        ) . '</p>';
        $message .= '<ul style="background:#f8fafc;padding:16px 24px;border-radius:6px;border:1px solid #e2e8f0;">';
        $message .= '<li>' . sprintf(esc_html__('Độ sẵn sàng (Uptime 30 ngày): %s%%', 'laca'), '<strong>' . number_format($uptimePct, 1) . '</strong>') . '</li>';
        $message .= '<li>' . sprintf(esc_html__('Thời gian phản hồi máy chủ trung bình: %dms', 'laca'), $avgMs) . '</li>';
        $message .= '<li>' . esc_html__('Hệ thống mã nguồn & an ninh bảo mật: Hoạt động ổn định, an toàn.', 'laca') . '</li>';
        $message .= '</ul>';
        $message .= '<div style="text-align:center;margin:28px 0;">';
        $message .= '<a href="' . esc_url($reportUrl) . '" target="_blank" style="background:#0284c7;color:#fff;text-decoration:none;padding:12px 24px;border-radius:6px;font-weight:600;display:inline-block;">' . esc_html__('Xem Báo Cáo Chi Tiết & Tải PDF', 'laca') . '</a>';
        $message .= '</div>';
        $message .= '<p style="font-size:12px;color:#64748b;">' . esc_html__('Nếu có bất kỳ thắc mắc hoặc yêu cầu hỗ trợ kỹ thuật nào, quý khách vui lòng liên hệ đội ngũ chúng tôi qua tính năng Hỗ trợ trên website hoặc phản hồi email này.', 'laca') . '</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $sent    = wp_mail($clientEmail, $subject, $message, $headers);

        if ($sent) {
            \App\Models\ProjectLog::add([
                'project_id'  => $projectId,
                'log_type'    => 'monthly_summary',
                'log_content' => sprintf(
                    /* translators: 1: tháng, 2: email người nhận */
                    __('✉️ Đã gửi email báo cáo tháng %1$s tới %2$s', 'laca'),
                    $month,
                    $clientEmail
                ),
                'log_by'      => 'Report Service',
                'is_auto'     => true,
            ]);
        }

        return (bool) $sent;
    }
}
