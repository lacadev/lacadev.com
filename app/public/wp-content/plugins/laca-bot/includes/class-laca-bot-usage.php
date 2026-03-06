<?php

if (!defined('ABSPATH')) {
    exit;
}

class Laca_Bot_Usage {

    /**
     * Log token usage for a provider
     */
    public static function log($provider, $tokens, $context = 'admin') {
        $today = date('Y-m-d');
        $usage = get_option('laca_bot_daily_usage', []);

        if (!isset($usage[$today])) {
            // Keep only last 7 days of logs to avoid bloating options
            $usage = array_slice($usage, -6, 6, true);
            $usage[$today] = [];
        }

        if (!isset($usage[$today][$provider])) {
            $usage[$today][$provider] = ['admin' => 0, 'user' => 0];
        }

        $usage[$today][$provider][$context] += $tokens;

        update_option('laca_bot_daily_usage', $usage);
    }

    /**
     * Get usage for today
     */
    public static function get_today_usage() {
        $today = date('Y-m-d');
        $usage = get_option('laca_bot_daily_usage', []);
        return $usage[$today] ?? [];
    }

    /**
     * Formats usage data for display
     */
    public static function get_formatted_usage() {
        $today = self::get_today_usage();
        $output = [];

        $providers = [
            'gemini' => 'Google Gemini',
            'groq' => 'Groq AI',
            'deepseek' => 'DeepSeek',
            'openai' => 'OpenAI (ChatGPT)',
            'anthropic' => 'Anthropic (Claude)'
        ];

        foreach ($providers as $slug => $name) {
            $data = $today[$slug] ?? ['admin' => 0, 'user' => 0];
            $output[] = [
                'slug' => $slug,
                'name' => $name,
                'admin' => $data['admin'],
                'user' => $data['user'],
                'total' => $data['admin'] + $data['user']
            ];
        }

        return $output;
    }
}
