<?php
/**
 * Hệ thống Quản lý Feature Flags
 * flag-detail.php – Chi tiết & chỉnh sửa cờ tính năng (Đồng bộ 100% với Khách hàng thực tế & Nhóm khách hàng)
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/services/PermissionService.php';
require_once __DIR__ . '/services/DatabaseService.php';
require_once __DIR__ . '/services/FeatureFlagService.php';

init_session_data(true);

$key        = trim($_GET['key'] ?? '');
$flags      = $_SESSION['ff_data']['flags'] ?? [];
$groups_all = $_SESSION['ff_data']['groups'] ?? [];
$registered_customers = load_customers();

// Map tra cứu nhanh khách hàng theo Mã KH hoặc Username
$customer_by_id = [];
$customer_by_username = [];
$customers_by_group = [];
foreach ($registered_customers as $c) {
    $cid = strtoupper((string)($c['id'] ?? ''));
    $uname = strtolower((string)($c['username'] ?? ''));
    $gid = (string)($c['group_id'] ?? 'nhom_chung');
    if ($cid !== '') {
        $customer_by_id[$cid] = $c;
    }
    if ($uname !== '') {
        $customer_by_username[$uname] = $c;
    }
    $customers_by_group[$gid][] = $c;
}

if (!isset($flags[$key])) {
    set_flash('error', 'Không tìm thấy cờ tính năng.');
    header('Location: flags.php');
    exit;
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

    // Parse user overrides (hỗ trợ cả Mã KH lẫn Tên đăng nhập khách hàng)
    $raw_ov = $can_edit_config ? $posted_overrides : '';
    $new_ov = [];
    if ($can_edit_config && !empty($raw_ov)) {
        foreach (explode("\n", $raw_ov) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (preg_match('/^([A-Za-z0-9_.-]+)\s*[=:]\s*(true|false|1|0|bật|tắt|bat|tat)/iu', $line, $m)) {
                $input_key = trim($m[1]);
                $upper_key = strtoupper($input_key);
                $lower_key = strtolower($input_key);

                if (isset($customer_by_id[$upper_key])) {
                    $uid = (string)$customer_by_id[$upper_key]['id'];
                } elseif (isset($customer_by_username[$lower_key])) {
                    $uid = (string)$customer_by_username[$lower_key]['id'];
                } else {
                    $uid = preg_match('/^kh\d+$/i', $input_key) ? $upper_key : $input_key;
                }

                $state = in_array(mb_strtolower($m[2], 'UTF-8'), ['true', '1', 'bật', 'bat'], true);
                $new_ov[$uid] = $state;
            }
        }
    } elseif (!$can_edit_config) {
        $new_ov = $current_config['user_overrides'] ?? [];
    }

    if (!$can_edit_config && !$can_toggle) {
        PermissionService::enforce('edit_flag');
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

    $old_pct = $current_config['rollout_percentage'] ?? 0;
    $old_name = $flags[$key]['name'] ?? '';
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
    $saved = DatabaseService::saveFlagDetail(
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
    if (!$saved) {
        init_session_data(true);
        set_flash('error', 'Không thể lưu cấu hình cờ vào CSDL MySQL.');
        header('Location: flag-detail.php?key=' . urlencode($key) . '&mode=edit&return_to=' . urlencode($return_to));
        exit;
    }

    append_audit_log(
        'SỬA_CẤU_HÌNH',
        $key,
        "Tên: $old_name | Tỷ lệ: $old_pct%",
        "Tên: $name | Tỷ lệ: $pct% | " . ($enabled ? 'Bật' : 'Tắt'),
        trim((string)($_POST['change_reason'] ?? 'Cập nhật từ trang chi tiết'))
    );
    set_flash('success', "Đã lưu cấu hình cờ [$key] và đồng bộ trực tiếp với khách hàng.");
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

$default_test_uid = !empty($registered_customers[0]['id']) ? (string)$registered_customers[0]['id'] : 'KH1';

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
                <input type="checkbox" name="enabled" id="flagEnabledSwitch" <?= $on?'checked':'' ?> <?= !$can_toggle?'disabled':'' ?>>
                <span class="slider"></span>
              </label>
              <span style="font-weight:600;color:<?= $on?'var(--success)':'var(--text-secondary)' ?>"><?= $on?'Đang bật':'Đang tắt' ?></span>
            </div>
          </div>

          <!-- Rollout -->
          <div class="form-group">
            <label class="form-label">Tỷ lệ triển khai: <strong id="pctDisplay"><?= $pct ?></strong>%</label>
            <input type="range" name="rollout_percentage" id="flagRolloutRange" min="0" max="100" step="10" value="<?= $pct ?>"
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
            <div style="display:flex;flex-direction:column;gap:.55rem">
              <?php foreach ($groups_all as $gid => $g):
                $grp_custs = $customers_by_group[$gid] ?? [];
                $grp_count = count($grp_custs);
                $grp_labels = array_map(fn($c) => ($c['id'] ?? '') . ' (' . ($c['username'] ?? '') . ')', array_slice($grp_custs, 0, 5));
                $grp_summary = $grp_count > 0
                    ? ($grp_count . ' khách hàng: ' . implode(', ', $grp_labels) . ($grp_count > 5 ? '...' : ''))
                    : 'Chưa có khách hàng';
              ?>
                <label style="display:flex;align-items:center;gap:.5rem;font-size:.85rem;flex-wrap:wrap">
                  <input type="checkbox" name="target_groups[]" value="<?= e($gid) ?>"
                         <?= in_array($gid, $env_cfg['target_groups'] ?? [],true)?'checked':'' ?>
                         <?= !$can_edit?'disabled':'' ?>>
                  <strong><?= e($g['name']) ?></strong>
                  <span style="color:var(--text-secondary);font-size:.8rem">(<?= e($grp_summary) ?>)</span>
                </label>
              <?php endforeach; ?>
            </div>
            <div class="form-text">Không chọn nhóm nào = áp dụng cho tất cả nhóm khách hàng. Nếu chỉ chọn một số nhóm, chỉ khách hàng thuộc nhóm được tích mới được bật tính năng.</div>
          </div>
        </div>
      </div>

      <!-- Card: Ngoại lệ cá nhân -->
      <div class="card">
        <div class="card-header">
          <h2 class="card-title">Ngoại lệ cá nhân (Theo Mã KH hoặc Tên đăng nhập)</h2>
        </div>
        <div class="card-body">
          <?php if ($can_edit && !empty($registered_customers)): ?>
          <div style="margin-bottom:.85rem;padding:.75rem;background:var(--gray-50);border:1px solid var(--border-color);border-radius:var(--radius-md)">
            <div style="font-size:.78rem;font-weight:600;color:var(--text-secondary);margin-bottom:.45rem">
              Chọn nhanh khách hàng đã đăng ký để thêm quy tắc riêng:
            </div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
              <select id="quickOverrideCustomer" class="form-control" style="flex:1;min-width:180px;padding:.38rem .6rem;font-size:.82rem">
                <?php foreach ($registered_customers as $rc): ?>
                  <option value="<?= e($rc['id']) ?>">
                    <?= e($rc['id']) ?> — <?= e($rc['username']) ?> (<?= e($rc['group_name'] ?? 'Khách Thường') ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <select id="quickOverrideState" class="form-control" style="width:140px;padding:.38rem .6rem;font-size:.82rem">
                <option value="bật">Cưỡng chế BẬT</option>
                <option value="tắt">Cưỡng chế TẮT</option>
              </select>
              <button type="button" class="btn btn-secondary btn-sm" onclick="addQuickOverride()">
                + Áp dụng cho KH này
              </button>
            </div>
          </div>
          <?php endif; ?>

          <label class="form-label">Danh sách ngoại lệ (mỗi dòng một khách hàng)</label>
          <textarea name="user_overrides_raw" id="userOverridesTextarea" class="form-control font-mono" rows="4"
                    placeholder="VD: KH1 = bật hoặc chungdzvl = tắt"
                    <?= !$can_edit?'readonly':'' ?>><?= e(trim($ov_text)) ?></textarea>
          <div class="form-text">Hỗ trợ nhập theo <strong>Mã khách hàng</strong> (<code>KH1 = bật</code>) hoặc <strong>Tên đăng nhập</strong> (<code>chungdzvl = tắt</code>). Ngoại lệ cá nhân được ưu tiên cao nhất.</div>

          <?php if (!empty($ov)): ?>
          <div style="margin-top:.75rem">
            <table class="data-table" style="font-size:.8rem">
              <thead>
                <tr>
                  <th>Mã khách hàng</th>
                  <th>Tên đăng nhập</th>
                  <th>Nhóm hiện tại</th>
                  <th>Quy tắc ghi đè</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($ov as $uid => $state):
                  $cInfo = $customer_by_id[strtoupper((string)$uid)] ?? $customer_by_username[strtolower((string)$uid)] ?? null;
                ?>
                <tr>
                  <td><span class="font-mono" style="font-weight:700;color:var(--primary)"><?= e((string)($cInfo['id'] ?? $uid)) ?></span></td>
                  <td><strong><?= e((string)($cInfo['username'] ?? '—')) ?></strong></td>
                  <td><?= e((string)($cInfo['group_name'] ?? 'Chưa đăng ký')) ?></td>
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

      <!-- Kiểm thử nhanh bằng API (đồng bộ danh sách khách hàng thực tế) -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Kiểm thử nhanh & Tra cứu số Bucket</h3></div>
        <div class="card-body">
          <?php if (!empty($registered_customers)): ?>
          <div style="margin-bottom:.85rem;padding:.65rem .75rem;background:var(--gray-50);border:1px solid var(--border-color);border-radius:var(--radius-md);font-size:.78rem">
            <div style="font-weight:700;color:var(--text-primary);margin-bottom:.4rem">
              📊 Bảng số Bucket của khách hàng với cờ <span class="font-mono">[<?= e($flag['key']) ?>]</span>:
            </div>
            <div style="display:flex;flex-direction:column;gap:.3rem">
              <?php foreach ($registered_customers as $rc):
                $rc_bucket = FeatureFlagService::calculateBucket((string)$rc['id'], (string)$flag['key']);
                $min_pct = (int)(floor($rc_bucket / 10) + 1) * 10;
              ?>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:.4rem;padding:.25rem .4rem;background:#fff;border-radius:4px;border:1px solid var(--border-color)">
                  <span><strong><?= e($rc['id']) ?></strong> (<?= e($rc['username']) ?>)</span>
                  <span>
                    Bucket: <strong class="font-mono" style="color:var(--primary)"><?= $rc_bucket ?></strong>
                    <span style="color:var(--text-secondary);font-size:.73rem">(Bật khi ≥ <?= $min_pct ?>%)</span>
                  </span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Chọn tài khoản khách hàng đã đăng ký</label>
            <select id="testCustomerSelect" class="form-control" onchange="quickTest()">
              <?php foreach ($registered_customers as $rc):
                $rc_bucket = FeatureFlagService::calculateBucket((string)$rc['id'], (string)$flag['key']);
              ?>
                <option value="<?= e($rc['id']) ?>">
                  <?= e($rc['id']) ?> — <?= e($rc['username']) ?> (Bucket: <?= $rc_bucket ?> • <?= e($rc['group_name'] ?? 'Khách Thường') ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php else: ?>
          <div style="margin-bottom:.75rem;font-size:.82rem;color:var(--text-secondary)">
            Chưa có khách hàng nào đăng ký tài khoản.
          </div>
          <?php endif; ?>
          <button type="button" class="btn btn-secondary" style="width:100%" onclick="quickTest()">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            Gọi API kiểm thử
          </button>
          <div id="testResult" style="margin-top:.85rem;font-size:.82rem;display:none">
            <div class="alert alert-info" id="testResultContent"></div>
          </div>
        </div>
      </div>

      <a href="demo-app.php" target="_blank" rel="noopener" class="btn btn-secondary" style="text-align:center">
        Mở trang Web CDS STORE (Tab mới) ↗
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
function addQuickOverride() {
  const uid = document.getElementById('quickOverrideCustomer')?.value;
  const state = document.getElementById('quickOverrideState')?.value || 'bật';
  const textarea = document.getElementById('userOverridesTextarea');
  if (!uid || !textarea) return;

  const lines = textarea.value.split('\n').map(l => l.trim()).filter(Boolean);
  const filtered = lines.filter(line => {
    const m = line.match(/^([A-Za-z0-9_.-]+)\s*[=:]/);
    return !m || m[1].toUpperCase() !== uid.toUpperCase();
  });
  filtered.push(`${uid} = ${state}`);
  textarea.value = filtered.join('\n');
}

function quickTest() {
  const uid = (document.getElementById('testCustomerSelect')?.value || '<?= e($default_test_uid) ?>').trim();
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
      const userLabel = d.username
        ? `<strong>${d.customer_id}</strong> (${d.username} &bull; ${d.group_name})`
        : `<strong>${uid}</strong>`;
      cnt.innerHTML = `<div style="line-height:1.5">${userLabel} → <strong>${d.enabled ? 'ĐANG BẬT' : 'ĐANG TẮT'}</strong><br>
        <span style="color:var(--text-secondary)">${d.reason}</span>
        ${d.bucket !== null ? `<br><small>Bucket băm: ${d.bucket}</small>` : ''}</div>`;
    })
    .catch(() => {
      document.getElementById('testResult').style.display = 'block';
      document.getElementById('testResultContent').className = 'alert alert-danger';
      document.getElementById('testResultContent').textContent = 'Lỗi kết nối API.';
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
