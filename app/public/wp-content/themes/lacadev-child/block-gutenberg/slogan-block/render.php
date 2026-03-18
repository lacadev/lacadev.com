<?php
$slogan = !empty($attributes['slogan']) ? $attributes['slogan'] : '';
$class_name = 'block-slogan';
if (!empty($attributes['className'])) {
    $class_name .= ' ' . $attributes['className'];
}
?>
<section class="<?php echo esc_attr($class_name); ?>">
    <div class="container">
        <?php echo wp_kses_post($slogan); ?>
    </div>
</section>
