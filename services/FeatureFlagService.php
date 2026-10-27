<?php
/**
 * Hệ thống Quản lý Feature Flags
 * services/FeatureFlagService.php – Lõi xử lý quyết định bật/tắt
 * Mã khách hàng dạng KH1 ... KH1000
 * Áp dụng 10 quy tắc nghiêm ngặt + băm ổn định (crc32 bucket 0-99)
 */
declare(strict_types=1);

require_once __DIR__ . '/../data/fake-data.php';

class FeatureFlagService
{
    public const DEFAULT_FALLBACK = false;

    /**
     * Đánh giá trạng thái cờ tính năng cho một khách hàng cụ thể.
     */
    public static function evaluate(
        string $flag_key,
        string $user_id,
        string $env       = 'testing',
        string $project_id = 'prj_ecommerce'
    ): array {
        init_session_data();
        $flags = $_SESSION['ff_data']['flags'] ?? [];

        // Quy tắc 9: tham số thiếu / cờ không tồn tại
        if (empty($flag_key) || !isset($flags[$flag_key])) {
            return self::result(false, $flag_key, $env, null, 'Không tìm thấy cờ tính năng; trả về giá trị mặc định (false)', false);
        }

        $env_cfg = $flags[$flag_key]['environments'][$env] ?? null;
        if (!$env_cfg) {
            return self::result(false, $flag_key, $env, null, "Cờ chưa cấu hình trên môi trường [$env]; trả mặc định (false)", false);
        }

        // Quy tắc 2: tắt toàn cục
        if (empty($env_cfg['enabled'])) {
            return self::result(false, $flag_key, $env, null, 'Tắt toàn cục trên môi trường này');
        }

        // Quy tắc 3: User Override (ưu tiên cao nhất)
        $overrides = $env_cfg['user_overrides'] ?? [];
        if (array_key_exists($user_id, $overrides)) {
            $state = (bool)$overrides[$user_id];
            return self::result($state, $flag_key, $env, null,
                'Áp dụng quy tắc riêng cho khách hàng (Override: ' . ($state ? 'Bật' : 'Tắt') . ')');
        }

        // Quy tắc 4: Kiểm tra nhóm khách hàng
        $groups = $env_cfg['target_groups'] ?? [];
        if (!empty($groups)) {
            $ugroup = self::getUserGroup($user_id);
            if (!in_array($ugroup, $groups, true)) {
                return self::result(false, $flag_key, $env, null,
                    "Khách hàng không thuộc nhóm mục tiêu (Nhóm: $ugroup)");
            }
        }

        // Quy tắc 5-7: Phân bổ bucket theo tỷ lệ rollout
        $pct = (int)($env_cfg['rollout_percentage'] ?? 0);

        if ($pct <= 0) {
            return self::result(false, $flag_key, $env, 0, 'Tỷ lệ triển khai = 0%');
        }
        if ($pct >= 100) {
            return self::result(true, $flag_key, $env, 100, 'Tỷ lệ triển khai = 100%');
        }

        $bucket = self::calculateBucket($user_id, $flag_key);
        $in     = $bucket < $pct;
        return self::result($in, $flag_key, $env, $bucket,
            $in ? "Trong phạm vi thử nghiệm (Bucket $bucket < $pct%)"
                : "Ngoài phạm vi thử nghiệm (Bucket $bucket ≥ $pct%)");
    }

    /** Tính bucket 0–99 ổn định theo user_id + flag_key */
    public static function calculateBucket(string $user_id, string $flag_key): int
    {
        return abs(crc32($user_id . ':' . $flag_key)) % 100;
    }

    /** Xác định nhóm khách hàng từ mã KH */
    private static function getUserGroup(string $user_id): string
    {
        if (preg_match('/KH(\d+)/i', $user_id, $m)) {
            $n = (int)$m[1];
            if ($n <= 50)  return 'nhom_noibu';
            if ($n <= 250) return 'nhom_beta';
            return 'nhom_chung';
        }
        return 'nhom_chung';
    }

    /** Đóng gói kết quả trả về API */
    private static function result(
        bool    $enabled,
        string  $flag_key,
        string  $env,
        ?int    $bucket,
        string  $reason,
        bool    $success = true
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
            ],
        ];
    }
}
