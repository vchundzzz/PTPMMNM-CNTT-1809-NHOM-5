<?php
/**
 * Web Feature Flag Management System
 * api/evaluate.php - API PHP quyết định Bật/Tắt tính năng theo thời gian thực
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../services/FeatureFlagService.php';

// Tiếp nhận tham số từ POST hoặc GET
$request_data = $_POST;
if (empty($request_data)) {
    // Thử đọc từ query GET hoặc php://input
    $raw_input = file_get_contents('php://input');
    if (!empty($raw_input)) {
        $json = json_decode($raw_input, true);
        if (is_array($json)) {
            $request_data = $json;
        }
    }
    if (empty($request_data)) {
        $request_data = $_GET;
    }
}

$flag_key = trim((string)($request_data['flag_key'] ?? ''));
$user_id = trim((string)($request_data['user_id'] ?? 'KH1'));
$env = trim((string)($request_data['environment_id'] ?? $request_data['environment'] ?? get_active_env()));
$project_id = trim((string)($request_data['project_id'] ?? 'prj_ecommerce'));

// Xử lý mô phỏng lỗi API nếu client gửi cờ simulate_error
if (!empty($request_data['simulate_error'])) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Simulated Server Error: Không thể kết nối máy chủ Feature Flag',
        'data' => [
            'flag_key' => $flag_key,
            'enabled' => FeatureFlagService::DEFAULT_FALLBACK,
            'environment' => $env,
            'version' => 'fallback',
            'reason' => 'Lỗi API được mô phỏng để kiểm tra giá trị dự phòng (Fallback: false)',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$result = FeatureFlagService::evaluate($flag_key, $user_id, $env, $project_id);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
