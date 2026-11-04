# PLAN — Dọn code/file dư thừa + Tài liệu tổng hợp hệ thống (11/2026)

Người thực hiện: **Antigravity** (code + viết tài liệu). Người review: **Claude Code**.
Antigravity **không commit, không push, không xoá file**. Claude review rồi commit.

## Ký hiệu đường dẫn

| Ký hiệu | Đường dẫn |
| --- | --- |
| `M` | `/Users/lacadev/Documents/wordpress/lacadev/app/public/wp-content/themes/lacadev` (site chính / hub) |
| `CP` | `/Users/lacadev/Documents/wordpress/lacadev-client/app/public/wp-content/themes/lacadev-client` (theme cha site khách) |
| `CC` | `/Users/lacadev/Documents/wordpress/lacadev-client/app/public/wp-content/themes/lacadev-client-child` (theme con site khách) |
| `MR` | `/Users/lacadev/Documents/wordpress/lacadev` (repo git site chính) |
| `CR` | `/Users/lacadev/Documents/wordpress/lacadev-client` (repo git site khách) |

## Quy tắc bắt buộc (nhắc lại)

1. **KHÔNG xoá file/thư mục** (`rm`, `git rm`, `unlink`, `find -delete` đều cấm). Thay vào đó, ghi lệnh xoá vào script ở mục B4 để người dùng tự chạy.
2. Được phép **sửa** file để gỡ chỗ đăng ký/gọi tới code chết (`use`, `new X()`, `require`, đăng ký trong `config.php`, enqueue…).
3. Không sửa `vendor/`, `dist/`, `build/`, `*.min.*` bằng tay. Nếu gỡ package composer thì phải hỏi Claude trước. Đợt này **không** gỡ package nào.
4. Không đụng DB. Dữ liệu test trong DB chỉ ghi vào danh sách ở mục B5 để người dùng tự xoá trong wp-admin.
5. Câu chữ hiển thị trong tài liệu và code: tiếng Việt, đi qua gettext domain `laca` nếu là code.
6. Repo `MR` vừa có commit `d5214168` (áp lại Phase 9–12). Dọn dẹp trên trạng thái này, không revert gì.
7. Sau mỗi phần phải chạy kiểm tra (mục B6) và báo kết quả thật, kể cả lỗi.

---

# PHẦN A — Kiểm chứng trước khi đánh dấu "dư thừa"

Với **từng** ứng viên ở mục B, phải làm đủ 3 bước, rồi ghi kết quả vào bảng báo cáo (cột: file · số chỗ tham chiếu · kết luận GIỮ/XOÁ · lý do):

1. `grep -rn "<TênClass>\|<tên-file>" <theme> --include=*.php --include=*.js --include=*.json --include=*.scss` (loại trừ `vendor/`, `node_modules/`, `dist/`, `build/`).
2. Kiểm tra cả các đăng ký gián tiếp:
   - `config.php` (providers)
   - `theme/functions.php`, `theme/setup/*.php`
   - Autoload `composer.json` mục `files`
   - `webpack`/`package.json` entry
   - `block.json`
   - Chuỗi tên hook `do_action`/`add_action`, route REST
3. Nếu file thuộc **endpoint REST** hoặc **cron** thì xác minh thêm phía site kia có gọi tới không:
   - Endpoint ở hub: grep trong `CP`/`CC`.
   - Endpoint ở client: grep trong `M`.

   Còn được gọi thì **GIỮ**.

**Có nghi ngờ thì GIỮ** và ghi rõ lý do vào báo cáo.

---

# PHẦN B — Danh sách dọn dẹp

## B1. Site chính (`M`) — code chết, ứng viên xoá

Tìm đúng đường dẫn bằng grep. Tên dưới đây là tên class hoặc file đã thấy trong lần khảo sát trước.

| # | Ứng viên | Ghi chú |
| --- | --- | --- |
| 1 | `Updater.php` (updater cũ) | Đã thay bằng endpoint `theme-package` (Phase 8). Kiểm tra không còn đăng ký. |
| 2 | `HookNames` | Hằng tên hook không ai dùng? |
| 3 | `AbstractTaxonomy` | Không có class con? |
| 4 | `AbstractTable` | Kiểm tra code Phase 10/11 (`wp_laca_uptime_log`) có dùng không. |
| 5 | `Validator` (App) | Không xoá package `illuminate/*`, chỉ xoá class của theme nếu chết. |
| 6 | `app/src/Module/` | Cả thư mục. |
| 7 | `theme/setup/sidebars.php` | Kiểm tra `register_sidebar`/`dynamic_sidebar` trong template. |
| 8 | `theme/setup/user-meta.php` | |
| 9 | `theme/setup/blocks/*` | Kiểm tra block đang đăng ký thật nằm ở `block-gutenberg/`. |
| 10 | `ClientWebhook` (`App\Features\ProjectManagement\Api\ClientWebhook`) | ⚠️ `CLAUDE.md` của dự án ghi nó nhận `/laca/v1/client-report`. Phải grep `client-report` trong `CP`. Nếu client còn gọi thì **GIỮ**. Nếu xoá thì cập nhật `MR/CLAUDE.md` và `doc/TRACKER_HUB_CLIENT_SYNC.md`. |
| 11 | `dashboard-search.js` | Kiểm tra entry webpack và enqueue. |
| 12 | `AutoDownloadImage` | |
| 13 | `app/helpers/woocommerce.php` | Site chính không chạy WooCommerce. Kiểm tra `function_exists`/require. |
| 14 | `DB.php` | |
| 15 | `app/src/Models/Post.php` | |
| 16 | Code trùng lặp mới do Phase 9–12 | Ví dụ helper gửi thông báo cũ đã được `NotificationBus` thay thế. Chỉ gỡ khi mọi chỗ gọi đã chuyển sang bus. |

## B2. Theme cha site khách (`CP`)

| # | Ứng viên | Ghi chú |
| --- | --- | --- |
| 1 | `block-gutenberg/ai-translate-plugin/` | Có đăng ký/enqueue ở đâu không? |
| 2 | `app/src/PostTypes/Concerns/BlockSyncSender.php` | Kiểm tra trait `use BlockSyncSender` ở các PostType. |
| 3 | Class/helper chỉ có ở `CP` mà không đăng ký | Quét `config.php` providers so với `app/src/**`. Liệt kê, chưa xoá vội. |

## B3. File/thư mục thừa và file test

| # | Đường dẫn | Loại |
| --- | --- | --- |
| 1 | `CR/app/public/wp-content/lacadev-client/` và `CR/app/public/wp-content/lacadev-client-child/` | Bản copy lạc chỗ (nằm ngoài `themes/`) |
| 2 | `CR/app/public/wp-content/themes/.gitignore` | Thừa, xung đột với `.gitignore` gốc repo. Kiểm tra nội dung trước. |
| 3 | `CC/block-gutenberg/test-verify-block/` | Block test |
| 4 | `MR/app/public/theme-updates/` | Gói thử local, không track |
| 5 | `MR/error_log` | Log production tải về để chẩn đoán |
| 6 | `/Users/lacadev/Documents/wordpress/_git-backup/` | Backup `.git` lồng cũ. Chỉ xoá khi người dùng xác nhận không cần lịch sử cũ. |
| 7 | `CP/doc/**/tools/test-*.php` | Script test thủ công. Đã chặn chạy qua web, có thể **GIỮ**. Báo người dùng chọn. |
| 8 | File rác editor/OS | `.DS_Store`, `*.orig`, `*.rej`, `*.bak` trong cả 3 theme (trừ `vendor`/`node_modules`) |

## B4. Script xoá cho người dùng tự chạy

Tạo 2 file. **Không chạy chúng.**
- `MR/tools/cleanup-2026-11.sh`
- `CR/tools/cleanup-2026-11.sh`

Yêu cầu với mỗi script:
- `#!/usr/bin/env bash`, `set -euo pipefail`. Đầu file `cd` về gốc repo theo đường dẫn tuyệt đối.
- Mỗi mục xoá có một dòng comment ghi lý do và mã mục (vd `# B1-3 AbstractTaxonomy: 0 tham chiếu`).
- File có track trong git thì dùng `git rm -r --quiet <path>`. File không track thì dùng `rm -rf <path>`. Kiểm tra tồn tại trước (`[ -e path ] &&`).
- Chỉ đưa vào các mục có kết luận **XOÁ** ở phần A.
- Cuối script in ra `git status --short` để người dùng xem.

## B5. Dữ liệu test trong DB — để người dùng tự xoá trong wp-admin

Ghi vào báo cáo, không chạy lệnh nào:

| Site | Dữ liệu |
| --- | --- |
| lacadev.local (hub) | Project ID 14, 15 · Request ID 25 · Bài nháp ID 6 |
| lacadev-client.local | User `test_khachhang` |

## B6. Kiểm tra sau khi sửa (trước khi giao Claude review)

Chạy trên `M` và `CP`/`CC`:
1. `php -l` cho mọi file PHP đã sửa.
2. Tạm áp danh sách xoá: không xoá thật, chỉ grep xác nhận không còn `use`/`new`/`require` tới các file sẽ xoá.
3. `composer dump-autoload --dry-run` nếu có. Nếu không thì grep `vendor/composer/autoload_classmap.php` xem class sắp xoá có trong classmap không. Nếu có thì ghi chú rằng **cần chạy `composer dump-autoload` sau khi xoá**, và Claude sẽ chạy.
4. `yarn build` (`M`: `yarn build:theme && yarn build:blocks`; `CC`: `yarn build`). Phải pass.
5. Render thử trang chủ và trang admin hub qua local (`http://lacadev.local`, `http://lacadev-client.local`) bằng `curl`. Phải trả 200, không có chuỗi `critical error`/`Fatal`.

---

# PHẦN C — Tài liệu tổng hợp chức năng và flow

Viết **1 file**: `M/doc/SYSTEM-OVERVIEW.md`. Tạo thêm file ngắn `CP/doc/SYSTEM-OVERVIEW.md` chỉ chứa 3–5 dòng trỏ về file ở hub, kèm tóm tắt phần client.

## Yêu cầu chung
- Tiếng Việt, rõ ràng, phân loại theo đề mục. Dùng bảng cho danh sách chức năng.
- **Chỉ ghi những gì có thật trong code.** Mỗi chức năng phải kèm class/file chính (đường dẫn tương đối theo theme). Chức năng nào không tìm thấy trong code thì không ghi.
- Không ghi secret, key, mật khẩu, token. Chỉ ghi **tên** option/meta (vd `_laca_public_id`), không ghi giá trị.
- Sơ đồ flow dùng khối `mermaid` (`sequenceDiagram`/`flowchart`).
- Đọc trước để khỏi khám phá lại từ đầu:
  - `M/doc/TRACKER_HUB_CLIENT_SYNC.md`
  - `M/doc/NOTIFICATION_RELIABILITY_AND_BILLING.md`
  - `M/doc/PLAN-ROADMAP-2026-11.md`
  - `M/doc/PLAN-SYNC-2026-11.md`

## Mục lục bắt buộc

1. **Tổng quan kiến trúc**
   - Sơ đồ hub ↔ spoke: lacadev.com (hub) ↔ các site khách (lacadev-client, lixroastery, nhakhoathienphuoc, phucdainam…).
   - Stack: WordPress + WPEmerge + Carbon Fields + composer PSR-4 `App\`. Cấu trúc thư mục `theme/` lồng cấp (slug `lacadev/theme`).
   - Bảng repo, theme, domain, workflow deploy.
2. **Site chính (hub) — chức năng.** Mỗi nhóm là một bảng (Chức năng · Mô tả · Class/file · Màn hình admin hoặc route):
   - 2.1 Quản lý dự án (CPT `project`, `LacaProjectsHub` dashboard CRM, fleet, backup)
   - 2.2 Theo dõi và cảnh báo (tracker, uptime song song, SSL expiry, cron health-check)
   - 2.3 Thông báo (`NotificationBus`, `ProjectNotificationHandler`: Email/Zalo/Telegram/Slack/Discord, refresh token Zalo, log lỗi kênh)
   - 2.4 Client Portal và thanh toán
   - 2.5 Yêu cầu hỗ trợ từ khách và báo cáo tháng
   - 2.6 Phân phối cập nhật theme (endpoint `theme-package`, JSON update)
   - 2.7 SEO (LacaSeo: Redirections, 404 log…), bảo mật, công cụ admin khác
   - 2.8 Giao diện admin (bộ UI component)
3. **Site khách (client) — chức năng**
   - 3.1 Theme cha `CP`: tính năng chung (tracker client, remote update, theme updater, ClientSupport, bảo mật, email log, DynamicCPT, Google services…)
   - 3.2 Theme con `CC`: block Gutenberg, Newsletter, phần riêng từng site
   - 3.3 Những gì site khách gửi về hub và những gì nó nhận từ hub
4. **Flow hoạt động**: mỗi flow có sơ đồ mermaid và các bước đánh số.
   - 4.1 Đăng ký site khách vào hub (tạo project, sinh 4 key, nhập key vào client)
   - 4.2 Tracker log: client → `/laca/v1/tracker/log` → lưu → cảnh báo
   - 4.3 Ký HMAC request (header `X-Laca-Project`/`X-Laca-Timestamp`/`X-Laca-Signature`, chuỗi canonical, cửa sổ 300s, chống replay, chế độ legacy `laca_allow_legacy_auth`)
   - 4.4 Remote update: hub ra lệnh cho client
   - 4.5 Cập nhật theme: CI publish → JSON → client kiểm tra → tải gói đã ký → cài
   - 4.6 Uptime và cảnh báo: cron → kiểm tra song song → NotificationBus → các kênh
   - 4.7 Yêu cầu hỗ trợ và báo cáo tháng
   - 4.8 Client Portal và hiển thị thanh toán
   - 4.9 Deploy: push → GitHub Actions → FTP → smoke test (ghi rõ có `concurrency` để không chạy chồng)
5. **Bảng endpoint REST**: Route · Method · Site · Xác thực (HMAC/legacy/cookie) · Class xử lý · Ai gọi.
6. **Bảng cron/scheduled event**: Hook · Chu kỳ · Site · Việc làm.
7. **Option/meta quan trọng**: chỉ ghi tên và ý nghĩa.
8. **Vận hành**
   - Deploy, smoke test (`tools/smoke.sh`), phát hành bản theme client.
   - Xử lý sự cố, kèm bài học 03/11: vendor autoload lệch do các lượt deploy chạy chồng.
9. **Việc còn tồn đọng/TODO**
   - Bước "bait JSON" chỉ gỡ khi mọi client ≥ 3.2.
   - Đề xuất 11 `laca-core` đang hoãn.
   - Phase 8.5 test nhakhoathienphuoc/phucdainam.

---

# PHẦN D — Báo cáo khi xong

Gửi Claude (`send_message`) báo cáo gồm:
1. Bảng kết quả phần A, với mọi ứng viên: GIỮ hoặc XOÁ, kèm lý do.
2. Danh sách file đã **sửa**, theo từng repo.
3. Đường dẫn 2 script cleanup và 2 file `SYSTEM-OVERVIEW.md`.
4. Output thật của các bước kiểm tra B6.
5. Danh sách dữ liệu DB ở B5.

Claude sẽ:
1. Review diff và tài liệu, so với code thật.
2. Gửi feedback cho Antigravity sửa nếu cần.
3. Commit theo từng repo (tách commit `refactor`/`docs`/`chore`).
4. Báo người dùng chạy script cleanup.
5. Sau đó `composer dump-autoload`, build, kiểm tra lại, commit việc xoá.
