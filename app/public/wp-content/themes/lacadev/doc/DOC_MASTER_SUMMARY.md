# LACADEV DOC MASTER SUMMARY

> Phiên bản tổng hợp nhanh từ bộ tài liệu trong `doc/` để dùng làm điểm vào duy nhất cho dev/PM/AI Agent.

---

## 1) Mục tiêu và phạm vi

- Chuẩn hóa cách phát triển theme `lacadev` theo kiến trúc rõ ràng, hiệu năng cao, bảo mật tốt.
- Giảm thời gian đọc nhiều file rời rạc bằng 1 tài liệu tổng quan có cấu trúc.
- Làm tài liệu “điểm neo” cho 3 nhóm: kỹ thuật (dev/AI), vận hành (deploy), quản lý dự án (PM).

---

## 2) Bản đồ tài liệu gốc (7 file)

- `AI_CODING_INSTRUCTIONS.md`: Quy định thực thi chi tiết cho AI/dev (file nào viết gì, viết ở đâu).
- `AGENT_GUIDE.md`: Nguyên tắc phát triển theo WPEmerge + checklist commit + skill gợi ý.
- `THEME-DEVELOPMENT-GUIDE.md`: Tài liệu onboarding kỹ thuật tổng quát.
- `THEME-FEATURES.md`: Danh mục đầy đủ tính năng hiện có của theme.
- `DEPLOY_SECURITY_PERFORMANCE_CHECKLIST.md`: Runbook pre/deploy/post theo hiệu năng + bảo mật.
- `PROJECT_MANAGER_GUIDE.md`: Hướng dẫn nghiệp vụ hệ thống quản lý dự án.
- `THEME-RECOMMENDATIONS.md`: Backlog tính năng còn thiếu theo mức ưu tiên.

---

## 3) Kiến trúc kỹ thuật cốt lõi

### 3.1 Kiến trúc tổng quát

- Theme bám hướng WPEmerge + module hóa + tách backend/frontend.
- Vùng backend chính:
  - `app/routes/`
  - `app/hooks.php`
  - `app/src/` (namespace `App\`)
  - `app/helpers/` (functional helpers)
- Vùng giao diện:
  - `theme/` (template WP)
  - `theme/setup/` (module setup, SEO, security, recaptcha...)
  - `theme/template-parts/` (partial tái sử dụng)
- Vùng assets:
  - `resources/scripts/`, `resources/styles/`
  - build ra `dist/`
- Gutenberg:
  - `block-gutenberg/[block-name]/`

### 3.2 Lưu ý mâu thuẫn tài liệu cần chốt

- `AGENT_GUIDE.md` có nhắc `Controllers`.
- `AI_CODING_INSTRUCTIONS.md` nêu rõ: không dùng thư mục `Controllers/`, logic đặt ở `app/hooks.php` (ngắn) hoặc class trong `app/src/` (dài/phức tạp).
- Khuyến nghị: lấy cấu trúc code thực tế làm chuẩn, sau đó cập nhật đồng bộ 2 tài liệu.

---

## 4) Quy chuẩn phát triển bắt buộc

### 4.1 Vị trí đặt code

- Logic ngắn: `app/hooks.php`.
- Logic nghiệp vụ phức tạp: class trong `app/src/` theo đúng domain (`PostTypes`, `Settings`, `Models`, `Validators`, `Features`, ...).
- Không nhồi logic chức năng vào `functions.php` (chỉ bootstrap).

### 4.2 Chuẩn hiệu năng

- Dùng enqueue có version/cache-busting.
- Tạo build production (`yarn build`) và critical CSS (`yarn critical`) khi cần.
- Tránh N+1 query; dùng `WP_Query` với `no_found_rows => true` khi không phân trang.
- Ưu tiên helper ảnh responsive của theme để tận dụng srcset/WebP.
- Lazy loading đúng chỗ (`loading="lazy"`, `decoding="async"` cho ảnh phù hợp).

### 4.3 Chuẩn bảo mật

- Escape output theo ngữ cảnh: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`, `wp_json_encode`.
- Sanitization mọi input từ request.
- Verify nonce cho form/AJAX (`check_ajax_referer` hoặc `wp_verify_nonce`).
- Không để debug tạm (`var_dump`, `dd`, `console.log`) trước release.

### 4.4 Chuẩn frontend

- Ưu tiên Tailwind + SCSS theo module.
- Hạn chế nesting SCSS sâu (khuyến nghị không quá 3 cấp).
- JS theo ES6+ Vanilla, hạn chế dependency thừa, tránh jQuery.
- HTML semantic + accessibility cơ bản (`label`, `aria-label`, cấu trúc heading hợp lý).

---

## 5) Tính năng hiện có (rút gọn theo nhóm)

### 5.1 Admin & hệ thống công cụ

- Laca Admin settings tổng.
- Tools Optimization (xóa tải thừa, tối ưu output, service worker, resource hints).
- Tools Security + Security Manager (audit, file integrity, malware scan, hidden users, custom login, 2FA).
- Maintenance mode, dashboard widgets, admin UX enhancements.

### 5.2 Project Manager

- CPT `project` + meta data quản trị dự án.
- Logs/alerts, tracker endpoint nhận sự kiện từ site khách.
- Thông báo cron domain/hosting/SSL hết hạn.
- Xuất báo giá/invoice PDF.
- Tích hợp báo cáo và vận hành dự án trong admin.

### 5.3 AI + nội dung + form

- AI Chat đa provider, AI Translation cho nội dung.
- Contact Form builder + submission + email notification.
- Email log theo dõi `wp_mail`.
- Dynamic CPT manager, DB cleaner, frontend features (CTA mobile, related posts, popup, dark mode/scripts hỗ trợ theo tài liệu).

---

## 6) Runbook deploy (Performance + Security)

### 6.1 Trước deploy

- Chốt commit release, dọn debug, soát luồng nhạy cảm.
- Build production (`yarn install --frozen-lockfile`, `yarn build`).
- Kiểm tra recaptcha, anti-bot, WAF/rate-limit, XML-RPC policy, hardening.
- Backup đầy đủ + xác nhận khả năng restore + chuẩn bị rollback plan.
- Smoke test luồng chính trên staging.

### 6.2 Trong deploy

- Bật maintenance mode nếu cần (thời gian ngắn nhất).
- Deploy đúng thứ tự, clear cache có kiểm soát.
- Ghi release metadata (timestamp, commit hash, người deploy).

### 6.3 Sau deploy

- 15 phút đầu: kiểm tra uptime, log lỗi, smoke test production.
- 60 phút đầu: theo dõi CWV/TTFB/CPU/RAM/I/O/cache hit/slow query.
- Bảo mật: giám sát endpoint nhạy cảm, spam queue, WAF events, file lạ.
- 24 giờ: so sánh KPI trước-sau, rollback/hotfix nếu lệch ngưỡng.

---

## 7) Backlog đề xuất theo ưu tiên

### 7.1 Ưu tiên cao

- Bổ sung custom taxonomies cho CPTs (để filter block hoạt động đúng).
- Output JSON-LD schema thực tế trong SEO setup.
- Cookie consent/GDPR banner (đặc biệt khi dùng tracking scripts).

### 7.2 Ưu tiên trung bình

- Notification UI cho client portal.
- 404 template chuyên biệt.
- Hoàn thiện sitemap CPT + loại trừ trang private/noindex.
- Bổ sung block testimonial và FAQ.

### 7.3 Ưu tiên thấp

- Tương thích Polylang tốt hơn.
- Print stylesheet cho trang frontend.
- Nâng cấp thêm dashboard widgets.
- Cân nhắc GraphQL nếu hướng tới headless.

---

## 8) Quy trình làm việc chuẩn (đề xuất áp dụng)

### Bước 1: Nhận task

- Xác định loại task: UI block, CPT/data, security fix, performance, deploy.
- Đối chiếu ngay tài liệu chuẩn:
  - Coding: `AI_CODING_INSTRUCTIONS.md`
  - Features map: `THEME-FEATURES.md`
  - Deploy/runbook: `DEPLOY_SECURITY_PERFORMANCE_CHECKLIST.md`

### Bước 2: Thi công

- Đặt code đúng vị trí theo domain.
- Tuân thủ escape/sanitize/nonce và chuẩn query hiệu năng.
- Tận dụng helper ảnh/asset sẵn có của theme.

### Bước 3: Tự kiểm tra trước bàn giao

- Security check cơ bản: input/output/nonce.
- Performance check cơ bản: query + assets + lazy load.
- Không còn debug tạm.
- Test responsive + luồng chức năng chính.

### Bước 4: Trước release

- Áp checklist deploy đầy đủ theo runbook.
- Ghi rõ release note + rollback plan.

---

## 9) Cheat sheet lệnh nhanh

- Development: `yarn start` hoặc `yarn dev` (tùy script dự án thực tế).
- Build production: `yarn build`.
- Critical CSS: `yarn critical`.
- Dò debug sót: `rg "var_dump|dd\\(|console\\.log|die\\(" /path/to/theme`.
- Kiểm tra syntax PHP nhanh: `php -l path/to/file.php`.

---

## 10) Kết luận

- Bộ tài liệu hiện tại đã rất đầy đủ cho cả dev, AI agent và vận hành.
- Trọng tâm cần duy trì: chuẩn vị trí code + chuẩn bảo mật + chuẩn hiệu năng + runbook deploy.
- Việc tiếp theo nên làm: đồng bộ mâu thuẫn tài liệu kiến trúc và chốt một “source of truth” duy nhất cho team.

