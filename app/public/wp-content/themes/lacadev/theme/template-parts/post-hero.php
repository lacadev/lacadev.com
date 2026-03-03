<!-- Hero Section -->
<div class="post-hero">
    <div class="container">
        <h1 class="post-title"><?php the_title(); ?></h1>
        <div class="post-meta">
            <span class="meta-item">
                <?php 
                if (get_post_type() === 'service') {
                    _e('Dịch vụ chuyên nghiệp', 'laca');
                } else {
                    the_category(', ');
                }
                ?>
            </span>
            <span class="meta-separator">•</span>
            <span class="meta-item"><?php echo get_the_date('d/m/Y'); ?></span>
        </div>
    </div>
</div>
