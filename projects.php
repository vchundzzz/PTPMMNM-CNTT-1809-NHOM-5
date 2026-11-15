<?php
/**
 * Web Feature Flag Management System
 * projects.php - Quản lý danh sách & Tạo/Sửa Dự án (Projects)
 * BTL Phát triển phần mềm mã nguồn mở
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/services/PermissionService.php';

$page_title = 'Quản lý dự án';
$breadcrumbs = [
    ['label' => 'Trang chủ', 'url' => 'dashboard.php'],
    ['label' => 'Dự án', 'url' => ''],
];

// Xử lý tạo mới hoặc sửa dự án qua POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf)) {
        set_flash('error', 'Mã xác thực CSRF không hợp lệ hoặc phiên đã hết hạn.');
        header('Location: projects.php');
        exit;
    }

    // Kiểm tra quyền phía Server
    PermissionService::enforce('manage_projects');

    $action = $_POST['action'] ?? '';
    if ($action === 'create' || $action === 'edit') {
        $name = trim((string)($_POST['name'] ?? ''));
        $key = trim((string)($_POST['key'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $status = in_array($_POST['status'] ?? '', ['Active', 'Archived'], true) ? $_POST['status'] : 'Active';

        if (empty($name) || empty($key)) {
            set_flash('error', 'Tên dự án và Mã định danh (Key) không được để trống.');
            header('Location: projects.php');
            exit;
        }

        // Định dạng key chuẩn: chỉ chữ cái thường, số và dấu gạch dưới
        if (!preg_match('/^[a-z0-9_]+$/', $key)) {
            set_flash('error', 'Mã dự án (Key) chỉ được chứa chữ thường không dấu, số và gạch dưới (vd: ecommerce_v2).');
            header('Location: projects.php');
            exit;
        }

        $project_id = ($action === 'edit') ? trim((string)($_POST['project_id'] ?? '')) : 'prj_' . $key;

        // Lưu vào Session
        $_SESSION['ff_data']['projects'][$project_id] = [
            'id' => $project_id,
            'key' => $key,
            'name' => $name,
            'description' => $description,
            'created_at' => ($action === 'edit' && isset($_SESSION['ff_data']['projects'][$project_id]['created_at'])) 
                            ? $_SESSION['ff_data']['projects'][$project_id]['created_at'] 
                            : date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'status' => $status,
        ];

        // Đồng bộ vào MySQL projects table
        require_once __DIR__ . '/services/DatabaseService.php';
        if (!DatabaseService::saveProject($project_id, $key, $name, $description, $status)) {
            init_session_data(true);
            set_flash('error', 'Không thể lưu dự án vào CSDL MySQL.');
            header('Location: projects.php');
            exit;
        }
        if ($status === 'Active') {
            $_SESSION['selected_project_id'] = $project_id;
        } elseif (($_SESSION['selected_project_id'] ?? '') === $project_id) {
            unset($_SESSION['selected_project_id']);
        }

        // Ghi nhận Audit Log đồng bộ vào MySQL
        append_audit_log(
            ($action === 'create') ? 'TẠO_DỰ_ÁN' : 'CẬP_NHẬT_DỰ_ÁN',
            $name . " ($key)",
            ($action === 'edit') ? 'Cấu hình cũ' : '—',
            "Trạng thái: $status",
            ($action === 'create') ? 'Khởi tạo dự án mới và lưu vào CSDL MySQL' : 'Cập nhật thông tin dự án trong CSDL MySQL'
        );

        set_flash('success', ($action === 'create') 
            ? "Đã tạo thành công dự án [$name] và lưu trực tiếp vào CSDL MySQL!" 
            : "Đã cập nhật thông tin dự án [$name] vào CSDL MySQL!");
        header('Location: projects.php');
        exit;
    }

    if ($action === 'delete') {
        PermissionService::enforce('delete_project');
        $project_id = trim((string)($_POST['project_id'] ?? ''));
        if ($project_id !== '' && isset($_SESSION['ff_data']['projects'][$project_id])) {
            $oldName = $_SESSION['ff_data']['projects'][$project_id]['name'] ?? $project_id;
            unset($_SESSION['ff_data']['projects'][$project_id]);
            require_once __DIR__ . '/services/DatabaseService.php';
            if (!DatabaseService::deleteProject($project_id)) {
                init_session_data(true);
                set_flash('error', 'Không thể xóa dự án khỏi CSDL MySQL.');
                header('Location: projects.php');
                exit;
            }
            if (($_SESSION['selected_project_id'] ?? '') === $project_id) {
                unset($_SESSION['selected_project_id']);
            }
            append_audit_log('XÓA_DỰ_ÁN', $oldName, 'Tồn tại', 'Đã xóa', 'Xóa dự án khỏi hệ thống và CSDL MySQL');
            set_flash('success', "Đã xóa dự án [$oldName] khỏi hệ thống và CSDL MySQL.");
        }
        header('Location: projects.php');
        exit;
    }
}

require_once __DIR__ . '/includes/header.php';

$projects = $_SESSION['ff_data']['projects'] ?? [];
$all_flags = $_SESSION['ff_data']['flags'] ?? [];
$can_delete_project = PermissionService::currentUserCan('delete_project');
$projects_pagination = paginate_items($projects, (int)($_GET['page'] ?? 1), 10);
$page_projects = $projects_pagination['items'];
?>

<style>
  #projectsTable tbody tr.project-active-row {
    background: #f0f7ff;
    box-shadow: inset 4px 0 0 var(--primary);
    font-weight: 600;
  }
  #projectsTable tbody tr.project-active-row td:first-child {
    font-weight: 700;
  }
  #projectsTable tbody tr.project-active-row:hover {
    background: #e8f3ff;
  }
</style>

<div class="page-header">
  <div>
    <h1 class="page-title">Quản lý dự án</h1>
    <p class="page-description">
      Danh sách ứng dụng được kết nối với hệ thống Feature Flags. Thao tác được lưu trực tiếp vào cơ sở dữ liệu.
    </p>
  </div>
  <div class="page-actions">
    <?php if (PermissionService::currentUserCan('manage_projects')): ?>
      <button type="button" class="btn btn-primary" onclick="openCreateProjectModal()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        <span>Tạo dự án mới</span>
      </button>
    <?php else: ?>
      <button type="button" class="btn btn-primary" disabled title="Chỉ Admin mới có quyền tạo dự án">
        Tạo dự án mới (Yêu cầu quyền Admin)
      </button>
    <?php endif; ?>
  </div>
</div>

<div class="filter-toolbar">
  <div class="filter-left">
    <div class="search-input-wrapper">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
      </svg>
      <input type="text" id="projectSearchInput" class="form-control" placeholder="Nhập tên hoặc mã dự án cần tìm">
    </div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="data-table" id="projectsTable">
      <thead>
        <tr class="<?= ($project['status'] ?? '') === 'Active' ? 'project-active-row' : '' ?>">
          <th>Tên dự án</th>
          <th>Mã dự án</th>
          <th>Mô tả</th>
          <th>Tính năng</th>
          <th>Trạng thái</th>
          <th>Cập nhật lần cuối</th>
          <th style="text-align: right;">Chức năng</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($page_projects as $project): ?>
          <?php
            $project_flags_count = count(array_filter($all_flags, static function (array $flag) use ($project): bool {
                return ($flag['project_id'] ?? '') === ($project['id'] ?? '');
            }));
          ?>
          <tr>
            <td>
              <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;"><?= e($project['name']) ?></div>
              <span class="font-mono" style="font-size: 0.72rem; color: var(--text-muted);"><?= e($project['id']) ?></span>
            </td>
            <td>
              <span class="font-mono"><?= e($project['key']) ?></span>
            </td>
            <td>
              <span style="font-size: 0.85rem; color: var(--text-secondary); max-width: 320px; display: inline-block;">
                <?= e($project['description']) ?>
              </span>
            </td>
            <td>
              <span class="badge badge-neutral" style="font-weight: 600;"><?= $project_flags_count ?> flags</span>
            </td>
            <td>
              <?= render_badge($project['status']) ?>
            </td>
            <td>
              <span style="font-size: 0.8rem; color: var(--text-muted);"><?= format_date($project['updated_at'] ?? $project['created_at']) ?></span>
            </td>
            <td style="text-align: right; white-space: nowrap;">
              <div class="action-icons" style="justify-content: flex-end;">
                <!-- Xem chi tiết (Ant Design EyeOutlined) -->
                <a href="flags.php?project_id=<?= urlencode((string)$project['id']) ?>" class="action-icon-btn action-view" title="Quản lý cờ của dự án" aria-label="Quản lý cờ của dự án">
                  <svg viewBox="64 64 896 896" fill="currentColor"><path d="M942.2 486.2C847.4 286.5 704.1 186 512 186c-192.2 0-335.4 100.5-430.2 300.3a60.3 60.3 0 0 0 0 51.5C176.6 737.5 319.9 838 512 838c192.2 0 335.4-100.5 430.2-300.3 7.7-16.2 7.7-35 0-51.5zM512 766c-161.3 0-279.4-81.8-362.7-254C232.6 339.8 350.7 258 512 258c161.3 0 279.4 81.8 362.7 254C791.5 684.2 673.4 766 512 766zm-4-422c-97.2 0-176 78.8-176 176s78.8 176 176 176 176-78.8 176-176-78.8-176-176-176zm0 288c-61.9 0-112-50.1-112-112s50.1-112 112-112 112 50.1 112 112-50.1 112-112 112z"/></svg>
                </a>
                <!-- Chỉnh sửa dự án (Ant Design EditOutlined) -->
                <?php if (PermissionService::currentUserCan('manage_projects')): ?>
                  <button type="button" class="action-icon-btn action-edit" title="Chỉnh sửa" aria-label="Chỉnh sửa" onclick='openEditProjectModal(<?= json_encode($project) ?>)'>
                    <svg viewBox="64 64 896 896" fill="currentColor"><path d="M257.7 752c2 0 4-.2 6-.5L431.9 722c2-.4 3.9-1.3 5.3-2.8l423.9-423.9a9.96 9.96 0 0 0 0-14.1L694.9 114.9c-1.9-1.9-4.4-2.9-7.1-2.9s-5.2 1-7.1 2.9L256.8 538.8c-1.5 1.5-2.4 3.3-2.8 5.3l-29.5 168.2a33.5 33.5 0 0 0 9.4 29.8c6.6 6.4 14.9 9.9 23.8 9.9zm67.4-174.4L687.8 215l57.2 57.2-362.7 362.6-68.9 12 11.7-69.2zM752 688H144c-17.7 0-32 14.3-32 32v120c0 4.4 3.6 8 8 8h784c4.4 0 8-3.6 8-8V720c0-17.7-14.3-32-32-32z"/></svg>
                  </button>
                  <?php if ($can_delete_project): ?>
                  <form method="POST" action="projects.php" style="display:inline" onsubmit="return confirm('Xác nhận xóa dự án [<?= e($project['name']) ?>]? Toàn bộ cờ thuộc dự án cũng sẽ bị xóa.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="project_id" value="<?= e($project['id']) ?>">
                    <button type="submit" class="action-icon-btn danger action-delete" title="Xóa dự án" aria-label="Xóa dự án">
                      <svg viewBox="64 64 896 896" fill="currentColor"><path d="M864 256H736v-80c0-35.3-28.7-64-64-64H352c-35.3 0-64 28.7-64 64v80H160c-17.7 0-32 14.3-32 32v32c0 4.4 3.6 8 8 8h60.5l49.1 530.6C249.2 884.2 284.9 912 326.6 912h370.8c41.7 0 77.4-27.8 81-73.4L827.5 328H888c4.4 0 8-3.6 8-8v-32c0-17.7-14.3-32-32-32zM360 200h304v56H360v-56zm366.7 632.7c-1.2 15.2-13.1 23.3-26.7 23.3H324c-13.6 0-25.5-8.1-26.7-23.3L249.8 328h524.4l-47.5 504.7zM432 400c17.7 0 32 14.3 32 32v304c0 17.7-14.3 32-32 32s-32-14.3-32-32V432c0-17.7 14.3-32 32-32zm160 0c17.7 0 32 14.3 32 32v304c0 17.7-14.3 32-32 32s-32-14.3-32-32V432c0-17.7 14.3-32 32-32z"/></svg>
                    </button>
                  </form>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= render_pagination($projects_pagination['total'], $projects_pagination['page'], 10, 'projects.php', 'dự án') ?>
</div>

<!-- ========================================================================
     MODAL TẠO / SỬA DỰ ÁN
     ======================================================================== -->
<div class="modal-backdrop" id="projectModal">
  <div class="modal-dialog">
    <form method="POST" action="projects.php" id="projectForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="projectFormAction" value="create">
      <input type="hidden" name="project_id" id="projectFormId" value="">

      <div class="modal-header">
        <h3 class="modal-title" id="projectModalTitle">Tạo dự án mới</h3>
        <button type="button" class="btn-ghost btn-sm" data-modal-close>&times;</button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label class="form-label" for="projName">Tên dự án *</label>
          <input type="text" name="name" id="projName" class="form-control" placeholder="Nhập tên dự án" required>
        </div>

        <div class="form-group">
          <label class="form-label" for="projKey">Mã định danh (Key) *</label>
          <input type="text" name="key" id="projKey" class="form-control font-mono" placeholder="Nhập mã dự án" required>
          <div class="form-text">Dùng làm namespace định danh trong API. Chỉ chữ thường và gạch dưới.</div>
        </div>

        <div class="form-group">
          <label class="form-label" for="projDesc">Mô tả dự án</label>
          <textarea name="description" id="projDesc" class="form-control" rows="3" placeholder="Nhập mô tả dự án"></textarea>
        </div>

        <div class="form-group">
          <label class="form-label" for="projStatus">Trạng thái</label>
          <select name="status" id="projStatus" class="form-select">
            <option value="Active">Active (Đang hoạt động)</option>
            <option value="Archived">Archived (Lưu trữ)</option>
          </select>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Hủy</button>
        <button type="submit" class="btn btn-primary" id="projectSubmitBtn">Lưu dự án</button>
      </div>
    </form>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    initTableSearch('projectSearchInput', 'projectsTable');
  });

  function openCreateProjectModal() {
    document.getElementById('projectFormAction').value = 'create';
    document.getElementById('projectFormId').value = '';
    document.getElementById('projectModalTitle').textContent = 'Tạo dự án mới';
    document.getElementById('projName').value = '';
    document.getElementById('projKey').value = '';
    document.getElementById('projKey').removeAttribute('readonly');
    document.getElementById('projDesc').value = '';
    document.getElementById('projStatus').value = 'Active';
    document.getElementById('projectSubmitBtn').textContent = 'Tạo dự án';
    openModal('projectModal');
  }

  function openEditProjectModal(project) {
    document.getElementById('projectFormAction').value = 'edit';
    document.getElementById('projectFormId').value = project.id;
    document.getElementById('projectModalTitle').textContent = 'Chỉnh sửa dự án: ' + project.name;
    document.getElementById('projName').value = project.name;
    document.getElementById('projKey').value = project.key;
    document.getElementById('projKey').setAttribute('readonly', 'readonly');
    document.getElementById('projDesc').value = project.description || '';
    document.getElementById('projStatus').value = project.status || 'Active';
    document.getElementById('projectSubmitBtn').textContent = 'Cập nhật thay đổi';
    openModal('projectModal');
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
