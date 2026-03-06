<?php
/**
 * Plugin Name: Laca Bot - AI Assistant
 * Plugin URI: https://lacadev.com
 * Description: Trợ lý AI toàn diện cho WordPress (Admin & Frontend).
 * Version: 1.0.0
 * Author: La Cà Dev
 * Author URI: https://lacadev.com
 * Text Domain: laca-bot
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define('LACA_BOT_VERSION', '1.0.0');
define('LACA_BOT_DIR', plugin_dir_path(__FILE__));
define('LACA_BOT_URL', plugin_dir_url(__FILE__));

// Require Core Files
require_once LACA_BOT_DIR . 'includes/class-laca-bot-settings.php';
require_once LACA_BOT_DIR . 'includes/class-laca-bot-core.php';
require_once LACA_BOT_DIR . 'includes/class-laca-bot-tools.php';
require_once LACA_BOT_DIR . 'includes/class-laca-bot-usage.php';
require_once LACA_BOT_DIR . 'includes/class-laca-bot-db.php';
require_once LACA_BOT_DIR . 'includes/class-laca-bot-ai-engine.php';

// Initialize Plugin
function laca_bot_init() {
    new Laca_Bot_Settings();
    new Laca_Bot_Core();
    Laca_Bot_DB::init();
    new Laca_Bot_AI_Engine();
}
add_action('plugins_loaded', 'laca_bot_init');
