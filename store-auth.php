<?php
/**
 * CDS Store - Đăng nhập và đăng ký tài khoản khách hàng
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/services/CustomerAuthService.php';

$mode = ($_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';
$redirect = trim((string)($_GET['redirect'] ?? $_POST['redirect'] ?? 'demo-app.php'));
if ($redirect === '' || $redirect[0] === '/' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $redirect)) {
    $redirect = 'demo-app.php';
}
$errors = [];
$old_username = strtolower(trim((string)($_GET['username'] ?? '')));

if (is_customer_logged_in()) {
    header('Location: demo-app.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = ($_POST['mode'] ?? 'login') === 'register' ? 'register' : 'login';
    $old_username = strtolower(trim((string)($_POST['username'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if (!validate_csrf_token((string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'Phiên làm việc hết hạn hoặc mã xác thực CSRF không hợp lệ. Vui lòng thử lại.';
    } elseif (empty($old_username) || empty($password)) {
        $errors[] = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } elseif ($mode === 'register') {
        if ($password !== (string)($_POST['password_confirmation'] ?? '')) {
            $errors[] = 'Mật khẩu xác nhận chưa trùng khớp.';
        } else {
            $result = CustomerAuthService::register($old_username, $password);
            if ($result['success']) {
                set_flash('success', 'Đăng ký thành công. Vui lòng đăng nhập để vào CDS Store.');
                header('Location: store-auth.php?mode=login&redirect=' . rawurlencode($redirect) . '&username=' . rawurlencode($old_username));
                exit;
            }
            $errors[] = $result['message'];
        }
    } else {
        $customer = CustomerAuthService::authenticate($old_username, $password);
        if ($customer !== null) {
            $_SESSION['customer'] = $customer;
            session_regenerate_id(true);
            set_flash('success', 'Đăng nhập thành công. Chúc bạn mua sắm vui vẻ!');
            header('Location: ' . $redirect);
            exit;
        }
        $errors[] = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $mode === 'register' ? 'Đăng ký' : 'Đăng nhập' ?> - CDS Store</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/components.css">
  <link rel="stylesheet" href="assets/css/responsive.css">
  <style>
    body {
      background: radial-gradient(circle at top right, #242424 0%, #101010 58%, #000 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .login-container { width: 100%; max-width: 440px; }
    .login-brand-header { text-align: center; margin-bottom: 2rem; }
    .login-brand-header h1,
    .card h1,
    .card .form-label {
      color: #ffffff !important;
    }
    .login-logo-box {
      width: 48px;
      height: 48px;
      margin: 0 auto 1rem;
      border-radius: var(--radius-lg);
      background: linear-gradient(135deg, #2f3033 0%, #101010 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      box-shadow: 0 4px 14px rgba(0, 0, 0, .35);
    }
    .auth-switch {
      display: block;
      margin-top: 1.25rem;
      color: #69b1ff;
      text-align: center;
      font-size: .86rem;
      font-weight: 600;
    }
    .card .form-label {
      color: #ffffff !important;
    }
    .card .form-control {
      color: #f5f5f7 !important;
      background: #27272b !important;
      border-color: #4a4a50 !important;
    }
    .card .form-control::placeholder {
      color: #fcfcfc;
      opacity: 1;
    }
    .card .form-control:focus {
      border-color: #2997ff !important;
      box-shadow: 0 0 0 2px rgba(41, 151, 255, .2);
    }
    .login-footer {
      color: #a7a7ad;
    }
  </style>
</head>
<body>
<div class="login-container">
  <div class="login-brand-header">
    <div class="login-logo-box">
      <span style="font-size: 1.15rem; font-weight: 900; letter-spacing: .04em;">
        CD<span style="background: linear-gradient(135deg, #ff3b30 0%, #ff9500 32%, #34c759 66%, #007aff 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">S</span>
      </span>
    </div>
    <h1 style="font-size: 1.45rem; font-weight: 700; color: #ffffff !important; margin-bottom: .25rem;">
      CDS Store
    </h1>
    <p style="font-size: .88rem;     color: #a7a7ad;">
      <?= $mode === 'register' ? 'Tạo tài khoản để bắt đầu mua sắm' : 'Đăng nhập để tiếp tục mua sắm' ?>
    </p>
  </div>

  <div class="card" style="background: #1d1d1f; border: 1px solid #3b3b3f; box-shadow: 0 16px 48px rgba(0,0,0,.35);">
    <div class="card-body" style="padding: 2rem;">
      <?php $flash = get_flash(); ?>
      <?php if ($flash): ?>
        <div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-info' ?>">
          <span><?= e($flash['message']) ?></span>
        </div>
      <?php endif; ?>
      <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger">
          <span><?= e($error) ?></span>
        </div>
      <?php endforeach; ?>

      <form method="POST" action="store-auth.php?mode=<?= e($mode) ?>" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="mode" value="<?= e($mode) ?>">
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">

        <div class="form-group">
          <label class="form-label" for="username">Tên đăng nhập</label>
          <input type="text" id="username" name="username" class="form-control" value="<?= e($old_username) ?>" placeholder="Nhập tên đăng nhập" autocomplete="username" required <?= $mode === 'login' ? 'autofocus' : '' ?>>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Mật khẩu</label>
          <input type="password" id="password" name="password" class="form-control" placeholder="Nhập mật khẩu" autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>" minlength="8" required>
        </div>

        <?php if ($mode === 'register'): ?>
          <div class="form-group">
            <label class="form-label" for="password_confirmation">Nhập lại mật khẩu</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="Nhập lại mật khẩu" autocomplete="new-password" minlength="8" required>
          </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: .75rem; margin-top: .5rem;">
          <?= $mode === 'register' ? 'Đăng ký tài khoản' : 'Đăng nhập CDS Store' ?>
        </button>
      </form>

      <?php if ($mode === 'login'): ?>
        <a class="auth-switch" href="store-auth.php?mode=register&redirect=<?= rawurlencode($redirect) ?>">
          Chưa có tài khoản? Đăng ký ngay
        </a>
      <?php else: ?>
        <a class="auth-switch" href="store-auth.php?mode=login&redirect=<?= rawurlencode($redirect) ?>">
          Đã có tài khoản? Đăng nhập
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="login-footer" style="text-align: center; margin-top: 1.5rem; font-size: .78rem;">
    CDS Store &bull; Cửa hàng Apple chính hãng
  </div>
</div>
</body>
</html>
