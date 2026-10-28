<?php
/**
 * Hệ thống Quản lý Feature Flags
 * includes/db.php – Lớp kết nối Cơ sở dữ liệu MySQL (PDO)
 */

declare(strict_types=1);

if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', 3306);
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'feature_flags_db');

class Database
{
    private static ?PDO $instance = null;
    private static ?string $lastError = null;

    /**
     * Lấy kết nối PDO Singleton đến Database
     */
    public static function getConnection(): ?PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            self::$lastError = null;
            return self::$instance;
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            return null;
        }
    }

    /**
     * Kết nối tới MySQL Server không chỉ định dbname (dùng khi kiểm tra hoặc tạo database)
     */
    public static function getServerConnection(): ?PDO
    {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            return new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            return null;
        }
    }

    /**
     * Kiểm tra trạng thái kết nối
     */
    public static function isConnected(): bool
    {
        return self::getConnection() !== null;
    }

    /**
     * Lấy thông báo lỗi gần nhất
     */
    public static function getLastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * Thực thi truy vấn Prepared Statement an toàn
     */
    public static function execute(string $sql, array $params = []): bool
    {
        $db = self::getConnection();
        if (!$db) return false;
        try {
            $stmt = $db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Lấy toàn bộ danh sách kết quả
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $db = self::getConnection();
        if (!$db) return [];
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * Lấy 1 bản ghi
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $db = self::getConnection();
        if (!$db) return null;
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetch();
            return $res !== false ? $res : null;
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            return null;
        }
    }

    /**
     * Lấy 1 giá trị đơn
     */
    public static function fetchColumn(string $sql, array $params = [], int $col = 0): mixed
    {
        $db = self::getConnection();
        if (!$db) return null;
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetchColumn($col);
            return $res !== false ? $res : null;
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            return null;
        }
    }
}

function db(): ?PDO
{
    return Database::getConnection();
}
