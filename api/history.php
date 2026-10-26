<?php
/**
 * API: /api/history.php
 * Trả về danh sách lịch sử thay đổi cấu hình, phát hành và khôi phục
 * Đáp ứng Chức năng 5: Lưu ai, lúc nào, phiên bản nào, lý do
 */

require_once __DIR__ . '/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Role, X-User-Name');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$pdo = get_db();
$env = $_GET['env'] ?? 'thu_nghiem';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

$stmt = $pdo->prepare("
    SELECT id, env, action, version_tag, author, reason, created_at
    FROM history
    WHERE env = ?
    ORDER BY id DESC
    LIMIT ?
");
$stmt->bindValue(1, $env, PDO::PARAM_STR);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->execute();
$history = $stmt->fetchAll();

send_json_response([
    'status' => 'success',
    'env' => $env,
    'total' => count($history),
    'data' => $history
]);
