<?php
session_save_path(__DIR__);
session_start();
$_SESSION['user'] = ['id' => 'admin', 'username' => 'admin', 'name' => 'Admin', 'role' => 'admin'];
$_SESSION['current_env'] = 'testing';
$_GET['user_id'] = 'KH1';

// capture buffer
ob_start();
try {
    include __DIR__ . '/../demo-app.php';
    $out = ob_get_clean();
    echo "Render succeeded! Length: " . strlen($out) . " bytes.\n";
    if (strpos($out, 'id="cartModal"') !== false) {
        echo "Found #cartModal in output!\n";
    }
    if (strpos($out, 'id="standardCheckoutModal"') !== false) {
        echo "Found #standardCheckoutModal in output!\n";
    }
} catch (Throwable $e) {
    ob_end_clean();
    echo "Error: " . $e->getMessage() . "\n";
}
