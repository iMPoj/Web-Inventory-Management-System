 <?php
session_start();
require 'db_connect.php';

$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$orderId) {
    header('Location: index.php');
    exit;
}

// Fetch the main order details
$orderStmt = $pdo->prepare("SELECT o.*, c.name as customer_name FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.id = ?");
$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    echo "Order not found.";
    exit;
}

// Fetch all items for this order
$itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
$itemStmt->execute([$orderId]);
$items = $itemStmt->fetchAll();

// Get all product codes and their related product info for SKU swapping
$products_by_sku = [];
if (!empty($items)) {
    $itemSkus = array_unique(array_column($items, 'sku'));
    $placeholders = implode(',', array_fill(0, count($itemSkus), '?'));
    
    $sql = "SELECT DISTINCT product_id FROM product_codes WHERE code IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($itemSkus);
    $productIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($productIds)) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $sql = "SELECT p.id, p.description, pc.code, pc.type FROM products p JOIN product_codes pc ON p.id = pc.product_id WHERE p.id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($productIds);
        $related_products_data = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);

        foreach ($related_products_data as $productId => $codes) {
            $product_info = ['description' => $codes[0]['description'], 'codes' => $codes];
            foreach ($product_info['codes'] as $code) {
                $products_by_sku[$code['code']] = ['productId' => $productId, 'description' => $product_info['description'], 'allSkus' => $product_info['codes']];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Order - PO #<?php echo htmlspecialchars($order['po_number']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-stone-100">
    <div id="loading-overlay" class="modal-backdrop" style="display: none; z-index: 9999;">
        <div class="animate-spin rounded-full h-16 w-16 border-t-2 border-b-2 border-white"></div>
    </div>
    
    <div class="container mx-auto p-4 md:p-8 max-w-6xl">
        <header class="mb-6 flex justify-between items-center">
            <?php
                $back_context = htmlspecialchars($_GET['context'] ?? 'dashboard');
                $back_link_href = "index.php#" . $back_context;
            ?>
            <a href="<?php echo $back_link_href; ?>" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                Back to Main App
            </a>
            
            <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'encoder')): ?>
                <div class="flex gap-4">
                    <button id="editOrderBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M17.414 2.586a2 2 0 00-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 000-2.828z" /><path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4a1 1 0 010 2H4v10h10v-4a1 1 0 112 0v4a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd" /></svg>
                        <span>Edit</span>
                    </button>
                    <button id="saveChangesBtn" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md flex items-center gap-2 hidden">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                        <span>Save Changes</span>
                    </button>
                    <button id="cancelChangesBtn" class="bg-slate-500 hover:bg-slate-600 text-white font-bold py-2 px-4 rounded-md hidden">
                        <span>Cancel</span>
                    </button>
                </div>
            <?php endif; ?>
        </header>

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex flex-col sm:flex-row justify-between items-start border-b pb-4 mb-4">
                 <div>
                    <h1 class="text-2xl font-bold text-stone-900">Order Details</h1>
                    <p class="text-stone-600">Purchase Order #<?php echo htmlspecialchars($order['po_number']); ?></p>
                    <p class="text-stone-600">Customer: <?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></p>
                </div>
                <div class="text-sm text-stone-700 mt-2 sm:mt-0 sm:text-right">
                    <p><strong>Date:</strong> <?php echo date("F j, Y", strtotime($order['order_date'])); ?></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($order['location']); ?></p>
                    <p><strong>Business Unit:</strong> <?php echo htmlspecialchars($order['bu']); ?></p>
                </div>
            </div>
            
            <div class="border rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-stone-200">
                    <thead class="bg-stone-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-stone-500 uppercase">Description / SKU</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-stone-500 uppercase">Quantity</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-stone-500 uppercase">Price</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-stone-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody id="orderItemsTableBody" class="bg-white divide-y divide-stone-200">
                        <?php foreach ($items as $item): ?>
                            <tr class="item-row text-sm" data-item-id="<?php echo $item['id']; ?>" data-original-sku="<?php echo htmlspecialchars($item['sku']); ?>" data-original-status="<?php echo htmlspecialchars($item['status']); ?>">
                                <td class="px-4 py-2">
                                    <p class="font-medium text-slate-800"><?php echo htmlspecialchars($item['description']); ?></p>
                                    <p class="sku-text-display mt-1 font-mono text-sm text-slate-500"><?php echo htmlspecialchars($item['sku']); ?></p>
                                    <select class="sku-select mt-1 block w-full rounded-md border-slate-300 shadow-sm text-xs hidden" disabled></select>
                                </td>
                                <td class="px-4 py-2">
                                    <input type="number" value="<?php echo htmlspecialchars($item['quantity']); ?>" class="quantity-input w-20 rounded-md border-slate-300 shadow-sm text-sm" disabled>
                                </td>
                                <td class="px-4 py-2">
                                    <input type="number" step="0.01" value="<?php echo htmlspecialchars($item['price']); ?>" class="price-input w-32 rounded-md border-slate-300 shadow-sm text-sm" disabled>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <button class="status-toggle-btn px-2 py-1 text-xs leading-5 font-semibold rounded-full" disabled></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        window.orderId = <?php echo json_encode($orderId); ?>;
        window.orderLocation = <?php echo json_encode($order['location']); ?>;
        window.productsBySku = <?php echo json_encode($products_by_sku); ?>;
    </script>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script type="module" src="js/view_order.js"></script>
</body>
</html