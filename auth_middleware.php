<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/firebase.php';

function getBearerToken() {
    $raw = null;

    // Method 1: getallheaders()
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower($name) === 'authorization') { $raw = $value; break; }
        }
    }
    // Method 2: $_SERVER HTTP_AUTHORIZATION (set by .htaccess RewriteRule)
    if (!$raw && !empty($_SERVER['HTTP_AUTHORIZATION']))
        $raw = $_SERVER['HTTP_AUTHORIZATION'];
    // Method 3: Redirect variant
    if (!$raw && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']))
        $raw = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    // Method 4: X-Auth-Token custom header (Apache never strips this)
    if (!$raw && !empty($_SERVER['HTTP_X_AUTH_TOKEN']))
        return trim($_SERVER['HTTP_X_AUTH_TOKEN']);

    if ($raw && preg_match('/Bearer\s+(\S+)/i', $raw, $m))
        return $m[1];

    // Method 5: POST body field idToken (always reaches PHP, even on XAMPP)
    static $body = null;
    if ($body === null) {
        $raw2 = file_get_contents('php://input');
        $body = $raw2 ? (json_decode($raw2, true) ?? []) : [];
    }
    if (!empty($body['idToken'])) return trim($body['idToken']);

    // Method 6: GET query param
    if (!empty($_GET['idToken'])) return trim($_GET['idToken']);

    return null;
}

function verifyTokenAndRole($requiredRole = null) {
    $idToken = getBearerToken();

    if (!$idToken) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: No token provided. Please log in.']);
        exit();
    }

    $verifyUrl = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . FIREBASE_WEB_API_KEY;
    $ch = curl_init($verifyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['idToken' => $idToken]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || !empty($curlErr)) {
        http_response_code(500);
        echo json_encode(['error' => 'Token verification error: ' . $curlErr]);
        exit();
    }

    $tokenData = json_decode($response, true);
    if ($httpCode !== 200 || isset($tokenData['error']) || empty($tokenData['users'][0]['localId'])) {
        http_response_code(401);
        $msg = $tokenData['error']['message'] ?? 'Invalid or expired token. Please log in again.';
        echo json_encode(['error' => $msg]);
        exit();
    }

    $uid = $tokenData['users'][0]['localId'];
    $userData = FirebaseDB::get('users/' . $uid, $idToken);
    $role = $userData['role'] ?? null;

    if (!$role) {
        http_response_code(403);
        echo json_encode(['error' => 'User role not found.']);
        exit();
    }

    if ($requiredRole !== null && $role !== $requiredRole) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden: requires role "' . $requiredRole . '", you have "' . $role . '"']);
        exit();
    }

    return ['uid' => $uid, 'role' => $role, 'token' => $idToken];
}
?>
