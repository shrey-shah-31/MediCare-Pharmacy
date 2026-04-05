<?php
// FIREBASE CONFIGURATION
define('FIREBASE_DB_URL', 'https://medcare-pharmacy-11f7c-default-rtdb.firebaseio.com/');
define('FIREBASE_WEB_API_KEY', 'AIzaSyB6sdvexQIxMKihrUJAEwBHl71_Znagv1U');

// Production: suppress error output (errors break JSON responses)
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1); // still logs to server error log

// Allow cross-origin requests (needed when frontend and API are on same domain)
if (!headers_sent()) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Auth-Token');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
}
?>
