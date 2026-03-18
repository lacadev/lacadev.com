<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme header partial.
 *
 * @link    https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WPEmergeTheme
 */
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?> data-theme="light">

<head>
	<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
	<link rel="profile" href="http://gmpg.org/xfn/11" />
	<link rel="pingback" href="<?php bloginfo('pingback_url'); ?>" />
	<?php wp_head(); ?>

	<link rel="apple-touch-icon" sizes="57x57" href="<?php theAsset('favicon/apple-icon-57x57.png'); ?>">
	<link rel="apple-touch-icon" sizes="60x60" href="<?php theAsset('favicon/apple-icon-60x60.png'); ?>">
	<link rel="apple-touch-icon" sizes="72x72" href="<?php theAsset('favicon/apple-icon-72x72.png'); ?>">
	<link rel="apple-touch-icon" sizes="76x76" href="<?php theAsset('favicon/apple-icon-76x76.png'); ?>">
	<link rel="apple-touch-icon" sizes="114x114" href="<?php theAsset('favicon/apple-icon-114x114.png'); ?>">
	<link rel="apple-touch-icon" sizes="120x120" href="<?php theAsset('favicon/apple-icon-120x120.png'); ?>">
	<link rel="apple-touch-icon" sizes="144x144" href="<?php theAsset('favicon/apple-icon-144x144.png'); ?>">
	<link rel="apple-touch-icon" sizes="152x152" href="<?php theAsset('favicon/apple-icon-152x152.png'); ?>">
	<link rel="apple-touch-icon" sizes="180x180" href="<?php theAsset('favicon/apple-icon-180x180.png'); ?>">
	<link rel="icon" type="image/png" sizes="192x192" href="<?php theAsset('favicon/android-icon-192x192.png'); ?>">
	<link rel="icon" type="image/png" sizes="32x32" href="<?php theAsset('favicon/favicon-32x32.png'); ?>">
	<link rel="icon" type="image/png" sizes="96x96" href="<?php theAsset('favicon/favicon-96x96.png'); ?>">
	<link rel="icon" type="image/png" sizes="16x16" href="<?php theAsset('favicon/favicon-16x16.png'); ?>">
	<link rel="manifest" href="<?php theAsset('favicon/manifest.json'); ?>">
	<meta name="msapplication-TileColor" content="#ffffff">
	<meta name="msapplication-TileImage" content="<?php theAsset('favicon/ms-icon-144x144.png'); ?>">
    <meta name="theme-color" content="#ffffff">
    <?php
    $critical_css_path = get_template_directory() . '/dist/styles/critical.css';
    if (file_exists($critical_css_path)) {
        echo '<style id="critical-css">' . file_get_contents($critical_css_path) . '</style>';
    }
    ?>
    <style>
        :root {
            /* Theme colors */
            --primary-color: <?php echo carbon_get_theme_option('primary_color'); ?>;
            --secondary-color: <?php echo carbon_get_theme_option('secondary_color'); ?>;
            --bg-color: <?php echo carbon_get_theme_option('bg_color'); ?>;

            --primary-color-dark: <?php echo carbon_get_theme_option('primary_color_dark'); ?>;
            --secondary-color-dark: <?php echo carbon_get_theme_option('secondary_color_dark'); ?>;
            --bg-color-dark: <?php echo carbon_get_theme_option('bg_color_dark'); ?>;
        }

        html[data-theme="dark"] {
            --primary-color: <?php echo carbon_get_theme_option('primary_color_dark'); ?>;
            --secondary-color: <?php echo carbon_get_theme_option('secondary_color_dark'); ?>;
            --bg-color: <?php echo carbon_get_theme_option('bg_color_dark'); ?>;

            --primary-color-dark: <?php echo carbon_get_theme_option('primary_color'); ?>;
            --secondary-color-dark: <?php echo carbon_get_theme_option('secondary_color'); ?>;
            --bg-color-dark: <?php echo carbon_get_theme_option('bg_color'); ?>;
        }
    </style>
</head>

<body <?php body_class(); ?>>
    <?php
    app_shim_wp_body_open();
    ?>

    <div class="page-loader">
        <div class="text-loader">
            <div class="right">
                <h4 class="randoms">MINIMAL</h4>
                <h2 class="randoms">LA CÀ DEV</h2>
                <h4 class="randoms">WORDPRESS</h4>
                <h4 class="randoms">BLOG</h4>
                <h4 class="randoms">TRAVELLING</h4>
            </div>
            <div class="left">
                <h4 class="randoms">WORDPRESS</h4>
                <h2 class="randoms">LA CÀ DEV</h2>
                <h4 class="randoms">CLEAN</h4>
                <h4 class="randoms">BLOG</h4>
                <h4 class="randoms">TRAVELLING</h4>
            </div>
        </div>
    </div>

    <!-- Custom Mouse Cursor -->
    <div class="mouse-cursor cursor-outer"></div>
    <div class="mouse-cursor cursor-inner">
        <svg class="cursor-arrow" width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M7 17L17 7M17 7H7M17 7V17" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>

	<!-- Skip to content link for accessibility -->
	<a class="skip-link screen-reader-text" href="#main-content">
		<?php esc_html_e('Skip to content', 'laca'); ?>
	</a>

	<?php
	if (is_home() || is_front_page()):
		echo '<h1 class="site-name screen-reader-text">' . esc_html(get_bloginfo('name')) . '</h1>';
	endif;
	?>

	<div class="wrapper" id="swup">
        <?php if (!is_404()) : ?>
		<header class="header" id="header">
			<div class="container">
                <div class="header__inner">
                    <!-- Main menu -->
                    <div class="header__menu-desktop">
                        <?php
                        echo '<nav class="header__nav" aria-label="' . esc_attr__('Main menu', 'laca') . '">';
                            wp_nav_menu([
                                'theme_location' => 'main-menu',
                                'menu_class'     => 'header__menu-list',
                                'container'      => false,
                                'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
                                'walker'         => new Laca_Menu_Walker(),
                            ]);
                        echo '</nav>';
                        ?>
                    </div>

                    <div class="header__menu-mobile">
                        <div class="header__hamburger" id="btn-hamburger">
                            <div class="header__hamburger-icon">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                    </div>

                    <!-- Logo -->
                    <?php
                    $logo_id = carbon_get_theme_option('logo');
                    $logo_dark_id = carbon_get_theme_option('logo_dark');
                    $logo_url = wp_get_attachment_image_url($logo_id, 'full');
                    $logo_dark_url = wp_get_attachment_image_url($logo_dark_id, 'full');
                    ?>
                    <div class="header__logo">
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="header__logo-link">
                            <?php if ($logo_url) : ?>
                                <img src="<?php echo esc_url($logo_url); ?>" class="header__logo-img header__logo-img--light" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                            <?php endif; ?>
                            <?php if ($logo_dark_url) : ?>
                                <img src="<?php echo esc_url($logo_dark_url); ?>" class="header__logo-img header__logo-img--dark" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                            <?php endif; ?>
                        </a>
                    </div>

                    <div class="header__actions">
                        <!-- search -->
                        <!-- <div class="header__search">
                            <div class="header__search-inner">
                                <form class="header__search-box" method="get" role="search" aria-label="<?php esc_attr_e('Tìm kiếm', 'laca'); ?>" action="<?php echo esc_url(home_url('/')) ?>">
                                    <label for="search-input" class="screen-reader-text"><?php esc_html_e('Từ khóa tìm kiếm', 'laca'); ?></label>
                                    <input type="text" 
                                            id="search-input"
                                            name="s"
                                            class="header__search-input"
                                            placeholder="<?php echo esc_attr__('Tìm kiếm ...', 'laca'); ?>" 
                                            aria-label="<?php esc_attr_e('Nhập từ khóa tìm kiếm', 'laca'); ?>"/>
                                    <button type="reset" class="header__search-reset" aria-label="<?php esc_attr_e('Xóa tìm kiếm', 'laca'); ?>"></button>
                                    <div class="header__search-results" 
                                            role="status" 
                                            aria-live="polite" 
                                            aria-atomic="true"></div>
                                </form>
                            </div>
                        </div> -->

                        <!-- multi language -->
                        <?php theLanguageSwitcher(); ?>

                        <!-- dark mode -->
                        <div id="darkmode" class="header__darkmode btn">
                            <div class="header__darkmode-bg header__darkmode-bg--1"></div>
                            <div class="header__darkmode-bg header__darkmode-bg--2"></div>
                            <label class="header__darkmode-toggle">
                                <input class="header__darkmode-input" type="checkbox" 
                                    aria-label="<?php esc_attr_e('Chuyển chế độ tối/sáng', 'laca'); ?>" 
                                    role="switch" 
                                    aria-checked="false"/>
                                <div class="header__darkmode-slider"></div>
                            </label>
                        </div>
                    </div>
                    <!-- end head-menu -->
                </div>
			</div>

            <!-- Mobile Overlay Menu -->
            <div class="header__overlay">
                <div class="header__overlay-bg"></div>
                <div class="header__overlay-inner">
                    <?php theLanguageSwitcher(); ?>
                    <div class="header__overlay-label"><?php _e('NAVIGATION', 'laca'); ?></div>
                    <?php
                    echo '<nav class="header__overlay-nav">';
                        wp_nav_menu([
                            'theme_location' => 'main-menu',
                            'menu_class'     => 'header__overlay-menu-list',
                            'container'      => false,
                            'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
                            'walker'         => new Laca_Menu_Walker(),
                        ]);
                    echo '</nav>';
                    ?>
                    
                    <div class="header__overlay-footer">
                        <div class="header__overlay-socials">
                            <a href="#" target="_blank" class="header__overlay-social-link">Facebook</a>
                            <a href="#" target="_blank" class="header__overlay-social-link">Instagram</a>
                        </div>
                    </div>
                </div>
            </div>
		</header>
        <?php endif; ?>
