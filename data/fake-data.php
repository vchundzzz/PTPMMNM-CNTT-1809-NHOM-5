<?php
/**
 * Hệ thống Quản lý Feature Flags
 * data/fake-data.php – Dữ liệu mẫu ban đầu
 * Mã khách hàng dạng KH1 … KH1000
 */

declare(strict_types=1);

/**
 * Sinh danh sách 1.000 mã khách hàng giả định KH1 → KH1000
 */
function generate_fake_users(): array
{
    static $cached = null;
    if ($cached !== null) return $cached;

    if (PHP_SAPI !== 'cli') {
        try {
            require_once __DIR__ . '/../services/DatabaseService.php';
            $dbUsers = DatabaseService::loadCustomers();
            if (!empty($dbUsers)) {
                return $cached = $dbUsers;
            }
        } catch (Throwable $e) {}
    }

    $users = [];
    for ($i = 1; $i <= 1000; $i++) {
        $id = 'KH' . $i;
        if ($i <= 50)        { $gid = 'nhom_noibu';  $gname = 'Nội bộ'; }
        elseif ($i <= 250)   { $gid = 'nhom_beta';   $gname = 'Khách Beta'; }
        else                 { $gid = 'nhom_chung';  $gname = 'Khách Thường'; }
        $users[] = [
            'id'         => $id,
            'name'       => 'Khách hàng ' . $id,
            'email'      => strtolower($id) . '@demo.local',
            'group_id'   => $gid,
            'group_name' => $gname,
        ];
    }
    return $cached = $users;
}

/**
 * Trả về toàn bộ dữ liệu mẫu gốc
 */
function get_initial_fake_data(): array
{
    return [
        // 1. Dự án
        'projects' => [
            'prj_ecommerce' => [
                'id'          => 'prj_ecommerce',
                'key'         => 'ecommerce_web',
                'name'        => 'Cửa hàng Thương mại Điện tử',
                'description' => 'Hệ thống bán lẻ công nghệ Apple phục vụ hàng triệu khách hàng trực tuyến.',
                'created_at'  => '2026-01-10 08:30:00',
                'updated_at'  => '2026-10-20 14:15:00',
                'status'      => 'Hoạt động',
            ],
        ],

        // 2. Môi trường
        'environments' => [
            'testing' => [
                'id'            => 'env_testing',
                'key'           => 'testing',
                'name'          => 'Kiểm thử (Testing)',
                'description'   => 'Môi trường kiểm thử nội bộ dành cho đội QA và lập trình viên.',
                'badge_color'   => 'warning',
                'is_production' => false,
                'created_at'    => '2026-01-10 09:00:00',
            ],
            'staging' => [
                'id'            => 'env_staging',
                'key'           => 'staging',
                'name'          => 'Vận hành (Staging)',
                'description'   => 'Môi trường vận hành dùng để kiểm tra cấu hình trước khi áp dụng thực tế.',
                'badge_color'   => 'primary',
                'is_production' => false,
                'created_at'    => '2026-01-10 09:00:00',
            ],
        ],

        // 3. Nhóm người dùng
        'groups' => [
            'nhom_noibu' => [
                'id'          => 'nhom_noibu',
                'key'         => 'noi_bo',
                'name'        => 'Nội bộ',
                'description' => 'Đội ngũ lập trình viên, QA và kiểm thử viên nội bộ công ty (50 tài khoản).',
                'user_count'  => 50,
                'range'       => 'KH1 – KH50',
                'created_at'  => '2026-01-12 10:00:00',
            ],
            'nhom_beta' => [
                'id'          => 'nhom_beta',
                'key'         => 'beta',
                'name'        => 'Khách Beta',
                'description' => 'Khách hàng đăng ký trải nghiệm sớm tính năng mới (200 tài khoản).',
                'user_count'  => 200,
                'range'       => 'KH51 – KH250',
                'created_at'  => '2026-01-12 10:00:00',
            ],
            'nhom_chung' => [
                'id'          => 'nhom_chung',
                'key'         => 'chung',
                'name'        => 'Khách Thường',
                'description' => 'Toàn bộ khách hàng phổ thông mua sắm trên nền tảng (750 tài khoản).',
                'user_count'  => 750,
                'range'       => 'KH251 – KH1000',
                'created_at'  => '2026-01-12 10:00:00',
            ],
        ],

        // 4. Tài khoản demo
        'members' => [
            'admin' => [
                'id'         => 'usr_admin',
                'username'   => 'admin',
                'password'   => 'admin123',
                'name'       => 'Admin',
                'email'      => 'admin@featuremanager.local',
                'role'       => 'admin',
                'role_title' => 'Admin',
                'avatar'     => 'A',
                'created_at' => '2026-01-01 00:00:00',
                'last_login' => '2026-10-27 15:00:00',
            ],
            'editor' => [
                'id'         => 'usr_editor',
                'username'   => 'editor',
                'password'   => 'editor123',
                'name'       => 'Biên tập',
                'email'      => 'editor@featuremanager.local',
                'role'       => 'editor',
                'role_title' => 'Biên tập',
                'avatar'     => 'BT',
                'created_at' => '2026-01-05 08:30:00',
                'last_login' => '2026-10-26 11:20:00',
            ],
            'viewer' => [
                'id'         => 'usr_viewer',
                'username'   => 'viewer',
                'password'   => 'viewer123',
                'name'       => 'Viewer',
                'email'      => 'viewer@featuremanager.local',
                'role'       => 'viewer',
                'role_title' => 'Viewer',
                'avatar'     => 'V',
                'created_at' => '2026-01-10 14:00:00',
                'last_login' => '2026-10-25 09:45:00',
            ],
        ],

        // 5. Feature Flags
        'flags' => [
            'thanh_toan_nhanh' => [
                'id'          => 'flg_001',
                'key'         => 'thanh_toan_nhanh',
                'name'        => 'Thanh toán một bước',
                'description' => 'Rút gọn quy trình thanh toán còn 1 trang, tăng 25% tỷ lệ chuyển đổi đơn hàng.',
                'project_id'  => 'prj_ecommerce',
                'environments' => [
                    'testing' => [
                        'enabled'             => true,
                        'rollout_percentage'  => 100,
                        'status'              => 'Đã phát hành',
                        'target_groups'       => ['nhom_noibu', 'nhom_beta', 'nhom_chung'],
                        'user_overrides'      => ['KH1' => true, 'KH2' => false],
                        'updated_at'          => '2026-10-25 10:30:00',
                        'updated_by'          => 'admin',
                    ],
                    'staging' => [
                        'enabled'             => true,
                        'rollout_percentage'  => 50,
                        'status'              => 'Đã phát hành',
                        'target_groups'       => ['nhom_noibu', 'nhom_beta'],
                        'user_overrides'      => ['KH1' => true, 'KH10' => false],
                        'updated_at'          => '2026-10-26 14:15:00',
                        'updated_by'          => 'editor',
                    ],
                ],
            ],
            'giao_dien_toi' => [
                'id'          => 'flg_002',
                'key'         => 'giao_dien_toi',
                'name'        => 'Giao diện ban đêm',
                'description' => 'Kích hoạt chủ đề nền tối giúp bảo vệ mắt và tiết kiệm pin màn hình OLED.',
                'project_id'  => 'prj_ecommerce',
                'environments' => [
                    'testing' => [
                        'enabled'            => true,
                        'rollout_percentage' => 100,
                        'status'             => 'Đã phát hành',
                        'target_groups'      => ['nhom_noibu', 'nhom_beta', 'nhom_chung'],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-24 16:45:00',
                        'updated_by'         => 'admin',
                    ],
                    'staging' => [
                        'enabled'            => true,
                        'rollout_percentage' => 50,
                        'status'             => 'Đã phát hành',
                        'target_groups'      => ['nhom_noibu', 'nhom_beta'],
                        'user_overrides'     => ['KH5' => true],
                        'updated_at'         => '2026-10-25 09:20:00',
                        'updated_by'         => 'editor',
                    ],
                ],
            ],
            'danh_gia_san_pham' => [
                'id'          => 'flg_003',
                'key'         => 'danh_gia_san_pham',
                'name'        => 'Đánh giá sản phẩm có hình ảnh',
                'description' => 'Cho phép khách hàng đính kèm ảnh thực tế khi đánh giá sao sản phẩm.',
                'project_id'  => 'prj_ecommerce',
                'environments' => [
                    'testing' => [
                        'enabled'            => true,
                        'rollout_percentage' => 100,
                        'status'             => 'Đã phát hành',
                        'target_groups'      => ['nhom_noibu'],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-22 11:10:00',
                        'updated_by'         => 'editor',
                    ],
                    'staging' => [
                        'enabled'            => true,
                        'rollout_percentage' => 10,
                        'status'             => 'Đã phát hành',
                        'target_groups'      => ['nhom_noibu'],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-23 15:30:00',
                        'updated_by'         => 'admin',
                    ],
                ],
            ],
            'danh_sach_yeu_thich' => [
                'id'          => 'flg_004',
                'key'         => 'danh_sach_yeu_thich',
                'name'        => 'Danh sách sản phẩm yêu thích',
                'description' => 'Lưu sản phẩm yêu thích và nhận thông báo khi giá giảm.',
                'project_id'  => 'prj_ecommerce',
                'environments' => [
                    'testing' => [
                        'enabled'            => true,
                        'rollout_percentage' => 100,
                        'status'             => 'Đã phát hành',
                        'target_groups'      => ['nhom_noibu', 'nhom_beta'],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-21 08:45:00',
                        'updated_by'         => 'admin',
                    ],
                    'staging' => [
                        'enabled'            => false,
                        'rollout_percentage' => 0,
                        'status'             => 'Bản nháp',
                        'target_groups'      => [],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-22 13:00:00',
                        'updated_by'         => 'editor',
                    ],
                ],
            ],
            'ho_tro_truc_tuyen' => [
                'id'          => 'flg_005',
                'key'         => 'ho_tro_truc_tuyen',
                'name'        => 'Hỗ trợ trực tuyến (Live Chat)',
                'description' => 'Widget chat hỗ trợ khách hàng kết nối trực tiếp tư vấn viên 24/7.',
                'project_id'  => 'prj_ecommerce',
                'environments' => [
                    'testing' => [
                        'enabled'            => true,
                        'rollout_percentage' => 50,
                        'status'             => 'Đã phát hành',
                        'target_groups'      => ['nhom_noibu'],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-20 14:00:00',
                        'updated_by'         => 'editor',
                    ],
                    'staging' => [
                        'enabled'            => false,
                        'rollout_percentage' => 0,
                        'status'             => 'Bản nháp',
                        'target_groups'      => [],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-20 14:00:00',
                        'updated_by'         => 'admin',
                    ],
                ],
            ],
            'ma_giam_gia' => [
                'id'          => 'flg_006',
                'key'         => 'ma_giam_gia',
                'name'        => 'Tự động gợi ý mã giảm giá',
                'description' => 'Tự động kiểm tra và áp dụng voucher giảm giá tốt nhất cho giỏ hàng.',
                'project_id'  => 'prj_ecommerce',
                'environments' => [
                    'testing' => [
                        'enabled'            => true,
                        'rollout_percentage' => 100,
                        'status'             => 'Đã phát hành',
                        'target_groups'      => ['nhom_noibu', 'nhom_beta'],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-19 16:20:00',
                        'updated_by'         => 'admin',
                    ],
                    'staging' => [
                        'enabled'            => true,
                        'rollout_percentage' => 10,
                        'status'             => 'Đã phát hành',
                        'target_groups'      => ['nhom_beta'],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-20 10:15:00',
                        'updated_by'         => 'editor',
                    ],
                ],
            ],
            'goi_y_san_pham' => [
                'id'          => 'flg_007',
                'key'         => 'goi_y_san_pham',
                'name'        => 'Gợi ý sản phẩm bằng AI',
                'description' => 'Đề xuất phụ kiện dựa trên hành vi duyệt web và lịch sử mua sắm.',
                'project_id'  => 'prj_ecommerce',
                'environments' => [
                    'testing' => [
                        'enabled'            => true,
                        'rollout_percentage' => 50,
                        'status'             => 'Đã phát hành',
                        'target_groups'      => ['nhom_noibu'],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-18 09:30:00',
                        'updated_by'         => 'editor',
                    ],
                    'staging' => [
                        'enabled'            => false,
                        'rollout_percentage' => 0,
                        'status'             => 'Bản nháp',
                        'target_groups'      => [],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-18 09:30:00',
                        'updated_by'         => 'admin',
                    ],
                ],
            ],
            'tim_kiem_nang_cao' => [
                'id'          => 'flg_008',
                'key'         => 'tim_kiem_nang_cao',
                'name'        => 'Tìm kiếm nâng cao đa tiêu chí',
                'description' => 'Lọc sản phẩm theo chip, RAM, màn hình và tầm giá chỉ trong vài giây.',
                'project_id'  => 'prj_ecommerce',
                'environments' => [
                    'testing' => [
                        'enabled'            => true,
                        'rollout_percentage' => 100,
                        'status'             => 'Đã phát hành',
                        'target_groups'      => ['nhom_noibu', 'nhom_beta'],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-17 11:00:00',
                        'updated_by'         => 'admin',
                    ],
                    'staging' => [
                        'enabled'            => true,
                        'rollout_percentage' => 50,
                        'status'             => 'Đã phát hành',
                        'target_groups'      => ['nhom_noibu', 'nhom_beta'],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-17 15:45:00',
                        'updated_by'         => 'editor',
                    ],
                ],
            ],
            'theo_doi_don_hang' => [
                'id'          => 'flg_009',
                'key'         => 'theo_doi_don_hang',
                'name'        => 'Theo dõi đơn hàng thời gian thực',
                'description' => 'Bản đồ trực quan cập nhật tọa độ tài xế giao hàng chặng cuối trong ngày.',
                'project_id'  => 'prj_ecommerce',
                'environments' => [
                    'testing' => [
                        'enabled'            => true,
                        'rollout_percentage' => 10,
                        'status'             => 'Bản nháp',
                        'target_groups'      => ['nhom_noibu'],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-16 10:15:00',
                        'updated_by'         => 'editor',
                    ],
                    'staging' => [
                        'enabled'            => false,
                        'rollout_percentage' => 0,
                        'status'             => 'Bản nháp',
                        'target_groups'      => [],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-16 10:15:00',
                        'updated_by'         => 'admin',
                    ],
                ],
            ],
            'tich_diem_thanh_vien' => [
                'id'          => 'flg_010',
                'key'         => 'tich_diem_thanh_vien',
                'name'        => 'Chương trình tích điểm hội viên',
                'description' => 'Tích lũy 2% giá trị hóa đơn thành điểm thưởng đổi quà tặng độc quyền.',
                'project_id'  => 'prj_ecommerce',
                'environments' => [
                    'testing' => [
                        'enabled'            => false,
                        'rollout_percentage' => 0,
                        'status'             => 'Bản nháp',
                        'target_groups'      => [],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-15 14:30:00',
                        'updated_by'         => 'admin',
                    ],
                    'staging' => [
                        'enabled'            => false,
                        'rollout_percentage' => 0,
                        'status'             => 'Bản nháp',
                        'target_groups'      => [],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-15 14:30:00',
                        'updated_by'         => 'admin',
                    ],
                ],
            ],
            'banner_quang_cao' => [
                'id'          => 'flg_011',
                'key'         => 'banner_quang_cao',
                'name'        => 'Banner quảng cáo trang chủ',
                'description' => 'Hiển thị thanh slider banner khuyến mãi các dòng sản phẩm Apple mới nhất.',
                'project_id'  => 'prj_ecommerce',
                'environments' => [
                    'testing' => [
                        'enabled'            => true,
                        'rollout_percentage' => 100,
                        'status'             => 'Đã phát hành',
                        'target_groups'      => ['nhom_noibu', 'nhom_beta', 'nhom_chung'],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-27 18:49:29',
                        'updated_by'         => 'admin',
                    ],
                    'staging' => [
                        'enabled'            => true,
                        'rollout_percentage' => 100,
                        'status'             => 'Đã phát hành',
                        'target_groups'      => ['nhom_noibu', 'nhom_beta', 'nhom_chung'],
                        'user_overrides'     => [],
                        'updated_at'         => '2026-10-27 18:49:29',
                        'updated_by'         => 'admin',
                    ],
                ],
            ],
        ],

        // 6. Phiên bản cấu hình
        'versions' => [
            'v1' => [
                'id'             => 'ver_001',
                'version_number' => 'v1.0.0',
                'tag'            => 'v1',
                'status'         => 'Lưu trữ',
                'environment'    => 'staging',
                'created_by'     => 'admin',
                'created_at'     => '2026-01-15 10:00:00',
                'reason'         => 'Khởi tạo cấu hình ban đầu cho 10 cờ tính năng mẫu.',
                'flags_snapshot' => [
                    'thanh_toan_nhanh'    => ['enabled' => false, 'rollout_percentage' => 0],
                    'giao_dien_toi'       => ['enabled' => false, 'rollout_percentage' => 0],
                    'danh_gia_san_pham'   => ['enabled' => false, 'rollout_percentage' => 0],
                    'danh_sach_yeu_thich' => ['enabled' => false, 'rollout_percentage' => 0],
                    'ho_tro_truc_tuyen'   => ['enabled' => false, 'rollout_percentage' => 0],
                    'ma_giam_gia'         => ['enabled' => false, 'rollout_percentage' => 0],
                    'goi_y_san_pham'      => ['enabled' => false, 'rollout_percentage' => 0],
                    'tim_kiem_nang_cao'   => ['enabled' => false, 'rollout_percentage' => 0],
                    'theo_doi_don_hang'   => ['enabled' => false, 'rollout_percentage' => 0],
                    'tich_diem_thanh_vien'=> ['enabled' => false, 'rollout_percentage' => 0],
                    'banner_quang_cao'    => ['enabled' => true,  'rollout_percentage' => 100],
                ],
            ],
            'v2' => [
                'id'             => 'ver_002',
                'version_number' => 'v1.1.0',
                'tag'            => 'v2',
                'status'         => 'Lưu trữ',
                'environment'    => 'staging',
                'created_by'     => 'editor',
                'created_at'     => '2026-06-20 15:30:00',
                'reason'         => 'Mở rộng thử nghiệm Giao diện tối và Tìm kiếm nâng cao lên 50% Staging.',
                'flags_snapshot' => [
                    'thanh_toan_nhanh'    => ['enabled' => false, 'rollout_percentage' => 0],
                    'giao_dien_toi'       => ['enabled' => true,  'rollout_percentage' => 50],
                    'danh_gia_san_pham'   => ['enabled' => false, 'rollout_percentage' => 0],
                    'danh_sach_yeu_thich' => ['enabled' => false, 'rollout_percentage' => 0],
                    'ho_tro_truc_tuyen'   => ['enabled' => false, 'rollout_percentage' => 0],
                    'ma_giam_gia'         => ['enabled' => false, 'rollout_percentage' => 0],
                    'goi_y_san_pham'      => ['enabled' => false, 'rollout_percentage' => 0],
                    'tim_kiem_nang_cao'   => ['enabled' => true,  'rollout_percentage' => 50],
                    'theo_doi_don_hang'   => ['enabled' => false, 'rollout_percentage' => 0],
                    'tich_diem_thanh_vien'=> ['enabled' => false, 'rollout_percentage' => 0],
                    'banner_quang_cao'    => ['enabled' => true,  'rollout_percentage' => 100],
                ],
            ],
            'v3' => [
                'id'             => 'ver_003',
                'version_number' => 'v1.2.0',
                'tag'            => 'v3',
                'status'         => 'Đang dùng',
                'environment'    => 'staging',
                'created_by'     => 'admin',
                'created_at'     => '2026-10-26 14:15:00',
                'reason'         => 'Phát hành Thanh toán nhanh 50% và kích hoạt Mã giảm giá 10% tại Staging.',
                'flags_snapshot' => [
                    'thanh_toan_nhanh'    => ['enabled' => true,  'rollout_percentage' => 50],
                    'giao_dien_toi'       => ['enabled' => true,  'rollout_percentage' => 50],
                    'danh_gia_san_pham'   => ['enabled' => true,  'rollout_percentage' => 10],
                    'danh_sach_yeu_thich' => ['enabled' => false, 'rollout_percentage' => 0],
                    'ho_tro_truc_tuyen'   => ['enabled' => false, 'rollout_percentage' => 0],
                    'ma_giam_gia'         => ['enabled' => true,  'rollout_percentage' => 10],
                    'goi_y_san_pham'      => ['enabled' => false, 'rollout_percentage' => 0],
                    'tim_kiem_nang_cao'   => ['enabled' => true,  'rollout_percentage' => 50],
                    'theo_doi_don_hang'   => ['enabled' => false, 'rollout_percentage' => 0],
                    'tich_diem_thanh_vien'=> ['enabled' => false, 'rollout_percentage' => 0],
                    'banner_quang_cao'    => ['enabled' => true,  'rollout_percentage' => 100],
                ],
            ],
        ],

        // 7. Lịch sử hoạt động
        'audit_logs' => [
            [
                'id'         => 'log_001',
                'actor'      => 'admin',
                'role'       => 'admin',
                'action'     => 'PHÁT_HÀNH_PHIÊN_BẢN',
                'target'     => 'Phiên bản v1.2.0',
                'old_value'  => 'v1.1.0 (Lưu trữ)',
                'new_value'  => 'v1.2.0 (Đang dùng)',
                'reason'     => 'Phát hành chính thức v1.2.0 trên Staging',
                'created_at' => '2026-10-26 14:15:00',
                'version'    => 'v1.2.0',
            ],
            [
                'id'         => 'log_002',
                'actor'      => 'editor',
                'role'       => 'editor',
                'action'     => 'CẬP_NHẬT_TỶ_LỆ',
                'target'     => 'thanh_toan_nhanh',
                'old_value'  => 'Tỷ lệ: 10%',
                'new_value'  => 'Tỷ lệ: 50%',
                'reason'     => 'Nâng tỷ lệ thử nghiệm sau phản hồi tích cực từ nhóm Beta',
                'created_at' => '2026-10-26 10:30:00',
                'version'    => 'v1.2.0',
            ],
            [
                'id'         => 'log_003',
                'actor'      => 'admin',
                'role'       => 'admin',
                'action'     => 'BẬT_TẮT_CỜ',
                'target'     => 'ma_giam_gia',
                'old_value'  => 'Tắt (0%)',
                'new_value'  => 'Bật (10%)',
                'reason'     => 'Kích hoạt thử nghiệm gợi ý voucher cho nhóm Beta',
                'created_at' => '2026-10-25 16:45:00',
                'version'    => 'v1.1.0',
            ],
            [
                'id'         => 'log_004',
                'actor'      => 'editor',
                'role'       => 'editor',
                'action'     => 'CẬP_NHẬT_OVERRIDE',
                'target'     => 'thanh_toan_nhanh',
                'old_value'  => 'Không có override',
                'new_value'  => 'KH1=Bật, KH2=Tắt',
                'reason'     => 'Thêm tài khoản ngoại lệ cho tester nội bộ',
                'created_at' => '2026-10-25 11:20:00',
                'version'    => 'v1.1.0',
            ],
            [
                'id'         => 'log_005',
                'actor'      => 'admin',
                'role'       => 'admin',
                'action'     => 'CẬP_NHẬT_TỶ_LỆ',
                'target'     => 'giao_dien_toi',
                'old_value'  => 'Tỷ lệ: 0%',
                'new_value'  => 'Tỷ lệ: 100% (Testing)',
                'reason'     => 'Mở toàn diện Giao diện tối trên môi trường Kiểm thử',
                'created_at' => '2026-10-24 16:45:00',
                'version'    => 'v1.1.0',
            ],
            [
                'id'         => 'log_006',
                'actor'      => 'editor',
                'role'       => 'editor',
                'action'     => 'BẬT_TẮT_CỜ',
                'target'     => 'danh_gia_san_pham',
                'old_value'  => 'Tắt',
                'new_value'  => 'Bật (10% Staging)',
                'reason'     => 'Bắt đầu thử nghiệm đánh giá sản phẩm có ảnh',
                'created_at' => '2026-10-23 15:30:00',
                'version'    => 'v1.1.0',
            ],
            [
                'id'         => 'log_007',
                'actor'      => 'admin',
                'role'       => 'admin',
                'action'     => 'TẠO_CỜ_MỚI',
                'target'     => 'tich_diem_thanh_vien',
                'old_value'  => '-',
                'new_value'  => 'tich_diem_thanh_vien (Bản nháp)',
                'reason'     => 'Tạo mới cờ chương trình tích điểm hội viên',
                'created_at' => '2026-10-15 14:30:00',
                'version'    => 'v1.0.0',
            ],
            [
                'id'         => 'log_008',
                'actor'      => 'admin',
                'role'       => 'admin',
                'action'     => 'KHÔI_PHỤC_PHIÊN_BẢN',
                'target'     => 'Cấu hình hệ thống',
                'old_value'  => 'Bản nháp không ổn định',
                'new_value'  => 'Khôi phục từ v1.0.0',
                'reason'     => 'Phát hiện lỗi logic, khôi phục về trạng thái ổn định',
                'created_at' => '2026-06-19 09:15:00',
                'version'    => 'v1.1.0',
            ],
        ],
    ];
}

/**
 * Khởi tạo dữ liệu mẫu trong SESSION
 */
function init_session_data(bool $forceReload = false): void
{
    if (session_status() === PHP_SESSION_NONE) @session_start();

    static $loadedThisRequest = false;

    if (PHP_SAPI !== 'cli' && (!$loadedThisRequest || $forceReload)) {
        $loadedThisRequest = true;
        try {
            require_once __DIR__ . '/../services/DatabaseService.php';
            $dbData = DatabaseService::loadDataFromDb();
            if ($dbData !== null && !empty($dbData['flags'])) {
                $_SESSION['ff_data'] = $dbData;
            }
        } catch (Throwable $e) {}
    }

    if (!isset($_SESSION['ff_data']) || !is_array($_SESSION['ff_data']) || count($_SESSION['ff_data']['flags'] ?? []) < 11) {
        try {
            require_once __DIR__ . '/../services/DatabaseService.php';
            $dbData = DatabaseService::loadDataFromDb();
        } catch (Throwable $e) {
            $dbData = null;
        }
        if ($dbData !== null && count($dbData['flags'] ?? []) >= 11) {
            $_SESSION['ff_data'] = $dbData;
        } else {
            $_SESSION['ff_data'] = get_initial_fake_data();
        }
    } elseif (!isset($_SESSION['ff_data']['flags']['banner_quang_cao'])) {
        $initData = get_initial_fake_data();
        if (isset($initData['flags']['banner_quang_cao'])) {
            $_SESSION['ff_data']['flags']['banner_quang_cao'] = $initData['flags']['banner_quang_cao'];
        }
    }

    if (!isset($_SESSION['current_env'])) {
        $_SESSION['current_env'] = 'testing';
    }

    // Đồng bộ thông tin user đang đăng nhập với bảng members mới nhất từ CSDL
    if (isset($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['ff_data']['members'])) {
        $curId   = $_SESSION['user']['id'] ?? '';
        $curUser = $_SESSION['user']['username'] ?? '';
        $matchedMember = null;

        if ($curUser !== '' && isset($_SESSION['ff_data']['members'][$curUser])) {
            $matchedMember = $_SESSION['ff_data']['members'][$curUser];
        } else {
            foreach ($_SESSION['ff_data']['members'] as $m) {
                if (($m['id'] ?? '') === $curId) {
                    $matchedMember = $m;
                    break;
                }
            }
        }

        if ($matchedMember !== null) {
            $_SESSION['user'] = [
                'id'         => $matchedMember['id'],
                'username'   => $matchedMember['username'],
                'name'       => $matchedMember['name'],
                'email'      => $matchedMember['email'],
                'role'       => $matchedMember['role'],
                'role_title' => $matchedMember['role_title'],
                'avatar'     => $matchedMember['avatar'],
            ];
        }
    }
}

/**
 * Đặt lại dữ liệu session về trạng thái ban đầu
 */
function reset_session_data(): void
{
    if (session_status() === PHP_SESSION_NONE) @session_start();
    if (PHP_SAPI !== 'cli') {
        try {
            require_once __DIR__ . '/../services/DatabaseService.php';
            DatabaseService::importSqlSchema();
            $dbData = DatabaseService::loadDataFromDb();
            if ($dbData !== null) {
                $_SESSION['ff_data'] = $dbData;
                $_SESSION['current_env'] = 'testing';
                return;
            }
        } catch (Throwable $e) {}
    }
    $_SESSION['ff_data']     = get_initial_fake_data();
    $_SESSION['current_env'] = 'testing';
}
