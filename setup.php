<?php
/**
 * Hệ thống Quản lý Feature Flags
 * setup.php – Quản Lý Kết Nối & CSDL MySQL (Giao diện đồng bộ Hệ thống Quản trị)
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/services/DatabaseService.php';
require_once __DIR__ . '/services/PermissionService.php';

require_login();

// Xử lý nạp / reset CSDL khi người dùng bấm nút
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Mã bảo mật CSRF không hợp lệ, vui lòng thử lại.');
        header('Location: setup.php');
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    if ($action === 'import_sql') {
        PermissionService::enforce('create_flag');
        $res = DatabaseService::importSqlSchema();
        if ($res['success']) {
            set_flash('success', 'Đã khởi tạo và nạp thành công 11 bảng cùng dữ liệu mẫu từ database.sql!');
        } else {
            set_flash('error', 'Lỗi khởi tạo CSDL: ' . $res['message']);
        }
        header('Location: setup.php');
        exit;
    }
}

// Kiểm tra trạng thái máy chủ MySQL & Database
$serverConnected = Database::getServerConnection() !== null;
$dbConnected     = Database::isConnected();
$stats           = $dbConnected ? DatabaseService::getTableStats() : [];
$lastError       = Database::getLastError();

$totalRows = 0;
foreach ($stats as $s) {
    $totalRows += $s['count'];
}
$totalTables = count($stats);

// Tự động nhận diện cổng phpMyAdmin
$httpHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$hostParts = explode(':', $httpHost);
$currentPort = $hostParts[1] ?? ($_SERVER['SERVER_PORT'] ?? '80');
$pmaPort = ($currentPort !== '80' && !empty($currentPort)) ? ":$currentPort" : "";
$pmaUrl = "http://localhost{$pmaPort}/phpmyadmin/index.php?route=/database/structure&db=feature_flags_db";

// Cấu hình Header và Breadcrumb đồng bộ
$page_title  = 'Cơ sở dữ liệu MySQL';
$breadcrumbs = [
    ['label' => 'Trang chủ', 'url' => 'dashboard.php'],
    ['label' => 'Cơ sở dữ liệu (MySQL)', 'url' => ''],
];

require_once __DIR__ . '/includes/header.php';

// Metadata bổ sung cho từng bảng
$tableMetadata = [
    'projects'            => ['desc' => 'Dự án & Nền tảng thương mại điện tử', 'category' => 'Hạ tầng', 'link' => 'projects.php'],
    'environments'        => ['desc' => 'Môi trường vận hành (Testing / Staging)', 'category' => 'Hạ tầng', 'link' => 'environments.php'],
    'target_groups'       => ['desc' => 'Nhóm khách hàng mục tiêu (Nội bộ, Beta, Thường)', 'category' => 'Khách hàng', 'link' => 'groups.php'],
    'customers'           => ['desc' => 'Danh sách khách hàng mẫu (KH1 - KH1000)', 'category' => 'Khách hàng', 'link' => 'groups.php'],
    'members'             => ['desc' => 'Tài khoản quản trị viên & Phân quyền RBAC', 'category' => 'Quản trị', 'link' => 'members.php'],
    'flags'               => ['desc' => 'Cờ tính năng lõi (10 Feature Flags + Banner)', 'category' => 'Tính năng', 'link' => 'flags.php'],
    'flag_environments'   => ['desc' => 'Cấu hình bật/tắt và rollout theo từng môi trường', 'category' => 'Tính năng', 'link' => 'flags.php'],
    'flag_target_groups'  => ['desc' => 'Phân bổ nhóm khách hàng mục tiêu cho cờ', 'category' => 'Tính năng', 'link' => 'flags.php'],
    'flag_user_overrides' => ['desc' => 'Quy tắc ghi đè cá nhân cho khách hàng mẫu', 'category' => 'Tính năng', 'link' => 'flags.php'],
    'audit_logs'          => ['desc' => 'Nhật ký kiểm toán hoạt động và thay đổi cấu hình', 'category' => 'Kiểm toán', 'link' => 'audit-logs.php'],
    'versions'            => ['desc' => 'Snapshot lịch sử phiên bản cấu hình hệ thống', 'category' => 'Phiên bản', 'link' => 'versions.php'],
];
?>

<style>
/* ============================================================
   DATABASE PAGE STYLES (ĐỒNG BỘ 100% GIAO DIỆN HỆ THỐNG / VERSIONS.PHP)
   ============================================================ */
.db-page { color: #1e293b; }

.db-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}

.db-page-title {
  display: flex;
  align-items: flex-start;
  gap: 0.9rem;
}

.db-title-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  flex: none;
  border-radius: 14px;
  background: #eaf2ff;
  color: #1677ff;
  box-shadow: 0 2px 8px rgba(22, 119, 255, 0.12);
}

.db-title-icon svg { width: 26px; height: 26px; }

.db-page-title h1 {
  margin: 0.1rem 0 0.25rem;
  color: #1e293b;
  font-size: 1.45rem;
  font-weight: 800;
  line-height: 1.25;
  letter-spacing: -0.02em;
}

.db-page-title p {
  max-width: 650px;
  margin: 0;
  color: #718096;
  font-size: 0.82rem;
  line-height: 1.55;
}

.db-page-actions {
  display: flex;
  align-items: center;
  gap: 0.55rem;
  flex-wrap: wrap;
}

.db-page-actions .btn {
  min-height: 38px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.82rem;
  font-weight: 600;
  padding: 0 14px;
  border-radius: 8px;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.db-refresh-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border: 1px solid #dbe3ef;
  border-radius: 9px;
  background: #fff;
  color: #64748b;
  cursor: pointer;
  transition: all 0.2s ease;
}

.db-refresh-btn:hover {
  color: #1677ff;
  border-color: #91caff;
  background: #f8fbff;
}

/* 4 STATS CARDS GRID */
.db-summary {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.8rem;
  margin-bottom: 1.25rem;
}

.db-stat {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  min-height: 88px;
  padding: 0.85rem 1.1rem;
  border: 1px solid #e6edf7;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 2px 8px rgba(30, 64, 120, 0.035);
  transition: all 0.2s ease;
}

.db-stat:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(30, 64, 120, 0.06);
  border-color: #cbd5e1;
}

.db-stat-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  flex: none;
  border-radius: 50%;
}

.db-stat-icon svg { width: 21px; height: 21px; }

.db-stat-icon.blue { background: #e8f1ff; color: #1677ff; }
.db-stat-icon.green { background: #e8f8ee; color: #16a34a; }
.db-stat-icon.purple { background: #f3eaff; color: #9333ea; }
.db-stat-icon.orange { background: #fff1df; color: #f97316; }

.db-stat-label {
  color: #64748b;
  font-size: 0.72rem;
  font-weight: 650;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  margin-bottom: 0.25rem;
}

.db-stat-value {
  color: #1e293b;
  font-size: 1.25rem;
  font-weight: 800;
  line-height: 1.15;
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 0.2rem;
}

.db-stat-note {
  color: #8290a4;
  font-size: 0.68rem;
  display: flex;
  align-items: center;
  gap: 4px;
}

/* PULSE STATUS DOT */
.pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}

.pulse-dot.online {
  background: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
  animation: pulseDot 2s infinite;
}

.pulse-dot.offline {
  background: #ef4444;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
}

@keyframes pulseDot {
  0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); }
  70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
  100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

/* TABLE CARD */
.db-list-card {
  overflow: hidden;
  border: 1px solid #e5eaf1;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 3px 12px rgba(30, 64, 120, 0.04);
}

.db-list-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.8rem;
  padding: 0.9rem 1.1rem;
  border-bottom: 1px solid #e9edf3;
  flex-wrap: wrap;
}

.db-list-heading-left {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.db-list-heading h2 {
  margin: 0;
  color: #243247;
  font-size: 0.92rem;
  font-weight: 700;
}

.db-count-badge {
  background: #f1f5f9;
  color: #475569;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 0.2rem 0.65rem;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
}

.db-table-wrap { overflow-x: auto; }

.db-table {
  width: 100%;
  min-width: 780px;
  border-collapse: collapse;
  text-align: left;
}

.db-table th {
  padding: 0.75rem 0.9rem;
  border-bottom: 1px solid #e5eaf1;
  background: #f8fafc;
  color: #738197;
  font-size: 0.67rem;
  font-weight: 700;
  letter-spacing: 0.045em;
  text-transform: uppercase;
  white-space: nowrap;
}

.db-table td {
  padding: 0.8rem 0.9rem;
  border-bottom: 1px solid #edf1f5;
  color: #56657a;
  font-size: 0.78rem;
  vertical-align: middle;
}

.db-table tbody tr:last-child td { border-bottom: 0; }
.db-table tbody tr { transition: background 0.15s ease; }
.db-table tbody tr:hover { background: #f8fbff; }

.table-code-name {
  color: #1677ff;
  font-size: 0.85rem;
  font-weight: 750;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.table-code-name:hover {
  color: #0958d9;
  text-decoration: underline;
}

.table-desc-text {
  color: #65748a;
  line-height: 1.45;
  font-size: 0.79rem;
}

.badge-status-online {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  background: #ecfdf5;
  color: #059669;
  border: 1px solid #a7f3d0;
  font-size: 0.68rem;
  font-weight: 650;
  white-space: nowrap;
}

.badge-category {
  display: inline-flex;
  padding: 0.18rem 0.5rem;
  border-radius: 6px;
  background: #eff6ff;
  color: #2563eb;
  border: 1px solid #bfdbfe;
  font-size: 0.68rem;
  font-weight: 600;
  white-space: nowrap;
}

.badge-category.client {
  background: #fdf4ff;
  color: #a21caf;
  border-color: #f5d0fe;
}

.badge-category.system {
  background: #fff7ed;
  color: #c2410c;
  border-color: #fed7aa;
}

.badge-rows-count {
  font-weight: 700;
  color: #1e293b;
  font-variant-numeric: tabular-nums;
  background: #f1f5f9;
  padding: 3px 9px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  font-size: 0.72rem;
}

.db-action-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  background: transparent;
  color: #1677ff;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.18s;
  text-decoration: none;
}

.db-action-link:hover {
  background: #eaf2ff;
  border-color: #91caff;
}

.db-table-footer-info {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1.1rem;
  background: #fafbfc;
  border-top: 1px solid #edf1f5;
  font-size: 0.73rem;
  color: #8290a4;
  flex-wrap: wrap;
  gap: 0.5rem;
}

@media (max-width: 960px) {
  .db-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 640px) {
  .db-summary { grid-template-columns: 1fr; }
  .db-page-header { flex-direction: column; align-items: flex-start; }
  .db-page-actions { width: 100%; }
  .db-page-actions form { width: 100%; }
  .db-page-actions .btn { width: 100%; justify-content: center; }
}
</style>

<div class="db-page">
  <!-- PAGE HEADER (ĐỒNG BỘ CHUẨN ANT DESIGN / APPLE DEV) -->
  <div class="page-header db-page-header">
    <div class="db-page-title">
      <div class="db-title-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
          <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
          <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
        </svg>
      </div>
      <div>
        <h1>Cơ sở dữ liệu & Kết nối MySQL</h1>
        <p>Theo dõi trạng thái máy chủ MariaDB / MySQL, cấu trúc 11 bảng dữ liệu và đồng bộ từ database.sql.</p>
      </div>
    </div>

    <div class="db-page-actions">
      <!-- Nút Khởi tạo 1-Click -->
      <form method="POST" onsubmit="return confirm('Bạn có chắc muốn nạp lại CSDL từ database.sql? Thao tác này sẽ làm mới toàn bộ 11 bảng và 1.000 khách hàng!');" style="margin:0;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="import_sql">
        <button type="submit" class="btn btn-primary" title="Tự động import database.sql vào MySQL">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          <span>Khởi tạo / Nạp lại CSDL</span>
        </button>
      </form>

      <!-- Tải file database.sql -->
      <a href="database.sql" download class="btn btn-secondary" title="Tải file database.sql về máy">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
          <polyline points="7 10 12 15 17 10"></polyline>
          <line x1="12" y1="15" x2="12" y2="3"></line>
        </svg>
        <span>Tải database.sql</span>
      </a>

      <!-- Mở phpMyAdmin -->
      <a href="<?= e($pmaUrl) ?>" target="_blank" class="btn btn-secondary" title="Mở phpMyAdmin để xem cấu trúc và chạy truy vấn SQL">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
          <polyline points="15 3 21 3 21 9"></polyline>
          <line x1="10" y1="14" x2="21" y2="3"></line>
        </svg>
        <span>phpMyAdmin</span>
      </a>

      <!-- Nút làm mới trang -->
      <a href="setup.php" class="db-refresh-btn" title="Làm mới trạng thái">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="23 4 23 10 17 10"></polyline>
          <polyline points="1 20 1 14 7 14"></polyline>
          <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
        </svg>
      </a>
    </div>
  </div>

  <!-- 4 THẺ THỐNG KÊ TỔNG QUAN (4 STATS CARDS) -->
  <div class="db-summary">
    <!-- Card 1: Server -->
    <div class="db-stat">
      <div class="db-stat-icon blue">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="2" width="20" height="8" rx="2"></rect>
          <rect x="2" y="14" width="20" height="8" rx="2"></rect>
          <line x1="6" y1="6" x2="6.01" y2="6"></line>
          <line x1="6" y1="18" x2="6.01" y2="18"></line>
        </svg>
      </div>
      <div>
        <div class="db-stat-label">MySQL Server</div>
        <div class="db-stat-value">
          <?php if ($serverConnected): ?>
            <span class="pulse-dot online"></span>
            <span>Port 3306</span>
          <?php else: ?>
            <span class="pulse-dot offline"></span>
            <span style="color:#ef4444;">Offline</span>
          <?php endif; ?>
        </div>
        <div class="db-stat-note">
          <?= $serverConnected ? 'Đang chạy trực tuyến' : 'Chưa bật XAMPP MySQL' ?>
        </div>
      </div>
    </div>

    <!-- Card 2: Database -->
    <div class="db-stat">
      <div class="db-stat-icon green">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
          <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
      </div>
      <div>
        <div class="db-stat-label">Database MariaDB</div>
        <div class="db-stat-value" style="color: <?= $dbConnected ? '#16a34a' : '#ef4444' ?>;">
          <?= $dbConnected ? 'Đã kết nối' : 'Chưa kết nối' ?>
        </div>
        <div class="db-stat-note">feature_flags_db (utf8mb4)</div>
      </div>
    </div>

    <!-- Card 3: Total Rows -->
    <div class="db-stat">
      <div class="db-stat-icon purple">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="16" y1="13" x2="8" y2="13"></line>
          <line x1="16" y1="17" x2="8" y2="17"></line>
          <polyline points="10 9 9 9 8 9"></polyline>
        </svg>
      </div>
      <div>
        <div class="db-stat-label">Tổng số bản ghi</div>
        <div class="db-stat-value" style="color:#9333ea;">
          <?= number_format($totalRows) ?>
        </div>
        <div class="db-stat-note">Lưu trữ trên <?= $totalTables ?> bảng dữ liệu</div>
      </div>
    </div>

    <!-- Card 4: Structure -->
    <div class="db-stat">
      <div class="db-stat-icon orange">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
          <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
          <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
        </svg>
      </div>
      <div>
        <div class="db-stat-label">Cấu trúc CSDL</div>
        <div class="db-stat-value" style="color:#f97316;">
          <?= $totalTables ?> bảng
        </div>
        <div class="db-stat-note">Tự động nạp từ database.sql</div>
      </div>
    </div>
  </div>

  <!-- MAIN LIST CARD (DANH SÁCH CHI TIẾT 11 BẢNG) -->
  <div class="db-list-card">
    <div class="db-list-heading">
      <div class="db-list-heading-left">
        <h2>Chi tiết các bảng dữ liệu trong CSDL</h2>
        <span class="badge-status-online">
          <span class="pulse-dot online" style="width:6px;height:6px;"></span>
          <span>Đồng bộ tự động</span>
        </span>
      </div>
      <span class="db-count-badge"><?= $totalTables ?> bảng dữ liệu</span>
    </div>

    <div class="db-table-wrap">
      <table class="db-table">
        <thead>
          <tr>
            <th>Tên bảng</th>
            <th>Mô tả chức năng</th>
            <th>Trạng thái</th>
            <th>Phân loại</th>
            <th>Số bản ghi</th>
            <th style="text-align: center;">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($stats)): ?>
            <?php foreach ($stats as $tName => $tInfo): 
              $meta = $tableMetadata[$tName] ?? [
                  'desc' => $tInfo['label'],
                  'category' => 'Hệ thống',
                  'link' => 'dashboard.php'
              ];
              $catClass = match($meta['category']) {
                  'Khách hàng' => 'client',
                  'Quản trị', 'Bảo mật' => 'system',
                  default => '',
              };
            ?>
              <tr>
                <!-- Tên bảng -->
                <td>
                  <a href="<?= e($meta['link']) ?>" class="table-code-name" title="Truy cập module <?= e($meta['category']) ?>">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                    <span><?= e($tName) ?></span>
                  </a>
                </td>

                <!-- Mô tả chức năng -->
                <td>
                  <div class="table-desc-text">
                    <strong><?= e($tInfo['label']) ?></strong>
                    <div style="font-size: 0.72rem; color: #8290a4; margin-top: 2px;">
                      <?= e($meta['desc']) ?>
                    </div>
                  </div>
                </td>

                <!-- Trạng thái -->
                <td>
                  <span class="badge-status-online">
                    <span class="pulse-dot online" style="width:5px;height:5px;"></span>
                    <span>Hoạt động</span>
                  </span>
                </td>

                <!-- Phân loại -->
                <td>
                  <span class="badge-category <?= $catClass ?>">
                    <?= e($meta['category']) ?>
                  </span>
                </td>

                <!-- Số bản ghi -->
                <td>
                  <span class="badge-rows-count">
                    <?= number_format($tInfo['count']) ?> dòng
                  </span>
                </td>

                <!-- Thao tác xem chi tiết -->
                <td style="text-align: center;">
                  <a href="<?= e($meta['link']) ?>" class="db-action-link" title="Xem chi tiết tại trang <?= e($meta['category']) ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" style="padding: 3rem 1rem; text-align: center; color: #64748b;">
                <div style="font-size: 2.2rem; margin-bottom: 0.5rem;">📂</div>
                <strong style="color: #334155; font-size: 0.95rem;">Chưa có dữ liệu bảng nào trong CSDL!</strong>
                <div style="margin-top: 0.35rem; font-size: 0.8rem;">
                  Hãy bấm nút <strong>"Khởi tạo / Nạp lại CSDL"</strong> ở góc trên bên phải để tạo 11 bảng và 1.000 khách hàng mẫu.
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- FOOTER INFO BAR -->
    <div class="db-table-footer-info">
      <div>
        <span>Nguồn cấu trúc: <strong>database.sql</strong> • Bộ mã: <strong>UTF-8 Unicode (utf8mb4_unicode_ci)</strong></span>
      </div>
      <div>
        <span>Động cơ lưu trữ: <strong>InnoDB</strong> • Tương thích <strong>MySQL 5.7+ / 8.0+ & MariaDB XAMPP</strong></span>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
