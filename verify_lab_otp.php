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
$otpInput   = trim($data['otp'] ?? '');

if (!$bookingId || empty($otpInput)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing booking_id or otp']);
    exit();
}

$bookingPath = "lab_bookings/{$bookingId}";
$booking = FirebaseDB::get($bookingPath);

if (!$booking) {
    http_response_code(404);
    echo json_encode(['error' => 'Booking not found']);
    exit();
}

if ($booking['status'] !== 'Confirmed') {
    http_response_code(400);
    echo json_encode(['error' => 'OTP verification only allowed for Confirmed bookings']);
    exit();
}

$currentTime = time();

if ($currentTime > ($booking['otp_expires_at'] ?? 0)) {
    http_response_code(400);
    echo json_encode(['error' => 'OTP expired.']);
    exit();
}

$attempts = (int)($booking['otp_attempts'] ?? 0);
if ($attempts >= 5) {
    http_response_code(403);
    echo json_encode(['error' => 'Too many failed attempts. Contact support.']);
    exit();
}

if ((string)$booking['sample_otp'] === $otpInput) {
    // Success — mark sample as collected
    $booking['status'] = 'Sample Collected';
    $booking['sample_otp'] = null;
    $booking['otp_generated_at'] = null;
    $booking['otp_expires_at'] = null;
    $booking['sample_collected_at'] = $currentTime;

    FirebaseDB::set($bookingPath, $booking);

    echo json_encode(['success' => true, 'message' => 'OTP verified! Sample marked as collected.']);
} else {
    $booking['otp_attempts'] = $attempts + 1;
    FirebaseDB::set($bookingPath, $booking);

    $remaining = 5 - $booking['otp_attempts'];
    http_response_code(400);
    echo json_encode(['error' => "Invalid OTP. {$remaining} attempts remaining."]);
}
?>
