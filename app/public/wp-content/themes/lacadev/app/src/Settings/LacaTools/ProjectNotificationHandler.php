<?php

namespace App\Settings\LacaTools;

use App\Models\ProjectAlert;

/**
 * Xử lý Cron Job và gửi thông báo qua Email / Zalo
 * cho Project Manager
 */
class ProjectNotificationHandler
{
    private const CRON_HOOK = 'laca_project_manager_daily_cron';

    public function init(): void
    {
        add_action('init', [$this, 'scheduleCronJob']);
        add_action(self::CRON_HOOK, [$this, 'processDailyChecks']);
    }

    /**
     * Lên lịch chạy mỗi ngày một lần (Daily)
     */
    public function scheduleCronJob(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Huỷ lịch Cron (dùng khi uninstall)
     */
    public static function clearCronJob(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Hàm chính chạy mỗi khi Cron kích hoạt
     */
    public function processDailyChecks(): void
    {
        $this->checkExpirations();
    }

    /**
     * Quét tất cả các Project để tìm Domain, Hosting, SSL sắp hết hạn
     */
    private function checkExpirations(): void
    {
        global $wpdb;

        $today = new \DateTime();
        
        $sql = "SELECT p.ID, p.post_title, m.meta_key, m.meta_value 
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
                WHERE p.post_type = 'project' 
                  AND p.post_status = 'publish'
                  AND m.meta_key IN ('_domain_expiry', '_hosting_expiry', '_ssl_expiry')
                  AND m.meta_value != ''";
                  
        $results = $wpdb->get_results($sql);

        $notifications = [];

        foreach ($results as $row) {
            $projectId = (int) $row->ID;
            $projectName = $row->post_title;
            $key = $row->meta_key;
            $expiryDateStr = $row->meta_value;
            
            try {
                $expiryDate = new \DateTime($expiryDateStr);
                $diff = $today->diff($expiryDate);
                $daysLeft = (int) $diff->format('%r%a');

                // Lấy cấu hình số ngày cảnh báo (mặc định 30 ngày cho domain/hosting, 14 cho SSL)
                $notifyDaysKey = str_replace('_expiry', '_notify_days', $key);
                $notifyDays = (int) carbon_get_post_meta($projectId, substr($notifyDaysKey, 1)) ?: ($key === '_ssl_expiry' ? 14 : 30);

                if ($daysLeft <= $notifyDays) {
                    $type = str_replace('_expiry', '', substr($key, 1));
                    $level = $daysLeft <= 7 ? 'critical' : 'warning';
                    
                    $alertType = "{$type}_expiry";
                    
                    // Tránh ghi log trùng lặp (1 alert tạo 1 lần trừ khi đã resolve)
                    if (!class_exists('\App\Models\ProjectAlert')) {
                        continue;
                    }
                    
                    if (!ProjectAlert::existsActive($projectId, $alertType)) {
                        $msg = sprintf(
                            "Dịch vụ %s của dự án '%s' sẽ hết hạn vào ngày %s (còn %d ngày).",
                            strtoupper($type),
                            $projectName,
                            $expiryDate->format('d/m/Y'),
                            $daysLeft
                        );

                        ProjectAlert::add([
                            'project_id'  => $projectId,
                            'alert_type'  => $alertType,
                            'alert_level' => $level,
                            'alert_msg'   => $msg,
                        ]);

                        // Chuẩn bị gửi email / zalo
                        $notifications[] = $msg;
                    }
                }

            } catch (\Exception $e) {
                // Invalid date
            }
        }

        // Nếu có thông báo, gom lại và gởi đi
        if (!empty($notifications)) {
            $this->sendNotifications($notifications);
        }
    }

    /**
     * Gửi Mail và Zalo
     */
    private function sendNotifications(array $messages): void
    {
        $content = "Hệ thống LacaDev Project Manager phát hiện các cảnh báo sau:\n\n";
        foreach ($messages as $msg) {
            $content .= "- " . $msg . "\n";
        }
        $content .= "\nVui lòng truy cập admin để quản lý gia hạn.";

        // Thông báo qua Email
        $isEmailEnabled = carbon_get_theme_option('enable_email_notify');
        if ($isEmailEnabled === 'yes' || $isEmailEnabled === true) {
            $emailRaw = carbon_get_theme_option('project_admin_email');
            if ($emailRaw) {
                $emails = array_map('trim', explode(',', $emailRaw));
                $subject = '[LacaDev PM] Cảnh báo dịch vụ sắp hết hạn';
                wp_mail($emails, $subject, $content);
            }
        }

        // Thông báo qua Zalo
        $isZaloEnabled = carbon_get_theme_option('enable_zalo_notify');
        if ($isZaloEnabled === 'yes' || $isZaloEnabled === true) {
            $oaToken = carbon_get_theme_option('zalo_oa_access_token');
            $receiversRaw = carbon_get_theme_option('zalo_default_receiver');
            if ($oaToken && $receiversRaw) {
                $receivers = array_map('trim', explode(',', $receiversRaw));
                foreach ($receivers as $uid) {
                    $this->sendZaloMessage($oaToken, $uid, $content);
                }
            }
        }

        // Thông báo qua Telegram
        $isTelegramEnabled = carbon_get_theme_option('enable_telegram_notify');
        if ($isTelegramEnabled === 'yes' || $isTelegramEnabled === true) {
            $telegramToken = carbon_get_theme_option('telegram_bot_token');
            $chatIdsRaw = carbon_get_theme_option('telegram_chat_id');
            if ($telegramToken && $chatIdsRaw) {
                $chatIds = array_map('trim', explode(',', $chatIdsRaw));
                foreach ($chatIds as $chatId) {
                    $this->sendTelegramMessage($telegramToken, $chatId, $content);
                }
            }
        }

        // Thông báo qua Slack
        $isSlackEnabled = carbon_get_theme_option('enable_slack_notify');
        if ($isSlackEnabled === 'yes' || $isSlackEnabled === true) {
            $slackWebhook = carbon_get_theme_option('slack_webhook_url');
            if ($slackWebhook) {
                $this->sendSlackMessage($slackWebhook, $content);
            }
        }
    }

    /**
     * Gửi tin nhắn qua Zalo OA API
     */
    private function sendZaloMessage(string $token, string $userId, string $text): bool
    {
        $url = 'https://openapi.zalo.me/v3.0/oa/message/cs';
        $body = [
            'recipient' => ['user_id' => $userId],
            'message'   => ['text'    => $text],
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'access_token' => $token,
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 15,
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Gửi tin nhắn qua Telegram Bot API
     */
    private function sendTelegramMessage(string $token, string $chatId, string $text): bool
    {
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $body = [
            'chat_id' => $chatId,
            'text'    => $text,
        ];

        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => 15,
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Gửi tin nhắn qua Slack Webhook
     */
    private function sendSlackMessage(string $webhookUrl, string $text): bool
    {
        $body = ['text' => $text];

        $response = wp_remote_post($webhookUrl, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => 15,
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }
}
