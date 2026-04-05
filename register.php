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
$name = $data['name'] ?? '';
$role = $data['role'] ?? 'customer';
$shopName = $data['shop_name'] ?? '';
// If retailer/lab but no shop name given, default
if ($role === 'retailer' && empty($shopName)) {
    $shopName = $name . "'s Pharmacy";
} else if ($role === 'laboratory' && empty($shopName)) {
    $shopName = $name . " Path Labs";
}

if (empty($email) || empty($password) || empty($name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

// 1. Create user in Firebase Authentication
$authUrl = 'https://identitytoolkit.googleapis.com/v1/accounts:signUp?key=' . FIREBASE_WEB_API_KEY;

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
    echo json_encode(['error' => $authResult['error']['message'] ?? 'Registration failed']);
    exit();
}

$localId = $authResult['localId'];
$idToken = $authResult['idToken'];

// 2. Save user details to Firebase Realtime Database
$city = $data['city'] ?? '';
$state = $data['state'] ?? '';

$dbResponse = FirebaseDB::set('users/' . $localId, [
    'name' => $name,
    'email' => $email,
    'role' => $role,
    'shop_name' => $shopName,
    'city' => $city,
    'state' => $state,
    'created_at' => time()
], $idToken);

// 3. Return success
echo json_encode([
    'message' => 'User registered successfully',
    'idToken' => $idToken,
    'refreshToken' => $authResult['refreshToken'] ?? '',
    'expiresIn' => $authResult['expiresIn'] ?? '3600',
    'localId' => $localId,
    'role' => $role,
    'name' => $name,
    'shop_name' => $shopName
]);
?>
