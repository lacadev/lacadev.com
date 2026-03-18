<?php
/**
 * Marquee Block Render Template.
 */
$brands = !empty($attributes['brands']) ? $attributes['brands'] : [];
if (empty($brands)) return;
?>

<div class="block-marquee">
    <div class="marquee-inner">
        <?php for($i=0; $i<10; $i++): ?>
            <?php foreach($brands as $brand): ?>
                <?php if (!empty($brand['url'])): ?>
                    <a href="<?php echo esc_url($brand['url']); ?>" class="marquee-item" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html($brand['name']); ?>
                        <span class="marquee-sep">•</span>
                    </a>
                <?php else: ?>
                    <span class="marquee-item">
                        <?php echo esc_html($brand['name']); ?>
                        <span class="marquee-sep">•</span>
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endfor; ?>
    </div>
</div>
