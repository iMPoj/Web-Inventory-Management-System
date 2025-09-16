 <?php
// --- DEBUG MODE ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 300);

// --- ROBUST ERROR HANDLER ---
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) { return; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

session_start();
header('Content-Type: application/json');

// --- WRAP ENTIRE APPLICATION IN A TRY...CATCH BLOCK ---
try {
    require 'db_connect.php';
    $action = $_REQUEST['action'] ?? '';

    // --- Security Check ---
    $public_actions = ['login'];
    if (!in_array($action, $public_actions)) {
        if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Authentication required.']);
            exit;
        }
    }

    $admin_only_actions = ['get_orders_for_export', 'add_customer', 'delete_customer', 'add_product', 'bulk_add_products', 'bulk_update_stock', 'delete_code', 'bulk_add_aliases', 'toggle_customer_priority', 'get_unlinked_skus'];
    if (in_array($action, $admin_only_actions)) {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Admin permission required for this action.']);
            exit;
        }
    }

    // --- Main Router ---
    switch ($action) {
        case 'login': login($pdo); break;
        case 'logout': logout(); break;
        case 'get_products': getProducts($pdo); break;
        case 'get_customers': getCustomers($pdo); break;
        case 'get_orders': getOrders($pdo); break;
        case 'get_unserved_orders': getUnservedOrders($pdo); break;
        case 'get_order_details': getOrderDetails($pdo); break;
        case 'update_order_items': updateOrderItems($pdo); break;
        case 'add_order': addOrder($pdo); break;
        case 'get_dashboard_data': getDashboardData($pdo); break;
        case 'get_customer_dashboard_data': getCustomerDashboardData($pdo); break;
        case 'get_fulfillable_items': getFulfillableItems($pdo); break;
        case 'get_orders_for_export': getOrdersForExport($pdo); break;
        case 'add_customer': addCustomer($pdo); break;
        case 'delete_customer': deleteCustomer($pdo); break;
        case 'toggle_customer_priority': toggleCustomerPriority($pdo); break;
        case 'add_product': addProduct($pdo); break;
        case 'bulk_add_products': bulkAddProducts($pdo); break;
        case 'bulk_update_stock': bulkUpdateStock($pdo); break;
        case 'delete_code': deleteCode($pdo); break;
        case 'bulk_add_aliases': bulkAddAliases($pdo); break;
        case 'get_unlinked_skus': getUnlinkedSkus($pdo); break;
        default: echo json_encode(['success' => false, 'message' => 'Invalid action specified']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine()]);
}

// --- FUNCTIONS ---

function login($pdo) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (empty($username) || empty($password)) { throw new Exception('Username and password are required.'); }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true); $_SESSION['user_logged_in'] = true; $_SESSION['user_id'] = $user['id']; $_SESSION['username'] = $user['username']; $_SESSION['role'] = $user['role'];
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    }
    exit;
}

function logout() {
    $_SESSION = array();
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
    exit;
}

function getCustomers($pdo) {
    $stmt = $pdo->query("SELECT id, name, is_priority FROM customers ORDER BY name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($customers);
    exit;
}

function getProducts($pdo) {
    $sql = "SELECT p.id, p.description, p.bu, p.is_promo, pc.code, pc.type, pc.pieces_per_case, pc.sales_price, il.location, il.stock FROM products p JOIN product_codes pc ON p.id = pc.product_id LEFT JOIN inventory_levels il ON pc.code = il.product_code ORDER BY p.description, pc.code";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $productsById = [];
    foreach ($rows as $row) {
        $productId = $row['id'];
        if (!isset($productsById[$productId])) { $productsById[$productId] = ['id' => (int)$row['id'], 'description' => $row['description'], 'bu' => $row['bu'], 'is_promo' => (bool)$row['is_promo'], 'codes' => []]; }
        $code = $row['code'];
        $codeIndex = -1;
        foreach ($productsById[$productId]['codes'] as $idx => $existingCode) { if ($existingCode['code'] === $code) { $codeIndex = $idx; break; } }
        if ($codeIndex === -1) {
            $productsById[$productId]['codes'][] = ['code' => $code, 'type' => $row['type'], 'pieces_per_case' => (int)$row['pieces_per_case'], 'sales_price' => (float)$row['sales_price'], 'inventory' => []];
            $codeIndex = count($productsById[$productId]['codes']) - 1;
        }
        if ($row['location'] !== null) { $productsById[$productId]['codes'][$codeIndex]['inventory'][] = ['location' => $row['location'], 'stock' => (int)$row['stock']]; }
    }
    echo json_encode(['success' => true, 'data' => array_values($productsById)]);
    exit;
}

function getOrders($pdo) {
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1; $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 50; $offset = ($page - 1) * $limit;
    $whereClauses = []; $params = [];
    if (!empty($_POST['location']) && $_POST['location'] !== 'all') { $whereClauses[] = "o.location = ?"; $params[] = $_POST['location']; }
    if (!empty($_POST['customer']) && $_POST['customer'] !== 'all') { $whereClauses[] = "c.name = ?"; $params[] = $_POST['customer']; }
    if (!empty($_POST['bu']) && $_POST['bu'] !== 'all') { $whereClauses[] = "o.bu = ?"; $params[] = $_POST['bu']; }
    $whereSql = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);
    $countSql = "SELECT COUNT(DISTINCT o.id) FROM orders o LEFT JOIN customers c ON o.customer_id = c.id $whereSql";
    $countStmt = $pdo->prepare($countSql); $countStmt->execute($params); $totalOrders = $countStmt->fetchColumn();
    $orderIdSql = "SELECT o.id FROM orders o LEFT JOIN customers c ON o.customer_id = c.id $whereSql ORDER BY o.order_date DESC, o.id ASC LIMIT ? OFFSET ?";
    $orderIdStmt = $pdo->prepare($orderIdSql); $orderIdStmt->execute(array_merge($params, [$limit, $offset])); $orderIds = $orderIdStmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($orderIds)) { echo json_encode(['success' => true, 'data' => [], 'total_orders' => 0]); exit; }
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $sql = "SELECT o.id, o.po_number, o.order_date, o.location, o.bu, o.discount_percentage, o.customer_address, c.name as customer_name, oi.sku, oi.description, oi.quantity, oi.price, oi.status FROM orders o LEFT JOIN customers c ON o.customer_id = c.id JOIN order_items oi ON o.id = oi.order_id WHERE o.id IN ($placeholders) ORDER BY o.order_date DESC, o.id ASC";
    $stmt = $pdo->prepare($sql); $stmt->execute($orderIds); $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $ordersById = [];
    foreach ($rows as $row) {
        $orderId = $row['id'];
        if (!isset($ordersById[$orderId])) { $ordersById[$orderId] = ['id' => $orderId, 'location' => $row['location'], 'bu' => $row['bu'], 'discount' => (float)$row['discount_percentage'], 'customer' => ['name' => $row['customer_name'] ?? 'N/A', 'address' => $row['customer_address'], 'poNumber' => $row['po_number']], 'date' => $row['order_date'], 'items' => []]; }
        $ordersById[$orderId]['items'][] = ['sku' => $row['sku'], 'description' => $row['description'], 'quantity' => $row['quantity'], 'price' => $row['price'], 'status' => $row['status']];
    }
    $sortedOrders = [];
    foreach($orderIds as $id){ if(isset($ordersById[$id])){ $sortedOrders[] = $ordersById[$id]; } }
    echo json_encode(['success' => true, 'data' => $sortedOrders, 'total_orders' => $totalOrders]);
    exit;
}

function getUnservedOrders($pdo) {
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 50;
    $offset = ($page - 1) * $limit;

    // Build WHERE clause for unserved orders
    $whereClauses = ["oi.status = 'unserved'"];
    $params = [];
    if (!empty($_POST['location']) && $_POST['location'] !== 'all') { $whereClauses[] = "o.location = ?"; $params[] = $_POST['location']; }
    if (!empty($_POST['bu']) && $_POST['bu'] !== 'all') { $whereClauses[] = "o.bu = ?"; $params[] = $_POST['bu']; }
    if (!empty($_POST['customer']) && $_POST['customer'] !== 'all') { $whereClauses[] = "c.name = ?"; $params[] = $_POST['customer']; }
    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);

    // Get Total Count of unserved orders
    $countSql = "SELECT COUNT(DISTINCT o.id) FROM orders o JOIN order_items oi ON o.id = oi.order_id LEFT JOIN customers c ON o.customer_id = c.id $whereSql";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalOrders = $countStmt->fetchColumn();

    // Get Paginated Unserved Order IDs
    $orderIdSql = "SELECT DISTINCT o.id FROM orders o JOIN order_items oi ON o.id = oi.order_id LEFT JOIN customers c ON o.customer_id = c.id $whereSql ORDER BY o.order_date DESC LIMIT ? OFFSET ?";
    $orderIdStmt = $pdo->prepare($orderIdSql);
    $orderIdStmt->execute(array_merge($params, [$limit, $offset]));
    $orderIds = $orderIdStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($orderIds)) {
        echo json_encode(['success' => true, 'data' => [], 'total_orders' => 0]);
        exit;
    }

    // Fetch details for those orders
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $sql = "SELECT o.id, o.po_number, o.order_date, o.location, o.bu, c.name as customer_name, oi.sku, oi.description, oi.quantity, oi.price, oi.status
            FROM orders o
            JOIN customers c ON o.customer_id = c.id
            JOIN order_items oi ON o.id = oi.order_id
            WHERE o.id IN ($placeholders)
            ORDER BY o.order_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($orderIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Reconstruct order objects
    $ordersById = [];
    foreach ($rows as $row) {
        $orderId = $row['id'];
        if (!isset($ordersById[$orderId])) {
            $ordersById[$orderId] = [
                'id' => $orderId, 'location' => $row['location'], 'bu' => $row['bu'],
                // THIS IS THE FIX: adding poNumber to the customer object
                'customer' => ['name' => $row['customer_name'], 'poNumber' => $row['po_number']],
                'date' => $row['order_date'], 'items' => []
            ];
        }
        $ordersById[$orderId]['items'][] = $row;
    }

    $sortedOrders = array_values($ordersById);
    echo json_encode(['success' => true, 'data' => $sortedOrders, 'total_orders' => $totalOrders]);
    exit;
}

function getOrderDetails($pdo) {
    $orderId = $_POST['id'] ?? 0;
    if (empty($orderId)) { throw new Exception('Order ID is required.'); }
    $orderStmt = $pdo->prepare("SELECT o.*, c.name as customer_name FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.id = ?");
    $orderStmt->execute([$orderId]); $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) { throw new Exception('Order not found.'); }
    $itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $itemStmt->execute([$orderId]); $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    $result = ['id' => $order['id'], 'location' => $order['location'], 'bu' => $order['bu'], 'discount' => (float)$order['discount_percentage'], 'customer' => ['name' => $order['customer_name'] ?? 'N/A', 'address' => $order['customer_address'], 'poNumber' => $order['po_number']], 'date' => $order['order_date'], 'items' => $items];
    echo json_encode(['success' => true, 'data' => $result]);
    exit;
}

function getFulfillableItems($pdo) {
    $sql = "WITH ProductStock AS (SELECT pc.product_id, il.location, SUM(il.stock) AS total_product_stock, GROUP_CONCAT(CASE WHEN il.stock > 0 THEN CONCAT(pc.code, ':', il.stock) ELSE NULL END SEPARATOR ',') AS available_skus_with_stock FROM product_codes pc JOIN inventory_levels il ON pc.code = il.product_code WHERE pc.type = 'sku' GROUP BY pc.product_id, il.location) SELECT o.id as order_id, o.po_number, o.order_date, o.location, c.name as customer_name, oi.id as item_id, oi.sku, oi.description, oi.quantity, ps.total_product_stock, ps.available_skus_with_stock, (SELECT MAX(il.last_updated) FROM inventory_levels il JOIN product_codes pc_sub ON il.product_code = pc_sub.code WHERE pc_sub.product_id = pc_original.product_id AND il.location = o.location) as stock_update_date FROM order_items oi JOIN orders o ON oi.order_id = o.id JOIN customers c ON o.customer_id = c.id JOIN product_codes pc_original ON oi.sku = pc_original.code JOIN ProductStock ps ON pc_original.product_id = ps.product_id AND o.location = ps.location WHERE oi.status = 'unserved' AND c.is_priority = 1 AND ps.total_product_stock >= oi.quantity ORDER BY o.order_date ASC";
    $stmt = $pdo->prepare($sql); $stmt->execute(); $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $items]);
    exit;
}

function getDashboardData($pdo) {
    $whereClauses = []; $params = [];
    if (!empty($_POST['location']) && $_POST['location'] !== 'all') { $whereClauses[] = "o.location = ?"; $params[] = $_POST['location']; }
    if (!empty($_POST['bu']) && $_POST['bu'] !== 'all') { $whereClauses[] = "o.bu = ?"; $params[] = $_POST['bu']; }
    if (!empty($_POST['customer']) && $_POST['customer'] !== 'all') { $whereClauses[] = "c.name = ?"; $params[] = $_POST['customer']; }
    $whereSql = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);
    $statsSql = "SELECT SUM(CASE WHEN oi.status = 'served' THEN oi.price ELSE 0 END) as totalServedValue, SUM(CASE WHEN oi.status = 'unserved' THEN oi.price ELSE 0 END) as totalUnservedValue, SUM(CASE WHEN oi.status = 'served' THEN oi.quantity ELSE 0 END) as totalServedQty, SUM(CASE WHEN oi.status = 'unserved' THEN oi.quantity ELSE 0 END) as totalUnservedQty, COUNT(DISTINCT CASE WHEN oi.status = 'unserved' THEN oi.sku END) as unservedSkuCount FROM orders o JOIN order_items oi ON o.id = oi.order_id LEFT JOIN customers c ON o.customer_id = c.id $whereSql";
    $stmt = $pdo->prepare($statsSql); $stmt->execute($params); $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $unservedSqlWhere = empty($whereClauses) ? "WHERE oi.status = 'unserved'" : "$whereSql AND oi.status = 'unserved'";
    $unservedSql = "SELECT oi.sku, oi.description, SUM(oi.quantity) as qty FROM orders o JOIN order_items oi ON o.id = oi.order_id LEFT JOIN customers c ON o.customer_id = c.id $unservedSqlWhere GROUP BY oi.sku, oi.description ORDER BY qty DESC LIMIT 20";
    $stmt = $pdo->prepare($unservedSql); $stmt->execute($params); $topUnserved = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $customerSqlWhere = empty($whereClauses) ? "WHERE oi.status = 'served'" : "$whereSql AND oi.status = 'served'";
    $customerSql = "SELECT c.name, SUM(oi.price) as value FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN customers c ON o.customer_id = c.id $customerSqlWhere GROUP BY c.name ORDER BY value DESC LIMIT 5";
    $stmt = $pdo->prepare($customerSql); $stmt->execute($params); $topCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $responseData = ['stats' => $stats, 'topUnserved' => $topUnserved, 'topCustomers' => $topCustomers];
    echo json_encode(['success' => true, 'data' => $responseData]);
    exit;
}

function getCustomerDashboardData($pdo) {
    $customerId = $_POST['customer_id'] ?? 0; if (!$customerId) throw new Exception("Customer ID is required.");
    $whereClauses = ["o.customer_id = ?"]; $params = [$customerId];
    if (!empty($_POST['location']) && $_POST['location'] !== 'all') { $whereClauses[] = "o.location = ?"; $params[] = $_POST['location']; }
    if (!empty($_POST['bu']) && $_POST['bu'] !== 'all') { $whereClauses[] = "o.bu = ?"; $params[] = $_POST['bu']; }
    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
    $sql = "SELECT SUM(CASE WHEN oi.status = 'served' THEN oi.price ELSE 0 END) as totalServedValue, SUM(CASE WHEN oi.status = 'unserved' THEN oi.price ELSE 0 END) as totalUnservedValue FROM orders o JOIN order_items oi ON o.id = oi.order_id $whereSql";
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $data = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function updateOrderItems($pdo) {
    $orderId = $_POST['order_id'] ?? 0; $itemsData = $_POST['items'] ?? null; $location = $_POST['location'] ?? '';
    if (empty($orderId) || empty($itemsData) || empty($location)) { throw new Exception('Missing order ID, items, or location data.'); }
    $newItems = json_decode($itemsData, true); if (json_last_error() !== JSON_ERROR_NONE) { throw new Exception('Invalid items JSON format.'); }
    $pdo->beginTransaction();
    try {
        $originalItemsStmt = $pdo->prepare("SELECT sku, quantity FROM order_items WHERE order_id = ? AND status = 'served'");
        $originalItemsStmt->execute([$orderId]); $originalServedItems = $originalItemsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$orderId]);
        $allSkus = array_column($newItems, 'sku'); $currentStocksMap = [];
        if(!empty($allSkus)){
            $placeholders = implode(',', array_fill(0, count($allSkus), '?'));
            $stockSql = "SELECT product_code, stock FROM inventory_levels WHERE location = ? AND product_code IN ($placeholders)";
            $stockStmt = $pdo->prepare($stockSql); $stockStmt->execute(array_merge([$location], $allSkus)); $currentStocksMap = $stockStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        $itemInsertStmt = $pdo->prepare("INSERT INTO order_items (order_id, sku, description, quantity, price, status, stock_snapshot) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $newServedItems = [];
        foreach ($newItems as $item) {
            $stockSnapshot = $currentStocksMap[$item['sku']] ?? 0;
            $itemInsertStmt->execute([$orderId, $item['sku'], $item['description'], $item['quantity'], $item['price'], $item['status'], $stockSnapshot]);
            if ($item['status'] === 'served') { $newServedItems[$item['sku']] = ($newServedItems[$item['sku']] ?? 0) + (int)$item['quantity']; }
        }
        $stockUpdateStmt = $pdo->prepare("UPDATE inventory_levels SET stock = stock + ? WHERE product_code = ? AND location = ?");
        $logStmt = $pdo->prepare("INSERT INTO inventory_log (product_code, location, change_amount, new_stock_level, reason, order_id, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $userId = $_SESSION['user_id'] ?? null;
        $stockCheckStmt = $pdo->prepare("SELECT stock FROM inventory_levels WHERE product_code = ? AND location = ?");
        $allSkusInvolved = array_unique(array_merge(array_keys($originalServedItems), array_keys($newServedItems)));
        foreach ($allSkusInvolved as $sku) {
            $originalQty = $originalServedItems[$sku] ?? 0; $newQty = $newServedItems[$sku] ?? 0; $adjustment = $originalQty - $newQty;
            if ($adjustment != 0) {
                $stockUpdateStmt->execute([$adjustment, $sku, $location]);
                $stockCheckStmt->execute([$sku, $location]); $newStockLevel = $stockCheckStmt->fetchColumn();
                $logStmt->execute([$sku, $location, $adjustment, $newStockLevel, 'ORDER_EDIT', $orderId, $userId]);
            }
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "Order #{$orderId} items updated successfully."]);
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
    exit;
}

function addOrder($pdo) {
    $itemsJson = $_POST['items'] ?? '[]';
    $location = $_POST['location'] ?? '';
    $bu = $_POST['bu'] ?? '';
    $discount = $_POST['discount'] ?? 0;
    $customerId = $_POST['customer_id'] ?? null;
    $customerAddress = $_POST['customer_address'] ?? '';
    $poNumber = $_POST['po_number'] ?? '';

    if (empty($poNumber) || empty($itemsJson) || empty($location) || empty($bu)) {
        throw new Exception("Missing required order data.");
    }

    $items = json_decode($itemsJson, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($items) || empty($items)) {
        throw new Exception("Invalid or empty items data provided.");
    }
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO orders (customer_id, customer_address, po_number, location, bu, discount_percentage, order_date) VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$customerId, $customerAddress, $poNumber, $location, $bu, $discount]);
        $orderId = $pdo->lastInsertId();

        $itemInsertStmt = $pdo->prepare(
            "INSERT INTO order_items (order_id, sku, description, quantity, price, status, stock_snapshot) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stockUpdateStmt = $pdo->prepare(
            "UPDATE inventory_levels SET stock = stock - ? WHERE product_code = ? AND location = ?"
        );
        $stockCheckStmt = $pdo->prepare("SELECT stock FROM inventory_levels WHERE product_code = ? AND location = ?");
        
        foreach ($items as $item) {
            // Recalculate price on the backend to be safe
            $priceStmt = $pdo->prepare("SELECT sales_price FROM product_codes WHERE code = ?");
            $priceStmt->execute([$item['sku']]);
            $sales_price = $priceStmt->fetchColumn();
            $price = ($sales_price ?: 0) * $item['quantity'];
            if ($discount > 0) {
                $price -= $price * ($discount / 100);
            }

            $stockCheckStmt->execute([$item['sku'], $location]);
            $currentStock = $stockCheckStmt->fetchColumn();
            if ($currentStock === false) $currentStock = 0;

            $status = ($currentStock >= $item['quantity']) ? 'served' : 'unserved';
            
            $itemInsertStmt->execute([
                $orderId, $item['sku'], $item['description'], $item['quantity'], $price, $status, $currentStock
            ]);

            if ($status === 'served') {
                $stockUpdateStmt->execute([$item['quantity'], $item['sku'], $location]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Order processed.', 'order_id' => $orderId]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    exit;
}

// --- ADMIN FUNCTIONS ---
function getOrdersForExport($pdo) {
    $year = $_POST['year'] ?? 0; $month = $_POST['month'] ?? 0; $location = $_POST['location'] ?? 'all';
    if (empty($year) || empty($month)) { throw new Exception('Invalid year or month provided.'); }
    $sql = "SELECT o.*, c.name as customer_name FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE YEAR(o.order_date) = ? AND MONTH(o.order_date) = ?";
    $params = [$year, $month];
    if ($location !== 'all') { $sql .= " AND o.location = ?"; $params[] = $location; }
    $sql .= " ORDER BY o.order_date ASC";
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];
    foreach ($orders as $order) {
        $itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $itemStmt->execute([$order['id']]); $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        $result[] = ['customer' => ['name' => $order['customer_name'] ?? 'N/A', 'address' => $order['customer_address'], 'poNumber' => $order['po_number']], 'date' => $order['order_date'], 'location' => $order['location'], 'items' => $items];
    }
    echo json_encode(['success' => true, 'data' => $result]);
    exit;
}

function addCustomer($pdo) {
    $name = $_POST['name'] ?? ''; if (empty($name)) { throw new Exception('Customer name is required.'); }
    $stmt = $pdo->prepare("INSERT INTO customers (name) VALUES (?)");
    $stmt->execute([$name]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

function deleteCustomer($pdo) {
    $id = $_POST['id'] ?? 0; if (empty($id)) { throw new Exception('Customer ID is required.'); }
    $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

function toggleCustomerPriority($pdo) {
    $id = $_POST['id'] ?? 0; $is_priority = $_POST['is_priority'] ?? 0;
    if (empty($id)) { throw new Exception('Customer ID is required.'); }
    $stmt = $pdo->prepare("UPDATE customers SET is_priority = ? WHERE id = ?");
    $stmt->execute([(int)$is_priority, $id]);
    echo json_encode(['success' => true]);
    exit;
}

function addProduct($pdo) {
    $sku = $_POST['sku'] ?? ''; $description = $_POST['description'] ?? ''; $bu = $_POST['bu'] ?? 'Health'; $stockQty = $_POST['stock'] ?? 0; $location = $_POST['location'] ?? '';
    if (empty($sku) || empty($description) || empty($location)) { throw new Exception('SKU, Description, and Location are required.'); }
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT id FROM products WHERE description = ? AND bu = ?");
    $stmt->execute([$description, $bu]); $productId = $stmt->fetchColumn();
    if (!$productId) {
        $stmt = $pdo->prepare("INSERT INTO products (description, bu) VALUES (?, ?)");
        $stmt->execute([$description, $bu]); $productId = $pdo->lastInsertId();
    }
    $stmt = $pdo->prepare("INSERT INTO product_codes (product_id, code, type) VALUES (?, ?, 'sku') ON DUPLICATE KEY UPDATE product_id = VALUES(product_id)");
    $stmt->execute([$productId, $sku]);
    $stockStmt = $pdo->prepare("INSERT INTO inventory_levels (product_code, location, stock) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE stock = VALUES(stock)");
    $stockStmt->execute([$sku, $location, (int)$stockQty]);
    $pdo->commit();
    echo json_encode(['success' => true]);
    exit;
}

function bulkAddProducts($pdo) {
    $data = $_POST['data'] ?? ''; if(empty($data)) { throw new Exception('No data provided.'); }
    $lines = explode("\n", trim($data)); $productsAdded = 0; $codesAdded = 0; $validBUs = ['Health', 'Hygiene', 'Nutri'];
    $pdo->beginTransaction();
    $productStmt = $pdo->prepare("INSERT INTO products (description, bu, is_promo) VALUES (?, ?, ?)");
    $findProductStmt = $pdo->prepare("SELECT id FROM products WHERE description = ? AND bu = ?");
    $updatePromoStmt = $pdo->prepare("UPDATE products SET is_promo = ? WHERE id = ?");
    $codeStmt = $pdo->prepare("INSERT INTO product_codes (product_id, code, type, pieces_per_case) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE pieces_per_case = VALUES(pieces_per_case)");
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $parts = preg_split('/\s+/', trim($line), -1, PREG_SPLIT_NO_EMPTY); if (count($parts) < 3) continue;
        $isPromo = false; $piecesPerCase = 1; $lastPart = $parts[count($parts) - 1];
        if (is_numeric($lastPart)) { $piecesPerCase = (int)array_pop($parts); }
        if (count($parts) >= 2) {
            $promoCheck = strtolower($parts[count($parts) - 2] . ' ' . $parts[count($parts) - 1]);
            if ($promoCheck === 'promo sku') { $isPromo = true; array_pop($parts); array_pop($parts); } 
            elseif ($promoCheck === 'regular sku') { $isPromo = false; array_pop($parts); array_pop($parts); }
        }
        $bu = 'Health'; $startIndex = 0; $firstPart = ucfirst(strtolower($parts[0]));
        if (in_array($firstPart, $validBUs)) { $bu = $firstPart; $startIndex = 1; }
        $numericCodes = []; $descriptionParts = []; $foundDescription = false;
        for ($i = $startIndex; $i < count($parts); $i++) { if (is_numeric($parts[$i]) && !$foundDescription) { $numericCodes[] = $parts[$i]; } else { $foundDescription = true; $descriptionParts[] = $parts[$i]; } }
        $description = implode(' ', $descriptionParts);
        if (empty($description) || empty($numericCodes)) continue;
        $findProductStmt->execute([$description, $bu]); $productId = $findProductStmt->fetchColumn();
        if (!$productId) { $productStmt->execute([$description, $bu, $isPromo]); $productId = $pdo->lastInsertId(); $productsAdded++; } else { $updatePromoStmt->execute([$isPromo, $productId]); }
        foreach (array_unique($numericCodes) as $code) {
            $type = strlen((string)$code) > 8 ? 'barcode' : 'sku';
            $codeStmt->execute([$productId, $code, $type, $piecesPerCase]);
            if ($codeStmt->rowCount() > 0) $codesAdded++;
        }
    }
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "$productsAdded products and $codesAdded codes processed."]);
    exit;
}

function bulkUpdateStock($pdo) {
    $data = $_POST['data'] ?? ''; $location = $_POST['location'] ?? '';
    if(empty($data) || empty($location)) { throw new Exception('No stock data or location provided.'); }
    $lines = explode("\n", trim($data));
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE inventory_levels SET stock = 0 WHERE location = ?")->execute([$location]);
        $stockUpdateStmt = $pdo->prepare("INSERT INTO inventory_levels (product_code, location, stock) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE stock = VALUES(stock)");
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $parts = preg_split('/\s+/', trim($line), -1, PREG_SPLIT_NO_EMPTY); if (count($parts) < 3) continue;
            array_pop($parts); $quantity = str_replace(',', '', array_pop($parts)); $code = array_shift($parts);
            if (empty($quantity) || empty($code)) continue;
            $stockUpdateStmt->execute([$code, $location, (int)$quantity]);
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "Inventory for '{$location}' successfully updated."]);
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
    exit;
}

function deleteCode($pdo) {
    $code = $_POST['code'] ?? ''; if (empty($code)) { throw new Exception('Code (SKU/Barcode) is required.'); }
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT product_id FROM product_codes WHERE code = ?");
    $stmt->execute([$code]); $productId = $stmt->fetchColumn();
    $pdo->prepare("DELETE FROM product_codes WHERE code = ?")->execute([$code]);
    $pdo->prepare("DELETE FROM inventory_levels WHERE product_code = ?")->execute([$code]);
    if ($productId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_codes WHERE product_id = ?");
        $stmt->execute([$productId]);
        if ($stmt->fetchColumn() == 0) { $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$productId]); }
    }
    $pdo->commit();
    echo json_encode(['success' => true]);
    exit;
}

function bulkAddAliases($pdo) {
    $data = $_POST['data'] ?? ''; if (empty($data)) { throw new Exception('No alias data provided.'); }
    $lines = explode("\n", trim($data)); $linkedCount = 0; $notFound = [];
    $pdo->beginTransaction();
    $findStmt = $pdo->prepare("SELECT product_id FROM product_codes WHERE code = ? AND type = 'barcode'");
    $insertStmt = $pdo->prepare("INSERT INTO product_codes (product_id, code, type) VALUES (?, ?, 'sku') ON DUPLICATE KEY UPDATE product_id = VALUES(product_id)");
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        $parts = preg_split('/\s+/', trim($line), -1, PREG_SPLIT_NO_EMPTY); if (count($parts) < 2) continue;
        $customerSku = array_shift($parts); $barcode = array_pop($parts);
        if (!is_numeric($customerSku) || !is_numeric($barcode)) { continue; }
        $findStmt->execute([$barcode]); $productId = $findStmt->fetchColumn();
        if ($productId) {
            $insertStmt->execute([$productId, $customerSku]);
            if ($insertStmt->rowCount() > 0) $linkedCount++;
        } else { $notFound[] = $barcode; }
    }
    $pdo->commit();
    $message = "$linkedCount customer SKUs were successfully linked.";
    if (!empty($notFound)) { $message .= " Barcodes not found: " . implode(', ', array_unique($notFound)); }
    echo json_encode(['success' => true, 'message' => $message]);
    exit;
}

function getUnlinkedSkus($pdo) {
    $location = $_POST['location'] ?? 'Davao';
    $sql = "SELECT p.description, pc.code AS sku, (SELECT il.stock FROM inventory_levels il WHERE il.product_code = pc.code AND il.location = ?) as current_stock FROM products p JOIN product_codes pc ON p.id = pc.product_id WHERE p.id IN (SELECT product_id FROM product_codes GROUP BY product_id HAVING SUM(CASE WHEN type = 'barcode' THEN 1 ELSE 0 END) = 0) AND pc.type = 'sku' ORDER BY p.description;";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$location]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $results]);
    exit;
}

?