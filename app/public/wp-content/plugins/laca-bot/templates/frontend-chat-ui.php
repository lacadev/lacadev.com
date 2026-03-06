<?php if (!defined('ABSPATH')) exit; ?>

<div id="laca-bot-frontend-widget" class="laca-bot-widget-container">
    <div id="laca-bot-chat-window" class="laca-bot-chat-window" style="display: none;">
        <div class="laca-bot-header">
            <div class="laca-bot-header-info">
                <img src="<?php echo esc_url(get_option('laca_bot_avatar') ?: LACA_BOT_URL . 'assets/img/default-bot.png'); ?>" alt="Bot Avatar" class="laca-bot-avatar">
                <div>
                    <h4 class="laca-bot-title"><?php echo esc_html(get_option('laca_bot_name') ?: 'Laca Bot'); ?></h4>
                    <span class="laca-bot-status">● Trực tuyến</span>
                </div>
            </div>
            <button id="laca-bot-close-btn" class="laca-bot-close-btn">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        
        <div id="laca-bot-message-list" class="laca-bot-message-list">
            <!-- Messages will be appended here -->
            <div class="laca-bot-message bot">
                <div class="laca-bot-message-bubble">
                    <?php echo esc_html(get_option('laca_bot_greeting') ?: 'Xin chào! Tôi là trợ lý AI thông minh. Tôi có thể giúp gì cho bạn hôm nay?'); ?>
                </div>
            </div>
        </div>

        <div class="laca-bot-input-area">
            <input type="text" id="laca-bot-input" placeholder="Nhập câu hỏi của bạn..." autocomplete="off">
            <button id="laca-bot-send-btn">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            </button>
        </div>
    </div>

    <button id="laca-bot-toggle-btn" class="laca-bot-toggle-btn">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
    </button>
</div>
