<?php
/**
 * CDS Store - Xác thực tài khoản khách hàng
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

class CustomerAuthService
{
    private static function ensureSchema(PDO $pdo): void
    {
        $usernameColumn = $pdo->query("SHOW COLUMNS FROM customers LIKE 'username'")->fetch(PDO::FETCH_ASSOC);
        if (!$usernameColumn) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN username VARCHAR(50) NULL UNIQUE AFTER id");
        }
        $column = $pdo->query("SHOW COLUMNS FROM customers LIKE 'password_hash'")->fetch(PDO::FETCH_ASSOC);
        if (!$column) {
            $pdo->exec("ALTER TABLE customers ADD COLUMN password_hash VARCHAR(255) NULL AFTER email");
        }
    }

    /**
     * Tạo tài khoản khách hàng mới.
     *
     * @return array{success: bool, message: string, customer?: array}
     */
    public static function register(string $username, string $password): array
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
            $id = 'KH' . strtoupper(bin2hex(random_bytes(5)));
            $name = $username;
            $email = $username . '@customer.cds-store.local';
            $stmt = $pdo->prepare(
                'INSERT INTO customers (id, username, name, email, password_hash, group_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $id,
                $username,
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                'nhom_chung',
            ]);

            return [
                'success' => true,
                'message' => 'Tạo tài khoản thành công.',
                'customer' => [
                    'id' => $id,
                    'username' => $username,
                    'name' => $name,
                    'email' => $email,
                    'group_id' => 'nhom_chung',
                ],
            ];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Không thể tạo tài khoản. Vui lòng kiểm tra lại thông tin.'];
        }
    }

    /**
     * Xác thực bằng email và mật khẩu.
     */
    public static function authenticate(string $username, string $password): ?array
    {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }

        try {
            self::ensureSchema($pdo);
            $stmt = $pdo->prepare(
                'SELECT id, username, name, email, password_hash, group_id
                 FROM customers WHERE username = ? LIMIT 1'
            );
            $stmt->execute([strtolower(trim($username))]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer || !password_verify($password, (string)$customer['password_hash'])) {
                return null;
            }

            unset($customer['password_hash']);
            return $customer;
        } catch (PDOException $e) {
            return null;
        }
    }
}
