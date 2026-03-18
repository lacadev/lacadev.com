<?php

use Carbon_Fields\Block;
use Carbon_Fields\Field;

Block::make(__('Block ABOUT ME', 'laca'))
    ->add_fields([
        Field::make('separator', 'about_spt', __('BLOCK ABOUT ME', 'laca')),
        //Circle
        Field::make('text', 'welcome_content', __('', 'laca'))
            ->set_attribute('placeholder', 'Nhập nội dung circle')
            ->set_default_value('about me - about me - about me - about me - about me -'),
        //Image
        Field::make('image', 'about_image', __('', 'laca')),
        //Title
        Field::make('text', 'about_title', __('', 'laca'))
            ->set_attribute('placeholder', 'Nhập tiêu đề của block'),
        //Description
        Field::make('textarea', 'about_desc', __('', 'laca'))
            ->set_attribute('placeholder', 'Nhập mô tả của block'),
    ])
    ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
        $circle = !empty($fields['welcome_content']) ? esc_html($fields['welcome_content']) : '';
        $image = getImageUrlById($fields['about_image']);
        $title = !empty($fields['about_title']) ? esc_html($fields['about_title']) : '';
        $desc = !empty($fields['about_desc']) ? apply_filters('the_content', $fields['about_desc']) : '';
        ?>
        <section class="block-about">
            <div class="block-about__head">
                <div class="scroll-circle">
                    <svg viewBox="0 0 200 200">
                        <path id="circlePath" d="M100,100 m-75,0 a75,75 0 1,1 150,0 a75,75 0 1,1 -150,0" fill="none" />
                        <text>
                            <textPath href="#circlePath" startOffset="0">
                                <?php echo $circle; ?>
                            </textPath>
                        </text>
                    </svg>
                    <div class="arrow"></div>
                </div>

                <div class="block-about__img">
                    <figure>
                        <img src="<?php echo $image; ?>" alt="<?php echo $title; ?>" loading="lazy">
                    </figure>
                </div>
            </div>
            <div class="block-about__body">
                <h2 class="block-title text-center"><?php echo $title; ?></h2>
                <div class="block-desc"><?php echo $desc; ?></div>
            </div>

        </section>
        <?php
    });
