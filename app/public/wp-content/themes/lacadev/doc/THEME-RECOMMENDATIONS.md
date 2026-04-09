# THEME-RECOMMENDATIONS.md — LacaDev Theme v3.1

> Tài liệu này phân tích các chức năng còn thiếu trong theme, giải thích **nguyên nhân rủi ro** nếu không bổ sung và **kết quả đạt được** sau khi implement. Kết hợp đọc cùng `AI_CODING_INSTRUCTIONS.md` để thực thi đúng kiến trúc.

---

## 🔴 ƯU TIÊN CAO

---

### 1. Custom Taxonomies cho CPTs

**Hiện trạng:** Theme có 3 CPTs (Project, Service, Template) nhưng **chưa đăng ký taxonomy nào**. Block `project-block` và `service-block` có bộ lọc (filter) theo category nhưng taxonomy không tồn tại → bộ lọc không hoạt động.

**Nguyên nhân rủi ro nếu không sửa:**
- Bộ lọc portfolio/service trên frontend trả về kết quả sai hoặc rỗng.
- Client không thể phân loại dự án/dịch vụ theo ngành hàng, lĩnh vực.
- SEO mất đi các archive page theo danh mục (`/danh-muc/thiet-ke-web/`).
- Nội dung bị đổ đống, không có cấu trúc phân cấp → trải nghiệm admin kém.

**Kết quả đạt được:**
- Block filter hoạt động đúng theo taxonomy.
- Admin có thể gán nhãn/category cho từng project và service.
- Xuất hiện thêm archive URL SEO-friendly theo danh mục.
- Hỗ trợ breadcrumb theo cấu trúc category → post.

**Vị trí implement:** `app/src/PostTypes/` — tạo class kế thừa `AbstractTaxonomy`, đăng ký vào bootstrap.

---

### 2. Schema.org Structured Data (JSON-LD)

**Hiện trạng:** SEO setup (`theme/setup/seo.php`) đã có Open Graph và canonical URL, nhưng **chưa có JSON-LD structured data**. Schema.org chỉ được đề cập trong comments, chưa có output thực tế.

**Nguyên nhân rủi ro nếu không sửa:**
- Google không hiển thị **rich results** (rating sao, breadcrumb, sitelinks searchbox) trong SERP.
- Cạnh tranh SEO yếu hơn đối thủ đã có structured data.
- Không qualify cho `Organization Knowledge Panel` trên Google.
- Với các agency site, thiếu `LocalBusiness` schema ảnh hưởng Google Maps ranking.

**Kết quả đạt được:**
- Rich results xuất hiện trong Google Search (breadcrumbs, sitelinks, logo).
- Tăng CTR từ 15–30% nhờ kết quả hiển thị đẹp hơn.
- Google hiểu đúng thông tin doanh nghiệp: tên, địa chỉ, điện thoại, dịch vụ.
- Hỗ trợ `WebPage`, `Article`, `Service`, `Organization`, `BreadcrumbList`.

**Vị trí implement:** `theme/setup/seo.php` — thêm `wp_head` action output JSON-LD dựa trên `is_single()`, `is_page()`, `is_front_page()`.

---

### 3. Form Contact tích hợp Admin UI ✅ *(đã implement — xem dưới)*

**Hiện trạng:** Không có form liên hệ nào được tích hợp sẵn. Nếu dùng plugin bên ngoài (Contact Form 7, WPForms) sẽ bị conflict CSS/JS với theme.

**Nguyên nhân rủi ro nếu không sửa:**
- Mỗi site phải cài thêm plugin form riêng → tăng dependency.
- Plugin form ngoài dùng jQuery và inline CSS → conflict với Tailwind CSS và Vanilla JS của theme.
- Không control được luồng email (SMTP, template, logging).
- Không thể custom validation theo Pristine.js đã có sẵn.
- Thiếu điểm contact = mất lead khách hàng tiềm năng.

**Kết quả đạt được:**
- Form builder tích hợp Admin UI → tạo form tùy ý không cần code.
- Email admin và email xác nhận khách tự động gửi qua SMTP đã cấu hình.
- Shortcode `[laca_contact_form id="X"]` nhúng vào bất kỳ trang nào.
- Lưu submissions vào database → admin xem lịch sử.
- Validation client-side (Pristine.js) + server-side PHP.
- Nonce verification đầy đủ theo chuẩn bảo mật theme.

---

### 4. Cookie Consent / GDPR Banner

**Hiện trạng:** Theme có CSP headers và security hardening tốt, nhưng **chưa có GDPR consent banner**. Nếu site dùng Google Analytics, Facebook Pixel, hoặc bất kỳ tracking nào thì vi phạm GDPR/PDPA.

**Nguyên nhân rủi ro nếu không sửa:**
- Vi phạm GDPR (EU) và PDPA (Việt Nam) → rủi ro pháp lý cho client.
- Google Analytics 4 không được phép thu thập data trước khi có consent.
- Nếu bị report, site có thể bị phạt hoặc bị yêu cầu gỡ.
- Các audit tool (Lighthouse, GDPR checker) sẽ flag lỗi.

**Kết quả đạt được:**
- Banner consent hiển thị lần đầu vào site.
- Chặn analytics script trước khi có consent.
- Lưu trạng thái consent vào `localStorage` → không hỏi lại.
- Tự động enable script sau khi accept.

**Vị trí implement:** `theme/setup/` — file `gdpr.php`, kết hợp `theme.json` setting, SCSS trong `resources/styles/components/`.

---

## 🟡 ƯU TIÊN TRUNG BÌNH

---

### 5. Notification UI cho Client Portal

**Hiện trạng:** `ProjectAlertTable` và `ProjectAlert` model đã tồn tại, `ProjectNotificationHandler` gửi cron alerts, nhưng **không có UI frontend** hiển thị alerts cho client đăng nhập.

**Nguyên nhân rủi ro nếu không sửa:**
- Data alerts được lưu xuống DB nhưng client không nhìn thấy → mất tác dụng.
- Admin phải liên hệ thủ công mỗi khi có alert → tốn thời gian.
- Client portal thiếu tính năng core → giảm giá trị sản phẩm.

**Kết quả đạt được:**
- Bell icon + counter badge trong header client portal.
- Dropdown hiển thị alerts chưa đọc với type color (info/warning/critical).
- AJAX polling mỗi 60s hoặc khi click để refresh.
- Mark as read khi click.

**Vị trí implement:** `resources/scripts/portal/`, template trong `theme/template-parts/portal/`.

---

### 6. Dark Mode

**Hiện trạng:** `tailwind.config.js` đã khai báo `darkMode: 'class'` nhưng không có toggle button và không có dark color variables trong `theme.json`.

**Nguyên nhân rủi ro nếu không sửa:**
- Người dùng bật dark mode ở OS nhưng site vẫn sáng → trải nghiệm không nhất quán.
- Thiếu tính năng ngày càng được users mong đợi.
- Lighthouse best practices có thể flag `prefers-color-scheme` media query missing.

**Kết quả đạt được:**
- Toggle button light/dark trong header.
- Tôn trọng `prefers-color-scheme` của OS theo mặc định.
- Lưu preference vào `localStorage`.
- Transition mượt mà nhờ CSS custom properties.

**Vị trí implement:** `resources/scripts/theme/dark-mode.js`, `resources/styles/utilities/_dark-mode.scss`, thêm toggle HTML vào `theme/header.php`.

---

### 7. Custom 404 Template

**Hiện trạng:** Không có `404.php` trong thư mục `theme/`. WordPress fallback về `index.php` → render sai layout.

**Nguyên nhân rủi ro nếu không sửa:**
- Trang 404 hiển thị layout không phù hợp hoặc trắng → trải nghiệm tệ.
- Crawlers thấy nhiều 404 không được xử lý tốt → ảnh hưởng SEO crawl budget.
- Thiếu CTA trên 404 → user rời site thay vì được redirect về nội dung hữu ích.
- Không có schema `WebPage` với `breadcrumb` cho 404 → Google confuse.

**Kết quả đạt được:**
- Layout 404 đồng bộ với design theme.
- Gợi ý trang phổ biến, search box, nút về trang chủ.
- Log 404 hits để admin biết link nào bị broken.

**Vị trí implement:** `theme/404.php`.

---

### 8. Sitemap XML tích hợp Custom Post Types

**Hiện trạng:** WordPress 5.5+ có native sitemap (`/wp-sitemap.xml`), nhưng CPTs `project` và `service` cần được đảm bảo có trong sitemap, và các page bị `noindex` cần được loại trừ.

**Nguyên nhân rủi ro nếu không sửa:**
- Google có thể không crawl hết các project/service pages.
- Trang maintenance, client portal (`/portal/`) bị index nhầm → lộ thông tin.
- Không có `lastmod` đúng cho các dynamic pages → Google craw không hiệu quả.

**Kết quả đạt được:**
- Sitemap sạch, chỉ chứa public pages và CPTs.
- `lastmod` chính xác theo `post_modified`.
- Exclude tự động các page private, noindex, password-protected.

**Vị trí implement:** `theme/setup/seo.php` — filter `wp_sitemaps_post_types`, `wp_sitemaps_posts_query_args`.

---

### 9. Block: Testimonials / Reviews

**Hiện trạng:** Theme có các block portfolio, services, process, blog... nhưng **không có block testimonial** — yếu tố social proof quan trọng cho agency site.

**Nguyên nhân rủi ro nếu không sửa:**
- Thiếu social proof → giảm conversion rate.
- Client phải tự code hoặc dùng plugin thêm → phá vỡ design consistency.
- Không có block → admin không thêm testimonial được qua Gutenberg.

**Kết quả đạt được:**
- Block `testimonial-block` với slider (Swiper đã có sẵn) hoặc grid.
- Repeater fields: tên, ảnh avatar, quote, chức vụ, rating sao.
- Animation bằng AOS (đã có sẵn).
- Review schema markup tích hợp.

**Vị trí implement:** `block-gutenberg/testimonial-block/` — theo pattern `tech-list-block` (repeater).

---

### 10. Block: FAQ (Accordion)

**Hiện trạng:** Không có FAQ block. FAQ là nội dung phổ biến và quan trọng cho SEO (FAQ schema).

**Nguyên nhân rủi ro nếu không sửa:**
- Không có FAQ → mất cơ hội rank Google FAQ rich results.
- Admin phải dùng shortcode hoặc HTML thô để tạo accordion.
- Thiếu FAQ Schema → Google không hiển thị Q&A trong SERP.

**Kết quả đạt được:**
- Block FAQ với InspectorControls (thêm/xóa Q&A trong Editor).
- Accordion animation CSS thuần (không cần thư viện).
- FAQ Schema `FAQPage` JSON-LD tự động.
- Accessible: ARIA `aria-expanded`, keyboard navigation.

**Vị trí implement:** `block-gutenberg/faq-block/` — theo pattern `tech-list-block`.

---

## 🟢 ƯU TIÊN THẤP (Nice-to-have)

---

### 11. Polylang Support

**Hiện trạng:** Code có nhiều hint WPML (`icl_register_string`, `icl_t`, `WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY`) nhưng không có Polylang support. Polylang là lựa chọn free và phổ biến hơn.

**Nguyên nhân rủi ro nếu không sửa:**
- Clients không dùng WPML (premium, ~$79/year) phải tự xử lý đa ngôn ngữ.
- `getOption()` helper tự map theo WPML language — sẽ return sai nếu dùng Polylang.

**Kết quả đạt được:**
- `getOption()` tương thích cả WPML và Polylang.
- Theme có thể deploy cho client cần đa ngôn ngữ không có WPML.

---

### 12. Print Stylesheet

**Hiện trạng:** `ProjectPdfExporter` xuất PDF cho admin, nhưng frontend pages không có `@media print` CSS. Nếu user in trang project/service, layout bị vỡ.

**Nguyên nhân rủi ro nếu không sửa:**
- Khách hàng print trang dịch vụ/portfolio → bị vỡ layout, thừa navigation/footer.
- Ảnh hưởng đến tính chuyên nghiệp khi gửi tài liệu in.

**Kết quả đạt được:**
- Navigation, footer, sidebars ẩn khi in.
- Typography adjust cho print (font-size, line-height).
- URL được in ra dưới mỗi link.

**Vị trí implement:** `resources/styles/utilities/_print.scss`.

---

### 13. Admin Dashboard Widgets cải tiến

**Hiện trạng:** Dashboard đã có 6 widgets (Business Hub, Báo cáo nội dung, Tình trạng website, Thư viện Media, Việc cần làm, Tìm kiếm nhanh) nhưng chưa hiển thị Form Submissions.

**Kết quả đạt được:**
- Widget "Liên hệ mới" hiển thị số submission chưa đọc trong 7 ngày.
- Quick link xem submissions.

---

### 14. GraphQL Support (WPGraphQL)

**Hiện trạng:** Theme có REST API (`/laca/v1/`) cho AI Chat và Client Portal, nhưng nếu muốn build headless frontend trong tương lai thì cần GraphQL schema.

**Nguyên nhân rủi ro nếu không sửa:**
- Không scale được sang headless/decoupled architecture.
- CPTs Project, Service không có GraphQL type → không query được từ Next.js/Nuxt.

**Kết quả đạt được:**
- Register GraphQL types cho tất cả CPTs qua WPGraphQL plugin hooks.
- Custom meta fields exposed qua GraphQL.

---

## 📋 TỔNG HỢP THEO NHÓM

| Nhóm | Tính năng thiếu | Mức độ |
|------|----------------|--------|
| **Data Model** | Custom Taxonomies cho CPTs | 🔴 Cao |
| **SEO** | Schema.org JSON-LD, Sitemap CPT control | 🔴🟡 |
| **UX/Lead** | Contact Form Builder (Admin UI) | 🔴 Cao |
| **Compliance** | GDPR Cookie Consent | 🔴 Cao |
| **Frontend UX** | Dark Mode, 404 Template | 🟡 Trung bình |
| **Client Portal** | Notification Bell UI | 🟡 Trung bình |
| **Content Blocks** | Testimonials Block, FAQ Block | 🟡 Trung bình |
| **I18n** | Polylang Support | 🟢 Thấp |
| **Accessibility** | Print Stylesheet | 🟢 Thấp |
| **Future-proof** | GraphQL Schema | 🟢 Thấp |

---

*Cập nhật: 2026-04-07 | Version: 1.0 | Author: LacaDev AI Agent*
