<?php
/**
 * Hệ thống Quản lý Feature Flags
 * services/DatabaseService.php – Tầng xử lý Dữ liệu tập trung với MySQL
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

class DatabaseService
{
    private static bool $virtualCleaned = false;
    private static bool $environmentSchemaChecked = false;

    private static function ensureEnvironmentSchema(PDO $pdo): void
    {
        if (self::$environmentSchemaChecked) {
            return;
        }

        $columns = [];
        foreach ($pdo->query("SHOW COLUMNS FROM environments") as $column) {
            $columns[(string)$column['Field']] = true;
        }

        if (!isset($columns['sort_order'])) {
            $pdo->exec("ALTER TABLE environments ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER badge_color");
        }
        if (!isset($columns['simulate_error'])) {
            $pdo->exec("ALTER TABLE environments ADD COLUMN simulate_error TINYINT(1) NOT NULL DEFAULT 0 AFTER is_production");
        }
        if (!isset($columns['status'])) {
            $pdo->exec("ALTER TABLE environments ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER sort_order");
        }
        if (!isset($columns['updated_at'])) {
            $pdo->exec("ALTER TABLE environments ADD COLUMN updated_at DATETIME NULL AFTER created_at");
        }

        $rows = $pdo->query("SELECT `key` FROM environments WHERE sort_order = 0 ORDER BY created_at ASC, id ASC")->fetchAll(PDO::FETCH_COLUMN);
        if ($rows) {
            $update = $pdo->prepare("UPDATE environments SET sort_order = ?, updated_at = COALESCE(updated_at, created_at) WHERE `key` = ?");
            foreach ($rows as $index => $key) {
                $update->execute([($index + 1) * 10, $key]);
            }
        }

        $pdo->exec("UPDATE environments SET status = 'active' WHERE status IS NULL OR status = ''");
        self::$environmentSchemaChecked = true;
    }

    private static function migrateLegacyMemberPasswords(PDO $pdo): void
    {
        $rows = $pdo->query('SELECT id, password FROM members')->fetchAll(PDO::FETCH_ASSOC);
        $update = $pdo->prepare('UPDATE members SET password = ? WHERE id = ?');
        foreach ($rows as $row) {
            $password = (string)($row['password'] ?? '');
            if ($password !== '' && password_get_info($password)['algo'] === 0) {
                $update->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
            }
        }
    }

    /**
     * Xóa toàn bộ mã khách hàng ảo cũ (KH1..KH1000 không có tài khoản thực) và đồng bộ lại số lượng nhóm
     */
    public static function cleanupVirtualCustomers(?PDO $pdo = null): void
    {
        if (self::$virtualCleaned) {
            return;
        }
        $pdo = $pdo ?? Database::getConnection();
        if (!$pdo) {
            return;
        }
        self::$virtualCleaned = true;

        try {
            $usernameCol = $pdo->query("SHOW COLUMNS FROM customers LIKE 'username'")->fetch(PDO::FETCH_ASSOC);
            if (!$usernameCol) {
                $pdo->exec("ALTER TABLE customers ADD COLUMN username VARCHAR(50) NULL UNIQUE AFTER id");
            }
            $passCol = $pdo->query("SHOW COLUMNS FROM customers LIKE 'password_hash'")->fetch(PDO::FETCH_ASSOC);
            if (!$passCol) {
                $pdo->exec("ALTER TABLE customers ADD COLUMN password_hash VARCHAR(255) NULL AFTER email");
            }
            try {
                $pdo->exec("ALTER TABLE customers MODIFY COLUMN email VARCHAR(255) NULL DEFAULT ''");
            } catch (Throwable $e) {}

            // Xóa toàn bộ khách hàng ảo cũ (không có username/mật khẩu hoặc dùng email @demo.local)
            $pdo->exec("
                DELETE FROM customers
                WHERE username IS NULL
                   OR TRIM(username) = ''
                   OR password_hash IS NULL
                   OR TRIM(password_hash) = ''
                   OR email LIKE '%@demo.local'
            ");

            // Xóa email demo tự sinh trên các tài khoản thực nếu có
            $pdo->exec("
                UPDATE customers
                SET email = ''
                WHERE email LIKE '%@customer.cds-store.local' OR email LIKE '%@demo.local'
            ");

            // Chuẩn hóa các mã khách hàng cũ dạng ngẫu nhiên (VD: KHB149A5D34F) về mã tuần tự KH1, KH2, KH3...
            $allCusts = $pdo->query("SELECT id, username FROM customers ORDER BY created_at ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $usedNums = [];
            foreach ($allCusts as $c) {
                if (preg_match('/^KH(\d+)$/i', (string)$c['id'], $m)) {
                    $usedNums[(int)$m[1]] = true;
                }
            }
            $updCustId = $pdo->prepare("UPDATE customers SET id = ? WHERE id = ?");
            $updOvrId  = $pdo->prepare("UPDATE flag_user_overrides SET user_id = ? WHERE user_id = ?");
            foreach ($allCusts as $c) {
                $oldId = (string)$c['id'];
                if (!preg_match('/^KH(\d+)$/i', $oldId)) {
                    $nextNum = 1;
                    while (isset($usedNums[$nextNum])) {
                        $nextNum++;
                    }
                    $newId = 'KH' . $nextNum;
                    $usedNums[$nextNum] = true;
                    $updCustId->execute([$newId, $oldId]);
                    $updOvrId->execute([$newId, $oldId]);
                }
            }

            // Làm sạch mô tả cũ có số lượng ảo (50/200/750 tài khoản)
            $pdo->exec("
                UPDATE target_groups
                SET description = 'Đội ngũ lập trình viên, QA và kiểm thử viên nội bộ công ty.'
                WHERE id = 'nhom_noibu' AND description LIKE '%50 tài khoản%'
            ");
            $pdo->exec("
                UPDATE target_groups
                SET description = 'Khách hàng đăng ký trải nghiệm sớm tính năng mới.'
                WHERE id = 'nhom_beta' AND description LIKE '%200 tài khoản%'
            ");
            $pdo->exec("
                UPDATE target_groups
                SET description = 'Khách hàng mua sắm trực tuyến trên hệ thống CDS Store.'
                WHERE id = 'nhom_chung' AND description LIKE '%750 tài khoản%'
            ");

            self::syncGroupCounts($pdo);
        } catch (Throwable $e) {}
    }

    /**
     * Đồng bộ số lượng thành viên thực tế và danh sách mã khách hàng của từng nhóm trong target_groups
     */
    public static function syncGroupCounts(?PDO $pdo = null): void
    {
        $pdo = $pdo ?? Database::getConnection();
        if (!$pdo) {
            return;
        }

        try {
            $groups = $pdo->query("SELECT id FROM target_groups")->fetchAll(PDO::FETCH_COLUMN);
            if (!$groups) {
                return;
            }

            $custStmt = $pdo->prepare("
                SELECT id FROM customers
                WHERE group_id = ? AND username IS NOT NULL AND TRIM(username) <> ''
                ORDER BY created_at ASC, id ASC
            ");
            $updStmt = $pdo->prepare("
                UPDATE target_groups
                SET user_count = ?, range_label = ?
                WHERE id = ?
            ");

            foreach ($groups as $gid) {
                $custStmt->execute([$gid]);
                $ids = $custStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $count = count($ids);
                if ($count === 0) {
                    $rangeLabel = 'Chưa có khách hàng';
                } elseif ($count <= 5) {
                    $rangeLabel = implode(', ', $ids);
                } else {
                    $first = reset($ids);
                    $last = end($ids);
                    $rangeLabel = "{$first} → {$last} ({$count} mã KH)";
                }
                $updStmt->execute([$count, $rangeLabel, $gid]);
            }
        } catch (Throwable $e) {}
    }

    /**
     * Nạp toàn bộ dữ liệu từ MySQL thành mảng tương thích với hệ thống
     */
    public static function loadDataFromDb(): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        try {
            self::ensureEnvironmentSchema($pdo);
            self::cleanupVirtualCustomers($pdo);
            self::migrateLegacyMemberPasswords($pdo);

            // 1. Projects
            $projects = [];
            foreach ($pdo->query("SELECT * FROM projects") as $row) {
                $projects[$row['id']] = $row;
            }

            // 2. Environments
            $environments = [];
            foreach ($pdo->query("SELECT * FROM environments ORDER BY sort_order ASC, created_at ASC, id ASC") as $row) {
                $row['is_production'] = (bool)$row['is_production'];
                $row['simulate_error'] = !empty($row['simulate_error']);
                $environments[$row['key']] = $row;
            }

            // 3. Target groups
            $groups = [];
            foreach ($pdo->query("SELECT * FROM target_groups") as $row) {
                $row['range'] = $row['range_label'] ?? 'Chưa có khách hàng';
                $row['user_count'] = (int)($row['user_count'] ?? 0);
                $groups[$row['id']] = $row;
            }

            // 4. Members
            $members = [];
            foreach ($pdo->query("SELECT * FROM members") as $row) {
                unset($row['password']);
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
                    'environments' => [],
                ];
                foreach ($environments as $environmentKey => $environment) {
                    $flags[$key]['environments'][$environmentKey] = [
                            'enabled'            => false,
                            'rollout_percentage' => 0,
                            'status'             => 'Bản nháp',
                            'target_groups'      => [],
                            'user_overrides'     => [],
                            'updated_at'         => null,
                            'updated_by'         => 'system',
                    ];
                }
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
    public static function toggleFlag(string $key, string $env, ?bool $newState = null, string $user = 'admin'): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return ['success' => false, 'status' => 503, 'error' => 'Không thể kết nối cơ sở dữ liệu.'];
        if ($key === '' || !self::environmentExists($pdo, $env, true)) {
            return ['success' => false, 'status' => 422, 'error' => 'Cờ hoặc môi trường không hợp lệ.'];
        }

        $now = date('Y-m-d H:i:s');

        try {
            $stmt = $pdo->prepare("SELECT fe.enabled FROM flag_environments fe INNER JOIN flags f ON f.`key` = fe.flag_key WHERE fe.flag_key = ? AND fe.environment = ? FOR UPDATE");
            $pdo->beginTransaction();
            $stmt->execute([$key, $env]);
            $current = $stmt->fetchColumn();
            if ($current === false) {
                $pdo->rollBack();
                return ['success' => false, 'status' => 404, 'error' => 'Không tìm thấy cờ trong môi trường đã chọn.'];
            }
            $oldState = (bool)$current;
            $newState = $newState ?? !$oldState;
            $newStatus = $newState ? 'Đã phát hành' : 'Bản nháp';
            $update = $pdo->prepare("
                UPDATE flag_environments
                SET enabled = ?, rollout_percentage = CASE WHEN ? = 1 AND rollout_percentage <= 0 THEN 100 ELSE rollout_percentage END,
                    status = ?, updated_at = ?, updated_by = ?
                WHERE flag_key = ? AND environment = ?
            ");
            $update->execute([$newState ? 1 : 0, $newState ? 1 : 0, $newStatus, $now, $user, $key, $env]);
            $pdo->commit();
            return ['success' => true, 'old_enabled' => $oldState, 'enabled' => $newState];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'status' => 500, 'error' => 'Không thể cập nhật trạng thái cờ.'];
        }
    }

    /**
     * Lấy môi trường đang kích hoạt từ CSDL MySQL
     */
    public static function getActiveEnv(): ?string
    {
        $pdo = Database::getConnection();
        if (!$pdo) return null;

        try {
            self::ensureEnvironmentSchema($pdo);
            $env = $pdo->query("SELECT `key` FROM environments WHERE is_production = 1 AND status = 'active' ORDER BY sort_order ASC LIMIT 1")->fetchColumn();
            if (is_string($env) && $env !== '') {
                return $env;
            }
            $env = $pdo->query("SELECT `key` FROM environments WHERE status = 'active' ORDER BY sort_order ASC, created_at ASC, id ASC LIMIT 1")->fetchColumn();
            if (is_string($env) && $env !== '') {
                return $env;
            }
        } catch (Throwable $e) {}
        return null;
    }

    /**
     * Lưu môi trường đang kích hoạt vào CSDL MySQL để đồng bộ giữa Quản trị và Trang Khách hàng
     */
    public static function setActiveEnv(string $env): void
    {
        $pdo = Database::getConnection();
        if (!$pdo) return;

        try {
            self::ensureEnvironmentSchema($pdo);
            if (!self::environmentExists($pdo, $env, true)) {
                return;
            }
            $stmt = $pdo->prepare("UPDATE environments SET is_production = CASE WHEN `key` = ? THEN 1 ELSE 0 END");
            $stmt->execute([$env]);
        } catch (Throwable $e) {}
    }

    /**
     * Kiểm tra trạng thái mô phỏng máy chủ lỗi (Ngắt API) trên môi trường hiện tại
     */
    public static function isApiErrorSimulated(?string $env = null): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        try {
            self::ensureEnvironmentSchema($pdo);
            $targetEnv = ($env !== null && $env !== '') ? $env : (self::getActiveEnv() ?? '');
            if ($targetEnv === '') return false;
            $stmt = $pdo->prepare("SELECT simulate_error FROM environments WHERE `key` = ? LIMIT 1");
            $stmt->execute([$targetEnv]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Bật/Tắt trạng thái mô phỏng máy chủ lỗi (Ngắt API) và đồng bộ trực tiếp qua CSDL MySQL
     */
    public static function setApiErrorSimulated(bool $simulated, ?string $env = null): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        try {
            self::ensureEnvironmentSchema($pdo);
            $targetEnv = ($env !== null && $env !== '') ? $env : (self::getActiveEnv() ?? '');
            if ($targetEnv === '') return false;
            $stmt = $pdo->prepare("UPDATE environments SET simulate_error = ?, updated_at = NOW() WHERE `key` = ?");
            return $stmt->execute([$simulated ? 1 : 0, $targetEnv]);
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function environmentExists(PDO $pdo, string $key, bool $activeOnly = false): bool
    {
        self::ensureEnvironmentSchema($pdo);
        $sql = "SELECT 1 FROM environments WHERE `key` = ?";
        if ($activeOnly) {
            $sql .= " AND status = 'active'";
        }
        $stmt = $pdo->prepare($sql . " LIMIT 1");
        $stmt->execute([$key]);
        return (bool)$stmt->fetchColumn();
    }

    public static function createEnvironment(array $data): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return ['success' => false, 'error' => 'Không thể kết nối cơ sở dữ liệu.'];
        }

        self::ensureEnvironmentSchema($pdo);
        $key = strtolower(trim((string)($data['key'] ?? '')));
        $name = trim((string)($data['name'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        $badgeColor = trim((string)($data['badge_color'] ?? 'primary'));
        $sortOrder = max(0, (int)($data['sort_order'] ?? 0));
        $user = trim((string)($data['updated_by'] ?? 'admin')) ?: 'admin';

        if (!preg_match('/^[a-z][a-z0-9_-]{1,49}$/', $key) || $name === '') {
            return ['success' => false, 'error' => 'Mã môi trường hoặc tên môi trường không hợp lệ.'];
        }
        if (self::environmentExists($pdo, $key)) {
            return ['success' => false, 'error' => 'Mã môi trường đã tồn tại.'];
        }

        try {
            $pdo->beginTransaction();
            $id = 'env_' . substr(hash('sha256', $key . microtime(true)), 0, 12);
            $now = date('Y-m-d H:i:s');
            $hasEnvironment = (bool)$pdo->query("SELECT 1 FROM environments LIMIT 1")->fetchColumn();
            $stmt = $pdo->prepare("
                INSERT INTO environments (id, `key`, name, description, badge_color, is_production, sort_order, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)
            ");
            $stmt->execute([$id, $key, $name, $description, $badgeColor, $hasEnvironment ? 0 : 1, $sortOrder, $now, $now]);

            $flags = $pdo->query("SELECT `key` FROM flags ORDER BY created_at ASC, id ASC")->fetchAll(PDO::FETCH_COLUMN);
            $insert = $pdo->prepare("
                INSERT INTO flag_environments (flag_key, environment, enabled, rollout_percentage, status, updated_at, updated_by)
                VALUES (?, ?, 0, 0, 'Bản nháp', ?, ?)
            ");
            foreach ($flags as $flagKey) {
                $insert->execute([$flagKey, $key, $now, $user]);
            }
            $pdo->commit();
            return ['success' => true, 'key' => $key];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'error' => 'Không thể tạo môi trường trong cơ sở dữ liệu.'];
        }
    }

    public static function updateEnvironment(string $key, array $data): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;
        self::ensureEnvironmentSchema($pdo);
        $status = ($data['status'] ?? 'active') === 'active' ? 'active' : 'inactive';
        $current = $pdo->prepare("SELECT is_production FROM environments WHERE `key` = ?");
        $current->execute([$key]);
        if ($status === 'inactive' && (bool)$current->fetchColumn()) {
            return false;
        }
        $stmt = $pdo->prepare("
            UPDATE environments
            SET name = ?, description = ?, badge_color = ?, sort_order = ?, status = ?, updated_at = ?
            WHERE `key` = ?
        ");
        return $stmt->execute([
            trim((string)($data['name'] ?? '')),
            trim((string)($data['description'] ?? '')),
            trim((string)($data['badge_color'] ?? 'primary')),
            max(0, (int)($data['sort_order'] ?? 0)),
            $status,
            date('Y-m-d H:i:s'),
            $key,
        ]);
    }

    public static function deleteEnvironment(string $key): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return ['success' => false, 'error' => 'Không thể kết nối cơ sở dữ liệu.'];
        self::ensureEnvironmentSchema($pdo);

        $count = (int)$pdo->query("SELECT COUNT(*) FROM environments WHERE status = 'active'")->fetchColumn();
        $stmt = $pdo->prepare("SELECT is_production FROM environments WHERE `key` = ?");
        $stmt->execute([$key]);
        $environment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$environment) return ['success' => false, 'error' => 'Không tìm thấy môi trường.'];
        if (!empty($environment['is_production'])) return ['success' => false, 'error' => 'Không thể xóa môi trường đang được kích hoạt.'];
        if ($count <= 1) return ['success' => false, 'error' => 'Phải giữ lại ít nhất một môi trường đang hoạt động.'];

        try {
            $pdo->beginTransaction();
            foreach (['flag_target_groups', 'flag_user_overrides', 'flag_environments'] as $table) {
                $delete = $pdo->prepare("DELETE FROM {$table} WHERE environment = ?");
                $delete->execute([$key]);
            }
            $delete = $pdo->prepare("DELETE FROM environments WHERE `key` = ?");
            $delete->execute([$key]);
            $pdo->commit();
            return ['success' => true];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'error' => 'Không thể xóa môi trường khỏi cơ sở dữ liệu.'];
        }
    }

    /**
     * Cập nhật tỷ lệ rollout trong MySQL
     */
    public static function updateRollout(string $key, string $env, int $pct, string $user = 'admin'): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;
        if (!self::environmentExists($pdo, $env, true)) return false;

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
        $projectId = trim((string)($data['project_id'] ?? 'prj_ecommerce')) ?: 'prj_ecommerce';
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
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    description = VALUES(description),
                    project_id = VALUES(project_id),
                    updated_at = VALUES(updated_at)
            ");
            $projectStmt = $pdo->prepare("SELECT 1 FROM projects WHERE id = ? LIMIT 1");
            $projectStmt->execute([$projectId]);
            if (!$projectStmt->fetchColumn()) {
                $pdo->rollBack();
                return false;
            }
            $stmt->execute([$id, $key, $name, $desc, $projectId, $now, $now]);

            $feStmt = $pdo->prepare("
                INSERT INTO flag_environments (flag_key, environment, enabled, rollout_percentage, status, updated_at, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    updated_at = VALUES(updated_at),
                    updated_by = VALUES(updated_by)
            ");
            $environmentKeys = $pdo->query("SELECT `key` FROM environments WHERE status = 'active' ORDER BY sort_order ASC, created_at ASC, id ASC")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($environmentKeys as $environmentKey) {
                $feStmt->execute([$key, $environmentKey, 0, 0, 'Bản nháp', $now, $user]);
            }

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
        if (!self::environmentExists($pdo, $env, true)) return false;

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
        try {
            $pdo->beginTransaction();

            if ($status === 'Active') {
                $pdo->query("SELECT id FROM projects FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
                $deactivateOthers = $pdo->prepare("
                    UPDATE projects
                    SET status = 'Archived', updated_at = ?
                    WHERE id <> ? AND status = 'Active'
                ");
                $deactivateOthers->execute([$now, $id]);
            }

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
            $saved = $stmt->execute([$id, $key, $name, $desc, $status, $now, $now]);

            if ($saved) {
                $pdo->commit();
                return true;
            }

            $pdo->rollBack();
            return false;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
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
            self::migrateLegacyMemberPasswords($pdo);
            $stmt = $pdo->prepare("SELECT * FROM members WHERE username = ? LIMIT 1");
            $stmt->execute([trim($username)]);
            $acc = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$acc) return null;

            $dbPass = (string)($acc['password'] ?? '');
            $matched = password_verify($password, $dbPass);
            if (!$matched && $dbPass !== '' && hash_equals($dbPass, $password)) {
                // Upgrade legacy plaintext credentials at the first successful login.
                $dbPass = password_hash($password, PASSWORD_DEFAULT);
                $upgrade = $pdo->prepare('UPDATE members SET password = ? WHERE id = ?');
                $upgrade->execute([$dbPass, $acc['id']]);
                $matched = true;
            }
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
            $password = (string)($m['password'] ?? '');
            $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
            $stmt = $pdo->prepare("
                INSERT INTO members (id, username, password, name, email, role, role_title, avatar, created_at, last_login)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    username = VALUES(username),
                    password = COALESCE(VALUES(password), password),
                    name = VALUES(name),
                    email = VALUES(email),
                    role = VALUES(role),
                    role_title = VALUES(role_title),
                    avatar = VALUES(avatar)
            ");
            return $stmt->execute([
                $m['id'],
                $m['username'],
                $passwordHash,
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
     * Tải danh sách khách hàng thực tế đã đăng ký từ bảng customers trong MySQL
     */
    public static function loadCustomers(): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) return [];

        try {
            self::cleanupVirtualCustomers($pdo);
            $stmt = $pdo->query("
                SELECT c.id, c.username, c.name, c.group_id, COALESCE(g.name, 'Khách Thường') AS group_name, c.created_at
                FROM customers c
                LEFT JOIN target_groups g ON g.id = c.group_id
                WHERE c.username IS NOT NULL AND TRIM(c.username) <> ''
                ORDER BY c.created_at DESC, c.id DESC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Cập nhật nhóm trực thuộc của khách hàng và đồng bộ số lượng nhóm
     */
    public static function updateCustomerGroup(string $customerId, string $groupId): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        try {
            $stmt = $pdo->prepare("UPDATE customers SET group_id = ? WHERE id = ?");
            $ok = $stmt->execute([$groupId, $customerId]);
            self::syncGroupCounts($pdo);
            return $ok;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Xóa tài khoản khách hàng khỏi hệ thống và đồng bộ lại nhóm
     */
    public static function deleteCustomer(string $customerId): bool
    {
        $pdo = Database::getConnection();
        if (!$pdo) return false;

        try {
            $pdo->prepare("DELETE FROM flag_user_overrides WHERE user_id = ?")->execute([$customerId]);
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $ok = $stmt->execute([$customerId]);
            self::syncGroupCounts($pdo);
            return $ok;
        } catch (Throwable $e) {
            return false;
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
            $key = $g['key'] ?? preg_replace('/^nhom_/', '', (string)$g['id']);
            if ($key === '') {
                $key = (string)$g['id'];
            }
            $stmt = $pdo->prepare("
                INSERT INTO target_groups (id, `key`, name, description, user_count, range_label, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    description = VALUES(description)
            ");
            $ok = $stmt->execute([
                $g['id'],
                $key,
                $g['name'],
                $g['description'] ?? '',
                (int)($g['user_count'] ?? 0),
                $g['range'] ?? ($g['range_label'] ?? 'Chưa có khách hàng'),
            ]);
            self::syncGroupCounts($pdo);
            return $ok;
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
            // Chuyển các khách hàng đang thuộc nhóm bị xóa về nhom_chung trước khi xóa nhóm
            $pdo->prepare("UPDATE customers SET group_id = 'nhom_chung' WHERE group_id = ?")->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM target_groups WHERE id = ?");
            $ok = $stmt->execute([$id]);
            self::syncGroupCounts($pdo);
            return $ok;
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
            'customers'           => 'Tài khoản khách hàng đã đăng ký',
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
            $existing = self::getTableStats();
            foreach ($existing as $table => $stat) {
                if ((int)$stat['count'] > 0) {
                    return [
                        'success' => false,
                        'message' => "Từ chối nạp lại vì bảng [$table] đang có dữ liệu. Hãy dùng migration có kiểm soát và backup trước khi thay đổi.",
                    ];
                }
            }
            $sql = file_get_contents($sqlFile);
            $serverPdo->exec($sql);
            return ['success' => true, 'message' => 'Đã nạp toàn bộ cấu trúc và dữ liệu từ database.sql thành công!'];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Lỗi khi nạp SQL: ' . $e->getMessage()];
        }
    }
}
