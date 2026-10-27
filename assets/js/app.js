/**
 * Web Feature Flag Management System
 * assets/js/app.js - Xử lý tương tác giao diện SaaS Admin Dashboard
 * Vanilla JS (ES6+), không phụ thuộc thư viện ngoài
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {
  initSidebar();
  initUserDropdown();
  initModals();
});

/**
 * Khởi tạo hành vi đóng/mở Sidebar trên màn hình di động
 */
function initSidebar() {
  const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
  const appSidebar = document.getElementById('appSidebar');
  const sidebarBackdrop = document.getElementById('sidebarBackdrop');

  if (!sidebarToggleBtn || !appSidebar) return;

  sidebarToggleBtn.addEventListener('click', () => {
    if (window.matchMedia('(max-width: 1024px)').matches) {
      appSidebar.classList.toggle('mobile-open');
      if (sidebarBackdrop) sidebarBackdrop.classList.toggle('show');
      return;
    }
    document.querySelector('.app-wrapper')?.classList.toggle('sidebar-collapsed');
  });

  if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', () => {
      appSidebar.classList.remove('mobile-open');
      sidebarBackdrop.classList.remove('show');
    });
  }
}

/**
 * Khởi tạo Dropdown menu tài khoản người dùng
 */
function initUserDropdown() {
  const userProfileBtn = document.getElementById('userProfileBtn');
  const userDropdownContent = document.getElementById('userDropdownContent');

  if (!userProfileBtn || !userDropdownContent) return;

  userProfileBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    userDropdownContent.classList.toggle('show');
  });

  // Đóng dropdown khi click ra ngoài
  document.addEventListener('click', (e) => {
    if (!userDropdownContent.contains(e.target) && !userProfileBtn.contains(e.target)) {
      userDropdownContent.classList.remove('show');
    }
  });
}

/**
 * Hiển thị thông báo Toast góc dưới màn hình
 * @param {string} message - Nội dung thông báo
 * @param {'success'|'error'|'warning'|'info'} type - Loại thông báo
 * @param {number} duration - Thời gian hiển thị (ms)
 */
function showToast(message, type = 'info', duration = 3500) {
  const container = document.getElementById('toast-container');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;

  // SVG Icon tương ứng
  let iconSvg = '';
  if (type === 'success') {
    iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>`;
  } else if (type === 'error') {
    iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>`;
  } else if (type === 'warning') {
    iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>`;
  } else {
    iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0ea5e9" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>`;
  }

  toast.innerHTML = `
    <div class="toast-icon">${iconSvg}</div>
    <div class="toast-message">${escapeHtml(message)}</div>
    <button type="button" class="toast-close" aria-label="Đóng">&times;</button>
  `;

  container.appendChild(toast);

  // Xử lý nút đóng thủ công
  const closeBtn = toast.querySelector('.toast-close');
  closeBtn.addEventListener('click', () => {
    dismissToast(toast);
  });

  // Tự động đóng sau duration
  setTimeout(() => {
    dismissToast(toast);
  }, duration);
}

function dismissToast(toastElement) {
  if (!toastElement || !toastElement.parentElement) return;
  toastElement.style.opacity = '0';
  toastElement.style.transform = 'translateY(10px)';
  toastElement.style.transition = 'all 0.2s ease-out';
  setTimeout(() => {
    if (toastElement.parentElement) {
      toastElement.parentElement.removeChild(toastElement);
    }
  }, 200);
}

/**
 * Xử lý mở / đóng Modal Dialog
 */
function initModals() {
  // Mở modal qua data-modal-target
  document.querySelectorAll('[data-modal-target]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
      const modalId = trigger.getAttribute('data-modal-target');
      openModal(modalId);
    });
  });

  // Đóng modal qua data-modal-close
  document.querySelectorAll('[data-modal-close]').forEach((closer) => {
    closer.addEventListener('click', () => {
      const modalBackdrop = closer.closest('.modal-backdrop');
      if (modalBackdrop) {
        modalBackdrop.classList.remove('show');
      }
    });
  });

  // Đóng modal khi bấm vào vùng nền mờ bên ngoài
  document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) {
        backdrop.classList.remove('show');
      }
    });
  });
}

function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('show');
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('show');
  }
}

/**
 * Bộ lọc và tìm kiếm nhanh bảng dữ liệu phía Client
 * @param {string} inputId - ID của ô input tìm kiếm
 * @param {string} tableId - ID của bảng dữ liệu
 */
function initTableSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;

  input.addEventListener('input', () => {
    const query = input.value.trim().toLowerCase();
    const rows = table.querySelectorAll('tbody tr');

    rows.forEach((row) => {
      const text = row.textContent.toLowerCase();
      if (text.includes(query)) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  });
}

/**
 * Escape chuỗi HTML an toàn trong JavaScript
 */
function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}
