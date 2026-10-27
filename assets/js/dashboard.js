/**
 * Web Feature Flag Management System
 * assets/js/dashboard.js - Tương tác giao diện Dashboard tổng quan
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
  // Tìm kiếm nhanh trong bảng tính năng cập nhật gần đây
  initTableSearch('dashboardSearchInput', 'recentFlagsTable');

  // Khởi tạo tương tác nút Bật/Tắt nhanh trên Dashboard kết nối API thời gian thực
  initQuickFlagToggles();
});

function initQuickFlagToggles() {
  const toggles = document.querySelectorAll('.dashboard-flag-toggle');

  toggles.forEach((checkbox) => {
    checkbox.addEventListener('change', async (e) => {
      const flagKey = checkbox.getAttribute('data-flag-key');
      const isChecked = checkbox.checked;
      checkbox.disabled = true;

      try {
        const response = await fetch('api/toggle.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            flag_key: flagKey,
            enabled: isChecked,
          }),
        });

        const data = await response.json();
        if (data.success) {
          showToast(data.message || `Đã ${isChecked ? 'BẬT' : 'TẮT'} tính năng [${flagKey}] thành công!`, isChecked ? 'success' : 'info');

          // Cập nhật nhãn trạng thái bên cạnh switch
          const badge = checkbox.closest('td')?.querySelector('.badge');
          if (badge) {
            if (isChecked) {
              badge.className = 'badge badge-success';
              badge.textContent = 'Đã phát hành';
            } else {
              badge.className = 'badge badge-neutral';
              badge.textContent = 'Bản nháp';
            }
          }
        } else {
          checkbox.checked = !isChecked;
          showToast(data.error || 'Có lỗi khi cập nhật cờ tính năng.', 'error');
        }
      } catch (err) {
        checkbox.checked = !isChecked;
        showToast('Lỗi kết nối máy chủ khi cập nhật cờ tính năng.', 'error');
      } finally {
        checkbox.disabled = false;
      }
    });
  });
}
