# PLAN-ROADMAP-2026-11 — Nâng cấp hub ↔ client (sau PLAN-SYNC)

Người lập/review: Claude Code · Người code: Antigravity · Ngày: 2026-11-03
Quy trình: Claude giao TỪNG PHASE → Antigravity code + tự kiểm tra → báo cáo → Claude review → sửa → **Claude push** (người dùng đã cho phép tự push sau mỗi phase đạt). **Antigravity KHÔNG BAO GIỜ push.**
Đề xuất 11 (tách package `laca-core`) đã được người dùng HOÃN — không làm trong plan này.

## 0. Ký hiệu & repo

| Ký hiệu | Đường dẫn | Repo git (commit ở đây) | Deploy khi push |
|---|---|---|---|
| `M` | `/Users/lacadev/Documents/wordpress/lacadev/app/public/wp-content/themes/lacadev` | `/Users/lacadev/Documents/wordpress/lacadev` | FTP thẳng `wp-content/themes/**` lên `lacadev.com` (`/public_html/`), **KHÔNG build** → file build trong `dist/` và `block-gutenberg/*/build` PHẢI được commit (force-add vì `.gitignore` theme chặn) |
| `CP` | `/Users/lacadev/Documents/wordpress/lacadev-client/app/public/wp-content/themes/lacadev-client` | `/Users/lacadev/Documents/wordpress/lacadev-client` | CI `yarn build:theme && build:blocks` rồi FTP lên `clients.lacadev.com` (`/clients.lacadev.com/`); workflow `publish-theme-updates.yml` đóng gói CP → FTP `/public_html/theme-updates/` (= `https://lacadev.com/theme-updates/`). KHÔNG commit dist/build. |
| `CC` | `…/lacadev-client/app/public/wp-content/themes/lacadev-client-child` | như CP | như CP |
| `NK` | `/Users/lacadev/Documents/wordpress/nhakhoathienphuoc/app/public/wp-content/themes/` | kiểm tra (`git -C … rev-parse`) | KHÔNG push (chỉ thử local) |
| `PD` | `/Users/lacadev/Documents/wordpress/phucdainam/app/public/wp-content/themes/` | kiểm tra | KHÔNG push (chỉ thử local) |

Site local: `http://lacadev.local`, `http://lacadev-client.local`, `http://nhakhoathienphuoc.local`, `http://phucdainam.local` (xác nhận URL trong `config.json` của child từng site).

## 1. Luật chung (bắt buộc, giữ toàn bộ luật mục 1 của PLAN-SYNC-2026-11.md)

1. Không xoá file (đề xuất xoá trong báo cáo). Không sửa WP core/wp-config/.htaccess/plugins/uploads. Không sửa tay dist/build.
2. File M đã có → lấy bản M làm gốc, chỉ gộp; cuối phase so danh sách `function` trước/sau mọi file PHP/JS bị sửa (không mất hàm nào trừ khi plan yêu cầu).
3. i18n: mọi câu hiển thị qua gettext, msgid tiếng Việt, **text domain `laca`** (sau Phase 5). JS lấy chữ qua localize/data-*.
4. DB: chỉ đọc khi test; mọi bảng mới tạo qua version-check (không dbDelta mỗi request); không xoá dữ liệu thật; dữ liệu test phải đánh dấu `[TEST]` và liệt kê trong báo cáo để người dùng xoá.
5. Không nhập credential/webhook/token thật. Không gửi email/Zalo/Telegram/Discord ra ngoài khi test (dùng môi trường local + Mailpit; kênh khác để trống).
6. **KIỂM TRA THẬT, KHÔNG CHỈ HTTP CODE**: dùng script smoke ở Phase 6 (`tools/smoke.sh`) — body > 2000 byte, có `</html>`, không có `Fatal error|critical error|Warning:`. Trước Phase 6 thì tự curl + grep tương đương. Trang chủ local lacadev.local trống là bình thường cho tới khi xong 5.1.
7. M: trước khi commit build thì `yarn build:theme && yarn build:blocks` lại, rồi `git add -u dist` + `git add -f` file build mới (trừ `dist/bundle-report.html`). Commit build riêng: `build: update compiled theme assets`.
8. Đụng tracker/remote/block sync/notification → đọc trước và CẬP NHẬT `M/doc/TRACKER_HUB_CLIENT_SYNC.md`, `M/doc/NOTIFICATION_RELIABILITY_AND_BILLING.md`.
9. Commit Conventional Commits tiếng Anh, footer `Co-Authored-By: Antigravity`. Mỗi phase con 1 commit.
10. Báo cáo cuối phase theo mẫu cuối file. Không làm vượt phase.

---

## PHASE 5 — Dọn dẹp & sửa nhanh (đề xuất 13 + lỗi phát hiện)

5.1 **Trang chủ trống (M)**: `theme/index.php` đang 0 byte → khi `show_on_front=posts` site trắng (cả production lacadev.com). Viết `index.php` fallback hiển thị danh sách bài viết dùng lại layout/template-part của `archive.php` (header/footer, loop, phân trang). KHÔNG đổi option DB. Kiểm tra `http://lacadev.local/` có nội dung.
5.2 **SEO cho project & taxonomy (M)**: trang project hiện thiếu meta description + JSON-LD; category thiếu description. Dùng cơ chế của Laca SEO (template tiêu đề/mô tả theo post type, schema theo post type) — đặt MẶC ĐỊNH qua filter/defaults trong code, không ghi DB: project → schema `CreativeWork` (hoặc `WebPage` + `about`), description fallback = excerpt → 155 ký tự đầu nội dung; term → description fallback = term description → "Danh mục {name} – {sitename}" qua `__()`. Kiểm tra 4 trang (post, page, project, category): mỗi trang 1 description + JSON-LD hợp lệ.
5.3 **Sitemap 404 trên production**: rewrite rules của Laca SEO sitemap chưa được flush khi deploy. Thêm cơ chế flush 1 lần khi version Laca SEO (hoặc theme) đổi: option `laca_seo_rewrite_version`; so khác → `flush_rewrite_rules(false)` trên `init` priority muộn, chỉ 1 lần. Áp dụng M và CP (code giống nhau). Kiểm tra: xoá giá trị option test bằng cách đổi version hằng số tạm → sitemap 200.
5.4 **Thống nhất text domain**: M load `laca`; các file copy từ CP dùng `'lacadev'` (~1100 chỗ) nên KHÔNG BAO GIỜ được dịch. Đổi `'lacadev'` → `'laca'` CHỈ trong lời gọi gettext (`__ _e _x _n esc_html__ esc_attr__ esc_html_e esc_attr_e esc_html_x esc_attr_x _nx`), `wp_set_script_translations`, `load_*textdomain` — trong M, CP, CC. Dùng script (perl/php) có regex chặt + liệt kê số thay thế mỗi file; KHÔNG đổi slug/option/handle chứa chữ `lacadev`. Sau đó `grep -rn "'lacadev')" ` chỉ còn các chuỗi không phải gettext (liệt kê).
5.5 **WP-CLI + .pot**: tải `wp-cli.phar` vào `/Users/lacadev/Documents/wordpress/_tools/wp-cli.phar` (ngoài repo). Sinh `languages/laca.pot` cho M (`theme/languages` hoặc đúng `Domain Path` của style.css), CP, CC bằng `php _tools/wp-cli.phar i18n make-pot <theme> <pot> --domain=laca --exclude=node_modules,vendor,dist,doc`. Nếu có `.po` sẵn thì `msgmerge`/`wp i18n update-po`, rồi `make-mo`. Commit .pot/.po/.mo.
5.6 **Gỡ composer không dùng (M)** (người dùng đã duyệt): xác minh lại bằng grep từng package (`GuzzleHttp`, `Jenssegers\Agent`, `Sunra\`/`simple_html_dom`, `PhpOffice\PhpSpreadsheet`, `Illuminate\Validation`, `Illuminate\Translation`) trong `app/ theme/` (không tính vendor). Package nào KHÔNG dùng → `composer remove <pkg>`; nếu `illuminate/*` là phụ thuộc của wpemerge thì giữ. M track `vendor/` → commit thay đổi vendor (dùng `git add -A -- vendor` cho đúng cả file bị xoá bởi composer — đây là xoá do composer quản lý, được phép). Chạy site + smoke sau khi gỡ.
5.7 **Kênh cập nhật cũ**: `CP/build-release.sh` và `CP/theme-server/*.json` đã bị workflow `publish-theme-updates.yml` thay thế → thêm khối comment đầu file "DEPRECATED — dùng workflow …" (không xoá).
5.8 Commit tách theo 5.1…5.7 (repo đúng). Smoke M + CP.

## PHASE 6 — CI chất lượng & smoke test (đề xuất 12) — làm sớm để bảo vệ các lần push sau

6.1 `tools/smoke.sh` (ở gốc mỗi repo, bash, không phụ thuộc mới): nhận BASE_URL + danh sách path từ file `tools/smoke-urls.txt`; mỗi URL kiểm: HTTP 200 (hoặc mã kỳ vọng ghi kèm), body ≥ 2000 byte, có `</html>`, không chứa `Fatal error|critical error|Parse error|Warning:</b>`; in bảng + exit 1 nếu lỗi. Danh sách mặc định M: `/`, `/hello-world/`, `/sample-page/`, `/category/uncategorized/`, `/sitemap_index.xml` (kỳ vọng XML: kiểm `<sitemapindex`), 1 trang 404 (kỳ vọng 404 nhưng có `</html>`). CP: `/` + trang mẫu thật của clients.lacadev.com.
6.2 Workflow `quality.yml` mỗi repo (push + PR): `php -l` toàn bộ PHP trong theme(s) (trừ vendor/node_modules), PHPStan level 1 trên `app/src` với `szepeviktor/phpstan-wordpress` cài **chỉ trong CI** (`composer global require` hoặc tải phar — KHÔNG thêm vào composer.json của theme để không đổi vendor deploy), baseline file `phpstan-baseline.neon` sinh lần đầu để không fail vì lỗi cũ; build JS. Workflow cũ `main.yml` có bước `yarn lint` — giữ nguyên nếu đang pass, nếu đang fail thì đặt `continue-on-error: true` kèm comment (báo cáo hiện trạng).
6.3 Thêm job **post-deploy smoke** vào cuối `deploy.yml` (M & lacadev-client): sau FTP, `sleep 20` rồi chạy `tools/smoke.sh https://lacadev.com` (hoặc `https://clients.lacadev.com`). Thất bại → job đỏ (GitHub tự email chủ repo). Không thêm secret mới.
6.4 Chạy `tools/smoke.sh` local cho cả 2 site, dán kết quả. Commit `ci: add quality checks and post-deploy smoke test`.

## PHASE 7 — Tách key theo mục đích + ký HMAC (đề xuất 2)

Hiện trạng: `_tracker_secret_key` dùng cho tracker log, block catalog, remote-update, và portal. Đọc `TRACKER_HUB_CLIENT_SYNC.md` trước.
7.1 **Hub (M) – key theo project**: thêm 4 meta (ẩn, tạo bằng `wp_generate_password(48,false)` khi project lưu lần đầu hoặc khi bấm "Tạo key"): `_laca_key_tracker` (mặc định = giá trị `_tracker_secret_key` cũ để tương thích), `_laca_key_catalog`, `_laca_key_remote`, `_laca_key_portal`. Key lưu mã hoá bằng `HasEncryption` (như mật khẩu). UI trong project: hiển thị dạng che, nút "Hiện 1 lần / Sao chép", "Tạo lại" (xác nhận), và khối "Cấu hình cho site khách" (copy cả bộ). Ghi log `ProjectLog` mỗi lần tạo lại.
7.2 **Chuẩn ký**: header `X-Laca-Project` (project public id — dùng `_portal_alias` hoặc id ngẫu nhiên mới `_laca_public_id`, KHÔNG dùng post ID lộ thứ tự), `X-Laca-Timestamp` (unix), `X-Laca-Signature` = `hex(hmac_sha256(METHOD . "\n" . PATH . "\n" . TIMESTAMP . "\n" . sha256(BODY), key))`. Bên nhận: lệch giờ > 300s → 401; chống replay: lưu signature vào transient 600s, trùng → 401; so sánh `hash_equals`. Viết 1 class dùng chung `App\Security\RequestSigner` (sign/verify) — copy giống hệt ở M và CP.
7.3 Áp dụng: tracker log (client→hub, key tracker), block catalog (client→hub, key catalog; bỏ query string), block sync push (hub→client, giữ `X-Laca-Key` cũ + thêm chữ ký bằng key catalog), remote-update (hub→client, key remote), portal (chỉ nhận key/alias portal — KHÔNG còn nhận tracker key; thêm rate limit cho `/portal/request` giống `/portal/project`).
7.4 **Tương thích ngược**: option hub `laca_allow_legacy_auth` (mặc định BẬT, có ô trong Laca Admin, hiển thị đếm ngược 30 ngày từ lúc bật phase này). Khi bật: request không ký nhưng có `secret_key` đúng (cách cũ) vẫn được nhận, ghi `_laca_legacy_auth_last_seen` cho project + hiện badge "Client chưa nâng cấp" ở project/Fleet. Client mới: nếu chưa nhập key mới thì fallback cách cũ.
7.5 Client (CP): Carbon options cho 4 key + public id (ô "dán cấu hình" parse khối copy từ hub); mọi sender ký; mọi receiver verify (remote-update: chỉ nhận chữ ký hợp lệ khi đã cấu hình key remote).
7.6 Test bằng script PHP CLI trong scratch (không commit) gọi giữa `lacadev-client.local` ↔ `lacadev.local` với project test `[TEST]`: ký đúng → 200; sai chữ ký/timestamp cũ/replay → 401; legacy bật → 200 + badge; legacy tắt → 401. Cập nhật `TRACKER_HUB_CLIENT_SYNC.md` (bảng route, header, cách migrate). Commit tách M / CP.

## PHASE 8 — Kênh cập nhật theme đúng host + tải gói có xác thực + phát hành 3.2 (đề xuất 1, 3)

Hiện trạng (Claude đã xác minh): CI upload `lacadev-client.json` + `.zip` lên `https://lacadev.com/theme-updates/`, nhưng `ThemeUpdater` ở mọi client đọc `https://clients.lacadev.com/theme-updates/lacadev-client.json` (404) và JSON trỏ download về clients.lacadev.com (404) → **chưa client nào nhận được update**.
8.1 **Gói không công khai**: sửa `publish-theme-updates.yml`: zip upload vào `/theme-packages/` (thư mục FTP NGOÀI webroot — cùng cấp `public_html`), JSON công khai (chỉ metadata) vẫn ở `/public_html/theme-updates/lacadev-client.json` với `download_url` = `https://lacadev.com/wp-json/laca/v1/theme-package/lacadev-client`. Changelog lấy từ `CP/CHANGELOG.md` (tạo file, mục 3.2 liệt kê thay đổi PLAN-SYNC + roadmap). Ghi rõ trong báo cáo: người dùng cần đảm bảo FTP user ghi được `/theme-packages/` (nếu không thì phương án dự phòng: `/public_html/theme-updates/private/` + `.htaccess` `Require all denied` do CI upload).
8.2 **Endpoint hub (M)** `GET /wp-json/laca/v1/theme-package/{slug}`: yêu cầu chữ ký Phase 7 (key tracker) hoặc legacy; slug whitelist (`lacadev-client`); đọc file từ option `laca_theme_packages_dir` (mặc định `dirname(ABSPATH) . '/theme-packages'`, chặn traversal); stream file (`readfile` + header Content-Type/Disposition/Length); log project + version đã tải (meta `_laca_theme_downloaded`).
8.3 **ThemeUpdater (CP)**: URL JSON → `https://lacadev.com/theme-updates/lacadev-client.json` (filter `laca_theme_update_json_url`); khi WP tải `download_url` thuộc host hub → filter `http_request_args` thêm header chữ ký (Phase 7). Giữ `upgrader_source_selection`.
8.4 **Mồi cho client cũ**: client đang chạy bản cũ chỉ đọc `clients.lacadev.com/theme-updates/lacadev-client.json`. Thêm bước CI (chỉ cho 3.2) upload thêm JSON + zip CÔNG KHAI vào `/clients.lacadev.com/theme-updates/` để client cũ cập nhật được 1 lần; ghi chú trong workflow "XOÁ BƯỚC NÀY sau khi mọi client lên ≥3.2" + liệt kê trong báo cáo (rủi ro: zip 3.2 công khai tạm thời).
8.5 **Thử tương thích local (người dùng đã cho phép)**: với NK và PD: xác nhận repo git; commit/ghi lại trạng thái trước (nếu repo sạch thì không cần; nếu có thay đổi chưa commit → DỪNG hỏi). Thay theme cha local bằng CP (rsync KHÔNG --delete, giữ child nguyên), build child nếu cần (`yarn install --frozen-lockfile && yarn build`), smoke các trang chính + wp-admin (Dashboard, Laca Admin, 1 trang có block site riêng). Child vỡ → sửa child tối thiểu (commit trong repo site đó, KHÔNG push), ghi lại để người dùng áp dụng trên production trước khi bấm update. Không đổi DB ngoài bảng/option do theme tự tạo.
8.6 Tăng `Version: 3.2` trong `CP/theme/style.css`. Test end-to-end local: đặt filter URL JSON trỏ lacadev.local + `laca_theme_packages_dir` trỏ thư mục zip test → `lacadev-client.local` thấy cập nhật 3.2 trong Bảng tin › Cập nhật và cập nhật thành công (thư mục đúng `lacadev-client`). Gỡ cấu hình test sau đó.
8.7 Commit tách M / CP. Claude push → CI publish 3.2.

## PHASE 9 — Theo dõi backup + bảng Fleet (đề xuất 4, 5)

9.1 Client: heartbeat thêm `meta.backup = {plugin, last_backup_at (ISO), status}` — tự nhận: UpdraftPlus (`updraft_last_backup`), BackWPup, WPvivid, All-in-One WP Migration (mtime file mới nhất trong thư mục backups của plugin), Duplicator; filter `laca_tracker_backup_status` để site tự khai báo; không có gì → `status=unknown`.
9.2 Hub: lưu `_tracker_last_backup` ; cảnh báo qua hệ thông báo hiện có khi > `laca_backup_max_age_days` (mặc định 7, sửa trong Laca Admin) hoặc `unknown` quá 7 ngày; dedup 1 lần/ngày/project.
9.3 Trang **Fleet** (submenu Laca Projects, UI component `.laca-*`): bảng mọi project có live_url: site, heartbeat gần nhất (màu theo tuổi), WP/PHP, theme cha/child (đỏ nếu < version trong `theme-updates/lacadev-client.json` — cache 1h), backup gần nhất, uptime/response time (Phase 10), số plugin chờ update, badge "chưa nâng cấp key" (Phase 7). Lọc/sắp xếp. Chọn nhiều → "Cập nhật theme cha" / "Cập nhật plugin" chạy hàng đợi AJAX tuần tự qua remote-update có ký, hiển thị tiến độ, sau mỗi site gọi `SiteHealthChecker::checkAfterDeploy`; lỗi → dừng site đó, ghi log, tiếp site sau.
9.4 Test với 2 project `[TEST]` trỏ lacadev-client.local. Commit M / CP.

## PHASE 10 — Một hệ thông báo + uptime song song (đề xuất 6, 7)

Đọc kỹ `NOTIFICATION_RELIABILITY_AND_BILLING.md` — phải giữ: log kênh thất bại, tự refresh token Zalo, cron health-check.
10.1 `App\Notifications\NotificationBus::emit(string $event, array $payload, ?int $projectId)`: bảng định tuyến (option, trang Laca Admin "Định tuyến thông báo"): mỗi loại sự kiện → các kênh (Email, Zalo, Telegram, Slack, Discord). Kênh = adapter bọc code hiện có (`ProjectNotificationHandler` cho 4 kênh cũ, `DiscordWebhookSender` cho Discord). Dedup theo `event+project+fingerprint` với cooldown cấu hình. Chuyển dần các nơi gọi trực tiếp sang bus (liệt kê từng chỗ đã chuyển, chỗ còn lại).
10.2 Bỏ trùng "cron trễ": giữ 1 bộ kiểm tra (chọn bộ của ProjectNotificationHandler vì đã có doc), DiscordHub `CronHealthMonitor` chuyển thành phát sự kiện qua bus thay vì tự gửi.
10.3 Uptime: `SiteHealthChecker` dùng `WpOrg\Requests\Requests::request_multiple` (hoặc `Requests::request_multiple` theo bản WP) theo lô 10, timeout 10s, `sslverify` = true (ô tắt riêng từng project), lưu `response_ms` + status vào bảng mới `wp_laca_uptime_log` (version-check install, purge 30 ngày); cảnh báo khi 2 lần liên tiếp lỗi; biểu đồ 24h/7 ngày ở trang project + cột Fleet.
10.4 Test (không gửi ra ngoài: kênh để trống, Email qua Mailpit). Commit.

## PHASE 11 — Tính năng cho khách (đề xuất 8, 9, 10)

11.1 **Gửi yêu cầu hỗ trợ (CP)**: mục admin bar "Hỗ trợ" + widget Dashboard: form (tiêu đề, loại: lỗi/sửa nội dung/tính năng/khác, mức ưu tiên, mô tả, đính kèm ảnh qua Media Library → gửi URL) → gửi `client_request` có ký về hub. Danh sách "Yêu cầu của tôi" lấy từ hub (endpoint mới `GET /laca/v1/client/requests` có ký) hiển thị trạng thái. Hub: lưu vào hệ Issues/Action Center hiện có (đọc `LacaProjectsHub` để dùng đúng cấu trúc), thông báo qua bus, admin đổi trạng thái/trả lời → client thấy.
11.2 **Báo cáo tháng (M)**: client gửi `monthly_summary` (ngày 1 hằng tháng, có ký): số liệu GA4/GSC nếu GoogleServices đã cấu hình (sessions, users, clicks, impressions, top 5 trang), số bài/trang mới, sự kiện bảo mật tháng. Hub ghép + uptime + các lần cập nhật/backup → tạo PDF (dùng engine của `ProjectPdfExporter`) — **mặc định KHÔNG gửi**: mỗi project có công tắc "Tự gửi báo cáo tháng" (mặc định tắt) + nút "Xem trước" / "Gửi ngay" (gửi tới email liên hệ của project). Mẫu email qua `__()`.
11.3 **Vai trò "Khách hàng" (CP)**: role `laca_client` (tạo/cập nhật qua version-check): quản lý bài/trang/media/menu/CPT nội dung, xem & xuất form liên hệ, sửa SEO từng bài; KHÔNG có plugins/themes/users/tools/Laca Admin/Theme Options kỹ thuật. Ẩn menu tương ứng + chặn truy cập trực tiếp. Trang "Hướng dẫn sử dụng" (nội dung qua `__()`, có mục: sửa trang bằng block, thêm bài, xem liên hệ, gửi hỗ trợ). Laca Admin có ô gán role cho user. Super user không bị ảnh hưởng.
11.4 Test bằng user `[TEST] khachhang` trên lacadev-client.local (liệt kê để xoá). Commit M / CP.

## PHASE 12 — Class hoá 16 trang Project hub (UI)

Theo danh sách ở báo cáo fix 4A (Dashboard, Action Center, Pipeline, Health, Khách hàng, Issues, Updates, Renewals, Vận hành, Finance, Reports, Portal preview, Notifications Center, PDF Export, Tracker Export, Block Catalog). Mỗi trang: bỏ `style=""`/`<style>` inline → component `.laca-*` (xem `admin.php?page=laca-ui-kit`), giữ nguyên logic/AJAX/ID mà JS dùng. Chụp ảnh trước/sau từng trang. Chia commit theo nhóm 4 trang.

---

## Mẫu báo cáo (mỗi phase)
```
PHASE: <số>   TRẠNG THÁI: xong | dừng (lý do)
COMMIT: <repo> <hash> <message>
FILE THÊM/SỬA: …
SO SÁNH FUNCTION (file M/CP có sẵn bị sửa): file — trước/sau — hàm mất (phải 0)
KIỂM TRA: php -l, build, tools/smoke.sh (dán bảng), test riêng của phase (dán output)
DỮ LIỆU TEST TẠO RA: … (để người dùng xoá)
ĐỀ XUẤT XOÁ: …
SAI LỆCH SO VỚI PLAN / CÂU HỎI: …
```
