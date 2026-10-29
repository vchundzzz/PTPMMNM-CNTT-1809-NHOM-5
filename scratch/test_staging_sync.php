<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../services/FeatureFlagService.php';

init_session_data();
$_SESSION['user'] = ['id' => 'usr_admin', 'username' => 'admin', 'role' => 'admin'];
$_SESSION['current_env'] = 'staging';
set_active_env('staging');

echo "=== KIỂM TRA ĐỒNG BỘ MÔI TRƯỜNG VẬN HÀNH (STAGING) VỚI WEB DEMO ===\n";

// 1. Kiểm tra trạng thái ban đầu của thanh_toan_nhanh trên staging
$res1 = FeatureFlagService::evaluate('thanh_toan_nhanh', 'KH1', 'staging');
echo "1. Ban đầu trên Vận hành (KH1): " . ($res1['data']['enabled'] ? 'BẬT' : 'TẮT') . " (Lý do: {$res1['data']['reason']})\n";

// 2. Giả lập Admin TẮT cờ thanh_toan_nhanh trên Vận hành qua Dashboard/Toggle API
$_SESSION['ff_data']['flags']['thanh_toan_nhanh']['environments']['staging']['enabled'] = false;
$res2 = FeatureFlagService::evaluate('thanh_toan_nhanh', 'KH1', 'staging');
echo "2. Sau khi Admin TẮT trên Bảng điều khiển: " . ($res2['data']['enabled'] ? 'BẬT' : 'TẮT') . " (Lý do: {$res2['data']['reason']})\n";

// 3. Giả lập Admin BẬT lại cờ trên Vận hành
$_SESSION['ff_data']['flags']['thanh_toan_nhanh']['environments']['staging']['enabled'] = true;
$res3 = FeatureFlagService::evaluate('thanh_toan_nhanh', 'KH1', 'staging');
echo "3. Sau khi Admin BẬT lại trên Bảng điều khiển: " . ($res3['data']['enabled'] ? 'BẬT' : 'TẮT') . " (Lý do: {$res3['data']['reason']})\n";

// 4. Kiểm tra render demo-app.php khi active_env = staging
$_GET = []; // Không truyền env qua URL, phải tự động lấy từ active_env
ob_start();
include __DIR__ . '/../demo-app.php';
$html = ob_get_clean();

$has_staging = str_contains($html, 'value="staging" selected');
$has_fast_pay = str_contains($html, ' Mua ngay 1-Chạm Express');
echo "4. Web Demo tự động nhận môi trường Vận hành: " . ($has_staging ? 'CHÍNH XÁC' : 'SAI') . "\n";
echo "5. Khi BẬT cờ: Web Demo hiển thị [ Mua ngay 1-Chạm Express]: " . ($has_fast_pay ? 'CÓ (CHÍNH XÁC)' : 'KHÔNG (LỖI)') . "\n";

// 5. Thử TẮT trên Staging và kiểm tra Web Demo
$_SESSION['ff_data']['flags']['thanh_toan_nhanh']['environments']['staging']['enabled'] = false;
ob_start();
include __DIR__ . '/../demo-app.php';
$html_off = ob_get_clean();
$has_fast_pay_off = str_contains($html_off, ' Mua ngay 1-Chạm Express');
$has_std_pay = str_contains($html_off, ' Mua ngay (Tiêu chuẩn 3 bước)');
echo "6. Sau khi TẮT cờ trên Vận hành: [ Mua ngay 1-Chạm Express] biến mất: " . (!$has_fast_pay_off ? 'ĐÚNG' : 'SAI') . "\n";
echo "7. Web Demo tự động chuyển về [ Mua ngay (Tiêu chuẩn 3 bước)]: " . ($has_std_pay ? 'CÓ (ĐỒNG BỘ 100%)' : 'KHÔNG (LỖI)') . "\n";
