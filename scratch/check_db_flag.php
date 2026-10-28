<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../services/DatabaseService.php';
$data = DatabaseService::loadDataFromDb();
echo "giao_dien_toi from DB:\n";
print_r($data['flags']['giao_dien_toi'] ?? 'NOT FOUND');
