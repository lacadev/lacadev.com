<?php

use Carbon_Fields\Block;
use Carbon_Fields\Field;

Block::make(__('Block Service', 'mms'))
    ->add_fields([
        Field::make('separator', 'service_section', __('BLOCK SERVICE', 'mms')),
        Field::make('text', 'service_title', __('', 'mms'))
            ->set_attribute('placeholder', 'Nhập tiêu đề của block'),
        Field::make('textarea', 'service_desc', __('', 'mms'))
            ->set_attribute('placeholder', 'Nhập mô tả của block'),

        Field::make('association', 'service_obj', __('Chọn dịch vụ hiển thị:', 'mms'))
            ->set_types([
                [
                    'type' => 'post', 
                    'post_type' => 'service',
                ],
            ]),
    ])
    ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
        $title = !empty($fields['service_title']) ? esc_html($fields['service_title']) : '';
        $desc = !empty($fields['service_desc']) ? apply_filters('the_content', $fields['service_desc']) : '';
        $services = $fields['service_obj'];
    ?>
        <section class="block-service">
            <div class="container">
                <h2 class="block-title block-title-scroll"><?php echo $title; ?></h2>
                <div class="block-desc"><?php echo $desc; ?></div>

                <div class="block-service__list">
                    <?php
                    foreach ($services as $service) :
                        $permalink = get_the_permalink($service['id']);
                        $title = get_the_title($service['id']);
                        $desc = get_the_excerpt($service['id']);
                        $firstLetter = substr($title, 0, 1);
                    ?>
                        <div class="block-service__item">
                            <a href="<?php echo $permalink; ?>" class="item__link">
                                <span class="item__icon"><?php echo $firstLetter; ?></span>
                                <h3 class="item__title"><?php echo $title; ?></h3>
                                <div class="item__desc"><?php echo $desc; ?></div>
                            </a>
                        </div>
                    <?php
                    endforeach;
                    ?>
                </div>
            </div>

        </section>
    <?php
    });
