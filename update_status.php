<?php
require_once 'config.php';
require_once 'firebase.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$orderId = $data['order_id'] ?? null;
$status = $data['status'] ?? null;

if (!$orderId || !$status) {
    http_response_code(400);
    exit();
}

// Update just the status node of the specific order
$dbUrl = rtrim(FIREBASE_DB_URL, '/') . '/orders/' . $orderId . '/status.json';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $dbUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($status));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo json_encode(['message' => 'Status updated']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update order status']);
}
?>
