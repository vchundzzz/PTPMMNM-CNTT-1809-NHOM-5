<<<<<<< HEAD
# Hệ Thống Quản Lý Cấu Hình Bật / Tắt Tính Năng Cho Web Apple (Feature Flags)

> **Môn học:** Phát Triển Ứng Dụng Mã Nguồn Mở  
> **Đề tài 1:** Xây dựng hệ thống quản lý cấu hình bật, tắt tính năng của ứng dụng Web  
> **Giấy phép:** [MIT License](LICENSE) (Mã nguồn mở)  
> **Kiến trúc:** Zero-Dependency, CSDL SQLite tự chứa, chạy trực tiếp với PHP Built-in Server.

---

## 🎯 1. Bối Cảnh & Kiến Trúc 3 Thành Phần

Khi Apple chuẩn bị ra mắt tính năng **"Banner iPhone 17 Pro Max"** hoặc **"Nút đặt trước iPhone 17"**, việc phát hành đồng loạt cho 100% người dùng có thể dẫn tới sự cố quá tải máy chủ. Hệ thống **Feature Flag (Công tắc tính năng)** cho phép bật tính năng cho một tỷ lệ % người dùng nhất định (ví dụ 40%) hoặc mở riêng cho danh sách khách VIP (Whitelist).

### Sơ Đồ Hệ Thống:
```text
Trang Web Admin (Quản trị danh mục & Cấu hình)  →  Database SQLite  ←  API PHP  ←  Web Apple Demo (Hỏi: Bật hay Tắt?)
```

1. **Trang Quản Trị Web Admin (`public/index.html`):** Giao diện chuẩn AdminLTE theo mẫu thực tế:
   - **Quản trị danh mục sản phẩm (iPhone, Mac, iPad):** Hiển thị danh sách sản phẩm với STT hộp xám, checkbox Trang chủ/Menu/Hiển thị, trạng thái `● Dofollow ● Index`, `● Đang bán`, cùng 3 nút icon chức năng: **Xem** (Xanh `#00c0ef`), **Sửa** (Vàng cam `#f39c12`), **Xóa** (Đỏ `#dd4b39`).
   - **Quản trị tính năng (Feature Flags):** Nơi người quản trị bật/tắt cờ, kéo thanh trượt tỷ lệ %, lưu phiên bản bất biến, so sánh khác biệt và khôi phục.
2. **API PHP (`api/evaluate.php`, `api/products.php`...):** Tiếp nhận câu hỏi `(user, flag, env)` và trả về `true` hoặc `false` cùng lý do. Quản lý toàn diện CRUD sản phẩm có chặn phân quyền RBAC.
3. **Web Apple (`public/apple.html`):** Website mô phỏng Apple Store gọi API PHP. Nếu API lỗi, tự động kích hoạt giá trị dự phòng Fallback (Tắt tính năng mới, web vẫn chạy mượt mà không crash).

---

## 🚀 2. Hướng Dẫn Khởi Động Nhanh (1-Click)

### Cách 1: Chạy bằng file Batch (Windows)
Nhấp đúp chuột vào tệp:
```cmd
run.bat
```
*(Hệ thống tự động nhận diện PHP từ XAMPP hoặc biến môi trường PATH, mở trình duyệt tại `http://localhost:8000`)*.

### Cách 2: Khởi động thủ công qua lệnh PHP
Mở Terminal/PowerShell tại thư mục dự án và chạy:
```bash
php -S localhost:8000 router.php
```

- **Trang Quản Trị:** [http://localhost:8000](http://localhost:8000)
- **Web Apple Store Demo:** [http://localhost:8000/apple](http://localhost:8000/apple)

---

## 📋 3. Năm Chức Năng Hoàn Thành

### 1. Đăng nhập và phân quyền (RBAC)
- **2 loại tài khoản:**
  - `Nguyễn Quản Trị` (Quyền: **Sửa** - Toàn quyền).
  - `Trần Khách Xem` (Quyền: **Xem** - Chỉ đọc).
- **Chặn quyền ở Server:** Khi tài khoản "Xem" cố tình gửi yêu cầu sửa đổi (POST/PUT/DELETE), server từ chối ngay lập tức với mã lỗi **`HTTP 403 Forbidden`**, không chỉ ẩn nút ở giao diện.
- **2 môi trường độc lập:** `thu_nghiem` (Staging) và `van_hanh` (Production). Chuyển môi trường nào thì xem và sửa cấu hình của môi trường đó.

### 2. Tạo tính năng và quy tắc
- Tạo tính năng gồm: Tên tính năng, Mã cờ, Mô tả, Môi trường.
- Đặt 2 loại quy tắc:
  - **Danh sách người dùng (Whitelist):** Luôn BẬT.
  - **Tỷ lệ phần trăm (% Rollout):** Từ 0 đến 100%.
- **Thứ tự ưu tiên cố định:**
  `Danh sách Whitelist (Xét trước) > Công tắc chính > Tỷ lệ % Rollout (Xét sau)`.

### 3. API và Web Apple
- **API `/api/evaluate.php`:** Nhận tham số `user`, `flag`, `env` và trả về `{ "enabled": true/false, "reason": "...", "hash_bucket": 42 }`.
- **Công thức tính tỷ lệ (Không dùng random):**
  $$\text{crc32}(user + flag + env) \pmod{100} < \text{tỷ\_lệ}$$
  Đảm bảo tính tất định: Cùng một người dùng và môi trường luôn nhận một kết quả cố định.
- **Cơ chế dự phòng Fallback:** Web Apple có công tắc *"Mô phỏng Lỗi API / Mất mạng"*. Khi kích hoạt, Web Apple tự chuyển sang đọc giá trị dự phòng an toàn (Tắt tính năng mới), ghi log cảnh báo và website vẫn vận hành bình thường.

### 4. Quản lý phiên bản (Versioning)
- **Nút "Lưu phiên bản" / "Phát hành":** Chụp lại toàn bộ cấu hình 10 tính năng hiện tại thành bản snapshot bất biến (`v1`, `v2`, `v3`...). Bản đã phát hành **bị khóa, không cho sửa trực tiếp**.
- **Nút "So sánh (Visual Diff)":** Hiển thị bảng so sánh 2 cột trực quan. Dòng nào khác nhau (tỷ lệ, công tắc, whitelist) được tô màu cảnh báo.
- **Nút "Khôi phục (Rollback)":** Tạo một phiên bản mới giống hệt bản cũ và phát hành ngay lập tức.

### 5. Lịch sử thay đổi & Nhập/Xuất JSON
- **Nhật ký Audit Trail:** Mỗi lần phát hành hoặc khôi phục đều lưu: Ai thực hiện, lúc nào, phiên bản nào và lý do thay đổi.
- **Xuất file JSON:** Tải xuống cấu hình của môi trường hiện tại.
- **Nhập từ file JSON (Kiểm tra hợp lệ nghiêm ngặt):**
  - Tên tính năng không được để trống.
  - Tỷ lệ % phải là số từ 0 đến 100.
  - Nếu vi phạm bất kỳ cờ nào: Báo lỗi đỏ chi tiết và **từ chối toàn bộ, không nhập vào CSDL**.

---

## 💾 4. Cấu Trúc Cơ Sở Dữ Liệu Tối Thiểu (SQLite)

Hệ thống sử dụng tệp CSDL SQLite tự chứa tại `data/feature_flags.sqlite` với 5 bảng chuẩn:

| Bảng | Chức năng lưu trữ |
| :--- | :--- |
| `users` | Tài khoản quản trị và 1.000 mã người dùng giả lập (`U0001` - `U1000`), phân quyền xem/sửa, phân nhóm (VIP, Beta, Khách thường). |
| `environments` | Danh sách 2 môi trường: `thu_nghiem` và `van_hanh`. |
| `flags` | Danh sách tính năng của từng môi trường (key, name, description, enabled, percentage, whitelist, fallback). |
| `versions` | Bản chụp cấu hình đóng băng (Snapshot lưu dạng JSON), số hiệu phiên bản (`v1`, `v2`...), trạng thái đã phát hành. |
| `history` | Lịch sử thay đổi, thời gian, người đổi, phiên bản và lý do đổi. |

---

## 🧪 5. Năm Phép Thử Bắt Buộc (Mục 7)

Kiểm tra trực tiếp tại **Tab 4** trên Trang Quản Trị hoặc chạy qua API `/api/test_runner.php`:

1. **Phép thử 1:** Gọi 50 lần cùng một người dùng và môi trường luôn cho kết quả bất biến 50/50 lần; kiểm tra riêng biên 0% luôn TẮT, 100% luôn BẬT.
2. **Phép thử 2:** Người trong danh sách ưu tiên (Whitelist) nhận đúng kết quả BẬT dù tỷ lệ đặt là 0%; Công tắc chính tắt thì cờ bị TẮT dù tỷ lệ 100%.
3. **Phép thử 3:** Tài khoản quyền "Xem" bị máy chủ từ chối với mã lỗi `HTTP 403 Forbidden` khi gửi lệnh sửa trực tiếp.
4. **Phép thử 4:** Khôi phục bản cũ tái tạo chính xác cách bật/tắt như trước đó; lịch sử phát hành và lý do được lưu trữ nguyên vẹn.
5. **Phép thử 5:** Cơ chế dự phòng Fallback kích hoạt an toàn khi máy chủ API lỗi, Web Apple tiếp tục hoạt động không bị crash.

---

## 🎬 6. Hai Tình Huống Trình Diễn Khi Chấm (Mục 5)

Tại **Tab 4** có 2 nút bấm tự động hóa kịch bản chấm điểm:
- **Tình huống 1:** Tạo cờ `demo_promo_tet` với tỷ lệ 80% &rarr; Kiểm tra đánh giá trên bản nháp &rarr; Phát hành phiên bản mới &rarr; Web Apple gọi API nhận cấu hình mới.
- **Tình huống 2:** Đổi tỷ lệ lên 90% mô phỏng sự cố &rarr; So sánh khác biệt Visual Diff với bản v1 &rarr; Bấm Khôi phục (Rollback) &rarr; Hệ thống trở lại an toàn.

---

## 🌟 3 Điểm Cốt Lõi Để Không Mất Điểm
1. **Không dùng `rand()`:** Hoàn toàn dùng hàm băm tất định `crc32(user + flag + env) % 100 < tỷ_lệ` để kết quả luôn ổn định và nhất quán.
2. **Bản đã phát hành không sửa trực tiếp:** Bản phát hành là bất biến (Immutable Snapshot); muốn thay đổi phải sửa trên bản nháp rồi phát hành số hiệu mới (`v2`, `v3`...).
3. **Chặn quyền ở Server:** Tài khoản quyền "Xem" bị chặn trực tiếp bằng mã `HTTP 403 Forbidden` ở tầng máy chủ PHP.
=======
# Lab02 — Bài thực hành dành cho sinh viên

Nhánh này chỉ chứa một bài lab. Dùng Ubuntu/WSL, PHP từ 8.1 và Git.

Không cần PostgreSQL hoặc Composer.

## Chuẩn bị trên Ubuntu/WSL

```bash
sudo apt update
sudo apt install php-cli git unzip
php -v
git --version
```

PHP cần từ 8.1 trở lên. Các lệnh bên dưới chạy trong Ubuntu/WSL. Khi tải nhiều bài, trở về thư mục cha chứa các thư mục lab trước mỗi lệnh clone; không clone bài tiếp theo bên trong `Ma_nguon` của bài trước.

## Tải bài

```bash
git clone --single-branch --branch lab2 https://github.com/FIT-DNU/Open-Source-Software-Development.git lab2
cd lab2/Ma_nguon
```

## Chạy bài

```bash
bash lab.sh check
bash lab.sh run
```

Kết quả hiển thị trong terminal; không cần trình duyệt.

## Kiểm tra

Mở terminal thứ hai tại Ma_nguon, hoặc dừng máy chủ trước:

```bash
bash lab.sh test
bash lab.sh extend
```

test kiểm tra phần mẫu; extend kiểm tra phần tự làm. Sinh viên cần hoàn thiện TODO; extend có thể chưa đạt ở bản khởi đầu.

[Hướng dẫn đầy đủ](HUONG_DAN.md) · [Hướng dẫn mã nguồn](Ma_nguon/README.md)

Không đưa .local, mật khẩu hoặc dữ liệu cá nhân thật vào bài nộp.

## Các bài khác

[Lab01](https://github.com/FIT-DNU/Open-Source-Software-Development/tree/lab1) · [Lab02](https://github.com/FIT-DNU/Open-Source-Software-Development/tree/lab2) · [Lab03](https://github.com/FIT-DNU/Open-Source-Software-Development/tree/lab3) · [Lab04](https://github.com/FIT-DNU/Open-Source-Software-Development/tree/lab4) · [Lab05](https://github.com/FIT-DNU/Open-Source-Software-Development/tree/lab5)

Kết quả yêu cầu của phần mẫu: `TESTS=5 FAILED=0`. `check` chỉ kiểm tra môi trường. `extend` ở bản khởi đầu còn ca chưa đạt vì TODO; không sửa test để bỏ qua lỗi.
>>>>>>> origin/lab2
