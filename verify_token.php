<?php
// ============================================================
// verify_token.php — Lightweight endpoint for frontend role check
// Called on page load by retailer.html & customer_dashboard.html
// Returns: { valid: true, uid: '...', role: '...' }
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/firebase.php';
require_once __DIR__ . '/auth_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// This will auto-respond with 401/403 and exit if invalid
$user = verifyTokenAndRole(null); // null = any authenticated user

echo json_encode([
    'valid' => true,
    'uid'   => $user['uid'],
    'role'  => $user['role']
]);
?>
