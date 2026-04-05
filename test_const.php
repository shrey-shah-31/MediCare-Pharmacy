<?php
require_once 'api/config.php';
echo "API Key is: " . FIREBASE_WEB_API_KEY . "\n";
echo "But I used: " . (defined('FIREBASE_API_KEY') ? FIREBASE_API_KEY : 'undefined') . "\n";
?>
