<?php

namespace App\Settings\LacaTools\Management;

/**
 * AdminUxService
 * Handles admin UX: merchant simplification and media submenu.
 * Extracted from ManagementExperience (lines 1592–1691).
 */
class AdminUxService
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerUnattachedMediaMenu']);
        add_action('admin_notices', [$this, 'renderUnattachedMediaNotice']);
        $this->simplifyMerchantAdmin();
    }

    /**
     * Registers a submenu under 'Media' for Unattached media.
     */
    public function registerUnattachedMediaMenu(): void
    {
        add_submenu_page(
            'upload.php',
            'Media Không Dùng',
            'Media Không Dùng',
            'manage_options',
            'upload.php?detached=1&mode=list'
        );
    }

    /**
     * Hiển thị giải thích ngắn ở đầu trang "Media Không Dùng" (upload.php?detached=1).
     */
    public function renderUnattachedMediaNotice(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'upload' || empty($_GET['detached'])) {
            return;
        }

        echo '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:14px 16px;margin:8px 0">'
            . '<p style="margin:0 0 8px;font-weight:600;color:#0369a1">🔧 Media Không Dùng</p>'
            . '<p style="margin:0;font-size:13px;color:#374151">Đây là danh sách các file (ảnh, tài liệu...) đã tải lên nhưng chưa được gắn vào bài viết/trang nào. '
            . 'Bạn có thể xoá bớt để giải phóng dung lượng lưu trữ, nhưng nên kiểm tra kỹ trước khi xoá vì một số file có thể vẫn đang được dùng ở nơi khác trên site.</p>'
            . '</div>';
    }

    /**
     * Simplifies the admin for non-developer roles (hides dev-only menus).
     */
    private function simplifyMerchantAdmin(): void
    {
        add_action('admin_head', function () {
            if (current_user_can('manage_options') && !in_array(wp_get_current_user()->user_login, ['lacadev'])) {
                echo '<style>
                    #toplevel_page_laca-admin { display: none !important; }
                    #menu-settings, #menu-tools, #menu-plugins { display: none !important; }
                    .update-nag, .notice-warning, .notice-info.is-dismissible { display: none !important; }
                    #contextual-help-link-wrap { display: none !important; }
                    #wp-admin-bar-updates, #wp-admin-bar-comments, #wp-admin-bar-new-content { display: none !important; }
                </style>';
            }
        });
    }
}
