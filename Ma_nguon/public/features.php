<?php
require_once __DIR__ . '/../src/Input.php';
require_once __DIR__ . '/../src/FeatureFilter.php';
require_once __DIR__ . '/../src/FeatureManager.php';

// Xử lý các hành động POST (Bật/Tắt cờ hoặc Reset dữ liệu)
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = field($_POST, 'action');
    if ($action === 'toggle') {
        $featureId = (int)field($_POST, 'id');
        if (toggleFeature($featureId)) {
            $message = 'Đã cập nhật trạng thái cờ tính năng thành công!';
        }
    } elseif ($action === 'reset') {
        resetFeatures();
        $message = 'Đã đặt lại dữ liệu cấu hình về mặc định!';
    }
}

// Lấy danh sách cờ tính năng hiện thời
$allFeatures = getFeatures();

// Đọc tham số bộ lọc từ GET
$query = field($_GET, 'q');
$category = field($_GET, 'category');
$status = field($_GET, 'status');
$environment = field($_GET, 'environment');

// Lọc danh sách theo các điều kiện kết hợp
$visibleFeatures = filterFeatures($allFeatures, $query, $category, $status, $environment);

// Thống kê tổng quan
$totalCount = count($allFeatures);
$activeCount = count(array_filter($allFeatures, fn($f) => !empty($f['enabled'])));
$inactiveCount = $totalCount - $activeCount;

require __DIR__ . '/../templates/header.php';
?>
<div class="page-heading">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1>Quản lý Cấu hình Bật / Tắt Tính năng</h1>
            <p>Hệ thống Feature Flags / Toggles quản lý việc phân phối và kiểm soát tính năng ứng dụng Web.</p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a class="text-button" href="/feature-create.php" style="background: var(--primary); color: var(--on-primary); padding: 10px 18px; border-radius: 8px; font-weight: 500; text-decoration: none;">+ Thêm cờ tính năng</a>
            <form method="post" style="display: inline;" onsubmit="return confirm('Bạn có chắc muốn đặt lại cấu hình về mặc định?');">
                <input type="hidden" name="action" value="reset">
                <button type="submit" class="text-button" style="border: 1px solid var(--outline); padding: 10px 14px; border-radius: 8px; background: transparent; cursor: pointer;">Đặt lại mặc định</button>
            </form>
        </div>
    </div>
</div>

<?php if ($message !== ''): ?>
    <div class="preview" role="status" style="margin-bottom: 24px; padding: 16px; background: var(--secondary-container); color: var(--on-secondary-container); border-radius: 8px;">
        <p class="status-message" style="margin: 0; font-weight: 500;">✓ <?= e($message) ?></p>
    </div>
<?php endif; ?>

<!-- Bảng điều khiển số liệu thống kê -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div style="padding: 16px 20px; background: var(--surface-container-low); border-radius: 12px; border: 1px solid var(--outline-variant);">
        <span style="font-size: 0.875rem; color: var(--on-surface-variant);">Tổng số cờ tính năng</span>
        <strong style="display: block; font-size: 1.75rem; margin-top: 4px;"><?= $totalCount ?></strong>
    </div>
    <div style="padding: 16px 20px; background: #e8f5e9; color: #1b5e20; border-radius: 12px; border: 1px solid #c8e6c9;">
        <span style="font-size: 0.875rem;">Đang Bật (Active)</span>
        <strong style="display: block; font-size: 1.75rem; margin-top: 4px;"><?= $activeCount ?></strong>
    </div>
    <div style="padding: 16px 20px; background: #fbe9e7; color: #bf360c; border-radius: 12px; border: 1px solid #ffccbc;">
        <span style="font-size: 0.875rem;">Đang Tắt (Inactive)</span>
        <strong style="display: block; font-size: 1.75rem; margin-top: 4px;"><?= $inactiveCount ?></strong>
    </div>
    <div style="padding: 16px 20px; background: var(--surface-container-low); border-radius: 12px; border: 1px solid var(--outline-variant);">
        <span style="font-size: 0.875rem; color: var(--on-surface-variant);">Kết quả đang hiển thị</span>
        <strong style="display: block; font-size: 1.75rem; margin-top: 4px;"><?= count($visibleFeatures) ?></strong>
    </div>
</div>

<!-- Form tìm kiếm và bộ lọc kết hợp -->
<form class="search-form" method="get" style="margin-bottom: 24px;">
    <label class="form-field" for="q">Từ khóa
        <input id="q" name="q" placeholder="Key, tên hoặc mô tả tính năng..." value="<?= e($query) ?>">
    </label>

    <label class="form-field" for="category">Phân loại
        <select id="category" name="category">
            <option value="">Tất cả phân loại</option>
            <option value="ui" <?= $category === 'ui' ? 'selected' : '' ?>>UI (Giao diện)</option>
            <option value="backend" <?= $category === 'backend' ? 'selected' : '' ?>>Backend (Xử lý)</option>
            <option value="system" <?= $category === 'system' ? 'selected' : '' ?>>System (Hệ thống)</option>
            <option value="experimental" <?= $category === 'experimental' ? 'selected' : '' ?>>Experimental (Thử nghiệm)</option>
        </select>
    </label>

    <label class="form-field" for="status">Trạng thái
        <select id="status" name="status">
            <option value="">Tất cả trạng thái</option>
            <option value="enabled" <?= $status === 'enabled' ? 'selected' : '' ?>>Đang Bật (Active)</option>
            <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>Đang Tắt (Inactive)</option>
        </select>
    </label>

    <label class="form-field" for="environment">Môi trường
        <select id="environment" name="environment">
            <option value="">Tất cả môi trường</option>
            <option value="production" <?= $environment === 'production' ? 'selected' : '' ?>>Production</option>
            <option value="staging" <?= $environment === 'staging' ? 'selected' : '' ?>>Staging</option>
            <option value="development" <?= $environment === 'development' ? 'selected' : '' ?>>Development</option>
        </select>
    </label>

    <button type="submit">Lọc tính năng</button>
    <?php if ($query !== '' || $category !== '' || $status !== '' || $environment !== ''): ?>
        <a href="/features.php" style="align-self: center; text-decoration: none; color: var(--primary); font-size: 0.9rem;">Xóa bộ lọc</a>
    <?php endif; ?>
</form>

<!-- Danh sách cờ tính năng -->
<section aria-label="Danh sách cờ tính năng" style="display: grid; gap: 16px;">
    <?php foreach ($visibleFeatures as $feature): ?>
        <article class="event-card" style="padding: 24px; border-radius: 12px; background: var(--surface); border: 1px solid var(--outline-variant); display: flex; flex-direction: column; gap: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 6px;">
                        <h2 style="font-size: 1.25rem; margin: 0;"><?= e($feature['name']) ?></h2>
                        <code style="background: var(--surface-container-high); padding: 2px 8px; border-radius: 4px; font-size: 0.85rem; font-family: monospace;"><?= e($feature['key']) ?></code>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; font-size: 0.85rem;">
                        <span style="background: var(--secondary-container); color: var(--on-secondary-container); padding: 2px 8px; border-radius: 4px;">
                            Phân loại: <?= e($feature['category']) ?>
                        </span>
                        <span style="background: var(--surface-container); color: var(--on-surface-variant); padding: 2px 8px; border-radius: 4px;">
                            Môi trường: <?= e($feature['environment']) ?>
                        </span>
                    </div>
                </div>

                <!-- Nút chuyển đổi Bật/Tắt -->
                <div style="display: flex; align-items: center; gap: 12px;">
                    <?php if (!empty($feature['enabled'])): ?>
                        <span style="background: #e8f5e9; color: #1b5e20; font-weight: 600; padding: 6px 12px; border-radius: 16px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 4px;">
                            ● Đang Bật
                        </span>
                        <form method="post" style="margin: 0;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$feature['id'] ?>">
                            <button type="submit" style="background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; padding: 6px 14px; border-radius: 6px; font-size: 0.85rem; cursor: pointer;">
                                Tắt
                            </button>
                        </form>
                    <?php else: ?>
                        <span style="background: #f5f5f5; color: #616161; font-weight: 600; padding: 6px 12px; border-radius: 16px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 4px;">
                            ○ Đang Tắt
                        </span>
                        <form method="post" style="margin: 0;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$feature['id'] ?>">
                            <button type="submit" style="background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; padding: 6px 14px; border-radius: 6px; font-size: 0.85rem; cursor: pointer;">
                                Bật
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <p style="margin: 0; color: var(--on-surface-variant);"><?= e($feature['description']) ?></p>
        </article>
    <?php endforeach; ?>
</section>

<?php if (!$visibleFeatures): ?>
    <p class="empty-state">Không có cờ tính năng nào phù hợp với bộ lọc.
        <span>Thử xóa hoặc điều chỉnh các tiêu chí tìm kiếm phía trên.</span>
    </p>
<?php endif; ?>

<div class="content-note" style="margin-top: 32px; padding: 16px; background: var(--surface-container-low); border-radius: 8px;">
    <strong>Ghi chú đề tài:</strong> Tính năng chuyển đổi Bật/Tắt hoạt động theo phiên làm việc (Session) của máy chủ Web. Các thay đổi được cập nhật tức thì trên giao diện quản lý cấu hình.
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>

