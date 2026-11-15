<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
?>
<!doctype html>
<html lang="vi">
<head><meta charset="utf-8"><title>Đang đăng xuất</title></head>
<body>
<script>
fetch('api/customer-auth.php', {
  method: 'POST',
  credentials: 'same-origin',
  headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
  body: JSON.stringify({action: 'logout', csrf_token: <?= json_encode(get_csrf_token()) ?>})
}).finally(() => { window.location.href = 'demo-app.php'; });
</script>
</body>
</html>
