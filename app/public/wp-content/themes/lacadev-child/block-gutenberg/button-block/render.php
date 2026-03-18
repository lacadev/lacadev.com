<?php
/**
 * Render the Button block
 */
$text         = !empty($attributes['text']) ? $attributes['text'] : '';
$url          = !empty($attributes['url']) ? $attributes['url'] : '#';
$style_type   = !empty($attributes['style']) ? $attributes['style'] : 'primary';
$alignment    = !empty($attributes['alignment']) ? $attributes['alignment'] : 'center';
$marginTop    = isset($attributes['marginTop']) ? $attributes['marginTop'] : 30;
$marginBottom = isset($attributes['marginBottom']) ? $attributes['marginBottom'] : 30;
$target       = !empty($attributes['target']) ? $attributes['target'] : '_self';
$fullWidth    = !empty($attributes['fullWidth']) ? $attributes['fullWidth'] : false;

$wrapper_classes = [
    'block-button',
    'align-' . $alignment,
];

if (!empty($attributes['className'])) {
    $wrapper_classes[] = $attributes['className'];
}

$wrapper_style = sprintf(
    'margin-top: %spx; margin-bottom: %spx;',
    esc_attr($marginTop),
    esc_attr($marginBottom)
);

$btn_classes = [
    'btn',
    'btn-' . $style_type,
];

if ($fullWidth) {
    $btn_classes[] = 'is-full-width';
}

if (!$text) return; // Don't render if there's no text
?>

<div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>" style="<?php echo esc_attr($wrapper_style); ?>">
    <a href="<?php echo esc_url($url); ?>" 
       class="<?php echo esc_attr(implode(' ', $btn_classes)); ?>"
       target="<?php echo esc_attr($target); ?>"
       <?php echo ($target === '_blank') ? 'rel="noopener noreferrer"' : ''; ?>
    >
        <?php if ($style_type === 'minimal') : ?>
            <span class="btn-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="20" x2="20" y2="4"></line><polyline points="10 4 20 4 20 14"></polyline></svg>
            </span>
        <?php endif; ?>
        <span class="btn-text"><?php echo esc_html($text); ?></span>
    </a>
</div>
