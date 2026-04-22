# Checklist Deploy: Performance + Security (Có thể Tick)

Tài liệu này dùng như runbook để đội triển khai và vận hành website WordPress (`lacadev`) theo chuẩn hiệu suất + bảo mật cao nhất.

## 0) Phạm vi và nơi thực hiện

- **Code/Theme**: `lacadev/app/public/wp-content/themes/lacadev`
- **WP Admin**: `/wp-admin` (Settings, Plugins, Comments, Updates)
- **Server**: Nginx/Apache + PHP-FPM + hệ điều hành
- **CDN/WAF**: Cloudflare dashboard
- **Giám sát**: log server, log PHP, Wordfence/Sucuri, analytics hiệu suất

## 1) Trước khi deploy (Pre-deploy)

### 1.1 Đồng bộ code và môi trường

- [ ] Branch release đã review, test, và chốt commit hash.
  - **Làm ở đâu**: Git repository + CI.
- [ ] Không còn debug tạm (`var_dump`, `dd`, log thử nghiệm không cần thiết).
  - **Làm ở đâu**: source code theme/plugin.
- [ ] Soát thay đổi bảo mật tại các luồng nhạy cảm (login/register/comment/contact/upload).
  - **Làm ở đâu**: code review + staging test.
- [ ] Kiểm tra version runtime giữa staging và production (PHP, DB, web server).
  - **Làm ở đâu**: server config / panel quản trị hạ tầng.

### 1.2 Build và tối ưu tài nguyên

- [ ] Cài dependency và build bản production.
  - **Làm ở đâu**: thư mục theme có frontend build.
  - **Lệnh mẫu**:
    - `yarn install --frozen-lockfile`
    - `yarn build`
- [ ] Xác nhận asset đã minify và có version/cache-busting.
  - **Làm ở đâu**: file enqueue trong theme + thư mục build.
- [ ] Xác nhận không tải JS/CSS thừa ở các trang không cần.
  - **Làm ở đâu**: enqueue hooks trong theme.
- [ ] Tối ưu ảnh (kích thước hiển thị đúng, định dạng phù hợp như WebP/AVIF nếu có).
  - **Làm ở đâu**: media pipeline hoặc plugin tối ưu ảnh.

### 1.3 Bảo mật WordPress + Theme (bắt buộc)

- [ ] Bật reCAPTCHA cho các form công khai (login/register/comment/contact nếu dùng).
  - **Làm ở đâu**: WP Admin -> theme options (reCAPTCHA).
- [ ] Xác nhận server-side reCAPTCHA đủ 5 điều kiện: token bắt buộc, score, action, hostname, token age.
  - **Làm ở đâu**: `theme/setup/recaptcha.php`.
- [ ] Bật anti-bot bổ sung: honeypot + timestamp + rate limit theo IP cho comment.
  - **Làm ở đâu**: code theme/plugin bảo mật.
- [ ] Bật Akismet hoặc anti-spam chuyên dụng cho comment.
  - **Làm ở đâu**: WP Admin -> Plugins.
- [ ] Bật WAF/CDN (Cloudflare), bật Bot Fight và cấu hình rate limit:
  - `wp-comments-post.php`
  - `wp-login.php`
  - `xmlrpc.php`
  - **Làm ở đâu**: Cloudflare -> Security/WAF/Rate Limiting.
- [ ] Chặn XML-RPC nếu không dùng.
  - **Làm ở đâu**: Nginx/Apache config hoặc plugin security.
- [ ] Bật Wordfence/Sucuri, lên lịch quét malware định kỳ.
  - **Làm ở đâu**: WP Admin -> Wordfence/Sucuri settings.
- [ ] Bật auto update cho core/theme/plugin theo chính sách nội bộ.
  - **Làm ở đâu**: WP Admin -> Updates + policy vận hành.
- [ ] Tắt sửa file trong WP Admin (`DISALLOW_FILE_EDIT`).
  - **Làm ở đâu**: `wp-config.php`.
  - **Cấu hình mẫu**: `define('DISALLOW_FILE_EDIT', true);`
- [ ] Hardening filesystem permission:
  - File `644`, folder `755`, không cấp quyền ghi rộng.
  - **Làm ở đâu**: server (SSH/deploy pipeline).

### 1.4 Database, cache, backup, rollback

- [ ] Backup đầy đủ trước deploy: DB, uploads, cấu hình môi trường.
  - **Làm ở đâu**: server backup job / control panel / script.
- [ ] Test restore từ backup gần nhất (ít nhất theo chu kỳ định kỳ).
  - **Làm ở đâu**: môi trường staging/khôi phục thử.
- [ ] Chuẩn bị rollback plan (người phụ trách, thời gian, bước rollback).
  - **Làm ở đâu**: tài liệu release nội bộ.
- [ ] Kiểm tra page cache/object cache/CDN cache đang healthy.
  - **Làm ở đâu**: cache plugin + Redis/Memcached + CDN dashboard.

### 1.5 Kiểm thử bắt buộc trước release

- [ ] Smoke test: homepage, single post, comment, login/register, contact.
  - **Làm ở đâu**: staging.
- [ ] Test bypass comment:
  - Submit hợp lệ từ UI phải qua.
  - Gửi request thiếu token/honeypot/timestamp phải fail.
  - **Làm ở đâu**: browser + Postman/cURL.
- [ ] Test responsive mobile/tablet/desktop.
  - **Làm ở đâu**: browser devtools/thiết bị thật.

## 2) Trong lúc deploy (Release window)

- [ ] Bật maintenance mode (nếu cần) trong thời gian ngắn nhất.
  - **Làm ở đâu**: plugin/CI/deploy script.
- [ ] Deploy đúng thứ tự chuẩn: code -> dependency -> cache clear có kiểm soát.
  - **Làm ở đâu**: pipeline/CD script.
- [ ] Không chạy thao tác phá hủy dữ liệu ngoài runbook đã duyệt.
  - **Làm ở đâu**: quy trình release.
- [ ] Ghi lại release metadata: timestamp, commit hash, người deploy.
  - **Làm ở đâu**: changelog/release note nội bộ.

## 3) Sau khi deploy (Post-deploy)

### 3.1 Trong 15 phút đầu

- [ ] Website lên bình thường, không lỗi trắng trang/5xx bất thường.
  - **Làm ở đâu**: production URL + monitor.
- [ ] Kiểm tra log: PHP, web server, WAF/security.
  - **Làm ở đâu**: server + Cloudflare + Wordfence/Sucuri.
- [ ] Smoke test lại các flow chính trên production.
  - **Làm ở đâu**: production.

### 3.2 Trong 60 phút đầu (hiệu suất)

- [ ] Đo nhanh CWV (LCP/INP/CLS) cho trang chính.
  - **Làm ở đâu**: PageSpeed/Lighthouse/monitor tool.
- [ ] Theo dõi TTFB, CPU/RAM/I/O, cache hit ratio.
  - **Làm ở đâu**: APM/server metrics/CDN analytics.
- [ ] Kiểm tra truy vấn chậm tăng bất thường.
  - **Làm ở đâu**: slow query log/APM/Query Monitor.

### 3.3 Trong 60 phút đầu (bảo mật)

- [ ] Theo dõi request tăng đột biến tới endpoint nhạy cảm.
  - **Làm ở đâu**: Cloudflare + server access log.
- [ ] Theo dõi moderation queue và số spam comment.
  - **Làm ở đâu**: WP Admin -> Comments.
- [ ] Xác nhận rule rate-limit/WAF đang chặn đúng.
  - **Làm ở đâu**: Cloudflare events + security logs.
- [ ] Kiểm tra không có file lạ ở thư mục writable.
  - **Làm ở đâu**: server scan + Wordfence/Sucuri malware scan.

## 4) Sau deploy 24 giờ

- [ ] So sánh số liệu trước/sau: error rate, latency, spam bị chặn, tài nguyên server.
  - **Làm ở đâu**: dashboard monitor + báo cáo nội bộ.
- [ ] Nếu KPI xấu: kích hoạt rollback/hotfix theo runbook.
  - **Làm ở đâu**: release process.
- [ ] Cập nhật lesson learned vào tài liệu vận hành.
  - **Làm ở đâu**: docs nội bộ.

## 5) Mục tiêu chuẩn tối thiểu (SLO)

- [ ] Không có fatal error mới sau deploy.
- [ ] Tỷ lệ 5xx không tăng bất thường.
- [ ] Không còn bypass ở comment/login/register.
- [ ] Lượng spam giảm rõ rệt so với trước.

## 6) Checklist bảo vệ cao nhất (khuyến nghị thêm)

- [ ] Bật Akismet hoặc anti-spam chuyên dụng cho comment.
  - **Làm ở đâu**: WP Admin -> Plugins.
- [ ] Bật WAF/CDN (Cloudflare) + Bot Fight + rate limit `wp-comments-post.php`, `wp-login.php`, `xmlrpc.php`.
  - **Làm ở đâu**: Cloudflare dashboard.
- [ ] Chặn XML-RPC nếu không dùng.
  - **Làm ở đâu**: Nginx/Apache hoặc plugin security.
- [ ] Giới hạn tần suất comment theo IP ở tầng server hoặc plugin bảo mật.
  - **Làm ở đâu**: Nginx/Apache/Wordfence.
- [ ] Bật Wordfence/Sucuri, quét malware định kỳ, tự động cập nhật bản vá.
  - **Làm ở đâu**: WP Admin + cron scan.
- [ ] Hardening file system + tắt edit wp-admin + backup/restore test định kỳ.
  - **Làm ở đâu**: `wp-config.php`, server permission, backup pipeline.

## 7) Mẫu lệnh tham khảo nhanh

- Build frontend:
  - `yarn install --frozen-lockfile`
  - `yarn build`
- Kiểm tra syntax PHP nhanh:
  - `php -l path/to/file.php`
- Tìm debug còn sót:
  - `rg "var_dump|dd\(|console\.log|die\(" /path/to/theme`