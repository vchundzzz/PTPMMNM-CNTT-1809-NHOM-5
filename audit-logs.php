<?php
/**
 * Hệ thống Quản lý Feature Flags
 * audit-logs.php – Nhật ký hoạt động có tìm kiếm, lọc và phân trang
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

$page_title  = 'Nhật ký hoạt động';
$breadcrumbs = [
    ['label' => 'Trang chủ',        'url' => 'dashboard.php'],
    ['label' => 'Nhật ký hoạt động','url' => ''],
];
require_once __DIR__ . '/includes/header.php';

$all_logs = array_reverse($_SESSION['ff_data']['audit_logs'] ?? []);
$logs = $all_logs;
$per_page = 10;

$search = trim($_GET['q'] ?? $_GET['actor'] ?? '');
$filter_action = trim($_GET['action'] ?? '');
$filter_version = trim($_GET['version'] ?? '');
$date_from = trim($_GET['from'] ?? '');
$date_to = trim($_GET['to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

foreach (['from' => &$date_from, 'to' => &$date_to] as $date_key => &$date_value) {
    if ($date_value !== '') {
        $parsed_date = DateTime::createFromFormat('!Y-m-d', $date_value);
        if (!$parsed_date || $parsed_date->format('Y-m-d') !== $date_value) {
            $date_value = '';
        }
    }
}
unset($date_value, $date_key);

$action_labels = [
    'BẬT_TẮT_CỜ' => 'Bật / Tắt',
    'CẬP_NHẬT_TỶ_LỆ' => 'Sửa cấu hình',
    'SỬA_CẤU_HÌNH' => 'Sửa cấu hình',
    'CẬP_NHẬT_OVERRIDE' => 'Sửa cấu hình',
    'KHÔI_PHỤC_PHIÊN_BẢN' => 'Khôi phục',
    'KHÔI_PHỤC_CẤU_HÌNH' => 'Khôi phục',
    'PHÁT_HÀNH_PHIÊN_BẢN' => 'Phát hành',
    'LƯU_PHIÊN_BẢN' => 'Lưu phiên bản',
    'TẠO_CỜ_MỚI' => 'Tạo cờ mới',
    'XÓA_CỜ' => 'Xóa',
    'XÓA_PHIÊN_BẢN' => 'Xóa phiên bản',
];

$action_label = static function (string $action) use ($action_labels): string {
    return $action_labels[$action] ?? ucwords(strtolower(str_replace(['_', '-'], ' ', $action)));
};

$action_style = static function (string $action): string {
    $action = strtoupper($action);
    if (str_contains($action, 'KHÔI_PHỤC')) return 'restore';
    if (str_contains($action, 'BẬT_TẮT') || str_contains($action, 'BẬT') || str_contains($action, 'TẮT')) return 'toggle';
    if (str_contains($action, 'XÓA')) return 'delete';
    if (str_contains($action, 'SỬA') || str_contains($action, 'CẬP_NHẬT') || str_contains($action, 'OVERRIDE')) return 'edit';
    return 'other';
};

$versions = array_values(array_unique(array_filter(array_map(
    static fn($log) => (string)($log['version'] ?? ''),
    $all_logs
))));

$logs = array_values(array_filter($logs, static function (array $log) use (
    $search,
    $filter_action,
    $filter_version,
    $date_from,
    $date_to
): bool {
    if ($search !== '') {
        $searchable = implode(' ', [
            (string)($log['target'] ?? ''),
            (string)($log['actor'] ?? ''),
            (string)($log['action'] ?? ''),
            (string)($log['old_value'] ?? ''),
            (string)($log['new_value'] ?? ''),
            (string)($log['reason'] ?? ''),
        ]);
        if (stripos($searchable, $search) === false) return false;
    }
    if ($filter_action !== '' && stripos((string)($log['action'] ?? ''), $filter_action) === false) return false;
    if ($filter_version !== '' && (string)($log['version'] ?? '') !== $filter_version) return false;

    $created_date = substr((string)($log['created_at'] ?? ''), 0, 10);
    if ($date_from !== '' && ($created_date === '' || $created_date < $date_from)) return false;
    if ($date_to !== '' && ($created_date === '' || $created_date > $date_to)) return false;
    return true;
}));

$filtered_count = count($logs);
$total_count = $filtered_count;
$toggle_count = 0;
$edit_count = 0;
$restore_count = 0;
foreach ($logs as $log) {
    $style = $action_style((string)($log['action'] ?? ''));
    if ($style === 'toggle') $toggle_count++;
    if ($style === 'edit') $edit_count++;
    if ($style === 'restore') $restore_count++;
}
$other_count = $total_count - $toggle_count - $edit_count - $restore_count;
$page_count = max(1, (int)ceil($filtered_count / $per_page));
$page = min($page, $page_count);
$page_logs = array_slice($logs, ($page - 1) * $per_page, $per_page);
$range_start = $filtered_count > 0 ? (($page - 1) * $per_page) + 1 : 0;
$range_end = min($page * $per_page, $filtered_count);

$query_without_page = $_GET;
unset($query_without_page['page']);
$page_url = static function (int $target_page) use ($query_without_page): string {
    return 'audit-logs.php?' . http_build_query($query_without_page + ['page' => $target_page]);
};
?>

<style>
.audit-page { color:#1e293b; }
.audit-page-header { align-items:center; margin-bottom:1rem; }
.audit-page-title { display:flex; align-items:flex-start; gap:.85rem; }
.audit-title-icon { display:flex; align-items:center; justify-content:center; width:48px; height:48px; flex:none; border-radius:14px; background:#e8f1ff; color:#1677ff; }
.audit-title-icon svg { width:27px; height:27px; }
.audit-page-title h1 { margin:.1rem 0 .25rem; color:#172b4d; font-size:1.45rem; line-height:1.25; }
.audit-page-title p { max-width:540px; margin:0; color:#7b8ba3; font-size:.78rem; line-height:1.5; }
.audit-total-pill { display:inline-flex; align-items:center; gap:.4rem; padding:.45rem .7rem; border:1px solid #e7edf5; border-radius:999px; background:#fff; color:#60728d; font-size:.72rem; white-space:nowrap; box-shadow:0 2px 6px rgba(30,64,120,.04); }
.audit-summary { display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:.7rem; margin-bottom:.7rem; padding:.7rem; border:1px solid #e7edf5; border-radius:12px; background:#fff; }
.audit-stat { display:flex; align-items:center; gap:.7rem; min-height:72px; padding:.65rem .75rem; border:1px solid #dce8f8; border-radius:9px; background:linear-gradient(120deg,#f8fbff,#fff); }
.audit-stat:nth-child(2) { border-color:#d8f1e2; background:linear-gradient(120deg,#f3fbf6,#fff); }
.audit-stat:nth-child(3) { border-color:#fde9c8; background:linear-gradient(120deg,#fffaf0,#fff); }
.audit-stat:nth-child(4) { border-color:#e9ddff; background:linear-gradient(120deg,#faf7ff,#fff); }
.audit-stat:nth-child(5) { border-color:#d8edf2; background:linear-gradient(120deg,#f3fbfc,#fff); }
.audit-stat-icon { display:flex; align-items:center; justify-content:center; width:38px; height:38px; flex:none; border-radius:13px; background:#dcecff; color:#1677ff; }
.audit-stat:nth-child(2) .audit-stat-icon { background:#bdebc9; color:#159447; }
.audit-stat:nth-child(3) .audit-stat-icon { background:#ffe0a6; color:#e77800; }
.audit-stat:nth-child(4) .audit-stat-icon { background:#ddcaff; color:#6d28d9; }
.audit-stat:nth-child(5) .audit-stat-icon { background:#c9edf2; color:#0e7490; }
.audit-stat-icon svg { width:19px; height:19px; }
.audit-stat-label { color:#53657f; font-size:.66rem; }
.audit-stat-value { margin-top:.08rem; color:#1e3558; font-size:1.1rem; font-weight:750; line-height:1.1; }
.audit-toolbar { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; margin-bottom:.55rem; padding:.65rem; border:1px solid #e7edf5; border-radius:10px; background:#fff; }
.audit-control { min-width:115px; height:34px; padding:0 .6rem; border:1px solid #dce5f0; border-radius:7px; background:#fff; color:#42536b; font:inherit; font-size:.68rem; }
.audit-search-wrap { position:relative; flex:1 1 270px; min-width:200px; }
.audit-search-wrap svg { position:absolute; top:50%; left:.65rem; width:14px; height:14px; color:#8392a8; transform:translateY(-50%); pointer-events:none; }
.audit-search { width:100%; padding-left:1.9rem; }
.audit-date { flex:0 1 150px; }
.audit-filter-submit { height:34px; padding:.35rem .7rem; font-size:.68rem; }
.audit-clear { display:inline-flex; align-items:center; gap:.3rem; padding:.35rem .55rem; color:#66768c; font-size:.68rem; white-space:nowrap; }
.audit-list { overflow:hidden; border:1px solid #e5eaf1; border-radius:10px; background:#fff; box-shadow:0 3px 12px rgba(30,64,120,.035); }
.audit-table-wrap { overflow-x:auto; }
.audit-table { width:100%; min-width:900px; border-collapse:collapse; text-align:left; }
.audit-table th { padding:.66rem .65rem; border-bottom:1px solid #e5eaf1; background:#f6f9fd; color:#71819a; font-size:.61rem; font-weight:750; letter-spacing:.035em; text-transform:uppercase; white-space:nowrap; }
.audit-table td { padding:.48rem .65rem; border-bottom:1px solid #edf1f6; color:#40526d; font-size:.67rem; vertical-align:middle; }
.audit-table tbody tr:nth-child(even) { background:#fbfcfe; }
.audit-table tbody tr:hover { background:#f8fbff; }
.audit-table tbody tr:last-child td { border-bottom:0; }
.audit-action { display:inline-flex; align-items:center; gap:.33rem; padding:.23rem .48rem; border:1px solid #bfdbfe; border-radius:999px; background:#eff6ff; color:#2563eb; font-size:.63rem; font-weight:650; white-space:nowrap; }
.audit-action svg { width:12px; height:12px; }
.audit-action.edit { border-color:#fed7aa; background:#fff7ed; color:#c2410c; }
.audit-action.restore { border-color:#ddd6fe; background:#f5f3ff; color:#6d28d9; }
.audit-action.delete { border-color:#fecaca; background:#fff1f2; color:#dc2626; }
.audit-target { display:inline-block; padding:.14rem .3rem; border:1px solid #dfe7f1; border-radius:4px; background:#f8fafc; color:#42536b; font-family:ui-monospace,monospace; font-size:.62rem; white-space:nowrap; }
.audit-value-old { color:#dc2626; }
.audit-value-new { color:#15803d; }
.audit-value-pill { display:inline-block; max-width:180px; overflow:hidden; padding:.2rem .42rem; border:1px solid #e7edf3; border-radius:6px; background:#f8fafc; text-overflow:ellipsis; vertical-align:middle; white-space:nowrap; }
.audit-value-pill.old { border-color:#fee2e2; background:#fff7f7; color:#c2413b; }
.audit-value-pill.new { border-color:#dcfce7; background:#f4fcf6; color:#15803d; }
.audit-note { max-width:185px; color:#708099; line-height:1.4; }
.audit-user { display:flex; align-items:center; gap:.35rem; white-space:nowrap; }
.audit-avatar { display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; border-radius:50%; background:linear-gradient(135deg,#60a5fa,#8b5cf6); color:#fff; font-size:.56rem; font-weight:700; }
.audit-version { display:inline-flex; padding:.16rem .4rem; border:1px solid #cfe2ff; border-radius:999px; background:#eff6ff; color:#2563eb; font-size:.61rem; font-weight:650; }
.audit-time { white-space:nowrap; color:#71819a; font-size:.63rem; }
.audit-footer { display:flex; align-items:center; justify-content:space-between; gap:.8rem; padding:.55rem .7rem; border-top:1px solid #edf1f6; color:#71819a; font-size:.65rem; }
.audit-pagination { display:flex; align-items:center; gap:.25rem; }
.audit-page-link { display:inline-flex; align-items:center; justify-content:center; min-width:25px; height:25px; padding:0 .35rem; border:1px solid #e1e8f1; border-radius:6px; background:#fff; color:#64748b; font-size:.65rem; text-decoration:none; }
.audit-page-link:hover { border-color:#93c5fd; color:#1677ff; }
.audit-page-link.active { border-color:#1677ff; background:#1677ff; color:#fff; }
.audit-page-link.disabled { color:#b8c2d0; pointer-events:none; }
.audit-empty { padding:2.2rem 1rem; text-align:center; color:#7b8ba3; }
.audit-empty strong { display:block; margin-bottom:.25rem; color:#334155; }
@media (max-width:1000px) { .audit-summary { grid-template-columns:repeat(3,minmax(0,1fr)); } }
@media (max-width:700px) { .audit-summary { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media (max-width:600px) {
  .audit-page-header { align-items:flex-start; }
  .audit-page-header .page-actions { display:none; }
  .audit-summary { gap:.45rem; padding:.45rem; }
  .audit-stat { min-height:63px; padding:.45rem; gap:.45rem; }
  .audit-stat-icon { width:32px; height:32px; border-radius:10px; }
  .audit-stat-label { font-size:.6rem; }
  .audit-stat-value { font-size:.95rem; }
  .audit-date { flex:1 1 130px; }
  .audit-footer { align-items:flex-start; flex-direction:column; }
}
</style>

<div class="audit-page">
  <div class="page-header audit-page-header">
    <div class="audit-page-title">
      <div class="audit-title-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 2.64-6.36L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/></svg>
      </div>
      <div>
        <h1 class="page-title">Lịch sử hoạt động</h1>
        <p class="page-description">Theo dõi các thay đổi cấu hình, người thực hiện và thời điểm thao tác để kiểm tra, truy vết khi cần.</p>
      </div>
    </div>
    <div class="page-actions">
      <span class="audit-total-pill">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg>
        Tổng số bản ghi: <?= $total_count ?>
      </span>
    </div>
  </div>

  <div class="audit-summary" aria-label="Thống kê nhật ký hoạt động">
    <div class="audit-stat">
      <div class="audit-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg></div>
      <div><div class="audit-stat-label">Bản ghi theo bộ lọc</div><div class="audit-stat-value"><?= $total_count ?></div></div>
    </div>
    <div class="audit-stat">
      <div class="audit-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v10"/><path d="M5.6 5.6a9 9 0 1 0 12.8 0"/></svg></div>
      <div><div class="audit-stat-label">Bật / Tắt</div><div class="audit-stat-value"><?= $toggle_count ?></div></div>
    </div>
    <div class="audit-stat">
      <div class="audit-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m16 4 4 4L9 19l-5 1 1-5L16 4Z"/><path d="m14 6 4 4"/></svg></div>
      <div><div class="audit-stat-label">Sửa cấu hình</div><div class="audit-stat-value"><?= $edit_count ?></div></div>
    </div>
    <div class="audit-stat">
      <div class="audit-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 2.64-6.36L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/></svg></div>
      <div><div class="audit-stat-label">Khôi phục</div><div class="audit-stat-value"><?= $restore_count ?></div></div>
    </div>
    <div class="audit-stat">
      <div class="audit-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg></div>
      <div><div class="audit-stat-label">Thao tác khác</div><div class="audit-stat-value"><?= $other_count ?></div></div>
    </div>
  </div>

  <form method="GET" action="audit-logs.php" class="audit-toolbar">
    <label class="audit-search-wrap">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input class="audit-control audit-search" type="search" name="q" value="<?= e($search) ?>" placeholder="Tìm theo tên tính năng, đối tượng hoặc người thực hiện...">
    </label>
    <select class="audit-control" name="action" aria-label="Lọc theo thao tác">
      <option value="">Tất cả thao tác</option>
      <?php foreach (array_values(array_unique(array_filter(array_map(static fn($log) => (string)($log['action'] ?? ''), $all_logs)))) as $available_action): ?>
        <option value="<?= e($available_action) ?>" <?= $filter_action === $available_action ? 'selected' : '' ?>><?= e($action_label($available_action)) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="audit-control" name="version" aria-label="Lọc theo phiên bản">
      <option value="">Tất cả phiên bản</option>
      <?php foreach ($versions as $available_version): ?>
        <option value="<?= e($available_version) ?>" <?= $filter_version === $available_version ? 'selected' : '' ?>><?= e($available_version) ?></option>
      <?php endforeach; ?>
    </select>
    <input class="audit-control audit-date" type="date" name="from" value="<?= e($date_from) ?>" aria-label="Từ ngày">
    <input class="audit-control audit-date" type="date" name="to" value="<?= e($date_to) ?>" aria-label="Đến ngày">
    <button class="btn btn-primary audit-filter-submit" type="submit">Lọc</button>
    <a class="audit-clear" href="audit-logs.php" title="Xóa bộ lọc">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg>
      Xóa bộ lọc
    </a>
  </form>

  <section class="audit-list" aria-label="Danh sách nhật ký hoạt động">
    <div class="audit-table-wrap">
      <table class="audit-table">
        <thead>
          <tr>
            <th>Loại thao tác</th>
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
          <?php if (empty($page_logs)): ?>
            <tr><td colspan="8"><div class="audit-empty"><strong>Không tìm thấy nhật ký phù hợp</strong>Hãy thử thay đổi hoặc xóa bớt bộ lọc.</div></td></tr>
          <?php endif; ?>
          <?php foreach ($page_logs as $log):
            $action = (string)($log['action'] ?? '');
            $style = $action_style($action);
            $actor = (string)($log['actor'] ?? '—');
            $created_at = (string)($log['created_at'] ?? '');
          ?>
          <tr>
            <td>
              <span class="audit-action <?= e($style) ?>">
                <?php if ($style === 'toggle'): ?>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v10"/><path d="M5.6 5.6a9 9 0 1 0 12.8 0"/></svg>
                <?php elseif ($style === 'edit'): ?>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m16 4 4 4L9 19l-5 1 1-5L16 4Z"/></svg>
                <?php elseif ($style === 'restore'): ?>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 2.64-6.36L3 8"/><path d="M3 3v5h5"/></svg>
                <?php else: ?>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/></svg>
                <?php endif; ?>
                <?= e($action_label($action)) ?>
              </span>
            </td>
            <td><span class="audit-target"><?= e((string)($log['target'] ?? '—')) ?></span></td>
            <td class="audit-value-old"><span class="audit-value-pill old" title="<?= e((string)($log['old_value'] ?? '—')) ?>"><?= e((string)($log['old_value'] ?? '—')) ?></span></td>
            <td class="audit-value-new"><span class="audit-value-pill new" title="<?= e((string)($log['new_value'] ?? '—')) ?>"><?= e((string)($log['new_value'] ?? '—')) ?></span></td>
            <td><div class="audit-note"><?= e((string)($log['reason'] ?? '—')) ?></div></td>
            <td>
              <div class="audit-user">
                <?= render_role_badge((string)($log['role'] ?? 'viewer')) ?>
                <span class="audit-avatar"><?= e(strtoupper(substr($actor, 0, 1))) ?></span>
                <?= e($actor) ?>
              </div>
            </td>
            <td><span class="audit-version"><?= e((string)($log['version'] ?? '—')) ?></span></td>
            <td class="audit-time"><?= format_date($created_at) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="audit-footer">
      <span>Hiển thị <?= $range_start ?> - <?= $range_end ?> trong tổng số <?= $filtered_count ?> bản ghi</span>
      <?php if ($page_count > 1): ?>
      <nav class="audit-pagination" aria-label="Phân trang nhật ký">
        <a class="audit-page-link <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= e($page_url(max(1, $page - 1))) ?>" aria-label="Trang trước">&lsaquo;</a>
        <?php
          $page_numbers = [1, $page_count];
          for ($number = max(1, $page - 2); $number <= min($page_count, $page + 2); $number++) {
              $page_numbers[] = $number;
          }
          $page_numbers = array_values(array_unique($page_numbers));
          sort($page_numbers);
          $previous_number = 0;
          foreach ($page_numbers as $number):
              if ($previous_number > 0 && $number > $previous_number + 1):
        ?>
          <span class="audit-page-link disabled" aria-hidden="true">…</span>
        <?php endif; ?>
          <a class="audit-page-link <?= $number === $page ? 'active' : '' ?>" href="<?= e($page_url($number)) ?>" <?= $number === $page ? 'aria-current="page"' : '' ?>><?= $number ?></a>
        <?php
              $previous_number = $number;
          endforeach;
        ?>
        <a class="audit-page-link <?= $page >= $page_count ? 'disabled' : '' ?>" href="<?= e($page_url(min($page_count, $page + 1))) ?>" aria-label="Trang sau">&rsaquo;</a>
      </nav>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
