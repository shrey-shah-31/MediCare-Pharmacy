<?php
require_once 'config.php';
require_once 'firebase.php';
require_once 'middleware.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $products = FirebaseDB::get('products');
    if (!$products) { echo json_encode([]); exit(); }
    
    // Determine intent without pulling params
    $intentRole = $_GET['role'] ?? '';
    
    // Establishing authorization based on caller context
    $authContext = null;
    if ($intentRole === 'retailer') {
        $authContext = authorize('retailer');
    }
    
    // Establish location-based filtering
    $targetCity = $_GET['city'] ?? null;
    $customerId = $_GET['customer_id'] ?? null;
    
    $uid = $authContext['uid'] ?? '';
    $role = $authContext['role'] ?? '';
    $validRetailerIds = null; // null means no filtering (e.g. for guests)
    
    $allUsers = FirebaseDB::get('users') ?: [];
    
    // If a customer ID is provided, inherit city from their profile
    if (!$targetCity && $customerId) {
        $customer = $allUsers[$customerId] ?? null;
        if ($customer && !empty($customer['city'])) {
            $targetCity = $customer['city'];
        }
    }
    
    // Build a list of all retailers in the target city
    if ($targetCity) {
        $targetCity = strtolower(trim($targetCity));
        if ($allUsers) {
            $validRetailerIds = [];
            foreach ($allUsers as $uid_iter => $user) {
                if (isset($user['role']) && $user['role'] === 'retailer') {
                    $retailerCity = strtolower(trim($user['city'] ?? ''));
                    // Exact match required
                    if ($retailerCity === $targetCity) {
                        $validRetailerIds[] = $uid_iter;
                    }
                }
            }
        }
    }
    
    $productList = [];
    foreach ($products as $key => $product) {
        $retailerId = $product['retailer_id'] ?? '';
        
        if ($role === 'retailer') {
            if (empty($uid)) continue;
            if ($retailerId !== $uid) {
                continue;
            }
        } else {
            // Enforce strict local filtering if validRetailerIds is constructed
            if (is_array($validRetailerIds)) {
                // Skip products originating from non-local retailers
                if (empty($retailerId) || !in_array($retailerId, $validRetailerIds)) {
                    continue;
                }
            }
        }
        
        $product['id'] = $key;
        
        // Attach location details for the frontend
        if (!empty($retailerId) && isset($allUsers[$retailerId])) {
            $product['shop_city'] = $allUsers[$retailerId]['city'] ?? '';
            $product['shop_state'] = $allUsers[$retailerId]['state'] ?? '';
            $product['shop_status'] = $allUsers[$retailerId]['shop_status'] ?? 'online';
        } else {
            $product['shop_city'] = '';
            $product['shop_state'] = '';
            $product['shop_status'] = 'online';
        }
        
        $productList[] = $product;
    }
    echo json_encode($productList);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = authorize('retailer');
    
    $data = json_decode(file_get_contents('php://input'), true);
    $requiredFields = ['name', 'category', 'price', 'description', 'rx_required'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            http_response_code(422);
            echo json_encode(['error' => "Missing '$field' field"]);
            exit();
        }
    }
    $productData = [
        'name'         => $data['name'],
        'category'     => $data['category'],
        'price'        => floatval($data['price']),
        'old_price'    => isset($data['old_price']) ? floatval($data['old_price']) : null,
        'description'  => $data['description'],
        'rx_required'  => filter_var($data['rx_required'], FILTER_VALIDATE_BOOLEAN),
        'stock'        => isset($data['stock']) ? intval($data['stock']) : 0,
        'rating'       => 5.0,
        'rating_count' => 0,
        'icon'         => htmlspecialchars($data['icon'] ?? 'fa-pills'),
        'shop_name'    => htmlspecialchars($data['shop_name'] ?? 'MediCare Pharmacy'),
        'retailer_id'  => $auth['uid'] // Hardcode ownership from token directly
    ];
    $result = FirebaseDB::push('products', $productData);
    if ($result) {
        echo json_encode(['message' => 'Product added', 'name' => $result['name']]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add product to database']);
    }
    exit();
}

// PATCH — update price / stock / name
if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $auth = authorize('retailer');
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = $data['id'] ?? null;
    if (!$id) { http_response_code(422); echo json_encode(['error' => 'Missing product id']); exit(); }

    $existing = FirebaseDB::get("products/{$id}");
    if (!$existing) { http_response_code(404); echo json_encode(['error' => 'Product not found']); exit(); }
    
    // Strict isolation enforcement:
    if (($existing['retailer_id'] ?? '') !== $auth['uid']) {
        http_response_code(403); echo json_encode(['error' => 'Forbidden: You do not own this product']); exit();
    }

    if (isset($data['price']))       $existing['price']       = floatval($data['price']);
    if (isset($data['stock']))       $existing['stock']       = intval($data['stock']);
    if (isset($data['name']))        $existing['name']        = htmlspecialchars($data['name']);
    if (isset($data['description'])) $existing['description'] = htmlspecialchars($data['description']);

    $ok = FirebaseDB::set("products/{$id}", $existing);
    echo json_encode($ok ? ['message' => 'Updated'] : ['error' => 'Update failed']);
    exit();
}

// DELETE — remove product
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $auth = authorize('retailer');
    
    $data = json_decode(file_get_contents('php://input'), true);
    $id   = $data['id'] ?? null;
    if (!$id) { http_response_code(422); echo json_encode(['error' => 'Missing product id']); exit(); }
    
    $existing = FirebaseDB::get("products/{$id}");
    if (!$existing) { http_response_code(404); echo json_encode(['error' => 'Product not found']); exit(); }
    
    // Strict isolation enforcement:
    if (($existing['retailer_id'] ?? '') !== $auth['uid']) {
        http_response_code(403); echo json_encode(['error' => 'Forbidden: You do not own this product']); exit();
    }

    $ok = FirebaseDB::delete("products/{$id}");
    echo json_encode($ok ? ['message' => 'Deleted'] : ['error' => 'Delete failed']);
    exit();
}
?>


