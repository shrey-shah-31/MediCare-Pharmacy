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

$orderPath = "orders/{$orderId}";
$order = FirebaseDB::get($orderPath);

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit();
}

if ($order['status'] !== 'Shipped') {
    http_response_code(400);
    echo json_encode(['error' => 'OTP can only be regenerated for orders currently Shipped/out for delivery']);
    exit();
}

$currentTime = time();
$lastRegen = $order['last_regenerated_at'] ?? 0;

if ($currentTime - $lastRegen < 30) {
    $wait = 30 - ($currentTime - $lastRegen);
    http_response_code(429);
    echo json_encode(['error' => "Please wait {$wait} seconds before regenerating the OTP."]);
    exit();
}

// Generate new OTP
$otp = (string) random_int(100000, 999999);
$order['delivery_otp'] = $otp;
$order['otp_generated_at'] = $currentTime;
$order['otp_expires_at'] = $currentTime + 600;
$order['otp_attempts'] = 0; // Reset
$order['last_regenerated_at'] = $currentTime;

FirebaseDB::push("delivery_logs", [
    'order_id' => $orderId,
    'action' => 'regenerated',
    'timestamp' => $currentTime
]);

$result = FirebaseDB::set($orderPath, $order);

if ($result) {
    // Stub SMS sending
    $phone = "Customer"; // Fallback placeholder
    $message = "MedCare Warning: Your delivery OTP was regenerated. New code for order #{$orderId} is: {$otp}";
    $logEntry = "[" . date('Y-m-d H:i:s') . "] SMS to {$phone}: {$message}\n";
    file_put_contents(__DIR__ . '/sms_log.txt', $logEntry, FILE_APPEND);

    echo json_encode(['success' => true, 'message' => 'OTP successfully regenerated']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to regenerate OTP']);
}
?>
