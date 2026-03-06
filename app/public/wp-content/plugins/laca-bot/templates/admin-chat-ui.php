<?php if (!defined('ABSPATH')) exit; ?>

<div id="laca-bot-admin-widget" class="laca-bot-widget-container admin-theme">
    <div id="laca-bot-chat-window" class="laca-bot-chat-window" style="display: none;">
        <div class="laca-bot-header">
            <div class="laca-bot-header-info">
                <img src="<?php echo esc_url(get_option('laca_bot_avatar') ?: LACA_BOT_URL . 'assets/img/default-bot.png'); ?>" alt="Bot Avatar" class="laca-bot-avatar">
                <div>
                    <h4 class="laca-bot-title">Admin Assistant</h4>
                    <span class="laca-bot-status">● System Online</span>
                </div>
            </div>
            <div class="laca-bot-header-btns">
                <button id="laca-bot-new-chat-btn" class="laca-bot-header-btn" title="Cuộc trò chuyện mới">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                </button>
                <button id="laca-bot-close-btn" class="laca-bot-close-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
        </div>
        
        <div id="laca-bot-message-list" class="laca-bot-message-list">
            <!-- Messages will be appended here -->
            <div class="laca-bot-message bot">
                <div class="laca-bot-message-bubble">
                    Chào Admin! Tôi có thể giúp bạn tra cứu lỗi SEO, tìm bài viết, hoặc hướng dẫn quản trị. Bạn cần trợ giúp gì?
                </div>
            </div>
        </div>

        <div class="laca-bot-input-area">
            <input type="text" id="laca-bot-input" placeholder="Hỏi AI quản trị viên..." autocomplete="off">
            <button id="laca-bot-send-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            </button>
        </div>
    </div>

    <button id="laca-bot-toggle-btn" class="laca-bot-toggle-btn">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="9" y1="9" x2="15" y2="15"></line><line x1="15" y1="9" x2="9" y2="15"></line></svg>
    </button>
</div>
