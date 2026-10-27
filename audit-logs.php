<?php
/**
 * Hệ thống Quản lý Feature Flags
 * audit-logs.php – Nhật ký hoạt động có lọc (Giai đoạn 3)
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

$page_title  = 'Nhật ký hoạt động';
$breadcrumbs = [
    ['label' => 'Trang chủ',       'url' => 'dashboard.php'],
    ['label' => 'Nhật ký hoạt động','url' => ''],
];
require_once __DIR__ . '/includes/header.php';

$logs = array_reverse($_SESSION['ff_data']['audit_logs'] ?? []);

// Lọc theo query
$filter_actor  = trim($_GET['actor']  ?? '');
$filter_action = trim($_GET['action'] ?? '');

if ($filter_actor) {
    $logs = array_filter($logs, fn($l) => stripos($l['actor']??'', $filter_actor) !== false);
}
if ($filter_action) {
    $logs = array_filter($logs, fn($l) => stripos($l['action']??'', $filter_action) !== false);
}

// Icon cho từng loại hành động
function action_icon(string $action): string {
    $a = strtoupper($action);
    if (str_contains($a,'TẠO') || str_contains($a,'LƯU'))    return '<span style="color:var(--success)">📦</span>';
    if (str_contains($a,'BẬT') || str_contains($a,'TẮT'))    return '<span style="color:var(--primary)">🔄</span>';
    if (str_contains($a,'XÓA'))                              return '<span style="color:var(--danger)">🗑️</span>';
    if (str_contains($a,'KHÔI_PHỤC'))                        return '<span style="color:var(--warning)">↩️</span>';
    if (str_contains($a,'TỶ_LỆ') || str_contains($a,'SỬA')) return '<span style="color:var(--info)">✏️</span>';
    if (str_contains($a,'OVERRIDE'))                         return '<span style="color:#722ed1">🎯</span>';
    return '<span>📋</span>';
}

// Nhãn hành động
function action_badge(string $action): string {
    $a = strtoupper($action);
    $map = [
        'TẠO_CỜ_MỚI'         => ['badge-success',  'Tạo cờ mới'],
        'BẬT_TẮT_CỜ'         => ['badge-primary',  'Bật / Tắt'],
        'CẬP_NHẬT_TỶ_LỆ'     => ['badge-warning',  'Đổi tỷ lệ'],
        'SỬA_CẤU_HÌNH'        => ['badge-warning',  'Sửa cấu hình'],
        'CẬP_NHẬT_OVERRIDE'   => ['badge-primary',  'Cập nhật Override'],
        'PHÁT_HÀNH_PHIÊN_BẢN' => ['badge-success',  'Phát hành'],
        'LƯU_PHIÊN_BẢN'       => ['badge-neutral',  'Lưu phiên bản'],
        'KHÔI_PHỤC_PHIÊN_BẢN' => ['badge-warning',  'Khôi phục'],
        'XÓA_CỜ'              => ['badge-danger',   'Xóa cờ'],
    ];
    foreach ($map as $key => $val) {
        if (str_contains($a, $key)) return '<span class="badge ' . $val[0] . '">' . $val[1] . '</span>';
    }
    return '<span class="badge badge-neutral">' . e($action) . '</span>';
}
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Nhật ký hoạt động</h1>
    <p class="page-description">Toàn bộ lịch sử thay đổi cấu hình theo thứ tự thời gian mới nhất.</p>
  </div>
  <div class="page-actions">
    <a href="audit-logs.php" class="btn btn-secondary" title="Xóa bộ lọc">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3"/></svg>
      Làm mới
    </a>
  </div>
</div>

<!-- Bộ lọc -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-body">
    <form method="GET" action="audit-logs.php" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin-bottom:0;flex:1;min-width:160px">
        <label class="form-label">Lọc theo tài khoản</label>
        <input type="text" name="actor" class="form-control" placeholder="Nhập người thực hiện" value="<?= e($filter_actor) ?>">
      </div>
      <div class="form-group" style="margin-bottom:0;flex:1;min-width:160px">
        <label class="form-label">Lọc theo loại hành động</label>
        <input type="text" name="action" class="form-control" placeholder="Nhập hành động" value="<?= e($filter_action) ?>">
      </div>
      <div>
        <button type="submit" class="btn btn-primary">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
          Lọc
        </button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">Lịch sử ghi lại</h2>
    <span class="badge badge-neutral"><?= count($logs) ?> bản ghi</span>
  </div>
  <div class="table-responsive" style="border:none;border-radius:0">
    <table class="data-table">
      <thead>
        <tr>
          <th>Loại</th>
          <th>Đối tượng</th>
          <th>Giá trị cũ</th>
          <th>Giá trị mới</th>
          <th>Ghi chú</th>
          <th>Người thực hiện</th>
          <th>Phiên bản</th>
          <th>Thời gian</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr><td colspan="8"><div class="empty-state"><div class="empty-title">Không có nhật ký nào</div><div class="empty-desc">Thử bỏ bộ lọc hoặc thực hiện thao tác để tạo bản ghi.</div></div></td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $log): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:.4rem">
              <?= action_icon($log['action'] ?? '') ?>
              <?= action_badge($log['action'] ?? '') ?>
            </div>
          </td>
          <td><span class="font-mono" style="font-size:.78rem"><?= e($log['target'] ?? '—') ?></span></td>
          <td style="font-size:.78rem;color:var(--danger-text)"><?= e($log['old_value'] ?? '—') ?></td>
          <td style="font-size:.78rem;color:var(--success-text)"><?= e($log['new_value'] ?? '—') ?></td>
          <td style="font-size:.78rem;color:var(--text-secondary);max-width:200px"><?= e($log['reason'] ?? '—') ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:.4rem">
              <?= render_role_badge($log['role'] ?? 'viewer') ?>
              <span style="font-size:.78rem"><?= e($log['actor'] ?? '—') ?></span>
            </div>
          </td>
          <td><span class="badge badge-primary" style="font-size:.68rem"><?= e($log['version'] ?? '—') ?></span></td>
          <td style="font-size:.75rem;color:var(--text-secondary);white-space:nowrap"><?= format_date($log['created_at'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
