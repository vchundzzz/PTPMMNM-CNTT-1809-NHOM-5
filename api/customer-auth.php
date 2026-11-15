<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../services/CustomerAuthService.php';

function customer_auth_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    customer_auth_response(['success' => false, 'error' => 'Method Not Allowed'], 405);
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    customer_auth_response(['success' => false, 'error' => 'JSON request không hợp lệ.'], 400);
}
if (!validate_csrf_token((string)($payload['csrf_token'] ?? ''))) {
    customer_auth_response(['success' => false, 'error' => 'CSRF token không hợp lệ.'], 419);
}

$action = (string)($payload['action'] ?? '');
if ($action === 'register') {
    $username = trim((string)($payload['username'] ?? ''));
    $password = (string)($payload['password'] ?? '');
    $confirmation = (string)($payload['password_confirmation'] ?? '');
    if ($password !== $confirmation) {
        customer_auth_response(['success' => false, 'error' => 'Mật khẩu xác nhận chưa trùng khớp.'], 422);
    }

    $result = CustomerAuthService::register(
        $username,
        $password,
        trim((string)($payload['group_id'] ?? 'nhom_chung'))
    );
    customer_auth_response(
        $result,
        $result['success'] ? 201 : 422
    );
}

if ($action === 'login') {
    $customer = CustomerAuthService::authenticate(
        trim((string)($payload['username'] ?? '')),
        (string)($payload['password'] ?? '')
    );
    if ($customer === null) {
        customer_auth_response(['success' => false, 'error' => 'Tên đăng nhập hoặc mật khẩu không chính xác.'], 401);
    }

    session_regenerate_id(true);
    $_SESSION['customer'] = $customer;
    customer_auth_response(['success' => true, 'data' => $customer]);
}

if ($action === 'logout') {
    logout_customer();
    customer_auth_response(['success' => true]);
}

if ($action === 'me') {
    if (!is_customer_logged_in()) {
        customer_auth_response(['success' => false, 'error' => 'Chưa đăng nhập.'], 401);
    }
    customer_auth_response(['success' => true, 'data' => get_authenticated_customer()]);
}

customer_auth_response(['success' => false, 'error' => 'Action không hợp lệ.'], 422);
