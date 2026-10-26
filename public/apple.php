<?php header('Content-Type: text/html; charset=utf-8'); ?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Apple (Việt Nam) — Trang Chủ Thử Nghiệm Tính Năng (Feature Flags)</title>
  <meta name="description" content="Trang web mô phỏng Apple Store tích hợp hệ thống Feature Flag: Banner iPhone 17, Tỷ lệ Rollout và cơ chế Fallback">
  <link rel="stylesheet" href="css/apple.php">
</head>
<body>

  <!-- ===================================================================
       1. THANH ĐIỀU KHIỂN DÀNH CHO THẦY CÔ & NGƯỜI CHẤM (DEMO CONTROLLER)
       =================================================================== -->
  <aside class="test-controller-bar" aria-label="Bảng điều khiển thử nghiệm">
    <div class="controller-left">
      <div class="controller-brand">
        <span>Apple Store Demo (Gọi API PHP)</span>
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
      <label class="outage-toggle" title="Gạt công tắc để ngắt API và kiểm tra phản ứng của Web Apple">
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
       2. THANH MENU CHUẨN APPLE (APPLE GLOBAL NAVIGATION)
       =================================================================== -->
  <header class="apple-nav">
    <nav class="apple-nav-inner" aria-label="Menu Apple">
      <a href="#">Cửa Hàng</a>
      <a href="#">Mac</a>
      <a href="#">iPad</a>
      <a href="#">iPhone</a>
      <a href="#">Watch</a>
      <a href="#">AirPods</a>
      <a href="#">TV &amp; Nhà</a>
      <a href="#">Giải Trí</a>
      <a href="#">Phụ Kiện</a>
      <a href="#">Hỗ Trợ</a>
    </nav>
  </header>

  <!-- Báo động khi API sập (Fallback Mode) -->
  <div id="outage-alert-box" class="inspector-panel" style="display: none; margin-top: 20px; margin-bottom: 0;">
    <div class="outage-alert">
      <div>
        <strong>CẢNH BÁO: MÁY CHỦ API KHÔNG PHẢN HỒI (MÔ PHỎNG SẬP MẠNG HOẶC LỖI 503)</strong><br>
        <span style="font-size: 13px;">Web Apple tự động chuyển sang đọc <strong>Giá Trị Dự Phòng (Fallback: TẮT các tính năng mới)</strong>. Web vẫn hoạt động bình thường, không bị gián đoạn hay crash trang!</span>
      </div>
    </div>
  </div>

  <!-- ===================================================================
       3. THANH THÔNG BÁO ƯU ĐÃI (TÍNH NĂNG 6, 7: TRẢ GÓP & GIAO HÀNG HỎA TỐC)
       =================================================================== -->
  <section class="sub-promo-bar" id="apple-promo-bar">
    <div class="sub-promo-item" id="flag-box-apple_pay_0percent" style="display: none;">
      <span><strong>Trả góp 0% Apple Pay:</strong> Kỳ hạn 12 tháng liên kết các ngân hàng hàng đầu.</span>
    </div>
    <div class="sub-promo-item" id="flag-box-express_delivery_2h" style="display: none;">
      <span><strong>Giao hàng hỏa tốc 2 giờ:</strong> Nhận hàng ngay tại TP.HCM &amp; Hà Nội.</span>
    </div>
    <div class="sub-promo-item" id="flag-box-trade_in_bonus" style="display: none;">
      <span><strong>Thu cũ đổi mới:</strong> Trợ giá lên đời iPhone 17 đến 5.000.000đ.</span>
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
          <span>CHÀO XUÂN ẤT TỴ 2026 — ĐÓN TẾT CÙNG APPLE STORE</span>
          <span>🧧</span>
        </div>
      </div>

      <!-- Banner iPhone 17 (Tính năng 1: banner_iphone_17) -->
      <div id="flag-box-banner_iphone_17">
        <h1 class="hero-title">iPhone 17 Pro</h1>
        <h2 class="hero-subtitle">Đỉnh Cao Titanium. Trí Tuệ Apple Intelligence.</h2>
        <p class="hero-desc">
          Chip xử lý Apple A19 Pro 2nm siêu mạnh mẽ. Hệ thống 3 camera 48MP tiềm vọng quang học 10x. Thời lượng pin kỷ lục suốt cả ngày.
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
            <h4>iPhone 17 Pro Max</h4>
            <p>Titanium Tự Nhiên &amp; Xanh Biển Đậm</p>
          </div>
        </div>
      </div>
    </article>

    <!-- ===================================================================
         5. BENTO GRID: CÁC DỊCH VỤ & TÍNH NĂNG APPLE (FLAGS 4, 5, 8, 9, 10)
         =================================================================== -->
    <section class="apple-grid-container" id="details">
      <h3 class="section-heading">Trải Nghiệm Độc Quyền Tại Apple Store</h3>
      
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
            <p class="card-desc">Khắc chữ ký, biểu tượng may mắn hoặc tên của bạn lên hộp sạc AirPods và Apple Pencil Pro.</p>
          </div>
        </div>

        <!-- Tính năng 9: gift_card_custom -->
        <div class="bento-card" id="flag-box-gift_card_custom" style="display: none;">
          <div>
            <div class="card-tag">Quà Tặng Tinh Tế</div>
            <h4 class="card-title">Apple Gift Card</h4>
            <p class="card-desc">Thẻ quà tặng mua sắm mọi sản phẩm và dịch vụ iCloud, Apple Music phiên bản giới hạn.</p>
          </div>
          <div class="card-visual">🎁</div>
        </div>

        <!-- Tính năng 10: genius_bar_live -->
        <div class="bento-card" id="flag-box-genius_bar_live" style="display: none;">
          <div>
            <div class="card-tag">Hỗ Trợ Chuyên Gia</div>
            <h4 class="card-title">Genius Bar Trực Tuyến 1:1</h4>
            <p class="card-desc">Kết nối video trực tiếp cùng chuyên viên Apple để được giải đáp thắc mắc kỹ thuật ngay tại nhà.</p>
          </div>
          <div class="card-visual">🧑‍💻</div>
        </div>
      </div>
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
