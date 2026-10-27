<?php
/**
 * Web Feature Flag Management System
 * logout.php - Xử lý đăng xuất phiên làm việc
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

logout_user();
set_flash('info', 'Bạn đã đăng xuất khỏi phiên làm việc thành công.');

header('Location: login.php');
exit;
