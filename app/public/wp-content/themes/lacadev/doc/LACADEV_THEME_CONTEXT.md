# LACADEV Theme Context

Last reviewed: 2026-05-07

This note is the working context for maintaining and upgrading the `lacadev` WordPress theme. Use it before implementing features, UI changes, Gutenberg blocks, admin tools, security/performance changes, or refactors.

## 1. Theme Identity

`lacadev` is a custom WordPress theme using a non-standard wrapper layout:

- WordPress theme wrapper: `theme/`
- PHP application code: `app/`
- Source frontend assets: `resources/`
- Compiled assets: `dist/`
- Custom Gutenberg blocks: `block-gutenberg/`
- Composer dependencies: `vendor/`
- Node dependencies: `node_modules/`

The actual `style.css` and WordPress templates live under `theme/`, but the project root is the parent directory `app/public/wp-content/themes/lacadev`. Many paths must therefore use `dirname(get_template_directory())` or `APP_DIR` when addressing `dist/`, `resources/`, or `block-gutenberg/`.

## 2. Core Stack

- PHP: `>=8.0`
- WordPress framework layer: WPEmerge `~0.15`
- Field/admin UI: Carbon Fields
- CPT helper: `johnbillion/extended-cpts`
- PHP namespaces: PSR-4 `App\` mapped to `app/src/`
- Frontend build: Webpack 5, Babel, SCSS, Tailwind 3, PostCSS
- Gutenberg build: `@wordpress/scripts` with `resources/build/webpack.blocks.js`
- JS policy: theme documentation says Zero jQuery for frontend; WordPress admin may still enqueue/use WordPress-provided jQuery where existing code depends on it.

## 3. Boot Flow

Primary bootstrap starts at `theme/functions.php`.

1. Define directory constants such as `APP_DIR`, `APP_APP_DIR`, `APP_THEME_DIR`, `APP_DIST_DIR`.
2. Load Composer autoload and boot Carbon Fields.
3. Load `app/helpers.php`.
4. Load `app/helpers/responsive-images.php`.
5. Bootstrap WPEmerge with `app/config.php`.
6. Load `app/hooks.php`.
7. On `after_setup_theme`, include theme setup modules from `theme/setup/`.
8. Autoload taxonomy and walker setup files.
9. Register custom DB tables and custom post types.

Important WPEmerge config:

- `app/config.php` registers providers:
  - `App\Routing\RouteConditionsServiceProvider`
  - `App\View\ViewServiceProvider`
  - `App\Module\ModuleServiceProvider`
- Routes are defined in:
  - `app/routes/web.php`
  - `app/routes/admin.php`
  - `app/routes/ajax.php`
- Views are searched in `get_stylesheet_directory()` and `get_template_directory()`.

## 4. Directory Map

### `theme/`

WordPress-facing theme files.

- `functions.php`: bootstrap only. Avoid adding feature logic here unless it is truly theme bootstrap.
- `header.php`, `footer.php`: global layout, loader, cursor, nav, dark mode, Barba wrapper, footer CTA.
- `single.php`, `page.php`, `archive.php`, `search.php`, `404.php`: standard templates.
- `single-project.php`, `single-service.php`, `single-template.php`: CPT templates.
- `page_templates/template-contact.php`: contact page template.
- `page_templates/template-client-portal.php`: client portal page template.
- `template-parts/`: reusable view partials.
- `setup/`: WordPress setup modules.

Key setup modules:

- `assets.php`: enqueue frontend/admin/login/editor assets, preload resources, add async/defer filters.
- `security.php`: security headers, XML-RPC disable, login attempt limiting, upload filename sanitization.
- `performance.php`: bloat removal, cache headers, compression, image attributes, service worker hooks.
- `image-optimization.php`: image optimization helpers/hooks.
- `gutenberg-blocks.php`: auto-register block directories under `block-gutenberg/`.
- `theme-support.php`, `menus.php`, `sidebars.php`, `theme-options.php`, `recaptcha.php`.

### `app/`

Application/business logic.

- `app/helpers.php`: includes helper files and instantiates major settings/tools classes.
- `app/hooks.php`: central action/filter registration. Keep logic short here.
- `app/helpers/`: global helper functions. Important helpers include asset, responsive image, template tag, Carbon Fields, AJAX, shortcode, and content helpers.
- `app/src/`: OOP code under `App\`.
- `app/routes/`: WPEmerge route definitions.

### `app/src/`

Important groups:

- `Abstracts/`: `AbstractPostType`, `AbstractTaxonomy`.
- `Contracts/`: constants such as `AssetHandles`.
- `Databases/`: custom DB table managers for project logs, alerts, contact form, schema versioning.
- `Features/`: user-facing or larger features:
  - contact form
  - dynamic CPT manager
  - project management
  - mobile sticky CTA
  - related posts
  - exit intent popup
  - frontend chatbot
- `Helpers/`: OOP helpers, currently includes `Crypto`.
- `Models/`: data access models for posts/project logs/alerts.
- `PostTypes/`: `service`, `project`, `template`.
- `Settings/`: admin/settings/tools/security modules.
- `Module/`: module loader interface/provider, currently mostly scaffolded.
- `Routing/`, `View/`, `Validators/`, `Widgets/`.

### `resources/`

Source assets. Edit here, not in `dist/`.

- `resources/scripts/theme/index.js`: frontend entry, imports Tailwind, SCSS, Barba, GSAP, SweetAlert, page/component modules.
- `resources/scripts/admin/index.js`: admin entry.
- `resources/scripts/login/index.js`: login page entry.
- `resources/scripts/editor/index.js`: editor entry.
- `resources/styles/theme/index.scss`: theme SCSS entry.
- `resources/styles/tailwind.css`: Tailwind entry.
- `resources/build/`: Webpack, BrowserSync, critical CSS, release scripts.
- `resources/fonts/`, `resources/images/`.

Frontend JS modules:

- Components: animations, dark mode, frontend chatbot, header, loader, mobile menu, reading progress, UI utilities.
- Pages: about-laca, comments, contact, global, login, register, search.
- Additional: ajax search, project block behavior, service worker register, web vitals.

### `block-gutenberg/`

Custom dynamic/static blocks. Each block generally has:

- `block.json`
- `index.js`
- `edit.js`
- `save.js`
- `render.php` for dynamic output when present
- `style.scss`
- `editor.scss` when needed
- `preview.png`

Registered blocks currently include:

- `lacadev/about-laca-block`
- `lacadev/blog-block`
- `lacadev/button-block`
- `lacadev/marquee-block`
- `lacadev/process-block`
- `lacadev/project-block`
- `lacadev/service-block`
- `lacadev/slogan-block`
- `lacadev/staggered-blog-block`
- `lacadev/statement-block`
- `lacadev/tech-list-block`
- `lacadev/workflow-block`

Registration is automated by `theme/setup/gutenberg-blocks.php`, which scans `block-gutenberg/*/block.json`. Blocks with an individual `build/` directory get their own assets; otherwise they use the shared `dist/gutenberg/index.js`.

## 5. Build System

Use commands from the theme root `app/public/wp-content/themes/lacadev`.

- `yarn dev`: run theme watch + Gutenberg block watch.
- `yarn dev:theme`: Webpack theme assets.
- `yarn dev:blocks`: Gutenberg blocks.
- `yarn build`: production theme build + block build + critical CSS.
- `yarn build:theme`: production theme bundle.
- `yarn build:blocks`: block build.
- `yarn critical`: regenerate `dist/styles/critical.css`.
- `yarn lint`: JS + SCSS lint.
- `yarn lint:scripts`: `wp-scripts lint-js` for `resources/scripts` and `block-gutenberg`.
- `yarn lint:styles`: Stylelint for `resources/styles`.
- `composer test`: PHPUnit with `tests/wp-stubs.php`.

Webpack entrypoints:

- `theme` -> `resources/scripts/theme/index.js`
- `admin` -> `resources/scripts/admin/index.js`
- `login` -> `resources/scripts/login/index.js`
- `editor` -> `resources/scripts/editor/index.js`

Compiled output is in `dist/`. Do not edit compiled assets manually.

## 6. Design System And UI Context

The visual system is a branded portfolio/service theme with a playful but technical voice. Current UI signals:

- Fonts: Be Vietnam Pro and Quicksand.
- Color source: Carbon Fields theme options exposed as CSS variables.
- Tailwind colors reference CSS variables:
  - `primary`, `secondary`, `bg`
  - `primary-dark`, `secondary-dark`, `bg-dark`
  - admin variants `primary-ad`, `secondary-ad`, `bg-ad`, `text-ad`
- Breakpoints:
  - `xs: 576px`
  - `sm: 768px`
  - `md: 992px`
  - `lg: 1200px`
  - `xl: 1440px`
- Max container: `90rem`.
- Global layout includes page loader, custom cursor, dark mode switch, desktop/mobile menus, Barba transitions.

When updating UI:

- Prefer existing Tailwind tokens and SCSS partials.
- Keep repeated components in `theme/template-parts/` or Gutenberg block render files.
- Keep global shell changes in `header.php`, `footer.php`, and layout SCSS.
- Ensure text does not overflow on mobile.
- Use semantic HTML, labels for forms, and `aria-label` for icon-only controls.
- For images, prefer theme responsive image helpers where possible.

## 7. Feature Placement Rules

Use these rules before adding code:

- New WordPress hook with simple glue logic: `app/hooks.php`.
- Complex backend logic or feature over roughly 50 lines: a class in `app/src/Features`, `app/src/Settings`, or a more specific namespace.
- Custom post type: class in `app/src/PostTypes/`, then register in `theme/functions.php` only as bootstrap.
- Taxonomy: `theme/setup/taxonomies/`.
- Admin setting/tool: `app/src/Settings/`.
- Admin UX or dashboard: `app/src/Settings/LacaTools/Management/` when related to Laca Tools.
- Project management feature: `app/src/Features/ProjectManagement/`.
- AJAX handler: prefer class-based handler in the feature namespace, initialized from `app/hooks.php`; always verify nonce and capability.
- Frontend JS: `resources/scripts/theme/`.
- Admin JS: `resources/scripts/admin/`.
- Login JS/CSS: `resources/scripts/login/`, `resources/styles/login/`.
- Frontend SCSS: `resources/styles/theme/`.
- Gutenberg block: `block-gutenberg/[block-name]/`.
- Template-only partial: `theme/template-parts/`.

## 7.1 Laca Projects CRM Context

The `project` CPT is managed through the `Laca Projects` top-level admin hub, not as a loose WordPress submenu list.

Primary files:

- Hub shell and grouped views: `app/src/Features/ProjectManagement/LacaProjectsHub.php`.
- CPT menu placement: `app/src/PostTypes/project.php`.
- Project workspace metabox views: `app/src/Features/ProjectManagement/Views/`.
- Client Portal REST API: `app/src/Settings/LacaTools/ClientPortalEndpoint.php`.
- Client Portal template: `theme/page_templates/template-client-portal.php`.
- Admin CRM assets: `resources/scripts/admin/project.js`, `resources/scripts/admin/project-charts.js`, `resources/styles/admin/_project.scss`.
- Client Portal styles: `resources/styles/theme/pages/_client-portal.scss`.

Current CRM surface:

- Dashboard: finance KPIs, outstanding payments, active alerts, client-site updates and Chart.js charts.
- Notifications: client requests, issues, finance, renewals and stale projects.
- Action center: urgent issues, unpaid projects, renewals and stale work.
- Pipeline: board grouped by project status.
- Clients: grouped CRM rows by client name/email, aggregating projects, revenue and debt.
- Reports: monthly revenue, logs, issue summary and monthly website-care report.
- Portal links: per-project customer portal URLs using tracker key or portal alias.

Client Portal exposes only public-safe data. It hides security/file-change logs, sanitizes sensitive strings, shows progress/checklists/service reports, and lets customers submit support requests through `POST /wp-json/laca/v1/portal/request`. Customer requests create `ProjectLog` rows with `log_type = client_request` and related `ProjectAlert` rows so the admin hub can surface them.

Permission/role separation for project/client access is intentionally deferred; do not implement it until explicitly requested.

## 8. Security Rules

- Escape output at render time:
  - text: `esc_html()`
  - attributes: `esc_attr()`
  - URLs: `esc_url()`
  - safe rich HTML: `wp_kses_post()`
  - JS data: `wp_json_encode()`
- Sanitize input before use/storage:
  - text: `sanitize_text_field()`
  - textarea: `sanitize_textarea_field()`
  - key: `sanitize_key()`
  - integer IDs: `absint()`
  - URLs: `esc_url_raw()`
- AJAX/form handlers must verify nonce and capability.
- Do not expose secrets or tracker keys in frontend markup.
- Be careful with security headers in `theme/setup/security.php`; CSP currently allows `'unsafe-inline'` for plugin compatibility.

## 9. Performance Rules

- Do not edit `dist/` directly; change source and build.
- Keep new frontend dependencies rare. Existing heavy libraries include GSAP, Barba, Swiper, SweetAlert2, Chart.js.
- Use `no_found_rows => true` on `WP_Query` when no pagination is needed.
- Avoid repeated `get_post_meta()` inside large loops; preload/cache or batch where practical.
- Keep above-the-fold CSS changes in mind; regenerate critical CSS after header/hero changes.
- Verify asset paths carefully because `dist/` is outside the `theme/` wrapper directory.

## 10. Current Review Findings

Status after the 2026-05-07 hardening pass:

1. Resolved: duplicate asset hook registration.
   - `theme/setup/assets.php` no longer registers enqueue hooks that are already registered in `app/hooks.php`.

2. Resolved: theme-root vs wrapper-root path mismatch.
   - Shared helpers now live in `app/helpers/functions.php`: `lacaThemeRootDir()`, `lacaThemeRootUri()`, `lacaDistDir()`, `lacaDistUrl()`, and `lacaResourceUrl()`.
   - Critical CSS, PWA/service worker, responsive image helper, admin assets, template tags, optimization tools, and 2FA QR asset paths now use those helpers.

3. Resolved: late service worker and web-vitals enqueue.
   - `theme/setup/performance.php` registers service worker and web-vitals assets on `wp_enqueue_scripts`, not from footer callbacks.

4. Resolved: unsafe inline color output.
   - `lacaSanitizeCssColor()` sanitizes Carbon/admin colors before printing CSS variables or localized chart colors.

5. Resolved: theme-level output compression.
   - `enable_compression()` is now intentionally a no-op; compression should be handled by server/cache/CDN.

6. Resolved: insecure thumbnail AJAX endpoint.
   - `mm_get_attachment_url_thumbnail` now checks `upload_files`, verifies nonce `laca_get_attachment_url`, sanitizes attachment ID with `absint()`, and returns JSON success/error responses.
   - Admin JS now sends the localized nonce.

7. Resolved: build and lint blockers.
   - Sass circular import/missing tokens were fixed in `resources/styles/theme/abstracts/_variables.scss`.
   - Stylelint config was aligned with current SCSS/Tailwind structure.
   - Gutenberg edit components no longer return preview markup before calling hooks.
   - JS globals supplied by WordPress/localized scripts are declared in `.eslintrc.js`.
   - `yarn lint`, `yarn build:theme`, `yarn build:blocks`, and `composer test` all pass.

8. Remaining non-blocking warnings / next optimization candidates.
   - `yarn lint:scripts` exits 0 but still reports warnings for existing `console`/`alert` usage, a few unused variables, and some hook dependency suggestions.
   - `yarn build:theme` exits 0 with a Webpack performance warning because the `theme` entrypoint is about 629 KiB; likely candidates are lazy-loading GSAP/Swiper/SweetAlert where page-specific.
   - `yarn build:blocks` exits 0 with Sass deprecation warnings from legacy Sass JS API and old `@import` usage in block SCSS.
   - `composer test` passes on PHP 8.5 but reports vendor deprecations from Composer dependencies, Illuminate helpers, and Carbon Fields/Pimple.
   - Existing documentation still has drift: README says PHP `7.4+`, while `composer.json` requires `>=8.0`; some old docs mention folders such as `Controllers/` that are not present.

## 11. Validation Checklist

Choose the smallest useful verification set for the change:

- PHP syntax for changed PHP files: `php -l path/to/file.php`.
- PHPUnit: `composer test`.
- JS lint: `yarn lint:scripts`.
- SCSS lint: `yarn lint:styles`.
- Full lint: `yarn lint`.
- Build frontend/theme assets: `yarn build:theme`.
- Build Gutenberg blocks: `yarn build:blocks`.
- Full production build: `yarn build`.
- For visible UI changes, run a local server and inspect desktop/mobile states.

## 12. Safe Editing Checklist

Before editing:

- Check `git status --short`.
- Read nearby files and existing helpers.
- Identify whether the change belongs in PHP app code, template, setup, JS, SCSS, or block.
- Avoid touching `vendor/`, `node_modules/`, and compiled `dist/` unless explicitly requested.

During editing:

- Keep changes tightly scoped.
- Follow existing naming and folder conventions.
- Preserve translations with text domain `laca` unless the surrounding file uses another existing domain.
- Do not introduce jQuery in frontend.
- Do not move feature logic into `functions.php`.

After editing:

- Run targeted validation.
- Note any commands that could not be run.
- If UI changed, mention whether build artifacts were regenerated.
