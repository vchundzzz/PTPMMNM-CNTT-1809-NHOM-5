<?php
/**
 * Web Feature Flag Management System
 * groups.php - Quản lý nhóm khách hàng & Tài khoản khách hàng đã đăng ký
 * Đồng bộ 2 chiều thời gian thực với bảng `target_groups` và `customers` trong CSDL MySQL
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/services/PermissionService.php';
require_once __DIR__ . '/services/DatabaseService.php';

require_login();

// Xử lý tạo / sửa / xóa nhóm khách hàng hoặc đổi nhóm của khách hàng trong CSDL MySQL
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

        if ($id === '' || $name === '') {
            set_flash('error', 'Mã nhóm và Tên nhóm không được để trống.');
            header('Location: groups.php');
            exit;
        }

        $is_edit = isset($_SESSION['ff_data']['groups'][$id]);
        $existing = $_SESSION['ff_data']['groups'][$id] ?? [];
        $groupData = [
            'id'          => $id,
            'key'         => $existing['key'] ?? preg_replace('/^nhom_/', '', $id),
            'name'        => $name,
            'description' => $description,
            'range'       => $existing['range'] ?? 'Chưa có khách hàng',
            'range_label' => $existing['range'] ?? 'Chưa có khách hàng',
            'user_count'  => (int)($existing['user_count'] ?? 0),
        ];

        $_SESSION['ff_data']['groups'][$id] = $groupData;
        DatabaseService::saveGroup($groupData);
        init_session_data(true);

        append_audit_log(
            $is_edit ? 'CẬP_NHẬT_NHÓM' : 'TẠO_NHÓM_MỚI',
            "$name ($id)",
            $is_edit ? 'Cấu hình cũ' : '—',
            "Nhóm: $name",
            'Lưu thông tin nhóm khách hàng vào CSDL MySQL'
        );

        set_flash('success', ($is_edit ? "Đã cập nhật nhóm [$name]" : "Đã tạo nhóm mới [$name]") . ' và đồng bộ CSDL MySQL!');
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

    if ($action === 'change_customer_group') {
        $customer_id = trim((string)($_POST['customer_id'] ?? ''));
        $new_group   = trim((string)($_POST['group_id'] ?? ''));
        if ($customer_id !== '' && isset($_SESSION['ff_data']['groups'][$new_group])) {
            DatabaseService::updateCustomerGroup($customer_id, $new_group);
            init_session_data(true);
            $grpName = $_SESSION['ff_data']['groups'][$new_group]['name'] ?? $new_group;
            append_audit_log('ĐỔI_NHÓM_KHÁCH_HÀNG', $customer_id, 'Nhóm cũ', $grpName, "Chuyển khách hàng $customer_id sang nhóm $grpName");
            set_flash('success', "Đã chuyển mã khách hàng [$customer_id] sang nhóm [$grpName] và đồng bộ MySQL!");
        }
        header('Location: groups.php' . (!empty($_GET['group']) ? '?group=' . urlencode((string)$_GET['group']) : ''));
        exit;
    }

    if ($action === 'delete_customer') {
        $customer_id = trim((string)($_POST['customer_id'] ?? ''));
        if ($customer_id !== '') {
            DatabaseService::deleteCustomer($customer_id);
            init_session_data(true);
            append_audit_log('XÓA_KHÁCH_HÀNG', $customer_id, 'Tồn tại', 'Đã xóa', "Xóa tài khoản khách hàng $customer_id");
            set_flash('success', "Đã xóa mã khách hàng [$customer_id] và cập nhật lại số lượng nhóm.");
        }
        header('Location: groups.php' . (!empty($_GET['group']) ? '?group=' . urlencode((string)$_GET['group']) : ''));
        exit;
    }
}

$page_title = 'Nhóm khách hàng';
$breadcrumbs = [
    ['label' => 'Trang chủ', 'url' => 'dashboard.php'],
    ['label' => 'Nhóm khách hàng', 'url' => ''],
];

require_once __DIR__ . '/includes/header.php';

DatabaseService::syncGroupCounts();
init_session_data(true);

$groups = $_SESSION['ff_data']['groups'] ?? [];
$can_manage_groups = PermissionService::currentUserCan('manage_groups');
$all_users = DatabaseService::loadCustomers() ?? [];

// Tính toán số lượng thực tế từng nhóm từ danh sách tài khoản đã đăng ký
$group_counts = [];
$group_codes  = [];
foreach ($groups as $gid => $g) {
    $group_counts[$gid] = 0;
    $group_codes[$gid]  = [];
}
foreach ($all_users as $u) {
    $ugid = $u['group_id'] ?? 'nhom_chung';
    if (!isset($group_counts[$ugid])) {
        $group_counts[$ugid] = 0;
        $group_codes[$ugid]  = [];
    }
    $group_counts[$ugid]++;
    $group_codes[$ugid][] = $u['id'];
}

$selected_group = trim((string)($_GET['group'] ?? ''));
$filtered_users = $all_users;
if ($selected_group !== '') {
    $filtered_users = array_values(array_filter($all_users, static function (array $user) use ($selected_group): bool {
        return ($user['group_id'] ?? '') === $selected_group;
    }));
}
$users_pagination = paginate_items($filtered_users, (int)($_GET['page'] ?? 1), 25);
$page_users = $users_pagination['items'];
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Quản lý nhóm khách hàng</h1>
    <p class="page-description">
      Quản lý các nhóm đối tượng và tài khoản khách hàng thực tế. Khi khách hàng đăng ký tài khoản mới, hệ thống tự động cấp mã khách hàng và đồng bộ trực tiếp vào đây.
    </p>
  </div>
  <div class="page-actions" style="display:flex; gap:0.5rem;">
    <a href="store-auth.php?mode=register" target="_blank" rel="noopener" class="btn btn-secondary">
      <span>+ Đăng ký tài khoản KH mới ↗</span>
    </a>
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
  <?php foreach ($groups as $grp_id => $group):
      $real_count = $group_counts[$grp_id] ?? 0;
      $codes_list = $group_codes[$grp_id] ?? [];
      if ($real_count === 0) {
          $codes_display = 'Chưa có khách hàng đăng ký';
      } elseif ($real_count <= 6) {
          $codes_display = implode(', ', $codes_list);
      } else {
          $codes_display = implode(', ', array_slice($codes_list, 0, 5)) . ' ... (+' . ($real_count - 5) . ' mã KH)';
      }
  ?>
    <div class="card" style="<?= $selected_group === (string)$grp_id ? 'border-color: var(--primary); box-shadow: 0 0 0 2px rgba(22,119,255,0.12);' : '' ?>">
      <div class="card-header">
        <span class="font-mono"><?= e((string)$grp_id) ?></span>
        <div style="display:flex; align-items:center; gap:0.5rem;">
          <span class="badge <?= $real_count > 0 ? 'badge-success' : 'badge-neutral' ?>"><?= $real_count ?> khách hàng</span>
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
          <div style="color: var(--text-muted); margin-bottom: 0.25rem;">Mã khách hàng trong nhóm:</div>
          <strong class="font-mono" style="color: var(--primary); font-size: 0.9rem;"><?= e($codes_display) ?></strong>
        </div>

        <?php if ($selected_group === (string)$grp_id): ?>
          <a href="groups.php" class="btn btn-primary btn-sm" style="width: 100%;">
            Đang lọc nhóm này &bull; Xem tất cả
          </a>
        <?php else: ?>
          <a href="groups.php?group=<?= urlencode((string)$grp_id) ?>" class="btn btn-secondary btn-sm" style="width: 100%;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Xem thành viên (<?= $real_count ?>)
          </a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Danh sách khách hàng thực tế đã đăng ký -->
<div class="card">
  <div class="card-header">
    <div>
      <h2 class="card-title">Danh sách tài khoản khách hàng đã đăng ký</h2>
      <div class="card-subtitle">
        Đồng bộ trực tiếp với các tài khoản đăng ký tại CDS Store (Tổng cộng: <strong><?= count($all_users) ?></strong> khách hàng)
      </div>
    </div>
    <div class="search-input-wrapper">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
      </svg>
      <input type="text" id="userSearchInput" class="form-control" placeholder="Tìm theo mã KH hoặc tên tài khoản...">
    </div>
  </div>

  <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
    <table class="data-table" id="usersTable">
      <thead style="position: sticky; top: 0; z-index: 10;">
        <tr>
          <th>Mã khách hàng</th>
          <th>Tài khoản đăng nhập</th>
          <th>Nhóm khách hàng</th>
          <th>Ngày đăng ký</th>
          <th style="text-align: right;">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($page_users)): ?>
          <tr>
            <td colspan="5" style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
              <strong style="display:block; color: var(--text-main); font-size: 0.95rem; margin-bottom: 0.35rem;">
                Chưa có tài khoản khách hàng nào<?= $selected_group !== '' ? ' trong nhóm này' : ' đăng ký' ?>
              </strong>
              <span>Khi đăng ký tài khoản tại <a href="store-auth.php?mode=register" target="_blank" style="color: var(--primary); font-weight: 600;">trang đăng ký CDS Store</a>, hệ thống sẽ tự động cấp mã khách hàng mới và đồng bộ vào danh sách này.</span>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($page_users as $usr): ?>
            <tr data-group="<?= e($usr['group_id']) ?>">
              <td>
                <span class="font-mono badge badge-primary" style="font-size: 0.82rem; font-weight: 700;">
                  <?= e($usr['id']) ?>
                </span>
              </td>
              <td>
                <strong style="color: var(--text-main);"><?= e($usr['username'] ?? $usr['name']) ?></strong>
              </td>
              <td>
                <?php if ($can_manage_groups): ?>
                  <form method="POST" action="groups.php<?= $selected_group !== '' ? '?group=' . urlencode($selected_group) : '' ?>" style="display:inline-flex; align-items:center; gap:0.4rem; margin:0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_customer_group">
                    <input type="hidden" name="customer_id" value="<?= e($usr['id']) ?>">
                    <select name="group_id" class="form-select" style="width:auto; min-height:32px; padding:0.2rem 1.8rem 0.2rem 0.6rem; font-size:0.8rem;" onchange="this.form.submit()">
                      <?php foreach ($groups as $gid => $ginfo): ?>
                        <option value="<?= e((string)$gid) ?>" <?= ($usr['group_id'] ?? '') === (string)$gid ? 'selected' : '' ?>>
                          <?= e($ginfo['name'] ?? (string)$gid) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                <?php else: ?>
                  <span class="badge badge-neutral"><?= e($usr['group_name']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <span style="color: var(--text-muted); font-size: 0.82rem;"><?= format_date($usr['created_at'] ?? '') ?></span>
              </td>
              <td style="text-align: right; white-space: nowrap;">
                <div style="display:inline-flex; align-items:center; gap:0.4rem;">
                  <a href="demo-app.php?user_id=<?= urlencode($usr['id']) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
                    Mở Web CDS STORE ↗
                  </a>
                  <?php if ($can_manage_groups): ?>
                    <form method="POST" action="groups.php<?= $selected_group !== '' ? '?group=' . urlencode($selected_group) : '' ?>" style="margin:0;" onsubmit="return confirm('Xóa tài khoản khách hàng [<?= e($usr['id']) ?>]?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_customer">
                      <input type="hidden" name="customer_id" value="<?= e($usr['id']) ?>">
                      <button type="submit" class="action-icon-btn danger action-delete" title="Xóa khách hàng">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <polyline points="3 6 5 6 21 6"></polyline>
                          <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                        </svg>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer" style="font-size: 0.82rem; color: var(--text-muted);">
    Hiển thị <?= $users_pagination['start'] ?> - <?= $users_pagination['end'] ?> trong tổng số <?= $users_pagination['total'] ?> khách hàng đã đăng ký<?= $selected_group !== '' ? ' thuộc nhóm ' . e($groups[$selected_group]['name'] ?? $selected_group) : '' ?>.
    <?= $selected_group !== '' ? '<a href="groups.php">Xem tất cả khách hàng</a>.' : '' ?>
  </div>
  <?= render_pagination($users_pagination['total'], $users_pagination['page'], 25, 'groups.php', 'khách hàng') ?>
</div>

<!-- Modal Tạo / Sửa Nhóm Khách Hàng -->
<div id="groupModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.45); z-index:9999; align-items:center; justify-content:center; padding:1rem;">
  <div class="card" style="width:100%; max-width:460px; box-shadow:0 20px 40px rgba(15,23,42,0.18);">
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
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Mô tả nhóm</label>
          <textarea name="description" id="modalGroupDesc" class="form-control" rows="3" placeholder="Mô tả đối tượng khách hàng trong nhóm"></textarea>
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
    } else {
      title.textContent = 'Tạo nhóm khách hàng mới';
      idInput.value = '';
      idInput.readOnly = false;
      document.getElementById('modalGroupName').value = '';
      document.getElementById('modalGroupDesc').value = '';
    }
    modal.style.display = 'flex';
  }

  function closeGroupModal() {
    document.getElementById('groupModal').style.display = 'none';
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
