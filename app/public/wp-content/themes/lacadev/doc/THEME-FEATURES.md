# LacaDev Theme — Danh sách chức năng đầy đủ

> Cập nhật: 2026-04-08  
> Namespace gốc: `App\` | Carbon Fields | WP Emerge

---

## Mục lục

1. [Laca Admin — Cài đặt tổng](#1-laca-admin--cài-đặt-tổng)
2. [Tools > Optimization](#2-tools--optimization)
3. [Tools > Security](#3-tools--security)
4. [Bảo mật nâng cao (Security Manager)](#4-bảo-mật-nâng-cao-security-manager)
5. [Quản lý dự án (Project Manager)](#5-quản-lý-dự-án-project-manager)
6. [AI Features](#6-ai-features)
7. [Form liên hệ](#7-form-liên-hệ)
8. [Email Log](#8-email-log)
9. [Dọn dẹp Database](#9-dọn-dẹp-database)
10. [Custom Post Types động](#10-custom-post-types-động)
11. [Exit Intent Popup](#11-exit-intent-popup)
12. [Maintenance Mode](#12-maintenance-mode)
13. [Frontend Features](#13-frontend-features)
14. [Admin Dashboard & UX](#14-admin-dashboard--ux)
15. [Database Tables tùy chỉnh](#15-database-tables-tùy-chỉnh)
16. [Built-in Post Types](#16-built-in-post-types)

---

## 1. Laca Admin — Cài đặt tổng

**File:** `app/src/Settings/AdminSettings.php`  
**Menu:** Laca Admin (top-level, position 3)

Trang cài đặt chính của theme, đăng ký qua Carbon Fields. Chia thành nhiều sub-container:

| Sub-page | Slug | Nội dung |
|----------|------|----------|
| Laca Admin | `laca-admin` | Màu sắc admin (primary, secondary, bg, text), bật maintenance, ẩn theme editor |
| Tools | `laca-tools` | Optimization + Security toggles (xem mục 2 & 3) |
| Google reCAPTCHA | `laca-recaptcha` | Site Key, Secret Key, cấu hình cho login/register/comment |
| LacaDev PM & Bots | `laca-project-notifications` | Cài đặt Zalo Bot, email thông báo dự án |
| Login Socials | `laca-login-socials` | OAuth (Google, Facebook) |
| Quản trị & HD Sử dụng | `laca-management-settings` | Cài đặt quản trị nội bộ |

**Chức năng luôn chạy (không cần bật):**
- Inject CSS variables màu sắc vào `<head>` admin
- Thêm custom file extensions cho Media upload (ac3, mpa, flv, svg)
- Auto resize ảnh gốc sau khi upload
- Đổi tên file upload tự động
- Ẩn menu/tính năng không cần thiết với user thường (non-super user)

---

## 2. Tools > Optimization

**File:** `app/src/Settings/LacaTools/Optimize.php`  
**Menu:** Laca Admin → Tools → tab Optimization  
**Kích hoạt:** Hook `carbon_fields_fields_registered`

### Disable unnecessary items

| Tùy chọn | Tác dụng |
|----------|----------|
| **Disable jQuery Migrate** | Xóa `jquery-migrate.js` (~8KB) khỏi frontend |
| **Disable Gutenberg CSS** | Xóa `wp-block-library.css` + `wp-block-library-theme.css` (~20KB) toàn site |
| **Disable Classic CSS** | Xóa `classic-theme-styles.css` (~8KB) toàn site |
| **Disable Emoji** | Xóa toàn bộ script/style/filter emoji WordPress |

### Optimization Library

| Tùy chọn | Tác dụng |
|----------|----------|
| **Enable Instant-page** | Prefetch trang khi hover link → click tức thì. Load từ `/dist/instantpage.js` |
| **Enable Smooth-scroll** | Cuộn mượt khi click anchor link. Load từ `/dist/smooth-scroll.min.js` |

### Image & Output Optimization

| Tùy chọn | Tác dụng |
|----------|----------|
| **Remove HTML comments** | Xóa `<!-- ... -->` khỏi HTML output (giữ IE conditionals) |
| **Bật Advanced Resource Hints** | Thêm `preconnect` + `dns-prefetch` cho Google Fonts vào `<head>` |
| **Tối ưu hóa thuộc tính ảnh** | Tự động thêm `loading="lazy"` và điền `alt` cho ảnh qua `wp_get_attachment_image()` |
| **Tối ưu hóa ảnh trong nội dung** | Tự động thêm `loading="lazy"` vào mọi `<img>` trong `the_content` |
| **Bật Service Worker cache** | Đăng ký `/dist/sw.js` để cache tài nguyên tĩnh, hỗ trợ offline cơ bản |

---

## 3. Tools > Security

**File:** `app/src/Settings/LacaTools/Security.php`  
**Menu:** Laca Admin → Tools → tab Security  
**Kích hoạt:** Hook `carbon_fields_fields_registered`

### Tắt tính năng có nguy cơ bảo mật

| Tùy chọn | Tác dụng |
|----------|----------|
| **Disable REST API (cho khách)** | Chặn `/wp-json/` với user chưa đăng nhập, trả 401 |
| **Disable XML-RPC** | Tắt hoàn toàn `/xmlrpc.php` — ngăn brute force và DDoS amplification |
| **Disable WP-Embed (oEmbed)** | Tắt tính năng cho site khác nhúng bài viết của bạn, xóa `wp-embed.js` |
| **Disable X-Pingback header** | Xóa header `X-Pingback` khỏi mọi response HTTP |

### WordPress Hardening

| Tùy chọn | Tác dụng |
|----------|----------|
| **Ẩn thông tin WordPress** | Xóa generator tag, RSD link, wlwmanifest, REST link header, adjacent posts links khỏi `<head>` |

### Tối ưu Server & Database

| Tùy chọn | Tác dụng |
|----------|----------|
| **Giới hạn Post Revision & Autosave** | Giữ tối đa 3 revision, autosave 5 phút (thay vì 60 giây) |
| **Tối ưu PHP Memory** | Tăng memory limit lên 256MB, bật PHP garbage collection |

---

## 4. Bảo mật nâng cao (Security Manager)

**Menu:** Laca Admin → Bảo mật  
**Files:** `app/src/Settings/Security/`

Trang admin tổng hợp với 6 tab:

### Tab 1 — Kiểm tra bảo mật (Security Audit)
**File:** `SecurityAudit.php`  
Chạy 20+ kiểm tra và cho điểm 0–100%:
- WordPress core & PHP version
- WP_DEBUG tắt/bật, SSL, file editor
- Admin username = "admin" hay không
- Database prefix mặc định (`wp_`)
- Custom login URL, 2FA có bật không
- REST API, XML-RPC, X-Pingback
- HTTP security headers: X-Frame-Options, X-Content-Type-Options, HSTS, CSP, Referrer-Policy
- AJAX: `wp_ajax_laca_security_audit`

### Tab 2 — Giám sát file (File Integrity Monitor)
**File:** `FileIntegrityMonitor.php`  
- Tạo baseline MD5 hash cho toàn bộ file `.php`, `.js`, `.json`, `.htaccess`, `.sh`
- So sánh với baseline → phát hiện file bị sửa / thêm mới / xóa
- Lưu baseline vào `wp_options` (`laca_file_baseline`, `laca_file_baseline_time`)
- Bỏ qua: `uploads/`, `cache/`, `backups/`, `node_modules/`
- AJAX: `laca_fim_scan`, `laca_fim_update_baseline`

### Tab 3 — Quét mã độc (Malware Scanner)
**File:** `MalwareScanner.php`  
- Quét file `.php`, `.phtml`, `.js`, `.html`, `.htm`, `.svg` (tối đa 2MB/file)
- 16+ pattern heuristic phát hiện:
  - `eval(base64_decode(...))` — backdoor (score 8)
  - `eval(gzinflate(...))` — obfuscation nhiều lớp (8)
  - `preg_replace` với flag `/e` — thực thi code (7)
  - `include()` từ URL bên ngoài (7)
  - `system()` / `exec()` / `shell_exec()` kết hợp `$_GET`/`$_POST` (9)
  - Base64 string dài bất thường (5)
  - `file_put_contents` → file `.php` (5)
  - JS: `document.write(atob(...))`, `unescape()` (5)
- Quét theo chunk (AJAX nhiều bước), không timeout
- AJAX: `laca_malware_init`, `laca_malware_chunk`, `laca_malware_result`

### Tab 4 — User ẩn (Hidden User Scanner)
**File:** `HiddenUserScanner.php`  
- So sánh users từ DB trực tiếp vs `get_users()` vs `WP_User_Query`
- Phát hiện tài khoản admin ẩn, tài khoản tạo từ kênh bất thường
- AJAX: `laca_hidden_user_scan`

### Tab 5 — URL đăng nhập tùy chỉnh (Custom Login)
**File:** `CustomLoginManager.php`  
- Ẩn `/wp-login.php` → trả 404 với user chưa đăng nhập
- Phục vụ login qua slug tùy chỉnh (vd: `/dang-nhap`)
- Filter `site_url` và `network_site_url` để thay thế URL login
- Filter `wp_redirect` để không lộ slug
- AJAX: `laca_save_login_settings`
- Options: `laca_login_slug`, `laca_enable_custom_login`

### Tab 6 — Xác thực 2 bước (2FA TOTP)
**File:** `TwoFactorAuth.php`  
- Tương thích Google Authenticator (RFC 4226 TOTP)
- Mã 6 chữ số, window 30 giây, drift ±1
- Backup codes: 10 mã dùng 1 lần
- Flow đăng nhập: sau khi nhập đúng mật khẩu → màn hình nhập OTP
- UI trong User Profile để bật/tắt, quét QR code
- AJAX: `laca_2fa_get_secret`, `laca_2fa_verify_setup`, `laca_2fa_disable`, `laca_2fa_regen_backup`
- User meta: `laca_2fa_enabled`, `laca_2fa_secret`, `laca_2fa_backup_codes`
- Option: `laca_2fa_master_enabled`

---

## 5. Quản lý dự án (Project Manager)

**CPT:** `project` | **Menu:** Quản lý dự án  
**Files:** `app/src/PostTypes/project.php`, `app/src/Features/ProjectManagement/`

Hệ thống quản lý dự án web agency đầy đủ:

### Thông tin dự án (Carbon Fields)
- Thông tin khách hàng: tên, SĐT, email
- Thông số kỹ thuật: nền tảng, page builder, features
- Tài chính: giá trị hợp đồng, đã thanh toán, trạng thái
- Theo dõi domain/hosting/SSL hết hạn
- Màu sắc thương hiệu (brand colors)
- Cài đặt client portal

### Cột danh sách (ProjectAdminColumns)
- Thumbnail, trạng thái, ngày hết hạn, số alerts, tên khách hàng, domain

### Logs & Alerts (AJAX)
- Thêm/xóa/resolve log cho từng dự án
- 4 loại log: deployment, security, warning, info
- Hệ thống alert tự động khi sắp hết hạn
- AJAX: `laca_*_log`, `laca_*_task`, `laca_remote_*`

### Tracker (Nhận log từ site client)
**File:** `TrackerEndpointHandler.php`  
- REST endpoint: `POST /wp-json/laca/v1/tracker/log`
- Xác thực bằng secret key (64-char hex, tự sinh)
- Nhận log: deployment, security event, plugin updates, warnings
- Tự tạo alert khi có event quan trọng

### Client Portal (API đọc dự án)
**File:** `ClientPortalEndpoint.php`  
- REST endpoint: `GET /wp-json/laca/v1/portal/project?key=SECRET`
- Không cần đăng nhập WordPress
- Trả về thông tin dự án, logs, alerts cho client portal

### Xuất báo giá PDF (ProjectPdfExporter)
- Meta box "📄 Báo giá / Invoice" trên trang edit project
- Sinh HTML báo giá chuẩn A4 → browser print → PDF
- Trigger qua query param `laca_export_quote`

### Thông báo tự động (ProjectNotificationHandler)
- Cron hàng ngày: `laca_project_manager_daily_cron`
- Kiểm tra domain/hosting/SSL sắp hết hạn (<30 ngày, SSL <14 ngày)
- Gửi email + Zalo Bot thông báo

### Báo cáo thống kê (ProjectReportsManager)
- Dashboard widget "📊 Thống kê Dự án"
- Chart.js: doughnut (theo trạng thái) + bar (theo tháng, 12 tháng gần nhất)

---

## 6. AI Features

### AI Chat (AIChatHandler)
**File:** `app/src/Settings/LacaTools/AIChatHandler.php`  
- REST endpoint: `POST /wp-json/laca/v1/ai/chat`
- Yêu cầu: đăng nhập + quyền `edit_posts`
- Nhận: `message`, `post_id` (optional), `context` (optional)
- Hỗ trợ multi-provider: **Gemini, Groq, OpenAI, Anthropic, DeepSeek**
- Frontend: floating chat button trong admin (`/resources/scripts/admin/ai-chat.js`)

### AI Dịch thuật (AITranslationManager)
**File:** `app/src/Settings/LacaTools/AITranslationManager.php`  
- Dịch toàn bộ bài viết sang ngôn ngữ đích (tích hợp Polylang)
- Dịch: `post_title`, `post_content`, `post_excerpt`
- Dịch SEO meta: Yoast / Rank Math / SEOPress
- Dịch từng block Gutenberg qua AJAX: `lacadev_ai_translate_block`
- Nút "Dịch với AI" xuất hiện trực tiếp trong Block Editor
- Admin action: `admin_post_lacadev_ai_translate`

---

## 7. Form liên hệ

**File:** `app/src/Features/ContactForm/`  
**Menu:** Laca Admin → Form Liên Hệ  
**Shortcode:** `[laca_contact_form id="X"]`

### Quản lý form (ContactFormManager)
- Tạo/sửa/xóa form qua giao diện kéo thả
- Layout dạng lưới 12 cột (span 3, 4, 6, 8, 12)
- 13 loại field: text, textarea, email, phone, number, select, multiselect, radio, checkbox, date, datetime, url, hidden
- Xem submissions theo từng form
- Export submissions ra CSV
- Admin actions: `laca_cf_save`, `laca_cf_delete`, `laca_cf_delete_submission`, `laca_cf_mark_read`, `laca_cf_export_csv`

### Xử lý submission (ContactFormAjaxHandler)
- AJAX: `laca_contact_submit` (cả logged-in và public)
- Validate từng field theo type (email format, phone format, required...)
- Lưu vào DB + gửi email thông báo
- Client-side validation: Pristine.js
- Bảo vệ: nonce verification, IP logging

### Email thông báo (ContactFormEmailService)
- Gửi email đến địa chỉ cấu hình khi có submission mới
- Template email tùy chỉnh

---

## 8. Email Log

**File:** `app/src/Settings/EmailLog/EmailLogManager.php`  
**Menu:** Laca Admin → Email Log

- Bắt intercepte mọi `wp_mail()` qua filter (priority 999)
- Lưu vào bảng `wp_laca_email_logs`: to_email, subject, source, status, sent_at
- Tự phát hiện nguồn gửi: `contact-form`, `project-alert`, `woocommerce`, `wordpress`
- Giao diện xem danh sách, phân trang 30/trang, lọc theo status
- Tự động xóa log cũ hơn 90 ngày

---

## 9. Dọn dẹp Database

**File:** `app/src/Settings/LacaTools/Management/DatabaseCleaner.php`  
**Menu:** Laca Admin → Dọn dẹp DB

Dọn dẹp dữ liệu rác WordPress 1 click:

| Hạng mục | Mô tả |
|----------|-------|
| Post revisions cũ | Giữ 3 bản mới nhất/post, xóa phần còn lại |
| Auto-drafts | Xóa auto-draft không cần thiết |
| Trashed posts | Xóa bài viết đã trash |
| Orphaned post meta | Xóa meta không còn post cha |
| Expired transients | Xóa transient hết hạn trong DB |
| Spam & trashed comments | Xóa comment spam và trong thùng rác |

- AJAX: `laca_db_clean`, `laca_db_analyze` (phân tích trước khi xóa)

---

## 10. Custom Post Types động

**Files:** `app/src/Features/DynamicCPT/`  
**Menu:** Laca Admin → Custom Post Types

### DynamicCptAdminPage
- Giao diện tạo/sửa/xóa CPT
- Cấu hình: slug, tên số ít/nhiều, icon Dashicons, supports (title, editor, thumbnail...)
- Lưu config vào `laca_dynamic_cpts` (JSON trong wp_options)

### DynamicCptManager
- Đọc config và `register_post_type()` trên hook `init` (priority 5)
- Tự thêm cột thumbnail trong admin list

### DynamicCptTemplateGenerator
- Tự sinh file `archive-{slug}.php` và `single-{slug}.php` khi tạo CPT mới

---

## 11. Exit Intent Popup

**File:** `app/src/Features/ExitIntentPopup.php`  
**Menu:** Laca Admin → Exit Popup

Popup xuất hiện khi người dùng sắp rời trang:

| Cài đặt | Mô tả |
|---------|-------|
| Tiêu đề | Tiêu đề popup |
| Nội dung | HTML hoặc shortcode (hỗ trợ `[laca_contact_form id="X"]`) |
| Trigger | `exit` (mouse rời viewport) / `time` (sau X giây) / `scroll` (cuộn X%) |
| Delay | Số giây (cho trigger = time) |
| Scroll % | Phần trăm cuộn (cho trigger = scroll) |
| Cookie | Số giờ trước khi hiện lại sau khi đóng |

- Exit intent: phát hiện mouse `clientY <= 0`
- Mobile fallback: hiện sau 15 giây nếu không có mouseleave
- Cookie `laca_popup_closed` ngăn hiện lại
- Accessibility: `role="dialog"`, `aria-modal`, đóng bằng Escape
- Admin action: `admin_post_laca_popup_save`

---

## 12. Maintenance Mode

**File:** `app/src/Settings/MaintenanceModeManager.php`

- Nút bật/tắt trực tiếp trên Admin Bar (không reload trang)
- Dot đỏ/xanh hiển thị trạng thái hiện tại
- Khi bật: redirect visitor đến `theme/maintenance.php` với HTTP 503
- Admin và IP whitelist vẫn xem được site bình thường
- AJAX: `laca_toggle_maintenance`
- Options: `laca_maintenance_mode`, `laca_maintenance_ip_whitelist`

---

## 13. Frontend Features

### Related Posts
**File:** `app/src/Features/RelatedPosts.php`  
- Hiển thị 3 bài liên quan cuối mỗi single post (hook `the_content` priority 99)
- Ưu tiên: cùng tag → cùng category → bài mới nhất
- Layout: grid 3 cột, responsive

### Mobile Sticky CTA
**File:** `app/src/Features/MobileStickyCta.php`  
- Thanh CTA cố định dưới màn hình mobile (<768px)
- 3 nút: 📞 Gọi ngay, 💬 Zalo, 📧 Báo giá
- Ẩn khi scroll lên, hiện khi scroll xuống
- Data: `phone_number`, `zalo`, `laca_cta_contact_page_id` (Carbon Fields)

### Reading Progress Bar
**File:** `resources/scripts/theme/components/reading-progress.js`  
- Thanh progress ở đầu trang hiển thị % đã đọc bài viết

### Dark Mode
**File:** `resources/scripts/theme/components/dark-mode.js`  
- Toggle dark/light theme, lưu preference

### Instant Page + Smooth Scroll
- Instant.page prefetch khi hover link
- Smooth scroll cho anchor links

### Service Worker
**File:** `resources/scripts/theme/service-worker-register.js`  
- Đăng ký `/dist/sw.js` cho offline support và caching

---

## 14. Admin Dashboard & UX

### Dashboard Widgets (7 widget)
**File:** `app/src/Settings/LacaTools/Management/DashboardWidgets.php`

| Widget | Nội dung |
|--------|----------|
| 🚀 LacaDev Business Hub | Tổng quan |
| 📈 Báo cáo Nội dung | Thống kê content health |
| 🩺 Tình trạng Website | Site health score |
| 🖼️ Thư viện Media | Stats media library |
| ✅ Việc cần làm | TODO tracker (QuickNotesWidget) |
| 🔍 Tìm kiếm nhanh | Live search bài viết/trang |
| 📊 Thống kê Dự án | Chart.js (theo trạng thái + theo tháng) |

AJAX: `lacadev_quick_search`

### Content Audit Service
**File:** `app/src/Settings/LacaTools/Management/ContentAuditService.php`  
- Cron hàng tuần: `lacadev_weekly_deep_audit`
- 14 kiểm tra sức khỏe nội dung: ảnh thiếu, nội dung ngắn, SEO meta, link hỏng, title quá dài/ngắn, bài cũ không update, duplicate title/excerpt, draft cũ...

### Media Service
**File:** `app/src/Settings/LacaTools/Management/MediaService.php`  
- Thống kê media: tổng số, không dùng, theo loại file, tổng dung lượng
- Submenu "Media Không Dùng" trong Media

### Admin UX Service
**File:** `app/src/Settings/LacaTools/Management/AdminUxService.php`  
- **Menu:** Laca Admin → HD Sử dụng
- Trang hướng dẫn sử dụng cho client (Carbon Fields repeater)
- Thông tin hỗ trợ: SĐT, email, website
- Ẩn top-level menu `#toplevel_page_laca-admin` (submenus vẫn hiện)

### List Table Enhancements
**File:** `app/src/Settings/LacaTools/Management/ListTableEnhancements.php`  
- Cột ID trong danh sách bài viết
- Cột Views (duplicate detection)
- Nút duplicate bài viết

---

## 15. Database Tables tùy chỉnh

| Table | File | Nội dung |
|-------|------|----------|
| `wp_laca_project_logs` | `ProjectLogTable.php` | Logs từng dự án (type, content, level, created_at) |
| `wp_laca_project_alerts` | `ProjectAlertTable.php` | Alerts dự án (type, level, status, created_at) |
| `wp_laca_contact_forms` | `ContactFormTable.php` | Định nghĩa form liên hệ |
| `wp_laca_contact_submissions` | `ContactFormTable.php` | Submissions từ form |
| `wp_laca_email_logs` | `EmailLogTable.php` | Log email gửi đi (tự xóa sau 90 ngày) |

---

## 16. Built-in Post Types

### Project (CPT)
- Slug: `/projects/`
- Icon: dashicons-layout
- Traits: HasEncryption, HasBrandColors, HasCurrencyFormat, HasPortalAlias, BlockSyncSender
- Tích hợp đầy đủ với Project Manager ở trên

### Service (CPT)
- Slug: `/services/`
- Icon: dashicons-admin-generic
- Supports: title, editor, thumbnail, excerpt

### Template (CPT)
- Slug: `/templates/`
- Icon: dashicons-layout
- Carbon Fields tabs: Visuals (live_url, gallery...), Tech Specs (platform, builder, features...)

---

## Tổng kết nhanh

| Nhóm | Số chức năng |
|------|-------------|
| Bảo mật (Security Manager + Tools) | 13 |
| Quản lý dự án | 8 |
| AI (Chat + Dịch thuật) | 2 |
| Admin Dashboard & UX | 6 |
| Form liên hệ + Email Log | 3 |
| Frontend (Popup, CTA, Related Posts...) | 5 |
| CPT động + Tools (DB Cleaner, Maintenance) | 4 |
| Database tables tùy chỉnh | 5 |
| **Tổng** | **~46 nhóm chức năng** |
