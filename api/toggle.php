<?php
/**
 * Web Feature Flag Management System
 * api/toggle.php - API Bật/Tắt nhanh cờ tính năng theo thời gian thực (AJAX)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../services/PermissionService.php';
require_once __DIR__ . '/../services/FeatureFlagService.php';

init_session_data();

// Kiểm tra quyền
if (!PermissionService::currentUserCan('toggle_flag')) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Bạn không có quyền bật/tắt cờ tính năng.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Đọc dữ liệu từ JSON body hoặc POST
$raw_input = file_get_contents('php://input');
$request_data = [];
if (!empty($raw_input)) {
    $decoded = json_decode($raw_input, true);
    if (is_array($decoded)) {
        $request_data = $decoded;
    }
}
if (empty($request_data)) {
    $request_data = $_POST;
}

$flag_key = trim((string)($request_data['flag_key'] ?? ''));
$env      = trim((string)($request_data['environment'] ?? $request_data['env'] ?? get_active_env()));
$user_id  = trim((string)($request_data['user_id'] ?? ''));
if (!in_array($env, ['testing', 'staging'], true)) {
    $env = get_active_env();
}

if (empty($flag_key) || !isset($_SESSION['ff_data']['flags'][$flag_key])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => "Không tìm thấy cờ tính năng [$flag_key].",
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Xác định trạng thái mới
$has_explicit_enabled = isset($request_data['enabled']);
if ($has_explicit_enabled) {
    $new_enabled = filter_var($request_data['enabled'], FILTER_VALIDATE_BOOLEAN);
} else {
    $cur = !empty($_SESSION['ff_data']['flags'][$flag_key]['environments'][$env]['enabled']);
    $new_enabled = !$cur;
}

$user = get_authenticated_user()['username'] ?? 'admin';
$cur_state = !empty($_SESSION['ff_data']['flags'][$flag_key]['environments'][$env]['enabled']);

// Cập nhật cấu hình trong Session
$_SESSION['ff_data']['flags'][$flag_key]['environments'][$env]['enabled']    = $new_enabled;
$_SESSION['ff_data']['flags'][$flag_key]['environments'][$env]['status']     = $new_enabled ? 'Đã phát hành' : 'Bản nháp';
$_SESSION['ff_data']['flags'][$flag_key]['environments'][$env]['updated_at'] = date('Y-m-d H:i:s');
$_SESSION['ff_data']['flags'][$flag_key]['environments'][$env]['updated_by'] = $user;

// Nếu bật cờ mà tỷ lệ rollout đang = 0% hoặc chưa có nhóm mục tiêu thì tự động mở 100% để đồng bộ
if ($new_enabled) {
    $cur_pct = (int)($_SESSION['ff_data']['flags'][$flag_key]['environments'][$env]['rollout_percentage'] ?? 0);
    if ($cur_pct <= 0) {
        $_SESSION['ff_data']['flags'][$flag_key]['environments'][$env]['rollout_percentage'] = 100;
    }
    if (empty($_SESSION['ff_data']['flags'][$flag_key]['environments'][$env]['target_groups'])) {
        $_SESSION['ff_data']['flags'][$flag_key]['environments'][$env]['target_groups'] = ['nhom_noibu', 'nhom_beta', 'nhom_chung'];
    }
}

// Đồng bộ trực tiếp vào CSDL MySQL
try {
    require_once __DIR__ . '/../services/DatabaseService.php';
    DatabaseService::toggleFlag($flag_key, $env, $new_enabled, $user);

    // Nếu gọi từ demo-app.php với user_id cụ thể và bật cờ nhưng user_id đang bị chặn bởi Override/Rollout thì đồng bộ Override cho user_id đó
    if ($new_enabled && $user_id !== '') {
        $eval_check = FeatureFlagService::evaluate($flag_key, $user_id, $env);
        if (empty($eval_check['data']['enabled'])) {
            $_SESSION['ff_data']['flags'][$flag_key]['environments'][$env]['user_overrides'][$user_id] = true;
            $pdo = Database::getConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("
                    INSERT INTO flag_user_overrides (flag_key, environment, user_id, state, updated_at)
                    VALUES (?, ?, ?, 1, NOW())
                    ON DUPLICATE KEY UPDATE state = 1, updated_at = NOW()
                ");
                $stmt->execute([$flag_key, $env, $user_id]);
            }
        }
    }
} catch (Throwable $e) {}

// Ghi audit log
append_audit_log(
    'BẬT_TẮT_CỜ',
    $flag_key,
    $cur_state ? 'Bật' : 'Tắt',
    $new_enabled ? 'Bật' : 'Tắt',
    "Thay đổi qua bảng điều khiển trên môi trường " . ($env === 'testing' ? 'Kiểm thử' : 'Vận hành')
);

echo json_encode([
    'success'     => true,
    'flag_key'    => $flag_key,
    'environment' => $env,
    'enabled'     => $new_enabled,
    'status'      => $new_enabled ? 'Đã phát hành' : 'Bản nháp',
    'message'     => 'Đã ' . ($new_enabled ? 'BẬT' : 'TẮT') . " cờ [$flag_key] trên môi trường " . ($env === 'testing' ? 'Kiểm thử' : 'Vận hành') . '.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
