<?php
require_once __DIR__ . '/../src/Input.php';
require_once __DIR__ . '/../src/FeatureManager.php';

$allFeatures = getFeatures();
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = validateFeatureInput($_POST, $allFeatures);
    if (empty($result['errors'])) {
        addFeature($result['data']);
    }
}

require __DIR__ . '/../templates/header.php';
?>
<div class="page-heading">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1>Thêm Cờ Tính Năng Mới</h1>
            <p>Đăng ký và cấu hình tính năng mới cho hệ thống quản lý ứng dụng Web.</p>
        </div>
        <a class="text-button" href="/features.php" style="border: 1px solid var(--outline); padding: 8px 16px; border-radius: 8px; text-decoration: none;">← Quay lại danh sách</a>
    </div>
</div>

<form class="registration-form" method="post" style="max-width: 680px;">
    <label class="form-field" for="key">Mã định danh (Feature Key)
        <small style="display: block; color: var(--on-surface-variant); margin-bottom: 4px;">Chỉ gồm chữ thường, số và dấu gạch dưới, từ 3-50 ký tự (ví dụ: new_checkout_flow)</small>
        <input id="key" name="key" maxlength="50" placeholder="ví dụ: payment_v2"
               value="<?= e(field($_POST, 'key')) ?>" required>
    </label>

    <label class="form-field" for="name">Tên hiển thị tính năng
        <input id="name" name="name" maxlength="120" placeholder="ví dụ: Tích hợp cổng thanh toán V2"
               value="<?= e(field($_POST, 'name')) ?>" required>
    </label>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
        <label class="form-field" for="category">Phân loại (Category)
            <select id="category" name="category" required>
                <?php $catVal = field($_POST, 'category'); ?>
                <option value="ui" <?= $catVal === 'ui' ? 'selected' : '' ?>>UI (Giao diện)</option>
                <option value="backend" <?= ($catVal === '' || $catVal === 'backend') ? 'selected' : '' ?>>Backend (Xử lý)</option>
                <option value="system" <?= $catVal === 'system' ? 'selected' : '' ?>>System (Hệ thống)</option>
                <option value="experimental" <?= $catVal === 'experimental' ? 'selected' : '' ?>>Experimental (Thử nghiệm)</option>
            </select>
        </label>

        <label class="form-field" for="environment">Môi trường (Environment)
            <select id="environment" name="environment" required>
                <?php $envVal = field($_POST, 'environment'); ?>
                <option value="development" <?= ($envVal === '' || $envVal === 'development') ? 'selected' : '' ?>>Development</option>
                <option value="staging" <?= $envVal === 'staging' ? 'selected' : '' ?>>Staging</option>
                <option value="production" <?= $envVal === 'production' ? 'selected' : '' ?>>Production</option>
            </select>
        </label>
    </div>

    <label class="form-field" for="description">Mô tả mục đích tính năng
        <textarea id="description" name="description" rows="3" maxlength="255"
                  style="display: block; width: 100%; padding: 12px 16px; border: 1px solid var(--outline); border-radius: 4px; font: inherit; background: var(--surface); color: var(--on-surface);"><?= e(field($_POST, 'description')) ?></textarea>
    </label>

    <label class="filter-checkbox" for="enabled" style="margin-top: 8px;">
        <input type="checkbox" id="enabled" name="enabled" value="1"
               <?= field($_POST, 'enabled') === '1' ? 'checked' : '' ?>>
        Kích hoạt bật tính năng này ngay sau khi tạo
    </label>

    <button type="submit" style="margin-top: 16px;">Lưu và Đăng ký tính năng</button>
</form>

<?php if ($result !== null): ?>
    <?php if (!empty($result['errors'])): ?>
        <div class="error-summary" role="alert" style="max-width: 680px; margin-top: 24px;">
            <p class="error-messages"><?= e(implode(', ', $result['errors'])) ?></p>
            <p>Thông tin cấu hình chưa hợp lệ. Vui lòng kiểm tra lại:</p>
            <ul style="margin: 8px 0 0 20px; font-size: 0.9rem;">
                <?php foreach ($result['errors'] as $err): ?>
                    <li>
                        <?php if ($err === 'KEY_INVALID'): ?>Mã Feature Key không đúng định dạng (phải gồm 3-50 ký tự thường a-z, 0-9, _)
                        <?php elseif ($err === 'KEY_EXISTS'): ?>Mã Feature Key này đã tồn tại trong hệ thống!
                        <?php elseif ($err === 'NAME_INVALID'): ?>Tên tính năng không được để trống hoặc vượt quá 120 ký tự
                        <?php elseif ($err === 'CATEGORY_INVALID'): ?>Phân loại không hợp lệ
                        <?php elseif ($err === 'ENVIRONMENT_INVALID'): ?>Môi trường không hợp lệ
                        <?php elseif ($err === 'DESCRIPTION_TOO_LONG'): ?>Mô tả vượt quá 255 ký tự
                        <?php else: ?><?= e($err) ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else: ?>
        <section class="preview" role="status" style="max-width: 680px; margin-top: 24px; padding: 24px; border: 2px solid #2e7d32; border-radius: 12px; background: #e8f5e9;">
            <h2 style="color: #1b5e20; margin-top: 0;">✓ Đăng ký tính năng thành công!</h2>
            <p class="status-message">PREVIEW_OK: Tính năng <strong><?= e($result['data']['name']) ?></strong> (<code><?= e($result['data']['key']) ?></code>) đã được thêm vào hệ thống quản lý.</p>
            <p>Trạng thái: <strong><?= $result['data']['enabled'] ? 'Đang Bật (Active)' : 'Đang Tắt (Inactive)' ?></strong> | Môi trường: <strong><?= e($result['data']['environment']) ?></strong></p>
            <div style="margin-top: 16px;">
                <a class="text-button" href="/features.php" style="background: #2e7d32; color: #fff; padding: 8px 16px; border-radius: 6px; text-decoration: none;">Xem trên bảng điều khiển →</a>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>

