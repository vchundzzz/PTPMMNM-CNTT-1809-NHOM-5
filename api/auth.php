<?php
/**
 * API: /api/auth.php
 * Trả về danh sách tài khoản quản trị và thông tin quyền (Xem / Sửa)
 */

require_once __DIR__ . '/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Role, X-User-Name');
header('Content-Type: application/json; charset=utf-8');

$pdo = get_db();

$accounts = [
    [
        'id' => 'ADMIN_01',
        'username' => 'admin',
        'fullname' => 'Admin',
        'role' => 'sua',
        'role_title' => 'Toàn quyền (Sửa & Phát hành)',
        'badge' => 'badge-success'
    ],
    [
        'id' => 'EDITOR_01',
        'username' => 'developer',
        'fullname' => 'Kỹ sư',
        'role' => 'sua',
        'role_title' => 'Lập trình viên (Sửa & Phát hành)',
        'badge' => 'badge-info'
    ],
    [
        'id' => 'VIEWER_01',
        'username' => 'viewer',
        'fullname' => 'Người dùng',
        'role' => 'xem',
        'role_title' => 'Khách xem (Chỉ đọc - Bị chặn sửa)',
        'badge' => 'badge-warning'
    ]
];

send_json_response([
    'status' => 'success',
    'accounts' => $accounts
]);
