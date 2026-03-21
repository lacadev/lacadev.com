<?php
/**
 * Workflow Block — Render Template.
 *
 * @package LacaDev
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sub_title       = esc_html( $attributes['subTitle'] ?? '' );
$title           = esc_html( $attributes['title'] ?? '' );
$steps           = $attributes['steps'] ?? [];
$background_color = $attributes['backgroundColor'] ?? '';

$section_style = $background_color ? 'background-color: ' . esc_attr( $background_color ) . ';' : '';
?>

<section <?php echo get_block_wrapper_attributes( [
	'class' => 'py-32 px-8 overflow-hidden',
	'style' => $section_style,
] ); ?>>

	<div class="max-w-screen-2xl mx-auto">

		<?php if ( $sub_title || $title ) : ?>
			<div class="text-center mb-24">
				<?php if ( $sub_title ) : ?>
					<span class="text-secondary font-label tracking-widest uppercase text-xs">
						<?php echo $sub_title; ?>
					</span>
				<?php endif; ?>
				<?php if ( $title ) : ?>
					<h2 class="font-headline text-5xl font-bold mt-4">
						<?php echo $title; ?>
					</h2>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $steps ) ) : ?>
			<div class="relative flex flex-col md:flex-row gap-12 md:gap-0">

				<!-- Progress Line -->
				<div class="hidden md:block absolute top-10 left-0 w-full h-px bg-surface-container-highest z-0"></div>

				<?php foreach ( $steps as $index => $step ) :
					$num        = str_pad( $index + 1, 2, '0', STR_PAD_LEFT );
					$step_title = esc_html( $step['title'] ?? '' );
					$step_desc  = esc_html( $step['desc'] ?? '' );
				?>

					<div class="relative z-10 flex-1 px-4">
						<div class="w-20 h-20 bg-surface-container-lowest flex items-center justify-center rounded-full mb-8 shadow-sm border border-surface-container mx-auto md:mx-0">
							<span class="text-4xl font-headline font-extrabold text-primary-dim opacity-20">
								<?php echo $num; ?>
							</span>
						</div>
						<h4 class="font-headline text-xl font-bold mb-3 text-center md:text-left">
							<?php echo $step_title; ?>
						</h4>
						<p class="text-on-surface-variant text-sm text-center md:text-left">
							<?php echo $step_desc; ?>
						</p>
					</div>

				<?php endforeach; ?>

			</div>
		<?php endif; ?>

	</div>
</section>
