<?php
// Backfill script — links existing orders to retailers via product lookup
// Access: http://localhost/MedCare/api/backfill_orders.php
// SAFE: Only adds/updates retailer_id and shop_name fields; does NOT delete anything
require_once 'config.php';
require_once 'firebase.php';

header('Content-Type: application/json');

$orders   = FirebaseDB::get('orders');
$products = FirebaseDB::get('products');
$users    = FirebaseDB::get('users');

if (!$orders) {
    echo json_encode(['error' => 'No orders found']);
    exit();
}

// Build product -> retailer map
$productRetailerMap = [];
if ($products) {
    foreach ($products as $pid => $product) {
        $productRetailerMap[$pid] = [
            'retailer_id' => $product['retailer_id'] ?? '',
            'shop_name'   => $product['shop_name']   ?? '',
        ];
    }
}

// Build retailer uid -> shop_name map
$retailerShopMap = [];
if ($users) {
    foreach ($users as $uid => $user) {
        if (($user['role'] ?? '') === 'retailer') {
            $retailerShopMap[$uid] = $user['shop_name'] ?? '';
        }
    }
}

$updated = 0;
$skipped = 0;
$log     = [];

foreach ($orders as $orderId => $order) {
    // Skip if already has retailer_id
    if (!empty($order['retailer_id'])) {
        $skipped++;
        $log[] = "SKIP $orderId — already has retailer_id: " . $order['retailer_id'];
        continue;
    }

    // Try to find retailer from items
    $foundRetailerId = '';
    $foundShopName   = '';

    if (!empty($order['items']) && is_array($order['items'])) {
        foreach ($order['items'] as $item) {
            $productId = $item['id'] ?? '';
            if ($productId && isset($productRetailerMap[$productId])) {
                $rid = $productRetailerMap[$productId]['retailer_id'];
                if (!empty($rid)) {
                    $foundRetailerId = $rid;
                    $foundShopName   = $productRetailerMap[$productId]['shop_name'];
                    break;
                }
            }
        }
    }

    if (empty($foundRetailerId)) {
        $skipped++;
        $log[] = "SKIP $orderId — cannot determine retailer (products may be mock/deleted)";
        continue;
    }

    // Patch just the retailer_id and shop_name onto the order
    $patch = [
        'retailer_id' => $foundRetailerId,
        'shop_name'   => $foundShopName ?: ($retailerShopMap[$foundRetailerId] ?? ''),
    ];

    $ok = FirebaseDB::update('orders/' . $orderId, $patch);
    if ($ok !== false) {
        $updated++;
        $log[] = "UPDATED $orderId — retailer: $foundRetailerId, shop: $patch[shop_name]";
    } else {
        $log[] = "FAILED  $orderId — Firebase update error";
    }
}

echo json_encode([
    'status'  => 'done',
    'updated' => $updated,
    'skipped' => $skipped,
    'log'     => $log,
], JSON_PRETTY_PRINT);
?>
