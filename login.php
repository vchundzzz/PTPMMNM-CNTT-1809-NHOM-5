<?php
/**
 * Web Feature Flag Management System
 * login.php - Trang đăng nhập giao diện SaaS hiện đại
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

// Nếu đã đăng nhập, chuyển hướng ngay về dashboard
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error_message = '';

// Xử lý submit form POST thủ công
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!validate_csrf_token($csrf_token)) {
        $error_message = 'Phiên làm việc hết hạn hoặc mã xác thực CSRF không hợp lệ. Vui lòng thử lại.';
    } elseif (empty($username) || empty($password)) {
        $error_message = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } elseif (attempt_login($username, $password)) {
        set_flash('success', 'Chào mừng bạn quay trở lại hệ thống Feature Manager!');
        header('Location: dashboard.php');
        exit;
    } else {
        $error_message = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
    }
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đăng nhập - Feature Manager</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/components.css">
  <link rel="stylesheet" href="assets/css/responsive.css">
  <style>
    body {
      background: radial-gradient(circle at top right, #eff6ff 0%, #f8fafc 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .login-container {
      width: 100%;
      max-width: 440px;
    }
    .login-brand-header {
      text-align: center;
      margin-bottom: 2rem;
    }
    .login-logo-box {
      width: 48px;
      height: 48px;
      margin: 0 auto 1rem auto;
      border-radius: var(--radius-lg);
      background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ffffff;
      box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
    }
  </style>
</head>
<body>

<div class="login-container">
  <!-- Đầu trang thương hiệu -->
  <div class="login-brand-header">
    <div class="login-logo-box">
      <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
        <line x1="4" y1="22" x2="4" y2="15"></line>
      </svg>
    </div>
    <h1 style="font-size: 1.45rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">
      Quản lý tính năng
    </h1>
    <p style="font-size: 0.88rem; color: var(--text-muted);">
      Hệ thống quản lý cấu hình bật/tắt tính năng (Feature Flags)
    </p>
  </div>

  <!-- Thẻ Form Đăng nhập -->
  <div class="card" style="box-shadow: var(--shadow-md);">
    <div class="card-body" style="padding: 2rem;">

      <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
          <span><?= e($error_message) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($flash && $flash['type'] === 'success'): ?>
        <div class="alert alert-success">
          <span><?= e($flash['message']) ?></span>
        </div>
      <?php endif; ?>

      <!-- Form nhập thủ công -->
      <form method="POST" action="login.php" autocomplete="off">
        <?= csrf_field() ?>

        <div class="form-group">
          <label class="form-label" for="username">Tên đăng nhập</label>
          <input type="text" id="username" name="username" class="form-control" placeholder="Nhập tên đăng nhập" required autofocus>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Mật khẩu</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="Nhập mật khẩu" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; margin-top: 0.5rem;">
          Đăng nhập hệ thống
        </button>
      </form>

    </div>
  </div>

  <div style="text-align: center; margin-top: 1.5rem; font-size: 0.78rem; color: var(--text-muted);">
    BTL Phát triển phần mềm mã nguồn mở &bull; Nhóm 5 &bull; Năm học 2025-2026
  </div>
</div>

</body>
</html>
