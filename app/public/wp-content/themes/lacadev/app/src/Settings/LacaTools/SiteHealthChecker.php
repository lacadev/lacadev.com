<?php

namespace App\Settings\LacaTools;

use App\Models\ProjectAlert;

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
 *  1. Cron hàng giờ quét toàn bộ project có `live_url` (giám sát uptime nền).
 *  2. Ngay sau khi push block hoặc trigger remote update thành công — kiểm
 *     tra tức thời xem site có bị vỡ không (post-deploy smoke test), thay vì
 *     đợi khách hàng tự báo lỗi.
 */
class SiteHealthChecker
{
    const CRON_HOOK = 'laca_uptime_check';

    const OPT_LAST_STATUS  = '_laca_uptime_last_status';
    const OPT_LAST_CHECKED = '_laca_uptime_last_checked';

    public function init(): void
    {
        add_action(self::CRON_HOOK, [$this, 'runScheduledCheck']);

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'hourly', self::CRON_HOOK);
        }
    }

    /**
     * Chạy định kỳ (cron hourly): kiểm tra TẤT CẢ project có `live_url`. Chỉ
     * bắn cảnh báo khi trạng thái THAY ĐỔI (up→down hoặc down→up) — tránh
     * spam cảnh báo lặp lại mỗi giờ trong khi site vẫn đang down.
     */
    public function runScheduledCheck(): void
    {
        $projects = get_posts([
            'post_type'      => 'project',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);

        foreach ($projects as $projectId) {
            $url = trim((string) carbon_get_post_meta($projectId, 'live_url'));
            if (empty($url)) {
                continue;
            }

            $this->evaluateAndRecord((int) $projectId, $url);
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
        $url = trim((string) carbon_get_post_meta($projectId, 'live_url'));
        if (empty($url)) {
            return;
        }

        $this->evaluateAndRecord($projectId, $url, $context);
    }

    /**
     * Logic dùng chung cho cả 2 luồng (cron định kỳ + kiểm tra sau deploy) —
     * trước đây 2 luồng có state machine khác nhau, khiến 1 site được phát
     * hiện "down" bởi cron, rồi được phát hiện "up trở lại" bởi post-deploy
     * check lại không hề bắn thông báo phục hồi (vì post-deploy chỉ cảnh báo
     * khi fail, không có nhánh xử lý transition down→up) — khách/admin cứ
     * tưởng site vẫn đang down dù đã tự khỏi.
     */
    private function evaluateAndRecord(int $projectId, string $url, ?string $deployContext = null): void
    {
        $result   = $this->pingSite($url);
        $newState = $result['up'] ? 'up' : 'down';
        $oldState = get_post_meta($projectId, self::OPT_LAST_STATUS, true) ?: 'up';

        // Cảnh báo "down" khi: (a) vừa chuyển từ up→down (áp dụng cho cả 2
        // luồng), hoặc (b) đang kiểm tra sau deploy và site đang down — admin
        // cần biết NGAY sau hành động vừa làm dù site đã down từ trước đó.
        if ($newState === 'down' && ($deployContext !== null || $oldState === 'up')) {
            $suffix = $deployContext !== null ? " ngay sau {$deployContext}" : '';
            $this->recordAlert(
                $projectId,
                "🔴 Site khách có vẻ đang KHÔNG TRUY CẬP ĐƯỢC{$suffix}: {$result['message']} ({$url})",
                'critical'
            );
        } elseif ($newState === 'up' && $oldState === 'down') {
            $this->recordAlert(
                $projectId,
                "✅ Site khách đã hoạt động trở lại: {$url}",
                'warning'
            );
        }

        update_post_meta($projectId, self::OPT_LAST_STATUS, $newState);
        update_post_meta($projectId, self::OPT_LAST_CHECKED, current_time('mysql'));
    }

    /**
     * Ghi alert vào bảng wp_laca_project_alerts TRƯỚC KHI bắn thông báo —
     * trước đây chỉ gọi do_action('laca_project_alert_notify', ...) suông,
     * không lưu DB, nên sự cố site-down không hề xuất hiện trong danh sách
     * "Cảnh báo"/badge của hub, không resolve/theo dõi được, và nếu tất cả
     * kênh Email/Zalo/Telegram/Slack đều tắt hoặc lỗi tại đúng thời điểm đó
     * thì sự cố sẽ không để lại dấu vết nào trong wp-admin.
     */
    private function recordAlert(int $projectId, string $msg, string $level): void
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

        if ($alertId !== false) {
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

        // Thử lại 1 lần sau vài giây trước khi kết luận "down" — tránh báo
        // nhầm do mạng chập chờn nhất thời phía hub (không phải site khách
        // thật sự lỗi). Chạy trong cron/AJAX nền, không chặn người dùng thật.
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
            // Nhiều site khách dùng SSL tự ký hoặc SSL sắp hết hạn — không để
            // lỗi verify SSL bị tính nhầm thành "site down".
            'sslverify'   => false,
            'user-agent'  => 'LacaDev-Uptime-Monitor/1.0',
        ]);

        if (is_wp_error($response)) {
            return ['up' => false, 'code' => null, 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);

        // >=500 (lỗi server), 401/403 (site chặn bot/htaccess, WAF), 404
        // (live_url cấu hình sai, domain hết hạn về trang parking) đều là
        // dấu hiệu site không truy cập được bình thường.
        if ($code >= 500 || in_array($code, [401, 403, 404], true)) {
            return ['up' => false, 'code' => $code, 'message' => "HTTP {$code}"];
        }

        // HTTP 200 nhưng body rỗng hoàn toàn — đúng dạng lỗi "homepage empty
        // body" từng gặp (PHP fatal error bị chặn hiển thị ra ngoài).
        $body = wp_remote_retrieve_body($response);
        if ($code === 200 && trim(strip_tags($body)) === '') {
            return ['up' => false, 'code' => $code, 'message' => 'HTTP 200 nhưng nội dung trang rỗng'];
        }

        return ['up' => true, 'code' => $code, 'message' => 'OK'];
    }
}
