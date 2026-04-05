<?php
// ============================================================
// get_profile.php — Fetch user profile from Firebase RTDB
// Full error reporting enabled for diagnosis
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/firebase.php';
require_once __DIR__ . '/middleware.php';

// -------- 1. Authorize Context --------
$auth = authorize(); // Only valid token structures are allowed
$uid = $auth['uid'];

// -------- 3. Read profile from Firebase RTDB --------
$profile = FirebaseDB::get('users/' . $uid . '/profile');

// If no profile exists yet, return sensible defaults (not null)
if ($profile === null) {
    $profile = [
        'name'    => $verifyData['users'][0]['displayName'] ?? '',
        'email'   => $verifyData['users'][0]['email']       ?? '',
        'phone'   => $verifyData['users'][0]['phoneNumber'] ?? '',
        'city'    => '',
        'state'   => '',
        'address' => '',
    ];
}

// -------- 4. Always return valid JSON --------
echo json_encode($profile);
