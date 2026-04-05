<?php
// ============================================================
// update_profile.php — Save user profile to Firebase RTDB
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/firebase.php';
require_once __DIR__ . '/middleware.php';

// -------- 1. Authorize Context --------
$auth = authorize(); // Only token owners get through, guaranteeing uid belongs to caller
$uid = $auth['uid'];

// -------- 2. Validate input --------
$body    = json_decode(file_get_contents('php://input'), true);

// -------- 3. Sanitize & build profile payload --------
$profile = [
    'name'       => htmlspecialchars(trim($body['name']    ?? '')),
    'phone'      => htmlspecialchars(trim($body['phone']   ?? '')),
    'city'       => htmlspecialchars(trim($body['city']    ?? '')),
    'state'      => htmlspecialchars(trim($body['state']   ?? '')),
    'address'    => htmlspecialchars(trim($body['address'] ?? '')),
    'updated_at' => date('c'),
];

// -------- 4. Write to Firebase RTDB --------
$result = FirebaseDB::set('users/' . $uid . '/profile', $profile);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save profile to database']);
    exit();
}

echo json_encode(['success' => true, 'profile' => $profile]);
