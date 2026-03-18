<?php
/**
 * Process Block Render Template.
 */
$title = !empty($attributes['title']) ? $attributes['title'] : '';
$description = !empty($attributes['description']) ? $attributes['description'] : '';
$steps = !empty($attributes['steps']) ? $attributes['steps'] : [];
?>

<section class="block-process">
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

        <div class="process-grid">
            <?php foreach ($steps as $step) : ?>
                <div class="process-item staggered-item">
                    <div class="process-num"><?php echo esc_html($step['num']); ?></div>
                    <div class="process-info">
                        <h3 class="process-step-title"><?php echo esc_html($step['title']); ?></h3>
                        <p class="process-step-desc"><?php echo esc_html($step['desc']); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
