<?php
$pageTitle = "Hệ thống quản lý tính năng";
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Web Feature Toggle Management System</title>

    <link rel="stylesheet" href="/assets/css/tokens.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>

<body class="lab-intro">

<a class="skip-link" href="#main">Đến nội dung chính</a>

<header class="site-header">
    <div class="header-inner">
        <h1 class="brand">Feature Manager</h1>
        <p>Hệ thống quản lý cấu hình bật/tắt tính năng ứng dụng Web</p>
    </div>
</header>

<main id="main" tabindex="-1">

    <p class="intro-status">
        Hệ thống quản lý tính năng
    </p>

    <h2>Tổng quan hệ thống</h2>

    <p>
        Quản lý tập trung các tính năng của ứng dụng Web,
        thiết lập điều kiện sử dụng, phát hành cấu hình
        và khôi phục phiên bản khi xảy ra lỗi.
    </p>

    <h2>Chức năng chính</h2>

    <ul>
        <li>Quản lý dự án và thành viên.</li>
        <li>Quản lý môi trường thử nghiệm và vận hành mô phỏng.</li>
        <li>Tạo tính năng và thiết lập trạng thái bật/tắt.</li>
        <li>Thiết lập nhóm người dùng và tỷ lệ áp dụng tính năng.</li>
        <li>Quản lý, so sánh và phát hành phiên bản cấu hình.</li>
        <li>Khôi phục cấu hình cũ khi cần thiết.</li>
        <li>Ghi nhật ký thay đổi và quản lý quyền truy cập.</li>
        <li>Kiểm tra API và sử dụng giá trị dự phòng khi xảy ra lỗi.</li>
    </ul>

    <h2>Môi trường hệ thống</h2>

    <p>
        <strong>Môi trường thử nghiệm:</strong>
        Kiểm tra tính năng trước khi phát hành.
    </p>

    <p>
        <strong>Môi trường vận hành mô phỏng:</strong>
        Kiểm tra cấu hình trong môi trường mô phỏng hoạt động thực tế.
    </p>

    <h2>Trạng thái triển khai</h2>

    <p>
        Giao diện giới thiệu đã được tạo.
        Các chức năng quản lý, cơ sở dữ liệu và API
        sẽ được tích hợp trong các bước tiếp theo.
    </p>

</main>

</body>
</html>
```
