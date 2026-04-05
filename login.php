<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';
require_once 'firebase.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password are required']);
    exit();
}

// 1. Authenticate with Firebase
$authUrl = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=' . FIREBASE_WEB_API_KEY;

$ch = curl_init($authUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => $email,
    'password' => $password,
    'returnSecureToken' => true
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$authResult = json_decode($response, true);

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode(['error' => $authResult['error']['message'] ?? 'Invalid login credentials']);
    exit();
}

$localId = $authResult['localId'];
$idToken = $authResult['idToken'];

// 2. Fetch User Profile from Realtime Database to determine role
$userProfile = FirebaseDB::get('users/' . $localId, $idToken);

$role = $userProfile['role'] ?? 'customer';
$name = $userProfile['name'] ?? 'User';
$shopName = $userProfile['shop_name'] ?? '';

// 3. Return success with token and role
echo json_encode([
    'message' => 'Login successful',
    'idToken' => $idToken,
    'refreshToken' => $authResult['refreshToken'] ?? '',
    'expiresIn' => $authResult['expiresIn'] ?? '3600',
    'localId' => $localId,
    'role' => $role,
    'name' => $name,
    'shop_name' => $shopName
]);
?>
