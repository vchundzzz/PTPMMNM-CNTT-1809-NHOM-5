<?php
/**
 * Hệ thống Quản lý Feature Flags
 * flag-detail.php – Chi tiết & chỉnh sửa cờ tính năng (Giai đoạn 2)
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/services/PermissionService.php';

$key        = trim($_GET['key'] ?? '');
$flags      = $_SESSION['ff_data']['flags'] ?? [];
$groups_all = $_SESSION['ff_data']['groups'] ?? [];

if (!isset($flags[$key])) {
    set_flash('error', 'Không tìm thấy cờ tính năng.');
    header('Location: flags.php'); exit;
}

$current_env = get_active_env();

/* ============================================================
   XỬ LÝ POST: lưu cấu hình chi tiết (tỷ lệ, nhóm, override)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'CSRF không hợp lệ.'); header('Location: flag-detail.php?key='.urlencode($key)); exit;
    }
    PermissionService::enforce('edit_flag');

    $pct     = max(0, min(100, (int)($_POST['rollout_percentage'] ?? 0)));
    $enabled = !empty($_POST['enabled']);
    $sel_grp = $_POST['target_groups'] ?? [];
    $sel_grp = array_filter($sel_grp, fn($g) => isset($groups_all[$g]));

    // Parse user overrides
    $raw_ov   = trim((string)($_POST['user_overrides_raw'] ?? ''));
    $new_ov   = [];
    if (!empty($raw_ov)) {
        foreach (explode("\n", $raw_ov) as $line) {
            $line = trim($line);
            if (preg_match('/^(KH\d+)\s*[=:]\s*(true|false|1|0|bật|tắt)/i', $line, $m)) {
                $uid   = strtoupper($m[1]);
                $state = in_array(strtolower($m[2]), ['true','1','bật'], true);
                $new_ov[$uid] = $state;
            }
        }
    }

    $old_pct = $_SESSION['ff_data']['flags'][$key]['environments'][$current_env]['rollout_percentage'] ?? 0;
    $_SESSION['ff_data']['flags'][$key]['environments'][$current_env] = array_merge(
        $_SESSION['ff_data']['flags'][$key]['environments'][$current_env],
        [
            'enabled'            => $enabled,
            'rollout_percentage' => $pct,
            'status'             => $enabled ? 'Đã phát hành' : 'Bản nháp',
            'target_groups'      => array_values($sel_grp),
            'user_overrides'     => $new_ov,
            'updated_at'         => date('Y-m-d H:i:s'),
            'updated_by'         => get_authenticated_user()['username'] ?? 'admin',
        ]
    );
    append_audit_log('SỬA_CẤU_HÌNH', $key, "Tỷ lệ: $old_pct%", "Tỷ lệ: $pct% | " . ($enabled?'Bật':'Tắt'),
        trim((string)($_POST['change_reason'] ?? 'Cập nhật từ trang chi tiết')));
    set_flash('success', "Đã lưu cấu hình cờ [$key] thành công.");
    header('Location: flag-detail.php?key='.urlencode($key)); exit;
}

/* ============================================================
   RENDER
   ============================================================ */
$flag    = $_SESSION['ff_data']['flags'][$key];
$env_cfg = $flag['environments'][$current_env] ?? [];
$on      = !empty($env_cfg['enabled']);
$pct     = (int)($env_cfg['rollout_percentage'] ?? 0);
$ov      = $env_cfg['user_overrides'] ?? [];

// Tạo text hiển thị override cho textarea
$ov_text = '';
foreach ($ov as $uid => $state) {
    $ov_text .= "$uid = " . ($state ? 'bật' : 'tắt') . "\n";
}

$page_title  = 'Chi tiết: ' . $flag['name'];
$breadcrumbs = [
    ['label' => 'Trang chủ',    'url' => 'dashboard.php'],
    ['label' => 'Cờ tính năng', 'url' => 'flags.php'],
    ['label' => $flag['name'],  'url' => ''],
];
require_once __DIR__ . '/includes/header.php';

$can_edit = PermissionService::currentUserCan('edit_flag');
?>

<div class="page-header">
  <div>
    <div style="display:flex;align-items:center;gap:.65rem;margin-bottom:.2rem">
      <h1 class="page-title"><?= e($flag['name']) ?></h1>
      <?= render_badge($env_cfg['status'] ?? 'Bản nháp') ?>
    </div>
    <p class="page-description">
      Mã cờ: <span class="font-mono"><?= e($flag['key']) ?></span> &bull; Môi trường: <?= render_env_badge($current_env) ?>
    </p>
  </div>
  <div class="page-actions">
    <a href="flags.php" class="btn btn-secondary">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      Quay lại
    </a>
  </div>
</div>

<?php if (!$can_edit): ?>
  <div class="alert alert-warning">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Bạn đang ở chế độ <strong>Chỉ xem</strong>. Không có quyền chỉnh sửa cấu hình cờ này.
  </div>
<?php endif; ?>

<form method="POST" action="flag-detail.php?key=<?= urlencode($key) ?>">
  <?= csrf_field() ?>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem">
    <!-- Cột trái: Cấu hình -->
    <div style="display:flex;flex-direction:column;gap:1.25rem">

      <!-- Card: Thông tin cờ -->
      <div class="card">
        <div class="card-header"><h2 class="card-title">Thông tin cơ bản</h2></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Tên hiển thị</label>
            <input type="text" class="form-control" value="<?= e($flag['name']) ?>" readonly>
          </div>
          <div class="form-group">
            <label class="form-label">Mã cờ (Key)</label>
            <input type="text" class="form-control font-mono" value="<?= e($flag['key']) ?>" readonly>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Mô tả</label>
            <textarea class="form-control" rows="2" readonly><?= e($flag['description']) ?></textarea>
          </div>
        </div>
      </div>

      <!-- Card: Quy tắc triển khai -->
      <div class="card">
        <div class="card-header"><h2 class="card-title">Quy tắc triển khai</h2></div>
        <div class="card-body">
          <!-- Bật / Tắt -->
          <div class="form-group">
            <label class="form-label">Trạng thái kích hoạt</label>
            <div style="display:flex;align-items:center;gap:.75rem">
              <label class="switch">
                <input type="checkbox" name="enabled" <?= $on?'checked':'' ?> <?= !$can_edit?'disabled':'' ?>>
                <span class="slider"></span>
              </label>
              <span style="font-weight:600;color:<?= $on?'var(--success)':'var(--text-secondary)' ?>"><?= $on?'Đang bật':'Đang tắt' ?></span>
            </div>
          </div>

          <!-- Rollout -->
          <div class="form-group">
            <label class="form-label">Tỷ lệ triển khai: <strong id="pctDisplay"><?= $pct ?></strong>%</label>
            <input type="range" name="rollout_percentage" min="0" max="100" step="10" value="<?= $pct ?>"
                   oninput="document.getElementById('pctDisplay').textContent=this.value"
                   <?= !$can_edit?'disabled':'' ?> style="width:100%;margin:.5rem 0">
            <div style="display:flex;justify-content:space-between;font-size:.72rem;color:var(--text-muted)">
              <span>0%</span><span>10%</span><span>50%</span><span>100%</span>
            </div>
            <div class="form-text">Phân bổ ổn định bằng hàm băm crc32(KH_ID:flag_key) % 100.</div>
          </div>

          <!-- Nhóm áp dụng -->
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Nhóm khách hàng áp dụng</label>
            <div style="display:flex;flex-direction:column;gap:.4rem">
              <?php foreach ($groups_all as $gid => $g): ?>
                <label style="display:flex;align-items:center;gap:.5rem;font-size:.85rem">
                  <input type="checkbox" name="target_groups[]" value="<?= e($gid) ?>"
                         <?= in_array($gid, $env_cfg['target_groups'] ?? [],true)?'checked':'' ?>
                         <?= !$can_edit?'disabled':'' ?>>
                  <strong><?= e($g['name']) ?></strong>
                  <span style="color:var(--text-secondary)">(<?= e($g['range']) ?>)</span>
                </label>
              <?php endforeach; ?>
            </div>
            <div class="form-text">Không chọn nhóm nào = áp dụng cho tất cả khách hàng.</div>
          </div>
        </div>
      </div>

      <!-- Card: Ngoại lệ cá nhân -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Ngoại lệ cá nhân (User Override)</h2>
        </div>
        <div class="card-body">
          <label class="form-label">Danh sách ngoại lệ (mỗi dòng một khách hàng)</label>
          <textarea name="user_overrides_raw" class="form-control font-mono" rows="5"
                    placeholder="Nhập danh sách ngoại lệ"
                    <?= !$can_edit?'readonly':'' ?>><?= e(trim($ov_text)) ?></textarea>
          <div class="form-text">Định dạng: <code>KH1 = bật</code> hoặc <code>KH1 = tắt</code>. Ngoại lệ được ưu tiên hơn tỷ lệ triển khai.</div>

          <?php if (!empty($ov)): ?>
          <div style="margin-top:.75rem">
            <table class="data-table" style="font-size:.8rem">
              <thead><tr><th>Mã khách hàng</th><th>Quy tắc ghi đè</th></tr></thead>
              <tbody>
                <?php foreach ($ov as $uid => $state): ?>
                <tr>
                  <td><span class="font-mono"><?= e((string)$uid) ?></span></td>
                  <td><?= $state ? '<span class="badge badge-success">Cưỡng chế BẬT</span>' : '<span class="badge badge-danger">Cưỡng chế TẮT</span>' ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($can_edit): ?>
      <!-- Lý do thay đổi -->
      <div class="card">
        <div class="card-body">
          <div class="form-group" style="margin-bottom:.75rem">
            <label class="form-label">Lý do thay đổi</label>
            <input type="text" name="change_reason" class="form-control" placeholder="Nhập lý do thay đổi">
          </div>
          <div style="display:flex;gap:.75rem">
            <button type="submit" class="btn btn-primary">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              Lưu cấu hình
            </button>
            <a href="flag-detail.php?key=<?= urlencode($key) ?>" class="btn btn-secondary">Hủy thay đổi</a>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Cột phải: Siêu dữ liệu & Kiểm thử nhanh -->
    <div style="display:flex;flex-direction:column;gap:1.25rem">
      <div class="card">
        <div class="card-header"><h3 class="card-title">Thông tin quản trị</h3></div>
        <div class="card-body" style="font-size:.83rem;display:flex;flex-direction:column;gap:.65rem">
          <div><span style="color:var(--text-secondary)">Môi trường:</span><div style="margin-top:.2rem"><?= render_env_badge($current_env) ?></div></div>
          <div><span style="color:var(--text-secondary)">Phiên bản:</span><div style="font-weight:600;color:var(--primary);margin-top:.2rem">v1.2.0 (Đang dùng)</div></div>
          <div><span style="color:var(--text-secondary)">Cập nhật lần cuối:</span><div style="font-weight:600;margin-top:.2rem"><?= format_date($env_cfg['updated_at'] ?? '') ?></div></div>
          <div><span style="color:var(--text-secondary)">Người cập nhật:</span><div style="font-weight:600;margin-top:.2rem"><?= e($env_cfg['updated_by'] ?? '—') ?></div></div>
        </div>
      </div>

      <!-- Kiểm thử nhanh bằng API -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Kiểm thử nhanh</h3></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Mã khách hàng thử</label>
            <input type="text" id="testUserId" class="form-control font-mono" value="KH1" placeholder="Nhập mã khách hàng thử">
          </div>
          <button type="button" class="btn btn-secondary" style="width:100%" onclick="quickTest()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Gọi API kiểm thử
          </button>
          <div id="testResult" style="margin-top:.85rem;font-size:.82rem;display:none">
            <div class="alert alert-info" id="testResultContent"></div>
          </div>
        </div>
      </div>

      <a href="demo-app.php?flag=<?= urlencode($key) ?>" class="btn btn-secondary" style="text-align:center">
        Mở trong Ứng dụng Demo →
      </a>
    </div>
  </div>
</form>

<script>
function quickTest() {
  const uid = document.getElementById('testUserId').value.trim() || 'KH1';
  const env = '<?= e($current_env) ?>';
  const key = '<?= e($flag['key']) ?>';

  fetch(`api/evaluate.php?flag_key=${encodeURIComponent(key)}&user_id=${encodeURIComponent(uid)}&environment=${env}`)
    .then(r => r.json())
    .then(data => {
      const d   = data.data;
      const box = document.getElementById('testResult');
      const cnt = document.getElementById('testResultContent');
      box.style.display = 'block';
      cnt.className = 'alert ' + (d.enabled ? 'alert-success' : 'alert-warning');
      cnt.innerHTML = `<strong>${uid}</strong> → <strong>${d.enabled ? '✅ BẬT' : '❌ TẮT'}</strong><br>
        <span style="color:var(--text-secondary)">${d.reason}</span>
        ${d.bucket !== null ? `<br><small>Bucket: ${d.bucket}</small>` : ''}`;
    })
    .catch(() => {
      document.getElementById('testResult').style.display = 'block';
      document.getElementById('testResultContent').className = 'alert alert-danger';
      document.getElementById('testResultContent').textContent = 'Lỗi kết nối API.';
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
