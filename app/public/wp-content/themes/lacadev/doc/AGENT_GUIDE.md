# HƯỚNG DẪN KỸ NĂNG AGENT - GIAO DIỆN LACA DEV

Tài liệu này cung cấp các hướng dẫn dành cho trợ lý AI và lập trình viên khi làm việc với giao diện `lacadev`, đảm bảo tuân thủ các tiêu chuẩn WordPress hiện đại, tối ưu hóa hiệu suất và các phương pháp bảo mật tốt nhất.

## 1. Kiến trúc Cốt lõi (WPEmerge & Cấu trúc Ứng dụng)
Giao diện sử dụng framework **WPEmerge** cho cách tiếp cận MVC có cấu trúc.
- **Routes (Đường dẫn):** `app/routes/` - Định nghĩa các URL và ánh xạ chúng tới Controllers.
- **Controllers (Bộ điều khiển):** `app/src/Controllers/` - Xử lý logic nghiệp vụ, tương tác với Models và chuẩn bị dữ liệu cho Views.
- **Views (Giao diện):** `theme/templates/` hoặc thông qua các lớp `App\View` - chỉ dành cho logic hiển thị.
- **Cấu hình:** `app/config.php` & `config.json`.

## 2. Tiêu chuẩn Lập trình & Phương pháp Tốt nhất

### A. Tối ưu hóa Hiệu suất (Quan trọng)
1. **Tải Tài nguyên:**
   - Sử dụng các wrapper `Assets::enqueueScript()` và `Assets::enqueueStyle()` để quản lý phiên bản và handle riêng biệt.
   - **Critical CSS:** Kiểm tra `dist/critical.css` và nhúng inline vào `<head>` (được xử lý qua `yarn critical`).
   - **Trì hoãn Script:** Script không quan trọng nên được trì hoãn bằng thuộc tính `defer` (được xử lý trong `assets.php`).
   - **Preloading:** Sử dụng `<link rel="preload">` trong `wp_head` cho các tài nguyên quan trọng (fonts, ảnh above-the-fold, CSS chính). **Không** echo thẻ preload bên trong filter `script_loader_tag`.

2. **Cơ sở dữ liệu & Truy xuất Dữ liệu:**
   - **Không truy vấn N+1:** Tránh dùng `get_post_meta()` hoặc `wp_get_object_terms()` bên trong vòng lặp.
   - **Caching:** Sử dụng `wp_cache_set` / `wp_cache_get` cho các tính toán nặng hoặc truy vấn phức tạp.
   - **Vòng lặp truy vấn:** Sử dụng `WP_Query` với `no_found_rows => true` khi không cần phân trang.

3. **Hình ảnh:**
   - Sử dụng các hàm helper responsive của theme (ví dụ: `theResponsivePostThumbnail`) để tận dụng `srcsets`.
   - Đảm bảo có `loading="lazy"` và `decoding="async"` trên các ảnh ngoài màn hình hiển thị.

### B. Bảo mật (Bắt buộc)
1. **Escape Đầu ra (Output Escaping):**
   - Thuộc tính HTML: `echo esc_attr($var)`
   - Nội dung HTML: `echo esc_html($var)`
   - HTML an toàn (có thẻ): `echo wp_kses_post($var)`
   - Đường dẫn URL: `echo esc_url($var)`
   - Dữ liệu JavaScript: `echo wp_json_encode($data)`
2. **Làm sạch Đầu vào (Input Sanitization):**
   - Làm sạch tất cả dữ liệu `$_GET`, `$_POST`, `$_REQUEST` trước khi sử dụng (ví dụ: `sanitize_text_field`, `absint`).
3. **Nonces (Token xác thực):**
   - Luôn xác minh nonce cho Form và các request AJAX (`check_ajax_referer` hoặc `wp_verify_nonce`).

### C. Gutenberg Blocks (Hybrid)
- **Static Blocks:** Được phát triển trong `block-gutenberg/` sử dụng React/JSX.
- **Dynamic Blocks:** Sử dụng `render.php` để render phía server, đảm bảo logic PHP chạy trên mỗi request.
- **block.json:** Phải có cho mỗi block để định nghĩa metadata, attributes, và styles.

## 3. Quy trình Phát triển
- **Start Dev Server:** `yarn dev` (Theo dõi thay đổi, biên dịch SCSS/JS).
- **Production Build:** `yarn build` (Nén assets, tạo `dist/`).
- **Generate Critical CSS:** `yarn critical` (Tạo lại CSS đường dẫn quan trọng).

## 4. Checklist Trước khi Commit cho Agents
1. [ ] **Escaping:** Tất cả biến trong file template đã được escape đúng cách chưa?
2. [ ] **Preload Logic:** Việc preload có được thực hiện trong `wp_head`, không phải trong footer scripts không?
3. [ ] **Console Logs:** Các dòng debug `console.log` hoặc `var_dump` đã được xóa chưa?
4. [ ] **Kích thước Asset:** Dung lượng bundle có tăng hiệu quả không? (Kiểm tra `dist/Only`).
5. [ ] **Kiểm tra Mobile:** Giao diện có responsive không?

---
*Được tạo bởi Phân tích Kỹ năng Agent*

## 5. Các Kỹ năng Agent Hỗ trợ (WordPress Skills)

Dưới đây là danh sách các kỹ năng chuyên biệt đã được tích hợp để hỗ trợ dự án WordPress này. Bạn có thể gọi trực tiếp các kỹ năng này bằng phím `@` hoặc yêu cầu Agent sử dụng chúng.

### A. WordPress Core & Development

| Kỹ năng | Mô tả chi tiết | Câu lệnh sử dụng ví dụ | Khi nào dùng |
| :--- | :--- | :--- | :--- |
| **wordpress** | Kỹ năng tổng quát cho mọi tác vụ WordPress (cấu hình, cài đặt, quản lý). | `@wordpress setup multisite` | Cấu hình WordPress, quản lý core |
| **wordpress-theme-development** | Chuyên sâu về phát triển giao diện, template hierarchy, và Gutenberg blocks. | `@wordpress-theme-development create custom template` | Phát triển template, custom post types |
| **wordpress-plugin-development** | Tập trung vào phát triển tính năng, hooks (actions/filters), và REST API. | `@wordpress-plugin-development add custom hooks` | Tạo plugin, custom functionality |
| **wordpress-woocommerce-development** | Tối ưu cho thương mại điện tử: tùy chỉnh cửa hàng, thanh toán và vận chuyển. | `@wordpress-woocommerce-development customize checkout` | Phát triển WooCommerce |
| **wp-block-development** | Hỗ trợ chuyên sâu phát triển Block (Gutenberg): metadata, attributes, render.php. | `@wp-block-development create dynamic block` | Tạo Gutenberg blocks |
| **wp-rest-api** | Phát triển và tối ưu hướng kết nối qua WordPress REST API endpoints. | `@wp-rest-api create custom endpoint` | Tạo REST API endpoints |
| **wp-interactivity-api** | Triển khai các tính năng tương tác (Interactivity API) hiện đại của WP. | `@wp-interactivity-api add interactive features` | Tính năng tương tác động |
| **wp-block-themes** | Block theme: theme.json, templates/parts, patterns, style variations, Site Editor. | `@wp-block-themes edit theme.json` | Phát triển block themes |
| **wp-abilities-api** | WordPress Abilities API: register abilities, categories, REST exposure, permission checks. | `@wp-abilities-api register new ability` | Quản lý Abilities API |
| **wp-phpstan** | Cấu hình, chạy và fix PHPStan static analysis cho WordPress projects. | `@wp-phpstan fix phpstan errors` | Static analysis PHP |
| **wp-playground** | WP Playground: disposable WP instances, blueprints, version switching, Xdebug. | `@wp-playground spin up test instance` | Test nhanh, không cần full stack |
| **wp-project-triage** | Phân tích cấu trúc repo WP: xác định loại project, tooling, versions, signals. | `@wp-project-triage inspect repo` | Triage dự án WP |
| **wp-wpcli-and-ops** | WP-CLI operations: search-replace, db, plugin/theme, cron, cache, multisite, automation. | `@wp-wpcli-and-ops safe search-replace` | Quản trị WP qua CLI |

### B. UI/UX & Frontend Design

| Kỹ năng | Mô tả chi tiết | Câu lệnh sử dụng ví dụ | Khi nào dùng |
| :--- | :--- | :--- | :--- |
| **ui-ux-pro-max** | Hiện thực hóa vibe minimalism, layout thoáng, typography tinh tế. 50 styles, 21 palettes, Tailwind, React. | `@ui-ux-pro-max design landing page` | Thiết kế UI/UX từ đầu |
| **ui-ux-designer** | Chuyên gia thiết kế giao diện: wireframes, design systems, user research, accessibility. | `@ui-ux-designer create design system` | Xây dựng design system |
| **frontend-design** | Tạo giao diện production-grade với intentional aesthetics, high craft, non-generic visual identity. | `@frontend-design build distinctive UI` | UI có tính thẩm mỹ cao |
| **tailwind-design-system** | Xây dựng design system scalable với Tailwind CSS, design tokens, component libraries. | `@tailwind-design-system setup component library` | Standardize UI components |
| **accessibility-compliance-accessibility-audit** | Kiểm tra tuân thủ WCAG 2.2, inclusive design, assistive technology compatibility. | `@accessibility-compliance-accessibility-audit check WCAG` | Đảm bảo accessibility |
| **wcag-audit-patterns** | WCAG 2.2 audit chi tiết: automated testing, manual verification, remediation guidance. | `@wcag-audit-patterns audit WCAG 2.2` | Audit accessibility nâng cao |
| **react-ui-patterns** | Modern React UI patterns cho loading states, error handling, data fetching. | `@react-ui-patterns implement loading states` | React component patterns |

### C. Performance & Optimization

| Kỹ năng | Mô tả chi tiết | Câu lệnh sử dụng ví dụ | Khi nào dùng |
| :--- | :--- | :--- | :--- |
| **wp-performance** | Điều tra và tối ưu hiệu suất WordPress: profiling, DB queries, object cache, WP-CLI. | `@wp-performance analyze slow queries` | Tối ưu tốc độ WordPress |
| **performance-engineer** | Chuyên gia tối ưu hiệu suất: OpenTelemetry, distributed tracing, load testing, Core Web Vitals. | `@performance-engineer optimize LCP FID CLS` | Cải thiện Core Web Vitals |
| **web-performance-optimization** | Tối ưu loading speed, bundle size, caching strategies, runtime performance. | `@web-performance-optimization reduce bundle size` | Tối ưu web performance |
| **database-optimizer** | Chuyên gia tối ưu database: query optimization, indexing, N+1 resolution, multi-tier caching. | `@database-optimizer fix N+1 queries` | Tối ưu database queries |
| **sql-optimization-patterns** | Master SQL query optimization, indexing strategies, EXPLAIN analysis. | `@sql-optimization-patterns optimize slow query` | Debug slow SQL queries |
| **react-best-practices** | React và Next.js performance optimization guidelines từ Vercel Engineering. | `@react-best-practices review component performance` | Tối ưu React/Next.js |

### D. Security & Compliance

| Kỹ năng | Mô tả chi tiết | Câu lệnh sử dụng ví dụ | Khi nào dùng |
| :--- | :--- | :--- | :--- |
| **security-auditor** | Chuyên gia bảo mật: vulnerability assessment, threat modeling, OAuth2/OIDC, OWASP standards. | `@security-auditor audit entire codebase` | Kiểm tra bảo mật toàn diện |
| **frontend-security-coder** | XSS prevention, output sanitization, client-side security patterns. | `@frontend-security-coder review client security` | Bảo mật phía client |
| **backend-security-coder** | Input validation, authentication, API security phía server. | `@backend-security-coder review server security` | Bảo mật phía server |
| **wordpress-penetration-testing** | Kiểm tra bảo mật chuyên sâu WordPress: quét lỗ hổng, exploit vulnerabilities. | `@wordpress-penetration-testing scan for vulnerabilities` | Pentest WordPress site |
| **web-security-testing** | Web application security testing: OWASP Top 10, injection, XSS, authentication flaws. | `@web-security-testing test for XSS` | Test web vulnerabilities |
| **security-scanning-security-hardening** | Multi-layer security scanning và hardening across application, infrastructure, compliance. | `@security-scanning-security-hardening harden server` | Thắt chặt bảo mật hệ thống |
| **xss-html-injection** | Chuyên về phòng chống Cross-Site Scripting và HTML injection attacks. | `@xss-html-injection test XSS vulnerabilities` | Kiểm tra XSS |
| **sql-injection-testing** | Test và phòng chống SQL injection vulnerabilities, bypass authentication. | `@sql-injection-testing test SQLi` | Kiểm tra SQL injection |

### E. SEO & Content Optimization

| Kỹ năng | Mô tả chi tiết | Câu lệnh sử dụng ví dụ | Khi nào dùng |
| :--- | :--- | :--- | :--- |
| **seo-fundamentals** | Core principles của SEO: E-E-A-T, Core Web Vitals, technical foundations, content quality. | `@seo-fundamentals explain SEO basics` | Hiểu nguyên lý SEO |
| **seo-audit** | Diagnose và audit SEO issues: crawlability, indexation, rankings, organic performance. | `@seo-audit analyze site SEO` | Kiểm tra SEO toàn diện |
| **seo-structure-architect** | Tối ưu cấu trúc HTML, header hierarchy, schema markup, internal linking. | `@seo-structure-architect optimize HTML structure` | Tối ưu cấu trúc SEO |
| **schema-markup** | Design, validate, và optimize schema.org structured data cho rich results. | `@schema-markup add Article schema` | Thêm structured data |
| **seo-content-writer** | Viết SEO-optimized content theo keywords và topic briefs, best practices. | `@seo-content-writer create blog post` | Viết nội dung SEO |
| **programmatic-seo** | Thiết kế programmatic SEO strategies: tạo SEO pages at scale với templates. | `@programmatic-seo design location pages` | SEO pages at scale |

### F. Development Tools, Clean Code & Workflow

| Kỹ năng | Mô tả chi tiết | Câu lệnh sử dụng ví dụ | Khi nào dùng |
| :--- | :--- | :--- | :--- |
| **php-pro** | Chuyên gia PHP: generators, iterators, SPL, modern OOP, high-performance optimization. | `@php-pro refactor complex logic` | Tối ưu PHP code |
| **javascript-pro** | Master ES6+, async patterns, browser/Node compatibility, event loops, promises. | `@javascript-pro optimize async code` | JS nâng cao, async debugging |
| **git-advanced-workflows** | Git nâng cao: rebasing, cherry-picking, bisect, worktrees, reflog, clean history. | `@git-advanced-workflows clean commit history` | Quản lý Git phức tạp |
| **code-reviewer** | Elite code review expert: AI-powered analysis, security scanning, performance optimization. | `@code-reviewer review pull request` | Review code chất lượng cao |
| **typescript-expert** | TypeScript expert: type-level programming, performance optimization, monorepo management. | `@typescript-expert fix type issues` | TypeScript advanced |
| **cc-skill-coding-standards** | Tiêu chuẩn lập trình phổ quát, bao gồm các pattern tốt nhất cho Frontend. | `@cc-skill-coding-standards audit source code` | Đảm bảo chuẩn code chung |
| **frontend-dev-guidelines** | Quy tắc frontend hiện đại, cấu trúc mã nguồn dễ bảo trì và mở rộng. | `@frontend-dev-guidelines setup component` | Thiết lập kiến trúc Frontend |
| **clean-code** | Áp dụng nguyên lý Clean Code, giúp mã (đặc biệt CSS/SCSS) dễ đọc, tránh specificity issues. | `@clean-code refactor scss code` | Dọn dẹp, refactor mã nguồn |
| **error-handling-patterns** | Xử lý lỗi graceful: exceptions, Result types, error propagation, degradation. | `@error-handling-patterns implement fallback` | Xử lý lỗi widget/block |
| **i18n-localization** | Đa ngôn ngữ: detecting hardcoded strings, locale files, RTL support, translation. | `@i18n-localization check hardcoded strings` | Hỗ trợ đa ngôn ngữ |
| **mobile-design** | Mobile-first design doctrine: touch interaction, performance, platform conventions, offline. | `@mobile-design audit mobile UX` | Thiết kế mobile-first |

#### 🏛️ Kiến thức Chuyên sâu về CSS/SCSS Architecture
Ngoài các module trên, Agent được trang bị kiến thức sâu rộng về các chuẩn CSS để duy trì mã nguồn Frontend bền vững:
- **BEM (Block Element Modifier):** Phương pháp đặt tên class giúp tránh xung đột và dễ hiểu cấu trúc HTML (VD: `.block__element--modifier`).
- **Sass 7-1 Pattern:** Cấu trúc tổ chức file SCSS chuyên nghiệp chia theo các thư mục (`abstracts`, `components`, `layout`, `pages`,...) dành cho dự án quy mô lớn.
- **CSS Architecture (ITCSS, SMACSS):** Các nguyên lý thiết kế hệ thống CSS giúp quản lý độ ưu tiên (specificity) rành mạch và tăng khả năng tái sử dụng của module. Khuyến cáo khắt khe: **KHÔNG lồng (nest) code SCSS quá 3 cấp**.

### G. Hướng dẫn Sử dụng Chi tiết

#### 🎯 Workflow Đề xuất

**1. Bắt đầu Dự án / Feature Mới:**
```
@wordpress-theme-development create custom post type "Projects"
@ui-ux-pro-max design project listing page
@tailwind-design-system setup component variants
```

**2. Tối ưu Performance (Hàng tuần):**
```
@wp-performance analyze database queries
@performance-engineer check Core Web Vitals
@database-optimizer optimize slow queries
@web-performance-optimization audit bundle size
```

**3. Kiểm tra Bảo mật (Trước khi Deploy):**
```
@security-auditor audit new features
@wordpress-penetration-testing scan vulnerabilities
@xss-html-injection test user inputs
@sql-injection-testing verify database queries
```

**4. SEO Audit (Sau khi Launch):**
```
@seo-audit analyze entire site
@seo-structure-architect optimize meta tags
@schema-markup validate structured data
@accessibility-compliance-accessibility-audit check WCAG
```

**5. Code Review (Trước PR):**
```
@code-reviewer review changes
@php-pro optimize PHP logic
@react-best-practices check React patterns
@git-advanced-workflows prepare clean commits
```

#### 📋 Checklist Tổng hợp (Trước khi Deploy Production)

- [ ] **Performance:** `@wp-performance` + `@web-performance-optimization` - Core Web Vitals đạt chuẩn
- [ ] **Security:** `@security-auditor` + `@wordpress-penetration-testing` - Không có lỗ hổng critical
- [ ] **SEO:** `@seo-audit` + `@schema-markup` - Meta tags và structured data đầy đủ
- [ ] **Accessibility:** `@accessibility-compliance-accessibility-audit` - WCAG 2.2 Level AA
- [ ] **Code Quality:** `@code-reviewer` + `@php-pro` - Code clean, không có smell
- [ ] **Database:** `@database-optimizer` - Không có N+1 queries
- [ ] **UI/UX:** `@ui-ux-designer` - Responsive trên mọi thiết bị

#### 🔥 Skills Combo Mạnh

**Combo 1: Tối ưu Tốc độ Toàn diện**
```bash
# Bước 1: Phân tích
@wp-performance profile site performance
@performance-engineer analyze Core Web Vitals

# Bước 2: Tối ưu Database
@database-optimizer fix N+1 queries
@sql-optimization-patterns optimize slow queries

# Bước 3: Tối ưu Frontend
@web-performance-optimization reduce bundle size
@react-best-practices optimize components
```

**Combo 2: Bảo mật Chuẩn Enterprise**
```bash
@security-auditor full security audit
@wordpress-penetration-testing pentest WordPress
@web-security-testing test OWASP Top 10
@xss-html-injection verify XSS protection
@sql-injection-testing verify SQLi protection
```

**Combo 3: SEO Power-up**
```bash
@seo-fundamentals understand SEO basics
@seo-audit diagnose current issues
@seo-structure-architect optimize structure
@schema-markup add rich snippets
@seo-content-writer create optimized content
```

---

## 6. Case Studies & Examples

### ✅ Case Study 1: Contact Form - UX/UI Optimization

**Vấn đề:** Form liên hệ không hoạt động do reCAPTCHA không load, UX kém.

**Giải pháp:** Áp dụng 8 skills đồng thời:
```bash
@ui-ux-pro-max          # Thiết kế UI modern
@frontend-design        # Production-grade interface
@wordpress-theme-development  # Template optimization
@web-performance-optimization # Conditional loading
@security-auditor       # XSS/CSRF protection
@accessibility-compliance-accessibility-audit # WCAG 2.2
@php-pro               # Clean PHP code
@typescript-expert      # Modern JS patterns
```

**Kết quả:**
- ✅ reCAPTCHA conditional loading (chỉ contact page)
- ✅ Client-side validation với inline errors
- ✅ WCAG 2.2 Level AA compliant
- ✅ Professional loading states
- ✅ Multi-layer security (Client + Server + reCAPTCHA)

**Tài liệu chi tiết:**
- 📄 `CONTACT_FORM_IMPROVEMENTS.md` - Technical details
- 📄 `CONTACT_FORM_QUICK_START.md` - Quick guide
- 📄 `FIX_CONTACT_ERROR.md` - Debug guide

---

### ✅ Case Study 2: Page Loader - Smart Caching với 24h Strategy

**Vấn đề:** Page loader stuck, hiển thị mỗi lần load trang → UX kém, annoying.

**Yêu cầu:**
- Lần đầu vào web → Show loader
- Navigate trong site → Dùng Swup, KHÔNG show loader
- Quay lại sau 24h → Show lại loader

**Giải pháp:** Áp dụng 5 skills:
```bash
@web-performance-optimization  # localStorage caching
@ui-ux-pro-max                # Smooth transitions
@frontend-design              # No flash of content
@typescript-expert            # Clean JS logic
@wordpress-theme-development   # Swup integration
```

**Implementation:**
```javascript
// localStorage-based tracking
const HOURS_24 = 24 * 60 * 60 * 1000;
const lastShown = localStorage.getItem('laca_loader_shown');

if (lastShown && (now - parseInt(lastShown)) < HOURS_24) {
    // Skip loader
} else {
    // Show loader + save timestamp
}
```

**Kết quả:**
- ✅ Loader chỉ show lần đầu (1 giây)
- ✅ Swup smooth transitions (không có loader)
- ✅ 99% faster page loads (1s → 0s)
- ✅ No flash of content
- ✅ Professional UX như SPA
- ✅ localStorage auto-cleanup

**Performance Impact:**
```
Trước: 100 page loads = 100 giây wasted
Sau:   100 page loads = 1 giây total
→ 99% improvement! 🚀
```

**Tài liệu chi tiết:**
- 📄 `PAGE_LOADER_FIX.md` - Technical implementation
- 📄 `LOADER_TEST_COMMANDS.md` - Testing cheat sheet

---

## 7. Workflows LacaDev

Các workflow tự động hóa dành riêng cho theme LacaDev, có thể kích hoạt bằng lệnh `/`:

| Workflow | Mô tả | Lệnh kích hoạt |
| :--- | :--- | :--- |
| **create-laca-block** | Tạo mới hoặc chuyển đổi Gutenberg Block chuẩn kiến trúc LacaDev. Bao gồm: block.json, edit.js, render.php, SCSS (BEM), và kiểm tra bảo mật/SEO. | `/create-laca-block` |
| **review-legacy-theme** | Đánh giá, tư vấn và hướng dẫn nâng cấp (refactor) theme WordPress cũ lên chuẩn kiến trúc LacaDev. Audit bảo mật, hiệu năng, CSS Architecture. | `/review-legacy-theme` |

---
Cập nhật lần cuối bởi Antigravity Agent - Extended WordPress Skills Collection (2026-03-06)

**Tổng cộng: 40+ skills chuyên biệt + 2 workflows + 11 WordPress Agent Skills**

