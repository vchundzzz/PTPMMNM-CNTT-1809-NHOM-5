<?php
/**
 * Feature flag mutation endpoint.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../services/PermissionService.php';
require_once __DIR__ . '/../services/DatabaseService.php';

function toggle_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_logged_in()) {
    toggle_response(['success' => false, 'error' => 'Yêu cầu đăng nhập.'], 401);
}
if (!PermissionService::currentUserCan('toggle_flag')) {
    toggle_response(['success' => false, 'error' => 'Bạn không có quyền bật/tắt cờ tính năng.'], 403);
}

$rawInput = file_get_contents('php://input');
$request = is_string($rawInput) && $rawInput !== '' ? json_decode($rawInput, true) : $_POST;
if (!is_array($request)) {
    toggle_response(['success' => false, 'error' => 'Dữ liệu yêu cầu không hợp lệ.'], 400);
}

$flagKey = trim((string)($request['flag_key'] ?? ''));
$env = trim((string)($request['environment'] ?? $request['env'] ?? get_active_env()));
if ($flagKey === '' || $env === '') {
    toggle_response(['success' => false, 'error' => 'Cờ hoặc môi trường không hợp lệ.'], 422);
}

$enabled = null;
if (array_key_exists('enabled', $request)) {
    $value = filter_var($request['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($value === null) {
        toggle_response(['success' => false, 'error' => 'enabled phải là boolean.'], 422);
    }
    $enabled = $value;
}

$actor = (string)(get_authenticated_user()['username'] ?? '');
$result = DatabaseService::toggleFlag($flagKey, $env, $enabled, $actor);
if (!$result['success']) {
    toggle_response(['success' => false, 'error' => $result['error']], $result['status']);
}

init_session_data(true);
append_audit_log(
    'BẬT_TẮT_CỜ',
    $flagKey,
    $result['old_enabled'] ? 'Bật' : 'Tắt',
    $result['enabled'] ? 'Bật' : 'Tắt',
    "Thay đổi qua API trên môi trường $env"
);

toggle_response([
    'success' => true,
    'flag_key' => $flagKey,
    'environment' => $env,
    'enabled' => $result['enabled'],
    'status' => $result['enabled'] ? 'Đã phát hành' : 'Bản nháp',
    'message' => 'Đã cập nhật trạng thái cờ thành công.',
]);
