<?php header('Content-Type: text/html; charset=utf-8'); ?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ứng Dụng Demo — Thử Nghiệm Tính Năng</title>
  <meta name="description" content="Ứng dụng demo tích hợp Feature Flag, tỷ lệ rollout và cơ chế fallback">
  <link rel="stylesheet" href="css/apple.php">
</head>
<body>

  <!-- ===================================================================
       1. THANH ĐIỀU KHIỂN DÀNH CHO THẦY CÔ & NGƯỜI CHẤM (DEMO CONTROLLER)
       =================================================================== -->
  <aside class="test-controller-bar" aria-label="Bảng điều khiển thử nghiệm">
    <div class="controller-left">
      <div class="controller-brand">
        <span>Ứng Dụng Demo (Gọi API PHP)</span>
      </div>

      <!-- Chọn User ID thử nghiệm -->
      <div class="controller-select-group">
        <label for="select-user">Người dùng:</label>
        <select id="select-user" class="controller-select" onchange="loadAppleFeatureFlags()">
          <option value="U0001">U0001 (VIP · Whitelist luôn BẬT)</option>
          <option value="U0002">U0002 (VIP · Whitelist)</option>
          <option value="U0010">U0010 (Beta Tester · Pre-order)</option>
          <option value="U0099">U0099 (Beta Tester · Giao diện Tết)</option>
          <option value="U0500" selected>U0500 (Khách thường · Thử tỷ lệ %)</option>
          <option value="U0750">U0750 (Khách thường · Thử tỷ lệ %)</option>
          <option value="U0999">U0999 (Khách thường · Cuối danh sách)</option>
        </select>
      </div>

      <!-- Nhập User ID tự do -->
      <div class="controller-select-group">
        <label for="input-custom-user">Tự gõ User:</label>
        <input type="text" id="input-custom-user" placeholder="VD: U0123" style="background:transparent; border:none; color:#fff; width:70px; font-size:12px; outline:none;" onchange="onCustomUserChange(this.value)">
      </div>

      <!-- Chọn Môi trường -->
      <div class="controller-select-group">
        <label for="select-env">Môi trường:</label>
        <select id="select-env" class="controller-select" onchange="loadAppleFeatureFlags()">
          <option value="thu_nghiem">Thử Nghiệm (thu_nghiem)</option>
          <option value="van_hanh" selected>Vận Hành (van_hanh)</option>
        </select>
      </div>
    </div>

    <div class="controller-right">
      <!-- Công tắc mô phỏng API lỗi (Test Fallback Yêu cầu Kỹ thuật 2) -->
      <label class="outage-toggle" title="Gạt công tắc để ngắt API và kiểm tra cơ chế fallback">
        <input type="checkbox" id="toggle-outage" onchange="toggleApiOutage(this.checked)">
        <span>Mô phỏng Lỗi API / Mất mạng</span>
      </label>

      <!-- Nút chuyển sang Trang Quản Trị -->
      <a href="/" class="btn-nav-admin" title="Mở trang quản trị để chỉnh cấu hình, phiên bản và cờ tính năng">
        <span>Sang Trang Quản Trị</span>
      </a>
    </div>
  </aside>

  <!-- ===================================================================
       2. THANH MENU ỨNG DỤNG
       =================================================================== -->
  <header class="apple-nav">
    <nav class="apple-nav-inner" aria-label="Menu ứng dụng">
      <a href="#">Tổng quan</a>
      <a href="#">Sản phẩm</a>
      <a href="#">Dịch vụ</a>
      <a href="#">Tài khoản</a>
      <a href="#">Báo cáo</a>
      <a href="#">Hỗ trợ</a>
    </nav>
  </header>

  <!-- Báo động khi API sập (Fallback Mode) -->
  <div id="outage-alert-box" class="inspector-panel" style="display: none; margin-top: 20px; margin-bottom: 0;">
    <div class="outage-alert">
      <div>
        <strong>CẢNH BÁO: MÁY CHỦ API KHÔNG PHẢN HỒI (MÔ PHỎNG SẬP MẠNG HOẶC LỖI 503)</strong><br>
        <span style="font-size: 13px;">Ứng dụng tự động chuyển sang đọc <strong>Giá Trị Dự Phòng (Fallback: TẮT các tính năng mới)</strong>. Hệ thống vẫn hoạt động bình thường!</span>
      </div>
    </div>
  </div>

  <!-- ===================================================================
       3. THANH THÔNG BÁO ƯU ĐÃI (TÍNH NĂNG 6, 7: TRẢ GÓP & GIAO HÀNG HỎA TỐC)
       =================================================================== -->
  <section class="sub-promo-bar" id="apple-promo-bar">
    <div class="sub-promo-item" id="flag-box-apple_pay_0percent" style="display: none;">
      <span><strong>Ưu đãi thanh toán:</strong> Hỗ trợ nhiều phương thức thanh toán.</span>
    </div>
    <div class="sub-promo-item" id="flag-box-express_delivery_2h" style="display: none;">
      <span><strong>Giao hàng hỏa tốc 2 giờ:</strong> Nhận hàng ngay tại TP.HCM &amp; Hà Nội.</span>
    </div>
    <div class="sub-promo-item" id="flag-box-trade_in_bonus" style="display: none;">
      <span><strong>Chương trình khuyến mãi:</strong> Ưu đãi dành cho khách hàng đủ điều kiện.</span>
    </div>
  </section>

  <!-- ===================================================================
       4. HERO BANNER: IPHONE 17 PRO MAX (TÍNH NĂNG 1, 2, 3)
       =================================================================== -->
  <main>
    <article class="hero-banner-17" id="hero-banner-section">
      <!-- Huy hiệu Tết (Tính năng 3: tet_holiday_theme) -->
      <div id="flag-box-tet_holiday_theme" style="display: none;">
        <div class="tet-badge">
          <span>🌸</span>
          <span>CHƯƠNG TRÌNH KHUYẾN MÃI MÙA LỄ 2026</span>
          <span>🧧</span>
        </div>
      </div>

      <!-- Banner iPhone 17 (Tính năng 1: banner_iphone_17) -->
      <div id="flag-box-banner_iphone_17">
        <h1 class="hero-title">Tính Năng Mới</h1>
        <h2 class="hero-subtitle">Trải nghiệm được cá nhân hóa theo cấu hình.</h2>
        <p class="hero-desc">
          Nội dung này được bật theo quy tắc rollout và nhóm người dùng được cấu hình trong hệ thống.
        </p>
      </div>

      <div class="hero-cta">
        <!-- Nút Đặt Trước Ưu Đãi (Tính năng 2: btn_preorder_17) -->
        <button class="btn-apple-preorder" id="flag-box-btn_preorder_17" onclick="alertPreorderSuccess()" style="display: none;">
          <span>Đặt Trước Ngay — Nhận Ưu Đãi 2.000.000đ</span>
        </button>

        <a href="#details" class="btn-apple-secondary">Tìm hiểu thêm &gt;</a>
      </div>

      <!-- Mockup thiết bị iPhone 17 Pro Titanium -->
      <div class="hero-image-wrap">
        <div class="iphone-17-mockup">
          <div class="dynamic-island"></div>
          <div class="screen-content">
            <h4>Phiên bản tính năng</h4>
            <p>Cấu hình tiêu chuẩn</p>
          </div>
        </div>
      </div>
    </article>

    <!-- ===================================================================
         5. BENTO GRID: CÁC DỊCH VỤ & TÍNH NĂNG APPLE (FLAGS 4, 5, 8, 9, 10)
         =================================================================== -->
    <section class="apple-grid-container" id="details">
      <h3 class="section-heading">Các Dịch Vụ Và Tính Năng</h3>
      
      <div class="apple-bento-grid">
        <!-- Tính năng 4: ai_accessories -->
        <div class="bento-card" id="flag-box-ai_accessories" style="display: none;">
          <div>
            <div class="card-tag">Trí Tuệ Nhân Tạo (AI)</div>
            <h4 class="card-title">Gợi ý phụ kiện AI MagSafe</h4>
            <p class="card-desc">Hệ sinh thái tự động đề xuất củ sạc nhanh 30W và ốp lưng Titanium khớp với màu máy của bạn.</p>
          </div>
          <div class="card-visual">🔋</div>
        </div>

        <!-- Tính năng 5: laser_engraving -->
        <div class="bento-card" id="flag-box-laser_engraving" style="display: none;">
          <div>
            <div class="card-tag">Miễn Phí Cá Nhân Hóa</div>
            <h4 class="card-title">Khắc Tên &amp; Emoji Bằng Laser</h4>
            <p class="card-desc">Tùy chỉnh nội dung hiển thị theo thông tin và quyền của người dùng.</p>
          </div>
        </div>

        <!-- Tính năng 9: gift_card_custom -->
        <div class="bento-card" id="flag-box-gift_card_custom" style="display: none;">
          <div>
            <div class="card-tag">Quà Tặng Tinh Tế</div>
            <h4 class="card-title">Mã Ưu Đãi</h4>
            <p class="card-desc">Mã ưu đãi được áp dụng theo điều kiện của chương trình.</p>
          </div>
          <div class="card-visual">🎁</div>
        </div>

        <!-- Tính năng 10: genius_bar_live -->
        <div class="bento-card" id="flag-box-genius_bar_live" style="display: none;">
          <div>
            <div class="card-tag">Hỗ Trợ Chuyên Gia</div>
            <h4 class="card-title">Genius Bar Trực Tuyến 1:1</h4>
            <p class="card-desc">Kết nối với bộ phận hỗ trợ để được giải đáp các vấn đề kỹ thuật.</p>
          </div>
          <div class="card-visual">🧑‍💻</div>
        </div>
      </div>

      <div id="dynamic-feature-flags" class="apple-bento-grid" style="margin-top: 18px;"></div>
    </section>

    <!-- ===================================================================
         6. BẢNG THANH TRA QUYẾT ĐỊNH BẬT / TẮT TỪ API (DECISION INSPECTOR)
         =================================================================== -->
    <section class="inspector-panel">
      <div class="inspector-card">
        <div class="inspector-header">
          <div class="inspector-title">
            <span>📊</span>
            <span>Bảng Quyết Định 10 Tính Năng Trả Về Từ API PHP</span>
          </div>
          <div style="font-size: 12px; color: var(--apple-text-muted);">
            User: <strong id="lbl-user-id" style="color: #fff;">U0500</strong> · 
            Env: <strong id="lbl-env-id" style="color: #fff;">van_hanh</strong> ·
            Công thức: <code>crc32(user + flag + env) % 100 &lt; tỷ_lệ</code>
          </div>
        </div>

        <div style="overflow-x: auto;">
          <table class="inspector-table">
            <thead>
              <tr>
                <th>Mã Tính Năng</th>
                <th>Tên Tính Năng</th>
                <th>Trạng Thái</th>
                <th>Hash Bucket</th>
                <th>Tỷ Lệ / Quy Tắc</th>
                <th>Lý Do Quyết Định Từ Server</th>
              </tr>
            </thead>
            <tbody id="inspector-table-body">
              <tr>
                <td colspan="6" style="text-align: center; color: var(--apple-text-muted); padding: 20px;">
                  Đang truy vấn API PHP (/api/evaluate.php)...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>

  <script src="js/apple.php"></script>
</body>
</html>
