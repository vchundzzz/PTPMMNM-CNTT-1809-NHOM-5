<?php
require_once __DIR__ . '/../services/FeatureFlagService.php';

$tests = [
    ['key' => 'giao_dien_toi', 'user' => 'KH1', 'env' => 'testing', 'expect' => true],
    ['key' => 'giao_dien_toi', 'user' => 'KH2', 'env' => 'testing', 'expect' => false],
    ['key' => 'giao_dien_toi', 'user' => 'KH5', 'env' => 'testing', 'expect' => true],
    ['key' => 'giao_dien_toi', 'user' => 'KH2', 'env' => 'staging', 'expect' => false],
    ['key' => 'giao_dien_toi', 'user' => 'KH5', 'env' => 'staging', 'expect' => true],
    ['key' => 'giao_dien_toi', 'user' => 'KH60', 'env' => 'staging', 'expect' => false],
    ['key' => 'giao_dien_toi', 'user' => 'KH500', 'env' => 'staging', 'expect' => false],
];

foreach ($tests as $t) {
    $res = FeatureFlagService::evaluate($t['key'], $t['user'], $t['env']);
    $actual = !empty($res['data']['enabled']);
    $pass = ($actual === $t['expect']) ? 'PASS' : 'FAIL';
    echo "[$pass] {$t['env']} - {$t['user']}: enabled=" . ($actual ? 'TRUE' : 'FALSE') . " (reason: {$res['data']['reason']})\n";
}

