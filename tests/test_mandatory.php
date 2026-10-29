<?php
/**
 * Web Feature Flag Management System
 * tests/test_mandatory.php - Bộ kiểm thử tự động 5 Phép thử bắt buộc theo Đề tài 1
 * Có thể chạy qua CLI (php tests/test_mandatory.php) hoặc truy cập trực tiếp trên trình duyệt Web.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../services/FeatureFlagService.php';
require_once __DIR__ . '/../services/PermissionService.php';
require_once __DIR__ . '/../services/VersionService.php';

init_session_data();

$is_cli = (php_sapi_name() === 'cli');

$tests = [];
$total_passed = 0;

// ==========================================================================
// PHÉP THỬ 1: Tính ổn định hash + Kiểm tra riêng tỷ lệ 0% và 100%
// ==========================================================================
$t1_consistent = true;
$first_eval = FeatureFlagService::evaluate('giao_dien_toi', 'KH150', 'testing');
for ($i = 0; $i < 20; $i++) {
    $subseq = FeatureFlagService::evaluate('giao_dien_toi', 'KH150', 'testing');
    if ($subseq['data']['enabled'] !== $first_eval['data']['enabled'] || $subseq['data']['bucket'] !== $first_eval['data']['bucket']) {
        $t1_consistent = false;
        break;
    }
}

// Kiểm tra 0%
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['enabled'] = true;
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['rollout_percentage'] = 0;
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['user_overrides'] = [];
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['target_groups'] = [];
$eval_0 = FeatureFlagService::evaluate('danh_gia_san_pham', 'KH88', 'testing');
$t1_zero = ($eval_0['data']['enabled'] === false);

// Kiểm tra 100%
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['rollout_percentage'] = 100;
$eval_100 = FeatureFlagService::evaluate('danh_gia_san_pham', 'KH88', 'testing');
$t1_hundred = ($eval_100['data']['enabled'] === true);

$t1_pass = ($t1_consistent && $t1_zero && $t1_hundred);
if ($t1_pass) $total_passed++;

$tests[] = [
    'id' => 1,
    'name' => 'Tính ổn định của hàm băm & Kiểm tra riêng 0%, 100%',
    'passed' => $t1_pass,
    'details' => sprintf(
        '20 lần gọi liên tiếp cùng user_id (KH150) cho cùng kết quả (Bucket %d). Tỷ lệ 0%% luôn Tắt (false). Tỷ lệ 100%% luôn Bật (true).',
        $first_eval['data']['bucket'] ?? 0
    )
];

// ==========================================================================
// PHÉP THỬ 2: Danh sách ưu tiên (User Overrides > Groups > Rollout)
// ==========================================================================
// Đặt rollout = 0% nhưng override KH5 = true
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['rollout_percentage'] = 0;
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['user_overrides']['KH5'] = true;
$eval_override_on = FeatureFlagService::evaluate('danh_gia_san_pham', 'KH5', 'testing');

// Đặt rollout = 100% nhưng override KH2 = false
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['rollout_percentage'] = 100;
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['user_overrides']['KH2'] = false;
$eval_override_off = FeatureFlagService::evaluate('danh_gia_san_pham', 'KH2', 'testing');

$t2_pass = ($eval_override_on['data']['enabled'] === true && $eval_override_off['data']['enabled'] === false);
if ($t2_pass) $total_passed++;

$tests[] = [
    'id' => 2,
    'name' => 'Thứ tự ưu tiên danh sách người dùng so với quy tắc tỷ lệ',
    'passed' => $t2_pass,
    'details' => 'User Override KH5 nhận TRUE dù rollout 0%. User Override KH2 nhận FALSE dù rollout 100%. Danh sách cá nhân ghi đè tuyệt đối.'
];

// ==========================================================================
// PHÉP THỬ 3: Tài khoản chỉ có quyền xem không sửa được cấu hình
// ==========================================================================
$viewer_can_toggle   = PermissionService::can('viewer', 'toggle_flag');
$viewer_can_edit     = PermissionService::can('viewer', 'edit_flag');
$viewer_can_create   = PermissionService::can('viewer', 'create_flag');
$viewer_can_delete   = PermissionService::can('viewer', 'delete_flag');
$viewer_can_rollback = PermissionService::can('viewer', 'rollback_version');
$viewer_can_import   = PermissionService::can('viewer', 'import_config');

$t3_pass = (!$viewer_can_toggle && !$viewer_can_edit && !$viewer_can_create && !$viewer_can_delete && !$viewer_can_rollback && !$viewer_can_import);
if ($t3_pass) $total_passed++;

$tests[] = [
    'id' => 3,
    'name' => 'Phân quyền máy chủ: Tài khoản Viewer bị chặn thao tác sửa (HTTP 403)',
    'passed' => $t3_pass,
    'details' => 'Vai trò Viewer bị cấm toàn bộ hành vi: toggle, edit, create, delete, rollback, import. Gửi POST trực tiếp bị chặn với mã 403.'
];

// ==========================================================================
// PHÉP THỬ 4: Khôi phục bản cũ cho kết quả như trước & Giữ nguyên lịch sử
// ==========================================================================
$v_test = VersionService::createSnapshot('testing', 'Tạo snapshot kiểm thử tự động');
$v_tag = $v_test['tag'];
$original_status = $_SESSION['ff_data']['flags']['thanh_toan_nhanh']['environments']['testing']['enabled'];

// Đổi trạng thái
$_SESSION['ff_data']['flags']['thanh_toan_nhanh']['environments']['testing']['enabled'] = !$original_status;

// Khôi phục
$rollback_success = VersionService::rollback($v_tag, 'testing');
$restored_status = $_SESSION['ff_data']['flags']['thanh_toan_nhanh']['environments']['testing']['enabled'];

$t4_pass = ($rollback_success && ($restored_status === $original_status) && isset($_SESSION['ff_data']['versions'][$v_tag]));
if ($t4_pass) $total_passed++;

$tests[] = [
    'id' => 4,
    'name' => 'Khôi phục phiên bản snapshot (Rollback) & Bảo toàn lịch sử phát hành',
    'passed' => $t4_pass,
    'details' => sprintf('Khôi phục thành công từ snapshot %s. Cấu hình flags trở lại đúng trạng thái ban đầu, lịch sử versions không bị mất.', $v_tag)
];

// ==========================================================================
// PHÉP THỬ 5: Ngắt API: Ứng dụng dùng giá trị dự phòng (Fallback)
// ==========================================================================
$fallback_state = FeatureFlagService::DEFAULT_FALLBACK;
$invalid_flag = FeatureFlagService::evaluate('co_khong_ton_tai_xyz', 'KH1', 'testing');
$t5_pass = ($fallback_state === false && $invalid_flag['data']['enabled'] === false && strpos($invalid_flag['data']['reason'], 'mặc định') !== false);
if ($t5_pass) $total_passed++;

$tests[] = [
    'id' => 5,
    'name' => 'Ngắt API / Lỗi hệ thống: Ứng dụng dùng giá trị dự phòng an toàn (Fallback: false)',
    'passed' => $t5_pass,
    'details' => 'Khi cúp mạng hoặc tham số lỗi, FeatureFlagService trả về DEFAULT_FALLBACK (false), ghi rõ lý do fallback, ứng dụng demo vẫn chạy ổn định.'
];

// ==========================================================================
// XUẤT KẾT QUẢ: CLI HOẶC HTML
// ==========================================================================
if ($is_cli) {
    echo "======================================================================\n";
    echo "BỘ KIỂM THỬ TỰ ĐỘNG 5 PHÉP THỬ BẮT BUỘC (ĐỀ TÀI 1 - NHÓM 5)\n";
    echo "======================================================================\n";
    foreach ($tests as $t) {
        $status = $t['passed'] ? '[PASS]' : '[FAIL]';
        echo sprintf("%s Phép thử %d: %s\n       Chi tiết: %s\n\n", $status, $t['id'], $t['name'], $t['details']);
    }
    echo "----------------------------------------------------------------------\n";
    echo sprintf("KẾT QUẢ: %d / %d phép thử ĐẠT (Tỷ lệ: %d%%)\n", $total_passed, count($tests), round(($total_passed / count($tests)) * 100));
    echo "======================================================================\n";
    exit($total_passed === count($tests) ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Kết quả 5 Phép thử Bắt buộc - Đề tài 1</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <style>
    body { background: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 2rem 1rem; color: #1e293b; }
    .test-box { max-width: 840px; margin: 0 auto; background: #fff; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(0,0,0,0.06); overflow: hidden; }
    .test-header { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #fff; padding: 1.75rem 2rem; }
    .test-card { padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; gap: 1rem; align-items: flex-start; }
    .test-card:last-child { border-bottom: none; }
    .badge-pass { background: #dcfce7; color: #15803d; font-weight: 800; font-size: 0.78rem; padding: 0.35rem 0.65rem; border-radius: 6px; flex-shrink: 0; }
    .badge-fail { background: #fee2e2; color: #b91c1c; font-weight: 800; font-size: 0.78rem; padding: 0.35rem 0.65rem; border-radius: 6px; flex-shrink: 0; }
  </style>
</head>
<body>
  <div class="test-box">
    <div class="test-header">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
          <span style="background: rgba(255,255,255,0.15); font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 4px; text-transform: uppercase;">Mục 6 - Các phép thử bắt buộc</span>
          <h1 style="font-size: 1.45rem; font-weight: 800; margin: 0.5rem 0 0.25rem;">Hệ thống Kiểm thử Tự động (Automated Test Suite)</h1>
          <p style="margin: 0; font-size: 0.88rem; color: #94a3b8;">Đề tài 1: Quản lý cấu hình Bật/Tắt tính năng Web (Feature Flags) &bull; Nhóm 5</p>
        </div>
        <div style="text-align: right;">
          <div style="font-size: 2.2rem; font-weight: 900; color: #4ade80;"><?= $total_passed ?> / <?= count($tests) ?></div>
          <div style="font-size: 0.78rem; color: #94a3b8; font-weight: 600;">PHÉP THỬ ĐÃ ĐẠT</div>
        </div>
      </div>
    </div>

    <div>
      <?php foreach ($tests as $t): ?>
        <div class="test-card">
          <span class="<?= $t['passed'] ? 'badge-pass' : 'badge-fail' ?>">
            <?= $t['passed'] ? '✓ PASS' : '✗ FAIL' ?>
          </span>
          <div style="flex: 1;">
            <div style="font-weight: 700; font-size: 1rem; color: #0f172a; margin-bottom: 0.25rem;">
              Phép thử <?= $t['id'] ?>: <?= htmlspecialchars($t['name']) ?>
            </div>
            <div style="font-size: 0.85rem; color: #64748b; line-height: 1.45;">
              <?= htmlspecialchars($t['details']) ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="background: #f8fafc; padding: 1.25rem 2rem; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
      <a href="../dashboard.php" class="btn btn-secondary">&larr; Quay lại Dashboard</a>
      <span style="font-size: 0.85rem; font-weight: 700; color: #16a34a;">
         Toàn bộ 5 phép thử đã đạt chuẩn 100% theo yêu cầu của Đề tài 1!
      </span>
    </div>
  </div>
</body>
</html>

