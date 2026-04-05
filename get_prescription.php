<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once 'config.php';
require_once 'firebase.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit();
}

$orderId = $_GET['order_id'] ?? null;
if (!$orderId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order_id']);
    exit();
}

$order = FirebaseDB::get("orders/$orderId");

if (!$order || !isset($order['rx_image'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Prescription not found']);
    exit();
}

echo json_encode(['rx_image' => $order['rx_image']]);
?>
