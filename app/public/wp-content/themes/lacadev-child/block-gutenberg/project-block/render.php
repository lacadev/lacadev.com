<?php
/**
 * Project Block Render Template.
 */

$title          = ! empty( $attributes['title'] ) ? $attributes['title'] : '';
$description    = ! empty( $attributes['description'] ) ? $attributes['description'] : '';
$post_type      = ! empty( $attributes['postType'] ) ? $attributes['postType'] : 'template';
$taxonomy       = array_key_exists( 'taxonomy', $attributes ) ? $attributes['taxonomy'] : 'template_cat';
$term_ids       = ! empty( $attributes['termIds'] ) ? $attributes['termIds'] : [];
$category_ids   = ! empty( $attributes['categoryIds'] ) ? $attributes['categoryIds'] : [];
$effective_term_ids = ! empty( $term_ids ) ? $term_ids : $category_ids;
$order_by       = ! empty( $attributes['orderBy'] ) ? $attributes['orderBy'] : 'date';
$count_desktop  = ! empty( $attributes['countDesktop'] ) ? (int) $attributes['countDesktop'] : 6;
$count_mobile   = ! empty( $attributes['countMobile'] ) ? (int) $attributes['countMobile'] : 4;

$max_count = max( $count_desktop, $count_mobile );

// Prepare initial query for "All" tab
$args = [
	'post_type'           => $post_type,
	'post_status'         => 'publish',
	'ignore_sticky_posts' => 1,
	'posts_per_page'      => $max_count,
];

// Apply ordering
if ( $order_by === 'rand' ) {
	$args['orderby'] = 'rand';
} elseif ( $order_by === 'hand_made' ) {
    $args['meta_key'] = '_is_real';
    $args['meta_value'] = 'yes';
    $args['orderby'] = 'date';
    $args['order'] = 'DESC';
} else {
	$args['orderby'] = 'date';
	$args['order']   = 'DESC';
}

// If categories are selected, limit "All" tab to those categories
if ( ! empty( $taxonomy ) && ! empty( $effective_term_ids ) ) {
	$args['tax_query'] = [
		[
			'taxonomy' => $taxonomy,
			'field'    => 'term_id',
			'terms'    => $effective_term_ids,
		],
	];
}

$query = new WP_Query( $args );

$class_name = 'laca-project-block';
if ( ! empty( $attributes['className'] ) ) {
	$class_name .= ' ' . $attributes['className'];
}

// Get selected category objects for tabs
$selected_cats = [];
if ( ! empty( $taxonomy ) && ! empty( $effective_term_ids ) ) {
	foreach ( $effective_term_ids as $cat_id ) {
		$term = get_term( $cat_id, $taxonomy );
		if ( ! is_wp_error( $term ) && $term ) {
			$selected_cats[] = $term;
		}
	}
}
?>

<section class="<?php echo esc_attr( $class_name ); ?>" 
    data-order-by="<?php echo esc_attr( $order_by ); ?>"
    data-count-desktop="<?php echo esc_attr( $count_desktop ); ?>"
    data-count-mobile="<?php echo esc_attr( $count_mobile ); ?>"
    data-category-ids="<?php echo esc_attr( implode(',', $effective_term_ids) ); ?>"
>
    <div class="container laca-project-block__container">
        
        <?php if ( $title || $description ) : ?>
            <div class="laca-project-block__header">
                <?php if ( $title ) : ?>
                    <h2 class="laca-project-block__title"><?php echo esc_html( $title ); ?></h2>
                <?php endif; ?>
                <?php if ( $description ) : ?>
                    <div class="laca-project-block__desc">
                        <?php echo wp_kses_post( $description ); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $selected_cats ) ) : ?>
            <div class="laca-project-block__tabs">
                <button class="tab-item is-active" data-category="0"><?php esc_html_e( 'All', 'laca' ); ?></button>
                <?php foreach ( $selected_cats as $cat ) : ?>
                    <button class="tab-item" data-category="<?php echo esc_attr( $cat->term_id ); ?>">
                        <?php echo esc_html( $cat->name ); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="laca-project-block__grid-wrapper">
            <div class="laca-skeleton-loader"></div>
            <div class="laca-project-block__grid">
                <?php
                if ( $query->have_posts() ) :
                    $index = 0;
                    while ( $query->have_posts() ) :
                        $query->the_post();
                        $index++;
                        
                        $quick_view_img_url = getPostMetaImageUrl( 'quick_view_img', get_the_ID(), null, null );
                        
                        // Fallback if the helper above doesn't work as expected for this specific field type
                        if ( ! $quick_view_img_url ) {
                            $quick_view_img_id = carbon_get_post_meta( get_the_ID(), 'quick_view_img' );
                            if ( is_numeric( $quick_view_img_id ) ) {
                                $quick_view_img_url = wp_get_attachment_image_url( $quick_view_img_id, 'full' );
                            } else {
                                $quick_view_img_url = $quick_view_img_id;
                            }
                        }
                        
                        $item_class = 'laca-project-block__item';
                        if ( $index > $count_mobile ) {
                            $item_class .= ' hidden-on-mobile';
                        }
                        if ( $index > $count_desktop ) {
                            $item_class .= ' hidden-on-desktop';
                        }
                        ?>
                        <div class="<?php echo esc_attr( $item_class ); ?>">
                            <a href="<?php the_permalink(); ?>" class="laca-project-block__card-link" data-cursor-arrow>
                                <div class="laca-project-block__image-wrap">
                                    <?php theResponsivePostThumbnail( 'large', [ 'alt' => esc_attr( get_the_title() ), 'class' => 'laca-project-block__img' ] ); ?>
                                    
                                    <?php if ( $quick_view_img_url ) : ?>
                                        <div class="laca-project-block__hover-img-wrap">
                                            <img src="<?php echo esc_url( $quick_view_img_url ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" class="laca-project-block__hover-img" loading="lazy">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                        <?php
                    endwhile;
                    wp_reset_postdata();
                else :
                    ?>
                    <p><?php esc_html_e( 'No projects found.', 'laca' ); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php
        $button_text = ! empty( $attributes['buttonText'] ) ? $attributes['buttonText'] : '';
        $button_url  = ! empty( $attributes['buttonUrl'] ) ? $attributes['buttonUrl'] : '';
        ?>
        <?php if ( $button_text && $button_url ) : ?>
            <div class="block-footer laca-project-block__footer">
                <a href="<?php echo esc_url( $button_url ); ?>" class="btn btn-minimal">
                    <span class="btn-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="20" x2="20" y2="4"></line><polyline points="10 4 20 4 20 14"></polyline></svg>
                    </span>
                    <span class="btn-text"><?php echo esc_html( $button_text ); ?></span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>
