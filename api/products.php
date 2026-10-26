<?php
/**
 * API: /api/products.php
 * Quản lý danh mục sản phẩm iPhone, Mac, iPad
 * Cung cấp CRUD, tìm kiếm, lọc, tác vụ hàng loạt và kiểm tra phân quyền RBAC
 */

require_once __DIR__ . '/db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Role, X-User-Name');
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    exit(0);
}

$pdo = get_db();

// 1. GET: Lấy danh sách sản phẩm (có tìm kiếm, lọc module, trạng thái)
if ($method === 'GET') {
    $search = trim($_GET['search'] ?? '');
    $module = trim($_GET['module'] ?? $_GET['category'] ?? 'all');
    $status = trim($_GET['status'] ?? 'all');

    $sql = "SELECT * FROM products WHERE 1=1";
    $params = [];

    if ($module !== 'all' && in_array(strtolower($module), ['iphone', 'mac', 'ipad'])) {
        $sql .= " AND LOWER(module) = ?";
        $params[] = strtolower($module);
    }

    if ($status !== 'all') {
        if ($status === 'available') {
            $sql .= " AND status = 'Đang bán'";
        } elseif ($status === 'home') {
            $sql .= " AND is_home = 1";
        } elseif ($status === 'menu') {
            $sql .= " AND is_menu = 1";
        } elseif ($status === 'visible') {
            $sql .= " AND is_visible = 1";
        }
    }

    if ($search !== '') {
        $sql .= " AND (name LIKE ? OR sku LIKE ? OR description LIKE ?)";
        $wildcard = "%{$search}%";
        $params[] = $wildcard;
        $params[] = $wildcard;
        $params[] = $wildcard;
    }

    $sql .= " ORDER BY stt ASC, id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    send_json_response([
        'status' => 'success',
        'total' => count($products),
        'products' => $products
    ]);
}

// Đối với các thao tác ghi (POST, PUT, DELETE), bắt buộc kiểm tra quyền SỬA
require_editor_permission();
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

// 2. POST: Thêm mới sản phẩm hoặc Tác vụ hàng loạt (Bulk action)
if ($method === 'POST') {
    $action = $_GET['action'] ?? ($input['action'] ?? 'create');

    // Thao tác hàng loạt (Bulk)
    if ($action === 'bulk') {
        $bulkAction = $input['bulk_action'] ?? '';
        $ids = $input['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            send_json_response(['status' => 'error', 'error' => 'Vui lòng chọn ít nhất 1 sản phẩm!'], 400);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        if ($bulkAction === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            send_json_response(['status' => 'success', 'message' => "Đã xóa thành công " . count($ids) . " sản phẩm!"]);
        } elseif ($bulkAction === 'toggle_home_on' || $bulkAction === 'toggle_home_off') {
            $val = ($bulkAction === 'toggle_home_on') ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE products SET is_home = ? WHERE id IN ($placeholders)");
            $stmt->execute(array_merge([$val], $ids));
            send_json_response(['status' => 'success', 'message' => "Đã cập nhật hiển thị Trang chủ!"]);
        } elseif ($bulkAction === 'toggle_menu_on' || $bulkAction === 'toggle_menu_off') {
            $val = ($bulkAction === 'toggle_menu_on') ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE products SET is_menu = ? WHERE id IN ($placeholders)");
            $stmt->execute(array_merge([$val], $ids));
            send_json_response(['status' => 'success', 'message' => "Đã cập nhật hiển thị Menu!"]);
        } elseif ($bulkAction === 'toggle_visible_on' || $bulkAction === 'toggle_visible_off') {
            $val = ($bulkAction === 'toggle_visible_on') ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE products SET is_visible = ? WHERE id IN ($placeholders)");
            $stmt->execute(array_merge([$val], $ids));
            send_json_response(['status' => 'success', 'message' => "Đã cập nhật trạng thái hiển thị!"]);
        }

        send_json_response(['status' => 'error', 'error' => 'Tác vụ hàng loạt không hợp lệ!'], 400);
    }

    // Thêm mới 1 sản phẩm
    $name = trim($input['name'] ?? '');
    if ($name === '') {
        send_json_response(['status' => 'error', 'error' => 'Tên sản phẩm không được để trống!'], 400);
    }

    $sku = trim($input['sku'] ?? '');
    $module = trim($input['module'] ?? 'iPhone');
    if ($sku === '') {
        $sku = 'APL-' . strtoupper(substr($module, 0, 3)) . '-' . rand(100, 999);
    }

    $maxStt = $pdo->query("SELECT MAX(stt) FROM products")->fetchColumn() ?: 0;
    $newId = 'prod_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $sku)) . '_' . time();

    $stmt = $pdo->prepare("
        INSERT INTO products (id, stt, sku, name, module, price, stock, image, is_home, is_menu, is_visible, status, status_seo, feature_flag, description)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $newId,
        $maxStt + 1,
        $sku,
        $name,
        in_array($module, ['iPhone', 'Mac', 'iPad']) ? $module : 'iPhone',
        (int)($input['price'] ?? 0),
        (int)($input['stock'] ?? 10),
        trim($input['image'] ?? 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=150&auto=format&fit=crop&q=80'),
        !empty($input['is_home']) ? 1 : 0,
        !empty($input['is_menu']) ? 1 : 0,
        isset($input['is_visible']) ? (!empty($input['is_visible']) ? 1 : 0) : 1,
        $input['status'] ?? 'Đang bán',
        'Dofollow,Index',
        $input['feature_flag'] ?? 'banner_iphone_17',
        trim($input['description'] ?? 'Sản phẩm Apple chính hãng VN/A')
    ]);

    send_json_response(['status' => 'success', 'message' => "Đã thêm sản phẩm '{$name}' thành công!"], 201);
}

// 3. PUT: Sửa sản phẩm
if ($method === 'PUT') {
    $id = $_GET['id'] ?? ($input['id'] ?? '');
    if (!$id) {
        send_json_response(['status' => 'error', 'error' => 'Thiếu ID sản phẩm cần cập nhật!'], 400);
    }

    $stmtFind = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmtFind->execute([$id]);
    $existing = $stmtFind->fetch();
    if (!$existing) {
        send_json_response(['status' => 'error', 'error' => 'Không tìm thấy sản phẩm!'], 404);
    }

    $name = isset($input['name']) ? trim($input['name']) : $existing['name'];
    $sku = isset($input['sku']) ? trim($input['sku']) : $existing['sku'];
    $module = isset($input['module']) ? trim($input['module']) : $existing['module'];
    $price = isset($input['price']) ? (int)$input['price'] : $existing['price'];
    $stock = isset($input['stock']) ? (int)$input['stock'] : $existing['stock'];
    $image = isset($input['image']) ? trim($input['image']) : $existing['image'];
    $is_home = isset($input['is_home']) ? (!empty($input['is_home']) ? 1 : 0) : $existing['is_home'];
    $is_menu = isset($input['is_menu']) ? (!empty($input['is_menu']) ? 1 : 0) : $existing['is_menu'];
    $is_visible = isset($input['is_visible']) ? (!empty($input['is_visible']) ? 1 : 0) : $existing['is_visible'];
    $status = isset($input['status']) ? trim($input['status']) : $existing['status'];
    $feature_flag = isset($input['feature_flag']) ? trim($input['feature_flag']) : $existing['feature_flag'];
    $description = isset($input['description']) ? trim($input['description']) : $existing['description'];

    $stmtUpdate = $pdo->prepare("
        UPDATE products SET 
            name = ?, sku = ?, module = ?, price = ?, stock = ?, 
            image = ?, is_home = ?, is_menu = ?, is_visible = ?, 
            status = ?, feature_flag = ?, description = ?
        WHERE id = ?
    ");
    $stmtUpdate->execute([
        $name, $sku, $module, $price, $stock,
        $image, $is_home, $is_menu, $is_visible,
        $status, $feature_flag, $description,
        $id
    ]);

    send_json_response(['status' => 'success', 'message' => "Cập nhật sản phẩm '{$name}' thành công!"]);
}

// 4. DELETE: Xóa sản phẩm
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? ($input['id'] ?? '');
    if (!$id) {
        send_json_response(['status' => 'error', 'error' => 'Thiếu ID sản phẩm cần xóa!'], 400);
    }

    $stmtDel = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmtDel->execute([$id]);

    send_json_response(['status' => 'success', 'message' => 'Đã xóa sản phẩm thành công!']);
}

send_json_response(['status' => 'error', 'error' => 'Phương thức không được hỗ trợ!'], 405);
