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
        $sequence = (int)($_SESSION['ff_data']['version_sequence'] ?? 0);
        foreach ($versions as $existing) {
            if (preg_match('/^v(\d+)$/', (string)($existing['tag'] ?? ''), $matches)) {
                $sequence = max($sequence, (int)$matches[1]);
            }
            if (preg_match('/^v1\.(\d+)\.0$/', (string)($existing['version_number'] ?? ''), $matches)) {
                $sequence = max($sequence, (int)$matches[1]);
            }
            if (preg_match('/^ver_(\d+)$/', (string)($existing['id'] ?? ''), $matches)) {
                $sequence = max($sequence, (int)$matches[1]);
            }
        }
        $sequence++;
        $_SESSION['ff_data']['version_sequence'] = $sequence;
        $minor    = $sequence;
        $ver_num  = "v1.$minor.0";
        $tag      = 'v' . $sequence;

        $snapshot = [];
        foreach ($flags as $key => $flag) {
            $ec = $flag['environments'][$env] ?? [];
            $snapshot[$key] = [
                'enabled'            => $ec['enabled'] ?? false,
                'rollout_percentage' => $ec['rollout_percentage'] ?? 0,
            ];
        }

        $version = [
            'id'             => sprintf('ver_%03d', $sequence),
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

        // Đồng bộ snapshot vào CSDL MySQL
        try {
            require_once __DIR__ . '/DatabaseService.php';
            DatabaseService::saveVersion($version);
        } catch (Throwable $e) {}

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
     * Xóa một snapshot khỏi lịch sử mà không thay đổi cấu hình cờ đang chạy.
     */
    public static function deleteSnapshot(string $tag): bool
    {
        init_session_data();
        $versions = $_SESSION['ff_data']['versions'] ?? [];
        $version_key = null;
        $version = null;

        foreach ($versions as $key => $candidate) {
            if ((string)$key === $tag || (string)($candidate['tag'] ?? '') === $tag) {
                $version_key = $key;
                $version = $candidate;
                break;
            }
        }

        if ($version_key === null || $version === null) {
            return false;
        }

        $sequence = (int)($_SESSION['ff_data']['version_sequence'] ?? 0);
        foreach ([
            (string)($version['tag'] ?? ''),
            (string)($version['version_number'] ?? ''),
            (string)($version['id'] ?? ''),
        ] as $identifier) {
            if (preg_match('/^v(\d+)$/', $identifier, $matches)
                || preg_match('/^v1\.(\d+)\.0$/', $identifier, $matches)
                || preg_match('/^ver_(\d+)$/', $identifier, $matches)) {
                $sequence = max($sequence, (int)$matches[1]);
            }
        }
        $_SESSION['ff_data']['version_sequence'] = $sequence;
        unset($_SESSION['ff_data']['versions'][$version_key]);
        try {
            require_once __DIR__ . '/DatabaseService.php';
            DatabaseService::deleteVersion((string)($version['tag'] ?? $tag));
        } catch (Throwable $e) {}
        append_audit_log(
            'XÓA_PHIÊN_BẢN',
            (string)($version['version_number'] ?? $tag),
            'Còn trong lịch sử',
            'Đã xóa',
            sprintf(
                'Xóa snapshot %s (%s) trên môi trường %s',
                $tag,
                $version['version_number'] ?? $tag,
                $version['environment'] ?? 'không xác định'
            )
        );

        return true;
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

        try {
            require_once __DIR__ . '/DatabaseService.php';
            DatabaseService::rollbackVersion($snapshot, $env, $actor);
        } catch (Throwable $e) {}

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
