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
require_login();

require_once __DIR__ . '/services/FeatureFlagService.php';

$page_title = 'CDS STORE - Cửa hàng Apple chính hãng (AAR)';

// --------------------------------------------------------------------------
// 1. THIẾT LẬP THÔNG SỐ KIỂM THỬ & KIỂM TRA PHÂN QUYỀN TRUY CẬP (RBAC)
// --------------------------------------------------------------------------
$current_auth_user = get_authenticated_user();
$auth_role = $current_auth_user['role'] ?? 'viewer';

// Chỉ vai trò Admin và Biên tập (Editor) mới được phép thấy và chỉnh sửa Testing Controller
$can_manage_controller = in_array($auth_role, ['admin', 'editor'], true);

if (!$can_manage_controller) {
    // Tài khoản Viewer: Môi trường luôn lấy theo hệ thống đang kích hoạt, không can thiệp controller thử nghiệm
    $current_env = get_active_env();
    $current_user_id = 'KH1';
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

// Danh sách 10 cờ tính năng đồng bộ với database
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
$is_dark_mode   = true;
// Keep the admin controller light regardless of the storefront's dark-mode flag.
$is_console_dark_mode = false;
$is_reviews     = !empty($eval_results['danh_gia_san_pham']['data']['enabled']);
$is_wishlist    = !empty($eval_results['danh_sach_yeu_thich']['data']['enabled']);
$is_chat        = !empty($eval_results['ho_tro_truc_tuyen']['data']['enabled']);
$is_coupon      = !empty($eval_results['ma_giam_gia']['data']['enabled']);
$is_ai_recs     = !empty($eval_results['goi_y_san_pham']['data']['enabled']);
$is_adv_filter  = !empty($eval_results['tim_kiem_nang_cao']['data']['enabled']);
$is_tracking    = !empty($eval_results['theo_doi_don_hang']['data']['enabled']);
$is_loyalty     = !empty($eval_results['tich_diem_thanh_vien']['data']['enabled']);

// --------------------------------------------------------------------------
// 3. DỮ LIỆU BANNER QUẢNG CÁO CDS STORE (HTTP 200 VERIFIED)
// --------------------------------------------------------------------------
$slider_banners = [
    [
        'img' => 'https://cdnv2.tgdd.vn/mwg-static/topzone/Banner/97/59/97591228f3cd3b267f752510f0b56174.jpg',
        'title' => 'iPhone 18 Pro Max - Đỉnh cao công nghệ Apple tại CDS STORE',
        'link' => '#sec-iphone'
    ],
    [
        'img' => 'https://cdnv2.tgdd.vn/mwg-static/topzone/Banner/52/3c/523c7e22d4a088b679b8ee2ded7fedbd.png',
        'title' => 'MacBook Air M3 - Mỏng nhẹ đỉnh cao, pin bứt phá 18 tiếng',
        'link' => '#sec-mac'
    ],
    [
        'img' => 'https://cdnv2.tgdd.vn/mwg-static/topzone/Banner/52/5d/525d6171316849033744cec9f6995ea1.png',
        'title' => 'iPad Pro M4 - Màn hình OLED Tandem siêu mỏng ấn tượng',
        'link' => '#sec-ipad'
    ],
    [
        'img' => 'https://cdnv2.tgdd.vn/mwg-static/topzone/Banner/e1/36/e136eda2841525dd57c710963477c8d3.png',
        'title' => 'Apple Watch Series 10 - Màn hình lớn nhất, vỏ mỏng nhất',
        'link' => '#sec-watch'
    ],
];

// Danh mục nhanh (Choose Category Icons)
$quick_categories = [
    ['name' => 'iPhone', 'img' => 'https://cdnv2.tgdd.vn/webmwg/2024/tz/images/desktop/IP_Desk_26.png', 'target' => 'sec-iphone'],
    ['name' => 'Mac', 'img' => 'https://cdnv2.tgdd.vn/webmwg/2024/tz/images/desktop/Mac_Desk_25.png', 'target' => 'sec-mac'],
    ['name' => 'iPad', 'img' => 'https://cdnv2.tgdd.vn/webmwg/2024/tz/images/desktop/Ipad_Desk_25.png', 'target' => 'sec-ipad'],
    ['name' => 'Watch', 'img' => 'https://cdnv2.tgdd.vn/webmwg/2024/tz/images/desktop/Watch_Desk_26.png', 'target' => 'sec-watch'],
    ['name' => 'Tai nghe, Loa', 'img' => 'https://cdnv2.tgdd.vn/webmwg/2024/tz/images/desktop/Speaker_Desk_25.png', 'target' => 'sec-audio'],
    ['name' => 'Phụ kiện', 'img' => 'https://cdnv2.tgdd.vn/webmwg/2024/tz/images/desktop/Phukien_Desk_25.png', 'target' => 'sec-accessories'],
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
            ['name' => 'Băng Thanh (Titanium)', 'hex' => '#9ab2c9', 'img' => 'https://cdn.topzone.vn/Products/Images/42/370982/s16/iphone-18-pro-max-thumb-650x650.png'],
            ['name' => 'Đen Bóng', 'hex' => '#212124', 'img' => 'https://cdn.topzone.vn/Products/Images/42/370983/s16/iphone-18-512gb-den-650x650.png'],
            ['name' => 'Đỏ Ruby', 'hex' => '#9e2a2b', 'img' => 'https://cdn.topzone.vn/Products/Images/42/370985/s16/iphone-18-promax-do-thumb-650x650.png'],
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
            ['name' => 'Băng Thanh (Titanium)', 'hex' => '#9ab2c9', 'img' => 'https://cdn.topzone.vn/Products/Images/42/370977/s16/iphone-18-pro-bang-thanh-thumb-650x650.png'],
            ['name' => 'Bạc Titanium', 'hex' => '#e3e4e5', 'img' => 'https://cdn.topzone.vn/Products/Images/42/370978/s16/iphone-18-pro-512gb-bac-thumb-650x650.png'],
            ['name' => 'Đỏ Ruby', 'hex' => '#9e2a2b', 'img' => 'https://cdn.topzone.vn/Products/Images/42/370979/s16/iphone-18-pro-1tb-do-thumb-650x650.png'],
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
            ['name' => 'Đen Bóng', 'hex' => '#212124', 'img' => 'https://cdn.topzone.vn/Products/Images/42/370983/s16/iphone-18-512gb-den-650x650.png'],
            ['name' => 'Băng Thanh', 'hex' => '#9ab2c9', 'img' => 'https://cdn.topzone.vn/Products/Images/42/370982/s16/iphone-18-pro-max-thumb-650x650.png'],
            ['name' => 'Đỏ Ruby', 'hex' => '#9e2a2b', 'img' => 'https://cdn.topzone.vn/Products/Images/42/370985/s16/iphone-18-promax-do-thumb-650x650.png'],
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
            ['name' => 'Xanh Lưu Ly', 'hex' => '#7b9cb8', 'img' => 'https://cdn.topzone.vn/Products/Images/42/329138/s16/iphone-16-plus-xanh-luu-ly-thumbnew-650x650.png'],
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
            ['name' => 'Hồng Pastel', 'hex' => '#f5cbd6', 'img' => 'https://cdn.topzone.vn/Products/Images/42/329135/s16/iphone-16-hong-1-650x650.png'],
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
            ['name' => 'Hồng', 'hex' => '#f7d3d9', 'img' => 'https://cdn.topzone.vn/Products/Images/42/281570/s16/iphone-15-pink-1-2-650x650.png'],
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
            ['name' => 'Tím', 'hex' => '#e5d7f2', 'img' => 'https://cdn.topzone.vn/Products/Images/42/240259/s16/iphone-14-tim-thumb-650x650.png'],
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
            ['name' => 'Xanh Ngọc', 'hex' => '#a7d5c9', 'img' => 'https://cdn.topzone.vn/Products/Images/42/342670/s16/iphone-air-xanh-thumb_0-650x650.png'],
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
        'image' => 'https://cdn.topzone.vn/Products/Images/44/363487/s16/macbook-air-13-inch-m5-16gb-512gb-blue-thumb-650x650.png',
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
        'image' => 'https://cdn.topzone.vn/Products/Images/44/363488/s16/macbook-pro-14-inch-m5-pro-24gb-1tb-den-thumb-650x650.png',
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
        'image' => 'https://cdn.topzone.vn/Products/Images/44/363491/s16/macbook-pro-16-inch-m5-pro-24gb-1tb-bac-thumb-1-2-650x650.png',
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
        'image' => 'https://cdn.topzone.vn/Products/Images/5698/331494/s16/mac-mini-m4-16gb-512gb-thumb-11-650x650.png',
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
        'image' => 'https://cdn.topzone.vn/Products/Images/5698/331480/s16/imac-24-inch-m4-xanh-duong-thumb-1-650x650.png',
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
        'image' => 'https://cdn.topzone.vn/Products/Images/44/363477/s16/macbook-air-15-inch-m5-16gb-512gb-vang-thumb-650x650.png',
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
        'image' => 'https://cdn.topzone.vn/Products/Images/522/363417/s16/ipad-air-m4-11-inch-wifi-128gb-xam-thumb-650x650.png',
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
        'image' => 'https://cdn.topzone.vn/Products/Images/522/363417/s16/ipad-air-m4-11-inch-wifi-128gb-xam-thumb-650x650.png',
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
        'image' => 'https://cdn.topzone.vn/Products/Images/522/335308/s16/ipad-11-wifi-yellow-thumb-650x650.png',
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
        'image' => 'https://cdn.topzone.vn/Products/Images/522/331229/s16/ipad-mini-7-wifi-purple-thumbtz-650x650.png',
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
        'image' => 'https://cdn.topzone.vn/Products/Images/7077/344752/s16/apple-watch-series-11-gps-cellular-42mm-vien-nhom-day-the-thao-den-thumb-650x650.png',
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
        'image' => 'https://cdn.topzone.vn/Products/Images/7077/344758/s16/apple-watch-series-11-gps-cellular-vien-titanium-day-the-thao-vamg-thumb-650x650.png',
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
        'image' => 'https://cdn.topzone.vn/Products/Images/7077/344758/s16/apple-watch-series-11-gps-cellular-vien-titanium-day-the-thao-vamg-thumb-650x650.png',
        'capacities' => ['49mm Titan Đen', '49mm Titan Tự Nhiên'],
        'specs' => 'Khung Titan chuẩn quân đội, Lặn sâu 100m, Thời lượng pin 72 giờ'
    ]
];

$recommendations = [
    [
        'id' => 'airpods_max',
        'name' => 'AirPods Max cổng USB-C',
        'category' => 'audio',
        'price' => '10.990.000đ',
        'old_price' => '14.990.000đ',
        'discount' => '-26%',
        'icon' => '🎧',
        'image' => 'https://cdn.topzone.vn/Products/Images/54/329161/s16/airpods-max-cong-usb-c-cam-650x650.png',
        'desc' => 'Âm thanh không gian cá nhân hóa & Chống ồn chủ động đỉnh cao'
    ],
    [
        'id' => 'airpods_4',
        'name' => 'AirPods 4 Chống Ồn Chủ Động',
        'category' => 'audio',
        'price' => '4.490.000đ',
        'old_price' => '4.790.000đ',
        'discount' => '-6%',
        'icon' => '🎵',
        'image' => 'https://cdn.topzone.vn/Products/Images/54/329161/s16/airpods-max-cong-usb-c-cam-650x650.png',
        'desc' => 'Chip H2, Chế độ Xuyên Âm thông minh & Hộp sạc tích hợp loa tìm kiếm'
    ],
    [
        'id' => 'sac_apple_20w',
        'name' => 'Củ sạc nhanh Apple 20W Type-C Chính Hãng',
        'category' => 'phukien',
        'price' => '490.000đ',
        'old_price' => '590.000đ',
        'discount' => '-17%',
        'icon' => '⚡',
        'image' => 'https://cdnv2.tgdd.vn/webmwg/2024/tz/images/desktop/Phukien_Desk_25.png',
        'desc' => 'Chuẩn sạc nhanh Power Delivery bảo vệ pin tối ưu'
    ],
    [
        'id' => 'cap_apple_typec',
        'name' => 'Cáp Sạc Bện Dù Apple USB-C 60W (1m)',
        'category' => 'phukien',
        'price' => '490.000đ',
        'old_price' => '590.000đ',
        'discount' => '-17%',
        'icon' => '🔌',
        'image' => 'https://cdnv2.tgdd.vn/webmwg/2024/tz/images/desktop/Phukien_Desk_25.png',
        'desc' => 'Dây bện dù siêu bền, tương thích mọi thiết bị Apple Type-C'
    ],
];

// Flashsale items
$flashsale_items = [
    [
        'name' => 'iPhone 18 Pro Max 256GB Băng Thanh',
        'price' => '41.990.000đ',
        'old_price' => '44.990.000đ',
        'discount' => '-7%',
        'img' => 'https://cdn.topzone.vn/Products/Images/42/370982/s16/iphone-18-pro-max-thumb-650x650.png',
        'sold' => 'Còn 3/5',
        'percent' => 60
    ],
    [
        'name' => 'MacBook Air 13 inch M3 16GB/512GB',
        'price' => '32.990.000đ',
        'old_price' => '35.290.000đ',
        'discount' => '-7%',
        'img' => 'https://cdn.topzone.vn/Products/Images/44/363487/s16/macbook-air-13-inch-m5-16gb-512gb-blue-thumb-650x650.png',
        'sold' => 'Còn 4/5',
        'percent' => 80
    ],
    [
        'name' => 'iPad mini 7 WiFi 128GB Tím',
        'price' => '13.590.000đ',
        'old_price' => '14.990.000đ',
        'discount' => '-9%',
        'img' => 'https://cdn.topzone.vn/Products/Images/522/331229/s16/ipad-mini-7-wifi-purple-thumbtz-650x650.png',
        'sold' => 'Còn 2/3',
        'percent' => 67
    ],
    [
        'name' => 'AirPods Max cổng USB-C Cam',
        'price' => '10.990.000đ',
        'old_price' => '14.990.000đ',
        'discount' => '-26%',
        'img' => 'https://cdn.topzone.vn/Products/Images/54/329161/s16/airpods-max-cong-usb-c-cam-650x650.png',
        'sold' => 'Còn 1/3',
        'percent' => 33
    ]
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- ========================================================================
     CSS THIẾT KẾ ĐẶC TRƯNG CHUẨN XÁC CDS STORE (APPLE STOREFRONT)
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

/* Nền khung ứng dụng demo CDS STORE chuẩn màu #3e3e3f */
.topzone-wrapper {
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  transition: all 0.3s ease;
  border-radius: 18px;
  overflow: hidden;
  border: 1px solid <?= $is_dark_mode ? '#2c2c2f' : '#e2e8f0' ?>;
  background: <?= $is_dark_mode ? '#3e3e3f' : '#f8fafc' ?>;
  color: <?= $is_dark_mode ? '#f5f5f7' : '#0f172a' ?>;
  box-shadow: 0 10px 40px rgba(0,0,0,<?= $is_dark_mode ? '0.7' : '0.08' ?>);
}

/* CDS STORE Navigation Bar chuẩn Header đen #101010 */
.tz-navbar {
  background: #101010 !important;
  border-bottom: 1px solid #232323;
  padding: 0 1.5rem;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.25rem;
  position: sticky;
  top: 0;
  z-index: 100;
}

.tz-brand {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  text-decoration: none;
  color: #ffffff;
}

.tz-brand-logo {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 1.35rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: #ffffff;
  text-transform: uppercase;
}

.tz-brand-divider {
  height: 22px;
  width: 1px;
  background: #3b3b3b;
  margin: 0 0.4rem;
}

.tz-brand-sub {
  display: flex;
  flex-direction: column;
  line-height: 1.1;
}

.tz-brand-sub span {
  font-size: 0.58rem;
  font-weight: 800;
  color: #a1a1aa;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

/* Menu CDS STORE chính hãng */
.tz-nav-links {
  display: flex;
  align-items: center;
  gap: 2px;
  list-style: none;
  margin: 0;
  padding: 0;
  height: 60px;
}

.tz-nav-links a {
  text-decoration: none;
  font-size: 14px;
  line-height: 21px;
  font-weight: 500;
  color: #e5e5e5;
  padding: 19px 12px 18px;
  border-radius: 4px;
  transition: all 0.2s ease;
  display: block;
}

.tz-nav-links a:hover {
  background: #2d2d2d;
  color: #ffffff;
}

.tz-nav-links a.active {
  background: #2d2d2d;
  color: #2997ff;
  font-weight: 700;
}

.tz-header-tools {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

/* Các nút tròn đen phong cách CDS STORE (#2f3033) */
.tz-round-btn {
  background: #2f3033;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  position: relative;
  transition: background 0.2s ease;
  border: none;
  color: #ffffff;
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

/* Slider banner */
.tz-slider-container {
  position: relative;
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  overflow: hidden;
  background: #000000;
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
}

.tz-slider-btn {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  background: rgba(41, 41, 42, 0.7);
  color: #ffffff;
  border: none;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background 0.2s ease;
  z-index: 10;
}

.tz-slider-btn:hover {
  background: #757575;
}

.tz-slider-prev { left: 16px; }
.tz-slider-next { right: 16px; }

.tz-slider-dots {
  position: absolute;
  bottom: 12px;
  left: 0;
  right: 0;
  display: flex;
  justify-content: center;
  gap: 8px;
  z-index: 10;
}

.tz-slider-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #c7c7c7;
  border: 1px solid #fff;
  cursor: pointer;
  transition: all 0.2s ease;
}

.tz-slider-dot.active {
  background: #ffffff;
  border: 2px solid #2997ff;
  transform: scale(1.2);
}

/* Flashsale shock block */
.tz-flashsale-bar {
  max-width: 1200px;
  margin: 1.5rem auto 1rem;
  background: #323232;
  border-radius: 16px;
  padding: 1rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 1rem;
  border: 1px solid #444448;
}

.tz-fs-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.tz-fs-timer {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-weight: 800;
  color: #ffffff;
  font-size: 0.95rem;
}

.tz-fs-timer span.box {
  background: #101010;
  color: #ffbe0b;
  padding: 0.2rem 0.45rem;
  border-radius: 6px;
  font-family: monospace;
  font-size: 1.05rem;
}

.tz-fs-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1rem;
  max-width: 1200px;
  margin: 0 auto 2rem;
  padding: 0 1rem;
}

.tz-fs-card {
  background: #323232;
  border-radius: 16px;
  padding: 1rem;
  border: 1px solid #444448;
  display: flex;
  gap: 0.75rem;
  align-items: center;
  transition: transform 0.2s ease;
}

.tz-fs-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 20px rgba(0,0,0,0.4);
}

.tz-fs-card img {
  width: 75px;
  height: 75px;
  object-fit: contain;
}

/* Category Icons */
.tz-choose-cate {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 2.5rem auto 3rem;
  max-width: 1200px;
  padding: 0 1rem;
  gap: 1rem;
  overflow-x: auto;
}

.tz-cate-item {
  flex: 1;
  min-width: 140px;
  background: #323232;
  border-radius: 16px;
  height: 180px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  color: #ffffff;
  transition: all 0.2s ease;
  border: 1px solid #444448;
  position: relative;
  padding: 1rem;
}

.tz-cate-item:hover {
  box-shadow: -4px -4px 8px rgba(134,134,134,.2), 4px 4px 8px rgba(28,28,28,.4);
  transform: translateY(-4px);
  border-color: #2997ff;
}

.tz-cate-item img {
  max-width: 100px;
  height: 85px;
  object-fit: contain;
  margin-bottom: 0.75rem;
  transition: transform 0.2s ease;
}

.tz-cate-item:hover img {
  transform: scale(1.08);
}

.tz-cate-item span {
  font-size: 15px;
  font-weight: 700;
  text-align: center;
}

/* Section Header */
.tz-section-header {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.65rem;
  margin: 3.5rem auto 1.5rem;
  text-align: center;
}

.tz-section-header h2 {
  color: #ffffff;
  font-size: 34px;
  font-weight: 700;
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
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

/* Product Grid */
.tz-product-grid {
  max-width: 1200px;
  margin: 0 auto 3.5rem;
  padding: 0 1rem;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 1.5rem;
}

.tz-product-card {
  background: <?= $is_dark_mode ? '#323232' : '#ffffff' ?>;
  border: 1px solid <?= $is_dark_mode ? '#444448' : '#e2e8f0' ?>;
  border-radius: 20px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: all 0.25s ease;
  position: relative;
}

.tz-product-card:hover {
  transform: translateY(-6px);
  box-shadow: -4px -4px 10px rgba(134,134,134,.15), 4px 6px 16px rgba(28,28,28,.45);
  border-color: <?= $is_dark_mode ? '#5e5e60' : '#cbd5e1' ?>;
}

.tz-product-thumb {
  position: relative;
  padding: 1.5rem 1rem 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 250px;
}

.tz-product-thumb img {
  max-width: 220px;
  max-height: 220px;
  object-fit: contain;
  transition: transform 0.3s ease;
}

.tz-product-card:hover .tz-product-thumb img {
  transform: scale(1.05);
}

.tz-badge-new {
  position: absolute;
  top: 1rem;
  left: 1rem;
  background: #0071e3;
  color: #ffffff;
  font-size: 0.7rem;
  font-weight: 800;
  padding: 0.2rem 0.55rem;
  border-radius: 6px;
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.tz-wishlist-btn {
  position: absolute;
  top: 1rem;
  right: 1rem;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.2);
  width: 34px;
  height: 34px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #ffffff;
  transition: all 0.2s ease;
  font-size: 1.1rem;
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
  margin: 0.5rem 0;
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
  gap: 0.4rem;
  margin: 0.65rem 0 0.25rem 0;
  flex-wrap: wrap;
  justify-content: center;
}

.tz-cap-btn {
  padding: 0.25rem 0.65rem;
  font-size: 0.75rem;
  font-weight: 700;
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
  padding: 1rem 1.25rem 1.25rem;
  display: flex;
  flex-direction: column;
  flex: 1;
  justify-content: space-between;
}

.tz-card-title {
  font-size: 1.05rem;
  font-weight: 700;
  margin-bottom: 0.35rem;
  line-height: 1.3;
  color: <?= $is_dark_mode ? '#ffffff' : '#0f172a' ?>;
}

.tz-price-box {
  display: flex;
  align-items: baseline;
  gap: 0.5rem;
  margin-bottom: 0.45rem;
  flex-wrap: wrap;
}

.tz-price-main {
  font-size: 1.25rem;
  font-weight: 800;
  color: <?= $is_dark_mode ? '#ffffff' : '#0f172a' ?>;
}

.tz-price-old {
  font-size: 0.85rem;
  color: <?= $is_dark_mode ? '#a1a1aa' : '#94a3b8' ?>;
  text-decoration: line-through;
}

.tz-discount-badge {
  background: #ff361d;
  color: #ffffff;
  font-size: 0.7rem;
  font-weight: 800;
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
}

.tz-rating-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.65rem;
  font-size: 0.78rem;
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
  padding: 0.65rem;
  border-radius: 12px;
  font-size: 0.92rem;
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

/* Commitments */
.tz-commitments {
  max-width: 1200px;
  margin: 3rem auto;
  padding: 2rem 1.5rem;
  background: #323232;
  border-radius: 20px;
  border: 1px solid #444448;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1.5rem;
}

.tz-commit-item {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.tz-commit-icon {
  font-size: 2rem;
  width: 50px;
  height: 50px;
  background: #252528;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

/* Footer */
.tz-footer {
  background: #101010;
  border-top: 1px solid #232323;
  padding: 3rem 1.5rem 1.5rem;
  color: #a1a1aa;
  font-size: 13px;
  line-height: 1.8;
}

.tz-foot-container {
  max-width: 1200px;
  margin: 0 auto;
}

.tz-foot-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 2rem;
  border-bottom: 1px solid #232323;
  padding-bottom: 2rem;
  margin-bottom: 2rem;
}

.tz-foot-col h4 {
  color: #ffffff;
  font-size: 15px;
  font-weight: 700;
  margin-bottom: 0.85rem;
}

.tz-foot-col a {
  color: #d4d4d8;
  text-decoration: none;
  display: block;
  transition: color 0.2s ease;
}

.tz-foot-col a:hover {
  color: #2997ff;
}

.tz-foot-col strong {
  color: #2997ff;
}

/* Livechat */
.tz-livechat-widget {
  position: fixed;
  bottom: 25px;
  right: 25px;
  z-index: 999;
}

.tz-chat-btn {
  background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
  color: #ffffff;
  border: none;
  border-radius: 9999px;
  padding: 0.75rem 1.25rem;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 10px 25px rgba(14, 165, 233, 0.45);
  display: flex;
  align-items: center;
  gap: 0.6rem;
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
</style>

<div class="page-header">
  <div>
    <h1 class="page-title">CDS STORE - Cửa hàng Apple chính hãng 100%</h1>
    <p class="page-description">
      Hệ thống bán lẻ ủy quyền chính thức <strong style="color: #2997ff;">CDS STORE (Apple Authorised Reseller)</strong>. Trải nghiệm iPhone 18 Series, Mac, iPad, Watch tích hợp 10 cờ tính năng Feature Flags.
    </p>
  </div>
  <div class="page-actions">
    <a href="flags.php" class="btn btn-secondary">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
        <polyline points="2 17 12 22 22 17"></polyline>
        <polyline points="2 12 12 17 22 12"></polyline>
      </svg>
      <span>Quản lý 10 Cờ tính năng</span>
    </a>
  </div>
</div>

<!-- ========================================================================
     BẢNG ĐIỀU KHIỂN THỬ NGHIỆM FEATURE FLAG (TESTING CONTROLLER)
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

$enabled_count = 0;
foreach ($eval_results as $res) {
    if (!empty($res['data']['enabled'])) {
        $enabled_count++;
    }
}
$total_count = count($eval_results);
?>
<div class="ff-console-panel">
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
          <span>⚠️</span> ĐANG MÔ PHỎNG LỖI API (FALLBACK FALSE)
        </span>
      <?php else: ?>
        <span style="background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 20px; font-weight: 600; padding: 4px 12px; font-size: 0.78rem; display: inline-flex; align-items: center; gap: 6px;">
          <span class="ff-pulse-dot"></span> ĐỘNG CƠ EVAL SẴN SÀNG
        </span>
      <?php endif; ?>
      <span style="background: rgba(255, 255, 255, 0.1); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 20px; font-weight: 700; padding: 4px 12px; font-size: 0.78rem; font-family: ui-monospace, monospace;">
        <span style="color: #38bdf8;"><?= $enabled_count ?></span>/<?= $total_count ?> CỜ BẬT
      </span>
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
          <option value="testing" <?= ($current_env === 'testing') ? 'selected' : '' ?>>🧪 Kiểm thử (Testing)</option>
          <option value="staging" <?= ($current_env === 'staging') ? 'selected' : '' ?>>🚀 Vận hành (Staging)</option>
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
        <span>Kết quả đánh giá 10 cờ tính năng cho [ <strong style="color: #0284c7;"><?= e($current_user_id) ?></strong> &bull; <strong style="color: #2563eb;"><?= strtoupper(e($current_env)) ?></strong> ]:</span>
      </div>
      <div style="font-size: 0.78rem; color: #94a3b8; display: flex; align-items: center; gap: 4px;">
        <span>Nhấp vào mã cờ để xem chi tiết hoặc</span>
        <a href="flags.php" style="color: #0284c7; text-decoration: none; font-weight: 600;">quản lý tất cả cờ &rarr;</a>
      </div>
    </div>

    <div class="ff-grid-10">
      <?php 
      $flag_ui_labels = [
          'thanh_toan_nhanh'      => ['title' => 'Nút Mua 1-Chạm CDS STORE Express', 'icon' => '⚡'],
          'giao_dien_toi'         => ['title' => 'Giao diện than chì CDS STORE Dark Mode', 'icon' => '🌙'],
          'danh_gia_san_pham'     => ['title' => 'Đánh giá sao & ảnh thực tế unbox', 'icon' => '⭐'],
          'danh_sach_yeu_thich'   => ['title' => 'Lưu sản phẩm yêu thích (Wishlist)', 'icon' => '❤️'],
          'ho_tro_truc_tuyen'     => ['title' => 'Chuyên viên Apple CDS STORE 24/7', 'icon' => '💬'],
          'ma_giam_gia'           => ['title' => 'Tự động áp voucher CDS18', 'icon' => '🔥'],
          'goi_y_san_pham'        => ['title' => 'Gợi ý combo phụ kiện Apple bằng AI', 'icon' => '✨'],
          'tim_kiem_nang_cao'     => ['title' => 'Bộ lọc đa tiêu chí (Model, Giá, GB)', 'icon' => '🔍'],
          'theo_doi_don_hang'     => ['title' => 'Tra cứu đơn hàng thời gian thực', 'icon' => '🚚'],
          'tich_diem_thanh_vien'  => ['title' => 'Tích điểm hội viên CDS STORE Member', 'icon' => '💎'],
      ];

      foreach ($eval_results as $f_key => $res): 
          $en = !empty($res['data']['enabled']);
          $reason = $res['data']['reason'] ?? '';
          $flag_info = $flag_ui_labels[$f_key] ?? ['title' => $f_key, 'icon' => '🚩'];
          $is_highlight = ($highlight_flag === $f_key);
      ?>
        <div class="ff-card-item <?= $en ? 'enabled' : 'disabled' ?> <?= $is_highlight ? 'highlight' : '' ?>">
          <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
            <a href="flag-detail.php?key=<?= urlencode($f_key) ?>" class="ff-card-key" title="Xem chi tiết cờ <?= e($f_key) ?>">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
              <span><?= e($f_key) ?></span>
            </a>
            <div style="display: flex; align-items: center; gap: 0.35rem;">
              <span class="ff-badge-status <?= $en ? 'on' : 'off' ?>">
                <span><?= $en ? '●' : '○' ?></span>
                <?= $en ? 'ENABLED' : 'DISABLED' ?>
              </span>
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
<?php endif; ?>

<!-- ========================================================================
     GIAO DIỆN CDS STORE STOREFRONT CHÍNH THỨC (AAR)
     ======================================================================== -->
<div class="topzone-wrapper">

  <!-- CDS STORE HEADER NAVIGATION CHUẨN ĐEN #101010 -->
  <nav class="tz-navbar">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
      <!-- Logo CDS STORE: Quả táo Apple + CDS STORE + Divider + Apple Authorised Reseller -->
      <a href="demo-app.php?env=<?= urlencode($current_env) ?>&user_id=<?= urlencode($current_user_id) ?>" class="tz-brand">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="#ffffff">
          <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.47c.68-.83 1.14-1.99.99-3.14-.99.04-2.19.66-2.9 1.49-.63.73-1.18 1.92-1.03 3.06 1.1.09 2.24-.58 2.94-1.41z"/>
        </svg>
        <span class="tz-brand-logo">cds<span style="font-weight: 300;">store</span></span>
        <div class="tz-brand-divider"></div>
        <div class="tz-brand-sub">
          <span>Apple Authorised</span>
          <span>Reseller</span>
        </div>
      </a>

      <!-- Menu Apple Categories CDS STORE -->
      <ul class="tz-nav-links">
        <li><a href="#sec-iphone" class="active">iPhone</a></li>
        <li><a href="#sec-mac">Mac</a></li>
        <li><a href="#sec-ipad">iPad</a></li>
        <li><a href="#sec-watch">Watch</a></li>
        <li><a href="#sec-audio">Tai nghe, Loa</a></li>
        <li><a href="#sec-accessories">Phụ kiện</a></li>
        <li><a href="javascript:;" onclick="showToast('TekZone: Chuyên trang tin tức Apple CDS STORE', 'info')">TekZone</a></li>
        <li><a href="javascript:;" onclick="showToast('CDS Care: Trung tâm bảo hành ủy quyền Apple chính hãng', 'info')">CDS Care</a></li>
      </ul>
    </div>

    <!-- Header Tools (Tích hợp các Cờ tính năng + Nút tròn #2f3033 chuẩn CDS STORE) -->
    <div class="tz-header-tools">
      
      <!-- CỜ 9: THEO DÕI ĐƠN HÀNG THỜI GIAN THỰC (theo_doi_don_hang) -->
      <?php if ($is_tracking): ?>
        <button type="button" class="btn btn-ghost btn-sm" style="color: #2997ff; display: flex; align-items: center; gap: 0.35rem; padding: 0.35rem 0.65rem;" onclick="openModal('trackingModal')">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="1" y="3" width="15" height="13"></rect>
            <polygon points="16 8 20 8 23 11 23 16 16 8"></polygon>
            <circle cx="5.5" cy="18.5" r="2.5"></circle>
            <circle cx="18.5" cy="18.5" r="2.5"></circle>
          </svg>
          <span style="font-size: 0.78rem; font-weight: 600;">Tra cứu đơn</span>
        </button>
      <?php endif; ?>

      <!-- CỜ 10: TÍCH ĐIỂM HỘI VIÊN CDS STORE MEMBER (tich_diem_thanh_vien) -->
      <?php if ($is_loyalty): ?>
        <button type="button" class="btn btn-ghost btn-sm" style="color: #f59e0b; display: flex; align-items: center; gap: 0.35rem; padding: 0.35rem 0.65rem;" onclick="openModal('loyaltyModal')">
          <span>💎</span>
          <span style="font-size: 0.78rem; font-weight: 700;">2.450 Điểm</span>
        </button>
      <?php endif; ?>

      <!-- CỜ 4: DANH SÁCH YÊU THÍCH (danh_sach_yeu_thich) -->
      <?php if ($is_wishlist): ?>
        <button type="button" class="tz-round-btn" onclick="openModal('wishlistModal')" title="Xem sản phẩm yêu thích (Wishlist)">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
          </svg>
          <span class="tz-cart-badge" id="wishlistCountBadge" style="display: none; background: #ef4444;">0</span>
        </button>
      <?php endif; ?>

      <!-- Nút Tìm kiếm tròn chuẩn CDS STORE (#2f3033) -->
      <button type="button" class="tz-round-btn" onclick="openSearchModal()" title="Tìm kiếm sản phẩm">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </svg>
      </button>

      <!-- Nút Giỏ hàng tròn chuẩn CDS STORE (#2f3033) -->
      <button type="button" class="tz-round-btn" onclick="openCartView()" title="Giỏ hàng CDS STORE">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
          <line x1="3" y1="6" x2="21" y2="6"></line>
          <path d="M16 10a4 4 0 0 1-8 0"></path>
        </svg>
        <span class="tz-cart-badge" id="cartBadgeCount">2</span>
      </button>

      <div style="font-size: 0.78rem; color: #a1a1aa; border-left: 1px solid #33333d; padding-left: 0.65rem;">
        ID: <strong style="color: #2997ff;"><?= e($current_user_id) ?></strong>
      </div>
    </div>
  </nav>

  <!-- CỜ 6: TỰ ĐỘNG GỢI Ý MÃ GIẢM GIÁ (ma_giam_gia) -->
  <?php if ($is_coupon): ?>
    <div style="background: linear-gradient(90deg, #dc2626 0%, #ea580c 100%); color: #ffffff; padding: 0.65rem 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; font-size: 0.88rem;">
      <div style="display: flex; align-items: center; gap: 0.6rem;">
        <span style="font-size: 1.2rem;">🔥</span>
        <span>
          <strong>Ưu đãi CDS STORE độc quyền:</strong> Nhập mã <code style="background: rgba(0,0,0,0.35); padding: 0.15rem 0.5rem; border-radius: 4px; font-weight: 800; color: #fef08a;">CDS18</code> giảm ngay <strong>1.000.000đ</strong> khi thanh toán online!
        </span>
      </div>
      <button type="button" class="btn btn-sm" style="background: #ffffff; color: #dc2626; font-weight: 700; border-radius: 9999px;" onclick="copyCouponCode('CDS18')">
        Sao chép & Áp mã
      </button>
    </div>
  <?php endif; ?>

  <!-- ========================================================================
       CDS STORE HERO SLIDER (BANNER QUẢNG CÁO CHUẨN AAR)
       ======================================================================== -->
  <div class="tz-slider-container">
    <div class="tz-slides" id="tzSlides">
      <?php foreach ($slider_banners as $idx => $banner): ?>
        <div class="tz-slide-item">
          <a href="<?= e($banner['link']) ?>">
            <img src="<?= e($banner['img']) ?>" alt="<?= e($banner['title']) ?>">
          </a>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Prev & Next arrows -->
    <button type="button" class="tz-slider-btn tz-slider-prev" onclick="prevSlide()">&#10094;</button>
    <button type="button" class="tz-slider-btn tz-slider-next" onclick="nextSlide()">&#10095;</button>

    <!-- Pagination Dots -->
    <div class="tz-slider-dots" id="tzSliderDots">
      <?php foreach ($slider_banners as $idx => $banner): ?>
        <div class="tz-slider-dot <?= $idx === 0 ? 'active' : '' ?>" onclick="goToSlide(<?= $idx ?>)"></div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ========================================================================
       KHỐI GIỜ VÀNG FLASHSALE CHẤN ĐỘNG (CDS STORE FLASHSALE)
       ======================================================================== -->
  <div class="tz-flashsale-bar">
    <div class="tz-fs-left">
      <img src="https://cdnv2.tgdd.vn/webmwg/2024/tz/images/icon-fs.png" alt="Flash Sale" style="height: 38px; width: auto;">
      <div class="tz-fs-timer">
        <span>KẾT THÚC TRONG:</span>
        <span class="box" id="fsHour">04</span> :
        <span class="box" id="fsMinute">28</span> :
        <span class="box" id="fsSecond">45</span>
      </div>
    </div>
    <div style="font-size: 0.85rem; color: #ffbe0b; font-weight: 700;">
      ⚡ Giảm đến 50% - Số lượng có hạn - Duy nhất hôm nay tại CDS STORE
    </div>
  </div>

  <!-- Flashsale Cards Grid -->
  <div class="tz-fs-grid">
    <?php foreach ($flashsale_items as $fs): ?>
      <div class="tz-fs-card">
        <img src="<?= e($fs['img']) ?>" alt="<?= e($fs['name']) ?>">
        <div style="flex: 1;">
          <div style="font-size: 0.88rem; font-weight: 700; color: #fff; margin-bottom: 0.25rem; line-height: 1.2;">
            <?= e($fs['name']) ?>
          </div>
          <div style="display: flex; align-items: baseline; gap: 0.4rem;">
            <span style="color: #ff361d; font-weight: 800; font-size: 1rem;"><?= e($fs['price']) ?></span>
            <span style="color: #888; font-size: 0.75rem; text-decoration: line-through;"><?= e($fs['old_price']) ?></span>
          </div>
          <!-- Progress bar -->
          <div style="background: #1c1c1f; border-radius: 9999px; height: 14px; position: relative; margin-top: 0.35rem; overflow: hidden; display: flex; align-items: center; justify-content: center;">
            <div style="position: absolute; left: 0; top: 0; bottom: 0; width: <?= $fs['percent'] ?>%; background: linear-gradient(90deg, #ff361d, #f59e0b); border-radius: 9999px;"></div>
            <span style="position: relative; font-size: 9px; color: #fff; font-weight: 700; z-index: 1;"><?= e($fs['sold']) ?></span>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- ========================================================================
       CHOOSE CATEGORY ICONS BAR (CHỌN DANH MỤC CDS STORE)
       ======================================================================== -->
  <div class="tz-choose-cate">
    <?php foreach ($quick_categories as $qc): ?>
      <a href="#<?= e($qc['target']) ?>" class="tz-cate-item">
        <img src="<?= e($qc['img']) ?>" alt="<?= e($qc['name']) ?>">
        <span><?= e($qc['name']) ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- ========================================================================
       MỤC 1: IPHONE (SECTION IPHONE CHUẨN CDS STORE)
       ======================================================================== -->
  <div id="sec-iphone">
    <div class="tz-section-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="#ffffff">
        <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.47c.68-.83 1.14-1.99.99-3.14-.99.04-2.19.66-2.9 1.49-.63.73-1.18 1.92-1.03 3.06 1.1.09 2.24-.58 2.94-1.41z"/>
      </svg>
      <h2>iPhone</h2>
    </div>

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
        <div class="tz-product-card" data-series="<?= e($p['series']) ?>" data-product-id="<?= e($p['id']) ?>">
          
          <!-- Thumbnail ảnh sản phẩm chính hãng -->
          <div class="tz-product-thumb">
            <?php if (!empty($p['is_new'])): ?>
              <span class="tz-badge-new">Mới</span>
            <?php endif; ?>

            <?php if ($is_wishlist): ?>
              <button type="button" class="tz-wishlist-btn" onclick="toggleWishlist('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', this)" title="Lưu vào danh sách yêu thích">
                ♥
              </button>
            <?php endif; ?>

            <img id="img-<?= e($p['id']) ?>" src="<?= e($p['colors'][0]['img'] ?? '') ?>" alt="<?= e($p['name']) ?>" loading="lazy">
          </div>

          <!-- Card Content -->
          <div class="tz-card-body">
            <div>
              <!-- Chấm màu CDS STORE -->
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

              <!-- Nút chọn dung lượng -->
              <?php if (!empty($p['capacities'])): ?>
                <div class="tz-capacities">
                  <?php foreach ($p['capacities'] as $cap_idx => $cap): ?>
                    <button type="button" class="tz-cap-btn <?= $cap_idx === 0 ? 'active' : '' ?>" onclick="selectCapacity(this)">
                      <?= e($cap) ?>
                    </button>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <!-- Tiêu đề sản phẩm -->
              <h3 class="tz-card-title"><?= e($p['name']) ?></h3>

              <!-- Khối giá bán -->
              <div class="tz-price-box">
                <span class="tz-price-main"><?= e($p['price']) ?></span>
                <?php if (!empty($p['old_price'])): ?>
                  <span class="tz-price-old"><?= e($p['old_price']) ?></span>
                <?php endif; ?>
                <?php if (!empty($p['discount'])): ?>
                  <span class="tz-discount-badge"><?= e($p['discount']) ?></span>
                <?php endif; ?>
              </div>

              <!-- CỜ 3: ĐÁNH GIÁ SẢN PHẨM & ẢNH UNBOX THỰC TẾ (Ant Design Star Rating) -->
              <?php if ($is_reviews): ?>
                <div class="tz-rating-row" onclick="openReviewsModal('<?= e($p['name']) ?>', <?= $p['rating'] ?>, <?= $p['reviews_count'] ?>)" style="cursor: pointer;" title="Xem đánh giá & ảnh unbox">
                  <?= render_ant_stars($p['rating'], $p['reviews_count'], $is_dark_mode) ?>
                  <span style="color: #2997ff; font-weight: 600;">Xem ảnh 📸</span>
                </div>
              <?php endif; ?>

              <p style="font-size: 0.78rem; color: #a1a1aa; margin-bottom: 0.85rem; line-height: 1.35;">
                <?= e($p['specs']) ?>
              </p>
            </div>

            <!-- CỜ 1: QUY TRÌNH MUA 1-CHẠM (thanh_toan_nhanh) -->
            <?php if ($is_fast_pay): ?>
              <button type="button" class="tz-buy-btn fast-pay" onclick="openFastCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['colors'][0]['img'] ?? '') ?>')">
                <span>⚡ Mua ngay 1-Chạm Express</span>
              </button>
            <?php else: ?>
              <button type="button" class="tz-buy-btn" onclick="openStandardCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>')">
                <span>🛒 Mua ngay (Tiêu chuẩn 3 bước)</span>
              </button>
            <?php endif; ?>
          </div>

        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ========================================================================
       MỤC 2: MAC (MACBOOK AIR, PRO, IMAC, MAC MINI CHUẨN CDS STORE)
       ======================================================================== -->
  <div id="sec-mac">
    <div class="tz-section-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="#ffffff">
        <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.47c.68-.83 1.14-1.99.99-3.14-.99.04-2.19.66-2.9 1.49-.63.73-1.18 1.92-1.03 3.06 1.1.09 2.24-.58 2.94-1.41z"/>
      </svg>
      <h2>Mac</h2>
    </div>

    <!-- Series Selector Tabs Mac -->
    <div class="tz-series-bar">
      <button type="button" class="tz-series-btn active" onclick="filterBySeries('all', this, 'mac')">Tất cả Mac</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('macbook-air', this, 'mac')">MacBook Air</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('macbook-pro', this, 'mac')">MacBook Pro</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('imac', this, 'mac')">iMac</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('mac-mini', this, 'mac')">Mac mini</button>
    </div>

    <!-- Lưới sản phẩm Mac -->
    <div class="tz-product-grid" id="macGrid">
      <?php foreach ($mac_products as $p): ?>
        <div class="tz-product-card" data-series="<?= e($p['series']) ?>" data-product-id="<?= e($p['id']) ?>">
          <div class="tz-product-thumb">
            <?php if (!empty($p['is_new'])): ?>
              <span class="tz-badge-new">Mới</span>
            <?php endif; ?>

            <?php if ($is_wishlist): ?>
              <button type="button" class="tz-wishlist-btn" onclick="toggleWishlist('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', this)">♥</button>
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
                  <span style="color: #2997ff; font-weight: 600;">Xem ảnh 📸</span>
                </div>
              <?php endif; ?>

              <p style="font-size: 0.78rem; color: #a1a1aa; margin-bottom: 0.85rem; line-height: 1.35;">
                <?= e($p['specs']) ?>
              </p>
            </div>

            <?php if ($is_fast_pay): ?>
              <button type="button" class="tz-buy-btn fast-pay" onclick="openFastCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>')">
                <span>⚡ Mua ngay 1-Chạm Express</span>
              </button>
            <?php else: ?>
              <button type="button" class="tz-buy-btn" onclick="openStandardCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>')">
                <span>🛒 Mua ngay (Tiêu chuẩn 3 bước)</span>
              </button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ========================================================================
       MỤC 3: IPAD (IPAD PRO M4, AIR M2, GEN 10, MINI 7 CHUẨN CDS STORE)
       ======================================================================== -->
  <div id="sec-ipad">
    <div class="tz-section-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="#ffffff">
        <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.47c.68-.83 1.14-1.99.99-3.14-.99.04-2.19.66-2.9 1.49-.63.73-1.18 1.92-1.03 3.06 1.1.09 2.24-.58 2.94-1.41z"/>
      </svg>
      <h2>iPad</h2>
    </div>

    <!-- Series Selector Tabs iPad -->
    <div class="tz-series-bar">
      <button type="button" class="tz-series-btn active" onclick="filterBySeries('all', this, 'ipad')">Tất cả iPad</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('ipad-pro', this, 'ipad')">iPad Pro M4</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('ipad-air', this, 'ipad')">iPad Air M2</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('ipad-gen10', this, 'ipad')">iPad Gen 10</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('ipad-mini', this, 'ipad')">iPad mini 7</button>
    </div>

    <!-- Lưới sản phẩm iPad -->
    <div class="tz-product-grid" id="ipadGrid">
      <?php foreach ($ipad_products as $p): ?>
        <div class="tz-product-card" data-series="<?= e($p['series']) ?>" data-product-id="<?= e($p['id']) ?>">
          <div class="tz-product-thumb">
            <?php if (!empty($p['is_new'])): ?>
              <span class="tz-badge-new">Mới</span>
            <?php endif; ?>

            <?php if ($is_wishlist): ?>
              <button type="button" class="tz-wishlist-btn" onclick="toggleWishlist('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', this)">♥</button>
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
                  <span style="color: #2997ff; font-weight: 600;">Xem ảnh 📸</span>
                </div>
              <?php endif; ?>

              <p style="font-size: 0.78rem; color: #a1a1aa; margin-bottom: 0.85rem; line-height: 1.35;">
                <?= e($p['specs']) ?>
              </p>
            </div>

            <?php if ($is_fast_pay): ?>
              <button type="button" class="tz-buy-btn fast-pay" onclick="openFastCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>')">
                <span>⚡ Mua ngay 1-Chạm Express</span>
              </button>
            <?php else: ?>
              <button type="button" class="tz-buy-btn" onclick="openStandardCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>')">
                <span>🛒 Mua ngay (Tiêu chuẩn 3 bước)</span>
              </button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ========================================================================
       MỤC 4: APPLE WATCH (SERIES 10, ULTRA 2, SE CHUẨN CDS STORE)
       ======================================================================== -->
  <div id="sec-watch">
    <div class="tz-section-header">
      <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="#ffffff">
        <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.47c.68-.83 1.14-1.99.99-3.14-.99.04-2.19.66-2.9 1.49-.63.73-1.18 1.92-1.03 3.06 1.1.09 2.24-.58 2.94-1.41z"/>
      </svg>
      <h2>Apple Watch</h2>
    </div>

    <!-- Series Selector Tabs Watch -->
    <div class="tz-series-bar">
      <button type="button" class="tz-series-btn active" onclick="filterBySeries('all', this, 'watch')">Tất cả Watch</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('watch-s10', this, 'watch')">Apple Watch Series 10</button>
      <button type="button" class="tz-series-btn" onclick="filterBySeries('watch-ultra', this, 'watch')">Apple Watch Ultra 2</button>
    </div>

    <!-- Lưới sản phẩm Watch -->
    <div class="tz-product-grid" id="watchGrid">
      <?php foreach ($watch_products as $p): ?>
        <div class="tz-product-card" data-series="<?= e($p['series']) ?>" data-product-id="<?= e($p['id']) ?>">
          <div class="tz-product-thumb">
            <?php if (!empty($p['is_new'])): ?>
              <span class="tz-badge-new">Mới</span>
            <?php endif; ?>

            <?php if ($is_wishlist): ?>
              <button type="button" class="tz-wishlist-btn" onclick="toggleWishlist('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', this)">♥</button>
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
                  <span style="color: #2997ff; font-weight: 600;">Xem ảnh 📸</span>
                </div>
              <?php endif; ?>

              <p style="font-size: 0.78rem; color: #a1a1aa; margin-bottom: 0.85rem; line-height: 1.35;">
                <?= e($p['specs']) ?>
              </p>
            </div>

            <?php if ($is_fast_pay): ?>
              <button type="button" class="tz-buy-btn fast-pay" onclick="openFastCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>')">
                <span>⚡ Mua ngay 1-Chạm Express</span>
              </button>
            <?php else: ?>
              <button type="button" class="tz-buy-btn" onclick="openStandardCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>')">
                <span>🛒 Mua ngay (Tiêu chuẩn 3 bước)</span>
              </button>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ========================================================================
       MỤC 5: TAI NGHE & PHỤ KIỆN AI RECOMMENDATIONS (CỜ goi_y_san_pham)
       ======================================================================== -->
  <div id="sec-audio">
    <?php if ($is_ai_recs): ?>
      <div style="max-width: 1200px; margin: 3rem auto; padding: 0 1rem;">
        <div style="background: <?= $is_dark_mode ? '#323232' : '#ffffff' ?>; border: 1px solid <?= $is_dark_mode ? '#444448' : '#e2e8f0' ?>; border-radius: 20px; padding: 1.5rem 2rem;">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.5rem;">
            <div style="display: flex; align-items: center; gap: 0.6rem;">
              <span style="font-size: 1.5rem;">✨</span>
              <div>
                <h3 style="font-size: 1.25rem; font-weight: 800; color: #2997ff; margin: 0;">AI Smart Combo: Phụ Kiện Apple Khuyên Dùng</h3>
                <p style="font-size: 0.82rem; color: #a1a1aa; margin: 0;">
                  Thuật toán thông minh CDS STORE đề xuất phụ kiện chính hãng tối ưu nhất cho thiết bị của bạn
                </p>
              </div>
            </div>
            <span class="badge" style="background: rgba(41,151,255,0.15); color: #2997ff; border: 1px solid #2997ff; font-weight: 700;">
              Feature Flag [goi_y_san_pham]: ENABLED
            </span>
          </div>

          <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
            <?php foreach ($recommendations as $rec): ?>
              <div style="background: <?= $is_dark_mode ? '#252528' : '#f8fafc' ?>; border: 1px solid <?= $is_dark_mode ? '#3b3b3e' : '#e2e8f0' ?>; border-radius: 14px; padding: 1rem; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                  <div style="font-size: 2rem; margin-bottom: 0.4rem;"><?= $rec['icon'] ?></div>
                  <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.25rem; color: #fff;"><?= e($rec['name']) ?></h4>
                  <p style="font-size: 0.75rem; color: #a1a1aa; margin-bottom: 0.75rem;">
                    <?= e($rec['desc']) ?>
                  </p>
                </div>
                <div>
                  <div style="display: flex; align-items: baseline; gap: 0.4rem; margin-bottom: 0.5rem;">
                    <span style="font-size: 1.05rem; font-weight: 800; color: #ff361d;"><?= $rec['price'] ?></span>
                    <span style="font-size: 0.78rem; text-decoration: line-through; color: #888;"><?= $rec['old_price'] ?></span>
                  </div>
                  <button type="button" class="btn btn-secondary btn-sm" style="width: 100%; font-size: 0.78rem; font-weight: 600;" onclick="showToast('Đã thêm <?= e($rec['name']) ?> vào giỏ mua kèm!', 'success')">
                    + Thêm mua kèm
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
       CAM KẾT CHÍNH HÃNG CDS STORE & CHUỖI CỬA HÀNG
       ======================================================================== -->
  <div class="tz-commitments">
    <div class="tz-commit-item">
      <div class="tz-commit-icon">🏅</div>
      <div>
        <div style="font-weight: 700; font-size: 0.95rem; color: #fff;">100% Chính hãng Apple</div>
        <div style="font-size: 0.8rem; color: #a1a1aa;">Đại lý ủy quyền chính thức CDS STORE (AAR) tại Việt Nam</div>
      </div>
    </div>
    <div class="tz-commit-item">
      <div class="tz-commit-icon">🛡️</div>
      <div>
        <div style="font-weight: 700; font-size: 0.95rem; color: #fff;">Bảo hành tại CDS Care</div>
        <div style="font-size: 0.8rem; color: #a1a1aa;">1 đổi 1 trong 30 ngày nếu lỗi từ nhà sản xuất</div>
      </div>
    </div>
    <div class="tz-commit-item">
      <div class="tz-commit-icon">🚀</div>
      <div>
        <div style="font-weight: 700; font-size: 0.95rem; color: #fff;">Giao siêu tốc 1 Giờ</div>
        <div style="font-size: 0.8rem; color: #a1a1aa;">Miễn phí vận chuyển toàn quốc cho đơn từ 500k</div>
      </div>
    </div>
    <div class="tz-commit-item">
      <div class="tz-commit-icon">💳</div>
      <div>
        <div style="font-weight: 700; font-size: 0.95rem; color: #fff;">Trả góp 0% Lãi suất</div>
        <div style="font-size: 0.8rem; color: #a1a1aa;">Duyệt hồ sơ nhanh chóng trong 3 phút</div>
      </div>
    </div>
  </div>

  <!-- ========================================================================
       CDS STORE FOOTER CHÍNH THỨC (#101010)
       ======================================================================== -->
  <footer class="tz-footer">
    <div class="tz-foot-container">
      
      <!-- Logo Foot -->
      <div style="display: flex; align-items: center; gap: 0.65rem; margin-bottom: 2rem;">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="#ffffff">
          <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.47c.68-.83 1.14-1.99.99-3.14-.99.04-2.19.66-2.9 1.49-.63.73-1.18 1.92-1.03 3.06 1.1.09 2.24-.58 2.94-1.41z"/>
        </svg>
        <span style="font-size: 1.35rem; font-weight: 800; color: #fff; text-transform: uppercase;">cds<span style="font-weight: 300;">store</span></span>
        <div style="height: 20px; width: 1px; background: #3b3b3b; margin: 0 0.5rem;"></div>
        <span style="font-size: 0.65rem; font-weight: 700; color: #888; text-transform: uppercase;">Apple Authorised Reseller</span>
      </div>

      <!-- 4 Columns Foot -->
      <div class="tz-foot-grid">
        <div class="tz-foot-col">
          <h4>Tổng đài hỗ trợ</h4>
          <p>Mua hàng: <strong>0362481351</strong> (8:00 - 21:30)</p>
          <p>Khiếu nại: <strong>0362481351</strong> (8:00 - 21:30)</p>
          <p>Bảo hành: <strong>0362481351</strong> (CDS Care)</p>
          <p>Hotline tư vấn 24/7: <strong>0362481351</strong></p>
        </div>

        <div class="tz-foot-col">
          <h4>Hệ thống cửa hàng CDS STORE</h4>
          <a href="javascript:;">Tìm hơn 100 cửa hàng CDS STORE trên toàn quốc</a>
          <a href="javascript:;">Nội quy và chính sách cửa hàng</a>
          <a href="javascript:;">Chất lượng phục vụ chuẩn Apple</a>
          <a href="javascript:;">Chính sách bảo mật thông tin</a>
        </div>

        <div class="tz-foot-col">
          <h4>Chính sách & Dịch vụ</h4>
          <a href="javascript:;">Chính sách giao hàng siêu tốc 1H</a>
          <a href="javascript:;">Chính sách đổi trả 30 ngày 1 đổi 1</a>
          <a href="javascript:;">Chính sách bảo hành tại CDS Care</a>
          <a href="javascript:;">Hướng dẫn trả góp lãi suất 0%</a>
        </div>

        <div class="tz-foot-col">
          <h4>Hệ sinh thái Apple AAR</h4>
          <p>CDS STORE là chuỗi cửa hàng cao cấp được ủy quyền chính thức từ Apple tại Việt Nam.</p>
          <p>Hotline hỗ trợ khách hàng: <strong style="color: #2997ff;">0362481351</strong></p>
        </div>
      </div>

      <!-- Copyright -->
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; font-size: 12px; color: #666;">
        <div>
          &copy; 2026 Công Ty Cổ Phần CDS STORE &bull; Hotline: 0362481351.
        </div>
        <div style="display: flex; gap: 1rem;">
          <span>Chứng nhận AAR Chính Thức</span>
          <span>Đã thông báo Bộ Công Thương</span>
        </div>
      </div>

    </div>
  </footer>

</div> <!-- Đóng .topzone-wrapper -->

<!-- ========================================================================
     CỜ 5: LIVE CHAT TƯ VẤN VIÊN APPLE CDS STORE 24/7 (ho_tro_truc_tuyen)
     ======================================================================== -->
<?php if ($is_chat): ?>
  <div class="tz-livechat-widget">
    <button type="button" class="tz-chat-btn" onclick="toggleLiveChat()">
      <span style="font-size: 1.25rem;">💬</span>
      <span>Tư vấn Apple 24/7</span>
      <span style="width: 8px; height: 8px; background: #22c55e; border-radius: 50%; display: inline-block;"></span>
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
     MODAL: CỜ 1 - THANH TOÁN 1 BƯỚC SIÊU TỐC (ONE-STEP CHECKOUT)
     ======================================================================== -->
<div class="modal-backdrop" id="oneStepCheckoutModal">
  <div class="modal-dialog" style="max-width: 540px;">
    <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%); color: #fff;">
      <div style="font-weight: 800; font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;">
        <span>⚡</span>
        <span>Thanh Toán 1-Chạm Siêu Tốc (CDS STORE Express)</span>
      </div>
      <button type="button" class="modal-close" style="color: #fff;" onclick="closeModal('oneStepCheckoutModal')">&times;</button>
    </div>
    <div class="modal-body" style="padding: 1.5rem;">
      <div style="background: rgba(245, 158, 11, 0.1); border-left: 4px solid #f59e0b; padding: 0.65rem 0.85rem; border-radius: 4px; font-size: 0.82rem; margin-bottom: 1.25rem;">
        Tính năng kích hoạt bởi Cờ <strong>thanh_toan_nhanh</strong>. Toàn bộ thông tin địa chỉ & thẻ đã được tự động điền sẵn cho khách hàng <strong><?= e($current_user_id) ?></strong>!
      </div>

      <div style="display: flex; gap: 1rem; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem;">
        <img id="modalProductImg" src="" alt="Product" style="width: 70px; height: 70px; object-fit: contain;">
        <div>
          <h4 id="modalProductName" style="font-size: 1rem; font-weight: 700; margin-bottom: 0.25rem;"></h4>
          <div id="modalProductPrice" style="font-size: 1.2rem; font-weight: 800; color: #ff361d;"></div>
        </div>
      </div>

      <form onsubmit="handleCheckoutSubmit(event)">
        <div style="margin-bottom: 0.85rem;">
          <label class="form-label" style="font-weight: 600;">Thông tin người nhận (Đã điền tự động):</label>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
            <input type="text" class="form-control" value="Khách hàng <?= e($current_user_id) ?>" required>
            <input type="text" class="form-control font-mono" value="0362481351" required>
          </div>
        </div>

        <div style="margin-bottom: 0.85rem;">
          <label class="form-label" style="font-weight: 600;">Địa chỉ giao hàng nhanh CDS STORE 1H:</label>
          <input type="text" class="form-control" value="Số 128 Trần Quang Khải, P. Tân Định, Quận 1, TP.HCM" required>
        </div>

        <!-- Phương thức thanh toán 1 bước -->
        <div style="margin-bottom: 1rem;">
          <label class="form-label" style="font-weight: 600;">Phương thức thanh toán 1-Chạm:</label>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
            <label style="border: 1px solid #2997ff; background: rgba(41,151,255,0.08); padding: 0.6rem; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 600;">
              <input type="radio" name="pay_method" value="applepay" checked>
              <span> Apple Pay / VNPay</span>
            </label>
            <label style="border: 1px solid var(--border-color); padding: 0.6rem; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; font-weight: 600;">
              <input type="radio" name="pay_method" value="cod">
              <span>Thanh toán khi nhận (COD)</span>
            </label>
          </div>
        </div>

        <!-- Ưu đãi tự động (nếu các cờ bật) -->
        <div style="background: <?= $is_dark_mode ? '#181822' : '#f0fdf4' ?>; border: 1px dashed #22c55e; padding: 0.85rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.82rem;">
          <?php if ($is_coupon): ?>
            <div style="display: flex; justify-content: space-between; color: #16a34a; font-weight: 600; margin-bottom: 0.25rem;">
              <span>Voucher CDS18 (Tự động áp dụng):</span>
              <span>-1.000.000đ</span>
            </div>
          <?php endif; ?>
          <?php if ($is_loyalty): ?>
            <div style="display: flex; justify-content: space-between; color: #d97706; font-weight: 600;">
              <span>Trừ điểm VIP CDS Member (2.450 điểm thưởng):</span>
              <span>-245.000đ</span>
            </div>
          <?php endif; ?>
          <div style="display: flex; justify-content: space-between; border-top: 1px solid rgba(0,0,0,0.1); padding-top: 0.4rem; margin-top: 0.4rem; font-weight: 800; font-size: 1rem;">
            <span>Tổng tiền thanh toán cuối cùng:</span>
            <span style="color: #ff361d;" id="modalFinalPrice">40.745.000đ</span>
          </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%); border: none; font-size: 1.05rem; font-weight: 800; padding: 0.85rem;">
          ⚡ Xác nhận đặt hàng tức thì (3 giây)
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ========================================================================
     MODAL: QUY TRÌNH THANH TOÁN TIÊU CHUẨN (KHI CỜ thanh_toan_nhanh = OFF)
     ======================================================================== -->
<div class="modal-backdrop" id="standardCheckoutModal">
  <div class="modal-dialog" style="max-width: 580px;">
    <div class="modal-header">
      <div style="font-weight: 700; font-size: 1.05rem;">
        Đặt hàng tiêu chuẩn tại CDS STORE (3 Bước xét duyệt)
      </div>
      <button type="button" class="modal-close" onclick="closeModal('standardCheckoutModal')">&times;</button>
    </div>
    <div class="modal-body" style="padding: 1.5rem;">
      <div style="background: #f1f5f9; border-left: 4px solid #64748b; padding: 0.65rem 0.85rem; border-radius: 4px; font-size: 0.82rem; margin-bottom: 1.25rem;">
        Cờ <strong>thanh_toan_nhanh = OFF</strong>. Hệ thống hiển thị quy trình đặt hàng 3 bước tiêu chuẩn dành cho khách vãng lai.
      </div>

      <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem;">
        <div style="flex: 1; text-align: center; padding: 0.4rem; background: #2997ff; color: #fff; font-size: 0.75rem; font-weight: 700; border-radius: 4px;">1. Giỏ hàng</div>
        <div style="flex: 1; text-align: center; padding: 0.4rem; background: #e2e8f0; color: #475569; font-size: 0.75rem; font-weight: 700; border-radius: 4px;">2. Địa chỉ</div>
        <div style="flex: 1; text-align: center; padding: 0.4rem; background: #e2e8f0; color: #475569; font-size: 0.75rem; font-weight: 700; border-radius: 4px;">3. Thanh toán</div>
      </div>

      <div style="margin-bottom: 1rem;">
        <label class="form-label">Sản phẩm chọn mua:</label>
        <input type="text" id="stdProductName" class="form-control" readonly>
      </div>

      <div style="margin-bottom: 1rem;">
        <label class="form-label">Giá tiền:</label>
        <input type="text" id="stdProductPrice" class="form-control font-mono" readonly style="font-weight: 700; color: #ff361d;">
      </div>

      <button type="button" class="btn btn-primary" style="width: 100%;" onclick="showToast('Đang chuyển sang Bước 2 (Nhập địa chỉ giao hàng)...', 'info')">
        Tiếp tục sang Bước 2 &rarr;
      </button>
    </div>
  </div>
</div>

<!-- ========================================================================
     MODAL: CỜ 4 - DANH SÁCH SẢN PHẨM YÊU THÍCH (WISHLIST)
     ======================================================================== -->
<div class="modal-backdrop" id="wishlistModal">
  <div class="modal-dialog" style="max-width: 500px;">
    <div class="modal-header">
      <div style="font-weight: 700; font-size: 1.05rem; display: flex; align-items: center; gap: 0.4rem;">
        <span style="color: #ef4444;">♥</span>
        <span>Sản phẩm yêu thích của bạn (Wishlist)</span>
      </div>
      <button type="button" class="modal-close" onclick="closeModal('wishlistModal')">&times;</button>
    </div>
    <div class="modal-body" style="padding: 1.5rem;">
      <div id="wishlistEmptyState" style="text-align: center; padding: 2rem 1rem; color: #94a3b8;">
        <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🤍</div>
        <div style="font-weight: 600;">Chưa có sản phẩm yêu thích nào</div>
        <div style="font-size: 0.8rem; margin-top: 0.25rem;">Nhấp vào biểu tượng trái tim góc sản phẩm để lưu lại xem sau</div>
      </div>
      <div id="wishlistItemsList" style="display: flex; flex-direction: column; gap: 0.65rem;"></div>
    </div>
  </div>
</div>

<!-- ========================================================================
     MODAL: CỜ 3 - ĐÁNH GIÁ VÀ HÌNH ẢNH UNBOX THỰC TẾ (danh_gia_san_pham)
     ======================================================================== -->
<div class="modal-backdrop" id="reviewsModal">
  <div class="modal-dialog" style="max-width: 600px;">
    <div class="modal-header">
      <div id="reviewModalTitle" style="font-weight: 700; font-size: 1.05rem;">
        Đánh giá & Ảnh unbox thực tế từ khách hàng
      </div>
      <button type="button" class="modal-close" onclick="closeModal('reviewsModal')">&times;</button>
    </div>
    <div class="modal-body" style="padding: 1.5rem;">
      <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1.25rem;">
        <div>
          <div id="reviewModalScore" style="font-size: 2.2rem; font-weight: 800; color: #faad14;">5.0 / 5.0</div>
          <div id="reviewModalStars" style="display: flex; align-items: center; gap: 4px; margin: 4px 0;">
            <?php for ($s = 1; $s <= 5; $s++): ?>
              <svg viewBox="64 64 896 896" width="18" height="18" fill="#faad14"><path d="M908.1 353.1l-253.9-36.9L540.7 86.1c-3.1-6.3-8.2-11.4-14.5-14.5-15.8-7.8-35-1.3-42.9 14.5L369.8 316.2l-253.9 36.9c-7 1-13.4 4.3-18.3 9.3a32.05 32.05 0 0 0 .6 45.3l183.7 179.1-43.4 252.9a31.95 31.95 0 0 0 46.4 33.7L512 754l227.1 119.4c6.2 3.3 13.4 4.4 20.3 3.2 17.4-3 29.1-19.5 26.1-36.9l-43.4-252.9 183.7-179.1c5-4.9 8.3-11.3 9.3-18.3 2.7-17.5-9.5-33.7-27-36.3z"/></svg>
            <?php endfor; ?>
          </div>
          <div id="reviewModalCount" style="font-size: 0.8rem; color: #64748b;">Dựa trên 520 đánh giá đã xác thực mua tại CDS STORE</div>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="showToast('Cảm ơn bạn! Hệ thống đánh giá đã ghi nhận.', 'success')">
          ✍️ Viết đánh giá
        </button>
      </div>

      <div style="margin-bottom: 1.25rem;">
        <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 0.65rem;">Hình ảnh unbox đập hộp thực tế:</h4>
        <div style="display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.5rem;">
          <img src="https://cdn.topzone.vn/Products/Images/42/370982/s16/iphone-18-pro-max-thumb-650x650.png" style="width: 80px; height: 80px; border-radius: 8px; border: 1px solid var(--border-color); object-fit: contain; background: #1c1c1f;" alt="Unbox 1">
          <img src="https://cdn.topzone.vn/Products/Images/42/370977/s16/iphone-18-pro-bang-thanh-thumb-650x650.png" style="width: 80px; height: 80px; border-radius: 8px; border: 1px solid var(--border-color); object-fit: contain; background: #1c1c1f;" alt="Unbox 2">
          <img src="https://cdn.topzone.vn/Products/Images/44/363487/s16/macbook-air-13-inch-m5-16gb-512gb-blue-thumb-650x650.png" style="width: 80px; height: 80px; border-radius: 8px; border: 1px solid var(--border-color); object-fit: contain; background: #1c1c1f;" alt="Unbox 3">
        </div>
      </div>

      <!-- Khách hàng nhận xét mẫu -->
      <div style="display: flex; flex-direction: column; gap: 0.85rem; font-size: 0.85rem;">
        <div style="background: var(--bg-body); padding: 0.85rem; border-radius: 8px;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
            <strong>Anh Minh Hoàng (Đã mua tại CDS STORE 128 Trần Quang Khải)</strong>
            <div style="display:inline-flex; align-items:center; gap:2px;">
              <?php for ($s = 1; $s <= 5; $s++): ?>
                <svg viewBox="64 64 896 896" width="13" height="13" fill="#faad14"><path d="M908.1 353.1l-253.9-36.9L540.7 86.1c-3.1-6.3-8.2-11.4-14.5-14.5-15.8-7.8-35-1.3-42.9 14.5L369.8 316.2l-253.9 36.9c-7 1-13.4 4.3-18.3 9.3a32.05 32.05 0 0 0 .6 45.3l183.7 179.1-43.4 252.9a31.95 31.95 0 0 0 46.4 33.7L512 754l227.1 119.4c6.2 3.3 13.4 4.4 20.3 3.2 17.4-3 29.1-19.5 26.1-36.9l-43.4-252.9 183.7-179.1c5-4.9 8.3-11.3 9.3-18.3 2.7-17.5-9.5-33.7-27-36.3z"/></svg>
              <?php endfor; ?>
            </div>
          </div>
          <p style="color: var(--text-secondary); margin: 0;">
            iPhone 18 Pro Max màu Băng Thanh bên ngoài cực kỳ sang trọng và cuốn hút. Nút Camera Control siêu nhạy, màn hình viền mỏng vô cực. Đội ngũ kỹ thuật viên CDS STORE tư vấn rất nhiệt tình qua hotline 0362481351!
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
  <div class="modal-dialog" style="max-width: 540px;">
    <div class="modal-header">
      <div style="font-weight: 700; font-size: 1.05rem; display: flex; align-items: center; gap: 0.4rem;">
        <span style="color: #2997ff;">🚚</span>
        <span>Định Vị & Theo Dõi Đơn Hàng CDS STORE Realtime</span>
      </div>
      <button type="button" class="modal-close" onclick="closeModal('trackingModal')">&times;</button>
    </div>
    <div class="modal-body" style="padding: 1.5rem;">
      <div style="margin-bottom: 1.25rem;">
        <label class="form-label" style="font-weight: 600;">Nhập mã đơn hàng CDS STORE của bạn:</label>
        <div style="display: flex; gap: 0.5rem;">
          <input type="text" id="trackingCodeInput" class="form-control font-mono" value="CDS-2026-98845" placeholder="Ví dụ: CDS-2026-XXXXX">
          <button type="button" class="btn btn-primary" onclick="searchTrackingOrder()">Tra cứu</button>
        </div>
      </div>

      <!-- Trạng thái mô phỏng GPS shipper -->
      <div style="border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem; background: var(--bg-body);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
          <div>
            <div style="font-weight: 700; font-size: 0.95rem;">Mã vận đơn: <span class="font-mono" style="color: #2997ff;">CDS-2026-98845</span></div>
            <div style="font-size: 0.78rem; color: var(--text-muted);">Dịch vụ: CDS STORE Express Giao Siêu Tốc 1 Giờ</div>
          </div>
          <span class="badge badge-success">Đang vận chuyển</span>
        </div>

        <div style="position: relative; padding-left: 1.75rem; display: flex; flex-direction: column; gap: 1rem; font-size: 0.82rem;">
          <div>
            <strong style="color: #22c55e;">18:42 - Shipper đang giao đến bạn</strong>
            <div style="color: var(--text-muted);">Tài xế Nguyễn Văn An (Hotline: 0362481351) - Khoảng cách 800m</div>
          </div>
          <div>
            <strong>18:15 - Xuất kho CDS STORE Quận 1</strong>
            <div style="color: var(--text-muted);">Đã kiểm tra tem niêm phong Apple chính hãng</div>
          </div>
          <div>
            <strong>18:05 - Đơn hàng đã xác nhận thanh toán</strong>
            <div style="color: var(--text-muted);">Áp dụng mã giảm giá CDS18 thành công</div>
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
  <div class="modal-dialog" style="max-width: 500px;">
    <div class="modal-header" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #fff;">
      <div style="font-weight: 700; font-size: 1.05rem; display: flex; align-items: center; gap: 0.4rem;">
        <span>💎</span>
        <span>Hội viên VIP: CDS STORE Member</span>
      </div>
      <button type="button" class="modal-close" style="color: #fff;" onclick="closeModal('loyaltyModal')">&times;</button>
    </div>
    <div class="modal-body" style="padding: 1.5rem;">
      <div style="background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%); color: #fff; padding: 1.25rem; border-radius: 12px; margin-bottom: 1.25rem; box-shadow: 0 4px 15px rgba(245,158,11,0.3);">
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

      <div style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.5;">
        <h4 style="font-size: 0.9rem; font-weight: 700; margin-bottom: 0.4rem; color: var(--text-primary);">Quyền lợi đặc quyền của bạn:</h4>
        <ul style="padding-left: 1.2rem; margin-bottom: 1rem;">
          <li>Tích lũy 1% giá trị cho mọi đơn hàng Apple chính hãng</li>
          <li>Được ưu tiên nhận iPhone 18 Pro Max đợt đầu không cần chờ</li>
          <li>Miễn phí vệ sinh máy & dán cường lực trọn đời tại CDS Care</li>
          <li>Hỗ trợ hotline VIP riêng: <strong>0362481351</strong></li>
        </ul>
      </div>

      <button type="button" class="btn btn-primary" style="width: 100%;" onclick="showToast('Điểm thưởng sẽ tự động trừ khi bạn thanh toán đơn tiếp theo!', 'success')">
        Áp dụng giảm trừ cho đơn hiện tại
      </button>
    </div>
  </div>
</div>

<!-- ========================================================================
     JAVASCRIPT ĐỒNG BỘ 10 TÍNH NĂNG VÀ TRÌNH DIỄN GIAO DIỆN CDS STORE
     ======================================================================== -->
<script>
// Đồng bộ trạng thái 10 Cờ từ PHP sang JavaScript Client
const APP_FLAGS = {
  fast_checkout: <?= $is_fast_pay ? 'true' : 'false' ?>,
  dark: <?= $is_dark_mode ? 'true' : 'false' ?>,
  reviews: <?= $is_reviews ? 'true' : 'false' ?>,
  wishlist: <?= $is_wishlist ? 'true' : 'false' ?>,
  chat: <?= $is_chat ? 'true' : 'false' ?>,
  coupon: <?= $is_coupon ? 'true' : 'false' ?>,
  ai_recs: <?= $is_ai_recs ? 'true' : 'false' ?>,
  adv_filter: <?= $is_adv_filter ? 'true' : 'false' ?>,
  tracking: <?= $is_tracking ? 'true' : 'false' ?>,
  loyalty: <?= $is_loyalty ? 'true' : 'false' ?>
};

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

// Bắt đầu autoplay banner
resetSlideTimer();

// --------------------------------------------------------------------------
// 2. ĐẾM NGƯỢC GIỜ VÀNG FLASHSALE CDS STORE
// --------------------------------------------------------------------------
let fsSeconds = 4 * 3600 + 28 * 60 + 45;
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
// 3. LỌC PHÂN KHÚC SERIES THEO TỪNG DANH MỤC (IPHONE, MAC, IPAD, WATCH)
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

// --------------------------------------------------------------------------
// 4. CHUYỂN ĐỔI CHẤM MÀU SẮC ĐỔI HÌNH ẢNH SẢN PHẨM REALTIME
// --------------------------------------------------------------------------
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

// Chọn dung lượng
function selectCapacity(btn) {
  const parent = btn.parentElement;
  if (parent) {
    parent.querySelectorAll('.tz-cap-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  }
}

// --------------------------------------------------------------------------
// 5. CỜ 1: THANH TOÁN 1 BƯỚC EXPRESS VS TIÊU CHUẨN
// --------------------------------------------------------------------------
function openFastCheckout(id, name, price, img) {
  document.getElementById('modalProductName').textContent = name;
  document.getElementById('modalProductPrice').textContent = price;
  document.getElementById('modalProductImg').src = img;

  let finalPrice = price;
  if (APP_FLAGS.coupon) {
    finalPrice = '40.990.000đ (Đã trừ 1.000.000đ voucher CDS18)';
  }
  document.getElementById('modalFinalPrice').textContent = finalPrice;
  openModal('oneStepCheckoutModal');
}

function openStandardCheckout(id, name, price) {
  document.getElementById('stdProductName').value = name;
  document.getElementById('stdProductPrice').value = price;
  openModal('standardCheckoutModal');
}

function handleCheckoutSubmit(e) {
  e.preventDefault();
  closeModal('oneStepCheckoutModal');
  showToast('🎉 Đặt hàng CDS STORE thành công! Mã đơn: CDS-2026-98845. Shipper sẽ liên hệ qua 0362481351 trong 15 phút.', 'success');
  const badge = document.getElementById('cartBadgeCount');
  if (badge) badge.textContent = '3';
}

// --------------------------------------------------------------------------
// 6. CỜ 4: DANH SÁCH YÊU THÍCH (WISHLIST)
// --------------------------------------------------------------------------
let wishlistItems = [];

function toggleWishlist(id, name, btn) {
  const idx = wishlistItems.findIndex(i => i.id === id);
  if (idx >= 0) {
    wishlistItems.splice(idx, 1);
    btn.classList.remove('active');
    btn.textContent = '♥';
    showToast(`Đã xóa [${name}] khỏi danh sách yêu thích!`, 'info');
  } else {
    wishlistItems.push({ id, name });
    btn.classList.add('active');
    btn.textContent = '❤️';
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
      <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0.85rem; border: 1px solid var(--border-color); border-radius: 8px;">
        <span style="font-weight: 600;">${item.name}</span>
        <button type="button" class="btn btn-sm btn-danger" onclick="toggleWishlist('${item.id}', '${item.name}', this)">Xóa</button>
      </div>
    `).join('');
  }
}

// --------------------------------------------------------------------------
// 7. CỜ 5: LIVECHAT CHUYÊN VIÊN APPLE 24/7
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
// 8. CÁC HÀM TIỆN ÍCH KHÁC (TOAST, MODAL, SAO CHÉP MÃ)
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

function openCartView() {
  showToast('Giỏ hàng CDS STORE: iPhone 18 Pro Max 256GB & Củ sạc 20W Apple (Hotline: 0362481351)', 'info');
}

function openSearchModal() {
  showToast('Bộ lọc tìm kiếm CDS STORE: Lọc theo dòng máy, dung lượng hoặc hotline 0362481351 để đặt trước', 'info');
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>