<?php
/**
 * ===================================================================
 * DATABASE & HÀM ĐÁNH GIÁ TÍNH NĂNG (CORE BACKEND)
 * ===================================================================
 * Dự án: Hệ thống quản lý cấu hình Bật/Tắt tính năng cho Web Apple
 * Chuẩn: Zero-Dependency, sử dụng SQLite PDO nhẹ, không cần cài đặt MySQL
 */

// Đảm bảo thư mục data tồn tại
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$dbPath = $dataDir . '/feature_flags.sqlite';

/**
 * Kết nối CSDL SQLite bằng PDO
 */
function get_db() {
    global $dbPath;
    static $pdo = null;
    
    if ($pdo === null) {
        $isNew = !file_exists($dbPath);
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Đảm bảo schema và dữ liệu được nạp đầy đủ
        init_database_schema($pdo);
        if ($isNew || filesize($dbPath) === 0) {
            seed_initial_data($pdo);
        }
        seed_projects_and_products_if_empty($pdo);
    }
    return $pdo;
}

/**
 * 1. Khởi tạo 6 bảng dữ liệu tối thiểu theo đúng yêu cầu đề tài
 */
function init_database_schema($pdo) {
    // Bảng 0: projects - Quản lý dự án (Chức năng 1)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS projects (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            code TEXT NOT NULL UNIQUE,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Bảng 1: users - Tài khoản và quyền (xem/sua)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id TEXT PRIMARY KEY,
            username TEXT NOT NULL UNIQUE,
            fullname TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ('xem', 'sua')),
            user_group TEXT DEFAULT 'Khách thường'
        )
    ");

    // Bảng 2: environments - Danh sách môi trường
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS environments (
            code TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            description TEXT
        )
    ");

    // Bảng 3: flags - Danh sách tính năng
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS flags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key TEXT NOT NULL,
            name TEXT NOT NULL,
            description TEXT,
            env TEXT NOT NULL,
            enabled INTEGER DEFAULT 1,
            fallback INTEGER DEFAULT 0,
            percentage INTEGER DEFAULT 50,
            whitelist TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(key, env)
        )
    ");

    // Bảng 4: versions - Bản chụp cấu hình (Snapshot dạng JSON), trạng thái nháp/đã phát hành
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS versions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            version_tag TEXT NOT NULL,
            env TEXT NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            snapshot_json TEXT NOT NULL,
            status TEXT NOT NULL CHECK(status IN ('nhap', 'da_phat_hanh')),
            created_by TEXT DEFAULT 'admin',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(version_tag, env)
        )
    ");

    // Bảng 5: history - Lịch sử thay đổi và lý do
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            env TEXT NOT NULL,
            action TEXT NOT NULL,
            version_tag TEXT,
            author TEXT NOT NULL,
            reason TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Bảng 6: products - Danh sách sản phẩm iPhone, Mac, iPad liên kết tính năng
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id TEXT PRIMARY KEY,
            stt INTEGER DEFAULT 0,
            sku TEXT NOT NULL,
            name TEXT NOT NULL,
            module TEXT NOT NULL,
            price INTEGER DEFAULT 0,
            stock INTEGER DEFAULT 0,
            image TEXT,
            is_home INTEGER DEFAULT 1,
            is_menu INTEGER DEFAULT 1,
            is_visible INTEGER DEFAULT 1,
            status TEXT DEFAULT 'Đang bán',
            status_seo TEXT DEFAULT 'Dofollow,Index',
            feature_flag TEXT DEFAULT '',
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

/**
 * 2. Nạp dữ liệu giả lập ban đầu: 2 môi trường, 10 tính năng Apple, 3 nhóm, 1.000 user
 */
function seed_initial_data($pdo) {
    // 2.1. Thêm 2 môi trường
    $stmtEnv = $pdo->prepare("INSERT OR IGNORE INTO environments (code, name, description) VALUES (?, ?, ?)");
    $stmtEnv->execute(['thu_nghiem', 'Thử Nghiệm (Staging)', 'Môi trường kiểm thử tính năng mới trước khi ra mắt']);
    $stmtEnv->execute(['van_hanh', 'Vận Hành (Production)', 'Môi trường thực tế phục vụ khách hàng Apple']);

    // 2.2. Thêm các tài khoản quản trị mẫu
    $stmtUser = $pdo->prepare("INSERT OR IGNORE INTO users (id, username, fullname, role, user_group) VALUES (?, ?, ?, ?, ?)");
    $stmtUser->execute(['ADMIN_01', 'admin', 'Nguyễn Quản Trị', 'sua', 'Nội bộ Apple']);
    $stmtUser->execute(['EDITOR_01', 'developer', 'Lê Kỹ Sư Web', 'sua', 'Nội bộ Apple']);
    $stmtUser->execute(['VIEWER_01', 'viewer', 'Trần Khách Xem', 'xem', 'Khách dự thính']);

    // 2.3. Sinh tự động 1.000 mã người dùng từ U0001 đến U1000
    // Phân vào 3 nhóm: VIP (10%), Beta (20%), Khách thường (70%)
    $pdo->beginTransaction();
    for ($i = 1; $i <= 1000; $i++) {
        $uId = sprintf('U%04d', $i);
        $group = 'Khách thường';
        if ($i <= 100) {
            $group = 'VIP';
        } elseif ($i <= 300) {
            $group = 'Beta Tester';
        }
        $stmtUser->execute([$uId, strtolower($uId), "Người dùng $uId", 'xem', $group]);
    }
    $pdo->commit();

    // 2.4. Danh sách 10 tính năng mẫu gắn với Web Apple
    $defaultFlags = [
        [
            'key' => 'banner_iphone_17',
            'name' => 'Banner iPhone 17 Pro Max',
            'desc' => 'Hiển thị banner động giới thiệu iPhone 17 Pro Titanium với chip A19 Pro',
            'pct_test' => 100, // ở thử nghiệm bật 100%
            'pct_prod' => 40,  // ở vận hành bật 40%
            'whitelist' => 'U0001,U0002,U0003,U0005'
        ],
        [
            'key' => 'btn_preorder_17',
            'name' => 'Nút đặt trước iPhone 17',
            'desc' => 'Nút Đặt Trước Nhận Ưu Đãi 2.000.000đ khi thanh toán sớm',
            'pct_test' => 100,
            'pct_prod' => 50,
            'whitelist' => 'U0001,U0010'
        ],
        [
            'key' => 'tet_holiday_theme',
            'name' => 'Giao diện Tết Apple Store',
            'desc' => 'Giao diện lễ hội Tết Nguyên Đán với hoa mai vàng rực rỡ và pháo hoa may mắn',
            'pct_test' => 80,
            'pct_prod' => 30,
            'whitelist' => 'U0001,U0099'
        ],
        [
            'key' => 'ai_accessories',
            'name' => 'Gợi ý phụ kiện AI MagSafe',
            'desc' => 'Tự động gợi ý ốp lưng Titanium và củ sạc nhanh MagSafe thế hệ mới',
            'pct_test' => 90,
            'pct_prod' => 60,
            'whitelist' => 'U0001,U0002'
        ],
        [
            'key' => 'laser_engraving',
            'name' => 'Khắc Laser Miễn Phí',
            'desc' => 'Cho phép khách hàng khắc emoji hoặc tên riêng lên hộp sạc AirPods và iPad',
            'pct_test' => 100,
            'pct_prod' => 70,
            'whitelist' => 'U0001,U0007'
        ],
        [
            'key' => 'apple_pay_0percent',
            'name' => 'Trả góp 0% Apple Pay',
            'desc' => 'Hỗ trợ trả góp 0% lãi suất kỳ hạn 12 tháng qua thẻ tín dụng và Apple Pay',
            'pct_test' => 100,
            'pct_prod' => 80,
            'whitelist' => 'U0001,U0002,U0003'
        ],
        [
            'key' => 'express_delivery_2h',
            'name' => 'Giao hàng hỏa tốc 2 giờ',
            'desc' => 'Giao hàng tận nơi siêu tốc trong 2 giờ tại khu vực TP.HCM và Hà Nội',
            'pct_test' => 100,
            'pct_prod' => 50,
            'whitelist' => 'U0001'
        ],
        [
            'key' => 'trade_in_bonus',
            'name' => 'Thu cũ đổi mới Trade-in',
            'desc' => 'Trợ giá lên đời máy cũ đến 5.000.000đ khi đổi sang iPhone 17 Series',
            'pct_test' => 90,
            'pct_prod' => 50,
            'whitelist' => 'U0001,U0050'
        ],
        [
            'key' => 'gift_card_custom',
            'name' => 'Thẻ quà tặng Apple Gift Card',
            'desc' => 'Tặng thẻ quà tặng điện tử cho người thân kèm thiệp chúc mừng độc quyền',
            'pct_test' => 70,
            'pct_prod' => 20,
            'whitelist' => 'U0001'
        ],
        [
            'key' => 'genius_bar_live',
            'name' => 'Tư vấn trực tiếp Genius Bar',
            'desc' => 'Nút kết nối video trực tiếp 1:1 với chuyên viên kỹ thuật Apple Genius Bar',
            'pct_test' => 50,
            'pct_prod' => 10,
            'whitelist' => 'U0001,U0002'
        ]
    ];

    $stmtFlag = $pdo->prepare("
        INSERT OR IGNORE INTO flags (key, name, description, env, enabled, fallback, percentage, whitelist)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($defaultFlags as $df) {
        // Môi trường Thử nghiệm
        $stmtFlag->execute([$df['key'], $df['name'], $df['desc'], 'thu_nghiem', 1, 0, $df['pct_test'], $df['whitelist']]);
        // Môi trường Vận hành
        $stmtFlag->execute([$df['key'], $df['name'], $df['desc'], 'van_hanh', 1, 0, $df['pct_prod'], $df['whitelist']]);
    }

    // 2.5. Tạo bản phát hành v1 ban đầu cho cả 2 môi trường
    foreach (['thu_nghiem', 'van_hanh'] as $env) {
        $stmt = $pdo->prepare("SELECT * FROM flags WHERE env = ?");
        $stmt->execute([$env]);
        $allFlags = $stmt->fetchAll();
        $snapshot = json_encode($allFlags, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $stmtVer = $pdo->prepare("
            INSERT OR IGNORE INTO versions (version_tag, env, title, description, snapshot_json, status, created_by)
            VALUES (?, ?, ?, ?, ?, 'da_phat_hanh', 'admin')
        ");
        $stmtVer->execute(['v1', $env, 'Phiên bản khởi tạo v1', 'Phát hành cấu hình 10 tính năng Apple ban đầu', $snapshot]);

        $stmtHist = $pdo->prepare("
            INSERT INTO history (env, action, version_tag, author, reason)
            VALUES (?, 'phat_hanh', 'v1', 'admin', 'Khởi tạo hệ thống lần đầu')
        ");
        $stmtHist->execute([$env]);
    }
}

/**
 * 2.6. Khởi tạo Projects và Products mẫu (iPhone, Mac, iPad)
 */
function seed_projects_and_products_if_empty($pdo) {
    // Projects
    $projCount = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
    if ($projCount == 0) {
        $stmtProj = $pdo->prepare("INSERT INTO projects (id, name, code, description) VALUES (?, ?, ?, ?)");
        $stmtProj->execute(['prj_ecommerce', 'TechStore E-Commerce (Apple Shop)', 'techstore', 'Hệ thống thương mại điện tử chuyên sản phẩm Apple']);
        $stmtProj->execute(['prj_crm', 'Enterprise CRM Portal', 'crm', 'Cổng thông tin quản lý quan hệ khách hàng']);
    }

    // Products
    $prodCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    if ($prodCount == 0) {
        $products = [
            ['prod_ip16pm', 1, 'APL-IP16PM-256', 'iPhone 16 Pro Max 256GB Desert Titanium', 'iPhone', 34990000, 25, 'https://images.unsplash.com/photo-1695048133142-1a20484d2569?w=150&auto=format&fit=crop&q=80', 1, 1, 1, 'Đang bán', 'Dofollow,Index', 'banner_iphone_17', 'Khung Titan sa mạc cao cấp, chip A18 Pro, Camera Fusion 48MP zoom quang 5x'],
            ['prod_ip16', 2, 'APL-IP16-128', 'iPhone 16 128GB Ultramarine Blue', 'iPhone', 22990000, 18, 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=150&auto=format&fit=crop&q=80', 1, 1, 1, 'Đang bán', 'Dofollow,Index', 'btn_preorder_17', 'Màu xanh lưu ly thời thượng, nút Camera Control và Action Button thế hệ mới'],
            ['prod_ip15p', 3, 'APL-IP15P-128', 'iPhone 15 Pro 128GB Natural Titanium', 'iPhone', 24490000, 4, 'https://images.unsplash.com/photo-1574944985070-8f3ebc6b79d2?w=150&auto=format&fit=crop&q=80', 1, 0, 1, 'Sắp hết', 'Dofollow,Index', 'trade_in_bonus', 'Chip A17 Pro Ray Tracing, khung Titan tự nhiên bền bỉ và cổng USB-C tốc độ cao'],
            ['prod_ip15', 4, 'APL-IP15-128', 'iPhone 15 128GB Pink Pastel', 'iPhone', 17490000, 12, 'https://images.unsplash.com/photo-1510557880182-3d4d3cba35a5?w=150&auto=format&fit=crop&q=80', 0, 1, 1, 'Đang bán', 'Dofollow,Index', 'apple_pay_0percent', 'Dynamic Island hiển thị thông báo trực quan sống động, camera 48MP sắc nét'],
            ['prod_ipad_pro13', 5, 'APL-IPADPRO13-M4', 'iPad Pro 13 inch M4 Wi-Fi 256GB Space Black', 'iPad', 33990000, 9, 'https://images.unsplash.com/photo-1544244015-0df4b3ffc6b0?w=150&auto=format&fit=crop&q=80', 1, 1, 1, 'Đang bán', 'Dofollow,Index', 'laser_engraving', 'Màn hình Ultra Retina XDR Tandem OLED, mỏng nhất lịch sử Apple chỉ 5.1mm, chip M4'],
            ['prod_ipad_air11', 6, 'APL-IPADAIR11-M2', 'iPad Air 11 inch M2 Wi-Fi 128GB Starlight', 'iPad', 16990000, 15, 'https://images.unsplash.com/photo-1561154464-82e9adf32764?w=150&auto=format&fit=crop&q=80', 1, 1, 1, 'Đang bán', 'Dofollow,Index', 'ai_accessories', 'Sức mạnh chip Apple M2, hỗ trợ bút Apple Pencil Pro cảm ứng xoay và bóp'],
            ['prod_ipad_mini7', 7, 'APL-IPADMINI7-128', 'iPad mini 7 Wi-Fi 128GB Blue A17 Pro', 'iPad', 13990000, 7, 'https://images.unsplash.com/photo-1589739900243-4b52cd9b104e?w=150&auto=format&fit=crop&q=80', 0, 1, 1, 'Đang bán', 'Dofollow,Index', 'express_delivery_2h', 'Kích thước nhỏ gọn 8.3 inch, chip A17 Pro hỗ trợ Apple Intelligence'],
            ['prod_ipad_gen10', 8, 'APL-IPAD10-64', 'iPad Gen 10 10.9 inch Wi-Fi 64GB Silver', 'iPad', 8990000, 0, 'https://images.unsplash.com/photo-1542751371-adc38448a05e?w=150&auto=format&fit=crop&q=80', 0, 0, 1, 'Hết hàng', 'Dofollow,Index', 'apple_pay_0percent', 'Thiết kế viền mỏng 4 cạnh thời trang, cổng USB-C, camera ngang tiện lợi'],
            ['prod_mbp14_m4p', 9, 'APL-MBP14-M4P', 'MacBook Pro 14 inch M4 Pro 24GB/512GB Space Black', 'Mac', 49990000, 6, 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=150&auto=format&fit=crop&q=80', 1, 1, 1, 'Đang bán', 'Dofollow,Index', 'apple_pay_0percent', 'Màn hình Liquid Retina XDR 1600 nits, chip M4 Pro 14-core CPU, pin 24 giờ liên tục'],
            ['prod_mba13_m3', 10, 'APL-MBA13-M3', 'MacBook Air 13 inch M3 16GB/256GB Midnight Dark', 'Mac', 27990000, 14, 'https://images.unsplash.com/photo-1611186871348-b1ce696e52c9?w=150&auto=format&fit=crop&q=80', 1, 1, 1, 'Đang bán', 'Dofollow,Index', 'trade_in_bonus', 'Nhôm nguyên khối mỏng nhẹ 1.24kg, hỗ trợ xuất cùng lúc 2 màn hình ngoài'],
            ['prod_mba15_m3', 11, 'APL-MBA15-M3', 'MacBook Air 15 inch M3 16GB/512GB Starlight Gold', 'Mac', 32990000, 8, 'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?w=150&auto=format&fit=crop&q=80', 1, 1, 1, 'Đang bán', 'Dofollow,Index', 'genius_bar_live', 'Không gian hiển thị rộng rãi 15.3 inch, 6 loa âm thanh Spatial Audio đỉnh cao'],
            ['prod_imac24_m4', 12, 'APL-IMAC24-M4', 'iMac 24 inch 4.5K Retina M4 16GB/256GB Silver', 'Mac', 34990000, 3, 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=150&auto=format&fit=crop&q=80', 0, 1, 1, 'Sắp hết', 'Dofollow,Index', 'tet_holiday_theme', 'Máy tính Tất-Cả-Trong-Một Retina 4.5K siêu nét 500 nits, camera Center Stage 12MP'],
            ['prod_macmini_m4', 13, 'APL-MACMINI-M4', 'Mac mini M4 16GB/256GB Silver Ultra Compact', 'Mac', 14990000, 19, 'https://images.unsplash.com/photo-1587831990711-23ca6441447b?w=150&auto=format&fit=crop&q=80', 0, 0, 1, 'Đang bán', 'Dofollow,Index', 'gift_card_custom', 'Kích thước siêu mini 12.7 x 12.7 cm nhỏ gọn trong lòng bàn tay, chip Apple M4']
        ];

        $stmtProd = $pdo->prepare("
            INSERT OR REPLACE INTO products (id, stt, sku, name, module, price, stock, image, is_home, is_menu, is_visible, status, status_seo, feature_flag, description)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($products as $p) {
            $stmtProd->execute($p);
        }
    }
}

/**
 * 3. HÀM QUYẾT ĐỊNH BẬT / TẮT (THE HEART OF FEATURE FLAGS)
 * Theo đúng công thức yêu cầu:
 * - crc32(user + flag + env) % 100 < tỷ_lệ (KHÔNG DÙNG RANDOM)
 * - Thứ tự ưu tiên:
 *   1. Danh sách người dùng (Whitelist) -> LUÔN BẬT (xét trước)
 *   2. Công tắc chính của cờ -> Nếu tắt thì TẮT
 *   3. Tỷ lệ % -> Xét sau bằng thuật toán băm
 */
function evaluate_feature_flag($user, $flag_key, $env, $customFlagsList = null) {
    $user = trim((string)$user);
    $flag_key = trim((string)$flag_key);
    $env = trim((string)$env);

    if (empty($user)) {
        $user = 'ANONYMOUS';
    }

    // Lấy cấu hình tính năng
    $flag = null;
    if ($customFlagsList !== null) {
        // Tìm trong danh sách truyền vào (ví dụ khi đánh giá trên một bản snapshot phiên bản)
        foreach ($customFlagsList as $f) {
            if ($f['key'] === $flag_key) {
                $flag = $f;
                break;
            }
        }
    } else {
        // Tìm trong CSDL môi trường hiện tại
        // Ưu tiên đọc từ bản phát hành mới nhất (da_phat_hanh)
        $pdo = get_db();
        $stmtVer = $pdo->prepare("
            SELECT snapshot_json FROM versions 
            WHERE env = ? AND status = 'da_phat_hanh' 
            ORDER BY id DESC LIMIT 1
        ");
        $stmtVer->execute([$env]);
        $verRow = $stmtVer->fetch();

        if ($verRow && !empty($verRow['snapshot_json'])) {
            $flagsInVer = json_decode($verRow['snapshot_json'], true);
            if (is_array($flagsInVer)) {
                foreach ($flagsInVer as $f) {
                    if ($f['key'] === $flag_key) {
                        $flag = $f;
                        break;
                    }
                }
            }
        }

        // Nếu chưa có bản phát hành, lấy từ bảng flags (bản nháp)
        if (!$flag) {
            $stmt = $pdo->prepare("SELECT * FROM flags WHERE key = ? AND env = ?");
            $stmt->execute([$flag_key, $env]);
            $flag = $stmt->fetch();
        }
    }

    // Nếu không tìm thấy tính năng, trả về mặc định TẮT
    if (!$flag) {
        return [
            'enabled' => false,
            'reason' => 'Không tìm thấy cờ tính năng: ' . $flag_key,
            'hash_bucket' => -1,
            'rule_applied' => 'not_found',
            'fallback' => 0
        ];
    }

    // Tính mã băm tất định (Deterministic Hash Bucket: 0 đến 99)
    // Dùng sprintf('%u') để đảm bảo luôn là số nguyên dương không dấu
    $hashString = $user . $flag_key . $env;
    $rawCrc = sprintf('%u', crc32($hashString));
    $hashBucket = (int)($rawCrc % 100);

    // QUY TẮC 1: Danh sách người dùng (Whitelist) xét trước
    $whitelistArr = array_filter(array_map('trim', explode(',', $flag['whitelist'] ?? '')));
    if (in_array($user, $whitelistArr, true)) {
        return [
            'enabled' => true,
            'reason' => "Người dùng [$user] nằm trong danh sách ưu tiên (Whitelist)",
            'hash_bucket' => $hashBucket,
            'rule_applied' => 'whitelist',
            'percentage' => (int)$flag['percentage'],
            'fallback' => (int)$flag['fallback']
        ];
    }

    // QUY TẮC 2: Kiểm tra công tắc chính (Master Switch)
    if (empty($flag['enabled']) || (int)$flag['enabled'] === 0) {
        return [
            'enabled' => false,
            'reason' => "Tính năng đang bị TẮT bởi công tắc chính",
            'hash_bucket' => $hashBucket,
            'rule_applied' => 'master_switch_off',
            'percentage' => (int)$flag['percentage'],
            'fallback' => (int)$flag['fallback']
        ];
    }

    // QUY TẮC 3: Tỷ lệ % (0 đến 100)
    $pct = (int)($flag['percentage'] ?? 0);
    if ($pct <= 0) {
        return [
            'enabled' => false,
            'reason' => "Tỷ lệ áp dụng là 0% (Luôn tắt cho người dùng không thuộc Whitelist)",
            'hash_bucket' => $hashBucket,
            'rule_applied' => 'percentage_zero',
            'percentage' => 0,
            'fallback' => (int)$flag['fallback']
        ];
    }
    if ($pct >= 100) {
        return [
            'enabled' => true,
            'reason' => "Tỷ lệ áp dụng là 100% (Luôn bật cho toàn bộ người dùng)",
            'hash_bucket' => $hashBucket,
            'rule_applied' => 'percentage_hundred',
            'percentage' => 100,
            'fallback' => (int)$flag['fallback']
        ];
    }

    // Xét băm: crc32(user + flag + env) % 100 < tỷ_lệ
    if ($hashBucket < $pct) {
        return [
            'enabled' => true,
            'reason' => "Mã băm ($hashBucket) < Tỷ lệ áp dụng ($pct%) -> BẬT",
            'hash_bucket' => $hashBucket,
            'rule_applied' => 'percentage_bucket_in',
            'percentage' => $pct,
            'fallback' => (int)$flag['fallback']
        ];
    } else {
        return [
            'enabled' => false,
            'reason' => "Mã băm ($hashBucket) >= Tỷ lệ áp dụng ($pct%) -> TẮT",
            'hash_bucket' => $hashBucket,
            'rule_applied' => 'percentage_bucket_out',
            'percentage' => $pct,
            'fallback' => (int)$flag['fallback']
        ];
    }
}

/**
 * 4. HÀM KIỂM TRA PHÂN QUYỀN VÀ CHẶN Ở SERVER
 * Yêu cầu quan trọng: "Tài khoản Xem không bấm được nút sửa, và server cũng từ chối nếu gửi yêu cầu sửa trực tiếp (HTTP 403)"
 */
function require_editor_permission() {
    // Nhận role qua Header X-User-Role hoặc query param hoặc body
    $role = $_SERVER['HTTP_X_USER_ROLE'] ?? $_GET['role'] ?? $_POST['role'] ?? 'sua';
    $role = strtolower(trim($role));

    if ($role === 'xem' || $role === 'viewer') {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'status' => 'error',
            'error' => 'HTTP 403 Forbidden: Tài khoản của bạn chỉ có quyền XEM. Máy chủ từ chối thực hiện yêu cầu sửa đổi!',
            'role' => 'xem'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit();
    }
}

/**
 * Trả về phản hồi JSON chuẩn
 */
function send_json_response($data, $statusCode = 200) {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}
