# HỆ THỐNG QUẢN LÝ CẤU HÌNH BẬT, TẮT TÍNH NĂNG CỦA ỨNG DỤNG WEB (FEATURE FLAGS)

> **Báo cáo Bài tập lớn: Phát triển phần mềm mã nguồn mở (PTPMMNM)**  
> **Lớp:** CNTT 18-09 &bull; **Nhóm:** 5 &bull; **Đề tài:** 1

---

## 1. THÀNH VIÊN NHÓM VÀ BẢNG PHÂN CÔNG ĐÓNG GÓP

| STT | Họ và tên | Vai trò | Phân công nhiệm vụ chính | Mức độ đóng góp |
| :---: | :--- | :---: | :--- | :---: |
| 1 | **Nguyễn Văn Đạt** | Trưởng nhóm | Thiết kế kiến trúc lõi (`FeatureFlagService`, `VersionService`), giải thuật băm nhất quán `crc32`, xây dựng Database Schema 11 bảng, viết bộ kiểm thử tự động `tests/test_mandatory.php`. | 100% |
| 2 | **Vũ Hồng Sơn** | Thành viên | Xây dựng hệ thống phân quyền máy chủ RBAC (`PermissionService`), quản lý dự án (`projects.php`), quản lý thành viên & nhóm khách hàng (`members.php`, `groups.php`), giao diện Dark/Light mode. | 100% |
| 3 | **Nguyễn Văn Chung** | Thành viên | Phát triển ứng dụng Web bán hàng mô phỏng CDS STORE ([demo-app.php](demo-app.php)), tích hợp 10 Feature Flags, cơ chế Fallback ngắt API, chức năng giỏ hàng và thanh toán. | 100% |

---

## 2. TỔNG QUAN ĐỀ TÀI & 5 CHỨC NĂNG CHÍNH

Hệ thống cho phép nhóm phát triển điều khiển bật/tắt tính năng theo thời gian thực mà không cần deploy lại mã nguồn.

1. **Quản lý dự án, thành viên & 2 môi trường**: Hỗ trợ môi trường `testing` (thử nghiệm) và `staging` (vận hành mô phỏng). Phân biệt quyền giữa Admin, Editor và Viewer.
2. **Khai báo tính năng, nhóm người dùng & tỷ lệ rollout**: Hỗ trợ 10 cờ tính năng, phân tập 1.000 khách hàng (`KH1 - KH1000`) vào 3 nhóm (`nhom_noibu`, `nhom_beta`, `nhom_chung`). Thứ tự ưu tiên nghiêm ngặt:
   $$\text{Master Enabled} \longrightarrow \text{User Override} \longrightarrow \text{Target Groups} \longrightarrow \text{Rollout \%} \longrightarrow \text{Fallback (false)}$$
3. **API PHP & Ứng dụng mô phỏng**: API [api/evaluate.php](api/evaluate.php) trả kết quả JSON thời gian thực. Ứng dụng demo CDS STORE ([demo-app.php](demo-app.php)) có nút mô phỏng lỗi cúp mạng API (`simulate_error`), tự động rơi về giá trị dự phòng an toàn.
4. **Quản lý phiên bản cấu hình (Snapshot & Rollback)**: Lưu snapshot ([versions.php](versions.php)), so sánh sai khác trực quan ([version-compare.php](version-compare.php)), và khôi phục (Rollback) về phiên bản cũ chính xác 100%.
5. **Nhật ký thay đổi & Nhập/Xuất cấu hình**: Ghi log kiểm toán ([audit-logs.php](audit-logs.php)). Nhập/xuất JSON ([import-export.php](import-export.php)) có bộ kiểm tra dữ liệu (Validation Engine) xác thực schema và tỷ lệ 0-100%.

---

## 3. CẤU TRÚC THƯ MỤC DỰ ÁN

```text
PTPMMNM-CNTT-1809-NHOM-5/
├── api/
│   ├── evaluate.php            # API PHP quyết định Bật/Tắt tính năng
│   └── toggle.php              # API Bật/Tắt nhanh qua AJAX
├── assets/                     # CSS, JS và tài nguyên giao diện
├── data/
│   └── fake-data.php           # Nạp 1.000 khách hàng giả lập và 10 cờ mẫu
├── includes/
│   ├── db.php                  # Kết nối PDO MariaDB/MySQL
│   ├── helpers.php             # Các hàm tiện ích, bảo mật CSRF, Session
│   ├── header.php / footer.php # Layout dùng chung
│   └── sidebar.php / topbar.php# Thanh điều hướng và chọn môi trường
├── services/
│   ├── DatabaseService.php     # Tương tác CSDL MySQL 11 bảng
│   ├── FeatureFlagService.php  # Lõi đánh giá cờ, băm ổn định crc32
│   ├── PermissionService.php   # Ma trận phân quyền RBAC (Admin, Editor, Viewer)
│   └── VersionService.php      # Quản lý snapshot, rollback, so sánh version
├── tests/
│   └── test_mandatory.php      # Bộ kiểm thử tự động 5 phép thử bắt buộc
├── audit-logs.php              # Nhật ký kiểm toán hệ thống
├── dashboard.php               # Bảng điều khiển tổng quan
├── database.sql                # Cấu trúc CSDL 11 bảng và dữ liệu khởi tạo
├── demo-app.php                # Ứng dụng Web bán lẻ Apple CDS STORE mô phỏng
├── environments.php            # Quản lý 2 môi trường (testing, staging)
├── flags.php / flag-detail.php # Quản lý và cấu hình chi tiết cờ tính năng
├── groups.php                  # Phân tập 3 nhóm khách hàng
├── import-export.php           # Nhập và xuất cấu hình JSON (có kiểm tra)
├── members.php                 # Quản lý tài khoản & ma trận phân quyền
├── projects.php                # Quản lý dự án
├── setup.php                   # Trang quản lý kết nối CSDL và nạp database.sql
├── version-compare.php         # So sánh diff giữa 2 phiên bản
├── versions.php                # Quản lý danh sách snapshot phiên bản
├── LICENSE                     # Giấy phép phần mềm mã nguồn mở MIT
└── README.md                   # Tài liệu hướng dẫn sử dụng và báo cáo
```

---

## 4. HƯỚNG DẪN CÀI ĐẶT VÀ KHỞI CHẠY (QUICK START)

### Yêu cầu môi trường
- XAMPP (Apache 2.4+, PHP 8.1+, MySQL / MariaDB 10.4+)
- Thư mục đặt mã nguồn: `C:\xampp\htdocs\PTPMMNM-CNTT-1809-NHOM-5\`

### Các bước cài đặt:
1. Mở **XAMPP Control Panel** và nhấn **Start** cả 2 dịch vụ **Apache** và **MySQL**.
2. Mở trình duyệt Web và truy cập trang tự động thiết lập CSDL:
   ```text
   http://localhost/PTPMMNM-CNTT-1809-NHOM-5/setup.php
   ```
   *(Nếu dùng cổng tùy chỉnh như 8080: `http://localhost:8080/PTPMMNM-CNTT-1809-NHOM-5/setup.php`)*
3. Nhấn nút **"Nạp lại CSDL từ database.sql"** để tự động tạo 11 bảng và nạp 1.106 bản ghi dữ liệu mẫu.
4. Truy cập trang đăng nhập và sử dụng tài khoản mẫu:
   - **Quản trị viên (Admin)**: `admin` / `admin123` *(Toàn quyền hệ thống)*
   - **Biên tập viên (Editor)**: `editor` / `editor123` *(Quyền tạo và sửa cờ)*
   - **Người xem (Viewer)**: `viewer` / `viewer123` *(Chỉ xem, bị từ chối sửa)*

---

## 5. HƯỚNG DẪN CHẠY KIỂM THỬ (5 PHÉP THỬ BẮT BUỘC)

Dự án cung cấp bộ kiểm thử tự động [tests/test_mandatory.php](tests/test_mandatory.php) kiểm tra toàn diện 5 phép thử:

### Cách 1: Chạy trực tiếp qua dòng lệnh (CLI)
```bash
cd C:\xampp\htdocs\PTPMMNM-CNTT-1809-NHOM-5
C:\xampp\php\php.exe tests\test_mandatory.php
```

### Cách 2: Xem kết quả trên giao diện Web
Truy cập:
```text
http://localhost/PTPMMNM-CNTT-1809-NHOM-5/tests/test_mandatory.php
```

### Kết quả kiểm thử:
- **Phép thử 1 (Tính ổn định & Tỷ lệ 0%, 100%)**: `PASS` (Băm nhất quán, 0% luôn false, 100% luôn true).
- **Phép thử 2 (Thứ tự ưu tiên danh sách người dùng)**: `PASS` (User Override ghi đè tuyệt đối quy tắc tỷ lệ).
- **Phép thử 3 (Phân quyền tài khoản Viewer)**: `PASS` (Viewer bị chặn mọi thao tác sửa với mã HTTP 403).
- **Phép thử 4 (Khôi phục bản cũ & Bảo toàn lịch sử)**: `PASS` (Rollback chính xác, lịch sử phiên bản giữ nguyên).
- **Phép thử 5 (Ngắt API & Fallback)**: `PASS` (Ứng dụng dùng fallback an toàn `false`, không bị gián đoạn).

---

## 6. KỊCH BẢN TRÌNH DIỄN 2 TÌNH HUỐNG SỬ DỤNG (BẢO VỆ ĐỒ ÁN)

### Tình huống 1: Tạo cấu hình &rarr; Kiểm tra &rarr; Phát hành &rarr; Gọi API từ ứng dụng
1. Đăng nhập tài khoản `admin`.
2. Vào **Cờ tính năng** ([flags.php](flags.php)) &rarr; Bấm **Tạo cờ mới** (ví dụ cờ `banner_quang_cao`).
3. Vào cấu hình chi tiết ([flag-detail.php](flag-detail.php)) &rarr; Chọn môi trường `testing` &rarr; Cài đặt Rollout `50%` hoặc Override `KH1 = Bật`.
4. Vào **Phiên bản** ([versions.php](versions.php)) &rarr; Bấm **Tạo phiên bản mới** với ghi chú: *"Phát hành thử nghiệm banner"*.
5. Chuyển sang **Ứng dụng Demo** ([demo-app.php](demo-app.php)) &rarr; Nhập `KH1` để thấy tính năng hiển thị thực tế qua API.

### Tình huống 2: Đổi tỷ lệ áp dụng &rarr; So sánh phiên bản &rarr; Khôi phục bản cũ &rarr; Kiểm tra ứng dụng
1. Vào **Cờ tính năng** &rarr; Chỉnh cờ `thanh_toan_nhanh` từ `50%` về `0%` và tạo snapshot phiên bản mới.
2. Vào **So sánh phiên bản** ([version-compare.php](version-compare.php)) &rarr; Chọn 2 phiên bản để xem bảng đối chiếu sai khác.
3. Vào **Phiên bản** &rarr; Tìm phiên bản snapshot trước đó &rarr; Bấm nút **Khôi phục (Rollback)**.
4. Mở lại **Ứng dụng Demo** ([demo-app.php](demo-app.php)) &rarr; Kiểm tra tính năng thanh toán nhanh đã được hoàn nguyên về trạng thái ban đầu.

---

## 7. GIẤY PHÉP VÀ NGUỒN TÀI NGUYÊN MÃ NGUỒN MỞ

- **Giấy phép phần mềm:** [MIT License](LICENSE) (Tự do sử dụng, chỉnh sửa và phân phối cho mục đích học tập).
- **Thư viện và công nghệ mã nguồn mở sử dụng:**
  - **PHP 8.1+ & PDO**: Xử lý backend hướng dịch vụ, bảo mật Prepared Statements chống SQL Injection.
  - **MariaDB / MySQL 10.4+**: Hệ quản trị cơ sở dữ liệu quan hệ chuẩn ACID.
  - **Chart.js**: Thư viện biểu đồ mã nguồn mở trực quan hóa thống kê cờ và lưu lượng truy cập.
  - **Tabler Icons / Feather Icons**: Bộ biểu tượng SVG mã nguồn mở chuẩn giao diện người dùng.

