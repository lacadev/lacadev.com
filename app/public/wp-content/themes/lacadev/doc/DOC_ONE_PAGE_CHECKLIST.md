# LACADEV ONE-PAGE CHECKLIST

> Checklist vận hành nhanh hằng ngày cho dev/AI/PM. Dùng trước commit, trước release và sau release.

---

## A) Nhận task và định vị đúng chỗ

- [ ] Xác định loại task: block, CPT, security fix, performance, admin feature, deploy.
- [ ] Chọn đúng tài liệu tham chiếu:
  - [ ] Chuẩn code: `AI_CODING_INSTRUCTIONS.md`
  - [ ] Bản đồ tính năng: `THEME-FEATURES.md`
  - [ ] Runbook deploy: `DEPLOY_SECURITY_PERFORMANCE_CHECKLIST.md`
- [ ] Xác nhận vị trí code đúng:
  - [ ] Hook ngắn: `app/hooks.php`
  - [ ] Logic phức tạp: class trong `app/src/`
  - [ ] Template/partial: `theme/`, `theme/template-parts/`
  - [ ] Block mới: `block-gutenberg/[block-name]/`

---

## B) Checklist coding bắt buộc (trước commit)

### B1. Bảo mật

- [ ] Tất cả output đã escape đúng ngữ cảnh (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- [ ] Input từ request đã sanitize (`sanitize_text_field`, `absint`, ...).
- [ ] Form/AJAX đã verify nonce (`check_ajax_referer` hoặc `wp_verify_nonce`).

### B2. Hiệu năng

- [ ] Không tạo N+1 query trong loop.
- [ ] Dùng `WP_Query` tối ưu (`no_found_rows => true` khi không phân trang).
- [ ] Dùng helper ảnh/asset của theme thay vì code ad-hoc.
- [ ] Không enqueue JS/CSS thừa ở trang không cần.

### B3. Frontend/UX

- [ ] HTML semantic hợp lý (`main`, `article`, `header`, ...).
- [ ] Form có `label`, icon button/link có `aria-label` khi cần.
- [ ] Responsive đã test nhanh mobile/tablet/desktop.

### B4. Vệ sinh code

- [ ] Không còn `var_dump`, `dd`, `console.log`, `die`.
- [ ] Không thêm logic nghiệp vụ vào `functions.php`.
- [ ] Đặt tên/hàm/class theo convention hiện có của project.

---

## C) Checklist build và pre-release

- [ ] Cài dependency nhất quán lockfile.
- [ ] Build production thành công:
  - [ ] `yarn install --frozen-lockfile`
  - [ ] `yarn build`
- [ ] Regenerate critical CSS nếu task ảnh hưởng phần above-the-fold:
  - [ ] `yarn critical`
- [ ] Smoke test trên staging:
  - [ ] Homepage
  - [ ] Single post/page
  - [ ] Form quan trọng
  - [ ] Login/register/comment (nếu liên quan)

---

## D) Checklist security & infra trước deploy

- [ ] reCAPTCHA đã bật và verify server-side đủ điều kiện.
- [ ] Anti-bot/rate-limit cho endpoint nhạy cảm:
  - [ ] `wp-comments-post.php`
  - [ ] `wp-login.php`
  - [ ] `xmlrpc.php`
- [ ] XML-RPC policy đã đúng (chặn nếu không dùng).
- [ ] `DISALLOW_FILE_EDIT` đã bật trên production.
- [ ] File permission an toàn (file `644`, folder `755`).

---

## E) Checklist deploy window

- [ ] Có backup mới nhất (DB + uploads + config).
- [ ] Có rollback plan rõ người phụ trách và thời gian thao tác.
- [ ] Nếu cần, bật maintenance mode ngắn nhất.
- [ ] Deploy đúng thứ tự: code -> dependency -> cache clear có kiểm soát.
- [ ] Ghi release metadata: thời gian, commit hash, người deploy.

---

## F) Checklist sau deploy

### F1. Trong 15 phút đầu

- [ ] Site không có lỗi trắng trang/5xx bất thường.
- [ ] Log PHP/web server/security không có spike lỗi.
- [ ] Smoke test lại luồng chính trên production.

### F2. Trong 60 phút đầu

- [ ] Theo dõi CWV nhanh: LCP/INP/CLS.
- [ ] Theo dõi TTFB, CPU/RAM/I/O, cache hit ratio.
- [ ] Kiểm tra slow query và endpoint bị tấn công/spam.

### F3. Trong 24 giờ

- [ ] So sánh KPI trước/sau: error rate, latency, spam, tài nguyên.
- [ ] Kích hoạt hotfix/rollback nếu vượt ngưỡng cảnh báo.
- [ ] Ghi lesson learned vào tài liệu nội bộ.

---

## G) Lệnh nhanh dùng mỗi ngày

- [ ] Tìm debug sót:
  - `rg "var_dump|dd\\(|console\\.log|die\\(" /path/to/theme`
- [ ] Check syntax PHP nhanh:
  - `php -l path/to/file.php`
- [ ] Build production:
  - `yarn build`

---

## H) Backlog ưu tiên ngắn hạn (tham chiếu nhanh)

- [ ] Cao: Taxonomy cho CPT, JSON-LD schema thật, GDPR consent banner.
- [ ] Trung bình: Portal notification UI, 404 template, sitemap CPT controls, block FAQ/Testimonial.
- [ ] Thấp: Polylang compatibility, print stylesheet, GraphQL readiness.

