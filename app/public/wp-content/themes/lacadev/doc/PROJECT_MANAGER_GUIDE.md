# Hướng dẫn sử dụng Hệ thống LacaDev Project Manager

Hệ thống quản trị dự án LacaDev được thiết kế tối ưu trên chính WordPress CMS, giúp bạn dễ dàng quản lý thông tin khách hàng, lưu trữ an toàn các loại password, quản lý tiền bạc, theo dõi tự động lỗi, thao tác trên web của khách hàng và xuất báo giá dạng PDF một cách cực kỳ chuyên nghiệp.

Dưới đây là cẩm nang hướng dẫn sử dụng toàn tập.

---

## 1. Lưu trữ và Nhập Dự án Mới

### Các bước nhập dự án

1. Truy cập vào **Dashboard -> Projects -> Thêm mới (Add New)**.
2. Nhập **Tên dự án** hoặc Tên Website khách hàng lên thanh tiêu đề chính.
3. Kéo xuống mục thiết lập **⚙️ Quản lý Dự án | Project Manager**, bạn sẽ thấy các tab thông tin riêng biệt để điền:
   - **👤 Khách hàng**: Tên, Số điện thoại (Zalo), Email, Địa chỉ, và Phân loại khách hàng (VIP/Thường).
   - **📊 Trạng thái & Phân công**: Tiến độ thi công, DEV phụ trách.
   - **⏰ Mốc thời gian**: Ngày bắt tay vào làm, ngày dự kiến bàn giao và bộ tickbox Checklist các công việc cần làm trước khi bàn giao.
   - **💰 Tài chính**: Nhập **Giá build năm đầu** và **Phí bảo trì hàng năm**.
     - Ở đây đã được tích sẵn Auto Mask (vd khi bạn gõ tiền 3000000 sẽ hiển thị 3.000.000 vnđ).
     - Hỗ trợ thanh toán từng phần: Nếu bạn chia làm nhiều đợt thanh toán, khi điền giá build là 5.000.000 VNĐ, nhập lịch sử vào lần 1: 3.000.000 VNĐ thì hệ thống báo status là "Partial" (đã thu một phần), nhập vào lần 2: 2.000.000 VNĐ thì trạng thái sẽ tự nhảy sang "Paid" (Đã thu đủ).
     - Đính kèm Bill/hóa đơn chuyển khoản vào nếu có.
   - **🖥️ Hosting & Domain**: Đây là nơi bảo mật nhất. Bạn điền Tên miền, Hosting, FTP, cPanel, SSL Password... Hệ thống sẽ sử dụng thuật toán **AES-256-CBC Encryption** để mã hoá các mật khẩu này ngay khi lưu xuống Database. Khi bạn tải trang Edit, LacaCMS mới giải mã để hiển thị cho bạn. Không ai đọc được pass từ database trừ chính website này! Đừng quên cài đặt Ngày hết hạn cho các dịch vụ để có thông báo hết hạn.
   - **🔧 Bảo trì**: Loại bảo trì và cam kết support trong bao nhiêu lâu.
4. Bấm **Đăng (Publish)** hoặc **Cập nhật (Update)** để lưu dữ liệu.

---

## 2. Xuất Hóa Đơn / Báo Giá PDF (PDF Proposal)

Tính năng này sẽ gom lại các thông tin: *Khách hàng, Thời gian, Giá build, Phí bảo trì và các cam kết checklist* để tạo thành một bảng báo giá / hóa đơn bàn giao nhìn rất xịn xò với bố cục Minimalism tĩnh, xuất logo công ty từ cấu hình "Laca Tools - Export Template".

1. Bạn phải đảm bảo đã **Đăng** hoặc **Lưu** dự án trước (để dữ liệu trong form đã được cập nhật vào server).
2. Nhìn sang cột bên tay phải (Sidebar), phía dưới mục *Xuất bản (Publish)*, bạn sẽ thấy khung: **🛠 Hành động thao tác**.
3. Bấm vào nút **"📄 Xuất Báo giá PDF"**.
4. Hệ thống sẽ tự tạo file `PDF` hoàn chỉnh (cung cấp bởi thư viện mPDF chuẩn tiếng Việt) và tải về trình duyệt máy bạn (không lưu rác lên hosting). Dùng file này để gửi đối tác ngay lập tức!

---

## 3. Hệ thống Theo dõi Web Khách hàng (Auto Activity Tracker)

Tính năng đắt giá nhất của LacaDev Project Manager. Nó giúp bạn "cài cắm" một hệ thống theo dõi ngầm lên website của khách. Giúp bạn luôn làm chủ được vấn đề "khách hay tự ý nâng cấp, dẫn đến hỏng web rồi bắt đền DEV".

### Cách cài đặt lên website của khách

1. Trong màn hình sửa Project (của dự án bạn muốn theo dõi).
2. Bạn sẽ thấy ngay hộp bự nhất và ở chính giữa bên dưới Carbon Fields là: **📋 Lịch sử & Cảnh báo (Logs & Alerts)**.
3. Bấm vào nút **"🚀 Xem code PHP Tracker"** để mở một popup SweetAlert2 hiển thị code với nút **"📋 Copy Code"** cực kì tiện. Hoặc bấm nút **"⬇️ Tải file MU-Plugin PHP"** để tải file `.php` mã nguồn (file này được hệ thống tự gen mà không cần tạo file cứng trên hosting LacaDev CMS của bạn).
4. Code này bao gồm:
   - `API Endpoint`: Đường dẫn kết nối trở lại trang hệ thống CMS Laca.
   - `Secret Key`: Khóa bảo mật ngẫu nhiên tự tạo (**độc nhất vô nhị** của dự án này), ngăn giả mạo thông tin.
5. Đăng nhập vào hosting của web khách hàng, ở mục `wp-content/mu-plugins/` hãy ném đoạn mã hoặc upload file php bạn vừa tải về qua (nếu chưa có thì tự tạo tên tự do vd: `l-tracker.php`). Phân quyền file (CHMOD) thành `0444`.

### Cách thức Tracker vận hành

Ngay khi có người ở bên web khách thực hiện một trong các thao tác sau:

- Nâng cấp/cài đặt (Update/Install) **Plugin**.
- Nâng cấp/cài đặt (Update/Install) **Theme**.
- WordPress tự động (hoặc người khác) chạy Core Update.
- Ai đó sửa tệp tin thông qua Theme/Plugin Editor (Cảnh báo mã độc/code modification).

Bộ Tracker ở web khách sẽ gửi thông báo (ping) trong vòng tích tắc trực tiếp về hệ thống Laca CMS của bạn!

Bạn có thể theo dõi nó bằng cách: Mở trang Edit Project ra, xem meta box **📋 Lịch sử & Cảnh báo (Logs & Alerts)**.

- **Logs thông báo**: Các bản ghi cập nhật thường.
- **Alert nguy hiểm**: Các bản ghi như sửa file Theme, sửa code... (Hiển thị tab Cảnh báo, viền màu đỏ đậm rất dễ nhìn).

---

## 4. Quản trị vòng đời và Cảnh báo Hết hạn (System Cronjob)

- Tại trang **Dashboard** chính của WordPress admin, ngay khi đăng nhập, bạn sẽ thấy Widget **Quản lý Dự án LacaDev** rất to và rõ ràng.
- Widget tóm tắt: Có bao nhiêu dự án Đang code, Đã xong, Bảo trì; Có bao nhiêu thẻ Domain/Hosting sắp hết hạn.
- **Cảnh báo (Alerts)**:
  - Hệ thống CMS sẽ chạy tự động Cronjob vào mỗi ngày một lần (daily).
  - Cronjob quét tất cả các projects. Dự án nào có Domain, Hosting hoặc SSL có ngày hết hạn `(Expiry Date)` nhỏ hơn hoặc sắp đến mức "cảnh báo báo trước `X` ngày" -> Hệ thống tạo 1 dòng Cảnh báo vào mục Alerts màu đỏ.
- **Xử lý Alert**: Sau thao tác (Gia hạn cho khách, hoặc đọc xong alert), hãy bấm nút **"✓ Đánh dấu đã xử lý"** ngay tại hộp Logs của giao diện sửa Project.
- **Lưu Logs Thủ công**: Bạn có thể chủ động nhập nhật ký bảo hành web cho khách dưới panel **Thêm nhật ký** có sẵn hộp soạn thảo nhằm tra cứu sau này cực kì tiện lợi.

---

## 5. Sổ tay File Logic (Files Reference)

Dưới đây là danh sách File Core nắm giữ logic của chức năng **Project Manager**, tiện cho bạn việc kiểm tra và bảo trì bảo dưỡng sau này:

- `app/src/PostTypes/project.php`
  - Nơi đăng ký CPT `project`.
  - Khai báo meta fields bằng thư viện Carbon Fields.
  - Phụ trách Render Backend Metabox cho form Logs & Alerts, Nút PDF, các Action chuyển hướng File Download. Thao tác tự động mã hóa Security AES-256 các thẻ mật khẩu cũng nằm ở hook `carbon_fields_post_meta_value_save`.

- `app/src/PostTypes/ProjectAlert.php` | `ProjectLog.php`
  - Hai Models quản lý việc Thêm/Xóa/Resolved các Logs và Alerts, thực thi tương tác qua DB tự sinh (custom db logic).

- `app/src/Settings/LacaTools/ProjectPdfExporter.php`
  - Chức năng gen file Báo giá PDF bằng thư viện `mpdf/mpdf`. Map dữ liệu dự án lên một khung thiết kế Template HTML. Nhanh, nhẹ, hỗ trợ xử lý Font Tiếng Việt mượt mà.

- `app/src/Settings/LacaTools/ProjectTrackerGenerator.php`
  - Nơi lấy template mã theo dõi khách hàng `tmpl-tracker.txt` và bind các mã như Auth Key / Webhook Endpoint vào nhằm xuất bản code (Export ra PHP hoặc Blob Download).

- `app/src/Api/TrackerApi.php`
  - Rest API hứng tín hiệu của các Website khách hàng từ Tracker Plugin gửi về LacaDev CMS.

- `resources/scripts/admin/project.js`
  - Scripts chỉ chạy trong Backend WP, đảm nhiệm format các dãy số tiền (Auto currency mask), theo dõi Auto toán và auto Switch Status Payment khi đủ tiền (Pending > Partial > Paid), xử lý Action click nút SweetAlert của Auto Tracker, Ajax (Add/Resolve/Delete Alerts).

- `resources/styles/admin/_project.scss`
  - Giao diện Admin cho Logs và metabox (có prefix classes `laca-pm-*`).
