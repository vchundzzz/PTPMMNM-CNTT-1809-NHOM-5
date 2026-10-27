<?php
/**
 * Web Feature Flag Management System
 * groups.php - Quản lý nhóm người dùng (User Groups)
 */

declare(strict_types=1);

$page_title = 'Nhóm người dùng';
$breadcrumbs = [
    ['label' => 'Trang chủ', 'url' => 'dashboard.php'],
    ['label' => 'Nhóm người dùng', 'url' => ''],
];

require_once __DIR__ . '/includes/header.php';

$groups = $_SESSION['ff_data']['groups'] ?? [];
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
      Phân tập 1.000 mã khách hàng (KH1 &rarr; KH1000) vào 3 nhóm đối tượng phục vụ việc thử nghiệm tính năng có định hướng.
    </p>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" onclick="alert('Chức năng tạo nhóm mới sẽ được mở rộng trong Giai đoạn 2.')" <?= PermissionService::currentUserCan('manage_groups') ? '' : 'disabled' ?>>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
      </svg>
      <span>Tạo nhóm mới</span>
    </button>
  </div>
</div>

<!-- Lưới 3 Nhóm Người Dùng -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
  <?php foreach ($groups as $grp_id => $group): ?>
    <div class="card">
      <div class="card-header">
        <span class="font-mono"><?= e($grp_id) ?></span>
        <span class="badge badge-success"><?= $group['user_count'] ?> người dùng</span>
      </div>
      <div class="card-body">
        <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">
          <?= e($group['name']) ?>
        </h3>
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1rem; min-height: 42px;">
          <?= e($group['description']) ?>
        </p>

        <div style="background-color: var(--bg-body); border-radius: var(--radius-md); padding: 0.85rem; font-size: 0.82rem; margin-bottom: 1rem;">
          <div style="color: var(--text-muted); margin-bottom: 0.25rem;">Dải mã người dùng:</div>
          <strong class="font-mono" style="color: var(--primary); font-size: 0.9rem;"><?= e($group['range']) ?></strong>
        </div>

        <a href="groups.php?group=<?= urlencode($grp_id) ?>" class="btn btn-secondary btn-sm" style="width: 100%;">
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
      <div class="card-subtitle">Hệ thống sinh sẵn 1.000 mã KH1 &ndash; KH1000 cố định theo đặc tả bài tập</div>
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
        <?php 
        foreach ($page_users as $usr):
        ?>
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

<script>
  document.addEventListener('DOMContentLoaded', () => {
    initTableSearch('userSearchInput', 'usersTable');
  });

</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
