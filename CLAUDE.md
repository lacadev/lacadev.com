# lacadev

Site hub quản lý dự án — theme `lacadev` chạy CPT `project`, CRM/dashboard `LacaProjectsHub`, và nhận báo cáo từ các site khách (spoke) như `lacadev-client`.

## Trước khi đụng vào tracker / cảnh báo / đồng bộ với site khách

Đọc `app/public/wp-content/themes/lacadev/doc/TRACKER_HUB_CLIENT_SYNC.md` trước — file này ghi lại toàn bộ kiến trúc kết nối 2 chiều với site khách (tracker log, remote-update, block sync), các thay đổi đã làm theo từng giai đoạn (P0/P1/P2), và hướng dẫn test. Không cần re-explore code từ đầu.

Trạng thái hiện tại: cả 3 giai đoạn (P0 nối cảnh báo real-time + field `ssl_expiry`; P1/P2 là thay đổi phía site khách) đều đã xong. Chỉ còn thao tác cấu hình + test thủ công (xem hướng dẫn test trong file trên).

Các class liên quan chính: `App\Settings\LacaTools\ProjectNotificationHandler` (fan-out Email/Zalo/Telegram/Slack), `App\Settings\LacaTools\TrackerEndpointHandler` (nhận `/laca/v1/tracker/log`), `App\Features\ProjectManagement\Api\ClientWebhook` (nhận `/laca/v1/client-report`), `App\Features\ProjectManagement\LacaProjectsHub` (dashboard CRM).
