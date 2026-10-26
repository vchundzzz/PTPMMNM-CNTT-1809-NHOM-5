<?php
/**
 * API: /api/import_export.php
 * Chức năng 5: Nhập và Xuất cấu hình bằng file JSON
 * - Xuất: Tải xuống cấu hình của môi trường hiện tại
 * - Nhập: Nạp cấu hình từ JSON
 *   Kiểm tra bắt buộc:
 *   1. Tên tính năng (name) không được để trống
 *   2. Tỷ lệ % (percentage) phải từ 0 đến 100
 *   Nếu có bất kỳ dòng nào sai -> Báo lỗi chi tiết và TỪ CHỐI KHÔNG NHẬP
 */

require_once __DIR__ . '/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Role, X-User-Name');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$pdo = get_db();
$action = $_GET['action'] ?? 'export';
$env = $_GET['env'] ?? 'thu_nghiem';

// -------------------------------------------------------------
// 1. XUẤT CẤU HÌNH RA FILE JSON (ACTION: export)
// -------------------------------------------------------------
if ($action === 'export') {
    $stmt = $pdo->prepare("SELECT key, name, description, enabled, fallback, percentage, whitelist FROM flags WHERE env = ? ORDER BY id ASC");
    $stmt->execute([$env]);
    $flags = $stmt->fetchAll();

    $exportData = [
        'system' => 'Apple Feature Flags Manager',
        'exported_at' => date('Y-m-d H:i:s'),
        'environment' => $env,
        'flag_count' => count($flags),
        'flags' => $flags
    ];

    $filename = "apple_feature_flags_{$env}_" . date('Ymd_His') . ".json";

    if (isset($_GET['download']) && $_GET['download'] === '1') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($exportData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    } else {
        send_json_response($exportData);
    }
}

// -------------------------------------------------------------
// 2. NHẬP CẤU HÌNH TỪ FILE HOẶC CHUỖI JSON (ACTION: import)
// Bắt buộc kiểm tra quyền ở Server và kiểm tra dữ liệu hợp lệ
// -------------------------------------------------------------
if ($action === 'import') {
    require_editor_permission();

    $author = $_SERVER['HTTP_X_USER_NAME'] ?? 'admin';

    // Nhận dữ liệu JSON từ body hoặc file upload
    $rawJson = '';
    if (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $rawJson = file_get_contents($_FILES['file']['tmp_name']);
    } else {
        $rawJson = file_get_contents('php://input');
    }

    $inputData = json_decode($rawJson, true);
    if (!$inputData) {
        send_json_response(['status' => 'error', 'error' => 'Dữ liệu không phải định dạng JSON hợp lệ!'], 400);
    }

    // Cho phép nhập mảng flags hoặc object chứa thuộc tính flags
    $flagsList = $inputData['flags'] ?? $inputData;
    if (!is_array($flagsList) || empty($flagsList)) {
        send_json_response(['status' => 'error', 'error' => 'Tệp JSON không chứa danh sách tính năng hợp lệ!'], 400);
    }

    // =========================================================
    // KIỂM TRA TÍNH HỢP LỆ (VALIDATION)
    // 1. Tên không trống
    // 2. Tỷ lệ từ 0 đến 100
    // =========================================================
    $errors = [];
    foreach ($flagsList as $index => $item) {
        $rowNum = $index + 1;
        $name = trim($item['name'] ?? '');
        $pct = $item['percentage'] ?? null;

        // Kiểm tra 1: Tên không để trống
        if (empty($name)) {
            $errors[] = "Dòng $rowNum: Tên tính năng (name) bị để trống!";
        }

        // Kiểm tra 2: Tỷ lệ phải từ 0 đến 100
        if ($pct === null || !is_numeric($pct) || (int)$pct < 0 || (int)$pct > 100) {
            $errors[] = "Dòng $rowNum ('$name'): Tỷ lệ % phải là số từ 0 đến 100 (giá trị hiện tại: " . json_encode($pct) . ")!";
        }
    }

    // Nếu có lỗi -> BÁO LỖI VÀ TỪ CHỐI TOÀN BỘ, KHÔNG NHẬP VÀO DATABASE
    if (!empty($errors)) {
        send_json_response([
            'status' => 'error',
            'error' => 'Dữ liệu nhập không hợp lệ. Hệ thống đã hủy toàn bộ thao tác nhập!',
            'validation_errors' => $errors
        ], 422);
    }

    // Nếu hợp lệ: Tiến hành nạp vào Database
    $pdo->beginTransaction();
    try {
        $stmtDel = $pdo->prepare("DELETE FROM flags WHERE env = ?");
        $stmtDel->execute([$env]);

        $stmtInsert = $pdo->prepare("
            INSERT INTO flags (key, name, description, env, enabled, fallback, percentage, whitelist)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $importedCount = 0;
        foreach ($flagsList as $item) {
            $key = trim($item['key'] ?? '');
            $name = trim($item['name']);
            if (empty($key)) {
                $key = strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', $name));
            }
            $desc = trim($item['description'] ?? '');
            $enabled = isset($item['enabled']) ? (int)$item['enabled'] : 1;
            $fallback = isset($item['fallback']) ? (int)$item['fallback'] : 0;
            $pct = (int)$item['percentage'];
            $whitelist = trim($item['whitelist'] ?? '');

            $stmtInsert->execute([$key, $name, $desc, $env, $enabled, $fallback, $pct, $whitelist]);
            $importedCount++;
        }

        // Ghi lịch sử nhập cấu hình
        $reason = trim($inputData['reason'] ?? "Nhập $importedCount cờ tính năng từ tệp JSON");
        $stmtHist = $pdo->prepare("
            INSERT INTO history (env, action, version_tag, author, reason)
            VALUES (?, 'nhap_json', 'nhap', ?, ?)
        ");
        $stmtHist->execute([$env, $author, $reason]);

        $pdo->commit();

        send_json_response([
            'status' => 'success',
            'message' => "Đã nhập thành công $importedCount tính năng vào môi trường $env!",
            'imported_count' => $importedCount
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        send_json_response(['status' => 'error', 'error' => 'Lỗi lưu vào CSDL: ' . $e->getMessage()], 500);
    }
}
