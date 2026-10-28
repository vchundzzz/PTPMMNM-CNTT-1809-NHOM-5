<?php
/**
 * Web Feature Flag Management System
 * members.php - Quản lý thành viên & Bảng ma trận phân quyền (RBAC)
 * Đồng bộ 2 chiều thời gian thực với bảng `members` trong CSDL MySQL
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/services/PermissionService.php';
require_once __DIR__ . '/services/DatabaseService.php';

require_login();

// Xử lý thêm / sửa / xóa thành viên và đồng bộ trực tiếp vào MySQL
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Mã xác thực CSRF không hợp lệ.');
        header('Location: members.php');
        exit;
    }

    PermissionService::enforce('manage_members');
    $action = $_POST['action'] ?? '';

    if ($action === 'save_member') {
        $id            = trim((string)($_POST['member_id'] ?? ''));
        $orig_username = trim((string)($_POST['orig_username'] ?? ''));
        $username      = trim((string)($_POST['username'] ?? ''));
        $password      = (string)($_POST['password'] ?? '');
        $name          = trim((string)($_POST['name'] ?? ''));
        $email         = trim((string)($_POST['email'] ?? ''));
        $role          = trim((string)($_POST['role'] ?? 'viewer'));

        if ($username === '' || $password === '' || $name === '') {
            set_flash('error', 'Vui lòng nhập đầy đủ Tên đăng nhập, Mật khẩu và Họ tên.');
            header('Location: members.php');
            exit;
        }

        if (!in_array($role, ['admin', 'editor', 'viewer'], true)) {
            $role = 'viewer';
        }
        $role_titles = [
            'admin'  => 'Admin',
            'editor' => 'Biên tập',
            'viewer' => 'Viewer',
        ];
        $role_title = $role_titles[$role] ?? 'Viewer';

        $existing = ($orig_username !== '' && isset($_SESSION['ff_data']['members'][$orig_username]))
            ? $_SESSION['ff_data']['members'][$orig_username]
            : null;

        if ($id === '') {
            $id = $existing['id'] ?? ('usr_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $username)));
        }

        $words = preg_split('/\s+/', $name) ?: [$username];
        $avatar = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
        if ($avatar === '') {
            $avatar = strtoupper(substr($username, 0, 1));
        }

        $memberData = [
            'id'         => $id,
            'username'   => $username,
            'password'   => $password,
            'name'       => $name,
            'email'      => $email !== '' ? $email : ($username . '@featuremanager.local'),
            'role'       => $role,
            'role_title' => $role_title,
            'avatar'     => $existing['avatar'] ?? $avatar,
            'created_at' => $existing['created_at'] ?? date('Y-m-d H:i:s'),
            'last_login' => $existing['last_login'] ?? date('Y-m-d H:i:s'),
        ];

        // Nếu đổi username, xóa key cũ trong session
        if ($orig_username !== '' && $orig_username !== $username) {
            unset($_SESSION['ff_data']['members'][$orig_username]);
        }
        $_SESSION['ff_data']['members'][$username] = $memberData;

        // Lưu trực tiếp vào bảng members trong CSDL MySQL
        DatabaseService::saveMember($memberData);
        init_session_data(true);

        append_audit_log(
            $existing ? 'CẬP_NHẬT_THÀNH_VIÊN' : 'THÊM_THÀNH_VIÊN',
            $username,
            $existing ? ($orig_username . ' (' . ($existing['role'] ?? '') . ')') : '—',
            $username . ' (' . $role . ')',
            'Đồng bộ tài khoản thành viên vào CSDL MySQL'
        );

        set_flash('success', 'Đã lưu thông tin thành viên [' . $username . '] và đồng bộ vào CSDL MySQL!');
        header('Location: members.php');
        exit;
    }

    if ($action === 'delete_member') {
        $del_user = trim((string)($_POST['username'] ?? ''));
        $del_id   = trim((string)($_POST['member_id'] ?? ''));
        $cur_user = get_authenticated_user()['username'] ?? '';

        if ($del_user === $cur_user) {
            set_flash('error', 'Không thể xóa tài khoản đang đăng nhập.');
            header('Location: members.php');
            exit;
        }

        if ($del_user !== '' && isset($_SESSION['ff_data']['members'][$del_user])) {
            unset($_SESSION['ff_data']['members'][$del_user]);
        }
        DatabaseService::deleteMember($del_id !== '' ? $del_id : $del_user);
        init_session_data(true);

        append_audit_log(
            'XÓA_THÀNH_VIÊN',
            $del_user,
            'Hoạt động',
            'Đã xóa',
            'Xóa tài khoản khỏi hệ thống và CSDL MySQL'
        );

        set_flash('success', 'Đã xóa thành viên [' . $del_user . '] khỏi hệ thống và CSDL MySQL.');
        header('Location: members.php');
        exit;
    }
}

$page_title = 'Thành viên & Phân quyền';
$breadcrumbs = [
    ['label' => 'Trang chủ', 'url' => 'dashboard.php'],
    ['label' => 'Thành viên & Phân quyền', 'url' => ''],
];

require_once __DIR__ . '/includes/header.php';

$members = $_SESSION['ff_data']['members'] ?? [];
$matrix = PermissionService::getPermissionsMatrix();
$can_manage = PermissionService::currentUserCan('manage_members');
$can_view_passwords = PermissionService::currentUserCan('view_member_passwords');
$members_pagination = paginate_items($members, (int)($_GET['page'] ?? 1), 10);
$page_members = $members_pagination['items'];
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Thành viên & phân quyền</h1>
    <p class="page-description">
      Quản lý tài khoản truy cập hệ thống (đồng bộ 2 chiều thời gian thực với bảng <code>members</code> trong MySQL) và ma trận quyền hạn RBAC.
    </p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" onclick="openMemberModal()" <?= $can_manage ? '' : 'disabled' ?>>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
      </svg>
      <span>Thêm thành viên</span>
    </button>
  </div>
</div>

<!-- Bảng danh sách thành viên -->
<div class="card" style="margin-bottom: 2rem;">
  <div class="card-header">
    <div>
      <h2 class="card-title">Danh sách tài khoản hệ thống</h2>
      <div class="card-subtitle">Thay đổi mật khẩu hoặc tên đăng nhập tại đây hay trong phpMyAdmin đều đồng bộ tức thì</div>
    </div>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Thành viên</th>
          <th>Tên đăng nhập</th>
          <th>Mật khẩu</th>
          <th>Email</th>
          <th>Vai trò</th>
          <th>Lần đăng nhập cuối</th>
          <?php if ($can_manage): ?>
            <th style="text-align: center;">Thao tác</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($page_members as $username => $member): ?>
          <tr>
            <td>
              <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="user-avatar-circle"><?= e($member['avatar'] ?? 'U') ?></div>
                <div>
                  <div style="font-weight: 600; color: var(--text-main);"><?= e($member['name'] ?? $username) ?></div>
                  <div style="font-size: 0.72rem; color: var(--text-muted);" class="font-mono"><?= e($member['id'] ?? '') ?></div>
                </div>
              </div>
            </td>
            <td>
              <span class="font-mono" style="font-weight: 600; color: var(--primary);"><?= e((string)($member['username'] ?? $username)) ?></span>
            </td>
            <td>
              <?php if ($can_view_passwords): ?>
              <div style="display: inline-flex; align-items: center; gap: 0.4rem;">
                <span class="font-mono pwd-mask" data-pwd="<?= e((string)($member['password'] ?? '')) ?>">••••••••</span>
                <button type="button" class="action-icon-btn" style="width: 26px; height: 26px;" data-member-password="<?= e((string)($member['password'] ?? '')) ?>" onclick="togglePwdView(this)" title="Hiện / Ẩn mật khẩu">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </button>
              </div>
              <?php else: ?>
                <div style="display: inline-flex; align-items: center; gap: 0.4rem;">
                  <span class="font-mono pwd-mask">••••••••</span>
                  <button type="button" class="action-icon-btn" style="width: 26px; height: 26px;" onclick="togglePwdView(this, false)" title="Hiện / Ẩn mật khẩu">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                  </button>
                </div>
              <?php endif; ?>
            </td>
            <td>
              <span style="color: var(--text-secondary);"><?= e($member['email'] ?? '') ?></span>
            </td>
            <td>
              <?= render_role_badge($member['role'] ?? 'viewer') ?>
            </td>
            <td>
              <span style="font-size: 0.8rem; color: var(--text-muted);"><?= format_date($member['last_login'] ?? '') ?></span>
            </td>
            <?php if ($can_manage): ?>
              <td style="text-align: center;">
                <div style="display: inline-flex; gap: 0.35rem;">
                  <button
                    type="button"
                    class="action-icon-btn action-edit"
                    title="Sửa thông tin / Đổi mật khẩu"
                    onclick='openMemberModal(<?= json_encode([
                        "id" => $member["id"] ?? "",
                        "username" => $member["username"] ?? $username,
                        "password" => $can_view_passwords ? ($member["password"] ?? "") : "",
                        "name" => $member["name"] ?? "",
                        "email" => $member["email"] ?? "",
                        "role" => $member["role"] ?? "viewer",
                    ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>)'
                  >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                  </button>
                  <?php if (($member['username'] ?? $username) !== ($current_user['username'] ?? '')): ?>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Bạn có chắc muốn xóa tài khoản này khỏi hệ thống và MySQL?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete_member">
                      <input type="hidden" name="member_id" value="<?= e($member['id'] ?? '') ?>">
                      <input type="hidden" name="username" value="<?= e((string)($member['username'] ?? $username)) ?>">
                      <button type="submit" class="action-icon-btn action-delete" title="Xóa thành viên">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <polyline points="3 6 5 6 21 6"></polyline>
                          <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                        </svg>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= render_pagination($members_pagination['total'], $members_pagination['page'], 10, 'members.php', 'thành viên') ?>
</div>

<!-- Bảng Ma trận Quyền hạn (Permissions Matrix) -->
<div class="card">
  <div class="card-header">
    <div>
      <h2 class="card-title">Ma trận quyền hạn chi tiết</h2>
      <div class="card-subtitle">Quy tắc phân quyền được thực thi chặt chẽ ở lớp dịch vụ PermissionService.php</div>
    </div>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Hành động / Quyền hạn</th>
          <th style="text-align: center;">Admin</th>
          <th style="text-align: center;">Editor</th>
          <th style="text-align: center;">Viewer</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($matrix as $action_key => $action_info): ?>
          <tr>
            <td>
              <div style="font-weight: 600; color: var(--text-main);"><?= e($action_info['name']) ?></div>
              <div style="font-size: 0.78rem; color: var(--text-muted);"><?= e($action_info['description']) ?></div>
            </td>
            <td style="text-align: center;">
              <?php if (PermissionService::can('admin', $action_key)): ?>
                <span style="color: var(--success); font-weight: 700; font-size: 1.1rem;">&check;</span>
              <?php else: ?>
                <span style="color: var(--text-light);">&ndash;</span>
              <?php endif; ?>
            </td>
            <td style="text-align: center;">
              <?php if (PermissionService::can('editor', $action_key)): ?>
                <span style="color: var(--success); font-weight: 700; font-size: 1.1rem;">&check;</span>
              <?php else: ?>
                <span style="color: var(--text-light);">&ndash;</span>
              <?php endif; ?>
            </td>
            <td style="text-align: center;">
              <?php if (PermissionService::can('viewer', $action_key)): ?>
                <span style="color: var(--success); font-weight: 700; font-size: 1.1rem;">&check;</span>
              <?php else: ?>
                <span style="color: var(--text-light);">&ndash;</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Thêm / Sửa Thành Viên -->
<div id="memberModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.45); z-index:9999; align-items:center; justify-content:center; padding:1rem;">
  <div class="card" style="width:100%; max-width:480px; box-shadow:0 20px 40px rgba(15,23,42,0.18);">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
      <h3 class="card-title" id="memberModalTitle">Chỉnh sửa thành viên</h3>
      <button type="button" onclick="closeMemberModal()" style="background:none; border:none; font-size:1.25rem; cursor:pointer; color:var(--text-muted);">&times;</button>
    </div>
    <form method="POST" action="members.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_member">
      <input type="hidden" name="member_id" id="modalMemberId" value="">
      <input type="hidden" name="orig_username" id="modalOrigUsername" value="">

      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Tên đăng nhập (Username)</label>
          <input type="text" name="username" id="modalUsername" class="form-control" required placeholder="VD: admin">
        </div>
        <div class="form-group">
          <label class="form-label">Mật khẩu (Password)</label>
          <input type="text" name="password" id="modalPassword" class="form-control" required placeholder="Nhập mật khẩu">
        </div>
        <div class="form-group">
          <label class="form-label">Họ và tên hiển thị</label>
          <input type="text" name="name" id="modalName" class="form-control" required placeholder="VD: Quản trị viên">
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" id="modalEmail" class="form-control" placeholder="VD: admin@featuremanager.local">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label class="form-label">Vai trò phân quyền (RBAC)</label>
          <select name="role" id="modalRole" class="form-control">
            <option value="admin">Admin (Toàn quyền hệ thống)</option>
            <option value="editor">Editor (Biên tập viên cấu hình)</option>
            <option value="viewer">Viewer (Chỉ xem)</option>
          </select>
        </div>
      </div>

      <div class="card-footer" style="display:flex; justify-content:flex-end; gap:0.65rem; padding:1rem 1.25rem; border-top:1px solid var(--border-color);">
        <button type="button" class="btn btn-secondary" onclick="closeMemberModal()">Hủy</button>
        <button type="submit" class="btn btn-primary">Lưu & Đồng bộ MySQL</button>
      </div>
    </form>
  </div>
</div>

<script>
function togglePwdView(btn, canView = true) {
  if (!canView) {
    alert('Vai trò tài khoản không có quyền xem mật khẩu.');
    return;
  }
  const span = btn.parentElement.querySelector('.pwd-mask');
  if (!span) return;
  const real = btn.getAttribute('data-member-password') || '';
  if (span.textContent === '••••••••') {
    span.textContent = real;
  } else {
    span.textContent = '••••••••';
  }
}

function openMemberModal(data) {
  const modal = document.getElementById('memberModal');
  const title = document.getElementById('memberModalTitle');
  if (data) {
    title.textContent = 'Sửa thông tin / Đổi mật khẩu';
    document.getElementById('modalMemberId').value = data.id || '';
    document.getElementById('modalOrigUsername').value = data.username || '';
    document.getElementById('modalUsername').value = data.username || '';
    document.getElementById('modalPassword').value = data.password || '';
    document.getElementById('modalName').value = data.name || '';
    document.getElementById('modalEmail').value = data.email || '';
    document.getElementById('modalRole').value = data.role || 'viewer';
  } else {
    title.textContent = 'Thêm thành viên mới';
    document.getElementById('modalMemberId').value = '';
    document.getElementById('modalOrigUsername').value = '';
    document.getElementById('modalUsername').value = '';
    document.getElementById('modalPassword').value = '';
    document.getElementById('modalName').value = '';
    document.getElementById('modalEmail').value = '';
    document.getElementById('modalRole').value = 'viewer';
  }
  modal.style.display = 'flex';
}

function closeMemberModal() {
  document.getElementById('memberModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
