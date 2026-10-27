<?php
/**
 * Hệ thống Quản lý Feature Flags
 * version-compare.php – So sánh 2 phiên bản snapshot cạnh nhau (Giai đoạn 3)
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/services/VersionService.php';

$versions_all = $_SESSION['ff_data']['versions'] ?? [];
$ver_keys     = array_keys($versions_all);

$v1 = $_GET['v']  ?? ($ver_keys[count($ver_keys)-1] ?? '');
$v2 = $_GET['v2'] ?? ($ver_keys[count($ver_keys)-2] ?? $v1);

$page_title  = 'So sánh phiên bản';
$breadcrumbs = [
    ['label' => 'Trang chủ',        'url' => 'dashboard.php'],
    ['label' => 'Lịch sử phiên bản','url' => 'versions.php'],
    ['label' => 'So sánh',          'url' => ''],
];
require_once __DIR__ . '/includes/header.php';

$diff = (!empty($v1) && !empty($v2)) ? VersionService::compare($v1, $v2) : [];
$meta1 = $versions_all[$v1] ?? null;
$meta2 = $versions_all[$v2] ?? null;

$changed_count = count(array_filter($diff, fn($d) => $d['changed']));
?>

<div class="page-header">
  <div>
    <h1 class="page-title">So sánh phiên bản cấu hình</h1>
    <p class="page-description">Xem sự khác biệt giữa hai phiên bản snapshot flag tính năng.</p>
  </div>
  <div class="page-actions">
    <a href="versions.php" class="btn btn-secondary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      Quay lại
    </a>
  </div>
</div>

<!-- Bộ chọn phiên bản -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-body">
    <form method="GET" action="version-compare.php" style="display:flex;align-items:flex-end;gap:1rem;flex-wrap:wrap">
      <div class="form-group" style="margin-bottom:0;flex:1;min-width:180px">
        <label class="form-label">Phiên bản gốc (A)</label>
        <select name="v" class="form-select">
          <?php foreach ($versions_all as $tag => $ver): ?>
            <option value="<?= e($tag) ?>" <?= $tag===$v1?'selected':'' ?>><?= e($ver['version_number'] ?? $tag) ?> – <?= e(substr($ver['reason']??'',0,40)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="font-size:1.2rem;padding-bottom:.4rem;color:var(--text-muted)">→</div>
      <div class="form-group" style="margin-bottom:0;flex:1;min-width:180px">
        <label class="form-label">Phiên bản đích (B)</label>
        <select name="v2" class="form-select">
          <?php foreach ($versions_all as $tag => $ver): ?>
            <option value="<?= e($tag) ?>" <?= $tag===$v2?'selected':'' ?>><?= e($ver['version_number'] ?? $tag) ?> – <?= e(substr($ver['reason']??'',0,40)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-bottom:0">So sánh</button>
    </form>
  </div>
</div>

<?php if ($meta1 && $meta2): ?>
<!-- Thông tin 2 phiên bản -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.25rem">
  <?php foreach ([['A', $meta1, $v1], ['B', $meta2, $v2]] as [$label, $meta, $tag]): ?>
  <div class="card">
    <div class="card-body">
      <div style="font-size:.72rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:.4rem">Phiên bản <?= $label ?></div>
      <div style="font-size:1.15rem;font-weight:700;color:var(--primary)"><?= e($meta['version_number'] ?? $tag) ?></div>
      <div style="font-size:.82rem;color:var(--text-secondary);margin:.25rem 0"><?= e($meta['reason'] ?? '') ?></div>
      <div style="font-size:.75rem;color:var(--text-muted)"><?= format_date($meta['created_at']??'') ?> &bull; <?= e($meta['created_by']??'') ?></div>
      <?= render_badge($meta['status'] ?? '') ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Bảng so sánh -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title">Kết quả so sánh</h2>
    <div style="display:flex;align-items:center;gap:.5rem">
      <?php if ($changed_count > 0): ?>
        <span class="badge badge-warning"><?= $changed_count ?> thay đổi</span>
      <?php else: ?>
        <span class="badge badge-success">Hoàn toàn giống nhau</span>
      <?php endif; ?>
      <span class="badge badge-neutral"><?= count($diff) ?> cờ</span>
    </div>
  </div>
  <div class="table-responsive" style="border:none;border-radius:0">
    <table class="data-table">
      <thead>
        <tr>
          <th>Tên tính năng</th>
          <th>Mã cờ</th>
          <th>Phiên bản A – <?= e($versions_all[$v1]['version_number'] ?? $v1) ?></th>
          <th>Phiên bản B – <?= e($versions_all[$v2]['version_number'] ?? $v2) ?></th>
          <th style="text-align:center">Thay đổi?</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($diff as $row): ?>
        <tr class="<?= $row['changed'] ? 'diff-changed' : '' ?>">
          <td style="font-weight:<?= $row['changed'] ? '600' : '400' ?>"><?= e($row['name']) ?></td>
          <td><span class="font-mono"><?= e($row['key']) ?></span></td>
          <td class="<?= $row['old_on'] ? 'diff-added' : '' ?>">
            <div style="display:flex;align-items:center;gap:.4rem">
              <label class="switch" style="pointer-events:none"><input type="checkbox" <?= $row['old_on']?'checked':'' ?> disabled><span class="slider"></span></label>
              <span style="font-weight:600"><?= $row['old_on'] ? 'BẬT' : 'TẮT' ?></span>
              <span class="badge badge-neutral"><?= $row['old_pct'] ?>%</span>
            </div>
          </td>
          <td class="<?= $row['new_on'] ? 'diff-added' : ($row['changed'] ? 'diff-removed' : '') ?>">
            <div style="display:flex;align-items:center;gap:.4rem">
              <label class="switch" style="pointer-events:none"><input type="checkbox" <?= $row['new_on']?'checked':'' ?> disabled><span class="slider"></span></label>
              <span style="font-weight:600"><?= $row['new_on'] ? 'BẬT' : 'TẮT' ?></span>
              <span class="badge badge-neutral"><?= $row['new_pct'] ?>%</span>
            </div>
          </td>
          <td style="text-align:center">
            <?php if (!$row['changed']): ?>
              <span class="badge badge-neutral">—</span>
            <?php elseif ($row['old_on'] && !$row['new_on']): ?>
              <span class="badge badge-danger">Đã tắt</span>
            <?php elseif (!$row['old_on'] && $row['new_on']): ?>
              <span class="badge badge-success">Đã bật</span>
            <?php else: ?>
              <span class="badge badge-warning">Đổi tỷ lệ</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
