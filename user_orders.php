<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once 'config.php';
require_once 'firebase.php';
require_once 'middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit();
}

$auth = authorize();
$uid  = $auth['uid'];
$role = $auth['role'];

$allOrders = FirebaseDB::get('orders');

if (!$allOrders) {
    echo json_encode([]);
    exit();
}

// For retailer: get their shop_name and city for matching
$retailerShopName = '';
$retailerCity     = '';
if ($role === 'retailer') {
    $userProfile      = FirebaseDB::get('users/' . $uid);
    $retailerShopName = strtolower(trim($userProfile['shop_name'] ?? ''));
    $retailerCity     = strtolower(trim($userProfile['city'] ?? ''));
}

// Build product->retailer map to resolve unlinked orders
$productMap = [];
if ($role === 'retailer') {
    $products = FirebaseDB::get('products');
    if ($products) {
        foreach ($products as $pid => $product) {
            if (!empty($product['retailer_id'])) {
                $productMap[$pid] = $product['retailer_id'];
            }
        }
    }
}

// Helper: check if a string looks like a real Firebase UID (not a JWT)
function isValidFirebaseUid($str) {
    // Firebase UIDs are 28 chars, alphanumeric only (no dots or dashes in bulk)
    // JWTs start with 'eyJ' and contain dots
    if (empty($str) || strpos($str, '.') !== false || strpos($str, 'eyJ') === 0) {
        return false;
    }
    return strlen($str) >= 20 && strlen($str) <= 36;
}

// Enrich an order with real customer name + phone from Firebase profile
function enrichOrderWithCustomer($order) {
    $customerId = $order['customer_id'] ?? '';
    if (!isValidFirebaseUid($customerId)) {
        return $order; // Can't look up – it's a legacy JWT or invalid
    }
    // Only enrich if name/phone are missing or are generic placeholders
    if (!empty($order['customer_phone']) && $order['customer_phone'] !== 'Not provided') {
        return $order; // Already has real phone
    }
    $profile = FirebaseDB::get('users/' . $customerId);
    if (!$profile) return $order;

    if (empty($order['customer_name']) || $order['customer_name'] === 'Customer') {
        $order['customer_name'] = $profile['profile']['name'] ?? $profile['name'] ?? $order['customer_name'] ?? 'Customer';
    }
    if (empty($order['customer_phone']) || $order['customer_phone'] === 'Not provided') {
        $order['customer_phone'] = $profile['profile']['phone'] ?? $profile['phone'] ?? 'Not provided';
    }
    return $order;
}

$ordersList = [];
foreach ($allOrders as $key => $order) {
    if ($role === 'retailer') {
        if (empty($uid)) continue;

        $matched = false;

        // Method 1: retailer_id on order
        $orderRetailerId = $order['retailer_id'] ?? '';
        if (!empty($orderRetailerId) && $orderRetailerId === $uid) {
            $matched = true;
        }

        // Method 2: shop_name match
        if (!$matched && !empty($retailerShopName)) {
            $orderShopName = strtolower(trim($order['shop_name'] ?? ''));
            if (!empty($orderShopName) && $orderShopName === $retailerShopName) {
                $matched = true;
            }
        }

        // Method 3: per-item retailer_id / vendorId / product map
        if (!$matched && !empty($order['items']) && is_array($order['items'])) {
            foreach ($order['items'] as $item) {
                $pid = $item['id'] ?? '';
                if (!empty($item['retailer_id']) && $item['retailer_id'] === $uid) { $matched = true; break; }
                if (!empty($item['vendorId'])    && $item['vendorId']    === $uid) { $matched = true; break; }
                if (!empty($pid) && isset($productMap[$pid]) && $productMap[$pid] === $uid) { $matched = true; break; }
            }
        }

        // Method 4: city fallback for fully unlinked orders
        if (!$matched && empty($orderRetailerId) && empty($order['shop_name']) && !empty($retailerCity)) {
            $customerId = $order['customer_id'] ?? '';
            if (isValidFirebaseUid($customerId)) {
                $customerProfile = FirebaseDB::get('users/' . $customerId);
                $customerCity    = strtolower(trim($customerProfile['city'] ?? ''));
                if (!empty($customerCity) && $customerCity === $retailerCity) {
                    $matched = true;
                }
            }
        }

        if ($matched) {
            $order['id']     = $key;
            $order['has_rx'] = !empty($order['rx_image']);
            unset($order['rx_image']);
            // ── Enrich with real customer name + phone ──────────────
            $order = enrichOrderWithCustomer($order);
            $ordersList[] = $order;
        }

    } else if ($role === 'delivery') {
        if ($order['status'] === 'Shipped') {
            $order['id']     = $key;
            $order['has_rx'] = !empty($order['rx_image']);
            unset($order['rx_image']);
            $ordersList[] = $order;
        }
    } else if (($order['customer_id'] ?? '') === $uid) {
        $order['id'] = $key;
        $ordersList[] = $order;
    }
}

usort($ordersList, function($a, $b) {
    return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
});

echo json_encode($ordersList);
?>
