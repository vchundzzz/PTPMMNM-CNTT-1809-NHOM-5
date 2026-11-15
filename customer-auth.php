<?php
/**
 * CDS STORE - Trang Đăng nhập & Đăng ký Tài khoản Khách hàng (Tách biệt hoàn toàn với Trang Quản trị)
 * Kết nối trực tiếp bảng `customers` trong MySQL (feature_flags_db)
 */
declare(strict_types=1);

require_once __DIR__ . '/data/fake-data.php';
require_once __DIR__ . '/services/FeatureFlagService.php';
init_session_data();

$action = $_GET['action'] ?? '';
if ($action === 'logout') {
    unset($_SESSION['store_customer']);
    header('Location: customer-auth.php?msg=logged_out');
    exit;
}

$mode    = ($_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';
$env     = $_GET['env'] ?? ($_SESSION['store_env'] ?? 'production');
$error   = '';
$success = '';

if (($_GET['msg'] ?? '') === 'logged_out') {
    $success = 'Bạn đã đăng xuất khỏi tài khoản khách hàng CDS STORE.';
}

// Xử lý form POST Đăng nhập / Đăng ký
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? 'login';

    if ($formType === 'login') {
        $mode       = 'login';
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = trim($_POST['password'] ?? '');

        $res = DatabaseService::authenticateCustomer($identifier, $password);
        if ($res['success']) {
            $_SESSION['store_customer'] = $res['customer'];
            $redirectEnv = $_POST['env'] ?? $env;
            header('Location: demo-app.php?user_id=' . urlencode($res['customer']['id']) . '&env=' . urlencode($redirectEnv));
            exit;
        } else {
            $error = $res['message'];
        }
    } elseif ($formType === 'register') {
        $mode      = 'register';
        $name      = trim($_POST['name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $password  = trim($_POST['password'] ?? '');
        $confirm   = trim($_POST['password_confirm'] ?? '');
        $groupId   = trim($_POST['group_id'] ?? 'nhom_chung');

        if ($password !== $confirm) {
            $error = 'Mật khẩu xác nhận không khớp!';
        } else {
            $res = DatabaseService::registerCustomer($name, $email, $password, $phone, $groupId);
            if ($res['success']) {
                $_SESSION['store_customer'] = $res['customer'];
                $redirectEnv = $_POST['env'] ?? $env;
                header('Location: demo-app.php?user_id=' . urlencode($res['customer']['id']) . '&env=' . urlencode($redirectEnv) . '&registered=1');
                exit;
            } else {
                $error = $res['message'];
            }
        }
    }
}

$currentCustomer = $_SESSION['store_customer'] ?? null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $mode === 'register' ? 'Đăng ký thành viên' : 'Đăng nhập' ?> — CDS STORE (Apple Authorized Reseller)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: radial-gradient(circle at top right, #1e293b 0%, #090d16 55%, #000000 100%);
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        /* Top Header CDS STORE */
        .store-header {
            background: rgba(16, 16, 16, 0.88);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(255,255,255,0.08);
            padding: 14px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .store-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #fff;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .store-brand .apple-badge {
            font-size: 11px;
            font-weight: 600;
            color: #94a3b8;
            border-left: 1px solid rgba(255,255,255,0.2);
            padding-left: 12px;
            line-height: 1.2;
        }
        .header-links {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn-back-store {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.1);
            color: #fff;
            text-decoration: none;
            padding: 9px 16px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid rgba(255,255,255,0.12);
            transition: all 0.2s;
        }
        .btn-back-store:hover {
            background: #0071e3;
            border-color: #0071e3;
        }

        /* Auth Container */
        .auth-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 36px 20px;
        }
        .auth-wrapper {
            width: 100%;
            max-width: 980px;
            display: grid;
            grid-template-columns: 1.05fr 1.2fr;
            background: rgba(22, 27, 38, 0.85);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 70px rgba(0,0,0,0.65);
            backdrop-filter: blur(20px);
        }
        @media (max-width: 820px) {
            .auth-wrapper { grid-template-columns: 1fr; }
        }

        /* Left Promo Side */
        .auth-promo {
            background: linear-gradient(155deg, #0071e3 0%, #1e1b4b 55%, #090d16 100%);
            padding: 42px 36px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .promo-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.15);
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            width: fit-content;
            margin-bottom: 18px;
        }
        .auth-promo h1 {
            font-size: 28px;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 14px;
        }
        .auth-promo p {
            font-size: 14px;
            color: rgba(255,255,255,0.82);
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .benefit-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 28px;
        }
        .benefit-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            color: #e2e8f0;
            line-height: 1.45;
        }
        .benefit-list li i {
            color: #38bdf8;
            margin-top: 3px;
        }

        /* Quick Demo Login Cards */
        .quick-login-box {
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255,255,255,0.14);
            border-radius: 16px;
            padding: 16px;
        }
        .quick-login-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #93c5fd;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .quick-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .quick-btn {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 10px;
            padding: 8px 10px;
            color: #fff;
            text-align: left;
            cursor: pointer;
            transition: all 0.18s;
            font-family: inherit;
        }
        .quick-btn:hover {
            background: rgba(0, 113, 227, 0.45);
            border-color: #60a5fa;
            transform: translateY(-1px);
        }
        .quick-btn strong {
            display: block;
            font-size: 12px;
            color: #fff;
        }
        .quick-btn span {
            font-size: 11px;
            color: #cbd5e1;
        }

        /* Right Form Side */
        .auth-form-panel {
            padding: 40px 38px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .auth-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: rgba(15, 23, 42, 0.8);
            padding: 5px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .auth-tab {
            text-align: center;
            padding: 10px 14px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.2s;
        }
        .auth-tab.active {
            background: #0071e3;
            color: #fff;
            box-shadow: 0 4px 14px rgba(0, 113, 227, 0.4);
        }

        .alert-msg {
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
        }
        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.4);
            color: #86efac;
        }

        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 6px;
        }
        .input-wrap {
            position: relative;
        }
        .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 14px;
        }
        .form-control {
            width: 100%;
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(255,255,255,0.14);
            border-radius: 10px;
            padding: 11px 14px 11px 38px;
            color: #fff;
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            border-color: #0071e3;
            box-shadow: 0 0 0 3px rgba(0, 113, 227, 0.2);
        }
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #0071e3, #2563eb);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 13px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 6px;
            transition: transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 8px 20px rgba(0, 113, 227, 0.35);
        }
        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(0, 113, 227, 0.5);
        }
        .helper-note {
            margin-top: 16px;
            font-size: 12px;
            color: #94a3b8;
            text-align: center;
            line-height: 1.5;
        }
        .current-session-banner {
            background: rgba(56, 189, 248, 0.12);
            border: 1px solid rgba(56, 189, 248, 0.3);
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: 12px;
        }
    </style>
</head>
<body>

    <!-- Header độc lập của Web Bán Hàng CDS STORE -->
    <header class="store-header">
        <a href="demo-app.php" class="store-brand">
            <i class="fa-brands fa-apple" style="font-size: 24px;"></i>
            <span>CDS STORE</span>
            <span class="apple-badge">Authorized<br>Reseller</span>
        </a>
        <div class="header-links">
            <a href="demo-app.php" class="btn-back-store">
                <i class="fa-solid fa-store"></i> Quay lại Cửa hàng CDS STORE
            </a>
        </div>
    </header>

    <main class="auth-main">
        <div class="auth-wrapper">
            <!-- Cột trái: Giới thiệu quyền lợi thành viên & Đăng nhập nhanh 1-click -->
            <div class="auth-promo">
                <div>
                    <div class="promo-badge">
                        <i class="fa-brands fa-apple"></i> CDS STORE MEMBERSHIP
                    </div>
                    <h1>Tài khoản Khách hàng CDS Care</h1>
                    <p>
                        Đăng nhập hoặc đăng ký tài khoản khách hàng để trải nghiệm các tính năng mua sắm thông minh được cá nhân hóa theo từng nhóm khách hàng (Nội bộ, Beta, Khách hàng Thường).
                    </p>
                    <ul class="benefit-list">
                        <li>
                            <i class="fa-solid fa-bolt"></i>
                            <span><strong>Cá nhân hóa theo Tài khoản:</strong> Giao diện tối/sáng, Thanh toán 1-Click, Mã giảm giá và Tích điểm tự động đồng bộ theo mã KH của bạn.</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-flask"></i>
                            <span><strong>Trải nghiệm sớm (Beta):</strong> Khách hàng đăng ký nhóm Beta được dùng thử các tính năng mới trước khi phát hành toàn bộ.</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-database"></i>
                            <span><strong>Đồng bộ MySQL 100%:</strong> Hỗ trợ sẵn 1.000 khách hàng (<code>KH1</code> – <code>KH1000</code>) và tự động cấp mã mới (<code>KH1001+</code>) khi đăng ký.</span>
                        </li>
                    </ul>
                </div>

                <!-- Khung chọn nhanh tài khoản mẫu để Demo -->
                <div class="quick-login-box">
                    <div class="quick-login-title">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Đăng nhập nhanh 1-Click (Dành cho Demo)
                    </div>
                    <div class="quick-grid">
                        <button type="button" class="quick-btn" onclick="quickFillLogin('KH1', '123456')">
                            <strong>👤 KH1 (Nội bộ VIP)</strong>
                            <span>Có Override Giao diện tối</span>
                        </button>
                        <button type="button" class="quick-btn" onclick="quickFillLogin('KH2', '123456')">
                            <strong>👤 KH2 (Nội bộ)</strong>
                            <span>Nhóm Nội bộ (KH1-KH50)</span>
                        </button>
                        <button type="button" class="quick-btn" onclick="quickFillLogin('KH60', '123456')">
                            <strong>🧪 KH60 (Khách Beta)</strong>
                            <span>Nhóm Beta (KH51-KH250)</span>
                        </button>
                        <button type="button" class="quick-btn" onclick="quickFillLogin('KH500', '123456')">
                            <strong>🛍️ KH500 (Khách Thường)</strong>
                            <span>Nhóm Chung (KH251-1000)</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Cột phải: Form Đăng nhập / Đăng ký -->
            <div class="auth-form-panel">
                <?php if ($currentCustomer): ?>
                <div class="current-session-banner">
                    <div>
                        Đang đăng nhập: <strong><?= htmlspecialchars($currentCustomer['name']) ?> (<?= htmlspecialchars($currentCustomer['id']) ?>)</strong>
                        <div style="color:#94a3b8; margin-top:2px;">Nhóm: <?= htmlspecialchars($currentCustomer['group_name'] ?? $currentCustomer['group_id']) ?></div>
                    </div>
                    <div style="display:flex; gap:6px;">
                        <a href="demo-app.php?user_id=<?= urlencode($currentCustomer['id']) ?>" style="background:#0071e3; color:#fff; text-decoration:none; padding:6px 10px; border-radius:8px; font-weight:600;">Vào Shop</a>
                        <a href="customer-auth.php?action=logout" style="background:rgba(239,68,68,0.2); color:#fca5a5; text-decoration:none; padding:6px 10px; border-radius:8px; font-weight:600;">Đăng xuất</a>
                    </div>
                </div>
                <?php endif; ?>

                <div class="auth-tabs">
                    <a href="customer-auth.php?mode=login&env=<?= urlencode($env) ?>" class="auth-tab <?= $mode === 'login' ? 'active' : '' ?>">
                        <i class="fa-solid fa-right-to-bracket"></i> Đăng nhập Khách hàng
                    </a>
                    <a href="customer-auth.php?mode=register&env=<?= urlencode($env) ?>" class="auth-tab <?= $mode === 'register' ? 'active' : '' ?>">
                        <i class="fa-solid fa-user-plus"></i> Đăng ký Tài khoản
                    </a>
                </div>

                <?php if ($error): ?>
                <div class="alert-msg alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div class="alert-msg alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
                <?php endif; ?>

                <?php if ($mode === 'login'): ?>
                <!-- FORM ĐĂNG NHẬP KHÁCH HÀNG -->
                <form method="POST" action="customer-auth.php?mode=login" id="customerLoginForm">
                    <input type="hidden" name="form_type" value="login">
                    <input type="hidden" name="env" value="<?= htmlspecialchars($env) ?>">

                    <div class="form-group">
                        <label>Mã Khách hàng (KH1 – KH1000+) hoặc Email</label>
                        <div class="input-wrap">
                            <i class="fa-solid fa-id-badge"></i>
                            <input type="text" name="identifier" id="loginIdentifier" class="form-control"
                                   placeholder="VD: KH1, KH60, KH500 hoặc kh1@demo.local"
                                   value="<?= htmlspecialchars($_POST['identifier'] ?? 'KH1') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Mật khẩu khách hàng</label>
                        <div class="input-wrap">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="password" id="loginPassword" class="form-control"
                                   placeholder="Nhập mật khẩu (mặc định: 123456)"
                                   value="123456" required>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fa-solid fa-right-to-bracket"></i> Đăng nhập vào CDS STORE
                    </button>

                    <div class="helper-note">
                        💡 Mật khẩu mặc định của 1.000 tài khoản khách hàng có sẵn (<code>KH1</code> → <code>KH1000</code>) là <strong>123456</strong>.<br>
                        Chưa có tài khoản riêng? <a href="customer-auth.php?mode=register" style="color:#38bdf8; font-weight:600;">Đăng ký ngay tại đây</a>.
                    </div>
                </form>

                <?php else: ?>
                <!-- FORM ĐĂNG KÝ KHÁCH HÀNG MỚI -->
                <form method="POST" action="customer-auth.php?mode=register">
                    <input type="hidden" name="form_type" value="register">
                    <input type="hidden" name="env" value="<?= htmlspecialchars($env) ?>">

                    <div class="form-group">
                        <label>Họ và tên khách hàng *</label>
                        <div class="input-wrap">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" name="name" class="form-control"
                                   placeholder="VD: Nguyễn Văn A"
                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label>Địa chỉ Email *</label>
                            <div class="input-wrap">
                                <i class="fa-solid fa-envelope"></i>
                                <input type="email" name="email" class="form-control"
                                       placeholder="tenban@gmail.com"
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Số điện thoại</label>
                            <div class="input-wrap">
                                <i class="fa-solid fa-phone"></i>
                                <input type="text" name="phone" class="form-control"
                                       placeholder="0987xxxxxx"
                                       value="<?= htmlspecialchars($_POST['phone'] ?? '0362481351') ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Hạng thành viên / Nhóm trải nghiệm tính năng</label>
                        <div class="input-wrap">
                            <i class="fa-solid fa-layer-group"></i>
                            <select name="group_id" class="form-control" style="cursor:pointer;">
                                <option value="nhom_chung" <?= (($_POST['group_id'] ?? '') === 'nhom_chung') ? 'selected' : '' ?>>
                                    🛍️ Khách hàng Thường (Nhận các tính năng đã phát hành chính thức)
                                </option>
                                <option value="nhom_beta" <?= (($_POST['group_id'] ?? '') === 'nhom_beta') ? 'selected' : '' ?>>
                                    🧪 Đăng ký Khách hàng Thử nghiệm Beta (Trải nghiệm sớm tính năng mới)
                                </option>
                                <option value="nhom_noibu" <?= (($_POST['group_id'] ?? '') === 'nhom_noibu') ? 'selected' : '' ?>>
                                    🛡️ Tài khoản Nội bộ / VIP (Mở khóa toàn bộ tính năng thử nghiệm)
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label>Mật khẩu *</label>
                            <div class="input-wrap">
                                <i class="fa-solid fa-key"></i>
                                <input type="password" name="password" class="form-control"
                                       placeholder="Tối thiểu 4 ký tự" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Nhập lại mật khẩu *</label>
                            <div class="input-wrap">
                                <i class="fa-solid fa-check-double"></i>
                                <input type="password" name="password_confirm" class="form-control"
                                       placeholder="Xác nhận mật khẩu" required>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fa-solid fa-user-plus"></i> Tạo Tài Khoản Khách Hàng Mới
                    </button>

                    <div class="helper-note">
                        Hệ thống sẽ tự động cấp mã khách hàng mới (VD: <code>KH1001</code>) và lưu trực tiếp vào bảng <code>customers</code> trong MySQL.
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        function quickFillLogin(khId, pwd) {
            const form = document.getElementById('customerLoginForm');
            if (!form) {
                window.location.href = 'customer-auth.php?mode=login';
                return;
            }
            document.getElementById('loginIdentifier').value = khId;
            document.getElementById('loginPassword').value = pwd;
            form.submit();
        }
    </script>
</body>
</html>

