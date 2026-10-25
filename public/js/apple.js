/**
 * ===================================================================
 * APPLE DEMO CLIENT APPLICATION (JQUERY-FREE, CLEAN JAVASCRIPT)
 * ===================================================================
 * Ứng dụng Web Apple gọi API PHP /api/evaluate.php để hỏi trạng thái tính năng
 * Đáp ứng Chức năng 3 & Yêu cầu Kỹ thuật 2 (Cơ chế Fallback):
 * - Khi API chạy bình thường: Bật/Tắt tính năng theo đúng câu trả lời từ API
 * - Khi API lỗi (mô phỏng mất mạng): Tự động bắt lỗi, đọc giá trị dự phòng
 *   (Fallback: Tắt tính năng, ghi log cảnh báo, web vẫn chạy mượt mà 100%)
 */

let isApiOutageSimulated = false;

// 1. Tự động chạy khi trang tải xong
document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const userParam = params.get('user');
    const envParam = params.get('env');

    if (userParam) {
        onCustomUserChange(userParam);
    }
    if (envParam) {
        const envSelect = document.getElementById('select-env');
        if (envSelect) envSelect.value = envParam;
    }

    loadAppleFeatureFlags();
});

// 2. Chuyển đổi khi người dùng nhập User ID tự do
function onCustomUserChange(val) {
    const clean = val.trim().toUpperCase();
    if (clean) {
        // Cập nhật giá trị
        const select = document.getElementById('select-user');
        // Thêm option tạm thời nếu chưa có
        let exists = false;
        for (let opt of select.options) {
            if (opt.value === clean) {
                select.value = clean;
                exists = true;
                break;
            }
        }
        if (!exists) {
            const newOpt = document.createElement('option');
            newOpt.value = clean;
            newOpt.textContent = `${clean} (Tự nhập)`;
            select.appendChild(newOpt);
            select.value = clean;
        }
        loadAppleFeatureFlags();
    }
}

// 3. Gạt công tắc mô phỏng lỗi API / mất mạng
function toggleApiOutage(checked) {
    isApiOutageSimulated = checked;
    const alertBox = document.getElementById('outage-alert-box');
    if (alertBox) {
        alertBox.style.display = checked ? 'block' : 'none';
    }
    loadAppleFeatureFlags();
}

// 4. Hàm chính: Gọi API và áp dụng cờ tính năng vào giao diện Apple
async function loadAppleFeatureFlags() {
    const userSelect = document.getElementById('select-user');
    const envSelect = document.getElementById('select-env');
    const tableBody = document.getElementById('inspector-table-body');
    const lblUser = document.getElementById('lbl-user-id');
    const lblEnv = document.getElementById('lbl-env-id');

    const currentUser = userSelect ? userSelect.value : 'U0500';
    const currentEnv = envSelect ? envSelect.value : 'van_hanh';

    if (lblUser) lblUser.textContent = currentUser;
    if (lblEnv) lblEnv.textContent = currentEnv;

    console.log(`[Apple Store] Đang truy vấn cấu hình cho User: ${currentUser}, Môi trường: ${currentEnv}...`);

    let responseData = null;
    let isFallbackActive = false;

    try {
        // Nếu người dùng bật công tắc mô phỏng lỗi API
        if (isApiOutageSimulated) {
            throw new Error('MÔ PHỎNG LỖI MẠNG: Máy chủ API /api/evaluate.php không phản hồi (HTTP 503)');
        }

        const url = `/api/evaluate.php?user=${encodeURIComponent(currentUser)}&env=${encodeURIComponent(currentEnv)}`;
        const res = await fetch(url);
        if (!res.ok) {
            throw new Error(`Máy chủ trả về mã lỗi HTTP: ${res.status}`);
        }
        responseData = await res.json();
    } catch (err) {
        // =============================================================
        // YÊU CẦU KỸ THUẬT 2: CƠ CHẾ DỰ PHÒNG FALLBACK KHI API GẶP SỰ CỐ
        // "Nếu API lỗi thì coi như tắt, ghi log, web vẫn chạy"
        // =============================================================
        console.warn(`[Apple Store Fallback] ⚠️ Gặp sự cố kết nối API: ${err.message}. Kích hoạt cơ chế dự phòng an toàn!`);
        isFallbackActive = true;

        // Giá trị dự phòng quy định: Toàn bộ cờ mới đều coi như TẮT
        const defaultFlags = [
            'banner_iphone_17', 'btn_preorder_17', 'tet_holiday_theme',
            'ai_accessories', 'laser_engraving', 'apple_pay_0percent',
            'express_delivery_2h', 'trade_in_bonus', 'gift_card_custom',
            'genius_bar_live'
        ];

        responseData = {
            user: currentUser,
            env: currentEnv,
            is_fallback: true,
            flags: {}
        };

        defaultFlags.forEach(k => {
            responseData.flags[k] = {
                name: k,
                enabled: false, // Giá trị dự phòng an toàn là TẮT
                hash_bucket: -1,
                reason: 'Giá trị dự phòng (Fallback): Máy chủ API lỗi, tạm thời TẮT tính năng để bảo vệ an toàn.',
                percentage: 0
            };
        });
    }

    // 5. Cập nhật các thành phần giao diện Web Apple theo cờ tính năng
    applyFlagsToAppleUI(responseData.flags, isFallbackActive);

    // 6. Điền dữ liệu vào Bảng Thanh Tra Quyết Định
    renderInspectorTable(tableBody, responseData.flags, isFallbackActive);
}

/**
 * Cập nhật hiển thị DOM dựa trên kết quả cờ
 */
function applyFlagsToAppleUI(flags, isFallback) {
    if (!flags) return;

    // 1. Banner iPhone 17 (banner_iphone_17)
    const bannerBox = document.getElementById('flag-box-banner_iphone_17');
    if (bannerBox) {
        const isBannerOn = flags['banner_iphone_17'] ? flags['banner_iphone_17'].enabled : false;
        bannerBox.style.display = isBannerOn ? 'block' : 'none';
    }

    // 2. Nút Đặt Trước (btn_preorder_17)
    const preorderBtn = document.getElementById('flag-box-btn_preorder_17');
    if (preorderBtn) {
        const isPreorderOn = flags['btn_preorder_17'] ? flags['btn_preorder_17'].enabled : false;
        preorderBtn.style.display = isPreorderOn ? 'inline-flex' : 'none';
    }

    // 3. Giao diện Tết (tet_holiday_theme)
    const tetBox = document.getElementById('flag-box-tet_holiday_theme');
    const heroBanner = document.getElementById('hero-banner-section');
    if (tetBox && heroBanner) {
        const isTetOn = flags['tet_holiday_theme'] ? flags['tet_holiday_theme'].enabled : false;
        tetBox.style.display = isTetOn ? 'block' : 'none';
        if (isTetOn) {
            heroBanner.classList.add('tet-theme');
        } else {
            heroBanner.classList.remove('tet-theme');
        }
    }

    // 4. Gợi ý phụ kiện AI (ai_accessories)
    setElementVisibility('flag-box-ai_accessories', flags['ai_accessories']?.enabled);

    // 5. Khắc Laser miễn phí (laser_engraving)
    setElementVisibility('flag-box-laser_engraving', flags['laser_engraving']?.enabled);

    // 6. Trả góp 0% Apple Pay (apple_pay_0percent)
    setElementVisibility('flag-box-apple_pay_0percent', flags['apple_pay_0percent']?.enabled);

    // 7. Giao hàng hỏa tốc 2 giờ (express_delivery_2h)
    setElementVisibility('flag-box-express_delivery_2h', flags['express_delivery_2h']?.enabled);

    // 8. Thu cũ đổi mới trợ giá (trade_in_bonus)
    setElementVisibility('flag-box-trade_in_bonus', flags['trade_in_bonus']?.enabled);

    // 9. Thẻ quà tặng Gift Card (gift_card_custom)
    setElementVisibility('flag-box-gift_card_custom', flags['gift_card_custom']?.enabled);

    // 10. Hỗ trợ trực tiếp Genius Bar (genius_bar_live)
    setElementVisibility('flag-box-genius_bar_live', flags['genius_bar_live']?.enabled);
}

function setElementVisibility(elementId, isVisible) {
    const el = document.getElementById(elementId);
    if (el) {
        el.style.display = isVisible ? 'block' : 'none';
    }
}

/**
 * Hiển thị bảng thanh tra trực quan
 */
function renderInspectorTable(tbody, flags, isFallback) {
    if (!tbody || !flags) return;

    let html = '';
    for (const [key, info] of Object.entries(flags)) {
        const isEnabled = info.enabled;
        const badge = isEnabled
            ? '<span class="badge-flag-on">● ĐANG BẬT</span>'
            : '<span class="badge-flag-off">○ ĐANG TẮT</span>';

        const bucketStr = info.hash_bucket >= 0 ? `Bucket: <strong>${info.hash_bucket}</strong>` : 'N/A';
        const ruleStr = info.percentage !== undefined ? `${info.percentage}% Rollout` : 'Mặc định';

        html += `
            <tr style="${isEnabled ? 'background: rgba(48, 209, 88, 0.04);' : ''}">
                <td><code>${key}</code></td>
                <td><strong>${info.name || key}</strong></td>
                <td>${badge}</td>
                <td>${bucketStr}</td>
                <td>${ruleStr}</td>
                <td style="font-size: 12px; color: ${isEnabled ? '#30d158' : 'var(--apple-text-muted)'};">
                    ${info.reason || 'Được xử lý bởi hệ thống'}
                </td>
            </tr>
        `;
    }

    tbody.innerHTML = html;
}

function alertPreorderSuccess() {
    alert('🎉 Chúc mừng! Bạn đã kích hoạt tính năng đặt trước iPhone 17 Pro Max sớm và được áp dụng phiếu quà tặng 2.000.000đ từ Apple Store!');
}
