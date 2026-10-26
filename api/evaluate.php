<?php
/**
 * API: /api/evaluate.php
 * Trả lời câu hỏi từ Web Apple: "Người dùng có được bật tính năng không?"
 * Nhận tham số: user, flag, env, version (tùy chọn)
 * Trả về: true / false kèm lý do và bucket băm
 */

require_once __DIR__ . '/db.php';

// Thiết lập header CORS và JSON
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Role, X-User-Name');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$user = $_GET['user'] ?? $_POST['user'] ?? 'U0001';
$flagKey = $_GET['flag'] ?? $_POST['flag'] ?? '';
$env = $_GET['env'] ?? $_POST['env'] ?? 'thu_nghiem';
$version = $_GET['version'] ?? $_POST['version'] ?? null;

// Hỗ trợ đánh giá trên một bản snapshot phiên bản cụ thể
$customFlagsList = null;
if (!empty($version)) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT snapshot_json FROM versions WHERE version_tag = ? AND env = ?");
    $stmt->execute([$version, $env]);
    $verRow = $stmt->fetch();
    if ($verRow && !empty($verRow['snapshot_json'])) {
        $customFlagsList = json_decode($verRow['snapshot_json'], true);
    }
}

// 1. Trường hợp hỏi cụ thể 1 cờ tính năng (Ví dụ: banner_iphone_17)
if (!empty($flagKey)) {
    $result = evaluate_feature_flag($user, $flagKey, $env, $customFlagsList);
    
    send_json_response([
        'user' => $user,
        'flag' => $flagKey,
        'env' => $env,
        'version' => $version ?? 'active_published',
        'enabled' => $result['enabled'],
        'reason' => $result['reason'],
        'hash_bucket' => $result['hash_bucket'],
        'rule_applied' => $result['rule_applied'],
        'fallback' => $result['fallback']
    ]);
}

// 2. Trường hợp hỏi toàn bộ cờ tính năng cho user này trong môi trường
$pdo = get_db();
if ($customFlagsList === null) {
    // Đọc từ bản phát hành hiện tại hoặc bảng flags
    $stmtVer = $pdo->prepare("SELECT snapshot_json FROM versions WHERE env = ? AND status = 'da_phat_hanh' ORDER BY id DESC LIMIT 1");
    $stmtVer->execute([$env]);
    $verRow = $stmtVer->fetch();
    if ($verRow && !empty($verRow['snapshot_json'])) {
        $flagsToEval = json_decode($verRow['snapshot_json'], true);
    } else {
        $stmtFlags = $pdo->prepare("SELECT * FROM flags WHERE env = ?");
        $stmtFlags->execute([$env]);
        $flagsToEval = $stmtFlags->fetchAll();
    }
} else {
    $flagsToEval = $customFlagsList;
}

$evaluatedFlags = [];
foreach ($flagsToEval as $f) {
    $k = $f['key'];
    $res = evaluate_feature_flag($user, $k, $env, $flagsToEval);
    $evaluatedFlags[$k] = [
        'name' => $f['name'],
        'description' => $f['description'] ?? '',
        'enabled' => $res['enabled'],
        'reason' => $res['reason'],
        'hash_bucket' => $res['hash_bucket'],
        'percentage' => (int)$f['percentage'],
        'whitelist' => $f['whitelist'] ?? '',
        'fallback' => (int)($f['fallback'] ?? 0)
    ];
}

send_json_response([
    'user' => $user,
    'env' => $env,
    'total_flags' => count($evaluatedFlags),
    'flags' => $evaluatedFlags
]);
