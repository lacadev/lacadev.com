<?php
/**
 * Staggered Blog Block Render Template.
 */

$title = !empty($attributes['title']) ? $attributes['title'] : '';
$description = !empty($attributes['description']) ? $attributes['description'] : '';
$mode = !empty($attributes['mode']) ? $attributes['mode'] : 'auto';
$order_by = !empty($attributes['orderBy']) ? $attributes['orderBy'] : 'date';
$post_type = !empty($attributes['postType']) ? $attributes['postType'] : 'post';
$taxonomy = isset($attributes['taxonomy']) ? $attributes['taxonomy'] : 'category';
$term_ids = !empty($attributes['termIds']) ? $attributes['termIds'] : [];
$category_ids = !empty($attributes['categoryIds']) ? $attributes['categoryIds'] : [];
$effective_term_ids = !empty($term_ids) ? $term_ids : $category_ids;
$post_ids = !empty($attributes['postIds']) ? $attributes['postIds'] : [];
$count = !empty($attributes['count']) ? $attributes['count'] : 3;

$args = [
    'post_type' => $post_type,
    'post_status' => 'publish',
    'ignore_sticky_posts' => 1,
];

if ($mode === 'manual' && !empty($post_ids)) {
    $args['post__in'] = $post_ids;
    $args['posts_per_page'] = -1;
    $args['orderby'] = 'post__in';
} else {
    $args['posts_per_page'] = $count;
    
    if (!empty($taxonomy) && !empty($effective_term_ids)) {
        $args['tax_query'] = [
            [
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => $effective_term_ids,
            ],
        ];
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

$class_name = 'block-staggered-blog';
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
            <div class="staggered-list">
                <?php 
                $index = 0;
                while ($query->have_posts()) : $query->the_post(); 
                    $is_even = ($index % 2 !== 0);
                    $excerpt = get_the_excerpt();
                    // Limit excerpt length for this specific design
                    $excerpt = wp_trim_words($excerpt, 50, '...');
                ?>
                    <div class="staggered-item <?php echo $is_even ? 'staggered-item--even' : 'staggered-item--odd'; ?>">
                        <div class="staggered-item__content">
                            <h3 class="staggered-item__title">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h3>
                            <div class="staggered-item__desc">
                                <?php echo esc_html($excerpt); ?>
                            </div>
                        </div>
                        <div class="staggered-item__image">
                            <a href="<?php the_permalink(); ?>" data-cursor-arrow>
                                <?php theResponsivePostThumbnail('tablet', ['alt' => esc_attr(get_the_title())]); ?>
                            </a>
                        </div>
                    </div>
                <?php 
                    $index++;
                endwhile; 
                wp_reset_postdata(); 
                ?>
            <?php 
            $button_text = !empty($attributes['buttonText']) ? $attributes['buttonText'] : ''; 
            $button_url = !empty($attributes['buttonUrl']) ? $attributes['buttonUrl'] : ''; 
            ?>
            <?php if ($button_text && $button_url) : ?>
                <div class="block-footer text-center" style="margin-top: 8rem;">
                    <a href="<?php echo esc_url($button_url); ?>" class="btn btn-minimal">
                        <span class="btn-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="20" x2="20" y2="4"></line><polyline points="10 4 20 4 20 14"></polyline></svg>
                        </span>
                        <span class="btn-text"><?php echo esc_html($button_text); ?></span>
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
