-- ============================================================
-- Hệ thống Quản lý Feature Flags
-- database.sql – Toàn bộ Cấu trúc & Dữ liệu CSDL MySQL
-- Tương thích: MySQL 5.7+ / MySQL 8.0+ / MariaDB (XAMPP)
-- Bộ mã: UTF-8 Unicode (utf8mb4_unicode_ci)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `feature_flags_db`
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `feature_flags_db`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Projects
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
    `id` VARCHAR(50) NOT NULL PRIMARY KEY,
    `key` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `status` VARCHAR(50) DEFAULT 'Hoạt động',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Environments
DROP TABLE IF EXISTS `environments`;
CREATE TABLE `environments` (
    `id` VARCHAR(50) NOT NULL PRIMARY KEY,
    `key` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `badge_color` VARCHAR(50) DEFAULT 'primary',
    `is_production` TINYINT(1) DEFAULT 0,
    `sort_order` INT NOT NULL DEFAULT 0,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Target Groups
DROP TABLE IF EXISTS `target_groups`;
CREATE TABLE `target_groups` (
    `id` VARCHAR(50) NOT NULL PRIMARY KEY,
    `key` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `user_count` INT DEFAULT 0,
    `range_label` VARCHAR(100) NULL DEFAULT 'Chưa có khách hàng',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Customers (Tài khoản khách hàng thực tế đăng ký mới được cấp mã KH)
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
    `id` VARCHAR(50) NOT NULL PRIMARY KEY,
    `username` VARCHAR(50) NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NULL DEFAULT '',
    `password_hash` VARCHAR(255) NULL,
    `group_id` VARCHAR(50) NOT NULL DEFAULT 'nhom_chung',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cust_group` (`group_id`),
    CONSTRAINT `fk_cust_group` FOREIGN KEY (`group_id`) REFERENCES `target_groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Members (Tài khoản quản trị)
DROP TABLE IF EXISTS `members`;
CREATE TABLE `members` (
    `id` VARCHAR(50) NOT NULL PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `role` VARCHAR(50) NOT NULL DEFAULT 'viewer',
    `role_title` VARCHAR(100) NOT NULL DEFAULT 'Viewer',
    `avatar` VARCHAR(50) DEFAULT 'U',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `last_login` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Flags
DROP TABLE IF EXISTS `flags`;
CREATE TABLE `flags` (
    `id` VARCHAR(50) NOT NULL PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `project_id` VARCHAR(50) NOT NULL DEFAULT 'prj_ecommerce',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_flag_key` (`key`),
    CONSTRAINT `fk_flag_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Flag Environments
DROP TABLE IF EXISTS `flag_environments`;
CREATE TABLE `flag_environments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `flag_key` VARCHAR(100) NOT NULL,
    `environment` VARCHAR(50) NOT NULL,
    `enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `rollout_percentage` INT NOT NULL DEFAULT 0,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Bản nháp',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` VARCHAR(50) DEFAULT 'admin',
    UNIQUE KEY `uk_flag_env` (`flag_key`, `environment`),
    CONSTRAINT `fk_fe_flag` FOREIGN KEY (`flag_key`) REFERENCES `flags`(`key`) ON DELETE CASCADE,
    CONSTRAINT `fk_fe_environment` FOREIGN KEY (`environment`) REFERENCES `environments`(`key`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Flag Target Groups
DROP TABLE IF EXISTS `flag_target_groups`;
CREATE TABLE `flag_target_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `flag_key` VARCHAR(100) NOT NULL,
    `environment` VARCHAR(50) NOT NULL,
    `group_id` VARCHAR(50) NOT NULL,
    UNIQUE KEY `uk_flag_env_group` (`flag_key`, `environment`, `group_id`),
    CONSTRAINT `fk_ftg_flag` FOREIGN KEY (`flag_key`) REFERENCES `flags`(`key`) ON DELETE CASCADE,
    CONSTRAINT `fk_ftg_group` FOREIGN KEY (`group_id`) REFERENCES `target_groups`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ftg_environment` FOREIGN KEY (`environment`) REFERENCES `environments`(`key`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Flag User Overrides
DROP TABLE IF EXISTS `flag_user_overrides`;
CREATE TABLE `flag_user_overrides` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `flag_key` VARCHAR(100) NOT NULL,
    `environment` VARCHAR(50) NOT NULL,
    `user_id` VARCHAR(50) NOT NULL,
    `state` TINYINT(1) NOT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_flag_env_user` (`flag_key`, `environment`, `user_id`),
    CONSTRAINT `fk_fuo_flag` FOREIGN KEY (`flag_key`) REFERENCES `flags`(`key`) ON DELETE CASCADE,
    CONSTRAINT `fk_fuo_environment` FOREIGN KEY (`environment`) REFERENCES `environments`(`key`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Audit Logs
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
    `id` VARCHAR(50) NOT NULL PRIMARY KEY,
    `actor` VARCHAR(50) NOT NULL,
    `role` VARCHAR(50) NOT NULL DEFAULT 'admin',
    `action` VARCHAR(100) NOT NULL,
    `target` VARCHAR(255) NOT NULL,
    `old_value` TEXT NULL,
    `new_value` TEXT NULL,
    `reason` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `version` VARCHAR(50) DEFAULT 'v1',
    INDEX `idx_log_created` (`created_at`),
    INDEX `idx_log_actor` (`actor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Versions
DROP TABLE IF EXISTS `versions`;
CREATE TABLE `versions` (
    `id` VARCHAR(50) NOT NULL PRIMARY KEY,
    `version_number` VARCHAR(50) NOT NULL,
    `tag` VARCHAR(50) NOT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Lưu trữ',
    `environment` VARCHAR(50) NOT NULL DEFAULT 'staging',
    `created_by` VARCHAR(50) NOT NULL DEFAULT 'admin',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `reason` TEXT NULL,
    `flags_snapshot` LONGTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dữ liệu: projects
INSERT INTO `projects` VALUES ('prj_ecommerce', 'ecommerce_web', 'Cửa hàng Thương mại Điện tử', 'Hệ thống bán lẻ công nghệ Apple phục vụ hàng triệu khách hàng trực tuyến.', 'Hoạt động', '2026-01-10 08:30:00', '2026-10-20 14:15:00');

-- Dữ liệu: environments
INSERT INTO `environments` (`id`, `key`, `name`, `description`, `badge_color`, `is_production`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
('env_testing', 'testing', 'Kiểm thử (Testing)', 'Môi trường kiểm thử nội bộ dành cho đội QA và lập trình viên.', 'warning', 0, 10, 'active', '2026-01-10 09:00:00', '2026-01-10 09:00:00'),
('env_staging', 'staging', 'Vận hành (Staging)', 'Môi trường vận hành dùng để kiểm tra cấu hình trước khi áp dụng thực tế.', 'primary', 0, 20, 'active', '2026-01-10 09:00:00', '2026-01-10 09:00:00');

-- Dữ liệu: target_groups
INSERT INTO `target_groups` VALUES ('nhom_noibu', 'noi_bo', 'Nội bộ', 'Đội ngũ lập trình viên, QA và kiểm thử viên nội bộ công ty.', 0, 'Chưa có khách hàng', '2026-01-12 10:00:00');
INSERT INTO `target_groups` VALUES ('nhom_beta', 'beta', 'Khách Beta', 'Khách hàng đăng ký trải nghiệm sớm tính năng mới.', 0, 'Chưa có khách hàng', '2026-01-12 10:00:00');
INSERT INTO `target_groups` VALUES ('nhom_chung', 'chung', 'Khách Thường', 'Toàn bộ khách hàng phổ thông mua sắm trên nền tảng.', 0, 'Chưa có khách hàng', '2026-01-12 10:00:00');

-- Dữ liệu: members (Tài khoản quản trị)
INSERT INTO `members` VALUES ('usr_admin', 'admin', '$2y$10$/HQDwLCbLgJQd3UfvU7WVeUOVktWpEtZHnWctSUAHGQwXEaYzqDpO', 'Admin', 'admin@featuremanager.local', 'admin', 'Admin', 'A', '2026-01-01 00:00:00', '2026-10-27 15:00:00');
INSERT INTO `members` VALUES ('usr_editor', 'editor', '$2y$10$DikOoVlFxaMukVToiXfrJe/s26cgGfizThbIhqCoUTXyeD81RZqAy', 'Biên tập', 'editor@featuremanager.local', 'editor', 'Biên tập', 'BT', '2026-01-05 08:30:00', '2026-10-26 11:20:00');
INSERT INTO `members` VALUES ('usr_viewer', 'viewer', '$2y$10$OoC/3IB6GWIRCeqCLPL6LedWYHfAlRtYG.vtdzLODD65eY.stq0kq', 'Viewer', 'viewer@featuremanager.local', 'viewer', 'Viewer', 'V', '2026-01-10 14:00:00', '2026-10-25 09:45:00');

-- Dữ liệu: flags & flag_environments & target_groups
INSERT INTO `flags` (`id`, `key`, `name`, `description`, `project_id`, `created_at`, `updated_at`) VALUES ('flg_001', 'thanh_toan_nhanh', 'Thanh toán một bước', 'Rút gọn quy trình thanh toán còn 1 trang, tăng 25% tỷ lệ chuyển đổi đơn hàng.', 'prj_ecommerce', NOW(), NOW());
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('thanh_toan_nhanh', 'testing', 1, 100, 'Đã phát hành', '2026-10-25 10:30:00', 'admin');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('thanh_toan_nhanh', 'testing', 'nhom_noibu');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('thanh_toan_nhanh', 'testing', 'nhom_beta');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('thanh_toan_nhanh', 'testing', 'nhom_chung');
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('thanh_toan_nhanh', 'staging', 1, 50, 'Đã phát hành', '2026-10-26 14:15:00', 'editor');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('thanh_toan_nhanh', 'staging', 'nhom_noibu');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('thanh_toan_nhanh', 'staging', 'nhom_beta');

INSERT INTO `flags` (`id`, `key`, `name`, `description`, `project_id`, `created_at`, `updated_at`) VALUES ('flg_002', 'giao_dien_toi', 'Giao diện ban đêm', 'Kích hoạt chủ đề nền tối giúp bảo vệ mắt và tiết kiệm pin màn hình OLED.', 'prj_ecommerce', NOW(), NOW());
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('giao_dien_toi', 'testing', 1, 100, 'Đã phát hành', '2026-10-24 16:45:00', 'admin');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('giao_dien_toi', 'testing', 'nhom_noibu');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('giao_dien_toi', 'testing', 'nhom_beta');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('giao_dien_toi', 'testing', 'nhom_chung');
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('giao_dien_toi', 'staging', 1, 50, 'Đã phát hành', '2026-10-25 09:20:00', 'editor');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('giao_dien_toi', 'staging', 'nhom_noibu');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('giao_dien_toi', 'staging', 'nhom_beta');

INSERT INTO `flags` (`id`, `key`, `name`, `description`, `project_id`, `created_at`, `updated_at`) VALUES ('flg_003', 'danh_gia_san_pham', 'Đánh giá sản phẩm có hình ảnh', 'Cho phép khách hàng đính kèm ảnh thực tế khi đánh giá sao sản phẩm.', 'prj_ecommerce', NOW(), NOW());
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('danh_gia_san_pham', 'testing', 1, 100, 'Đã phát hành', '2026-10-22 11:10:00', 'editor');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('danh_gia_san_pham', 'testing', 'nhom_noibu');
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('danh_gia_san_pham', 'staging', 1, 10, 'Đã phát hành', '2026-10-23 15:30:00', 'admin');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('danh_gia_san_pham', 'staging', 'nhom_noibu');

INSERT INTO `flags` (`id`, `key`, `name`, `description`, `project_id`, `created_at`, `updated_at`) VALUES ('flg_004', 'danh_sach_yeu_thich', 'Danh sách sản phẩm yêu thích', 'Lưu sản phẩm yêu thích và nhận thông báo khi giá giảm.', 'prj_ecommerce', NOW(), NOW());
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('danh_sach_yeu_thich', 'testing', 1, 100, 'Đã phát hành', '2026-10-21 08:45:00', 'admin');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('danh_sach_yeu_thich', 'testing', 'nhom_noibu');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('danh_sach_yeu_thich', 'testing', 'nhom_beta');
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('danh_sach_yeu_thich', 'staging', 0, 0, 'Bản nháp', '2026-10-22 13:00:00', 'editor');

INSERT INTO `flags` (`id`, `key`, `name`, `description`, `project_id`, `created_at`, `updated_at`) VALUES ('flg_005', 'ho_tro_truc_tuyen', 'Hỗ trợ trực tuyến (Live Chat)', 'Widget chat hỗ trợ khách hàng kết nối trực tiếp tư vấn viên 24/7.', 'prj_ecommerce', NOW(), NOW());
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('ho_tro_truc_tuyen', 'testing', 1, 50, 'Đã phát hành', '2026-10-20 14:00:00', 'editor');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('ho_tro_truc_tuyen', 'testing', 'nhom_noibu');
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('ho_tro_truc_tuyen', 'staging', 0, 0, 'Bản nháp', '2026-10-20 14:00:00', 'admin');

INSERT INTO `flags` (`id`, `key`, `name`, `description`, `project_id`, `created_at`, `updated_at`) VALUES ('flg_006', 'ma_giam_gia', 'Tự động gợi ý mã giảm giá', 'Tự động kiểm tra và áp dụng voucher giảm giá tốt nhất cho giỏ hàng.', 'prj_ecommerce', NOW(), NOW());
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('ma_giam_gia', 'testing', 1, 100, 'Đã phát hành', '2026-10-19 16:20:00', 'admin');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('ma_giam_gia', 'testing', 'nhom_noibu');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('ma_giam_gia', 'testing', 'nhom_beta');
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('ma_giam_gia', 'staging', 1, 10, 'Đã phát hành', '2026-10-20 10:15:00', 'editor');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('ma_giam_gia', 'staging', 'nhom_beta');

INSERT INTO `flags` (`id`, `key`, `name`, `description`, `project_id`, `created_at`, `updated_at`) VALUES ('flg_007', 'goi_y_san_pham', 'Gợi ý sản phẩm bằng AI', 'Đề xuất phụ kiện dựa trên hành vi duyệt web và lịch sử mua sắm.', 'prj_ecommerce', NOW(), NOW());
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('goi_y_san_pham', 'testing', 1, 50, 'Đã phát hành', '2026-10-18 09:30:00', 'editor');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('goi_y_san_pham', 'testing', 'nhom_noibu');
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('goi_y_san_pham', 'staging', 0, 0, 'Bản nháp', '2026-10-18 09:30:00', 'admin');

INSERT INTO `flags` (`id`, `key`, `name`, `description`, `project_id`, `created_at`, `updated_at`) VALUES ('flg_008', 'tim_kiem_nang_cao', 'Tìm kiếm nâng cao đa tiêu chí', 'Lọc sản phẩm theo chip, RAM, màn hình và tầm giá chỉ trong vài giây.', 'prj_ecommerce', NOW(), NOW());
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('tim_kiem_nang_cao', 'testing', 1, 100, 'Đã phát hành', '2026-10-17 11:00:00', 'admin');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('tim_kiem_nang_cao', 'testing', 'nhom_noibu');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('tim_kiem_nang_cao', 'testing', 'nhom_beta');
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('tim_kiem_nang_cao', 'staging', 1, 50, 'Đã phát hành', '2026-10-17 15:45:00', 'editor');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('tim_kiem_nang_cao', 'staging', 'nhom_noibu');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('tim_kiem_nang_cao', 'staging', 'nhom_beta');

INSERT INTO `flags` (`id`, `key`, `name`, `description`, `project_id`, `created_at`, `updated_at`) VALUES ('flg_009', 'theo_doi_don_hang', 'Theo dõi đơn hàng thời gian thực', 'Bản đồ trực quan cập nhật tọa độ tài xế giao hàng chặng cuối trong ngày.', 'prj_ecommerce', NOW(), NOW());
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('theo_doi_don_hang', 'testing', 1, 10, 'Bản nháp', '2026-10-16 10:15:00', 'editor');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('theo_doi_don_hang', 'testing', 'nhom_noibu');
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('theo_doi_don_hang', 'staging', 0, 0, 'Bản nháp', '2026-10-16 10:15:00', 'admin');

INSERT INTO `flags` (`id`, `key`, `name`, `description`, `project_id`, `created_at`, `updated_at`) VALUES ('flg_010', 'tich_diem_thanh_vien', 'Chương trình tích điểm hội viên', 'Tích lũy 2% giá trị hóa đơn thành điểm thưởng đổi quà tặng độc quyền.', 'prj_ecommerce', NOW(), NOW());
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('tich_diem_thanh_vien', 'testing', 0, 0, 'Bản nháp', '2026-10-15 14:30:00', 'admin');
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('tich_diem_thanh_vien', 'staging', 0, 0, 'Bản nháp', '2026-10-15 14:30:00', 'admin');

INSERT INTO `flags` (`id`, `key`, `name`, `description`, `project_id`, `created_at`, `updated_at`) VALUES ('flg_011', 'banner_quang_cao', 'Banner quảng cáo trang chủ', 'Hiển thị thanh slider banner khuyến mãi các dòng sản phẩm Apple mới nhất.', 'prj_ecommerce', NOW(), NOW());
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('banner_quang_cao', 'testing', 1, 100, 'Đã phát hành', '2026-10-27 18:49:29', 'admin');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('banner_quang_cao', 'testing', 'nhom_noibu');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('banner_quang_cao', 'testing', 'nhom_beta');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('banner_quang_cao', 'testing', 'nhom_chung');
INSERT INTO `flag_environments` (`flag_key`, `environment`, `enabled`, `rollout_percentage`, `status`, `updated_at`, `updated_by`) VALUES ('banner_quang_cao', 'staging', 1, 100, 'Đã phát hành', '2026-10-27 18:49:29', 'admin');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('banner_quang_cao', 'staging', 'nhom_noibu');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('banner_quang_cao', 'staging', 'nhom_beta');
INSERT INTO `flag_target_groups` (`flag_key`, `environment`, `group_id`) VALUES ('banner_quang_cao', 'staging', 'nhom_chung');

-- Dữ liệu: audit_logs

-- Dữ liệu: versions
INSERT INTO `versions` VALUES ('ver_001', 'v1.0.0', 'v1', 'Lưu trữ', 'staging', 'admin', '2026-01-15 10:00:00', 'Khởi tạo cấu hình ban đầu cho 10 cờ tính năng mẫu.', '{\"thanh_toan_nhanh\":{\"enabled\":false,\"rollout_percentage\":0},\"giao_dien_toi\":{\"enabled\":false,\"rollout_percentage\":0},\"danh_gia_san_pham\":{\"enabled\":false,\"rollout_percentage\":0},\"danh_sach_yeu_thich\":{\"enabled\":false,\"rollout_percentage\":0},\"ho_tro_truc_tuyen\":{\"enabled\":false,\"rollout_percentage\":0},\"ma_giam_gia\":{\"enabled\":false,\"rollout_percentage\":0},\"goi_y_san_pham\":{\"enabled\":false,\"rollout_percentage\":0},\"tim_kiem_nang_cao\":{\"enabled\":false,\"rollout_percentage\":0},\"theo_doi_don_hang\":{\"enabled\":false,\"rollout_percentage\":0},\"tich_diem_thanh_vien\":{\"enabled\":false,\"rollout_percentage\":0},\"banner_quang_cao\":{\"enabled\":true,\"rollout_percentage\":100}}');
INSERT INTO `versions` VALUES ('ver_002', 'v1.1.0', 'v2', 'Lưu trữ', 'staging', 'editor', '2026-06-20 15:30:00', 'Mở rộng thử nghiệm Giao diện tối và Tìm kiếm nâng cao lên 50% Staging.', '{\"thanh_toan_nhanh\":{\"enabled\":false,\"rollout_percentage\":0},\"giao_dien_toi\":{\"enabled\":true,\"rollout_percentage\":50},\"danh_gia_san_pham\":{\"enabled\":false,\"rollout_percentage\":0},\"danh_sach_yeu_thich\":{\"enabled\":false,\"rollout_percentage\":0},\"ho_tro_truc_tuyen\":{\"enabled\":false,\"rollout_percentage\":0},\"ma_giam_gia\":{\"enabled\":false,\"rollout_percentage\":0},\"goi_y_san_pham\":{\"enabled\":false,\"rollout_percentage\":0},\"tim_kiem_nang_cao\":{\"enabled\":true,\"rollout_percentage\":50},\"theo_doi_don_hang\":{\"enabled\":false,\"rollout_percentage\":0},\"tich_diem_thanh_vien\":{\"enabled\":false,\"rollout_percentage\":0},\"banner_quang_cao\":{\"enabled\":true,\"rollout_percentage\":100}}');
INSERT INTO `versions` VALUES ('ver_003', 'v1.2.0', 'v3', 'Đang dùng', 'staging', 'admin', '2026-10-26 14:15:00', 'Phát hành Thanh toán nhanh 50% và kích hoạt Mã giảm giá 10% tại Staging.', '{\"thanh_toan_nhanh\":{\"enabled\":true,\"rollout_percentage\":50},\"giao_dien_toi\":{\"enabled\":true,\"rollout_percentage\":50},\"danh_gia_san_pham\":{\"enabled\":true,\"rollout_percentage\":10},\"danh_sach_yeu_thich\":{\"enabled\":false,\"rollout_percentage\":0},\"ho_tro_truc_tuyen\":{\"enabled\":false,\"rollout_percentage\":0},\"ma_giam_gia\":{\"enabled\":true,\"rollout_percentage\":10},\"goi_y_san_pham\":{\"enabled\":false,\"rollout_percentage\":0},\"tim_kiem_nang_cao\":{\"enabled\":true,\"rollout_percentage\":50},\"theo_doi_don_hang\":{\"enabled\":false,\"rollout_percentage\":0},\"tich_diem_thanh_vien\":{\"enabled\":false,\"rollout_percentage\":0},\"banner_quang_cao\":{\"enabled\":true,\"rollout_percentage\":100}}');

SET FOREIGN_KEY_CHECKS = 1;
