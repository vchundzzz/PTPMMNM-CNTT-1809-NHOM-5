<?php
$file = __DIR__ . '/../demo-app.php';
$content = file_get_contents($file);

// 1. iPhone product card buy button
$target_iphone = <<<'EOD'
            <!-- CỜ 1: QUY TRÌNH MUA 1-CHẠM (thanh_toan_nhanh) -->
            <?php if ($is_fast_pay): ?>
              <button type="button" class="tz-buy-btn fast-pay" onclick="openFastCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['colors'][0]['img'] ?? '') ?>')">
                <span> Mua ngay 1-Chạm Express</span>
              </button>
            <?php else: ?>
              <button type="button" class="tz-buy-btn" onclick="openStandardCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>')">
                <span> Mua ngay (Tiêu chuẩn 3 bước)</span>
              </button>
            <?php endif; ?>
EOD;

$replace_iphone = <<<'EOD'
            <!-- CỜ 1: QUY TRÌNH MUA 1-CHẠM (thanh_toan_nhanh) -->
            <div style="display: flex; gap: 0.45rem; width: 100%;">
              <?php if ($is_fast_pay): ?>
                <button type="button" class="tz-buy-btn fast-pay" style="flex: 1;" onclick="openFastCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['colors'][0]['img'] ?? '') ?>')">
                  <span> Mua ngay</span>
                </button>
              <?php else: ?>
                <button type="button" class="tz-buy-btn" style="flex: 1;" onclick="openStandardCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>')">
                  <span> Mua ngay</span>
                </button>
              <?php endif; ?>
              <button type="button" class="tz-round-btn" style="width: 40px; height: 38px; border-radius: 10px; flex-shrink: 0;" title="Thêm vào giỏ hàng" onclick="addToCart('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['colors'][0]['img'] ?? '') ?>')">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                  <line x1="3" y1="6" x2="21" y2="6"></line>
                  <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
              </button>
            </div>
EOD;

// 2. Mac product card buy button
$target_mac = <<<'EOD'
            <?php if ($is_fast_pay): ?>
              <button type="button" class="tz-buy-btn fast-pay" onclick="openFastCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>')">
                <span> Mua ngay 1-Chạm Express</span>
              </button>
            <?php else: ?>
              <button type="button" class="tz-buy-btn" onclick="openStandardCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>')">
                <span> Mua ngay (Tiêu chuẩn 3 bước)</span>
              </button>
            <?php endif; ?>
EOD;

$replace_mac = <<<'EOD'
            <div style="display: flex; gap: 0.45rem; width: 100%;">
              <?php if ($is_fast_pay): ?>
                <button type="button" class="tz-buy-btn fast-pay" style="flex: 1;" onclick="openFastCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>')">
                  <span> Mua ngay</span>
                </button>
              <?php else: ?>
                <button type="button" class="tz-buy-btn" style="flex: 1;" onclick="openStandardCheckout('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>')">
                  <span> Mua ngay</span>
                </button>
              <?php endif; ?>
              <button type="button" class="tz-round-btn" style="width: 40px; height: 38px; border-radius: 10px; flex-shrink: 0;" title="Thêm vào giỏ hàng" onclick="addToCart('<?= e($p['id']) ?>', '<?= e($p['name']) ?>', '<?= e($p['price']) ?>', '<?= e($p['image']) ?>')">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                  <line x1="3" y1="6" x2="21" y2="6"></line>
                  <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
              </button>
            </div>
EOD;

// Normalize line breaks for matching
function normalize_replace($haystack, $target, $replacement) {
    $crlf_target = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $target));
    $lf_target = str_replace("\r\n", "\n", $target);
    $crlf_rep = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $replacement));
    $lf_rep = str_replace("\r\n", "\n", $replacement);

    if (strpos($haystack, $crlf_target) !== false) {
        return str_replace($crlf_target, $crlf_rep, $haystack);
    } elseif (strpos($haystack, $lf_target) !== false) {
        return str_replace($lf_target, $lf_rep, $haystack);
    }
    return $haystack;
}

$c1 = normalize_replace($content, $target_iphone, $replace_iphone);
if ($c1 === $content) {
    echo "Warning: iPhone target not replaced!\n";
} else {
    echo "Success: iPhone target replaced.\n";
    $content = $c1;
}

// Replace target_mac in 3 places (Mac, iPad, Watch)
$c2 = normalize_replace($content, $target_mac, $replace_mac);
if ($c2 === $content) {
    echo "Warning: Mac/iPad/Watch target not replaced!\n";
} else {
    echo "Success: Mac/iPad/Watch target replaced.\n";
    $content = $c2;
}

// 3. AI Recommendations combo button
$target_rec = "onclick=\"showToast('Đã thêm <?= e(\$rec['name']) ?> vào giỏ mua kèm!', 'success')\"";
$replace_rec = "onclick=\"addToCart('<?= e(\$rec['id']) ?>', '<?= e(\$rec['name']) ?>', '<?= e(\$rec['price']) ?>', '<?= e(\$rec['image']) ?>')\"";
$c3 = str_replace($target_rec, $replace_rec, $content);
if ($c3 === $content) {
    echo "Warning: AI Rec button not replaced!\n";
} else {
    echo "Success: AI Rec button replaced.\n";
    $content = $c3;
}

// 4. Insert #cartModal before #oneStepCheckoutModal
$target_one_step = '<!-- ========================================================================
     MODAL: CỜ 1 - THANH TOÁN 1 BƯỚC SIÊU TỐC (ONE-STEP CHECKOUT)
     ======================================================================== -->';

$cart_modal_html = <<<'EOD'
<!-- ========================================================================
     MODAL: GIỎ HÀNG CDS STORE (#cartModal)
     ======================================================================== -->
<div class="modal-backdrop" id="cartModal">
  <div class="modal-dialog" style="max-width: 600px; background: <?= $is_dark_mode ? '#1f1f27' : '#ffffff' ?>; color: <?= $is_dark_mode ? '#f4f4f5' : '#0f172a' ?>; border-radius: 14px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.35);">
    <div class="modal-header" style="background: <?= $is_dark_mode ? '#14141a' : '#f8fafc' ?>; border-bottom: 1px solid <?= $is_dark_mode ? '#2d2d3a' : '#e2e8f0' ?>; padding: 1rem 1.4rem; display: flex; align-items: center; justify-content: space-between;">
      <div style="font-weight: 700; font-size: 1.05rem; display: flex; align-items: center; gap: 0.5rem;">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
          <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
          <line x1="3" y1="6" x2="21" y2="6"></line>
          <path d="M16 10a4 4 0 0 1-8 0"></path>
        </svg>
        <span>Giỏ hàng CDS STORE</span>
        <span class="badge" id="cartModalItemCount" style="background: #2563eb; color: #fff; font-size: 0.72rem; padding: 0.2rem 0.5rem; border-radius: 12px;">2 món</span>
      </div>
      <button type="button" class="modal-close" style="color: <?= $is_dark_mode ? '#fff' : '#475569' ?>;" onclick="closeModal('cartModal')">&times;</button>
    </div>
    <div class="modal-body" style="padding: 1.25rem 1.4rem;">
      <!-- Trạng thái giỏ rỗng -->
      <div id="cartEmptyState" style="display: none; text-align: center; padding: 2.5rem 1rem; color: #94a3b8;">
        <div style="font-size: 3rem; margin-bottom: 0.5rem;"></div>
        <div style="font-weight: 700; font-size: 1.1rem; color: <?= $is_dark_mode ? '#fff' : '#0f172a' ?>; margin-bottom: 0.35rem;">Giỏ hàng của bạn đang trống</div>
        <div style="font-size: 0.85rem; margin-bottom: 1.25rem;">Hãy khám phá các siêu phẩm Apple chính hãng tại CDS STORE</div>
        <button type="button" class="btn btn-primary" onclick="closeModal('cartModal')">Khám phá sản phẩm ngay</button>
      </div>

      <!-- Nội dung khi có hàng -->
      <div id="cartContentArea">
        <div id="cartItemsList" style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 280px; overflow-y: auto; padding-right: 0.25rem; margin-bottom: 1.15rem;"></div>

        <!-- Khối tính tiền -->
        <div style="background: <?= $is_dark_mode ? '#14141a' : '#f8fafc' ?>; border: 1px solid <?= $is_dark_mode ? '#2d2d3a' : '#e2e8f0' ?>; border-radius: 10px; padding: 0.9rem 1.1rem; margin-bottom: 1.15rem; font-size: 0.86rem;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.45rem; color: <?= $is_dark_mode ? '#a1a1aa' : '#64748b' ?>;">
            <span>Tạm tính (<span id="cartSummaryQty">2</span> sản phẩm):</span>
            <span id="cartSubtotalText" class="font-mono" style="font-weight: 600; color: <?= $is_dark_mode ? '#fff' : '#0f172a' ?>;">42.540.000đ</span>
          </div>

          <?php if ($is_coupon): ?>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.45rem; color: #16a34a; font-weight: 600;">
              <span>Voucher CDS18 (Tự động kích hoạt):</span>
              <span id="cartVoucherDiscountText">-1.000.000đ</span>
            </div>
          <?php endif; ?>

          <?php if ($is_loyalty): ?>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.45rem; color: #d97706; font-weight: 600;">
              <span>Điểm thưởng CDS VIP:</span>
              <span id="cartLoyaltyDiscountText">-245.000đ</span>
            </div>
          <?php endif; ?>

          <div style="display: flex; justify-content: space-between; border-top: 1px solid <?= $is_dark_mode ? '#2d2d3a' : '#e2e8f0' ?>; padding-top: 0.6rem; margin-top: 0.4rem; font-weight: 800; font-size: 1.05rem;">
            <span>Tổng thanh toán:</span>
            <span id="cartGrandTotalText" style="color: #ff361d;" class="font-mono">41.295.000đ</span>
          </div>
        </div>

        <!-- Các nút hành động -->
        <div style="display: flex; gap: 0.75rem; align-items: center;">
          <button type="button" class="btn btn-secondary" onclick="clearCart()" style="font-size: 0.82rem; padding: 0.6rem 0.9rem;">
            Xóa giỏ hàng
          </button>
          <button type="button" class="btn btn-primary" style="flex: 1; font-weight: 700; padding: 0.7rem; font-size: 0.95rem; background: <?= $is_fast_pay ? 'linear-gradient(135deg, #f59e0b 0%, #ea580c 100%)' : '#2563eb' ?>; border: none;" onclick="proceedToCheckoutFromCart()">
            <?php if ($is_fast_pay): ?>
               Thanh toán 1-Chạm Express &rarr;
            <?php else: ?>
               Tiến hành đặt hàng (3 bước) &rarr;
            <?php endif; ?>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ========================================================================
     MODAL: CỜ 1 - THANH TOÁN 1 BƯỚC SIÊU TỐC (ONE-STEP CHECKOUT)
     ======================================================================== -->
EOD;

$c4 = normalize_replace($content, $target_one_step, $cart_modal_html);
if ($c4 === $content) {
    echo "Warning: cartModal could not be inserted before oneStepCheckoutModal!\n";
} else {
    echo "Success: cartModal inserted.\n";
    $content = $c4;
}

// 5. Replace standardCheckoutModal with 3-step interactive modal
$target_std_modal = <<<'EOD'
<!-- ========================================================================
     MODAL: QUY TRÌNH THANH TOÁN TIÊU CHUẨN (KHI CỜ thanh_toan_nhanh = OFF)
     ======================================================================== -->
<div class="modal-backdrop" id="standardCheckoutModal">
  <div class="modal-dialog" style="max-width: 580px;">
    <div class="modal-header">
      <div style="font-weight: 700; font-size: 1.05rem;">
        Đặt hàng tiêu chuẩn tại CDS STORE (3 Bước xét duyệt)
      </div>
      <button type="button" class="modal-close" onclick="closeModal('standardCheckoutModal')">&times;</button>
    </div>
    <div class="modal-body" style="padding: 1.5rem;">
      <div style="background: #f1f5f9; border-left: 4px solid #64748b; padding: 0.65rem 0.85rem; border-radius: 4px; font-size: 0.82rem; margin-bottom: 1.25rem;">
        Cờ <strong>thanh_toan_nhanh = OFF</strong>. Hệ thống hiển thị quy trình đặt hàng 3 bước tiêu chuẩn dành cho khách vãng lai.
      </div>

      <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem;">
        <div style="flex: 1; text-align: center; padding: 0.4rem; background: #2997ff; color: #fff; font-size: 0.75rem; font-weight: 700; border-radius: 4px;">1. Giỏ hàng</div>
        <div style="flex: 1; text-align: center; padding: 0.4rem; background: #e2e8f0; color: #475569; font-size: 0.75rem; font-weight: 700; border-radius: 4px;">2. Địa chỉ</div>
        <div style="flex: 1; text-align: center; padding: 0.4rem; background: #e2e8f0; color: #475569; font-size: 0.75rem; font-weight: 700; border-radius: 4px;">3. Thanh toán</div>
      </div>

      <div style="margin-bottom: 1rem;">
        <label class="form-label">Sản phẩm chọn mua:</label>
        <input type="text" id="stdProductName" class="form-control" readonly>
      </div>

      <div style="margin-bottom: 1rem;">
        <label class="form-label">Giá tiền:</label>
        <input type="text" id="stdProductPrice" class="form-control font-mono" readonly style="font-weight: 700; color: #ff361d;">
      </div>

      <button type="button" class="btn btn-primary" style="width: 100%;" onclick="showToast('Đang chuyển sang Bước 2 (Nhập địa chỉ giao hàng)...', 'info')">
        Tiếp tục sang Bước 2 &rarr;
      </button>
    </div>
  </div>
</div>
EOD;

$replace_std_modal = <<<'EOD'
<!-- ========================================================================
     MODAL: QUY TRÌNH THANH TOÁN TIÊU CHUẨN (KHI CỜ thanh_toan_nhanh = OFF)
     ======================================================================== -->
<div class="modal-backdrop" id="standardCheckoutModal">
  <div class="modal-dialog" style="max-width: 580px; background: <?= $is_dark_mode ? '#1f1f27' : '#ffffff' ?>; color: <?= $is_dark_mode ? '#f4f4f5' : '#0f172a' ?>; border-radius: 14px; overflow: hidden;">
    <div class="modal-header" style="background: <?= $is_dark_mode ? '#14141a' : '#f8fafc' ?>; border-bottom: 1px solid <?= $is_dark_mode ? '#2d2d3a' : '#e2e8f0' ?>; padding: 1rem 1.4rem;">
      <div style="font-weight: 700; font-size: 1.05rem;">
        Đặt hàng tiêu chuẩn tại CDS STORE (3 Bước xét duyệt)
      </div>
      <button type="button" class="modal-close" style="color: <?= $is_dark_mode ? '#fff' : '#475569' ?>;" onclick="closeModal('standardCheckoutModal')">&times;</button>
    </div>
    <div class="modal-body" style="padding: 1.4rem;">
      <div style="background: <?= $is_dark_mode ? '#14141a' : '#f1f5f9' ?>; border-left: 4px solid #64748b; padding: 0.65rem 0.85rem; border-radius: 4px; font-size: 0.82rem; margin-bottom: 1.25rem; color: <?= $is_dark_mode ? '#cbd5e1' : '#334155' ?>;">
        Cờ <strong>thanh_toan_nhanh = OFF</strong>. Hệ thống kích hoạt quy trình đặt hàng 3 bước tiêu chuẩn dành cho khách vãng lai.
      </div>

      <!-- Thanh trạng thái 3 bước -->
      <div style="display: flex; gap: 0.5rem; margin-bottom: 1.35rem;">
        <div id="stdStepTab1" style="flex: 1; text-align: center; padding: 0.45rem; background: #2997ff; color: #fff; font-size: 0.76rem; font-weight: 700; border-radius: 6px; transition: all 0.2s;">1. Giỏ hàng</div>
        <div id="stdStepTab2" style="flex: 1; text-align: center; padding: 0.45rem; background: <?= $is_dark_mode ? '#2d2d3a' : '#e2e8f0' ?>; color: <?= $is_dark_mode ? '#94a3b8' : '#475569' ?>; font-size: 0.76rem; font-weight: 700; border-radius: 6px; transition: all 0.2s;">2. Địa chỉ</div>
        <div id="stdStepTab3" style="flex: 1; text-align: center; padding: 0.45rem; background: <?= $is_dark_mode ? '#2d2d3a' : '#e2e8f0' ?>; color: <?= $is_dark_mode ? '#94a3b8' : '#475569' ?>; font-size: 0.76rem; font-weight: 700; border-radius: 6px; transition: all 0.2s;">3. Thanh toán</div>
      </div>

      <!-- BƯỚC 1: GIỎ HÀNG -->
      <div id="stdStep1">
        <div style="margin-bottom: 0.9rem;">
          <label class="form-label" style="font-weight: 600;">Sản phẩm chọn mua:</label>
          <input type="text" id="stdProductName" class="form-control" readonly style="background: <?= $is_dark_mode ? '#14141a' : '#f8fafc' ?>; color: <?= $is_dark_mode ? '#fff' : '#0f172a' ?>;">
        </div>

        <div style="margin-bottom: 1.25rem;">
          <label class="form-label" style="font-weight: 600;">Giá tiền dự kiến:</label>
          <input type="text" id="stdProductPrice" class="form-control font-mono" readonly style="font-weight: 700; color: #ff361d; background: <?= $is_dark_mode ? '#14141a' : '#f8fafc' ?>;">
        </div>

        <button type="button" class="btn btn-primary" style="width: 100%; font-weight: 700; padding: 0.75rem;" onclick="goToStandardStep(2)">
          Tiếp tục sang Bước 2: Thông tin giao hàng &rarr;
        </button>
      </div>

      <!-- BƯỚC 2: ĐỊA CHỈ GIAO HÀNG -->
      <div id="stdStep2" style="display: none;">
        <div style="margin-bottom: 0.85rem;">
          <label class="form-label" style="font-weight: 600;">Họ tên người nhận:</label>
          <input type="text" id="stdReceiverName" class="form-control" value="Khách hàng <?= e($current_user_id) ?>" placeholder="Nhập họ tên" style="background: <?= $is_dark_mode ? '#14141a' : '#fff' ?>; color: <?= $is_dark_mode ? '#fff' : '#0f172a' ?>;">
        </div>

        <div style="margin-bottom: 0.85rem;">
          <label class="form-label" style="font-weight: 600;">Số điện thoại liên hệ:</label>
          <input type="text" id="stdReceiverPhone" class="form-control font-mono" value="0362481351" placeholder="Nhập số điện thoại" style="background: <?= $is_dark_mode ? '#14141a' : '#fff' ?>; color: <?= $is_dark_mode ? '#fff' : '#0f172a' ?>;">
        </div>

        <div style="margin-bottom: 1.25rem;">
          <label class="form-label" style="font-weight: 600;">Địa chỉ giao hàng (Tỉnh/Thành, Quận, Số nhà):</label>
          <input type="text" id="stdReceiverAddress" class="form-control" value="Số 128 Trần Quang Khải, P. Tân Định, Quận 1, TP.HCM" placeholder="Nhập địa chỉ nhận hàng" style="background: <?= $is_dark_mode ? '#14141a' : '#fff' ?>; color: <?= $is_dark_mode ? '#fff' : '#0f172a' ?>;">
        </div>

        <div style="display: flex; gap: 0.75rem;">
          <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="goToStandardStep(1)">
            &larr; Quay lại Giỏ hàng
          </button>
          <button type="button" class="btn btn-primary" style="flex: 2; font-weight: 700;" onclick="goToStandardStep(3)">
            Tiếp tục sang Bước 3: Thanh toán &rarr;
          </button>
        </div>
      </div>

      <!-- BƯỚC 3: PHƯƠNG THỨC THANH TOÁN -->
      <div id="stdStep3" style="display: none;">
        <div style="margin-bottom: 1rem;">
          <label class="form-label" style="font-weight: 600;">Chọn phương thức thanh toán:</label>
          <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <label style="border: 1px solid #2997ff; background: rgba(41,151,255,0.08); padding: 0.65rem 0.85rem; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 0.6rem; font-size: 0.85rem; font-weight: 600;">
              <input type="radio" name="std_pay_method" value="bank" checked>
              <span> Chuyển khoản QR Ngân hàng (Miễn phí & Giảm thêm 100k)</span>
            </label>
            <label style="border: 1px solid <?= $is_dark_mode ? '#2d2d3a' : '#e2e8f0' ?>; padding: 0.65rem 0.85rem; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 0.6rem; font-size: 0.85rem; font-weight: 600;">
              <input type="radio" name="std_pay_method" value="cod">
              <span> Thanh toán tiền mặt khi nhận hàng (COD)</span>
            </label>
            <label style="border: 1px solid <?= $is_dark_mode ? '#2d2d3a' : '#e2e8f0' ?>; padding: 0.65rem 0.85rem; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 0.6rem; font-size: 0.85rem; font-weight: 600;">
              <input type="radio" name="std_pay_method" value="installment">
              <span> Trả góp 0% lãi suất qua thẻ tín dụng (25 ngân hàng)</span>
            </label>
          </div>
        </div>

        <div style="background: <?= $is_dark_mode ? '#14141a' : '#f8fafc' ?>; border: 1px dashed #22c55e; padding: 0.85rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.84rem;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
            <span>Đơn hàng:</span>
            <span id="stdSummaryName" style="font-weight: 600;">-</span>
          </div>
          <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 0.95rem; border-top: 1px solid <?= $is_dark_mode ? '#2d2d3a' : '#e2e8f0' ?>; padding-top: 0.4rem; margin-top: 0.4rem;">
            <span>Tổng tiền thanh toán:</span>
            <span id="stdSummaryPrice" style="color: #ff361d;" class="font-mono">-</span>
          </div>
        </div>

        <div style="display: flex; gap: 0.75rem;">
          <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="goToStandardStep(2)">
            &larr; Quay lại Địa chỉ
          </button>
          <button type="button" class="btn btn-primary" style="flex: 2; font-weight: 800; background: #16a34a; border: none; padding: 0.75rem;" onclick="handleStandardCheckoutSubmit()">
             Xác nhận hoàn tất đặt hàng
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
EOD;

$c5 = normalize_replace($content, $target_std_modal, $replace_std_modal);
if ($c5 === $content) {
    echo "Warning: standardCheckoutModal could not be replaced!\n";
} else {
    echo "Success: standardCheckoutModal replaced.\n";
    $content = $c5;
}

// 6. Replace Checkout and Cart JavaScript functions
$target_js = <<<'EOD'
function openStandardCheckout(id, name, price) {
  document.getElementById('stdProductName').value = name;
  document.getElementById('stdProductPrice').value = price;
  openModal('standardCheckoutModal');
}

function handleCheckoutSubmit(e) {
  e.preventDefault();
  closeModal('oneStepCheckoutModal');
  showToast(' Đặt hàng CDS STORE thành công! Mã đơn: CDS-2026-98845. Shipper sẽ liên hệ qua 0362481351 trong 15 phút.', 'success');
  const badge = document.getElementById('cartBadgeCount');
  if (badge) badge.textContent = '3';
}
EOD;

$replace_js = <<<'EOD'
function openStandardCheckout(id, name, price) {
  document.getElementById('stdProductName').value = name;
  document.getElementById('stdProductPrice').value = price;
  const summaryName = document.getElementById('stdSummaryName');
  if (summaryName) summaryName.textContent = name;
  const summaryPrice = document.getElementById('stdSummaryPrice');
  if (summaryPrice) summaryPrice.textContent = price;
  goToStandardStep(1);
  openModal('standardCheckoutModal');
}

function handleCheckoutSubmit(e) {
  e.preventDefault();
  closeModal('oneStepCheckoutModal');
  showToast(' Đặt hàng CDS STORE thành công! Mã đơn: CDS-2026-98845. Shipper sẽ liên hệ qua 0362481351 trong 15 phút.', 'success');
  cartItems = [];
  updateCartUI();
}

function goToStandardStep(step) {
  const s1 = document.getElementById('stdStep1');
  const s2 = document.getElementById('stdStep2');
  const s3 = document.getElementById('stdStep3');
  const t1 = document.getElementById('stdStepTab1');
  const t2 = document.getElementById('stdStepTab2');
  const t3 = document.getElementById('stdStepTab3');

  if (!s1 || !s2 || !s3) return;

  s1.style.display = (step === 1) ? 'block' : 'none';
  s2.style.display = (step === 2) ? 'block' : 'none';
  s3.style.display = (step === 3) ? 'block' : 'none';

  const isDark = APP_FLAGS.dark;
  const activeBg = '#2997ff';
  const inactiveBg = isDark ? '#2d2d3a' : '#e2e8f0';
  const activeColor = '#ffffff';
  const inactiveColor = isDark ? '#94a3b8' : '#475569';

  if (t1) {
    t1.style.background = (step >= 1) ? activeBg : inactiveBg;
    t1.style.color = (step >= 1) ? activeColor : inactiveColor;
  }
  if (t2) {
    t2.style.background = (step >= 2) ? activeBg : inactiveBg;
    t2.style.color = (step >= 2) ? activeColor : inactiveColor;
  }
  if (t3) {
    t3.style.background = (step >= 3) ? activeBg : inactiveBg;
    t3.style.color = (step >= 3) ? activeColor : inactiveColor;
  }
}

function handleStandardCheckoutSubmit() {
  closeModal('standardCheckoutModal');
  const code = 'CDS-STD-' + Math.floor(10000 + Math.random() * 90000);
  showToast(` Đặt hàng CDS STORE thành công qua quy trình 3 bước! Mã đơn: ${code}. Shipper sẽ liên hệ trong 15 phút.`, 'success');
  cartItems = [];
  updateCartUI();
  goToStandardStep(1);
}
EOD;

$c6 = normalize_replace($content, $target_js, $replace_js);
if ($c6 === $content) {
    echo "Warning: Checkout JS could not be replaced!\n";
} else {
    echo "Success: Checkout JS replaced.\n";
    $content = $c6;
}

// 7. Replace openCartView() and add Cart Engine
$target_open_cart = <<<'EOD'
function openCartView() {
  showToast('Giỏ hàng CDS STORE: iPhone 18 Pro Max 256GB & Củ sạc 20W Apple (Hotline: 0362481351)', 'info');
}
EOD;

$replace_open_cart = <<<'EOD'
// --------------------------------------------------------------------------
// QUẢN LÝ GIỎ HÀNG CDS STORE (SHOPPING CART ENGINE)
// --------------------------------------------------------------------------
let cartItems = [
  {
    id: 'p1',
    name: 'iPhone 18 Pro Max 256GB - Titan Sa Mạc',
    price: 41990000,
    qty: 1,
    img: 'https://cdn-v2.didongviet.vn/files/products/2024/8/10/1/1725929654877_thumb_iphone_16_pro_max_didongviet.jpg'
  },
  {
    id: 'rec1',
    name: 'Củ sạc nhanh Apple 20W USB-C',
    price: 550000,
    qty: 1,
    img: 'https://cdnv2.tgdd.vn/webmwg/2024/tz/images/desktop/Phukien_Desk_25.png'
  }
];

function formatVND(num) {
  return new Intl.NumberFormat('vi-VN').format(num) + 'đ';
}

function updateCartUI() {
  const badge = document.getElementById('cartBadgeCount');
  const modalCount = document.getElementById('cartModalItemCount');
  const summaryQty = document.getElementById('cartSummaryQty');
  const emptyState = document.getElementById('cartEmptyState');
  const contentArea = document.getElementById('cartContentArea');
  const listContainer = document.getElementById('cartItemsList');

  const totalCount = cartItems.reduce((acc, item) => acc + item.qty, 0);

  if (badge) {
    badge.textContent = totalCount;
    badge.style.display = totalCount > 0 ? 'inline-block' : 'none';
  }

  if (modalCount) modalCount.textContent = `${totalCount} món`;
  if (summaryQty) summaryQty.textContent = totalCount;

  if (cartItems.length === 0) {
    if (emptyState) emptyState.style.display = 'block';
    if (contentArea) contentArea.style.display = 'none';
    if (listContainer) listContainer.innerHTML = '';
    return;
  }

  if (emptyState) emptyState.style.display = 'none';
  if (contentArea) contentArea.style.display = 'block';

  let subtotal = 0;
  if (listContainer) {
    const isDark = APP_FLAGS.dark;
    const itemBorder = isDark ? '#2d2d3a' : '#e2e8f0';
    const itemBg = isDark ? '#14141a' : '#ffffff';
    const textMain = isDark ? '#ffffff' : '#0f172a';

    listContainer.innerHTML = cartItems.map(item => {
      const lineTotal = item.price * item.qty;
      subtotal += lineTotal;
      return `
        <div style="display: flex; gap: 0.75rem; align-items: center; padding: 0.75rem; border: 1px solid ${itemBorder}; border-radius: 10px; background: ${itemBg};">
          <img src="${item.img}" alt="${item.name}" style="width: 50px; height: 50px; object-fit: contain; border-radius: 6px; background: #fff; padding: 2px;">
          <div style="flex: 1; min-width: 0;">
            <div style="font-weight: 600; font-size: 0.88rem; color: ${textMain}; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
              ${item.name}
            </div>
            <div style="font-size: 0.82rem; font-weight: 700; color: #ff361d; margin-top: 2px;">
              ${formatVND(item.price)}
            </div>
          </div>
          <div style="display: flex; align-items: center; gap: 6px;">
            <button type="button" style="width: 26px; height: 26px; border: 1px solid ${itemBorder}; background: ${isDark ? '#272733' : '#f1f5f9'}; color: ${textMain}; border-radius: 4px; cursor: pointer; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;" onclick="changeCartQty('${item.id}', -1)">&minus;</button>
            <span style="font-weight: 700; font-size: 0.88rem; min-width: 18px; text-align: center; color: ${textMain};">${item.qty}</span>
            <button type="button" style="width: 26px; height: 26px; border: 1px solid ${itemBorder}; background: ${isDark ? '#272733' : '#f1f5f9'}; color: ${textMain}; border-radius: 4px; cursor: pointer; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;" onclick="changeCartQty('${item.id}', 1)">&plus;</button>
          </div>
          <button type="button" style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 4px; font-size: 1rem;" title="Xóa món" onclick="removeFromCart('${item.id}')">
          </button>
        </div>
      `;
    }).join('');
  } else {
    subtotal = cartItems.reduce((acc, item) => acc + (item.price * item.qty), 0);
  }

  const subtotalEl = document.getElementById('cartSubtotalText');
  if (subtotalEl) subtotalEl.textContent = formatVND(subtotal);

  let voucherDiscount = (APP_FLAGS.coupon && subtotal > 0) ? 1000000 : 0;
  let loyaltyDiscount = (APP_FLAGS.loyalty && subtotal > 0) ? 245000 : 0;
  let grandTotal = Math.max(0, subtotal - voucherDiscount - loyaltyDiscount);

  const grandTotalEl = document.getElementById('cartGrandTotalText');
  if (grandTotalEl) grandTotalEl.textContent = formatVND(grandTotal);
}

function changeCartQty(id, delta) {
  const item = cartItems.find(i => i.id === id);
  if (!item) return;
  item.qty += delta;
  if (item.qty <= 0) {
    removeFromCart(id);
    return;
  }
  updateCartUI();
}

function removeFromCart(id) {
  const item = cartItems.find(i => i.id === id);
  const name = item ? item.name : 'Sản phẩm';
  cartItems = cartItems.filter(i => i.id !== id);
  updateCartUI();
  showToast(`Đã xóa [${name}] khỏi giỏ hàng!`, 'info');
}

function clearCart() {
  if (cartItems.length === 0) return;
  if (confirm('Bạn có chắc chắn muốn làm trống toàn bộ giỏ hàng không?')) {
    cartItems = [];
    updateCartUI();
    showToast('Đã xóa toàn bộ giỏ hàng!', 'info');
  }
}

function addToCart(id, name, price, img) {
  let numPrice = typeof price === 'number' ? price : parseInt(String(price).replace(/\D/g, ''), 10) || 550000;
  const existing = cartItems.find(item => item.id === id);
  if (existing) {
    existing.qty += 1;
  } else {
    cartItems.push({
      id: id,
      name: name,
      price: numPrice,
      qty: 1,
      img: img || 'https://cdn-v2.didongviet.vn/files/products/2024/8/10/1/1725929654877_thumb_iphone_16_pro_max_didongviet.jpg'
    });
  }
  updateCartUI();
  showToast(`Đã thêm [${name}] vào giỏ hàng CDS STORE!`, 'success');
}

function openCartView() {
  updateCartUI();
  openModal('cartModal');
}

function proceedToCheckoutFromCart() {
  if (cartItems.length === 0) {
    showToast('Giỏ hàng đang trống! Vui lòng chọn sản phẩm.', 'warning');
    return;
  }
  closeModal('cartModal');

  const mainItem = cartItems[0];
  const totalCount = cartItems.reduce((acc, item) => acc + item.qty, 0);
  const subtotal = cartItems.reduce((acc, item) => acc + (item.price * item.qty), 0);
  let voucherDiscount = (APP_FLAGS.coupon && subtotal > 0) ? 1000000 : 0;
  let loyaltyDiscount = (APP_FLAGS.loyalty && subtotal > 0) ? 245000 : 0;
  let grandTotal = Math.max(0, subtotal - voucherDiscount - loyaltyDiscount);
  const summaryTitle = totalCount > 1 ? `${mainItem.name} (+${totalCount - 1} món khác)` : mainItem.name;

  if (APP_FLAGS.fast_checkout) {
    document.getElementById('modalProductName').textContent = summaryTitle;
    document.getElementById('modalProductPrice').textContent = formatVND(subtotal);
    document.getElementById('modalProductImg').src = mainItem.img;
    document.getElementById('modalFinalPrice').textContent = formatVND(grandTotal);
    openModal('oneStepCheckoutModal');
  } else {
    document.getElementById('stdProductName').value = summaryTitle;
    document.getElementById('stdProductPrice').value = formatVND(grandTotal);
    const sName = document.getElementById('stdSummaryName');
    if (sName) sName.textContent = summaryTitle;
    const sPrice = document.getElementById('stdSummaryPrice');
    if (sPrice) sPrice.textContent = formatVND(grandTotal);
    goToStandardStep(1);
    openModal('standardCheckoutModal');
  }
}

// Khởi chạy đồng bộ giao diện giỏ hàng khi tải trang
document.addEventListener('DOMContentLoaded', updateCartUI);
EOD;

$c7 = normalize_replace($content, $target_open_cart, $replace_open_cart);
if ($c7 === $content) {
    echo "Warning: openCartView could not be replaced!\n";
} else {
    echo "Success: openCartView replaced.\n";
    $content = $c7;
}

file_put_contents($file, $content);
echo "File demo-app.php updated successfully.\n";
