<?php
/**
 * Web Feature Flag Management System
 * import-export.php - Nhập và Xuất cấu hình hệ thống (Đồng bộ trực tiếp với CSDL MySQL)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/services/PermissionService.php';
require_once __DIR__ . '/services/DatabaseService.php';

require_login();

// Xử lý Xuất file JSON tải về trực tiếp
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    PermissionService::enforce('export_config');

    $export_data = [
        'system' => 'Web Feature Flag Management System',
        'exported_at' => date('Y-m-d H:i:s'),
        'exported_by' => get_authenticated_user()['username'] ?? 'unknown',
        'data' => $_SESSION['ff_data'],
    ];

    $filename = 'feature-flags-backup-' . date('Ymd-His') . '.json';
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Xử lý Nhập file JSON cấu hình và đồng bộ vào MySQL
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'import_json') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Mã bảo mật CSRF không hợp lệ.');
        header('Location: import-export.php');
        exit;
    }

    PermissionService::enforce('import_config');

    if (empty($_FILES['config_file']['tmp_name']) || !is_uploaded_file($_FILES['config_file']['tmp_name'])) {
        set_flash('error', 'Vui lòng chọn tệp JSON cấu hình hợp lệ từ máy tính.');
        header('Location: import-export.php');
        exit;
    }

    $rawJson = (string)file_get_contents($_FILES['config_file']['tmp_name']);
    $decoded = json_decode($rawJson, true);

    if (!is_array($decoded)) {
        set_flash('error', 'Tệp tải lên không đúng định dạng JSON hợp lệ.');
        header('Location: import-export.php');
        exit;
    }

    $payload = isset($decoded['data']) && is_array($decoded['data']) ? $decoded['data'] : $decoded;
    if (empty($payload['flags']) || !is_array($payload['flags'])) {
        set_flash('error', 'Dữ liệu nhập thiếu danh sách cờ tính năng (flags) hợp lệ.');
        header('Location: import-export.php');
        exit;
    }

    // Kiểm tra tính hợp lệ của từng cờ (rollout 0 - 100%)
    $importedCount = 0;
    $user = get_authenticated_user()['username'] ?? 'admin';

    foreach ($payload['flags'] as $fKey => $fData) {
        if (!is_array($fData)) continue;
        $key  = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($fData['key'] ?? $fKey)));
        $name = trim((string)($fData['name'] ?? $key));
        $desc = trim((string)($fData['description'] ?? ''));
        if ($key === '') continue;

        if (!isset($_SESSION['ff_data']['flags'][$key])) {
            DatabaseService::createFlag([
                'id'          => $fData['id'] ?? ('flg_' . uniqid()),
                'key'         => $key,
                'name'        => $name,
                'description' => $desc,
                'updated_by'  => $user,
            ]);
        }

        foreach (['testing', 'staging'] as $envKey) {
            if (isset($fData['environments'][$envKey]) && is_array($fData['environments'][$envKey])) {
                $ec = $fData['environments'][$envKey];
                $en = !empty($ec['enabled']);
                $pct = max(0, min(100, (int)($ec['rollout_percentage'] ?? 0)));
                $grps = is_array($ec['target_groups'] ?? null) ? array_values($ec['target_groups']) : [];
                $ovrs = is_array($ec['user_overrides'] ?? null) ? $ec['user_overrides'] : [];

                DatabaseService::saveFlagDetail($key, $envKey, $name, $desc, $en, $pct, $grps, $ovrs, $user);
            }
        }
        $importedCount++;
    }

    $_SESSION['ff_data']['flags'] = $payload['flags'];
    init_session_data(true);

    append_audit_log(
        'NHẬP_CẤU_HÌNH',
        'Hệ thống (' . $importedCount . ' cờ)',
        'Cấu hình trước đó',
        'Đã nhập từ file JSON',
        'Nhập và đồng bộ cấu hình từ file JSON vào CSDL MySQL'
    );

    set_flash('success', "Đã kiểm tra và nhập thành công $importedCount cờ tính năng vào hệ thống & CSDL MySQL!");
    header('Location: import-export.php');
    exit;
}

$page_title = 'Nhập / Xuất cấu hình';
$breadcrumbs = [
    ['label' => 'Trang chủ', 'url' => 'dashboard.php'],
    ['label' => 'Nhập / Xuất cấu hình', 'url' => ''],
];

require_once __DIR__ . '/includes/header.php';
$flags_total = count($_SESSION['ff_data']['flags'] ?? []);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Nhập / Xuất cấu hình hệ thống</h1>
    <p class="page-description">
      Sao lưu và khôi phục toàn bộ cấu hình dự án, môi trường, cờ tính năng và phiên bản (đồng bộ trực tiếp với CSDL MySQL).
    </p>
  </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem;">
  <!-- Khối Xuất cấu hình (Export) -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
          <polyline points="7 10 12 15 17 10"></polyline>
          <line x1="12" y1="15" x2="12" y2="3"></line>
        </svg>
        <span>Xuất dữ liệu cấu hình (JSON)</span>
      </h2>
    </div>
    <div class="card-body">
      <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem; line-height: 1.5;">
        Tải xuống bản sao lưu đầy đủ gồm: dự án, 2 môi trường, <?= $flags_total ?> tính năng, nhóm người dùng và toàn bộ lịch sử các phiên bản từ CSDL.
      </p>

      <div style="background-color: var(--bg-body); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.25rem; font-size: 0.82rem;">
        <div style="margin-bottom: 0.35rem;">&bull; Định dạng: <strong>JSON chuẩn (UTF-8)</strong></div>
        <div style="margin-bottom: 0.35rem;">&bull; Dữ liệu: <strong>Đồng bộ thời gian thực từ MySQL</strong></div>
        <div>&bull; Quyền thực hiện: <strong>Tất cả vai trò</strong></div>
      </div>

      <a href="import-export.php?action=export" class="btn btn-primary" style="width: 100%; justify-content: center;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
        <span>Tải xuống bản sao lưu (.json)</span>
      </a>
    </div>
  </div>

  <!-- Khối Nhập cấu hình (Import) -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
          <polyline points="17 8 12 3 7 8"></polyline>
          <line x1="12" y1="3" x2="12" y2="15"></line>
        </svg>
        <span>Nhập dữ liệu cấu hình (JSON)</span>
      </h2>
    </div>
    <div class="card-body">
      <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem; line-height: 1.5;">
        Tải lên tệp cấu hình <code>.json</code> để khôi phục. Hệ thống tự động kiểm tra cấu trúc, tỷ lệ hợp lệ (0 &ndash; 100%) và ghi trực tiếp vào CSDL MySQL.
      </p>

      <form method="POST" action="import-export.php" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import_json">

        <div class="form-group">
          <label class="form-label">Chọn tệp cấu hình từ máy tính (.json)</label>
          <input type="file" name="config_file" accept=".json" class="form-control" required <?= PermissionService::currentUserCan('import_config') ? '' : 'disabled' ?>>
        </div>

        <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content: center;" <?= PermissionService::currentUserCan('import_config') ? '' : 'disabled' ?>>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 12 2 2 4-4"/><path d="M20 6 9 17l-5-5"/></svg>
          <span>Kiểm tra & Nhập vào CSDL MySQL</span>
        </button>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
