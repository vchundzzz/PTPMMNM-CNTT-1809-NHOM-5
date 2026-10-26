<?php
/**
 * API: /api/flags.php
 * Quản lý danh sách cờ tính năng (CRUD)
 * GET: Lấy danh sách cờ theo môi trường
 * POST: Tạo cờ mới
 * PUT: Cập nhật cờ (tỷ lệ %, whitelist, công tắc)
 * DELETE: Xóa cờ
 * 
 * BẢO VỆ PHÂN QUYỀN: Kiểm tra quyền ở Server, tài khoản 'xem' sẽ bị chặn với HTTP 403 Forbidden
 */

require_once __DIR__ . '/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Role, X-User-Name');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$pdo = get_db();
$method = $_SERVER['REQUEST_METHOD'];
$env = $_GET['env'] ?? 'thu_nghiem';

// -------------------------------------------------------------
// GET: Xem danh sách tính năng (Cả tài khoản Xem và Sửa đều xem được)
// -------------------------------------------------------------
if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM flags WHERE env = ? ORDER BY id ASC");
    $stmt->execute([$env]);
    $flags = $stmt->fetchAll();

    // Lấy thông tin phiên bản đang phát hành của môi trường này
    $stmtVer = $pdo->prepare("SELECT version_tag, title, created_at FROM versions WHERE env = ? AND status = 'da_phat_hanh' ORDER BY id DESC LIMIT 1");
    $stmtVer->execute([$env]);
    $activeVersion = $stmtVer->fetch();

    send_json_response([
        'status' => 'success',
        'env' => $env,
        'active_version' => $activeVersion['version_tag'] ?? 'v1',
        'total' => count($flags),
        'data' => $flags
    ]);
}

// =============================================================
// CÁC THAO TÁC SỬA / TẠO / XÓA BẮT BUỘC KIỂM TRA QUYỀN Ở SERVER
// =============================================================
require_editor_permission();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$author = $_SERVER['HTTP_X_USER_NAME'] ?? 'admin';

// -------------------------------------------------------------
// POST: Tạo tính năng mới
// -------------------------------------------------------------
if ($method === 'POST') {
    $key = trim($input['key'] ?? '');
    $name = trim($input['name'] ?? '');
    $desc = trim($input['description'] ?? '');
    $pct = isset($input['percentage']) ? (int)$input['percentage'] : 50;
    $whitelist = trim($input['whitelist'] ?? '');
    $enabled = isset($input['enabled']) ? (int)$input['enabled'] : 1;
    $fallback = isset($input['fallback']) ? (int)$input['fallback'] : 0;

    // Kiểm tra hợp lệ: Tên không để trống, Tỷ lệ từ 0 đến 100
    if (empty($name)) {
        send_json_response(['status' => 'error', 'error' => 'Tên tính năng không được để trống!'], 400);
    }
    if (empty($key)) {
        // Tự động tạo key từ tên nếu chưa nhập
        $key = strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', $name));
    }
    if ($pct < 0 || $pct > 100) {
        send_json_response(['status' => 'error', 'error' => 'Tỷ lệ % áp dụng phải từ 0 đến 100!'], 400);
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO flags (key, name, description, env, enabled, fallback, percentage, whitelist)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$key, $name, $desc, $env, $enabled, $fallback, $pct, $whitelist]);

        // Ghi nhật ký
        $stmtHist = $pdo->prepare("
            INSERT INTO history (env, action, version_tag, author, reason)
            VALUES (?, 'tao_co', 'nhap', ?, ?)
        ");
        $stmtHist->execute([$env, $author, "Thêm tính năng mới: $name ($key)"]);

        send_json_response([
            'status' => 'success',
            'message' => "Đã tạo tính năng '$name' thành công!",
            'data' => [
                'key' => $key,
                'name' => $name,
                'percentage' => $pct,
                'env' => $env
            ]
        ], 201);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE') !== false) {
            send_json_response(['status' => 'error', 'error' => "Mã tính năng '$key' đã tồn tại trong môi trường này!"], 400);
        }
        send_json_response(['status' => 'error', 'error' => 'Lỗi CSDL: ' . $e->getMessage()], 500);
    }
}

// -------------------------------------------------------------
// PUT: Cập nhật cờ tính năng (Tỷ lệ %, Whitelist, Công tắc chính)
// -------------------------------------------------------------
if ($method === 'PUT') {
    $flagKey = $_GET['key'] ?? $input['key'] ?? '';
    if (empty($flagKey)) {
        send_json_response(['status' => 'error', 'error' => 'Thiếu mã tính năng (key)!'], 400);
    }

    // Kiểm tra cờ có tồn tại không
    $stmtCheck = $pdo->prepare("SELECT * FROM flags WHERE key = ? AND env = ?");
    $stmtCheck->execute([$flagKey, $env]);
    $existing = $stmtCheck->fetch();

    if (!$existing) {
        send_json_response(['status' => 'error', 'error' => "Không tìm thấy tính năng '$flagKey' trong môi trường '$env'!"], 404);
    }

    $name = isset($input['name']) ? trim($input['name']) : $existing['name'];
    $desc = isset($input['description']) ? trim($input['description']) : $existing['description'];
    $pct = isset($input['percentage']) ? (int)$input['percentage'] : (int)$existing['percentage'];
    $whitelist = isset($input['whitelist']) ? trim($input['whitelist']) : $existing['whitelist'];
    $enabled = isset($input['enabled']) ? (int)$input['enabled'] : (int)$existing['enabled'];
    $fallback = isset($input['fallback']) ? (int)$input['fallback'] : (int)$existing['fallback'];
    $reason = trim($input['reason'] ?? "Cập nhật tính năng $name");

    // Kiểm tra tính hợp lệ
    if (empty($name)) {
        send_json_response(['status' => 'error', 'error' => 'Tên tính năng không được để trống!'], 400);
    }
    if ($pct < 0 || $pct > 100) {
        send_json_response(['status' => 'error', 'error' => 'Tỷ lệ % áp dụng phải từ 0 đến 100!'], 400);
    }

    $stmtUpdate = $pdo->prepare("
        UPDATE flags 
        SET name = ?, description = ?, enabled = ?, fallback = ?, percentage = ?, whitelist = ?, updated_at = CURRENT_TIMESTAMP
        WHERE key = ? AND env = ?
    ");
    $stmtUpdate->execute([$name, $desc, $enabled, $fallback, $pct, $whitelist, $flagKey, $env]);

    // Ghi nhật ký
    $stmtHist = $pdo->prepare("
        INSERT INTO history (env, action, version_tag, author, reason)
        VALUES (?, 'sua_co', 'nhap', ?, ?)
    ");
    $stmtHist->execute([$env, $author, $reason]);

    send_json_response([
        'status' => 'success',
        'message' => "Cập nhật tính năng '$name' thành công!",
        'data' => [
            'key' => $flagKey,
            'name' => $name,
            'percentage' => $pct,
            'enabled' => $enabled,
            'whitelist' => $whitelist
        ]
    ]);
}

// -------------------------------------------------------------
// DELETE: Xóa cờ tính năng
// -------------------------------------------------------------
if ($method === 'DELETE') {
    $flagKey = $_GET['key'] ?? $input['key'] ?? '';
    if (empty($flagKey)) {
        send_json_response(['status' => 'error', 'error' => 'Thiếu mã tính năng (key)!'], 400);
    }

    $stmtDel = $pdo->prepare("DELETE FROM flags WHERE key = ? AND env = ?");
    $stmtDel->execute([$flagKey, $env]);

    // Ghi nhật ký
    $stmtHist = $pdo->prepare("
        INSERT INTO history (env, action, version_tag, author, reason)
        VALUES (?, 'xoa_co', 'nhap', ?, ?)
    ");
    $stmtHist->execute([$env, $author, "Xóa cờ tính năng: $flagKey"]);

    send_json_response([
        'status' => 'success',
        'message' => "Đã xóa tính năng '$flagKey' thành công!"
    ]);
}
