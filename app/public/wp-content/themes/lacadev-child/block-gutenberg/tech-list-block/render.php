<?php
/**
 * Tech List Block Render Template.
 *
 * @param array $attributes Block attributes.
 * @param string $content Block default content.
 * @param bool $is_preview True during backend preview.
 * @param int $post_id The post ID the block is rendering on.
 */

$technologies = !empty($attributes['technologies']) ? $attributes['technologies'] : [];

$class_name = 'block-tech-list';
if (!empty($attributes['className'])) {
    $class_name .= ' ' . $attributes['className'];
}

// Generate unique ID for CSS
$block_id = 'tech-list-' . wp_generate_uuid4();
?>

<section class="<?php echo esc_attr($class_name); ?>" id="<?php echo esc_attr($block_id); ?>">
    <div class="container">
        <?php if (!empty($technologies)) : ?>
            <div class="tech-list-grid">
                <?php foreach ($technologies as $tech) : 
                    $tech_name = isset($tech['name']) ? $tech['name'] : '';
                    $icon_id = isset($tech['iconId']) ? absint($tech['iconId']) : 0;
                    $icon_url = isset($tech['iconUrl']) ? esc_url($tech['iconUrl']) : '';
                ?>
                    <div class="tech-item">
                        <div class="tech-icon-wrapper">
                            <?php if ($icon_id) : ?>
                                <?php 
                                // Nếu có helper theme lấy ảnh
                                if (function_exists('getResponsiveImage')) {
                                    echo getResponsiveImage($icon_id, 'thumbnail', ['alt' => esc_attr($tech_name), 'class' => 'tech-icon']);
                                } else {
                                    echo wp_get_attachment_image($icon_id, 'thumbnail', false, ['alt' => esc_attr($tech_name), 'class' => 'tech-icon']);
                                }
                                ?>
                            <?php elseif ($icon_url) : ?>
                                <img src="<?php echo $icon_url; ?>" alt="<?php echo esc_attr($tech_name); ?>" class="tech-icon" loading="lazy" decoding="async">
                            <?php endif; ?>
                        </div>
                        <?php if ($tech_name) : ?>
                            <div class="tech-name"><?php echo esc_html($tech_name); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
