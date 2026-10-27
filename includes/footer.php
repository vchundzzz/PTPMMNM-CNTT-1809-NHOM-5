<?php
/**
 * Web Feature Flag Management System
 * includes/footer.php - Chân trang, container thông báo Toast và nạp JavaScript
 */

declare(strict_types=1);

$flash = get_flash();
?>
    </main> <!-- Đóng .content-wrapper -->

    <!-- Chân trang -->
    <footer class="app-footer">
      <div>
        <strong>Hệ thống quản lý tính năng</strong> &copy; <?= date('Y') ?> - Quản lý cấu hình bật/tắt tính năng
      </div>
      <div class="footer-links">
        <span>Bài tập lớn: <strong>Phát triển phần mềm mã nguồn mở</strong></span>
        <span>Môi trường: <strong style="color: var(--primary);"><?= get_active_env() === 'testing' ? 'Kiểm thử' : 'Vận hành' ?></strong></span>
      </div>
    </footer>
  </div> <!-- Đóng .app-main -->
</div> <!-- Đóng .app-wrapper -->

<!-- Container hiển thị thông báo Toast -->
<div id="toast-container" class="toast-container" aria-live="polite"></div>

<!-- Lớp nền mờ khi mở Sidebar trên điện thoại -->
<div id="sidebar-backdrop" class="sidebar-backdrop"></div>

<!-- JavaScript chung của hệ thống -->
<script src="assets/js/app.js"></script>

<!-- JavaScript riêng theo từng trang nếu có -->
<?php if (!empty($page_js)): ?>
  <script src="<?= e($page_js) ?>"></script>
<?php endif; ?>

<!-- Tự động hiển thị Flash Message từ Session nếu có -->
<?php if ($flash): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      if (typeof showToast === 'function') {
        showToast(<?= json_encode($flash['message']) ?>, <?= json_encode($flash['type']) ?>);
      }
    });
  </script>
<?php endif; ?>

</body>
</html>
