<?php
/**
 * Web Feature Flag Management System
 * members.php - Quản lý thành viên & Bảng ma trận phân quyền (RBAC)
 */

declare(strict_types=1);

$page_title = 'Thành viên & Phân quyền';
$breadcrumbs = [
    ['label' => 'Trang chủ', 'url' => 'dashboard.php'],
    ['label' => 'Thành viên & Phân quyền', 'url' => ''],
];

require_once __DIR__ . '/includes/header.php';

$members = $_SESSION['ff_data']['members'] ?? [];
$matrix = PermissionService::getPermissionsMatrix();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Thành viên & phân quyền</h1>
    <p class="page-description">
      Quản lý tài khoản truy cập hệ thống và ma trận quyền hạn giữa các vai trò Admin, Editor và Viewer.
    </p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" onclick="alert('Chức năng thêm thành viên sẽ được kết nối chi tiết ở Giai đoạn 2.')" <?= PermissionService::currentUserCan('manage_members') ? '' : 'disabled' ?>>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
      </svg>
      <span>Thêm thành viên</span>
    </button>
  </div>
</div>

<!-- Bảng danh sách thành viên Demo -->
<div class="card" style="margin-bottom: 2rem;">
  <div class="card-header">
    <h2 class="card-title">Danh sách tài khoản hệ thống</h2>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Thành viên</th>
          <th>Tên đăng nhập</th>
          <th>Email</th>
          <th>Vai trò</th>
          <th>Lần đăng nhập cuối</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $username => $member): ?>
          <tr>
            <td>
              <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="user-avatar-circle"><?= e($member['avatar']) ?></div>
                <div style="font-weight: 600; color: var(--text-main);"><?= e($member['name']) ?></div>
              </div>
            </td>
            <td>
              <span class="font-mono"><?= e($username) ?></span>
            </td>
            <td>
              <span style="color: var(--text-secondary);"><?= e($member['email']) ?></span>
            </td>
            <td>
              <?= render_role_badge($member['role']) ?>
            </td>
            <td>
              <span style="font-size: 0.8rem; color: var(--text-muted);"><?= format_date($member['last_login']) ?></span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Bảng Ma trận Quyền hạn (Permissions Matrix) -->
<div class="card">
  <div class="card-header">
    <div>
      <h2 class="card-title">Ma trận quyền hạn chi tiết (Phía Server kiểm tra)</h2>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
