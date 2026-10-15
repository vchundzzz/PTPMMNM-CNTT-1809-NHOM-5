<?php
declare(strict_types=1);

/**
 * Lọc danh sách cờ tính năng (Feature Flags) kết hợp nhiều điều kiện:
 * - $query: Từ khóa tìm kiếm theo key, name hoặc description.
 * - $category: Lọc theo phân loại (ui, backend, system, experimental).
 * - $status: Lọc theo trạng thái ('enabled', 'disabled' hoặc rỗng để lấy tất cả).
 * - $environment: Lọc theo môi trường ('production', 'staging', 'development' hoặc rỗng).
 */
function filterFeatures(
    array $features,
    string $query = '',
    string $category = '',
    string $status = '',
    string $environment = ''
): array {
    $result = [];
    foreach ($features as $feature) {
        $matchesQuery = $query === ''
            || stripos($feature['key'], $query) !== false
            || stripos($feature['name'], $query) !== false
            || stripos($feature['description'], $query) !== false;

        $matchesCategory = $category === ''
            || $feature['category'] === $category;

        $matchesStatus = $status === ''
            || ($status === 'enabled' && !empty($feature['enabled']))
            || ($status === 'disabled' && empty($feature['enabled']));

        $matchesEnv = $environment === ''
            || $feature['environment'] === $environment;

        if ($matchesQuery && $matchesCategory && $matchesStatus && $matchesEnv) {
            $result[] = $feature;
        }
    }
    return $result;
}

