<?php
/**
 * API: /api/users.php
 * Trả về danh sách người dùng mẫu (1.000 user: U0001 -> U1000)
 * Hỗ trợ tìm kiếm, phân trang và lọc theo nhóm (VIP, Beta, Khách thường)
 */

require_once __DIR__ . '/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Role');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$pdo = get_db();
$search = trim($_GET['search'] ?? '');
$group = trim($_GET['group'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 25)));
$offset = ($page - 1) * $limit;

$sql = "SELECT id, username, fullname, role, user_group FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (id LIKE ? OR fullname LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($group)) {
    $sql .= " AND user_group = ?";
    $params[] = $group;
}

// Đếm tổng số
$stmtCount = $pdo->prepare(str_replace("SELECT id, username, fullname, role, user_group FROM users", "SELECT COUNT(*) FROM users", $sql));
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();

// Lấy dữ liệu phân trang
$sql .= " ORDER BY id ASC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$bindIndex = 1;
foreach ($params as $p) {
    $stmt->bindValue($bindIndex++, $p);
}
$stmt->bindValue($bindIndex++, $limit, PDO::PARAM_INT);
$stmt->bindValue($bindIndex++, $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

send_json_response([
    'status' => 'success',
    'total' => $totalRows,
    'page' => $page,
    'limit' => $limit,
    'total_pages' => ceil($totalRows / $limit),
    'data' => $users
]);
