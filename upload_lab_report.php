<?php
require_once 'config.php';
require_once 'firebase.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['booking_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing booking_id']);
        exit();
    }
    
    $bookingId = $_POST['booking_id'];
    
    if (!isset($_FILES['report_file']) || $_FILES['report_file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'No file uploaded or upload error']);
        exit();
    }
    
    $uploadDir = '../uploads/reports/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create upload directory']);
            exit();
        }
    }
    
    $fileTmpPath = $_FILES['report_file']['tmp_name'];
    $fileName = $_FILES['report_file']['name'];
    $fileInfo = pathinfo($fileName);
    $extension = strtolower($fileInfo['extension']);
    
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($extension, $allowedExtensions)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type. Only PDF and images are allowed.']);
        exit();
    }
    
    $newFileName = 'report_' . $bookingId . '_' . time() . '.' . $extension;
    $destination = $uploadDir . $newFileName;
    
    if (move_uploaded_file($fileTmpPath, $destination)) {
        // Save relative path for frontend
        $reportUrl = 'uploads/reports/' . $newFileName;
        
        // Update database
        FirebaseDB::update('lab_bookings/' . $bookingId, [
            'status' => 'Report Ready',
            'report_url' => $reportUrl
        ]);
        
        echo json_encode(['message' => 'Report uploaded successfully', 'report_url' => $reportUrl]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to move uploaded file']);
    }
    exit();
}
?>
