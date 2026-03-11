<?php

namespace App\PostTypes;

use Carbon_Fields\Container\Container;
use Carbon_Fields\Field;
use App\Models\ProjectLog;
use App\Models\ProjectAlert;

class Project extends \App\Abstracts\AbstractPostType
{

    public function __construct()
    {
        $this->showThumbnailOnList = true;
        $this->supports            = [
            'title',
            'editor',
            'thumbnail',
            'excerpt',
            'page-attributes',
            'comments',
        ];

        $this->menuIcon         = 'dashicons-layout';
        $this->post_type        = 'project';
        $this->singularName     = $this->pluralName = __('Project', 'laca');
        $this->titlePlaceHolder = __('Tên dự án / website', 'laca');
        $this->slug             = 'projects';
        parent::__construct();

        // Custom admin columns cho danh sách project
        add_filter('manage_project_posts_columns', [$this, 'addListColumns']);
        add_action('manage_project_posts_custom_column', [$this, 'renderListColumn'], 10, 2);
        add_filter('manage_edit-project_sortable_columns', [$this, 'sortableColumns']);

        // Filter theo status
        add_action('restrict_manage_posts', [$this, 'addStatusFilter']);
        add_filter('parse_query', [$this, 'filterByStatus']);

        // AJAX: resolve alert
        add_action('wp_ajax_laca_resolve_alert', [$this, 'ajaxResolveAlert']);

        // AJAX: xoá log
        add_action('wp_ajax_laca_delete_log', [$this, 'ajaxDeleteLog']);
        
        // AJAX: thêm log
        add_action('wp_ajax_laca_add_log', [$this, 'ajaxAddLog']);
        
        // AJAX: thêm alert
        add_action('wp_ajax_laca_add_alert', [$this, 'ajaxAddAlert']);

        // Thêm custom meta box cho Logs & Alerts
        add_action('add_meta_boxes', [$this, 'registerLogsMetaBox']);

        // Mã hóa mật khẩu khi lưu
        add_filter('carbon_fields_post_meta_value_save', [$this, 'encryptPasswordsOnSave'], 10, 4);
        
        // Giải mã mật khẩu khi load lên admin (hiển thị trong textbox)
        add_filter('carbon_fields_post_meta_value_load', [$this, 'decryptPasswordsOnLoad'], 10, 4);

        // Format tiền tệ (Lưu dạng số, hiển thị dạng 1.000.000)
        add_filter('carbon_fields_post_meta_value_save', [$this, 'formatCurrencyOnSave'], 11, 4);
        add_filter('carbon_fields_post_meta_value_load', [$this, 'formatCurrencyOnLoad'], 11, 4);
        add_action('admin_footer', [$this, 'addCurrencyFormatterScript']);

        // Tự động tính toán Payment Status
        // Priority 9999: đảm bảo chạy SAU KHI Carbon Fields đã lưu xong tất cả meta
        add_action('save_post_project', [$this, 'autoCalculatePaymentStatus'], 9999, 2);
    }

    public function encryptPasswordsOnSave($value, $name, $id, $type)
    {
        $encryptedFields = ['_domain_password', '_hosting_password', '_ftp_password', '_db_password'];
        if (in_array($name, $encryptedFields, true) && !empty($value)) {
            if (!\App\Helpers\Crypto::isEncrypted($value)) {
                return \App\Helpers\Crypto::encrypt($value);
            }
        }
        return $value;
    }

    public function decryptPasswordsOnLoad($value, $name, $id, $type)
    {
        if (empty($value)) return $value;

        $encryptedFields = ['_domain_password', '_hosting_password', '_ftp_password', '_db_password'];
        if (in_array($name, $encryptedFields, true) && \App\Helpers\Crypto::isEncrypted($value)) {
            return \App\Helpers\Crypto::decrypt($value);
        }
        return $value;
    }

    // =========================================================================
    // CARBON FIELDS META BOXES
    // =========================================================================

    public function metaFields()
    {
        $container = Container::make('post_meta', __('⚙️ Quản lý Dự án | Project Manager', 'laca'))
            ->where('post_type', '=', $this->post_type);

        // ---- Tab 1: Báo giá ----
        $container->add_tab(__('📄 Báo giá', 'laca'), [
            Field::make('separator', 'sep_quotation_intro', __('I. Giới thiệu', 'laca')),

            Field::make('rich_text', 'quotation_intro', __('Nội dung giới thiệu', 'laca'))
                ->set_help_text('Ví dụ: "mooms.dev xin gửi đến Quý khách báo giá chi tiết về việc xây dựng website cho [tên khách]."'),

            Field::make('separator', 'sep_design_pages', __('II. Phạm vi công việc — Danh sách trang thiết kế', 'laca')),

            Field::make('complex', 'design_pages', __('Danh sách trang thiết kế', 'laca'))
                ->setup_labels([
                    'plural_name'   => 'Trang',
                    'singular_name' => 'Trang',
                ])
                ->add_fields([
                    Field::make('text', 'page_name', __('Tên trang', 'laca'))
                        ->set_width(40)
                        ->set_attribute('placeholder', 'Trang chủ'),
                    Field::make('text', 'page_demo_url', __('Website mẫu (URL)', 'laca'))
                        ->set_width(60)
                        ->set_attribute('placeholder', 'https://...'),
                ])
                ->set_header_template('<% if (page_name) { %><%-page_name%><% } else { %>Trang mới<% } %>'),

            Field::make('rich_text', 'backend_features', __('Tính năng kỹ thuật / Lập trình Backend', 'laca'))
                ->set_help_text('Mô tả các module, tính năng kỹ thuật chi tiết (CMS, SEO, tích hợp mạng xã hội, v.v.)'),

            Field::make('separator', 'sep_timeline_phases', __('III. Thời gian thực hiện — Các giai đoạn', 'laca')),

            Field::make('complex', 'timeline_phases', __('Giai đoạn thực hiện', 'laca'))
                ->setup_labels([
                    'plural_name'   => 'Giai đoạn',
                    'singular_name' => 'Giai đoạn',
                ])
                ->add_fields([
                    Field::make('text', 'phase_name', __('Tên giai đoạn', 'laca'))
                        ->set_width(40)
                        ->set_attribute('placeholder', 'Giai đoạn 1'),
                    Field::make('text', 'phase_days', __('Số ngày', 'laca'))
                        ->set_width(20)
                        ->set_attribute('placeholder', '10'),
                    Field::make('rich_text', 'phase_content', __('Nội dung công việc', 'laca'))
                        ->set_width(40),
                ])
                ->set_header_template('<% if (phase_name) { %><%-phase_name%><% if (phase_days) { %> (<%-phase_days%> ngày)<% } %><% } %>'),

            Field::make('separator', 'sep_quotation_items', __('IV. Chi phí thực hiện', 'laca')),

            Field::make('complex', 'quotation_items', __('Bảng chi phí chi tiết', 'laca'))
                ->setup_labels([
                    'plural_name'   => 'Hạng mục',
                    'singular_name' => 'Hạng mục',
                ])
                ->add_fields([
                    Field::make('text', 'item_name', __('Mô tả hạng mục', 'laca'))
                        ->set_width(40)
                        ->set_attribute('placeholder', 'Thiết kế giao diện website'),
                    Field::make('text', 'item_unit_price', __('Đơn giá', 'laca'))
                        ->set_width(20)
                        ->set_attribute('data-type', 'currency')
                        ->set_attribute('placeholder', '14.000.000')
                        ->set_classes('laca-pay-amount'),
                    Field::make('text', 'item_qty', __('Số lượng', 'laca'))
                        ->set_width(10)
                        ->set_attribute('placeholder', '1'),
                    Field::make('text', 'item_note', __('Thành tiền / Ghi chú', 'laca'))
                        ->set_width(30)
                        ->set_attribute('placeholder', 'Miễn phí năm đầu / theo yêu cầu'),
                ])
                ->set_header_template('<% if (item_name) { %><%-item_name%><% } %>'),

            Field::make('separator', 'sep_workflow', __('V. Quy trình làm việc & VII. Yêu cầu khách hàng', 'laca')),

            Field::make('rich_text', 'workflow_steps', __('Quy trình làm việc', 'laca'))
                ->set_help_text('Ví dụ: Ứng 50% trước → Xây dựng demo → Chỉnh sửa → Bàn giao...'),

            Field::make('rich_text', 'client_requirements', __('Yêu cầu từ phía khách hàng', 'laca'))
                ->set_help_text('Những điều khách hàng cần chuẩn bị hoặc phối hợp.'),

            Field::make('separator', 'sep_payment_terms', __('VIII. Phương thức thanh toán', 'laca')),

            Field::make('rich_text', 'payment_terms', __('Điều khoản & phương thức thanh toán', 'laca'))
                ->set_help_text('Ví dụ: Thanh toán bằng chuyển khoản, chia 2 đợt: 50% trước — 50% khi bàn giao.'),

            Field::make('text', 'quotation_valid_days', __('Hiệu lực báo giá (ngày)', 'laca'))
                ->set_attribute('placeholder', '15')
                ->set_default_value('15')
                ->set_help_text('Số ngày báo giá còn hiệu lực kể từ ngày lập.'),
        ]);

        // ---- Tab 2: Thông tin Khách hàng ----
        $container->add_tab(__('👤 Khách hàng', 'laca'), [
            Field::make('separator', 'sep_client_info', __('Thông tin Chủ dự án', 'laca')),

            Field::make('select', 'client_type', __('Phân loại khách hàng', 'laca'))
                ->set_width(33.33)
                ->add_options([
                    'normal'  => '👤 Khách thường',
                    'vip'     => '⭐ VIP',
                    'partner' => '🤝 Đối tác',
                ])
                ->set_default_value('normal'),

            Field::make('text', 'client_name', __('Tên khách hàng / Công ty', 'laca'))
                ->set_width(33.33)
                ->set_required(true)
                ->set_attribute('placeholder', 'Nguyễn Văn A / Công ty ABC'),

            Field::make('text', 'client_phone', __('Số điện thoại', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', '0901 234 567')
                ->set_attribute('type', 'tel'),

            Field::make('text', 'client_zalo', __('Zalo (SĐT / ID)', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', '0901234567'),

            Field::make('text', 'client_email', __('Email', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'email@example.com')
                ->set_attribute('type', 'email'),

            Field::make('text', 'client_address', __('Địa chỉ', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'Quận 1, TP.HCM'),

            Field::make('textarea', 'client_note', __('Ghi chú về khách hàng', 'laca'))
                ->set_rows(3)
                ->set_attribute('placeholder', 'Tính cách, yêu cầu đặc biệt, lịch sử hợp tác...'),
        ]);

        // ---- Tab 3: Trạng thái & Thời gian ----
        $container->add_tab(__('📅 Trạng thái', 'laca'), [
            Field::make('select', 'project_status', __('Trạng thái dự án', 'laca'))
                ->set_width(50)
                ->add_options([
                    'pending'       => '🕐 Chờ làm',
                    'in_progress'   => '🔨 Đang làm',
                    'done'          => '✅ Đã xong',
                    'maintenance'   => '🔧 Đang bảo trì',
                    'paused'        => '⏸️ Tạm dừng',
                ])
                ->set_default_value('pending'),

            Field::make('multiselect', 'project_tags', __('Tags / Nhãn dự án', 'laca'))
                ->set_width(50)
                ->add_options([
                    'wordpress'    => 'WordPress',
                    'woocommerce'  => 'WooCommerce',
                    'landing_page' => 'Landing Page',
                    'shopify'      => 'Shopify',
                    'ecommerce'    => 'E-commerce',
                    'blog'         => 'Blog',
                    'portfolio'    => 'Portfolio',
                    'saas'         => 'SaaS',
                    'booking'      => 'Booking',
                ]),

            Field::make('separator', 'sep_timeline', __('Mốc thời gian', 'laca')),

            Field::make('text', 'estimated_days', __('⏳ Thời gian làm (ngày)', 'laca'))
                ->set_width(25)
                ->set_attribute('placeholder', 'VD: 15'),

            Field::make('date', 'date_start', __('📅 Ngày bắt đầu làm', 'laca'))
                ->set_width(25)
                ->set_storage_format('Y-m-d'),

            Field::make('date', 'date_handover', __('📅 Ngày bàn giao (dự kiến)', 'laca'))
                ->set_width(25)
                ->set_storage_format('Y-m-d'),

            Field::make('date', 'date_actual_handover', __('✅ Ngày bàn giao thực tế', 'laca'))
                ->set_width(25)
                ->set_storage_format('Y-m-d'),

            Field::make('separator', 'sep_checklist', __('Checklist bàn giao', 'laca')),
            Field::make('set', 'handover_checklist', __('Đã hoàn thành', 'laca'))
                ->add_options([
                    'backup'        => '💾 Đã backup toàn bộ',
                    'ssl'           => '🔐 SSL đã cài & test',
                    'speed_test'    => '⚡ Kiểm tra tốc độ (PageSpeed)',
                    'mobile_test'   => '📱 Test responsive mobile',
                    'seo_basic'     => '🔍 SEO cơ bản đã cấu hình',
                    'training'      => '🎓 Đã hướng dẫn khách dùng',
                    'payment_done'  => '💰 Đã thanh toán đầy đủ',
                    'handover_doc'  => '📄 Đã gửi tài liệu bàn giao',
                ]),
        ]);

        // ---- Tab 4: Tài chính ----
        $container->add_tab(__('💰 Tài chính', 'laca'), [
            Field::make('text', 'price_build', __('💰 Giá build', 'laca'))
                ->set_width(33.33)
                ->set_attribute('data-type', 'currency')
                ->set_attribute('placeholder', '8.000.000')
                ->set_classes('laca-price-build'),

            Field::make('text', 'price_maintenance_yearly', __('🔄 Phí bảo trì hàng năm', 'laca'))
                ->set_width(33.33)
                ->set_attribute('data-type', 'currency')
                ->set_attribute('placeholder', '2.000.000'),

            Field::make('select', 'payment_status', __('Trạng thái thanh toán (Auto)', 'laca'))
                ->set_width(33.33)
                ->add_options([
                    'pending'   => '⏳ Chưa thanh toán',
                    'partial'   => '🔸 Đã thanh toán một phần',
                    'paid'      => '✅ Đã thanh toán đủ',
                    'overdue'   => '🔴 Quá hạn thanh toán',
                ])
                ->set_default_value('pending')
                ->set_classes('laca-payment-status'),

            Field::make('complex', 'payment_history', __('Lịch sử thanh toán từng đợt', 'laca'))
                ->setup_labels([
                    'plural_name' => 'Lần thanh toán',
                    'singular_name' => 'Lần thanh toán',
                ])
                ->add_fields([
                    Field::make('date', 'pay_date', __('Ngày thanh toán', 'laca'))
                        ->set_width(30),
                    Field::make('text', 'pay_amount', __('Số tiền', 'laca'))
                        ->set_width(30)
                        ->set_attribute('data-type', 'currency')
                        ->set_help_text('Nhập số tiền khách trả đợt này')
                        ->set_classes('laca-pay-amount'),
                    Field::make('text', 'pay_note', __('Ghi chú / Mã GD', 'laca'))
                        ->set_width(40),
                ])
                ->set_header_template('<% if (pay_date) { %>Ngày <%-pay_date%><% } else { %>Thanh toán mới<% } %>'),

            Field::make('file', 'invoice_file', __('📄 File hóa đơn / Bằng chứng chung', 'laca'))
                ->set_width(30)
                ->set_type(['application/pdf', 'image']),

            Field::make('textarea', 'finance_note', __('Ghi chú tài chính nội bộ', 'laca'))
                ->set_width(70)
                ->set_rows(2)
                ->set_attribute('placeholder', 'Ghi chú nhắc nợ...'),
        ]);

        // ---- Tab 5: Hosting & Domain (Tab QUAN TRỌNG nhất) ----
        $container->add_tab(__('🖥️ Hosting & Domain', 'laca'), [
            // DOMAIN
            Field::make('separator', 'sep_domain', __('🌐 Thông tin Domain', 'laca')),

            Field::make('text', 'domain_name', __('Tên miền', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'example.com'),

            Field::make('text', 'domain_registrar', __('Nhà đăng ký', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'GoDaddy / NameSilo / PA Vietnam...'),

            Field::make('text', 'domain_username', __('Tài khoản đăng nhập', 'laca'))
                ->set_width(50)
                ->set_attribute('placeholder', 'username'),

            Field::make('text', 'domain_password', __('Mật khẩu (mã hóa khi lưu)', 'laca'))
                ->set_width(50)
                ->set_attribute('placeholder', '••••••••')
                ->set_attribute('type', 'password')
                ->set_classes('laca-password-input'),

            Field::make('date', 'domain_expiry', __('📅 Ngày hết hạn Domain', 'laca'))
                ->set_width(50)
                ->set_storage_format('Y-m-d'),

            Field::make('text', 'domain_notify_days', __('Cảnh báo trước (ngày)', 'laca'))
                ->set_width(50)
                ->set_default_value('30')
                ->set_attribute('placeholder', '30'),

            // HOSTING
            Field::make('separator', 'sep_hosting', __('🖥️ Thông tin Hosting', 'laca')),

            Field::make('text', 'hosting_price', __('Giá gia hạn / năm', 'laca'))
                ->set_width(33.33)
                ->set_attribute('data-type', 'currency')
                ->set_attribute('placeholder', '1.200.000'),

            Field::make('date', 'hosting_expiry', __('📅 Ngày hết hạn Hosting', 'laca'))
                ->set_width(33.33)
                ->set_storage_format('Y-m-d'),

            Field::make('text', 'hosting_notify_days', __('Cảnh báo trước (ngày)', 'laca'))
                ->set_width(33.33)
                ->set_default_value('30')
                ->set_attribute('placeholder', '30'),

            Field::make('text', 'hosting_provider', __('Nhà cung cấp hosting', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'Azdigi / SiteGround / Vultr...'),

            Field::make('text', 'hosting_user', __('Tài khoản hosting', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'username'),
            
            Field::make('text', 'hosting_password', __('Mật khẩu hosting', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', '••••••••')
                ->set_attribute('type', 'password')
                ->set_classes('laca-password-input'),

            Field::make('text', 'hosting_url', __('URL cPanel / DirectAdmin', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'https://cpanel.example.com:2083'),

            Field::make('text', 'cpanel_username', __('Tài khoản cPanel', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'username'),

            Field::make('text', 'cpanel_password', __('Mật khẩu cPanel (mã hóa)', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', '••••••••')
                ->set_attribute('type', 'password')
                ->set_classes('laca-password-input'),

            // FTP
            Field::make('separator', 'sep_ftp', __('📂 FTP / SFTP', 'laca')),

            Field::make('text', 'ftp_host', __('FTP Host', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'ftp.example.com'),

            Field::make('text', 'ftp_username', __('FTP Username', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'ftpuser'),

            Field::make('text', 'ftp_password', __('Mật khẩu FTP', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', '••••••••')
                ->set_attribute('type', 'password')
                ->set_classes('laca-password-input'),

        ]);

        // ---- Tab 6: Bảo trì ----
        $container->add_tab(__('🔧 Bảo trì', 'laca'), [
            Field::make('select', 'maintenance_type', __('Loại bảo trì', 'laca'))
                ->set_width(20)
                ->add_options([
                    'none' => '❌ Không có bảo trì',
                    'free' => '🎁 Bảo trì miễn phí',
                    'paid' => '💳 Bảo trì có phí',
                ])
                ->set_default_value('free'),

            Field::make('text', 'maintenance_response_time', __('Thời gian phản hồi cam kết', 'laca'))
                ->set_width(20)
                ->set_attribute('placeholder', '24 giờ')
                ->set_default_value('24 giờ'),

            Field::make('date', 'maintenance_start', __('📅 Bắt đầu bảo hành', 'laca'))
                ->set_width(30)
                ->set_storage_format('Y-m-d'),

            Field::make('date', 'maintenance_end', __('📅 Kết thúc bảo hành', 'laca'))
                ->set_width(30)
                ->set_storage_format('Y-m-d'),

            Field::make('rich_text', 'maintenance_scope', __('Nội dung bảo hành / Cam kết', 'laca'))
                ->set_help_text('Mô tả chi tiết những gì được bảo hành: sửa lỗi phát sinh, update plugin, backup định kỳ...'),
        ]);

        // ---- Tab 7: Tech Specs (giữ lại từ before) ----
        $container->add_tab(__('⚙️ Tech Specs', 'laca'), [
            Field::make('text', 'demo_design_url', __('URL web mẫu / Link Figma', 'laca'))
                ->set_attribute('placeholder', 'https://'),

            Field::make('multiselect', 'platform', __('Nền tảng (Platform)', 'laca'))
                ->set_width(50)
                ->add_options([
                    'wordpress'    => 'WordPress',
                    'woocommerce'  => 'WooCommerce',
                    'landing_page' => 'Landing Page',
                    'shopify'      => 'Shopify',
                    'laravel'      => 'Laravel',
                    'next_js'      => 'Next.js',
                    'custom'       => 'Custom Code',
                ]),

            Field::make('multiselect', 'builder', __('Page Builder', 'laca'))
                ->set_width(50)
                ->set_default_value('none')
                ->add_options([
                    'bricks'    => 'Bricks Builder',
                    'gutenberg' => 'Gutenberg',
                    'elementor' => 'Elementor',
                    'flatsome'  => 'Flatsome',
                    'none'      => 'Code thuần',
                ]),

            Field::make('multiselect', 'features', __('Tính năng phổ biến', 'laca'))
                ->add_options([
                    'landing_page'   => 'Landing Page',
                    'multi_language' => 'Multi-language',
                    'booking'        => 'Booking System',
                    'payment'        => 'Payment Gateway',
                    'flash_sale'     => 'Flash Sale',
                    'seo'            => 'SEO Optimized',
                    'speed'          => 'High Speed',
                    'membership'     => 'Membership',
                    'chat'           => 'Live Chat',
                ]),

            Field::make('complex', 'custom_features', __('Tính năng khác (Tùy chỉnh)', 'laca'))
                ->add_fields([
                    Field::make('text', 'name', __('Tên tính năng', 'laca')),
                ])
                ->set_header_template('<% if (name) { %><%-name%><% } %>'),
        ]);
    }

    // =========================================================================
    // CUSTOM ADMIN LIST COLUMNS
    // =========================================================================

    public function addListColumns(array $columns): array
    {
        // Giữ checkbox và thumbnail
        $new = [];
        $new['cb']             = $columns['cb'] ?? '';
        $new['featured_image'] = __('Ảnh', 'laca');
        $new['title']          = __('Tên dự án', 'laca');
        $new['laca_client']    = __('👤 Khách hàng', 'laca');
        $new['laca_status']    = __('📊 Trạng thái', 'laca');
        $new['laca_domain']    = __('🌐 Domain', 'laca');
        $new['laca_expiry']    = __('📅 Hết hạn', 'laca');
        $new['laca_alerts']    = __('⚠️ Cảnh báo', 'laca');
        $new['date']           = $columns['date'] ?? '';

        return $new;
    }

    public function renderListColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'laca_client':
                $name  = esc_html(carbon_get_post_meta($postId, 'client_name') ?: '—');
                $phone = esc_html(carbon_get_post_meta($postId, 'client_phone') ?: '');
                echo '<strong>' . $name . '</strong>';
                if ($phone) {
                    echo '<br><small style="color:#888;">' . $phone . '</small>';
                }
                break;

            case 'laca_status':
                $status = carbon_get_post_meta($postId, 'project_status') ?: 'pending';
                $labels = [
                    'pending'     => ['🕐 Chờ làm', '#f0ad4e'],
                    'in_progress' => ['🔨 Đang làm', '#5bc0de'],
                    'done'        => ['✅ Đã xong', '#5cb85c'],
                    'maintenance' => ['🔧 Bảo trì', '#d9534f'],
                    'paused'      => ['⏸️ Tạm dừng', '#999'],
                ];
                $info  = $labels[$status] ?? [$status, '#666'];
                echo '<span style="
                    display:inline-block;padding:3px 8px;border-radius:12px;
                    background:' . esc_attr($info[1]) . '22;
                    color:' . esc_attr($info[1]) . ';
                    font-size:12px;font-weight:600;white-space:nowrap;">'
                    . esc_html($info[0])
                    . '</span>';
                break;

            case 'laca_domain':
                $domain  = esc_html(carbon_get_post_meta($postId, 'domain_name') ?: '—');
                $liveUrl = esc_url(carbon_get_post_meta($postId, 'live_url') ?: '');
                if ($liveUrl) {
                    echo '<a href="' . $liveUrl . '" target="_blank" title="Mở website">' . $domain . ' ↗</a>';
                } else {
                    echo $domain;
                }
                break;

            case 'laca_expiry':
                $domainExpiry  = carbon_get_post_meta($postId, 'domain_expiry');
                $hostingExpiry = carbon_get_post_meta($postId, 'hosting_expiry');
                $today         = new \DateTime();

                foreach ([
                    '🌐 Domain'  => $domainExpiry,
                    '🖥️ Hosting' => $hostingExpiry,
                ] as $label => $expiry) {
                    if (!$expiry) {
                        continue;
                    }
                    $expiryDate = new \DateTime($expiry);
                    $diff       = $today->diff($expiryDate);
                    $daysLeft   = (int) $diff->format('%r%a');
                    $color      = $daysLeft < 7 ? '#d9534f' : ($daysLeft < 30 ? '#f0ad4e' : '#5cb85c');

                    echo '<div style="font-size:12px;margin-bottom:2px;">'
                        . esc_html($label) . ': '
                        . '<span style="color:' . esc_attr($color) . ';font-weight:600;">'
                        . esc_html($expiryDate->format('d/m/Y'))
                        . ' <small>(' . ($daysLeft >= 0 ? '+' . $daysLeft : $daysLeft) . 'd)</small>'
                        . '</span></div>';
                }
                break;

            case 'laca_alerts':
                $count = ProjectAlert::countActive($postId);
                if ($count > 0) {
                    echo '<span style="
                        background:#d9534f;color:#fff;
                        border-radius:50%;width:22px;height:22px;
                        display:inline-flex;align-items:center;justify-content:center;
                        font-size:12px;font-weight:700;">'
                        . esc_html($count)
                        . '</span>';
                } else {
                    echo '<span style="color:#5cb85c;">✓</span>';
                }
                break;
        }
    }

    public function sortableColumns(array $columns): array
    {
        $columns['laca_expiry'] = 'domain_expiry';
        return $columns;
    }

    public function addStatusFilter(): void
    {
        global $typenow;
        if ($typenow !== 'project') {
            return;
        }

        $currentStatus = sanitize_key($_GET['laca_status'] ?? '');
        $statuses      = [
            ''            => 'Tất cả trạng thái',
            'pending'     => '🕐 Chờ làm',
            'in_progress' => '🔨 Đang làm',
            'done'        => '✅ Đã xong',
            'maintenance' => '🔧 Bảo trì',
            'paused'      => '⏸️ Tạm dừng',
        ];

        echo '<select name="laca_status">';
        foreach ($statuses as $value => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($value),
                selected($currentStatus, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    public function filterByStatus(\WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if (($query->get('post_type') !== 'project') || empty($_GET['laca_status'])) {
            return;
        }

        $status = sanitize_key($_GET['laca_status']);
        if (empty($status)) {
            return;
        }

        $meta = $query->get('meta_query', []);
        $meta[] = [
            'key'     => '_project_status',
            'value'   => $status,
            'compare' => '=',
        ];
        $query->set('meta_query', $meta);
    }

    // =========================================================================
    // AJAX HANDLERS
    // =========================================================================

    public function ajaxResolveAlert(): void
    {
        check_ajax_referer('laca_project_manager', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Không có quyền'], 403);
        }

        $alertId   = absint($_POST['alert_id'] ?? 0);
        $projectId = absint($_POST['project_id'] ?? 0);

        if (!$alertId) {
            wp_send_json_error(['message' => 'Thiếu alert_id']);
        }

        $result = ProjectAlert::resolve($alertId, $projectId);
        if ($result) {
            wp_send_json_success(['message' => 'Đã đánh dấu xử lý']);
        } else {
            wp_send_json_error(['message' => 'Không tìm thấy cảnh báo']);
        }
    }

    public function ajaxDeleteLog(): void
    {
        check_ajax_referer('laca_project_manager', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Không có quyền'], 403);
        }

        $logId     = absint($_POST['log_id'] ?? 0);
        $projectId = absint($_POST['project_id'] ?? 0);

        $result = ProjectLog::delete($logId, $projectId);
        if ($result) {
            wp_send_json_success(['message' => 'Đã xoá']);
        } else {
            wp_send_json_error(['message' => 'Không tìm thấy']);
        }
    }

    public function ajaxAddLog(): void
    {
        check_ajax_referer('laca_project_manager', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Không có quyền'], 403);
        }

        $projectId = absint($_POST['project_id'] ?? 0);
        $content   = sanitize_textarea_field($_POST['log_content'] ?? '');
        $type      = sanitize_key($_POST['log_type'] ?? 'note');

        if (!$projectId || !$content) {
            wp_send_json_error(['message' => 'Vui lòng nhập nội dung']);
        }

        $logId = ProjectLog::add([
            'project_id'  => $projectId,
            'log_content' => $content,
            'log_type'    => $type,
        ]);

        if ($logId) {
            wp_send_json_success(['message' => 'Đã thêm log thành công']);
        } else {
            wp_send_json_error(['message' => 'Không thể lưu log']);
        }
    }

    public function ajaxAddAlert(): void
    {
        check_ajax_referer('laca_project_manager', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Không có quyền'], 403);
        }

        $projectId = absint($_POST['project_id'] ?? 0);
        $msg       = sanitize_textarea_field($_POST['alert_msg'] ?? '');
        $type      = sanitize_key($_POST['alert_type'] ?? 'other');
        $level     = sanitize_key($_POST['alert_level'] ?? 'info');

        if (!$projectId || !$msg) {
            wp_send_json_error(['message' => 'Vui lòng nhập nội dung cảnh báo']);
        }

        $alertId = ProjectAlert::add([
            'project_id'  => $projectId,
            'alert_msg'   => $msg,
            'alert_type'  => $type,
            'alert_level' => $level,
        ]);

        if ($alertId) {
            wp_send_json_success(['message' => 'Đã gửi cảnh báo']);
        } else {
            wp_send_json_error(['message' => 'Không thể lưu cảnh báo']);
        }
    }

    // =========================================================================
    // NATIVE META BOX (CHO LOGS & ALERTS)
    // =========================================================================

    public function registerLogsMetaBox(): void
    {
        add_meta_box(
            'laca_project_logs_alerts',
            '📋 Logs & ⚠️ Alerts',
            [$this, 'renderLogsMetaBox'],
            'project',
            'normal',
            'high'
        );
    }

    public function renderLogsMetaBox(\WP_Post $post): void
    {
        if (!class_exists('\App\Models\ProjectLog') || !class_exists('\App\Models\ProjectAlert')) {
            echo '<p>Các bảng DB chưa được tạo. Vui lòng kích hoạt lại theme.</p>';
            return;
        }

        $projectId = $post->ID;
        $logs      = ProjectLog::getByProject($projectId);
        $alerts    = ProjectAlert::getActive($projectId);
        
        $secretKey = get_post_meta($projectId, '_tracker_secret_key', true);
        if (empty($secretKey)) {
            $secretKey = wp_generate_password(24, false);
            update_post_meta($projectId, '_tracker_secret_key', $secretKey);
        }
        $endpoint = rest_url('laca/v1/tracker/log');

        wp_nonce_field('laca_project_manager', 'laca_pm_nonce');
        ?>
        <div class="laca-pm-wrap laca-logs-container" data-project-id="<?php echo esc_attr($projectId); ?>">
            <!-- ALERTS & TRACKER SECTION -->
            <div class="laca-pm-col" style="display:flex; flex-direction:column; gap:20px;">
                <div>
                    <h3 style="margin-top:0">⚠️ Cảnh báo đang hoạt động</h3>
                    <div class="laca-pm-list">
                        <?php if (empty($alerts)): ?>
                            <p style="color:#5cb85c; font-weight:600;">✅ Không có cảnh báo nào.</p>
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

                    <hr style="border:0; border-top:1px dashed #ddd; margin:15px 0;">
                    <h4 style="margin:0 0 10px 0;">Tạo cảnh báo mới</h4>
                    <div class="laca-form-group">
                        <select id="new_alert_type">
                            <option value="other">📌 Khác</option>
                            <option value="bug">🐛 Lỗi website</option>
                            <option value="security">⚠️ Cảnh báo bảo mật</option>
                        </select>
                    </div>
                    <div class="laca-form-group">
                        <select id="new_alert_level">
                            <option value="info">🔹 Info</option>
                            <option value="warning">🔸 Warning</option>
                            <option value="critical">🔴 Critical</option>
                        </select>
                    </div>
                    <div class="laca-form-group">
                        <textarea id="new_alert_msg" placeholder="Nội dung cảnh báo..."></textarea>
                    </div>
                    <button type="button" class="button button-secondary" id="btn_add_alert">Gửi cảnh báo</button>
                </div>

                <div style="padding-top:15px; border-top:1px solid #eee;">
                    <h3 style="margin-top:0">🔗 Auto Activity Tracker</h3>
                    <p style="margin:0 0 10px 0; color:#666; font-size:12px;">Cài đặt plugin vào <code>wp-content/mu-plugins/</code> ở web khách để auto tracking (Cập nhật, thay đổi code).</p>
                    <div class="laca-form-group">
                        <label style="font-weight:600;font-size:12px;">Endpoint URL</label>
                        <input type="text" readonly value="<?php echo esc_url($endpoint); ?>" class="laca-copyable-input">
                    </div>
                    <div class="laca-form-group">
                        <label style="font-weight:600;font-size:12px;">Secret Key</label>
                        <input type="text" readonly value="<?php echo esc_attr($secretKey); ?>" class="laca-copyable-input">
                    </div>
                    <div style="margin-top:10px;">
                        <button type="button" class="button button-primary" id="btn_download_tracker">⬇️ Tải xuống MU-Plugin</button>
                        <button type="button" class="button" id="btn_view_tracker_code">👁 Xem code PHP</button>
                    </div>
                </div>
            </div>

            <!-- LOGS SECTION -->
            <div class="laca-pm-col">
                <h3 style="margin-top:0">📋 Lịch sử & Nhật ký</h3>
                <div class="laca-pm-list">
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
                                <div style="text-align: right;">
                                    <a class="laca-action-btn delete-log" data-id="<?php echo $l['id']; ?>">Xoá</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <hr style="border:0; border-top:1px dashed #ddd; margin:15px 0;">
                <h4 style="margin:0 0 10px 0;">Thêm nhật ký</h4>
                <div class="laca-form-group">
                    <select id="new_log_type">
                        <option value="note">📝 Ghi chú</option>
                        <option value="client_request">👤 Yêu cầu khách hàng</option>
                        <option value="bug_fix">🐛 Sửa lỗi</option>
                        <option value="theme_switch">🎨 Đổi thiết kế/theme</option>
                        <option value="deployment">🚀 Deploy / Update lớn</option>
                    </select>
                </div>
                <div class="laca-form-group">
                    <textarea id="new_log_msg" placeholder="Nội dung..."></textarea>
                </div>
                <button type="button" class="button button-secondary" id="btn_add_log">Lưu nhật ký</button>
            </div>
        </div>
        <?php
    }


    // =========================================================================
    // CURRENCY FORMATTER
    // =========================================================================

    public function formatCurrencyOnSave($value, $id, $name, $field)
    {
        // Handle normal fields
        $currencyFields = ['price_build', 'price_maintenance_yearly', 'hosting_price'];
        if (in_array($name, $currencyFields) && !empty($value)) {
            return preg_replace('/[^0-9]/', '', $value);
        }
        
        // Handle complex fields specifically for pay_amount
        if (strpos($name, '_payment_history|pay_amount|') !== false && !empty($value)) {
            return preg_replace('/[^0-9]/', '', $value);
        }

        return $value;
    }

    public function formatCurrencyOnLoad($value, $id, $name, $field)
    {
        $currencyFields = ['price_build', 'price_maintenance_yearly', 'hosting_price'];
        if (in_array($name, $currencyFields) && is_numeric($value)) {
            return number_format((float)$value, 0, ',', '.');
        }

        if (strpos($name, '_payment_history|pay_amount|') !== false && is_numeric($value)) {
            return number_format((float)$value, 0, ',', '.');
        }

        return $value;
    }

    public function autoCalculatePaymentStatus(int $postId, \WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'project') return;
        if (wp_is_post_revision($postId)) return;

        // =========================================================
        // QUAN TRỌNG: Dùng get_post_meta() raw, KHÔNG dùng
        // carbon_get_post_meta() vì CF có internal cache riêng
        // và có thể trả về data cũ ngay trong cùng request save.
        // Hook này chạy ở priority 9999 để CF đã save xong.
        // =========================================================

        // 1. Đọc giá build (CF lưu với prefix _ => meta key: _price_build)
        $rawBuild   = get_post_meta($postId, '_price_build', true);
        $totalBuild = (int) preg_replace('/[^0-9]/', '', (string) $rawBuild);

        // 2. Đọc payment_history — CF lưu sub-field theo format PIPE:
        //    _payment_history|pay_amount|0, _payment_history|pay_amount|1, ...
        //    (Không phải underscore: _payment_history_0_pay_amount)
        $totalPaid = 0;
        for ($i = 0; $i < 100; $i++) {
            $metaKey = "_payment_history|pay_amount|{$i}";
            if (!metadata_exists('post', $postId, $metaKey)) {
                break;
            }
            $amt = get_post_meta($postId, $metaKey, true);
            $totalPaid += (int) preg_replace('/[^0-9]/', '', (string) $amt);
        }

        error_log("[LACA] autoCalculate post={$postId} totalBuild={$totalBuild} totalPaid={$totalPaid}");

        // 3. Xác định trạng thái
        $currentStatus = get_post_meta($postId, '_payment_status', true) ?: 'pending';
        $newStatus     = $currentStatus;

        if ($totalBuild > 0) {
            if ($totalPaid <= 0) {
                $newStatus = 'pending';
            } elseif ($totalPaid < $totalBuild) {
                $newStatus = 'partial';
            } else {
                $newStatus = 'paid';
            }
        }

        // 4. Lưu nếu thay đổi
        if ($newStatus !== $currentStatus) {
            update_post_meta($postId, '_payment_status', $newStatus);
        }
    }

    public function addCurrencyFormatterScript()
    {
        $screen = get_current_screen();
        if ($screen && $screen->id === 'project') {
            // JS moved to JS build pipeline
        }
    }
}
