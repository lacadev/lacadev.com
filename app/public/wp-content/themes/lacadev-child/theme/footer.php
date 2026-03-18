<?php

/**
 * Theme footer partial.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WPEmergeTheme
 */
?>
<!-- footer -->
<footer class="site-footer">
    <div class="container">
        <div class="footer-cta">
            <p class="cta-label"><?php _e('HÃY CÙNG NHAU LA CÀ VÀ', 'laca'); ?></p>
            <?php
            // Lấy động URL của trang đang sử dụng template-contact.php
            // Thử cả 2 trường hợp có và không có prefix 'theme/' tùy theo cách WP lưu meta
            $template_path = 'theme/page_templates/template-contact.php';
            $contact_pages = get_pages([
                'meta_key' => '_wp_page_template',
                'meta_value' => $template_path
            ]);

            // Nếu không tìm thấy, thử tìm bản không có prefix 'theme/'
            if (empty($contact_pages)) {
                $contact_pages = get_pages([
                    'meta_key' => '_wp_page_template',
                    'meta_value' => 'page_templates/template-contact.php'
                ]);
            }

            $contact_url = !empty($contact_pages) ? get_permalink($contact_pages[0]->ID) : home_url('/ghe-tram/');
            ?>
            <a href="<?php echo esc_url($contact_url); ?>" class="cta-main-link"><?php _e('viết nên hành trình mới', 'laca'); ?></a>
        </div>

        <div class="footer-grid">
            <div class="footer-col">
                <h4 class="footer-title"><?php _e('Ping tôi', 'laca'); ?></h4>
                <?php 
                $phone = getOption('phone_number');
                $email = getOption('email');
                ?>
                <div class="footer-content">
                    <?php if ($phone) : ?>
                        <p><a href="tel:<?php echo esc_attr(str_replace(['.', ' '], '', $phone)); ?>"><?php echo esc_html($phone); ?></a></p>
                    <?php endif; ?>
                    <?php if ($email) : ?>
                        <p><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="footer-col">
                <h4 class="footer-title"><?php _e('Điểm dừng', 'laca'); ?></h4>
                <nav class="footer-nav">
                    <?php
                    wp_nav_menu([
                        'menu' => 'footer-menu',
                        'theme_location' => 'footer-menu',
                        'container' => false,
                        'fallback_cb' => false,
                        'menu_class' => 'footer-links',
                    ]);
                    ?>
                </nav>
            </div>

            <div class="footer-col">
                <h4 class="footer-title"><?php _e('Gặp tôi tại', 'laca'); ?></h4>
                <?php 
                $socials = [
                    'facebook'  => 'Facebook',
                    'linkedin'  => 'LinkedIn',
                    'instagram' => 'Instagram',
                    'tiktok'    => 'TikTok',
                    'youtube'   => 'YouTube',
                ];
                ?>
                <div class="footer-socials">
                    <?php foreach ($socials as $key => $label) : 
                        $url = getOption($key);
                        if ($url) : ?>
                            <a href="<?php echo esc_url($url); ?>" target="_blank" rel="nofollow" class="social-link"><?php echo esc_html($label); ?></a>
                        <?php endif;
                    endforeach; ?>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-copyright">
                &copy; <?php echo date('Y'); ?> La Cà Dev. All rights reserved.
            </div>
            <div class="footer-legal">
                <a href="#"><?php _e('Chính sách bảo mật', 'laca'); ?></a>
                <a href="#"><?php _e('Điều khoản sử dụng', 'laca'); ?></a>
            </div>
        </div>
    </div>
</footer>
<!-- footer end -->

</div>
<!-- container-wrapper end -->


<?php wp_footer(); ?>
</body>

</html>