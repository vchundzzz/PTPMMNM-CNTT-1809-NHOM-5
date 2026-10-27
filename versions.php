<?php
/**
 * Hệ thống Quản lý Feature Flags
 * versions.php – Quản lý phiên bản cấu hình (Giai đoạn 3)
 * Lưu snapshot toàn bộ flags, so sánh và khôi phục
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/services/PermissionService.php';
require_once __DIR__ . '/services/VersionService.php';

$current_env = get_active_env();

/* ============================================================
   XỬ LÝ POST
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'CSRF không hợp lệ.'); header('Location: versions.php'); exit;
    }
    $action = $_POST['action'] ?? '';

    /* --- Lưu phiên bản mới --- */
    if ($action === 'create_version') {
        PermissionService::enforce('create_version');
        $reason = trim($_POST['reason'] ?? 'Không ghi chú');
        $result = VersionService::createSnapshot($current_env, $reason);
        set_flash('success', "Đã lưu phiên bản <strong>{$result['version_number']}</strong> thành công!");
        header('Location: versions.php'); exit;
    }

    /* --- Xóa phiên bản --- */
    if ($action === 'delete_version') {
        PermissionService::enforce('delete_version');
        $tag = trim($_POST['version_tag'] ?? '');
        if (VersionService::deleteSnapshot($tag)) {
            set_flash('success', 'Đã xóa phiên bản <strong>' . e($tag) . '</strong> khỏi lịch sử. Cấu hình cờ đang chạy không bị thay đổi.');
        } else {
            set_flash('error', 'Không tìm thấy phiên bản cần xóa.');
        }
        header('Location: versions.php'); exit;
    }

    /* --- Khôi phục phiên bản --- */
    if ($action === 'rollback') {
        PermissionService::enforce('rollback_version');
        $tag = trim($_POST['version_tag'] ?? '');
        if (VersionService::rollback($tag, $current_env)) {
            set_flash('success', "Đã khôi phục cấu hình về phiên bản <strong>$tag</strong>.");
        } else {
            set_flash('error', 'Không tìm thấy phiên bản cần khôi phục.');
        }
        header('Location: versions.php'); exit;
    }
}

/* ============================================================
   RENDER
   ============================================================ */
$page_title  = 'Lịch sử phiên bản';
$breadcrumbs = [
    ['label' => 'Trang chủ',        'url' => 'dashboard.php'],
    ['label' => 'Lịch sử phiên bản','url' => ''],
];
require_once __DIR__ . '/includes/header.php';

$versions    = array_values($_SESSION['ff_data']['versions'] ?? []);
// Mới nhất lên đầu
usort($versions, fn($a,$b) => strcmp($b['created_at'],$a['created_at']));
$versions_pagination = paginate_items($versions, (int)($_GET['page'] ?? 1), 10);
$page_versions = $versions_pagination['items'];

$env_label      = $current_env === 'testing' ? 'Kiểm thử' : 'Vận hành';
$can_create_ver = PermissionService::currentUserCan('create_version');
$can_delete_ver = PermissionService::currentUserCan('delete_version');
$can_rollback   = PermissionService::currentUserCan('rollback_version');
$current_count = count(array_filter($versions, fn($version) => ($version['status'] ?? '') === 'Đang dùng'));
$draft_count = count(array_filter($versions, fn($version) => in_array($version['status'] ?? '', ['Bản nháp', 'Draft'], true)));
$rollback_count = count(array_filter(
    $_SESSION['ff_data']['audit_logs'] ?? [],
    fn($log) => ($log['action'] ?? '') === 'KHÔI_PHỤC_PHIÊN_BẢN'
));
?>

<style>
.version-page { color:#1e293b; }
.version-page-header { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; }
.version-page-title { display:flex; align-items:flex-start; gap:.9rem; }
.version-title-icon { display:flex; align-items:center; justify-content:center; width:48px; height:48px; flex:none; border-radius:14px; background:#eaf2ff; color:#1677ff; }
.version-title-icon svg { width:27px; height:27px; }
.version-page-title h1 { margin:.1rem 0 .25rem; color:#1e293b; font-size:1.45rem; line-height:1.25; }
.version-page-title p { max-width:620px; margin:0; color:#718096; font-size:.82rem; line-height:1.55; }
.version-page-actions { display:flex; align-items:center; gap:.55rem; }
.version-page-actions .btn { min-height:38px; }
.version-refresh { display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border:1px solid #dbe3ef; border-radius:9px; background:#fff; color:#64748b; }
.version-refresh:hover { color:#1677ff; border-color:#91caff; }
.version-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:.8rem; margin-bottom:1rem; }
.version-stat { display:flex; align-items:center; gap:.8rem; min-height:88px; padding:.85rem 1rem; border:1px solid #e6edf7; border-radius:12px; background:#fff; box-shadow:0 2px 8px rgba(30,64,120,.035); }
.version-stat-icon { display:flex; align-items:center; justify-content:center; width:42px; height:42px; flex:none; border-radius:50%; }
.version-stat-icon svg { width:21px; height:21px; }
.version-stat-icon.blue { background:#e8f1ff; color:#1677ff; }
.version-stat-icon.green { background:#e8f8ee; color:#16a34a; }
.version-stat-icon.purple { background:#f3eaff; color:#9333ea; }
.version-stat-icon.orange { background:#fff1df; color:#f97316; }
.version-stat-value { color:#1e293b; font-size:1.25rem; font-weight:750; line-height:1.1; }
.version-stat-label { margin-top:.2rem; color:#334155; font-size:.76rem; font-weight:600; }
.version-stat-note { margin-top:.15rem; color:#8290a4; font-size:.68rem; }
.version-list-card { overflow:hidden; border:1px solid #e5eaf1; border-radius:12px; background:#fff; box-shadow:0 3px 12px rgba(30,64,120,.04); }
.version-list-heading { display:flex; align-items:center; justify-content:space-between; gap:.8rem; padding:.9rem 1.1rem; border-bottom:1px solid #e9edf3; }
.version-list-heading h2 { margin:0; color:#243247; font-size:.92rem; font-weight:700; }
.version-table-wrap { overflow-x:auto; }
.version-table { width:100%; min-width:760px; border-collapse:collapse; text-align:left; }
.version-table th { padding:.75rem .9rem; border-bottom:1px solid #e5eaf1; background:#f8fafc; color:#738197; font-size:.67rem; font-weight:700; letter-spacing:.045em; text-transform:uppercase; white-space:nowrap; }
.version-table td { padding:.8rem .9rem; border-bottom:1px solid #edf1f5; color:#56657a; font-size:.78rem; vertical-align:middle; }
.version-table tbody tr:last-child td { border-bottom:0; }
.version-table tbody tr:hover { background:#f8fbff; }
.version-table tbody tr.current { background:#e8f4ff; }
.version-number { color:#1677ff; font-size:.87rem; font-weight:750; }
.version-id { display:inline-block; margin-top:.2rem; padding:.08rem .32rem; border:1px solid #dce5ef; border-radius:5px; background:#f8fafc; color:#66758b; font-family:ui-monospace,monospace; font-size:.66rem; }
.version-reason { max-width:370px; color:#65748a; line-height:1.5; }
.version-env { display:inline-flex; padding:.2rem .55rem; border:1px solid #bfdbfe; border-radius:999px; background:#eff6ff; color:#2563eb; font-size:.68rem; font-weight:650; white-space:nowrap; }
.version-env.testing { border-color:#fde68a; background:#fffbeb; color:#b45309; }
.version-user { display:inline-flex; padding:.2rem .55rem; border:1px solid #e2e8f0; border-radius:999px; background:#f8fafc; color:#64748b; font-size:.68rem; }
.version-actions { display:flex; justify-content:center; gap:.35rem; }
.version-action { display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border:none; border-radius:8px; background:transparent; color:#1677ff; cursor:pointer; }
.version-action svg { width:17px; height:17px; }
.version-action:hover { background:transparent; }
.version-action.restore { background:transparent; color:#16a34a; }
.version-action.restore:hover { background:transparent; }
.version-action.delete { background:transparent; color:#dc2626; }
.version-action.delete:hover { background:transparent; }
.version-empty { padding:2.5rem 1rem; text-align:center; }
.version-empty strong { display:block; margin-bottom:.3rem; color:#334155; }
.version-empty span { color:#8290a4; font-size:.8rem; }
@media (max-width:900px) { .version-summary { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media (max-width:600px) {
  .version-page-header { align-items:flex-start; flex-direction:column; }
  .version-page-actions { width:100%; }
  .version-page-actions .btn { flex:1; }
  .version-summary { gap:.55rem; }
  .version-stat { min-height:78px; padding:.65rem; gap:.55rem; }
  .version-stat-icon { width:36px; height:36px; }
  .version-stat-note { display:none; }
}
</style>

<div class="version-page">
<div class="page-header version-page-header">
  <div class="version-page-title">
    <div class="version-title-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 2.64-6.36L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/></svg>
    </div>
    <div>
      <h1 class="page-title">Lịch sử phiên bản cấu hình</h1>
      <p class="page-description">Theo dõi các snapshot cấu hình tính năng của ứng dụng. Xem chi tiết, so sánh phiên bản hoặc khôi phục khi cần.</p>
    </div>
  </div>
  <div class="version-page-actions">
    <?php if ($can_create_ver): ?>
    <button class="btn btn-primary" type="button" onclick="openModal('createVersionModal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>
      Tạo phiên bản mới
    </button>
    <?php endif; ?>
    <a class="version-refresh" href="versions.php" title="Làm mới danh sách" aria-label="Làm mới danh sách">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7v5h-5"/><path d="M4 17v-5h5"/><path d="M5.6 9a7 7 0 0 1 11.55-2.6L20 12M4 12l2.85 5.6A7 7 0 0 0 18.4 15"/></svg>
    </a>
  </div>
</div>

<div class="version-summary" aria-label="Thống kê phiên bản">
  <div class="version-stat">
    <div class="version-stat-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/></svg></div>
    <div><div class="version-stat-value"><?= count($versions) ?></div><div class="version-stat-label">Tổng phiên bản</div><div class="version-stat-note">Snapshot cấu hình đã lưu</div></div>
  </div>
  <div class="version-stat">
    <div class="version-stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/><circle cx="12" cy="12" r="10"/></svg></div>
    <div><div class="version-stat-value"><?= $current_count ?></div><div class="version-stat-label">Đang áp dụng</div><div class="version-stat-note">Phiên bản hiện hành</div></div>
  </div>
  <div class="version-stat">
    <div class="version-stat-icon purple"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></div>
    <div><div class="version-stat-value"><?= $draft_count ?></div><div class="version-stat-label">Bản nháp</div><div class="version-stat-note">Phiên bản chưa phát hành</div></div>
  </div>
  <div class="version-stat">
    <div class="version-stat-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 2.64-6.36L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/></svg></div>
    <div><div class="version-stat-value"><?= $rollback_count ?></div><div class="version-stat-label">Lần khôi phục</div><div class="version-stat-note">Tổng lượt rollback đã ghi nhận</div></div>
  </div>
</div>

<section class="version-list-card" aria-labelledby="versionListTitle">
  <div class="version-list-heading">
    <h2 id="versionListTitle">Danh sách phiên bản &mdash; <?= render_env_badge($current_env) ?></h2>
    <span class="badge badge-neutral"><?= count($versions) ?> phiên bản</span>
  </div>
  <div class="version-table-wrap">
    <table class="version-table">
      <thead>
        <tr>
          <th>Phiên bản</th>
          <th>Ghi chú</th>
          <th>Trạng thái</th>
          <th>Môi trường</th>
          <th>Người tạo</th>
          <th>Thời gian tạo</th>
          <th style="text-align:center">Chức năng</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($page_versions as $v):
          $is_current = ($v['status'] ?? '') === 'Đang dùng';
          $version_env = $v['environment'] ?? $current_env;
        ?>
        <tr class="<?= $is_current ? 'current' : '' ?>">
          <td>
            <div class="version-number"><?= e($v['version_number'] ?? $v['tag'] ?? '') ?></div>
            <span class="version-id"><?= e($v['id'] ?? '') ?></span>
          </td>
          <td>
            <div class="version-reason"><?= e($v['reason'] ?? '—') ?></div>
          </td>
          <td>
            <?php if ($is_current): ?>
              <span class="badge badge-success"><span class="badge-dot" style="background:var(--success)"></span>Đang dùng</span>
            <?php elseif (($v['status'] ?? '') === 'Lưu trữ'): ?>
              <span class="badge badge-neutral">Lưu trữ</span>
            <?php else: ?>
              <?= render_badge($v['status'] ?? '') ?>
            <?php endif; ?>
          </td>
          <td>
            <span class="version-env <?= $version_env === 'testing' ? 'testing' : '' ?>"><?= e($version_env === 'testing' ? 'Kiểm thử' : 'Vận hành') ?></span>
          </td>
          <td><span class="version-user"><?= e($v['created_by'] ?? '—') ?></span></td>
          <td><?= format_date($v['created_at'] ?? '') ?></td>
          <td style="text-align:center">
            <div class="version-actions">
              <a href="version-compare.php?v=<?= urlencode($v['tag'] ?? $v['id']) ?>" class="version-action" title="Xem chi tiết" aria-label="Xem chi tiết">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              </a>
              <?php if ($can_rollback && !$is_current): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Khôi phục về <?= e($v['version_number'] ?? $v['tag']) ?> trên môi trường <?= e($env_label) ?>?\nCấu hình hiện tại sẽ bị ghi đè.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="rollback">
                <input type="hidden" name="version_tag" value="<?= e($v['tag'] ?? $v['id']) ?>">
                <button type="submit" class="version-action restore" title="Khôi phục phiên bản" aria-label="Khôi phục phiên bản">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 2.64-6.36L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/></svg>
                </button>
              </form>
              <?php endif; ?>
              <?php if ($can_delete_ver): ?>
              <?php
                $version_tag = (string)($v['tag'] ?? $v['id']);
                $delete_confirmation = 'Xác nhận xóa phiên bản ' . ($v['version_number'] ?? $version_tag) . '? Thao tác này không thể hoàn tác.';
                if ($is_current) {
                    $delete_confirmation .= ' Đây là phiên bản đang dùng; việc xóa chỉ gỡ snapshot khỏi lịch sử và không thay đổi cấu hình cờ đang chạy.';
                }
              ?>
              <form method="POST" style="display:inline" onsubmit="return confirm(<?= e(json_encode($delete_confirmation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>)">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_version">
                <input type="hidden" name="version_tag" value="<?= e($version_tag) ?>">
                <button type="submit" class="version-action delete" title="Xóa" aria-label="Xóa">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($versions)): ?>
        <tr><td colspan="7"><div class="version-empty"><strong>Chưa có phiên bản nào</strong><span>Nhấn “Tạo phiên bản mới” để lưu snapshot đầu tiên.</span></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?= render_pagination($versions_pagination['total'], $versions_pagination['page'], 10, 'versions.php', 'phiên bản') ?>
</section>

<!-- Modal tạo phiên bản -->
<div class="modal-backdrop" id="createVersionModal">
  <div class="modal-dialog">
    <form method="POST" action="versions.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_version">
      <div class="modal-header">
        <h3 class="modal-title">Lưu phiên bản cấu hình mới</h3>
        <button type="button" class="modal-close-btn" data-modal-close>&times;</button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          Hệ thống sẽ chụp snapshot <strong>toàn bộ <?= count($_SESSION['ff_data']['flags']) ?> cờ tính năng</strong>
          trên môi trường <strong><?= e($env_label) ?></strong> tại thời điểm này.
        </div>
        <div class="form-group">
          <label class="form-label">Ghi chú / lý do tạo phiên bản</label>
          <input type="text" name="reason" class="form-control" placeholder="Nhập lý do phát hành phiên bản" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Hủy</button>
        <button type="submit" class="btn btn-primary">Lưu phiên bản</button>
      </div>
    </form>
  </div>
</div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
