<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

require_once 'config.php';
require_once 'firebase.php';
require_once 'middleware.php';

ob_clean();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

$auth = authorize('customer');
$uid  = $auth['uid'];

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true) ?? [];

$items   = $data['items']   ?? [];
$total   = $data['total']   ?? 0;
$address = $data['address'] ?? '';

if (empty($items)) {
    http_response_code(400);
    echo json_encode(['error' => 'Cart is empty']);
    exit();
}

// ── Enrich items with retailer_id from product DB ────────────────
// cart items have: id, name, price, quantity, rx_required, shop_name
// We look up each product to get the canonical retailer_id
$enrichedItems = [];
$retailerUid   = '';
$shopName      = '';

foreach ($items as $item) {
    $productId = $item['id'] ?? '';
    if ($productId) {
        $product = FirebaseDB::get('products/' . $productId);
        if ($product) {
            $item['retailer_id'] = $product['retailer_id'] ?? '';
            $item['shop_name']   = $product['shop_name']   ?? ($item['shop_name'] ?? '');
            // Use first item's retailer as the order owner (single-retailer cart)
            if (empty($retailerUid) && !empty($item['retailer_id'])) {
                $retailerUid = $item['retailer_id'];
                $shopName    = $item['shop_name'];
            }
        }
    }
    $enrichedItems[] = $item;
}

// ── Fetch customer name + phone for delivery details ─────────────
$customerProfile  = FirebaseDB::get('users/' . $uid);
$customerName     = $customerProfile['name']  ?? '';
$customerPhone    = $customerProfile['phone'] ?? '';

$orderData = [
    'customer_id'    => $uid,
    'customer_name'  => $customerName,
    'customer_phone' => $customerPhone,
    'retailer_id'    => $retailerUid,   // ← key field for retailer filtering
    'shop_name'      => $shopName,
    'items'          => $enrichedItems,
    'total_amount'   => floatval($total),
    'address'        => htmlspecialchars(trim($address)),
    'status'         => 'Processing',
    'timestamp'      => time(),
];

// Use the idToken from the auth header (passed through middleware)
// We need to push with admin access — use server key approach
$result = FirebaseDB::push('orders', $orderData);

if ($result) {
    echo json_encode(['message' => 'Order placed successfully!', 'order_id' => $result['name']]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Could not process checkout. Please try again.']);
}
?>
