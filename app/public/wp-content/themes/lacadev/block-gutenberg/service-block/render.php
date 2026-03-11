<?php
$title = !empty($attributes['title']) ? $attributes['title'] : '';
$description = !empty($attributes['description']) ? $attributes['description'] : '';
$post_type = !empty($attributes['postType']) ? $attributes['postType'] : 'service';
$taxonomy = isset($attributes['taxonomy']) ? $attributes['taxonomy'] : '';
$term_ids = !empty($attributes['termIds']) ? $attributes['termIds'] : [];
$mode = !empty($attributes['mode']) ? $attributes['mode'] : 'manual';
$post_ids = !empty($attributes['postIds']) ? $attributes['postIds'] : [];
// Back-compat: old `serviceIds`
$service_ids = !empty($attributes['serviceIds']) ? $attributes['serviceIds'] : [];
$effective_post_ids = !empty($post_ids) ? $post_ids : $service_ids;

$class_name = 'block-service';
if (!empty($attributes['className'])) {
    $class_name .= ' ' . $attributes['className'];
}

$services = [];
if (!empty($effective_post_ids)) {
    $query = new WP_Query([
        'post_type' => $post_type,
        'post__in' => $effective_post_ids,
        'posts_per_page' => -1,
        'orderby' => 'post__in',
    ]);
    if ($query->have_posts()) {
        $services = $query->posts;
    }
    wp_reset_postdata();
}
?>

<section class="<?php echo esc_attr($class_name); ?>">
    <div class="container">
        <?php if ($title || $description) : ?>
            <div class="block-header">
                <?php if ($title) : ?>
                    <h2 class="block-title block-title-scroll"><?php echo esc_html($title); ?></h2>
                <?php endif; ?>
                <?php if ($description) : ?>
                    <div class="block-desc">
                        <?php echo wp_kses_post($description); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($services)) : ?>
            <div class="block-service__list">
                <?php foreach ($services as $service) : 
                    $service_title = get_the_title($service->ID);
                    $first_letter = mb_substr($service_title, 0, 1);
                    $excerpt = get_the_excerpt($service->ID);
                    $link = get_permalink($service->ID);
                ?>
                    <div class="block-service__item">
                        <a href="<?php echo esc_url($link); ?>" class="item__link" data-cursor-arrow>
                            <span class="item__icon"><?php echo esc_html($first_letter); ?></span>
                            <h3 class="item__title"><?php echo esc_html($service_title); ?></h3>
                            <div class="item__desc">
                                <?php echo esc_html($excerpt); ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
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
        <?php endif; ?>
    </div>
</section>
