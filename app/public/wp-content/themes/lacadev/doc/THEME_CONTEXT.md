# Lacadev Theme Context

Tài liệu này là bản đồ ngữ cảnh nhanh cho agent và developer khi làm việc với theme `lacadev`. Trước khi sửa code trong theme, hãy đọc file này cùng các file neo được liệt kê bên dưới để tránh đoán sai kiến trúc, tooling hoặc vị trí triển khai.

## Mục Đích

- Giúp chat/agent mới nắm nhanh cấu trúc theme.
- Ghi lại các entry point, module lớn, quy ước và lệnh thường dùng.
- Nhắc các vùng rủi ro cao như AJAX, REST, database schema và bootstrap runtime.

## Tổng Quan

`lacadev` là một classic WordPress theme có app layer riêng:

- Theme wrapper WordPress nằm trong `theme/`.
- App PHP chính nằm trong `app/`.
- Namespace Composer PSR-4 là `App\`, trỏ tới `app/src/`.
- Framework chính là WPEmerge.
- Gutenberg blocks nằm trong `block-gutenberg/`.
- Source frontend nằm trong `resources/`.
- Tài liệu nội bộ nằm trong `doc/`.

Theme header chính ở `theme/style.css`, với text domain chủ đạo là `laca`.

## File Neo Cần Đọc Trước

Khi bắt đầu một yêu cầu mới, ưu tiên đọc các file sau theo phạm vi công việc:

- Bootstrap/runtime: `theme/functions.php`, `app/config.php`, `app/helpers.php`, `app/hooks.php`.
- Tooling/build: `composer.json`, `package.json`, `resources/build/webpack.development.js`, `resources/build/webpack.production.js`, `resources/build/webpack.blocks.js`.
- Gutenberg blocks: `theme/setup/gutenberg-blocks.php`, `block-gutenberg/*/block.json`, `block-gutenberg/*/render.php`.
- Post types/taxonomies: `app/src/PostTypes/`, `theme/setup/taxonomies/`.
- Database: `app/src/Databases/`.
- Project management: `app/src/Features/ProjectManagement/`, `app/src/PostTypes/project.php`.
- Contact form: `app/src/Features/ContactForm/`, `app/helpers/ajax.php`.
- Admin/settings/tools: `app/src/Settings/`, đặc biệt `app/src/Settings/LacaTools/` và `app/src/Settings/Security/`.

## Bootstrap Và Routing

Entry point chính là `theme/functions.php`. File này thường chịu trách nhiệm:

- Define constants.
- Load Composer autoload từ `vendor/autoload.php`.
- Boot Carbon Fields.
- Load helpers.
- Bootstrap WPEmerge bằng `app/config.php`.
- Require hooks.
- Setup theme.
- Register database tables và custom post types.

`app/config.php` cấu hình WPEmerge, service providers và routes:

- `App\Routing\RouteConditionsServiceProvider`
- `App\View\ViewServiceProvider`
- `App\Module\ModuleServiceProvider`
- `app/routes/web.php`
- `app/routes/admin.php`
- `app/routes/ajax.php`

`app/routes/web.php` dùng WPEmerge route layer cho frontend request. `app/routes/admin.php` và `app/routes/ajax.php` hiện không phải nơi chứa phần lớn logic admin/AJAX; nhiều logic được đăng ký trực tiếp qua WordPress hooks trong helpers hoặc class feature.

## Tooling Và Môi Trường

Yêu cầu chính:

- PHP `>=8.0`.
- Node `>=20.0`.
- Composer autoload namespace `App\`.
- Package manager trong docs/scripts hiện dùng `yarn`.

Lệnh thường dùng:

```bash
# Install PHP and Node dependencies.
composer install && yarn install

# Watch and build frontend assets during development.
yarn dev

# Build production assets.
yarn build

# Build WordPress theme assets.
yarn build:theme

# Build Gutenberg blocks.
yarn build:blocks

# Generate critical CSS.
yarn critical

# Run JavaScript and style linting.
yarn lint
```

`composer test` có tồn tại, nhưng cần kiểm tra `tests/` hoặc `phpunit.xml` trước khi xem test PHP là sẵn sàng chạy.

## Asset Pipeline

Webpack config nằm trong `resources/build/`:

- `webpack.development.js`: watch mode, BrowserSync, manifest, copy service worker/libs.
- `webpack.production.js`: minify JS/CSS, optimize images, generate WebP, split chunks cho vendor libraries.
- `webpack.blocks.js`: scan `block-gutenberg/*/block.json`, build từng block vào `block-gutenberg/<block>/build`, đồng thời build legacy Gutenberg bundle vào `dist/gutenberg`.

`tailwind.config.js` scan các vùng chính như `block-gutenberg`, `app`, `theme`, `resources` và dùng CSS variables từ Carbon Fields cho màu/font.

## Gutenberg Blocks

Blocks được đăng ký trong `theme/setup/gutenberg-blocks.php` bằng `register_block_type_from_metadata()`.

Các block hiện có:

- `about-laca-block`
- `blog-block`
- `button-block`
- `marquee-block`
- `process-block`
- `project-block`
- `service-block`
- `slogan-block`
- `staggered-blog-block`
- `statement-block`
- `tech-list-block`
- `workflow-block`

Một số block dùng `render.php` để dynamic render phía server. Khi sửa block, luôn kiểm tra cả `block.json`, `edit.js`, `save.js`, `render.php`, SCSS và build output nếu cần.

## Module Và Tính Năng Chính

### Project Management

Khu vực chính:

- `app/src/PostTypes/project.php`
- `app/src/Features/ProjectManagement/`
- `app/src/PostTypes/Concerns/`
- `app/src/Models/ProjectLog.php`
- `app/src/Models/ProjectAlert.php`

Tính năng liên quan gồm project CPT, admin columns, logs, alerts, tasks, payment service, remote updates, client webhook, PDF/export/reporting và block sync.

### Contact Form

Khu vực chính:

- `app/src/Features/ContactForm/ContactFormManager.php`
- `app/src/Features/ContactForm/ContactFormAjaxHandler.php`
- `app/src/Features/ContactForm/ContactFormEmailService.php`
- `app/src/Databases/ContactFormTable.php`
- `app/helpers/ajax.php`

Cần chú ý khả năng overlap quanh action `laca_contact_submit` giữa class handler và helper AJAX.

### Dynamic CPT

Khu vực chính:

- `app/src/Features/DynamicCPT/DynamicCptManager.php`
- `app/src/Features/DynamicCPT/DynamicCptAdminPage.php`
- `app/src/Features/DynamicCPT/DynamicCptTemplateGenerator.php`

Không thay đổi cấu trúc template hoặc cách generate CPT nếu yêu cầu không trực tiếp liên quan.

### LacaTools Và Admin Settings

Khu vực chính:

- `app/src/Settings/AdminSettings.php`
- `app/src/Settings/ThemeSettings.php`
- `app/src/Settings/LacaTools/`
- `app/src/Settings/LacaTools/Management/`

Bao gồm AI chat, AI translation, dashboard widgets, database cleaner, project reports, PDF exporter, tracker/client portal endpoints, admin UX và optimization tools.

### Security

Khu vực chính:

- `app/src/Settings/Security/SecurityManager.php`
- `app/src/Settings/Security/CustomLoginManager.php`
- `app/src/Settings/Security/TwoFactorAuth.php`
- `app/src/Settings/Security/FileIntegrityMonitor.php`
- `app/src/Settings/Security/MalwareScanner.php`
- `app/src/Settings/Security/HiddenUserScanner.php`
- `app/src/Settings/Security/SecurityAudit.php`

Mọi thay đổi ở vùng này cần kiểm tra capability, nonce, sanitization, escaping, logging và tác động tới login/admin flow.

## Database

Custom table classes nằm trong `app/src/Databases/`.

Các bảng quan trọng:

- `wp_laca_project_logs`
- `wp_laca_project_alerts`
- `wp_laca_contact_forms`
- `wp_laca_contact_submissions`
- `wp_laca_email_log`

Có `DbVersionManager`, nhưng một số table vẫn có version/install flow riêng. Trước khi sửa schema, đọc toàn bộ install/version path và cân nhắc migration/backward compatibility.

## CPT Và Taxonomies

CPT chính:

- `project`, slug `projects`
- `service`, slug `services`
- `template`, slug `templates`
- Dynamic CPT từ admin panel

Base class:

- `app/src/Abstracts/AbstractPostType.php`
- `app/src/Abstracts/AbstractTaxonomy.php`

Taxonomies nằm trong `theme/setup/taxonomies/`, ví dụ project, service, template và blog categories/tags.

## AJAX Và REST

Theme có AJAX/REST surface lớn. Trước khi chỉnh endpoint public, cần kiểm tra:

- Nonce hoặc secret/HMAC.
- Capability checks.
- Sanitization input.
- Escaping output.
- Rate limit.
- Error response shape.
- Log dữ liệu nhạy cảm.

REST namespace chính là `laca/v1`, gồm các nhóm như tracker log, portal project, client report, chatbot và AI chat.

Một số AJAX surface nằm trong:

- `app/helpers/ajax.php`
- `theme/setup/users/auth.php`
- `app/src/Features/ProjectManagement/Ajax/`
- `app/src/Settings/Security/`
- `app/src/Settings/LacaTools/`

## Quy Ước Code

- Giữ style hiện có của từng file, không refactor rộng nếu không được yêu cầu.
- PHP dùng namespace `App\` trong `app/src`.
- WordPress hooks được dùng trực tiếp khá nhiều.
- Carbon Fields được dùng rộng cho settings và post meta.
- UI strings/comment có thể mix English và Vietnamese theo file hiện hữu.
- PHPDoc không để dòng trống giữa `/**` và dòng mô tả đầu tiên.
- PHP file phải có đúng một dòng trống cuối file.
- Luôn escape output bằng `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` hoặc API phù hợp.
- Luôn sanitize dữ liệu từ `$_GET`, `$_POST`, `$_REQUEST`, REST body và AJAX payload.

## Rủi Ro Và Lưu Ý

- `theme/functions.php` vừa bootstrap vừa có nhiều side effect runtime. Sửa file này cần đọc kỹ phạm vi.
- Nhiều REST endpoint có `permission_callback => __return_true` và tự xử lý bảo mật bằng secret/HMAC/rate limit. Không giả định endpoint là an toàn nếu chưa đọc handler.
- Contact form có dấu hiệu trùng luồng xử lý AJAX giữa helper và class feature.
- Custom DB install có thể chạy trong request lifecycle. Không đổi schema tùy tiện.
- Production build có minify/mangle. Nếu sửa admin JS phụ thuộc global names, cần kiểm tra build production.
- Repo có thể đang có nhiều file modified. Không revert hoặc ghi đè thay đổi không liên quan.

## Quy Trình Khi Nhận Yêu Cầu Mới

1. Xác định yêu cầu thuộc vùng nào: block, frontend asset, CPT, REST/AJAX, DB, admin settings, security, project management hoặc contact form.
2. Đọc file neo tương ứng trước khi đề xuất sửa.
3. Ưu tiên pattern hiện có trong cùng module.
4. Nếu đụng public request, kiểm tra bảo mật trước khi code.
5. Nếu sửa PHP, giữ PHPDoc style và đúng một dòng trống cuối file.
6. Nếu sửa JS/SCSS/block, cân nhắc chạy `yarn lint` hoặc build tương ứng.
7. Sau khi sửa, kiểm tra lint/diagnostics cho file đã chỉnh.

## Prompt Gợi Ý Cho Chat Mới

```text
Trước khi làm việc, hãy đọc `app/public/wp-content/themes/lacadev/doc/THEME_CONTEXT.md`. Theme này là classic WordPress theme dùng WPEmerge, bootstrap ở `theme/functions.php`, app PSR-4 `App\` trong `app/src`, Gutenberg blocks ở `block-gutenberg`, build bằng Yarn/Webpack, REST namespace chính là `laca/v1`. Hãy đọc file neo theo đúng phạm vi yêu cầu trước khi sửa code.
```
