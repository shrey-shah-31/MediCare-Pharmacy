<?php
// ============================================================
// middleware.php — Authorization & Security Hardening
// ============================================================
require_once __DIR__ . '/config.php';

// -------- 1. CORS Dynamic Validation --------
function setupStrictCORS() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = ['http://localhost', 'http://localhost:5173', 'http://127.0.0.1'];
    
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        header("Access-Control-Allow-Origin: http://localhost"); // Default fallback
    }
    
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE, PATCH");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    
    // Handle preflight OPTIONS requests immediately
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// -------- 2. Rate Limiting --------
// Uses file-based tracking for simple out-of-the-box XAMPP support
function checkRateLimit($identifier, $limit, $windowSeconds = 60) {
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'medcare_limits';
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0777, true);
    }
    
    // Use md5 to safely name the file
    $file = $tempDir . DIRECTORY_SEPARATOR . md5($identifier) . '.json';
    $now = time();
    $requests = [];
    
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) {
            foreach ($data as $timestamp) {
                if ($now - $timestamp < $windowSeconds) {
                    $requests[] = $timestamp;
                }
            }
        }
    }
    
    if (count($requests) >= $limit) {
        http_response_code(429);
        echo json_encode(['error' => 'Too Many Requests Limit Exceeded']);
        exit();
    }
    
    $requests[] = $now;
    file_put_contents($file, json_encode($requests));
}

// -------- 3. Main Authorization Middleware --------
function authorize($requiredRole = null, $resourceOwnerId = null) {
    // We are now authenticated: switch from IP rate limit to Token rate limit (60/min)
    // We will do this after parsing the token.
    
    // If running in apache env, get headers properly
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
    } else {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
    }
    
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/i', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Missing or invalid Bearer token']);
        exit();
    }
    
    $idToken = $matches[1];
    
    // Verify Token via Firebase Auth REST API 
    $verifyUrl = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . FIREBASE_WEB_API_KEY;
    $ch = curl_init($verifyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['idToken' => $idToken]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$response) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Invalid token']);
        exit();
    }
    
    $data = json_decode($response, true);
    if (isset($data['error']) || empty($data['users'][0]['localId'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized: Token expired or invalid']);
        exit();
    }
    
    $uid = $data['users'][0]['localId'];
    
    // Validate custom JWT claims/roles.
    $tokenParts = explode('.', $idToken);
    $payload = [];
    if (count($tokenParts) >= 2) {
        $payload = json_decode(base64_decode(strtr($tokenParts[1], '-_', '+/')), true);
    }
    
    $role = $payload['role'] ?? null;
    
    // Fallback: If role wasn't attached via Firebase Custom Claims logic natively,
    // we strictly pull it from our secured database as a reliable source of truth.
    if (!$role) {
        require_once __DIR__ . '/firebase.php';
        $dbUser = FirebaseDB::get('users/' . $uid);
        $role = $dbUser['role'] ?? 'customer'; // Zero trust fallback
    }

    // Role Enforcement
    if ($requiredRole !== null && $role !== $requiredRole) {
        http_response_code(403);
        echo json_encode(['error' => "Forbidden: Insufficient privileges (Requires: $requiredRole, got $role)"]);
        exit();
    }
    
    // Resource Owner Enforcement
    if ($resourceOwnerId !== null && $uid !== $resourceOwnerId) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden: You do not own this resource']);
        exit();
    }
    
    // Apply authenticated rate limit
    checkRateLimit("user_$uid", 60);
    
    return [
       'uid' => $uid,
       'role' => $role
    ];
}

// Global Execution
setupStrictCORS();

// Apply public rate limit explicitly before scripts continue
// If authorize() runs later, it will overlap and track user tokens too
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';
checkRateLimit("ip_$ip", 120);
?>
