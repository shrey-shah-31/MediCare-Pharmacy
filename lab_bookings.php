<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once 'config.php';
require_once 'firebase.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $role = $_GET['role'] ?? 'customer';
    $userId = $_GET['customer_id'] ?? null;
    $shopName = $_GET['shop_name'] ?? '';
    $labUid = $_GET['uid'] ?? null;
    
    $bookingsDb = FirebaseDB::get('lab_bookings');
    $bookingsList = [];
    
    if ($bookingsDb) {
        foreach ($bookingsDb as $key => $b) {
            $b['id'] = $key;
            if ($role === 'retailer') {
                // Filter by lab_id if available, otherwise fall back to shop_name
                if (!empty($labUid) && isset($b['lab_id'])) {
                    if ($b['lab_id'] === $labUid) {
                        $bookingsList[] = $b;
                    }
                } elseif (!empty($shopName) && isset($b['lab_name']) && $b['lab_name'] === $shopName) {
                    $bookingsList[] = $b;
                } elseif (empty($labUid) && empty($shopName)) {
                    $bookingsList[] = $b;
                }
            } else if ($role === 'customer' && $b['customer_id'] === $userId) {
                $bookingsList[] = $b;
            }
        }
    }
    
    // Sort descending by timestamp
    usort($bookingsList, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    echo json_encode($bookingsList);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Admin route to update status
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['booking_id']) || !isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing booking_id or status']);
        exit();
    }
    
    $id = $data['booking_id'];
    $status = $data['status'];
    
    // Update just the status field using PATCH (merge)
    $result = FirebaseDB::update("lab_bookings/$id", ['status' => $status]);
    
    if ($result !== false) {
        echo json_encode(['message' => 'Status updated']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
    exit();
}
?>
