<?php
require_once __DIR__ . '/../includes/helpers.php';

echo "=== KIỂM TRA PHÂN QUYỀN HIỂN THỊ BẢNG ĐIỀU KHIỂN TESTING CONTROLLER ===\n";

// Case 1: Admin
$_SESSION['user'] = ['id' => 'usr_admin', 'username' => 'admin', 'role' => 'admin', 'name' => 'Admin'];
ob_start();
include __DIR__ . '/../demo-app.php';
$html_admin = ob_get_clean();
$admin_sees_panel = str_contains($html_admin, 'Bảng điều khiển kiểm thử Feature Flags (Testing Controller)');
echo "1. Tài khoản Admin: " . ($admin_sees_panel ? 'THẤY VÀ SỬA ĐƯỢC (ĐÚNG)' : 'KHÔNG THẤY (LỖI)') . "\n";

// Case 2: Editor (Biên tập)
$_SESSION['user'] = ['id' => 'usr_editor', 'username' => 'editor', 'role' => 'editor', 'name' => 'Editor'];
ob_start();
include __DIR__ . '/../demo-app.php';
$html_editor = ob_get_clean();
$editor_sees_panel = str_contains($html_editor, 'Bảng điều khiển kiểm thử Feature Flags (Testing Controller)');
echo "2. Tài khoản Biên tập (Editor): " . ($editor_sees_panel ? 'THẤY VÀ SỬA ĐƯỢC (ĐÚNG)' : 'KHÔNG THẤY (LỖI)') . "\n";

// Case 3: Viewer (Chỉ xem)
$_SESSION['user'] = ['id' => 'usr_viewer', 'username' => 'viewer', 'role' => 'viewer', 'name' => 'Viewer'];
ob_start();
include __DIR__ . '/../demo-app.php';
$html_viewer = ob_get_clean();
$viewer_sees_panel = str_contains($html_viewer, 'Bảng điều khiển kiểm thử Feature Flags (Testing Controller)');
echo "3. Tài khoản Viewer (Chỉ xem): " . (!$viewer_sees_panel ? 'ĐÃ ẨN HOÀN TOÀN (CHÍNH XÁC)' : 'VẪN THẤY (LỖI)') . "\n";
