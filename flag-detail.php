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
$mode = $_GET['mode'] ?? 'edit';
$read_only = $mode === 'view';
$return_to = in_array($_GET['return_to'] ?? '', ['dashboard.php', 'flags.php'], true)
    ? $_GET['return_to']
    : 'flags.php';

/* ============================================================
   XỬ LÝ POST: lưu cấu hình chi tiết (tỷ lệ, nhóm, override)
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'CSRF không hợp lệ.');
        header('Location: ' . $return_to);
        exit;
    }
    if ($read_only) {
        http_response_code(403);
        exit('Chế độ chỉ xem không cho phép lưu thay đổi.');
    }
    $current_config = $_SESSION['ff_data']['flags'][$key]['environments'][$current_env];
    $can_edit_config = PermissionService::currentUserCan('edit_flag');
    $can_toggle = PermissionService::currentUserCan('toggle_flag');
    $posted_name = trim((string)($_POST['name'] ?? $flags[$key]['name']));
    $posted_description = trim((string)($_POST['description'] ?? $flags[$key]['description']));
    $posted_pct = max(0, min(100, (int)($_POST['rollout_percentage'] ?? $current_config['rollout_percentage'] ?? 0)));
    $posted_enabled = !empty($_POST['enabled']);
    $posted_groups = $_POST['target_groups'] ?? [];
    $posted_groups = array_values(array_filter($posted_groups, fn($g) => isset($groups_all[$g])));
    $posted_overrides = trim((string)($_POST['user_overrides_raw'] ?? ''));

    $config_changed = $can_edit_config && (
        $posted_name !== ($flags[$key]['name'] ?? '')
        || $posted_description !== ($flags[$key]['description'] ?? '')
        || $posted_groups !== ($current_config['target_groups'] ?? [])
        || $posted_overrides !== implode("\n", array_map(
            static fn($uid, $state): string => "$uid = " . ($state ? 'bật' : 'tắt'),
            array_keys($current_config['user_overrides'] ?? []),
            $current_config['user_overrides'] ?? []
        ))
    );
    $toggle_changed = $can_toggle && (
        $posted_pct !== (int)($current_config['rollout_percentage'] ?? 0)
        || $posted_enabled !== !empty($current_config['enabled'])
    );

    if ($config_changed && !$can_edit_config) {
        PermissionService::enforce('edit_flag');
    }
    if ($toggle_changed && !$can_toggle) {
        PermissionService::enforce('toggle_flag');
    }
    if (!$config_changed && !$toggle_changed) {
        set_flash('info', 'Không có thay đổi nào cần lưu.');
        header('Location: ' . $return_to);
        exit;
    }

    $name = $can_edit_config ? $posted_name : (string)($flags[$key]['name'] ?? '');
    $description = $can_edit_config ? $posted_description : (string)($flags[$key]['description'] ?? '');
    if ($can_edit_config && $name === '') {
        set_flash('error', 'Tên hiển thị không được để trống.');
        header('Location: flag-detail.php?key=' . urlencode($key) . '&mode=edit&return_to=' . urlencode($return_to));
        exit;
    }

    $pct = $can_toggle ? $posted_pct : (int)($current_config['rollout_percentage'] ?? 0);
    $enabled = $can_toggle ? $posted_enabled : !empty($current_config['enabled']);
    $sel_grp = $can_edit_config ? $posted_groups : ($current_config['target_groups'] ?? []);

    // Parse user overrides
    $raw_ov = $can_edit_config ? $posted_overrides : '';
    $new_ov   = [];
    if ($can_edit_config && !empty($raw_ov)) {
        foreach (explode("\n", $raw_ov) as $line) {
            $line = trim($line);
            if (preg_match('/^(KH\d+)\s*[=:]\s*(true|false|1|0|bật|tắt)/i', $line, $m)) {
                $uid   = strtoupper($m[1]);
                $state = in_array(strtolower($m[2]), ['true','1','bật'], true);
                $new_ov[$uid] = $state;
            }
        }
    }

    $old_pct = $current_config['rollout_percentage'] ?? 0;
    $old_name = $flags[$key]['name'] ?? '';
    if (!$can_edit_config) {
        $new_ov = $current_config['user_overrides'] ?? [];
    }
    $user_name = get_authenticated_user()['username'] ?? 'admin';
    $_SESSION['ff_data']['flags'][$key]['name'] = $name;
    $_SESSION['ff_data']['flags'][$key]['description'] = $description;
    $_SESSION['ff_data']['flags'][$key]['environments'][$current_env] = array_merge(
        $current_config,
        [
            'name'               => $name,
            'description'        => $description,
            'enabled'            => $enabled,
            'rollout_percentage' => $pct,
            'status'             => $enabled ? 'Đã phát hành' : 'Bản nháp',
            'target_groups'      => array_values($sel_grp),
            'user_overrides'     => $new_ov,
            'updated_at'         => date('Y-m-d H:i:s'),
            'updated_by'         => $user_name,
        ]
    );

    // Đồng bộ chi tiết cờ tính năng vào CSDL MySQL
    try {
        require_once __DIR__ . '/services/DatabaseService.php';
        DatabaseService::saveFlagDetail(
            $key,
            $current_env,
            $name,
            $description,
            $enabled,
            $pct,
            array_values($sel_grp),
            $new_ov,
            $user_name
        );
    } catch (Throwable $e) {}

    append_audit_log('SỬA_CẤU_HÌNH', $key, "Tên: $old_name | Tỷ lệ: $old_pct%", "Tên: $name | Tỷ lệ: $pct% | " . ($enabled?'Bật':'Tắt'),
        trim((string)($_POST['change_reason'] ?? 'Cập nhật từ trang chi tiết')));
    set_flash('success', "Đã lưu cấu hình cờ [$key] thành công.");
    header('Location: ' . $return_to);
    exit;
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

$can_edit = PermissionService::currentUserCan('edit_flag') && !$read_only;
$can_toggle = PermissionService::currentUserCan('toggle_flag') && !$read_only;
$can_modify = $can_edit || $can_toggle;
?>

<a href="<?= e($return_to) ?>" class="detail-back-link" title="Quay lại danh sách" aria-label="Quay lại danh sách">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
  <span>Quay lại</span>
</a>
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
</div>

<?php if ($read_only || !$can_modify): ?>
  <div class="alert alert-warning">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    <?php if ($read_only): ?>
      Bạn đang ở chế độ <strong>Chỉ xem</strong>. Hãy mở Chi tiết để chỉnh sửa khi có quyền.
    <?php else: ?>
      Bạn không có quyền chỉnh sửa cấu hình cờ này.
    <?php endif; ?>
  </div>
<?php endif; ?>

<form method="POST" action="flag-detail.php?key=<?= urlencode($key) ?>&mode=edit&return_to=<?= urlencode($return_to) ?>" class="<?= $can_modify ? 'flag-detail-edit-form' : '' ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="return_to" value="<?= e($return_to) ?>">

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.25rem">
    <!-- Cột trái: Cấu hình -->
    <div style="display:flex;flex-direction:column;gap:1.25rem">

      <!-- Card: Thông tin cờ -->
      <div class="card">
        <div class="card-header"><h2 class="card-title">Thông tin cơ bản</h2></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Tên hiển thị</label>
            <input type="text" name="name" class="form-control" value="<?= e($flag['name']) ?>" <?= !$can_edit ? 'readonly' : '' ?>>
          </div>
          <div class="form-group">
            <label class="form-label">Mã cờ (Key)</label>
            <input type="text" class="form-control font-mono" value="<?= e($flag['key']) ?>" readonly>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Mô tả</label>
            <textarea name="description" class="form-control" rows="2" <?= !$can_edit ? 'readonly' : '' ?>><?= e($flag['description']) ?></textarea>
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
                <input type="checkbox" name="enabled" <?= $on?'checked':'' ?> <?= !$can_toggle?'disabled':'' ?>>
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
                   <?= !$can_toggle?'disabled':'' ?> style="width:100%;margin:.5rem 0">
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
          <h2 class="card-title">Ngoại lệ cá nhân</h2>
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

      <?php if ($can_modify): ?>
      <!-- Lý do thay đổi -->
      <div class="card">
        <div class="card-body">
          <div class="form-group" style="margin-bottom:.75rem">
            <label class="form-label">Lý do thay đổi</label>
            <input type="text" name="change_reason" class="form-control" placeholder="Nhập lý do thay đổi">
          </div>
          <div class="flag-detail-submit-actions">
            <button type="submit" class="btn btn-primary">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              Lưu cấu hình
            </button>
            <a href="<?= e($return_to) ?>" class="btn btn-secondary">Hủy thay đổi</a>
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
            <input type="text" id="testUserId" class="form-control font-mono" value="KH1" placeholder="Nhập mã khách hàng thử" readonly>
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

<style>
.flag-detail-edit-form { padding-bottom: 5rem; }
.flag-detail-submit-actions {
  position: fixed;
  right: 0;
  bottom: 0;
  left: var(--sidebar-width, 0);
  z-index: 900;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: .75rem;
  min-height: 4rem;
  padding: .75rem 1.5rem;
  border-top: 1px solid var(--border-color);
  background: rgba(255, 255, 255, .96);
  box-shadow: 0 -4px 16px rgba(15, 23, 42, .08);
  backdrop-filter: blur(8px);
}

@media (max-width: 768px) {
  .flag-detail-submit-actions {
    left: 0;
    right: 0;
    padding-right: 1rem;
    padding-left: 1rem;
  }

  .flag-detail-submit-actions .btn { flex: 1; }
}
</style>

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
