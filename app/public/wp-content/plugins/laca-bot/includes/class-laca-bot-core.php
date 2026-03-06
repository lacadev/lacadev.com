<?php

if (!defined('ABSPATH')) {
    exit;
}

class Laca_Bot_Core {

    public function __construct() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_footer', [$this, 'render_admin_chat_ui']);
        add_action('wp_footer', [$this, 'render_frontend_chat_ui']);
        add_action('wp_head', [$this, 'inject_dynamic_css']);
        add_action('admin_head', [$this, 'inject_dynamic_css']);
    }

    public function inject_dynamic_css() {
        $primary_color = get_option('laca_bot_primary_color', '#2271b1');
        $accent_color = get_option('laca_bot_accent_color', '#1d2327');
        $pos = get_option('laca_bot_position', 'right');
        $btn_style = get_option('laca_bot_button_style', 'rounded');

        $border_radius = '8px';
        if ($btn_style === 'circle') $border_radius = '50%';
        if ($btn_style === 'square') $border_radius = '0px';

        ?>
        <style id="laca-bot-dynamic-styles">
            :root {
                --laca-bot-primary: <?php echo esc_attr($primary_color); ?>;
                --laca-bot-accent: <?php echo esc_attr($accent_color); ?>;
            }
            .laca-bot-widget-container {
                <?php echo $pos === 'left' ? 'left: 20px; right: auto;' : 'right: 20px; left: auto;'; ?>
            }
            .laca-bot-widget-container .laca-bot-toggle-btn {
                border-radius: <?php echo $border_radius; ?> !important;
                background-color: var(--laca-bot-primary) !important;
            }
            .laca-bot-widget-container .laca-bot-header {
                background: var(--laca-bot-accent) !important;
            }
            .laca-bot-widget-container .laca-bot-message.user .laca-bot-message-bubble {
                background: var(--laca-bot-primary) !important;
            }
            .laca-bot-widget-container .laca-bot-chat-window {
                <?php echo $pos === 'left' ? 'left: 0; right: auto;' : 'right: 0; left: auto;'; ?>
            }
            .laca-bot-widget-container #laca-bot-send-btn {
                background: var(--laca-bot-primary) !important;
            }
        </style>
        <?php
    }

    public function enqueue_admin_assets() {
        if (!get_option('laca_bot_enable_backend', 1)) return;

        wp_enqueue_style('laca-bot-admin-css', LACA_BOT_URL . 'assets/css/laca-bot-admin.css', [], LACA_BOT_VERSION);
        wp_enqueue_script('marked-js', 'https://cdn.jsdelivr.net/npm/marked/marked.min.js', [], '4.0.0', true);
        wp_enqueue_script('laca-bot-admin-js', LACA_BOT_URL . 'assets/js/laca-bot-admin.js', ['jquery', 'marked-js'], LACA_BOT_VERSION, true);

        wp_localize_script('laca-bot-admin-js', 'lacaBotObj', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('laca_bot_nonce'),
            'bot_name' => get_option('laca_bot_name') ?: 'Laca Bot',
            'bot_avatar' => get_option('laca_bot_avatar') ?: LACA_BOT_URL . 'assets/img/default-bot.png',
            'quick_chips' => array_map('trim', explode(',', get_option('laca_bot_quick_chips', 'Kiểm tra SEO, Tìm bài viết mới, Hướng dẫn chỉnh sửa'))),
        ]);
    }

    public function enqueue_frontend_assets() {
        if (!get_option('laca_bot_enable_frontend', 1)) return;

        wp_enqueue_style('laca-bot-frontend-css', LACA_BOT_URL . 'assets/css/laca-bot-frontend.css', [], LACA_BOT_VERSION);
        wp_enqueue_script('marked-js', 'https://cdn.jsdelivr.net/npm/marked/marked.min.js', [], '4.0.0', true);
        wp_enqueue_script('laca-bot-frontend-js', LACA_BOT_URL . 'assets/js/laca-bot-frontend.js', ['jquery', 'marked-js'], LACA_BOT_VERSION, true);

        // Parse Label|Prompt format for frontend chips
        $raw_chips = explode(',', get_option('laca_bot_quick_chips', 'Dịch vụ SEO, Báo giá, Liên hệ tư vấn'));
        $parsed_chips = array_map(function($chip) {
            $parts = explode('|', trim($chip), 2);
            return [
                'label' => trim($parts[0]),
                'prompt' => isset($parts[1]) ? trim($parts[1]) : trim($parts[0])
            ];
        }, $raw_chips);

        wp_localize_script('laca-bot-frontend-js', 'lacaBotObj', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('laca_bot_nonce'),
            'bot_name' => get_option('laca_bot_name') ?: 'Laca Bot',
            'bot_avatar' => get_option('laca_bot_avatar') ?: LACA_BOT_URL . 'assets/img/default-bot.png',
            'greeting' => get_option('laca_bot_greeting') ?: 'Xin chào! Tôi là trợ lý AI thông minh của LacaDev. Tôi có thể giúp gì cho bạn hôm nay?',
            'quick_chips' => array_filter($parsed_chips, function($c) { return !empty($c['label']); }),
        ]);
    }

    public function render_admin_chat_ui() {
        if (!get_option('laca_bot_enable_backend', 1)) return;
        include LACA_BOT_DIR . 'templates/admin-chat-ui.php';
    }

    public function render_frontend_chat_ui() {
        if (!get_option('laca_bot_enable_frontend', 1)) return;
        include LACA_BOT_DIR . 'templates/frontend-chat-ui.php';
    }
}
