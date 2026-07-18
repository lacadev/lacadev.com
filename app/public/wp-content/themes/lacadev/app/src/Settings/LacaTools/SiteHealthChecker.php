<?php

namespace App\Settings\LacaTools;

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

            $this->checkAndAlertOnChange((int) $projectId, $url);
        }
    }

    /**
     * Kiểm tra ngay sau 1 hành động vừa thực hiện trên site khách (push
     * block, remote update...) — mục đích là bắt lỗi NGAY nếu hành động vừa
     * làm vô tình làm site vỡ, nên LUÔN cảnh báo nếu fail, không so trạng
     * thái trước đó như kiểm tra định kỳ.
     */
    public function checkAfterDeploy(int $projectId, string $context): void
    {
        $url = trim((string) carbon_get_post_meta($projectId, 'live_url'));
        if (empty($url)) {
            return;
        }

        $result = $this->pingSite($url);

        if (!$result['up']) {
            do_action(
                'laca_project_alert_notify',
                $projectId,
                'critical',
                "🔥 Site có dấu hiệu bị lỗi ngay sau {$context}: {$result['message']}. Kiểm tra lại: {$url}"
            );
        }

        update_post_meta($projectId, self::OPT_LAST_STATUS, $result['up'] ? 'up' : 'down');
        update_post_meta($projectId, self::OPT_LAST_CHECKED, current_time('mysql'));
    }

    private function checkAndAlertOnChange(int $projectId, string $url): void
    {
        $result   = $this->pingSite($url);
        $newState = $result['up'] ? 'up' : 'down';
        $oldState = get_post_meta($projectId, self::OPT_LAST_STATUS, true) ?: 'up';

        if ($newState !== $oldState) {
            if ($newState === 'down') {
                do_action(
                    'laca_project_alert_notify',
                    $projectId,
                    'critical',
                    "🔴 Site khách có vẻ đang KHÔNG TRUY CẬP ĐƯỢC: {$result['message']} ({$url})"
                );
            } else {
                do_action(
                    'laca_project_alert_notify',
                    $projectId,
                    'info',
                    "✅ Site khách đã hoạt động trở lại: {$url}"
                );
            }
        }

        update_post_meta($projectId, self::OPT_LAST_STATUS, $newState);
        update_post_meta($projectId, self::OPT_LAST_CHECKED, current_time('mysql'));
    }

    /**
     * @return array{up:bool, code:?int, message:string}
     */
    public function pingSite(string $url): array
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

        if ($code >= 500) {
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
