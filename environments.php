<?php
/**
 * Web Feature Flag Management System
 * environments.php - Quản lý môi trường triển khai (Testing & Staging)
 */

declare(strict_types=1);

$page_title = 'Quản lý môi trường';
$breadcrumbs = [
    ['label' => 'Trang chủ', 'url' => 'dashboard.php'],
    ['label' => 'Môi trường', 'url' => ''],
];

require_once __DIR__ . '/includes/header.php';

$environments = $_SESSION['ff_data']['environments'] ?? [];
$flags = $_SESSION['ff_data']['flags'] ?? [];
$current_env = get_active_env();
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Môi trường triển khai (Environments)</h1>
    <p class="page-description">
      Quản lý độc lập trạng thái và tỷ lệ rollout của các tính năng trên từng môi trường.
    </p>
  </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
  <?php foreach ($environments as $env_key => $env): 
      $is_active = ($current_env === $env_key);
      
      // Đếm số cờ bật/tắt trong môi trường này
      $env_enabled = 0;
      $env_total = count($flags);
      foreach ($flags as $f) {
          if (!empty($f['environments'][$env_key]['enabled'])) {
              $env_enabled++;
          }
      }
  ?>
    <div class="card" style="<?= $is_active ? 'border-color: var(--primary); box-shadow: var(--shadow-md);' : '' ?>">
      <div class="card-header">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
          <?= render_env_badge($env_key) ?>
          <?php if ($is_active): ?>
            <span class="badge badge-success" style="font-size: 0.7rem;">MÔI TRƯỜNG ĐANG XEM</span>
          <?php endif; ?>
        </div>
        <span class="font-mono"><?= e($env['id']) ?></span>
      </div>

      <div class="card-body">
        <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.5rem;">
          <?= e($env['name']) ?>
        </h3>
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem; min-height: 40px;">
          <?= e($env['description']) ?>
        </p>

        <div style="background-color: var(--bg-body); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.25rem;">
          <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.5rem;">
            <span style="color: var(--text-secondary);">Tính năng đang BẬT:</span>
            <strong style="color: var(--success);"><?= $env_enabled ?> / <?= $env_total ?></strong>
          </div>
          <div class="progress-bar-wrap" style="height: 8px;">
            <div class="progress-fill <?= ($env_enabled === $env_total) ? 'full' : '' ?>" style="width: <?= round(($env_enabled / max(1, $env_total)) * 100) ?>%;"></div>
          </div>
        </div>

        <div style="display: flex; gap: 0.75rem;">
          <?php if ($is_active): ?>
            <button class="btn btn-secondary btn-sm" disabled style="width: 100%;">
              &check; Đang kích hoạt
            </button>
          <?php else: ?>
            <a href="?switch_env=<?= urlencode($env_key) ?>" class="btn btn-primary btn-sm" style="width: 100%;">
              Chuyển sang môi trường này
            </a>
          <?php endif; ?>
          <a href="flags.php" class="btn btn-secondary btn-sm" style="white-space: nowrap;">
            Xem cấu hình
          </a>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">Quy tắc độc lập môi trường</h3>
  </div>
  <div class="card-body" style="font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6;">
    <p>&bull; Mỗi môi trường (<strong>Testing</strong> hoặc <strong>Staging</strong>) duy trì một trạng thái độc lập về: công tắc bật/tắt (enabled), tỷ lệ phần trăm rollout (0 - 100%), danh sách nhóm người dùng áp dụng và các quy tắc user override.</p>
    <p>&bull; Thao tác thay đổi trên môi trường này sẽ <strong>không làm ảnh hưởng</strong> hoặc ghi đè cấu hình của môi trường khác.</p>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
