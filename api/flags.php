<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../services/DatabaseService.php';
require_once __DIR__ . '/../services/PermissionService.php';

function api_flags_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_logged_in()) {
    api_flags_response(['success' => false, 'error' => 'Yêu cầu đăng nhập.'], 401);
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$key = trim((string)($_GET['key'] ?? ''));
if ($method === 'GET') {
    $data = DatabaseService::loadDataFromDb();
    if ($data === null) {
        api_flags_response(['success' => false, 'error' => 'Không thể đọc dữ liệu từ cơ sở dữ liệu.'], 503);
    }
    $flags = $data['flags'] ?? [];
    api_flags_response(['success' => true, 'data' => $key !== '' ? ($flags[$key] ?? null) : array_values($flags)]);
}

$raw = file_get_contents('php://input');
$payload = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($payload)) {
    api_flags_response(['success' => false, 'error' => 'JSON request không hợp lệ.'], 400);
}
$actor = (string)(get_authenticated_user()['username'] ?? '');

if ($method === 'POST') {
    if (!PermissionService::currentUserCan('create_flag')) {
        api_flags_response(['success' => false, 'error' => 'Bạn không có quyền tạo cờ.'], 403);
    }
    $flagKey = strtolower(trim((string)($payload['key'] ?? '')));
    $name = trim((string)($payload['name'] ?? ''));
    $projectId = trim((string)($payload['project_id'] ?? 'prj_ecommerce'));
    if (!preg_match('/^[a-z0-9_]{2,100}$/', $flagKey) || $name === '') {
        api_flags_response(['success' => false, 'error' => 'key hoặc name không hợp lệ.'], 422);
    }
    $data = DatabaseService::loadDataFromDb();
    $id = 'flg_' . str_pad((string)(count($data['flags'] ?? []) + 1), 3, '0', STR_PAD_LEFT);
    if (!DatabaseService::createFlag(['id' => $id, 'key' => $flagKey, 'name' => $name, 'description' => trim((string)($payload['description'] ?? '')), 'project_id' => $projectId, 'updated_by' => $actor])) {
        api_flags_response(['success' => false, 'error' => 'Không thể lưu cờ vào cơ sở dữ liệu.'], 500);
    }
    init_session_data(true);
    api_flags_response(['success' => true, 'data' => $_SESSION['ff_data']['flags'][$flagKey] ?? null], 201);
}

if ($method === 'PATCH') {
    if (!PermissionService::currentUserCan('edit_flag')) {
        api_flags_response(['success' => false, 'error' => 'Bạn không có quyền cập nhật cờ.'], 403);
    }
    $flagKey = $key !== '' ? $key : trim((string)($payload['key'] ?? ''));
    $env = trim((string)($payload['environment'] ?? get_active_env()));
    $data = DatabaseService::loadDataFromDb();
    $current = $data['flags'][$flagKey]['environments'][$env] ?? null;
    if ($current === null) {
        api_flags_response(['success' => false, 'error' => 'Không tìm thấy cờ hoặc môi trường.'], 404);
    }
    $flag = $data['flags'][$flagKey];
    $enabled = array_key_exists('enabled', $payload) ? filter_var($payload['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : (bool)$current['enabled'];
    $pct = array_key_exists('rollout_percentage', $payload) ? filter_var($payload['rollout_percentage'], FILTER_VALIDATE_INT) : (int)$current['rollout_percentage'];
    if ($enabled === null || $pct === false || $pct < 0 || $pct > 100) {
        api_flags_response(['success' => false, 'error' => 'enabled hoặc rollout_percentage không hợp lệ.'], 422);
    }
    $groups = isset($payload['target_groups']) && is_array($payload['target_groups']) ? array_values($payload['target_groups']) : ($current['target_groups'] ?? []);
    $overrides = isset($payload['user_overrides']) && is_array($payload['user_overrides']) ? $payload['user_overrides'] : ($current['user_overrides'] ?? []);
    if (!DatabaseService::saveFlagDetail($flagKey, $env, trim((string)($payload['name'] ?? $flag['name'])), trim((string)($payload['description'] ?? $flag['description'])), $enabled, $pct, $groups, $overrides, $actor)) {
        api_flags_response(['success' => false, 'error' => 'Không thể cập nhật cờ trong cơ sở dữ liệu.'], 500);
    }
    init_session_data(true);
    api_flags_response(['success' => true, 'data' => $_SESSION['ff_data']['flags'][$flagKey] ?? null]);
}

if ($method === 'DELETE') {
    if (!PermissionService::currentUserCan('delete_flag')) {
        api_flags_response(['success' => false, 'error' => 'Bạn không có quyền xóa cờ.'], 403);
    }
    if ($key === '' || !DatabaseService::deleteFlag($key)) {
        api_flags_response(['success' => false, 'error' => 'Không thể xóa cờ hoặc cờ không tồn tại.'], 404);
    }
    init_session_data(true);
    api_flags_response(['success' => true, 'message' => 'Đã xóa cờ.']);
}

api_flags_response(['success' => false, 'error' => 'Method Not Allowed'], 405);
