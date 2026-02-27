<?php
/**
 * Blog Block Render Template.
 *
 * @param array $attributes Block attributes.
 * @param string $content Block default content.
 * @param bool $is_preview True during backend preview.
 * @param int $post_id The post ID the block is rendering on.
 */

$title = !empty($attributes['title']) ? $attributes['title'] : '';
$description = !empty($attributes['description']) ? $attributes['description'] : '';
$mode = !empty($attributes['mode']) ? $attributes['mode'] : 'auto';
$order_by = !empty($attributes['orderBy']) ? $attributes['orderBy'] : 'date';
$category_ids = !empty($attributes['categoryIds']) ? $attributes['categoryIds'] : [];
$post_ids = !empty($attributes['postIds']) ? $attributes['postIds'] : [];
$count = !empty($attributes['count']) ? $attributes['count'] : 3;

$args = [
    'post_type' => 'post',
    'post_status' => 'publish',
    'ignore_sticky_posts' => 1,
];

if ($mode === 'manual' && !empty($post_ids)) {
    $args['post__in'] = $post_ids;
    $args['posts_per_page'] = -1;
    $args['orderby'] = 'post__in';
} else {
    $args['posts_per_page'] = $count;
    
    if (!empty($category_ids)) {
        $args['category__in'] = $category_ids;
    }

    if ($order_by === 'rand') {
        // PERFORMANCE FIX: Prevent slow queries caused by ORDER BY RAND()
        $args['orderby'] = 'date';
        $args['order'] = 'DESC';
        $do_shuffle = true;
    } elseif ($order_by === 'comment_count') {
        $args['orderby'] = 'comment_count';
        $args['order'] = 'DESC';
    } else {
        $args['orderby'] = 'date';
        $args['order'] = 'DESC';
    }
}

$query = new WP_Query($args);

if (isset($do_shuffle) && $do_shuffle && $query->have_posts()) {
    shuffle($query->posts);
}

$class_name = 'block-blog';
if (!empty($attributes['className'])) {
    $class_name .= ' ' . $attributes['className'];
}
?>

<section class="<?php echo esc_attr($class_name); ?>">
    <div class="container">
        <?php if ($title || $description) : ?>
            <div class="block-header">
                <?php if ($title) : ?>
                    <h2 class="block-title"><?php echo esc_html($title); ?></h2>
                <?php endif; ?>
                <?php if ($description) : ?>
                    <div class="block-desc">
                        <?php echo wp_kses_post($description); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($query->have_posts()) : ?>
            <div class="blog-list">
                <?php while ($query->have_posts()) : $query->the_post(); 
                    $author_name = get_the_author();
                    // Optional: human readable time
                    $time_diff = human_time_diff(get_the_time('U'), current_time('timestamp'));
                    // Translate 'ago' if needed or just use simple format
                    $time_diff_text = sprintf(__('%s ago', 'laca'), $time_diff);
                ?>
                    <div class="blog-item">
                        <div class="blog-card">
                            <a href="<?php the_permalink(); ?>" class="card-link" data-cursor-arrow>
                                <div class="card-image-wrap">
                                    <?php theResponsivePostThumbnail('mobile', ['alt' => esc_attr(get_the_title())]); ?>
                                </div>
                                <div class="card-body">
                                    <h3 class="card-title"><?php the_title(); ?></h3>
                                    <div class="card-meta">
                                        <span class="meta-author"><?php printf(__('By %s', 'laca'), esc_html($author_name)); ?></span>
                                        <span class="meta-date"><?php echo esc_html($time_diff_text); ?></span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
            
            <?php 
            $button_text = !empty($attributes['buttonText']) ? $attributes['buttonText'] : ''; 
            $button_url = !empty($attributes['buttonUrl']) ? $attributes['buttonUrl'] : ''; 
            ?>
            <?php if ($button_text && $button_url) : ?>
                <div class="block-footer">
                    <a href="<?php echo esc_url($button_url); ?>" class="btn btn-minimal">
                        <span class="btn-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="20" x2="20" y2="4"></line><polyline points="10 4 20 4 20 14"></polyline></svg>
                        </span>
                        <span class="btn-text"><?php echo esc_html($button_text); ?></span>
                    </a>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <?php if ($is_preview) : ?>
                <div class="block-alert"><?php _e('Vui lòng chọn hoặc thêm bài viết để hiển thị.', 'laca'); ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
