<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once 'config.php';
require_once 'firebase.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$bookingId = $data['booking_id'] ?? null;

if (!$bookingId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing booking_id']);
    exit();
}

$bookingPath = "lab_bookings/{$bookingId}";
$booking = FirebaseDB::get($bookingPath);

if (!$booking) {
    http_response_code(404);
    echo json_encode(['error' => 'Booking not found']);
    exit();
}

// Generate OTP
$otp = (string) random_int(100000, 999999);
$generatedAt = time();
$expiresAt = $generatedAt + 86400; // 24 hours - valid for the whole day

// Update booking - set to Confirmed + store OTP
$booking['status'] = 'Confirmed';
$booking['sample_otp'] = $otp;
$booking['otp_generated_at'] = $generatedAt;
$booking['otp_expires_at'] = $expiresAt;
$booking['otp_attempts'] = 0;

$result = FirebaseDB::set($bookingPath, $booking);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'Booking confirmed and OTP generated for customer.']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to confirm booking and generate OTP']);
}
?>
