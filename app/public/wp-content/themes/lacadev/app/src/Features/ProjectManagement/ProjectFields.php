<?php

namespace App\Features\ProjectManagement;

use Carbon_Fields\Container\Container;
use Carbon_Fields\Field;

/**
 * Project Carbon Fields
 *
 * - Container chính: "Thông tin dự án" (7 tabs)
 * - Container phụ : "Báo giá" (meta box riêng)
 */
class ProjectFields
{
    public function __construct(private string $postType = 'project') {}

    public function register(): void
    {
        // Container chính — 7 tabs
        $container = Container::make('post_meta', __('Thông tin dự án', 'laca'))
            ->where('post_type', '=', $this->postType);

        $this->addTabStatus($container);
        $this->addTabFinance($container);
        $this->addTabHostingDomain($container);
        $this->addTabTechSpecs($container);
        $this->addTabBlockSync($container);

        // Container riêng — Báo giá (meta box độc lập)
        $this->registerQuotationContainer();

        // Default 3 đợt thanh toán cho dự án mới
        add_filter('carbon_fields_value_default', function ($default, $name) {
            if ($name !== '_payment_steps') {
                return $default;
            }
            return [
                ['step_number' => '1', 'step_title' => 'Tạm ứng 50%',    'step_desc' => 'Sau khi ký hợp đồng'],
                ['step_number' => '2', 'step_title' => 'Nghiệm thu 30%',  'step_desc' => 'Sau khi hoàn thành UI/UX'],
                ['step_number' => '3', 'step_title' => 'Bàn giao 20%',    'step_desc' => 'Sau khi nghiệm thu dự án'],
            ];
        }, 10, 2);

        // Default 3 bước quy trình cho dự án mới
        add_filter('carbon_fields_value_default', function ($default, $name) {
            if ($name !== '_process_steps') {
                return $default;
            }
            return [
                ['process_number' => '01', 'process_title' => 'Discovery & Strategy',    'process_desc' => 'Thấu hiểu giá trị cốt lõi và mục tiêu kinh doanh của khách hàng.'],
                ['process_number' => '02', 'process_title' => 'Design & Development',    'process_desc' => 'Thiết kế UI/UX và lập trình hệ thống quản trị nội dung CMS.'],
                ['process_number' => '03', 'process_title' => 'Launch & Warranty',       'process_desc' => 'Bàn giao, hướng dẫn sử dụng và kích hoạt chế độ bảo hành trọn đời.'],
            ];
        }, 10, 2);
    }

    // =========================================================================
    // Báo giá — Container riêng (meta box độc lập)
    // =========================================================================

    private function registerQuotationContainer(): void
    {
        $container = Container::make('post_meta', __('Báo giá', 'laca'))
            ->where('post_type', '=', $this->postType);

        // Thông tin khách hàng
        $container->add_tab(__('Khách hàng', 'laca'), [
            Field::make('html', 'client_tab_info', '')
                ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">👤 Thông tin khách hàng</p><p style="margin:0;font-size:13px;color:#374151">Nhập thông tin liên hệ của khách hàng để hiển thị trên bản báo giá gửi cho khách.</p></div>'),

            Field::make('text', 'client_name', __('', 'laca'))
                ->set_width(33.33)
                ->set_required(true)
                ->set_attribute('placeholder', 'Tên khách hàng / Công ty'),

            Field::make('text', 'client_phone', __('', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'Số điện thoại')
                ->set_attribute('type', 'tel'),

            Field::make('text', 'client_email', __('', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'email@example.com')
                ->set_attribute('type', 'email'),

            Field::make('text', 'client_address', __('', 'laca'))
                ->set_attribute('placeholder', 'Địa chỉ'),
        ]);

        // Tổng quan dự án
        $container->add_tab(__('Tổng quan dự án', 'laca'), [
            Field::make('html', 'quotation_intro_tab_info', '')
                ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">📄 Tổng quan dự án</p><p style="margin:0;font-size:13px;color:#374151">Viết đoạn giới thiệu ngắn về dự án, sẽ hiển thị ở đầu bản báo giá gửi cho khách hàng.</p></div>'),

            Field::make('rich_text', 'quotation_intro', __('', 'laca')),
        ]);

        // Danh sách trang thiết kế
        $container->add_tab(__('Danh sách trang thiết kế', 'laca'), [
            Field::make('html', 'design_pages_tab_info', '')
                ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">📑 Danh sách trang thiết kế</p><p style="margin:0;font-size:13px;color:#374151">Liệt kê các trang sẽ thiết kế cho dự án, sẽ hiển thị thành danh sách trong bản báo giá gửi khách.</p></div>'),

            Field::make('complex', 'design_pages', __('', 'laca'))
                ->set_layout('tabbed-vertical')
                ->setup_labels([
                    'plural_name'   => 'Trang',
                    'singular_name' => 'Trang',
                ])
                ->add_fields([
                    Field::make('text', 'page_name', __('', 'laca'))
                        ->set_width(50)
                        ->set_attribute('placeholder', 'Tên trang'),

                    Field::make('text', 'subpage_name', __('', 'laca'))
                        ->set_width(50)
                        ->set_attribute('placeholder', 'Subpage'),

                    Field::make('text', 'page_demo_url', __('', 'laca'))
                        ->set_width(60)
                        ->set_attribute('placeholder', 'https://...'),
                ])
                ->set_header_template('<% if (page_name) { %><%-page_name%><% } else { %>Trang mới<% } %>'),
        ]);

        // Thời gian thực hiện
        $container->add_tab(__('Giai đoạn thực hiện', 'laca'), [
            Field::make('html', 'timeline_phases_tab_info', '')
                ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🗂️ Giai đoạn thực hiện</p><p style="margin:0;font-size:13px;color:#374151">Mô tả các giai đoạn triển khai dự án kèm số ngày dự kiến, hiển thị trong bản báo giá gửi khách.</p></div>'),

            Field::make('complex', 'timeline_phases', __('', 'laca'))
                ->set_layout('tabbed-vertical')
                ->setup_labels([
                    'plural_name'   => 'Giai đoạn',
                    'singular_name' => 'Giai đoạn',
                ])
                ->add_fields([
                    Field::make('text', 'phase_days', __('', 'laca'))
                        ->set_width(20)
                        ->set_attribute('placeholder', 'Số ngày'),
                    Field::make('rich_text', 'phase_content', __('', 'laca'))
                        ->set_width(80),
                ])
                ->set_header_template('<% if (phase_days) { %> <%- phase_days %> ngày<% } %>'),
        ]);

        // Mốc thời gian
        $container->add_tab(__('Mốc thời gian', 'laca'), [
            Field::make('html', 'timeline_tab_info', '')
                ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">📅 Mốc thời gian</p><p style="margin:0;font-size:13px;color:#374151">Nhập ngày bắt đầu, ngày bàn giao dự kiến và số ngày làm việc để tính tiến độ hiển thị trong báo giá.</p></div>'),

            Field::make('text', 'estimated_days', __('Thời gian làm (ngày)', 'laca'))
                ->set_width(25)
                ->set_attribute('placeholder', 'VD: 15'),

            Field::make('text', 'exclude_days', __('Thời gian không tính', 'laca'))
                ->set_width(25)
                ->set_default_value("Không tính Chủ nhật và ngày lễ"),

            Field::make('date', 'date_start', __('Ngày bắt đầu làm', 'laca'))
                ->set_width(25)
                ->set_storage_format('Y-m-d'),

            Field::make('date', 'date_handover', __('Ngày bàn giao (dự kiến)', 'laca'))
                ->set_width(25)
                ->set_storage_format('Y-m-d'),
        ]);

        // Chi phí thực hiện
        $container->add_tab(__('Chi phí thực hiện', 'laca'), [
            Field::make('html', 'quotation_items_tab_info', '')
                ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">💵 Chi phí thực hiện</p><p style="margin:0;font-size:13px;color:#374151">Nhập bảng chi tiết các hạng mục và đơn giá, dùng để tính tổng chi phí hiển thị trong bản báo giá gửi khách.</p></div>'),

            Field::make('complex', 'quotation_items', __('Bảng chi phí chi tiết', 'laca'))
                ->set_layout('tabbed-vertical')
                ->setup_labels([
                    'plural_name'   => 'Hạng mục',
                    'singular_name' => 'Hạng mục',
                ])
                ->add_fields([
                    Field::make('text', 'item_name', __('', 'laca'))
                        ->set_width(40)
                        ->set_attribute('placeholder', 'Tên hạng mục'),

                    Field::make('text', 'item_desc', __('', 'laca'))
                        ->set_width(40)
                        ->set_attribute('placeholder', 'Mô tả hạng mục'),

                    Field::make('text', 'item_unit_price', __('', 'laca'))
                        ->set_width(20)
                        ->set_attribute('data-type', 'currency')
                        ->set_attribute('placeholder', 'Đơn giá')
                        ->set_classes('laca-pay-amount'),
                ])
                ->set_header_template('<% if (item_name) { %><%-item_name%><% } %>')
                ->set_default_value([
                    [
                        'item_name'       => 'Thiết kế giao diện website (responsive)',
                        'item_desc'       => 'UI/UX chuẩn mobile-first, tương thích mọi thiết bị',
                        'item_unit_price' => '4500000',
                    ],
                    [
                        'item_name'       => 'Lập trình website',
                        'item_desc'       => 'WordPress + Gutenberg blocks, tích hợp Carbon Fields',
                        'item_unit_price' => '3500000',
                    ],
                    [
                        'item_name'       => 'SEO On-page cơ bản',
                        'item_desc'       => 'Schema markup, sitemap, tối ưu tốc độ tải trang',
                        'item_unit_price' => '',
                        'item_note'       => 'Bao gồm trong gói',
                    ],
                    [
                        'item_name'       => 'SSL Certificate',
                        'item_desc'       => 'Chứng chỉ bảo mật HTTPS, tự động gia hạn.',
                        'item_unit_price' => '',
                        'item_note'       => 'Miễn phí năm đầu',
                    ],
                    [
                        'item_name'       => 'Email doanh nghiệp',
                        'item_desc'       => '5 hộp thư @tên-miền, dung lượng 5GB/hộp.',
                        'item_unit_price' => '',
                        'item_note'       => 'Miễn phí năm đầu',
                    ],
                ]),
        ]);

        // Quy trình thực hiện
        $container->add_tab(__('Quy trình', 'laca'), [
            Field::make('html', 'process_steps_tab_info', '')
                ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔁 Quy trình thực hiện</p><p style="margin:0;font-size:13px;color:#374151">Mô tả các bước quy trình làm việc, hiển thị trong bản báo giá để khách hàng hiểu cách dự án được triển khai.</p></div>'),

            Field::make('complex', 'process_steps', __('', 'laca'))
                ->set_layout('tabbed-vertical')
                ->setup_labels([
                    'plural_name'   => 'Bước',
                    'singular_name' => 'Bước',
                ])
                ->add_fields([
                    Field::make('text', 'process_number', __('', 'laca'))
                        ->set_width(10)
                        ->set_attribute('placeholder', '01'),
                    Field::make('text', 'process_title', __('', 'laca'))
                        ->set_width(40)
                        ->set_attribute('placeholder', 'Discovery & Strategy'),
                    Field::make('text', 'process_desc', __('', 'laca'))
                        ->set_width(50)
                        ->set_attribute('placeholder', 'Thấu hiểu mục tiêu kinh doanh'),
                ])
                ->set_header_template('<% if (process_title) { %><%- process_title %><% } %>'),
        ]);

        // Tab: Tính năng kỹ thuật
        $container->add_tab(__('Tính năng kỹ thuật', 'laca'), [
            Field::make('textarea', 'tech_description', __('Mô tả ngắn', 'laca'))
                ->set_rows(2)
                ->set_attribute('placeholder', 'Chúng tôi sử dụng những công nghệ hiện đại nhất...')
                ->set_default_value('Chúng tôi sử dụng những công nghệ hiện đại nhất để đảm bảo website vận hành ổn định, bảo mật và thân thiện với các công cụ tìm kiếm.'),

            Field::make('complex', 'tech_tags', __('Tech Tags (nền tảng)', 'laca'))
                ->set_layout('tabbed-horizontal')
                ->setup_labels(['plural_name' => 'Tags', 'singular_name' => 'Tag'])
                ->add_fields([
                    Field::make('text', 'tag_name', __('', 'laca'))
                        ->set_attribute('placeholder', 'WordPress'),
                ])
                ->set_header_template('<% if (tag_name) { %><%-tag_name%><% } %>')
                ->set_default_value([
                    ['tag_name' => 'WordPress'],
                    ['tag_name' => 'Code thuần'],
                    ['tag_name' => 'Gutenberg'],
                ]),

            Field::make('complex', 'tech_modules', __('Modules tính năng', 'laca'))
                ->set_layout('tabbed-vertical')
                ->setup_labels(['plural_name' => 'Module', 'singular_name' => 'Module'])
                ->add_fields([
                    Field::make('text', 'module_icon', __('', 'laca'))
                        ->set_width(25)
                        ->set_attribute('placeholder', 'edit_note')
                        ->set_help_text('Material Symbol name: edit_note, chat_bubble, search, mail, shield, speed... — <a href="https://fonts.google.com/icons?icon.set=Material+Symbols&icon.style=Outlined" target="_blank" rel="noopener">Xem tất cả icon ↗</a>'),

                    Field::make('select', 'module_color', __('', 'laca'))
                        ->set_width(25)
                        ->add_options([
                            'secondary' => 'Secondary (xanh lá)',
                            'tertiary'  => 'Tertiary (vàng)',
                            'primary'   => 'Primary (xám)',
                            'neutral'   => 'Neutral (stone)',
                        ])
                        ->set_default_value('secondary'),

                    Field::make('text', 'module_title', __('', 'laca'))
                        ->set_width(50)
                        ->set_attribute('placeholder', 'Module Cập nhật nội dung'),

                    Field::make('textarea', 'module_items', __('', 'laca'))
                        ->set_rows(4)
                        ->set_attribute('placeholder', "Mỗi dòng = 1 bullet point\nQuản lý thêm, sửa, xóa bài viết\nSắp xếp thứ tự các blocks nội dung"),
                ])
                ->set_header_template('<% if (module_title) { %><%-module_title%><% } %>')
                ->set_default_value([
                    [
                        'module_icon'  => 'edit_note',
                        'module_color' => 'secondary',
                        'module_title' => 'Module Cập nhật nội dung',
                        'module_items' => "Quản lý thêm, sửa, xóa bài viết\nSắp xếp thứ tự các blocks nội dung\nTối ưu hóa hình ảnh tự động",
                    ],
                    [
                        'module_icon'  => 'chat_bubble',
                        'module_color' => 'tertiary',
                        'module_title' => 'Module Tính năng & Live Chat',
                        'module_items' => "Tích hợp Live Chat (Facebook/Zalo)\nNút gọi điện nhanh trên di động\nTích hợp bản đồ & chỉ đường",
                    ],
                    [
                        'module_icon'  => 'search',
                        'module_color' => 'primary',
                        'module_title' => 'Tối ưu hóa SEO',
                        'module_items' => "Cấu trúc URL thân thiện (Breadcrumbs)\nTối ưu tốc độ tải trang (LCP, FID)\nKhai báo Schema Markup tiêu chuẩn",
                    ],
                    [
                        'module_icon'  => 'mail',
                        'module_color' => 'neutral',
                        'module_title' => 'Module Liên hệ',
                        'module_items' => "Form gửi yêu cầu tư vấn\nThông báo email tự động về admin\nChống spam qua Google reCAPTCHA",
                    ],
                ]),
        ]);

        // Tab: Bảo trì & Bảo hành
        $container->add_tab(__('Bảo trì & Bảo hành', 'laca'), [
            Field::make('html', 'warranty_tab_info', '')
                ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🛡️ Bảo trì & Bảo hành</p><p style="margin:0;font-size:13px;color:#374151">Nhập chính sách bảo hành, bảo trì áp dụng cho dự án — nội dung này sẽ hiển thị trong bản báo giá gửi khách hàng.</p></div>'),

            Field::make('select', 'warranty_status', __('Trạng thái bảo hành', 'laca'))
                ->set_width(30)
                ->set_default_value('free')
                ->add_options([
                    'free'     => 'Bảo hành miễn phí',
                    'paid'     => 'Bảo hành có phí',
                    'expired'  => 'Hết bảo hành',
                    'none'     => 'Không áp dụng',
                ]),

            Field::make('rich_text', 'warranty_policy', __('', 'laca'))
                ->set_width(70)
                ->set_default_value('
                 <h2 class="text-2xl md:text-3xl font-bold mb-6 tracking-tight">Chính sách Bảo trì &amp; Bảo hành</h2>
                    <ul>
                    <li><strong>Bảo hành trọn đời:</strong> Áp dụng cho các lỗi kỹ thuật phát sinh từ phía hệ thống code do lacadev triển khai khi khách hàng sử dụng dịch vụ Hosting tại lacadev.</li>
                    <li><strong>Hỗ trợ vận hành:</strong> Miễn phí hướng dẫn sử dụng và hỗ trợ kỹ thuật qua Hotline/Zalo trong suốt quá trình vận hành website.</li>
                    </ul>
                    '),
        ]);

        $container->add_tab(__('Thanh toán', 'laca'), [
            Field::make('complex', 'payment_steps', __('Quy trình thanh toán', 'laca'))
                ->set_layout('tabbed-vertical')
                ->setup_labels([
                    'plural_name'   => 'Đợt thanh toán',
                    'singular_name' => 'Đợt thanh toán',
                ])
                ->add_fields([
                    Field::make('text', 'step_number', __('', 'laca'))
                        ->set_width(10)
                        ->set_attribute('placeholder', '1'),
                    Field::make('text', 'step_title', __('', 'laca'))
                        ->set_width(45)
                        ->set_attribute('placeholder', 'Tạm ứng 50%'),
                    Field::make('text', 'step_desc', __('', 'laca'))
                        ->set_width(45)
                        ->set_attribute('placeholder', 'Sau khi ký hợp đồng'),
                ])
                ->set_header_template('<% if (step_title) { %><%- step_title %><% } %>'),

            Field::make('text', 'quotation_valid_days', __('Hiệu lực báo giá (ngày)', 'laca'))
                ->set_attribute('placeholder', '15')
                ->set_default_value('15')
                ->set_help_text('Số ngày báo giá còn hiệu lực kể từ ngày lập.'),
        ]);
    }

    // =========================================================================
    // Tab 3: Trạng thái & Thời gian
    // =========================================================================

    private function addTabStatus(Container $container): void
    {
        $container->add_tab(__('Trạng thái', 'laca'), [
            Field::make('html', 'status_tab_info', '')
                ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">📌 Trạng thái dự án</p><p style="margin:0;font-size:13px;color:#374151">Theo dõi tiến độ và gắn nhãn phân loại cho dự án. Trạng thái này hiển thị trên danh sách và dashboard quản lý dự án.</p></div>'),

            Field::make('select', 'project_status', __('Trạng thái dự án', 'laca'))
                ->set_width(50)
                ->add_options([
                    'pending'     => 'Chờ làm',
                    'in_progress' => 'Đang làm',
                    'done'        => 'Đã xong',
                    'maintenance' => 'Đang bảo trì',
                    'paused'      => 'Tạm dừng',
                ])
                ->set_default_value('pending'),

            Field::make('multiselect', 'project_tags', __('Tags / Nhãn dự án', 'laca'))
                ->set_width(50)
                ->add_options([
                    'wordpress'    => 'WordPress',
                    'woocommerce'  => 'WooCommerce',
                    'landing_page' => 'Landing Page',
                    'ecommerce'    => 'E-commerce',
                    'blog'         => 'Blog',
                    'portfolio'    => 'Portfolio',
                    'booking'      => 'Booking',
                ]),
        ]);
    }

    // =========================================================================
    // Tab 4: Tài chính
    // =========================================================================

    private function addTabFinance(Container $container): void
    {
        $container->add_tab(__('Tài chính', 'laca'), [
            Field::make('html', 'finance_tab_info', '')
                ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">💰 Tài chính dự án</p><p style="margin:0;font-size:13px;color:#374151">Quản lý giá trị hợp đồng, phí bảo trì hàng năm và lịch sử thanh toán. Trạng thái thanh toán sẽ tự động cập nhật dựa theo lịch sử nhập bên dưới.</p></div>'),

            Field::make('text', 'price_build', __('Giá build', 'laca'))
                ->set_width(33.33)
                ->set_attribute('data-type', 'currency')
                ->set_attribute('placeholder', '8.000.000')
                ->set_classes('laca-price-build'),

            Field::make('text', 'price_maintenance_yearly', __('Phí bảo trì hàng năm', 'laca'))
                ->set_width(33.33)
                ->set_attribute('data-type', 'currency')
                ->set_attribute('placeholder', '2.000.000'),

            Field::make('select', 'payment_status', __('Trạng thái thanh toán (Auto)', 'laca'))
                ->set_width(33.33)
                ->add_options([
                    'pending' => 'Chưa thanh toán',
                    'partial' => 'Đã thanh toán một phần',
                    'paid'    => 'Đã thanh toán đủ',
                    'overdue' => 'Quá hạn thanh toán',
                ])
                ->set_default_value('pending')
                ->set_classes('laca-payment-status'),

            Field::make('complex', 'payment_history', __('Lịch sử thanh toán từng đợt', 'laca'))
                ->setup_labels([
                    'plural_name'   => 'Lần thanh toán',
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

            Field::make('file', 'invoice_file', __('File hóa đơn / Bằng chứng chung', 'laca'))
                ->set_width(30)
                ->set_type(['application/pdf', 'image']),

            Field::make('textarea', 'finance_note', __('Ghi chú tài chính nội bộ', 'laca'))
                ->set_width(70)
                ->set_rows(2)
                ->set_attribute('placeholder', 'Ghi chú nhắc nợ...'),
        ]);
    }

    // =========================================================================
    // Tab 5: Hosting & Domain
    // =========================================================================

    private function addTabHostingDomain(Container $container): void
    {
        $container->add_tab(__('Hosting & Domain', 'laca'), [
            Field::make('html', 'hosting_domain_tab_info', '')
                ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🌐 Hosting & Domain</p><p style="margin:0;font-size:13px;color:#374151">Lưu thông tin domain, hosting, SSL và FTP của dự án để tiện tra cứu và tự động nhận cảnh báo khi sắp hết hạn.</p></div>'),

            // DOMAIN
            Field::make('separator', 'sep_domain', __('Thông tin Domain', 'laca')),

            Field::make('text', 'domain_name', __('Tên miền', 'laca'))
                ->set_width(33.33)
                ->set_attribute('placeholder', 'example.com'),

            Field::make('text', 'domain_price', __('Giá gia hạn / năm', 'laca'))
                ->set_width(33.33)
                ->set_attribute('data-type', 'currency')
                ->set_attribute('placeholder', '500.000'),

            Field::make('date', 'domain_expiry', __('Ngày hết hạn Domain', 'laca'))
                ->set_width(33.33)
                ->set_storage_format('Y-m-d'),

            Field::make('text', 'domain_registrar', __('Nhà đăng ký', 'laca'))
                ->set_width(50)
                ->set_attribute('placeholder', 'GoDaddy / NameSilo / PA Vietnam...'),

            Field::make('text', 'domain_username', __('Tài khoản đăng nhập', 'laca'))
                ->set_width(50)
                ->set_attribute('placeholder', 'username'),

            Field::make('text', 'domain_password', __('Mật khẩu (mã hóa khi lưu)', 'laca'))
                ->set_width(50)
                ->set_attribute('placeholder', '••••••••')
                ->set_attribute('type', 'password')
                ->set_classes('laca-password-input'),

            Field::make('text', 'domain_notify_days', __('Cảnh báo trước (ngày)', 'laca'))
                ->set_width(50)
                ->set_default_value('30')
                ->set_attribute('placeholder', '30'),

            // HOSTING
            Field::make('separator', 'sep_hosting', __('Thông tin Hosting', 'laca')),

            Field::make('text', 'hosting_price', __('Giá gia hạn / năm', 'laca'))
                ->set_width(33.33)
                ->set_attribute('data-type', 'currency')
                ->set_attribute('placeholder', '1.200.000'),

            Field::make('date', 'hosting_expiry', __('Ngày hết hạn Hosting', 'laca'))
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

            // SSL
            Field::make('separator', 'sep_ssl', __('Thông tin SSL', 'laca')),

            Field::make('date', 'ssl_expiry', __('Ngày hết hạn SSL', 'laca'))
                ->set_width(50)
                ->set_storage_format('Y-m-d'),

            Field::make('text', 'ssl_notify_days', __('Cảnh báo trước (ngày)', 'laca'))
                ->set_width(50)
                ->set_default_value('14')
                ->set_attribute('placeholder', '14'),

            // FTP
            Field::make('separator', 'sep_ftp', __('FTP / SFTP', 'laca')),

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
    }

    // =========================================================================
    // Tab 7: Tech Specs
    // =========================================================================

    private function addTabTechSpecs(Container $container): void
    {
        $container->add_tab(__('Tech Specs', 'laca'), [
            Field::make('html', 'tech_specs_tab_info', '')
                ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">⚙️ Tech Specs</p><p style="margin:0;font-size:13px;color:#374151">Thông tin kỹ thuật và bảng màu thương hiệu của dự án, dùng để tham chiếu nhanh khi bàn giao hoặc tra cứu.</p></div>'),

            Field::make('text', 'demo_design_url', __('URL web mẫu / Link Figma', 'laca'))
                ->set_width(50)
                ->set_attribute('placeholder', 'https://'),

            Field::make('text', 'live_url', __('URL website đang chạy', 'laca'))
                ->set_width(50)
                ->set_attribute('placeholder', 'https://example.com')
                ->set_help_text('URL thực tế của website (dùng trong Client Portal và danh sách project).'),

            Field::make('separator', 'sep_brand_colors', __('Màu sắc chủ đạo', 'laca')),

            Field::make('complex', 'brand_colors', __('Tối đa 3 màu', 'laca'))
                ->set_max(3)
                ->setup_labels([
                    'plural_name'   => 'Màu',
                    'singular_name' => 'Màu',
                ])
                ->add_fields([
                    Field::make('color', 'hex', __('Mã màu (HEX)', 'laca'))
                        ->set_help_text('Ví dụ: #0F172A'),
                    Field::make('text', 'label', __('Ghi chú (tuỳ chọn)', 'laca'))
                        ->set_attribute('placeholder', 'Primary / Accent / Background...'),
                ])
                ->set_header_template('<% if (hex) { %><%-hex%><% } else { %>Màu mới<% } %>'),
        ]);
    }

    // =========================================================================
    // Tab 8: Block Sync
    // =========================================================================

    private function addTabBlockSync(Container $container): void
    {
        $container->add_tab(__('Block Sync', 'laca'), [
            Field::make('html', 'block_sync_tab_info', '')
                ->set_html('<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0"><p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔄 Block Sync</p><p style="margin:0;font-size:13px;color:#374151">Kết nối tới site khách để đẩy (push) các Gutenberg blocks từ hub sang site client qua REST API.</p></div>'),

            Field::make('separator', 'sep_block_sync_info', __('Cấu hình REST API cho client site', 'laca')),

            Field::make('text', 'sync_api_key', __('API Key của client', 'laca'))
                ->set_help_text(__('Lấy từ Settings → LacaDev trên website client. Dùng để xác thực khi push blocks.', 'laca'))
                ->set_attribute('type', 'password')
                ->set_width(50),

            Field::make('text', 'sync_endpoint_url', __('Block Sync Endpoint URL', 'laca'))
                ->set_help_text(__('URL đến REST endpoint. Ví dụ: https://client.com/wp-json/lacadev/v1/sync-block', 'laca'))
                ->set_width(50),
        ]);
    }
}
