# PLAN-SYNC-2026-11 — Đồng bộ lacadev-client từ LIX, dọn & nâng cấp site chính

Người lập: Claude Code (review) · Người code: Antigravity · Ngày: 2026-11-03
Quy trình: Claude giao **từng phase** → Antigravity code + tự kiểm tra → báo cáo → Claude review → yêu cầu sửa (nếu có) → phase tiếp theo.
**KHÔNG làm vượt phase được giao.** Hết phase thì dừng và báo cáo theo mẫu ở cuối file.

## 0. Ký hiệu đường dẫn

| Ký hiệu | Đường dẫn tuyệt đối | Vai trò |
|---|---|---|
| `LP` | `/Users/lacadev/Documents/wordpress/lixroastery/app/public/wp-content/themes/lacadev-client` | NGUỒN — parent mới nhất (CHỈ ĐỌC) |
| `LC` | `/Users/lacadev/Documents/wordpress/lixroastery/app/public/wp-content/themes/lacadev-client-child` | NGUỒN — child LIX (CHỈ ĐỌC) |
| `CP` | `/Users/lacadev/Documents/wordpress/lacadev-client/app/public/wp-content/themes/lacadev-client` | ĐÍCH — parent template client |
| `CC` | `/Users/lacadev/Documents/wordpress/lacadev-client/app/public/wp-content/themes/lacadev-client-child` | ĐÍCH — child template client |
| `M`  | `/Users/lacadev/Documents/wordpress/lacadev/app/public/wp-content/themes/lacadev` | ĐÍCH — theme site chính lacadev.com (hub) |

Site local: `http://lacadev.local` (M), `http://lacadev-client.local` (CP/CC), `http://lixroastery.local` (nguồn).

## 1. Luật chung (bắt buộc mọi phase)

1. **Không sửa gì trong lixroastery** (`LP`, `LC`) — chỉ đọc/copy ra. Không đụng nhakhoathienphuoc, phucdainam.
2. **Không xoá file** (`rm`, `rm -rf`, `unlink`, `git rm`, `rsync --delete`, `find -delete`). File cần bỏ → liệt kê trong báo cáo mục "ĐỀ XUẤT XOÁ", người dùng tự xoá. Được phép bỏ *code* bên trong file (xoá dòng/hàm) khi phase yêu cầu rõ.
3. Không sửa WP core, `wp-config.php`, `.htaccess`, `plugins/`, `uploads/`. Không sửa tay `dist/`, `build/`, `*.min.*`.
4. Không `yarn add/upgrade`, `npm install <pkg>`, `composer require/update`. Được `yarn install --frozen-lockfile` / `composer install` theo lockfile có sẵn. Nếu thật sự cần package mới → DỪNG, hỏi qua báo cáo.
5. Git: sau Phase 0 mỗi theme có repo riêng. Mỗi phase con = 1 commit (Conventional Commits, tiếng Anh, ví dụ `feat(parent): sync admin ui system from lixroastery`), cuối message thêm dòng `Co-Authored-By: Antigravity`. `git add <file/thư mục cụ thể>`, không `git add -A` bừa. Không push, không reset/clean/rebase.
6. i18n: mọi câu hiển thị qua `__()/esc_html__()/esc_attr__()…`, msgid tiếng Việt, **giữ text domain đang dùng trong file nguồn** (không đổi `lacadev`↔`laca` trong phạm vi plan này). Không ghi cứng mảng câu theo ngôn ngữ. JS lấy chữ qua `data-*` hoặc `wp_localize_script`.
7. Khi copy file từ LIX: **trung tính hoá** mọi chi tiết LIX (tên "LIX/Lix Roastery/XLIII/xliiicoffee", domain `lixroastery.*`, dữ liệu mock cà phê, màu `#3D5A45`, font Oswald/VNF-Futura/SFUFutura, post type `journal`/`product` hard-code). Thay bằng: ví dụ chung ("Tên website", `example.com`), lấy `get_bloginfo('name')`/`home_url()` khi là fallback runtime, hoặc xoá mock.
   Lệnh kiểm tra bắt buộc cuối mỗi phase (phải ra rỗng, trừ các mục ghi chú được chấp nhận):
   `grep -rniI -e lix -e roastery -e xliii -e "VNF-Futura" -e Oswald <thư-mục-đích> --exclude-dir=node_modules --exclude-dir=vendor --exclude-dir=dist --exclude-dir=build --exclude-dir=.git`
8. Kiểm tra sau mỗi phase:
   - `php -l` cho MỌI file PHP đã thêm/sửa (`git diff --name-only HEAD~1 -- '*.php' | xargs -n1 php -l`).
   - Build (`yarn build` ở thư mục build của theme đó) phải thành công, dán 20 dòng cuối output.
   - `curl -s -o /dev/null -w "%{http_code}" <site>/` và `<site>/wp-login.php` (hoặc slug login custom) = 200/302; mở `wp-admin` bằng trình duyệt, chụp màn hình Dashboard + 1 trang Laca Admin; xem `app/public/wp-content/debug.log` (nếu có) — không có Fatal/Warning mới.
   - Không để `yarn dev`/watcher chạy nền.
9. Gặp tình huống plan không nói tới → không đoán; ghi vào mục "CÂU HỎI" của báo cáo và dừng ở điểm an toàn.

---

## PHASE 0 — Sao lưu bằng git (người dùng đã duyệt)

0.1 `cd CP/..` (= `/Users/lacadev/Documents/wordpress/lacadev-client/app/public/wp-content/themes`) → `git init`, tạo `.gitignore`:
```
node_modules/
.DS_Store
*.log
releases/
```
(vendor/ và dist/ ĐƯỢC track vì đang deploy kèm). `git add lacadev-client lacadev-client-child index.php .gitignore` → commit `chore: baseline snapshot of lacadev-client themes`.
0.2 `cd M` → `git init`, `.gitignore` tương tự (thêm `releases/`), `git add .` (đã ignore node_modules) → commit `chore: baseline snapshot of lacadev theme`.
0.3 Báo cáo: `git log --oneline` + `git count-objects -vH` của 2 repo.

---

## PHASE 1 — Đồng bộ parent `CP` ← `LP` (yêu cầu 1, phần parent)

Parent là framework chung, copy gần nguyên vẹn rồi trung tính hoá.

1.1 Copy (không xoá gì ở đích):
```
rsync -av --exclude '.DS_Store' --exclude 'node_modules' --exclude '.git' \
  --exclude 'doc/laca-seo/tools/head-golden' \
  LP/ CP/
```
1.2 KHÔNG giữ các stub preview chết — vì không được xoá file, xử lý như sau: các file `app/src/Settings/LacaAdmin/UiPreview.php`, `DashboardPreviewPage.php`, `resources/scripts/admin/ui-preview.js`, `resources/styles/admin/preview/`, `resources/images/ui-preview/` → KHÔNG copy (thêm `--exclude` tương ứng vào lệnh rsync ở 1.1). Xác nhận không file nào `require/use/import` chúng.
1.3 File chỉ có ở `CP` (LP không có): `block-gutenberg/ai-translate-plugin/`. Kiểm tra còn được tham chiếu không (`grep -rn ai-translate-plugin CP LC CC`). Nếu đã được thay bằng `resources/scripts/editor/ai-translate-block.js` → ghi vào ĐỀ XUẤT XOÁ, không xoá.
1.4 Trung tính hoá (trong `CP`, không sửa LP):
   - `app/src/Features/GoogleServices/GuideContent.php:23` fallback `'lixroastery.com'` → `'example.com'`; dòng ~115 ví dụ tên project → `"Tên website của bạn"`.
   - `app/src/Features/GoogleServices/GoogleDashboardService.php` (~504-736), `GaClient.php` (~894), `EntityStats.php` (~569): dữ liệu mock LIX → thay bằng mock trung tính (từ khoá "dịch vụ thiết kế website", URL `/gioi-thieu/`, tiêu đề "Trang chủ | " . get_bloginfo('name')). Nếu mock chỉ dùng cho chế độ demo, giữ cấu trúc, đổi nội dung.
   - `EntityStats.php:17` `SUPPORTED_POST_TYPES` có `journal` → đổi thành lấy động: `post`, `page` + mọi CPT public (`get_post_types(['public'=>true])` trừ `attachment`), có filter `laca_google_entity_post_types`.
   - `app/src/Features/LacaSeo/Core/Variables.php` (~723-750), `Fields/TitlesFields.php` (dòng ~92,173,183,242,658), `Fields/GeneralFields.php` (~411,1069): ví dụ "Lix Roastery"/"Cà phê Robusta" → "Tên website"/"Dịch vụ thiết kế website".
   - `resources/scripts/admin/laca-seo/analyzer.js:~141` fallback `lixroastery.local` → `window.location.hostname`; `editor.js:~433` fallback `'LIX ROASTERY'` → lấy từ dữ liệu localize (site name) — nếu chưa có thì thêm `siteName: get_bloginfo('name')` vào `wp_localize_script` tương ứng trong `LacaSeo/Admin/Metabox/PostMetabox.php`.
   - `app/src/Features/ContactForm/ContactFormManager.php:~548` placeholder email LIX → `info@example.com, hr@example.com`; `resources/scripts/admin/contact-form.js:~1976` fallback `'Lix Roastery'` → tên site qua localize.
   - `resources/styles/admin/_list-table-inline.scss:~702-707` bỏ rule `column-laca_variety_cat`, `column-laca_product_brand` (chuyển sang child nếu LIX cần — KHÔNG làm, chỉ ghi chú cho người dùng).
   - `doc/` copy sang được giữ làm tài liệu; KHÔNG cần trung tính hoá doc (ghi rõ trong báo cáo grep).
1.5 `app/src/PostTypes/Concerns/BlockSyncSender.php` trong parent client là bản sao chết (683 dòng, không class nào dùng). Kiểm tra lại bằng grep `BlockSyncSender` trong CP/CC; nếu đúng không dùng → ĐỀ XUẤT XOÁ.
1.6 `composer install` trong `CP` (theo composer.lock mới copy sang) — chỉ chạy nếu `composer.json` CP có `vendor` cần cập nhật; so `vendor/composer/installed.json` CP vs LP, nếu khác thì chạy.
1.7 Không build ở phase này (parent không có package.json — được build từ child ở Phase 2). Chỉ `php -l` toàn bộ `CP/app`, `CP/theme`.
1.8 Commit: `feat(parent): sync lacadev-client parent with lixroastery latest`.

---

## PHASE 2 — Gộp child `CC` ← `LC` có chọn lọc (yêu cầu 1, phần child)

`CC` là TEMPLATE GỐC cho mọi client → chỉ lấy phần dùng chung + module Newsletter (người dùng đã chọn). KHÔNG lấy WooCommerce, journal, glossary, product, font/favicon/brand LIX. GIỮ NGUYÊN mọi block/utils/template hiện có của CC (block-featured-projects, journey-gallery, projects-slider, services-grid, stats-counter, team-carousel, team-leaders, video-feedback, lacadev-blocks-site/, nhakhoathienphuoc-blocks-site/, utils/icon-*, utils/post-source-*, RULE_BLOCK_GUTENBERG.md, archive-project/gallery/pdn_tv, single-project).

2.1 Build tooling (copy từ LC đè CC):
   - `resources/build/webpack/externals.js`, `webpack.development.js`, `webpack.production.js`, `webpack.blocks.js`, `resources/build/lib/parent-path.js`, `resources/build/webpack/resolve.js` (nếu khác).
   - `resources/build/webpack/entry.js`: copy; sửa comment nhắc Oswald/Afacad thành chung chung.
   - `resources/build/critical.js`: copy rồi trung tính hoá: fallback `http://lixroastery.local` → đọc `config.json` (`development.url`); các URL mẫu LIX (`/product/...`, `/journal/...`, `/manifesto/`) → chuyển thành mảng `critical.pages` đọc từ `config.json` (thêm key này vào `config.json` + `config.json.dist` của CC, mặc định chỉ `home`, `single` (bài mới nhất), `page`); bỏ rule `VNF-Futura` hard-code. Các loại shop/product/journal chỉ chạy khi có cấu hình.
   - `package.json` + `yarn.lock`: copy từ LC (deps mới đều là @wordpress/* + sortablejs, scripts không đổi). Giữ `name`/`description` của CC nếu khác. Sau đó `yarn install --frozen-lockfile`.
   - GIỮ `config.json`, `config.json.dist` (chỉ thêm key critical ở trên), `.gitignore` của CC.
2.2 `block-gutenberg/utils`: copy `inspector-panels.js`, `preview.js`, `google-fonts-vi.js` từ LC (đều tương thích ngược). KHÔNG đụng utils chỉ có ở CC.
2.3 `app/helpers` — copy từ LC: `layout-mode.php`, `block-image-render.php`, `google-fonts-vi.php`, `taxonomy-helpers.php` (sửa docblock nhắc journal), `ajax-pagination-markup.php`.
   KHÔNG copy: `journal-*`, `mini-cart-render.php`, `product-grid-render.php`, `single-product-related-render.php`, `list-table-fields.php`, `cpt-grid-render.php`, `top-hero-helpers.php` (chỉ copy nếu sau này lấy block top-hero — lần này KHÔNG).
   ⚠ Vì không có `top-hero-helpers.php`, mọi chỗ gọi `laca_page_starts_with_top_hero()` khi gộp `page.php`/`header.php` phải bọc `function_exists()`.
2.4 `theme/setup` — copy: `page_meta.php` (sửa comment glossary), `menus.php` (sửa docblock), `language-switcher.php` (sửa comment xliiicoffee), `assets.php` (bản LC: filemtime cache-busting, bỏ hunk stats-counter chết).
   KHÔNG copy: `product_meta.php`, `product_taxonomy.php`, `journal_*`, `glossary_meta.php`.
2.5 `theme/setup/theme-options.php` — gộp TỪNG HUNK:
   - LẤY: field `logo_white`; tab "Giao diện" (`layout_container_mode` fluid/boxed); tab "Footer" (tên công ty, ĐKKD, cột link, badges, DMCA); các AI key mở rộng (OpenRouter, Cloudflare, Mistral, Cohere, NVIDIA); tab Newsletter (xem 2.10, đổi tiền tố).
   - BỎ: tab Journal, tab "☕ Product Page", giá trị mặc định stats LIX, heredoc Apps Script nếu có chữ LIX (giữ nếu là code chung cho Newsletter → trung tính hoá).
   - GIỮ của CC: `primary_color`, `secondary_color`, `bg_color` (header.php và SCSS dùng), các field footer-contact.
   - Không gọi `NewsletterTexts::defaults()` ở đầu file nếu module chưa nạp → bọc `class_exists`.
2.6 `theme/functions.php`: lấy `laca_child_load_textdomain()` + hook `change_locale` (thay `load_child_theme_textdomain`); thêm require `menus.php`, `language-switcher.php`, `page_meta.php` (theme-options.php đã được parent include). BỎ require product/glossary/journal.
2.7 `app/hooks.php` — lấy các hunk chung: require các helper ở 2.3; inline CSS `block-block-*` qua `path` + `styles_inline_size_limit`; fix CLS Accordion core; filter critical-CSS theo loại trang (bỏ/guard nhánh journal/product/shop); jQuery xuống footer/defer + guard `is_cart/is_checkout` bằng `function_exists`; dequeue `comment-reply` khi không cần; bỏ emoji. BỎ: kk-star-ratings, enqueue product-grid/cpt-grid, khối dequeue WooCommerce + async handle Woo, preload font VNF-Futura/Oswald, các handler PDN bị comment. GIỮ comment "child does not auto-update" của CC.
2.8 Template:
   - Copy từ LC: `theme/search.php`, `theme/template-parts/page-hero.php`, `theme/page_templates/template-contact.php` (đọc kỹ markup trước, trung tính hoá).
   - `template-parts/breadcrumb.php`, `comment-single.php`, `post-hero.php`, `share_box.php` — bản LC chỉ thêm i18n/`hide_breadcrumb`/`laca_seo_breadcrumbs`: **đưa vào parent `CP/theme/template-parts/`** (đè bản parent sau khi so sánh để không mất gì), KHÔNG tạo override trong child.
   - `theme/page.php`: lấy bản LC (breadcrumb trừ khi top-hero; `.entry-content`) — guard `function_exists('laca_page_starts_with_top_hero')`.
   - `theme/archive.php`: lấy fallback `laca_seo_breadcrumbs`, bỏ config AJAX chết, khởi tạo `$cat_link`; gọi `laca_render_dynamic_cpt_archive_intro()` có guard `function_exists`.
   - `theme/single.php`: giữ layout chung của CC (hero, sidebar, related, share/rating), thay `theContent()` bằng `the_content()` (để không mất `<style>` block), BỎ hộp form "Liên hệ tư vấn" hard-code kiểu PDN.
   - `theme/header.php`: thêm class `header--hero-overlay` (guard function_exists), logo kép default/white có width/height, markup search popup (`#btn-search-open`, `#header-search`), slot `header-right-menu`, language switcher, skip-link. KHÔNG thêm giỏ hàng/mini-cart.
   - `theme/footer.php`: thêm markup floating-contact (back-to-top/phone/Zalo, lấy số từ theme options), badges/DMCA, newsletter partial (2.10). Bỏ chữ kiểu PDN ("Showroom & nhà máy", "Dự án tiêu biểu") → lấy từ theme options hoặc câu chung qua `__()`. Form footer phải có `data-msg-success/-error/-network` (dùng `esc_attr__`) để khớp `footer-contact-form.js` mới.
   - `theme/style.css`: chỉ lấy header `Text Domain`/`Domain Path`; bỏ CSS footer `#F0C11B`.
   - `theme/languages/`: tạo `laca.pot` mới cho CC (WP-CLI không có trên máy → dùng Loco Translate trên lacadev-client.local hoặc ghi chú để người dùng chạy). KHÔNG copy `.po/.mo` LIX, KHÔNG copy `theme/theme/languages/` (thư mục đặt sai).
2.9 Scripts & SCSS:
   - Copy `resources/scripts/theme/components/`: `sticky-header.js`, `search-popup.js`, `share-popup.js`, `floating-contact.js`, `footer-contact-form.js` (sửa comment xliiicoffee). KHÔNG copy cart-popup, single-product-nav, journal-*, glossary-*, hero-effects, cpt-grid.
   - `resources/scripts/theme/index.js`: GIỮ import `stats-counter`; thêm sticky-header, share-popup, floating-contact, search-popup; nối `initMobileMenu`/`initSearchPopup` như LC.
   - SCSS copy: `abstracts/_mixin.scss` (container-inset/gutter), `components/_breadcumb.scss`, `components/_contact-form.scss`, `layout/_floating-contact.scss`, `pages/_search-results.scss`. Trong `abstracts/_variables.scss` của CC THÊM alias để partial biên dịch được: `$darkColor`, `$lightColor`, `$errorColor`, `$subFont` trỏ tới biến/CSS var hiện có của CC (không dùng màu/font LIX).
   - `layout/_general.scss`: lấy hệ layout-mode (`--laca-container-max`, `.laca-layout-boxed`, `.is-layout-*`, `.container/.container-fluid` + `container-gutter`, fix container lồng, gutter/alignwide/alignfull của `.entry-content`, separator 1px). KHÔNG lấy typography thương hiệu (heading uppercase, cỡ h1–h3 cố định, `line-height:1.7`, padding `body:not(.home)`). Giữ `background-color: $bgColor`.
   - KHÔNG lấy các file LC đã làm rỗng: `_pagination.scss`, `_archive.scss`, `_single-post.scss`, `pages/_contact.scss`, `_common-blocks.scss` — giữ bản CC.
   - `child.scss`: thêm `contact-form`, `floating-contact`, `search-results`, `newsletter`.
   - KHÔNG copy font (Oswald/VNF-Futura/SFU), favicon, icon-cart, review-stars, screenshot.png của LC.
2.10 Newsletter (người dùng đã chọn) — copy `app/src/Newsletter/*`, `app/src/Ajax/NewsletterAjaxHandler.php`, `theme/template-parts/newsletter-form.php`, `resources/scripts/theme/components/newsletter-form.js`, `resources/styles/theme/layout/_newsletter.scss`, rồi:
   - Đổi TOÀN BỘ tiền tố `lix_` → `laca_`, slug `lix-newsletter` → `laca-newsletter`, action AJAX/nonce `lix_newsletter_*` → `laca_newsletter_*`, option `lix_newsletter_*` → `laca_newsletter_*`, chuỗi auth Brevo `'lix:'` → `'laca:'`. Đồng bộ JS ↔ PHP.
   - Thêm công tắc bật/tắt trong theme options (mặc định TẮT); khi tắt: không đăng ký menu admin, không enqueue JS, không render form.
   - Khởi tạo module ở chỗ LC đang khởi tạo (tìm trong `LC/app/hooks.php`/`functions.php`), bọc theo công tắc.
   - Câu chữ mặc định (`NewsletterTexts`) phải qua `__()` trong hàm, không mảng ngôn ngữ cố định.
2.11 Build & kiểm tra: `cd CC && yarn install --frozen-lockfile && yarn build` (build cả admin/laca-seo của parent). `php -l` mọi file PHP thay đổi trong CC và CP. Bật site lacadev-client.local, kiểm tra: trang chủ, 1 bài viết, 1 trang, trang tìm kiếm, 404, wp-admin Dashboard, Laca Admin, Laca SEO, Theme Options (tab Giao diện, Footer, Newsletter), Editor Gutenberg mở 1 block cũ của CC (vd team-carousel) không lỗi "This block contains unexpected content".
2.12 Commit 2 lần: `feat(child): merge generic improvements from lixroastery` và `feat(child): add optional newsletter module`.

---

## PHASE 3 — Sửa liên kết hub ↔ client + dọn site chính (yêu cầu 2)

### 3A. Sửa lỗi liên kết (bắt buộc)
3A.1 **Route block files (lỗi chặn):** Hub `M/app/src/PostTypes/Concerns/BlockSyncSender.php:~622` gọi `{catalogUrl}/files?block=X`, còn provider client chỉ có `/blocks-catalog/(?P<block>…)/files`. Sửa PHÍA HUB thành `rtrim($catalogUrl,'/') . '/' . rawurlencode($blockName) . '/files'`. Đồng thời PHÍA CLIENT (`CP/app/src/Settings/BlockCatalogProvider.php`) đăng ký thêm route tương thích `/blocks-catalog/files` nhận `?block=` để các hub cũ vẫn chạy. Kiểm tra tên block dạng `bucket/block` — nếu catalog trả tên có `/`, hub phải encode và provider phải hỗ trợ (regex `[a-zA-Z0-9_\-\/]+` + chống path traversal).
3A.2 **Heartbeat:** `CP/app/src/Settings/LacaDevTrackerClient.php` thêm cron `twicedaily` gửi `type=heartbeat` (payload: site_url, wp_version, php_version, theme versions parent/child) về tracker. Kiểm tra hub `TrackerEndpointHandler` cập nhật `_tracker_last_seen_*` và card "Tracker heartbeat" ở `M/app/src/PostTypes/project.php:~186` hiển thị.
3A.3 **update_pending:** client gửi thêm mảng `plugins[]` có cấu trúc (slug, name, current, new) giống payload của mu-plugin `ProjectTrackerGenerator`, để `RemoteAjaxHandler::getPendingUpdates` ở hub có dữ liệu. Đọc cả 2 phía để payload khớp 100%.
3A.4 **Kiểm tra key catalog:** `M/app/src/.../BlockCatalogHub.php` tra key phải thêm điều kiện `post_status = 'publish'` như TrackerEndpointHandler. Chấp nhận key từ header `X-Laca-Key` (ưu tiên) và vẫn nhận `?secret_key=` cho tương thích; client (`CP/app/src/Settings/BlockMarketplace.php::fetchFromHub`) chuyển sang gửi bằng header.
3A.5 **Cập nhật theme client:**
   - `CP/theme-server/lacadev-client.json`: sửa name "LacaDev Client", `download_url` → `https://clients.lacadev.com/theme-updates/lacadev-client-<ver>.zip`, slug đúng. `lacadev-child.json` giữ cho child.
   - `CP/build-release.sh`: host thống nhất `https://clients.lacadev.com/theme-updates/` (đúng với `hooks.php` đang đọc); zip phải có thư mục gốc `lacadev-client/` (không phải `lacadev-client-build/`), hướng dẫn upload đúng host.
   - `CP/app/src/Settings/ThemeUpdater.php`: cache lỗi bằng giá trị đánh dấu (vd `['error'=>true]` 1 giờ) thay vì `false`; thêm filter `upgrader_source_selection` đổi tên thư mục giải nén về `lacadev-client` nếu khác. Ghi chú trong báo cáo: style.css nằm ở `theme/` — kiểm tra cấu trúc zip có được Theme_Upgrader chấp nhận không (WP cần style.css ở gốc thư mục theme; theme này dùng cơ chế WPEmerge `theme/` — xác minh cách CP đang khai báo `Template`/stylesheet dir và mô tả kết luận, KHÔNG tự đổi cấu trúc thư mục).
3A.6 Dọn rác liên kết ở hub: `M/app/hooks.php:~256` meta `_tracker_endpoint` sai route & không ai đọc → bỏ dòng ghi; `M` cài bảng 2 lần (`theme/functions.php:~275-277` mỗi request + `DbVersionManager`) → bỏ lời gọi ở functions.php, giữ DbVersionManager.
3A.7 `remoteUpdate` URL hard-code `/wp-json/` → dùng `?rest_route=` dự phòng khi 404.

### 3B. Dọn dẹp site chính (người dùng đã chọn cả 4 nhóm)
Với mỗi mục: grep xác nhận không còn tham chiếu → bỏ phần NẠP (require/new/add_action) + bỏ code trong file dùng chung; **file riêng lẻ không còn dùng → ghi vào ĐỀ XUẤT XOÁ, không xoá**.
3B.1 Code chết: `app/src/Settings/Updater.php`; `Contracts/HookNames.php`; `Abstracts/AbstractTaxonomy.php`; `Databases/AbstractTable.php`; `Validators/Validator.php`; `Widgets/Carbon_Rich_Text_Widget.php`; `Module/ModuleInterface|ModuleLoader|ModuleServiceProvider` (+ dòng đăng ký trong `app/config.php`); `theme/setup/sidebars.php`; `theme/setup/users/user-meta.php`; `theme/setup/blocks/{about,blog,service,slider}.php` (+ dòng comment ở functions.php:~157); `app/routes/*.php` rỗng (kiểm tra WPEmerge config có bắt buộc file routes không — nếu bắt buộc thì GIỮ); `resources/scripts/admin/dashboard-search.js` (laca-2fa.js: XEM Phase 4B — gói bảo mật dùng lại thì giữ); `ClientWebhook` (`laca/v1/client-report`, bỏ đăng ký ở `app/hooks.php`). Composer deps không dùng (`guzzlehttp/guzzle`, `jenssegers/agent`, `sunra/php-simple-html-dom-parser`, `phpoffice/phpspreadsheet`, `illuminate/validation`, `illuminate/translation` nếu chỉ Validator dùng): **chỉ liệt kê, KHÔNG chạy composer remove** — người dùng tự quyết. Sửa metadata composer.json: name `lacadev/lacadev-theme`, bỏ keyword "travel", bỏ `autoload-dev Tests\` trỏ thư mục không tồn tại (sửa composer.json không cần chạy update; sau đó `composer dump-autoload`).
3B.2 Gộp form liên hệ: giữ `Features/ContactForm/*`; bỏ `lacadev_handle_contact_submit` + 2 `add_action` ở `M/app/helpers/ajax.php:~439-444` (và phần reCAPTCHA trùng trong đó); chuyển form trong `theme/page_templates/template-contact.php` sang shortcode/handler của ContactForm (field tương đương, nonce `laca_contact_submit_nonce`, `_nonce`). Kiểm tra gửi thử cả form shortcode và trang liên hệ → bản ghi vào bảng ContactForm + email (EmailLog) OK.
3B.3 Gộp trùng lặp: (a) security: giữ `LacaTools/Security.php` làm nơi có toggle; bỏ phần trùng (xmlrpc, generator, …) trong `theme/setup/security.php`, giữ header bảo mật nếu Security.php không có; bộ đếm `login_attempts_` cũ ở `setup/security.php:~78` → bỏ khi Phase 4B đã có LoginGuard (đánh dấu TODO, làm ở 4B). (b) service worker: chỉ đăng ký ở `setup/performance.php`, bỏ ở `LacaTools/Optimize.php:~181`. (c) reCAPTCHA: chỉ dùng `Laca_Recaptcha` (`setup/recaptcha.php`).
3B.4 Tính năng thừa: bỏ nạp `AutoDownloadImage` (rủi ro SSRF); bỏ nạp `app/helpers/woocommerce.php`; thêm công tắc bật/tắt cho `MobileStickyCta` trong Laca Admin (mặc định GIỮ trạng thái hiện tại = bật).
3B.5 Kiểm tra: php -l, `yarn build` trong M, curl + duyệt lacadev.local (trang chủ, liên hệ, 1 project, wp-admin, Laca Projects hub, Block Catalog). Commit tách: `fix(sync): ...` (3A), `refactor: remove dead code ...` (3B.1), `fix(contact): ...` (3B.2), `refactor(security): ...` (3B.3), `chore: ...` (3B.4).

---

## PHASE 4 — Port admin UI & chức năng từ LIX sang site chính (yêu cầu 3)

Cùng namespace `App\` (PSR-4 `app/src/`), không cần đổi namespace. Text domain giữ như nguồn. Nguồn = `CP` (đã đồng bộ & trung tính hoá ở Phase 1 — **copy từ CP, không từ LP**). File đã phân nhánh nhiều ở M (ContactForm*, contact-form.js, AdminSettings, LacaAdminMenuOrganizer, SecurityManager, assets.php, admin/index.js, hooks.php) → **gộp bằng tay, giữ toàn bộ chức năng riêng của M** (Project hub, Global Alerts, ProjectReports…).

### 4A. Admin UI lõi
- Copy nguyên: `resources/styles/admin/_tokens.scss`, `components/*.scss`, `skins/_wp-chrome|_wp-core|_carbon|_gutenberg-inspector|_laca-dock|_self-authored.scss`, `_list-table-inline.scss`, `_admin-column-style.scss`, `_admin-help.scss`; `resources/scripts/admin/laca-autosave.js`, `carbon-autosave.js`, `list-table-inline.js`, `taxonomy-collapsible-terms.js`; `app/src/Settings/LacaAdmin/UiKitPage.php`, `LacaAdminTabBar.php`; `app/src/Settings/LacaTools/Management/ListTableInlineEditor.php`; `app/src/Features/DynamicCPT/CollapsibleTaxonomyTerms.php`.
- Gộp: `resources/styles/admin/index.scss` (thêm tokens/components/skins/list-table-inline, giữ `_project.scss`); `_admin-custom|_admin-dashboard|_contact-form.scss` (thay `--primary-color-ad` bằng token, giữ rule riêng M); `resources/styles/login/index.scss`.
- `_self-authored.scss` chỉ có tác dụng khi markup PHP dùng class G6. Với các trang M có cùng tên ở CP (SecurityManager, TwoFactorAuth, ContactForm, EmailLog, DatabaseCleaner, DashboardWidgets, DynamicCPT) → gộp phần markup class hoá (bỏ `style=""` inline) giữ logic M. Các trang riêng M (Project hub ~14 trang, Global Alerts, ProjectReports, Media) → đổi inline style sang class/token theo cùng hệ component (`.laca-card`, `.laca-btn`, `.laca-table`, `.laca-badge`, …) — xem UiKitPage làm chuẩn. Đây là phần lớn nhất, làm theo từng trang và chụp ảnh trước/sau.
- `LacaAdminMenuOrganizer.php`: bỏ `printStyles()` inline (dùng `_laca-dock.scss`), gọi tab bar như CP, GIỮ nhóm project/management và slug `laca-management-settings`, `laca-dynamic-cpt` của M. `LacaAdminTabBar::TAB_GROUPS`: bỏ slug chỉ có ở client (`laca-block-sync`, `lacadev-block-categories`, `laca-tracker`), thêm nhóm Projects của M.
- `AdminSettings.php` (M): bỏ `applyAdminColorVariables()` + tab "ADMIN COLOR"; thêm `getSuperUserLogins()` (public static) + nhóm extra-super-user (`restrictExtraSuperUserAccess`, `isExtraSuperUser`, `getExtraSuperUserDenied*`, option `laca_extra_super_user_logins`) y như CP; nonce cho AJAX `mm_get_attachment_url_thumbnail`. Đảm bảo `isSuperUser()` cũ vẫn hoạt động (gọi qua hàm mới).
- `theme/setup/assets.php` (M): bỏ inline CSS `--*-color-ad` (~252-276); `lacaProjectCharts.primary` = `'#18181b'`; giữ `Theme::uri()`/AssetHandles. `ProjectReportsManager.php:~33` đọc `primary_color_ad` → `#18181b`.
- `resources/scripts/admin/index.js` (M): thêm import + init `carbon-autosave`, `list-table-inline` (release-checklist ở 4C); giữ project, ai-chat, project-charts, …
- `app/hooks.php` (M): handler autosave JSON trên `carbon_fields_theme_options_container_saved` (CP `app/hooks.php:~152-163`); đăng ký UiKitPage (`init`, is_admin). `ManagementExperience.php` (M): thêm ListTableInlineEditor, CollapsibleTaxonomyTerms giống CP.
- Thumbnail markup `AbstractPostType.php`/`DynamicCptManager.php`: gộp thay đổi nhỏ của CP.
- Commit: `feat(admin): port token-based admin ui from lacadev-client`.

### 4B. Gói bảo mật
- Copy: `app/src/Helpers/ClientIp.php`, `Settings/Security/LoginGuard.php`, `UserEnumerationGuard.php`, `CoreInfoFileCleanup.php`, `Settings/LoginLog/LoginLogManager.php`, `LoginLogTable.php`, `resources/scripts/admin/laca-2fa.js` (nếu TwoFactorAuth bản CP dùng nó).
- Đăng ký: `app/hooks.php` (`after_setup_theme` ưu tiên 15 cho 3 guard; `init` cho LoginLog như CP); `LoginLogTable::install()` qua DbVersionManager của M (không gọi mỗi request).
- Gộp `SecurityManager.php` (tab Super User, markup class) và `TwoFactorAuth.php` từ CP vào M, giữ FileIntegrityMonitor/MalwareScanner/HiddenUserScanner/SecurityAudit của M.
- Bỏ limiter `login_attempts_` cũ ở `M/theme/setup/security.php:~78` (tránh đếm 2 lần). `SecurityAudit.php:~127` kiểm `laca_block_author_enum` → coi là luôn đạt khi UserEnumerationGuard nạp.
- Kiểm tra: đăng nhập sai 5 lần từ trình duyệt ẩn danh → bị khoá; `/?author=1` bị chặn; trang Login Log có bản ghi. **Không tự khoá chính mình**: whitelist IP 127.0.0.1 qua filter `laca_login_guard_whitelist` trên local.
- Commit: `feat(security): add login guard, user enumeration guard and login log`.

### 4C. DiscordHub + Release Checklist
- Copy `app/src/Features/DiscordHub/**`, đăng ký `after_setup_theme` 20 bọc try/catch như CP; thêm `laca-discord-hub` vào menu organizer/tab bar.
- Copy `Settings/LacaAdmin/ReleaseChecklist.php` + `resources/scripts/admin/release-checklist.js` (import trong admin/index.js). Điều chỉnh mục kiểm tra cho site chính: bỏ/ẩn mục WooCommerce, `_primary_color_ad`; giữ LiteSpeed/Laca SEO/DNS/login slug/superuser.
- Commit: `feat(admin): add discord hub and release checklist`.

### 4D. Laca SEO + block SEO + Google Services
- Copy `app/src/Features/LacaSeo/**`, `resources/scripts/admin/laca-seo/*`, `resources/styles/admin/laca-seo/*`, `resources/data/laca-seo/**`, `block-gutenberg/block-seo-{faq,howto,toc}/`; `app/src/Features/GoogleServices/**`, `Settings/LacaAdmin/GoogleServicesPage.php`, `resources/scripts/admin/google-dashboard-report.js`; `app/src/Helpers/PolylangLanguages.php` (M có Polylang Pro). Doc: `doc/laca-seo/` (bỏ `tools/head-golden`), `doc/GOOGLE-SERVICES-GUIDE.md`.
- Đăng ký: `LacaSeo::boot()`, `GoogleServices::boot()` như CP `app/hooks.php`. Gỡ nạp SEO cũ của M (`theme/setup/seo.php` nếu M có và trùng) — kiểm tra M đang xuất meta/OG/schema ở đâu để KHÔNG in trùng thẻ `<title>`, meta description, OG, JSON-LD.
- Hàm rank-math alias + đăng ký block SEO ở `theme/setup/gutenberg-blocks.php` (CP ~900-950) → gộp vào M; đảm bảo category `lacadev-blocks` tồn tại.
- Build M (`M/resources/build/`):
  - `webpack/entry.js`: thêm entry `laca-seo` (`admin/laca-seo/index.js` + `styles/admin/laca-seo/index.scss`) và `laca-seo-editor` (`editor.js` + `editor.scss`).
  - `webpack/externals.js`: map `@wordpress/*` → `wp.*` như CP (để không cần cài thêm package). Nếu code import package không phải @wordpress (vd `sortablejs`) mà M chưa có trong yarn.lock → DỪNG, hỏi.
  - `webpack.production.js`: M dùng `splitChunks: chunks:'all'` → loại 2 entry laca-seo khỏi splitChunks (`chunks: (chunk) => !chunk.name.startsWith('laca-seo')`) để PHP enqueue 1 file là đủ.
  - Đường dẫn enqueue trong PHP (`dirname(get_stylesheet_directory()).'/dist/'`) phải ra `M/dist/` — xác minh.
- Kiểm tra: soạn 1 bài → metabox Laca SEO hiện điểm; xem nguồn trang chủ + 1 bài: chỉ 1 `<title>`, 1 meta description, OG đầy đủ, JSON-LD hợp lệ; sitemap (nếu module có) trả 200; trang Laca Admin → Dịch vụ Google mở được (chưa cần OAuth thật); 3 block SEO chèn được.
- Commit: `feat(seo): port laca seo, seo blocks and google services`.

---

## PHASE 5 — Tư vấn (Claude thực hiện, Antigravity không làm)

---

## Mẫu báo cáo cuối mỗi phase (Antigravity gửi lại)
```
PHASE: <số>
TRẠNG THÁI: xong | dừng giữa chừng (lý do)
COMMIT: <hash> <message> (mỗi repo)
FILE ĐÃ THÊM/SỬA: <danh sách theo repo>
KIỂM TRA: php -l (số file, lỗi), yarn build (20 dòng cuối), curl codes, debug.log, ảnh chụp đã xem
GREP LIX: <kết quả + giải thích phần còn lại>
ĐỀ XUẤT XOÁ: <file + lý do>
SAI LỆCH SO VỚI PLAN: <gì và vì sao>
CÂU HỎI: <nếu có>
```
