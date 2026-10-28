<?php
/**
 * Hệ thống Quản lý Feature Flags
 * services/DatabaseService.php – Tầng xử lý Dữ liệu tập trung với MySQL
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

class DatabaseService
{
    /**
     * Nạp toàn bộ dữ liệu từ MySQL thành mảng tương thích với hệ thống
     */
    public static function loadDataFromDb(): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        try {
            // 1. Projects
            $projects = [];
            foreach ($pdo->query("SELECT * FROM projects") as $row) {
                $projects[$row['id']] = $row;
            }

            // 2. Environments
            $environments = [];
            foreach ($pdo->query("SELECT * FROM environments") as $row) {
                $row['is_production'] = (bool)$row['is_production'];
                $environments[$row['key']] = $row;
            }

            // 3. Target groups
            $groups = [];
            foreach ($pdo->query("SELECT * FROM target_groups") as $row) {
                $row['range'] = $row['range_label'] ?? '';
                $groups[$row['id']] = $row;
            }

            // 4. Members
            $members = [];
            foreach ($pdo->query("SELECT * FROM members") as $row) {
                $members[$row['username']] = $row;
            }

            // 5. Flags + Environments + Target Groups + User Overrides
            $flags = [];
            foreach ($pdo->query("SELECT * FROM flags ORDER BY created_at ASC") as $row) {
                $key = $row['key'];
                $flags[$key] = [
                    'id'          => $row['id'],
                    'key'         => $key,
                    'name'        => $row['name'],
                    'description' => $row['description'] ?? '',
                    'project_id'  => $row['project_id'] ?? 'prj_ecommerce',
                    'created_at'  => $row['created_at'],
                    'updated_at'  => $row['updated_at'],
                    'environments' => [
                        'testing' => [
                            'enabled'            => false,
                            'rollout_percentage' => 0,
                            'status'             => 'Bản nháp',
                            'target_groups'      => [],
                            'user_overrides'     => [],
                            'updated_at'         => null,
                            'updated_by'         => 'system',
                        ],
                        'staging' => [
                            'enabled'            => false,
                            'rollout_percentage' => 0,
                            'status'             => 'Bản nháp',
                            'target_groups'      => [],
                            'user_overrides'     => [],
                            'updated_at'         => null,
                            'updated_by'         => 'system',
                        ],
                    ],
                ];
            }

            // Gán cấu hình theo môi trường
            foreach ($pdo->query("SELECT * FROM flag_environments") as $fe) {
                $fKey = $fe['flag_key'];
                $env  = $fe['environment'];
                if (isset($flags[$fKey]['environments'][$env])) {
                    $flags[$fKey]['environments'][$env]['enabled']            = (bool)$fe['enabled'];
                    $flags[$fKey]['environments'][$env]['rollout_percentage'] = (int)$fe['rollout_percentage'];
                    $flags[$fKey]['environments'][$env]['status']             = $fe['status'];
                    $flags[$fKey]['environments'][$env]['updated_at']         = $fe['updated_at'];
                    $flags[$fKey]['environments'][$env]['updated_by']         = $fe['updated_by'] ?? 'admin';
                }
            }

            // Gán target groups
            foreach ($pdo->query("SELECT * FROM flag_target_groups") as $ftg) {
                $fKey = $ftg['flag_key'];
                $env  = $ftg['environment'];
                if (isset($flags[$fKey]['environments'][$env])) {
                    $flags[$fKey]['environments'][$env]['target_groups'][] = $ftg['group_id'];
                }
            }

            // Gán user overrides
            foreach ($pdo->query("SELECT * FROM flag_user_overrides") as $fuo) {
                $fKey = $fuo['flag_key'];
                $env  = $fuo['environment'];
                $uid  = $fuo['user_id'];
                if (isset($flags[$fKey]['environments'][$env])) {
                    $flags[$fKey]['environments'][$env]['user_overrides'][$uid] = (bool)$fuo['state'];
                }
            }

            // 6. Audit Logs
            $audit_logs = [];
            foreach ($pdo->query("SELECT * FROM audit_logs ORDER BY created_at DESC") as $row) {
                $audit_logs[] = $row;
            }

            // 7. Versions
            $versions = [];
            foreach ($pdo->query("SELECT * FROM versions ORDER BY created_at ASC") as $row) {
                $v = $row;
                $v['flags_snapshot'] = json_decode($row['flags_snapshot'] ?? '{}', true) ?: [];
                $versions[$row['tag']] = $v;
            }

            return [
                'projects'     => $projects,
                'environments' => $environments,
                'groups'       => $groups,
                'members'      => $members,
                'flags'        => $flags,
                'audit_logs'   => $audit_logs,
                'versions'     => $versions,
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Bật/tắt cờ trong MySQL
     */
    public static function toggleFlag(string $key, string $env, ?bool $newState = null, string $user = 'admin'): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $now = date('Y-m-d H:i:s');

        // Lấy trạng thái hiện tại nếu không truyền vào newState
        if ($newState === null) {
            $stmt = $pdo->prepare("SELECT enabled FROM flag_environments WHERE flag_key = ? AND environment = ?");
            $stmt->execute([$key, $env]);
            $cur = (bool)$stmt->fetchColumn();
            $newState = !$cur;
        }

        $newStatus = $newState ? 'Đã phát hành' : 'Bản nháp';

        $stmt = $pdo->prepare("
            INSERT INTO flag_environments (flag_key, environment, enabled, status, updated_at, updated_by)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                enabled = VALUES(enabled),
                status = VALUES(status),
                updated_at = VALUES(updated_at),
                updated_by = VALUES(updated_by)
        ");
        return $stmt->execute([$key, $env, $newState ? 1 : 0, $newStatus, $now, $user]);
    }

    /**
     * Cập nhật tỷ lệ rollout trong MySQL
     */
    public static function updateRollout(string $key, string $env, int $pct, string $user = 'admin'): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $pct = max(0, min(100, $pct));
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            UPDATE flag_environments
            SET rollout_percentage = ?, updated_at = ?, updated_by = ?
            WHERE flag_key = ? AND environment = ?
        ");
        return $stmt->execute([$pct, $now, $user, $key, $env]);
    }

    /**
     * Tạo cờ mới trong MySQL
     */
    public static function createFlag(array $data): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $key  = $data['key'];
        $name = $data['name'];
        $desc = $data['description'] ?? '';
        $id   = $data['id'] ?? ('flg_' . uniqid());
        $now  = date('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO flags (id, `key`, name, description, project_id, created_at, updated_at) VALUES (?, ?, ?, ?, 'prj_ecommerce', ?, ?)");
            $stmt->execute([$id, $key, $name, $desc, $now, $now]);

            $feStmt = $pdo->prepare("INSERT INTO flag_environments (flag_key, environment, enabled, rollout_percentage, status, updated_at, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $feStmt->execute([$key, 'testing', 0, 0, 'Bản nháp', $now, 'admin']);
            $feStmt->execute([$key, 'staging', 0, 0, 'Bản nháp', $now, 'admin']);

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            return false;
        }
    }

    /**
     * Cập nhật chi tiết cờ tính năng (tên, mô tả, bật/tắt, rollout %, nhóm mục tiêu, user overrides) vào MySQL
     */
    public static function saveFlagDetail(
        string $key,
        string $env,
        string $name,
        string $description,
        bool $enabled,
        int $pct,
        array $groups,
        array $overrides,
        string $user = 'admin'
    ): bool {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $now = date('Y-m-d H:i:s');
        $status = $enabled ? 'Đã phát hành' : 'Bản nháp';
        $pct = max(0, min(100, $pct));

        $pdo->beginTransaction();
        try {
            // 1. Cập nhật bảng flags
            $stmt = $pdo->prepare("UPDATE flags SET name = ?, description = ?, updated_at = ? WHERE `key` = ?");
            $stmt->execute([$name, $description, $now, $key]);

            // 2. Cập nhật cấu hình môi trường
            $feStmt = $pdo->prepare("
                INSERT INTO flag_environments (flag_key, environment, enabled, rollout_percentage, status, updated_at, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    enabled = VALUES(enabled),
                    rollout_percentage = VALUES(rollout_percentage),
                    status = VALUES(status),
                    updated_at = VALUES(updated_at),
                    updated_by = VALUES(updated_by)
            ");
            $feStmt->execute([$key, $env, $enabled ? 1 : 0, $pct, $status, $now, $user]);

            // 3. Cập nhật nhóm mục tiêu
            $delGrp = $pdo->prepare("DELETE FROM flag_target_groups WHERE flag_key = ? AND environment = ?");
            $delGrp->execute([$key, $env]);

            if (!empty($groups)) {
                $insGrp = $pdo->prepare("INSERT INTO flag_target_groups (flag_key, environment, group_id) VALUES (?, ?, ?)");
                foreach ($groups as $gid) {
                    $insGrp->execute([$key, $env, $gid]);
                }
            }

            // 4. Cập nhật ghi đè user
            $delOvr = $pdo->prepare("DELETE FROM flag_user_overrides WHERE flag_key = ? AND environment = ?");
            $delOvr->execute([$key, $env]);

            if (!empty($overrides)) {
                $insOvr = $pdo->prepare("INSERT INTO flag_user_overrides (flag_key, environment, user_id, state, updated_at) VALUES (?, ?, ?, ?, ?)");
                foreach ($overrides as $uid => $state) {
                    $insOvr->execute([$key, $env, $uid, $state ? 1 : 0, $now]);
                }
            }

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            return false;
        }
    }

    /**
     * Lưu hoặc cập nhật dự án vào MySQL
     */
    public static function saveProject(string $id, string $key, string $name, string $desc, string $status = 'Active'): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            INSERT INTO projects (id, `key`, name, description, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                `key` = VALUES(`key`),
                name = VALUES(name),
                description = VALUES(description),
                status = VALUES(status),
                updated_at = VALUES(updated_at)
        ");
        return $stmt->execute([$id, $key, $name, $desc, $status, $now, $now]);
    }

    /**
     * Xóa dự án khỏi MySQL
     */
    public static function deleteProject(string $id): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Lưu snapshot phiên bản vào MySQL
     */
    public static function saveVersion(array $v): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $stmt = $pdo->prepare("
            INSERT INTO versions (id, version_number, tag, status, environment, created_by, created_at, reason, flags_snapshot)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                reason = VALUES(reason),
                flags_snapshot = VALUES(flags_snapshot)
        ");
        return $stmt->execute([
            $v['id'],
            $v['version_number'],
            $v['tag'],
            $v['status'] ?? 'Lưu trữ',
            $v['environment'] ?? 'staging',
            $v['created_by'] ?? 'admin',
            $v['created_at'] ?? date('Y-m-d H:i:s'),
            $v['reason'] ?? '',
            is_array($v['flags_snapshot']) ? json_encode($v['flags_snapshot'], JSON_UNESCAPED_UNICODE) : $v['flags_snapshot']
        ]);
    }

    /**
     * Xóa phiên bản khỏi MySQL
     */
    public static function deleteVersion(string $tag): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;
        $stmt = $pdo->prepare("DELETE FROM versions WHERE tag = ? OR version_number = ?");
        return $stmt->execute([$tag, $tag]);
    }

    /**
     * Xóa cờ khỏi MySQL
     */
    public static function deleteFlag(string $key): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $stmt = $pdo->prepare("DELETE FROM flags WHERE `key` = ?");
        return $stmt->execute([$key]);
    }

    /**
     * Thêm nhật ký hoạt động
     */
    public static function addAuditLog(array $log): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $id = $log['id'] ?? ('log_' . uniqid());
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (id, actor, role, `action`, target, old_value, new_value, reason, created_at, version)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $id,
            $log['actor'] ?? 'admin',
            $log['role'] ?? 'admin',
            $log['action'] ?? 'THAY_ĐỔI',
            $log['target'] ?? '',
            $log['old_value'] ?? '',
            $log['new_value'] ?? '',
            $log['reason'] ?? '',
            $log['created_at'] ?? date('Y-m-d H:i:s'),
            $log['version'] ?? 'v3'
        ]);
    }

    /**
     * Thống kê số lượng bản ghi trong từng bảng
     */
    public static function getTableStats(): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return [];

        $tables = [
            'projects'            => 'Dự án',
            'environments'        => 'Môi trường',
            'target_groups'       => 'Nhóm khách hàng',
            'customers'           => 'Khách hàng (KH1 - KH1000)',
            'members'             => 'Tài khoản quản trị',
            'flags'               => 'Cờ tính năng (Feature Flags)',
            'flag_environments'   => 'Cấu hình cờ theo môi trường',
            'flag_target_groups'  => 'Nhóm mục tiêu của cờ',
            'flag_user_overrides' => 'Ghi đè khách hàng',
            'audit_logs'          => 'Nhật ký kiểm toán',
            'versions'            => 'Phiên bản cấu hình',
        ];

        $stats = [];
        foreach ($tables as $t => $label) {
            try {
                $count = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
                $stats[$t] = ['label' => $label, 'count' => $count];
            } catch (Throwable $e) {
                $stats[$t] = ['label' => $label, 'count' => 0];
            }
        }
        return $stats;
    }

    /**
     * Chạy script database.sql để nạp mới hoặc reset toàn bộ CSDL
     */
    public static function importSqlSchema(): array
    {
        $sqlFile = __DIR__ . '/../database.sql';
        if (!file_exists($sqlFile)) {
            return ['success' => false, 'message' => 'Không tìm thấy file database.sql'];
        }

        $serverPdo = Database::getServerConnection();
        if (!$serverPdo) {
            return ['success' => false, 'message' => 'Không thể kết nối đến MySQL: ' . Database::getLastError()];
        }

        try {
            $sql = file_get_contents($sqlFile);
            $serverPdo->exec($sql);
            return ['success' => true, 'message' => 'Đã nạp toàn bộ cấu trúc và dữ liệu từ database.sql thành công!'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Lỗi khi nạp SQL: ' . $e->getMessage()];
        }
    }
}
