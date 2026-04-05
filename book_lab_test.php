<?php
require_once 'config.php';
require_once 'firebase.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $requiredFields = ['customer_id', 'customer_name', 'test_id', 'test_name', 'price', 'date', 'time', 'patient_name', 'patient_age', 'address', 'contact'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing '$field' field"]);
            exit();
        }
    }
    
    $bookingData = [
        'customer_id' => $data['customer_id'],
        'customer_name' => $data['customer_name'],
        'test_id' => $data['test_id'],
        'test_name' => $data['test_name'],
        'lab_name' => $data['lab_name'] ?? 'City PathLabs',
        'price' => floatval($data['price']),
        'booking_date' => $data['date'],
        'time_slot' => $data['time'],
        'patient_name' => $data['patient_name'],
        'patient_age' => (int)$data['patient_age'],
        'address' => $data['address'],
        'contact' => $data['contact'],
        'status' => 'Pending',
        'timestamp' => time()
    ];
    
    $result = FirebaseDB::push('lab_bookings', $bookingData);
    
    if ($result) {
        echo json_encode(['message' => 'Lab test booked successfully', 'booking_id' => $result['name']]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to place booking in database']);
    }
    exit();
}
?>
