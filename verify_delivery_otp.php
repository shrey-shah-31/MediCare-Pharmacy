<?php
require_once 'config.php';
require_once 'firebase.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$orderId = $data['order_id'] ?? null;
$otpInput = $data['otp'] ?? '';
$role = $data['role'] ?? '';

// Basic auth check for delivery partner role
if (!$orderId || empty($otpInput) || $role !== 'delivery') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized or missing data']);
    exit();
}

$orderPath = "orders/{$orderId}";
$order = FirebaseDB::get($orderPath);

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit();
}

if ($order['status'] !== 'Shipped') {
    http_response_code(400);
    echo json_encode(['error' => 'Order is not in Shipped (out for delivery) status']);
    exit();
}

$currentTime = time();

if ($currentTime > $order['otp_expires_at']) {
    http_response_code(400);
    echo json_encode(['error' => 'OTP expired. Please ask the retailer or admin to regenerate.']);
    exit();
}

if ($order['otp_attempts'] >= 5) {
    http_response_code(403);
    echo json_encode(['error' => 'Too many failed OTP attempts. Contact support.']);
    exit();
}

if ($order['delivery_otp'] === $otpInput) {
    // Success! Update to Delivered
    $order['status'] = 'Delivered';
    $order['delivery_otp'] = null;
    $order['otp_generated_at'] = null;
    $order['otp_expires_at'] = null;
    
    FirebaseDB::set($orderPath, $order);
    
    FirebaseDB::push("delivery_logs", [
        'order_id' => $orderId,
        'action' => 'verified_success',
        'timestamp' => $currentTime
    ]);
    
    echo json_encode(['success' => true]);
} else {
    // Failure! Increment attempts
    $order['otp_attempts'] += 1;
    FirebaseDB::set($orderPath, $order);
    
    FirebaseDB::push("delivery_logs", [
        'order_id' => $orderId,
        'action' => 'verified_failed',
        'timestamp' => $currentTime
    ]);
    
    $remaining = 5 - $order['otp_attempts'];
    http_response_code(400);
    echo json_encode(['error' => "Invalid OTP. You have {$remaining} attempts remaining."]);
}
?>
