<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

logout_customer();
set_flash('info', 'Bạn đã đăng xuất khỏi tài khoản CDS Store.');

header('Location: demo-app.php');
exit;
