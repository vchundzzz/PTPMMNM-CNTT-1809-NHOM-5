<?php
/**
 * customer-auth.php
 * Chuyển hướng đồng bộ sang trang đăng nhập/đăng ký tài khoản khách hàng thực tế (store-auth.php)
 */

declare(strict_types=1);

$mode = ($_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';
$redirect = trim((string)($_GET['redirect'] ?? 'demo-app.php'));
header('Location: store-auth.php?mode=' . rawurlencode($mode) . '&redirect=' . rawurlencode($redirect));
exit;
