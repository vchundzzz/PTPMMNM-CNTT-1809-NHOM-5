<?php
/**
 * Hệ thống Quản lý Feature Flags
 * flags.php – Quản lý danh sách cờ tính năng (CRUD đầy đủ, Giai đoạn 2)
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/services/PermissionService.php';

/* ============================================================
   XỬ LÝ POST: bật/tắt, thêm mới, cập nhật rollout, xóa
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf)) {
        set_flash('error', 'Mã bảo mật CSRF không hợp lệ, vui lòng thử lại.');
        header('Location: flags.php'); exit;
    }

    $action = $_POST['action'] ?? '';
    $env    = get_active_env();

    /* --- Toggle bật / tắt --- */
    if ($action === 'toggle') {
        PermissionService::enforce('toggle_flag');
        $key = trim($_POST['flag_key'] ?? '');
        if (isset($_SESSION['ff_data']['flags'][$key])) {
            $cur = !empty($_SESSION['ff_data']['flags'][$key]['environments'][$env]['enabled']);
            $new = !$cur;
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['enabled']    = $new;
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['updated_at'] = date('Y-m-d H:i:s');
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['updated_by'] = get_authenticated_user()['username'] ?? 'admin';
            // Cập nhật trạng thái hiển thị
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['status'] = $new ? 'Đã phát hành' : 'Bản nháp';
            append_audit_log('BẬT_TẮT_CỜ', $key, $cur?'Bật':'Tắt', $new?'Bật':'Tắt', 'Thay đổi trực tiếp từ danh sách');
            set_flash('success', ($new ? 'Đã BẬT' : 'Đã TẮT') . " cờ [$key] trên môi trường " . ($env==='testing'?'Kiểm thử':'Vận hành') . '.');
        }
        header('Location: flags.php'); exit;
    }

    /* --- Tạo cờ mới --- */
    if ($action === 'create') {
        PermissionService::enforce('create_flag');
        $key  = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['flag_key'] ?? '')));
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');

        if (empty($key) || empty($name)) {
            set_flash('error', 'Tên và mã cờ không được để trống.'); header('Location: flags.php'); exit;
        }
        if (isset($_SESSION['ff_data']['flags'][$key])) {
            set_flash('error', "Mã cờ [$key] đã tồn tại trong hệ thống."); header('Location: flags.php'); exit;
        }
        $num = count($_SESSION['ff_data']['flags']) + 1;
        $_SESSION['ff_data']['flags'][$key] = [
            'id'          => sprintf('flg_%03d', $num),
            'key'         => $key,
            'name'        => $name,
            'description' => $desc,
            'project_id'  => 'prj_ecommerce',
            'environments' => [
                'testing' => [
                    'enabled' => false, 'rollout_percentage' => 0,
                    'status'  => 'Bản nháp', 'target_groups' => [],
                    'user_overrides' => [],
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => get_authenticated_user()['username'] ?? 'admin',
                ],
                'staging' => [
                    'enabled' => false, 'rollout_percentage' => 0,
                    'status'  => 'Bản nháp', 'target_groups' => [],
                    'user_overrides' => [],
                    'updated_at' => date('Y-m-d H:i:s'),
                    'updated_by' => get_authenticated_user()['username'] ?? 'admin',
                ],
            ],
        ];
        append_audit_log('TẠO_CỜ_MỚI', $key, '-', "[$key] – Bản nháp", 'Tạo mới từ giao diện danh sách');
        set_flash('success', "Đã tạo cờ tính năng [$key] thành công (bản nháp).");
        header('Location: flags.php'); exit;
    }

    /* --- Cập nhật tỷ lệ rollout --- */
    if ($action === 'update_rollout') {
        PermissionService::enforce('toggle_flag');
        $key = trim($_POST['flag_key'] ?? '');
        $pct = max(0, min(100, (int)($_POST['rollout_percentage'] ?? 0)));
        if (isset($_SESSION['ff_data']['flags'][$key])) {
            $old = $_SESSION['ff_data']['flags'][$key]['environments'][$env]['rollout_percentage'] ?? 0;
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['rollout_percentage'] = $pct;
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['updated_at'] = date('Y-m-d H:i:s');
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['updated_by'] = get_authenticated_user()['username'] ?? 'admin';
            append_audit_log('CẬP_NHẬT_TỶ_LỆ', $key, "Tỷ lệ: $old%", "Tỷ lệ: $pct%", 'Điều chỉnh trực tiếp từ danh sách');
            set_flash('success', "Đã cập nhật tỷ lệ triển khai cờ [$key] → $pct%.");
        }
        header('Location: flags.php'); exit;
    }

    /* --- Xóa cờ --- */
    if ($action === 'delete') {
        PermissionService::enforce('delete_flag');
        $key = trim($_POST['flag_key'] ?? '');
        if (isset($_SESSION['ff_data']['flags'][$key])) {
            unset($_SESSION['ff_data']['flags'][$key]);
            append_audit_log('XÓA_CỜ', $key, 'Tồn tại', 'Đã xóa', 'Xóa từ giao diện danh sách');
            set_flash('success', "Đã xóa cờ tính năng [$key].");
        }
        header('Location: flags.php'); exit;
    }
}

/* ============================================================
   RENDER TRANG
   ============================================================ */
$page_title  = 'Cờ tính năng (Feature Flags)';
$breadcrumbs = [
    ['label' => 'Trang chủ', 'url' => 'dashboard.php'],
    ['label' => 'Cờ tính năng', 'url' => ''],
];
require_once __DIR__ . '/includes/header.php';

$flags       = $_SESSION['ff_data']['flags'] ?? [];
$current_env = get_active_env();
$env_label   = $current_env === 'testing' ? 'Kiểm thử' : 'Vận hành';

$can_create = PermissionService::currentUserCan('create_flag');
$can_toggle = PermissionService::currentUserCan('toggle_flag');
$can_delete = PermissionService::currentUserCan('delete_flag');
$can_edit   = PermissionService::currentUserCan('toggle_flag');
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Cờ tính năng</h1>
    <p class="page-description">
      Quản lý bật/tắt và tỷ lệ triển khai trên môi trường <strong style="color:var(--primary)"><?= e($env_label) ?></strong>.
    </p>
  </div>
  <div class="page-actions">
    <?php if ($can_create): ?>
      <button class="btn btn-primary" onclick="openModal('createFlagModal')">
        <!-- plus icon -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Tạo cờ mới
      </button>
    <?php endif; ?>
  </div>
</div>

<div class="filter-toolbar">
  <div class="filter-left">
    <div class="search-input-wrapper">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="flagSearch" class="form-control" placeholder="Nhập tên hoặc mã cờ cần tìm">
    </div>
    <select class="form-select" style="width:auto" onchange="location.href='?switch_env='+this.value">
      <option value="testing" <?= $current_env==='testing'?'selected':'' ?>>Môi trường: Kiểm thử</option>
      <option value="staging" <?= $current_env==='staging'?'selected':'' ?>>Môi trường: Vận hành</option>
    </select>
    <select class="form-select" style="width:auto" id="statusFilter" onchange="filterByStatus(this.value)">
      <option value="">Tất cả trạng thái</option>
      <option value="bật">Đang bật</option>
      <option value="tắt">Đang tắt</option>
    </select>
  </div>
  <div class="filter-right" style="font-size:.8rem;color:var(--text-secondary);">
    <?= count($flags) ?> cờ tính năng
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="data-table" id="flagsTable">
      <thead>
        <tr>
          <th>Tên tính năng</th>
          <th>Mã cờ</th>
          <th>Trạng thái</th>
          <th>Tỷ lệ triển khai</th>
          <th>Nhóm áp dụng</th>
          <th>Ngoại lệ</th>
          <th>Cập nhật lần cuối</th>
          <th style="text-align:center">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($flags as $flag):
          $ec  = $flag['environments'][$current_env] ?? [];
          $on  = !empty($ec['enabled']);
          $pct = (int)($ec['rollout_percentage'] ?? 0);
          $st  = $ec['status'] ?? 'Bản nháp';
          $ov  = count($ec['user_overrides'] ?? []);
          $grp = count($ec['target_groups'] ?? []);
        ?>
        <tr data-status="<?= $on?'bật':'tắt' ?>">
          <td>
            <div style="font-weight:600;color:var(--text-main)"><?= e($flag['name']) ?></div>
            <div style="font-size:.75rem;color:var(--text-secondary);max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              <?= e($flag['description']) ?>
            </div>
          </td>
          <td><span class="font-mono"><?= e($flag['key']) ?></span></td>
          <td>
            <div style="display:flex;align-items:center;gap:.5rem">
              <!-- Toggle -->
              <?php if ($can_toggle): ?>
              <form method="POST" action="flags.php" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="flag_key" value="<?= e($flag['key']) ?>">
                <label class="switch" title="<?= $on?'Nhấn để TẮT':'Nhấn để BẬT' ?>">
                  <input type="checkbox" <?= $on?'checked':'' ?> onchange="this.form.submit()">
                  <span class="slider"></span>
                </label>
              </form>
              <?php else: ?>
              <label class="switch"><input type="checkbox" <?= $on?'checked':'' ?> disabled><span class="slider"></span></label>
              <?php endif; ?>
              <?= render_badge($st) ?>
            </div>
          </td>
          <td>
            <div class="rollout-container">
              <div class="progress-bar-wrap">
                <div class="progress-fill <?= $pct===100?'full':'' ?>" style="width:<?= $pct ?>%"></div>
              </div>
              <span class="rollout-percent"><?= $pct ?>%</span>
            </div>
          </td>
          <td>
            <?php if ($grp > 0): ?>
              <span class="badge badge-primary"><?= $grp ?> nhóm</span>
            <?php else: ?>
              <span class="badge badge-neutral">Tất cả</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($ov > 0): ?>
              <span class="badge badge-warning"><?= $ov ?> KH</span>
            <?php else: ?>
              <span style="color:var(--text-muted);font-size:.78rem">—</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="font-size:.78rem;color:var(--text-secondary)"><?= format_date($ec['updated_at'] ?? '') ?></div>
            <div style="font-size:.72rem;color:var(--text-muted)"><?= e($ec['updated_by'] ?? '') ?></div>
          </td>
          <td style="text-align:center">
            <div class="action-icons" style="justify-content:center">
              <!-- Xem chi tiết (Ant Design EyeOutlined) -->
              <a href="flag-detail.php?key=<?= urlencode($flag['key']) ?>" class="action-icon-btn action-view" title="Xem chi tiết" aria-label="Xem chi tiết">
                <svg viewBox="64 64 896 896" fill="currentColor"><path d="M942.2 486.2C847.4 286.5 704.1 186 512 186c-192.2 0-335.4 100.5-430.2 300.3a60.3 60.3 0 0 0 0 51.5C176.6 737.5 319.9 838 512 838c192.2 0 335.4-100.5 430.2-300.3 7.7-16.2 7.7-35 0-51.5zM512 766c-161.3 0-279.4-81.8-362.7-254C232.6 339.8 350.7 258 512 258c161.3 0 279.4 81.8 362.7 254C791.5 684.2 673.4 766 512 766zm-4-422c-97.2 0-176 78.8-176 176s78.8 176 176 176 176-78.8 176-176-78.8-176-176-176zm0 288c-61.9 0-112-50.1-112-112s50.1-112 112-112 112 50.1 112 112-50.1 112-112 112z"/></svg>
              </a>
              <!-- Chỉnh sửa cấu hình & Tỷ lệ (Ant Design EditOutlined / Cây bút) -->
              <?php if ($can_toggle): ?>
              <button class="action-icon-btn action-edit" title="Chỉnh sửa cấu hình & Tỷ lệ triển khai" onclick="openRolloutModal('<?= e($flag['key']) ?>',<?= $pct ?>)">
                <svg viewBox="64 64 896 896" fill="currentColor"><path d="M257.7 752c2 0 4-.2 6-.5L431.9 722c2-.4 3.9-1.3 5.3-2.8l423.9-423.9a9.96 9.96 0 0 0 0-14.1L694.9 114.9c-1.9-1.9-4.4-2.9-7.1-2.9s-5.2 1-7.1 2.9L256.8 538.8c-1.5 1.5-2.4 3.3-2.8 5.3l-29.5 168.2a33.5 33.5 0 0 0 9.4 29.8c6.6 6.4 14.9 9.9 23.8 9.9zm67.4-174.4L687.8 215l57.2 57.2-362.7 362.6-68.9 12 11.7-69.2zM752 688H144c-17.7 0-32 14.3-32 32v120c0 4.4 3.6 8 8 8h784c4.4 0 8-3.6 8-8V720c0-17.7-14.3-32-32-32z"/></svg>
              </button>
              <?php endif; ?>
              <!-- Xóa cờ (Ant Design DeleteOutlined) -->
              <?php if ($can_delete): ?>
              <form method="POST" action="flags.php" style="display:inline" onsubmit="return confirm('Xác nhận xóa cờ [<?= e($flag['key']) ?>]? Thao tác này không thể hoàn tác.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="flag_key" value="<?= e($flag['key']) ?>">
                <button type="submit" class="action-icon-btn danger action-delete" title="Xóa cờ">
                  <svg viewBox="64 64 896 896" fill="currentColor"><path d="M864 256H736v-80c0-35.3-28.7-64-64-64H352c-35.3 0-64 28.7-64 64v80H160c-17.7 0-32 14.3-32 32v32c0 4.4 3.6 8 8 8h60.5l49.1 530.6C249.2 884.2 284.9 912 326.6 912h370.8c41.7 0 77.4-27.8 81-73.4L827.5 328H888c4.4 0 8-3.6 8-8v-32c0-17.7-14.3-32-32-32zM360 200h304v56H360v-56zm366.7 632.7c-1.2 15.2-13.1 23.3-26.7 23.3H324c-13.6 0-25.5-8.1-26.7-23.3L249.8 328h524.4l-47.5 504.7zM432 400c17.7 0 32 14.3 32 32v304c0 17.7-14.3 32-32 32s-32-14.3-32-32V432c0-17.7 14.3-32 32-32zm160 0c17.7 0 32 14.3 32 32v304c0 17.7-14.3 32-32 32s-32-14.3-32-32V432c0-17.7 14.3-32 32-32z"/></svg>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Tạo cờ mới -->
<div class="modal-backdrop" id="createFlagModal">
  <div class="modal-dialog">
    <form method="POST" action="flags.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <div class="modal-header">
        <h3 class="modal-title">Tạo cờ tính năng mới</h3>
        <button type="button" class="modal-close-btn" data-modal-close>&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Tên tính năng *</label>
          <input type="text" name="name" class="form-control" placeholder="Nhập tên tính năng" required>
        </div>
        <div class="form-group">
          <label class="form-label">Mã cờ (Key) *</label>
          <input type="text" name="flag_key" class="form-control font-mono" placeholder="Nhập mã cờ tính năng" required>
          <div class="form-text">Dùng làm tham số flag_key trong API evaluate. Không thể đổi sau khi tạo.</div>
        </div>
        <div class="form-group">
          <label class="form-label">Mô tả</label>
          <textarea name="description" class="form-control" rows="3" placeholder="Nhập mô tả tính năng"></textarea>
        </div>
        <div class="alert alert-info" style="margin-top:.5rem">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          Cờ mới sẽ ở trạng thái <strong>Bản nháp – Tắt (0%)</strong> trên cả 2 môi trường. Kích hoạt sau khi kiểm tra xong.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Hủy</button>
        <button type="submit" class="btn btn-primary">Tạo cờ</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal đổi Rollout % -->
<div class="modal-backdrop" id="rolloutModal">
  <div class="modal-dialog" style="max-width:380px">
    <form method="POST" action="flags.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_rollout">
      <input type="hidden" name="flag_key" id="rolloutFlagKey">
      <div class="modal-header">
        <h3 class="modal-title">Điều chỉnh tỷ lệ triển khai</h3>
        <button type="button" class="modal-close-btn" data-modal-close>&times;</button>
      </div>
      <div class="modal-body">
        <p style="font-size:.85rem;color:var(--text-secondary);margin-bottom:1rem">
          Cờ: <strong id="rolloutFlagName"></strong> trên <strong><?= e($env_label) ?></strong>
        </p>
        <div class="form-group">
          <label class="form-label">Tỷ lệ % người dùng nhận tính năng: <strong id="rolloutDisplay">50</strong>%</label>
          <input type="range" name="rollout_percentage" id="rolloutSlider" min="0" max="100" step="10" value="50"
                 oninput="document.getElementById('rolloutDisplay').textContent=this.value" style="width:100%;margin:.5rem 0">
          <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--text-muted)">
            <span>0% (Tắt hết)</span><span>10%</span><span>50%</span><span>100% (Bật hết)</span>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Hủy</button>
        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  initTableSearch('flagSearch', 'flagsTable');
});

function filterByStatus(val) {
  document.querySelectorAll('#flagsTable tbody tr').forEach(tr => {
    if (!val) { tr.style.display = ''; return; }
    tr.style.display = tr.dataset.status === val ? '' : 'none';
  });
}

function openRolloutModal(key, current) {
  document.getElementById('rolloutFlagKey').value  = key;
  document.getElementById('rolloutFlagName').textContent = key;
  document.getElementById('rolloutSlider').value   = current;
  document.getElementById('rolloutDisplay').textContent = current;
  openModal('rolloutModal');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
