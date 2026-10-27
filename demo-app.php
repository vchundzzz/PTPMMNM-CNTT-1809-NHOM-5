<?php
/**
 * Web Feature Flag Management System
 * demo-app.php - Ứng dụng Web bán lẻ Apple Storefront mô phỏng tích hợp Feature Flags
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/services/FeatureFlagService.php';

$page_title = 'Ứng dụng Demo (Apple Storefront)';
$breadcrumbs = [
    ['label' => 'Trang chủ', 'url' => 'dashboard.php'],
    ['label' => 'Ứng dụng demo', 'url' => ''],
];

// Tiếp nhận tham số người dùng và môi trường kiểm thử
$current_env = $_GET['env'] ?? get_active_env();
$current_user_id = $_GET['user_id'] ?? 'KH1';
$simulate_error = !empty($_GET['simulate_error']);

// Danh sách các cờ được đánh giá trực tiếp qua FeatureFlagService
$eval_results = [];
$flag_keys = [
    'thanh_toan_nhanh',
    'giao_dien_toi',
    'danh_gia_san_pham',
    'danh_sach_yeu_thich',
    'ho_tro_truc_tuyen',
    'ma_giam_gia',
    'goi_y_san_pham',
    'tim_kiem_nang_cao',
    'theo_doi_don_hang',
    'tich_diem_thanh_vien',
];

foreach ($flag_keys as $k) {
    if ($simulate_error) {
        $eval_results[$k] = [
            'success' => false,
            'data' => [
                'flag_key' => $k,
                'enabled' => FeatureFlagService::DEFAULT_FALLBACK, // Giá trị dự phòng: false
                'environment' => $current_env,
                'version' => 'fallback',
                'bucket' => null,
                'reason' => 'Lỗi kết nối API máy chủ; Kích hoạt giá trị dự phòng (Fallback: false)',
            ],
        ];
    } else {
        $eval_results[$k] = FeatureFlagService::evaluate($k, $current_user_id, $current_env);
    }
}

// Trạng thái cờ tiện lợi
$is_dark_mode = !empty($eval_results['giao_dien_toi']['data']['enabled']);
$is_reviews = !empty($eval_results['danh_gia_san_pham']['data']['enabled']);
$is_wishlist = !empty($eval_results['danh_sach_yeu_thich']['data']['enabled']);
$is_chat = !empty($eval_results['ho_tro_truc_tuyen']['data']['enabled']);
$is_coupon = !empty($eval_results['ma_giam_gia']['data']['enabled']);
$is_recs = !empty($eval_results['goi_y_san_pham']['data']['enabled']);
$is_checkout = !empty($eval_results['thanh_toan_nhanh']['data']['enabled']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Ứng dụng Demo: Apple Storefront</h1>
    <p class="page-description">
      Mô phỏng website thương mại điện tử thực tế được điều khiển động bởi hệ thống Feature Flags.
    </p>
  </div>
  <div class="page-actions">
    <a href="flags.php" class="btn btn-secondary">
      Quay lại Quản lý Cờ
    </a>
  </div>
</div>

<!-- ========================================================================
     BẢNG ĐIỀU KHIỂN THỬ NGHIỆM FEATURE FLAG (TESTING CONTROLLER)
     ======================================================================== -->
<div class="card" style="margin-bottom: 2rem; border-left: 4px solid var(--primary);">
  <div class="card-header" style="background-color: var(--primary-light);">
    <div style="font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 0.5rem;">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
        <polyline points="2 17 12 22 22 17"></polyline>
        <polyline points="2 12 12 17 22 12"></polyline>
      </svg>
      <span>Bảng điều khiển kiểm thử tính năng (Testing Controller)</span>
    </div>
    <div>
      <?php if ($simulate_error): ?>
        <span class="badge badge-danger">ĐANG MÔ PHỎNG LỖI API (FALLBACK ACTIVATED)</span>
      <?php else: ?>
        <span class="badge badge-success">API HOẠT ĐỘNG BÌNH THƯỜNG</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="card-body">
    <form method="GET" action="demo-app.php" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
      <!-- Chọn Môi trường -->
      <div style="min-width: 180px;">
        <label class="form-label">1. Môi trường:</label>
        <select name="env" class="form-select">
          <option value="testing" <?= ($current_env === 'testing') ? 'selected' : '' ?>>Kiểm thử</option>
          <option value="staging" <?= ($current_env === 'staging') ? 'selected' : '' ?>>Vận hành</option>
        </select>
      </div>

      <!-- Chọn hoặc nhập User ID -->
      <div style="min-width: 240px; flex: 1;">
        <label class="form-label">2. Mã người dùng (User ID):</label>
        <input type="text" name="user_id" class="form-control font-mono" value="<?= e($current_user_id) ?>" placeholder="Nhập mã khách hàng" list="sample_users_list">
        <datalist id="sample_users_list">
          <option value="KH1">KH1 (Nội bộ - thanh toán nhanh bật)</option>
          <option value="KH2">KH2 (Nội bộ - thanh toán nhanh tắt)</option>
          <option value="KH5">KH5 (Nội bộ - giao diện tối bật)</option>
          <option value="KH60">KH60 (Khách Beta)</option>
          <option value="KH500">KH500 (Khách Thường)</option>
          <option value="KH999">KH999 (Khách Thường)</option>
        </datalist>
      </div>

      <!-- Mô phỏng lỗi API để kiểm tra giá trị dự phòng -->
      <div style="display: flex; align-items: center; gap: 0.5rem; padding-bottom: 0.65rem;">
        <label style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; cursor: pointer; color: var(--danger); font-weight: 600;">
          <input type="checkbox" name="simulate_error" value="1" <?= $simulate_error ? 'checked' : '' ?>>
          <span>Mô phỏng lỗi API (Test Fallback)</span>
        </label>
      </div>

      <button type="submit" class="btn btn-primary">
        Kiểm tra & Cập nhật App
      </button>

      <?php if ($simulate_error): ?>
        <a href="demo-app.php?env=<?= urlencode($current_env) ?>&user_id=<?= urlencode($current_user_id) ?>" class="btn btn-secondary">
          Tắt mô phỏng lỗi
        </a>
      <?php endif; ?>
    </form>

    <!-- Quick User Links -->
    <div style="margin-top: 1rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; font-size: 0.8rem;">
      <span style="color: var(--text-muted); font-weight: 600;">Thử nhanh:</span>
      <a href="demo-app.php?env=<?= urlencode($current_env) ?>&user_id=KH1" class="badge badge-neutral">KH1 (Ngoại lệ bật)</a>
      <a href="demo-app.php?env=<?= urlencode($current_env) ?>&user_id=KH2" class="badge badge-neutral">KH2 (Ngoại lệ tắt)</a>
      <a href="demo-app.php?env=<?= urlencode($current_env) ?>&user_id=KH60" class="badge badge-neutral">KH60 (Khách Beta)</a>
      <a href="demo-app.php?env=<?= urlencode($current_env) ?>&user_id=KH500" class="badge badge-neutral">KH500 (Khách Thường)</a>
    </div>
  </div>

  <!-- Bảng chẩn đoán trạng thái 10 Cờ theo thời gian thực -->
  <div style="border-top: 1px solid var(--border-color); padding: 1rem 1.5rem; background: var(--bg-body);">
    <div style="font-size: 0.82rem; font-weight: 700; color: var(--text-secondary); margin-bottom: 0.75rem; text-transform: uppercase;">
      Kết quả đánh giá API theo thời gian thực cho [<?= e($current_user_id) ?> &bull; <?= ucfirst($current_env) ?>]:
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 0.65rem;">
      <?php foreach ($eval_results as $f_key => $res): 
          $en = !empty($res['data']['enabled']);
          $reason = $res['data']['reason'] ?? '';
      ?>
        <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 0.55rem 0.75rem; display: flex; flex-direction: column; gap: 0.2rem;">
          <div style="display: flex; align-items: center; justify-content: space-between;">
            <span class="font-mono" style="font-size: 0.8rem; font-weight: 600;"><?= e($f_key) ?></span>
            <span class="badge <?= $en ? 'badge-success' : 'badge-neutral' ?>" style="font-size: 0.68rem;">
              <?= $en ? 'ENABLED' : 'DISABLED' ?>
            </span>
          </div>
          <div style="font-size: 0.72rem; color: var(--text-muted); line-height: 1.2;">
            <?= e($reason) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ========================================================================
     GIAO DIỆN ỨNG DỤNG APPLE STOREFRONT DEMO
     ======================================================================== -->
<div class="storefront-container" style="background: <?= $is_dark_mode ? '#18181b' : '#ffffff' ?>; color: <?= $is_dark_mode ? '#f4f4f5' : '#0f172a' ?>; border-radius: var(--radius-lg); border: 1px solid var(--border-color); overflow: hidden; box-shadow: var(--shadow-md);">
  
  <!-- Storefront Header -->
  <div style="padding: 1.25rem 2rem; border-bottom: 1px solid <?= $is_dark_mode ? '#27272a' : 'var(--border-color)' ?>; display: flex; align-items: center; justify-content: space-between; background: <?= $is_dark_mode ? '#09090b' : '#f8fafc' ?>;">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
      <!-- Apple Logo SVG -->
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
        <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.47c.68-.83 1.14-1.99.99-3.14-.99.04-2.19.66-2.9 1.49-.63.73-1.18 1.92-1.03 3.06 1.1.09 2.24-.58 2.94-1.41z"/>
      </svg>
      <span style="font-weight: 700; font-size: 1.15rem; letter-spacing: -0.01em;">Apple Store Online</span>
    </div>

    <div style="display: flex; align-items: center; gap: 1.25rem;">
      <?php if ($is_wishlist): ?>
        <button type="button" class="btn btn-ghost btn-sm" style="color: #ef4444;" onclick="showToast('Tính năng Danh sách yêu thích (Wishlist) đang BẬT!', 'success')">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
          </svg>
          <span>Yêu thích (3)</span>
        </button>
      <?php endif; ?>

      <span style="font-size: 0.85rem; color: var(--text-muted);">
        Xin chào, <strong><?= e($current_user_id) ?></strong>
      </span>
    </div>
  </div>

  <!-- Hero Banner -->
  <div style="padding: 3rem 2rem; text-align: center; background: <?= $is_dark_mode ? '#18181b' : 'linear-gradient(180deg, #f8fafc 0%, #ffffff 100%)' ?>; border-bottom: 1px solid <?= $is_dark_mode ? '#27272a' : 'var(--border-color)' ?>;">
    <h2 style="font-size: 2.2rem; font-weight: 800; margin-bottom: 0.5rem; letter-spacing: -0.02em;">
      iPhone 16 Pro Max
    </h2>
    <p style="font-size: 1.1rem; color: <?= $is_dark_mode ? '#a1a1aa' : 'var(--text-muted)' ?>; max-width: 580px; margin: 0 auto 1.5rem auto;">
      Khung Titanium đẳng cấp, chip A18 Pro dẫn đầu công nghệ và nút điều khiển Camera hoàn toàn mới.
    </p>

    <?php if ($is_coupon): ?>
      <div style="display: inline-block; background-color: var(--warning-light); border: 1px dashed var(--warning); padding: 0.5rem 1.25rem; border-radius: var(--radius-full); margin-bottom: 1.5rem; font-size: 0.88rem; color: var(--warning-text); font-weight: 600;">
        &star; [Feature Flag: coupon_system = ENABLED] Tự động áp dụng mã <strong>APPLECARE10</strong> giảm ngay 10%!
      </div>
    <?php endif; ?>
  </div>

  <!-- Danh sách 4 sản phẩm mẫu -->
  <div style="padding: 2.5rem 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
      <h3 style="font-size: 1.35rem; font-weight: 700;">Dòng sản phẩm nổi bật</h3>
      <span style="font-size: 0.85rem; color: var(--text-muted);">Môi trường: <strong><?= ucfirst($current_env) ?></strong></span>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.75rem;">
      <!-- Sản phẩm 1: iPhone 16 Pro -->
      <div class="card" style="background: <?= $is_dark_mode ? '#27272a' : '#ffffff' ?>; border-color: <?= $is_dark_mode ? '#3f3f46' : 'var(--border-color)' ?>;">
        <div class="card-body" style="padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
          <div>
            <div style="height: 140px; background: <?= $is_dark_mode ? '#18181b' : '#f1f5f9' ?>; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; font-size: 2.5rem;">
              📱
            </div>

            <h4 style="font-size: 1.1rem; font-weight: 700; color: <?= $is_dark_mode ? '#ffffff' : 'var(--text-main)' ?>; margin-bottom: 0.35rem;">
              iPhone 16 Pro 128GB
            </h4>
            <div style="font-size: 1.15rem; font-weight: 800; color: var(--primary); margin-bottom: 0.65rem;">
              28.990.000đ
            </div>

            <!-- Feature Flag: product_reviews -->
            <?php if ($is_reviews): ?>
              <div style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; color: #f59e0b; margin-bottom: 0.75rem;">
                <span>★★★★★</span>
                <span style="color: var(--text-muted);">(4.9 &bull; 142 đánh giá)</span>
              </div>
            <?php endif; ?>
          </div>

          <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
            <button type="button" class="btn btn-primary btn-sm" style="flex: 1;" onclick="handleBuyProduct('iPhone 16 Pro', <?= $is_checkout ? 'true' : 'false' ?>)">
              <?= $is_checkout ? '⚡ One-Step Checkout' : 'Mua ngay' ?>
            </button>
            <?php if ($is_wishlist): ?>
              <button type="button" class="btn btn-secondary btn-sm" onclick="showToast('Đã lưu iPhone 16 Pro vào Wishlist!', 'success')">
                ❤️
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Sản phẩm 2: MacBook Pro M3 -->
      <div class="card" style="background: <?= $is_dark_mode ? '#27272a' : '#ffffff' ?>; border-color: <?= $is_dark_mode ? '#3f3f46' : 'var(--border-color)' ?>;">
        <div class="card-body" style="padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
          <div>
            <div style="height: 140px; background: <?= $is_dark_mode ? '#18181b' : '#f1f5f9' ?>; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; font-size: 2.5rem;">
              💻
            </div>

            <h4 style="font-size: 1.1rem; font-weight: 700; color: <?= $is_dark_mode ? '#ffffff' : 'var(--text-main)' ?>; margin-bottom: 0.35rem;">
              MacBook Pro 14" M3
            </h4>
            <div style="font-size: 1.15rem; font-weight: 800; color: var(--primary); margin-bottom: 0.65rem;">
              39.990.000đ
            </div>

            <?php if ($is_reviews): ?>
              <div style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; color: #f59e0b; margin-bottom: 0.75rem;">
                <span>★★★★★</span>
                <span style="color: var(--text-muted);">(4.8 &bull; 89 đánh giá)</span>
              </div>
            <?php endif; ?>
          </div>

          <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
            <button type="button" class="btn btn-primary btn-sm" style="flex: 1;" onclick="handleBuyProduct('MacBook Pro 14&quot; M3', <?= $is_checkout ? 'true' : 'false' ?>)">
              <?= $is_checkout ? '⚡ One-Step Checkout' : 'Mua ngay' ?>
            </button>
            <?php if ($is_wishlist): ?>
              <button type="button" class="btn btn-secondary btn-sm" onclick="showToast('Đã lưu MacBook Pro vào Wishlist!', 'success')">
                ❤️
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Sản phẩm 3: AirPods Pro 2 -->
      <div class="card" style="background: <?= $is_dark_mode ? '#27272a' : '#ffffff' ?>; border-color: <?= $is_dark_mode ? '#3f3f46' : 'var(--border-color)' ?>;">
        <div class="card-body" style="padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
          <div>
            <div style="height: 140px; background: <?= $is_dark_mode ? '#18181b' : '#f1f5f9' ?>; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; font-size: 2.5rem;">
              🎧
            </div>

            <h4 style="font-size: 1.1rem; font-weight: 700; color: <?= $is_dark_mode ? '#ffffff' : 'var(--text-main)' ?>; margin-bottom: 0.35rem;">
              AirPods Pro 2 USB-C
            </h4>
            <div style="font-size: 1.15rem; font-weight: 800; color: var(--primary); margin-bottom: 0.65rem;">
              5.990.000đ
            </div>

            <?php if ($is_reviews): ?>
              <div style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; color: #f59e0b; margin-bottom: 0.75rem;">
                <span>★★★★★</span>
                <span style="color: var(--text-muted);">(4.9 &bull; 320 đánh giá)</span>
              </div>
            <?php endif; ?>
          </div>

          <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
            <button type="button" class="btn btn-primary btn-sm" style="flex: 1;" onclick="handleBuyProduct('AirPods Pro 2', <?= $is_checkout ? 'true' : 'false' ?>)">
              <?= $is_checkout ? '⚡ One-Step Checkout' : 'Mua ngay' ?>
            </button>
            <?php if ($is_wishlist): ?>
              <button type="button" class="btn btn-secondary btn-sm" onclick="showToast('Đã lưu AirPods Pro 2 vào Wishlist!', 'success')">
                ❤️
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Sản phẩm 4: Apple Watch Ultra 2 -->
      <div class="card" style="background: <?= $is_dark_mode ? '#27272a' : '#ffffff' ?>; border-color: <?= $is_dark_mode ? '#3f3f46' : 'var(--border-color)' ?>;">
        <div class="card-body" style="padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;">
          <div>
            <div style="height: 140px; background: <?= $is_dark_mode ? '#18181b' : '#f1f5f9' ?>; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; font-size: 2.5rem;">
              ⌚
            </div>

            <h4 style="font-size: 1.1rem; font-weight: 700; color: <?= $is_dark_mode ? '#ffffff' : 'var(--text-main)' ?>; margin-bottom: 0.35rem;">
              Apple Watch Ultra 2
            </h4>
            <div style="font-size: 1.15rem; font-weight: 800; color: var(--primary); margin-bottom: 0.65rem;">
              21.490.000đ
            </div>

            <?php if ($is_reviews): ?>
              <div style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; color: #f59e0b; margin-bottom: 0.75rem;">
                <span>★★★★★</span>
                <span style="color: var(--text-muted);">(4.7 &bull; 65 đánh giá)</span>
              </div>
            <?php endif; ?>
          </div>

          <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
            <button type="button" class="btn btn-primary btn-sm" style="flex: 1;" onclick="handleBuyProduct('Apple Watch Ultra 2', <?= $is_checkout ? 'true' : 'false' ?>)">
              <?= $is_checkout ? '⚡ One-Step Checkout' : 'Mua ngay' ?>
            </button>
            <?php if ($is_wishlist): ?>
              <button type="button" class="btn btn-secondary btn-sm" onclick="showToast('Đã lưu Apple Watch vào Wishlist!', 'success')">
                ❤️
              </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Feature Flag: product_recommendations -->
    <?php if ($is_recs): ?>
      <div style="margin-top: 2.5rem; padding: 1.5rem; background: <?= $is_dark_mode ? '#27272a' : '#eff6ff' ?>; border: 1px solid <?= $is_dark_mode ? '#3f3f46' : 'var(--primary-border)' ?>; border-radius: var(--radius-md);">
        <h4 style="font-size: 1.05rem; font-weight: 700; color: var(--primary); margin-bottom: 0.35rem;">
          ✨ [Feature Flag: product_recommendations = ENABLED] Gợi ý phụ kiện thông minh bằng AI
        </h4>
        <p style="font-size: 0.85rem; color: <?= $is_dark_mode ? '#d4d4d8' : 'var(--text-secondary)' ?>;">
          Khách hàng mua iPhone 16 Pro thường chọn thêm củ sạc nhanh 30W USB-C và ốp lưng MagSafe Silicon chính hãng.
        </p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Feature Flag: live_chat Floating Widget -->
<?php if ($is_chat): ?>
  <div style="position: fixed; bottom: 2rem; right: 2rem; z-index: 500;">
    <button type="button" class="btn btn-primary" style="border-radius: var(--radius-full); padding: 0.75rem 1.25rem; box-shadow: var(--shadow-lg);" onclick="showToast('Hộp thoại tư vấn trực tuyến Live Chat 24/7 đang hoạt động!', 'info')">
      💬 Tư vấn viên Apple (Live Chat)
    </button>
  </div>
<?php endif; ?>

<script>
  function handleBuyProduct(productName, isOneStepCheckout) {
    if (isOneStepCheckout) {
      showToast(`[One-Step Checkout]: Mở thanh toán nhanh 1 bước cho sản phẩm "${productName}".`, 'success');
    } else {
      showToast(`[Standard Flow]: Chuyển hướng đến giỏ hàng truyền thống 3 bước cho sản phẩm "${productName}".`, 'info');
    }
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
