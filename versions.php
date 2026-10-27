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

$env_label      = $current_env === 'testing' ? 'Kiểm thử' : 'Vận hành';
$can_create_ver = PermissionService::currentUserCan('create_version');
$can_rollback   = PermissionService::currentUserCan('rollback_version');
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Lịch sử phiên bản cấu hình</h1>
    <p class="page-description">Lưu snapshot cấu hình tại mỗi thời điểm, so sánh sự khác biệt và khôi phục khi cần.</p>
  </div>
  <div class="page-actions">
    <?php if ($can_create_ver): ?>
    <button class="btn btn-primary" onclick="openModal('createVersionModal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
      Lưu phiên bản mới
    </button>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">Danh sách phiên bản &ndash; <?= render_env_badge($current_env) ?></h2>
    <span class="badge badge-neutral"><?= count($versions) ?> phiên bản</span>
  </div>
  <div class="table-responsive" style="border:none;border-radius:0">
    <table class="data-table">
      <thead>
        <tr>
          <th>Phiên bản</th>
          <th>Ghi chú</th>
          <th>Trạng thái</th>
          <th>Thời gian tạo</th>
          <th>Người tạo</th>
          <th style="text-align:center">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($versions as $v):
          $is_current = ($v['status'] ?? '') === 'Đang dùng';
        ?>
        <tr style="<?= $is_current ? 'background:var(--primary-light)' : '' ?>">
          <td>
            <div style="font-weight:700;font-size:.95rem;color:var(--primary)"><?= e($v['version_number'] ?? $v['tag'] ?? '') ?></div>
            <div><span class="font-mono" style="font-size:.72rem"><?= e($v['id']) ?></span></div>
          </td>
          <td>
            <div style="max-width:320px;font-size:.83rem;color:var(--text-secondary)"><?= e($v['reason'] ?? '—') ?></div>
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
          <td style="font-size:.82rem;color:var(--text-secondary)"><?= format_date($v['created_at'] ?? '') ?></td>
          <td>
            <span class="badge badge-neutral"><?= e($v['created_by'] ?? '—') ?></span>
          </td>
          <td style="text-align:center">
            <div class="action-icons" style="justify-content:center">
              <!-- So sánh -->
              <a href="version-compare.php?v=<?= urlencode($v['tag'] ?? $v['id']) ?>" class="action-icon-btn" title="Xem & so sánh snapshot">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
              </a>
              <!-- Khôi phục -->
              <?php if ($can_rollback && !$is_current): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Khôi phục về <?= e($v['version_number'] ?? $v['tag']) ?>?\nCấu hình hiện tại sẽ bị ghi đè.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="rollback">
                <input type="hidden" name="version_tag" value="<?= e($v['tag'] ?? $v['id']) ?>">
                <button type="submit" class="action-icon-btn success" title="Khôi phục phiên bản này">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3"/></svg>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($versions)): ?>
        <tr><td colspan="6"><div class="empty-state"><div class="empty-title">Chưa có phiên bản nào</div><div class="empty-desc">Nhấn "Lưu phiên bản mới" để tạo snapshot đầu tiên.</div></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
