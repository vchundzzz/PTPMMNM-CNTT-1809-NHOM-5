<?php
/**
 * CDS Store - Xác thực và cấp mã khách hàng thực tế
 * Đồng bộ trực tiếp với bảng `customers` và `target_groups` trong MySQL
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/DatabaseService.php';

class CustomerAuthService
{
    public static function ensureSchema(PDO $pdo): void
    {
        $usernameColumn = $pdo->query("SHOW COLUMNS FROM customers LIKE 'username'")->fetch(PDO::FETCH_ASSOC);
        if (!$usernameColumn) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN username VARCHAR(50) NULL UNIQUE AFTER id");
        }
        $column = $pdo->query("SHOW COLUMNS FROM customers LIKE 'password_hash'")->fetch(PDO::FETCH_ASSOC);
        if (!$column) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN password_hash VARCHAR(255) NULL AFTER email");
        }
        try {
            $pdo->exec("ALTER TABLE customers MODIFY COLUMN email VARCHAR(255) NULL DEFAULT ''");
        } catch (Throwable $e) {}

        // Dọn dẹp toàn bộ dữ liệu khách hàng ảo cũ (không có tài khoản thực hoặc dùng email demo)
        DatabaseService::cleanupVirtualCustomers($pdo);
    }

    /**
     * Sinh mã khách hàng mới tuần tự (KH1, KH2, KH3...) cho tài khoản đăng ký thực tế.
     */
    private static function generateNextCustomerCode(PDO $pdo): string
    {
        $stmt = $pdo->query("SELECT id FROM customers");
        $maxNum = 0;
        $existingIds = [];
        if ($stmt) {
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $existingId) {
                $existingIdStr = strtoupper((string)$existingId);
                $existingIds[$existingIdStr] = true;
                if (preg_match('/^KH(\d+)$/i', $existingIdStr, $m)) {
                    $num = (int)$m[1];
                    if ($num > $maxNum) {
                        $maxNum = $num;
                    }
                }
            }
        }

        $next = $maxNum + 1;
        while (isset($existingIds['KH' . $next])) {
            $next++;
        }
        return 'KH' . $next;
    }

    /**
     * Tạo tài khoản khách hàng mới, cấp mã khách hàng mới và đồng bộ nhóm khách hàng.
     *
     * @return array{success: bool, message: string, customer?: array}
     */
    public static function register(string $username, string $password, string $groupId = 'nhom_chung'): array
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return ['success' => false, 'message' => 'Không thể kết nối hệ thống lúc này. Vui lòng thử lại sau.'];
        }

        $username = strtolower(trim($username));

        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
            return ['success' => false, 'message' => 'Tên đăng nhập gồm 3-50 ký tự: chữ thường, số, dấu chấm, gạch dưới hoặc gạch ngang.'];
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            return ['success' => false, 'message' => 'Mật khẩu cần dài từ 8 đến 72 ký tự.'];
        }

        try {
            self::ensureSchema($pdo);
            $check = $pdo->prepare('SELECT id FROM customers WHERE username = ? LIMIT 1');
            $check->execute([$username]);
            if ($check->fetchColumn() !== false) {
                return ['success' => false, 'message' => 'Tên đăng nhập này đã được sử dụng. Vui lòng chọn tên khác.'];
            }

            // Kiểm tra nhóm hợp lệ trong target_groups, mặc định là nhom_chung
            $grpStmt = $pdo->prepare('SELECT id, name FROM target_groups WHERE id = ? LIMIT 1');
            $grpStmt->execute([$groupId]);
            $groupRow = $grpStmt->fetch(PDO::FETCH_ASSOC);
            if (!$groupRow) {
                $groupId = 'nhom_chung';
                $grpStmt->execute([$groupId]);
                $groupRow = $grpStmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => 'nhom_chung', 'name' => 'Khách Thường'];
            }

            $id = self::generateNextCustomerCode($pdo);
            $name = $username;
            $stmt = $pdo->prepare(
                'INSERT INTO customers (id, username, name, email, password_hash, group_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $id,
                $username,
                $name,
                '',
                password_hash($password, PASSWORD_DEFAULT),
                $groupId,
            ]);

            // Đồng bộ số lượng thành viên và dải mã khách hàng trong bảng target_groups
            DatabaseService::syncGroupCounts($pdo);
            if (function_exists('init_session_data')) {
                init_session_data(true);
            }

            return [
                'success' => true,
                'message' => "Đăng ký tài khoản thành công! Mã khách hàng của bạn là {$id}.",
                'customer' => [
                    'id'         => $id,
                    'username'   => $username,
                    'name'       => $name,
                    'group_id'   => $groupId,
                    'group_name' => $groupRow['name'] ?? 'Khách Thường',
                ],
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Không thể tạo tài khoản. Vui lòng kiểm tra lại thông tin.'];
        }
    }

    /**
     * Xác thực bằng tên đăng nhập (hoặc mã KH) và mật khẩu.
     */
    public static function authenticate(string $username, string $password): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }

        try {
            self::ensureSchema($pdo);
            $identifier = trim($username);
            $stmt = $pdo->prepare(
                'SELECT c.id, c.username, c.name, c.password_hash, c.group_id, g.name AS group_name
                 FROM customers c
                 LEFT JOIN target_groups g ON g.id = c.group_id
                 WHERE c.username = ? OR UPPER(c.id) = UPPER(?)
                 LIMIT 1'
            );
            $stmt->execute([strtolower($identifier), $identifier]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer || empty($customer['password_hash']) || !password_verify($password, (string)$customer['password_hash'])) {
                return null;
            }

            unset($customer['password_hash']);
            return $customer;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Lấy thông tin khách hàng thực tế theo mã KH hoặc username để đồng bộ phiên.
     */
    public static function findCustomer(string $idOrUsername): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }

        try {
            self::ensureSchema($pdo);
            $stmt = $pdo->prepare(
                'SELECT c.id, c.username, c.name, c.group_id, g.name AS group_name, c.created_at
                 FROM customers c
                 LEFT JOIN target_groups g ON g.id = c.group_id
                 WHERE UPPER(c.id) = UPPER(?) OR c.username = ?
                 LIMIT 1'
            );
            $trimmed = trim($idOrUsername);
            $stmt->execute([$trimmed, strtolower($trimmed)]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            return $customer ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }
}
