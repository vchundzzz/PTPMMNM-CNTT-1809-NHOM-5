<?php
/**
 * demo-app.php
 * Website Bán Hàng CDS STORE (Apple Authorised Reseller - AAR) Demo
 * Tích hợp toàn diện 10 Cờ Tính Năng (Feature Flags) của hệ thống quản trị:
 * 1.  thanh_toan_nhanh     -> Quy trình thanh toán 1 bước (One-step checkout) vs 3 bước tiêu chuẩn
 * 2.  giao_dien_toi        -> Giao diện đen bóng than chì (#3e3e3f) chuẩn Dark Mode
 * 3.  danh_gia_san_pham    -> Khối đánh giá sao và xem ảnh unbox thực tế của khách hàng
 * 4.  danh_sach_yeu_thich  -> Nút thả tim lưu sản phẩm yêu thích (Wishlist)
 * 5.  ho_tro_truc_tuyen    -> Widget chat chuyên viên tư vấn Apple CDS STORE 24/7
 * 6.  ma_giam_gia          -> Tự động nhận diện và áp dụng mã giảm giá độc quyền (CDS18)
 * 7.  goi_y_san_pham       -> Gợi ý combo phụ kiện Apple đi kèm (AI Recommendation)
 * 8.  tim_kiem_nang_cao    -> Bộ lọc nâng cao theo dòng máy, dung lượng, khoảng giá và sắp xếp
 * 9.  theo_doi_don_hang    -> Tra cứu tiến độ giao hàng thời gian thực (GPS shipper map)
 * 10. tich_diem_thanh_vien -> Tích lũy và trừ điểm hội viên CDS STORE Member khi thanh toán
 */

require_once __DIR__ . '/includes/helpers.php';

require_once __DIR__ . '/services/FeatureFlagService.php';

if (!is_customer_logged_in()) {
    $store_theme = ($_SESSION['store_theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
    $return_url = 'demo-app.php';
    if (!empty($_SERVER['QUERY_STRING'])) {
        $return_url .= '?' . $_SERVER['QUERY_STRING'];
    }
    header('Location: store-auth.php?mode=login&theme=' . $store_theme . '&redirect=' . rawurlencode($return_url));
    exit;
}

$page_title = 'CDS - Cửa hàng Apple chính hãng (APR)';

// --------------------------------------------------------------------------
// 1. THIẾT LẬP THÔNG SỐ KIỂM THỬ & KIỂM TRA PHÂN QUYỀN TRUY CẬP (RBAC)
// --------------------------------------------------------------------------
$current_auth_user = get_authenticated_user();
$current_customer = get_authenticated_customer();
$auth_role = $current_auth_user['role'] ?? 'viewer';

// Chỉ vai trò Admin và Biên tập (Editor) mới được phép thấy và chỉnh sửa Testing Controller
$can_manage_controller = in_array($auth_role, ['admin', 'editor'], true);

if (!$can_manage_controller) {
    // Khách hàng xem storefront theo môi trường đang kích hoạt của hệ thống.
    $current_env = get_active_env();
    $current_user_id = $current_customer['id'] ?? 'KH1';
    $simulate_error = false;
    $highlight_flag = '';
} else {
    // Tự động đồng bộ với môi trường đang kích hoạt trong hệ thống quản trị ($_SESSION['current_env'])
    if (isset($_GET['env']) && in_array($_GET['env'], ['testing', 'staging'], true)) {
        $current_env = $_GET['env'];
        set_active_env($current_env);
        $_SESSION['current_env'] = $current_env;
    } else {
        $current_env = get_active_env();
    }

    $current_user_id = trim($_GET['user_id'] ?? 'KH1');
    if ($current_user_id === '') {
        $current_user_id = 'KH1';
    }

    $simulate_error = !empty($_GET['simulate_error']);
    $highlight_flag = $_GET['highlight'] ?? '';
}

// Danh sách 11 cờ tính năng đồng bộ 100% với CSDL MySQL
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
    'banner_quang_cao',
];

// --------------------------------------------------------------------------
// 2. ĐÁNH GIÁ ĐỒNG LOẠT 10 CỜ TÍNH NĂNG QUA FeatureFlagService
// --------------------------------------------------------------------------
$eval_results = [];
foreach ($flag_keys as $f_key) {
    if ($simulate_error) {
        $eval_results[$f_key] = [
            'status' => 200,
            'data' => [
                'flag_key' => $f_key,
                'enabled'  => false,
                'reason'   => 'Fallback: Mô phỏng máy chủ lỗi hoặc cúp mạng API (Default False)'
            ]
        ];
    } else {
        $eval_results[$f_key] = FeatureFlagService::evaluate($f_key, $current_user_id, $current_env);
    }
}

// Shortcut biến trạng thái bật/tắt của từng cờ
$is_fast_pay    = !empty($eval_results['thanh_toan_nhanh']['data']['enabled']);
// Keep storefront colors consistent between testing and staging.
$is_dark_mode   = !empty($eval_results['giao_dien_toi']['data']['enabled']);
// Keep the admin controller light regardless of the storefront's dark-mode flag.
$is_console_dark_mode = $is_dark_mode;
$is_reviews     = !empty($eval_results['danh_gia_san_pham']['data']['enabled']);
$is_wishlist    = !empty($eval_results['danh_sach_yeu_thich']['data']['enabled']);
$is_chat        = !empty($eval_results['ho_tro_truc_tuyen']['data']['enabled']);
$is_coupon      = !empty($eval_results['ma_giam_gia']['data']['enabled']);
$is_ai_recs     = !empty($eval_results['goi_y_san_pham']['data']['enabled']);
$is_adv_filter  = !empty($eval_results['tim_kiem_nang_cao']['data']['enabled']);
$is_tracking    = !empty($eval_results['theo_doi_don_hang']['data']['enabled']);
$is_loyalty     = !empty($eval_results['tich_diem_thanh_vien']['data']['enabled']);
$is_banner      = !empty($eval_results['banner_quang_cao']['data']['enabled']);
$_SESSION['store_theme'] = $is_dark_mode ? 'dark' : 'light';

// --------------------------------------------------------------------------
// 3. DỮ LIỆU BANNER QUẢNG CÁO CDS STORE (HTTP 200 VERIFIED)
// --------------------------------------------------------------------------
$slider_banners = [
    [
        'img' => 'assets/images/products/banner-iphone-18.jpg',
        'title' => 'Nâng cấp lên iPhone Duo - Gập TopZone Thu cũ trợ giá đến 3 triệu',
        'link' => '#sec-iphone'
    ],
    [
        'img' => 'assets/images/products/banner-macbook-m3.png',
        'title' => 'MacBook Air M3 - Mỏng nhẹ đỉnh cao, pin bứt phá 18 tiếng',
        'link' => '#sec-mac'
    ],
    [
        'img' => 'assets/images/products/banner-ipad-mini.jpg',
        'title' => 'iPad mini 7 & iPad Air M2 - Chip Apple Intelligence siêu mạnh',
        'link' => '#sec-ipad'
    ],
    [
        'img' => 'assets/images/products/banner-macbook-m6.jpg',
        'title' => 'MacBook Pro M3 Pro / Max - Hiệu năng chuyên nghiệp đỉnh cao',
        'link' => '#sec-mac'
    ],
    [
        'img' => 'assets/images/products/banner-watch-s12.png',
        'title' => 'Apple Watch Series 11 & Ultra 3 - Chuẩn mực đồng hồ thông minh Apple',
        'link' => '#sec-watch'
    ],
    [
        'img' => 'assets/images/products/banner-macbook-neo.png',
        'title' => 'MacBook Neo - Đột phá thiết kế mới tại TopZone',
        'link' => '#sec-mac'
    ],
];

// Danh mục nhanh (Choose Category Icons)
$quick_categories = [
    ['name' => 'iPhone', 'img' => 'assets/images/products/cat-iphone.png', 'target' => 'sec-iphone'],
    ['name' => 'Mac', 'img' => 'assets/images/products/cat-mac.png', 'target' => 'sec-mac'],
    ['name' => 'iPad', 'img' => 'assets/images/products/cat-ipad.png', 'target' => 'sec-ipad'],
    ['name' => 'Watch', 'img' => 'assets/images/products/cat-watch.png', 'target' => 'sec-watch'],
    ['name' => 'Tai nghe, Loa', 'img' => 'assets/images/products/cat-audio.png', 'target' => 'sec-audio'],
    ['name' => 'Phụ kiện', 'img' => 'assets/images/products/cat-accessories.png', 'target' => 'sec-accessories'],
];

// --------------------------------------------------------------------------
// HÀM HELPER: RENDER ĐÁNH GIÁ 5 SAO CHUẨN ANT DESIGN (Rate component)
// --------------------------------------------------------------------------
if (!function_exists('render_ant_stars')) {
    function render_ant_stars($rating, $reviews_count, $is_dark_mode = true) {
        $rating = (float)$rating;
        $html = '<div class="tz-stars" title="' . $rating . ' sao trên 5 sao (' . $reviews_count . ' đánh giá)">';
        $html .= '<div class="ant-rate-stars" style="display:inline-flex; align-items:center; gap:2px; margin-right:5px;">';
        for ($i = 1; $i <= 5; $i++) {
            $fill = ($i <= round($rating)) ? '#faad14' : ($is_dark_mode ? '#434343' : '#d9d9d9');
            $html .= '<svg viewBox="64 64 896 896" width="13" height="13" fill="' . $fill . '" style="display:inline-block; vertical-align:-1px;"><path d="M908.1 353.1l-253.9-36.9L540.7 86.1c-3.1-6.3-8.2-11.4-14.5-14.5-15.8-7.8-35-1.3-42.9 14.5L369.8 316.2l-253.9 36.9c-7 1-13.4 4.3-18.3 9.3a32.05 32.05 0 0 0 .6 45.3l183.7 179.1-43.4 252.9a31.95 31.95 0 0 0 46.4 33.7L512 754l227.1 119.4c6.2 3.3 13.4 4.4 20.3 3.2 17.4-3 29.1-19.5 26.1-36.9l-43.4-252.9 183.7-179.1c5-4.9 8.3-11.3 9.3-18.3 2.7-17.5-9.5-33.7-27-36.3z"/></svg>';
        }
        $html .= '</div>';
        $html .= '<strong style="color:#faad14; font-weight:700; font-size:0.86rem; margin-right:4px;">' . number_format($rating, 1) . '</strong>';
        $count_color = $is_dark_mode ? '#94a3b8' : '#64748b';
        $html .= '<span style="color:' . $count_color . '; font-size:0.75rem;">(' . $reviews_count . ' đánh giá)</span>';
        $html .= '</div>';
        return $html;
    }
}

// --------------------------------------------------------------------------
// 4. DANH MỤC SẢN PHẨM CDS STORE (100% ẢNH ĐÃ VERIFIED HTTP 200)
// --------------------------------------------------------------------------
$iphone_products = [
    [
        'id' => 'ip18promax',
        'name' => 'iPhone 18 Pro Max 256GB',
        'series' => '18-series',
        'category' => 'iphone',
        'price' => '41.990.000đ',
        'old_price' => '44.990.000đ',
        'discount' => '-7%',
        'is_new' => true,
        'rating' => 5.0,
        'reviews_count' => 520,
        'colors' => [
            ['name' => 'Băng Thanh (Titanium)', 'hex' => '#9ab2c9', 'img' => 'assets/images/products/iphone-18-promax-titan.png'],
            ['name' => 'Đen Bóng', 'hex' => '#212124', 'img' => 'assets/images/products/iphone-18-promax-black.png'],
            ['name' => 'Đỏ Ruby', 'hex' => '#9e2a2b', 'img' => 'assets/images/products/iphone-18-promax-ruby.png'],
        ],
        'capacities' => ['256GB', '512GB', '1TB', '2TB'],
        'specs' => 'Titanium vũ trụ siêu bền, Chip Apple A19 Pro, Màn hình Ultra Dynamic 6.9"'
    ],
    [
        'id' => 'ip18pro',
        'name' => 'iPhone 18 Pro 256GB',
        'series' => '18-series',
        'category' => 'iphone',
        'price' => '38.990.000đ',
        'old_price' => '40.990.000đ',
        'discount' => '-5%',
        'is_new' => true,
        'rating' => 4.9,
        'reviews_count' => 310,
        'colors' => [
            ['name' => 'Băng Thanh (Titanium)', 'hex' => '#9ab2c9', 'img' => 'assets/images/products/iphone-18-pro-titan.png'],
            ['name' => 'Bạc Titanium', 'hex' => '#e3e4e5', 'img' => 'assets/images/products/iphone-18-pro-silver.png'],
            ['name' => 'Đỏ Ruby', 'hex' => '#9e2a2b', 'img' => 'assets/images/products/iphone-18-pro-ruby.png'],
        ],
        'capacities' => ['256GB', '512GB', '1TB'],
        'specs' => 'Màn hình ProMotion 120Hz 6.3 inch, 3 Camera 48MP zoom quang 5x'
    ],
    [
        'id' => 'ip18promax_512',
        'name' => 'iPhone 18 Pro Max 512GB',
        'series' => '18-series',
        'category' => 'iphone',
        'price' => '48.490.000đ',
        'old_price' => '50.990.000đ',
        'discount' => '-5%',
        'is_new' => true,
        'rating' => 5.0,
        'reviews_count' => 240,
        'colors' => [
            ['name' => 'Đen Bóng', 'hex' => '#212124', 'img' => 'assets/images/products/iphone-18-promax-black.png'],
            ['name' => 'Băng Thanh', 'hex' => '#9ab2c9', 'img' => 'assets/images/products/iphone-18-promax-titan.png'],
            ['name' => 'Đỏ Ruby', 'hex' => '#9e2a2b', 'img' => 'assets/images/products/iphone-18-promax-ruby.png'],
        ],
        'capacities' => ['512GB', '1TB', '2TB'],
        'specs' => 'Dung lượng cực lớn 512GB, Quay ProRes 4K 120fps chuyên nghiệp, Pin 33 giờ'
    ],
    [
        'id' => 'ip16plus',
        'name' => 'iPhone 16 Plus 128GB',
        'series' => '16-series',
        'category' => 'iphone',
        'price' => '25.990.000đ',
        'old_price' => '26.990.000đ',
        'discount' => '-4%',
        'is_new' => true,
        'rating' => 4.8,
        'reviews_count' => 145,
        'colors' => [
            ['name' => 'Xanh Lưu Ly', 'hex' => '#7b9cb8', 'img' => 'assets/images/products/iphone-16-plus-blue.png'],
        ],
        'capacities' => ['128GB', '256GB', '512GB'],
        'specs' => 'Màn hình lớn 6.7 inch, Pin trâu bậc nhất, Nút Camera Control thế hệ mới'
    ],
    [
        'id' => 'ip16',
        'name' => 'iPhone 16 128GB',
        'series' => '16-series',
        'category' => 'iphone',
        'price' => '22.590.000đ',
        'old_price' => '23.490.000đ',
        'discount' => '-4%',
        'is_new' => true,
        'rating' => 4.8,
        'reviews_count' => 190,
        'colors' => [
            ['name' => 'Hồng Pastel', 'hex' => '#f5cbd6', 'img' => 'assets/images/products/iphone-16-pink.png'],
        ],
        'capacities' => ['128GB', '256GB', '512GB'],
        'specs' => 'Camera kép xếp dọc ấn tượng hỗ trợ quay video không gian Spatial'
    ],
    [
        'id' => 'ip15',
        'name' => 'iPhone 15 128GB',
        'series' => '15-series',
        'category' => 'iphone',
        'price' => '19.490.000đ',
        'old_price' => '21.990.000đ',
        'discount' => '-11%',
        'is_new' => false,
        'rating' => 4.8,
        'reviews_count' => 520,
        'colors' => [
            ['name' => 'Hồng', 'hex' => '#f7d3d9', 'img' => 'assets/images/products/iphone-15-pink.png'],
        ],
        'capacities' => ['128GB', '256GB'],
        'specs' => 'Dynamic Island đa nhiệm thông minh, Camera 48MP sắc nét'
    ],
    [
        'id' => 'ip14',
        'name' => 'iPhone 14 128GB',
        'series' => '14-series',
        'category' => 'iphone',
        'price' => '16.890.000đ',
        'old_price' => '18.990.000đ',
        'discount' => '-11%',
        'is_new' => false,
        'rating' => 4.7,
        'reviews_count' => 410,
        'colors' => [
            ['name' => 'Tím', 'hex' => '#e5d7f2', 'img' => 'assets/images/products/iphone-14-purple.png'],
        ],
        'capacities' => ['128GB', '256GB'],
        'specs' => 'Chip A15 Bionic 5 nhân GPU, Phát hiện va chạm khẩn cấp'
    ],
    [
        'id' => 'ip_air',
        'name' => 'iPhone Air 256GB',
        'series' => '18-series',
        'category' => 'iphone',
        'price' => '22.990.000đ',
        'old_price' => '24.990.000đ',
        'discount' => '-8%',
        'is_new' => true,
        'rating' => 4.8,
        'reviews_count' => 160,
        'colors' => [
            ['name' => 'Xanh Ngọc', 'hex' => '#a7d5c9', 'img' => 'assets/images/products/iphone-air-mint.png'],
        ],
        'capacities' => ['256GB', '512GB'],
        'specs' => 'Thiết kế siêu mỏng nhẹ thời thượng, Màn hình OLED viền siêu mỏng'
    ],
];

$mac_products = [
    [
        'id' => 'mac_air_13_m3',
        'name' => 'MacBook Air 13 inch M3 16GB/512GB',
        'series' => 'macbook-air',
        'category' => 'mac',
        'price' => '32.990.000đ',
        'old_price' => '35.290.000đ',
        'discount' => '-7%',
        'is_new' => false,
        'rating' => 4.9,
        'reviews_count' => 124,
        'image' => 'assets/images/products/macbook-air-13-blue.png',
        'capacities' => ['256GB', '512GB', '1TB'],
        'specs' => 'Apple M3 8-core CPU, 10-core GPU, Màn Liquid Retina 13.6"'
    ],
    [
        'id' => 'mac_pro_14_m3',
        'name' => 'MacBook Pro 14 inch M3 Pro 18GB/512GB',
        'series' => 'macbook-pro',
        'category' => 'mac',
        'price' => '49.990.000đ',
        'old_price' => '52.990.000đ',
        'discount' => '-6%',
        'is_new' => true,
        'rating' => 5.0,
        'reviews_count' => 88,
        'image' => 'assets/images/products/macbook-pro-14-black.png',
        'capacities' => ['512GB', '1TB', '2TB'],
        'specs' => 'Apple M3 Pro 11-core CPU, 14-core GPU, Liquid Retina XDR 120Hz'
    ],
    [
        'id' => 'mac_pro_16_m3',
        'name' => 'MacBook Pro 16 inch M3 Max 36GB/1TB',
        'series' => 'macbook-pro',
        'category' => 'mac',
        'price' => '78.690.000đ',
        'old_price' => '81.990.000đ',
        'discount' => '-4%',
        'is_new' => true,
        'rating' => 5.0,
        'reviews_count' => 45,
        'image' => 'assets/images/products/macbook-pro-16-silver.png',
        'capacities' => ['1TB', '2TB', '4TB'],
        'specs' => 'Apple M3 Max 14-core CPU, 30-core GPU, Pin siêu trâu 22 giờ'
    ],
    [
        'id' => 'mac_mini_m4',
        'name' => 'Mac mini M4 16GB/512GB',
        'series' => 'mac-mini',
        'category' => 'mac',
        'price' => '26.690.000đ',
        'old_price' => '27.490.000đ',
        'discount' => '-3%',
        'is_new' => true,
        'rating' => 4.8,
        'reviews_count' => 96,
        'image' => 'assets/images/products/mac-mini-m4.png',
        'capacities' => ['256GB', '512GB', '1TB'],
        'specs' => 'Thiết kế vuông 12.7cm, Chip Apple M4 thế hệ mới nhất'
    ],
    [
        'id' => 'imac_24_m3',
        'name' => 'iMac 24 inch M3 16GB/256GB',
        'series' => 'imac',
        'category' => 'mac',
        'price' => '40.190.000đ',
        'old_price' => '41.490.000đ',
        'discount' => '-3%',
        'is_new' => false,
        'rating' => 4.9,
        'reviews_count' => 64,
        'image' => 'assets/images/products/imac-24-blue.png',
        'capacities' => ['256GB', '512GB'],
        'specs' => 'Màn hình Retina 4.5K 24 inch, 7 sắc màu cá tính, Loa Dolby Atmos'
    ],
    [
        'id' => 'mac_air_15_m3',
        'name' => 'MacBook Air 15 inch M3 16GB/512GB',
        'series' => 'macbook-air',
        'category' => 'mac',
        'price' => '41.390.000đ',
        'old_price' => '41.990.000đ',
        'discount' => '-2%',
        'is_new' => false,
        'rating' => 4.9,
        'reviews_count' => 77,
        'image' => 'assets/images/products/macbook-air-15-gold.png',
        'capacities' => ['256GB', '512GB'],
        'specs' => 'Màn hình 15.3 inch rộng rãi, mỏng 11.5mm, 6 loa khử từ tính'
    ]
];

$ipad_products = [
    [
        'id' => 'ipad_pro_m4_11',
        'name' => 'iPad Pro M4 11 inch WiFi 256GB',
        'series' => 'ipad-pro',
        'category' => 'ipad',
        'price' => '27.990.000đ',
        'old_price' => '28.990.000đ',
        'discount' => '-4%',
        'is_new' => true,
        'rating' => 5.0,
        'reviews_count' => 152,
        'image' => 'assets/images/products/ipad-pro-m4-gray.png',
        'capacities' => ['256GB', '512GB', '1TB'],
        'specs' => 'Màn Ultra Retina XDR Tandem OLED, Siêu mỏng 5.3mm, Chip M4'
    ],
    [
        'id' => 'ipad_air_m2_11',
        'name' => 'iPad Air M2 11 inch WiFi 128GB',
        'series' => 'ipad-air',
        'category' => 'ipad',
        'price' => '16.490.000đ',
        'old_price' => '16.990.000đ',
        'discount' => '-3%',
        'is_new' => false,
        'rating' => 4.9,
        'reviews_count' => 189,
        'image' => 'assets/images/products/ipad-air-m2-gray.png',
        'capacities' => ['128GB', '256GB', '512GB'],
        'specs' => 'Chip Apple M2 bứt phá, Hỗ trợ Apple Pencil Pro & Magic Keyboard'
    ],
    [
        'id' => 'ipad_gen_10',
        'name' => 'iPad 10.9 inch (Gen 10) WiFi 64GB',
        'series' => 'ipad-gen10',
        'category' => 'ipad',
        'price' => '9.490.000đ',
        'old_price' => '10.990.000đ',
        'discount' => '-14%',
        'is_new' => false,
        'rating' => 4.8,
        'reviews_count' => 340,
        'image' => 'assets/images/products/ipad-gen10-yellow.png',
        'capacities' => ['64GB', '256GB'],
        'specs' => 'Thiết kế vuông vức tràn viền, Cổng USB-C, 4 màu sắc trẻ trung'
    ],
    [
        'id' => 'ipad_mini_7',
        'name' => 'iPad mini 7 WiFi 128GB',
        'series' => 'ipad-mini',
        'category' => 'ipad',
        'price' => '13.990.000đ',
        'old_price' => '14.990.000đ',
        'discount' => '-7%',
        'is_new' => true,
        'rating' => 4.9,
        'reviews_count' => 86,
        'image' => 'assets/images/products/ipad-mini-7-purple.png',
        'capacities' => ['128GB', '256GB', '512GB'],
        'specs' => 'Màn Liquid Retina 8.3 inch, Chip A17 Pro hỗ trợ Apple Intelligence'
    ]
];

$watch_products = [
    [
        'id' => 'watch_s10_nhom',
        'name' => 'Apple Watch Series 10 GPS 42mm viền nhôm',
        'series' => 'watch-s10',
        'category' => 'watch',
        'price' => '10.490.000đ',
        'old_price' => '10.990.000đ',
        'discount' => '-5%',
        'is_new' => true,
        'rating' => 4.9,
        'reviews_count' => 110,
        'image' => 'assets/images/products/watch-s10-black.png',
        'capacities' => ['42mm', '46mm'],
        'specs' => 'Màn hình OLED góc rộng sáng nhất, Mỏng hơn 10%, Cảnh báo ngưng thở khi ngủ'
    ],
    [
        'id' => 'watch_s10_titan',
        'name' => 'Apple Watch Series 10 Cellular 42mm Titanium',
        'series' => 'watch-s10',
        'category' => 'watch',
        'price' => '18.990.000đ',
        'old_price' => '20.990.000đ',
        'discount' => '-9%',
        'is_new' => true,
        'rating' => 5.0,
        'reviews_count' => 47,
        'image' => 'assets/images/products/watch-s10-gold.png',
        'capacities' => ['42mm', '46mm'],
        'specs' => 'Vỏ Titanium đánh bóng trang sức sang trọng, Kính Sapphire chống trầy'
    ],
    [
        'id' => 'watch_ultra_2',
        'name' => 'Apple Watch Ultra 2 GPS + Cellular 49mm',
        'series' => 'watch-ultra',
        'category' => 'watch',
        'price' => '21.490.000đ',
        'old_price' => '21.990.000đ',
        'discount' => '-3%',
        'is_new' => false,
        'rating' => 5.0,
        'reviews_count' => 95,
        'image' => 'assets/images/products/watch-ultra-2.png',
        'capacities' => ['49mm Titan Đen', '49mm Titan Tự Nhiên'],
        'specs' => 'Khung Titan chuẩn quân đội, Lặn sâu 100m, Thời lượng pin 72 giờ'
    ],
    [
        'id' => 'watch_ultra_3_ocean',
        'name' => 'Apple Watch Ultra 3 GPS + Cellular 49mm Ocean',
        'series' => 'watch-ultra',
        'category' => 'watch',
        'price' => '23.090.000đ',
        'old_price' => '23.990.000đ',
        'discount' => '-4%',
        'is_new' => true,
        'rating' => 5.0,
        'reviews_count' => 118,
        'image' => 'assets/images/products/watch-ultra-3-ocean.png',
        'capacities' => ['49mm Dây Ocean Đen', '49mm Dây Alpine'],
        'specs' => 'Viền Titanium siêu bền, Dây Ocean chuyên lặn biển, Pin 72 giờ'
    ]
];

$audio_products = [
    [
        'id' => 'airpods_max_usbc',
        'name' => 'AirPods Max cổng USB-C',
        'series' => 'airpods-max',
        'category' => 'audio',
        'price' => '13.590.000đ',
        'old_price' => '13.990.000đ',
        'discount' => '-3%',
        'is_new' => true,
        'rating' => 5.0,
        'reviews_count' => 142,
        'image' => 'assets/images/products/airpods-max-orange.png',
        'capacities' => ['Cam', 'Đen', ' Ánh Sao'],
        'specs' => 'Âm thanh không gian cá nhân hóa & Chống ồn chủ động đỉnh cao'
    ],
    [
        'id' => 'airpods_4_anc',
        'name' => 'AirPods 4 (Chống ồn chủ động)',
        'series' => 'airpods-4',
        'category' => 'audio',
        'price' => '4.490.000đ',
        'old_price' => '4.790.000đ',
        'discount' => '-6%',
        'is_new' => true,
        'rating' => 4.9,
        'reviews_count' => 235,
        'image' => 'assets/images/products/airpods-4.png',
        'capacities' => ['Bản Chống Ồn ANC', 'Bản Tiêu Chuẩn'],
        'specs' => 'Chip H2, Chế độ Xuyên Âm thông minh & Hộp sạc tích hợp loa tìm kiếm'
    ],
    [
        'id' => 'airpods_4_std',
        'name' => 'AirPods 4 (Bản tiêu chuẩn)',
        'series' => 'airpods-4',
        'category' => 'audio',
        'price' => '3.490.000đ',
        'old_price' => '3.790.000đ',
        'discount' => '-8%',
        'is_new' => false,
        'rating' => 4.8,
        'reviews_count' => 310,
        'image' => 'assets/images/products/airpods-4.png',
        'capacities' => ['USB-C'],
        'specs' => 'Thiết kế công thái học vừa vặn, Chống bụi và mồ hôi chuẩn IP54'
    ],
    [
        'id' => 'airpods_max_starlight',
        'name' => 'AirPods Max USB-C Bản Đặc Biệt',
        'series' => 'airpods-max',
        'category' => 'audio',
        'price' => '12.990.000đ',
        'old_price' => '13.990.000đ',
        'discount' => '-7%',
        'is_new' => false,
        'rating' => 4.9,
        'reviews_count' => 89,
        'image' => 'assets/images/products/airpods-max-orange.png',
        'capacities' => ['USB-C Lossless'],
        'specs' => 'Hỗ trợ âm thanh Lossless độ trễ cực thấp qua cáp USB-C'
    ],
];

$accessory_products = [
    [
        'id' => 'pk_adapter_20w',
        'name' => 'Adapter sạc Apple USB-C 20W',
        'series' => 'adapter',
        'category' => 'accessories',
        'price' => '490.000đ',
        'old_price' => '550.000đ',
        'discount' => '-10%',
        'is_new' => false,
        'rating' => 5.0,
        'reviews_count' => 640,
        'image' => 'assets/images/products/charger-20w.png',
        'capacities' => ['20W USB-C'],
        'specs' => 'Chuẩn sạc nhanh Power Delivery chính hãng Apple bảo vệ pin tối ưu'
    ],
    [
        'id' => 'pk_cable_usbc',
        'name' => 'Cáp sạc Apple USB-C Bện Dù 60W (1m)',
        'series' => 'cable',
        'category' => 'accessories',
        'price' => '490.000đ',
        'old_price' => '590.000đ',
        'discount' => '-17%',
        'is_new' => true,
        'rating' => 4.9,
        'reviews_count' => 415,
        'image' => 'assets/images/products/cable-usbc.png',
        'capacities' => ['1m (60W)', '2m (240W)'],
        'specs' => 'Dây bện dù siêu bền, tương thích mọi thiết bị Apple Type-C'
    ],
    [
        'id' => 'pk_case_magsafe',
        'name' => 'Ốp lưng MagSafe iPhone Pro Max Trong Suốt',
        'series' => 'case',
        'category' => 'accessories',
        'price' => '1.190.000đ',
        'old_price' => '1.420.000đ',
        'discount' => '-16%',
        'is_new' => true,
        'rating' => 4.9,
        'reviews_count' => 210,
        'image' => 'assets/images/products/case-magsafe.png',
        'capacities' => ['Pro Max', 'Pro'],
        'specs' => 'Vòng nam châm MagSafe tự động hít chặt, Phủ lớp chống ố vàng'
    ],
    [
        'id' => 'pk_adapter_35w',
        'name' => 'Adapter sạc đôi Apple USB-C 35W',
        'series' => 'adapter',
        'category' => 'accessories',
        'price' => '1.290.000đ',
        'old_price' => '1.490.000đ',
        'discount' => '-13%',
        'is_new' => false,
        'rating' => 4.8,
        'reviews_count' => 175,
        'image' => 'assets/images/products/charger-20w.png',
        'capacities' => ['35W 2 cổng USB-C'],
        'specs' => 'Sạc cùng lúc iPhone và MacBook Air / iPad cực kỳ tiện lợi'
    ],
];

$tekzone_news = [
    [
        'title' => 'Tổng hợp ứng dụng được tối ưu hóa cho iPhone Duo màn hình gập mới nhất',
        'time' => '2 giờ trước',
        'img' => 'assets/images/products/news-1.jpg',
    ],
    [
        'title' => 'Cách cài đặt iOS 27.3 Beta trên iPhone cực đơn giản để trải nghiệm tính năng mới',
        'time' => '5 giờ trước',
        'img' => 'assets/images/products/news-2.jpg',
    ],
    [
        'title' => 'Khám phá ứng dụng Sức khỏe mới trên iOS 27.3 với trợ lý AI cá nhân hóa',
        'time' => '1 ngày trước',
        'img' => 'assets/images/products/news-3.jpg',
    ],
];

$recommendations = [
    [
        'id' => 'airpods_max',
        'name' => 'AirPods Max cổng USB-C',
        'category' => 'audio',
        'price' => '13.590.000đ',
        'old_price' => '13.990.000đ',
        'discount' => '-3%',
        'icon' => '',
        'image' => 'assets/images/products/airpods-max-orange.png',
        'desc' => 'Âm thanh không gian cá nhân hóa & Chống ồn chủ động đỉnh cao'
    ],
    [
        'id' => 'airpods_4',
        'name' => 'AirPods 4 Chống Ồn Chủ Động',
        'category' => 'audio',
        'price' => '4.490.000đ',
        'old_price' => '4.790.000đ',
        'discount' => '-6%',
        'icon' => '',
        'image' => 'assets/images/products/airpods-4.png',
        'desc' => 'Chip H2, Chế độ Xuyên Âm thông minh & Hộp sạc tích hợp loa tìm kiếm'
    ],
    [
        'id' => 'sac_apple_20w',
        'name' => 'Củ sạc nhanh Apple 20W Type-C Chính Hãng',
        'category' => 'phukien',
        'price' => '490.000đ',
        'old_price' => '550.000đ',
        'discount' => '-10%',
        'icon' => '',
        'image' => 'assets/images/products/charger-20w.png',
        'desc' => 'Chuẩn sạc nhanh Power Delivery bảo vệ pin tối ưu'
    ],
    [
        'id' => 'cap_apple_typec',
        'name' => 'Cáp Sạc Bện Dù Apple USB-C 60W (1m)',
        'category' => 'phukien',
        'price' => '490.000đ',
        'old_price' => '590.000đ',
        'discount' => '-17%',
        'icon' => '',
        'image' => 'assets/images/products/cable-usbc.png',
        'desc' => 'Dây bện dù siêu bền, tương thích mọi thiết bị Apple Type-C'
    ],
];

// Flashsale items (6 sản phẩm chuẩn bố cục 6 cột của TopZone.vn)
$flashsale_items = [
    [
        'id' => 'fs_watch_s11',
        'name' => 'Apple Watch Series 11 46mm viền nhôm dây thể thao',
        'price' => '11.490.000đ',
        'old_price' => '12.490.000đ',
        'discount' => '-8%',
        'img' => 'assets/images/products/watch-s11-gray.png',
        'sold' => 'Còn 5/5',
        'percent' => 100
    ],
    [
        'id' => 'fs_mac_mini_m4',
        'name' => 'Mac mini M4 16GB/256GB Chính hãng',
        'price' => '13.990.000đ',
        'old_price' => '14.990.000đ',
        'discount' => '-6%',
        'img' => 'assets/images/products/mac-mini-m4.png',
        'sold' => 'Còn 4/5',
        'percent' => 80
    ],
    [
        'id' => 'fs_watch_s10_gold',
        'name' => 'Apple Watch Series 10 42mm viền Titan dây Milanese',
        'price' => '18.590.000đ',
        'old_price' => '20.990.000đ',
        'discount' => '-11%',
        'img' => 'assets/images/products/watch-s10-gold.png',
        'sold' => 'Còn 3/5',
        'percent' => 60
    ],
    [
        'id' => 'fs_watch_ultra_3',
        'name' => 'Apple Watch Ultra 3 GPS + Cellular 49mm viền Titanium',
        'price' => '23.090.000đ',
        'old_price' => '23.990.000đ',
        'discount' => '-3%',
        'img' => 'assets/images/products/watch-ultra-3-ocean.png',
        'sold' => 'Còn 5/5',
        'percent' => 100
    ],
    [
        'id' => 'fs_watch_s10_black',
        'name' => 'Apple Watch Series 10 42mm viền nhôm dây thể thao Đen',
        'price' => '9.990.000đ',
        'old_price' => '10.990.000đ',
        'discount' => '-9%',
        'img' => 'assets/images/products/watch-s10-black.png',
        'sold' => 'Còn 4/5',
        'percent' => 80
    ],
    [
        'id' => 'fs_mac_mini_512',
        'name' => 'Mac mini M4 16GB/512GB Chính hãng Apple',
        'price' => '18.490.000đ',
        'old_price' => '19.990.000đ',
        'discount' => '-7%',
        'img' => 'assets/images/products/mac-mini-m4.png',
        'sold' => 'Còn 3/5',
        'percent' => 60
    ]
];

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title><?= e($page_title) ?></title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/components.css">
  <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body style="margin: 0; padding: 0; background: <?= $is_dark_mode ? '#3e3e3f' : '#f8fafc' ?>; overflow-x: hidden;">

<!-- ========================================================================
     CSS THIẾT KẾ ĐẶC TRƯNG CHUẨN XÁC CDS STORE (APPLE STOREFRONT ĐỘC LẬP)
     ======================================================================== -->
<style>
:root {
  --tz-black: #101010;
  --tz-carbon-bg: #3e3e3f;
  --tz-card-bg: #323232;
  --tz-card-hover: #3a3a3d;
  --tz-border: #444448;
  --tz-accent-blue: #2997ff;
  --tz-accent-red: #ff361d;
  --tz-accent-gold: #f59e0b;
}

/* Nền khung ứng dụng demo CDS STORE toàn màn hình độc lập */
.topzone-wrapper {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  transition: all 0.3s ease;
  min-height: 100vh;
  width: 100%;
  border-radius: 0;
  border: none;
  background: <?= $is_dark_mode ? '#3e3e3f' : '#f8fafc' ?>;
  color: <?= $is_dark_mode ? '#f5f5f7' : '#0f172a' ?>;
}

/* ========================================================================
   OFFICIAL CDS BRAND LOGO & SHARP VECTOR ICONS (CHUẨN TOPZONE STYLE)
   ======================================================================== */
.cds-logo-brand {
  display: inline-flex;
  align-items: center;
  font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, sans-serif;
  font-size: 27px;
  font-weight: 900;
  letter-spacing: 0.5px;
  color: #ffffff;
  text-decoration: none;
  line-height: 1;
  border-right: 1px solid #3b3b3b;
  padding-right: 12px;
  margin-right: 10px;
  height: 34px;
}
.cds-logo-brand .cds-char-s {
  background: linear-gradient(135deg, #ff3b30 0%, #ff9500 32%, #34c759 66%, #007aff 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  display: inline-block;
}
.topzone-autho {
  background-image: url('assets/images/products/APR_logo.webp') !important;
  background-size: 100% !important;
  background-position: 0 0 !important;
  background-repeat: no-repeat !important;
  width: 32px;
  height: 40px;
  display: inline-block;
  vertical-align: middle;
}
.topzone-search {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2.3' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'/%3E%3C/svg%3E");
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  width: 18px;
  height: 18px;
  display: inline-block;
}
.topzone-cart {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z'/%3E%3Cline x1='3' y1='6' x2='21' y2='6'/%3E%3Cpath d='M16 10a4 4 0 0 1-8 0'/%3E%3C/svg%3E");
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  width: 17px;
  height: 17px;
  display: inline-block;
}
.topzone-iconapple {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ffffff'%3E%3Cpath d='M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.47c.68-.83 1.14-1.99.99-3.14-.99.04-2.19.66-2.9 1.49-.63.73-1.18 1.92-1.03 3.06 1.1.09 2.24-.58 2.94-1.41z'/%3E%3C/svg%3E");
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  width: 30px;
  height: 34px;
  display: inline-block;
  vertical-align: middle;
}
.topzone-homeDiver {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M22 11.08V12a10 10 0 1 1-5.93-9.14'/%3E%3Cpolyline points='22 4 12 14.01 9 11.01'/%3E%3C/svg%3E");
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  width: 36px;
  height: 36px;
  display: inline-block;
}
.topzone-homeDeli {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Crect x='1' y='3' width='15' height='13' rx='1'/%3E%3Cpolygon points='16 8 20 8 23 11 23 16 16 16 16 8'/%3E%3Ccircle cx='5.5' cy='18.5' r='2.5'/%3E%3Ccircle cx='18.5' cy='18.5' r='2.5'/%3E%3C/svg%3E");
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  width: 38px;
  height: 36px;
  display: inline-block;
}
.topzone-homeInsur {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'/%3E%3Cpolyline points='9 12 11 14 15 10'/%3E%3C/svg%3E");
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  width: 36px;
  height: 36px;
  display: inline-block;
}
.topzone-homeReturn {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='1 4 1 10 7 10'/%3E%3Cpolyline points='23 20 23 14 17 14'/%3E%3Cpath d='M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15'/%3E%3C/svg%3E");
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
  width: 36px;
  height: 36px;
  display: inline-block;
}
.iconsocial-facebook {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 36 36'%3E%3Ccircle cx='18' cy='18' r='18' fill='%231877F2'/%3E%3Cpath d='M22.5 12H20c-1.1 0-1.5.5-1.5 1.5V16h4l-.5 4h-3.5v10h-4V20H12v-4h2.5v-2.8C14.5 10.3 16.2 8.5 19.5 8.5c1.4 0 3 .2 3 .2V12z' fill='%23fff'/%3E%3C/svg%3E");
  background-size: contain;
  background-repeat: no-repeat;
  width: 33px;
  height: 33px;
  display: inline-block;
}
.iconsocial-youtube {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 36 36'%3E%3Ccircle cx='18' cy='18' r='18' fill='%23FF0000'/%3E%3Cpath d='M26.8 13.4a2.8 2.8 0 0 0-2-2C23 11 18 11 18 11s-5 0-6.8.4a2.8 2.8 0 0 0-2 2C8.8 15.2 8.8 18 8.8 18s0 2.8.4 4.6a2.8 2.8 0 0 0 2 2c1.8.4 6.8.4 6.8.4s5 0 6.8-.4a2.8 2.8 0 0 0 2-2c.4-1.8.4-4.6.4-4.6s0-2.8-.4-4.6zM16 21v-6l5.5 3-5.5 3z' fill='%23fff'/%3E%3C/svg%3E");
  background-size: contain;
  background-repeat: no-repeat;
  width: 33px;
  height: 33px;
  display: inline-block;
}
.iconsocial-zalo {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 36 36'%3E%3Ccircle cx='18' cy='18' r='18' fill='%230068FF'/%3E%3Ctext x='18' y='22' text-anchor='middle' fill='%23ffffff' font-family='Arial,sans-serif' font-weight='900' font-size='11'%3EZalo%3C/text%3E%3C/svg%3E");
  background-size: contain;
  background-repeat: no-repeat;
  width: 33px;
  height: 33px;
  display: inline-block;
}
.cds-eco-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #2d2d30;
  border: 1px solid #3d3d42;
  border-radius: 6px;
  padding: 5px 12px;
  color: #e5e5e5;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.2s ease;
}
.cds-eco-badge:hover {
  border-color: #2997ff;
  color: #ffffff;
  background: #36363a;
}
.boxchat-balloons {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background-color: #101010;
  border: 2.5px solid #ffffff;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5), 0 0 0 4px rgba(255, 255, 255, 0.12);
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Cpath d='M28 15c-9.9 0-18 6.7-18 15 0 3.6 1.5 6.9 4.1 9.5-.5 2.7-1.9 5.2-3.6 6.8-.5.5-.1 1.3.6 1.2 3.4-.4 6.8-1.7 9.2-3.3 2.4.5 5 .8 7.7.8 9.9 0 18-6.7 18-15S37.9 15 28 15z' fill='%23ffffff'/%3E%3Ccircle cx='21.5' cy='30' r='2.3' fill='%23101010'/%3E%3Ccircle cx='28' cy='30' r='2.3' fill='%23101010'/%3E%3Ccircle cx='34.5' cy='30' r='2.3' fill='%23101010'/%3E%3Cpath d='M47.5 29.5c.3 1.2.5 2.4.5 3.7 0 8.5-7.8 15.5-17.8 16.2 2.4 2.4 6.2 4 10.6 4 1.8 0 3.6-.2 5.2-.6 1.8 1.2 4.3 2.1 6.8 2.4.5.1.8-.5.4-.9-1.2-1.2-2.2-3-2.6-5 1.9-1.9 3-4.3 3-7 0-5.6-2.5-10.4-6.1-12.8z' fill='%23ffffff'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: center;
  background-size: 38px 38px;
  display: inline-block;
  position: relative;
  box-sizing: border-box;
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.boxchat-balloons::after {
  content: "";
  position: absolute;
  top: 1px;
  right: 1px;
  width: 13px;
  height: 13px;
  background: #22c55e;
  border: 2px solid #101010;
  border-radius: 50%;
}
.tz-chat-btn:hover .boxchat-balloons {
  transform: translateY(-3px) scale(1.05);
  border-color: #2997ff;
  box-shadow: 0 12px 28px rgba(41, 151, 255, 0.45);
}

/* ========================================================================
   CDS HEADER NAVIGATION CHUẨN #101010 (HEIGHT 60PX, MAX-WIDTH 1200PX)
   ======================================================================== */
.tz-header-wrap {
  background: #101010 !important;
  position: sticky;
  top: 0;
  z-index: 100;
  width: 100%;
  height: 60px;
}
.tz-navbar {
  background: #101010 !important;
  max-width: 1200px;
  min-width: 1024px;
  margin: auto;
  width: 100%;
  height: 60px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 10px;
}
@media (max-width: 1024px) {
  .tz-navbar { min-width: 100%; }
}
.logo-topzone {
  width: 16%;
  display: flex;
  align-items: center;
}
.logo-topzone a {
  display: inline-flex;
  align-items: center;
  vertical-align: middle;
  text-decoration: none;
}
.tz-nav-links {
  width: 62%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  list-style: none;
  margin: 0;
  padding: 0;
  height: 60px;
}
.tz-nav-links li {
  float: left;
  width: 12.5%;
  text-align: center;
}
.tz-nav-links a {
  display: block;
  color: #ffffff;
  font-size: 14px;
  line-height: 21px;
  padding: 20px 0 19px;
  text-decoration: none;
  font-weight: 400;
  transition: background 0.2s ease;
}
.tz-nav-links a:hover,
.tz-nav-links a.active {
  background: #2d2d2d;
  color: #ffffff;
}
.tz-header-tools {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
}
.tz-account-link {
  display: inline-flex;
  align-items: center;
  max-width: 180px;
  gap: 6px;
  padding: 8px 11px;
  border: 1px solid #444448;
  border-radius: 999px;
  color: #f5f5f7;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.tz-account-link:hover { border-color: #2997ff; color: #2997ff; }
.tz-account-menu { position: relative; }
.tz-account-trigger {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 40px;
  padding: 4px 9px 4px 5px;
  border: 1px solid #444448;
  border-radius: 999px;
  color: #f5f5f7;
  background: #2f3033;
  cursor: pointer;
}
.tz-account-trigger:hover { background: #5e5e60; }
.tz-account-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 29px;
  height: 29px;
  border-radius: 50%;
  color: #fff;
  background: #2997ff;
  font-size: 11px;
  font-weight: 800;
}
.tz-account-name { max-width: 90px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; font-weight: 700; }
.tz-account-dropdown {
  position: absolute;
  top: calc(100% + 7px);
  right: 0;
  z-index: 300;
  display: none;
  width: 215px;
  padding: 6px;
  border: 1px solid #444448;
  border-radius: 8px;
  background: #1d1d1f;
  box-shadow: 0 12px 30px rgba(0,0,0,.35);
}
.tz-account-dropdown.show { display: block; }
.tz-account-dropdown-header { padding: 8px 9px; border-bottom: 1px solid #3b3b3f; }
.tz-account-dropdown-item { display: flex; align-items: center; gap: 8px; padding: 9px; border-radius: 5px; color: #d7d7dc; font-size: 12px; text-decoration: none; }
.tz-account-dropdown-item:hover { color: #fff; background: #2f3033; }
.tz-account-dropdown-item.text-danger { color: #ff6b6b; }
.tz-round-btn {
  background: #2f3033;
  width: 40px;
  height: 40px;
  border-radius: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  position: relative;
  transition: background 0.2s ease;
  border: none;
  color: #ffffff;
  padding: 0;
}
.tz-round-btn:hover {
  background: #5e5e60;
}
.tz-cart-badge {
  position: absolute;
  bottom: 2px;
  left: 2px;
  background-color: #ff361d;
  border-radius: 50%;
  color: #fff;
  font-size: 10px;
  line-height: 14px;
  font-weight: 700;
  width: 15px;
  height: 15px;
  text-align: center;
}

/* ========================================================================
   FULL-WIDTH HERO BANNER SLIDER CHUẨN TOPZONE.VN (.slide-home)
   ======================================================================== */
.tz-slider-container {
  position: relative;
  width: 100%;
  max-width: 100%;
  margin: 0 auto;
  overflow: hidden;
  background: #101010;
}
.tz-slides {
  display: flex;
  transition: transform 0.5s ease-in-out;
}
.tz-slide-item {
  min-width: 100%;
  box-sizing: border-box;
}
.tz-slide-item img {
  width: 100%;
  height: auto;
  display: block;
  object-fit: cover;
}
.tz-slider-btn {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  background: #29292a;
  opacity: 0.7;
  color: #ffffff;
  border: none;
  width: 50px;
  height: 50px;
  border-radius: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  z-index: 10;
  font-size: 18px;
}
.tz-slider-btn:hover {
  background: #757575;
  opacity: 1;
}
.tz-slider-prev { left: 50px; }
.tz-slider-next { right: 50px; }
.tz-slider-dots {
  position: absolute;
  bottom: 12px;
  left: 0;
  right: 0;
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 10px;
  z-index: 10;
}
.tz-slider-dot {
  width: 10px;
  height: 10px;
  border-radius: 100%;
  background: #c7c7c7;
  border: 1px solid #fff;
  cursor: pointer;
  transition: all 0.2s ease;
}
.tz-slider-dot.active {
  background: #ffffff;
  border: 2px solid #2997ff;
}

/* ========================================================================
   KHỐI FLASH SALE CHUẨN TOPZONE.VN (.flashsale-block)
   ======================================================================== */
.flashsale-block {
  background: #161616;
  max-width: 1200px;
  border-radius: 12px;
  margin: 20px auto;
  padding: 0 10px 10px;
  position: relative;
}
.flex-fs {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
}
.gvdshock {
  flex: 1.5;
  display: inline-flex;
  align-items: center;
  position: relative;
}
.gvdshock h3 {
  width: 70%;
  margin: 0;
}
.gvdshock h3 img {
  width: 100%;
  height: auto;
  margin-left: -15px;
  display: block;
}
.gvdshock .endtime {
  font-size: 14px;
  line-height: 20px;
  color: #fff;
  display: block;
  text-align: center;
  width: 30%;
  margin: 0 0 0 10px;
}
.gvdshock .endtime .title-end {
  display: block;
  text-transform: uppercase;
  font-weight: 500;
}
.gvdshock .endtime .countdown-timer {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 8px;
}
.gvdshock .endtime label {
  font-weight: 700;
  position: relative;
  background: #000000;
  border-radius: 8px;
  color: #ffffff;
  font-size: 25px;
  line-height: 24px;
  padding: 5px;
  margin: 0 8px;
  min-width: 34px;
  display: inline-block;
}
.gvdshock .endtime label::after {
  content: ":";
  position: absolute;
  right: -11px;
  top: 2px;
}
.gvdshock .endtime label:last-child::after {
  display: none;
}
.listing-timeline {
  flex: 1;
  display: inline-flex;
  text-align: center;
}
.listing-timeline a {
  font-size: 14px;
  line-height: 20px;
  color: #888888;
  display: inline-block;
  vertical-align: middle;
  width: 50%;
  padding: 20px 5px;
  position: relative;
  text-decoration: none;
}
.listing-timeline a.active {
  color: #ffffff;
}
.listing-timeline a.active::after {
  content: "";
  width: 40px;
  height: 4px;
  background: #ffffff;
  left: 0;
  right: 0;
  bottom: 10px;
  margin: auto;
  position: absolute;
}
.listing-timeline a span {
  display: block;
  font-size: 13px;
}
.listing-timeline a span.timeline {
  font-size: 20px;
  font-weight: 600;
  margin: 3px 0 0;
}
.slider-flashsale {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 0;
}
@media (max-width: 1024px) {
  .slider-flashsale { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 640px) {
  .slider-flashsale { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
.slider-flashsale .item {
  margin: 0 5px 10px;
  background: #323232;
  border-radius: 15px;
  padding: 0 5px 12px;
  display: block;
  text-decoration: none;
  position: relative;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.slider-flashsale .item:hover {
  transform: translateY(-3px);
  box-shadow: -4px -4px 8px rgba(134,134,134,.2), 4px 4px 8px rgba(28,28,28,.4);
}
.slider-flashsale .item-img {
  height: 195px;
  line-height: 195px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.slider-flashsale .item-img img {
  max-width: 165px;
  max-height: 165px;
  width: 100%;
  height: auto;
  object-fit: contain;
  transition: transform 0.25s ease;
}
.slider-flashsale .item:hover .item-img img {
  transform: scale(1.04);
}
.slider-flashsale .item h3 {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  font-size: 14px;
  font-weight: 400;
  line-height: 20px;
  text-align: center;
  color: #ffffff;
  overflow: hidden;
  padding: 0 10px;
  margin: 10px auto 5px;
  height: 40px;
}
.slider-flashsale .item strong.price {
  color: #ff9921;
  font-size: 18px;
  line-height: 23px;
  text-align: center;
  display: block;
  font-weight: 700;
}
.slider-flashsale .item .price-and-discount {
  display: block;
  text-align: center;
  margin-bottom: 10px;
}
.slider-flashsale .item .price-old {
  line-height: 18px;
  display: inline-block;
  vertical-align: middle;
  font-size: 14px;
  color: #c7c7c7;
  text-decoration-line: line-through;
}
.slider-flashsale .item small {
  color: #ffffff;
  background: #f70000;
  border-radius: 3px;
  padding: 1px 4px;
  display: inline-block;
  vertical-align: middle;
  margin-left: 5px;
  font-size: 13px;
  line-height: 18px;
}
.slider-flashsale .fs-contain {
  display: inline-block;
  position: relative;
  min-height: 20px;
  margin: 0 8px;
  width: calc(100% - 16px);
}
.slider-flashsale .fs-contain > img {
  position: absolute;
  left: -1px;
  top: -11px;
  width: 21px;
  z-index: 1;
}
.slider-flashsale .rq_count {
  background-color: #dddddd;
  border-radius: 25px;
  display: block;
  height: 17px;
  padding-left: 5px;
  position: relative;
  width: 100%;
  overflow: hidden;
}
.slider-flashsale .rq_count i.fs-iconfire {
  width: 0;
  background: linear-gradient(64.85deg, #fcb500 23.67%, #ffd41d 106.12%);
  border-radius: 25px 0 0 25px;
  height: 17px;
  left: 0;
  position: absolute;
  top: 0;
}
.slider-flashsale .rq_count b {
  color: #000000;
  display: block;
  font-size: 11px;
  font-weight: 500;
  line-height: 18px;
  margin: 0 auto 0 15px;
  position: relative;
  text-align: center;
  z-index: 1;
}

/* ========================================================================
   CHOOSE CATEGORY BAR CHUẨN TOPZONE.VN (ul.choose-cate)
   ======================================================================== */
.choose-cate {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 50px auto 80px;
  max-width: 1200px;
  padding: 0;
  list-style: none;
}
.choose-cate li {
  float: left;
  width: 15%;
  margin: 0 10px;
  border-radius: 12px;
  background: #323232;
  height: 220px;
  position: relative;
  transition: all 0.25s ease;
}
.choose-cate li:hover {
  box-shadow: -4px -4px 8px rgba(134,134,134,.2), 4px 4px 8px rgba(28,28,28,.4);
}
.choose-cate li a {
  display: block;
  overflow: hidden;
  width: 100%;
  height: 100%;
  text-decoration: none;
}
.choose-cate li .cate-pro {
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 25px auto 0;
  height: 125px;
}
.choose-cate li .cate-pro img {
  max-width: 145px;
  max-height: 115px;
  object-fit: contain;
  display: block;
  margin: auto;
  transition: transform 0.25s ease;
}
.choose-cate li:hover .cate-pro img {
  transform: scale(1.06);
}
.choose-cate li span {
  color: #ffffff;
  font-size: 15px;
  line-height: 20px;
  text-align: center;
  display: block;
  position: absolute;
  left: 0;
  right: 0;
  bottom: 20px;
  font-weight: 400;
}

/* ========================================================================
   KHỐI DANH MỤC SẢN PHẨM CHUẨN TOPZONE.VN (.box-slide / .logo-cate / .item)
   ======================================================================== */
.logo-cate {
  display: flex;
  justify-content: center;
  align-items: center;
  margin: 0 auto 30px;
  width: max-content;
  text-decoration: none;
}
.logo-cate .topzone-iconapple {
  margin-right: 7px;
}
.logo-cate .title-text {
  color: <?= $is_dark_mode ? '#ffffff' : '#0f172a' ?>;
  font-size: 36px;
  line-height: 62px;
  font-weight: 700;
  margin: 0;
}

/* Series selector tabs */
.tz-series-bar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.65rem;
  margin-bottom: 2rem;
  flex-wrap: wrap;
  padding: 0 1rem;
}
.tz-series-btn {
  padding: 0.5rem 1.15rem;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid transparent;
  background: <?= $is_dark_mode ? '#323232' : '#e2e8f0' ?>;
  color: <?= $is_dark_mode ? '#afb7bd' : '#475569' ?>;
  transition: all 0.2s ease;
}
.tz-series-btn:hover,
.tz-series-btn.active {
  background: <?= $is_dark_mode ? '#323232' : '#ffffff' ?>;
  border: 1px solid <?= $is_dark_mode ? '#ffffff' : '#0f172a' ?>;
  color: <?= $is_dark_mode ? '#ffffff' : '#0f172a' ?>;
  font-weight: 700;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
}

/* 4-Column Product Grid chuẩn TopZone (.slide-cate) */
.tz-product-grid {
  max-width: 1200px;
  margin: 0 auto 70px;
  padding: 0 10px;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 20px;
}
@media (max-width: 1024px) {
  .tz-product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 600px) {
  .tz-product-grid { grid-template-columns: 1fr; }
}

/* ========================================================================
   BỘ LỌC & TÌM KIẾM NÂNG CAO CHUẨN TOPZONE DARK GRAPHITE (.tz-advanced-search)
   ======================================================================== */
.tz-advanced-search {
  max-width: 1200px;
  margin: 0 auto 55px;
  padding: 24px 28px;
  border: 1px solid <?= $is_dark_mode ? 'rgba(255, 255, 255, 0.1)' : '#e2e8f0' ?>;
  border-radius: 24px;
  background: <?= $is_dark_mode ? 'linear-gradient(145deg, #323232 0%, #262629 100%)' : '#ffffff' ?>;
  box-shadow: <?= $is_dark_mode ? '-4px -4px 8px rgba(134,134,134,.12), 4px 8px 24px rgba(0,0,0,.45)' : '0 10px 30px rgba(15,23,42,0.06)' ?>;
  position: relative;
  overflow: hidden;
}
.tz-advanced-search::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, #ff3b30 0%, #ff9500 35%, #34c759 68%, #2997ff 100%);
}
.tz-advanced-search-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1.15rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid <?= $is_dark_mode ? 'rgba(255,255,255,0.08)' : '#f1f5f9' ?>;
  flex-wrap: wrap;
}
.tz-adv-title-box {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}
.tz-adv-icon-circle {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  background: <?= $is_dark_mode ? '#1d1d20' : '#eff6ff' ?>;
  border: 1px solid <?= $is_dark_mode ? '#3f3f46' : '#bfdbfe' ?>;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #2997ff;
  flex-shrink: 0;
}
.tz-advanced-search-title {
  color: <?= $is_dark_mode ? '#ffffff' : '#0f172a' ?>;
  font-size: 1.15rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.tz-advanced-search-subtitle {
  color: <?= $is_dark_mode ? '#a1a1aa' : '#64748b' ?>;
  font-size: 0.82rem;
  margin-top: 0.2rem;
}
.tz-advanced-search-result {
  background: <?= $is_dark_mode ? '#1d1d20' : '#f1f5f9' ?>;
  border: 1px solid <?= $is_dark_mode ? '#3f3f46' : '#cbd5e1' ?>;
  color: #2997ff;
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.4rem 0.9rem;
  border-radius: 999px;
}
.tz-adv-quick-tags {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
  margin-bottom: 1.15rem;
}
.tz-adv-tag {
  background: <?= $is_dark_mode ? '#222225' : '#f8fafc' ?>;
  border: 1px solid <?= $is_dark_mode ? '#3f3f46' : '#cbd5e1' ?>;
  color: <?= $is_dark_mode ? '#d4d4d8' : '#475569' ?>;
  border-radius: 999px;
  padding: 0.32rem 0.85rem;
  font-size: 0.76rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}
.tz-adv-tag:hover,
.tz-adv-tag.active {
  background: rgba(41, 151, 255, 0.16);
  border-color: #2997ff;
  color: #ffffff;
}
.tz-advanced-search-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.9rem;
}
.tz-advanced-search-field {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}
.tz-advanced-search-field label {
  color: <?= $is_dark_mode ? '#d4d4d8' : '#475569' ?>;
  font-size: 0.76rem;
  font-weight: 600;
  letter-spacing: 0.01em;
}
.tz-advanced-search-field input,
.tz-advanced-search-field select {
  width: 100%;
  height: 42px;
  padding: 0 0.85rem;
  border: 1px solid <?= $is_dark_mode ? '#44444a' : '#cbd5e1' ?>;
  border-radius: 12px;
  background: <?= $is_dark_mode ? '#1c1c1f' : '#f8fafc' ?>;
  color: <?= $is_dark_mode ? '#f5f5f7' : '#0f172a' ?>;
  font-size: 0.85rem;
  font-weight: 500;
  transition: all 0.2s ease;
  outline: none;
}
.tz-advanced-search-field input:focus,
.tz-advanced-search-field select:focus {
  border-color: #2997ff;
  box-shadow: 0 0 0 3px rgba(41, 151, 255, 0.22);
  background: <?= $is_dark_mode ? '#141417' : '#ffffff' ?>;
}
.tz-advanced-search-field select option {
  background: #1c1c1f;
  color: #ffffff;
}
.tz-advanced-search-check {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  color: <?= $is_dark_mode ? '#e4e4e7' : '#334155' ?>;
  font-size: 0.84rem;
  font-weight: 500;
  cursor: pointer;
  user-select: none;
}
.tz-advanced-search-check input[type="checkbox"] {
  width: 17px;
  height: 17px;
  accent-color: #2997ff;
  cursor: pointer;
}
.tz-advanced-search-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 1.2rem;
  padding-top: 1rem;
  border-top: 1px dashed <?= $is_dark_mode ? 'rgba(255,255,255,0.1)' : '#e2e8f0' ?>;
  flex-wrap: wrap;
}
.tz-advanced-search-buttons {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}
.tz-advanced-search-button,
.tz-adv-reset-btn {
  border: none;
  border-radius: 999px;
  padding: 0.55rem 1.35rem;
  background: linear-gradient(135deg, #2997ff 0%, #0071e3 100%);
  color: #ffffff;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  box-shadow: 0 4px 14px rgba(41, 151, 255, 0.35);
  transition: all 0.2s ease;
}
.tz-advanced-search-button:hover,
.tz-adv-reset-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(41, 151, 255, 0.5);
}
.tz-advanced-search-empty {
  display: none;
  max-width: 1200px;
  margin: -1.5rem auto 2.5rem;
  padding: 2rem;
  border-radius: 20px;
  background: <?= $is_dark_mode ? '#323232' : '#ffffff' ?>;
  border: 1px dashed <?= $is_dark_mode ? '#52525b' : '#cbd5e1' ?>;
  text-align: center;
  color: <?= $is_dark_mode ? '#e4e4e7' : '#475569' ?>;
}
@media (max-width: 900px) { .tz-advanced-search-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px) { .tz-advanced-search-grid { grid-template-columns: 1fr; } .tz-advanced-search-header, .tz-advanced-search-actions { align-items: flex-start; flex-direction: column; } }

/* ========================================================================
   GIỎ HÀNG & THANH TOÁN CHUYÊN NGHIỆP CHUẨN CDS APPLE STORE (.cds-pro-modal)
   ======================================================================== */
.cds-pro-dialog {
  background: <?= $is_dark_mode ? '#242427' : '#ffffff' ?> !important;
  color: <?= $is_dark_mode ? '#f5f5f7' : '#0f172a' ?> !important;
  border: 1px solid <?= $is_dark_mode ? '#3f3f46' : '#e2e8f0' ?> !important;
  border-radius: 24px !important;
  overflow: hidden;
  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.65) !important;
}
.cds-pro-header {
  background: <?= $is_dark_mode ? '#161618' : '#f8fafc' ?>;
  border-bottom: 1px solid <?= $is_dark_mode ? '#323236' : '#e2e8f0' ?>;
  padding: 1.15rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.cds-pro-close {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: <?= $is_dark_mode ? '#2f3033' : '#e2e8f0' ?>;
  color: <?= $is_dark_mode ? '#ffffff' : '#0f172a' ?>;
  border: none;
  font-size: 1.2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
}
.cds-pro-close:hover {
  background: #ef4444;
  color: #ffffff;
}
.cds-cart-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: 0.9rem 1rem;
  background: <?= $is_dark_mode ? '#1b1b1e' : '#f8fafc' ?>;
  border: 1px solid <?= $is_dark_mode ? '#323236' : '#e2e8f0' ?>;
  border-radius: 16px;
  margin-bottom: 0.75rem;
  transition: border-color 0.2s ease;
}
.cds-cart-item-row:hover {
  border-color: #2997ff;
}
.cds-cart-thumb {
  width: 64px;
  height: 64px;
  border-radius: 12px;
  background: <?= $is_dark_mode ? '#2a2a2e' : '#ffffff' ?>;
  padding: 6px;
  object-fit: contain;
  flex-shrink: 0;
}
.cds-qty-control {
  display: inline-flex;
  align-items: center;
  background: <?= $is_dark_mode ? '#2a2a2e' : '#e2e8f0' ?>;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid <?= $is_dark_mode ? '#3f3f46' : '#cbd5e1' ?>;
}
.cds-qty-btn {
  width: 28px;
  height: 28px;
  border: none;
  background: transparent;
  color: <?= $is_dark_mode ? '#ffffff' : '#0f172a' ?>;
  font-weight: 700;
  cursor: pointer;
  font-size: 0.95rem;
}
.cds-qty-btn:hover {
  background: rgba(41, 151, 255, 0.2);
  color: #2997ff;
}
.cds-qty-val {
  min-width: 28px;
  text-align: center;
  font-size: 0.85rem;
  font-weight: 700;
}
.cds-summary-box {
  background: <?= $is_dark_mode ? '#18181b' : '#f8fafc' ?>;
  border: 1px solid <?= $is_dark_mode ? '#323236' : '#e2e8f0' ?>;
  border-radius: 16px;
  padding: 1rem 1.25rem;
  margin-top: 1rem;
}
.cds-summary-line {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.86rem;
  margin-bottom: 0.5rem;
  color: <?= $is_dark_mode ? '#d4d4d8' : '#475569' ?>;
}
.cds-summary-total {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  border-top: 1px dashed <?= $is_dark_mode ? '#3f3f46' : '#cbd5e1' ?>;
  padding-top: 0.75rem;
  margin-top: 0.65rem;
  font-weight: 800;
  font-size: 1.05rem;
  color: <?= $is_dark_mode ? '#ffffff' : '#0f172a' ?>;
}
.cds-input {
  width: 100%;
  height: 42px;
  padding: 0 0.9rem;
  border-radius: 12px;
  border: 1px solid <?= $is_dark_mode ? '#3f3f46' : '#cbd5e1' ?>;
  background: <?= $is_dark_mode ? '#18181b' : '#f8fafc' ?>;
  color: <?= $is_dark_mode ? '#ffffff' : '#0f172a' ?>;
  font-size: 0.86rem;
  outline: none;
  transition: all 0.2s ease;
}
.cds-input:focus {
  border-color: #2997ff;
  box-shadow: 0 0 0 3px rgba(41, 151, 255, 0.2);
}
.cds-pay-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.65rem;
}
.cds-pay-option {
  border: 1px solid <?= $is_dark_mode ? '#3f3f46' : '#cbd5e1' ?>;
  background: <?= $is_dark_mode ? '#18181b' : '#f8fafc' ?>;
  border-radius: 12px;
  padding: 0.7rem 0.85rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.55rem;
  font-size: 0.83rem;
  font-weight: 600;
  color: <?= $is_dark_mode ? '#e4e4e7' : '#1e293b' ?>;
  transition: all 0.2s ease;
}
.cds-pay-option:hover,
.cds-pay-option:has(input:checked) {
  border-color: #2997ff;
  background: rgba(41, 151, 255, 0.12);
  color: <?= $is_dark_mode ? '#ffffff' : '#0f172a' ?>;
}
.cds-card-actions-row {
  display: flex;
  gap: 8px;
  align-items: center;
}
.tz-add-cart-btn {
  width: 42px;
  height: 40px;
  border-radius: 12px;
  border: 1px solid <?= $is_dark_mode ? '#4b4b52' : '#cbd5e1' ?>;
  background: <?= $is_dark_mode ? '#262629' : '#f1f5f9' ?>;
  color: <?= $is_dark_mode ? '#ffffff' : '#0f172a' ?>;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: all 0.2s ease;
}
.tz-add-cart-btn:hover {
  background: #2997ff;
  border-color: #2997ff;
  color: #ffffff;
}

.tz-product-card {
  background: <?= $is_dark_mode ? '#323232' : '#ffffff' ?>;
  border: <?= $is_dark_mode ? 'none' : '1px solid #e2e8f0' ?>;
  border-radius: 24px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: all 0.25s ease;
  position: relative;
  padding: 0 20px 26px;
}
.tz-product-card:hover {
  box-shadow: -4px -4px 8px rgba(134,134,134,.2), 4px 4px 8px rgba(28,28,28,.4);
  transform: translateY(-4px);
}
.tz-product-thumb {
  position: relative;
  margin: 35px 0 15px;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 245px;
}
.tz-product-thumb img {
  max-width: 235px;
  max-height: 235px;
  width: 100%;
  height: auto;
  object-fit: contain;
  transition: transform 0.3s ease;
}
.tz-product-card:hover .tz-product-thumb img {
  transform: scale(1.04);
}
.tz-badge-new {
  position: absolute;
  top: -23px;
  left: 0;
  width: 52px;
  height: 30px;
  color: #ff9f00;
  border: 1px solid #ff9f00;
  box-sizing: border-box;
  border-radius: 100px;
  font-size: 14px;
  line-height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: transparent;
  font-weight: 500;
}
.tz-wishlist-btn {
  position: absolute;
  top: -23px;
  right: 0;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #ffffff;
  transition: all 0.2s ease;
  font-size: 1rem;
}
.tz-wishlist-btn:hover,
.tz-wishlist-btn.active {
  background: #ffffff;
  color: #ef4444;
  transform: scale(1.15);
}
.tz-colors {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  margin: 0.4rem 0;
}
.tz-color-dot {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  cursor: pointer;
  border: 2px solid transparent;
  transition: all 0.15s ease;
  box-shadow: 0 0 2px rgba(0,0,0,0.5);
}
.tz-color-dot.active {
  border-color: #2997ff;
  transform: scale(1.25);
}
.tz-capacities {
  display: flex;
  gap: 0.35rem;
  margin: 0.5rem 0 0.35rem 0;
  flex-wrap: wrap;
  justify-content: center;
}
.tz-cap-btn {
  padding: 0.22rem 0.6rem;
  font-size: 0.73rem;
  font-weight: 600;
  border-radius: 8px;
  border: 1px solid <?= $is_dark_mode ? '#444448' : '#cbd5e1' ?>;
  background: <?= $is_dark_mode ? '#252528' : '#f1f5f9' ?>;
  color: <?= $is_dark_mode ? '#d4d4d8' : '#475569' ?>;
  cursor: pointer;
  transition: all 0.15s ease;
}
.tz-cap-btn.active {
  border-color: #2997ff;
  background: #2997ff;
  color: #ffffff;
}
.tz-card-body {
  padding: 0;
  display: flex;
  flex-direction: column;
  flex: 1;
  justify-content: space-between;
  text-align: center;
}
.tz-card-title {
  font-size: 15px;
  line-height: 20px;
  font-weight: 400;
  margin: 8px 0;
  text-align: center;
  color: <?= $is_dark_mode ? '#ffffff' : '#0f172a' ?>;
  min-height: 40px;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.tz-price-box {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}
.tz-price-main {
  font-size: 18px;
  line-height: 23px;
  font-weight: 700;
  color: <?= $is_dark_mode ? '#ffffff' : '#0f172a' ?>;
}
.tz-price-old {
  font-size: 15px;
  line-height: 20px;
  font-weight: 400;
  color: #757575;
  text-decoration: line-through;
}
.tz-discount-badge {
  font-size: 14px;
  line-height: 20px;
  color: <?= $is_dark_mode ? '#ffffff' : '#dc2626' ?>;
  font-weight: 400;
}
.tz-rating-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-bottom: 0.5rem;
  font-size: 0.76rem;
  padding: 4px 6px;
  border-radius: 6px;
  transition: all 0.2s ease;
  color: <?= $is_dark_mode ? '#a1a1aa' : '#64748b' ?>;
}
.tz-rating-row:hover {
  background: <?= $is_dark_mode ? 'rgba(250, 173, 20, 0.08)' : '#fffbe6' ?>;
}
.tz-stars {
  color: #faad14;
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
}
.tz-buy-btn {
  width: 100%;
  padding: 0.6rem;
  border-radius: 12px;
  font-size: 0.88rem;
  font-weight: 700;
  cursor: pointer;
  border: none;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  background: #0071e3;
  color: #ffffff;
}
.tz-buy-btn:hover {
  background: #0077ed;
  box-shadow: 0 4px 14px rgba(0, 113, 227, 0.45);
}
.tz-buy-btn.fast-pay {
  background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
}
.tz-buy-btn.fast-pay:hover {
  background: linear-gradient(135deg, #d97706 0%, #c2410c 100%);
  box-shadow: 0 4px 14px rgba(245, 158, 11, 0.45);
}

/* ========================================================================
   TEKZONE NEWS SECTION CHUẨN TOPZONE.VN (.slide-topnews)
   ======================================================================== */
.tz-tekzone-grid {
  max-width: 1200px;
  margin: 0 auto 80px;
  padding: 0 10px;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 20px;
}
@media (max-width: 900px) {
  .tz-tekzone-grid { grid-template-columns: 1fr; }
}
.tz-news-item {
  background: #323232;
  border-radius: 24px;
  overflow: hidden;
  display: block;
  text-decoration: none;
  transition: all 0.25s ease;
}
.tz-news-item:hover {
  box-shadow: -4px -4px 8px rgba(134,134,134,.2), 4px 4px 8px rgba(28,28,28,.4);
  transform: translateY(-4px);
}
.tz-news-item img {
  width: 100%;
  height: 224px;
  object-fit: cover;
  display: block;
}
.tz-news-item h3 {
  font-size: 16px;
  line-height: 22px;
  color: #ffffff;
  font-weight: 400;
  margin: 15px 20px 10px;
  height: 44px;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
.tz-news-item .timepost {
  display: block;
  font-size: 13px;
  color: #888888;
  margin: 0 20px 20px;
}

/* ========================================================================
   POLICY BAR & FOOTER CHUẨN TOPZONE.VN (.policy / footer)
   ======================================================================== */
.policy {
  width: 100%;
  background: #323232;
  padding: 25px 0;
  text-align: center;
}
.pr-policy {
  display: flex;
  justify-content: space-between;
  max-width: 1200px;
  margin: auto;
  padding: 0;
  list-style: none;
  flex-wrap: wrap;
}
.pr-policy li {
  width: 24%;
  text-align: center;
}
@media (max-width: 768px) {
  .pr-policy li { width: 48%; margin-bottom: 15px; }
}
.pr-policy li i {
  display: block;
  margin: 0 auto 15px;
}
.pr-policy li span {
  display: block;
  color: #ffffff;
  font-size: 15px;
  line-height: 20px;
}

.tz-footer {
  width: 100%;
  background: #101010;
  color: #e5e5e5;
  font-size: 13px;
  line-height: 1.7;
}
.tz-foot-container {
  max-width: 1200px;
  margin: auto;
  padding: 0 10px;
}
.logo-foot {
  padding: 30px 0;
  display: flex;
  align-items: center;
}
.logo-foot a {
  display: inline-block;
  text-decoration: none;
}
.logo-foot a:first-child .topzone-logo {
  margin-right: 10px;
  border-right: 1px solid #3b3b3b;
  padding-right: 10px;
}
.list-foot {
  display: flex;
  justify-content: space-between;
  align-items: stretch;
  border-bottom: 1px solid #333333;
  padding: 0 0 30px;
  flex-wrap: wrap;
  list-style: none;
  margin: 0;
}
.list-foot li {
  width: 17%;
}
.list-foot li:first-child {
  width: 27%;
}
@media (max-width: 900px) {
  .list-foot li, .list-foot li:first-child { width: 48%; margin-bottom: 20px; }
}
.list-foot li span.foot-head {
  display: block;
  color: #ffffff;
  font-size: 15px;
  line-height: 20px;
  padding: 0 0 8px;
  font-weight: 500;
}
.list-foot li a {
  display: block;
  font-size: 13px;
  line-height: 18px;
  color: #e5e5e5;
  padding: 0 0 10px;
  text-decoration: none;
  transition: color 0.2s ease;
}
.list-foot li a:hover {
  color: #2997ff;
}
.list-foot li a b {
  font-weight: 400;
  color: #2997ff;
}
.txtlink {
  color: #888888;
  font-size: 13px;
  margin: 12px 0 8px;
}
.social-link {
  display: flex;
  gap: 12px;
  align-items: center;
}
.social-link a {
  display: inline-block !important;
  padding: 0 !important;
}
.text-cpr {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 22px 0;
  flex-wrap: wrap;
  gap: 15px;
}
.text-cpr p {
  font-size: 11px;
  color: #666666;
  width: 60%;
  line-height: 18px;
  margin: 0;
}
.certify {
  display: flex;
  align-items: center;
  gap: 12px;
}
.certify img {
  height: 40px;
  width: auto;
}
.footer__logo {
  background: #222222;
  padding: 15px 15px 5px;
  text-align: center;
}
.footer__logo__hd {
  color: #888888;
  font-size: 12px;
  line-height: 14px;
  margin: 0 0 12px;
}
.footer__logo__list {
  font-size: 0;
  list-style: none;
  padding: 0;
  margin: 0;
}
.footer__logo__list li {
  display: inline-block;
  vertical-align: top;
  margin: 0 8px 10px;
}

/* Livechat */
.tz-livechat-widget {
  position: fixed;
  bottom: 24px;
  right: 32px;
  z-index: 999;
}
.tz-chat-btn {
  background: transparent;
  color: #ffffff;
  border: none;
  padding: 0;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  transition: transform 0.2s ease;
}
.tz-chat-btn:hover {
  transform: scale(1.06);
}

.tz-chat-popup {
  display: none;
  position: absolute;
  bottom: 60px;
  right: 0;
  width: 350px;
  height: 480px;
  background: <?= $is_dark_mode ? '#1c1c24' : '#ffffff' ?>;
  border: 1px solid <?= $is_dark_mode ? '#2d2d3a' : '#e2e8f0' ?>;
  border-radius: 20px;
  box-shadow: 0 20px 40px rgba(0,0,0,0.35);
  flex-direction: column;
  overflow: hidden;
}

/* ============================================================
   ĐỒNG BỘ GIAO DIỆN SÁNG / TỐI (LIGHT MODE vs DARK MODE) CHO CỜ giao_dien_toi
   ============================================================ */
.topzone-wrapper.light-mode {
  background: #f1f5f9 !important;
  color: #0f172a !important;
  border-color: #cbd5e1 !important;
}
.topzone-wrapper.light-mode .tz-navbar {
  background: #ffffff !important;
  border-bottom: 1px solid #e2e8f0 !important;
  box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
}
.topzone-wrapper.light-mode .tz-brand,
.topzone-wrapper.light-mode .tz-brand-logo {
  color: #0f172a !important;
}
.topzone-wrapper.light-mode .tz-brand svg {
  fill: #0f172a !important;
}
.topzone-wrapper.light-mode .tz-brand-divider {
  background: #cbd5e1 !important;
}
.topzone-wrapper.light-mode .tz-brand-sub span {
  color: #64748b !important;
}
.topzone-wrapper.light-mode .tz-nav-links a {
  color: #334155 !important;
}
.topzone-wrapper.light-mode .tz-nav-links a:hover {
  background: #f1f5f9 !important;
  color: #0f172a !important;
}
.topzone-wrapper.light-mode .tz-nav-links a.active {
  background: #eff6ff !important;
  color: #2563eb !important;
}
.topzone-wrapper.light-mode .tz-round-btn {
  background: #f1f5f9 !important;
  color: #0f172a !important;
  border: 1px solid #cbd5e1 !important;
}
.topzone-wrapper.light-mode .tz-round-btn:hover {
  background: #e2e8f0 !important;
}
.topzone-wrapper.light-mode .tz-flashsale-bar,
.topzone-wrapper.light-mode .tz-fs-card,
.topzone-wrapper.light-mode .tz-cate-item,
.topzone-wrapper.light-mode .tz-commitments {
  background: #ffffff !important;
  border: 1px solid #e2e8f0 !important;
  color: #0f172a !important;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
}
.topzone-wrapper.light-mode .tz-fs-timer,
.topzone-wrapper.light-mode .tz-fs-card div[style*="color: #fff"],
.topzone-wrapper.light-mode .tz-commit-item div[style*="color: #fff"],
.topzone-wrapper.light-mode #sec-audio h4 {
  color: #0f172a !important;
}
.topzone-wrapper.light-mode .tz-section-header h2 {
  color: #0f172a !important;
}
.topzone-wrapper.light-mode .tz-section-header svg {
  fill: #0f172a !important;
}
.topzone-wrapper.light-mode .tz-commit-icon {
  background: #f1f5f9 !important;
}
.topzone-wrapper.light-mode .tz-footer {
  background: #ffffff !important;
  border-top: 1px solid #e2e8f0 !important;
  color: #475569 !important;
}
.topzone-wrapper.light-mode .tz-foot-grid {
  border-bottom: 1px solid #e2e8f0 !important;
}
.topzone-wrapper.light-mode .tz-foot-col h4,
.topzone-wrapper.light-mode .tz-footer span[style*="color: #fff"] {
  color: #0f172a !important;
}
.topzone-wrapper.light-mode .tz-footer svg {
  fill: #0f172a !important;
}
.topzone-wrapper.light-mode .tz-foot-col a {
  color: #334155 !important;
}

.tz-chat-popup.show {
  display: flex;
}

/* ============================================================
   TESTING CONTROLLER CONSOLE (ANT DESIGN & APPLE DEV STYLE)
   ============================================================ */
.ff-console-panel {
  background: <?= $is_console_dark_mode ? '#181b24' : '#ffffff' ?>;
  border: 1px solid <?= $is_console_dark_mode ? 'rgba(56, 189, 248, 0.25)' : '#e2e8f0' ?>;
  border-radius: 18px;
  box-shadow: <?= $is_console_dark_mode ? '0 12px 36px rgba(0,0,0,0.5)' : '0 10px 30px -5px rgba(0, 0, 0, 0.07), 0 4px 12px rgba(0, 0, 0, 0.04)' ?>;
  margin-bottom: 2rem;
  overflow: hidden;
  transition: all 0.3s ease;
}

.ff-console-header {
  background: <?= $is_console_dark_mode ? 'linear-gradient(135deg, #0b1329 0%, #172554 100%)' : 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)' ?>;
  padding: 1.15rem 1.6rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
  color: #ffffff;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}

.ff-console-title-wrap {
  display: flex;
  align-items: center;
  gap: 0.9rem;
}

.ff-console-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: linear-gradient(135deg, #38bdf8 0%, #2563eb 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
  flex-shrink: 0;
}

.ff-console-title-text {
  font-size: 1.05rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: #ffffff;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.ff-console-subtitle {
  font-size: 0.78rem;
  color: rgba(255, 255, 255, 0.65);
  margin-top: 2px;
}

.ff-pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
  background: #10b981;
  box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
  animation: ffPulse 2s infinite;
}

@keyframes ffPulse {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
}

.ff-console-body {
  padding: 1.4rem 1.6rem;
  background: <?= $is_console_dark_mode ? '#141722' : '#f8fafc' ?>;
  border-bottom: 1px solid <?= $is_console_dark_mode ? '#232938' : '#e2e8f0' ?>;
}

.ff-form-grid {
  display: grid;
  grid-template-columns: 210px 1fr auto auto;
  gap: 1.25rem;
  align-items: flex-end;
}
@media (max-width: 992px) {
  .ff-form-grid {
    grid-template-columns: 1fr;
  }
}

.ff-field-group {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.ff-field-label {
  font-size: 0.8rem;
  font-weight: 700;
  color: <?= $is_console_dark_mode ? '#cbd5e1' : '#334155' ?>;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.ff-form-control {
  width: 100%;
  padding: 0.65rem 0.95rem;
  border-radius: 10px;
  border: 1px solid <?= $is_console_dark_mode ? '#334155' : '#cbd5e1' ?>;
  background: <?= $is_console_dark_mode ? '#1e2433' : '#ffffff' ?>;
  color: <?= $is_console_dark_mode ? '#f1f5f9' : '#0f172a' ?>;
  font-size: 0.88rem;
  font-weight: 500;
  transition: all 0.2s ease;
  box-shadow: 0 1px 2px rgba(0,0,0,0.03);
}

.ff-form-control:focus {
  outline: none;
  border-color: #38bdf8;
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.2);
}

.ff-btn-submit {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: linear-gradient(135deg, #0284c7 0%, #2563eb 100%);
  color: #ffffff;
  border: none;
  border-radius: 10px;
  padding: 0.68rem 1.35rem;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
  white-space: nowrap;
}

.ff-btn-submit:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35);
}

.ff-simulate-toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.6rem 0.85rem;
  border-radius: 10px;
  border: 1px dashed <?= $simulate_error ? '#ef4444' : ($is_console_dark_mode ? '#334155' : '#cbd5e1') ?>;
  background: <?= $simulate_error ? 'rgba(239, 68, 68, 0.08)' : ($is_console_dark_mode ? '#1a202c' : '#ffffff') ?>;
  cursor: pointer;
  font-size: 0.82rem;
  font-weight: 600;
  color: <?= $simulate_error ? '#ef4444' : ($is_console_dark_mode ? '#94a3b8' : '#64748b') ?>;
  transition: all 0.2s ease;
}

.ff-simulate-toggle:hover {
  border-color: #ef4444;
  color: #ef4444;
}

.ff-pills-row {
  margin-top: 1.15rem;
  padding-top: 1rem;
  border-top: 1px dashed <?= $is_console_dark_mode ? '#2d3748' : '#e2e8f0' ?>;
  display: flex;
  align-items: center;
  gap: 0.55rem;
  flex-wrap: wrap;
}

.ff-quick-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.38rem 0.8rem;
  border-radius: 20px;
  font-size: 0.78rem;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s ease;
  background: <?= $is_console_dark_mode ? '#1e2433' : '#ffffff' ?>;
  color: <?= $is_console_dark_mode ? '#94a3b8' : '#475569' ?>;
  border: 1px solid <?= $is_console_dark_mode ? '#334155' : '#e2e8f0' ?>;
}

.ff-quick-pill:hover {
  background: <?= $is_console_dark_mode ? '#2d3748' : '#f1f5f9' ?>;
  color: #0284c7;
  border-color: #38bdf8;
  transform: translateY(-1px);
}

.ff-quick-pill.active {
  background: linear-gradient(135deg, rgba(2, 132, 199, 0.15) 0%, rgba(37, 99, 235, 0.2) 100%);
  color: #0284c7;
  border-color: #0284c7;
  font-weight: 700;
  box-shadow: 0 2px 8px rgba(2, 132, 199, 0.25);
}

/* CARDS GRID */
.ff-cards-section {
  padding: 1.4rem 1.6rem;
  background: <?= $is_console_dark_mode ? '#12151f' : '#ffffff' ?>;
}

.ff-grid-10 {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(295px, 1fr));
  gap: 0.95rem;
}

.ff-card-item {
  background: <?= $is_console_dark_mode ? '#1a1f2d' : '#ffffff' ?>;
  border-radius: 12px;
  border: 1px solid <?= $is_console_dark_mode ? '#283246' : '#e2e8f0' ?>;
  padding: 0.95rem 1.05rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  gap: 0.55rem;
  position: relative;
  transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 0 2px 5px rgba(0,0,0,0.03);
}

.ff-card-item:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(0,0,0,0.08);
  border-color: #38bdf8;
}

.ff-card-item.enabled {
  border-left: 4px solid #10b981;
  background: <?= $is_console_dark_mode ? 'linear-gradient(135deg, #1a2233 0%, #162426 100%)' : 'linear-gradient(135deg, #ffffff 60%, #f0fdf4 100%)' ?>;
}

.ff-card-item.disabled {
  border-left: 4px solid #94a3b8;
  opacity: 0.88;
  background: <?= $is_console_dark_mode ? '#161924' : '#fafafa' ?>;
}

.ff-card-item.highlight {
  border-color: #38bdf8 !important;
  box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.35), 0 8px 20px rgba(0,0,0,0.1) !important;
}

.ff-badge-status {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 0.2rem 0.55rem;
  border-radius: 9999px;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.ff-badge-status.on {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.ff-badge-status.off {
  background: rgba(148, 163, 184, 0.15);
  color: #64748b;
  border: 1px solid rgba(148, 163, 184, 0.3);
}

.ff-card-key {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.8rem;
  font-weight: 700;
  color: #0284c7;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.ff-card-key:hover {
  color: #2563eb;
  text-decoration: underline;
}

.ff-card-desc {
  font-size: 0.85rem;
  font-weight: 600;
  color: <?= $is_console_dark_mode ? '#f1f5f9' : '#1e293b' ?>;
  line-height: 1.35;
}

.ff-card-meta {
  font-size: 0.72rem;
  color: <?= $is_console_dark_mode ? '#94a3b8' : '#64748b' ?>;
  background: <?= $is_console_dark_mode ? 'rgba(0,0,0,0.25)' : '#f1f5f9' ?>;
  padding: 0.35rem 0.6rem;
  border-radius: 6px;
  display: flex;
  align-items: flex-start;
  gap: 0.35rem;
  line-height: 1.35;
}

.ff-edit-link {
  font-size: 0.72rem;
  color: #64748b;
  text-decoration: none;
  padding: 2px 6px;
  border-radius: 4px;
  transition: all 0.15s ease;
  display: inline-flex;
  align-items: center;
  gap: 3px;
}
.ff-edit-link:hover {
  background: rgba(2, 132, 199, 0.1);
  color: #0284c7;
}

/* ========================================================================
   THANH MINI BAR GỌN NHẸ (FLOATING QUICK BAR) CHO TRANG WEB BÁN HÀNG ĐỘC LẬP
   ======================================================================== */
.cds-floating-devbar {
  position: fixed;
  bottom: 18px;
  left: 18px;
  z-index: 9990;
  display: flex;
  align-items: center;
  gap: 0.45rem;
  background: rgba(16, 16, 18, 0.92);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 999px;
  padding: 0.38rem 0.65rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.45);
  color: #f8fafc;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  font-size: 0.76rem;
  flex-wrap: wrap;
}
.cds-devbar-link {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  color: #e2e8f0;
  text-decoration: none;
  font-weight: 600;
  padding: 0.28rem 0.65rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.12);
  transition: all 0.18s ease;
}
.cds-devbar-link:hover {
  background: #2997ff;
  border-color: #2997ff;
  color: #ffffff;
}
.cds-devbar-select,
.cds-devbar-input {
  background: rgba(255, 255, 255, 0.1);
  color: #ffffff;
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 999px;
  padding: 0.24rem 0.6rem;
  font-size: 0.75rem;
  font-weight: 600;
  outline: none;
}
.cds-devbar-select option {
  background: #18181b;
  color: #ffffff;
}
.cds-devbar-input {
  width: 74px;
  text-align: center;
  font-family: ui-monospace, monospace;
}
.cds-devbar-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  background: linear-gradient(135deg, #0284c7 0%, #2563eb 100%);
  color: #ffffff;
  border: none;
  border-radius: 999px;
  padding: 0.28rem 0.7rem;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.18s ease;
}
.cds-devbar-btn:hover {
  filter: brightness(1.1);
}
.cds-console-drawer {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 9995;
  background: rgba(2, 6, 23, 0.68);
  backdrop-filter: blur(6px);
  padding: 2rem 1.25rem;
  overflow-y: auto;
}
.cds-console-drawer.open {
  display: block;
}
.cds-console-drawer-inner {
  max-width: 1220px;
  margin: 0 auto;
}
</style>

<!-- ========================================================================
     THANH CÔNG CỤ NHỎ GỌN (CHUYỂN NHANH KH / MÔI TRƯỜNG / VỀ QUẢN TRỊ)
     ======================================================================== -->
<?php
$enabled_count = 0;
foreach ($eval_results as $res) {
    if (!empty($res['data']['enabled'])) {
        $enabled_count++;
    }
}
$total_count = count($eval_results);
?>
<div class="cds-floating-devbar">
  <a href="dashboard.php" class="cds-devbar-link" title="Quay về Hệ thống Quản trị Feature Flags">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>
    <span>Quản trị</span>
  </a>

  <?php if ($can_manage_controller): ?>
    <form method="GET" action="demo-app.php" style="display: inline-flex; align-items: center; gap: 0.35rem; margin: 0;">
      <select name="env" class="cds-devbar-select" onchange="this.form.submit()" title="Chuyển nhanh môi trường">
        <option value="testing" <?= ($current_env === 'testing') ? 'selected' : '' ?>>Kiểm thử (Testing)</option>
        <option value="staging" <?= ($current_env === 'staging') ? 'selected' : '' ?>>Vận hành (Staging)</option>
      </select>

      <input type="text" name="user_id" value="<?= e($current_user_id) ?>" class="cds-devbar-input" list="sample_users_list" title="Nhập mã khách hàng (KH1 - KH1000) rồi nhấn Enter" placeholder="KH1">
      <?php if ($simulate_error): ?>
        <input type="hidden" name="simulate_error" value="1">
      <?php endif; ?>
      <button type="submit" class="cds-devbar-link" style="cursor: pointer;" title="Áp dụng mã khách hàng">Đổi KH</button>
    </form>

    <button type="button" class="cds-devbar-btn" onclick="document.getElementById('cdsConsoleDrawer').classList.toggle('open')" title="Mở bảng điều khiển kiểm thử 11 Cờ tính năng">
      <span>⚡ <?= $enabled_count ?>/<?= $total_count ?> cờ</span>
    </button>
  <?php else: ?>
    <span style="padding: 0 0.4rem; color: #94a3b8;">
      Môi trường: <strong style="color: #38bdf8;"><?= strtoupper(e($current_env)) ?></strong> • ID: <strong style="color: #fff;"><?= e($current_user_id) ?></strong>
    </span>
  <?php endif; ?>
</div>

<!-- ========================================================================
     BẢNG ĐIỀU KHIỂN THỬ NGHIỆM FEATURE FLAG (POPUP DRAWER KHI CẦN MỞ)
     (Chỉ hiển thị và cho phép chỉnh sửa với Admin và Biên tập / Editor)
     ======================================================================== -->
<?php if ($can_manage_controller): ?>
<?php
$quick_users = [
    'KH1' => ['label' => 'KH1', 'desc' => 'Nội bộ • Fast Checkout BẬT'],
    'KH2' => ['label' => 'KH2', 'desc' => 'Nội bộ • Fast Checkout TẮT'],
    'KH5' => ['label' => 'KH5', 'desc' => 'Nội bộ • Dark Mode BẬT'],
    'KH60' => ['label' => 'KH60', 'desc' => 'Khách Beta (Rollout %)'],
    'KH500' => ['label' => 'KH500', 'desc' => 'Khách Phổ thông'],
];
$auto_open_drawer = !empty($highlight_flag) || !empty($_GET['open_console']);
?>
<div class="cds-console-drawer <?= $auto_open_drawer ? 'open' : '' ?>" id="cdsConsoleDrawer" onclick="if(event.target===this)this.classList.remove('open')">
<div class="cds-console-drawer-inner">
<div class="ff-console-panel" style="margin-bottom: 0;">
  <!-- CONSOLE HEADER (ANT DESIGN / APPLE DEV TECH GRADIENT) -->
  <div class="ff-console-header">
    <div class="ff-console-title-wrap">
      <div class="ff-console-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
          <line x1="8" y1="21" x2="16" y2="21"></line>
          <line x1="12" y1="17" x2="12" y2="21"></line>
        </svg>
      </div>
      <div>
        <div class="ff-console-title-text">
          <span>Bảng điều khiển kiểm thử Feature Flags (Testing Controller)</span>
          <span style="font-size: 0.72rem; padding: 0.15rem 0.55rem; border-radius: 6px; background: rgba(56, 189, 248, 0.2); color: #38bdf8; font-weight: 700; border: 1px solid rgba(56, 189, 248, 0.35);">DEV CONSOLE</span>
        </div>
        <div class="ff-console-subtitle">
          Mô phỏng ngữ cảnh người dùng thực tế và kiểm thử quy tắc đánh giá Rules, Overrides, Rollout
        </div>
      </div>
    </div>

    <div style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
      <?php if ($simulate_error): ?>
        <span style="background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 20px; font-weight: 700; padding: 4px 12px; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 6px;">
          <span></span> ĐANG MÔ PHỎNG LỖI API (FALLBACK FALSE)
        </span>
      <?php else: ?>
        <span style="background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 20px; font-weight: 600; padding: 4px 12px; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 6px;">
          <span class="ff-pulse-dot"></span> ĐỘNG CƠ EVAL SẴN SÀNG
        </span>
      <?php endif; ?>
      <span style="background: rgba(255, 255, 255, 0.1); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 20px; font-weight: 700; padding: 4px 12px; font-size: 0.78rem; font-family: ui-monospace, monospace;">
        <span style="color: #38bdf8;"><?= $enabled_count ?></span>/<?= $total_count ?> CỜ BẬT
      </span>
      <button type="button" onclick="document.getElementById('cdsConsoleDrawer').classList.remove('open')" style="background: rgba(255,255,255,0.12); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; padding: 4px 10px; font-size: 0.78rem; font-weight: 700; cursor: pointer;">
        ✕ Đóng
      </button>
    </div>
  </div>

  <!-- CONSOLE INTERACTIVE CONTROLS -->
  <div class="ff-console-body">
    <form method="GET" action="demo-app.php" class="ff-form-grid">
      <!-- 1. Môi trường -->
      <div class="ff-field-group">
        <label class="ff-field-label">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
          <span>1. Môi trường:</span>
        </label>
        <select name="env" class="ff-form-control">
          <option value="testing" <?= ($current_env === 'testing') ? 'selected' : '' ?>> Kiểm thử (Testing)</option>
          <option value="staging" <?= ($current_env === 'staging') ? 'selected' : '' ?>> Vận hành (Staging)</option>
        </select>
      </div>

      <!-- 2. Mã khách hàng -->
      <div class="ff-field-group">
        <label class="ff-field-label">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          <span>2. Mã khách hàng (User ID):</span>
        </label>
        <div style="position: relative;">
          <input type="text" name="user_id" class="ff-form-control font-mono" value="<?= e($current_user_id) ?>" placeholder="Nhập KH1, KH2, KH60, KH500..." list="sample_users_list" style="padding-left: 2.2rem;">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); pointer-events: none;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        </div>
        <datalist id="sample_users_list">
          <option value="KH1">KH1 - Nội bộ (Override: thanh toán nhanh = BẬT)</option>
          <option value="KH2">KH2 - Nội bộ (Override: thanh toán nhanh = TẮT)</option>
          <option value="KH5">KH5 - Nội bộ (Override: giao diện tối = BẬT)</option>
          <option value="KH60">KH60 - Khách hàng Beta (Rollout theo tỷ lệ)</option>
          <option value="KH500">KH500 - Khách hàng Thường (Phổ thông)</option>
        </datalist>
      </div>

      <!-- 3. Mô phỏng lỗi API Fallback -->
      <div style="padding-bottom: 0.15rem;">
        <label class="ff-simulate-toggle" title="Kích hoạt chế độ mô phỏng mất kết nối tới API để kiểm tra cơ chế dự phòng an toàn (Fail-safe Fallback)">
          <input type="checkbox" name="simulate_error" value="1" <?= $simulate_error ? 'checked' : '' ?> style="accent-color: #ef4444; width: 16px; height: 16px; cursor: pointer;">
          <span>Mô phỏng máy chủ lỗi (Fallback: false)</span>
        </label>
      </div>

      <!-- Actions -->
      <div style="display: flex; gap: 0.5rem; align-items: center; padding-bottom: 0.15rem;">
        <button type="submit" class="ff-btn-submit">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
          <span>Áp dụng & Đánh giá</span>
        </button>

        <?php if ($simulate_error): ?>
          <a href="demo-app.php?env=<?= urlencode($current_env) ?>&user_id=<?= urlencode($current_user_id) ?>" class="ff-quick-pill" style="border-color: #ef4444; color: #ef4444;" title="Bỏ chọn mô phỏng lỗi">
            Tắt lỗi
          </a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Quick User Pills -->
    <div class="ff-pills-row">
      <span style="font-size: 0.78rem; font-weight: 700; color: <?= $is_console_dark_mode ? '#94a3b8' : '#64748b' ?>; display: inline-flex; align-items: center; gap: 4px;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        Chuyển nhanh khách hàng mẫu:
      </span>
      <?php foreach ($quick_users as $qu_id => $qu_info): 
        $is_active_qu = ($current_user_id === $qu_id);
      ?>
        <a href="demo-app.php?env=<?= urlencode($current_env) ?>&user_id=<?= urlencode($qu_id) ?><?= $simulate_error ? '&simulate_error=1' : '' ?>" class="ff-quick-pill <?= $is_active_qu ? 'active' : '' ?>">
          <?php if ($is_active_qu): ?>
            <span style="color: #0284c7; font-size: 10px;">✓</span>
          <?php endif; ?>
          <strong><?= e($qu_info['label']) ?></strong>
          <span style="font-weight: 400; opacity: 0.85;">(<?= e($qu_info['desc']) ?>)</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- 10 FEATURE FLAGS STATUS MATRIX (ANT DESIGN CARDS GRID) -->
  <div class="ff-cards-section">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
      <div style="font-size: 0.82rem; font-weight: 700; color: <?= $is_console_dark_mode ? '#cbd5e1' : '#475569' ?>; text-transform: uppercase; letter-spacing: 0.03em; display: flex; align-items: center; gap: 0.5rem;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
        <span>Kết quả đánh giá <?= $total_count ?> cờ tính năng cho [ <strong style="color: #0284c7;"><?= e($current_user_id) ?></strong> &bull; <strong style="color: #2563eb;"><?= strtoupper(e($current_env)) ?></strong> ]:</span>
      </div>
      <div style="font-size: 0.78rem; color: #94a3b8; display: flex; align-items: center; gap: 4px;">
        <span>Nhấp vào mã cờ để xem chi tiết hoặc</span>
        <a href="flags.php" style="color: #0284c7; text-decoration: none; font-weight: 600;">quản lý tất cả cờ &rarr;</a>
      </div>
    </div>

    <div class="ff-grid-10">
      <?php 
      $flag_ui_labels = [
          'thanh_toan_nhanh'      => ['title' => 'Nút Mua 1-Chạm CDS STORE Express', 'icon' => ''],
          'giao_dien_toi'         => ['title' => 'Giao diện than chì CDS STORE Dark Mode', 'icon' => ''],
          'danh_gia_san_pham'     => ['title' => 'Đánh giá sao & ảnh thực tế unbox', 'icon' => ''],
          'danh_sach_yeu_thich'   => ['title' => 'Lưu sản phẩm yêu thích (Wishlist)', 'icon' => ''],
          'ho_tro_truc_tuyen'     => ['title' => 'Chuyên viên Apple CDS STORE 24/7', 'icon' => ''],
          'ma_giam_gia'           => ['title' => 'Tự động áp voucher CDS18', 'icon' => ''],
          'goi_y_san_pham'        => ['title' => 'Gợi ý combo phụ kiện Apple bằng AI', 'icon' => ''],
          'tim_kiem_nang_cao'     => ['title' => 'Tìm kiếm nâng cao đa tiêu chí', 'icon' => ''],
          'theo_doi_don_hang'     => ['title' => 'Tra cứu đơn hàng thời gian thực', 'icon' => ''],
          'tich_diem_thanh_vien'  => ['title' => 'Tích điểm hội viên CDS STORE Member', 'icon' => ''],
          'banner_quang_cao'      => ['title' => 'Banner quảng cáo trang chủ (Hero Slider)', 'icon' => ''],
      ];

      foreach ($eval_results as $f_key => $res): 
          $en = !empty($res['data']['enabled']);
          $reason = $res['data']['reason'] ?? '';
          $flag_info = $flag_ui_labels[$f_key] ?? ['title' => $f_key, 'icon' => ''];
          $is_highlight = ($highlight_flag === $f_key);
      ?>
        <div class="ff-card-item <?= $en ? 'enabled' : 'disabled' ?> <?= $is_highlight ? 'highlight' : '' ?>">
          <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
            <a href="flag-detail.php?key=<?= urlencode($f_key) ?>" class="ff-card-key" title="Xem chi tiết cờ <?= e($f_key) ?>">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
              <span><?= e($f_key) ?></span>
            </a>
            <div style="display: flex; align-items: center; gap: 0.35rem;">
              <button
                type="button"
                class="ff-badge-status <?= $en ? 'on' : 'off' ?>"
                style="cursor: pointer; border: none;"
                title="Bấm để Bật / Tắt nhanh cờ <?= e($f_key) ?> và đồng bộ vào CSDL MySQL"
                onclick="quickToggleDemoFlag('<?= e($f_key) ?>', '<?= e($current_env) ?>', <?= $en ? 'false' : 'true' ?>)"
              >
                <span><?= $en ? '●' : '○' ?></span>
                <?= $en ? 'ENABLED' : 'DISABLED' ?>
              </button>
              <a href="flag-detail.php?key=<?= urlencode($f_key) ?>" class="ff-edit-link" title="Chỉnh sửa cấu hình cờ">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
              </a>
            </div>
          </div>

          <div class="ff-card-desc">
            <span style="margin-right: 3px;"><?= $flag_info['icon'] ?></span>
            <?= e($flag_info['title']) ?>
          </div>

          <div class="ff-card-meta" title="<?= e($reason) ?>">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
              <?= e($reason) ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</div>
</div>
<?php endif; ?>

<!-- ========================================================================
     GIAO DIỆN CDS STORE STOREFRONT CHÍNH THỨC (AAR)
     ======================================================================== -->
<div class="topzone-wrapper <?= $is_dark_mode ? 'dark-mode' : 'light-mode' ?>">

  <!-- CDS HEADER NAVIGATION CHUẨN ĐEN #101010 -->
  <header class="tz-header-wrap">
    <nav class="tz-navbar">
      <!-- Logo CDS + APR Chính hãng -->
      <div class="logo-topzone">
        <a href="demo-app.php?env=<?= urlencode($current_env) ?>&user_id=<?= urlencode($current_user_id) ?>" class="cds-logo-brand" title="CDS - Đại lý ủy quyền chính thức của Apple">
          <span>CD</span><span class="cds-char-s">S</span>
        </a>
        <a href="#sec-policy" title="Apple Premium Reseller (APR)">
          <i class="topzone-autho"></i>
        </a>
      </div>

      <!-- Menu 8 Danh mục chuẩn Apple APR -->
      <ul class="tz-nav-links">
        <li><a href="#sec-iphone">iPhone</a></li>
        <li><a href="#sec-mac">Mac</a></li>
        <li><a href="#sec-ipad">iPad</a></li>
        <li><a href="#sec-watch">Watch</a></li>
        <li><a href="#sec-audio">Tai nghe, Loa</a></li>
        <li><a href="#sec-accessories">Phụ kiện</a></li>
        <li><a href="#sec-tekzone">TekZone</a></li>
        <li><a href="#sec-policy">CDSCare</a></li>
      </ul>

      <!-- Header Tools (Tích hợp các Cờ tính năng + Nút tròn #2f3033) -->
      <div class="tz-header-tools">
        
        <!-- CỜ 9: THEO DÕI ĐƠN HÀNG THỜI GIAN THỰC (theo_doi_don_hang) -->
        <?php if ($is_tracking): ?>
          <button type="button" class="tz-round-btn" onclick="openModal('trackingModal')" title="Cờ [theo_doi_don_hang]: Tra cứu đơn hàng thời gian thực">
            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#2997ff" stroke-width="2.2">
              <rect x="1" y="3" width="15" height="13"></rect>
              <polygon points="16 8 20 8 23 11 23 16 16 8"></polygon>
              <circle cx="5.5" cy="18.5" r="2.5"></circle>
              <circle cx="18.5" cy="18.5" r="2.5"></circle>
            </svg>
          </button>
        <?php endif; ?>

        <!-- CỜ 10: TÍCH ĐIỂM HỘI VIÊN MEMBER (tich_diem_thanh_vien) -->
        <?php if ($is_loyalty): ?>
          <button type="button" class="tz-round-btn" style="width: auto; border-radius: 999px; padding: 0 10px; gap: 4px; color: #f59e0b; font-size: 12px; font-weight: 700;" onclick="openModal('loyaltyModal')" title="Cờ [tich_diem_thanh_vien]: Điểm hội viên CDS">
            <span>👑</span>
            <span>2.450đ</span>
          </button>
        <?php endif; ?>

        <!-- CỜ 4: DANH SÁCH YÊU THÍCH (danh_sach_yeu_thich) -->
        <?php if ($is_wishlist): ?>
          <button type="button" class="tz-round-btn" onclick="openModal('wishlistModal')" title="Xem sản phẩm yêu thích (Wishlist)">
            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.2">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
            </svg>
            <span class="tz-cart-badge" id="wishlistCountBadge" style="display: none; background: #ef4444;">0</span>
          </button>
        <?php endif; ?>

        <!-- Nút Tìm kiếm tròn -->
        <button type="button" class="tz-round-btn" onclick="openSearchModal()" title="Tìm kiếm sản phẩm">
          <i class="topzone-search"></i>
        </button>

        <!-- Nút Giỏ hàng tròn -->
        <button type="button" class="tz-round-btn" onclick="openCartView()" title="Giỏ hàng của bạn">
          <i class="topzone-cart"></i>
          <span class="tz-cart-badge" id="cartBadgeCount">2</span>
        </button>

        <!-- Nút chuyển đổi Dark / Light Mode (Cờ giao_dien_toi) -->
        <button type="button" class="tz-round-btn" onclick="quickToggleDemoFlag('giao_dien_toi', '<?= e($current_env) ?>', <?= $is_dark_mode ? 'false' : 'true' ?>)" title="Cờ [giao_dien_toi]: Đang ở chế độ <?= $is_dark_mode ? 'Tối (Dark Mode)' : 'Sáng (Light Mode)' ?> - Bấm để chuyển đổi">
          <span style="font-size: 0.9rem;"><?= $is_dark_mode ? '🌙' : '☀️' ?></span>
        </button>

        <?php if ($current_customer): ?>
          <div class="tz-account-menu">
            <button type="button" class="tz-account-trigger" id="storeAccountBtn" aria-expanded="false">
              <span class="tz-account-avatar"><?= e(strtoupper(substr((string)($current_customer['username'] ?? 'KH'), 0, 2))) ?></span>
              <span class="tz-account-name"><?= e($current_customer['username'] ?? 'Tài khoản') ?></span>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="tz-account-dropdown" id="storeAccountDropdown">
              <div class="tz-account-dropdown-header">
                <div style="font-size: .85rem; font-weight: 700; color: #fff;"><?= e($current_customer['username'] ?? '') ?></div>
              </div>
              <a class="tz-account-dropdown-item text-danger" href="store-logout.php">Đăng xuất tài khoản</a>
            </div>
          </div>
        <?php else: ?>
          <a class="tz-account-link" href="store-auth.php?mode=login&theme=<?= $is_dark_mode ? 'dark' : 'light' ?>" title="Đăng nhập hoặc đăng ký tài khoản">
            <span>👤</span>
            <span>Đăng nhập</span>
          </a>
        <?php endif; ?>
      </div>
    </nav>
  </header>

  <!-- CỜ 6: TỰ ĐỘNG GỢI Ý MÃ GIẢM GIÁ (ma_giam_gia) -->
  <?php if ($is_coupon): ?>
    <div style="background: linear-gradient(90deg, #1d1d1f 0%, #2d2d30 50%, #1d1d1f 100%); border-bottom: 1px solid #3b3b3b; color: #ffffff; padding: 0.45rem 1.5rem; display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 0.85rem; font-size: 0.82rem;">
      <span>
        🎁 <strong>Ưu đãi CDS độc quyền:</strong> Nhập mã <code style="background: rgba(255,159,0,0.2); border: 1px solid #ff9f00; padding: 0.1rem 0.45rem; border-radius: 4px; font-weight: 800; color: #ff9f00;">CDS18</code> giảm thêm <strong>1.000.000đ</strong> khi đặt mua trực tuyến hôm nay (Hotline: <strong>0362481351</strong>)!
      </span>
      <button type="button" class="btn btn-sm" style="background: #2997ff; color: #ffffff; font-weight: 700; border-radius: 9999px; padding: 2px 12px; font-size: 0.76rem; border: none; cursor: pointer;" onclick="copyCouponCode('CDS18')">
        Sao chép mã
      </button>
    </div>
  <?php endif; ?>

  <!-- ========================================================================
       CỜ 11: BANNER QUẢNG CÁO TRANG CHỦ FULL-WIDTH (banner_quang_cao)
       CHUẨN TOPZONE.VN (.slide-home)
       ======================================================================== -->
  <?php if ($is_banner): ?>
  <div class="tz-slider-container" id="tzHeroBanner">
    <div class="tz-slides" id="tzSlides">
      <?php foreach ($slider_banners as $idx => $banner): ?>
        <div class="tz-slide-item">
          <a href="<?= e($banner['link']) ?>">
            <img src="<?= e($banner['img']) ?>" alt="<?= e($banner['title']) ?>">
          </a>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Prev & Next arrows chuẩn TopZone -->
    <button type="button" class="tz-slider-btn tz-slider-prev" onclick="prevSlide()" aria-label="Slide trước">&#10094;</button>
    <button type="button" class="tz-slider-btn tz-slider-next" onclick="nextSlide()" aria-label="Slide tiếp">&#10095;</button>

    <!-- Pagination Dots -->
    <div class="tz-slider-dots" id="tzSliderDots">
      <?php foreach ($slider_banners as $idx => $banner): ?>
        <div class="tz-slider-dot <?= $idx === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $idx ?>)"></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ========================================================================
       KHỐI FLASH SALE CHUẨN TOPZONE.VN (.flashsale-block - 6 CỘT)
       ======================================================================== -->
  <div class="flashsale-block">
    <div class="flex-fs">
      <div class="gvdshock">
        <h3>
          <img width="415" height="71" src="assets/images/products/flashsale-icon.png" alt="icon flashsale">
        </h3>
        <div class="endtime">
          <span class="title-end">KẾT THÚC TRONG</span>
          <span class="countdown-timer">
            <label id="fsHour">01</label>
            <label id="fsMinute">35</label>
            <label id="fsSecond">05</label>
          </span>
        </div>
      </div>
      <div class="listing-timeline">
        <a href="javascript:;" class="active">
          <span>Đang diễn ra</span>
          <span class="timeline">08:00 - 23:59</span>
        </a>
        <a href="javascript:;" onclick="showToast('Khung giờ Flash Sale ngày mai (08:00 - 23:59) sẽ mở bán sớm!', 'info')">
          <span>Ngày mai</span>
          <span class="timeline">08:00 - 23:59</span>
        </a>
      </div>
    </div>

    <div class="slider-flashsale">
      <?php foreach ($flashsale_items as $fs): ?>
        <?php if ($is_fast_pay): ?>
          <a href="javascript:;" class="item" onclick="openFastCheckout('<?= e($fs['id']) ?>', '<?= e($fs['name']) ?>', '<?= e($fs['price']) ?>', '<?= e($fs['img']) ?>')">
        <?php else: ?>
          <a href="javascript:;" class="item" onclick="openStandardCheckout('<?= e($fs['id']) ?>', '<?= e($fs['name']) ?>', '<?= e($fs['price']) ?>', '<?= e($fs['img']) ?>')">
        <?php endif; ?>
            <div class="item-img">
              <img src="<?= e($fs['img']) ?>" alt="<?= e($fs['name']) ?>" loading="lazy">
            </div>
            <h3><?= e($fs['name']) ?></h3>
            <div class="box-p">
              <strong class="price"><?= e($fs['price']) ?></strong>
              <div class="price-and-discount">
                <label class="price-old"><?= e($fs['old_price']) ?></label>
                <small><?= e($fs['discount']) ?></small>
              </div>
            </div>
            <div class="fs-contain">
              <img width="15" height="20" src="assets/images/products/fs-iconfire.png" alt="icon flashsale">
              <span class="rq_count fscount">
                <i style="width: <?= (int)$fs['percent'] ?>%;" class="fs-iconfire"></i>
                <b><?= e($fs['sold']) ?></b>
              </span>
            </div>
          </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ========================================================================
       CHOOSE CATEGORY ICONS BAR CHUẨN TOPZONE.VN (ul.choose-cate)
       ======================================================================== -->
  <ul class="choose-cate">
    <?php foreach ($quick_categories as $qc): ?>
      <li>
        <a href="#<?= e($qc['target']) ?>">
          <div class="cate-pro">
            <img src="<?= e($qc['img']) ?>" alt="<?= e($qc['name']) ?>">
          </div>
          <span><?= e($qc['name']) ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($is_adv_filter): ?>
    <section class="tz-advanced-search" id="sec-advanced-search" aria-labelledby="advancedSearchTitle">
      <div class="tz-advanced-search-header">
        <div class="tz-adv-title-box">
          <div class="tz-adv-icon-circle">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/><line x1="11" y1="8" x2="11" y2="14"/></svg>
          </div>
          <div>
            <div class="tz-advanced-search-title" id="advancedSearchTitle">
              <span>Tìm Kiếm & Bộ Lọc Thông Minh CDS</span>
              <span style="font-size: 0.72rem; padding: 3px 9px; border-radius: 999px; background: rgba(41,151,255,0.16); color: #2997ff; border: 1px solid rgba(41,151,255,0.35); font-weight: 700;">Cờ: tim_kiem_nang_cao</span>
            </div>
            <div class="tz-advanced-search-subtitle">Kết hợp từ khóa, dòng máy, dung lượng, khoảng giá & đánh giá sao chuẩn hệ sinh thái Apple CDS</div>
          </div>
        </div>
        <span class="tz-advanced-search-result" id="advancedSearchResult">Hiển thị toàn bộ sản phẩm</span>
      </div>

      <!-- Gợi ý lọc nhanh 1 chạm -->
      <div class="tz-adv-quick-tags" id="advQuickTags">
        <span class="tz-adv-quick-label">⚡ Lọc nhanh:</span>
        <button type="button" class="tz-adv-tag active" onclick="applyQuickTagFilter('all', this)">Tất cả Apple</button>
        <button type="button" class="tz-adv-tag" onclick="applyQuickTagFilter('new', this)">🔥 Mới Ra Mắt 2026</button>
        <button type="button" class="tz-adv-tag" onclick="applyQuickTagFilter('under15', this)">💎 Dưới 15 Triệu</button>
        <button type="button" class="tz-adv-tag" onclick="applyQuickTagFilter('15to30', this)">⚡ Từ 15 - 30 Triệu</button>
        <button type="button" class="tz-adv-tag" onclick="applyQuickTagFilter('over30', this)">👑 Flagship Trên 30 Triệu</button>
        <button type="button" class="tz-adv-tag" onclick="applyQuickTagFilter('rate5', this)">⭐ Đánh giá 5.0 Sao</button>
      </div>

      <div class="tz-advanced-search-grid">
        <div class="tz-advanced-search-field">
          <label for="advancedSearchKeyword">Từ khóa / Model / Chip</label>
          <input type="search" id="advancedSearchKeyword" placeholder="VD: iPhone 18 Pro Max, M4, Ultra...">
        </div>
        <div class="tz-advanced-search-field">
          <label for="advancedSearchCategory">Danh mục Apple</label>
          <select id="advancedSearchCategory">
            <option value="all">Tất cả danh mục (6 nhóm)</option>
            <option value="iphone">iPhone</option>
            <option value="mac">Mac (MacBook / iMac / Mac mini)</option>
            <option value="ipad">iPad</option>
            <option value="watch">Apple Watch</option>
            <option value="audio">Tai nghe, Loa AirPods</option>
            <option value="accessories">Phụ kiện Apple chính hãng</option>
          </select>
        </div>
        <div class="tz-advanced-search-field">
          <label for="advancedSearchSeries">Phân khúc / Dòng máy</label>
          <select id="advancedSearchSeries">
            <option value="all">Tất cả dòng máy</option>
          </select>
        </div>
        <div class="tz-advanced-search-field">
          <label for="advancedSearchCapacity">Dung lượng bộ nhớ</label>
          <select id="advancedSearchCapacity">
            <option value="all">Tất cả dung lượng</option>
            <option value="128GB">128GB</option>
            <option value="256GB">256GB</option>
            <option value="512GB">512GB</option>
            <option value="1TB">1TB</option>
            <option value="2TB">2TB</option>
          </select>
        </div>
        <div class="tz-advanced-search-field">
          <label for="advancedSearchMinPrice">Mức giá từ (VNĐ)</label>
          <input type="number" id="advancedSearchMinPrice" min="0" step="500000" placeholder="VD: 10000000">
        </div>
        <div class="tz-advanced-search-field">
          <label for="advancedSearchMaxPrice">Mức giá đến (VNĐ)</label>
          <input type="number" id="advancedSearchMaxPrice" min="0" step="500000" placeholder="VD: 45000000">
        </div>
        <div class="tz-advanced-search-field">
          <label for="advancedSearchRating">Đánh giá khách hàng</label>
          <select id="advancedSearchRating">
            <option value="0">Mọi mức đánh giá</option>
            <option value="4.5">Từ 4.5 ★ trở lên</option>
            <option value="4.8">Từ 4.8 ★ trở lên</option>
            <option value="5">Tuyệt đối 5.0 ★</option>
          </select>
        </div>
        <div class="tz-advanced-search-field">
          <label for="advancedSearchSort">Thứ tự hiển thị</label>
          <select id="advancedSearchSort">
            <option value="default">Mặc định đề xuất CDS</option>
            <option value="price-asc">Giá tăng dần (Thấp → Cao)</option>
            <option value="price-desc">Giá giảm dần (Cao → Thấp)</option>
            <option value="rating-desc">Đánh giá cao nhất</option>
            <option value="name-asc">Tên sản phẩm (A → Z)</option>
          </select>
        </div>
      </div>
      <div class="tz-advanced-search-actions">
        <label class="tz-advanced-search-check">
          <input type="checkbox" id="advancedSearchNewOnly" onchange="applyAdvancedSearch()">
          <span>Chỉ hiển thị sản phẩm có nhãn <strong>MỚI</strong></span>
        </label>
        <div class="tz-advanced-search-buttons">
          <button type="button" class="tz-adv-reset-btn" onclick="resetAdvancedSearch()">Đặt lại mặc định</button>
          <button type="button" class="tz-advanced-search-button" onclick="applyAdvancedSearch()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            <span>Áp dụng bộ lọc</span>
          </button>
        </div>
      </div>
    </section>
    <div class="tz-advanced-search-empty" id="advancedSearchEmpty">
      <div style="font-size: 2rem; margin-bottom: 8px;">🔍</div>
      <div style="font-size: 1.05rem; font-weight: 700; color: #fff; margin-bottom: 4px;">Không tìm thấy sản phẩm Apple phù hợp</div>
      <div>Hãy thử nới rộng khoảng giá hoặc nhấn <a href="javascript:;" onclick="resetAdvancedSearch()" style="color:#2997ff; font-weight:700; text-decoration:underline;">Đặt lại mặc định</a> (Hotline hỗ trợ đặt hàng: <strong>0362481351</strong>).</div>
    </div>
  <?php endif; ?>

  <!-- ========================================================================
       MỤC 1: IPHONE (CHUẨN TOPZONE.VN .logo-cate + 4-COLUMN GRID)
       ======================================================================== -->
  <div id="sec-iphone">
    <a href="#sec-iphone" class="logo-cate">
      <i class="topzone-iconapple"></i>
      <h2 class="title-text">iPhone</h2>
    </a>

    <!-- Series Selector Tabs iPhone -->
    <div class="tz-series-bar">
      <button type="button" class="tz-series-btn active" onclick="filterBySeries('all', this, 'iphone')">Tất cả iPhone</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('18-series', this, 'iphone')">iPhone 18 Series</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('16-series', this, 'iphone')">iPhone 16 Series</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('15-series', this, 'iphone')">iPhone 15 Series</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('14-series', this, 'iphone')">iPhone 14 Series</button>
    </div>

    <!-- Lưới sản phẩm iPhone -->
    <div class="tz-product-grid" id="iphoneGrid">
      <?php foreach ($iphone_products as $p): ?>
        <div class="tz-product-card" data-series="<?= e($p['series']) ?>" data-category="<?= e($p['category']) ?>" data-product-id="<?= e($p['id']) ?>" data-product-name="<?= e($p['name']) ?>" data-price="<?= (int)preg_replace('/\D+/', '', $p['price']) ?>" data-capacities="<?= e(implode(',', $p['capacities'] ?? [])) ?>" data-rating="<?= e((string)$p['rating']) ?>" data-is-new="<?= !empty($p['is_new']) ? '1' : '0' ?>">
          
          <div class="tz-product-thumb">
            <?php if (!empty($p['is_new'])): ?>
              <label class="tz-badge-new">Mới</label>
            <?php endif; ?>

            <?php if ($is_wishlist): ?>
              <button type="button" class="tz-wishlist-btn" onclick="toggleWishlist('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', this)" title="Lưu vào danh sách yêu thích">♥</button>
            <?php endif; ?>

            <img id="img-<?= e($p['id']) ?>" src="<?= e($p['colors'][0]['img'] ?? '') ?>" alt="<?= e($p['name']) ?>" loading="lazy">
          </div>

          <div class="tz-card-body">
            <div>
              <?php if (!empty($p['colors']) && count($p['colors']) > 1): ?>
                <div class="tz-colors">
                  <?php foreach ($p['colors'] as $c_idx => $c): ?>
                    <div class="tz-color-dot <?= $c_idx === 0 ? 'active' : '' ?>" 
                         style="background-color: <?= $c['hex'] ?>;" 
                         title="<?= e($c['name']) ?>"
                         onclick="changeProductColor('<?= e($p['id']) ?>', '<?= e($c['img']) ?>', this)">
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if (!empty($p['capacities'])): ?>
                <div class="tz-capacities">
                  <?php foreach ($p['capacities'] as $cap_idx => $cap): ?>
                    <button type="button" class="tz-cap-btn <?= $cap_idx === 0 ? 'active' : '' ?>" onclick="selectCapacity(this)">
                      <?= e($cap) ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <h3 class="tz-card-title"><?= e($p['name']) ?></h3>

              <div class="tz-price-box">
                <span class="tz-price-main"><?= e($p['price']) ?></span>
                <?php if (!empty($p['old_price'])): ?>
                  <span class="tz-price-old"><?= e($p['old_price']) ?></span>
                <?php endif; ?>
                <?php if (!empty($p['discount'])): ?>
                  <span class="tz-discount-badge"><?= e($p['discount']) ?></span>
                <?php endif; ?>
              </div>

              <?php if ($is_reviews): ?>
                <div class="tz-rating-row" onclick="openReviewsModal('<?= e($p['name']) ?>', <?= $p['rating'] ?>, <?= $p['reviews_count'] ?>)" style="cursor: pointer;" title="Xem đánh giá & ảnh unbox">
                  <?= render_ant_stars($p['rating'], $p['reviews_count'], $is_dark_mode) ?>
                  <span style="color: #2997ff; font-weight: 600;">Xem ảnh</span>
                </div>
              <?php endif; ?>
            </div>

            <div class="cds-card-actions-row">
              <?php if ($is_fast_pay): ?>
                <button type="button" class="tz-buy-btn fast-pay" onclick="openFastCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['colors'][0]['img'] ?? '') ?>')">
                  <span>⚡ Mua 1-Chạm</span>
                </button>
              <?php else: ?>
                <button type="button" class="tz-buy-btn" onclick="openStandardCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['colors'][0]['img'] ?? '') ?>')">
                  <span>Mua ngay</span>
                </button>
              <?php endif; ?>
              <button type="button" class="tz-add-cart-btn" onclick="addToCart('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['colors'][0]['img'] ?? '') ?>', '<?= e($p['capacities'][0] ?? 'Tiêu chuẩn') ?>')" title="Thêm vào giỏ hàng CDS">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
              </button>
            </div>
          </div>

        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ========================================================================
       MỤC 2: MAC (MACBOOK AIR, PRO, IMAC, MAC MINI CHUẨN TOPZONE.VN)
       ======================================================================== -->
  <div id="sec-mac">
    <a href="#sec-mac" class="logo-cate">
      <i class="topzone-iconapple"></i>
      <h2 class="title-text">Mac</h2>
    </a>

    <div class="tz-series-bar">
      <button type="button" class="tz-series-btn active" onclick="filterBySeries('all', this, 'mac')">Tất cả Mac</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('macbook-air', this, 'mac')">MacBook Air</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('macbook-pro', this, 'mac')">MacBook Pro</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('imac', this, 'mac')">iMac</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('mac-mini', this, 'mac')">Mac mini</button>
    </div>

    <div class="tz-product-grid" id="macGrid">
      <?php foreach ($mac_products as $p): ?>
        <div class="tz-product-card" data-series="<?= e($p['series']) ?>" data-category="<?= e($p['category']) ?>" data-product-id="<?= e($p['id']) ?>" data-product-name="<?= e($p['name']) ?>" data-price="<?= (int)preg_replace('/\D+/', '', $p['price']) ?>" data-capacities="<?= e(implode(',', $p['capacities'] ?? [])) ?>" data-rating="<?= e((string)$p['rating']) ?>" data-is-new="<?= !empty($p['is_new']) ? '1' : '0' ?>">
          <div class="tz-product-thumb">
            <?php if (!empty($p['is_new'])): ?>
              <label class="tz-badge-new">Mới</label>
            <?php endif; ?>

            <?php if ($is_wishlist): ?>
              <button type="button" class="tz-wishlist-btn" onclick="toggleWishlist('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', this)" title="Lưu vào danh sách yêu thích">♥</button>
            <?php endif; ?>

            <img id="img-<?= e($p['id']) ?>" src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
          </div>

          <div class="tz-card-body">
            <div>
              <?php if (!empty($p['capacities'])): ?>
                <div class="tz-capacities">
                  <?php foreach ($p['capacities'] as $cap_idx => $cap): ?>
                    <button type="button" class="tz-cap-btn <?= $cap_idx === 0 ? 'active' : '' ?>" onclick="selectCapacity(this)">
                      <?= e($cap) ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <h3 class="tz-card-title"><?= e($p['name']) ?></h3>

              <div class="tz-price-box">
                <span class="tz-price-main"><?= e($p['price']) ?></span>
                <span class="tz-price-old"><?= e($p['old_price']) ?></span>
                <span class="tz-discount-badge"><?= e($p['discount']) ?></span>
              </div>

              <?php if ($is_reviews): ?>
                <div class="tz-rating-row" onclick="openReviewsModal('<?= e($p['name']) ?>', <?= $p['rating'] ?>, <?= $p['reviews_count'] ?>)" style="cursor: pointer;" title="Xem đánh giá & ảnh unbox">
                  <?= render_ant_stars($p['rating'], $p['reviews_count'], $is_dark_mode) ?>
                  <span style="color: #2997ff; font-weight: 600;">Xem ảnh</span>
                </div>
              <?php endif; ?>
            </div>

            <div class="cds-card-actions-row">
              <?php if ($is_fast_pay): ?>
                <button type="button" class="tz-buy-btn fast-pay" onclick="openFastCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>')">
                  <span>⚡ Mua 1-Chạm</span>
                </button>
              <?php else: ?>
                <button type="button" class="tz-buy-btn" onclick="openStandardCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>')">
                  <span>Mua ngay</span>
                </button>
              <?php endif; ?>
              <button type="button" class="tz-add-cart-btn" onclick="addToCart('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>', '<?= e($p['capacities'][0] ?? 'Tiêu chuẩn') ?>')" title="Thêm vào giỏ hàng CDS">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ========================================================================
       MỤC 3: IPAD (IPAD PRO M4, AIR M2, GEN 10, MINI 7 CHUẨN TOPZONE.VN)
       ======================================================================== -->
  <div id="sec-ipad">
    <a href="#sec-ipad" class="logo-cate">
      <i class="topzone-iconapple"></i>
      <h2 class="title-text">iPad</h2>
    </a>

    <div class="tz-series-bar">
      <button type="button" class="tz-series-btn active" onclick="filterBySeries('all', this, 'ipad')">Tất cả iPad</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('ipad-pro', this, 'ipad')">iPad Pro M4</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('ipad-air', this, 'ipad')">iPad Air M2</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('ipad-gen10', this, 'ipad')">iPad Gen 10</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('ipad-mini', this, 'ipad')">iPad mini 7</button>
    </div>

    <div class="tz-product-grid" id="ipadGrid">
      <?php foreach ($ipad_products as $p): ?>
        <div class="tz-product-card" data-series="<?= e($p['series']) ?>" data-category="<?= e($p['category']) ?>" data-product-id="<?= e($p['id']) ?>" data-product-name="<?= e($p['name']) ?>" data-price="<?= (int)preg_replace('/\D+/', '', $p['price']) ?>" data-capacities="<?= e(implode(',', $p['capacities'] ?? [])) ?>" data-rating="<?= e((string)$p['rating']) ?>" data-is-new="<?= !empty($p['is_new']) ? '1' : '0' ?>">
          <div class="tz-product-thumb">
            <?php if (!empty($p['is_new'])): ?>
              <label class="tz-badge-new">Mới</label>
            <?php endif; ?>

            <?php if ($is_wishlist): ?>
              <button type="button" class="tz-wishlist-btn" onclick="toggleWishlist('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', this)" title="Lưu vào danh sách yêu thích">♥</button>
            <?php endif; ?>

            <img id="img-<?= e($p['id']) ?>" src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
          </div>

          <div class="tz-card-body">
            <div>
              <?php if (!empty($p['capacities'])): ?>
                <div class="tz-capacities">
                  <?php foreach ($p['capacities'] as $cap_idx => $cap): ?>
                    <button type="button" class="tz-cap-btn <?= $cap_idx === 0 ? 'active' : '' ?>" onclick="selectCapacity(this)">
                      <?= e($cap) ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <h3 class="tz-card-title"><?= e($p['name']) ?></h3>

              <div class="tz-price-box">
                <span class="tz-price-main"><?= e($p['price']) ?></span>
                <span class="tz-price-old"><?= e($p['old_price']) ?></span>
                <span class="tz-discount-badge"><?= e($p['discount']) ?></span>
              </div>

              <?php if ($is_reviews): ?>
                <div class="tz-rating-row" onclick="openReviewsModal('<?= e($p['name']) ?>', <?= $p['rating'] ?>, <?= $p['reviews_count'] ?>)" style="cursor: pointer;" title="Xem đánh giá & ảnh unbox">
                  <?= render_ant_stars($p['rating'], $p['reviews_count'], $is_dark_mode) ?>
                  <span style="color: #2997ff; font-weight: 600;">Xem ảnh</span>
                </div>
              <?php endif; ?>
            </div>

            <div class="cds-card-actions-row">
              <?php if ($is_fast_pay): ?>
                <button type="button" class="tz-buy-btn fast-pay" onclick="openFastCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>')">
                  <span>⚡ Mua 1-Chạm</span>
                </button>
              <?php else: ?>
                <button type="button" class="tz-buy-btn" onclick="openStandardCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>')">
                  <span>Mua ngay</span>
                </button>
              <?php endif; ?>
              <button type="button" class="tz-add-cart-btn" onclick="addToCart('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>', '<?= e($p['capacities'][0] ?? 'Tiêu chuẩn') ?>')" title="Thêm vào giỏ hàng CDS">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ========================================================================
       MỤC 4: WATCH (SERIES 10, ULTRA 2, ULTRA 3 CHUẨN TOPZONE.VN)
       ======================================================================== -->
  <div id="sec-watch">
    <a href="#sec-watch" class="logo-cate">
      <i class="topzone-iconapple"></i>
      <h2 class="title-text">Watch</h2>
    </a>

    <div class="tz-series-bar">
      <button type="button" class="tz-series-btn active" onclick="filterBySeries('all', this, 'watch')">Tất cả Watch</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('watch-s10', this, 'watch')">Apple Watch Series 10</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('watch-ultra', this, 'watch')">Apple Watch Ultra</button>
    </div>

    <div class="tz-product-grid" id="watchGrid">
      <?php foreach ($watch_products as $p): ?>
        <div class="tz-product-card" data-series="<?= e($p['series']) ?>" data-category="<?= e($p['category']) ?>" data-product-id="<?= e($p['id']) ?>" data-product-name="<?= e($p['name']) ?>" data-price="<?= (int)preg_replace('/\D+/', '', $p['price']) ?>" data-capacities="<?= e(implode(',', $p['capacities'] ?? [])) ?>" data-rating="<?= e((string)$p['rating']) ?>" data-is-new="<?= !empty($p['is_new']) ? '1' : '0' ?>">
          <div class="tz-product-thumb">
            <?php if (!empty($p['is_new'])): ?>
              <label class="tz-badge-new">Mới</label>
            <?php endif; ?>

            <?php if ($is_wishlist): ?>
              <button type="button" class="tz-wishlist-btn" onclick="toggleWishlist('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', this)" title="Lưu vào danh sách yêu thích">♥</button>
            <?php endif; ?>

            <img id="img-<?= e($p['id']) ?>" src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
          </div>

          <div class="tz-card-body">
            <div>
              <?php if (!empty($p['capacities'])): ?>
                <div class="tz-capacities">
                  <?php foreach ($p['capacities'] as $cap_idx => $cap): ?>
                    <button type="button" class="tz-cap-btn <?= $cap_idx === 0 ? 'active' : '' ?>" onclick="selectCapacity(this)">
                      <?= e($cap) ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <h3 class="tz-card-title"><?= e($p['name']) ?></h3>

              <div class="tz-price-box">
                <span class="tz-price-main"><?= e($p['price']) ?></span>
                <span class="tz-price-old"><?= e($p['old_price']) ?></span>
                <span class="tz-discount-badge"><?= e($p['discount']) ?></span>
              </div>

              <?php if ($is_reviews): ?>
                <div class="tz-rating-row" onclick="openReviewsModal('<?= e($p['name']) ?>', <?= $p['rating'] ?>, <?= $p['reviews_count'] ?>)" style="cursor: pointer;" title="Xem đánh giá & ảnh unbox">
                  <?= render_ant_stars($p['rating'], $p['reviews_count'], $is_dark_mode) ?>
                  <span style="color: #2997ff; font-weight: 600;">Xem ảnh</span>
                </div>
              <?php endif; ?>
            </div>

            <div class="cds-card-actions-row">
              <?php if ($is_fast_pay): ?>
                <button type="button" class="tz-buy-btn fast-pay" onclick="openFastCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>')">
                  <span>⚡ Mua 1-Chạm</span>
                </button>
              <?php else: ?>
                <button type="button" class="tz-buy-btn" onclick="openStandardCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>')">
                  <span>Mua ngay</span>
                </button>
              <?php endif; ?>
              <button type="button" class="tz-add-cart-btn" onclick="addToCart('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>', '<?= e($p['capacities'][0] ?? 'Tiêu chuẩn') ?>')" title="Thêm vào giỏ hàng CDS">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ========================================================================
       MỤC 5: TAI NGHE, LOA CHUẨN TOPZONE.VN
       ======================================================================== -->
  <div id="sec-audio">
    <a href="#sec-audio" class="logo-cate">
      <h2 class="title-text">Tai nghe, Loa</h2>
    </a>

    <div class="tz-product-grid" id="audioGrid">
      <?php foreach ($audio_products as $p): ?>
        <div class="tz-product-card" data-series="<?= e($p['series']) ?>" data-category="<?= e($p['category']) ?>" data-product-id="<?= e($p['id']) ?>" data-product-name="<?= e($p['name']) ?>" data-price="<?= (int)preg_replace('/\D+/', '', $p['price']) ?>" data-capacities="<?= e(implode(',', $p['capacities'] ?? [])) ?>" data-rating="<?= e((string)$p['rating']) ?>" data-is-new="<?= !empty($p['is_new']) ? '1' : '0' ?>">
          <div class="tz-product-thumb">
            <?php if (!empty($p['is_new'])): ?>
              <label class="tz-badge-new">Mới</label>
            <?php endif; ?>

            <?php if ($is_wishlist): ?>
              <button type="button" class="tz-wishlist-btn" onclick="toggleWishlist('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', this)" title="Lưu vào danh sách yêu thích">♥</button>
            <?php endif; ?>

            <img id="img-<?= e($p['id']) ?>" src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
          </div>

          <div class="tz-card-body">
            <div>
              <h3 class="tz-card-title"><?= e($p['name']) ?></h3>
              <div class="tz-price-box">
                <span class="tz-price-main"><?= e($p['price']) ?></span>
                <span class="tz-price-old"><?= e($p['old_price']) ?></span>
                <span class="tz-discount-badge"><?= e($p['discount']) ?></span>
              </div>
              <?php if ($is_reviews): ?>
                <div class="tz-rating-row" onclick="openReviewsModal('<?= e($p['name']) ?>', <?= $p['rating'] ?>, <?= $p['reviews_count'] ?>)" style="cursor: pointer;" title="Xem đánh giá & ảnh unbox">
                  <?= render_ant_stars($p['rating'], $p['reviews_count'], $is_dark_mode) ?>
                  <span style="color: #2997ff; font-weight: 600;">Xem ảnh</span>
                </div>
              <?php endif; ?>
            </div>

            <div class="cds-card-actions-row">
              <?php if ($is_fast_pay): ?>
                <button type="button" class="tz-buy-btn fast-pay" onclick="openFastCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>')">
                  <span>⚡ Mua 1-Chạm</span>
                </button>
              <?php else: ?>
                <button type="button" class="tz-buy-btn" onclick="openStandardCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>')">
                  <span>Mua ngay</span>
                </button>
              <?php endif; ?>
              <button type="button" class="tz-add-cart-btn" onclick="addToCart('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>', 'Chính hãng Apple')" title="Thêm vào giỏ hàng CDS">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ========================================================================
       MỤC 6: PHỤ KIỆN CHUẨN TOPZONE.VN & AI RECOMMENDATIONS (CỜ goi_y_san_pham)
       ======================================================================== -->
  <div id="sec-accessories">
    <a href="#sec-accessories" class="logo-cate">
      <h2 class="title-text">Phụ kiện</h2>
    </a>

    <div class="tz-product-grid" id="accessoriesGrid">
      <?php foreach ($accessory_products as $p): ?>
        <div class="tz-product-card" data-series="<?= e($p['series']) ?>" data-category="<?= e($p['category']) ?>" data-product-id="<?= e($p['id']) ?>" data-product-name="<?= e($p['name']) ?>" data-price="<?= (int)preg_replace('/\D+/', '', $p['price']) ?>" data-capacities="<?= e(implode(',', $p['capacities'] ?? [])) ?>" data-rating="<?= e((string)$p['rating']) ?>" data-is-new="<?= !empty($p['is_new']) ? '1' : '0' ?>">
          <div class="tz-product-thumb">
            <?php if (!empty($p['is_new'])): ?>
              <label class="tz-badge-new">Mới</label>
            <?php endif; ?>

            <?php if ($is_wishlist): ?>
              <button type="button" class="tz-wishlist-btn" onclick="toggleWishlist('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', this)" title="Lưu vào danh sách yêu thích">♥</button>
            <?php endif; ?>

            <img id="img-<?= e($p['id']) ?>" src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
          </div>

          <div class="tz-card-body">
            <div>
              <h3 class="tz-card-title"><?= e($p['name']) ?></h3>
              <div class="tz-price-box">
                <span class="tz-price-main"><?= e($p['price']) ?></span>
                <span class="tz-price-old"><?= e($p['old_price']) ?></span>
                <span class="tz-discount-badge"><?= e($p['discount']) ?></span>
              </div>
              <?php if ($is_reviews): ?>
                <div class="tz-rating-row" onclick="openReviewsModal('<?= e($p['name']) ?>', <?= $p['rating'] ?>, <?= $p['reviews_count'] ?>)" style="cursor: pointer;" title="Xem đánh giá & ảnh unbox">
                  <?= render_ant_stars($p['rating'], $p['reviews_count'], $is_dark_mode) ?>
                  <span style="color: #2997ff; font-weight: 600;">Xem ảnh</span>
                </div>
              <?php endif; ?>
            </div>

            <div class="cds-card-actions-row">
              <?php if ($is_fast_pay): ?>
                <button type="button" class="tz-buy-btn fast-pay" onclick="openFastCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>')">
                  <span>⚡ Mua 1-Chạm</span>
                </button>
              <?php else: ?>
                <button type="button" class="tz-buy-btn" onclick="openStandardCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>')">
                  <span>Mua ngay</span>
                </button>
              <?php endif; ?>
              <button type="button" class="tz-add-cart-btn" onclick="addToCart('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>', 'Phụ kiện Apple')" title="Thêm vào giỏ hàng CDS">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($is_ai_recs): ?>
      <div style="max-width: 1200px; margin: 0 auto 70px; padding: 0 10px;">
        <div style="background: <?= $is_dark_mode ? '#323232' : '#ffffff' ?>; border-radius: 24px; padding: 1.5rem 2rem;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.5rem;">
            <div>
              <h3 style="font-size: 1.2rem; font-weight: 700; color: #2997ff; margin: 0;">✨ AI Smart Combo: Phụ Kiện Apple Mua Kèm Giá Sốc</h3>
              <p style="font-size: 0.82rem; color: #a1a1aa; margin: 4px 0 0;">
                Đề xuất cá nhân hóa theo thiết bị của bạn (Cờ tính năng <code>goi_y_san_pham</code>)
              </p>
            </div>
            <span class="badge" style="background: rgba(41,151,255,0.15); color: #2997ff; border: 1px solid #2997ff; font-weight: 700;">
              AI Recommendation: ON
            </span>
          </div>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
            <?php foreach ($recommendations as $rec): ?>
              <div style="background: <?= $is_dark_mode ? '#252528' : '#f8fafc' ?>; border-radius: 16px; padding: 1rem; display: flex; flex-direction: column; justify-content: space-between; text-align: center;">
                <div>
                  <div style="height: 120px; display: flex; align-items: center; justify-content: center; margin-bottom: 0.65rem;">
                    <img src="<?= e($rec['image']) ?>" alt="<?= e($rec['name']) ?>" style="max-width: 110px; max-height: 110px; object-fit: contain;">
                  </div>
                  <h4 style="font-size: 0.92rem; font-weight: 600; margin-bottom: 0.25rem; color: <?= $is_dark_mode ? '#fff' : '#0f172a' ?>;"><?= e($rec['name']) ?></h4>
                  <p style="font-size: 0.75rem; color: #a1a1aa; margin-bottom: 0.75rem;">
                    <?= e($rec['desc']) ?>
                  </p>
                </div>
                <div>
                  <div style="display: flex; align-items: center; justify-content: center; gap: 0.4rem; margin-bottom: 0.5rem;">
                    <span style="font-size: 1.05rem; font-weight: 700; color: #ff9921;"><?= $rec['price'] ?></span>
                    <span style="font-size: 0.78rem; text-decoration: line-through; color: #888;"><?= $rec['old_price'] ?></span>
                  </div>
                  <button type="button" class="btn btn-secondary btn-sm" style="width: 100%; font-size: 0.78rem; font-weight: 700; background: rgba(41,151,255,0.14); color: #2997ff; border: 1px solid rgba(41,151,255,0.35); border-radius: 10px;" onclick="addToCart('<?= e($rec['id'] ?? $rec['name']) ?>', '<?= e($rec['name']) ?>', '<?= e($rec['price']) ?>', '<?= e($rec['image']) ?>', 'Combo AI Mua Kèm')">
                    + Thêm vào giỏ mua kèm
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- ========================================================================
       MỤC 7: TEKZONE CHUYÊN TRANG TIN TỨC APPLE CHUẨN TOPZONE.VN
       ======================================================================== -->
  <div id="sec-tekzone">
    <a href="#sec-tekzone" class="logo-cate">
      <h2 class="title-text">TekZone</h2>
    </a>
    <div class="tz-tekzone-grid">
      <?php foreach ($tekzone_news as $news): ?>
        <a href="javascript:;" class="tz-news-item" onclick="showToast('Đang mở bài viết TekZone: <?= e($news['title']) ?>', 'info')">
          <img src="<?= e($news['img']) ?>" alt="<?= e($news['title']) ?>" loading="lazy">
          <h3><?= e($news['title']) ?></h3>
          <span class="timepost"><?= e($news['time']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ========================================================================
       THANH CAM KẾT CHÍNH SÁCH CHUẨN (.policy / ul.pr-policy)
       ======================================================================== -->
  <div class="policy" id="sec-policy">
    <ul class="pr-policy">
      <li>
        <i class="topzone-homeDiver"></i>
        <span>Mẫu mã đa dạng, <br>chính hãng</span>
      </li>
      <li>
        <i class="topzone-homeDeli"></i>
        <span>Giao hàng toàn quốc</span>
      </li>
      <li>
        <i class="topzone-homeInsur"></i>
        <span>Bảo hành có cam kết <br>tới 12 tháng</span>
      </li>
      <li>
        <i class="topzone-homeReturn"></i>
        <span>Có thể đổi trả tại <br>hệ thống CDS trên toàn quốc</span>
      </li>
    </ul>
  </div>

  <!-- ========================================================================
       CDS FOOTER CHÍNH THỨC (#101010)
       ======================================================================== -->
  <footer class="tz-footer">
    <div class="tz-foot-container">
      
      <!-- Logo Foot chuẩn CDS + APR -->
      <div class="logo-foot">
        <a href="demo-app.php?env=<?= urlencode($current_env) ?>&user_id=<?= urlencode($current_user_id) ?>" class="cds-logo-brand">
          <span>CD</span><span class="cds-char-s">S</span>
        </a>
        <a href="#sec-policy">
          <i class="topzone-autho"></i>
        </a>
      </div>

      <!-- 5 Cột Thông Tin Chuẩn -->
      <ul class="list-foot">
        <li>
          <span class="foot-head">Tổng đài</span>
          <a href="tel:0362481351"><span>Mua hàng:</span> <b>0362481351</b> (8:00 - 21:30)</a>
          <a href="tel:0362481351"><span>Khiếu nại:</span> <b>0362481351</b> (8:00 - 21:30)</a>
          <a href="tel:0362481351"><span>Bảo hành:</span> <b>0362481351</b> (8:00 - 21:30)</a>
          <div class="footer-social">
            <p class="txtlink">Kết nối với chúng tôi</p>
            <div class="social-link">
              <a href="javascript:;" class="link-social"><i class="iconsocial-facebook"></i></a>
              <a href="javascript:;" class="link-social"><i class="iconsocial-youtube"></i></a>
              <a href="javascript:;" class="link-social"><i class="iconsocial-zalo"></i></a>
            </div>
          </div>
        </li>
        <li>
          <span class="foot-head">Hệ thống cửa hàng</span>
          <a href="javascript:;">Xem 85 cửa hàng CDS</a>
          <a href="javascript:;">Nội quy cửa hàng</a>
          <a href="javascript:;">Chất lượng phục vụ</a>
          <a href="javascript:;">Chính sách bảo hành &amp; đổi trả</a>
        </li>
        <li>
          <span class="foot-head">Hỗ trợ khách hàng</span>
          <a href="javascript:;">Điều kiện giao dịch chung</a>
          <a href="javascript:;">Hướng dẫn mua hàng online</a>
          <a href="javascript:;">Chính sách giao hàng</a>
          <a href="javascript:;">Hướng dẫn thanh toán</a>
        </li>
        <li>
          <span class="foot-head">Về thương hiệu CDS</span>
          <a href="javascript:;" style="color: #2997ff !important;">Tích điểm Quà tặng VIP</a>
          <a href="javascript:;">Giới thiệu CDS</a>
          <a href="javascript:;">Bán hàng doanh nghiệp</a>
          <a href="javascript:;">Chính sách xử lý dữ liệu cá nhân</a>
        </li>
        <li>
          <span class="foot-head">Trung tâm bảo hành CDSCare</span>
          <a href="javascript:;">Giới thiệu CDSCare</a>
          <a href="tel:0362481351">Hotline CDSCare: <b>0362481351</b></a>
        </li>
      </ul>

      <!-- Copyright & Chứng nhận Bộ Công Thương -->
      <div class="text-cpr">
        <p>
          &copy; 2026. Công ty cổ phần CDS Việt Nam. Đại lý ủy quyền chính thức của Apple (APR). <br>
          Địa chỉ: 128 Trần Quang Khải, P.Tân Định, TP. Hồ Chí Minh. Điện thoại: <strong>0362481351</strong>.
        </p>
        <div class="certify">
          <a href="javascript:;"><img src="assets/images/products/certify-bct.png" alt="Đã thông báo Bộ Công Thương"></a>
        </div>
      </div>
    </div>

    <!-- Hệ sinh thái Tập đoàn CDS -->
    <div class="footer__logo">
      <p class="footer__logo__hd">Ghé thăm các website khác cùng tập đoàn CDS (Hotline: 0362481351)</p>
      <ul class="footer__logo__list">
        <li><a href="javascript:;" class="cds-eco-badge"><span style="color:#ff3b30;">●</span> CDS Mobile</a></li>
        <li><a href="javascript:;" class="cds-eco-badge"><span style="color:#ff9500;">●</span> CDS MacCenter</a></li>
        <li><a href="javascript:;" class="cds-eco-badge"><span style="color:#34c759;">●</span> CDS Care</a></li>
        <li><a href="javascript:;" class="cds-eco-badge"><span style="color:#007aff;">●</span> CDS Audio</a></li>
        <li><a href="javascript:;" class="cds-eco-badge"><span style="color:#af52de;">●</span> CDS TekZone</a></li>
        <li><a href="javascript:;" class="cds-eco-badge"><span style="color:#38bdf8;">●</span> CDS Enterprise</a></li>
        <li><a href="javascript:;" class="cds-eco-badge"><span style="color:#facc15;">●</span> CDS Trade-In</a></li>
        <li><a href="javascript:;" class="cds-eco-badge"><span style="color:#4ade80;">●</span> CDS Express</a></li>
      </ul>
    </div>
  </footer>

</div> <!-- Đóng .topzone-wrapper -->

<!-- ========================================================================
     CỜ 5: LIVE CHAT TƯ VẤN VIÊN APPLE CDS 24/7 (ho_tro_truc_tuyen)
     ======================================================================== -->
<?php if ($is_chat): ?>
  <div class="tz-livechat-widget">
    <button type="button" class="tz-chat-btn" onclick="toggleLiveChat()" title="Chat trực tuyến với chuyên viên tư vấn CDS 24/7 (0362481351)">
      <i class="boxchat-balloons"></i>
    </button>

    <div class="tz-chat-popup" id="liveChatPopup">
      <div style="background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%); color: #fff; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: 0.6rem;">
          <div style="width: 36px; height: 36px; background: #fff; color: #2563eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: 800;">
            
          </div>
          <div>
            <div style="font-weight: 700; font-size: 0.95rem;">Chuyên viên CDS STORE</div>
            <div style="font-size: 0.72rem; color: #e0f2fe; display: flex; align-items: center; gap: 0.25rem;">
              <span style="width: 6px; height: 6px; background: #4ade80; border-radius: 50%;"></span>
              Hotline: 0362481351 (Trực 24/7)
            </div>
          </div>
        </div>
        <button type="button" style="background: none; border: none; color: #fff; font-size: 1.4rem; cursor: pointer;" onclick="toggleLiveChat()">&times;</button>
      </div>

      <div id="chatMessages" style="flex: 1; padding: 1rem; overflow-y: auto; display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.85rem;">
        <div style="background: <?= $is_dark_mode ? '#272733' : '#f1f5f9' ?>; padding: 0.75rem 1rem; border-radius: 12px 12px 12px 2px; max-width: 85%;">
          Xin chào quý khách <strong><?= e($current_user_id) ?></strong>! Em là chuyên viên Apple từ CDS STORE (Hotline: 0362481351). Em có thể hỗ trợ quý khách về iPhone 18 Pro Max, MacBook M3 hay chính sách trả góp 0% ạ?
        </div>
      </div>

      <div style="padding: 0.5rem 0.75rem; display: flex; gap: 0.35rem; overflow-x: auto; white-space: nowrap; border-top: 1px solid <?= $is_dark_mode ? '#2d2d3a' : '#e2e8f0' ?>;">
        <button type="button" class="btn btn-secondary btn-sm" style="font-size: 0.72rem;" onclick="sendQuickReply('iPhone 18 Pro Max giá bao nhiêu?')">Giá iPhone 18 PRM?</button>
        <button type="button" class="btn btn-secondary btn-sm" style="font-size: 0.72rem;" onclick="sendQuickReply('Thủ tục trả góp 0% như thế nào?')">Trả góp 0%</button>
        <button type="button" class="btn btn-secondary btn-sm" style="font-size: 0.72rem;" onclick="sendQuickReply('Gọi hotline 0362481351 tư vấn ngay')">Gọi 0362481351</button>
      </div>

      <div style="padding: 0.75rem; border-top: 1px solid <?= $is_dark_mode ? '#2d2d3a' : '#e2e8f0' ?>; display: flex; gap: 0.5rem;">
        <input type="text" id="chatInput" class="form-control" placeholder="Nhập câu hỏi của quý khách..." onkeypress="if(event.key==='Enter') sendChatMessage()">
        <button type="button" class="btn btn-primary" onclick="sendChatMessage()">Gửi</button>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- ========================================================================
     MODAL GIỎ HÀNG CHUYÊN NGHIỆP CDS STORE (#cartModal)
     ======================================================================== -->
<div class="modal-backdrop" id="cartModal">
  <div class="modal-dialog cds-pro-dialog" style="max-width: 660px;">
    <div class="cds-pro-header">
      <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, rgba(41,151,255,0.22) 0%, rgba(37,99,235,0.12) 100%); border: 1px solid rgba(41,151,255,0.4); display: flex; align-items: center; justify-content: center; color: #2997ff;">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        </div>
        <div>
          <div style="font-weight: 800; font-size: 1.12rem; display: flex; align-items: center; gap: 8px;">
            <span>Giỏ Hàng CDS Store</span>
            <span id="cartModalCountTag" style="font-size: 0.74rem; padding: 2px 9px; border-radius: 999px; background: rgba(41,151,255,0.18); color: #2997ff; border: 1px solid rgba(41,151,255,0.35);">2 sản phẩm</span>
          </div>
          <div style="font-size: 0.78rem; color: #94a3b8; margin-top: 2px;">Đại lý ủy quyền Apple chính hãng • Giao siêu tốc 1 giờ (Hotline: <strong>0362481351</strong>)</div>
        </div>
      </div>
      <button type="button" class="cds-pro-close" onclick="closeModal('cartModal')">&times;</button>
    </div>

    <div class="cds-pro-body" style="max-height: 75vh; overflow-y: auto;">
      <!-- Danh sách sản phẩm trong giỏ -->
      <div id="cartItemsContainer" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px;"></div>

      <!-- Gợi ý mua kèm nhanh ngay trong giỏ -->
      <div style="background: <?= $is_dark_mode ? 'rgba(41,151,255,0.06)' : '#f0f9ff' ?>; border: 1px dashed rgba(41,151,255,0.35); border-radius: 14px; padding: 12px 14px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <img src="assets/images/products/adapter-20w.png" alt="Adapter 20W" style="width: 40px; height: 40px; object-fit: contain; background: #18181b; border-radius: 8px; padding: 4px;">
          <div>
            <div style="font-size: 0.84rem; font-weight: 700;">⚡ Mua kèm Củ sạc Apple USB-C 20W chính hãng</div>
            <div style="font-size: 0.76rem; color: #94a3b8;">Giá ưu đãi mua kèm chỉ <strong style="color: #ff9f00;">490.000đ</strong> <span style="text-decoration: line-through;">690.000đ</span></div>
          </div>
        </div>
        <button type="button" onclick="addToCart('acc-20w', 'Adapter Sạc Apple Type-C 20W Chính hãng', '490.000đ', 'assets/images/products/adapter-20w.png', 'Mua kèm giá sốc')" style="background: #2997ff; color: #fff; border: none; border-radius: 8px; padding: 7px 12px; font-size: 0.78rem; font-weight: 700; cursor: pointer;">+ Thêm vào giỏ</button>
      </div>

      <!-- Khung Tổng kết đơn hàng & Ưu đãi -->
      <div class="cds-summary-box" id="cartSummaryBox">
        <div class="cds-summary-line">
          <span style="color: #94a3b8;">Tạm tính (<span id="cartSummaryQty">0</span> sản phẩm):</span>
          <strong id="cartSubtotalText">0đ</strong>
        </div>
        <?php if ($is_coupon): ?>
          <div class="cds-summary-line" style="color: #4ade80;">
            <span>🎟️ Mã giảm giá <strong>CDS18</strong> (Cờ <code>ma_giam_gia</code>):</span>
            <strong id="cartCouponText">-1.000.000đ</strong>
          </div>
        <?php endif; ?>
        <?php if ($is_loyalty): ?>
          <div class="cds-summary-line" style="color: #fbbf24;">
            <span>👑 Quy đổi 2.450 điểm VIP Member (Cờ <code>tich_diem_thanh_vien</code>):</span>
            <strong id="cartLoyaltyText">-245.000đ</strong>
          </div>
        <?php endif; ?>
        <div class="cds-summary-line">
          <span style="color: #94a3b8;">Phí giao hàng CDS Express 1 Giờ:</span>
          <strong style="color: #38bdf8;">Miễn phí (0đ)</strong>
        </div>
        <div class="cds-summary-total">
          <span style="font-size: 0.96rem; font-weight: 800;">Tổng thanh toán tạm tính:</span>
          <span style="font-size: 1.32rem; font-weight: 800; color: #ff453a;" id="cartTotalText">0đ</span>
        </div>
      </div>

      <!-- Nút điều hướng Giỏ hàng -->
      <div style="display: flex; gap: 12px; margin-top: 18px; flex-wrap: wrap;">
        <button type="button" onclick="closeModal('cartModal')" style="flex: 1; min-width: 160px; padding: 12px 16px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.16); background: rgba(255,255,255,0.05); color: inherit; font-weight: 700; font-size: 0.9rem; cursor: pointer;">
          &larr; Tiếp tục xem sản phẩm
        </button>
        <button type="button" id="cartProceedBtn" onclick="proceedCartToCheckout()" style="flex: 1.6; min-width: 220px; padding: 13px 20px; border-radius: 12px; border: none; background: <?= $is_fast_pay ? 'linear-gradient(135deg, #ff9f00 0%, #ff5e00 100%)' : 'linear-gradient(135deg, #0071e3 0%, #2997ff 100%)' ?>; color: #fff; font-weight: 800; font-size: 0.95rem; cursor: pointer; box-shadow: 0 10px 24px rgba(0, 113, 227, 0.3); display: flex; align-items: center; justify-content: center; gap: 8px;">
          <span><?= $is_fast_pay ? '⚡ Tiến hành Thanh Toán 1-Chạm Express' : '🛡️ Tiến hành Đặt Hàng & Thanh Toán (3 Bước)' ?></span>
          <span>&rarr;</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ========================================================================
     MODAL: CỜ 1 - THANH TOÁN 1 BƯỚC SIÊU TỐC (ONE-STEP EXPRESS CHECKOUT)
     ======================================================================== -->
<div class="modal-backdrop" id="oneStepCheckoutModal">
  <div class="modal-dialog cds-pro-dialog" style="max-width: 650px;">
    <div class="cds-pro-header" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.18) 0%, rgba(234, 88, 12, 0.08) 100%);">
      <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 800; box-shadow: 0 6px 16px rgba(234, 88, 12, 0.35);">
          ⚡
        </div>
        <div>
          <div style="font-weight: 800; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
            <span>Thanh Toán 1-Chạm Siêu Tốc CDS Express</span>
            <span style="font-size: 0.7rem; padding: 2px 8px; border-radius: 999px; background: rgba(245,158,11,0.2); color: #fbbf24; border: 1px solid rgba(245,158,11,0.4);">thanh_toan_nhanh: ON</span>
          </div>
          <div style="font-size: 0.78rem; color: #94a3b8; margin-top: 2px;">Tự động điền hồ sơ VIP của <strong><?= e($current_user_id) ?></strong> • Hoàn tất đơn trong 3 giây</div>
        </div>
      </div>
      <button type="button" class="cds-pro-close" onclick="closeModal('oneStepCheckoutModal')">&times;</button>
    </div>

    <div class="cds-pro-body" style="max-height: 78vh; overflow-y: auto;">
      <!-- Sản phẩm đang thanh toán -->
      <div id="fastCheckoutItemsBox" style="background: <?= $is_dark_mode ? '#26262b' : '#f8fafc' ?>; border: 1px solid <?= $is_dark_mode ? 'rgba(255,255,255,0.08)' : '#e2e8f0' ?>; border-radius: 14px; padding: 12px 14px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
          <img id="modalProductImg" src="assets/images/products/iphone-18-promax-titan.png" alt="Product" style="width: 60px; height: 60px; object-fit: contain; background: #18181b; border-radius: 12px; padding: 6px;">
          <div>
            <div style="font-size: 0.72rem; color: #38bdf8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;">Sản phẩm chính hãng Apple VN/A</div>
            <h4 id="modalProductName" style="font-size: 0.98rem; font-weight: 700; margin: 2px 0 4px;">iPhone 18 Pro Max 256GB</h4>
            <div id="modalProductPrice" style="font-size: 1.08rem; font-weight: 800; color: #ff9f00;">41.990.000đ</div>
          </div>
        </div>
        <button type="button" onclick="closeModal('oneStepCheckoutModal'); openCartView();" style="background: rgba(41,151,255,0.12); color: #2997ff; border: 1px solid rgba(41,151,255,0.3); border-radius: 8px; padding: 6px 10px; font-size: 0.76rem; font-weight: 700; cursor: pointer;">
          Xem giỏ hàng
        </button>
      </div>

      <form onsubmit="handleCheckoutSubmit(event)">
        <!-- Hình thức nhận hàng -->
        <div style="margin-bottom: 14px;">
          <label style="display: block; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #94a3b8; margin-bottom: 8px;">1. Hình thức & Thông tin nhận hàng (Tự động điền):</label>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
            <input type="text" class="cds-input" id="fastBuyerName" value="Khách hàng VIP (<?= e($current_user_id) ?>)" required placeholder="Họ và tên người nhận">
            <input type="text" class="cds-input font-mono" id="fastBuyerPhone" value="0362481351" required placeholder="Số điện thoại liên hệ">
          </div>
          <input type="text" class="cds-input" id="fastBuyerAddress" value="128 Trần Quang Khải, P. Tân Định, Quận 1, TP. Hồ Chí Minh (Giao siêu tốc 1 Giờ)" required placeholder="Địa chỉ nhận hàng">
        </div>

        <!-- Phương thức thanh toán 1 chạm -->
        <div style="margin-bottom: 16px;">
          <label style="display: block; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #94a3b8; margin-bottom: 8px;">2. Chọn phương thức thanh toán 1-Chạm:</label>
          <div class="cds-pay-grid" id="fastPayMethodGrid">
            <label class="cds-pay-option active" onclick="selectPayCard(this)">
              <input type="radio" name="pay_method" value="applepay" checked>
              <div>
                <div style="font-weight: 700; font-size: 0.86rem;"> Apple Pay / VNPay-QR</div>
                <div style="font-size: 0.73rem; color: #94a3b8;">Quét mã tức thì • Giảm thêm 200K</div>
              </div>
            </label>
            <label class="cds-pay-option" onclick="selectPayCard(this)">
              <input type="radio" name="pay_method" value="installment">
              <div>
                <div style="font-weight: 700; font-size: 0.86rem;">💳 Trả góp 0% Thẻ Tín Dụng</div>
                <div style="font-size: 0.73rem; color: #94a3b8;">25 ngân hàng • Duyệt tự động</div>
              </div>
            </label>
            <label class="cds-pay-option" onclick="selectPayCard(this)">
              <input type="radio" name="pay_method" value="bank">
              <div>
                <div style="font-weight: 700; font-size: 0.86rem;">🏦 Chuyển khoản VietQR 24/7</div>
                <div style="font-size: 0.73rem; color: #94a3b8;">Xác nhận tự động trong 5 giây</div>
              </div>
            </label>
            <label class="cds-pay-option" onclick="selectPayCard(this)">
              <input type="radio" name="pay_method" value="cod">
              <div>
                <div style="font-weight: 700; font-size: 0.86rem;">📦 Thanh toán khi nhận (COD)</div>
                <div style="font-size: 0.73rem; color: #94a3b8;">Kiểm tra nguyên seal trước khi trả</div>
              </div>
            </label>
          </div>
        </div>

        <!-- Chi tiết thanh toán & Ưu đãi -->
        <div class="cds-summary-box" style="margin-bottom: 18px;">
          <div class="cds-summary-line">
            <span style="color: #94a3b8;">Giá niêm yết tạm tính:</span>
            <strong id="fastModalSubPrice">41.990.000đ</strong>
          </div>
          <?php if ($is_coupon): ?>
            <div class="cds-summary-line" style="color: #4ade80;">
              <span>🎟️ Voucher <strong>CDS18</strong> (Tự động áp dụng):</span>
              <strong>-1.000.000đ</strong>
            </div>
          <?php endif; ?>
          <?php if ($is_loyalty): ?>
            <div class="cds-summary-line" style="color: #fbbf24;">
              <span>👑 Trừ điểm VIP CDS Member (2.450 điểm):</span>
              <strong>-245.000đ</strong>
            </div>
          <?php endif; ?>
          <div class="cds-summary-line">
            <span style="color: #94a3b8;">Bảo hành chính hãng Apple Care+ & Giao 1H:</span>
            <strong style="color: #38bdf8;">Miễn phí</strong>
          </div>
          <div class="cds-summary-total">
            <span style="font-weight: 800; font-size: 0.96rem;">Tổng thanh toán cuối cùng:</span>
            <span style="color: #ff453a; font-size: 1.32rem; font-weight: 800;" id="modalFinalPrice">40.745.000đ</span>
          </div>
        </div>

        <button type="submit" style="width: 100%; background: linear-gradient(135deg, #ff9f00 0%, #ff5e00 100%); color: #fff; border: none; border-radius: 12px; font-size: 1.02rem; font-weight: 800; padding: 14px 20px; cursor: pointer; box-shadow: 0 10px 26px rgba(255, 94, 0, 0.35); display: flex; align-items: center; justify-content: center; gap: 8px;">
          <span>⚡ Xác Nhận Đặt Hàng Tức Thì (Hotline hỗ trợ: 0362481351)</span>
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ========================================================================
     MODAL: QUY TRÌNH THANH TOÁN TIÊU CHUẨN 3 BƯỚC (KHI thanh_toan_nhanh = OFF)
     ======================================================================== -->
<div class="modal-backdrop" id="standardCheckoutModal">
  <div class="modal-dialog cds-pro-dialog" style="max-width: 640px;">
    <div class="cds-pro-header">
      <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(41,151,255,0.16); border: 1px solid rgba(41,151,255,0.35); color: #2997ff; display: flex; align-items: center; justify-content: center; font-weight: 800;">
          🛡️
        </div>
        <div>
          <div style="font-weight: 800; font-size: 1.06rem;">Đặt Hàng Tiêu Chuẩn Tại CDS Store (Quy trình 3 Bước)</div>
          <div style="font-size: 0.76rem; color: #94a3b8;">Cờ <code>thanh_toan_nhanh = OFF</code> • Quy trình xác thực từng bước tiêu chuẩn</div>
        </div>
      </div>
      <button type="button" class="cds-pro-close" onclick="closeModal('standardCheckoutModal')">&times;</button>
    </div>

    <div class="cds-pro-body">
      <!-- Thanh tiến trình 3 bước tương tác -->
      <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 18px;">
        <div id="stdStepBadge1" onclick="goToStdStep(1)" style="cursor: pointer; text-align: center; padding: 9px 8px; background: #2997ff; color: #fff; font-size: 0.78rem; font-weight: 700; border-radius: 10px; transition: all 0.2s;">
          1. Kiểm tra đơn hàng
        </div>
        <div id="stdStepBadge2" onclick="goToStdStep(2)" style="cursor: pointer; text-align: center; padding: 9px 8px; background: rgba(255,255,255,0.06); color: #94a3b8; font-size: 0.78rem; font-weight: 700; border-radius: 10px; transition: all 0.2s;">
          2. Địa chỉ nhận hàng
        </div>
        <div id="stdStepBadge3" onclick="goToStdStep(3)" style="cursor: pointer; text-align: center; padding: 9px 8px; background: rgba(255,255,255,0.06); color: #94a3b8; font-size: 0.78rem; font-weight: 700; border-radius: 10px; transition: all 0.2s;">
          3. Thanh toán & Hoàn tất
        </div>
      </div>

      <!-- BƯỚC 1: KIỂM TRA SẢN PHẨM -->
      <div id="stdStepPanel1">
        <div style="background: <?= $is_dark_mode ? '#26262b' : '#f8fafc' ?>; border: 1px solid <?= $is_dark_mode ? 'rgba(255,255,255,0.08)' : '#e2e8f0' ?>; border-radius: 14px; padding: 14px; margin-bottom: 16px; display: flex; align-items: center; gap: 14px;">
          <img id="stdProductImg" src="assets/images/products/iphone-18-promax-titan.png" alt="Product" style="width: 64px; height: 64px; object-fit: contain; background: #18181b; border-radius: 12px; padding: 6px;">
          <div style="flex: 1;">
            <div style="font-size: 0.74rem; color: #2997ff; font-weight: 700;">Sản phẩm đã chọn trong đơn</div>
            <input type="text" id="stdProductName" class="cds-input" readonly style="margin-top: 4px; margin-bottom: 6px; font-weight: 700;">
            <input type="text" id="stdProductPrice" class="cds-input font-mono" readonly style="font-weight: 800; color: #ff9f00;">
          </div>
        </div>
        <div style="display: flex; justify-content: flex-end;">
          <button type="button" onclick="goToStdStep(2)" style="background: linear-gradient(135deg, #0071e3 0%, #2997ff 100%); color: #fff; border: none; border-radius: 10px; padding: 11px 22px; font-weight: 700; font-size: 0.9rem; cursor: pointer;">
            Tiếp tục sang Bước 2 (Nhập địa chỉ) &rarr;
          </button>
        </div>
      </div>

      <!-- BƯỚC 2: NHẬP THÔNG TIN GIAO HÀNG -->
      <div id="stdStepPanel2" style="display: none;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
          <div>
            <label style="font-size: 0.78rem; color: #94a3b8; display: block; margin-bottom: 4px;">Họ và tên người nhận *</label>
            <input type="text" id="stdBuyerName" class="cds-input" value="Khách hàng <?= e($current_user_id) ?>" placeholder="Nhập họ tên người nhận">
          </div>
          <div>
            <label style="font-size: 0.78rem; color: #94a3b8; display: block; margin-bottom: 4px;">Số điện thoại liên hệ *</label>
            <input type="text" id="stdBuyerPhone" class="cds-input font-mono" value="0362481351" placeholder="SĐT nhận hàng">
          </div>
        </div>
        <div style="margin-bottom: 16px;">
          <label style="font-size: 0.78rem; color: #94a3b8; display: block; margin-bottom: 4px;">Địa chỉ nhận hàng chi tiết *</label>
          <input type="text" id="stdBuyerAddr" class="cds-input" value="128 Trần Quang Khải, P. Tân Định, Quận 1, TP.HCM" placeholder="Số nhà, tên đường, phường/xã, quận/huyện">
        </div>
        <div style="display: flex; justify-content: space-between; gap: 10px;">
          <button type="button" onclick="goToStdStep(1)" style="background: rgba(255,255,255,0.06); color: inherit; border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 10px 18px; font-weight: 700; cursor: pointer;">
            &larr; Quay lại Bước 1
          </button>
          <button type="button" onclick="goToStdStep(3)" style="background: linear-gradient(135deg, #0071e3 0%, #2997ff 100%); color: #fff; border: none; border-radius: 10px; padding: 10px 22px; font-weight: 700; cursor: pointer;">
            Tiếp tục sang Bước 3 (Thanh toán) &rarr;
          </button>
        </div>
      </div>

      <!-- BƯỚC 3: CHỌN THANH TOÁN & HOÀN TẤT -->
      <div id="stdStepPanel3" style="display: none;">
        <div class="cds-pay-grid" style="margin-bottom: 14px;">
          <label class="cds-pay-option active" onclick="selectPayCard(this)">
            <input type="radio" name="std_pay" checked>
            <div>
              <div style="font-weight: 700; font-size: 0.85rem;">🏦 Chuyển khoản Ngân hàng (VietQR)</div>
              <div style="font-size: 0.72rem; color: #94a3b8;">Miễn phí xử lý • Có hóa đơn VAT</div>
            </div>
          </label>
          <label class="cds-pay-option" onclick="selectPayCard(this)">
            <input type="radio" name="std_pay">
            <div>
              <div style="font-weight: 700; font-size: 0.85rem;">📦 Thanh toán khi nhận hàng (COD)</div>
              <div style="font-size: 0.72rem; color: #94a3b8;">Đồng kiểm cùng nhân viên CDS</div>
            </div>
          </label>
        </div>
        <div class="cds-summary-box" style="margin-bottom: 16px;">
          <div class="cds-summary-line">
            <span style="color: #94a3b8;">Tổng giá trị đơn hàng:</span>
            <strong id="stdFinalTotalText" style="color: #ff453a; font-size: 1.15rem;">41.990.000đ</strong>
          </div>
          <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 6px;">Tổng đài viên CDS (<strong>0362481351</strong>) sẽ gọi xác nhận đơn trước khi xuất kho.</div>
        </div>
        <div style="display: flex; justify-content: space-between; gap: 10px;">
          <button type="button" onclick="goToStdStep(2)" style="background: rgba(255,255,255,0.06); color: inherit; border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 11px 18px; font-weight: 700; cursor: pointer;">
            &larr; Quay lại Bước 2
          </button>
          <button type="button" onclick="handleStandardCheckoutSubmit()" style="flex: 1; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: #fff; border: none; border-radius: 10px; padding: 12px 22px; font-weight: 800; font-size: 0.95rem; cursor: pointer;">
            ✓ Hoàn Tất Đặt Hàng Tiêu Chuẩn
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ========================================================================
     MODAL: CỜ 4 - DANH SÁCH SẢN PHẨM YÊU THÍCH (WISHLIST)
     ======================================================================== -->
<div class="modal-backdrop" id="wishlistModal">
  <div class="modal-dialog cds-pro-dialog" style="max-width: 520px;">
    <div class="cds-pro-header">
      <div style="font-weight: 800; font-size: 1.05rem; display: flex; align-items: center; gap: 0.5rem;">
        <span style="color: #ef4444;">♥</span>
        <span>Sản phẩm yêu thích của bạn (Wishlist)</span>
      </div>
      <button type="button" class="cds-pro-close" onclick="closeModal('wishlistModal')">&times;</button>
    </div>
    <div class="cds-pro-body">
      <div id="wishlistEmptyState" style="text-align: center; padding: 2rem 1rem; color: #94a3b8;">
        <div style="font-size: 2.2rem; margin-bottom: 0.5rem;">💝</div>
        <div style="font-weight: 700; color: inherit;">Chưa có sản phẩm yêu thích nào</div>
        <div style="font-size: 0.8rem; margin-top: 0.25rem;">Nhấp vào biểu tượng trái tim ♥ góc trên thẻ sản phẩm để lưu lại xem sau</div>
      </div>
      <div id="wishlistItemsList" style="display: flex; flex-direction: column; gap: 0.65rem;"></div>
    </div>
  </div>
</div>

<!-- ========================================================================
     MODAL: CỜ 3 - ĐÁNH GIÁ VÀ HÌNH ẢNH UNBOX THỰC TẾ (danh_gia_san_pham)
     ======================================================================== -->
<div class="modal-backdrop" id="reviewsModal">
  <div class="modal-dialog cds-pro-dialog" style="max-width: 600px;">
    <div class="cds-pro-header">
      <div id="reviewModalTitle" style="font-weight: 800; font-size: 1.05rem;">
        Đánh giá & Ảnh unbox thực tế từ khách hàng
      </div>
      <button type="button" class="cds-pro-close" onclick="closeModal('reviewsModal')">&times;</button>
    </div>
    <div class="cds-pro-body">
      <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 1rem; margin-bottom: 1.25rem;">
        <div>
          <div id="reviewModalScore" style="font-size: 2.2rem; font-weight: 800; color: #faad14;">5.0 / 5.0</div>
          <div id="reviewModalStars" style="display: flex; align-items: center; gap: 4px; margin: 4px 0;">
            <?php for ($s = 1; $s <= 5; $s++): ?>
              <svg viewBox="64 64 896 896" width="18" height="18" fill="#faad14"><path d="M908.1 353.1l-253.9-36.9L540.7 86.1c-3.1-6.3-8.2-11.4-14.5-14.5-15.8-7.8-35-1.3-42.9 14.5L369.8 316.2l-253.9 36.9c-7 1-13.4 4.3-18.3 9.3a32.05 32.05 0 0 0 .6 45.3l183.7 179.1-43.4 252.9a31.95 31.95 0 0 0 46.4 33.7L512 754l227.1 119.4c6.2 3.3 13.4 4.4 20.3 3.2 17.4-3 29.1-19.5 26.1-36.9l-43.4-252.9 183.7-179.1c5-4.9 8.3-11.3 9.3-18.3 2.7-17.5-9.5-33.7-27-36.3z"/></svg>
            <?php endfor; ?>
          </div>
          <div id="reviewModalCount" style="font-size: 0.8rem; color: #94a3b8;">Dựa trên 520 đánh giá đã xác thực mua tại CDS STORE</div>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="showToast('Cảm ơn bạn! Hệ thống đánh giá CDS đã ghi nhận.', 'success')">
          ✍️ Viết đánh giá
        </button>
      </div>

      <div style="margin-bottom: 1.25rem;">
        <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 0.65rem;">Hình ảnh unbox đập hộp thực tế:</h4>
        <div style="display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.5rem;">
          <img src="assets/images/products/iphone-18-promax-titan.png" style="width: 80px; height: 80px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.12); object-fit: contain; background: #18181b; padding: 4px;" alt="Unbox 1">
          <img src="assets/images/products/iphone-18-pro-titan.png" style="width: 80px; height: 80px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.12); object-fit: contain; background: #18181b; padding: 4px;" alt="Unbox 2">
          <img src="assets/images/products/macbook-air-13-blue.png" style="width: 80px; height: 80px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.12); object-fit: contain; background: #18181b; padding: 4px;" alt="Unbox 3">
        </div>
      </div>

      <div style="display: flex; flex-direction: column; gap: 0.85rem; font-size: 0.85rem;">
        <div style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); padding: 0.95rem; border-radius: 12px;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
            <strong>Anh Minh Hoàng (Đã mua tại CDS STORE 128 Trần Quang Khải)</strong>
            <div style="display:inline-flex; align-items:center; gap:2px;">
              <?php for ($s = 1; $s <= 5; $s++): ?>
                <svg viewBox="64 64 896 896" width="13" height="13" fill="#faad14"><path d="M908.1 353.1l-253.9-36.9L540.7 86.1c-3.1-6.3-8.2-11.4-14.5-14.5-15.8-7.8-35-1.3-42.9 14.5L369.8 316.2l-253.9 36.9c-7 1-13.4 4.3-18.3 9.3a32.05 32.05 0 0 0 .6 45.3l183.7 179.1-43.4 252.9a31.95 31.95 0 0 0 46.4 33.7L512 754l227.1 119.4c6.2 3.3 13.4 4.4 20.3 3.2 17.4-3 29.1-19.5 26.1-36.9l-43.4-252.9 183.7-179.1c5-4.9 8.3-11.3 9.3-18.3 2.7-17.5-9.5-33.7-27-36.3z"/></svg>
              <?php endfor; ?>
            </div>
          </div>
          <p style="color: #94a3b8; margin: 0; line-height: 1.5;">
            iPhone 18 Pro Max bên ngoài cực kỳ sang trọng và cuốn hút. Nút Camera Control siêu nhạy, màn hình viền mỏng vô cực. Đội ngũ kỹ thuật viên CDS STORE tư vấn rất nhiệt tình qua hotline <strong>0362481351</strong>!
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ========================================================================
     MODAL: CỜ 9 - TRA CỨU ĐƠN HÀNG THỜI GIAN THỰC (theo_doi_don_hang)
     ======================================================================== -->
<div class="modal-backdrop" id="trackingModal">
  <div class="modal-dialog cds-pro-dialog" style="max-width: 540px;">
    <div class="cds-pro-header">
      <div style="font-weight: 800; font-size: 1.05rem; display: flex; align-items: center; gap: 0.4rem;">
        <span style="color: #2997ff;">🚚</span>
        <span>Định Vị & Theo Dõi Đơn Hàng CDS STORE Realtime</span>
      </div>
      <button type="button" class="cds-pro-close" onclick="closeModal('trackingModal')">&times;</button>
    </div>
    <div class="cds-pro-body">
      <div style="margin-bottom: 1.25rem;">
        <label style="font-size: 0.8rem; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 6px;">Nhập mã đơn hàng CDS STORE của bạn:</label>
        <div style="display: flex; gap: 0.5rem;">
          <input type="text" id="trackingCodeInput" class="cds-input font-mono" value="CDS-2026-98845" placeholder="Ví dụ: CDS-2026-XXXXX">
          <button type="button" class="btn btn-primary" onclick="searchTrackingOrder()" style="white-space: nowrap;">Tra cứu</button>
        </div>
      </div>

      <div style="border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 1.25rem; background: rgba(255,255,255,0.03);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
          <div>
            <div style="font-weight: 700; font-size: 0.95rem;">Mã vận đơn: <span class="font-mono" style="color: #2997ff;">CDS-2026-98845</span></div>
            <div style="font-size: 0.78rem; color: #94a3b8;">Dịch vụ: CDS STORE Express Giao Siêu Tốc 1 Giờ</div>
          </div>
          <span class="badge badge-success">Đang vận chuyển</span>
        </div>

        <div style="position: relative; padding-left: 1.25rem; border-left: 2px dashed rgba(41,151,255,0.4); display: flex; flex-direction: column; gap: 1rem; font-size: 0.82rem;">
          <div>
            <strong style="color: #4ade80;">18:42 - Shipper đang giao đến bạn</strong>
            <div style="color: #94a3b8;">Tài xế Nguyễn Văn An (Hotline: <strong>0362481351</strong>) - Khoảng cách 800m</div>
          </div>
          <div>
            <strong>18:15 - Xuất kho CDS STORE Quận 1</strong>
            <div style="color: #94a3b8;">Đã kiểm tra tem niêm phong Apple chính hãng</div>
          </div>
          <div>
            <strong>18:05 - Đơn hàng đã xác nhận thanh toán</strong>
            <div style="color: #94a3b8;">Áp dụng mã giảm giá CDS18 thành công</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ========================================================================
     MODAL: CỜ 10 - TÍCH ĐIỂM HỘI VIÊN CDS STORE MEMBER (tich_diem_thanh_vien)
     ======================================================================== -->
<div class="modal-backdrop" id="loyaltyModal">
  <div class="modal-dialog cds-pro-dialog" style="max-width: 500px;">
    <div class="cds-pro-header">
      <div style="font-weight: 800; font-size: 1.05rem; display: flex; align-items: center; gap: 0.4rem;">
        <span>👑</span>
        <span>Hội viên VIP: CDS STORE Member</span>
      </div>
      <button type="button" class="cds-pro-close" onclick="closeModal('loyaltyModal')">&times;</button>
    </div>
    <div class="cds-pro-body">
      <div style="background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%); color: #fff; padding: 1.25rem; border-radius: 14px; margin-bottom: 1.25rem; box-shadow: 0 8px 20px rgba(245,158,11,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <div>
            <div style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.9;">Hạng thẻ Kim Cương</div>
            <div style="font-size: 1.4rem; font-weight: 800;"><?= e($current_user_id) ?></div>
          </div>
          <div style="font-size: 1.8rem;"></div>
        </div>
        <div style="margin-top: 1.25rem; display: flex; justify-content: space-between; align-items: flex-end;">
          <div>
            <div style="font-size: 0.75rem; opacity: 0.9;">Điểm thưởng khả dụng</div>
            <div style="font-size: 1.6rem; font-weight: 800;">2.450 <span style="font-size: 0.9rem;">điểm</span></div>
          </div>
          <div style="font-size: 0.82rem; font-weight: 600; background: rgba(0,0,0,0.25); padding: 0.2rem 0.5rem; border-radius: 4px;">
            Quy đổi: 245.000đ
          </div>
        </div>
      </div>

      <div style="font-size: 0.85rem; color: #94a3b8; line-height: 1.6;">
        <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 0.4rem; color: #fff;">Quyền lợi đặc quyền của bạn:</h4>
        <ul style="padding-left: 1.2rem; margin-bottom: 1rem;">
          <li>Tích lũy 1% giá trị cho mọi đơn hàng Apple chính hãng</li>
          <li>Được ưu tiên nhận iPhone 18 Pro Max đợt đầu không cần chờ</li>
          <li>Miễn phí vệ sinh máy & dán cường lực trọn đời tại CDS Care</li>
          <li>Hỗ trợ hotline VIP riêng: <strong>0362481351</strong></li>
        </ul>
      </div>

      <button type="button" class="btn btn-primary" style="width: 100%; border-radius: 10px; padding: 11px;" onclick="closeModal('loyaltyModal'); openCartView();">
        Mở Giỏ Hàng & Áp dụng giảm trừ 245.000đ ngay
      </button>
    </div>
  </div>
</div>

<!-- ========================================================================
     JAVASCRIPT ĐỒNG BỘ 10 TÍNH NĂNG, GIỎ HÀNG & THANH TOÁN CDS STORE
     ======================================================================== -->
<script>
// Đồng bộ trạng thái 10 Cờ từ PHP sang JavaScript Client
const APP_FLAGS = {
  customer_logged_in: <?= $current_customer ? 'true' : 'false' ?>,
  fast_checkout: <?= $is_fast_pay ? 'true' : 'false' ?>,
  dark: <?= $is_dark_mode ? 'true' : 'false' ?>,
  reviews: <?= $is_reviews ? 'true' : 'false' ?>,
  wishlist: <?= $is_wishlist ? 'true' : 'false' ?>,
  chat: <?= $is_chat ? 'true' : 'false' ?>,
  coupon: <?= $is_coupon ? 'true' : 'false' ?>,
  ai_recs: <?= $is_ai_recs ? 'true' : 'false' ?>,
  adv_filter: <?= $is_adv_filter ? 'true' : 'false' ?>,
  tracking: <?= $is_tracking ? 'true' : 'false' ?>,
  loyalty: <?= $is_loyalty ? 'true' : 'false' ?>,
  banner: <?= $is_banner ? 'true' : 'false' ?>
};

async function quickToggleDemoFlag(flagKey, envKey, targetEnabled) {
  try {
    const res = await fetch('api/toggle.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': '<?= e(get_csrf_token()) ?>'
      },
      body: JSON.stringify({
        flag_key: flagKey,
        environment: envKey,
        enabled: targetEnabled,
        user_id: '<?= e($current_user_id) ?>',
        csrf_token: '<?= e(get_csrf_token()) ?>'
      })
    });
    const data = await res.json();
    if (data && data.success) {
      window.location.reload();
    } else {
      alert((data && data.error) ? data.error : 'Không thể bật/tắt cờ tính năng.');
    }
  } catch (err) {
    alert('Lỗi kết nối khi bật/tắt cờ tính năng.');
  }
}

// Định dạng tiền tệ VNĐ chuẩn
function formatVND(num) {
  const n = Math.max(0, Math.round(Number(num) || 0));
  return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.') + 'đ';
}

function parseVND(priceStr) {
  return Number(String(priceStr || '').replace(/\D+/g, '')) || 0;
}

// --------------------------------------------------------------------------
// 1. BANNER SLIDER TỰ ĐỘNG CHUẨN CDS STORE
// --------------------------------------------------------------------------
let currentSlide = 0;
const totalSlides = <?= count($slider_banners) ?>;
let slideInterval = null;

function updateSlidePosition() {
  const slides = document.getElementById('tzSlides');
  if (slides) {
    slides.style.transform = `translateX(-${currentSlide * 100}%)`;
  }
  const dots = document.querySelectorAll('#tzSliderDots .tz-slider-dot');
  dots.forEach((dot, idx) => {
    dot.classList.toggle('active', idx === currentSlide);
  });
}

function nextSlide() {
  currentSlide = (currentSlide + 1) % totalSlides;
  updateSlidePosition();
}

function prevSlide() {
  currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
  updateSlidePosition();
}

function goToSlide(idx) {
  currentSlide = idx;
  updateSlidePosition();
  resetSlideTimer();
}

function resetSlideTimer() {
  if (slideInterval) clearInterval(slideInterval);
  slideInterval = setInterval(nextSlide, 4000);
}

resetSlideTimer();

// --------------------------------------------------------------------------
// 2. ĐẾM NGƯỢC GIỜ VÀNG FLASHSALE CDS STORE
// --------------------------------------------------------------------------
let fsSeconds = 1 * 3600 + 35 * 60 + 5;
setInterval(() => {
  if (fsSeconds > 0) fsSeconds--;
  const h = Math.floor(fsSeconds / 3600);
  const m = Math.floor((fsSeconds % 3600) / 60);
  const s = fsSeconds % 60;
  const hEl = document.getElementById('fsHour');
  const mEl = document.getElementById('fsMinute');
  const sEl = document.getElementById('fsSecond');
  if (hEl) hEl.textContent = String(h).padStart(2, '0');
  if (mEl) mEl.textContent = String(m).padStart(2, '0');
  if (sEl) sEl.textContent = String(s).padStart(2, '0');
}, 1000);

// --------------------------------------------------------------------------
// 3. TÌM KIẾM NÂNG CAO ĐA TIÊU CHÍ & BỘ LỌC NHANH 1-CHẠM
// --------------------------------------------------------------------------
const advancedSearchCards = () => Array.from(document.querySelectorAll('.tz-product-card[data-category]'));

function normalizeSearchText(value) {
  return String(value || '').toLocaleLowerCase('vi').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function escapeHtmlSafe(str) {
  if (typeof escapeHtml === 'function') return escapeHtml(str);
  return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function updateAdvancedSeriesOptions() {
  const select = document.getElementById('advancedSearchSeries');
  if (!select) return;
  const current = select.value;
  const series = [...new Set(advancedSearchCards().map(card => card.dataset.series).filter(Boolean))].sort();
  select.innerHTML = '<option value="all">Tất cả dòng máy</option>' + series.map(item => `<option value="${escapeHtmlSafe(item)}">${escapeHtmlSafe(item)}</option>`).join('');
  select.value = series.includes(current) ? current : 'all';
}

function applyQuickTagFilter(mode, btn) {
  const container = document.getElementById('advQuickTags');
  if (container) {
    container.querySelectorAll('.tz-adv-tag').forEach(t => t.classList.remove('active'));
  }
  if (btn) btn.classList.add('active');

  const minEl = document.getElementById('advancedSearchMinPrice');
  const maxEl = document.getElementById('advancedSearchMaxPrice');
  const rateEl = document.getElementById('advancedSearchRating');
  const newEl = document.getElementById('advancedSearchNewOnly');

  if (minEl) minEl.value = '';
  if (maxEl) maxEl.value = '';
  if (rateEl) rateEl.value = '0';
  if (newEl) newEl.checked = false;

  if (mode === 'new' && newEl) {
    newEl.checked = true;
  } else if (mode === 'under15' && maxEl) {
    maxEl.value = '15000000';
  } else if (mode === '15to30') {
    if (minEl) minEl.value = '15000000';
    if (maxEl) maxEl.value = '30000000';
  } else if (mode === 'over30' && minEl) {
    minEl.value = '30000000';
  } else if (mode === 'rate5' && rateEl) {
    rateEl.value = '5';
  }
  applyAdvancedSearch();
}

function applyAdvancedSearch() {
  const keyword = normalizeSearchText(document.getElementById('advancedSearchKeyword')?.value);
  const category = document.getElementById('advancedSearchCategory')?.value || 'all';
  const series = document.getElementById('advancedSearchSeries')?.value || 'all';
  const capacity = document.getElementById('advancedSearchCapacity')?.value || 'all';
  const minPrice = Number(document.getElementById('advancedSearchMinPrice')?.value || 0);
  const maxPrice = Number(document.getElementById('advancedSearchMaxPrice')?.value || 0);
  const minRating = Number(document.getElementById('advancedSearchRating')?.value || 0);
  const newOnly = Boolean(document.getElementById('advancedSearchNewOnly')?.checked);
  const sort = document.getElementById('advancedSearchSort')?.value || 'default';
  const cards = advancedSearchCards();
  let matched = 0;

  cards.forEach(card => {
    const name = normalizeSearchText(`${card.dataset.productName} ${card.dataset.capacities} ${card.dataset.series}`);
    const price = Number(card.dataset.price || 0);
    const rating = Number(card.dataset.rating || 0);
    const capacities = (card.dataset.capacities || '').split(',');
    const visible = (!keyword || name.includes(keyword))
      && (category === 'all' || card.dataset.category === category)
      && (series === 'all' || card.dataset.series === series)
      && (capacity === 'all' || capacities.includes(capacity))
      && (!minPrice || price >= minPrice)
      && (!maxPrice || price <= maxPrice)
      && rating >= minRating
      && (!newOnly || card.dataset.isNew === '1');
    card.style.display = visible ? 'flex' : 'none';
    if (visible) matched++;
  });

  document.querySelectorAll('.tz-product-grid').forEach(grid => {
    const hasVisibleCards = Array.from(grid.querySelectorAll('.tz-product-card')).some(card => card.style.display !== 'none');
    grid.closest('[id^="sec-"]')?.style.setProperty('display', hasVisibleCards ? '' : 'none');
  });

  const sortedCards = [...cards].sort((a, b) => {
    if (sort === 'price-asc') return Number(a.dataset.price) - Number(b.dataset.price);
    if (sort === 'price-desc') return Number(b.dataset.price) - Number(a.dataset.price);
    if (sort === 'rating-desc') return Number(b.dataset.rating) - Number(a.dataset.rating);
    if (sort === 'name-asc') return normalizeSearchText(a.dataset.productName).localeCompare(normalizeSearchText(b.dataset.productName), 'vi');
    return 0;
  });
  if (sort !== 'default') {
    sortedCards.forEach(card => card.parentElement?.appendChild(card));
  }

  const result = document.getElementById('advancedSearchResult');
  const empty = document.getElementById('advancedSearchEmpty');
  if (result) result.textContent = `Đang hiển thị ${matched}/${cards.length} sản phẩm Apple`;
  if (empty) empty.style.display = matched ? 'none' : 'block';
}

function resetAdvancedSearch() {
  ['advancedSearchKeyword', 'advancedSearchMinPrice', 'advancedSearchMaxPrice'].forEach(id => {
    const input = document.getElementById(id);
    if (input) input.value = '';
  });
  ['advancedSearchCategory', 'advancedSearchSeries', 'advancedSearchCapacity', 'advancedSearchRating', 'advancedSearchSort'].forEach(id => {
    const input = document.getElementById(id);
    if (input) input.value = id === 'advancedSearchRating' ? '0' : (id === 'advancedSearchSort' ? 'default' : 'all');
  });
  const newOnly = document.getElementById('advancedSearchNewOnly');
  if (newOnly) newOnly.checked = false;
  const container = document.getElementById('advQuickTags');
  if (container) {
    const tags = container.querySelectorAll('.tz-adv-tag');
    tags.forEach((t, i) => t.classList.toggle('active', i === 0));
  }
  applyAdvancedSearch();
}

function initAdvancedSearch() {
  if (!document.getElementById('advancedSearchKeyword')) return;
  updateAdvancedSeriesOptions();
  document.getElementById('advancedSearchKeyword')?.addEventListener('keydown', event => {
    if (event.key === 'Enter') {
      event.preventDefault();
      applyAdvancedSearch();
    }
  });
  ['advancedSearchCategory', 'advancedSearchSeries', 'advancedSearchCapacity', 'advancedSearchRating', 'advancedSearchSort'].forEach(id => {
    document.getElementById(id)?.addEventListener('change', () => applyAdvancedSearch());
  });
  applyAdvancedSearch();
}

initAdvancedSearch();

// --------------------------------------------------------------------------
// 4. LỌC PHÂN KHÚC SERIES THEO TỪNG DANH MỤC (IPHONE, MAC, IPAD, WATCH)
// --------------------------------------------------------------------------
function filterBySeries(series, btn, category) {
  const parent = btn.parentElement;
  if (parent) {
    parent.querySelectorAll('.tz-series-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  }

  let gridId = 'iphoneGrid';
  if (category === 'mac') gridId = 'macGrid';
  if (category === 'ipad') gridId = 'ipadGrid';
  if (category === 'watch') gridId = 'watchGrid';

  const grid = document.getElementById(gridId);
  if (!grid) return;

  const cards = grid.querySelectorAll('.tz-product-card');
  let matched = 0;
  cards.forEach(card => {
    const s = card.getAttribute('data-series');
    if (series === 'all' || s === series) {
      card.style.display = 'flex';
      matched++;
    } else {
      card.style.display = 'none';
    }
  });

  showToast(`Đã lọc danh sách: ${matched} sản phẩm hiển thị`, 'info');
}

function changeProductColor(productId, imgUrl, dotElement) {
  const img = document.getElementById(`img-${productId}`);
  if (img) {
    img.src = imgUrl;
  }
  const parent = dotElement.parentElement;
  if (parent) {
    parent.querySelectorAll('.tz-color-dot').forEach(d => d.classList.remove('active'));
    dotElement.classList.add('active');
  }
}

function selectCapacity(btn) {
  const parent = btn.parentElement;
  if (parent) {
    parent.querySelectorAll('.tz-cap-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  }
}

// --------------------------------------------------------------------------
// 5. HỆ THỐNG GIỎ HÀNG CHUYÊN NGHIỆP CDS STORE (XEM SẢN PHẨM, TĂNG GIẢM SL)
// --------------------------------------------------------------------------
let cartItems = [
  {
    id: 'ip18pm-256',
    name: 'iPhone 18 Pro Max 256GB',
    price: 41990000,
    img: 'assets/images/products/iphone-18-promax-titan.png',
    cap: '256GB • Titan Sa Mạc',
    qty: 1
  },
  {
    id: 'acc-magsafe',
    name: 'Sạc MagSafe Apple 25W Chính hãng',
    price: 1190000,
    img: 'assets/images/products/magsafe-charger.png',
    cap: 'Phụ kiện Apple VN/A',
    qty: 1
  }
];

function getCartTotalQty() {
  return cartItems.reduce((sum, item) => sum + item.qty, 0);
}

function getCartSubtotal() {
  return cartItems.reduce((sum, item) => sum + (item.price * item.qty), 0);
}

function getCartFinalTotal(subtotal) {
  if (subtotal <= 0) return 0;
  let total = subtotal;
  if (APP_FLAGS.coupon) total -= 1000000;
  if (APP_FLAGS.loyalty) total -= 245000;
  return Math.max(0, total);
}

function syncCartBadge() {
  const totalQty = getCartTotalQty();
  const badge = document.getElementById('cartBadgeCount');
  if (badge) badge.textContent = String(totalQty);
  const modalTag = document.getElementById('cartModalCountTag');
  if (modalTag) modalTag.textContent = `${totalQty} sản phẩm`;
}

function renderCartModal() {
  syncCartBadge();
  const container = document.getElementById('cartItemsContainer');
  if (!container) return;

  if (cartItems.length === 0) {
    container.innerHTML = `
      <div style="text-align: center; padding: 36px 20px; background: rgba(255,255,255,0.03); border: 1px dashed rgba(255,255,255,0.12); border-radius: 16px;">
        <div style="font-size: 2.6rem; margin-bottom: 8px;">🛒</div>
        <div style="font-size: 1rem; font-weight: 700; margin-bottom: 4px;">Giỏ hàng CDS của bạn đang trống</div>
        <div style="font-size: 0.82rem; color: #94a3b8;">Hãy chọn sản phẩm Apple yêu thích hoặc gọi <strong>0362481351</strong> để được tư vấn nhanh!</div>
      </div>
    `;
  } else {
    container.innerHTML = cartItems.map(item => `
      <div class="cds-cart-item-row">
        <img src="${escapeHtmlSafe(item.img)}" alt="${escapeHtmlSafe(item.name)}" class="cds-cart-thumb">
        <div style="flex: 1; min-width: 0;">
          <div style="font-weight: 700; font-size: 0.94rem; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${escapeHtmlSafe(item.name)}</div>
          <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <span style="font-size: 0.72rem; padding: 2px 8px; border-radius: 6px; background: rgba(41,151,255,0.14); color: #38bdf8; font-weight: 600;">${escapeHtmlSafe(item.cap || 'Chính hãng Apple')}</span>
            <span style="font-size: 0.88rem; font-weight: 800; color: #ff9f00;">${formatVND(item.price)}</span>
          </div>
        </div>
        <div class="cds-qty-control">
          <button type="button" class="cds-qty-btn" onclick="updateCartQty('${escapeHtmlSafe(item.id)}', -1)">&minus;</button>
          <span class="cds-qty-val">${item.qty}</span>
          <button type="button" class="cds-qty-btn" onclick="updateCartQty('${escapeHtmlSafe(item.id)}', 1)">+</button>
        </div>
        <div style="text-align: right; min-width: 105px;">
          <div style="font-weight: 800; font-size: 0.95rem; color: #fff;">${formatVND(item.price * item.qty)}</div>
          <button type="button" onclick="removeFromCart('${escapeHtmlSafe(item.id)}')" style="background: none; border: none; color: #f87171; font-size: 0.74rem; font-weight: 600; cursor: pointer; padding: 2px 0; margin-top: 2px;">✕ Xóa</button>
        </div>
      </div>
    `).join('');
  }

  const subtotal = getCartSubtotal();
  const finalTotal = getCartFinalTotal(subtotal);
  const qtyEl = document.getElementById('cartSummaryQty');
  const subEl = document.getElementById('cartSubtotalText');
  const totEl = document.getElementById('cartTotalText');
  const proceedBtn = document.getElementById('cartProceedBtn');

  if (qtyEl) qtyEl.textContent = String(getCartTotalQty());
  if (subEl) subEl.textContent = formatVND(subtotal);
  if (totEl) totEl.textContent = formatVND(finalTotal);
  if (proceedBtn) {
    proceedBtn.disabled = cartItems.length === 0;
    proceedBtn.style.opacity = cartItems.length === 0 ? '0.5' : '1';
  }
}

function addToCart(id, name, priceStr, img, cap = 'Chính hãng Apple VN/A') {
  const price = parseVND(priceStr);
  const existing = cartItems.find(item => item.id === id);
  if (existing) {
    existing.qty += 1;
  } else {
    cartItems.push({
      id: id || ('cds-' + Date.now()),
      name: name,
      price: price,
      img: img || 'assets/images/products/iphone-18-promax-titan.png',
      cap: cap,
      qty: 1
    });
  }
  renderCartModal();
  openModal('cartModal');
  showToast(`🛒 Đã thêm [${name}] vào giỏ hàng CDS!`, 'success');
}

function updateCartQty(id, delta) {
  const idx = cartItems.findIndex(item => item.id === id);
  if (idx === -1) return;
  cartItems[idx].qty += delta;
  if (cartItems[idx].qty <= 0) {
    const removedName = cartItems[idx].name;
    cartItems.splice(idx, 1);
    showToast(`Đã xóa [${removedName}] khỏi giỏ hàng`, 'info');
  }
  renderCartModal();
}

function removeFromCart(id) {
  const idx = cartItems.findIndex(item => item.id === id);
  if (idx === -1) return;
  const removedName = cartItems[idx].name;
  cartItems.splice(idx, 1);
  renderCartModal();
  showToast(`Đã xóa [${removedName}] khỏi giỏ hàng`, 'info');
}

function openCartView() {
  renderCartModal();
  openModal('cartModal');
}

function proceedCartToCheckout() {
  if (cartItems.length === 0) {
    showToast('Giỏ hàng đang trống, vui lòng chọn sản phẩm trước khi thanh toán!', 'warning');
    return;
  }
  if (!APP_FLAGS.customer_logged_in) {
    showToast('Bạn hãy đăng nhập để tiếp tục thanh toán.', 'info');
    window.location.href = 'store-auth.php?mode=login&theme=' + (APP_FLAGS.dark ? 'dark' : 'light');
    return;
  }
  closeModal('cartModal');
  const subtotal = getCartSubtotal();
  const firstItem = cartItems[0];
  const totalCount = getCartTotalQty();
  const summaryTitle = totalCount > 1
    ? `${firstItem.name} (+${totalCount - 1} sản phẩm khác trong giỏ)`
    : `${firstItem.name} (x${firstItem.qty})`;

  if (APP_FLAGS.fast_checkout) {
    openFastCheckout(firstItem.id, summaryTitle, formatVND(subtotal), firstItem.img);
  } else {
    openStandardCheckout(firstItem.id, summaryTitle, formatVND(subtotal), firstItem.img);
  }
}

// Khởi tạo badge giỏ hàng ngay khi tải trang
syncCartBadge();

// --------------------------------------------------------------------------
// 6. CỜ 1: THANH TOÁN 1 BƯỚC EXPRESS VS TIÊU CHUẨN 3 BƯỚC
// --------------------------------------------------------------------------
function selectPayCard(labelEl) {
  const grid = labelEl.parentElement;
  if (grid) {
    grid.querySelectorAll('.cds-pay-option').forEach(opt => opt.classList.remove('active'));
  }
  labelEl.classList.add('active');
  const radio = labelEl.querySelector('input[type="radio"]');
  if (radio) radio.checked = true;
}

function openFastCheckout(id, name, price, img) {
  const nameEl = document.getElementById('modalProductName');
  const priceEl = document.getElementById('modalProductPrice');
  const subEl = document.getElementById('fastModalSubPrice');
  const imgEl = document.getElementById('modalProductImg');
  const finalEl = document.getElementById('modalFinalPrice');

  if (nameEl) nameEl.textContent = name;
  if (priceEl) priceEl.textContent = price;
  if (subEl) subEl.textContent = price;
  if (imgEl && img) imgEl.src = img;

  const rawPrice = parseVND(price);
  const finalAmount = getCartFinalTotal(rawPrice);
  if (finalEl) finalEl.textContent = formatVND(finalAmount);

  openModal('oneStepCheckoutModal');
}

function goToStdStep(step) {
  [1, 2, 3].forEach(s => {
    const panel = document.getElementById(`stdStepPanel${s}`);
    const badge = document.getElementById(`stdStepBadge${s}`);
    if (panel) panel.style.display = (s === step) ? 'block' : 'none';
    if (badge) {
      if (s === step) {
        badge.style.background = '#2997ff';
        badge.style.color = '#ffffff';
      } else if (s < step) {
        badge.style.background = 'rgba(16, 185, 129, 0.2)';
        badge.style.color = '#34d399';
      } else {
        badge.style.background = 'rgba(255, 255, 255, 0.06)';
        badge.style.color = '#94a3b8';
      }
    }
  });
}

function openStandardCheckout(id, name, price, img) {
  const nameEl = document.getElementById('stdProductName');
  const priceEl = document.getElementById('stdProductPrice');
  const totalEl = document.getElementById('stdFinalTotalText');
  const imgEl = document.getElementById('stdProductImg');

  if (nameEl) nameEl.value = name;
  if (priceEl) priceEl.value = price;
  if (totalEl) totalEl.textContent = formatVND(getCartFinalTotal(parseVND(price)));
  if (imgEl && img) imgEl.src = img;

  goToStdStep(1);
  openModal('standardCheckoutModal');
}

function handleCheckoutSubmit(e) {
  e.preventDefault();
  closeModal('oneStepCheckoutModal');
  const orderCode = 'CDS-2026-' + Math.floor(10000 + Math.random() * 90000);
  const trackInput = document.getElementById('trackingCodeInput');
  if (trackInput) trackInput.value = orderCode;
  showToast(`🎉 Đặt hàng 1-Chạm thành công! Mã vận đơn: ${orderCode}. Shipper CDS sẽ gọi 0362481351 giao trong 1 giờ!`, 'success');
}

function handleStandardCheckoutSubmit() {
  closeModal('standardCheckoutModal');
  const orderCode = 'CDS-2026-' + Math.floor(10000 + Math.random() * 90000);
  const trackInput = document.getElementById('trackingCodeInput');
  if (trackInput) trackInput.value = orderCode;
  showToast(`✅ Đặt hàng tiêu chuẩn 3 bước thành công! Mã đơn: ${orderCode}. Tổng đài 0362481351 sẽ liên hệ xác nhận ngay!`, 'success');
}

// --------------------------------------------------------------------------
// 7. CỜ 4: DANH SÁCH YÊU THÍCH (WISHLIST)
// --------------------------------------------------------------------------
let wishlistItems = [];

function toggleWishlist(id, name, btn) {
  if (!APP_FLAGS.customer_logged_in) {
    showToast('Bạn hãy đăng nhập để lưu sản phẩm yêu thích.', 'info');
    window.location.href = 'store-auth.php?mode=login&theme=' + (APP_FLAGS.dark ? 'dark' : 'light');
    return;
  }
  const idx = wishlistItems.findIndex(i => i.id === id);
  if (idx >= 0) {
    wishlistItems.splice(idx, 1);
    btn.classList.remove('active');
    if (btn.classList.contains('tz-wishlist-btn')) btn.textContent = '♥';
    showToast(`Đã xóa [${name}] khỏi danh sách yêu thích!`, 'info');
  } else {
    wishlistItems.push({ id, name });
    btn.classList.add('active');
    if (btn.classList.contains('tz-wishlist-btn')) btn.textContent = '♥';
    showToast(`Đã thêm [${name}] vào danh sách yêu thích!`, 'success');
  }
  updateWishlistUI();
}

function updateWishlistUI() {
  const badge = document.getElementById('wishlistCountBadge');
  if (badge) {
    badge.textContent = wishlistItems.length;
    badge.style.display = wishlistItems.length > 0 ? 'inline-block' : 'none';
  }

  const emptyState = document.getElementById('wishlistEmptyState');
  const listContainer = document.getElementById('wishlistItemsList');
  if (!listContainer) return;

  if (wishlistItems.length === 0) {
    if (emptyState) emptyState.style.display = 'block';
    listContainer.innerHTML = '';
  } else {
    if (emptyState) emptyState.style.display = 'none';
    listContainer.innerHTML = wishlistItems.map(item => `
      <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.03); border-radius: 10px;">
        <span style="font-weight: 600;">♥ ${escapeHtmlSafe(item.name)}</span>
        <button type="button" class="btn btn-sm btn-danger" onclick="toggleWishlist('${escapeHtmlSafe(item.id)}', '${escapeHtmlSafe(item.name)}', this)">Bỏ thích</button>
      </div>
    `).join('');
  }
}

// --------------------------------------------------------------------------
// 8. CỜ 5: LIVECHAT CHUYÊN VIÊN APPLE 24/7
// --------------------------------------------------------------------------
function toggleLiveChat() {
  const popup = document.getElementById('liveChatPopup');
  if (popup) {
    popup.classList.toggle('show');
  }
}

function sendQuickReply(text) {
  appendMessage('user', text);
  setTimeout(() => {
    let reply = 'Cảm ơn quý khách! CDS STORE hiện có sẵn đầy đủ màu iPhone 18 Pro Max và giao nhanh trong 1 giờ. Hotline hỗ trợ trực tiếp: 0362481351 ạ!';
    if (text.includes('iPhone 18')) {
      reply = 'Dạ iPhone 18 Pro Max 256GB giá ưu đãi 41.990.000đ (giảm thêm 1.000.000đ khi nhập mã CDS18). Quý khách có thể gọi 0362481351 để giữ máy ạ!';
    } else if (text.includes('trả góp')) {
      reply = 'Dạ CDS STORE hỗ trợ trả góp 0% lãi suất duyệt hồ sơ online trong 3 phút, hoặc qua thẻ tín dụng hơn 25 ngân hàng ạ!';
    }
    appendMessage('bot', reply);
  }, 600);
}

function sendChatMessage() {
  const input = document.getElementById('chatInput');
  if (!input) return;
  const val = input.value.trim();
  if (!val) return;
  appendMessage('user', val);
  input.value = '';
  setTimeout(() => {
    appendMessage('bot', 'Dạ chuyên viên CDS STORE đã ghi nhận. Em đang kiểm tra kho máy sẵn và sẽ gọi lại cho bạn qua hotline 0362481351 ngay ạ!');
  }, 700);
}

function appendMessage(sender, text) {
  const container = document.getElementById('chatMessages');
  if (!container) return;
  const msgDiv = document.createElement('div');
  const isUser = (sender === 'user');
  msgDiv.style.padding = '0.75rem 1rem';
  msgDiv.style.borderRadius = isUser ? '12px 12px 2px 12px' : '12px 12px 12px 2px';
  msgDiv.style.maxWidth = '85%';
  msgDiv.style.alignSelf = isUser ? 'flex-end' : 'flex-start';
  msgDiv.style.background = isUser ? '#2563eb' : (APP_FLAGS.dark ? '#272733' : '#f1f5f9');
  msgDiv.style.color = isUser ? '#ffffff' : (APP_FLAGS.dark ? '#f4f4f5' : '#0f172a');
  msgDiv.textContent = text;
  container.appendChild(msgDiv);
  container.scrollTop = container.scrollHeight;
}

// --------------------------------------------------------------------------
// 9. CÁC HÀM TIỆN ÍCH KHÁC (TOAST, MODAL, TÌM KIẾM NHANH)
// --------------------------------------------------------------------------
function copyCouponCode(code) {
  navigator.clipboard.writeText(code).then(() => {
    showToast(`Đã sao chép mã ưu đãi [${code}]! Giảm ngay 1.000.000đ khi thanh toán tại CDS STORE.`, 'success');
  });
}

function openReviewsModal(name, rating, count) {
  document.getElementById('reviewModalTitle').textContent = `Đánh giá & Ảnh unbox thực tế: ${name}`;
  const scoreEl = document.getElementById('reviewModalScore');
  if (scoreEl) scoreEl.textContent = `${Number(rating).toFixed(1)} / 5.0`;
  const countEl = document.getElementById('reviewModalCount');
  if (countEl) countEl.textContent = `Dựa trên ${count} đánh giá đã xác thực mua tại CDS STORE`;
  const starsEl = document.getElementById('reviewModalStars');
  if (starsEl) {
    let starsHtml = '';
    for (let i = 1; i <= 5; i++) {
      const fill = (i <= Math.round(Number(rating))) ? '#faad14' : '#434343';
      starsHtml += `<svg viewBox="64 64 896 896" width="18" height="18" fill="${fill}"><path d="M908.1 353.1l-253.9-36.9L540.7 86.1c-3.1-6.3-8.2-11.4-14.5-14.5-15.8-7.8-35-1.3-42.9 14.5L369.8 316.2l-253.9 36.9c-7 1-13.4 4.3-18.3 9.3a32.05 32.05 0 0 0 .6 45.3l183.7 179.1-43.4 252.9a31.95 31.95 0 0 0 46.4 33.7L512 754l227.1 119.4c6.2 3.3 13.4 4.4 20.3 3.2 17.4-3 29.1-19.5 26.1-36.9l-43.4-252.9 183.7-179.1c5-4.9 8.3-11.3 9.3-18.3 2.7-17.5-9.5-33.7-27-36.3z"/></svg>`;
    }
    starsEl.innerHTML = starsHtml;
  }
  openModal('reviewsModal');
}

function searchTrackingOrder() {
  const code = (document.getElementById('trackingCodeInput')?.value || '').trim();
  if (!code) {
    showToast('Vui lòng nhập mã đơn hàng!', 'warning');
    return;
  }
  showToast(`Đang định vị đơn hàng [${code}] trên bản đồ CDS STORE Express... Hotline hỗ trợ: 0362481351`, 'info');
}

function openSearchModal() {
  const advSec = document.getElementById('sec-advanced-search');
  if (advSec) {
    advSec.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => {
      const kwInput = document.getElementById('advancedSearchKeyword');
      if (kwInput) kwInput.focus();
    }, 350);
    showToast('🔍 Đã di chuyển đến Bộ Lọc & Tìm Kiếm Nâng Cao CDS!', 'info');
  } else {
    showToast('Hãy bật cờ [tim_kiem_nang_cao] ở nút góc trái dưới để sử dụng Bộ lọc thông minh CDS!', 'info');
  }
}

function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('show');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('show');
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const accountButton = document.getElementById('storeAccountBtn');
  const accountDropdown = document.getElementById('storeAccountDropdown');
  if (!accountButton || !accountDropdown) return;
  accountButton.addEventListener('click', function (event) {
    event.stopPropagation();
    const isOpen = accountDropdown.classList.toggle('show');
    accountButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });
  document.addEventListener('click', function (event) {
    if (!accountDropdown.contains(event.target) && !accountButton.contains(event.target)) {
      accountDropdown.classList.remove('show');
      accountButton.setAttribute('aria-expanded', 'false');
    }
  });
});
</script>

<!-- Container hiển thị thông báo Toast -->
<div id="toast-container" class="toast-container" aria-live="polite"></div>

<!-- JavaScript chung của hệ thống -->
<script src="assets/js/app.js"></script>

<?php if ($flash): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      if (typeof showToast === 'function') {
        showToast(<?= json_encode($flash['message']) ?>, <?= json_encode($flash['type']) ?>);
      }
    });
  </script>
<?php endif; ?>

</body>
</html>
