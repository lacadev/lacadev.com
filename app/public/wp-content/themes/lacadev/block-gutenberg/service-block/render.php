<?php
$title = !empty($attributes['title']) ? $attributes['title'] : '';
$description = !empty($attributes['description']) ? $attributes['description'] : '';
$service_ids = !empty($attributes['serviceIds']) ? $attributes['serviceIds'] : [];

$class_name = 'block-service';
if (!empty($attributes['className'])) {
    $class_name .= ' ' . $attributes['className'];
}

$services = [];
if (!empty($service_ids)) {
    $query = new WP_Query([
        'post_type' => 'service',
        'post__in' => $service_ids,
        'posts_per_page' => -1,
        'orderby' => 'post__in'
    ]);
    if ($query->have_posts()) {
        $services = $query->posts;
    }
    wp_reset_postdata();
}
?>

<section class="<?php echo esc_attr($class_name); ?>">
    <div class="container">
        <?php if ($title) : ?>
            <h2 class="block-title block-title-scroll"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>
        
        <?php if ($description) : ?>
            <div class="block-desc">
                <?php echo wp_kses_post($description); ?>
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
        <?php endif; ?>
    </div>
</section>
