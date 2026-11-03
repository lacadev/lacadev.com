<?php

namespace App\Settings\LacaTools;

use App\Models\ProjectAlert;
use App\Databases\UptimeLogTable;
use App\Notifications\NotificationBus;

/**
 * SiteHealthChecker
 *
 * Toàn bộ hệ thống cảnh báo hiện tại (site down, SSL sắp hết hạn...) dựa vào
 * SITE KHÁCH tự báo cáo về hub qua tracker cron. Nếu site khách sập hẳn
 * (hosting treo, DB lỗi, hết dung lượng), nó không thể tự báo được — hub sẽ
 * không biết trừ khi có ai đó tự vào kiểm tra. Class này để HUB chủ động đi
 * hỏi từng site khách còn sống không, độc lập hoàn toàn với tracker.
 *
 * Dùng ở 2 nơi:
 *  1. Cron hàng giờ quét toàn bộ project có `live_url` (giám sát uptime nền theo lô 10).
 *  2. Ngay sau khi push block hoặc trigger remote update thành công — kiểm
 *     tra tức thời xem site có bị vỡ không (post-deploy smoke test), thay vì
 *     đợi khách hàng tự báo lỗi.
 */
class SiteHealthChecker
{
    public const CRON_HOOK = 'laca_uptime_check';

    public const OPT_LAST_STATUS  = '_laca_uptime_last_status';
    public const OPT_LAST_CHECKED = '_laca_uptime_last_checked';
    public const OPT_CONSECUTIVE_FAILURES = '_laca_uptime_consecutive_failures';

    public function init(): void
    {
        add_action(self::CRON_HOOK, [$this, 'runScheduledCheck']);

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'hourly', self::CRON_HOOK);
        }
    }

    /**
     * Chạy định kỳ (cron hourly): kiểm tra TẤT CẢ project có `live_url`.
     * Sử dụng WpOrg\Requests\Requests::request_multiple theo lô 10 song song.
     * Cảnh báo khi 2 lần liên tiếp lỗi.
     */
    public function runScheduledCheck(): void
    {
        $projects = get_posts([
            'post_type'      => 'project',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);

        $targets = [];
        foreach ($projects as $projectId) {
            $url = trim((string) (carbon_get_post_meta($projectId, 'live_url') ?: get_post_meta($projectId, '_live_url', true)));
            if (empty($url)) {
                continue;
            }
            $targets[(int) $projectId] = $url;
        }

        if (empty($targets)) {
            return;
        }

        // Chia lô 10 site kiểm tra song song
        $chunks = array_chunk($targets, 10, true);
        foreach ($chunks as $chunk) {
            $this->checkBatchParallel($chunk);
        }

        // Tự động dọn dẹp log quá 30 ngày
        if (class_exists(UptimeLogTable::class)) {
            UptimeLogTable::purgeOldLogs(30);
        }
    }

    /**
     * Kiểm tra ngay sau 1 hành động vừa thực hiện trên site khách (push
     * block, remote update...) — mục đích là bắt lỗi NGAY nếu hành động vừa
     * làm vô tình làm site vỡ, nên LUÔN cảnh báo nếu đang down, không chỉ khi
     * trạng thái vừa chuyển (khác với kiểm tra định kỳ).
     */
    public function checkAfterDeploy(int $projectId, string $context): void
    {
        $url = trim((string) (carbon_get_post_meta($projectId, 'live_url') ?: get_post_meta($projectId, '_live_url', true)));
        if (empty($url)) {
            return;
        }

        $this->evaluateAndRecord($projectId, $url, $context);
    }

    /**
     * Logic dùng chung cho cả 2 luồng (cron định kỳ + kiểm tra sau deploy)
     */
    private function evaluateAndRecord(int $projectId, string $url, ?string $deployContext = null): void
    {
        $start    = microtime(true);
        $result   = $this->pingSite($url);
        $resMs    = (int) round((microtime(true) - $start) * 1000);
        $newState = $result['up'] ? 'up' : 'down';
        $oldState = get_post_meta($projectId, self::OPT_LAST_STATUS, true) ?: 'up';

        // Ghi vào UptimeLogTable
        if (class_exists(UptimeLogTable::class)) {
            UptimeLogTable::log(
                $projectId,
                $url,
                $newState,
                $result['code'],
                $resMs,
                $result['up'] ? null : $result['message']
            );
        }

        // Cảnh báo "down" khi: (a) vừa chuyển từ up→down, hoặc (b) kiểm tra sau deploy
        if ($newState === 'down' && ($deployContext !== null || $oldState === 'up')) {
            $suffix = $deployContext !== null ? " ngay sau {$deployContext}" : '';
            $msg    = sprintf(
                /* translators: 1: hậu cảnh deploy nếu có, 2: thông điệp lỗi, 3: url */
                __('🔴 Site khách có vẻ đang KHÔNG TRUY CẬP ĐƯỢC%1$s: %2$s (%3$s)', 'laca'),
                $suffix,
                $result['message'],
                $url
            );

            $this->recordAlert($projectId, $msg, 'critical', false);

            if (class_exists(NotificationBus::class)) {
                NotificationBus::emit(NotificationBus::EVENT_UPTIME_DOWN, [
                    'url'         => $url,
                    'http_code'   => $result['code'],
                    'response_ms' => $resMs,
                    'message'     => $msg,
                ], $projectId);
            }
        } elseif ($newState === 'up' && $oldState === 'down') {
            $msg = sprintf(
                /* translators: %s: url */
                __('✅ Site khách đã hoạt động trở lại: %s', 'laca'),
                $url
            );
            $this->recordAlert($projectId, $msg, 'warning', false);

            if (class_exists(NotificationBus::class)) {
                NotificationBus::emit(NotificationBus::EVENT_UPTIME_UP, [
                    'url'         => $url,
                    'response_ms' => $resMs,
                    'message'     => $msg,
                ], $projectId);
            }
        }

        update_post_meta($projectId, self::OPT_LAST_STATUS, $newState);
        update_post_meta($projectId, self::OPT_LAST_CHECKED, current_time('mysql'));
    }

    /**
     * Kiểm tra theo lô song song bằng Requests::request_multiple
     *
     * @param array<int, string> $targets Mảng [projectId => url]
     */
    private function checkBatchParallel(array $targets): void
    {
        $requestsClass = class_exists('\WpOrg\Requests\Requests') ? '\WpOrg\Requests\Requests' : '\Requests';

        $requests   = [];
        $startTimes = [];

        foreach ($targets as $projectId => $url) {
            $sslVerifyVal = carbon_get_post_meta($projectId, 'uptime_sslverify');
            // Mặc định sslverify = true, chỉ tắt khi người dùng cố ý bỏ chọn
            $sslVerify = ($sslVerifyVal === null || $sslVerifyVal === true || $sslVerifyVal === 'yes' || $sslVerifyVal === '1');

            $requests[$projectId] = [
                'url'     => $url,
                'type'    => 'GET',
                'headers' => ['User-Agent' => 'LacaDev-Uptime-Monitor/1.0'],
                'options' => [
                    'timeout'          => 10,
                    'verify'           => $sslVerify,
                    'follow_redirects' => true,
                    'redirects'        => 3,
                ],
            ];
            $startTimes[$projectId] = microtime(true);
        }

        try {
            /** @var array<int, mixed> $responses */
            $responses = $requestsClass::request_multiple($requests);
        } catch (\Throwable $e) {
            // Dự phòng: nếu request_multiple lỗi môi trường, fallback tuần tự
            foreach ($targets as $projectId => $url) {
                $this->evaluateAndRecord((int) $projectId, $url);
            }
            return;
        }

        $now = microtime(true);

        foreach ($responses as $projectId => $resp) {
            $url        = $targets[$projectId] ?? '';
            $start      = $startTimes[$projectId] ?? $now;
            $responseMs = (int) round(($now - $start) * 1000);

            $isUp     = false;
            $httpCode = null;
            $errorMsg = null;

            if ($resp instanceof \Throwable || is_a($resp, '\Exception')) {
                $isUp     = false;
                $errorMsg = $resp->getMessage();
            } else {
                $httpCode = (int) ($resp->status_code ?? 0);
                if ($httpCode >= 500 || in_array($httpCode, [401, 403, 404], true)) {
                    $isUp     = false;
                    $errorMsg = "HTTP {$httpCode}";
                } elseif ($httpCode === 200 && trim(strip_tags((string) $resp->body)) === '') {
                    $isUp     = false;
                    $errorMsg = __('HTTP 200 nhưng nội dung trang rỗng', 'laca');
                } elseif ($httpCode > 0) {
                    $isUp = true;
                } else {
                    $isUp     = false;
                    $errorMsg = __('Không nhận được phản hồi HTTP', 'laca');
                }
            }

            // Ghi vào UptimeLogTable
            if (class_exists(UptimeLogTable::class)) {
                UptimeLogTable::log(
                    (int) $projectId,
                    $url,
                    $isUp ? 'up' : 'down',
                    $httpCode,
                    $responseMs,
                    $errorMsg
                );
            }

            // Xử lý cảnh báo sau 2 lần lỗi liên tiếp
            $this->processConsecutiveStatus((int) $projectId, $url, $isUp, $httpCode, $responseMs, $errorMsg);
        }
    }

    /**
     * Xử lý cảnh báo khi có 2 lần liên tiếp lỗi và phục hồi khi site up trở lại
     */
    private function processConsecutiveStatus(
        int $projectId,
        string $url,
        bool $isUp,
        ?int $httpCode,
        int $responseMs,
        ?string $errorMsg
    ): void {
        $consecutiveFailures = (int) get_post_meta($projectId, self::OPT_CONSECUTIVE_FAILURES, true);

        if (!$isUp) {
            $consecutiveFailures++;
            update_post_meta($projectId, self::OPT_CONSECUTIVE_FAILURES, $consecutiveFailures);
            update_post_meta($projectId, self::OPT_LAST_STATUS, 'down');
            update_post_meta($projectId, self::OPT_LAST_CHECKED, current_time('mysql'));

            // Cảnh báo khi đạt ĐÚNG 2 lần liên tiếp lỗi
            if ($consecutiveFailures === 2) {
                $reason = $errorMsg ?: ($httpCode ? "HTTP {$httpCode}" : __('Lỗi kết nối / Timeout', 'laca'));
                $projectName = get_the_title($projectId) ?: "#{$projectId}";
                /* translators: 1: tên dự án, 2: url, 3: lý do lỗi */
                $msg = sprintf(
                    __('🔴 Site khách KHÔNG TRUY CẬP ĐƯỢC (2 lần kiểm tra liên tiếp): %1$s (%2$s). Lỗi: %3$s', 'laca'),
                    $projectName,
                    $url,
                    $reason
                );

                $this->recordAlert($projectId, $msg, 'critical', false);

                if (class_exists(NotificationBus::class)) {
                    NotificationBus::emit(
                        NotificationBus::EVENT_UPTIME_DOWN,
                        [
                            'url'         => $url,
                            'http_code'   => $httpCode,
                            'response_ms' => $responseMs,
                            'message'     => $msg,
                        ],
                        $projectId
                    );
                }
            }
        } else {
            // Site đang sống (UP)
            if ($consecutiveFailures >= 2) {
                // Đã từng lỗi >= 2 lần -> Bắn cảnh báo phục hồi!
                $projectName = get_the_title($projectId) ?: "#{$projectId}";
                /* translators: 1: tên dự án, 2: url, 3: số ms phản hồi */
                $msg = sprintf(
                    __('✅ Site khách đã HOẠT ĐỘNG TRỞ LẠI: %1$s (%2$s). Phản hồi: %3$dms', 'laca'),
                    $projectName,
                    $url,
                    $responseMs
                );

                $this->recordAlert($projectId, $msg, 'warning', false);

                if (class_exists(NotificationBus::class)) {
                    NotificationBus::emit(
                        NotificationBus::EVENT_UPTIME_UP,
                        [
                            'url'         => $url,
                            'response_ms' => $responseMs,
                            'message'     => $msg,
                        ],
                        $projectId
                    );
                }
            }

            delete_post_meta($projectId, self::OPT_CONSECUTIVE_FAILURES);
            update_post_meta($projectId, self::OPT_LAST_STATUS, 'up');
            update_post_meta($projectId, self::OPT_LAST_CHECKED, current_time('mysql'));
        }
    }

    /**
     * Ghi alert vào bảng wp_laca_project_alerts TRƯỚC KHI bắn thông báo
     */
    private function recordAlert(int $projectId, string $msg, string $level, bool $notify = true): void
    {
        if (!class_exists(ProjectAlert::class)) {
            return;
        }

        if (ProjectAlert::existsActiveByMsg($projectId, $msg)) {
            return;
        }

        $alertId = ProjectAlert::add([
            'project_id'  => $projectId,
            'alert_type'  => 'other',
            'alert_level' => $level,
            'alert_msg'   => $msg,
        ]);

        if ($alertId !== false && $notify) {
            do_action('laca_project_alert_notify', $projectId, $level, $msg);
        }
    }

    /**
     * @return array{up:bool, code:?int, message:string}
     */
    public function pingSite(string $url): array
    {
        $result = $this->doPing($url);
        if ($result['up']) {
            return $result;
        }

        // Thử lại 1 lần sau vài giây trước khi kết luận "down"
        sleep(3);

        return $this->doPing($url);
    }

    /**
     * @return array{up:bool, code:?int, message:string}
     */
    private function doPing(string $url): array
    {
        $response = wp_remote_get($url, [
            'timeout'     => 15,
            'redirection' => 3,
            'sslverify'   => false,
            'user-agent'  => 'LacaDev-Uptime-Monitor/1.0',
        ]);

        if (is_wp_error($response)) {
            return ['up' => false, 'code' => null, 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 500 || in_array($code, [401, 403, 404], true)) {
            return ['up' => false, 'code' => $code, 'message' => "HTTP {$code}"];
        }

        $body = wp_remote_retrieve_body($response);
        if ($code === 200 && trim(strip_tags($body)) === '') {
            return ['up' => false, 'code' => $code, 'message' => __('HTTP 200 nhưng nội dung trang rỗng', 'laca')];
        }

        return ['up' => true, 'code' => $code, 'message' => 'OK'];
    }
}
