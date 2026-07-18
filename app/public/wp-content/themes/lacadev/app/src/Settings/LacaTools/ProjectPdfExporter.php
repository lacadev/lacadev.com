<?php

namespace App\Settings\LacaTools;

class ProjectPdfExporter
{
    public function init(): void
    {
        // Bắt request xuất PDF
        add_action('template_redirect', [$this, 'handleExportRequest']);
        
        // Thêm meta box hành động báo giá vào màn hình edit Project
        add_action('add_meta_boxes', [$this, 'registerActionMetaBox']);
    }

    public function registerActionMetaBox(): void
    {
        add_meta_box(
            'laca_project_pdf_actions',
            __('📄 Báo giá / Invoice', 'laca'),
            [$this, 'renderActionMetaBox'],
            'project',
            'side',
            'high'
        );
    }

    public function renderActionMetaBox(\WP_Post $post): void
    {
        if ($post->post_status === 'auto-draft') {
            echo '<p>Vui lòng <strong>Lưu nháp</strong> hoặc <strong>Đăng</strong> dự án trước khi xuất báo giá.</p>';
            return;
        }

        $exportUrl = home_url('/?laca_export_quote=' . $post->ID);
        echo '<a href="' . esc_url($exportUrl) . '" target="_blank" class="button button-primary button-large" style="width:100%; text-align:center; padding: 10px 0; font-size: 15px; height: auto;">Xuất PDF Báo Giá</a>';
        echo '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:12px 14px;margin:10px 0 0"><p style="margin:0;font-size:13px;color:#374151">PDF tạo ra là bản báo giá chính thức (bảng giá, checklist tính năng, thông tin khách hàng) — dùng để gửi cho khách hàng xem và ký duyệt trước khi triển khai.</p></div>';
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
}
