<?php

namespace App\Features\ProjectManagement;

use App\Helpers\Crypto;
use App\Models\ProjectLog;

/**
 * ProjectKeyManager
 *
 * Quản lý 4 khóa riêng biệt theo mục đích cho mỗi dự án (Hub - M):
 *   1. _laca_key_tracker:   Dành riêng cho Tracker logs (Client -> Hub)
 *   2. _laca_key_catalog:   Dành cho Block Catalog & Sync (Client <-> Hub)
 *   3. _laca_key_remote:    Dành cho Remote Update lệnh bảo trì (Hub -> Client)
 *   4. _laca_key_portal:    Dành cho Client Portal truy cập công khai
 *
 * Toàn bộ khóa được lưu mã hoá qua Crypto (AES-256-CBC với AUTH_KEY).
 * Dự án được định danh công khai bằng `_laca_public_id` (không lộ ID DB).
 */
class ProjectKeyManager
{
    public const KEY_TRACKER = 'tracker';
    public const KEY_CATALOG = 'catalog';
    public const KEY_REMOTE  = 'remote';
    public const KEY_PORTAL  = 'portal';

    public const META_PUBLIC_ID = '_laca_public_id';
    public const META_KEYS = [
        self::KEY_TRACKER => '_laca_key_tracker',
        self::KEY_CATALOG => '_laca_key_catalog',
        self::KEY_REMOTE  => '_laca_key_remote',
        self::KEY_PORTAL  => '_laca_key_portal',
    ];

    /**
     * Lấy Public ID của dự án (tạo mới nếu chưa có)
     */
    public static function getPublicId(int $projectId): string
    {
        $publicId = (string) get_post_meta($projectId, self::META_PUBLIC_ID, true);
        if ($publicId === '') {
            // Ưu tiên dùng _portal_alias nếu có, nếu không sinh chuỗi ngẫu nhiên
            $alias = (string) get_post_meta($projectId, '_portal_alias', true);
            $publicId = $alias ?: ('proj_' . bin2hex(random_bytes(12)));
            update_post_meta($projectId, self::META_PUBLIC_ID, $publicId);
        }
        return $publicId;
    }

    /**
     * Tìm Project ID từ Public ID (hoặc portal alias)
     */
    public static function findProjectIdByPublicId(string $publicId): ?int
    {
        global $wpdb;

        if (empty($publicId)) {
            return null;
        }

        $cacheKey = 'laca_proj_pub_' . md5($publicId);
        $cached   = wp_cache_get($cacheKey, 'laca_keys');
        if ($cached !== false) {
            return (int) $cached ?: null;
        }

        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_status = 'publish'
             LIMIT 1",
            self::META_PUBLIC_ID,
            $publicId
        ));

        // Fallback tìm theo _portal_alias
        if (!$id) {
            $id = $wpdb->get_var($wpdb->prepare(
                "SELECT pm.post_id FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_portal_alias' AND pm.meta_value = %s AND p.post_status = 'publish'
                 LIMIT 1",
                $publicId
            ));
        }

        $projectId = $id ? (int) $id : null;
        wp_cache_set($cacheKey, $projectId ?: 0, 'laca_keys', 300);

        return $projectId;
    }

    /**
     * Lấy giá trị giải mã của một key theo loại
     */
    public static function getKey(int $projectId, string $type): string
    {
        $metaKey = self::META_KEYS[$type] ?? '';
        if (!$metaKey) {
            return '';
        }

        $stored = (string) get_post_meta($projectId, $metaKey, true);

        // Fallback cho key tracker: lấy _tracker_secret_key cũ nếu chưa có key mới
        if ($stored === '' && $type === self::KEY_TRACKER) {
            $oldKey = (string) get_post_meta($projectId, '_tracker_secret_key', true);
            if ($oldKey !== '') {
                self::setKey($projectId, self::KEY_TRACKER, $oldKey);
                return $oldKey;
            }
        }

        if ($stored === '') {
            return '';
        }

        // Nếu đã mã hoá thì giải mã + lazy rehash sang AEAD v2
        if (Crypto::isEncrypted($stored)) {
            $decrypted = Crypto::decrypt($stored);
            if ($decrypted === '') {
                error_log(sprintf('[laca-crypto] Failed to decrypt key meta %s for project #%d.', $metaKey, $projectId));
                return '';
            }

            if (Crypto::needsRehash($stored)) {
                self::setKey($projectId, $type, $decrypted);
            }

            return $decrypted;
        }

        // Dữ liệu cũ chưa mã hoá → tự động mã hoá sang v2
        self::setKey($projectId, $type, $stored);
        return $stored;
    }

    /**
     * Lưu key đã được mã hoá
     */
    public static function setKey(int $projectId, string $type, string $plainKey): void
    {
        $metaKey = self::META_KEYS[$type] ?? '';
        if (!$metaKey || $plainKey === '') {
            return;
        }

        $encrypted = Crypto::encrypt($plainKey);
        if ($encrypted === '') {
            return;
        }

        update_post_meta($projectId, $metaKey, $encrypted);

        // Riêng portal key: lưu thêm hash sha256 để tìm kiếm O(1) không cần quét toàn bộ DB
        if ($type === self::KEY_PORTAL) {
            update_post_meta($projectId, '_laca_key_portal_hash', hash('sha256', $plainKey));
        }
    }

    /**
     * Đảm bảo đủ 4 key cho một dự án (khởi tạo lần đầu nếu chưa có)
     *
     * @return array<string,string> Mảng các plain keys
     */
    public static function ensureKeys(int $projectId): array
    {
        $keys = [];
        self::getPublicId($projectId);

        foreach (self::META_KEYS as $type => $metaKey) {
            $val = self::getKey($projectId, $type);
            if ($val === '') {
                $rawStored = (string) get_post_meta($projectId, $metaKey, true);
                if ($rawStored !== '') {
                    // Đã có dữ liệu mã hoá nhưng giải mã lỗi → KHÔNG tự sinh đè làm đứt kết nối site khách
                    $keys[$type] = '';
                    continue;
                }

                if ($type === self::KEY_TRACKER) {
                    $old = (string) get_post_meta($projectId, '_tracker_secret_key', true);
                    $val = $old ?: wp_generate_password(48, false);
                } else {
                    $val = wp_generate_password(48, false);
                }
                self::setKey($projectId, $type, $val);
            }
            $keys[$type] = $val;
        }

        return $keys;
    }

    /**
     * Tạo lại 1 key và ghi nhật ký ProjectLog
     */
    public static function regenerateKey(int $projectId, string $type, ?int $userId = null): string
    {
        if (!isset(self::META_KEYS[$type])) {
            return '';
        }

        $newKey = wp_generate_password(48, false);
        self::setKey($projectId, $type, $newKey);

        $labels = [
            self::KEY_TRACKER => 'Tracker Key',
            self::KEY_CATALOG => 'Block Catalog Key',
            self::KEY_REMOTE  => 'Remote Update Key',
            self::KEY_PORTAL  => 'Portal Key',
        ];

        $label = $labels[$type] ?? $type;
        $userName = $userId ? get_userdata($userId)?->display_name : 'Admin';

        ProjectLog::add([
            'project_id'  => $projectId,
            'log_content' => "🔑 Đã tạo lại {$label} (bởi {$userName})",
            'log_type'    => 'note',
            'log_by'      => $userName,
            'meta'        => ['key_type' => $type, 'user_id' => $userId],
        ]);

        return $newKey;
    }

    /**
     * Tìm Project từ Portal Key hoặc Alias
     */
    public static function findProjectIdByPortalKey(string $key): ?int
    {
        global $wpdb;

        if (empty($key)) {
            return null;
        }

        // 1. Tìm theo _portal_alias
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_portal_alias' AND pm.meta_value = %s AND p.post_status = 'publish'
             LIMIT 1",
            $key
        ));

        // 2. Tìm theo sha256 hash của _laca_key_portal
        if (!$id) {
            $hash = hash('sha256', $key);
            $id = $wpdb->get_var($wpdb->prepare(
                "SELECT pm.post_id FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_laca_key_portal_hash' AND pm.meta_value = %s AND p.post_status = 'publish'
                 LIMIT 1",
                $hash
            ));
        }

        return $id ? (int) $id : null;
    }

    /**
     * Xuất khối JSON cấu hình chuẩn cho site khách hàng
     */
    public static function getClientConfigPayload(int $projectId): array
    {
        $keys = self::ensureKeys($projectId);

        return [
            'hub_url'    => home_url(),
            'endpoint'   => rest_url('laca/v1/tracker/log'),
            'project_id' => self::getPublicId($projectId),
            'keys'       => [
                'tracker' => $keys[self::KEY_TRACKER],
                'catalog' => $keys[self::KEY_CATALOG],
                'remote'  => $keys[self::KEY_REMOTE],
                'portal'  => $keys[self::KEY_PORTAL],
            ],
        ];
    }

    /**
     * Đăng ký AJAX hooks cho ProjectKeyManager
     */
    public static function registerAjax(): void
    {
        add_action('wp_ajax_laca_regenerate_project_key', [self::class, 'handleAjaxRegenerate']);
    }

    /**
     * Xử lý AJAX tạo lại 1 key cho project
     */
    public static function handleAjaxRegenerate(): void
    {
        check_ajax_referer('laca_project_manager', 'nonce');

        $projectId = absint($_POST['project_id'] ?? 0);
        if (!$projectId || !current_user_can('edit_post', $projectId)) {
            wp_send_json_error(['message' => __('Không có quyền thực hiện.', 'laca')]);
        }

        $type = sanitize_key($_POST['key_type'] ?? '');
        if (!isset(self::META_KEYS[$type])) {
            wp_send_json_error(['message' => __('Loại key không hợp lệ.', 'laca')]);
        }

        $newKey  = self::regenerateKey($projectId, $type, get_current_user_id());
        $payload = self::getClientConfigPayload($projectId);

        wp_send_json_success([
            'message'        => __('Đã tạo lại key thành công.', 'laca'),
            'key_type'       => $type,
            'new_key'        => $newKey,
            'config_payload' => wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }
}
