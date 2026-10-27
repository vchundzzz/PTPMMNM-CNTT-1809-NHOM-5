<?php
/**
 * Web Feature Flag Management System
 * index.php - Điểm vào hệ thống, điều hướng thông minh
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
