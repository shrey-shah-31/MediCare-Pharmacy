<?php
// Debug script - check actual Firebase order structure
// Access: http://localhost/MedCare/api/debug_orders.php
require_once 'config.php';
require_once 'firebase.php';

header('Content-Type: application/json');

// Get all orders
$orders = FirebaseDB::get('orders');

if (!$orders) {
    echo json_encode(['error' => 'No orders found', 'orders' => null]);
    exit();
}

// Sample first 5 orders with their structure
$sample = [];
$count = 0;
foreach ($orders as $key => $order) {
    if ($count >= 5) break;
    $sample[$key] = [
        'has_customer_id'  => isset($order['customer_id']),
        'has_retailer_id'  => isset($order['retailer_id']),
        'has_shop_name'    => isset($order['shop_name']),
        'has_vendorId'     => isset($order['vendorId']),
        'customer_id'      => $order['customer_id'] ?? 'MISSING',
        'retailer_id'      => $order['retailer_id'] ?? 'MISSING',
        'shop_name'        => $order['shop_name'] ?? 'MISSING',
        'status'           => $order['status'] ?? 'MISSING',
        'timestamp'        => $order['timestamp'] ?? 'MISSING',
        'items_count'      => isset($order['items']) ? count($order['items']) : 0,
        'first_item_keys'  => isset($order['items'][0]) ? array_keys($order['items'][0]) : [],
        'first_item_sample'=> isset($order['items'][0]) ? $order['items'][0] : 'NO ITEMS',
    ];
    $count++;
}

// Get all users (to check retailer shop names)
$users = FirebaseDB::get('users');
$retailers = [];
if ($users) {
    foreach ($users as $uid => $user) {
        if (($user['role'] ?? '') === 'retailer') {
            $retailers[$uid] = [
                'name'      => $user['name'] ?? 'N/A',
                'shop_name' => $user['shop_name'] ?? 'MISSING',
                'role'      => $user['role'] ?? 'N/A',
            ];
        }
    }
}

echo json_encode([
    'total_orders'   => count($orders),
    'order_samples'  => $sample,
    'retailers'      => $retailers,
], JSON_PRETTY_PRINT);
?>
