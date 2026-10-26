# LAB 01 — VẬN HÀNH DỰ ÁN TRÊN LINUX VÀ VIẾT SHELL SCRIPT
# LAB 02 — XÂY DỰNG MODULE PHP KIỂM TRA ĐIỀU KIỆN ĐĂNG KÝ
>>>>>>> origin/lab2

Học phần: Phát triển phần mềm mã nguồn mở. Đối tượng: sinh viên năm 3, chuyên ngành Phát triển phần mềm. Bài thực hành sử dụng mã nguồn CommunityPass được cấp trong thư mục Ma\_nguon.

## I. MỤC TIÊU VÀ CHUẨN BỊ

Nội dung bài giảng: Chương 2, slide 10–13: Ubuntu; 14–25: Shell, biến, điều kiện, vòng lặp, chuỗi, mảng, hàm. Số trang tính cả trang bìa. Git và kiểm thử được sử dụng để hỗ trợ thực hành.

Sau khi hoàn thành bài thực hành, sinh viên có khả năng:

- Sử dụng lệnh Linux để xác định đúng thư mục và tệp của dự án.
- Viết script có biến, hàm, điều kiện và vòng lặp; đọc đúng mã thoát.
- Kiểm tra công cụ và tệp cần thiết; xử lý trường hợp thiếu điều kiện chạy.
- Viết và kiểm thử script quét nhiều tệp PHP, tổng hợp kết quả và trả đúng mã thoát.
Nội dung bài giảng: Chương 3, slide 3–8: PHP, kiểu dữ liệu, biến, toán tử, điều kiện, hàm và đối tượng. Số trang tính cả trang bìa. Git và kiểm thử được sử dụng để hỗ trợ thực hành.

Sau khi hoàn thành bài thực hành, sinh viên có khả năng:

- Giải thích đầu vào, đầu ra và thứ tự kiểm tra của một quy tắc PHP.
- Viết hàm/lớp độc lập với HTML và CSDL; gọi được từ chương trình thử.
- Tự mở rộng quy tắc với ba kết quả và xử lý dữ liệu biên.
- Hoàn thiện lớp RegistrationPolicy và kiểm thử đầy đủ ba trạng thái đăng ký.
>>>>>>> origin/lab2

Chuẩn bị: đọc nội dung bài giảng được nêu trên, giải nén trọn thư mục của lab và mở Ma\_nguon trong trình soạn thảo. Bài đã có đủ mã và dữ liệu khởi đầu; chỉ sửa những tệp được chỉ rõ trong phần hướng dẫn và bài tự làm.

### Công cụ cần có

Sử dụng terminal Ubuntu hoặc Ubuntu trong WSL, PHP từ 8.1 và Git. Nếu máy đã có đủ công cụ, chỉ cần kiểm tra phiên bản. Trên máy được phép cài phần mềm, thực hiện:

```bash
sudo apt update
sudo apt install php-cli git unzip
php -v
git --version
```

Các lệnh Bash chạy trong terminal Ubuntu, không chạy trong PowerShell. Bài này không cần cơ sở dữ liệu hoặc framework.

### Bối cảnh và nội dung thực hành

CommunityPass là ứng dụng đăng ký tham dự sự kiện. Khi tiếp nhận mã nguồn, cần kiểm tra công cụ PHP, các tệp của dự án và khả năng khởi chạy ứng dụng. Trong bài này, sinh viên sử dụng lệnh Linux và viết Shell Script để tự động thực hiện các bước kiểm tra đó.

Mã PHP đã được cung cấp để phục vụ việc chạy thử. Nội dung thực hành tập trung vào biến, hàm, điều kiện, vòng lặp và xử lý lỗi trong Shell; chưa yêu cầu xây dựng chức năng Web.

![Trang mẫu dùng để kiểm tra môi trường](Hinh_anh/hinh_01.png)

*Trang mẫu dùng để kiểm tra môi trường*

Kết quả mẫu: trình duyệt hiển thị CommunityPass và dòng “Mã nguồn PHP đã chạy trên máy của bạn”.

## II. QUY TRÌNH THỰC HÀNH

![Sơ đồ hướng thực hiện Lab 01](Hinh_anh/hinh_02.png)

*Sơ đồ hướng thực hiện Lab 01*

Sơ đồ thể hiện thứ tự thực hiện và các bước kiểm tra. Tại nút quyết định hình thoi, đối chiếu kết quả với yêu cầu; nếu chưa đạt, quay lại bước xử lý tương ứng trước khi tiếp tục.

Mở thư mục Lab01/Ma\_nguon bằng trình soạn thảo và mở terminal Ubuntu tại thư mục này. Chạy pwd và ls để kiểm tra vị trí; danh sách tệp cần có lab.sh. Các đường dẫn trong bài được tính từ Ma\_nguon. Trên Windows, thực hiện lệnh Bash trong Ubuntu/WSL.
Một sự kiện có sức chứa 10 người. Hệ thống cần xác định có thể tiếp nhận thêm đăng ký hay không dựa trên số người hiện tại. Bài thực hành xây dựng quy tắc này bằng hàm và lớp PHP; chưa sử dụng Form hoặc cơ sở dữ liệu.

Lớp CapacityPolicy nhận sức chứa và số người đã đăng ký, sau đó trả kết quả còn chỗ hoặc không thể đăng ký. Tách quy tắc khỏi giao diện giúp kiểm thử với nhiều bộ dữ liệu và sử dụng lại trong các trang của ứng dụng.

| **Đầu vào** | **Kết quả mẫu** |
| --- | --- |
| Sức chứa 10, hiện có 9 | true, chương trình minh họa in AVAILABLE. |
| Sức chứa 10, hiện có 10 | false, in FULL. |
| Sức chứa 0 hoặc số đã đăng ký âm | false, dữ liệu không hợp lệ. |

Trong mẫu đơn giản, FULL là nhãn chung cho kết quả không thể đăng ký; chưa phân biệt hết chỗ với dữ liệu sai. Phần tự mở rộng sẽ thiết kế kết quả rõ hơn.

## II. QUY TRÌNH THỰC HÀNH

![Sơ đồ hướng thực hiện Lab 02](Hinh_anh/hinh_01.png)

*Sơ đồ hướng thực hiện Lab 02*

Sơ đồ thể hiện thứ tự thực hiện và các bước kiểm tra. Tại nút quyết định hình thoi, đối chiếu kết quả với yêu cầu; nếu chưa đạt, quay lại bước xử lý tương ứng trước khi tiếp tục.

Mở thư mục Lab02/Ma\_nguon bằng trình soạn thảo và mở terminal Ubuntu tại thư mục này. Chạy pwd và ls để kiểm tra vị trí; danh sách tệp cần có lab.sh. Các đường dẫn trong bài được tính từ Ma\_nguon. Trên Windows, thực hiện lệnh Bash trong Ubuntu/WSL.
>>>>>>> origin/lab2

```bash
bash lab.sh check
bash lab.sh run
```

Kết quả chạy: mở http://127.0.0.1:8101. Giữ terminal máy chủ mở, dùng terminal thứ hai tại cùng thư mục để sửa/chạy test. Dừng bằng Ctrl+C; không mở hai máy chủ cùng cổng.
Kết quả chạy: chương trình demo.php in kết quả trong terminal rồi kết thúc; bài này không có trang Web.
>>>>>>> origin/lab2

Kiểm tra trước khi thực hành:

- [ ] Lệnh check xác nhận đủ công cụ để chạy bài thực hành.
- [ ] Chạy được chương trình mẫu và xác định được thư mục chứa mã nguồn.
- [ ] Phân biệt lệnh test dùng cho bài mẫu và lệnh extend dùng cho bài tập mở rộng.

## III. CẤU TRÚC MÃ NGUỒN

Thư mục Lab01/Ma\_nguon đã có mã mẫu cho phần hướng dẫn. Chạy mẫu trước để xem kết quả, sau đó đọc và thực hiện lại các bước ở phần IV trên chính các tệp đó. Khi bài yêu cầu thay toàn bộ nội dung, không chép nối thêm một hàm hoặc lớp trùng tên. Phần mở rộng để sinh viên tự hoàn thiện ở mục VI.

| **Phần thực hành** | **Tệp và công việc** | **Kết quả cần đạt** |
| --- | --- | --- |
| Mẫu có hướng dẫn | scripts/check-env.sh; public/index.php<br>Đọc trang PHP đã cấp; viết lại script kiểm tra PHP, Git và các tệp của dự án theo từng bước ở phần IV. | bash lab.sh test: có CHECK\_ENV=PASS, mã thoát 0. Trang mẫu ở cổng 8101. |
| Bài tự mở rộng | scripts/scan-source.sh<br>Hoàn thiện ba vị trí TODO: kiểm tra thư mục, đếm tệp đúng/sai và trả mã kết quả. Kiểm tra cả thư mục con và tên có khoảng trắng. | bash lab.sh extend: SHELL\_CASES=4 FAILED=0. Có tệp sai: vẫn kiểm tra đủ tệp; thiếu/rỗng: mã 2. |

Vai trò từng tệp trong bài mẫu:

| **Tệp/thư mục** | Chức năng |
| --- | --- |
| public/index.php | Trang Web được cấp sẵn; máy chủ PHP trả trang này cho trình duyệt. |
| README.md | Hướng dẫn giới thiệu và sử dụng dự án. |
| LICENSE | Văn bản giấy phép của mã mẫu. Chỉ đọc, không tự xóa tên tác giả. |
| scripts/check-env.sh | Tệp bạn đọc và viết lại để kiểm tra môi trường và tệp dự án. |

Bash thực hiện các lệnh trong script, thông báo điều kiện đạt hoặc thiếu, sau đó trả mã kết quả cho chương trình gọi. Việc kiểm tra LICENSE trong bài chỉ xác nhận tệp tồn tại, không đánh giá nội dung hoặc việc tuân thủ giấy phép.

## IV. HƯỚNG DẪN THỰC HÀNH

### Bước 1. Thử ba lệnh mà script sẽ sử dụng

Trong terminal đang ở Ma\_nguon, chạy từng dòng:

```bash
php -v
git --version
php -l public/index.php
```

Hai lệnh đầu in phiên bản công cụ. Lệnh thứ ba có chữ No syntax errors detected. Tham số -l yêu cầu PHP chỉ kiểm tra cú pháp tệp, không chạy chức năng của trang. Vì vậy một tệp đúng cú pháp vẫn có thể sai nghiệp vụ.

Để xem lệnh có tồn tại trong môi trường, thử:

```bash
command -v php
```

Lệnh trả về đường dẫn đến PHP, chẳng hạn /usr/bin/php. Đường dẫn cụ thể phụ thuộc môi trường cài đặt.

Kiểm tra kết quả bước 1:

- [ ] Lệnh in phiên bản PHP/Git và thông báo không lỗi cú pháp; biết -l chỉ kiểm tra cú pháp.

### Bước 2. Tạo tệp Shell và chạy phiên bản đầu tiên

Trong trình soạn thảo, tạo thư mục scripts nếu chưa có. Mở tệp scripts/check-env.sh và thay toàn bộ nội dung bằng đoạn sau:

```bash
#!/usr/bin/env bash
project_name="CommunityPass"
echo "Kiểm tra dự án: $project_name"
```

Lưu tệp, quay lại terminal rồi chạy:

```bash
bash scripts/check-env.sh
```

Kết quả: Kiểm tra dự án: CommunityPass.

Dòng đầu chỉ định Bash; project\_name là biến; dấu $ lấy giá trị biến; echo in thông báo. Khi gán biến không đặt khoảng trắng quanh dấu =. Khi gọi qua bash ..., chưa cần cấp quyền thực thi. Sau này có thể dùng chmod +x scripts/check-env.sh rồi gọi ./scripts/check-env.sh.

Kiểm tra kết quả bước 2:

- [ ] Script in Kiểm tra dự án: CommunityPass; tên biến không có khoảng trắng quanh dấu =.

### Bước 3. Thử điều kiện có công cụ PHP

Thay toàn bộ nội dung tệp bằng đoạn dưới, lưu và chạy lại:

```bash
#!/usr/bin/env bash
if command -v php >/dev/null 2>&1; then
    echo "OK tool php"
else
    echo "MISSING tool php"
    exit 2
fi
```

if xem lệnh kiểm tra có thành công hay không; then là nhánh đạt; else là nhánh thiếu; fi kết thúc điều kiện. Cụm &gt;/dev/null 2&gt;&amp;1 chỉ ẩn phần thông báo của lệnh kiểm tra để kết quả gọn; không biến lỗi thành thành công.

Không gỡ PHP khỏi máy để thử nhánh thiếu. Có thể tạm thay tên kiểm tra php thành cong\_cu\_khong\_co, chạy để quan sát rồi đổi lại. Sau một lần chạy, gõ ngay:

```bash
echo "exit=$?"
```

$? chứa mã thoát của lệnh vừa chạy. Mã 0 nghĩa là thành công; mẫu này quy ước 2 là thiếu điều kiện chuẩn bị. Phải đọc mã ngay sau lệnh cần kiểm tra, trước khi chạy lệnh khác.

Kiểm tra kết quả bước 3:

- [ ] Thử tên công cụ không tồn tại thấy nhánh MISSING và mã 2, rồi khôi phục php.

### Bước 4. Hoàn thiện script dùng hàm và vòng lặp

Thay toàn bộ scripts/check-env.sh bằng phiên bản hoàn chỉnh sau. Đây là mã mẫu được phép sử dụng trong phần hướng dẫn:

```bash
#!/usr/bin/env bash
set -u
root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)" || exit 2
fail=0
check_tool() {
  if command -v "$1" >/dev/null 2>&1; then
    printf 'OK tool %s\n' "$1"
  else
    printf 'MISSING tool %s\n' "$1" >&2
    fail=$((fail + 1))
  fi
}
for tool in php git; do check_tool "$tool"; done
for file in README.md public/index.php; do
  if [[ -f "$root/$file" ]]; then
    printf 'OK file %s\n' "$file"
  else
    printf 'MISSING file %s\n' "$file" >&2
    fail=$((fail + 1))
  fi
done
if [[ "$fail" -gt 0 ]]; then exit 2; fi
if php -l "$root/public/index.php"; then
  echo 'CHECK_ENV=PASS'
else
  echo 'CHECK_ENV=FAIL' >&2
  exit 1
fi
```

Giải thích theo thứ tự thực thi:

| **Thành phần** | Ý nghĩa |
| --- | --- |
| set -u | Báo khi sử dụng biến chưa được khai báo; giảm nhầm tên biến. |
| root=... | Tìm thư mục dự án từ vị trí tệp script. dirname lấy thư mục chứa script; /.. đi lên một cấp; pwd lấy đường dẫn đầy đủ. $(...) lấy kết quả lệnh làm giá trị biến. |
| fail=0 | Biến đếm điều kiện chưa đạt. |
| check\_tool() | Hàm nhận tên công cụ qua $1, kiểm tra và tăng biến đếm nếu thiếu. |
| for tool in php git | Gọi cùng hàm cho PHP và Git, không viết lại hai khối if. |
| \[\[ -f ... \]\] | Kiểm tra đường dẫn là một tệp có tồn tại. |
| fail=$((fail + 1)) | Phép cộng số nguyên trong Bash. |
| exit 2, exit 1 | Mẫu phân biệt thiếu điều kiện chạy và sai cú pháp PHP. |

Dấu nháy kép giúp Bash coi đường dẫn có khoảng trắng là một đối số. Biến root xác định thư mục dự án từ vị trí script, vì vậy có thể chạy script từ thư mục khác.

Kiểm tra kết quả bước 4:

- [ ] Chỉ ra biến fail, hàm check\_tool, hai vòng lặp và điều kiện kết thúc.

### Bước 5. Chạy script và đọc kết quả

```bash
bash scripts/check-env.sh
echo "exit=$?"
```

Kết quả có OK tool php, OK tool git, OK file README.md, OK file public/index.php, CHECK\_ENV=PASS và cuối cùng exit=0. Thông báo cú pháp PHP có đường dẫn riêng của máy bạn.

Thử chạy từ thư mục khác:

```bash
cd ..
bash Ma_nguon/scripts/check-env.sh
cd Ma_nguon
```

Script vẫn tìm được tệp vì dùng root. Đây là lý do không nên viết mọi đường dẫn phụ thuộc vào nơi người dùng đang đứng.

Kiểm tra kết quả bước 5:

- [ ] Chạy từ thư mục cha vẫn tìm được README và public/index.php.

### Bước 6. Tạo lỗi có kiểm soát rồi khôi phục

Thực hiện tại thư mục Ma\_nguon của bài:

```bash
mv README.md README.backup
bash scripts/check-env.sh
echo "exit=$?"
mv README.backup README.md
bash scripts/check-env.sh
echo "exit=$?"
```

Lần thiếu tệp có MISSING file README.md và mã 2. Sau khi đổi tên trở lại, kết quả là CHECK\_ENV=PASS và mã 0. Không dùng rm để xóa tài liệu gốc.

Kiểm tra kết quả bước 6:

- [ ] Đổi tên README báo mã 2; khôi phục báo mã 0, không mất tệp.

### Bước 7. Khởi chạy lại phần mềm

```bash
bash lab.sh run
```

Mở lại trang trong trình duyệt để kiểm tra ứng dụng. Script scripts/check-env.sh là phần sinh viên thực hiện; lab.sh run chỉ hỗ trợ khởi chạy máy chủ. Nhấn Ctrl+C để dừng máy chủ trước khi chuyển nội dung.

Kiểm tra kết quả bước 7:

- [ ] Trình duyệt tại cổng 8101 hiện trang mẫu; biết dừng máy chủ bằng Ctrl+C.

Kết quả sau phần hướng dẫn: bash lab.sh test: có CHECK\_ENV=PASS, mã thoát 0. Trang mẫu ở cổng 8101.

## V. LỖI THƯỜNG GẶP VÀ CÁCH KHẮC PHỤC

| **Hiện tượng** | **Kiểm tra và cách sửa** |
| --- | --- |
| No such file or directory | Chạy pwd, ls; bảo đảm đứng ở Ma\_nguon, không phải thư mục Sinh\_vien. |
| php: command not found | Thực hiện phần cài công cụ trong mục I của bài này; không sửa script để bỏ qua điều kiện thiếu PHP. |
| Tệp báo bad interpreter hoặc chứa ký tự ^M | Đổi kiểu xuống dòng từ CRLF sang LF trong trình soạn thảo, rồi lưu lại. |
| Trình duyệt báo không kết nối | Giữ terminal máy chủ đang chạy; kiểm tra đúng địa chỉ và cổng. |
| Address already in use | Dừng máy chủ cũ bằng Ctrl+C. Không chạy hai bản mẫu và bài làm cùng cổng. |

## VI. BÀI TẬP MỞ RỘNG: KIỂM TRA NHIỀU TỆP PHP

Viết script kiểm tra cú pháp tất cả tệp PHP trong một thư mục, kể cả các thư mục con. Vận dụng cách kiểm tra công cụ, điều kiện và vòng lặp đã thực hiện trong bài mẫu.

Không đổi thư mục. Bài mở rộng dùng thêm một tệp và bộ kiểm thử ngay trong dự án:
Thư mục Lab02/Ma\_nguon đã có mã mẫu cho phần hướng dẫn. Chạy mẫu trước để xem kết quả, sau đó đọc và thực hiện lại các bước ở phần IV trên chính các tệp đó. Khi bài yêu cầu thay toàn bộ nội dung, không chép nối thêm một hàm hoặc lớp trùng tên. Phần mở rộng để sinh viên tự hoàn thiện ở mục VI.

| **Phần thực hành** | **Tệp và công việc** | **Kết quả cần đạt** |
| --- | --- | --- |
| Mẫu có hướng dẫn | src/CapacityPolicy.php; demo.php<br>Đọc và viết lại lớp CapacityPolicy cùng chương trình gọi. Kiểm tra sức chứa, số người hiện tại và điều kiện còn chỗ. | bash lab.sh run: AVAILABLE với (10, 9). bash lab.sh test: TESTS=5 FAILED=0. |
| Bài tự mở rộng | src/RegistrationPolicy.php<br>Viết phương thức decide để trả confirmed, waiting hoặc rejected; xử lý dữ liệu sai, sự kiện đóng và danh sách chờ. | Bộ 8 ca đã cấp: TESTS=8 FAILED=0. Nếu bổ sung ca, tổng TESTS tăng tương ứng; FAILED phải bằng 0. |

Vai trò từng tệp trong bài mẫu:

| **Tệp** | **Vai trò** |
| --- | --- |
| src/CapacityPolicy.php | Mã mẫu xét điều kiện còn chỗ; đọc và viết lại theo bước 3. |
| demo.php | Tạo đối tượng, truyền đầu vào và in kết quả. Đọc và viết lại theo bước 4. |
| tests/run.php | Bộ dữ liệu thử đã cấp để kiểm tra hành vi của lớp. |
| tests/helper.php | Hàm so sánh kết quả mong đợi và kết quả thật; không cần sửa. |

Luồng: demo.php nạp lớp → tạo đối tượng → gọi phương thức với hai số nguyên → nhận Boolean → quyết định dòng chữ cần in. Không có database hoặc Session trong luồng này.

## IV. HƯỚNG DẪN THỰC HÀNH

### Bước 1. Viết quy tắc bằng lời trước khi viết mã

Trong ghi chú của bài, ghi: sức chứa phải lớn hơn 0; số đã đăng ký không âm; chỉ còn chỗ khi số đã đăng ký nhỏ hơn sức chứa. Với mẫu này, nếu số đã đăng ký lớn hơn sức chứa thì cũng không cho đăng ký tiếp.

Câu “còn chỗ” được biểu diễn bằng toán tử &lt;, không phải &lt;=. Đây là quyết định nghiệp vụ, không phải quy ước đặt tên.

Kiểm tra kết quả bước 1:

- [ ] Viết được điều kiện còn chỗ và quy tắc từ chối đầu vào không hợp lệ.

### Bước 2. Thử quy tắc dưới dạng một hàm

Mở tệp thu-ham.php tại thư mục gốc bài làm với nội dung:

```php
<?php
function canRegister(int $capacity, int $registered): bool
{
    if ($capacity <= 0 || $registered < 0) {
        return false;
    }
    return $registered < $capacity;
}
 
var_dump(canRegister(10, 9));
var_dump(canRegister(10, 10));
```

Chạy php thu-ham.php. Kết quả gồm bool(true) và bool(false).

int chỉ tham số số nguyên; bool chỉ kết quả đúng/sai; \|\| nghĩa là hoặc. return kết thúc hàm và trả giá trị cho nơi gọi. Hai biến tham số chỉ có ý nghĩa bên trong lần gọi hàm, không đọc dữ liệu từ giao diện.

Kiểm tra kết quả bước 2:

- [ ] Hàm thử trả true khi 10/9, false khi 10/10 và khi dữ liệu sai.

### Bước 3. Đưa cùng quy tắc vào một lớp

Mở src/CapacityPolicy.php và nhập toàn bộ nội dung sau:

```php
<?php
declare(strict_types=1);
 
final class CapacityPolicy
{
    public function canRegister(int $capacity, int $registered): bool
    {
        if ($capacity <= 0 || $registered < 0) {
            return false;
        }
        return $registered < $capacity;
    }
}
```

Lớp là nơi nhóm các phương thức liên quan; trong bài này chỉ cần một phương thức canRegister. public cho phép gọi phương thức từ nơi khác. final không cho lớp khác kế thừa lớp này; bài không yêu cầu thiết kế kế thừa. declare(strict\_types=1) bật kiểm tra kiểu nghiêm ngặt cho các lời gọi có kiểu trong tệp; dữ liệu form dạng chuỗi khi nhận dữ liệu Form vẫn phải được kiểm tra và chuyển đổi trước khi sử dụng.

Kiểm tra kết quả bước 3:

- [ ] Lớp CapacityPolicy chỉ xử lý quy tắc, chưa cần HTML hoặc database.

### Bước 4. Tạo chương trình sử dụng lớp

Mở demo.php ở gốc bài làm:

```php
<?php
require __DIR__ . '/src/CapacityPolicy.php';
 
$policy = new CapacityPolicy();
$available = $policy->canRegister(10, 9);
if ($available) {
    echo "AVAILABLE\n";
} else {
    echo "FULL\n";
}
```

require nạp tệp định nghĩa lớp; \_\_DIR\_\_ là thư mục của chính tệp hiện tại; new tạo đối tượng. Dấu -&gt; gọi phương thức của đối tượng. Biến $available nhận kết quả Boolean, còn việc in chữ nằm ở demo.php, không nằm trong lớp nghiệp vụ.

Đây là ví dụ sử dụng require tối thiểu để tách tệp. Cách dùng nhiều tệp và tổ chức giao diện được học đầy đủ trong phần xử lý Form, không yêu cầu tự viết bộ tự nạp lớp lúc này.

Kiểm tra kết quả bước 4:

- [ ] demo.php nạp đúng tệp và gọi canRegister của đối tượng đã tạo.

### Bước 5. Chạy và thay đầu vào để thấy tác dụng

```bash
php -l src/CapacityPolicy.php
php demo.php
```

Lần đầu có AVAILABLE. Sửa duy nhất lời gọi thành canRegister(10, 10), lưu demo.php, chạy lại: có FULL. Sửa về 10,9 trước khi đối chiếu với bản mẫu.

Thử các cặp (10, 11), (0, 0), (10, -1); cả ba đều không được đăng ký. Lập bảng ghi dữ liệu đầu vào, kết quả yêu cầu và kết quả thực tế.

Kiểm tra kết quả bước 5:

- [ ] Khi thay dữ liệu đầu vào, chương trình trả kết quả phù hợp với quy tắc đã xác định.

### Bước 6. Dùng bộ kiểm thử đã cấp

```bash
php tests/run.php
```

Mở tests/run.php và đọc các lời gọi same(...). Hàm so sánh kết quả của chương trình với giá trị yêu cầu; nhãn của ca kiểm thử giúp xác định trường hợp sai. Giữ nguyên các giá trị yêu cầu khi sửa chương trình.

Thử tạm đổi &lt; thành &lt;= trong lớp rồi chạy test: ca sức chứa bằng số đã đăng ký phải bị phát hiện. Khôi phục &lt; và chạy lại. Đây là kiểm thử hồi quy: giúp lần sửa sau không làm hỏng hành vi cũ.

Kiểm tra kết quả bước 6:

- [ ] Năm ca cơ bản đạt; giải thích được ca đầy chỗ và ca sức chứa sai.

Kết quả sau phần hướng dẫn: bash lab.sh run: AVAILABLE với (10, 9). bash lab.sh test: TESTS=5 FAILED=0.

## V. LỖI THƯỜNG GẶP VÀ CÁCH KHẮC PHỤC

| **Hiện tượng** | **Nguyên nhân thường gặp và cách kiểm tra** |
| --- | --- |
| Class ... not found | Kiểm tra tên lớp, tên tệp và đường dẫn require. |
| Failed opening required | CapacityPolicy.php có ở đúng src không? Kiểm tra tên chữ hoa/chữ thường trên Linux. |
| Parse error | Kiểm tra dấu ;, {}, tên phương thức; chạy php -l trước. |
| Đủ chỗ vẫn được đăng ký | Bạn có viết &lt;= thay vì &lt; không? Chạy ca 10,10. |
| Chương trình in chữ nhưng test sai | Không viết echo thay cho return trong phương thức; test cần giá trị Boolean. |

## VI. BÀI TẬP MỞ RỘNG: BA TRẠNG THÁI ĐĂNG KÝ

Yêu cầu mới: sự kiện có thể đang đóng và có thể bật danh sách chờ. Mỗi yêu cầu đăng ký cần nhận một trong ba kết quả: confirmed (xác nhận), waiting (chờ), rejected (từ chối).
>>>>>>> origin/lab2

```bash
# Vẫn đứng trong Ma_nguon của bài này.
bash lab.sh extend
```

Mở scripts/scan-source.sh và hoàn thiện ba vị trí TODO: kiểm tra đầu vào, đếm tệp đúng/sai cú pháp và trả kết quả tổng hợp. Phần tìm tệp, xử lý tên có khoảng trắng và dọn tệp tạm đã được cung cấp.

| **Đầu vào kiểm thử** | Kết quả yêu cầu |
| --- | --- |
| Hai tệp đúng, một tên đường dẫn có khoảng trắng | TOTAL=2 PASS=2 FAIL=0, mã 0. |
| Ba tệp, trong đó một tệp sai cú pháp | Kiểm tra đủ ba tệp; TOTAL=3 PASS=2 FAIL=1, mã 1. |
| Thư mục không tồn tại | Báo lỗi và mã 2. |
| Thư mục không có tệp PHP | Báo không có tệp để kiểm tra và mã 2. |

Chạy bash lab.sh extend. Kết quả yêu cầu là SHELL\_CASES=4 FAILED=0. Bản khởi đầu có thể chưa đạt vì còn TODO. Đặt php -l trong nhánh if để tiếp tục kiểm tra các tệp còn lại khi phát hiện một tệp lỗi.

## KẾT QUẢ CỦA BÀI THỰC HÀNH

Hoàn thành script kiểm tra môi trường và script quét mã PHP; xử lý được thư mục thiếu, thư mục rỗng, tệp sai cú pháp và đường dẫn có khoảng trắng.

## VII. BẢNG KIỂM HOÀN THÀNH

- [ ] Script kiểm tra đủ PHP, Git và tệp cần thiết.
- [ ] Bộ quét kiểm tra cả thư mục con và đường dẫn có khoảng trắng.
- [ ] Kết quả tổng hợp khớp số tệp; từng tình huống có mã thoát đúng.
- [ ] Dữ liệu thử vẫn còn nguyên sau khi kiểm tra.
- [ ] Chạy lại test và extend đều đạt trên bản mã nộp.

Kết quả tổng hợp của phần tự làm phải có SHELL\_CASES=4 FAILED=0. Ngoài các phép thử tự động, thực hiện các tình huống kiểm tra trực tiếp nêu trong bài. Lệnh check chỉ xác nhận môi trường, không thay cho test và extend.
Mở src/RegistrationPolicy.php, giữ tên và tham số của phương thức đã cấp; tự viết phần thân decide.

| **Điều kiện** | **Kết quả yêu cầu** |
| --- | --- |
| Sức chứa ≤ 0, số đã đăng ký &lt; 0 hoặc &gt; sức chứa | rejected. |
| Dữ liệu hợp lệ nhưng sự kiện đóng | rejected, kể cả vẫn còn chỗ. |
| Sự kiện mở và còn chỗ | confirmed. |
| Sự kiện mở, hết chỗ, có danh sách chờ | waiting. |
| Sự kiện mở, hết chỗ, không có danh sách chờ | rejected. |

Kiểm tra tính hợp lệ của dữ liệu và trạng thái đóng của sự kiện trước khi xét số chỗ. Chạy bash lab.sh extend, bổ sung ít nhất một ca kiểm thử và giải thích mục đích của ca đó. Phần nâng cao: bổ sung mã lý do cho kết quả nhưng giữ nguyên giao diện của phương thức bắt buộc.

## KẾT QUẢ CỦA BÀI THỰC HÀNH

Có lớp kiểm tra sức chứa, chương trình gọi lớp và lớp mở rộng trả confirmed, waiting hoặc rejected theo quy tắc đã công bố.

## VII. BẢNG KIỂM HOÀN THÀNH

- [ ] Giải thích được đầu vào và giá trị trả về của canRegister().
- [ ] demo.php gọi đúng lớp và in kết quả theo dữ liệu thử.
- [ ] decide() trả đúng ba trạng thái và thứ tự ưu tiên.
- [ ] Các ca đúng, sai và biên có kết quả đối chiếu.
- [ ] test và extend đạt; không sửa đáp án trong bộ kiểm thử.

Kết quả tổng hợp của phần tự làm phải có TESTS=8 FAILED=0. Ngoài các phép thử tự động, thực hiện các tình huống kiểm tra trực tiếp nêu trong bài. Lệnh check chỉ xác nhận môi trường, không thay cho test và extend.
>>>>>>> origin/lab2

## VIII. RUBRIC LAB — 10 ĐIỂM

| **Tiêu chí** | **Điểm** | **Cách chấm** |
| --- | --- | --- |
| Phần mẫu có hướng dẫn | 2,0 | 1 điểm: thực hiện và có kết quả đúng; 1 điểm: giải thích luồng mã. Mỗi phần đạt một phần: 0,5; chưa đạt: 0. |
| Kiểm tra đầu vào | 1,0 | Từ chối thư mục thiếu/không đọc được; báo mã 2 cho thư mục không có PHP. |
| Duyệt và kiểm tra tệp | 1,5 | Kiểm tra đệ quy, giữ đúng tên có khoảng trắng; không dừng ở tệp lỗi đầu tiên. |
| Tổng hợp và mã thoát | 1,5 | Tổng TOTAL/PASS/FAIL đúng; mã 0 khi tất cả đạt, 1 khi có tệp lỗi, 2 khi đầu vào không dùng được. |
| Dữ liệu và trạng thái đóng | 1,5 | Từ chối sức chứa không hợp lệ, số đăng ký âm/vượt sức chứa và sự kiện đã đóng. |
| Xác nhận và danh sách chờ | 1,5 | Trả confirmed khi còn chỗ; waiting chỉ khi đầy và cho chờ; các trường hợp còn lại rejected. |
| Tổ chức module | 1,0 | Lớp trả dữ liệu đúng kiểu; không in HTML, không phụ thuộc Session hoặc CSDL. |
>>>>>>> origin/lab2
| Kiểm thử và xử lý lỗi | 2,0 | 1 điểm: có dữ liệu và kết quả mong đợi cho tình huống đúng, sai, biên; 1 điểm: chạy lại phần mẫu và mở rộng sau sửa. Mỗi phần đạt một phần: 0,5; chưa đạt: 0. |
| Mã nguồn và bàn giao | 1,0 | 0,5: nộp đúng tệp, dữ liệu và phiên bản; 0,5: README có lệnh chạy/kiểm thử, không chứa cấu hình riêng. Đạt một phần mỗi mục: 0,25. |
| Giải thích cá nhân | 1,0 | 0,5: giải thích phần tự viết; 0,5: giải thích một ca lỗi hoặc thực hiện một thay đổi nhỏ. Đạt một phần mỗi mục: 0,25. |
| Tổng | 10,0 | Chấm trực tiếp sản phẩm và phần giải thích của bài lab. |

Với tiêu chí 1,5 điểm: đủ yêu cầu được 1,5; thực hiện một phần được 0,75; chưa thực hiện được 0. Với tiêu chí 1 điểm không chia tiểu mục: đủ được 1; một phần được 0,5; chưa thực hiện được 0. Bài tự làm không đạt điểm chỉ nhờ thay bộ kiểm thử hoặc ghi sẵn kết quả.

## IX. NỘP BÀI

Bước 1. Lưu mã bài mẫu và bài tự làm tại Lab01/Ma\_nguon, kèm dữ liệu, kiểm thử và README. Loại bỏ .local, mật khẩu và dữ liệu cá nhân thật khỏi bài nộp.

Bước 2. Chạy bash lab.sh check, bash lab.sh test và bash lab.sh extend. Thực hiện các phép kiểm tra trực tiếp trong bài và sửa lỗi trước khi nộp.

Bước 3. Commit, đẩy mã lên GitHub và nộp tên nhóm/thành viên, Lab 01, liên kết Ma\_nguon tại đúng commit. Kiểm tra quyền đọc; kèm lần chạy Actions khi được yêu cầu. Hạn và địa chỉ nộp do giảng viên công bố. Nếu nhận ZIP, giữ nguyên cấu trúc trên.
Bước 1. Lưu mã bài mẫu và bài tự làm tại Lab02/Ma\_nguon, kèm dữ liệu, kiểm thử và README. Loại bỏ .local, mật khẩu và dữ liệu cá nhân thật khỏi bài nộp.

Bước 2. Chạy bash lab.sh check, bash lab.sh test và bash lab.sh extend. Thực hiện các phép kiểm tra trực tiếp trong bài và sửa lỗi trước khi nộp.

Bước 3. Commit, đẩy mã lên GitHub và nộp tên nhóm/thành viên, Lab 02, liên kết Ma\_nguon tại đúng commit. Kiểm tra quyền đọc; kèm lần chạy Actions khi được yêu cầu. Hạn và địa chỉ nộp do giảng viên công bố. Nếu nhận ZIP, giữ nguyên cấu trúc trên.
>>>>>>> origin/lab2
