<?php
require_once 'config.php';
require_once 'firebase.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$orderId = $data['order_id'] ?? null;
$role = $data['role'] ?? '';

if (!$orderId || ($role !== 'retailer' && $role !== 'admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized or missing order_id']);
    exit();
}

// Fetch the order
$orderPath = "orders/{$orderId}";
$order = FirebaseDB::get($orderPath);

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit();
}

if ($order['status'] !== 'Confirmed' && $order['status'] !== 'Packing') {
    http_response_code(400);
    echo json_encode(['error' => 'OTP can only be generated for Confirmed/Packing orders. Current: ' . $order['status']]);
    exit();
}

// Generate OTP
$otp = (string) random_int(100000, 999999);
$generatedAt = time();
$expiresAt = $generatedAt + 600; // 10 minutes

// Update Order
$order['status'] = 'Shipped';
$order['delivery_otp'] = $otp;
$order['otp_generated_at'] = $generatedAt;
$order['otp_expires_at'] = $expiresAt;
$order['otp_attempts'] = 0;
$order['last_regenerated_at'] = $generatedAt;

FirebaseDB::push("delivery_logs", [
    'order_id' => $orderId,
    'action' => 'generated',
    'timestamp' => $generatedAt
]);

$result = FirebaseDB::set($orderPath, $order);

if ($result) {
    // Stub SMS sending
    $phone = "Customer"; // Fallback placeholder
    $message = "Your MedCare order #{$orderId} is out for delivery! Give this OTP to the delivery partner: {$otp}";
    $logEntry = "[" . date('Y-m-d H:i:s') . "] SMS to {$phone}: {$message}\n";
    file_put_contents(__DIR__ . '/sms_log.txt', $logEntry, FILE_APPEND);

    echo json_encode(['success' => true, 'message' => 'OTP generated and order Shipped']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate OTP']);
}
?>
