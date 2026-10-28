<?php
/**
 * Web Feature Flag Management System
 * dashboard.php - Bảng điều khiển trung tâm (Dashboard)
 * BTL Phát triển phần mềm mã nguồn mở
 */

declare(strict_types=1);

$page_title = 'Tổng quan hệ thống';
$page_js = 'assets/js/dashboard.js';
$breadcrumbs = [
    ['label' => 'Trang chủ', 'url' => 'dashboard.php'],
    ['label' => 'Tổng quan', 'url' => ''],
];

require_once __DIR__ . '/includes/header.php';

// Trích xuất dữ liệu giả lập từ Session
$data = $_SESSION['ff_data'] ?? [];
$projects = $data['projects'] ?? [];
$flags = $data['flags'] ?? [];
$versions = $data['versions'] ?? [];
$audit_logs = $data['audit_logs'] ?? [];
$current_env = get_active_env();

// Tính toán các chỉ số thống kê động theo môi trường hiện tại
$total_projects = count($projects);
$total_flags = count($flags);
$enabled_count = 0;
$disabled_count = 0;
$rollout_distribution = [
    '0' => 0,
    '10' => 0,
    '50' => 0,
    '100' => 0,
];

foreach ($flags as $flag) {
    $env_cfg = $flag['environments'][$current_env] ?? null;
    if ($env_cfg && !empty($env_cfg['enabled'])) {
        $enabled_count++;
    } else {
        $disabled_count++;
    }

    $pct = (string)($env_cfg['rollout_percentage'] ?? 0);
    if (isset($rollout_distribution[$pct])) {
        $rollout_distribution[$pct]++;
    }
}

// Số phiên bản đã phát hành
$published_versions_count = 0;
foreach ($versions as $v) {
    if (($v['status'] ?? '') === 'Published') {
        $published_versions_count++;
    }
}
$recent_changes_count = count($audit_logs);

// Sắp xếp danh sách flags theo thời gian cập nhật gần nhất
$sorted_flags = $flags;
uasort($sorted_flags, function ($a, $b) use ($current_env) {
    $timeA = $a['environments'][$current_env]['updated_at'] ?? '';
    $timeB = $b['environments'][$current_env]['updated_at'] ?? '';
    return strcmp($timeB, $timeA);
});
$flags_pagination = paginate_items($sorted_flags, (int)($_GET['flags_page'] ?? 1), 20);
$paged_flags = $flags_pagination['items'];
?>

<!-- Tiêu đề trang & Thao tác nhanh -->
<div class="page-header">
  <div>
    <h1 class="page-title">Bảng điều khiển tổng quan</h1>
    <p class="page-description">
      Theo dõi trạng thái hệ thống, tỷ lệ triển khai cờ tính năng trên môi trường 
      <strong style="color: var(--primary); text-transform: uppercase;"><?= e($current_env) ?></strong>.
    </p>
  </div>
  <div class="page-actions" style="display: flex; gap: 0.5rem; align-items: center;">
    <a href="setup.php" class="btn btn-secondary">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <ellipse cx="12" cy="5" rx="9" ry="3"/>
        <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
        <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
      </svg>
      <span>CSDL MySQL</span>
    </a>
    <a href="flags.php" class="btn btn-primary">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <line x1="12" y1="5" x2="12" y2="19"></line>
        <line x1="5" y1="12" x2="19" y2="12"></line>
      </svg>
      <span>Quản lý tính năng</span>
    </a>
  </div>
</div>

<!-- Lưới Thẻ Thống Kê (6 Metric Cards chuẩn yêu cầu mục 6.1) -->
<div class="stats-grid">
  <!-- Thẻ 1: Tổng số dự án -->
  <div class="stat-card">
    <div class="stat-header">
      <span class="stat-title">Tổng số dự án</span>
      <div class="stat-icon primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
        </svg>
      </div>
    </div>
    <div class="stat-value"><?= $total_projects ?></div>
    <div class="stat-meta">
      <span>Dự án chính: <strong>E-Commerce Storefront</strong></span>
    </div>
  </div>

  <!-- Thẻ 2: Tổng số tính năng -->
  <div class="stat-card">
    <div class="stat-header">
      <span class="stat-title">Tổng số tính năng</span>
      <div class="stat-icon info">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
          <polyline points="2 17 12 22 22 17"></polyline>
          <polyline points="2 12 12 17 22 12"></polyline>
        </svg>
      </div>
    </div>
    <div class="stat-value"><?= $total_flags ?></div>
    <div class="stat-meta">
      <span>Cấu hình song song 2 môi trường</span>
    </div>
  </div>

  <!-- Thẻ 3: Số tính năng đang BẬT -->
  <div class="stat-card">
    <div class="stat-header">
      <span class="stat-title">Đang Bật (<?= ucfirst($current_env) ?>)</span>
      <div class="stat-icon success">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="20 6 9 17 4 12"></polyline>
        </svg>
      </div>
    </div>
    <div class="stat-value" style="color: var(--success);"><?= $enabled_count ?></div>
    <div class="stat-meta">
      <span><?= round(($enabled_count / max(1, $total_flags)) * 100) ?>% tổng số tính năng</span>
    </div>
  </div>

  <!-- Thẻ 4: Số tính năng đang TẮT -->
  <div class="stat-card">
    <div class="stat-header">
      <span class="stat-title">Đang Tắt (<?= ucfirst($current_env) ?>)</span>
      <div class="stat-icon warning">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="15" y1="9" x2="9" y2="15"></line>
        </svg>
      </div>
    </div>
    <div class="stat-value" style="color: var(--warning);"><?= $disabled_count ?></div>
    <div class="stat-meta">
      <span>Trạng thái Draft hoặc Disable</span>
    </div>
  </div>

  <!-- Thẻ 5: Phiên bản đã phát hành -->
  <div class="stat-card">
    <div class="stat-header">
      <span class="stat-title">Phiên bản phát hành</span>
      <div class="stat-icon primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <polyline points="12 6 12 12 16 14"></polyline>
        </svg>
      </div>
    </div>
    <div class="stat-value"><?= $published_versions_count ?></div>
    <div class="stat-meta">
      <span>Phiên bản hiện hành: <strong>v1.2.0 (v3)</strong></span>
    </div>
  </div>

  <!-- Thẻ 6: Thay đổi gần đây -->
  <div class="stat-card">
    <div class="stat-header">
      <span class="stat-title">Thay đổi gần đây</span>
      <div class="stat-icon info">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
        </svg>
      </div>
    </div>
    <div class="stat-value"><?= $recent_changes_count ?></div>
    <div class="stat-meta">
      <span>Lịch sử thay đổi được ghi nhận</span>
    </div>
  </div>
</div>

<!-- Khối trực quan: Thống kê phân bổ tỷ lệ Rollout & So sánh môi trường -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem; margin-bottom: 1.75rem;">
  <!-- Card 1: Phân bổ tỷ lệ Rollout % -->
  <div class="card">
    <div class="card-header">
      <div>
        <h2 class="card-title">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="20" x2="18" y2="10"></line>
            <line x1="12" y1="20" x2="12" y2="4"></line>
            <line x1="6" y1="20" x2="6" y2="14"></line>
          </svg>
          <span>Phân bổ tỷ lệ Rollout (<?= ucfirst($current_env) ?>)</span>
        </h2>
        <div class="card-subtitle">Thống kê số lượng tính năng theo các mức tỷ lệ triển khai</div>
      </div>
    </div>
    <div class="card-body">
      <div style="display: flex; flex-direction: column; gap: 1rem;">
        <?php foreach ($rollout_distribution as $rate => $count): 
            $percentOfTotal = round(($count / max(1, $total_flags)) * 100);
        ?>
          <div>
            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.35rem;">
              <span style="font-weight: 600; color: var(--text-main);">Mức triển khai <?= $rate ?>%</span>
              <span style="color: var(--text-muted); font-weight: 500;">
                <strong><?= $count ?></strong> tính năng (<?= $percentOfTotal ?>%)
              </span>
            </div>
            <div class="progress-bar-wrap" style="height: 9px;">
              <div class="progress-fill <?= ($rate == '100') ? 'full' : '' ?>" style="width: <?= $percentOfTotal ?>%;"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Card 2: So sánh trạng thái 2 Môi trường (Testing vs Staging) -->
  <div class="card">
    <div class="card-header">
      <div>
        <h2 class="card-title">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
            <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
          </svg>
          <span>Đối chiếu Môi trường Triển khai</span>
        </h2>
        <div class="card-subtitle">So sánh nhanh chỉ số giữa Testing và Staging</div>
      </div>
      <a href="environments.php" class="btn btn-ghost btn-sm">Chi tiết &rarr;</a>
    </div>
    <div class="card-body">
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <!-- Cột Testing -->
        <div style="padding: 1rem; border-radius: var(--radius-md); border: 1px solid <?= ($current_env === 'testing') ? 'var(--warning-border)' : 'var(--border-color)' ?>; background: <?= ($current_env === 'testing') ? 'var(--warning-light)' : '#ffffff' ?>;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
            <?= render_env_badge('testing') ?>
            <?php if ($current_env === 'testing'): ?>
              <span style="font-size: 0.72rem; font-weight: 700; color: var(--warning-text);">ĐANG XEM</span>
            <?php endif; ?>
          </div>
          <div style="font-size: 1.35rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">
            9 / 10 Bật
          </div>
          <div style="font-size: 0.8rem; color: var(--text-muted);">
            Thử nghiệm nội bộ (100% rollout các cờ chính)
          </div>
        </div>

        <!-- Cột Staging -->
        <div style="padding: 1rem; border-radius: var(--radius-md); border: 1px solid <?= ($current_env === 'staging') ? 'var(--primary-border)' : 'var(--border-color)' ?>; background: <?= ($current_env === 'staging') ? 'var(--primary-light)' : '#ffffff' ?>;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
            <?= render_env_badge('staging') ?>
            <?php if ($current_env === 'staging'): ?>
              <span style="font-size: 0.72rem; font-weight: 700; color: var(--primary);">ĐANG XEM</span>
            <?php endif; ?>
          </div>
          <div style="font-size: 1.35rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">
            5 / 10 Bật
          </div>
          <div style="font-size: 0.8rem; color: var(--text-muted);">
            Phiên bản vận hành v1.2.0 (Kiểm thử 50%)
          </div>
        </div>
      </div>

      <div style="margin-top: 1.25rem; font-size: 0.82rem; color: var(--text-muted); line-height: 1.4;">
        &bull; <em>Lưu ý:</em> Chuyển đổi môi trường qua nút chọn trên thanh Topbar phía trên để quan sát cấu hình và kết quả quyết định bật/tắt riêng biệt của từng môi trường.
      </div>
    </div>
  </div>
</div>

<!-- Bảng: Tính năng vừa cập nhật gần đây (Recently Updated Feature Flags) -->
<div class="card" style="margin-bottom: 1.75rem;">
  <div class="card-header">
    <div>
      <h2 class="card-title">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
          <polyline points="2 17 12 22 22 17"></polyline>
          <polyline points="2 12 12 17 22 12"></polyline>
        </svg>
        <span>Tính năng vừa cập nhật gần đây (<?= ucfirst($current_env) ?>)</span>
      </h2>
      <div class="card-subtitle">Hiển thị danh sách 10 cờ tính năng và trạng thái trong môi trường đang chọn</div>
    </div>
    <div style="display: flex; gap: 0.5rem;">
      <div class="search-input-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
        <input type="text" id="dashboardSearchInput" class="form-control" placeholder="Nhập từ khóa tìm kiếm cờ tính năng">
      </div>
      <a href="flags.php" class="btn btn-secondary btn-sm">Xem tất cả</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="data-table" id="recentFlagsTable">
      <thead>
        <tr>
          <th>Tên tính năng</th>
          <th>Mã định danh</th>
          <th>Môi trường</th>
          <th>Trạng thái</th>
          <th>Tỷ lệ triển khai</th>
          <th>Nhóm áp dụng</th>
          <th>Cập nhật bởi</th>
          <th>Thời gian</th>
          <th style="text-align: right;">Chức năng</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($paged_flags as $flag):
            $env_cfg = $flag['environments'][$current_env] ?? [];
            $is_enabled = !empty($env_cfg['enabled']);
            $rollout = (int)($env_cfg['rollout_percentage'] ?? 0);
            $status = $env_cfg['status'] ?? 'Draft';
            $target_groups = $env_cfg['target_groups'] ?? [];
        ?>
          <tr>
            <td>
              <div style="font-weight: 600; color: var(--text-main);"><?= e($flag['name']) ?></div>
              <div style="font-size: 0.78rem; color: var(--text-muted); max-width: 280px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                <?= e($flag['description']) ?>
              </div>
            </td>
            <td>
              <span class="font-mono"><?= e($flag['key']) ?></span>
            </td>
            <td>
              <?= render_env_badge($current_env) ?>
            </td>
            <td>
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <!-- Công tắc demo trực quan -->
                <label class="switch">
                  <input type="checkbox" class="dashboard-flag-toggle" data-flag-key="<?= e($flag['key']) ?>" <?= $is_enabled ? 'checked' : '' ?> <?= PermissionService::currentUserCan('toggle_flag') ? '' : 'disabled' ?>>
                  <span class="slider"></span>
                </label>
                <?= render_badge($status) ?>
              </div>
            </td>
            <td>
              <div class="rollout-container" aria-label="Tỷ lệ triển khai <?= $rollout ?>%">
                <div class="progress-bar-wrap">
                  <div class="progress-fill <?= ($rollout === 100) ? 'full' : '' ?>" style="width: <?= $rollout ?>%;"></div>
                </div>
                <span class="rollout-percent"><?= $rollout ?>%</span>
              </div>
            </td>
            <td>
              <?php if (empty($target_groups)): ?>
                <span style="color: var(--text-light); font-size: 0.8rem;">Toàn bộ</span>
              <?php else: ?>
                <span class="badge badge-neutral"><?= count($target_groups) ?> nhóm</span>
              <?php endif; ?>
            </td>
            <td>
              <span style="font-size: 0.82rem; font-weight: 500;"><?= e($env_cfg['updated_by'] ?? 'admin') ?></span>
            </td>
            <td>
              <span style="font-size: 0.8rem; color: var(--text-muted);"><?= format_date($env_cfg['updated_at'] ?? '') ?></span>
            </td>
            <td style="text-align: right; white-space: nowrap;">
              <div class="action-icons" style="justify-content: flex-end;">
                <a href="flag-detail.php?key=<?= urlencode($flag['key']) ?>&mode=view&return_to=dashboard.php" class="action-icon-btn action-view" title="Xem chi tiết" aria-label="Xem chi tiết">
                  <svg viewBox="64 64 896 896" fill="currentColor"><path d="M942.2 486.2C847.4 286.5 704.1 186 512 186c-192.2 0-335.4 100.5-430.2 300.3a60.3 60.3 0 0 0 0 51.5C176.6 737.5 319.9 838 512 838c192.2 0 335.4-100.5 430.2-300.3 7.7-16.2 7.7-35 0-51.5zM512 766c-161.3 0-279.4-81.8-362.7-254C232.6 339.8 350.7 258 512 258c161.3 0 279.4 81.8 362.7 254C791.5 684.2 673.4 766 512 766zm-4-422c-97.2 0-176 78.8-176 176s78.8 176 176 176 176-78.8 176-176-78.8-176-176-176zm0 288c-61.9 0-112-50.1-112-112s50.1-112 112-112 112 50.1 112 112-50.1 112-112 112z"/></svg>
                </a>
                <a href="flag-detail.php?key=<?= urlencode($flag['key']) ?>&mode=edit&return_to=dashboard.php" class="action-icon-btn action-edit" title="Chỉnh sửa" aria-label="Chỉnh sửa">
                  <svg viewBox="64 64 896 896" fill="currentColor"><path d="M257.7 752c2 0 4-.2 6-.5L431.9 722c2-.4 3.9-1.3 5.3-2.8l423.9-423.9a9.96 9.96 0 0 0 0-14.1L694.9 114.9c-1.9-1.9-4.4-2.9-7.1-2.9s-5.2 1-7.1 2.9L256.8 538.8c-1.5 1.5-2.4 3.3-2.8 5.3l-29.5 168.2a33.5 33.5 0 0 0 9.4 29.8c6.6 6.4 14.9 9.9 23.8 9.9zm67.4-174.4L687.8 215l57.2 57.2-362.7 362.6-68.9 12 11.7-69.2zM752 688H144c-17.7 0-32 14.3-32 32v120c0 4.4 3.6 8 8 8h784c4.4 0 8-3.6 8-8V720c0-17.7-14.3-32-32-32z"/></svg>
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($flags_pagination['pages'] > 1): ?>
    <div class="pagination-wrap">
      <span class="pagination-summary">Hiển thị <?= $flags_pagination['start'] ?> - <?= $flags_pagination['end'] ?> trong tổng số <?= $flags_pagination['total'] ?> cờ tính năng</span>
      <nav class="pagination" aria-label="Phân trang cờ tính năng">
        <?php
        $flags_query = $_GET;
        unset($flags_query['flags_page']);
        $flags_url = static function (int $page) use ($flags_query): string {
            return 'dashboard.php?' . http_build_query($flags_query + ['flags_page' => $page]);
        };
        ?>
        <a class="pagination-link <?= $flags_pagination['page'] <= 1 ? 'disabled' : '' ?>" href="<?= e($flags_url(max(1, $flags_pagination['page'] - 1))) ?>" aria-label="Trang trước">&lsaquo;</a>
        <?php for ($page_number = 1; $page_number <= $flags_pagination['pages']; $page_number++): ?>
          <a class="pagination-link <?= $page_number === $flags_pagination['page'] ? 'active' : '' ?>" href="<?= e($flags_url($page_number)) ?>" <?= $page_number === $flags_pagination['page'] ? 'aria-current="page"' : '' ?>><?= $page_number ?></a>
        <?php endfor; ?>
        <a class="pagination-link <?= $flags_pagination['page'] >= $flags_pagination['pages'] ? 'disabled' : '' ?>" href="<?= e($flags_url(min($flags_pagination['pages'], $flags_pagination['page'] + 1))) ?>" aria-label="Trang sau">&rsaquo;</a>
      </nav>
    </div>
  <?php endif; ?>
</div>

<!-- Lịch sử hoạt động gần đây (Audit Logs Stream) -->
<div class="card">
  <div class="card-header">
    <div>
      <h2 class="card-title">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
        </svg>
        <span>Lịch sử hoạt động & Kiểm toán gần đây</span>
      </h2>
      <div class="card-subtitle">Ghi nhận các thao tác bật/tắt, cập nhật rollout và phát hành phiên bản</div>
    </div>
    <a href="audit-logs.php" class="btn btn-ghost btn-sm">Xem tất cả nhật ký &rarr;</a>
  </div>

  <div class="table-responsive">
    <table class="data-table">
      <thead>
        <tr>
          <th>Người thực hiện</th>
          <th>Hành động</th>
          <th>Đối tượng thay đổi</th>
          <th>Giá trị cũ &rarr; mới</th>
          <th>Lý do nghiệp vụ</th>
          <th>Thời điểm</th>
        </tr>
      </thead>
      <tbody>
        <?php 
        $display_logs = array_slice($audit_logs, 0, 5);
        foreach ($display_logs as $log): 
        ?>
          <tr>
            <td>
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <span class="user-avatar-circle" style="width: 24px; height: 24px; font-size: 0.7rem;">
                  <?= strtoupper(substr($log['actor'], 0, 2)) ?>
                </span>
                <div>
                  <div style="font-weight: 600; font-size: 0.85rem;"><?= e($log['actor']) ?></div>
                  <div><?= render_role_badge($log['role'] ?? 'editor') ?></div>
                </div>
              </div>
            </td>
            <td>
              <span class="badge badge-neutral" style="font-family: monospace; font-size: 0.72rem;">
                <?= e($log['action']) ?>
              </span>
            </td>
            <td>
              <strong style="color: var(--text-main); font-size: 0.85rem;"><?= e($log['target']) ?></strong>
            </td>
            <td>
              <div style="font-size: 0.78rem; color: var(--text-muted);">
                <span style="color: var(--danger);"><?= e($log['old_value']) ?></span>
                &rarr;
                <span style="color: var(--success); font-weight: 600;"><?= e($log['new_value']) ?></span>
              </div>
            </td>
            <td>
              <span style="font-size: 0.82rem; color: var(--text-secondary);"><?= e($log['reason']) ?></span>
            </td>
            <td>
              <span style="font-size: 0.8rem; color: var(--text-muted);"><?= format_date($log['created_at']) ?></span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
