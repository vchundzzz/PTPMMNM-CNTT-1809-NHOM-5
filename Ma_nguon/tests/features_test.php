<?php
require __DIR__ . '/helper.php';
require __DIR__ . '/../src/Input.php';
require __DIR__ . '/../src/FeatureFilter.php';
require __DIR__ . '/../src/FeatureManager.php';

$features = require __DIR__ . '/../data/features.php';

// 1. Lọc tất cả khi không có điều kiện
same(count(filterFeatures($features, '')), 6, 'features-all');

// 2. Lọc theo từ khóa
same(count(filterFeatures($features, 'dark')), 1, 'features-keyword-dark');

// 3. Lọc theo phân loại category
same(count(filterFeatures($features, '', 'ui')), 2, 'features-category-ui');

// 4. Lọc theo trạng thái enabled
$enabledFeatures = filterFeatures($features, '', '', 'enabled');
same(count($enabledFeatures), 3, 'features-status-enabled');

// 5. Lọc theo môi trường
same(count(filterFeatures($features, '', '', '', 'production')), 3, 'features-env-prod');

// 6. Lọc kết hợp (Category 'ui' + Môi trường 'production' + Status 'enabled')
$combined = filterFeatures($features, '', 'ui', 'enabled', 'production');
same(count($combined), 1, 'features-combined-match');
same($combined[0]['key'] ?? '', 'dark_mode_ui', 'features-combined-key');

// 7. Validation dữ liệu hợp lệ
$validInput = [
    'key' => 'custom_theme_v2',
    'name' => 'Giao diện tùy chỉnh v2',
    'category' => 'ui',
    'environment' => 'staging',
    'description' => 'Mô tả tính năng',
    'enabled' => '1',
];
$res = validateFeatureInput($validInput, $features);
same($res['errors'], [], 'feature-valid-input');

// 8. Validation key không hợp lệ
$invalidKeyInput = [
    'key' => 'Invalid Key @#$!',
    'name' => 'Tên tính năng',
    'category' => 'ui',
    'environment' => 'staging',
];
$resKey = validateFeatureInput($invalidKeyInput, $features);
same(in_array('KEY_INVALID', $resKey['errors'], true), true, 'feature-invalid-key');

// 9. Validation trùng lặp key đã có
$duplicateKeyInput = [
    'key' => 'dark_mode_ui',
    'name' => 'Trùng key',
    'category' => 'ui',
    'environment' => 'staging',
];
$resDup = validateFeatureInput($duplicateKeyInput, $features);
same(in_array('KEY_EXISTS', $resDup['errors'], true), true, 'feature-duplicate-key');

// 10. Chuyển đổi trạng thái Bật / Tắt (Toggle)
toggleFeature(1);
$current = getFeatures();
same($current[0]['enabled'], false, 'feature-toggle-to-false');
toggleFeature(1);
$current2 = getFeatures();
same($current2[0]['enabled'], true, 'feature-toggle-back-to-true');

finish();

