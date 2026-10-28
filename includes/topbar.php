<?php
/**
 * Hệ thống Quản lý Feature Flags
 * includes/topbar.php – Thanh công cụ phía trên
 */
declare(strict_types=1);

$current_env = get_active_env();
$breadcrumbs = $breadcrumbs ?? [
    ['label' => 'Trang chủ', 'url' => 'dashboard.php'],
    ['label' => $page_title ?? 'Tổng quan', 'url' => ''],
];
$env_label = $current_env === 'testing' ? 'Kiểm thử' : 'Vận hành';
?>
<header class="app-topbar">
  <!-- Trái: toggle + breadcrumb -->
  <div class="topbar-left" style="display:flex;align-items:center;gap:1rem;">
    <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Thu gọn hoặc mở rộng thanh điều hướng" title="Thu gọn / mở rộng thanh điều hướng">
      <!-- menu icon -->
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
    </button>

    <nav class="breadcrumb-nav">
      <?php foreach ($breadcrumbs as $i => $crumb): ?>
        <?php if ($i > 0): ?><span class="breadcrumb-separator">/</span><?php endif; ?>
        <?php if (!empty($crumb['url']) && $i < count($breadcrumbs)-1): ?>
          <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
        <?php else: ?>
          <span class="breadcrumb-current"><?= e($crumb['label']) ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
  </div>

  <!-- Phải: môi trường + user menu -->
  <div class="topbar-right">
    <!-- Bộ chọn môi trường -->
    <div class="env-selector-wrapper">
      <a href="?switch_env=testing" class="env-tab-btn <?= $current_env==='testing' ? 'active env-testing' : '' ?>">
        <span class="dot"></span><span>Kiểm thử</span>
      </a>
      <a href="?switch_env=staging" class="env-tab-btn <?= $current_env==='staging' ? 'active env-staging' : '' ?>">
        <span class="dot"></span><span>Vận hành</span>
      </a>
    </div>

    <!-- Menu tài khoản -->
    <div class="user-menu-dropdown" id="userMenuDropdown">
      <button type="button" class="user-profile-trigger" id="userProfileBtn">
        <div class="user-avatar-circle"><?= e($current_user['avatar'] ?? 'US') ?></div>
        <div style="display:flex;flex-direction:column;text-align:left;line-height:1.2;margin-right:.3rem;">
          <span class="user-name"><?= e($current_user['name'] ?? 'Người dùng') ?></span>
          <span style="font-size:.7rem;color:var(--text-secondary);"><?= render_role_badge($current_user['role'] ?? 'viewer') ?></span>
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--text-muted)"><polyline points="6 9 12 15 18 9"/></svg>
      </button>

      <div class="user-dropdown-menu" id="userDropdownContent">
        <div class="dropdown-header">
          <div style="font-weight:600;font-size:.85rem;"><?= e($current_user['name'] ?? '') ?></div>
          <div style="font-size:.72rem;color:var(--text-secondary);"><?= e($current_user['email'] ?? '') ?></div>
        </div>

        <div class="dropdown-divider"></div>

        <a href="?reset_demo_data=1" class="dropdown-item"
           onclick="return confirm('Khôi phục toàn bộ dữ liệu về trạng thái ban đầu?')">
          <!-- redo icon -->
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>
          <span>Đặt lại dữ liệu demo</span>
        </a>

        <a href="logout.php" class="dropdown-item text-danger">
          <!-- logout icon -->
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          <span>Đăng xuất</span>
        </a>
      </div>
    </div>
  </div>
</header>
