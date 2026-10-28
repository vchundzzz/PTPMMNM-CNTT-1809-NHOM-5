<?php
/**
 * Web Feature Flag Management System
 * services/PermissionService.php - Quản lý phân quyền kiểm tra phía Server (RBAC)
 */

declare(strict_types=1);

class PermissionService
{
    /**
     * Bảng ma trận quyền theo vai trò (Role-Based Access Control)
     */
    private const ROLE_PERMISSIONS = [
        'admin' => [
            'view_all' => true,
            'create_flag' => true,
            'create_version' => true,
            'delete_version' => true,
            'edit_flag' => true,
            'delete_flag' => true,
            'toggle_flag' => true,
            'publish_version' => true,
            'rollback_version' => true,
            'manage_projects' => true,
            'manage_environments' => true,
            'manage_members' => true,
            'view_member_passwords' => true,
            'manage_groups' => true,
            'import_config' => true,
            'export_config' => true,
        ],
        'editor' => [
            'view_all' => true,
            'create_flag' => true,
            'create_version' => true,
            'delete_version' => false,
            'edit_flag' => true,
            'delete_flag' => false,
            'toggle_flag' => true,
            'publish_version' => false, // Chỉ Admin mới được phát hành chính thức
            'rollback_version' => false, // Chỉ Admin mới được khôi phục phiên bản
            'manage_projects' => false,
            'manage_environments' => false,
            'manage_members' => false,
            'view_member_passwords' => false,
            'manage_groups' => true,
            'import_config' => false,
            'export_config' => true,
        ],
        'viewer' => [
            'view_all' => true,
            'create_flag' => false,
            'create_version' => false,
            'delete_version' => false,
            'edit_flag' => false,
            'delete_flag' => false,
            'toggle_flag' => false,
            'publish_version' => false,
            'rollback_version' => false,
            'manage_projects' => false,
            'manage_environments' => false,
            'manage_members' => false,
            'view_member_passwords' => false,
            'manage_groups' => false,
            'import_config' => false,
            'export_config' => true, // Cho phép Viewer tải file cấu hình về xem
        ],
    ];

    /**
     * Kiểm tra quyền của một vai trò đối với hành động cụ thể
     */
    public static function can(string $role, string $action): bool
    {
        $role = strtolower(trim($role));
        if (!isset(self::ROLE_PERMISSIONS[$role])) {
            return false;
        }

        return self::ROLE_PERMISSIONS[$role][$action] ?? false;
    }

    /**
     * Kiểm tra quyền của tài khoản hiện tại đang đăng nhập
     */
    public static function currentUserCan(string $action): bool
    {
        $user = get_authenticated_user();
        if (!$user || !isset($user['role'])) {
            return false;
        }

        return self::can($user['role'], $action);
    }

    /**
     * Bắt buộc có quyền, nếu không sẽ dừng xử lý hoặc hiển thị lỗi từ chối
     */
    public static function enforce(string $action): void
    {
        if (!self::currentUserCan($action)) {
            http_response_code(403);
            set_flash('error', 'Bạn không có quyền thực hiện thao tác này (Hành động bị từ chối do phân quyền vai trò).');
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'dashboard.php'));
            exit;
        }
    }

    /**
     * Lấy toàn bộ danh sách quyền mô tả trực quan cho giao diện trang Quản lý thành viên
     * @return array<string, array<string, mixed>>
     */
    public static function getPermissionsMatrix(): array
    {
        return [
            'view_all' => [
                'name' => 'Xem toàn bộ dữ liệu',
                'description' => 'Xem bảng tin, dự án, cờ tính năng, phiên bản và ứng dụng demo',
            ],
            'create_flag' => [
                'name' => 'Tạo cờ tính năng mới',
                'description' => 'Tạo mới bản nháp cờ tính năng trong môi trường',
            ],
            'create_version' => [
                'name' => 'Tạo snapshot phiên bản',
                'description' => 'Lưu snapshot cấu hình cờ hiện tại của môi trường đang chọn',
            ],
            'delete_version' => [
                'name' => 'Xóa phiên bản cấu hình',
                'description' => 'Xóa snapshot phiên bản khỏi lịch sử cấu hình',
            ],
            'edit_flag' => [
                'name' => 'Chỉnh sửa cờ tính năng',
                'description' => 'Sửa tên, mô tả, nhóm áp dụng và danh sách người dùng override',
            ],
            'toggle_flag' => [
                'name' => 'Bật / Tắt cờ & Chỉnh Rollout %',
                'description' => 'Bật hoặc tắt trạng thái và thay đổi tỷ lệ % rollout trực tiếp',
            ],
            'publish_version' => [
                'name' => 'Phát hành phiên bản (Publish)',
                'description' => 'Phát hành chính thức bản nháp cấu hình thành phiên bản hoạt động',
            ],
            'rollback_version' => [
                'name' => 'Khôi phục phiên bản (Rollback)',
                'description' => 'Khôi phục cấu hình từ phiên bản lịch sử trước đó',
            ],
            'manage_members' => [
                'name' => 'Quản lý thành viên & Phân quyền',
                'description' => 'Thêm, sửa thành viên và chỉ định vai trò quản trị, biên tập hoặc xem',
            ],
            'view_member_passwords' => [
                'name' => 'Xem mật khẩu thành viên',
                'description' => 'Hiển thị mật khẩu tài khoản trong danh sách và biểu mẫu chỉnh sửa',
            ],
            'import_config' => [
                'name' => 'Nhập file cấu hình',
                'description' => 'Tải lên và kiểm định file cấu hình thay thế',
            ],
            'export_config' => [
                'name' => 'Xuất file cấu hình',
                'description' => 'Tải xuống bản sao lưu cấu hình toàn bộ hệ thống',
            ],
        ];
    }
}
