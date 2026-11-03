# Cảnh báo tin cậy hơn + Cron health-check + Thanh toán trên Client Portal

> File này ghi lại 5 fix xuất phát từ báo cáo đánh giá P1 (góc nhìn khách hàng + quản lý đa năng): ghi log khi gửi thông báo thất bại, tự động refresh token Zalo OA, tự phát hiện cron bị trễ, hiện trạng thái thanh toán trên Client Portal, và dọn 1 đoạn code chết nguy hiểm. Đọc file này trước khi đụng lại vào các phần này — không cần re-explore code.

## 1. Ghi log khi gửi cảnh báo thất bại

**Vấn đề cũ:** `sendZaloMessage()`/`sendTelegramMessage()`/`sendSlackMessage()`/`wp_mail()` đều trả về `bool` nhưng bị bỏ qua hoàn toàn ở `ProjectNotificationHandler::sendNotifications()` — 1 token hết hạn hay webhook sai có thể "chết êm" nhiều tuần không ai biết.

**Đã sửa** (`app/src/Settings/LacaTools/ProjectNotificationHandler.php`):
- `sendZaloMessage()`/`sendTelegramMessage()`/`sendSlackMessage()` đổi return type từ `bool` sang `array{ok:bool,error:string}` (qua helper chung `toChannelResult()`).
- Email: bắt lỗi qua hook `wp_mail_failed` (WP tự bắn action này kèm `WP_Error` khi `wp_mail()` thất bại).
- Mỗi lần gửi (bất kể 4 kênh) đều gọi `recordChannelResult($channel, $ok, $error)`, ghi vào option `laca_notify_channel_status` = `['zalo' => ['ok'=>bool,'at'=>timestamp,'error'=>string], 'telegram'=>[...], 'slack'=>[...], 'email'=>[...]]`.

**Cách dùng / xem ở đâu:**
- Nếu 1 kênh đang bật (`enable_X_notify`) mà lần gửi gần nhất thất bại → tự hiện **1 khung đỏ ở đầu mọi trang wp-admin** (`renderChannelFailureNotice()`), kèm giờ thất bại + nội dung lỗi thật (HTTP code/response body hoặc `wp_mail_failed` message).
- Trang **Laca Projects → Dashboard** có thêm ô **"Kênh thông báo"** — hiện "Ổn định" (xanh) hoặc "N lỗi" (đỏ) tóm tắt nhanh.
- Muốn xem trạng thái thô: `get_option('laca_notify_channel_status')`.

**Test:** đổi tạm `slack_webhook_url` hoặc `telegram_bot_token` thành giá trị sai → trigger 1 cảnh báo thật (vd sửa ngày hết hạn SSL của 1 project thành 3 ngày tới, chạy cron `laca_project_manager_daily_cron` qua WP Crontrol "Run now") → xác nhận khung đỏ xuất hiện + tile Dashboard chuyển sang "N lỗi". Sửa lại giá trị đúng, gửi lại → xác nhận khung đỏ biến mất.

## 2. Tự động refresh Access Token Zalo OA

**Vấn đề cũ:** Field `zalo_oa_refresh_token` tồn tại trên UI nhưng không có code nào dùng tới — Access Token Zalo OA sống ~1h, hết hạn là kênh Zalo chết vĩnh viễn cho tới khi ai đó vào Zalo Developers lấy token mới dán tay.

**Đã sửa:**
- Thêm 2 field mới trong `Laca Admin → LacaDev PM & Bots → Zalo OA`: **App ID** và **App Secret Key** (`AdminSettings.php`) — đây là thông tin của **Zalo App** (đăng ký ở [developers.zalo.me](https://developers.zalo.me)), KHÁC với Access/Refresh Token của riêng OA.
- `ProjectNotificationHandler::refreshZaloToken()` — gọi `POST https://oauth.zaloapp.com/v4/oa/access_token` (header `secret_key` = App Secret, body `app_id`+`refresh_token`+`grant_type=refresh_token`), ghi token mới (cả access + refresh — Zalo xoay cả 2 mỗi lần refresh) và thời điểm hết hạn vào option `_zalo_oa_token_expires_at`.
- Trước mỗi lần gửi Zalo: nếu token đã/sắp hết hạn (buffer 5 phút) → tự refresh trước. Nếu vẫn gửi thất bại → refresh 1 lần rồi gửi lại đúng 1 lần (không lặp vô hạn).

**Cách dùng:**
1. Vào [developers.zalo.me](https://developers.zalo.me) → mở App đã đăng ký OA đang dùng → lấy **App ID** và **Secret Key** của App (không phải Access Token của OA).
2. Dán vào `Laca Admin → LacaDev PM & Bots → Zalo OA → App ID` / `App Secret Key` → Save.
3. Xong — từ giờ Access/Refresh Token của OA tự cập nhật, không cần vào lại Zalo Developers dán tay mỗi khi hết hạn (trừ khi Refresh Token cũng hết hạn — Zalo giới hạn ~3 tháng, lúc đó vẫn cần lấy token OA mới 1 lần).
4. Nếu chưa điền App ID/App Secret, hệ thống vẫn hoạt động như trước (gửi thất bại khi token hết hạn, hiện cảnh báo đỏ ở Mục 1) — không bắt buộc điền ngay.

**Test:** gọi `refreshZaloToken()` thủ công qua 1 trang debug tạm (hoặc chờ Access Token hiện tại hết hạn tự nhiên rồi gửi 1 cảnh báo thật) → xác nhận `_zalo_oa_access_token`/`_zalo_oa_refresh_token`/`_zalo_oa_token_expires_at` được ghi giá trị mới.

## 3. Tự phát hiện cron bị trễ (cả 2 theme)

**Vấn đề cũ:** WordPress pseudo-cron chỉ chạy khi có người truy cập site — site ít traffic có thể khiến cron im lặng nhiều ngày không ai biết. Thêm 1 bug nguy hiểm tìm thấy trong lúc sửa: `Security.php` (client) check `_disable_wp_cron === 'yes'` rồi gọi `$this->disableWpCron()` — method này **không tồn tại**, sẽ Fatal Error nếu option này từng được set (không có UI nào set được, nhưng vẫn là bom nổ chậm). **Đã xoá đoạn này.**

**Đã sửa:**
- Mỗi cron (`laca_project_manager_daily_cron` ở hub; `laca_tracker_hourly_scan`/`laca_tracker_daily_digest` ở client) tự ghi thời điểm chạy vào option `_laca_cron_last_run_*` ngay khi bắt đầu chạy.
- Nếu đã lên lịch (`wp_next_scheduled()`) nhưng lần chạy cuối trễ quá ngưỡng (3 ngày cho cron daily, 6h cho cron hourly) → hiện khung vàng cảnh báo ở đầu wp-admin.
- Trang `Laca Admin → 📡 Tracker` (client) có thêm ô **"Cron URL"** copy-được (`home_url('wp-cron.php?doing_wp_cron')`).

**Cách dùng (khi thấy cảnh báo cron trễ, hoặc chủ động phòng trước cho site ít traffic):**
1. Copy URL ở ô "Cron URL" (client) hoặc tự ghép `https://domain-site/wp-cron.php?doing_wp_cron` (hub — chưa có ô riêng, tự ghép tương tự).
2. Vào Cron Jobs của hosting (cPanel/DirectAdmin), thêm job gọi URL trên mỗi 15 phút bằng `wget -q -O /dev/null "..."` hoặc `curl -s -o /dev/null "..."`.
3. Chỉ sau khi xác nhận cảnh báo "cron trễ" đã biến mất, mới thêm `define('DISABLE_WP_CRON', true);` vào `wp-config.php` để tắt pseudo-cron — tránh chạy trùng 2 lần trước khi chắc chắn cron thật hoạt động.
4. Chi tiết đầy đủ + lựa chọn WP-CLI nâng cao: xem `doc/TRACKER_HUB_CLIENT_SYNC.md` mục "Cron hệ thống thật cho site ít traffic".

**Test:** tạm sửa `_laca_cron_last_run_daily` (hoặc `_hourly`) thành 1 timestamp cũ (`update_option('_laca_cron_last_run_daily', time() - 4*DAY_IN_SECONDS)`) → F5 wp-admin, xác nhận khung vàng hiện đúng số giờ trễ → xoá/sửa lại option để xác nhận khung biến mất.

## 4. Hiện trạng thái thanh toán trên Client Portal

**Vấn đề cũ:** `payment_status`/`payment_history`/`price_build` đã có sẵn trong CPT `project` nhưng chưa từng xuất hiện trên Client Portal — khách phải hỏi qua tin nhắn mới biết đã trả bao nhiêu, còn nợ bao nhiêu.

**Đã sửa:**
- `ProjectPaymentService::readBuildPrice()`/`readTotalPaid()` đổi từ `private` sang `public` (logic giữ nguyên — đọc raw `get_post_meta()`, không dùng `carbon_get_post_meta()` vì cache stale, xem docblock trong file).
- `ClientPortalEndpoint::buildProjectData()` thêm khối `payment` vào response: `{status, status_label, total, paid, remaining}`. **Chủ động không** đưa `finance_note` (ghi chú nội bộ), `invoice_file`, hay `pay_note` từng dòng ra ngoài.
- `template-client-portal.php` thêm section "Thanh toán" (chỉ hiện nếu `price_build > 0`) — 3 ô Tổng giá trị / Đã thanh toán / Còn lại + badge trạng thái, tái dùng đúng CSS class `cp-stat-grid`/`cp-stat`/`cp-section-badge` đã có.

**Cách dùng:** không cần làm gì thêm — tự động hiện trên Client Portal của mọi project đã có `price_build` > 0. Nếu dự án chưa nhập giá build, section này tự ẩn (không hiện "0đ" gây hiểu nhầm).

**Test:** mở link Client Portal của 1 project đã điền `price_build` + ít nhất 1 dòng `payment_history` → xác nhận số liệu khớp với dữ liệu trong tab "Tài chính" khi sửa project. Gọi trực tiếp `curl "https://domain-hub/wp-json/laca/v1/portal/project?key=<secret_key_hoặc_alias>"` → xác nhận JSON có khối `payment` nhưng **không** có `finance_note`/`invoice_file`/`pay_note`.

## 5. Event Bus thông báo hợp nhất (NotificationBus)

**Vấn đề cũ:** Các sự kiện thông báo (cảnh báo dự án, cron trễ, sao lưu quá hạn, deploy) được gọi rời rạc qua nhiều kênh khác nhau, không có cơ chế khử trùng lặp (dedup) tập trung và khó kiểm soát kênh nhận thông báo cho từng loại sự kiện.

**Đã sửa** (`app/src/Notifications/NotificationBus.php`):
- `NotificationBus::emit(string $event, array $payload, ?int $projectId)`: Điểm vào duy nhất cho mọi thông báo hệ thống.
- Bảng định tuyến (`laca_notification_routes`): Cấu hình kênh nhận (Email, Zalo, Telegram, Slack, Discord) cho từng sự kiện trong `Laca Admin → LacaDev PM & Bots → Định tuyến thông báo`.
- Tự động khử trùng lặp (dedup) theo transient với thời gian cooldown cấu hình riêng cho từng loại sự kiện (project_alert: 30p, cron_late: 12h, backup_outdated: 24h, uptime: 30p, deployment: 5p).
- Kênh Discord tích hợp chặt chẽ với DiscordHub (`Notifier::send()`), các kênh Email/Zalo/Telegram/Slack gọi qua `ProjectNotificationHandler::sendNotificationsFiltered()`.
- Bỏ trùng thông báo "cron trễ": DiscordHub `CronHealthMonitor` phát sự kiện `cron_late` qua bus thay vì tự gửi tin trực tiếp.

### Bảng định tuyến mặc định (DEFAULT_ROUTES)
Bảo đảm tính tương thích ngược hoàn toàn với hệ thống thông báo trước Phase 10:

| Sự kiện | Hằng sự kiện | Kênh nhận mặc định | Tương thích ngược |
|---|---|---|---|
| Cảnh báo dự án | `EVENT_PROJECT_ALERT` | Email, Zalo, Telegram, Slack, Discord | Tương thích 100% `handleRealtimeAlert` |
| Cron hệ thống trễ | `EVENT_CRON_LATE` | Discord | Tương thích `CronHealthMonitor` (chỉ Discord) |
| Sao lưu quá hạn | `EVENT_BACKUP_OUTDATED` | Email, Telegram, Discord | Cảnh báo sao lưu hằng ngày |
| Website không truy cập được | `EVENT_UPTIME_DOWN` | Email, Zalo, Telegram, Slack, Discord | Tương thích 100% `handleRealtimeAlert` |
| Website hoạt động trở lại | `EVENT_UPTIME_UP` | Email, Zalo, Telegram, Slack, Discord | Tương thích 100% `handleRealtimeAlert` |
| Cập nhật từ xa thành công | `EVENT_DEPLOYMENT` | Slack, Discord | Thông báo devops |

## 6. Giám sát Uptime song song & nhật ký phản hồi

**Vấn đề cũ:** Uptime trước đây quét tuần tự từng site bằng `wp_remote_get()` và chỉ lưu trạng thái hiện tại (`up`/`down`) mà không có lịch sử đo độ trễ (response time ms), dễ nghẽn khi số lượng website khách tăng lớn.

**Đã sửa** (`app/src/Settings/LacaTools/SiteHealthChecker.php` & `app/src/Databases/UptimeLogTable.php`):
- Sử dụng `WpOrg\Requests\Requests::request_multiple()` (hoặc `Requests::request_multiple` fallback) kiểm tra song song theo từng lô 10 website, timeout 10s.
- Hỗ trợ tuỳ chọn kiểm tra chứng chỉ SSL riêng từng project (`uptime_sslverify`, mặc định bật).
- Lưu kết quả chi tiết (trạng thái, mã HTTP, `response_ms`, lỗi) vào custom table `wp_laca_uptime_log`. Tự động dọn dẹp (purge) log cũ hơn 30 ngày.
- Cảnh báo lỗi thông minh: Chỉ phát cảnh báo critical khi website bị lỗi **2 lần kiểm tra liên tiếp**, tránh báo động giả khi mạng chập chờn nhất thời; tự động phát cảnh báo phục hồi khi site hoạt động trở lại sau sự cố.
- Hiển thị tỷ lệ uptime và độ trễ phản hồi (ms) trung bình 24h trên cột Uptime trang **Fleet** và widget Uptime Monitor trong màn hình chi tiết dự án.

---

## Tổng hợp file đã sửa

| Theme | File | Mục |
|---|---|---|
| hub | `app/src/Settings/LacaTools/ProjectNotificationHandler.php` | 1, 2, 3, 5 |
| hub | `app/src/Notifications/NotificationBus.php` | 5 |
| hub | `app/src/Databases/UptimeLogTable.php` | 6 |
| hub | `app/src/Settings/LacaTools/SiteHealthChecker.php` | 6 |
| hub | `app/src/Features/DiscordHub/Monitors/CronHealthMonitor.php` | 5 |
| hub | `app/src/Features/ProjectManagement/ProjectFields.php` | 6 |
| hub | `app/src/Features/ProjectManagement/FleetPage.php` | 6 |
| hub | `app/src/Features/ProjectManagement/Views/meta-box-col1.php` | 6 |
| hub | `app/src/Settings/AdminSettings.php` | 2, 5 |
| hub | `app/src/Features/ProjectManagement/LacaProjectsHub.php` | 1 |
| hub | `app/src/Features/ProjectManagement/ProjectPaymentService.php` | 4 |
| hub | `app/src/Settings/LacaTools/ClientPortalEndpoint.php` | 4 |
| hub | `theme/page_templates/template-client-portal.php` | 4 |
| hub | `doc/PROJECT_MANAGER_GUIDE.md` | 1–4 (hướng dẫn dùng), sửa tham chiếu file sai |
| hub | `doc/DEPLOY_SECURITY_PERFORMANCE_CHECKLIST.md` | 3 |
| client | `app/src/Settings/LacaTools/Security.php` | 3 (dọn dead code) |
| client | `app/src/Settings/LacaDevTrackerClient.php` | 3 |
| client | `app/src/Settings/AdminSettings.php` | 3 |
| cả 2 theme | `doc/TRACKER_HUB_CLIENT_SYNC.md` | 3 |
