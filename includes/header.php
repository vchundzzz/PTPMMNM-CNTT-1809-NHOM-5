<?php
/**
 * Hệ thống quản lý tính năng
 * includes/header.php - Phần đầu trang HTML và nạp tài nguyên giao diện
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../services/PermissionService.php';

// Kiểm tra phiên đăng nhập
require_login();

// Biến cấu hình tiêu đề trang
$page_title = $page_title ?? 'Bảng điều khiển';
$current_user = get_authenticated_user();
$active_env = get_active_env();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title><?= e($page_title) ?> - Quản lý tính năng</title>
  
  <!-- CSS Stylesheets theo kiến trúc tách biệt -->
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/components.css">
  <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body>

<div class="app-wrapper">
  <!-- Sidebar điều hướng chính -->
  <?php require_once __DIR__ . '/sidebar.php'; ?>

  <!-- Khu vực nội dung chính -->
  <div class="app-main">
    <!-- Topbar trên cùng -->
    <?php require_once __DIR__ . '/topbar.php'; ?>

    <!-- Wrapper nội dung trang -->
    <main class="content-wrapper">
