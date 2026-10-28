<?php
/**
 * Hệ thống Quản lý Feature Flags
 * includes/sidebar.php – Sidebar điều hướng (icon kiểu Ant Design SVG)
 */
declare(strict_types=1);

$flags_count    = count($_SESSION['ff_data']['flags']    ?? []);
$versions_count = count($_SESSION['ff_data']['versions'] ?? []);
$groups_count   = count($_SESSION['ff_data']['groups']   ?? []);
$cur_page = basename($_SERVER['PHP_SELF'] ?? '');
?>
<aside class="app-sidebar" id="appSidebar">
  <div class="sidebar-header">
    <div class="brand-logo-icon">
      <!-- Ant Design: flag icon -->
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>
      </svg>
    </div>
    <span class="brand-name">Quản lý Tính năng</span>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-title">Tổng quan</div>
    <ul class="nav-list">
      <li>
        <a href="dashboard.php" class="nav-link <?= ($cur_page==='dashboard.php')?'active':'' ?>">
          <!-- dashboard icon -->
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
          <span>Bảng điều khiển</span>
        </a>
      </li>
    </ul>

    <div class="nav-section-title">Hạ tầng & Tổ chức</div>
    <ul class="nav-list">
      <li>
        <a href="projects.php" class="nav-link <?= ($cur_page==='projects.php')?'active':'' ?>">
          <!-- folder icon -->
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
          <span>Dự án</span>
        </a>
      </li>
      <li>
        <a href="environments.php" class="nav-link <?= ($cur_page==='environments.php')?'active':'' ?>">
          <!-- server icon -->
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
          <span>Môi trường</span>
        </a>
      </li>
      <li>
        <a href="setup.php" class="nav-link <?= ($cur_page==='setup.php')?'active':'' ?>">
          <!-- database icon -->
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <ellipse cx="12" cy="5" rx="9" ry="3"/>
            <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
            <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
          </svg>
          <span>Cơ sở dữ liệu (MySQL)</span>
          <span style="margin-left:auto;font-size:.65rem;background:#10b981;color:#fff;padding:.1rem .4rem;border-radius:99px;font-weight:700;">Live</span>
        </a>
      </li>
      <li>
        <a href="groups.php" class="nav-link <?= ($cur_page==='groups.php')?'active':'' ?>">
          <!-- users icon -->
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span>Nhóm khách hàng</span>
          <span style="margin-left:auto;font-size:.68rem;background:rgba(255,255,255,.12);color:#fff;padding:.1rem .4rem;border-radius:99px;"><?= $groups_count ?></span>
        </a>
      </li>
      <li>
        <a href="members.php" class="nav-link <?= ($cur_page==='members.php')?'active':'' ?>">
          <!-- shield icon -->
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          <span>Thành viên & Quyền</span>
        </a>
      </li>
    </ul>

    <div class="nav-section-title">Cấu hình Tính năng</div>
    <ul class="nav-list">
      <li>
        <a href="flags.php" class="nav-link <?= in_array($cur_page,['flags.php','flag-detail.php'])?'active':'' ?>">
          <!-- toggle icon -->
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
          <span>Cờ tính năng</span>
          <span style="margin-left:auto;font-size:.68rem;background:rgba(255,255,255,.12);color:#fff;padding:.1rem .4rem;border-radius:99px;"><?= $flags_count ?></span>
        </a>
      </li>
      <li>
        <a href="versions.php" class="nav-link <?= in_array($cur_page,['versions.php','version-compare.php'])?'active':'' ?>">
          <!-- clock icon -->
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <span>Lịch sử phiên bản</span>
          <span style="margin-left:auto;font-size:.68rem;background:rgba(255,255,255,.12);color:#fff;padding:.1rem .4rem;border-radius:99px;"><?= $versions_count ?></span>
        </a>
      </li>
      <li>
        <a href="audit-logs.php" class="nav-link <?= ($cur_page==='audit-logs.php')?'active':'' ?>">
          <!-- file-text icon -->
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          <span>Nhật ký hoạt động</span>
        </a>
      </li>
      <li>
        <a href="import-export.php" class="nav-link <?= ($cur_page==='import-export.php')?'active':'' ?>">
          <!-- repeat icon -->
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
          <span>Nhập / Xuất cấu hình</span>
        </a>
      </li>
    </ul>

    <div class="nav-section-title">Thử nghiệm</div>
    <ul class="nav-list">
      <li>
        <a href="demo-app.php" class="nav-link <?= ($cur_page==='demo-app.php')?'active':'' ?>" style="color:#69b1ff">
          <!-- shopping-cart icon -->
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
          <span>Web CDS STORE </span>
          <span style="margin-left:auto;font-size:.65rem;background:#ef4444;color:#fff;padding:.1rem .4rem;border-radius:99px;font-weight:700;">AAR</span>
        </a>
      </li>
    </ul>
  </nav>

  <div class="sidebar-footer">
    <div>PTPMMNM – Nhóm 5 – CNTT K18</div>
    <div>Phát triển Phần mềm Mã nguồn Mở</div>
  </div>
</aside>
