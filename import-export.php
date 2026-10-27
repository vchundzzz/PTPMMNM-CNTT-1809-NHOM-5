<?php
/**
 * Web Feature Flag Management System
 * import-export.php - Nhập và Xuất cấu hình hệ thống
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

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

$page_title = 'Nhập / Xuất cấu hình';
$breadcrumbs = [
    ['label' => 'Trang chủ', 'url' => 'dashboard.php'],
    ['label' => 'Nhập / Xuất cấu hình', 'url' => ''],
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Nhập / Xuất cấu hình hệ thống</h1>
    <p class="page-description">
      Sao lưu toàn bộ cấu hình dự án, môi trường, cờ tính năng và phiên bản.
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
        <span>Xuất dữ liệu cấu hình</span>
      </h2>
    </div>
    <div class="card-body">
      <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem; line-height: 1.5;">
        Tải xuống bản sao lưu đầy đủ gồm: 1 dự án, 2 môi trường, 10 tính năng, 3 nhóm người dùng và toàn bộ lịch sử các phiên bản.
      </p>

      <div style="background-color: var(--bg-body); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.25rem; font-size: 0.82rem;">
        <div style="margin-bottom: 0.35rem;">&bull; Định dạng file cấu hình</div>
        <div style="margin-bottom: 0.35rem;">&bull; Mã hóa: <strong>UTF-8</strong></div>
        <div>&bull; Quyền thực hiện: <strong>Tất cả vai trò</strong></div>
      </div>

      <a href="import-export.php?action=export" class="action-icon-btn action-view" style="width: 100%;" title="Tải xuống cấu hình" aria-label="Tải xuống cấu hình">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
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
        <span>Nhập dữ liệu cấu hình</span>
      </h2>
    </div>
    <div class="card-body">
      <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem; line-height: 1.5;">
        Tải lên tệp cấu hình để khôi phục. Hệ thống sẽ tự động kiểm tra cấu trúc, kiểu dữ liệu và tỷ lệ hợp lệ từ 0 đến 100%.
      </p>

      <div class="form-group">
        <label class="form-label">Chọn tệp cấu hình từ máy tính</label>
        <input type="file" accept=".json" class="form-control" <?= PermissionService::currentUserCan('import_config') ? '' : 'disabled' ?>>
      </div>

      <button type="button" class="action-icon-btn action-edit" style="width: 100%;" title="Kiểm tra và nhập cấu hình" aria-label="Kiểm tra và nhập cấu hình" onclick="alert('Bộ kiểm tra cấu hình sẽ được bổ sung sau.')" <?= PermissionService::currentUserCan('import_config') ? '' : 'disabled' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 12 2 2 4-4"/><path d="M20 6 9 17l-5-5"/></svg>
      </button>

      <?php if (!PermissionService::currentUserCan('import_config')): ?>
        <div style="margin-top: 0.75rem; font-size: 0.78rem; color: var(--danger);">
          * Lưu ý: Vai trò hiện tại không có quyền nhập file cấu hình.
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
