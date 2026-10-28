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
            $user = get_authenticated_user()['username'] ?? 'admin';
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['enabled']    = $new;
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['updated_at'] = date('Y-m-d H:i:s');
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['updated_by'] = $user;
            // Cập nhật trạng thái hiển thị
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['status'] = $new ? 'Đã phát hành' : 'Bản nháp';

            // Đồng bộ trực tiếp vào CSDL MySQL
            try {
                require_once __DIR__ . '/services/DatabaseService.php';
                DatabaseService::toggleFlag($key, $env, $new, $user);
            } catch (Throwable $e) {}

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
        try {
            require_once __DIR__ . '/services/DatabaseService.php';
            DatabaseService::createFlag([
                'id'          => sprintf('flg_%03d', $num),
                'key'         => $key,
                'name'        => $name,
                'description' => $desc,
                'updated_by'  => get_authenticated_user()['username'] ?? 'admin',
            ]);
        } catch (Throwable $e) {}

        append_audit_log('TẠO_CỜ_MỚI', $key, '-', "[$key] – Bản nháp", 'Tạo mới từ giao diện danh sách');
        set_flash('success', "Đã tạo cờ tính năng [$key] và lưu vào CSDL MySQL thành công.");
        header('Location: flags.php'); exit;
    }

    /* --- Cập nhật tỷ lệ rollout --- */
    if ($action === 'update_rollout') {
        PermissionService::enforce('toggle_flag');
        $key = trim($_POST['flag_key'] ?? '');
        $pct = max(0, min(100, (int)($_POST['rollout_percentage'] ?? 0)));
        if (isset($_SESSION['ff_data']['flags'][$key])) {
            $user = get_authenticated_user()['username'] ?? 'admin';
            $old = $_SESSION['ff_data']['flags'][$key]['environments'][$env]['rollout_percentage'] ?? 0;
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['rollout_percentage'] = $pct;
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['updated_at'] = date('Y-m-d H:i:s');
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['updated_by'] = $user;

            try {
                require_once __DIR__ . '/services/DatabaseService.php';
                DatabaseService::updateRollout($key, $env, $pct, $user);
            } catch (Throwable $e) {}

            append_audit_log('CẬP_NHẬT_TỶ_LỆ', $key, "Tỷ lệ: $old%", "Tỷ lệ: $pct%", 'Điều chỉnh trực tiếp từ danh sách');
            set_flash('success', "Đã cập nhật tỷ lệ triển khai cờ [$key] → $pct% và lưu vào CSDL MySQL.");
        }
        header('Location: flags.php'); exit;
    }

    /* --- Xóa cờ --- */
    if ($action === 'delete') {
        PermissionService::enforce('delete_flag');
        $key = trim($_POST['flag_key'] ?? '');
        if (isset($_SESSION['ff_data']['flags'][$key])) {
            unset($_SESSION['ff_data']['flags'][$key]);

            // Đồng bộ xóa trong CSDL MySQL
            try {
                require_once __DIR__ . '/services/DatabaseService.php';
                DatabaseService::deleteFlag($key);
            } catch (Throwable $e) {}

            append_audit_log('XÓA_CỜ', $key, 'Tồn tại', 'Đã xóa', 'Xóa từ giao diện danh sách');
            set_flash('success', "Đã xóa cờ tính năng [$key].");
        }
        header('Location: flags.php'); exit;
    }

    /* --- Xóa nhiều cờ --- */
    if ($action === 'bulk_delete') {
        PermissionService::enforce('delete_flag');
        $submitted_keys = $_POST['flag_keys'] ?? [];
        $keys = is_array($submitted_keys)
            ? array_values(array_unique(array_filter($submitted_keys, 'is_string')))
            : [];
        $deleted = 0;

        foreach ($keys as $key) {
            $key = trim($key);
            if ($key === '' || !isset($_SESSION['ff_data']['flags'][$key])) {
                continue;
            }

            unset($_SESSION['ff_data']['flags'][$key]);
            try {
                require_once __DIR__ . '/services/DatabaseService.php';
                DatabaseService::deleteFlag($key);
            } catch (Throwable $e) {}
            append_audit_log('XÓA_CỜ', $key, 'Tồn tại', 'Đã xóa', 'Xóa hàng loạt từ giao diện danh sách');
            $deleted++;
        }

        if ($deleted > 0) {
            set_flash('success', "Đã xóa thành công $deleted cờ tính năng.");
        } else {
            set_flash('error', 'Không có cờ hợp lệ nào được chọn để xóa.');
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
$flags_pagination = paginate_items($flags, (int)($_GET['page'] ?? 1), 20);
$page_flags = $flags_pagination['items'];
?>

<style>
.flag-management-page { color:#243247; }
.flag-management-page .page-header { align-items:center; }
.flag-management-page .page-title { color:#172b4d; letter-spacing:-.025em; }
.flag-management-page .page-description { color:#74839a; }
.flag-management-page .filter-toolbar { gap:.8rem; padding:.75rem; border:1px solid #e6edf5; border-radius:12px; background:linear-gradient(135deg,#fff 0%,#f8fbff 100%); box-shadow:0 3px 12px rgba(30,64,120,.035); }
.flag-management-page .filter-left { flex:1; }
.flag-management-page .search-input-wrapper { flex:1 1 270px; }
.flag-management-page .search-input-wrapper input,
.flag-management-page .filter-toolbar .form-select { min-height:38px; border-color:#dce5f0; border-radius:8px; }
.flag-management-page .search-input-wrapper input:focus,
.flag-management-page .filter-toolbar .form-select:focus { border-color:#7eb3ff; box-shadow:0 0 0 3px rgba(22,119,255,.1); }
.flag-management-page .filter-right { padding:.45rem .7rem; border:1px solid #dce8f8; border-radius:999px; background:#eff6ff; color:#2563eb !important; font-weight:650; white-space:nowrap; }
.flag-selection-cell { width:42px; text-align:center; }
.flag-select-checkbox { width:16px; height:16px; margin:0; accent-color:#1677ff; cursor:pointer; vertical-align:middle; }
.flag-select-checkbox:focus-visible { outline:3px solid rgba(22,119,255,.25); outline-offset:2px; }
.flag-bulk-toolbar { display:none; align-items:center; gap:.65rem; margin:-.3rem 0 .75rem; padding:.65rem .8rem; border:1px solid #bfdbfe; border-radius:10px; background:linear-gradient(110deg,#eff6ff,#f8fbff); color:#334155; box-shadow:0 3px 10px rgba(37,99,235,.06); }
.flag-bulk-toolbar.show { display:flex; animation:flagToolbarIn .18s ease-out; }
@keyframes flagToolbarIn { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:translateY(0); } }
.flag-bulk-count { color:#334155; font-size:.82rem; font-weight:600; }
.flag-bulk-count strong { color:#1677ff; }
.flag-bulk-clear { padding:.38rem .55rem; border:0; border-radius:6px; background:transparent; color:#475569; font-size:.78rem; cursor:pointer; transition:background .18s,color .18s; }
.flag-bulk-clear:hover { background:#e2edff; color:#0958d9; }
.flag-management-page .btn-danger { background:#ff4d4f; border-color:#ff4d4f; color:#fff; box-shadow:0 2px 6px rgba(255,77,79,.16); }
.flag-management-page .btn-danger:hover { background:#e63946; border-color:#e63946; color:#fff; box-shadow:0 4px 10px rgba(255,77,79,.22); }
.flag-management-page .card { overflow:hidden; border-color:#e3eaf3; border-radius:12px; box-shadow:0 4px 18px rgba(30,64,120,.045); }
.flag-management-page .table-responsive { border:0; border-radius:0; }
.flags-data-table { min-width:1050px; }
.flags-data-table th { padding:.8rem .85rem; background:#f7f9fc; color:#71819a; font-size:.68rem; letter-spacing:.045em; }
.flags-data-table td { padding:.85rem .85rem; border-bottom-color:#edf1f6; font-size:.79rem; }
.flags-data-table tbody tr { transition:background .18s ease,box-shadow .18s ease; }
.flags-data-table tbody tr:nth-child(even) { background:#fbfcfe; }
.flags-data-table tbody tr:hover { background:#f3f8ff; }
.flags-data-table tbody tr.flag-row-selected { background:#eaf3ff; }
.flags-data-table tbody tr:last-child td { border-bottom:0; }
.flag-name { color:#243247; font-size:.82rem; font-weight:650; line-height:1.35; }
.flag-description { display:block; max-width:280px; margin-top:.22rem; overflow:hidden; color:#8491a4; font-size:.71rem; text-overflow:ellipsis; white-space:nowrap; }
.flag-key { display:inline-block; padding:.18rem .38rem; border:1px solid #dce5ef; border-radius:5px; background:#f8fafc; color:#53657d; font-size:.7rem; white-space:nowrap; }
.flag-state-cell { display:flex; align-items:center; gap:.45rem; white-space:nowrap; }
.flag-state-cell .badge { max-width:88px; white-space:normal; line-height:1.35; }
.flag-rollout { min-width:115px; }
.flag-rollout .progress-bar-wrap { height:7px; background:#edf1f6; }
.flag-rollout .progress-fill { background:linear-gradient(90deg,#4096ff,#1677ff); }
.flag-rollout .progress-fill.full { background:linear-gradient(90deg,#73d13d,#389e0d); }
.flag-rollout .rollout-percent { color:#71819a; font-size:.72rem; font-weight:650; }
.flag-updated-at { color:#64748b; font-size:.72rem; white-space:nowrap; }
.flag-updated-by { display:block; margin-top:.18rem; color:#9aa6b6; font-size:.68rem; }
.flags-data-table .action-icons { gap:.4rem; }
.flags-data-table .action-icon-btn { width:34px; height:34px; border-radius:8px; }
.flag-delete-warning { display:flex; gap:.65rem; padding:.85rem; border:1px solid var(--danger-border); border-radius:var(--radius-md); background:var(--danger-light); color:var(--danger-text); font-size:.85rem; }
.flag-delete-warning svg { flex:none; margin-top:.08rem; }
@media (max-width:760px) {
  .flag-management-page .filter-toolbar { align-items:stretch; }
  .flag-management-page .filter-left { width:100%; align-items:stretch; }
  .flag-management-page .search-input-wrapper { min-width:100%; }
  .flag-management-page .filter-left .form-select { width:100% !important; }
  .flag-management-page .filter-right { margin-left:auto; }
}
@media (max-width:640px) {
  .flag-bulk-toolbar { flex-wrap:wrap; }
  .flag-bulk-count { flex:1 1 100%; }
}
</style>

<div class="flag-management-page">
<div class="flag-management-page">
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
        Tạo mới
      </button>
    <?php endif; ?>
  </div>
</div>

<?php if ($can_delete): ?>
<div class="flag-bulk-toolbar" id="flagBulkToolbar" role="region" aria-live="polite" aria-label="Thao tác với các cờ đã chọn">
  <span class="flag-bulk-count">Đã chọn <strong id="selectedFlagCount">0</strong> tính năng</span>
  <button type="button" class="btn btn-danger btn-sm" id="openBulkDeleteModal">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg>
    Xóa đã chọn
  </button>
  <button type="button" class="flag-bulk-clear" id="clearSelectedFlags">Bỏ chọn tất cả</button>
</div>
<form method="POST" action="flags.php" id="bulkDeleteForm" hidden>
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="bulk_delete">
  <div id="bulkDeleteKeys"></div>
</form>
<?php endif; ?>

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
    <select class="form-select" style="width:auto" id="statusFilter">
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
    <table class="data-table flags-data-table" id="flagsTable">
      <thead>
        <tr>
          <?php if ($can_delete): ?>
          <th class="flag-selection-cell"><input type="checkbox" class="flag-select-checkbox" id="selectAllFlags" aria-label="Chọn tất cả cờ tính năng"></th>
          <?php endif; ?>
          <th>Tên tính năng</th>
          <th>Mã cờ</th>
          <th>Trạng thái</th>
          <th>Tỷ lệ triển khai</th>
          <th>Nhóm áp dụng</th>
          <th>Ngoại lệ</th>
          <th>Cập nhật lần cuối</th>
          <th style="text-align:center">Chức năng</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($page_flags as $flag):
          $ec  = $flag['environments'][$current_env] ?? [];
          $on  = !empty($ec['enabled']);
          $pct = (int)($ec['rollout_percentage'] ?? 0);
          $st  = $ec['status'] ?? 'Bản nháp';
          $ov  = count($ec['user_overrides'] ?? []);
          $grp = count($ec['target_groups'] ?? []);
        ?>
        <tr data-status="<?= $on?'bật':'tắt' ?>" class="flag-data-row">
          <?php if ($can_delete): ?>
          <td class="flag-selection-cell">
            <input type="checkbox" class="flag-select-checkbox flag-row-checkbox" value="<?= e($flag['key']) ?>" aria-label="Chọn cờ <?= e($flag['name']) ?>">
          </td>
          <?php endif; ?>
          <td>
            <div class="flag-name"><?= e($flag['name']) ?></div>
            <div class="flag-description">
              <?= e($flag['description']) ?>
            </div>
          </td>
          <td><span class="font-mono flag-key"><?= e($flag['key']) ?></span></td>
          <td>
            <div class="flag-state-cell">
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
            <div class="rollout-container flag-rollout" aria-label="Tỷ lệ triển khai <?= $pct ?>%">
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
            <div class="flag-updated-at"><?= format_date($ec['updated_at'] ?? '') ?></div>
            <span class="flag-updated-by"><?= e($ec['updated_by'] ?? '') ?></span>
          </td>
          <td style="text-align:center">
            <div class="action-icons" style="justify-content:center">
              <!-- Xem chi tiết (Ant Design EyeOutlined) -->
              <a href="flag-detail.php?key=<?= urlencode($flag['key']) ?>&mode=view&return_to=flags.php" class="action-icon-btn action-view" title="Xem chi tiết" aria-label="Xem chi tiết">
                <svg viewBox="64 64 896 896" fill="currentColor"><path d="M942.2 486.2C847.4 286.5 704.1 186 512 186c-192.2 0-335.4 100.5-430.2 300.3a60.3 60.3 0 0 0 0 51.5C176.6 737.5 319.9 838 512 838c192.2 0 335.4-100.5 430.2-300.3 7.7-16.2 7.7-35 0-51.5zM512 766c-161.3 0-279.4-81.8-362.7-254C232.6 339.8 350.7 258 512 258c161.3 0 279.4 81.8 362.7 254C791.5 684.2 673.4 766 512 766zm-4-422c-97.2 0-176 78.8-176 176s78.8 176 176 176 176-78.8 176-176-78.8-176-176-176zm0 288c-61.9 0-112-50.1-112-112s50.1-112 112-112 112 50.1 112 112-50.1 112-112 112z"/></svg>
              </a>
              <!-- Mở chi tiết ở chế độ chỉnh sửa -->
              <?php if (PermissionService::currentUserCan('edit_flag')): ?>
              <a href="flag-detail.php?key=<?= urlencode($flag['key']) ?>&mode=edit&return_to=flags.php" class="action-icon-btn action-edit" title="Chỉnh sửa" aria-label="Chỉnh sửa">
                <svg viewBox="64 64 896 896" fill="currentColor"><path d="M257.7 752c2 0 4-.2 6-.5L431.9 722c2-.4 3.9-1.3 5.3-2.8l423.9-423.9a9.96 9.96 0 0 0 0-14.1L694.9 114.9c-1.9-1.9-4.4-2.9-7.1-2.9s-5.2 1-7.1 2.9L256.8 538.8c-1.5 1.5-2.4 3.3-2.8 5.3l-29.5 168.2a33.5 33.5 0 0 0 9.4 29.8c6.6 6.4 14.9 9.9 23.8 9.9zm67.4-174.4L687.8 215l57.2 57.2-362.7 362.6-68.9 12 11.7-69.2zM752 688H144c-17.7 0-32 14.3-32 32v120c0 4.4 3.6 8 8 8h784c4.4 0 8-3.6 8-8V720c0-17.7-14.3-32-32-32z"/></svg>
              </a>
              <?php endif; ?>
              <!-- Xóa cờ (Ant Design DeleteOutlined) -->
              <?php if ($can_delete): ?>
              <form method="POST" action="flags.php" style="display:inline" onsubmit="return confirm('Xác nhận xóa cờ [<?= e($flag['key']) ?>]? Thao tác này không thể hoàn tác.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="flag_key" value="<?= e($flag['key']) ?>">
                <button type="submit" class="action-icon-btn danger action-delete" title="Xóa">
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
  <?= render_pagination($flags_pagination['total'], $flags_pagination['page'], 20, 'flags.php', 'cờ tính năng') ?>
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

<?php if ($can_delete): ?>
<div class="modal-backdrop" id="bulkDeleteConfirmModal" role="dialog" aria-modal="true" aria-labelledby="bulkDeleteModalTitle">
  <div class="modal-dialog" style="max-width:440px">
    <div class="modal-header">
      <h3 class="modal-title" id="bulkDeleteModalTitle">
        <span style="display:inline-flex;align-items:center;gap:.5rem">
          <span style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:var(--danger-light);color:var(--danger)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg>
          </span>
          Xác nhận xóa
        </span>
      </h3>
      <button type="button" class="modal-close-btn" data-modal-close aria-label="Đóng">&times;</button>
    </div>
    <div class="modal-body">
      <p style="font-size:.9rem;color:var(--text-main);margin-bottom:.85rem">
        Bạn có chắc chắn muốn xóa <strong id="bulkDeleteCount">0</strong> tính năng đã chọn?
      </p>
      <div class="flag-delete-warning">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
        <span>Các tính năng đã chọn sẽ bị gỡ khỏi cấu hình. Thao tác xóa sẽ được ghi vào nhật ký và không thể hoàn tác.</span>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-modal-close>Hủy</button>
      <button type="submit" class="btn btn-danger" form="bulkDeleteForm" id="confirmBulkDelete">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/></svg>
        Xác nhận xóa
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('flagSearch');
  const statusFilter = document.getElementById('statusFilter');
  const table = document.getElementById('flagsTable');
  const selectAll = document.getElementById('selectAllFlags');
  const toolbar = document.getElementById('flagBulkToolbar');
  const selectedCount = document.getElementById('selectedFlagCount');
  const keysContainer = document.getElementById('bulkDeleteKeys');
  const bulkForm = document.getElementById('bulkDeleteForm');
  const deleteCount = document.getElementById('bulkDeleteCount');

  function getRowCheckboxes() {
    return table ? Array.from(table.querySelectorAll('.flag-row-checkbox')) : [];
  }

  function updateBulkSelection() {
    const checkboxes = getRowCheckboxes();
    const selected = checkboxes.filter((checkbox) => checkbox.checked);
    checkboxes.forEach((checkbox) => {
      checkbox.closest('tr').classList.toggle('flag-row-selected', checkbox.checked);
    });

    if (toolbar) {
      toolbar.classList.toggle('show', selected.length > 0);
      selectedCount.textContent = String(selected.length);
    }

    if (selectAll) {
      const visibleCheckboxes = checkboxes.filter((checkbox) => checkbox.closest('tr').style.display !== 'none');
      const visibleSelected = visibleCheckboxes.filter((checkbox) => checkbox.checked);
      selectAll.checked = visibleCheckboxes.length > 0 && visibleSelected.length === visibleCheckboxes.length;
      selectAll.indeterminate = visibleSelected.length > 0 && visibleSelected.length < visibleCheckboxes.length;
      selectAll.disabled = visibleCheckboxes.length === 0;
    }

    if (keysContainer) {
      keysContainer.replaceChildren();
      selected.forEach((checkbox) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'flag_keys[]';
        input.value = checkbox.value;
        keysContainer.appendChild(input);
      });
    }

    if (deleteCount) {
      deleteCount.textContent = String(selected.length);
    }
    if (bulkForm) {
      document.getElementById('confirmBulkDelete').disabled = selected.length === 0;
    }
  }

  function applyFlagFilters() {
    if (!table) return;
    const query = searchInput ? searchInput.value.trim().toLocaleLowerCase() : '';
    const status = statusFilter ? statusFilter.value : '';
    table.querySelectorAll('tbody tr').forEach((row) => {
      const matchesStatus = !status || row.dataset.status === status;
      const matchesSearch = !query || row.textContent.toLocaleLowerCase().includes(query);
      row.style.display = matchesStatus && matchesSearch ? '' : 'none';
    });
    updateBulkSelection();
  }

  if (searchInput) searchInput.addEventListener('input', applyFlagFilters);
  if (statusFilter) statusFilter.addEventListener('change', applyFlagFilters);

  if (table) {
    table.addEventListener('change', (event) => {
      if (event.target.matches('.flag-row-checkbox')) {
        updateBulkSelection();
      }
    });
  }

  if (selectAll) {
    selectAll.addEventListener('change', () => {
      getRowCheckboxes().forEach((checkbox) => {
        if (checkbox.closest('tr').style.display !== 'none') {
          checkbox.checked = selectAll.checked;
        }
      });
      updateBulkSelection();
    });
  }

  const clearSelection = document.getElementById('clearSelectedFlags');
  if (clearSelection) {
    clearSelection.addEventListener('click', () => {
      getRowCheckboxes().forEach((checkbox) => { checkbox.checked = false; });
      updateBulkSelection();
    });
  }

  const openBulkDelete = document.getElementById('openBulkDeleteModal');
  if (openBulkDelete) {
    openBulkDelete.addEventListener('click', () => {
      if (getRowCheckboxes().some((checkbox) => checkbox.checked)) {
        openModal('bulkDeleteConfirmModal');
      }
    });
  }

  updateBulkSelection();
});

function openRolloutModal(key, current) {
  document.getElementById('rolloutFlagKey').value  = key;
  document.getElementById('rolloutFlagName').textContent = key;
  document.getElementById('rolloutSlider').value   = current;
  document.getElementById('rolloutDisplay').textContent = current;
  openModal('rolloutModal');
}
</script>

 </div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
