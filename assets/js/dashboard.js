/**
 * Web Feature Flag Management System
 * assets/js/dashboard.js - Tương tác giao diện Dashboard tổng quan
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
  // Tìm kiếm nhanh trong bảng tính năng cập nhật gần đây
  initTableSearch('dashboardSearchInput', 'recentFlagsTable');

  // Khởi tạo tương tác nút Bật/Tắt nhanh trên Dashboard
  initQuickFlagToggles();
});

function initQuickFlagToggles() {
  const toggles = document.querySelectorAll('.dashboard-flag-toggle');

  toggles.forEach((checkbox) => {
    checkbox.addEventListener('change', (e) => {
      const flagKey = checkbox.getAttribute('data-flag-key');
      const isChecked = checkbox.checked;

      // Mô phỏng cập nhật trạng thái trong phiên làm việc
      showToast(
        `Đã ${isChecked ? 'BẬT' : 'TẮT'} tính năng [${flagKey}] trên môi trường hiện tại (Demo Session).`,
        isChecked ? 'success' : 'info'
      );
    });
  });
}
