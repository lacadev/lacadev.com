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

    /** Option lưu timestamp lần processDailyChecks() chạy gần nhất — dùng để phát hiện cron bị trễ. */
    private const OPT_LAST_RUN = '_laca_cron_last_run_daily';

    /** Option lưu trạng thái gửi gần nhất của từng kênh (ok/at/error) — xem recordChannelResult(). */
    private const OPT_CHANNEL_STATUS = 'laca_notify_channel_status';

    /** Option lưu thời điểm Access Token Zalo OA hết hạn — xem refreshZaloToken(). */
    private const OPT_ZALO_TOKEN_EXPIRES_AT = '_zalo_oa_token_expires_at';

    /** Ghi lại bởi hook wp_mail_failed — chỉ dùng tạm trong 1 lượt sendNotifications(). */
    private string $lastMailError = '';

    public function init(): void
    {
        add_action('init', [$this, 'scheduleCronJob']);
        add_action(self::CRON_HOOK, [$this, 'processDailyChecks']);
        add_action('laca_project_alert_notify', [$this, 'handleRealtimeAlert'], 10, 3);
        add_action('admin_notices', [$this, 'renderCronHealthNotice']);
        add_action('admin_notices', [$this, 'renderChannelFailureNotice']);
        add_action('wp_mail_failed', function (\WP_Error $error): void {
            $this->lastMailError = $error->get_error_message();
        });
    }

    /**
     * Cảnh báo trong wp-admin nếu kênh thông báo nào đang bật mà lần gửi gần
     * nhất thất bại — trước đây sendZaloMessage()/sendTelegramMessage()/
     * sendSlackMessage() trả về bool nhưng bị bỏ qua hoàn toàn, nên 1 token
     * hết hạn hay webhook sai có thể chết êm re không ai biết.
     */
    public function renderChannelFailureNotice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $channelLabels = ['email' => 'Email', 'zalo' => 'Zalo OA', 'telegram' => 'Telegram', 'slack' => 'Slack'];
        $status = $this->getChannelStatusReport();

        foreach ($channelLabels as $channel => $label) {
            if (empty($status[$channel]) || !empty($status[$channel]['ok'])) {
                continue;
            }

            $at = !empty($status[$channel]['at']) ? wp_date('H:i d/m/Y', (int) $status[$channel]['at']) : '?';
            $error = $status[$channel]['error'] ?? '';
            ?>
            <div class="notice notice-error">
                <p>
                    ⚠️ <strong><?php echo esc_html(sprintf(__('Gửi thông báo qua %s thất bại', 'laca'), $label)); ?></strong>
                    — <?php echo esc_html(sprintf(__('lần cuối lúc %s.', 'laca'), $at)); ?>
                    <?php if ($error): ?>
                        <?php echo esc_html__('Lỗi:', 'laca'); ?> <code><?php echo esc_html($error); ?></code>.
                    <?php endif; ?>
                    <?php echo esc_html__('Kiểm tra cấu hình tại Laca Admin → LacaDev PM & Bots.', 'laca'); ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Trạng thái gửi gần nhất của từng kênh (dùng cho notice trên + dashboard
     * tile ở LacaProjectsHub).
     *
     * @return array<string,array{ok:bool,at:int,error:string}>
     */
    public function getChannelStatusReport(): array
    {
        $status = get_option(self::OPT_CHANNEL_STATUS, []);
        return is_array($status) ? $status : [];
    }

    /**
     * Ghi lại kết quả gửi gần nhất của 1 kênh — gọi từ sendNotifications()
     * ngay sau mỗi lần gửi thực tế (không gọi nếu kênh đang tắt).
     */
    private function recordChannelResult(string $channel, bool $ok, string $error = ''): void
    {
        $status = $this->getChannelStatusReport();
        $status[$channel] = [
            'ok'    => $ok,
            'at'    => time(),
            'error' => $ok ? '' : substr($error, 0, 300),
        ];
        update_option(self::OPT_CHANNEL_STATUS, $status, false);
    }

    /**
     * Cảnh báo trong wp-admin nếu cron daily đã trễ quá 3 ngày so với lần
     * chạy gần nhất — dấu hiệu WordPress pseudo-cron không tự chạy đều vì
     * site ít traffic. Bỏ qua nếu chưa từng có baseline (site mới cài).
     */
    public function renderCronHealthNotice(): void
    {
        if (!current_user_can('manage_options') || !wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        $lastRun = (int) get_option(self::OPT_LAST_RUN, 0);
        if ($lastRun === 0 || (time() - $lastRun) < 3 * DAY_IN_SECONDS) {
            return;
        }

        $hoursLate = (int) round((time() - $lastRun) / HOUR_IN_SECONDS);
        ?>
        <div class="notice notice-warning">
            <p>
                ⏰ <strong><?php echo esc_html__('Cron có thể đang trễ:', 'laca'); ?></strong>
                <?php echo esc_html(sprintf(__('Cronjob kiểm tra hết hạn dịch vụ (laca_project_manager_daily_cron) chưa chạy lại trong %d giờ qua.', 'laca'), $hoursLate)); ?>
                <?php echo esc_html__('Site ít traffic thì WordPress pseudo-cron không tự chạy đều — xem hướng dẫn cài cron hệ thống thật trong doc/TRACKER_HUB_CLIENT_SYNC.md.', 'laca'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Đẩy ngay cảnh báo warning/critical vào các kênh Email/Zalo/Telegram/Slack
     * thay vì chỉ chờ cron kiểm tra hết hạn hằng ngày. Được gọi qua
     * do_action('laca_project_alert_notify', $projectId, $level, $message)
     * từ TrackerEndpointHandler / ClientWebhook ngay sau khi tạo ProjectAlert mới.
     */
    public function handleRealtimeAlert(int $projectId, string $level, string $message): void
    {
        if (!in_array($level, ['warning', 'critical'], true)) {
            return;
        }

        $projectName = get_the_title($projectId) ?: "#{$projectId}";
        $this->sendNotifications(["[{$projectName}] {$message}"]);
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
        update_option(self::OPT_LAST_RUN, time(), false);
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
                $this->lastMailError = '';
                $sent = wp_mail($emails, $subject, $content);
                $this->recordChannelResult('email', $sent, $this->lastMailError ?: 'wp_mail() trả về false');
            }
        }

        // Thông báo qua Zalo
        $isZaloEnabled = carbon_get_theme_option('enable_zalo_notify');
        if ($isZaloEnabled === 'yes' || $isZaloEnabled === true) {
            // Access Token Zalo OA sống ~1h — refresh chủ động trước khi gửi
            // nếu đã qua/sắp qua hạn (buffer 5 phút), tránh gửi thất bại vô ích.
            $expiresAt = (int) get_option(self::OPT_ZALO_TOKEN_EXPIRES_AT, 0);
            if ($expiresAt > 0 && $expiresAt - 300 < time()) {
                $this->refreshZaloToken();
            }

            $oaToken = carbon_get_theme_option('zalo_oa_access_token');
            $receiversRaw = carbon_get_theme_option('zalo_default_receiver');
            if ($oaToken && $receiversRaw) {
                $receivers = array_map('trim', explode(',', $receiversRaw));
                $ok = true;
                $error = '';
                foreach ($receivers as $uid) {
                    $result = $this->sendZaloMessage($oaToken, $uid, $content);
                    // Thất bại có thể do token hết hạn ngoài dự kiến — refresh
                    // 1 lần rồi gửi lại đúng 1 lần, không lặp vô hạn.
                    if (!$result['ok'] && $this->refreshZaloToken()) {
                        $oaToken = carbon_get_theme_option('zalo_oa_access_token');
                        $result = $this->sendZaloMessage($oaToken, $uid, $content);
                    }
                    if (!$result['ok']) {
                        $ok = false;
                        $error = $result['error'];
                    }
                }
                $this->recordChannelResult('zalo', $ok, $error);
            }
        }

        // Thông báo qua Telegram
        $isTelegramEnabled = carbon_get_theme_option('enable_telegram_notify');
        if ($isTelegramEnabled === 'yes' || $isTelegramEnabled === true) {
            $telegramToken = carbon_get_theme_option('telegram_bot_token');
            $chatIdsRaw = carbon_get_theme_option('telegram_chat_id');
            if ($telegramToken && $chatIdsRaw) {
                $chatIds = array_map('trim', explode(',', $chatIdsRaw));
                $ok = true;
                $error = '';
                foreach ($chatIds as $chatId) {
                    $result = $this->sendTelegramMessage($telegramToken, $chatId, $content);
                    if (!$result['ok']) {
                        $ok = false;
                        $error = $result['error'];
                    }
                }
                $this->recordChannelResult('telegram', $ok, $error);
            }
        }

        // Thông báo qua Slack
        $isSlackEnabled = carbon_get_theme_option('enable_slack_notify');
        if ($isSlackEnabled === 'yes' || $isSlackEnabled === true) {
            $slackWebhook = carbon_get_theme_option('slack_webhook_url');
            if ($slackWebhook) {
                $result = $this->sendSlackMessage($slackWebhook, $content);
                $this->recordChannelResult('slack', $result['ok'], $result['error']);
            }
        }
    }

    /**
     * Refresh Access Token của Zalo OA bằng Refresh Token hiện có.
     *
     * Cần App ID + App Secret (đăng ký ở Zalo Developers, khác với Access/
     * Refresh Token của riêng OA) — không có 2 giá trị này thì bỏ qua, giữ
     * nguyên hành vi cũ (gửi thất bại nếu token thật sự đã hết hạn).
     * Zalo xoay cả access_token và refresh_token mỗi lần refresh — token cũ
     * hết hiệu lực ngay, nên phải ghi đè cả 2 giá trị mới vào theme option.
     */
    private function refreshZaloToken(): bool
    {
        $appId = carbon_get_theme_option('zalo_app_id');
        $appSecret = carbon_get_theme_option('zalo_app_secret');
        $refreshToken = carbon_get_theme_option('zalo_oa_refresh_token');

        if (!$appId || !$appSecret || !$refreshToken) {
            return false;
        }

        $response = wp_remote_post('https://oauth.zaloapp.com/v4/oa/access_token', [
            'headers' => [
                'secret_key'   => $appSecret,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'app_id'        => $appId,
                'refresh_token' => $refreshToken,
                'grant_type'    => 'refresh_token',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['access_token'])) {
            return false;
        }

        // Carbon Fields theme options lưu ngầm dưới option name có tiền tố
        // "_" — ghi trực tiếp qua update_option() thay vì qua Carbon Fields
        // API (chỉ hỗ trợ đọc tiện lợi ở runtime, không có API set chuẩn).
        update_option('_zalo_oa_access_token', sanitize_text_field($data['access_token']), false);
        if (!empty($data['refresh_token'])) {
            update_option('_zalo_oa_refresh_token', sanitize_text_field($data['refresh_token']), false);
        }
        update_option(self::OPT_ZALO_TOKEN_EXPIRES_AT, time() + (int) ($data['expires_in'] ?? 3600), false);

        return true;
    }

    /**
     * Gửi tin nhắn qua Zalo OA API
     *
     * @return array{ok:bool,error:string}
     */
    private function sendZaloMessage(string $token, string $userId, string $text): array
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

        return $this->toChannelResult($response);
    }

    /**
     * Gửi tin nhắn qua Telegram Bot API
     *
     * @return array{ok:bool,error:string}
     */
    private function sendTelegramMessage(string $token, string $chatId, string $text): array
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

        return $this->toChannelResult($response);
    }

    /**
     * Gửi tin nhắn qua Slack Webhook
     *
     * @return array{ok:bool,error:string}
     */
    private function sendSlackMessage(string $webhookUrl, string $text): array
    {
        $body = ['text' => $text];

        $response = wp_remote_post($webhookUrl, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => 15,
        ]);

        return $this->toChannelResult($response);
    }

    /**
     * Chuẩn hoá kết quả wp_remote_post() thành {ok, error} — dùng chung cho
     * cả 3 kênh Zalo/Telegram/Slack.
     *
     * @param mixed $response Kết quả wp_remote_post() (WP_Error hoặc array).
     * @return array{ok:bool,error:string}
     */
    private function toChannelResult($response): array
    {
        if (is_wp_error($response)) {
            return ['ok' => false, 'error' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code === 200) {
            return ['ok' => true, 'error' => ''];
        }

        return ['ok' => false, 'error' => 'HTTP ' . $code . ': ' . wp_remote_retrieve_body($response)];
    }
}
