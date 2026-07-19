<?php

namespace App\Features\ProjectManagement;

/**
 * ProjectPaymentService
 *
 * Tự động tính toán và cập nhật payment_status sau khi lưu project.
 *
 * Logic:
 *   - pending  : chưa thanh toán gì
 *   - partial  : đã trả một phần
 *   - paid     : đã trả đủ hoặc vượt giá build
 *
 * QUAN TRỌNG: Dùng get_post_meta() raw, KHÔNG dùng carbon_get_post_meta()
 * vì CF có internal cache riêng và có thể trả về data cũ ngay trong cùng
 * request save. Hook phải chạy ở priority 9999 để CF đã save xong.
 */
class ProjectPaymentService
{
    public function register(): void
    {
        add_action('save_post_project', [$this, 'autoCalculate'], 9999, 2);
    }

    // -----------------------------------------------------------------------

    public function autoCalculate(int $postId, \WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'project') return;
        if (wp_is_post_revision($postId)) return;

        $totalBuild = $this->readBuildPrice($postId);
        $totalPaid  = $this->readTotalPaid($postId);
        $newStatus  = $this->resolveStatus($totalBuild, $totalPaid);

        $currentStatus = get_post_meta($postId, '_payment_status', true) ?: 'pending';

        if ($newStatus !== $currentStatus) {
            update_post_meta($postId, '_payment_status', $newStatus);
        }
    }

    // -----------------------------------------------------------------------
    // Public helpers (đọc số liệu — dùng chung cho autoCalculate() và
    // ClientPortalEndpoint để hiện tình trạng thanh toán cho khách)
    // -----------------------------------------------------------------------

    /**
     * Đọc giá build (CF lưu với prefix _ → meta key: _price_build).
     */
    public function readBuildPrice(int $postId): int
    {
        $raw = get_post_meta($postId, '_price_build', true);
        return (int) preg_replace('/[^0-9]/', '', (string) $raw);
    }

    /**
     * Tổng tiền đã thanh toán — CF lưu sub-field theo format PIPE đầy đủ:
     *   _payment_history|pay_amount|0|0|value, _payment_history|pay_amount|1|0|value, ...
     * (root|field|hàng thứ mấy|value_index|property — thiếu "|0|value" ở
     * cuối sẽ không bao giờ khớp key thật, khiến hàm này luôn trả về 0).
     */
    public function readTotalPaid(int $postId): int
    {
        $total = 0;
        for ($i = 0; $i < 100; $i++) {
            $metaKey = "_payment_history|pay_amount|{$i}|0|value";
            if (!metadata_exists('post', $postId, $metaKey)) {
                break;
            }
            $amt    = get_post_meta($postId, $metaKey, true);
            $total += (int) preg_replace('/[^0-9]/', '', (string) $amt);
        }
        return $total;
    }

    private function resolveStatus(int $totalBuild, int $totalPaid): string
    {
        if ($totalBuild <= 0) {
            return 'pending';
        }

        if ($totalPaid <= 0) {
            return 'pending';
        }

        if ($totalPaid < $totalBuild) {
            return 'partial';
        }

        return 'paid';
    }
}
