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

            // Tự động đồng bộ Override KH1 cho giao_dien_toi (giống thanh_toan_nhanh) để khi cờ giao_dien_toi BẬT trên Testing/Staging thì khách hàng mặc định KH1 luôn đồng bộ trạng thái BẬT
            foreach (['testing', 'staging'] as $envCheck) {
                if (isset($flags['giao_dien_toi']['environments'][$envCheck]) && !array_key_exists('KH1', $flags['giao_dien_toi']['environments'][$envCheck]['user_overrides'])) {
                    $flags['giao_dien_toi']['environments'][$envCheck]['user_overrides']['KH1'] = true;
                    try {
                        $pdo->prepare("INSERT IGNORE INTO flag_user_overrides (flag_key, environment, user_id, state, updated_at) VALUES ('giao_dien_toi', ?, 'KH1', 1, NOW())")->execute([$envCheck]);
                    } catch (Throwable $e) {}
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
        $ok = $stmt->execute([$key, $env, $newState ? 1 : 0, $newStatus, $now, $user]);
        if ($newState) {
            $pdo->prepare("UPDATE flag_environments SET rollout_percentage = 100 WHERE flag_key = ? AND environment = ? AND rollout_percentage <= 0")->execute([$key, $env]);
            $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM flag_target_groups WHERE flag_key = ? AND environment = ?");
            $cntStmt->execute([$key, $env]);
            if ((int)$cntStmt->fetchColumn() === 0) {
                $insGrp = $pdo->prepare("INSERT IGNORE INTO flag_target_groups (flag_key, environment, group_id) VALUES (?, ?, ?)");
                foreach (['nhom_noibu', 'nhom_beta', 'nhom_chung'] as $gid) {
                    $insGrp->execute([$key, $env, $gid]);
                }
            }
        }
        return $ok;
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
            $maxId = (string)$pdo->query("SELECT MAX(id) FROM flags WHERE id LIKE 'flg_%'")->fetchColumn();
            if (preg_match('/^flg_(\d+)$/', $maxId, $m)) {
                $id = sprintf('flg_%03d', (int)$m[1] + 1);
            }
            $user = $data['updated_by'] ?? 'admin';

            $stmt = $pdo->prepare("
                INSERT INTO flags (id, `key`, name, description, project_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'prj_ecommerce', ?, ?)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    description = VALUES(description),
                    updated_at = VALUES(updated_at)
            ");
            $stmt->execute([$id, $key, $name, $desc, $now, $now]);

            $feStmt = $pdo->prepare("
                INSERT INTO flag_environments (flag_key, environment, enabled, rollout_percentage, status, updated_at, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    updated_at = VALUES(updated_at),
                    updated_by = VALUES(updated_by)
            ");
            $feStmt->execute([$key, 'testing', 0, 0, 'Bản nháp', $now, $user]);
            $feStmt->execute([$key, 'staging', 0, 0, 'Bản nháp', $now, $user]);

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
     * Xác thực đăng nhập trực tiếp từ bảng members trong MySQL và cập nhật last_login
     */
    public static function authenticateMember(string $username, string $password): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        try {
            $stmt = $pdo->prepare("SELECT * FROM members WHERE username = ? LIMIT 1");
            $stmt->execute([trim($username)]);
            $acc = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$acc) return null;

            $dbPass = (string)($acc['password'] ?? '');
            $matched = ($dbPass === $password) || (strlen($dbPass) >= 60 && password_verify($password, $dbPass));
            if (!$matched) return null;

            $now = date('Y-m-d H:i:s');
            $upd = $pdo->prepare("UPDATE members SET last_login = ? WHERE id = ?");
            $upd->execute([$now, $acc['id']]);
            $acc['last_login'] = $now;

            return $acc;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Thêm mới hoặc cập nhật thành viên trong bảng members MySQL
     */
    public static function saveMember(array $m): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        try {
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("
                INSERT INTO members (id, username, password, name, email, role, role_title, avatar, created_at, last_login)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    username = VALUES(username),
                    password = VALUES(password),
                    name = VALUES(name),
                    email = VALUES(email),
                    role = VALUES(role),
                    role_title = VALUES(role_title),
                    avatar = VALUES(avatar)
            ");
            return $stmt->execute([
                $m['id'],
                $m['username'],
                $m['password'],
                $m['name'],
                $m['email'] ?? ($m['username'] . '@featuremanager.local'),
                $m['role'] ?? 'viewer',
                $m['role_title'] ?? 'Viewer',
                $m['avatar'] ?? strtoupper(substr($m['username'], 0, 2)),
                $m['created_at'] ?? $now,
                $m['last_login'] ?? null,
            ]);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Xóa thành viên khỏi bảng members MySQL
     */
    public static function deleteMember(string $idOrUsername): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        try {
            $stmt = $pdo->prepare("DELETE FROM members WHERE id = ? OR username = ?");
            return $stmt->execute([$idOrUsername, $idOrUsername]);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Khôi phục cấu hình cờ trên môi trường từ snapshot phiên bản trong MySQL
     */
    public static function rollbackVersion(array $snapshot, string $env, string $actor = 'admin'): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        $now = date('Y-m-d H:i:s');
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                UPDATE flag_environments
                SET enabled = ?, rollout_percentage = ?, status = ?, updated_at = ?, updated_by = ?
                WHERE flag_key = ? AND environment = ?
            ");
            foreach ($snapshot as $key => $cfg) {
                $en = !empty($cfg['enabled']) ? 1 : 0;
                $pct = (int)($cfg['rollout_percentage'] ?? 0);
                $status = $en ? 'Đã phát hành' : 'Bản nháp';
                $stmt->execute([$en, $pct, $status, $now, $actor, $key, $env]);
            }
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            return false;
        }
    }

    /**
     * Tải danh sách khách hàng từ bảng customers trong MySQL
     */
    public static function loadCustomers(): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        try {
            $stmt = $pdo->query("SELECT id, name, email, group_id, group_name FROM customers");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return !empty($rows) ? $rows : null;
        } catch (Throwable $e) {
            return null;
        }
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
     * Lưu hoặc cập nhật nhóm khách hàng mục tiêu (target_groups) vào MySQL
     */
    public static function saveGroup(array $g): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO target_groups (id, name, description, range_label, user_count, min_index, max_index)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    description = VALUES(description),
                    range_label = VALUES(range_label),
                    user_count = VALUES(user_count),
                    min_index = VALUES(min_index),
                    max_index = VALUES(max_index)
            ");
            return $stmt->execute([
                $g['id'],
                $g['name'],
                $g['description'] ?? '',
                $g['range'] ?? ($g['range_label'] ?? ''),
                (int)($g['user_count'] ?? 0),
                (int)($g['min_index'] ?? 1),
                (int)($g['max_index'] ?? 100),
            ]);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Xóa nhóm khách hàng khỏi MySQL
     */
    public static function deleteGroup(string $id): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        try {
            $stmt = $pdo->prepare("DELETE FROM target_groups WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Thêm nhật ký hoạt động
     */
    public static function addAuditLog(array $log): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        try {
            $maxId = (string)$pdo->query("SELECT MAX(id) FROM audit_logs WHERE id LIKE 'log_%'")->fetchColumn();
            $nextNum = 1;
            if (preg_match('/^log_(\d+)$/', $maxId, $m)) {
                $nextNum = (int)$m[1] + 1;
            }
            $id = sprintf('log_%03d', $nextNum);

            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (id, actor, role, `action`, target, old_value, new_value, reason, created_at, version)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    actor = VALUES(actor),
                    `action` = VALUES(`action`),
                    target = VALUES(target),
                    old_value = VALUES(old_value),
                    new_value = VALUES(new_value),
                    reason = VALUES(reason),
                    created_at = VALUES(created_at)
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
        } catch (Throwable $e) {
            return false;
        }
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
