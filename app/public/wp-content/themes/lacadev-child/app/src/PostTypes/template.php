<?php

namespace App\PostTypes;

use Carbon_Fields\Container\Container;
use Carbon_Fields\Field;

class template extends \App\Abstracts\AbstractPostType
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
        // $this->menuIcon = get_template_directory_uri() . '/images/custom-icon.png';
        $this->post_type        = 'template';
        $this->singularName     = $this->pluralName = __('Giao diện mẫu', 'laca');
        $this->titlePlaceHolder = __('Giao diện mẫu', 'laca');
        $this->slug             = 'templates';
        parent::__construct();
    }

    public function metaFields()
    {
        Container::make('post_meta', __('Project Settings | Cài đặt dự án', 'laca'))
            ->where('post_type', '=', $this->post_type)
            ->add_tab(__('Visuals', 'laca'), [
                Field::make('select', 'is_real', __('Đã làm thực tế', 'laca'))
                    ->add_options([
                        'yes' => 'Có',
                        'no' => 'Không',
                    ])
                    ->set_width(40),
                Field::make('text', 'live_url', __('Live Demo URL', 'laca'))
                    ->set_width(60)
                    ->set_attribute('placeholder', 'https://...'),
                Field::make('image', 'quick_view_img', __('Quick view', 'laca'))
                    ->set_width(30),
                Field::make('media_gallery', 'gallery', __('Thư viện ảnh (Gallery)', 'laca'))
                    ->set_type(['image'])
                    ->set_width(70),
            ])
            ->add_tab(__('Tech Specs', 'laca'), [
                Field::make('multiselect', 'platform', __('Nền tảng (Platform)', 'laca'))
                    ->set_width(50)
                    ->add_options([
                        'wordpress'    => 'WordPress',
                        'woocommerce'  => 'WooCommerce',
                        'landing_page' => 'Landing Page',
                        'shopify'      => 'Shopify',
                    ]),
                Field::make('multiselect', 'builder', __('Công cụ xây dựng (Page Builder)', 'laca'))
                    ->set_width(50)
                    ->set_default_value('none')
                    ->add_options([
                        'bricks'    => 'Bricks Builder',
                        'gutenberg' => 'Gutenberg',
                        'elementor' => 'Elementor',
                        'flatsome'  => 'Flatsome',
                        'none'      => __('Code thuần', 'laca'),
                    ]),
                Field::make('multiselect', 'features', __('Tính năng phổ biến (Presets)', 'laca'))
                    ->add_options([
                        'landing_page'   => __('Landing Page', 'laca'),
                        'multi_language' => __('Multi-language', 'laca'),
                        'booking'        => __('Booking System', 'laca'),
                        'payment'        => __('Payment Gateway', 'laca'),
                        'flash_sale'     => __('Flash Sale', 'laca'),
                        'seo'            => __('SEO Optimized', 'laca'),
                        'speed'          => __('High Speed', 'laca'),
                    ]),
                Field::make('complex', 'custom_features', __('Tính năng khác (Tùy chỉnh)', 'laca'))
                    ->set_help_text(__('Dùng để thêm các tính năng đặc thù cho dự án này mà không có trong danh sách trên.', 'laca'))
                    ->add_fields([
                        Field::make('text', 'name', __('Tên tính năng', 'laca')),
                    ])
                    ->set_header_template('<% if (name) { %><%- name %><% } %>'),
            ])
            ->add_tab(__('Conversion', 'laca'), [
                Field::make('text', 'price_label', __('Giá ước tính (Price Tag)', 'laca'))
                    ->set_width(50)
                    ->set_attribute('placeholder', __('Chỉ từ 5.000.000đ', 'laca')),
                Field::make('text', 'duration', __('Thời gian hoàn thành dự kiến', 'laca'))
                    ->set_width(50)
                    ->set_attribute('placeholder', __('Ví dụ: 5-7 ngày', 'laca')),
                Field::make('multiselect', 'included_services', __('Gói dịch vụ đi kèm', 'laca'))
                    ->add_options([
                        'hosting'          => __('Tặng kèm Hosting 1 năm', 'laca'),
                        'domain'           => __('Tặng kèm Domain (.com)', 'laca'),
                        'ssl'              => __('Miễn phí chứng chỉ SSL', 'laca'),
                        'maintenance'      => __('Bảo trì miễn phí 12 tháng', 'laca'),
                        'seo'              => __('Tối ưu SEO cơ bản', 'laca'),
                        'speed'            => __('Tối ưu tốc độ PageSpeed', 'laca'),
                        'training'         => __('Hướng dẫn quản trị website', 'laca'),
                    ])
                    ->set_default_value(['hosting', 'domain', 'ssl', 'maintenance', 'seo', 'speed', 'training']),
            ]);
    }
}
