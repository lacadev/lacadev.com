<?php

namespace App\Notifications;

use App\Settings\LacaTools\ProjectNotificationHandler;
use App\Features\DiscordHub\Notifier;
use App\Features\DiscordHub\Embed;
use App\Features\DiscordHub\Settings as DiscordSettings;

/**
 * NotificationBus: Hệ thống Event Bus thông báo hợp nhất
 *
 * Định tuyến các sự kiện hệ thống (cảnh báo project, cron trễ, sao lưu quá hạn,
 * uptime, deployment...) tới các kênh (Email, Zalo, Telegram, Slack, Discord)
 * theo bảng cấu hình với cơ chế chống trùng lặp (dedup).
 */
class NotificationBus
{
    public const OPT_ROUTES = 'laca_notification_routes';

    public const EVENT_PROJECT_ALERT   = 'project_alert';
    public const EVENT_CRON_LATE       = 'cron_late';
    public const EVENT_BACKUP_OUTDATED = 'backup_outdated';
    public const EVENT_UPTIME_DOWN     = 'uptime_down';
    public const EVENT_UPTIME_UP       = 'uptime_up';
    public const EVENT_DEPLOYMENT      = 'deployment';
    public const EVENT_CLIENT_REQUEST  = 'client_request';

    public const DEFAULT_ROUTES = [
        self::EVENT_PROJECT_ALERT   => ['email', 'zalo', 'telegram', 'slack', 'discord'],
        self::EVENT_CRON_LATE       => ['discord'],
        self::EVENT_BACKUP_OUTDATED => ['email', 'telegram', 'discord'],
        self::EVENT_UPTIME_DOWN     => ['email', 'zalo', 'telegram', 'slack', 'discord'],
        self::EVENT_UPTIME_UP       => ['email', 'zalo', 'telegram', 'slack', 'discord'],
        self::EVENT_DEPLOYMENT      => ['slack', 'discord'],
        self::EVENT_CLIENT_REQUEST  => ['email', 'zalo', 'telegram', 'slack', 'discord'],
    ];

    private const COOLDOWNS = [
        self::EVENT_PROJECT_ALERT   => 1800,  // 30 phút
        self::EVENT_CRON_LATE       => 43200, // 12 giờ
        self::EVENT_BACKUP_OUTDATED => 86400, // 24 giờ
        self::EVENT_UPTIME_DOWN     => 1800,  // 30 phút
        self::EVENT_UPTIME_UP       => 1800,  // 30 phút
        self::EVENT_DEPLOYMENT      => 300,   // 5 phút
        self::EVENT_CLIENT_REQUEST  => 60,    // 1 phút
    ];

    /**
     * Phát sự kiện qua Bus
     *
     * @param string $event Tên sự kiện
     * @param array<string, mixed> $payload Dữ liệu sự kiện
     * @param int|null $projectId ID dự án liên quan (nếu có)
     * @return bool True nếu được gửi đi, False nếu bị dedup hoặc không có kênh nào bật
     */
    public static function emit(string $event, array $payload = [], ?int $projectId = null): bool
    {
        // 1. Chống trùng lặp (Dedup)
        $fingerprint  = md5($event . '|' . ($projectId ?? 0) . '|' . wp_json_encode($payload));
        $transientKey = '_laca_bus_dd_' . substr($fingerprint, 0, 20);

        if (get_transient($transientKey)) {
            return false;
        }

        $cooldown = self::COOLDOWNS[$event] ?? 1800;
        set_transient($transientKey, 1, $cooldown);

        // 2. Tra cứu bảng định tuyến
        $routes   = self::getRoutes();
        $channels = $routes[$event] ?? (self::DEFAULT_ROUTES[$event] ?? []);

        if (empty($channels)) {
            return false;
        }

        // Cho phép môi trường test chặn gửi ra ngoài
        if (apply_filters('laca_suppress_realtime_notifications', false)) {
            return true;
        }

        // 3. Chuẩn bị nội dung thông báo
        $messageText = self::formatMessageText($event, $payload, $projectId);

        // 4. Gửi qua các kênh truyền thống (Email, Zalo, Telegram, Slack)
        $handlerChannels = array_values(array_intersect($channels, ['email', 'zalo', 'telegram', 'slack']));
        if (!empty($handlerChannels)) {
            foreach ($handlerChannels as $ch) {
                do_action('laca_notification_bus_dispatch_channel', $ch, $event, $payload, $projectId);
            }
            $handler = new ProjectNotificationHandler();
            $handler->sendNotificationsFiltered([$messageText], $handlerChannels);
        }

        // 5. Gửi qua kênh Discord
        if (in_array('discord', $channels, true) && class_exists(Notifier::class) && class_exists(DiscordSettings::class)) {
            do_action('laca_notification_bus_dispatch_channel', 'discord', $event, $payload, $projectId);
            self::sendToDiscord($event, $payload, $projectId, $messageText);
        }

        return true;
    }

    /**
     * Lấy cấu hình bảng định tuyến hiện tại
     *
     * @return array<string, list<string>>
     */
    public static function getRoutes(): array
    {
        // Ưu tiên đọc từ Carbon Fields nếu đã lưu
        if (function_exists('carbon_get_theme_option')) {
            $routes = [];
            foreach (self::DEFAULT_ROUTES as $event => $defaultChannels) {
                $cfKey = 'notify_route_' . $event;
                $val   = carbon_get_theme_option($cfKey);
                if (is_array($val) && !empty($val)) {
                    $routes[$event] = array_values(array_map('strval', $val));
                }
            }
            if (!empty($routes)) {
                return array_merge(self::DEFAULT_ROUTES, $routes);
            }
        }

        $saved = get_option(self::OPT_ROUTES, null);
        if (is_array($saved) && !empty($saved)) {
            return array_merge(self::DEFAULT_ROUTES, $saved);
        }

        return self::DEFAULT_ROUTES;
    }

    /**
     * Cập nhật cấu hình bảng định tuyến
     *
     * @param array<string, list<string>> $routes
     */
    public static function setRoutes(array $routes): void
    {
        update_option(self::OPT_ROUTES, $routes, false);
    }

    private static function formatMessageText(string $event, array $payload, ?int $projectId = null): string
    {
        $projectName = '';
        if ($projectId !== null && $projectId > 0) {
            $title = get_the_title($projectId);
            $projectName = $title ? "[{$title}] " : "[#{$projectId}] ";
        }

        return match ($event) {
            self::EVENT_PROJECT_ALERT => $projectName . ($payload['message'] ?? __('Cảnh báo mới từ hệ thống.', 'laca')),
            self::EVENT_CRON_LATE     => sprintf(
                /* translators: 1: số giờ trễ, 2: lệnh cron */
                __('⏰ Cron có thể đang trễ: chưa chạy lại trong %1$d giờ qua. Lệnh gợi ý: %2$s', 'laca'),
                (int) ($payload['hours'] ?? 0),
                (string) ($payload['command'] ?? 'wp-cron.php')
            ),
            self::EVENT_BACKUP_OUTDATED => $projectName . ($payload['message'] ?? __('Bản sao lưu website đã quá hạn.', 'laca')),
            self::EVENT_UPTIME_DOWN     => sprintf(
                /* translators: 1: tên dự án, 2: url, 3: mã lỗi hoặc http code */
                __('🔴 Site khách KHÔNG TRUY CẬP ĐƯỢC: %1$s (%2$s). Mã HTTP: %3$s', 'laca'),
                $projectName,
                (string) ($payload['url'] ?? ''),
                (string) ($payload['http_code'] ?? '—')
            ),
            self::EVENT_UPTIME_UP       => sprintf(
                /* translators: 1: tên dự án, 2: url, 3: số ms */
                __('✅ Site khách đã HOẠT ĐỘNG TRỞ LẠI: %1$s (%2$s). Thời gian phản hồi: %3$dms', 'laca'),
                $projectName,
                (string) ($payload['url'] ?? ''),
                (int) ($payload['response_ms'] ?? 0)
            ),
            self::EVENT_DEPLOYMENT      => sprintf(
                /* translators: 1: tên dự án, 2: hành động hoặc tin nhắn deploy */
                __('🚀 Cập nhật từ xa thành công: %1$s (%2$s).', 'laca'),
                $projectName,
                (string) ($payload['message'] ?? ($payload['action'] ?? 'deploy'))
            ),
            self::EVENT_CLIENT_REQUEST  => sprintf(
                /* translators: 1: tên dự án, 2: loại yêu cầu, 3: tiêu đề yêu cầu */
                __('🎫 %1$sYêu cầu hỗ trợ [%2$s]: %3$s', 'laca'),
                $projectName,
                (string) ($payload['category_label'] ?? ($payload['category'] ?? 'Hỗ trợ')),
                (string) ($payload['title'] ?? ($payload['message'] ?? ''))
            ),
            default => $projectName . ($payload['message'] ?? __('Thông báo hệ thống LacaDev.', 'laca'))
        };
    }

    private static function sendToDiscord(string $event, array $payload, ?int $projectId, string $formattedText): void
    {
        $discordChannel = ($event === self::EVENT_DEPLOYMENT) ? DiscordSettings::CHANNEL_UPDATES : DiscordSettings::CHANNEL_SECURITY;

        $color = match ($event) {
            self::EVENT_UPTIME_DOWN, self::EVENT_CRON_LATE => Embed::COLOR_DANGER,
            self::EVENT_BACKUP_OUTDATED, self::EVENT_PROJECT_ALERT => Embed::COLOR_WARNING,
            self::EVENT_UPTIME_UP, self::EVENT_DEPLOYMENT => Embed::COLOR_SUCCESS,
            self::EVENT_CLIENT_REQUEST => Embed::COLOR_INFO,
            default => Embed::COLOR_INFO,
        };

        $title = match ($event) {
            self::EVENT_UPTIME_DOWN     => '🔴 Website Down Alert',
            self::EVENT_UPTIME_UP       => '✅ Website Recovered',
            self::EVENT_CRON_LATE       => '⏰ Cron Delay Warning',
            self::EVENT_BACKUP_OUTDATED => '⚠️ Backup Outdated Warning',
            self::EVENT_DEPLOYMENT      => '🚀 Remote Deployment',
            self::EVENT_CLIENT_REQUEST  => '🎫 Client Support Request',
            default                     => '🔔 Laca System Alert',
        };

        $embed = Embed::make($title, $color)->description($formattedText, true);

        if ($projectId !== null && $projectId > 0) {
            $embed->field('Dự án', get_the_title($projectId) ?: "#{$projectId}");
        }

        if (!empty($payload['url'])) {
            $embed->field('URL', (string) $payload['url']);
        }

        if (isset($payload['response_ms']) && (int) $payload['response_ms'] > 0) {
            $embed->field('Response Time', $payload['response_ms'] . 'ms');
        }

        if (!empty($payload['priority'])) {
            $embed->field('Mức ưu tiên', strtoupper((string) $payload['priority']));
        }

        if (!empty($payload['attachment_url'])) {
            $embed->field('Ảnh đính kèm', (string) $payload['attachment_url']);
        }

        Notifier::send($discordChannel, 'bus.' . $event, $embed);
    }
}

