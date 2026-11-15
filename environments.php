<?php
/**
 * Web Feature Flag Management System
 * environments.php - Quản lý môi trường triển khai từ Database
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/services/DatabaseService.php';
require_once __DIR__ . '/services/PermissionService.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    PermissionService::enforce('manage_environments');
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Mã bảo mật CSRF không hợp lệ, vui lòng thử lại.');
        header('Location: environments.php');
        exit;
    }

    $action = (string)($_POST['action'] ?? '');
    $actor = (string)(get_authenticated_user()['username'] ?? 'admin');

    if ($action === 'create') {
        $result = DatabaseService::createEnvironment([
            'key' => $_POST['key'] ?? '',
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'badge_color' => $_POST['badge_color'] ?? 'primary',
            'sort_order' => $_POST['sort_order'] ?? 0,
            'updated_by' => $actor,
        ]);
        if (!$result['success']) {
            set_flash('error', $result['error'] ?? 'Không thể tạo môi trường.');
        } else {
            init_session_data(true);
            append_audit_log('TẠO_MÔI_TRƯỜNG', $result['key'], '-', 'Đã tạo', 'Tạo môi trường từ giao diện quản trị');
            set_flash('success', 'Đã tạo môi trường và khởi tạo cấu hình cho toàn bộ cờ tính năng.');
        }
        header('Location: environments.php');
        exit;
    }

    if ($action === 'update') {
        $key = trim((string)($_POST['environment_key'] ?? ''));
        $old = $_SESSION['ff_data']['environments'][$key] ?? [];
        $updated = DatabaseService::updateEnvironment($key, [
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'badge_color' => $_POST['badge_color'] ?? 'primary',
            'sort_order' => $_POST['sort_order'] ?? 0,
            'status' => $_POST['status'] ?? 'active',
        ]);
        if (!$updated) {
            set_flash('error', 'Không thể cập nhật môi trường trong cơ sở dữ liệu.');
        } else {
            init_session_data(true);
            append_audit_log('CẬP_NHẬT_MÔI_TRƯỜNG', $key, (string)($old['name'] ?? $key), trim((string)($_POST['name'] ?? $key)), 'Cập nhật từ giao diện quản trị');
            set_flash('success', 'Đã cập nhật môi trường.');
        }
        header('Location: environments.php');
        exit;
    }

    if ($action === 'delete') {
        PermissionService::enforce('delete_environment');
        $key = trim((string)($_POST['environment_key'] ?? ''));
        $result = DatabaseService::deleteEnvironment($key);
        if (!$result['success']) {
            set_flash('error', $result['error'] ?? 'Không thể xóa môi trường.');
        } else {
            init_session_data(true);
            append_audit_log('XÓA_MÔI_TRƯỜNG', $key, 'Tồn tại', 'Đã xóa', 'Xóa từ giao diện quản trị');
            set_flash('success', 'Đã xóa môi trường và các cấu hình cờ liên quan.');
        }
        header('Location: environments.php');
        exit;
    }
}

$page_title = 'Quản lý môi trường';
$breadcrumbs = [
    ['label' => 'Trang chủ', 'url' => 'dashboard.php'],
    ['label' => 'Môi trường', 'url' => ''],
];

require_once __DIR__ . '/includes/header.php';

$environments = $_SESSION['ff_data']['environments'] ?? [];
$flags = $_SESSION['ff_data']['flags'] ?? [];
$current_env = get_active_env();
$can_manage = PermissionService::currentUserCan('manage_environments');
$can_delete_environment = PermissionService::currentUserCan('delete_environment');
$badge_colors = [
    'primary' => 'Xanh dương',
    'success' => 'Xanh lá',
    'warning' => 'Vàng',
    'danger' => 'Đỏ',
    'info' => 'Xanh nhạt',
    'neutral' => 'Trung tính',
];
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Môi trường triển khai</h1>
    <p class="page-description">Quản lý không giới hạn môi trường và cấu hình Feature Flag độc lập trên từng môi trường.</p>
  </div>
  <?php if ($can_manage): ?>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" onclick="openModal('createEnvironmentModal')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
      </svg>
      Tạo môi trường
    </button>
  </div>
  <?php endif; ?>
</div>

<?php if ($can_manage): ?>
<div class="modal-backdrop" id="createEnvironmentModal">
  <div class="modal-dialog" style="max-width:620px;">
    <form method="POST" action="environments.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="modal-header">
        <h3 class="modal-title">Tạo môi trường mới</h3>
        <button type="button" class="modal-close-btn" data-modal-close>&times;</button>
      </div>
      <div class="modal-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
          <div class="form-group">
            <label class="form-label">Mã môi trường *</label>
            <input class="form-control font-mono" name="key" pattern="[a-z][a-z0-9_-]{1,49}" placeholder="Nhập mã môi trường" required>
          </div>
          <div class="form-group">
            <label class="form-label">Tên hiển thị *</label>
            <input class="form-control" name="name" placeholder="Nhập tên môi trường" required>
          </div>
          <div class="form-group">
            <label class="form-label">Màu nhãn</label>
            <select class="form-select" name="badge_color">
              <?php foreach ($badge_colors as $color => $label): ?><option value="<?= e($color) ?>"><?= e($label) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Thứ tự hiển thị</label>
            <input class="form-control" type="number" name="sort_order" min="0" placeholder="Nhập thứ tự hiển thị">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Mô tả</label>
          <textarea class="form-control" name="description" rows="3" placeholder="Nhập mô tả môi trường"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Hủy</button>
        <button class="btn btn-primary" type="submit">Tạo môi trường</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:1.5rem;margin-bottom:2rem;">
<?php foreach ($environments as $env_key => $env):
    $is_active = $current_env === $env_key;
    $env_enabled = 0;
    foreach ($flags as $flag) {
        if (!empty($flag['environments'][$env_key]['enabled'])) $env_enabled++;
    }
    $env_total = count($flags);
    $env_status = ($env['status'] ?? 'active') === 'active';
?>
  <div class="card" style="<?= $is_active ? 'border-color:var(--primary);box-shadow:var(--shadow-md);' : '' ?>">
    <div class="card-header">
      <div style="display:flex;align-items:center;gap:.75rem;">
        <?= render_env_badge($env_key) ?>
        <?php if ($is_active): ?><span class="badge badge-success" style="font-size:.7rem;">ĐANG XEM</span><?php endif; ?>
        <?php if (!$env_status): ?><span class="badge badge-neutral">Đã tắt</span><?php endif; ?>
      </div>
      <span class="font-mono"><?= e($env['id'] ?? $env_key) ?></span>
    </div>
    <div class="card-body">
      <h3 style="font-size:1.25rem;font-weight:700;color:var(--text-main);margin-bottom:.5rem;"><?= e($env['name'] ?? $env_key) ?></h3>
      <p style="font-size:.88rem;color:var(--text-muted);margin-bottom:1.25rem;min-height:40px;"><?= e($env['description'] ?? '') ?></p>
      <div style="background-color:var(--bg-body);border-radius:var(--radius-md);padding:1rem;margin-bottom:1.25rem;">
        <div style="display:flex;justify-content:space-between;font-size:.85rem;margin-bottom:.5rem;">
          <span style="color:var(--text-secondary);">Tính năng đang bật:</span>
          <strong style="color:var(--success);"><?= $env_enabled ?> / <?= $env_total ?></strong>
        </div>
        <div class="progress-bar-wrap" style="height:8px;"><div class="progress-fill <?= $env_enabled === $env_total ? 'full' : '' ?>" style="width:<?= round(($env_enabled / max(1, $env_total)) * 100) ?>%;"></div></div>
      </div>
      <div style="display:flex;gap:.75rem;">
        <?php if ($is_active): ?>
          <button class="btn btn-secondary btn-sm" disabled style="width:100%;">&check; Đang kích hoạt</button>
        <?php elseif ($env_status): ?>
          <a href="?switch_env=<?= urlencode($env_key) ?>" class="btn btn-primary btn-sm" style="width:100%;">Chuyển sang môi trường này</a>
        <?php else: ?>
          <button class="btn btn-secondary btn-sm" disabled style="width:100%;">Môi trường đã tắt</button>
        <?php endif; ?>
        <a href="flags.php?switch_env=<?= urlencode($env_key) ?>" class="btn btn-secondary btn-sm" style="white-space:nowrap;">Xem cấu hình</a>
      </div>
      <?php if ($can_manage): ?>
      <button type="button" class="btn btn-secondary btn-sm" style="margin-top:1rem;" onclick="openModal('editEnvironmentModal_<?= e($env_key) ?>')">Chỉnh sửa môi trường</button>

      <div class="modal-backdrop" id="editEnvironmentModal_<?= e($env_key) ?>">
        <div class="modal-dialog" style="max-width:620px;">
          <form method="POST" action="environments.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="environment_key" value="<?= e($env_key) ?>">
            <div class="modal-header">
              <h3 class="modal-title">Chỉnh sửa môi trường</h3>
              <button type="button" class="modal-close-btn" data-modal-close>&times;</button>
            </div>
            <div class="modal-body">
              <div class="form-group"><label class="form-label">Mã môi trường</label><input class="form-control font-mono" value="<?= e($env_key) ?>" disabled></div>
              <div class="form-group"><label class="form-label">Tên hiển thị *</label><input class="form-control" name="name" value="<?= e($env['name'] ?? '') ?>" placeholder="Nhập tên môi trường" required></div>
              <div class="form-group"><label class="form-label">Mô tả</label><textarea class="form-control" name="description" rows="3" placeholder="Nhập mô tả môi trường"><?= e($env['description'] ?? '') ?></textarea></div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div class="form-group"><label class="form-label">Màu nhãn</label><select class="form-select" name="badge_color"><?php foreach ($badge_colors as $color => $label): ?><option value="<?= e($color) ?>" <?= ($env['badge_color'] ?? '') === $color ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label class="form-label">Thứ tự hiển thị</label><input class="form-control" type="number" name="sort_order" min="0" value="<?= (int)($env['sort_order'] ?? 0) ?>" placeholder="Nhập thứ tự hiển thị"></div>
              </div>
              <div class="form-group"><label class="form-label">Trạng thái</label><select class="form-select" name="status"><option value="active" <?= $env_status ? 'selected' : '' ?>>Đang hoạt động</option><option value="inactive" <?= !$env_status ? 'selected' : '' ?>>Tạm ngưng</option></select></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-modal-close>Hủy</button>
              <button class="btn btn-primary" type="submit">Lưu thay đổi</button>
            </div>
          </form>
        </div>
      </div>

      <?php if ($can_delete_environment && !$is_active): ?>
      <form method="POST" action="environments.php" style="display:inline;margin-top:1rem;" onsubmit="return confirm('Xóa môi trường này và toàn bộ cấu hình cờ liên quan?')">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="environment_key" value="<?= e($env_key) ?>">
        <button class="btn btn-danger btn-sm" type="submit">
          <svg viewBox="64 64 896 896" fill="currentColor" width="15" height="15" aria-hidden="true"><path d="M864 256H736v-80c0-35.3-28.7-64-64-64H352c-35.3 0-64 28.7-64 64v80H160c-17.7 0-32 14.3-32 32v32c0 4.4 3.6 8 8 8h60.5l49.1 530.6C249.2 884.2 284.9 912 326.6 912h370.8c41.7 0 77.4-27.8 81-73.4L827.5 328H888c4.4 0 8-3.6 8-8v-32c0-17.7-14.3-32-32-32zM360 200h304v56H360v-56zm366.7 632.7c-1.2 15.2-13.1 23.3-26.7 23.3H324c-13.6 0-25.5-8.1-26.7-23.3L249.8 328h524.4l-47.5 504.7zM432 400c17.7 0 32 14.3 32 32v304c0 17.7-14.3 32-32 32s-32-14.3-32-32V432c0-32 32-32 32-32zm160 0c17.7 0 32 14.3 32 32v304c0 17.7-14.3 32-32 32s-32-14.3-32-32V432c0-32 32-32 32-32z"/></svg>
          Xóa
        </button>
      </form>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header"><h3 class="card-title">Quy tắc môi trường</h3></div>
  <div class="card-body" style="font-size:.9rem;color:var(--text-secondary);line-height:1.6;">
    <p>&bull; Mỗi môi trường có cấu hình bật/tắt, rollout, nhóm mục tiêu và user override độc lập.</p>
    <p>&bull; Khi tạo môi trường, hệ thống tự khởi tạo cấu hình bản nháp cho toàn bộ cờ hiện có.</p>
    <p>&bull; Mọi thay đổi được lưu vào Database và ghi vào nhật ký hoạt động.</p>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
