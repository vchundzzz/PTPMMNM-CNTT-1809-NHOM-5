<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = Database::getConnection();
if ($pdo) {
    $stmt = $pdo->prepare("INSERT INTO flag_user_overrides (flag_key, environment, user_id, state) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE state = VALUES(state)");
    $stmt->execute(['giao_dien_toi', 'testing', 'KH5', 1]);
    $stmt->execute(['giao_dien_toi', 'testing', 'KH2', 0]);
    $stmt->execute(['giao_dien_toi', 'staging', 'KH5', 1]);
    $stmt->execute(['giao_dien_toi', 'staging', 'KH2', 0]);
    echo "DB overrides updated successfully\n";
} else {
    echo "Failed to connect to DB\n";
}

