<?php

use Carbon_Fields\Block;
use Carbon_Fields\Field;

$addLabels = array(
    'plural_name' => 'media',
    'singular_name' => 'media',
);

Block::make(__('Block blog', 'laca'))
    ->add_fields([
        Field::make('separator', 'blog_spt', __('BLOCK TIN TỨC', 'laca')),
        //Title
        Field::make('text', 'blog_title', __('', 'laca'))->set_width(60)
            ->set_attribute('placeholder', 'Nhập tiêu đề của block'),
        //Description
        Field::make('textarea', 'blog_desc', __('', 'laca'))
            ->set_attribute('placeholder', 'Nhập mô tả của block'),
        //URL
        Field::make('text', 'blog_page_url', __('', 'laca'))->set_width(30)
            ->set_attribute('placeholder', 'Nhập URL của trang blog'),
        // type media
        Field::make('select', 'display_type', __('', 'laca'))->set_width(20)
            ->set_default_value('auto')
            ->set_options([
                'auto' => __('Auto'),
                'manual' => __('Manual'),
            ]),
        //Auto
        Field::make('separator', 'auto_spt', __('Tự động hiển thị 3 bài viết mới nhất', 'laca'))->set_width(50)
            ->set_conditional_logic([
                'relation' => 'AND',
                ['field' => 'display_type', 'value' => 'auto', 'compare' => '='],
            ]),
        //Manual
        Field::make('separator', 'manual_spt', __('Chọn bài viết thủ công', 'laca'))->set_width(50)
            ->set_conditional_logic([
                'relation' => 'AND',
                ['field' => 'display_type', 'value' => 'manual', 'compare' => '='],
            ]),
        //Manual
        Field::make('association', 'manual_blog', __('', 'laca'))->set_width(70)
            ->set_types([
                [
                    'type'      => 'post',
                    'post_type' => 'post',
                ]
            ])
            ->set_conditional_logic([
                'relation' => 'AND',
                ['field' => 'display_type', 'value' => 'manual', 'compare' => '='],
            ]),
    ])
    ->set_render_callback(function ($fields, $attributes, $inner_blocks) {
        $title = !empty($fields['blog_title']) ? esc_html($fields['blog_title']) : '';
        $desc = !empty($fields['blog_desc']) ? wp_kses_post($fields['blog_desc']) : '';
        $url = !empty($fields['blog_page_url']) ? esc_url($fields['blog_page_url']) : '';
        $type = !empty($fields['display_type']) ? $fields['display_type'] : '';
        $blogs = !empty($fields['manual_blog']) ? $fields['manual_blog'] : '';
?>
    <section class="blog-block">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="main-content">
                        <div class="heading">
                            <a href="<?= $url; ?>">
                                <h2 class="block-title"> <?= $title; ?> </h2>
                            </a>
                        </div>
                        <div class="desc">
                            <?= apply_filters('the_content', $desc); ?>
                        </div>
                    </div>
                </div>

                <?php
                if ($type == 'auto') :
                    $post_query = new WP_Query([
                        'post_type' => 'blog',
                        'posts_per_page' => 3,
                        'post_status' => 'publish',
                        'orderby' => 'date',
                        'order' => 'DESC',
                    ]);
                    if ($post_query->have_posts()) :
                        while ($post_query->have_posts()) : $post_query->the_post();
                            get_template_part('template-parts/loop', 'post');
                        endwhile;
                    endif;
                    wp_reset_postdata();
                    wp_reset_query();
                elseif ($type == 'manual') :
                    foreach ($blogs as $blog) :
                        get_template_part('template-parts/loop', 'post');
                    endforeach;
                endif;
                ?>
            </div>
        </div>
    </section>
<?php
    });
