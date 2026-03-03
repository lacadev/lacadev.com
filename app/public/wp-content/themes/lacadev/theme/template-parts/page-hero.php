<?php
/**
 * Unified Page Header / Hero section for all pages and archives
 * 
 * @package LacaDev
 */

$title = '';
$excerpt = '';

if (is_archive()) {
    if (is_post_type_archive()) {
        $title = post_type_archive_title('', false);
    } elseif (is_category()) {
        $title = single_cat_title('', false);
    } elseif (is_tag()) {
        $title = single_tag_title('', false);
    } else {
        $title = get_the_archive_title();
    }
    $excerpt = get_the_archive_description();
} elseif (is_home()) {
    $title = single_post_title('', false);
    // Usually the posts page doesn't show its own excerpt by default in WP, but we can fetch it
    $posts_page_id = get_option('page_for_posts');
    if ($posts_page_id) {
        $excerpt = get_the_excerpt($posts_page_id);
    }
} else {
    $title = get_the_title();
    if (has_excerpt()) {
        $excerpt = get_the_excerpt();
    }
}

$title_class = 'page-title';

// Special case for Service Archive if we want custom text from Theme Options
if (is_post_type_archive('service')) {
    $service_title = getOption('service_page_title');
    $service_desc = getOption('service_page_description');

    if ($service_title) {
        $title = $service_title;
    }
    if ($service_desc) {
        $excerpt = $service_desc;
    } elseif (empty($excerpt)) {
        $excerpt = __('Khám phá các giải pháp công nghệ chuyên sâu giúp bạn bứt phá hành trình số.', 'laca');
    }

    $title_class .= ' block-title-scroll';
}
?>

<div class="page-hero">
    <div class="container">
        <div class="hero-content" data-aos="fade-up">
            <h1 class="<?php echo esc_attr($title_class); ?>"><?php echo esc_html($title); ?></h1>
            <?php if ($excerpt) : ?>
                <div class="page-excerpt">
                    <?php echo wp_kses_post($excerpt); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
