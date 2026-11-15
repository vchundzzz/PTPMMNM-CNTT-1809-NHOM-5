<?php
/**
 * Hệ thống Quản lý Feature Flags
 * services/FeatureFlagService.php – Lõi xử lý quyết định bật/tắt
 * Đồng bộ trực tiếp với tài khoản khách hàng thực tế đã đăng ký (customers & target_groups)
 * Áp dụng 10 quy tắc nghiêm ngặt + băm ổn định (crc32 bucket 0-99)
 */
declare(strict_types=1);

require_once __DIR__ . '/../data/fake-data.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/DatabaseService.php';

class FeatureFlagService
{
    public const DEFAULT_FALLBACK = false;

    /**
     * Đánh giá trạng thái cờ tính năng cho một khách hàng cụ thể (theo mã KH hoặc username).
     */
    public static function evaluate(
        string $flag_key,
        string $user_id,
        string $env       = '',
        string $project_id = 'prj_ecommerce'
    ): array {
        init_session_data();
        $env = $env !== '' ? $env : (DatabaseService::getActiveEnv() ?? '');
        $flags = $_SESSION['ff_data']['flags'] ?? [];
        $groups_map = $_SESSION['ff_data']['groups'] ?? [];

        $customer = self::resolveCustomer($user_id);
        $canonical_id = $customer['id'] ?? $user_id;
        $username = $customer['username'] ?? '';
        $ugroup = $customer['group_id'] ?? 'nhom_chung';
        $ugroup_name = $customer['group_name'] ?? ($groups_map[$ugroup]['name'] ?? $ugroup);
        $bucket = !empty($flag_key) ? self::calculateBucket($canonical_id, $flag_key) : null;

        // Quy tắc 9: tham số thiếu / cờ không tồn tại
        if (empty($flag_key) || !isset($flags[$flag_key])) {
            return self::result(
                false,
                $flag_key,
                $env,
                null,
                'Không tìm thấy cờ tính năng; trả về giá trị mặc định (false)',
                false,
                $canonical_id,
                $username,
                $ugroup,
                $ugroup_name
            );
        }

        if (($flags[$flag_key]['project_id'] ?? '') !== $project_id) {
            return self::result(
                false,
                $flag_key,
                $env,
                $bucket,
                'Cờ không thuộc dự án được yêu cầu',
                false,
                $canonical_id,
                $username,
                $ugroup,
                $ugroup_name
            );
        }

        $env_cfg = $flags[$flag_key]['environments'][$env] ?? null;
        if (!$env_cfg) {
            return self::result(
                false,
                $flag_key,
                $env,
                $bucket,
                "Cờ chưa cấu hình trên môi trường [$env]; trả mặc định (false)",
                false,
                $canonical_id,
                $username,
                $ugroup,
                $ugroup_name
            );
        }

        // Quy tắc 2: tắt toàn cục
        if (empty($env_cfg['enabled'])) {
            return self::result(
                false,
                $flag_key,
                $env,
                $bucket,
                'Tắt toàn cục trên môi trường này',
                true,
                $canonical_id,
                $username,
                $ugroup,
                $ugroup_name
            );
        }

        // Quy tắc 3: User Override (ưu tiên cao nhất — khớp cả Mã KH lẫn Tên đăng nhập)
        $overrides = $env_cfg['user_overrides'] ?? [];
        $matched_override = null;
        if (array_key_exists($canonical_id, $overrides)) {
            $matched_override = (bool)$overrides[$canonical_id];
        } elseif (array_key_exists($user_id, $overrides)) {
            $matched_override = (bool)$overrides[$user_id];
        } else {
            foreach ($overrides as $ovKey => $ovVal) {
                $ovKeyStr = (string)$ovKey;
                if (
                    strcasecmp($ovKeyStr, $canonical_id) === 0
                    || ($username !== '' && strcasecmp($ovKeyStr, $username) === 0)
                    || strcasecmp($ovKeyStr, $user_id) === 0
                ) {
                    $matched_override = (bool)$ovVal;
                    break;
                }
            }
        }

        if ($matched_override !== null) {
            return self::result(
                $matched_override,
                $flag_key,
                $env,
                $bucket,
                'Áp dụng quy tắc riêng cho khách hàng (Override: ' . ($matched_override ? 'Bật' : 'Tắt') . ')',
                true,
                $canonical_id,
                $username,
                $ugroup,
                $ugroup_name
            );
        }

        // Quy tắc 4: Kiểm tra nhóm khách hàng
        $groups = $env_cfg['target_groups'] ?? [];
        if (!empty($groups)) {
            if (!in_array($ugroup, $groups, true)) {
                return self::result(
                    false,
                    $flag_key,
                    $env,
                    $bucket,
                    "Khách hàng thuộc nhóm [$ugroup_name] không nằm trong nhóm mục tiêu áp dụng",
                    true,
                    $canonical_id,
                    $username,
                    $ugroup,
                    $ugroup_name
                );
            }
        }

        // Quy tắc 5-7: Phân bổ bucket theo tỷ lệ rollout
        $pct = (int)($env_cfg['rollout_percentage'] ?? 0);

        if ($pct <= 0) {
            return self::result(false, $flag_key, $env, $bucket, "Tỷ lệ triển khai = 0% (Bucket của KH = $bucket)", true, $canonical_id, $username, $ugroup, $ugroup_name);
        }
        if ($pct >= 100) {
            return self::result(true, $flag_key, $env, $bucket, "Tỷ lệ triển khai = 100% (Bucket của KH = $bucket)", true, $canonical_id, $username, $ugroup, $ugroup_name);
        }

        $in = $bucket < $pct;
        return self::result(
            $in,
            $flag_key,
            $env,
            $bucket,
            $in ? "Trong phạm vi thử nghiệm (Bucket $bucket < $pct%)"
                : "Ngoài phạm vi thử nghiệm (Bucket $bucket ≥ $pct%)",
            true,
            $canonical_id,
            $username,
            $ugroup,
            $ugroup_name
        );
    }

    /** Tính bucket 0–99 ổn định theo user_id + flag_key */
    public static function calculateBucket(string $user_id, string $flag_key): int
    {
        return abs(crc32($user_id . ':' . $flag_key)) % 100;
    }

    /**
     * Tra cứu thông tin tài khoản khách hàng thực tế từ CSDL MySQL (hoặc danh sách hiện tại)
     */
    public static function resolveCustomer(string $user_id): ?array
    {
        $user_id = trim($user_id);
        if ($user_id === '') {
            return null;
        }

        try {
            $pdo = Database::getConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("
                    SELECT c.id, c.username, c.name, c.group_id, COALESCE(g.name, c.group_id) AS group_name
                    FROM customers c
                    LEFT JOIN target_groups g ON g.id = c.group_id
                    WHERE UPPER(c.id) = UPPER(?) OR LOWER(c.username) = LOWER(?)
                    LIMIT 1
                ");
                $stmt->execute([$user_id, $user_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row) && !empty($row['id'])) {
                    return [
                        'id'         => (string)$row['id'],
                        'username'   => (string)($row['username'] ?? $row['id']),
                        'name'       => (string)($row['name'] ?? $row['username'] ?? $row['id']),
                        'group_id'   => (string)($row['group_id'] ?? 'nhom_chung'),
                        'group_name' => (string)($row['group_name'] ?? 'Khách Thường'),
                    ];
                }
            }
        } catch (Throwable $e) {}

        foreach (load_customers() as $customer) {
            if (
                strcasecmp((string)($customer['id'] ?? ''), $user_id) === 0
                || strcasecmp((string)($customer['username'] ?? ''), $user_id) === 0
            ) {
                return [
                    'id'         => (string)$customer['id'],
                    'username'   => (string)($customer['username'] ?? $customer['id']),
                    'name'       => (string)($customer['name'] ?? $customer['username'] ?? $customer['id']),
                    'group_id'   => (string)($customer['group_id'] ?? 'nhom_chung'),
                    'group_name' => (string)($customer['group_name'] ?? 'Khách Thường'),
                ];
            }
        }

        return null;
    }

    /** Đóng gói kết quả trả về API */
    private static function result(
        bool    $enabled,
        string  $flag_key,
        string  $env,
        ?int    $bucket,
        string  $reason,
        bool    $success = true,
        string  $customer_id = '',
        string  $username = '',
        string  $group_id = 'nhom_chung',
        string  $group_name = 'Khách Thường'
    ): array {
        return [
            'success' => $success,
            'data'    => [
                'flag_key'    => $flag_key,
                'enabled'     => $enabled,
                'environment' => $env,
                'version'     => 'v3',
                'bucket'      => $bucket,
                'reason'      => $reason,
                'customer_id' => $customer_id,
                'username'    => $username,
                'group_id'    => $group_id,
                'group_name'  => $group_name,
            ],
        ];
    }
}
