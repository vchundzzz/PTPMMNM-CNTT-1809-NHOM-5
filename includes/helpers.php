<?php
/**
 * Hệ thống Quản lý Feature Flags
 * includes/helpers.php – Hàm tiện ích dùng chung + xử lý URL nhanh
 */

declare(strict_types=1);

require_once __DIR__ . '/../data/fake-data.php';

if (session_status() === PHP_SESSION_NONE) session_start();
init_session_data();

/* ============================================================
   HÀM TIỆN ÍCH CƠ BẢN
   ============================================================ */

/** Escape HTML an toàn chống XSS */
function e(?string $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

/** CSRF token */
function get_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(get_csrf_token()) . '">';
}

function validate_csrf_token(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

/* ============================================================
   XÁC THỰC & SESSION NGƯỜI DÙNG
   ============================================================ */

function is_logged_in(): bool
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']);
}

function get_authenticated_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function attempt_login(string $username, string $password): bool
{
    $username = trim($username);
    $members  = $_SESSION['ff_data']['members'] ?? [];
    if (!isset($members[$username])) return false;
    $acc = $members[$username];
    if ($acc['password'] !== $password) return false;

    $_SESSION['user'] = [
        'id'         => $acc['id'],
        'username'   => $acc['username'],
        'name'       => $acc['name'],
        'email'      => $acc['email'],
        'role'       => $acc['role'],
        'role_title' => $acc['role_title'],
        'avatar'     => $acc['avatar'],
    ];
    $_SESSION['ff_data']['members'][$username]['last_login'] = date('Y-m-d H:i:s');
    return true;
}

function logout_user(): void
{
    unset($_SESSION['user']);
    session_regenerate_id(true);
}

/* ============================================================
   MÔI TRƯỜNG & FLASH
   ============================================================ */

function get_active_env(): string
{
    $env = $_SESSION['current_env'] ?? 'testing';
    return in_array($env, ['testing', 'staging'], true) ? $env : 'testing';
}

function set_active_env(string $env): void
{
    if (in_array($env, ['testing', 'staging'], true)) {
        $_SESSION['current_env'] = $env;
    }
}

function set_flash(string $type, string $msg): void
{
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $msg];
}

function get_flash(): ?array
{
    if (isset($_SESSION['flash_message'])) {
        $f = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $f;
    }
    return null;
}

/* ============================================================
   GIAO DIỆN TIỆN ÍCH
   ============================================================ */

function is_nav_active(string $file): string
{
    return (basename($_SERVER['PHP_SELF'] ?? '') === $file) ? 'active' : '';
}

function format_date(?string $dt, string $fmt = 'd/m/Y H:i'): string
{
    if (empty($dt)) return '—';
    $ts = strtotime($dt);
    return $ts ? date($fmt, $ts) : $dt;
}

function render_badge(string $status): string
{
    $map = [
        'đã phát hành' => 'badge-success',
        'đang dùng'    => 'badge-success',
        'published'    => 'badge-success',
        'hoạt động'    => 'badge-success',
        'active'       => 'badge-success',
        'bản nháp'     => 'badge-warning',
        'draft'        => 'badge-warning',
        'lưu trữ'      => 'badge-neutral',
        'archived'     => 'badge-neutral',
        'disabled'     => 'badge-neutral',
    ];
    $cls = $map[strtolower(trim($status))] ?? 'badge-neutral';
    return '<span class="badge ' . $cls . '">' . e($status) . '</span>';
}

function render_role_badge(string $role): string
{
    $map = [
        'admin'  => ['Admin',    'badge-role-admin'],
        'editor' => ['Biên tập', 'badge-role-editor'],
        'viewer' => ['Viewer',   'badge-role-viewer'],
    ];
    [$label, $cls] = $map[strtolower($role)] ?? [ucfirst($role), 'badge-neutral'];
    return '<span class="badge ' . $cls . '">' . e($label) . '</span>';
}

function render_env_badge(string $env): string
{
    $env = strtolower(trim($env));
    if ($env === 'testing') {
        return '<span class="badge badge-env-testing"><span class="badge-dot"></span>Kiểm thử</span>';
    } elseif ($env === 'staging') {
        return '<span class="badge badge-env-staging"><span class="badge-dot"></span>Vận hành</span>';
    }
    return '<span class="badge badge-neutral">' . e(ucfirst($env)) . '</span>';
}

/* ============================================================
   XỬ LÝ URL NHANH (switch env / role / reset)
   ============================================================ */

if (isset($_GET['switch_env']) && in_array($_GET['switch_env'], ['testing', 'staging'], true)) {
    set_active_env($_GET['switch_env']);
    set_flash('info', 'Đã chuyển sang môi trường: ' . ($_GET['switch_env'] === 'testing' ? 'Kiểm thử' : 'Vận hành'));
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if (isset($_GET['reset_demo_data']) && $_GET['reset_demo_data'] === '1') {
    reset_session_data();
    set_flash('success', 'Đã khôi phục toàn bộ dữ liệu về trạng thái ban đầu!');
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/* ============================================================
   GHI NHẬT KÝ HOẠT ĐỘNG
   ============================================================ */

function append_audit_log(
    string $action,
    string $target,
    string $old_value,
    string $new_value,
    string $reason = '',
    string $version = 'v3'
): void {
    $user = get_authenticated_user();
    $_SESSION['ff_data']['audit_logs'][] = [
        'id'         => 'log_' . sprintf('%03d', count($_SESSION['ff_data']['audit_logs']) + 1),
        'actor'      => $user['username'] ?? 'system',
        'role'       => $user['role'] ?? 'admin',
        'action'     => $action,
        'target'     => $target,
        'old_value'  => $old_value,
        'new_value'  => $new_value,
        'reason'     => $reason,
        'created_at' => date('Y-m-d H:i:s'),
        'version'    => $version,
    ];
}
