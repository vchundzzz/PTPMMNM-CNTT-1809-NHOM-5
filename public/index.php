<?php header('Content-Type: text/html; charset=utf-8'); ?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hệ Thống Quản Trị Tính Năng Web Apple & Quản Lý Sản Phẩm</title>
  <meta name="description" content="Hệ thống Web Admin quản lý sản phẩm iPhone, Mac, iPad và cấu hình bật/tắt tính năng theo đề tài 1">
  <link rel="stylesheet" href="css/style.php">
  <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
</head>
<body>

  <!-- ===================================================================
       1. THANH TIÊU ĐỀ TRÊN CÙNG (MAIN HEADER / TOPBAR #3c8dbc)
       =================================================================== -->
  <header class="main-header">
    <!-- Khối Logo xanh đậm #367fa9 rộng 230px -->
    <a href="javascript:void(0)" class="logo-box" onclick="switchAdminTab('tab-products')">
      <span>HỆ THỐNG QUẢN TRỊ</span>
    </a>

    <!-- Container thanh điều hướng trên -->
    <div class="navbar-container">
      <div class="navbar-left">
        <!-- Nút bật/tắt thu gọn sidebar -->
        <button class="sidebar-toggle-btn" id="sidebar-toggle" onclick="toggleSidebar()" title="Thu gọn / Mở rộng menu bên trái">
          <iconify-icon icon="ant-design:unordered-list-outlined"></iconify-icon>
        </button>

        <!-- Nút Xem website mở ứng dụng Apple Demo -->
        <a href="apple.php" target="_blank" class="view-website-link" title="Mở trang chủ Web Apple Store gọi API bật/tắt">
          <span>Xem Web Apple Demo</span>
        </a>
      </div>

      <div class="navbar-right">
        <!-- 1. Chọn môi trường -->
        <div class="header-select-group" title="Chuyển đổi môi trường cấu hình">
          <span class="header-select-label">Môi trường:</span>
          <select id="select-env" class="header-select" onchange="onEnvironmentChange()">
            <option value="van_hanh" selected>Vận Hành</option>
            <option value="thu_nghiem">Thử Nghiệm</option>
          </select>
        </div>

        <!-- 2. Chọn vai trò tài khoản (RBAC) -->
        <div class="header-select-group" title="Chuyển đổi tài khoản để kiểm tra phân quyền Xem và Sửa">
          <span class="header-select-label">Tài khoản:</span>
          <select id="select-role" class="header-select" onchange="onRoleChange()">
            <option value="sua" selected>Admin</option>
            <option value="xem">Người dùng</option>
          </select>
        </div>

        <!-- Thông tin User Admin -->
        <div class="user-badge-header">
          <div class="user-avatar-small">A</div>
          <span id="header-user-name">Quản trị viên</span>
        </div>
      </div>
    </div>
  </header>

  <!-- ===================================================================
       2. THANH MENU BÊN TRÁI (MAIN SIDEBAR #222d32) - ĐÃ CẮT BỎ MỤC THỪA
       =================================================================== -->
  <aside class="main-sidebar" id="main-sidebar">
    <!-- User Panel -->
    <div class="user-panel">
      <div class="user-panel-avatar">
      </div>
      <div class="user-panel-info">
        <div class="user-panel-name" id="sidebar-user-name">Quản trị viên</div>
        <div class="user-panel-status">
          <span class="status-dot-green"></span>
          <span>Online</span>
        </div>
      </div>
    </div>

    <!-- MAIN NAVIGATION -->
    <div class="sidebar-header-title">DANH MỤC QUẢN TRỊ</div>

    <ul class="sidebar-menu">
      <!-- MỤC 1: QUẢN TRỊ SẢN PHẨM APPLE (TRỌNG TÂM GIAO DIỆN) -->
      <li class="active treeview" id="nav-group-danhmuc">
        <a href="javascript:void(0)" onclick="toggleTreeview('nav-group-danhmuc')">
          <span class="menu-text">QUẢN LÝ SẢN PHẨM</span>
        </a>
        <ul class="treeview-menu" style="display: block;">
          <li class="active" id="menu-item-products">
            <a href="javascript:void(0)" onclick="switchAdminTab('tab-products')">
              <span class="sub-circle"></span> <strong>Sản phẩm (iPhone, Mac, iPad)</strong>
            </a>
          </li>
        </ul>
      </li>

      <!-- MỤC 2: QUẢN TRỊ CẤU HÌNH TÍNH NĂNG (ĐỀ TÀI 1) -->
      <li class="treeview" id="nav-group-flags">
        <a href="javascript:void(0)" onclick="toggleTreeview('nav-group-flags')">
          <span class="menu-text">CẤU HÌNH TÍNH NĂNG (ĐỀ TÀI 1)</span>
        </a>
        <ul class="treeview-menu" style="display: block;">
          <li id="menu-item-flags">
            <a href="javascript:void(0)" onclick="switchAdminTab('tab-flags')">
              <span class="sub-circle"></span> 1. Khai báo cờ &amp; Tỷ lệ %
            </a>
          </li>
          <li id="menu-item-versions">
            <a href="javascript:void(0)" onclick="switchAdminTab('tab-versions')">
              <span class="sub-circle"></span> 2. Phiên bản, Diff &amp; Rollback
            </a>
          </li>
          <li id="menu-item-history">
            <a href="javascript:void(0)" onclick="switchAdminTab('tab-history')">
              <span class="sub-circle"></span> 3. Lịch sử Audit &amp; Nhập/Xuất JSON
            </a>
          </li>
          <li id="menu-item-tests">
            <a href="javascript:void(0)" onclick="switchAdminTab('tab-tests')">
              <span class="sub-circle"></span> 4. 5 Phép thử &amp; 2 Kịch bản
            </a>
          </li>
          <li id="menu-item-users">
            <a href="javascript:void(0)" onclick="switchAdminTab('tab-users')">
              <span class="sub-circle"></span> 5. 1.000 User &amp; Băm CRC32
            </a>
          </li>
        </ul>
      </li>

      <!-- MỤC 3: ỨNG DỤNG CLIENT DEMO -->
      <li>
        <a href="apple.php" target="_blank" title="Mở trang chủ Apple Store gọi API đánh giá cờ">
          <span class="menu-text">XEM WEB APPLE DEMO</span>
        </a>
      </li>
    </ul>
  </aside>

  <!-- ===================================================================
       3. NỘI DUNG CHÍNH (CONTENT WRAPPER)
       =================================================================== -->
  <main class="content-wrapper">

    <!-- ================================================================
         TAB 0: BẢNG QUẢN LÝ SẢN PHẨM (IPHONE, MAC, IPAD)
         ================================================================ -->
    <div id="tab-products" class="tab-pane-view active">
      <!-- Breadcrumb Header dễ hiểu và dễ đọc -->
      <div class="content-header">
        <h1 class="content-title">Quản Lý Sản Phẩm Apple (iPhone, Mac, iPad)</h1>
        <ul class="content-breadcrumb">
          <li><a href="javascript:void(0)">Trang chủ</a></li>
          <li><span class="divider">&gt;</span></li>
          <li style="color: #666; font-weight: 600;">Danh mục sản phẩm Apple</li>
        </ul>
      </div>

      <!-- Hộp Admin Box chứa bảng chuẩn -->
      <div class="admin-box">
        <!-- Thanh công cụ Toolbar -->
        <div class="admin-toolbar">
          <div class="toolbar-left">
            <!-- Dropdown Tác vụ hàng loạt -->
            <select id="bulk-action-select" class="form-select-sm">
              <option value="">Tác vụ chọn nhiều...</option>
              <option value="delete">Xóa đã chọn</option>
              <option value="toggle_home_on">Bật hiển thị Trang chủ</option>
              <option value="toggle_home_off">Tắt hiển thị Trang chủ</option>
              <option value="toggle_menu_on">Bật hiển thị Menu</option>
              <option value="toggle_menu_off">Tắt hiển thị Menu</option>
              <option value="toggle_visible_on">Bật Hiển thị</option>
              <option value="toggle_visible_off">Tắt Hiển thị</option>
            </select>
            <button class="btn-admin btn-admin-default" onclick="applyBulkAction()" title="Áp dụng tác vụ cho các sản phẩm đã chọn">Áp dụng</button>

            <!-- Ô nhập tìm kiếm -->
            <input type="text" id="product-search-input" class="form-input-sm" placeholder="Tìm kiếm tên sản phẩm, mã SKU..." oninput="onProductSearchChange(this.value)">

            <!-- Dropdown Lọc theo dòng máy -->
            <select id="product-module-filter" class="form-select-sm" onchange="loadProducts()">
              <option value="all">Tất cả dòng máy (iPhone, Mac, iPad)</option>
              <option value="iPhone">Dòng máy iPhone</option>
              <option value="Mac">Dòng máy Mac (MacBook, iMac, Mac mini)</option>
              <option value="iPad">Dòng máy iPad (Pro, Air, mini, Gen 10)</option>
            </select>

            <!-- Nút Làm mới -->
            <button class="btn-admin btn-admin-default" onclick="resetProductFilters()" title="Làm mới bộ lọc danh sách">
              Làm mới
            </button>
          </div>

          <div class="toolbar-right">
            <!-- Nút Thêm mới sản phẩm -->
            <button class="btn-admin btn-admin-primary" id="btn-add-product" onclick="openAddProductModal()">
              <span>Thêm Sản Phẩm Mới</span>
            </button>
          </div>
        </div>

        <!-- Bảng danh sách sản phẩm -->
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width: 40px;">
                  <span class="custom-checkbox-wrap">
                    <input type="checkbox" id="check-all-products" onchange="toggleSelectAllProducts(this.checked)" title="Chọn tất cả">
                  </span>
                </th>
                <th style="width: 55px;">STT</th>
                <th class="text-left" style="min-width: 260px;">TÊN SẢN PHẨM APPLE</th>
                <th style="width: 80px;">HÌNH ẢNH</th>
                <th style="width: 110px;">DÒNG MÁY</th>
                <th style="width: 90px;">TRANG CHỦ</th>
                <th style="width: 80px;">MENU</th>
                <th style="width: 90px;">HIỂN THỊ</th>
                <th style="width: 125px;">THAO TÁC</th>
              </tr>
            </thead>
            <tbody id="products-table-body">
              <tr>
                <td colspan="9" style="text-align: center; padding: 30px; color: #888;">
                  Đang tải danh mục sản phẩm iPhone, Mac, iPad...
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Footer bảng thông tin & phân trang -->
        <div class="admin-table-footer">
          <div id="products-count-info">Hiển thị 13 sản phẩm</div>
          <div style="display: flex; gap: 6px;">
            <button class="btn-admin btn-admin-default btn-sm" onclick="showToast('Trang đầu tiên', 'info')">Trang đầu</button>
            <button class="btn-admin btn-admin-primary btn-sm">1</button>
            <button class="btn-admin btn-admin-default btn-sm" onclick="showToast('Chỉ có 1 trang', 'info')">Trang sau</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ================================================================
         TAB 1: KHAI BÁO CỜ & QUY TẮC TỶ LỆ % (CHỨC NĂNG 2 CỦA ĐỀ TÀI)
         ================================================================ -->
    <div id="tab-flags" class="tab-pane-view" style="display: none;">
      <div class="content-header">
        <h1 class="content-title">Quản Lý Cấu Hình Bật / Tắt Tính Năng (Feature Flags)</h1>
        <ul class="content-breadcrumb">
          <li><a href="javascript:void(0)">Đề tài 1</a></li>
          <li><span class="divider">&gt;</span></li>
          <li style="color: #666; font-weight: 600;">Khai báo tính năng &amp; Quy tắc tỷ lệ %</li>
        </ul>
      </div>

      <!-- 4 Thẻ thống kê -->
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 15px; margin-bottom: 20px;">
        <div style="background: #fff; padding: 16px; border-radius: 3px; border-left: 4px solid var(--admin-blue); box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center;">
          <div>
            <div style="font-size: 24px; font-weight: 700; color: #333;" id="stat-total-flags">10</div>
            <div style="font-size: 12px; color: #777; font-weight: 600;">Tính năng Apple khai báo</div>
          </div>
        </div>

        <div style="background: #fff; padding: 16px; border-radius: 3px; border-left: 4px solid var(--admin-green); box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center;">
          <div>
            <div style="font-size: 24px; font-weight: 700; color: var(--admin-green);" id="stat-active-flags">10</div>
            <div style="font-size: 12px; color: #777; font-weight: 600;">Đang bật công tắc</div>
          </div>
        </div>

        <div style="background: #fff; padding: 16px; border-radius: 3px; border-left: 4px solid var(--admin-orange); box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center;">
          <div>
            <div style="font-size: 24px; font-weight: 700; color: var(--admin-orange);" id="stat-active-version">v1</div>
            <div style="font-size: 12px; color: #777; font-weight: 600;">Phiên bản đang phát hành</div>
          </div>
        </div>

        <div style="background: #fff; padding: 16px; border-radius: 3px; border-left: 4px solid var(--admin-purple); box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center;">
          <div>
            <div style="font-size: 24px; font-weight: 700; color: var(--admin-purple);">1.000</div>
            <div style="font-size: 12px; color: #777; font-weight: 600;">Mã người dùng (U0001 - U1000)</div>
          </div>
        </div>
      </div>

      <!-- Hộp giải thích quy tắc ưu tiên cố định (Yêu cầu 1 của đề tài) -->
      <div style="background: #e8f4f8; border: 1px solid #bce8f1; color: #31708f; padding: 14px 18px; border-radius: 3px; margin-bottom: 20px; font-size: 13px; line-height: 1.6;">
        <strong style="color: #245269;">QUY ĐỊNH THỨ TỰ ƯU TIÊN KHI NHIỀU ĐIỀU KIỆN CÙNG ĐÚNG (YÊU CẦU 1):</strong><br>
        1. <strong>Danh sách người dùng ưu tiên (Whitelist):</strong> Xét trước nhất. Nếu mã người dùng nằm trong Whitelist &rarr; <span style="color:#00a65a; font-weight:700;">LUÔN BẬT</span> (ghi đè cả tỷ lệ 0%).<br>
        2. <strong>Công tắc chính (Master Switch):</strong> Nếu công tắc tắt &rarr; <span style="color:#dd4b39; font-weight:700;">LUÔN TẮT</span> toàn bộ người dùng thông thường.<br>
        3. <strong>Quy tắc tỷ lệ % (Rollout %):</strong> Dùng thuật toán băm tất định <code>crc32(user + flag_key + env) % 100 &lt; percentage</code>. Cùng người dùng, tính năng, môi trường và phiên bản luôn cho kết quả ổn định 100%. Xử lý đúng 0% (luôn tắt) và 100% (luôn bật).
      </div>

      <!-- Admin Box danh sách cờ -->
      <div class="admin-box">
        <div class="admin-toolbar">
          <div class="toolbar-left">
            <strong style="font-size: 15px; color: #333;">Danh Sách 10 Cờ Tính Năng Web Apple (Bản Nháp Làm Việc)</strong>
          </div>
          <div class="toolbar-right">
            <button class="btn-admin btn-admin-primary" id="btn-add-flag" onclick="openAddFlagModal()">
              Thêm Tính Năng Mới
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width: 50px;">STT</th>
                <th class="text-left" style="width: 180px;">MÃ TÍNH NĂNG</th>
                <th class="text-left">TÊN TÍNH NĂNG &amp; MÔ TẢ</th>
                <th style="width: 90px;">CÔNG TẮC</th>
                <th style="width: 220px;">TỶ LỆ (% ROLLOUT)</th>
                <th class="text-left" style="width: 220px;">DANH SÁCH ƯU TIÊN (WHITELIST)</th>
                <th style="width: 80px;">XÓA</th>
              </tr>
            </thead>
            <tbody id="flags-table-body">
              <tr>
                <td colspan="7" style="text-align: center; padding: 25px; color: #888;">
                  Đang nạp cấu hình cờ tính năng...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ================================================================
         TAB 2: PHIÊN BẢN, SO SÁNH DIFF & KHÔI PHỤC (CHỨC NĂNG 4)
         ================================================================ -->
    <div id="tab-versions" class="tab-pane-view" style="display: none;">
      <div class="content-header">
        <h1 class="content-title">Quản Lý Phiên Bản Cấu Hình &amp; Khôi Phục (Rollback)</h1>
        <ul class="content-breadcrumb">
          <li><a href="javascript:void(0)">Đề tài 1</a></li>
          <li><span class="divider">&gt;</span></li>
          <li style="color: #666; font-weight: 600;">Lưu, so sánh, phát hành &amp; khôi phục</li>
        </ul>
      </div>

      <div style="background: #fcf8e3; border: 1px solid #faebcc; color: #8a6d3b; padding: 14px 18px; border-radius: 3px; margin-bottom: 20px; font-size: 13px; line-height: 1.5;">
        <strong>🔒 QUY TẮC BẤT BIẾN CỦA PHIÊN BẢN ĐÃ PHÁT HÀNH:</strong><br>
        Bản cấu hình sau khi phát hành được đánh dấu đóng băng (bất biến), khóa không cho sửa trực tiếp để đảm bảo an toàn vận hành. Muốn sửa, hãy tạo phiên bản mới hoặc dùng nút <strong>"Khôi phục"</strong> để đưa hệ thống về bản cấu hình an toàn trước đó.
      </div>

      <div class="admin-box">
        <div class="admin-toolbar">
          <div class="toolbar-left">
            <strong style="font-size: 15px; color: #333;">Danh Sách Các Bản Cấu Hình Đã Lưu (Snapshots)</strong>
          </div>
          <div class="toolbar-right">
            <button class="btn-admin btn-admin-success" id="btn-publish-new" onclick="openPublishModal()">
              Phát Hành Phiên Bản Mới
            </button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width: 80px;">BẢN</th>
                <th class="text-left">TIÊU ĐỀ PHÁT HÀNH</th>
                <th style="width: 120px;">TRẠNG THÁI</th>
                <th class="text-left">LÝ DO THAY ĐỔI</th>
                <th style="width: 130px;">NGƯỜI TẠO</th>
                <th style="width: 150px;">THỜI GIAN</th>
                <th style="width: 80px;">SỐ CỜ</th>
                <th style="width: 200px;">THAO TÁC</th>
              </tr>
            </thead>
            <tbody id="versions-table-body">
              <tr>
                <td colspan="8" style="text-align: center; padding: 25px; color: #888;">
                  Đang tải danh sách phiên bản...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ================================================================
         TAB 3: LỊCH SỬ AUDIT TRAIL & NHẬP / XUẤT JSON (CHỨC NĂNG 5)
         ================================================================ -->
    <div id="tab-history" class="tab-pane-view" style="display: none;">
      <div class="content-header">
        <h1 class="content-title">Nhật Ký Thay Đổi (Audit Trail) &amp; Nhập/Xuất Cấu Hình JSON</h1>
        <ul class="content-breadcrumb">
          <li><a href="javascript:void(0)">Đề tài 1</a></li>
          <li><span class="divider">&gt;</span></li>
          <li style="color: #666; font-weight: 600;">Lịch sử thay đổi &amp; Kiểm tra dữ liệu nhập</li>
        </ul>
      </div>

      <div class="admin-box" style="margin-bottom: 20px;">
        <div class="admin-toolbar">
          <div class="toolbar-left">
            <strong style="font-size: 15px; color: #333;">Nhập &amp; Xuất Cấu Hình JSON (Kèm Kiểm Tra Hợp Lệ Dữ Liệu)</strong>
          </div>
          <div class="toolbar-right">
            <button class="btn-admin btn-admin-default" onclick="exportConfigJson()">
              Xuất File JSON
            </button>
            <button class="btn-admin btn-admin-primary" id="btn-open-import" onclick="openImportModal()">
              <span>📤</span> Nhập Từ File JSON
            </button>
          </div>
        </div>
      </div>

      <div class="admin-box">
        <div class="admin-toolbar">
          <div class="toolbar-left">
            <strong style="font-size: 15px; color: #333;">Nhật Ký Lịch Sử Thay Đổi Toàn Hệ Thống (Audit Trail)</strong>
          </div>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width: 60px;">ID</th>
                <th style="width: 150px;">THỜI GIAN</th>
                <th style="width: 140px;">HÀNH ĐỘNG</th>
                <th style="width: 90px;">PHIÊN BẢN</th>
                <th style="width: 150px;">NGƯỜI THỰC HIỆN</th>
                <th class="text-left">LÝ DO BẬT / TẮT / THAY ĐỔI</th>
              </tr>
            </thead>
            <tbody id="history-table-body">
              <tr>
                <td colspan="6" style="text-align: center; padding: 25px; color: #888;">
                  Đang nạp nhật ký lịch sử...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ================================================================
         TAB 4: 5 PHÉP THỬ TỰ ĐỘNG & 2 TÌNH HUỐNG TRÌNH DIỄN
         ================================================================ -->
    <div id="tab-tests" class="tab-pane-view" style="display: none;">
      <div class="content-header">
        <h1 class="content-title">Kiểm Thử Tự Động 5 Phép Thử Bắt Buộc &amp; 2 Tình Huống</h1>
        <ul class="content-breadcrumb">
          <li><a href="javascript:void(0)">Đề tài 1</a></li>
          <li><span class="divider">&gt;</span></li>
          <li style="color: #666; font-weight: 600;">5 Phép thử &amp; 2 Kịch bản demo</li>
        </ul>
      </div>

      <!-- 5 Phép thử bắt buộc của đề tài -->
      <div class="admin-box" style="margin-bottom: 25px;">
        <div class="admin-toolbar">
          <div class="toolbar-left">
            <strong style="font-size: 15px; color: #333;">Bộ 5 Phép Thử Bắt Buộc (Đối chiếu Mục 6 &amp; Rubric Chấm Điểm)</strong>
          </div>
          <div class="toolbar-right">
            <button class="btn-admin btn-admin-primary" onclick="runAutomatedTests()">
              <span>▶</span> Chạy Toàn Bộ 5 Phép Thử
            </button>
          </div>
        </div>

        <div style="padding: 15px;" id="test-results-wrapper">
          <div style="text-align: center; padding: 25px; color: #777;">
            Bấm <strong>"▶ Chạy Toàn Bộ 5 Phép Thử"</strong> để bắt đầu kiểm tra tự động toàn diện.
          </div>
        </div>
      </div>

      <!-- 2 Tình huống trình diễn khi chấm vấn đáp -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <!-- Tình huống 1 -->
        <div class="admin-box">
          <div class="admin-toolbar">
            <div class="toolbar-left">
              <strong style="font-size: 14px; color: #333;">🎬 Tình Huống 1: Tạo cấu hình &rarr; Kiểm tra &rarr; Phát hành &rarr; Gọi API</strong>
            </div>
            <div class="toolbar-right">
              <button class="btn-admin btn-admin-success btn-sm" id="btn-run-s1" onclick="runScenario1()">
                <span>▶</span> Chạy Tình Huống 1
              </button>
            </div>
          </div>
          <div style="padding: 14px; font-size: 13px; min-height: 140px; background: #fff;" id="scenario1-log">
            <p style="color: #777;">Bấm nút để chạy tự động quy trình Tình huống 1: Tạo cờ Banner Tết &rarr; Thử nghiệm nháp &rarr; Phát hành v2 &rarr; Web Apple hiển thị giao diện Tết.</p>
          </div>
        </div>

        <!-- Tình huống 2 -->
        <div class="admin-box">
          <div class="admin-toolbar">
            <div class="toolbar-left">
              <strong style="font-size: 14px; color: #333;">🎬 Tình Huống 2: Đổi tỷ lệ 90% &rarr; So sánh bản &rarr; Khôi phục bản cũ</strong>
            </div>
            <div class="toolbar-right">
              <button class="btn-admin btn-admin-warning btn-sm" id="btn-run-s2" onclick="runScenario2()">
                <span>▶</span> Chạy Tình Huống 2
              </button>
            </div>
          </div>
          <div style="padding: 14px; font-size: 13px; min-height: 140px; background: #fff;" id="scenario2-log">
            <p style="color: #777;">Bấm nút để mô phỏng sự cố: Đẩy tỷ lệ lên 90% phát sinh lỗi &rarr; So sánh khác biệt &rarr; Khôi phục về v1 đưa hệ thống về an toàn.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ================================================================
         TAB 5: DANH SÁCH 1.000 NGƯỜI DÙNG MẪU (CHỨC NĂNG 1 & DỮ LIỆU)
         ================================================================ -->
    <div id="tab-users" class="tab-pane-view" style="display: none;">
      <div class="content-header">
        <h1 class="content-title">Quản Lý 1.000 Mã Người Dùng &amp; Băm Tất Định (CRC32)</h1>
        <ul class="content-breadcrumb">
          <li><a href="javascript:void(0)">Đề tài 1</a></li>
          <li><span class="divider">&gt;</span></li>
          <li style="color: #666; font-weight: 600;">Dữ liệu 1.000 User (U0001 - U1000)</li>
        </ul>
      </div>

      <div class="admin-box">
        <div class="admin-toolbar">
          <div class="toolbar-left">
            <input type="text" id="user-search-input" class="form-input-sm" placeholder="Tìm theo mã user (VD: U0001)..." oninput="onUserSearchInput(this.value)">
            <select id="user-group-filter" class="form-select-sm" onchange="loadUsers()">
              <option value="">Tất cả nhóm</option>
              <option value="VIP">Nhóm VIP</option>
              <option value="Beta Tester">Nhóm Beta Tester</option>
              <option value="Khách thường">Nhóm Khách thường</option>
            </select>
          </div>
          <div class="toolbar-right">
            <span id="users-page-info" style="font-size: 13px; color: #666;">Hiển thị trang 1</span>
            <button class="btn-admin btn-admin-default btn-sm" onclick="changeUserPage(-1)">← Trước</button>
            <button class="btn-admin btn-admin-default btn-sm" onclick="changeUserPage(1)">Sau →</button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th style="width: 100px;">MÃ USER ID</th>
                <th class="text-left">TÊN NGƯỜI DÙNG</th>
                <th style="width: 130px;">PHÂN NHÓM</th>
                <th style="width: 180px;">MÃ BĂM CRC32 (BUCKET 0-99)</th>
                <th style="width: 130px;">TRẠNG THÁI</th>
                <th style="width: 130px;">THỬ NGHIỆM</th>
              </tr>
            </thead>
            <tbody id="users-table-body">
              <tr>
                <td colspan="6" style="text-align: center; padding: 25px; color: #888;">
                  Đang tải danh sách 1.000 người dùng...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </main>

  <!-- ===================================================================
       4. CÁC MODAL HỘP THOẠI QUẢN TRỊ
       =================================================================== -->

  <!-- MODAL THÊM MỚI SẢN PHẨM -->
  <div class="admin-modal-backdrop" id="modal-add-product">
    <div class="admin-modal-dialog">
      <div class="admin-modal-header">
        <div class="admin-modal-title">Thêm Sản Phẩm Mới (iPhone, Mac, iPad)</div>
        <button class="admin-modal-close" onclick="closeAdminModal('modal-add-product')">&times;</button>
      </div>
      <div class="admin-modal-body">
        <div class="form-grid-2">
          <div class="form-group">
            <label for="prod-name">Tên sản phẩm <b>*</b>:</label>
            <input type="text" id="prod-name" class="form-control" placeholder="Ví dụ: iPhone 16 Pro Max 256GB">
          </div>
          <div class="form-group">
            <label for="prod-sku">Mã sản phẩm (SKU):</label>
            <input type="text" id="prod-sku" class="form-control" placeholder="Để trống tự sinh (VD: APL-IPH-999)">
          </div>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label for="prod-module">Phân loại Dòng máy <b>*</b>:</label>
            <select id="prod-module" class="form-control">
              <option value="iPhone" selected>iPhone</option>
              <option value="Mac">Mac</option>
              <option value="iPad">iPad</option>
            </select>
          </div>
          <div class="form-group">
            <label for="prod-price">Giá bán (VNĐ):</label>
            <input type="number" id="prod-price" class="form-control" value="29990000" step="10000">
          </div>
        </div>

        <div class="form-group">
          <label for="prod-image">Đường dẫn hình ảnh (URL):</label>
          <input type="text" id="prod-image" class="form-control" value="https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=150&auto=format&fit=crop&q=80">
        </div>

        <div class="form-group">
          <label for="prod-flag">Gắn với Cờ Tính Năng (Feature Flag):</label>
          <select id="prod-flag" class="form-control">
            <option value="banner_iphone_17">banner_iphone_17 (Banner Quảng Bá iPhone)</option>
            <option value="dark_mode">dark_mode (Giao Diện Tối Tự Động)</option>
            <option value="apple_intelligence">apple_intelligence (Tính Năng AI Thông Minh)</option>
            <option value="tra_gop_0_dong">tra_gop_0_dong (Ưu Đãi Trả Góp 0%)</option>
            <option value="thu_cu_doi_moi">thu_cu_doi_moi (Chương Trình Thu Cũ Đổi Mới)</option>
          </select>
        </div>

        <div class="form-group">
          <label>Các tùy chọn hiển thị trên Web Apple:</label>
          <div class="form-checkbox-inline-group">
            <label class="form-checkbox-inline">
              <input type="checkbox" id="prod-is-home" checked> Hiện ở Trang chủ
            </label>
            <label class="form-checkbox-inline">
              <input type="checkbox" id="prod-is-menu" checked> Hiện ở Menu
            </label>
            <label class="form-checkbox-inline">
              <input type="checkbox" id="prod-is-visible" checked> Cho phép Hiển thị
            </label>
          </div>
        </div>

        <div class="form-group">
          <label for="prod-desc">Mô tả ngắn gọn:</label>
          <textarea id="prod-desc" class="form-control" rows="2" placeholder="Sản phẩm Apple chính hãng VN/A, bảo hành 12 tháng"></textarea>
        </div>
      </div>
      <div class="admin-modal-footer">
        <button class="btn-admin btn-admin-default" onclick="closeAdminModal('modal-add-product')">Hủy bỏ</button>
        <button class="btn-admin btn-admin-primary" onclick="submitCreateProduct()">Lưu Sản Phẩm</button>
      </div>
    </div>
  </div>

  <!-- MODAL SỬA SẢN PHẨM -->
  <div class="admin-modal-backdrop" id="modal-edit-product">
    <div class="admin-modal-dialog">
      <div class="admin-modal-header">
        <div class="admin-modal-title">Chỉnh Sửa Sản Phẩm</div>
        <button class="admin-modal-close" onclick="closeAdminModal('modal-edit-product')">&times;</button>
      </div>
      <div class="admin-modal-body">
        <input type="hidden" id="edit-prod-id">
        <div class="form-grid-2">
          <div class="form-group">
            <label for="edit-prod-name">Tên sản phẩm <b>*</b>:</label>
            <input type="text" id="edit-prod-name" class="form-control">
          </div>
          <div class="form-group">
            <label for="edit-prod-sku">Mã sản phẩm (SKU):</label>
            <input type="text" id="edit-prod-sku" class="form-control">
          </div>
        </div>

        <div class="form-grid-2">
          <div class="form-group">
            <label for="edit-prod-module">Phân loại Dòng máy <b>*</b>:</label>
            <select id="edit-prod-module" class="form-control">
              <option value="iPhone">iPhone</option>
              <option value="Mac">Mac</option>
              <option value="iPad">iPad</option>
            </select>
          </div>
          <div class="form-group">
            <label for="edit-prod-price">Giá bán (VNĐ):</label>
            <input type="number" id="edit-prod-price" class="form-control" step="10000">
          </div>
        </div>

        <div class="form-group">
          <label for="edit-prod-image">Đường dẫn hình ảnh (URL):</label>
          <input type="text" id="edit-prod-image" class="form-control">
        </div>

        <div class="form-group">
          <label>Các tùy chọn hiển thị:</label>
          <div class="form-checkbox-inline-group">
            <label class="form-checkbox-inline">
              <input type="checkbox" id="edit-prod-is-home"> Hiện ở Trang chủ
            </label>
            <label class="form-checkbox-inline">
              <input type="checkbox" id="edit-prod-is-menu"> Hiện ở Menu
            </label>
            <label class="form-checkbox-inline">
              <input type="checkbox" id="edit-prod-is-visible"> Cho phép Hiển thị
            </label>
          </div>
        </div>

        <div class="form-group">
          <label for="edit-prod-desc">Mô tả sản phẩm:</label>
          <textarea id="edit-prod-desc" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="admin-modal-footer">
        <button class="btn-admin btn-admin-default" onclick="closeAdminModal('modal-edit-product')">Hủy bỏ</button>
        <button class="btn-admin btn-admin-warning" onclick="submitEditProduct()">Cập Nhật Thay Đổi</button>
      </div>
    </div>
  </div>

  <!-- MODAL XEM CHI TIẾT SẢN PHẨM -->
  <div class="admin-modal-backdrop" id="modal-view-product">
    <div class="admin-modal-dialog">
      <div class="admin-modal-header" style="background-color: var(--admin-cyan);">
        <div class="admin-modal-title">Chi Tiết Sản Phẩm Apple</div>
        <button class="admin-modal-close" onclick="closeAdminModal('modal-view-product')">&times;</button>
      </div>
      <div class="admin-modal-body" id="view-product-body">
        <!-- Render bằng JS -->
      </div>
      <div class="admin-modal-footer">
        <button class="btn-admin btn-admin-default" onclick="closeAdminModal('modal-view-product')">Đóng</button>
      </div>
    </div>
  </div>

  <!-- MODAL THÊM CỜ TÍNH NĂNG MỚI -->
  <div class="admin-modal-backdrop" id="modal-add-flag">
    <div class="admin-modal-dialog">
      <div class="admin-modal-header">
        <div class="admin-modal-title">Thêm Cờ Tính Năng Mới</div>
        <button class="admin-modal-close" onclick="closeAdminModal('modal-add-flag')">&times;</button>
      </div>
      <div class="admin-modal-body">
        <div class="form-group">
          <label for="flag-name">Tên tính năng <b>*</b>:</label>
          <input type="text" id="flag-name" class="form-control" placeholder="Ví dụ: Banner iPhone 17 Pro Max">
          <small style="color: #888;">Không được để trống theo quy định kiểm tra hợp lệ dữ liệu.</small>
        </div>
        <div class="form-group">
          <label for="flag-key">Mã tính năng (Key):</label>
          <input type="text" id="flag-key" class="form-control" placeholder="Tự sinh nếu để trống (VD: banner_iphone_17)">
        </div>
        <div class="form-group">
          <label for="flag-desc">Mô tả tính năng:</label>
          <input type="text" id="flag-desc" class="form-control" placeholder="Chi tiết hiển thị trên Web Apple">
        </div>
        <div class="form-group">
          <label for="flag-pct">Tỷ lệ áp dụng (% Rollout từ 0 đến 100):</label>
          <input type="number" id="flag-pct" class="form-control" min="0" max="100" value="50">
        </div>
        <div class="form-group">
          <label for="flag-whitelist">Danh sách người dùng ưu tiên (Whitelist - Phân cách bằng dấu phẩy):</label>
          <input type="text" id="flag-whitelist" class="form-control" placeholder="Ví dụ: U0001,U0002,U0010">
        </div>
      </div>
      <div class="admin-modal-footer">
        <button class="btn-admin btn-admin-default" onclick="closeAdminModal('modal-add-flag')">Hủy bỏ</button>
        <button class="btn-admin btn-admin-primary" onclick="submitCreateFlag()">Lưu Tính Năng</button>
      </div>
    </div>
  </div>

  <!-- MODAL PHÁT HÀNH PHIÊN BẢN MỚI -->
  <div class="admin-modal-backdrop" id="modal-publish">
    <div class="admin-modal-dialog">
      <div class="admin-modal-header" style="background-color: var(--admin-green);">
        <div class="admin-modal-title">Phát Hành Phiên Bản Cấu Hình Mới</div>
        <button class="admin-modal-close" onclick="closeAdminModal('modal-publish')">&times;</button>
      </div>
      <div class="admin-modal-body">
        <p style="margin-bottom: 14px; font-size: 13px; color: #555;">
          Toàn bộ cấu hình 10 tính năng hiện tại sẽ được lưu thành bản chụp snapshot bất biến. Bản này sẽ được API dùng và khóa không cho sửa trực tiếp.
        </p>
        <div class="form-group">
          <label for="publish-title">Tiêu đề phát hành:</label>
          <input type="text" id="publish-title" class="form-control" placeholder="Ví dụ: Phát hành tính năng đợt mở bán iPhone 16">
        </div>
        <div class="form-group">
          <label for="publish-reason">Lý do phát hành <b>*</b> (Bắt buộc theo Chức năng 5):</label>
          <textarea id="publish-reason" class="form-control" rows="3" placeholder="Ví dụ: Tăng tỷ lệ banner lên 60% phục vụ chiến dịch mở bán toàn quốc"></textarea>
        </div>
      </div>
      <div class="admin-modal-footer">
        <button class="btn-admin btn-admin-default" onclick="closeAdminModal('modal-publish')">Hủy bỏ</button>
        <button class="btn-admin btn-admin-success" onclick="submitPublishVersion()">Xác Nhận Phát Hành</button>
      </div>
    </div>
  </div>

  <!-- MODAL SO SÁNH KHÁC BIỆT (VISUAL DIFF) -->
  <div class="admin-modal-backdrop" id="modal-diff">
    <div class="admin-modal-dialog modal-lg">
      <div class="admin-modal-header">
        <div class="admin-modal-title" id="diff-modal-title">🔍 So Sánh Khác Biệt Giữa 2 Phiên Bản</div>
        <button class="admin-modal-close" onclick="closeAdminModal('modal-diff')">&times;</button>
      </div>
      <div class="admin-modal-body">
        <div style="display: flex; gap: 10px; margin-bottom: 14px; align-items: center; flex-wrap: wrap;">
          <label>So sánh bản:</label>
          <select id="diff-v1-select" class="form-select-sm" style="width: 130px;"></select>
          <span>với bản:</span>
          <select id="diff-v2-select" class="form-select-sm" style="width: 130px;"></select>
          <button class="btn-admin btn-admin-primary btn-sm" onclick="reloadDiffModal()">So Sánh Lại</button>
        </div>

        <div id="diff-summary-alert" style="background:#e8f4f8; padding:10px 14px; border-radius:3px; margin-bottom:12px; font-size:12px; color:#31708f;">
          Đang phân tích khác biệt giữa 2 phiên bản...
        </div>

        <div class="table-responsive">
          <table class="admin-table" style="font-size: 12px;">
            <thead>
              <tr>
                <th style="width: 140px;" class="text-left">MÃ CỜ</th>
                <th class="text-left">TÊN TÍNH NĂNG</th>
                <th id="diff-th-v1" style="width: 120px;">BẢN V1</th>
                <th id="diff-th-v2" style="width: 120px;">BẢN V2</th>
                <th class="text-left">CHI TIẾT THAY ĐỔI</th>
              </tr>
            </thead>
            <tbody id="diff-table-body">
              <!-- Render bằng JS -->
            </tbody>
          </table>
        </div>
      </div>
      <div class="admin-modal-footer">
        <button class="btn-admin btn-admin-default" onclick="closeAdminModal('modal-diff')">Đóng</button>
      </div>
    </div>
  </div>

  <!-- MODAL KHÔI PHỤC (ROLLBACK) -->
  <div class="admin-modal-backdrop" id="modal-rollback">
    <div class="admin-modal-dialog">
      <div class="admin-modal-header" style="background-color: var(--admin-red);">
        <div class="admin-modal-title">Khôi Phục Cấu Hình Về Bản Cũ (Rollback)</div>
        <button class="admin-modal-close" onclick="closeAdminModal('modal-rollback')">&times;</button>
      </div>
      <div class="admin-modal-body">
        <div style="background: #fcf8e3; border: 1px solid #faebcc; color: #8a6d3b; padding: 12px; border-radius: 3px; margin-bottom: 14px; font-size: 13px;">
          Hệ thống sẽ tái tạo cấu hình y hệt bản cũ và <strong>tạo một phiên bản mới rồi phát hành ngay</strong>. Đảm bảo tính bất biến, không xóa lịch sử cũ và tuyệt đối an toàn.
        </div>
        <div class="form-group">
          <label>Phiên bản đích cần quay lại:</label>
          <input type="text" id="rollback-target-version" class="form-control" readonly style="background:#eee; font-weight:700;">
        </div>
        <div class="form-group">
          <label for="rollback-reason">Lý do khôi phục <b>*</b> (Bắt buộc theo Chức năng 5):</label>
          <textarea id="rollback-reason" class="form-control" rows="3" placeholder="Ví dụ: Bản mới gây lỗi phát sinh tỷ lệ cao, khôi phục gấp về bản v1 an toàn"></textarea>
        </div>
      </div>
      <div class="admin-modal-footer">
        <button class="btn-admin btn-admin-default" onclick="closeAdminModal('modal-rollback')">Hủy bỏ</button>
        <button class="btn-admin btn-admin-danger" onclick="submitRollbackVersion()">Xác Nhận Khôi Phục</button>
      </div>
    </div>
  </div>

  <!-- MODAL NHẬP CẤU HÌNH TỪ JSON -->
  <div class="admin-modal-backdrop" id="modal-import">
    <div class="admin-modal-dialog">
      <div class="admin-modal-header">
        <div class="admin-modal-title">📤 Nhập Cấu Hình Từ Tệp JSON</div>
        <button class="admin-modal-close" onclick="closeAdminModal('modal-import')">&times;</button>
      </div>
      <div class="admin-modal-body">
        <div style="background: #e8f4f8; padding: 10px 14px; border-radius: 3px; font-size: 12px; color: #31708f; margin-bottom: 14px;">
          <strong>Quy tắc kiểm tra hợp lệ:</strong> Tên tính năng (name) không được để trống và Tỷ lệ % (percentage) phải từ 0 đến 100. Nếu vi phạm, hệ thống báo lỗi đỏ và từ chối nạp.
        </div>
        <div class="form-group">
          <label for="import-file">Chọn tệp JSON:</label>
          <input type="file" id="import-file" class="form-control" accept=".json" onchange="onFileSelected(this)">
        </div>
        <div class="form-group">
          <label for="import-text">Hoặc dán trực tiếp nội dung JSON:</label>
          <textarea id="import-text" class="form-control" rows="5" placeholder='[{"name": "Banner iPhone 17", "key": "banner_iphone_17", "percentage": 50, ...}]'></textarea>
        </div>
        <div class="form-group">
          <label for="import-reason">Lý do nhập cấu hình:</label>
          <input type="text" id="import-reason" class="form-control" placeholder="Ví dụ: Đồng bộ cấu hình đợt mở bán từ file backup">
        </div>
        <div id="import-error-box" style="display:none; background:#f8d7da; color:#721c24; padding:10px; border-radius:3px; margin-top:10px; font-size:12px;"></div>
      </div>
      <div class="admin-modal-footer">
        <button class="btn-admin btn-admin-default" onclick="closeAdminModal('modal-import')">Hủy bỏ</button>
        <button class="btn-admin btn-admin-primary" onclick="submitImportConfig()">Xác Nhận Nhập</button>
      </div>
    </div>
  </div>

  <!-- Toast Container -->
  <div id="toast-container"></div>

  <!-- JavaScript Modules -->
  <script src="js/admin_app.php"></script>
</body>
</html>
