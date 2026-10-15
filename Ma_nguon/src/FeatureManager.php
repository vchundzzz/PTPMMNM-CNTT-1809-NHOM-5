<?php
declare(strict_types=1);

require_once __DIR__ . '/Input.php';

/**
 * Khởi tạo và nạp danh sách cờ tính năng từ Session (hoặc tệp mặc định).
 */
function getFeatures(): array
{
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }

    if (!isset($_SESSION['feature_flags']) || !is_array($_SESSION['feature_flags'])) {
        $defaultFeatures = require __DIR__ . '/../data/features.php';
        $_SESSION['feature_flags'] = $defaultFeatures;
    }

    return $_SESSION['feature_flags'];
}

/**
 * Đặt lại dữ liệu cờ tính năng về mặc định ban đầu.
 */
function resetFeatures(): void
{
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    $defaultFeatures = require __DIR__ . '/../data/features.php';
    $_SESSION['feature_flags'] = $defaultFeatures;
}

/**
 * Chuyển đổi trạng thái Bật / Tắt của một cờ tính năng theo ID.
 */
function toggleFeature(int $id): bool
{
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }

    if (!isset($_SESSION['feature_flags']) || !is_array($_SESSION['feature_flags'])) {
        getFeatures();
    }

    foreach ($_SESSION['feature_flags'] as &$feature) {
        if ((int)$feature['id'] === $id) {
            $feature['enabled'] = !$feature['enabled'];
            return true;
        }
    }
    unset($feature);

    return false;
}

/**
 * Thêm một cờ tính năng mới vào hệ thống.
 */
function addFeature(array $featureData): array
{
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }

    if (!isset($_SESSION['feature_flags']) || !is_array($_SESSION['feature_flags'])) {
        getFeatures();
    }

    $maxId = 0;
    foreach ($_SESSION['feature_flags'] as $f) {
        if ((int)$f['id'] > $maxId) {
            $maxId = (int)$f['id'];
        }
    }

    $newFeature = [
        'id' => $maxId + 1,
        'key' => $featureData['key'],
        'name' => $featureData['name'],
        'category' => $featureData['category'],
        'enabled' => !empty($featureData['enabled']),
        'environment' => $featureData['environment'],
        'description' => $featureData['description'] ?? '',
    ];

    $_SESSION['feature_flags'][] = $newFeature;
    return $newFeature;
}

/**
 * Kiểm tra tính hợp lệ của dữ liệu đầu vào khi tạo cờ tính năng mới.
 */
function validateFeatureInput(array $input, array $existingFeatures): array
{
    $key = strtolower(field($input, 'key'));
    $name = field($input, 'name');
    $category = field($input, 'category');
    $environment = field($input, 'environment');
    $description = field($input, 'description');
    $enabled = field($input, 'enabled') === '1' || field($input, 'enabled') === 'true';

    $errors = [];

    // Kiểm tra Feature Key
    if ($key === '' || !preg_match('/^[a-z0-9_]{3,50}$/', $key)) {
        $errors[] = 'KEY_INVALID';
    } else {
        // Kiểm tra trùng lặp key
        foreach ($existingFeatures as $item) {
            if (isset($item['key']) && strtolower($item['key']) === $key) {
                $errors[] = 'KEY_EXISTS';
                break;
            }
        }
    }

    // Kiểm tra Tên tính năng
    if ($name === '' || strlen($name) > 120 || str_contains($name, "\0")) {
        $errors[] = 'NAME_INVALID';
    }

    // Kiểm tra Phân loại
    $validCategories = ['ui', 'backend', 'system', 'experimental'];
    if (!in_array($category, $validCategories, true)) {
        $errors[] = 'CATEGORY_INVALID';
    }

    // Kiểm tra Môi trường
    $validEnvironments = ['production', 'staging', 'development'];
    if (!in_array($environment, $validEnvironments, true)) {
        $errors[] = 'ENVIRONMENT_INVALID';
    }

    // Kiểm tra Độ dài mô tả
    if (strlen($description) > 255) {
        $errors[] = 'DESCRIPTION_TOO_LONG';
    }

    return [
        'errors' => $errors,
        'data' => [
            'key' => $key,
            'name' => $name,
            'category' => $category,
            'environment' => $environment,
            'description' => $description,
            'enabled' => $enabled,
        ],
    ];
}
