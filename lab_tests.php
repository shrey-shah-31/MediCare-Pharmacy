<?php
require_once 'config.php';
require_once 'firebase.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tests = FirebaseDB::get('lab_tests');
    
    // Seed initial data if empty
    if (!$tests) {
        $mockTests = [
            'test_cbc' => [
                'name' => 'Complete Blood Count (CBC)',
                'description' => 'Evaluates overall health and detects a wide range of disorders, including anemia, infection and leukemia.',
                'price' => 399.00,
                'requirements' => 'No fasting required. Blood sample is normally taken from a vein in your arm.',
                'turnaround_time' => '24 hours',
                'icon' => 'fa-vial'
            ],
            'test_lft' => [
                'name' => 'Liver Function Test (LFT)',
                'description' => 'Measures the levels of proteins, liver enzymes, and bilirubin in your blood.',
                'price' => 550.00,
                'requirements' => 'Fasting of 10-12 hours is required.',
                'turnaround_time' => '24 hours',
                'icon' => 'fa-flask-vial'
            ],
            'test_lipid' => [
                'name' => 'Lipid Profile',
                'description' => 'A complete cholesterol test to determine risk of heart disease.',
                'price' => 450.00,
                'requirements' => 'Fasting of 10-12 hours is required.',
                'turnaround_time' => '24 hours',
                'icon' => 'fa-heart-pulse'
            ],
            'test_full_body' => [
                'name' => 'Full Body Checkup (Advanced)',
                'description' => 'Includes 64 tests covering Thyroid, Liver, Heart, Kidneys, Bones and Diabetes.',
                'price' => 1299.00,
                'requirements' => 'Fasting for 10-12 hours. Best given early morning.',
                'turnaround_time' => '48 hours',
                'icon' => 'fa-person-half-dress'
            ]
        ];
        foreach ($mockTests as $key => $val) {
            FirebaseDB::set('lab_tests/' . $key, $val);
        }
        $tests = $mockTests;
    }
    
    // Location-based filtering setup
    $customerId = $_GET['customer_id'] ?? null;
    $labIdParam = $_GET['lab_id'] ?? null;
    $validLabIds = null; // null implies no filtering
    
    // If a lab requests its own tests
    if ($labIdParam) {
        $validLabIds = [$labIdParam];
    }
    
    $allUsers = FirebaseDB::get('users');

    if ($customerId) {
        $customer = FirebaseDB::get('users/' . $customerId);
        if ($customer && !empty($customer['city'])) {
            $customerCity = strtolower(trim($customer['city']));
            if ($allUsers) {
                // Merge or instantiate validLabIds
                $validLabIds = [];
                foreach ($allUsers as $uid => $user) {
                    if (isset($user['role']) && $user['role'] === 'laboratory') {
                        $labCity = strtolower(trim($user['city'] ?? ''));
                        if ($labCity === $customerCity) {
                            $validLabIds[] = $uid;
                        }
                    }
                }
            }
        }
    }
    
    $testList = [];
    if($tests) {
        foreach ($tests as $key => $test) {
            // Strict filtering
            if (is_array($validLabIds)) {
                $labId = $test['lab_id'] ?? '';
                if (empty($labId) || !in_array($labId, $validLabIds)) {
                    continue;
                }
            }
            $test['id'] = $key;
            $labId = $test['lab_id'] ?? '';
            if (!empty($labId) && isset($allUsers[$labId]['city'])) {
                $test['lab_city'] = $allUsers[$labId]['city'];
            }
            $testList[] = $test;
        }
    }
    
    echo json_encode($testList);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['name', 'description', 'price', 'requirements', 'turnaround_time', 'lab_name'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing $field"]);
            exit();
        }
    }
    
    $payload = [
        'name' => $data['name'],
        'description' => $data['description'],
        'price' => floatval($data['price']),
        'requirements' => $data['requirements'],
        'turnaround_time' => $data['turnaround_time'],
        'lab_name' => $data['lab_name'],
        'lab_id' => $data['lab_id'] ?? '', // Crucial for filtering
        'timing' => $data['timing'] ?? null,
        'icon' => 'fa-vial-virus'
    ];
    
    $result = FirebaseDB::push('lab_tests', $payload);
    
    if ($result) {
        echo json_encode(['message' => 'Lab test added successfully', 'id' => $result['name']]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to add lab test']);
    }
    exit();
}
?>
