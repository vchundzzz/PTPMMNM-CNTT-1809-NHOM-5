<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}
$username = trim((string)($payload['username'] ?? ''));
$password = (string)($payload['password'] ?? '');
if ($username === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Tên đăng nhập và mật khẩu là bắt buộc.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!attempt_login($username, $password)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Thông tin đăng nhập không chính xác.'], JSON_UNESCAPED_UNICODE);
    exit;
}

session_regenerate_id(true);
echo json_encode(['success' => true, 'data' => get_authenticated_user()], JSON_UNESCAPED_UNICODE);