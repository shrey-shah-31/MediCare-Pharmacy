<?php
require_once 'config.php';
require_once 'firebase.php';

header('Content-Type: application/json');

// Prevent cached GET responses
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

$method = $_SERVER['REQUEST_METHOD'];

// Extract token from query parameter (bypassing Apache header stripping)
$token = $_GET['token'] ?? '';

// Helper to authenticated fetch
function getAuthUser($uid, $token) {
    if (empty($token)) {
        return FirebaseDB::get("users/{$uid}");
    }
    $url = rtrim(FIREBASE_DB_URL, '/') . "/users/{$uid}.json?auth=" . $token;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// Handle GET: Fetch Profile
if ($method === 'GET') {
    $uid = $_GET['uid'] ?? '';
    if (empty($uid)) {
        http_response_code(400); 
        echo json_encode(['error' => 'Missing UID parameters.']);
        exit();
    }
    $user = getAuthUser($uid, $token ?? '');
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found.']);
        exit();
    }
    echo json_encode($user);
    exit();
}

// Handle POST: Update Profile
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $uid = $data['uid'] ?? '';
    $postToken = $data['token'] ?? $token ?? '';
    
    if (empty($uid)) {
        http_response_code(400);
        echo json_encode(['error' => 'UID is missing.']);
        exit();
    }
    
    $existing = getAuthUser($uid, $postToken);
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found.']);
        exit();
    }
    
    $existing['name']    = $data['name']    ?? $existing['name'] ?? '';
    $existing['phone']   = $data['phone']   ?? $existing['phone'] ?? '';
    $existing['city']    = $data['city']    ?? $existing['city'] ?? '';
    $existing['state']   = $data['state']   ?? $existing['state'] ?? '';
    $existing['address'] = $data['address'] ?? $existing['address'] ?? '';
    
    // Perform authenticated PUT request using the token
    $dbUrl = rtrim(FIREBASE_DB_URL, '/') . "/users/{$uid}.json";
    if (!empty($postToken)) {
        $dbUrl .= '?auth=' . $postToken;
    }
    
    $chDb = curl_init($dbUrl);
    curl_setopt($chDb, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chDb, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($chDb, CURLOPT_POSTFIELDS, json_encode($existing));
    curl_setopt($chDb, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($chDb);
    $httpCode = curl_getinfo($chDb, CURLINFO_HTTP_CODE);
    curl_close($chDb);
    
    if ($httpCode === 200) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => "Database write failed with Code: $httpCode"]);
    }
    exit();
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>
