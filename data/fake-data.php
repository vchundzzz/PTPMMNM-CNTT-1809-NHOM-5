<?php
/**
 * Database-backed application state loader.
 *
 * The filename is retained for backwards-compatible includes. No business
 * data is generated here; all records must come from the configured database.
 */

declare(strict_types=1);

function load_customers(): array
{
    require_once __DIR__ . '/../services/DatabaseService.php';
    return DatabaseService::loadCustomers() ?? [];
}

function init_session_data(bool $forceReload = false): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    static $loadedThisRequest = false;
    if ($loadedThisRequest && !$forceReload) {
        return;
    }
    $loadedThisRequest = true;

    require_once __DIR__ . '/../services/DatabaseService.php';
    $dbData = DatabaseService::loadDataFromDb();
    if ($dbData !== null) {
        $_SESSION['ff_data'] = $dbData;
    } elseif (!isset($_SESSION['ff_data'])) {
        $_SESSION['ff_data'] = [
            'projects' => [],
            'environments' => [],
            'groups' => [],
            'members' => [],
            'flags' => [],
            'audit_logs' => [],
            'versions' => [],
        ];
    }

    if (!isset($_SESSION['current_env'])) {
        $defaultEnvironment = DatabaseService::getActiveEnv();
        if ($defaultEnvironment !== null) {
            $_SESSION['current_env'] = $defaultEnvironment;
        }
    }

    if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
        $username = (string)($_SESSION['user']['username'] ?? '');
        $member = $_SESSION['ff_data']['members'][$username] ?? null;
        if (is_array($member)) {
            $_SESSION['user'] = [
                'id' => $member['id'],
                'username' => $member['username'],
                'name' => $member['name'],
                'email' => $member['email'],
                'role' => $member['role'],
                'role_title' => $member['role_title'],
                'avatar' => $member['avatar'],
            ];
        }
    }
}

function reset_session_data(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once __DIR__ . '/../services/DatabaseService.php';
    $result = DatabaseService::importSqlSchema();
    if (!$result['success']) {
        throw new RuntimeException($result['message']);
    }
    init_session_data(true);
    $_SESSION['current_env'] = DatabaseService::getActiveEnv() ?? array_key_first($_SESSION['ff_data']['environments'] ?? '');
}
