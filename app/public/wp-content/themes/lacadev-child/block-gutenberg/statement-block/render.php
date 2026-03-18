<?php
/**
 * Statement Block Render Template.
 */
$title = !empty($attributes['title']) ? $attributes['title'] : '';
$subtitle = !empty($attributes['subtitle']) ? $attributes['subtitle'] : '';

$class_name = 'block-statement';
if (!empty($attributes['className'])) {
    $class_name .= ' ' . $attributes['className'];
}
?>

<section class="<?php echo esc_attr($class_name); ?>">
    <div class="container">
        <?php if ($subtitle) : ?>
            <p class="statement-subtitle"><?php echo esc_html($subtitle); ?></p>
        <?php endif; ?>
        
        <?php if ($title) : ?>
            <h2 class="statement-title"><?php echo wp_kses_post($title); ?></h2>
        <?php endif; ?>
    </div>
</section>
