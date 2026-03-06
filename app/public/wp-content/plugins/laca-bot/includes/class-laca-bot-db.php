<?php

if (!defined('ABSPATH')) {
    exit;
}

class Laca_Bot_DB {

    public static function init() {
        self::create_tables();
        
        if (!wp_next_scheduled('laca_bot_cleanup_chats_event')) {
            wp_schedule_event(time(), 'hourly', 'laca_bot_cleanup_chats_event');
        }
        
        add_action('laca_bot_cleanup_chats_event', [__CLASS__, 'cleanup_old_chats']);
    }

    public static function create_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'laca_bot_chats';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT 0,
            session_id varchar(50) NOT NULL,
            context varchar(10) NOT NULL,
            role varchar(10) NOT NULL,
            content longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function add_message($session_id, $role, $content, $context = 'admin', $user_id = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'laca_bot_chats';
        
        $wpdb->insert($table_name, [
            'session_id' => $session_id,
            'user_id' => $user_id,
            'role' => $role,
            'content' => $content,
            'context' => $context,
            'created_at' => current_time('mysql')
        ]);
    }

    public static function get_history($session_id, $limit = 10) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'laca_bot_chats';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT role, content FROM $table_name WHERE session_id = %s ORDER BY created_at ASC LIMIT %d",
            $session_id,
            $limit
        ), ARRAY_A);
    }

    public static function cleanup_old_chats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'laca_bot_chats';

        // Delete Admin chats older than 3 days
        $wpdb->query("DELETE FROM $table_name WHERE context = 'admin' AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)");

        // Delete Frontend chats older than 12 hours
        $wpdb->query("DELETE FROM $table_name WHERE context = 'user' AND created_at < DATE_SUB(NOW(), INTERVAL 12 HOUR)");
    }
}
