<?php
/**
 * Hệ thống Quản lý Feature Flags
 * services/VersionService.php – Quản lý snapshot phiên bản (Giai đoạn 3)
 */
declare(strict_types=1);

require_once __DIR__ . '/../data/fake-data.php';
require_once __DIR__ . '/../includes/helpers.php';

class VersionService
{
    /**
     * Chụp snapshot cấu hình hiện tại của tất cả flags trên một môi trường
     */
    public static function createSnapshot(string $env, string $reason = ''): array
    {
        init_session_data();
        $flags    = $_SESSION['ff_data']['flags'] ?? [];
        $versions = $_SESSION['ff_data']['versions'] ?? [];
        $count    = count($versions) + 1;
        $minor    = $count;                    // v1.0, v1.1, …
        $ver_num  = "v1.$minor.0";
        $tag      = 'v' . ($count + 3);       // v4, v5, … (tiếp nối v3 hiện có)

        $snapshot = [];
        foreach ($flags as $key => $flag) {
            $ec = $flag['environments'][$env] ?? [];
            $snapshot[$key] = [
                'enabled'            => $ec['enabled'] ?? false,
                'rollout_percentage' => $ec['rollout_percentage'] ?? 0,
            ];
        }

        $version = [
            'id'             => sprintf('ver_%03d', count($versions) + 1),
            'version_number' => $ver_num,
            'tag'            => $tag,
            'status'         => 'Lưu trữ',
            'environment'    => $env,
            'created_by'     => get_authenticated_user()['username'] ?? 'system',
            'created_at'     => date('Y-m-d H:i:s'),
            'reason'         => $reason,
            'flags_snapshot' => $snapshot,
        ];

        $_SESSION['ff_data']['versions'][$tag] = $version;

        append_audit_log(
            'LƯU_PHIÊN_BẢN',
            $ver_num,
            '—',
            "$ver_num (Lưu trữ – $env)",
            $reason
        );

        return $version;
    }

    /**
     * Khôi phục cấu hình flags từ một phiên bản (theo tag)
     */
    public static function rollback(string $tag, string $env): bool
    {
        init_session_data();
        $versions = $_SESSION['ff_data']['versions'] ?? [];
        if (!isset($versions[$tag])) return false;

        $snapshot = $versions[$tag]['flags_snapshot'] ?? [];
        $actor    = get_authenticated_user()['username'] ?? 'admin';

        foreach ($snapshot as $key => $cfg) {
            if (!isset($_SESSION['ff_data']['flags'][$key])) continue;
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['enabled']            = $cfg['enabled'];
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['rollout_percentage'] = $cfg['rollout_percentage'];
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['status']             = $cfg['enabled'] ? 'Đã phát hành' : 'Bản nháp';
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['updated_at']         = date('Y-m-d H:i:s');
            $_SESSION['ff_data']['flags'][$key]['environments'][$env]['updated_by']         = $actor;
        }

        append_audit_log(
            'KHÔI_PHỤC_PHIÊN_BẢN',
            "Cấu hình $env",
            'Cấu hình hiện tại',
            "Khôi phục từ $tag",
            "Rollback về $tag trên $env"
        );

        return true;
    }

    /**
     * So sánh snapshot của hai phiên bản
     * Trả về danh sách thay đổi dạng: [ [key, old_on, new_on, old_pct, new_pct, changed], … ]
     */
    public static function compare(string $tag_a, string $tag_b): array
    {
        init_session_data();
        $versions = $_SESSION['ff_data']['versions'] ?? [];
        $snap_a = $versions[$tag_a]['flags_snapshot'] ?? [];
        $snap_b = $versions[$tag_b]['flags_snapshot'] ?? [];
        $flags  = $_SESSION['ff_data']['flags'] ?? [];

        $all_keys = array_unique(array_merge(array_keys($snap_a), array_keys($snap_b)));
        $diff = [];

        foreach ($all_keys as $key) {
            $a_on  = $snap_a[$key]['enabled']            ?? false;
            $b_on  = $snap_b[$key]['enabled']            ?? false;
            $a_pct = $snap_a[$key]['rollout_percentage'] ?? 0;
            $b_pct = $snap_b[$key]['rollout_percentage'] ?? 0;
            $diff[] = [
                'key'     => $key,
                'name'    => $flags[$key]['name'] ?? $key,
                'old_on'  => $a_on,
                'new_on'  => $b_on,
                'old_pct' => $a_pct,
                'new_pct' => $b_pct,
                'changed' => ($a_on !== $b_on || $a_pct !== $b_pct),
            ];
        }
        return $diff;
    }
}
