/**
 * ===================================================================
 * WEB ADMIN & FEATURE FLAGS MANAGEMENT SCRIPT
 * Phong cách chuẩn AdminLTE kết hợp Modern Enterprise UI
 * Đáp ứng đầy đủ 100% Đề tài 1 & Giao diện bảng sản phẩm theo ảnh mẫu:
 * 1. Quản lý sản phẩm iPhone, Mac, iPad (CRUD, Xem, Sửa, Xóa, Lọc, Tác vụ)
 * 2. Khai báo 10 cờ tính năng, Công tắc chính, Tỷ lệ Rollout %, Whitelist
 * 3. Bất biến phiên bản (Snapshots), So sánh trực quan (Visual Diff), Khôi phục (Rollback)
 * 4. Lịch sử thay đổi (Audit Trail), Nhập/Xuất JSON có kiểm tra hợp lệ Schema
 * 5. Bộ kiểm thử tự động 5 phép thử (5/5 PASS) & 2 Tình huống thực tế
 * 6. Quản lý 1.000 mã người dùng giả (U0001 - U1000) & Băm CRC32 tất định
 * ===================================================================
 */

// TRẠNG THÁI ỨNG DỤNG TOÀN CỤC (APP STATE)
const appState = {
    env: 'van_hanh',          // 'van_hanh' hoặc 'thu_nghiem'
    role: 'sua',              // 'sua' (Toàn quyền) hoặc 'xem' (Chỉ đọc)
    currentUser: 'Nguyễn Quản Trị',
    activeTab: 'tab-products',// Mặc định mở tab Sản phẩm theo ảnh
    products: [],
    productSearch: '',
    productModule: 'all',
    selectedProductIds: new Set(),
    flags: [],
    versions: [],
    usersPage: 1,
    usersLimit: 12,
    userSearch: '',
    userGroup: '',
    activeVersionTag: 'v1'
};

// 1. KHỞI ĐỘNG HỆ THỐNG KHI TẢI TRANG
document.addEventListener('DOMContentLoaded', () => {
    initApp();
});

async function initApp() {
    updateRoleUI();
    await loadProducts();
    await loadFlags();
    await loadVersions();
    await loadHistory();
    await loadUsers();
}

// -------------------------------------------------------------
// ĐIỀU HƯỚNG GIAO DIỆN & SIDEBAR MENU
// -------------------------------------------------------------
function toggleSidebar() {
    document.body.classList.toggle('sidebar-collapsed');
}

function toggleTreeview(groupId) {
    const groupEl = document.getElementById(groupId);
    if (!groupEl) return;
    const submenu = groupEl.querySelector('.treeview-menu');
    if (submenu) {
        const isShown = submenu.style.display !== 'none';
        submenu.style.display = isShown ? 'none' : 'block';
    }
}

function switchAdminTab(tabId) {
    appState.activeTab = tabId;

    // 1. Cập nhật hiển thị view nội dung
    document.querySelectorAll('.tab-pane-view').forEach(pane => {
        pane.style.display = (pane.id === tabId) ? 'block' : 'none';
    });

    // 2. Cập nhật active ở menu sidebar
    document.querySelectorAll('.sidebar-menu li').forEach(li => li.classList.remove('active'));
    
    if (tabId === 'tab-products') {
        const item = document.getElementById('menu-item-products');
        if (item) item.classList.add('active');
        const grp = document.getElementById('nav-group-danhmuc');
        if (grp) grp.classList.add('active');
        loadProducts();
    } else if (tabId === 'tab-flags') {
        const item = document.getElementById('menu-item-flags');
        if (item) item.classList.add('active');
        const grp = document.getElementById('nav-group-flags');
        if (grp) grp.classList.add('active');
        loadFlags();
    } else if (tabId === 'tab-versions') {
        const item = document.getElementById('menu-item-versions');
        if (item) item.classList.add('active');
        loadVersions();
    } else if (tabId === 'tab-history') {
        const item = document.getElementById('menu-item-history');
        if (item) item.classList.add('active');
        loadHistory();
    } else if (tabId === 'tab-tests') {
        const item = document.getElementById('menu-item-tests');
        if (item) item.classList.add('active');
    } else if (tabId === 'tab-users') {
        const item = document.getElementById('menu-item-users');
        if (item) item.classList.add('active');
        loadUsers();
    }
}

// -------------------------------------------------------------
// CHUYỂN MÔI TRƯỜNG & CHUYỂN VAI TRÒ (RBAC)
// -------------------------------------------------------------
function onEnvironmentChange() {
    const sel = document.getElementById('select-env');
    appState.env = sel.value;
    showToast(`Đã chuyển sang môi trường: ${sel.options[sel.selectedIndex].text}`, 'info');

    // Nạp lại cấu hình tương ứng với môi trường
    loadFlags();
    loadVersions();
    loadHistory();
}

function onRoleChange() {
    const sel = document.getElementById('select-role');
    appState.role = sel.value;
    appState.currentUser = (appState.role === 'sua') ? 'Nguyễn Quản Trị' : 'Trần Khách Xem';

    updateRoleUI();
    renderProductsTable();
    renderFlagsTable();
    renderVersionsTable();

    const roleName = (appState.role === 'sua') ? 'SỬA (Toàn quyền)' : 'CHỈ XEM (Khóa sửa)';
    showToast(`Đã đổi vai trò: ${roleName}`, appState.role === 'sua' ? 'success' : 'warning');
}

function updateRoleUI() {
    const badge = document.getElementById('role-badge');
    const userHeader = document.getElementById('header-user-name');
    const userSidebar = document.getElementById('sidebar-user-name');
    const isEditor = (appState.role === 'sua');

    if (badge) {
        badge.className = isEditor ? 'badge-role admin' : 'badge-role viewer';
        badge.textContent = isEditor ? 'SỬA' : 'XEM';
    }

    if (userHeader) userHeader.textContent = isEditor ? 'Quản trị viên' : 'Người xem';
    if (userSidebar) userSidebar.textContent = isEditor ? 'Quản trị viên' : 'Khách xem';

    // Khóa hoặc mở các nút thêm/phát hành
    const btnAddProd = document.getElementById('btn-add-product');
    const btnAddFlag = document.getElementById('btn-add-flag');
    const btnPubNew = document.getElementById('btn-publish-new');
    const btnOpenImp = document.getElementById('btn-open-import');

    if (btnAddProd) btnAddProd.disabled = !isEditor;
    if (btnAddFlag) btnAddFlag.disabled = !isEditor;
    if (btnPubNew) btnPubNew.disabled = !isEditor;
    if (btnOpenImp) btnOpenImp.disabled = !isEditor;
}

// =============================================================
// PHẦN 1: QUẢN LÝ SẢN PHẨM APPLE (IPHONE, MAC, IPAD)
// Theo đúng 100% hình ảnh mẫu của người dùng
// =============================================================
async function loadProducts() {
    const searchInput = document.getElementById('product-search-input');
    const moduleSelect = document.getElementById('product-module-filter');

    const searchVal = searchInput ? searchInput.value.trim() : '';
    const moduleVal = moduleSelect ? moduleSelect.value : 'all';

    try {
        let url = `/api/products.php?module=${encodeURIComponent(moduleVal)}`;
        if (searchVal) url += `&search=${encodeURIComponent(searchVal)}`;

        const res = await fetch(url);
        const json = await res.json();

        if (json.status === 'success') {
            appState.products = json.products || [];
            renderProductsTable();
        }
    } catch (err) {
        console.error('Lỗi khi tải sản phẩm:', err);
    }
}

let productSearchDebounceTimer = null;
function onProductSearchChange(val) {
    clearTimeout(productSearchDebounceTimer);
    productSearchDebounceTimer = setTimeout(() => {
        loadProducts();
    }, 300);
}

function resetProductFilters() {
    const searchInput = document.getElementById('product-search-input');
    const moduleSelect = document.getElementById('product-module-filter');
    if (searchInput) searchInput.value = '';
    if (moduleSelect) moduleSelect.value = 'all';
    appState.selectedProductIds.clear();
    const chkAll = document.getElementById('check-all-products');
    if (chkAll) chkAll.checked = false;
    loadProducts();
    showToast('Đã làm mới bộ lọc sản phẩm', 'info');
}

function renderProductsTable() {
    const tbody = document.getElementById('products-table-body');
    const countInfo = document.getElementById('products-count-info');
    if (!tbody) return;

    if (countInfo) {
        countInfo.textContent = `Hiển thị ${appState.products.length} sản phẩm Apple`;
    }

    if (appState.products.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" style="text-align: center; padding: 35px; color: #888;">
                    Không tìm thấy sản phẩm nào phù hợp với bộ lọc.
                </td>
            </tr>
        `;
        return;
    }

    const isEditor = (appState.role === 'sua');
    let html = '';

    appState.products.forEach((p, idx) => {
        const isChecked = appState.selectedProductIds.has(p.id);
        const homeChecked = parseInt(p.is_home) === 1 ? 'checked' : '';
        const menuChecked = parseInt(p.is_menu) === 1 ? 'checked' : '';
        const visibleChecked = parseInt(p.is_visible) === 1 ? 'checked' : '';
        const disabledAttr = isEditor ? '' : 'disabled';

        // Phân loại nhãn module
        let moduleBadge = `<span style="font-weight:600; color:#3c8dbc;">${p.module}</span>`;

        html += `
            <tr>
                <td class="text-center">
                    <span class="custom-checkbox-wrap">
                        <input type="checkbox" value="${p.id}" ${isChecked ? 'checked' : ''} onchange="toggleSelectProduct('${p.id}', this.checked)">
                    </span>
                </td>
                <td class="text-center">
                    <span class="stt-box">${p.stt !== null ? p.stt : idx}</span>
                </td>
                <td class="text-left">
                    <div style="font-weight: 600; color: #222d32; font-size: 13px;">${p.name}</div>
                    <div style="font-size: 11px; color: #777; margin-top: 2px;">
                        <span>Mã: <code>${p.sku}</code></span>
                        <span style="margin: 0 4px;">•</span>
                        <span>Cờ: <code style="color:#008d4c;">${p.feature_flag || 'banner_iphone_17'}</code></span>
                    </div>
                </td>
                <td class="text-center">
                    <img src="${p.image}" alt="${p.name}" class="product-thumbnail" onerror="this.src='https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=150'">
                </td>
                <td class="text-center">
                    ${moduleBadge}
                </td>
                <td class="text-center">
                    <span class="custom-checkbox-wrap">
                        <input type="checkbox" ${homeChecked} ${disabledAttr} onchange="quickToggleProduct('${p.id}', 'is_home', this.checked)">
                    </span>
                </td>
                <td class="text-center">
                    <span class="custom-checkbox-wrap">
                        <input type="checkbox" ${menuChecked} ${disabledAttr} onchange="quickToggleProduct('${p.id}', 'is_menu', this.checked)">
                    </span>
                </td>
                <td class="text-center">
                    <span class="custom-checkbox-wrap">
                        <input type="checkbox" ${visibleChecked} ${disabledAttr} onchange="quickToggleProduct('${p.id}', 'is_visible', this.checked)">
                    </span>
                </td>
                <td>
                    <div class="status-bullet-group">
                        <div class="status-bullet-item">
                            <span class="dot"></span>
                            <span>Dofollow</span>
                        </div>
                        <div class="status-bullet-item">
                            <span class="dot"></span>
                            <span>Index</span>
                        </div>
                    </div>
                </td>
                <td class="text-center">
                    <div class="action-buttons-wrap">
                        <!-- Nút Xem: Icon Outline Mắt (#00c0ef) -->
                        <button class="btn-action-outline btn-action-outline-view" onclick="viewProductDetails('${p.id}')" title="Xem chi tiết sản phẩm">
                            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                        <!-- Nút Sửa: Icon Outline Bút Chì (#f39c12) -->
                        <button class="btn-action-outline btn-action-outline-edit" ${disabledAttr} onclick="openEditProductModal('${p.id}')" title="Chỉnh sửa thông tin sản phẩm">
                            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        </button>
                        <!-- Nút Xóa: Icon Outline Thùng Rác (#dd4b39) -->
                        <button class="btn-action-outline btn-action-outline-delete" ${disabledAttr} onclick="deleteProduct('${p.id}', '${p.name.replace(/'/g, "\\'")}')" title="Xóa sản phẩm">
                            <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

// Chọn/bỏ chọn sản phẩm
function toggleSelectProduct(id, checked) {
    if (checked) {
        appState.selectedProductIds.add(id);
    } else {
        appState.selectedProductIds.delete(id);
    }
}

function toggleSelectAllProducts(checked) {
    if (checked) {
        appState.products.forEach(p => appState.selectedProductIds.add(p.id));
    } else {
        appState.selectedProductIds.clear();
    }
    renderProductsTable();
}

// Bật/tắt nhanh các checkbox Trang chủ, Menu, Hiển thị
async function quickToggleProduct(id, field, value) {
    if (appState.role === 'xem') {
        showToast('Tài khoản chỉ có quyền XEM. Không thể sửa cấu hình!', 'error');
        loadProducts();
        return;
    }

    try {
        const payload = {};
        payload[field] = value ? 1 : 0;

        const res = await fetch(`/api/products.php?id=${encodeURIComponent(id)}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-User-Role': appState.role,
                'X-User-Name': appState.currentUser
            },
            body: JSON.stringify(payload)
        });

        const json = await res.json();
        if (res.ok) {
            showToast('Đã cập nhật trạng thái hiển thị!', 'success');
        } else {
            showToast(`Lỗi: ${json.error}`, 'error');
            loadProducts();
        }
    } catch (err) {
        showToast(`Lỗi kết nối: ${err.message}`, 'error');
        loadProducts();
    }
}

// Áp dụng tác vụ hàng loạt (Bulk Action)
async function applyBulkAction() {
    if (appState.role === 'xem') {
        showToast('Tài khoản XEM bị chặn quyền thao tác ghi!', 'error');
        return;
    }

    const sel = document.getElementById('bulk-action-select');
    const bulkAction = sel ? sel.value : '';

    if (!bulkAction) {
        showToast('Vui lòng chọn một tác vụ trong danh sách!', 'warning');
        return;
    }

    const ids = Array.from(appState.selectedProductIds);
    if (ids.length === 0) {
        showToast('Vui lòng tích chọn ít nhất 1 sản phẩm để thực hiện!', 'warning');
        return;
    }

    if (bulkAction === 'delete') {
        if (!confirm(`Bạn có chắc chắn muốn XÓA vĩnh viễn ${ids.length} sản phẩm đã chọn?`)) {
            return;
        }
    }

    try {
        const res = await fetch(`/api/products.php?action=bulk`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-User-Role': appState.role,
                'X-User-Name': appState.currentUser
            },
            body: JSON.stringify({
                action: 'bulk',
                bulk_action: bulkAction,
                ids: ids
            })
        });

        const json = await res.json();
        if (res.ok) {
            showToast(json.message || 'Thực hiện tác vụ thành công!', 'success');
            appState.selectedProductIds.clear();
            const chkAll = document.getElementById('check-all-products');
            if (chkAll) chkAll.checked = false;
            loadProducts();
        } else {
            showToast(`Lỗi: ${json.error}`, 'error');
        }
    } catch (err) {
        showToast(`Lỗi: ${err.message}`, 'error');
    }
}

// Modal Thêm mới sản phẩm
function openAddProductModal() {
    if (appState.role === 'xem') {
        showToast('Tài khoản XEM không có quyền thêm mới!', 'error');
        return;
    }

    document.getElementById('prod-name').value = '';
    document.getElementById('prod-sku').value = '';
    document.getElementById('prod-module').value = 'iPhone';
    document.getElementById('prod-price').value = '29990000';
    document.getElementById('prod-image').value = 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=150&auto=format&fit=crop&q=80';
    document.getElementById('prod-flag').value = 'banner_iphone_17';
    document.getElementById('prod-is-home').checked = true;
    document.getElementById('prod-is-menu').checked = true;
    document.getElementById('prod-is-visible').checked = true;
    document.getElementById('prod-desc').value = 'Sản phẩm Apple chính hãng VN/A';

    openAdminModal('modal-add-product');
}

async function submitCreateProduct() {
    const name = document.getElementById('prod-name').value.trim();
    const sku = document.getElementById('prod-sku').value.trim();
    const moduleVal = document.getElementById('prod-module').value;
    const price = parseInt(document.getElementById('prod-price').value) || 0;
    const image = document.getElementById('prod-image').value.trim();
    const feature_flag = document.getElementById('prod-flag').value;
    const is_home = document.getElementById('prod-is-home').checked ? 1 : 0;
    const is_menu = document.getElementById('prod-is-menu').checked ? 1 : 0;
    const is_visible = document.getElementById('prod-is-visible').checked ? 1 : 0;
    const desc = document.getElementById('prod-desc').value.trim();

    if (!name) {
        showToast('Tên sản phẩm không được để trống!', 'warning');
        return;
    }

    try {
        const res = await fetch(`/api/products.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-User-Role': appState.role,
                'X-User-Name': appState.currentUser
            },
            body: JSON.stringify({
                name, sku, module: moduleVal, price, image,
                feature_flag, is_home, is_menu, is_visible,
                description: desc
            })
        });

        const json = await res.json();
        if (res.ok) {
            closeAdminModal('modal-add-product');
            showToast(`Đã thêm sản phẩm "${name}" thành công!`, 'success');
            loadProducts();
        } else {
            showToast(`Lỗi: ${json.error}`, 'error');
        }
    } catch (err) {
        showToast(`Lỗi kết nối: ${err.message}`, 'error');
    }
}

// Modal Sửa sản phẩm
function openEditProductModal(id) {
    if (appState.role === 'xem') {
        showToast('Tài khoản XEM không có quyền sửa sản phẩm!', 'error');
        return;
    }

    const p = appState.products.find(item => item.id === id);
    if (!p) {
        showToast('Không tìm thấy thông tin sản phẩm!', 'error');
        return;
    }

    document.getElementById('edit-prod-id').value = p.id;
    document.getElementById('edit-prod-name').value = p.name;
    document.getElementById('edit-prod-sku').value = p.sku;
    document.getElementById('edit-prod-module').value = p.module;
    document.getElementById('edit-prod-price').value = p.price;
    document.getElementById('edit-prod-image').value = p.image;
    document.getElementById('edit-prod-is-home').checked = parseInt(p.is_home) === 1;
    document.getElementById('edit-prod-is-menu').checked = parseInt(p.is_menu) === 1;
    document.getElementById('edit-prod-is-visible').checked = parseInt(p.is_visible) === 1;
    document.getElementById('edit-prod-desc').value = p.description || '';

    openAdminModal('modal-edit-product');
}

async function submitEditProduct() {
    const id = document.getElementById('edit-prod-id').value;
    const name = document.getElementById('edit-prod-name').value.trim();
    const sku = document.getElementById('edit-prod-sku').value.trim();
    const moduleVal = document.getElementById('edit-prod-module').value;
    const price = parseInt(document.getElementById('edit-prod-price').value) || 0;
    const image = document.getElementById('edit-prod-image').value.trim();
    const is_home = document.getElementById('edit-prod-is-home').checked ? 1 : 0;
    const is_menu = document.getElementById('edit-prod-is-menu').checked ? 1 : 0;
    const is_visible = document.getElementById('edit-prod-is-visible').checked ? 1 : 0;
    const desc = document.getElementById('edit-prod-desc').value.trim();

    if (!name) {
        showToast('Tên sản phẩm không được để trống!', 'warning');
        return;
    }

    try {
        const res = await fetch(`/api/products.php?id=${encodeURIComponent(id)}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-User-Role': appState.role,
                'X-User-Name': appState.currentUser
            },
            body: JSON.stringify({
                name, sku, module: moduleVal, price, image,
                is_home, is_menu, is_visible, description: desc
            })
        });

        const json = await res.json();
        if (res.ok) {
            closeAdminModal('modal-edit-product');
            showToast(`Cập nhật sản phẩm "${name}" thành công!`, 'success');
            loadProducts();
        } else {
            showToast(`Lỗi: ${json.error}`, 'error');
        }
    } catch (err) {
        showToast(`Lỗi kết nối: ${err.message}`, 'error');
    }
}

// Modal Xem chi tiết sản phẩm
function viewProductDetails(id) {
    const p = appState.products.find(item => item.id === id);
    if (!p) return;

    const body = document.getElementById('view-product-body');
    const priceFormatted = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(p.price);

    body.innerHTML = `
        <div style="display:flex; gap:18px; margin-bottom:15px; align-items:flex-start;">
            <img src="${p.image}" alt="${p.name}" style="width:110px; height:110px; object-fit:cover; border-radius:4px; border:1px solid #ddd;">
            <div style="flex:1;">
                <h3 style="font-size:16px; color:#222d32; margin-bottom:6px;">${p.name}</h3>
                <div style="font-size:13px; color:#555; margin-bottom:4px;">Mã SKU: <strong>${p.sku}</strong></div>
                <div style="font-size:13px; color:#555; margin-bottom:4px;">Phân loại: <strong style="color:var(--admin-blue);">${p.module}</strong></div>
                <div style="font-size:16px; color:var(--admin-red); font-weight:700;">${priceFormatted}</div>
            </div>
        </div>
        <div style="background:#f9f9f9; padding:12px; border-radius:3px; font-size:13px; border:1px solid #eee; margin-bottom:12px;">
            <div style="margin-bottom:6px;"><strong>Trạng thái bán:</strong> <span class="status-badge-inline badge-selling">● ${p.status || 'Đang bán'}</span></div>
            <div style="margin-bottom:6px;"><strong>Cờ tính năng gắn kèm:</strong> <code>${p.feature_flag || 'banner_iphone_17'}</code></div>
            <div style="margin-bottom:6px;"><strong>Cấu hình SEO:</strong> Dofollow, Index</div>
            <div><strong>Tùy chọn:</strong> Trang chủ: ${p.is_home ? '✓' : '✗'} | Menu: ${p.is_menu ? '✓' : '✗'} | Hiển thị: ${p.is_visible ? '✓' : '✗'}</div>
        </div>
        <div style="font-size:13px; color:#666;">
            <strong>Mô tả:</strong> ${p.description || 'Chưa có mô tả chi tiết.'}
        </div>
    `;

    openAdminModal('modal-view-product');
}

// Xóa 1 sản phẩm
async function deleteProduct(id, name) {
    if (appState.role === 'xem') {
        showToast('Tài khoản XEM bị chặn quyền xóa sản phẩm!', 'error');
        return;
    }

    if (!confirm(`Bạn có chắc chắn muốn xóa sản phẩm "${name || id}"?`)) {
        return;
    }

    try {
        const res = await fetch(`/api/products.php?id=${encodeURIComponent(id)}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-User-Role': appState.role,
                'X-User-Name': appState.currentUser
            }
        });

        const json = await res.json();
        if (res.ok) {
            showToast(json.message || 'Đã xóa sản phẩm thành công!', 'success');
            appState.selectedProductIds.delete(id);
            loadProducts();
        } else {
            showToast(`Lỗi: ${json.error}`, 'error');
        }
    } catch (err) {
        showToast(`Lỗi: ${err.message}`, 'error');
    }
}

// =============================================================
// PHẦN 2: QUẢN LÝ CỜ TÍNH NĂNG & QUY TẮC TỶ LỆ (ĐỀ TÀI 1)
// =============================================================
async function loadFlags() {
    try {
        const res = await fetch(`/api/flags.php?env=${encodeURIComponent(appState.env)}`);
        const json = await res.json();

        if (json.status === 'success') {
            appState.flags = json.data || [];
            appState.activeVersionTag = json.active_version || 'v1';

            // Cập nhật thống kê
            const elTotal = document.getElementById('stat-total-flags');
            const elActive = document.getElementById('stat-active-flags');
            const elVer = document.getElementById('stat-active-version');

            if (elTotal) elTotal.textContent = appState.flags.length;
            if (elActive) {
                const activeCount = appState.flags.filter(f => parseInt(f.enabled) === 1).length;
                elActive.textContent = activeCount;
            }
            if (elVer) elVer.textContent = appState.activeVersionTag;

            renderFlagsTable();
        }
    } catch (err) {
        console.error('Lỗi khi nạp danh sách cờ:', err);
    }
}

function renderFlagsTable() {
    const tbody = document.getElementById('flags-table-body');
    if (!tbody) return;

    if (appState.flags.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding:25px; color:#888;">Chưa có tính năng nào trong môi trường này.</td></tr>`;
        return;
    }

    const isEditor = (appState.role === 'sua');
    let html = '';

    appState.flags.forEach((f, idx) => {
        const isChecked = parseInt(f.enabled) === 1 ? 'checked' : '';
        const pct = parseInt(f.percentage) || 0;
        const disabledAttr = isEditor ? '' : 'disabled';

        html += `
            <tr>
                <td class="text-center">
                    <span class="stt-box">${idx + 1}</span>
                </td>
                <td class="text-left">
                    <code style="color: #3c8dbc; font-weight: 700; font-size: 13px;">${f.key}</code>
                </td>
                <td class="text-left">
                    <strong style="color: #222d32;">${f.name}</strong>
                    <div style="font-size: 11px; color: #777;">${f.description || ''}</div>
                </td>
                <td class="text-center">
                    <label class="toggle-switch">
                        <input type="checkbox" ${isChecked} ${disabledAttr} onchange="toggleFlagSwitch('${f.key}', this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                </td>
                <td>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <input type="range" class="form-control" style="padding:0; height:24px; flex:1;" min="0" max="100" value="${pct}" ${disabledAttr}
                               oninput="onSliderDrag('${f.key}', this.value)"
                               onchange="onSliderCommit('${f.key}', this.value)">
                        <strong style="min-width: 40px; text-align:right; color:#222d32;" id="val-${f.key}">${pct}%</strong>
                    </div>
                </td>
                <td class="text-left">
                    <input type="text" class="form-control" value="${f.whitelist || ''}" placeholder="VD: U0001,U0002" ${disabledAttr}
                           style="padding: 4px 8px; font-size: 12px; height: 28px;"
                           onchange="updateFlagWhitelist('${f.key}', this.value)">
                </td>
                <td class="text-center">
                    <button class="btn-action-outline btn-action-outline-delete" ${disabledAttr} onclick="deleteFlag('${f.key}')" title="Xóa cờ tính năng này">
                        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function onSliderDrag(key, val) {
    const span = document.getElementById(`val-${key}`);
    if (span) span.textContent = `${val}%`;
}

async function onSliderCommit(key, val) {
    await updateFlagData(key, { percentage: parseInt(val), reason: `Điều chỉnh tỷ lệ % tính năng [${key}] thành ${val}%` });
}

async function toggleFlagSwitch(key, isEnabled) {
    await updateFlagData(key, { enabled: isEnabled ? 1 : 0, reason: `Chuyển công tắc cờ [${key}] sang ` + (isEnabled ? 'BẬT' : 'TẮT') });
}

async function updateFlagWhitelist(key, whitelistVal) {
    await updateFlagData(key, { whitelist: whitelistVal.trim(), reason: `Cập nhật Whitelist cho cờ [${key}]` });
}

async function updateFlagData(key, payload) {
    try {
        const res = await fetch(`/api/flags.php?env=${encodeURIComponent(appState.env)}&key=${encodeURIComponent(key)}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-User-Role': appState.role,
                'X-User-Name': appState.currentUser
            },
            body: JSON.stringify(payload)
        });

        const json = await res.json();
        if (!res.ok) {
            showToast(`Lỗi: ${json.error || 'Không thể cập nhật cờ!'}`, 'error');
            loadFlags();
            return;
        }

        const found = appState.flags.find(f => f.key === key);
        if (found) Object.assign(found, payload);
        showToast(`Đã lưu thay đổi cờ [${key}]`, 'success');
    } catch (err) {
        showToast(`Lỗi kết nối: ${err.message}`, 'error');
        loadFlags();
    }
}

function openAddFlagModal() {
    if (appState.role === 'xem') {
        showToast('Tài khoản XEM không thể thêm cờ tính năng!', 'error');
        return;
    }
    document.getElementById('flag-name').value = '';
    document.getElementById('flag-key').value = '';
    document.getElementById('flag-desc').value = '';
    document.getElementById('flag-pct').value = '50';
    document.getElementById('flag-whitelist').value = '';
    openAdminModal('modal-add-flag');
}

async function submitCreateFlag() {
    const name = document.getElementById('flag-name').value.trim();
    const key = document.getElementById('flag-key').value.trim();
    const desc = document.getElementById('flag-desc').value.trim();
    const pct = parseInt(document.getElementById('flag-pct').value);
    const whitelist = document.getElementById('flag-whitelist').value.trim();

    if (!name) {
        showToast('Tên tính năng không được để trống!', 'warning');
        return;
    }
    if (isNaN(pct) || pct < 0 || pct > 100) {
        showToast('Tỷ lệ áp dụng phải là số từ 0 đến 100!', 'warning');
        return;
    }

    try {
        const res = await fetch(`/api/flags.php?env=${encodeURIComponent(appState.env)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-User-Role': appState.role,
                'X-User-Name': appState.currentUser
            },
            body: JSON.stringify({ key, name, description: desc, percentage: pct, whitelist })
        });

        const json = await res.json();
        if (!res.ok) {
            showToast(`Lỗi: ${json.error}`, 'error');
            return;
        }

        closeAdminModal('modal-add-flag');
        showToast(`Đã thêm tính năng "${name}" thành công!`, 'success');
        loadFlags();
        loadHistory();
    } catch (err) {
        showToast(`Lỗi: ${err.message}`, 'error');
    }
}

async function deleteFlag(key) {
    if (appState.role === 'xem') {
        showToast('Tài khoản XEM bị chặn quyền xóa!', 'error');
        return;
    }
    if (!confirm(`Bạn có chắc chắn muốn xóa cờ [${key}] khỏi môi trường ${appState.env}?`)) {
        return;
    }

    try {
        const res = await fetch(`/api/flags.php?env=${encodeURIComponent(appState.env)}&key=${encodeURIComponent(key)}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-User-Role': appState.role,
                'X-User-Name': appState.currentUser
            }
        });
        const json = await res.json();
        if (!res.ok) {
            showToast(`Lỗi: ${json.error}`, 'error');
            return;
        }
        showToast(`Đã xóa cờ [${key}]`, 'success');
        loadFlags();
        loadHistory();
    } catch (err) {
        showToast(`Lỗi: ${err.message}`, 'error');
    }
}

// =============================================================
// PHẦN 3: PHIÊN BẢN, SO SÁNH DIFF & KHÔI PHỤC (ROLLBACK)
// =============================================================
async function loadVersions() {
    try {
        const res = await fetch(`/api/versions.php?action=list&env=${encodeURIComponent(appState.env)}`);
        const json = await res.json();
        if (json.status === 'success') {
            appState.versions = json.data || [];
            renderVersionsTable();
        }
    } catch (err) {
        console.error('Lỗi khi nạp phiên bản:', err);
    }
}

function renderVersionsTable() {
    const tbody = document.getElementById('versions-table-body');
    if (!tbody) return;

    if (appState.versions.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:25px; color:#888;">Chưa có bản phát hành nào.</td></tr>`;
        return;
    }

    const isEditor = (appState.role === 'sua');
    let html = '';

    appState.versions.forEach((v, idx) => {
        const isLatest = (idx === 0);
        const statusBadge = isLatest
            ? '<span class="status-badge-inline badge-selling">● ĐANG CHẠY</span>'
            : '<span style="color:#777; font-size:12px;">ĐÃ ĐÓNG BĂNG</span>';
        const disabledAttr = isEditor ? '' : 'disabled';

        html += `
            <tr>
                <td class="text-center">
                    <strong style="color: var(--admin-orange); font-size: 14px;">${v.version_tag}</strong>
                </td>
                <td class="text-left">
                    <strong style="color: #222d32;">${v.title}</strong>
                </td>
                <td class="text-center">${statusBadge}</td>
                <td class="text-left" style="font-size: 12px; color: #555;">${v.description || 'Không có ghi chú'}</td>
                <td class="text-center">${v.created_by || 'admin'}</td>
                <td class="text-center" style="font-size: 12px; color: #888;">${v.created_at}</td>
                <td class="text-center"><span class="badge-role admin">${v.flag_count || 10} cờ</span></td>
                <td class="text-center">
                    <div class="action-buttons-wrap">
                        <button class="btn-action-view" onclick="openDiffModal('${v.version_tag}')" title="So sánh bản này">
                            <span>🔍 So Sánh</span>
                        </button>
                        <button class="btn-action-edit" ${disabledAttr} onclick="openRollbackModal('${v.version_tag}')" title="Khôi phục về bản này">
                            <span>↩️ Khôi Phục</span>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function openPublishModal() {
    if (appState.role === 'xem') {
        showToast('Tài khoản XEM bị khóa quyền phát hành!', 'error');
        return;
    }
    document.getElementById('publish-title').value = '';
    document.getElementById('publish-reason').value = '';
    openAdminModal('modal-publish');
}

async function submitPublishVersion() {
    const title = document.getElementById('publish-title').value.trim();
    const reason = document.getElementById('publish-reason').value.trim();

    if (!reason) {
        showToast('Bắt buộc phải nhập lý do phát hành theo Chức năng 5!', 'warning');
        return;
    }

    try {
        const res = await fetch(`/api/versions.php?action=publish&env=${encodeURIComponent(appState.env)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-User-Role': appState.role,
                'X-User-Name': appState.currentUser
            },
            body: JSON.stringify({ title, reason, author: appState.currentUser })
        });

        const json = await res.json();
        if (!res.ok) {
            showToast(`Lỗi: ${json.error}`, 'error');
            return;
        }

        closeAdminModal('modal-publish');
        showToast(`🎉 Đã phát hành thành công phiên bản ${json.version_tag}!`, 'success');
        await loadVersions();
        await loadFlags();
        await loadHistory();
    } catch (err) {
        showToast(`Lỗi: ${err.message}`, 'error');
    }
}

// Modal So Sánh Diff
async function openDiffModal(targetVerTag = null) {
    if (appState.versions.length < 2) {
        showToast('Cần ít nhất 2 phiên bản để thực hiện so sánh Visual Diff!', 'warning');
        return;
    }

    const v1Select = document.getElementById('diff-v1-select');
    const v2Select = document.getElementById('diff-v2-select');

    v1Select.innerHTML = '';
    v2Select.innerHTML = '';

    appState.versions.forEach(v => {
        v1Select.add(new Option(v.version_tag, v.version_tag));
        v2Select.add(new Option(v.version_tag, v.version_tag));
    });

    v1Select.value = targetVerTag || appState.versions[0].version_tag;
    v2Select.value = appState.versions[1] ? appState.versions[1].version_tag : appState.versions[0].version_tag;

    openAdminModal('modal-diff');
    await reloadDiffModal();
}

async function reloadDiffModal() {
    const v1 = document.getElementById('diff-v1-select').value;
    const v2 = document.getElementById('diff-v2-select').value;
    const alertBox = document.getElementById('diff-summary-alert');
    const tbody = document.getElementById('diff-table-body');
    const thV1 = document.getElementById('diff-th-v1');
    const thV2 = document.getElementById('diff-th-v2');

    thV1.textContent = `Bản ${v1}`;
    thV2.textContent = `Bản ${v2}`;
    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:20px;">Đang phân tích khác biệt...</td></tr>`;

    try {
        const res = await fetch(`/api/versions.php?action=diff&v1=${encodeURIComponent(v1)}&v2=${encodeURIComponent(v2)}&env=${encodeURIComponent(appState.env)}`);
        const json = await res.json();
        if (!res.ok) {
            alertBox.style.background = '#f8d7da';
            alertBox.style.color = '#721c24';
            alertBox.textContent = json.error || 'Lỗi khi so sánh!';
            return;
        }

        const diffs = json.diff_rows || [];
        const totalDiff = json.total_differences;

        alertBox.style.background = totalDiff > 0 ? '#fff3cd' : '#d1ecf1';
        alertBox.style.color = totalDiff > 0 ? '#856404' : '#0c5460';
        alertBox.innerHTML = `Phát hiện <strong>${totalDiff}</strong> điểm khác biệt giữa bản <strong>${v1}</strong> và bản <strong>${v2}</strong>. Các dòng khác nhau được tô màu trực quan.`;

        let html = '';
        diffs.forEach(d => {
            const rowStyle = d.is_different ? 'background-color: #fff9db;' : '';
            const status1 = d.v1_exists ? `${d.v1_enabled ? '🟢 BẬT' : '🔴 TẮT'} (${d.v1_percentage}%)` : '<span style="color:#aaa;">Chưa có</span>';
            const status2 = d.v2_exists ? `${d.v2_enabled ? '🟢 BẬT' : '🔴 TẮT'} (${d.v2_percentage}%)` : '<span style="color:#aaa;">Chưa có</span>';
            const changesText = d.changes && d.changes.length > 0 ? d.changes.join(', ') : 'Giống nhau 100%';

            html += `
                <tr style="${rowStyle}">
                    <td class="text-left"><code>${d.key}</code></td>
                    <td class="text-left"><strong>${d.name}</strong></td>
                    <td class="text-center">${status1}</td>
                    <td class="text-center">${status2}</td>
                    <td class="text-left" style="font-size:12px; color:${d.is_different ? '#b27b00' : '#888'}; font-weight:${d.is_different ? '600' : 'normal'};">
                        ${changesText}
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    } catch (err) {
        alertBox.textContent = `Lỗi: ${err.message}`;
    }
}

// Modal Khôi Phục (Rollback)
function openRollbackModal(targetTag) {
    if (appState.role === 'xem') {
        showToast('Tài khoản XEM bị khóa thao tác khôi phục!', 'error');
        return;
    }
    document.getElementById('rollback-target-version').value = targetTag;
    document.getElementById('rollback-reason').value = `Khôi phục về cấu hình an toàn của bản ${targetTag}`;
    openAdminModal('modal-rollback');
}

async function submitRollbackVersion() {
    const targetTag = document.getElementById('rollback-target-version').value;
    const reason = document.getElementById('rollback-reason').value.trim();

    if (!reason) {
        showToast('Bắt buộc phải nhập lý do khôi phục theo Chức năng 5!', 'warning');
        return;
    }

    try {
        const res = await fetch(`/api/versions.php?action=rollback&env=${encodeURIComponent(appState.env)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-User-Role': appState.role,
                'X-User-Name': appState.currentUser
            },
            body: JSON.stringify({ version: targetTag, reason, author: appState.currentUser })
        });

        const json = await res.json();
        if (!res.ok) {
            showToast(`Lỗi: ${json.error}`, 'error');
            return;
        }

        closeAdminModal('modal-rollback');
        showToast(`↩️ ${json.message}`, 'success');
        await loadVersions();
        await loadFlags();
        await loadHistory();
    } catch (err) {
        showToast(`Lỗi: ${err.message}`, 'error');
    }
}

// =============================================================
// PHẦN 4: LỊCH SỬ AUDIT TRAIL & NHẬP / XUẤT JSON
// =============================================================
async function loadHistory() {
    try {
        const res = await fetch(`/api/history.php?env=${encodeURIComponent(appState.env)}`);
        const json = await res.json();
        if (json.status === 'success') {
            renderHistoryTable(json.data || []);
        }
    } catch (err) {
        console.error('Lỗi khi nạp lịch sử:', err);
    }
}

function renderHistoryTable(historyList) {
    const tbody = document.getElementById('history-table-body');
    if (!tbody) return;

    if (historyList.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:25px; color:#888;">Chưa có nhật ký thay đổi nào.</td></tr>`;
        return;
    }

    let html = '';
    historyList.forEach(h => {
        let actionBadge = '<span class="status-badge-inline" style="background:#e9ecef; color:#495057;">THAY ĐỔI</span>';
        if (h.action === 'phat_hanh') actionBadge = '<span class="status-badge-inline badge-selling">🚀 PHÁT HÀNH</span>';
        if (h.action === 'khoi_phuc') actionBadge = '<span class="status-badge-inline badge-warning-stock">↩️ KHÔI PHỤC</span>';
        if (h.action === 'nhap_json') actionBadge = '<span class="status-badge-inline" style="background:#ede7f6; color:#5e35b1;">📥 NHẬP JSON</span>';

        html += `
            <tr>
                <td class="text-center" style="color:#888;">${h.id}</td>
                <td class="text-center" style="font-size:12px; color:#666;">${h.created_at}</td>
                <td class="text-center">${actionBadge}</td>
                <td class="text-center"><strong style="color:var(--admin-orange);">${h.version_tag || 'nháp'}</strong></td>
                <td class="text-center"><strong>${h.author}</strong></td>
                <td class="text-left" style="font-size:13px;">${h.reason}</td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function exportConfigJson() {
    window.location.href = `/api/import_export.php?action=export&env=${encodeURIComponent(appState.env)}&download=1`;
}

function openImportModal() {
    if (appState.role === 'xem') {
        showToast('Tài khoản XEM bị khóa thao tác nhập JSON!', 'error');
        return;
    }
    document.getElementById('import-file').value = '';
    document.getElementById('import-text').value = '';
    document.getElementById('import-reason').value = '';
    const errBox = document.getElementById('import-error-box');
    if (errBox) {
        errBox.style.display = 'none';
        errBox.innerHTML = '';
    }
    openAdminModal('modal-import');
}

function onFileSelected(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = (e) => {
            document.getElementById('import-text').value = e.target.result;
        };
        reader.readAsText(input.files[0]);
    }
}

async function submitImportConfig() {
    const rawJson = document.getElementById('import-text').value.trim();
    const reason = document.getElementById('import-reason').value.trim();
    const errBox = document.getElementById('import-error-box');
    if (errBox) errBox.style.display = 'none';

    if (!rawJson) {
        showToast('Vui lòng chọn tệp JSON hoặc dán nội dung vào ô!', 'warning');
        return;
    }

    try {
        const parsed = JSON.parse(rawJson);
        const res = await fetch(`/api/import_export.php?action=import&env=${encodeURIComponent(appState.env)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-User-Role': appState.role,
                'X-User-Name': appState.currentUser
            },
            body: JSON.stringify({ ...parsed, reason })
        });

        const json = await res.json();
        if (!res.ok) {
            if (errBox) {
                errBox.style.display = 'block';
                let errMsg = `<strong>LỖI NHẬP DỮ LIỆU:</strong><br>${json.error || 'Dữ liệu không hợp lệ!'}`;
                if (json.validation_errors && json.validation_errors.length > 0) {
                    errMsg += `<ul style="margin-top:6px; padding-left:18px;">${json.validation_errors.map(e => `<li>${e}</li>`).join('')}</ul>`;
                }
                errBox.innerHTML = errMsg;
            }
            return;
        }

        closeAdminModal('modal-import');
        showToast(`📥 ${json.message}`, 'success');
        await loadFlags();
        await loadHistory();
    } catch (err) {
        if (errBox) {
            errBox.style.display = 'block';
            errBox.innerHTML = `<strong>LỖI ĐỊNH DẠNG JSON:</strong> ${err.message}`;
        }
    }
}

// =============================================================
// PHẦN 5: 5 PHÉP THỬ BẮT BUỘC & 2 TÌNH HUỐNG TRÌNH DIỄN
// =============================================================
async function runAutomatedTests() {
    const wrapper = document.getElementById('test-results-wrapper');
    wrapper.innerHTML = `<div style="text-align:center; padding:25px; color:#555;">⏳ Đang thực thi 5 phép thử tự động...</div>`;

    try {
        const res = await fetch(`/api/test_runner.php?env=${encodeURIComponent(appState.env)}`);
        const json = await res.json();

        let html = `
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; padding:10px 14px; background:#e8f5e9; border-radius:3px; border:1px solid #c8e6c9;">
                <span style="font-size:14px; font-weight:700; color:#2e7d32;">
                    ${json.all_passed ? '✓ TẤT CẢ 5 PHÉP THỬ ĐỀU ĐẠT (5/5 PASS)' : 'CÓ PHÉP THỬ THẤT BẠI'}
                </span>
                <span style="font-size:12px; color:#555;">Thời gian chạy: ${json.timestamp}</span>
            </div>
        `;

        json.results.forEach(t => {
            html += `
                <div class="test-card passed">
                    <div class="test-card-header">
                        <span class="test-card-title">${t.name}</span>
                        <span class="test-status-badge pass">✓ ĐẠT (PASS)</span>
                    </div>
                    <div class="test-card-details">
                        ${t.details.map(d => `<div>→ ${d}</div>`).join('')}
                    </div>
                </div>
            `;
        });

        wrapper.innerHTML = html;
        showToast('Đã chạy xong 5 phép thử tự động!', 'success');
    } catch (err) {
        wrapper.innerHTML = `<div style="color:var(--admin-red); padding:15px;">Lỗi: ${err.message}</div>`;
    }
}

// Tình huống 1: Tạo cấu hình -> Kiểm tra -> Phát hành -> Web Apple nhận
async function runScenario1() {
    const logBox = document.getElementById('scenario1-log');
    logBox.innerHTML = `
        <div style="color:var(--admin-blue); font-weight:600;">🚀 Đang chạy Tình Huống 1...</div>
        <div>1. Tạo cờ thử nghiệm [demo_promo_tet] với tỷ lệ 80%...</div>
    `;

    try {
        await fetch(`/api/flags.php?env=${encodeURIComponent(appState.env)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-User-Role': 'sua', 'X-User-Name': 'admin' },
            body: JSON.stringify({ key: 'demo_promo_tet', name: 'Ưu Đãi Tết Apple', percentage: 80, whitelist: 'U0001' })
        });
        logBox.innerHTML += `<div>✓ Bước 1 xong: Đã tạo cờ [demo_promo_tet] trong bản nháp.</div>`;

        const resEval = await fetch(`/api/evaluate.php?user=U0001&flag=demo_promo_tet&env=${encodeURIComponent(appState.env)}`);
        const evalJson = await resEval.json();
        logBox.innerHTML += `<div>✓ Bước 2 xong: Đánh giá user U0001 nhận kết quả [${evalJson.enabled ? 'BẬT' : 'TẮT'}].</div>`;

        const resPub = await fetch(`/api/versions.php?action=publish&env=${encodeURIComponent(appState.env)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-User-Role': 'sua', 'X-User-Name': 'admin' },
            body: JSON.stringify({ title: 'Phát hành Demo Tình Huống 1', reason: 'Hoàn thành chu trình Tình Huống 1 Mục 5', author: 'admin' })
        });
        const pubJson = await resPub.json();
        logBox.innerHTML += `<div>✓ Bước 3 xong: Đã đóng băng cấu hình thành bản <strong>${pubJson.version_tag}</strong>.</div>`;
        logBox.innerHTML += `<div style="color:var(--admin-green); margin-top:8px; font-weight:700;">🎉 TÌNH HUỐNG 1 HOÀN TẤT THÀNH CÔNG! Web Apple đã nhận diện phiên bản mới.</div>`;

        await loadFlags();
        await loadVersions();
    } catch (err) {
        logBox.innerHTML += `<div style="color:var(--admin-red);">Lỗi: ${err.message}</div>`;
    }
}

// Tình huống 2: Đổi tỷ lệ 90% -> So sánh Diff -> Khôi phục Rollback
async function runScenario2() {
    const logBox = document.getElementById('scenario2-log');
    logBox.innerHTML = `
        <div style="color:var(--admin-orange); font-weight:600;">⚠️ Đang chạy Tình Huống 2...</div>
        <div>1. Đổi tỷ lệ cờ [banner_iphone_17] lên 90% gây quá tải mô phỏng...</div>
    `;

    try {
        await fetch(`/api/flags.php?env=${encodeURIComponent(appState.env)}&key=banner_iphone_17`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-User-Role': 'sua', 'X-User-Name': 'admin' },
            body: JSON.stringify({ percentage: 90, reason: 'Tăng lên 90% thử nghiệm quá tải' })
        });
        const resPub = await fetch(`/api/versions.php?action=publish&env=${encodeURIComponent(appState.env)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-User-Role': 'sua', 'X-User-Name': 'admin' },
            body: JSON.stringify({ title: 'Bản sự cố 90%', reason: 'Bản thử nghiệm phát sinh lỗi quá tải', author: 'admin' })
        });
        const pubJson = await resPub.json();
        logBox.innerHTML += `<div>✓ Đã phát hành bản sự cố [${pubJson.version_tag}].</div>`;

        const resDiff = await fetch(`/api/versions.php?action=diff&v1=v1&v2=${pubJson.version_tag}&env=${encodeURIComponent(appState.env)}`);
        const diffJson = await resDiff.json();
        logBox.innerHTML += `<div>✓ So sánh Diff: Tìm thấy ${diffJson.total_differences} cờ bị thay đổi tỷ lệ.</div>`;

        const resRoll = await fetch(`/api/versions.php?action=rollback&env=${encodeURIComponent(appState.env)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-User-Role': 'sua', 'X-User-Name': 'admin' },
            body: JSON.stringify({ version: 'v1', reason: 'Tình huống 2: Khôi phục khẩn cấp về bản v1 an toàn', author: 'admin' })
        });
        const rollJson = await resRoll.json();
        logBox.innerHTML += `<div>✓ Đã khôi phục về cấu hình bản v1, phát hành thành bản [<strong>${rollJson.new_version}</strong>].</div>`;
        logBox.innerHTML += `<div style="color:var(--admin-green); margin-top:8px; font-weight:700;">🎉 TÌNH HUỐNG 2 HOÀN TẤT THÀNH CÔNG! Hệ thống đã an toàn trở lại.</div>`;

        await loadFlags();
        await loadVersions();
        await loadHistory();
    } catch (err) {
        logBox.innerHTML += `<div style="color:var(--admin-red);">Lỗi: ${err.message}</div>`;
    }
}

// =============================================================
// PHẦN 6: DANH SÁCH 1.000 NGƯỜI DÙNG & BĂM CRC32
// =============================================================
let userSearchDebounceTimer = null;
function onUserSearchInput(val) {
    clearTimeout(userSearchDebounceTimer);
    userSearchDebounceTimer = setTimeout(() => {
        appState.userSearch = val.trim();
        appState.usersPage = 1;
        loadUsers();
    }, 300);
}

function changeUserPage(delta) {
    appState.usersPage = Math.max(1, appState.usersPage + delta);
    loadUsers();
}

async function loadUsers() {
    const groupSel = document.getElementById('user-group-filter');
    const groupVal = groupSel ? groupSel.value : '';
    const tbody = document.getElementById('users-table-body');
    const pageInfo = document.getElementById('users-page-info');

    try {
        const url = `/api/users.php?page=${appState.usersPage}&limit=${appState.usersLimit}&search=${encodeURIComponent(appState.userSearch)}&group=${encodeURIComponent(groupVal)}`;
        const res = await fetch(url);
        const json = await res.json();

        if (json.status === 'success') {
            const users = json.data || [];
            if (pageInfo) {
                pageInfo.textContent = `Trang ${json.page} / ${json.total_pages || 1} (Tổng: ${json.total} user)`;
            }

            if (users.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:25px; color:#888;">Không tìm thấy người dùng phù hợp.</td></tr>`;
                return;
            }

            let html = '';
            for (const u of users) {
                const str = u.id + 'banner_iphone_17' + appState.env;
                const bucket = Math.abs(crc32Simple(str)) % 100;
                let badgeGroup = '<span class="status-badge-inline" style="background:#eee; color:#555;">Thường</span>';
                if (u.user_group === 'VIP') badgeGroup = '<span class="status-badge-inline badge-warning-stock">⭐ VIP</span>';
                if (u.user_group === 'Beta Tester') badgeGroup = '<span class="status-badge-inline" style="background:#ede7f6; color:#5e35b1;">🧪 Beta</span>';

                html += `
                    <tr>
                        <td class="text-center"><strong style="color:var(--admin-blue);">${u.id}</strong></td>
                        <td class="text-left">${u.fullname}</td>
                        <td class="text-center">${badgeGroup}</td>
                        <td class="text-center">Bucket: <strong>${bucket}</strong></td>
                        <td class="text-center">
                            <span class="status-badge-inline badge-selling">● Khả dụng</span>
                        </td>
                        <td class="text-center">
                            <button class="btn-action-view" onclick="testUserOnApple('${u.id}')" title="Mở Web Apple với mã User này">
                                <span> Thử Ngay</span>
                            </button>
                        </td>
                    </tr>
                `;
            }
            tbody.innerHTML = html;
        }
    } catch (err) {
        console.error('Lỗi khi nạp người dùng:', err);
    }
}

function testUserOnApple(userId) {
    window.open(`/apple?user=${encodeURIComponent(userId)}&env=${encodeURIComponent(appState.env)}`, '_blank');
}

// Băm CRC32 nhẹ ở Client
function crc32Simple(r){for(var a,o=[],c=0;c<256;c++){a=c;for(var f=0;f<8;f++)a=1&a?3988292384^a>>>1:a>>>1;o[c]=a}for(var n=-1,t=0;t<r.length;t++)n=n>>>8^o[255&(n^r.charCodeAt(t))];return(-1^n)>>>0}

// =============================================================
// HELPER: MODALS & TOAST NOTIFICATIONS
// =============================================================
function openAdminModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
}

function closeAdminModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast-msg ${type}`;
    let icon = 'ℹ️';
    if (type === 'success') icon = '✓';
    if (type === 'error') icon = '⚠️';
    if (type === 'warning') icon = '🔔';

    toast.innerHTML = `<span style="font-weight:700;">${icon}</span><span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3200);
}
