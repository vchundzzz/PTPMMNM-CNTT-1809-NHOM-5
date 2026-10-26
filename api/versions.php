<?php
/**
 * API: /api/versions.php
 * Quản lý phiên bản cấu hình (Snapshots, Phát hành, So sánh Diff, Khôi phục Rollback)
 * 
 * Đáp ứng Chức năng 4 & Yêu cầu kỹ thuật 2:
 * - Lưu bản chụp cấu hình hiện tại (v1, v2, v3...)
 * - Phát hành bản mới và khóa không cho sửa trực tiếp bản đã phát hành
 * - So sánh trực quan (Visual Diff) giữa 2 phiên bản
 * - Khôi phục (Rollback): Tạo phiên bản mới giống hệt bản cũ rồi phát hành
 */

require_once __DIR__ . '/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Role, X-User-Name');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$pdo = get_db();
$action = $_GET['action'] ?? 'list';
$env = $_GET['env'] ?? 'thu_nghiem';

// -------------------------------------------------------------
// 1. DANH SÁCH CÁC PHIÊN BẢN (ACTION: list)
// -------------------------------------------------------------
if ($action === 'list') {
    $stmt = $pdo->prepare("
        SELECT id, version_tag, env, title, description, status, created_by, created_at,
               length(snapshot_json) as snapshot_size
        FROM versions 
        WHERE env = ? 
        ORDER BY id DESC
    ");
    $stmt->execute([$env]);
    $versions = $stmt->fetchAll();

    // Thêm số lượng cờ cho mỗi phiên bản
    $results = [];
    foreach ($versions as $v) {
        $stmtDetail = $pdo->prepare("SELECT snapshot_json FROM versions WHERE id = ?");
        $stmtDetail->execute([$v['id']]);
        $json = $stmtDetail->fetchColumn();
        $flagsArray = json_decode($json, true) ?: [];
        $v['flag_count'] = count($flagsArray);
        $results[] = $v;
    }

    send_json_response([
        'status' => 'success',
        'env' => $env,
        'data' => $results
    ]);
}

// -------------------------------------------------------------
// 2. SO SÁNH 2 PHIÊN BẢN (ACTION: diff)
// Hiện bảng 2 cột, cái nào khác nhau thì đánh dấu tô màu
// -------------------------------------------------------------
if ($action === 'diff') {
    $v1Tag = $_GET['v1'] ?? '';
    $v2Tag = $_GET['v2'] ?? '';

    if (empty($v1Tag) || empty($v2Tag)) {
        send_json_response(['status' => 'error', 'error' => 'Vui lòng cung cấp cả v1 và v2 để so sánh!'], 400);
    }

    // Đọc snapshot v1
    $stmt1 = $pdo->prepare("SELECT snapshot_json, title, created_at FROM versions WHERE version_tag = ? AND env = ?");
    $stmt1->execute([$v1Tag, $env]);
    $row1 = $stmt1->fetch();

    // Đọc snapshot v2
    $stmt2 = $pdo->prepare("SELECT snapshot_json, title, created_at FROM versions WHERE version_tag = ? AND env = ?");
    $stmt2->execute([$v2Tag, $env]);
    $row2 = $stmt2->fetch();

    if (!$row1 || !$row2) {
        send_json_response(['status' => 'error', 'error' => 'Không tìm thấy một trong hai phiên bản cần so sánh!'], 404);
    }

    $flags1 = json_decode($row1['snapshot_json'], true) ?: [];
    $flags2 = json_decode($row2['snapshot_json'], true) ?: [];

    // Lập bản đồ tra cứu theo key
    $map1 = [];
    foreach ($flags1 as $f) { $map1[$f['key']] = $f; }

    $map2 = [];
    foreach ($flags2 as $f) { $map2[$f['key']] = $f; }

    $allKeys = array_unique(array_merge(array_keys($map1), array_keys($map2)));
    sort($allKeys);

    $diffRows = [];
    $totalDifferences = 0;

    foreach ($allKeys as $k) {
        $f1 = $map1[$k] ?? null;
        $f2 = $map2[$k] ?? null;

        $name = $f2['name'] ?? $f1['name'] ?? $k;
        $pct1 = $f1 ? (int)$f1['percentage'] : null;
        $pct2 = $f2 ? (int)$f2['percentage'] : null;
        $en1 = $f1 ? (int)$f1['enabled'] : null;
        $en2 = $f2 ? (int)$f2['enabled'] : null;
        $wl1 = $f1 ? trim($f1['whitelist']) : '';
        $wl2 = $f2 ? trim($f2['whitelist']) : '';

        // Kiểm tra xem có khác nhau không
        $isDifferent = false;
        $changes = [];

        if ($f1 === null && $f2 !== null) {
            $isDifferent = true;
            $changes[] = 'Tính năng mới thêm vào ở ' . $v2Tag;
        } elseif ($f1 !== null && $f2 === null) {
            $isDifferent = true;
            $changes[] = 'Tính năng bị xóa ở ' . $v2Tag;
        } else {
            if ($pct1 !== $pct2) {
                $isDifferent = true;
                $changes[] = "Tỷ lệ đổi từ {$pct1}% sang {$pct2}%";
            }
            if ($en1 !== $en2) {
                $isDifferent = true;
                $changes[] = "Công tắc đổi từ " . ($en1 ? 'BẬT' : 'TẮT') . " sang " . ($en2 ? 'BẬT' : 'TẮT');
            }
            if ($wl1 !== $wl2) {
                $isDifferent = true;
                $changes[] = "Danh sách người dùng ưu tiên (Whitelist) thay đổi";
            }
        }

        if ($isDifferent) {
            $totalDifferences++;
        }

        $diffRows[] = [
            'key' => $k,
            'name' => $name,
            'v1_exists' => ($f1 !== null),
            'v2_exists' => ($f2 !== null),
            'v1_percentage' => $pct1,
            'v2_percentage' => $pct2,
            'v1_enabled' => $en1,
            'v2_enabled' => $en2,
            'v1_whitelist' => $wl1,
            'v2_whitelist' => $wl2,
            'is_different' => $isDifferent,
            'changes' => $changes
        ];
    }

    send_json_response([
        'status' => 'success',
        'env' => $env,
        'v1' => [
            'tag' => $v1Tag,
            'title' => $row1['title'],
            'created_at' => $row1['created_at']
        ],
        'v2' => [
            'tag' => $v2Tag,
            'title' => $row2['title'],
            'created_at' => $row2['created_at']
        ],
        'total_differences' => $totalDifferences,
        'diff_rows' => $diffRows
    ]);
}

// =============================================================
// CÁC THAO TÁC PHÁT HÀNH / KHÔI PHỤC BẮT BUỘC KIỂM TRA QUYỀN Ở SERVER
// =============================================================
require_editor_permission();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$author = $_SERVER['HTTP_X_USER_NAME'] ?? $input['author'] ?? 'admin';
$reason = trim($input['reason'] ?? '');

// -------------------------------------------------------------
// 3. PHÁT HÀNH PHIÊN BẢN MỚI (ACTION: publish)
// Chụp lại toàn bộ cấu hình hiện tại (v1, v2, v3...) và phát hành
// -------------------------------------------------------------
if ($action === 'publish') {
    if (empty($reason)) {
        send_json_response(['status' => 'error', 'error' => 'Bắt buộc phải nhập lý do phát hành phiên bản mới!'], 400);
    }

    $title = trim($input['title'] ?? '');
    
    // Đọc toàn bộ cờ hiện tại của môi trường
    $stmtCurrent = $pdo->prepare("SELECT * FROM flags WHERE env = ? ORDER BY id ASC");
    $stmtCurrent->execute([$env]);
    $currentFlags = $stmtCurrent->fetchAll();

    if (empty($currentFlags)) {
        send_json_response(['status' => 'error', 'error' => 'Chưa có cờ tính năng nào để phát hành!'], 400);
    }

    // Tự động tìm số hiệu phiên bản tiếp theo: v1 -> v2 -> v3...
    $stmtMaxVer = $pdo->prepare("SELECT version_tag FROM versions WHERE env = ? ORDER BY id DESC LIMIT 1");
    $stmtMaxVer->execute([$env]);
    $lastVerTag = $stmtMaxVer->fetchColumn();

    $nextNum = 1;
    if ($lastVerTag && preg_match('/v(\d+)/i', $lastVerTag, $m)) {
        $nextNum = ((int)$m[1]) + 1;
    }
    $newVerTag = 'v' . $nextNum;

    if (empty($title)) {
        $title = "Bản phát hành $newVerTag";
    }

    $snapshotJson = json_encode($currentFlags, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    // Lưu vào bảng versions với trạng thái 'da_phat_hanh' (Khóa bất biến)
    $stmtInsert = $pdo->prepare("
        INSERT INTO versions (version_tag, env, title, description, snapshot_json, status, created_by)
        VALUES (?, ?, ?, ?, ?, 'da_phat_hanh', ?)
    ");
    $stmtInsert->execute([$newVerTag, $env, $title, $reason, $snapshotJson, $author]);

    // Ghi lịch sử
    $stmtHist = $pdo->prepare("
        INSERT INTO history (env, action, version_tag, author, reason)
        VALUES (?, 'phat_hanh', ?, ?, ?)
    ");
    $stmtHist->execute([$env, $newVerTag, $author, $reason]);

    send_json_response([
        'status' => 'success',
        'message' => "Đã phát hành thành công phiên bản $newVerTag!",
        'version_tag' => $newVerTag,
        'title' => $title,
        'reason' => $reason,
        'flag_count' => count($currentFlags)
    ], 201);
}

// -------------------------------------------------------------
// 4. KHÔI PHỤC (ROLLBACK) VỀ BẢN CŨ (ACTION: rollback)
// Tạo phiên bản mới giống hệt bản cũ rồi phát hành
// -------------------------------------------------------------
if ($action === 'rollback') {
    $targetVersion = trim($input['version'] ?? '');
    if (empty($targetVersion)) {
        send_json_response(['status' => 'error', 'error' => 'Thiếu thông tin phiên bản cần khôi phục!'], 400);
    }
    if (empty($reason)) {
        send_json_response(['status' => 'error', 'error' => 'Bắt buộc phải nhập lý do khôi phục phiên bản!'], 400);
    }

    // Đọc snapshot của bản cũ cần khôi phục
    $stmtTarget = $pdo->prepare("SELECT * FROM versions WHERE version_tag = ? AND env = ?");
    $stmtTarget->execute([$targetVersion, $env]);
    $oldVersion = $stmtTarget->fetch();

    if (!$oldVersion) {
        send_json_response(['status' => 'error', 'error' => "Không tìm thấy phiên bản '$targetVersion' để khôi phục!"], 404);
    }

    $flagsRestored = json_decode($oldVersion['snapshot_json'], true);
    if (!is_array($flagsRestored)) {
        send_json_response(['status' => 'error', 'error' => 'Dữ liệu bản chụp snapshot của phiên bản cũ bị hỏng!'], 500);
    }

    // Tìm số hiệu phiên bản mới nhất tiếp theo (ví dụ v3 -> v4)
    $stmtMaxVer = $pdo->prepare("SELECT version_tag FROM versions WHERE env = ? ORDER BY id DESC LIMIT 1");
    $stmtMaxVer->execute([$env]);
    $lastVerTag = $stmtMaxVer->fetchColumn();

    $nextNum = 1;
    if ($lastVerTag && preg_match('/v(\d+)/i', $lastVerTag, $m)) {
        $nextNum = ((int)$m[1]) + 1;
    }
    $newRollbackTag = 'v' . $nextNum;
    $newTitle = "Khôi phục từ {$targetVersion} (Rollback)";

    // Bắt đầu giao dịch: ghi đè bảng flags hiện tại và tạo version mới
    $pdo->beginTransaction();
    try {
        // Cập nhật lại bảng flags làm việc về đúng trạng thái bản cũ
        $stmtDel = $pdo->prepare("DELETE FROM flags WHERE env = ?");
        $stmtDel->execute([$env]);

        $stmtInsertFlag = $pdo->prepare("
            INSERT INTO flags (key, name, description, env, enabled, fallback, percentage, whitelist)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($flagsRestored as $rf) {
            $stmtInsertFlag->execute([
                $rf['key'],
                $rf['name'],
                $rf['description'] ?? '',
                $env,
                $rf['enabled'] ?? 1,
                $rf['fallback'] ?? 0,
                $rf['percentage'] ?? 50,
                $rf['whitelist'] ?? ''
            ]);
        }

        // Tạo bản phát hành mới giống hệt bản cũ
        $stmtVer = $pdo->prepare("
            INSERT INTO versions (version_tag, env, title, description, snapshot_json, status, created_by)
            VALUES (?, ?, ?, ?, ?, 'da_phat_hanh', ?)
        ");
        $stmtVer->execute([
            $newRollbackTag,
            $env,
            $newTitle,
            "Khôi phục cấu hình từ phiên bản {$targetVersion}. Lý do: {$reason}",
            $oldVersion['snapshot_json'],
            $author
        ]);

        // Ghi lịch sử khôi phục
        $stmtHist = $pdo->prepare("
            INSERT INTO history (env, action, version_tag, author, reason)
            VALUES (?, 'khoi_phuc', ?, ?, ?)
        ");
        $stmtHist->execute([
            $env,
            $newRollbackTag,
            $author,
            "Khôi phục về {$targetVersion}. Lý do: {$reason}"
        ]);

        $pdo->commit();

        send_json_response([
            'status' => 'success',
            'message' => "Đã khôi phục thành công về cấu hình của {$targetVersion} và phát hành thành bản {$newRollbackTag}!",
            'restored_from' => $targetVersion,
            'new_version' => $newRollbackTag,
            'flag_count' => count($flagsRestored)
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        send_json_response(['status' => 'error', 'error' => 'Lỗi khi khôi phục: ' . $e->getMessage()], 500);
    }
}
