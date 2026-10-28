<?php
/**
 * Web Feature Flag Management System
 * groups.php - Quản lý nhóm người dùng (User Groups)
 * Đồng bộ 2 chiều thời gian thực với bảng `target_groups` và `customers` trong CSDL MySQL
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/services/PermissionService.php';
require_once __DIR__ . '/services/DatabaseService.php';

require_login();

// Xử lý tạo / sửa / xóa nhóm khách hàng và lưu vào CSDL MySQL
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Mã bảo mật CSRF không hợp lệ.');
        header('Location: groups.php');
        exit;
    }

    PermissionService::enforce('manage_groups');
    $action = $_POST['action'] ?? '';

    if ($action === 'save_group') {
        $id          = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string)($_POST['group_id'] ?? ''))));
        $name        = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $range       = trim((string)($_POST['range_label'] ?? 'KH1 - KH100'));
        $user_count  = max(0, (int)($_POST['user_count'] ?? 100));

        if ($id === '' || $name === '') {
            set_flash('error', 'Mã nhóm và Tên nhóm không được để trống.');
            header('Location: groups.php');
            exit;
        }

        $is_edit = isset($_SESSION['ff_data']['groups'][$id]);
        $groupData = [
            'id'          => $id,
            'name'        => $name,
            'description' => $description,
            'range'       => $range,
            'range_label' => $range,
            'user_count'  => $user_count,
            'min_index'   => 1,
            'max_index'   => max(1, $user_count),
        ];

        $_SESSION['ff_data']['groups'][$id] = $groupData;
        DatabaseService::saveGroup($groupData);
        init_session_data(true);

        append_audit_log(
            $is_edit ? 'CẬP_NHẬT_NHÓM' : 'TẠO_NHÓM_MỚI',
            "$name ($id)",
            $is_edit ? 'Cấu hình cũ' : '—',
            "Số lượng: $user_count ($range)",
            'Lưu thông tin nhóm khách hàng vào CSDL MySQL'
        );

        set_flash('success', ($is_edit ? "Đã cập nhật nhóm [$name]" : "Đã tạo nhóm mới [$name]") . ' và lưu vào CSDL MySQL!');
        header('Location: groups.php');
        exit;
    }

    if ($action === 'delete_group') {
        $id = trim((string)($_POST['group_id'] ?? ''));
        if ($id !== '' && isset($_SESSION['ff_data']['groups'][$id])) {
            $oldName = $_SESSION['ff_data']['groups'][$id]['name'] ?? $id;
            unset($_SESSION['ff_data']['groups'][$id]);
            DatabaseService::deleteGroup($id);
            init_session_data(true);

            append_audit_log('XÓA_NHÓM', "$oldName ($id)", 'Tồn tại', 'Đã xóa', 'Xóa nhóm khách hàng khỏi CSDL MySQL');
            set_flash('success', "Đã xóa nhóm [$oldName] khỏi hệ thống và CSDL MySQL.");
        }
        header('Location: groups.php');
        exit;
    }
}

$page_title = 'Nhóm người dùng';
$breadcrumbs = [
    ['label' => 'Trang chủ', 'url' => 'dashboard.php'],
    ['label' => 'Nhóm người dùng', 'url' => ''],
];

require_once __DIR__ . '/includes/header.php';

$groups = $_SESSION['ff_data']['groups'] ?? [];
$can_manage_groups = PermissionService::currentUserCan('manage_groups');
$all_users = generate_fake_users();
$selected_group = trim((string)($_GET['group'] ?? ''));
if ($selected_group !== '') {
    $all_users = array_values(array_filter($all_users, static function (array $user) use ($selected_group): bool {
        return ($user['group_id'] ?? '') === $selected_group;
    }));
}
$users_pagination = paginate_items($all_users, (int)($_GET['page'] ?? 1), 25);
$page_users = $users_pagination['items'];
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Quản lý nhóm khách hàng</h1>
    <p class="page-description">
      Phân tập 1.000 mã khách hàng (KH1 &rarr; KH1000) vào các nhóm đối tượng phục vụ thử nghiệm tính năng (đồng bộ trực tiếp bảng <code>target_groups</code> và <code>customers</code> trong MySQL).
    </p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" onclick="openGroupModal()" <?= $can_manage_groups ? '' : 'disabled' ?>>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
      </svg>
      <span>Tạo nhóm mới</span>
    </button>
  </div>
</div>

<!-- Lưới Nhóm Người Dùng -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
  <?php foreach ($groups as $grp_id => $group): ?>
    <div class="card">
      <div class="card-header">
        <span class="font-mono"><?= e((string)$grp_id) ?></span>
        <div style="display:flex; align-items:center; gap:0.5rem;">
          <span class="badge badge-success"><?= (int)($group['user_count'] ?? 0) ?> người dùng</span>
          <?php if ($can_manage_groups): ?>
            <button
              type="button"
              class="action-icon-btn action-edit"
              style="width:26px; height:26px;"
              title="Sửa nhóm"
              onclick='openGroupModal(<?= json_encode([
                  "id" => $grp_id,
                  "name" => $group["name"] ?? "",
                  "description" => $group["description"] ?? "",
                  "range" => $group["range"] ?? ($group["range_label"] ?? ""),
                  "user_count" => (int)($group["user_count"] ?? 0),
              ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'
            >
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
              </svg>
            </button>
            <?php if (!in_array($grp_id, ['nhom_noi_bo', 'nhom_noibu', 'nhom_beta', 'nhom_chung'], true)): ?>
              <form method="POST" style="margin:0;" onsubmit="return confirm('Xóa nhóm khách hàng này khỏi hệ thống và CSDL MySQL?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_group">
                <input type="hidden" name="group_id" value="<?= e((string)$grp_id) ?>">
                <button type="submit" class="action-icon-btn action-delete" style="width:26px; height:26px;" title="Xóa nhóm">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                  </svg>
                </button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body">
        <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">
          <?= e($group['name'] ?? '') ?>
        </h3>
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1rem; min-height: 42px;">
          <?= e($group['description'] ?? '') ?>
        </p>

        <div style="background-color: var(--bg-body); border-radius: var(--radius-md); padding: 0.85rem; font-size: 0.82rem; margin-bottom: 1rem;">
          <div style="color: var(--text-muted); margin-bottom: 0.25rem;">Dải mã người dùng:</div>
          <strong class="font-mono" style="color: var(--primary); font-size: 0.9rem;"><?= e($group['range'] ?? ($group['range_label'] ?? '')) ?></strong>
        </div>

        <a href="groups.php?group=<?= urlencode((string)$grp_id) ?>" class="btn btn-secondary btn-sm" style="width: 100%;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Xem thành viên
        </a>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Tra cứu mã khách hàng trong 1.000 mã giả lập -->
<div class="card">
  <div class="card-header">
    <div>
      <h2 class="card-title">Tra cứu danh sách 1.000 mã khách hàng giả định</h2>
      <div class="card-subtitle">Hệ thống đồng bộ trực tiếp 1.000 mã KH1 &ndash; KH1000 từ bảng <code>customers</code></div>
    </div>
    <div class="search-input-wrapper">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
      </svg>
      <input type="text" id="userSearchInput" class="form-control" placeholder="Nhập mã khách hàng cần tìm">
    </div>
  </div>

  <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
    <table class="data-table" id="usersTable">
      <thead style="position: sticky; top: 0; z-index: 10;">
        <tr>
          <th>Mã khách hàng</th>
          <th>Tên người dùng</th>
          <th>Email demo</th>
          <th>Nhóm trực thuộc</th>
          <th style="text-align: right;">Thử nghiệm tính năng</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($page_users as $usr): ?>
          <tr data-group="<?= e($usr['group_id']) ?>">
            <td><span class="font-mono"><?= e($usr['id']) ?></span></td>
            <td><strong style="color: var(--text-main);"><?= e($usr['name']) ?></strong></td>
            <td><span style="color: var(--text-secondary);"><?= e($usr['email']) ?></span></td>
            <td><span class="badge badge-neutral"><?= e($usr['group_name']) ?></span></td>
            <td style="text-align: right;">
              <a href="demo-app.php?user_id=<?= urlencode($usr['id']) ?>" class="btn btn-secondary btn-sm">
                Mở ứng dụng
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer" style="font-size: 0.82rem; color: var(--text-muted);">
    Hiển thị <?= $users_pagination['start'] ?> - <?= $users_pagination['end'] ?> trong tổng số <?= $users_pagination['total'] ?> khách hàng<?= $selected_group !== '' ? ' của nhóm ' . e($selected_group) : '' ?>.
    <?= $selected_group === '' ? 'Sử dụng ô tìm kiếm để tra cứu bất kỳ mã nào từ KH1 đến KH1000.' : '<a href="groups.php">Xem tất cả khách hàng</a>.' ?>
  </div>
  <?= render_pagination($users_pagination['total'], $users_pagination['page'], 25, 'groups.php', 'khách hàng') ?>
</div>

<!-- Modal Tạo / Sửa Nhóm Khách Hàng -->
<div id="groupModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.45); z-index:9999; align-items:center; justify-content:center; padding:1rem;">
  <div class="card" style="width:100%; max-width:480px; box-shadow:0 20px 40px rgba(15,23,42,0.18);">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <h3 class="card-title" id="groupModalTitle">Tạo nhóm khách hàng mới</h3>
      <button type="button" onclick="closeGroupModal()" style="background:none; border:none; font-size:1.25rem; cursor:pointer; color:var(--text-muted);">&times;</button>
    </div>
    <form method="POST" action="groups.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_group">

      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Mã nhóm định danh (Group ID)</label>
          <input type="text" name="group_id" id="modalGroupId" class="form-control" required placeholder="VD: nhom_vip">
        </div>
        <div class="form-group">
          <label class="form-label">Tên nhóm khách hàng</label>
          <input type="text" name="name" id="modalGroupName" class="form-control" required placeholder="VD: Khách hàng VIP">
        </div>
        <div class="form-group">
          <label class="form-label">Mô tả nhóm</label>
          <textarea name="description" id="modalGroupDesc" class="form-control" rows="2" placeholder="Mô tả đối tượng khách hàng trong nhóm"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Dải mã khách hàng áp dụng</label>
          <input type="text" name="range_label" id="modalGroupRange" class="form-control" placeholder="VD: KH1 -> KH100" value="KH1 -> KH100">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Số lượng người dùng dự kiến</label>
          <input type="number" name="user_count" id="modalGroupCount" class="form-control" min="0" max="10000" value="100">
        </div>
      </div>

      <div class="card-footer" style="display:flex; justify-content:flex-end; gap:0.65rem; padding:1rem 1.25rem; border-top:1px solid var(--border-color);">
        <button type="button" class="btn btn-secondary" onclick="closeGroupModal()">Hủy</button>
        <button type="submit" class="btn btn-primary">Lưu & Đồng bộ MySQL</button>
      </div>
    </form>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    initTableSearch('userSearchInput', 'usersTable');
  });

  function openGroupModal(data) {
    const modal = document.getElementById('groupModal');
    const title = document.getElementById('groupModalTitle');
    const idInput = document.getElementById('modalGroupId');
    if (data) {
      title.textContent = 'Chỉnh sửa nhóm khách hàng';
      idInput.value = data.id || '';
      idInput.readOnly = true;
      document.getElementById('modalGroupName').value = data.name || '';
      document.getElementById('modalGroupDesc').value = data.description || '';
      document.getElementById('modalGroupRange').value = data.range || '';
      document.getElementById('modalGroupCount').value = data.user_count ?? 100;
    } else {
      title.textContent = 'Tạo nhóm khách hàng mới';
      idInput.value = '';
      idInput.readOnly = false;
      document.getElementById('modalGroupName').value = '';
      document.getElementById('modalGroupDesc').value = '';
      document.getElementById('modalGroupRange').value = 'KH1 -> KH100';
      document.getElementById('modalGroupCount').value = 100;
    }
    modal.style.display = 'flex';
  }

  function closeGroupModal() {
    document.getElementById('groupModal').style.display = 'none';
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
