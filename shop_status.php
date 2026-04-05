<?php
// ============================================================
// shop_status.php — Toggle retailer shop online/offline status
// ============================================================
require_once 'config.php';
require_once 'firebase.php';
require_once 'middleware.php';

// Support both Retailer and Laboratory roles for status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = authorize(); // Let middleware handle valid token check
    if (!in_array($auth['role'], ['retailer', 'laboratory'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit();
    }
    
    $uid = $auth['uid'];
    $data = json_decode(file_get_contents('php://input'), true);

    // Update shop online/offline status if provided
    if (isset($data['status'])) {
        $status = $data['status'] === 'offline' ? 'offline' : 'online';
        FirebaseDB::set("users/{$uid}/shop_status", $status);
        echo json_encode(['success' => true, 'status' => $status]);
        exit();
    }

    // Update home collection toggle if provided
    if (isset($data['home_collection'])) {
        $hc = $data['home_collection'] ? true : false;
        FirebaseDB::set("users/{$uid}/home_collection", $hc);
        echo json_encode(['success' => true, 'home_collection' => $hc]);
        exit();
    }

    http_response_code(400);
    echo json_encode(['error' => 'No valid field to update']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $auth = authorize();
    if (!in_array($auth['role'], ['retailer', 'laboratory'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit();
    }
    
    $uid = $auth['uid'];
    $status = FirebaseDB::get("users/{$uid}/shop_status");
    $hc = FirebaseDB::get("users/{$uid}/home_collection");
    echo json_encode([
        'status' => $status === 'offline' ? 'offline' : 'online',
        'home_collection' => $hc === true || $hc === 'true'
    ]);
    exit();
}
