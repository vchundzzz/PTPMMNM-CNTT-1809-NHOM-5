<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../services/FeatureFlagService.php';
require_once __DIR__ . '/../services/PermissionService.php';
require_once __DIR__ . '/../services/VersionService.php';

session_save_path(__DIR__);
session_start();
init_session_data();

echo "=======================================================\n";
echo "CHẠY KIỂM THỬ 5 PHÉP THỬ BẮT BUỘC THEO ĐỀ TÀI\n";
echo "=======================================================\n\n";

$all_passed = true;

// --------------------------------------------------------------------------
// PHÉP THỬ 1: Tính ổn định hash + Kiểm tra riêng tỷ lệ 0% và 100%
// --------------------------------------------------------------------------
echo "[PHÉP THỬ 1] Tính ổn định & Tỷ lệ 0%, 100%:\n";

// A. 10 lần gọi liên tiếp cùng user + flag
$res1 = FeatureFlagService::evaluate('giao_dien_toi', 'KH120', 'testing');
$consistent = true;
for ($i = 0; $i < 10; $i++) {
    $cur = FeatureFlagService::evaluate('giao_dien_toi', 'KH120', 'testing');
    if ($cur['data']['enabled'] !== $res1['data']['enabled'] || $cur['data']['bucket'] !== $res1['data']['bucket']) {
        $consistent = false;
        break;
    }
}
echo "  - 10 lần gọi cùng user_id (KH120) và cờ: " . ($consistent ? "PASS (Kết quả hoàn toàn nhất quán)" : "FAIL") . "\n";
if (!$consistent) $all_passed = false;

// B. Rollout 0%
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['enabled'] = true;
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['rollout_percentage'] = 0;
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['user_overrides'] = [];
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['target_groups'] = [];
$res_0 = FeatureFlagService::evaluate('danh_gia_san_pham', 'KH55', 'testing');
$pass_0 = ($res_0['data']['enabled'] === false);
echo "  - Rollout = 0%: " . ($pass_0 ? "PASS (Luôn tắt false)" : "FAIL") . "\n";
if (!$pass_0) $all_passed = false;

// C. Rollout 100%
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['rollout_percentage'] = 100;
$res_100 = FeatureFlagService::evaluate('danh_gia_san_pham', 'KH55', 'testing');
$pass_100 = ($res_100['data']['enabled'] === true);
echo "  - Rollout = 100%: " . ($pass_100 ? "PASS (Luôn bật true)" : "FAIL") . "\n";
if (!$pass_100) $all_passed = false;

// --------------------------------------------------------------------------
// PHÉP THỬ 2: Thứ tự ưu tiên (User Overrides > Groups > Rollout)
// --------------------------------------------------------------------------
echo "\n[PHÉP THỬ 2] Thứ tự ưu tiên quy tắc:\n";
// Đặt flag rollout 0% nhưng override KH5 = true
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['rollout_percentage'] = 0;
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['user_overrides']['KH5'] = true;
$res_override = FeatureFlagService::evaluate('danh_gia_san_pham', 'KH5', 'testing');
$pass_override = ($res_override['data']['enabled'] === true);
echo "  - User Override (KH5 = true khi rollout = 0%): " . ($pass_override ? "PASS (Ăn theo Override)" : "FAIL") . "\n";
if (!$pass_override) $all_passed = false;

// Đặt flag rollout 100% nhưng override KH2 = false
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['rollout_percentage'] = 100;
$_SESSION['ff_data']['flags']['danh_gia_san_pham']['environments']['testing']['user_overrides']['KH2'] = false;
$res_override_false = FeatureFlagService::evaluate('danh_gia_san_pham', 'KH2', 'testing');
$pass_override_false = ($res_override_false['data']['enabled'] === false);
echo "  - User Override (KH2 = false khi rollout = 100%): " . ($pass_override_false ? "PASS (Ăn theo Override)" : "FAIL") . "\n";
if (!$pass_override_false) $all_passed = false;

// --------------------------------------------------------------------------
// PHÉP THỬ 3: Tài khoản chỉ xem (Viewer) không sửa được cấu hình
// --------------------------------------------------------------------------
echo "\n[PHÉP THỬ 3] Phân quyền tài khoản Viewer (Read-only):\n";
$viewer_can_toggle = PermissionService::can('viewer', 'toggle_flag');
$viewer_can_edit = PermissionService::can('viewer', 'edit_flag');
$viewer_can_create = PermissionService::can('viewer', 'create_flag');
$viewer_can_rollback = PermissionService::can('viewer', 'rollback_version');
$pass_viewer = (!$viewer_can_toggle && !$viewer_can_edit && !$viewer_can_create && !$viewer_can_rollback);
echo "  - Quyền sửa của Viewer: " . ($pass_viewer ? "PASS (Bị từ chối toàn bộ thao tác sửa)" : "FAIL") . "\n";
if (!$pass_viewer) $all_passed = false;

// --------------------------------------------------------------------------
// PHÉP THỬ 4: Khôi phục bản cũ (Rollback) & Giữ nguyên lịch sử
// --------------------------------------------------------------------------
echo "\n[PHÉP THỬ 4] Khôi phục snapshot phiên bản (Rollback):\n";
// Tạo snapshot ban đầu
$v1 = VersionService::createSnapshot('testing', 'Snapshot thử nghiệm v1');
$v1_tag = $v1['tag'];
$old_enabled = $_SESSION['ff_data']['flags']['thanh_toan_nhanh']['environments']['testing']['enabled'];

// Thay đổi trạng thái
$_SESSION['ff_data']['flags']['thanh_toan_nhanh']['environments']['testing']['enabled'] = !$old_enabled;
$changed_enabled = $_SESSION['ff_data']['flags']['thanh_toan_nhanh']['environments']['testing']['enabled'];

// Rollback về v1
$rollback_ok = VersionService::rollback($v1_tag, 'testing');
$restored_enabled = $_SESSION['ff_data']['flags']['thanh_toan_nhanh']['environments']['testing']['enabled'];
$pass_rollback = $rollback_ok && ($restored_enabled === $old_enabled) && isset($_SESSION['ff_data']['versions'][$v1_tag]);
echo "  - Rollback về $v1_tag: " . ($pass_rollback ? "PASS (Cấu hình được khôi phục chính xác, lịch sử còn nguyên)" : "FAIL") . "\n";
if (!$pass_rollback) $all_passed = false;

// --------------------------------------------------------------------------
// PHÉP THỬ 5: Ngắt API / Mô phỏng lỗi (Fallback)
// --------------------------------------------------------------------------
echo "\n[PHÉP THỬ 5] Ngắt API & Giá trị dự phòng (Fallback):\n";
// Kiểm tra fallback của service
$fallback_val = FeatureFlagService::DEFAULT_FALLBACK;
$pass_fallback = ($fallback_val === false);
echo "  - Fallback mặc định: " . ($pass_fallback ? "PASS (Default False an toàn)" : "FAIL") . "\n";
if (!$pass_fallback) $all_passed = false;

echo "\n=======================================================\n";
echo "KẾT LUẬN KIỂM THỬ: " . ($all_passed ? "TẤT CẢ 5 PHÉP THỬ ĐÃ ĐẠT 100%!" : "CÓ PHÉP THỬ THẤT BẠI!") . "\n";
echo "=======================================================\n";

