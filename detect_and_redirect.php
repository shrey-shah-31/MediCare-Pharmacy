<?php
session_start();

$user_ip = $_SERVER['REMOTE_ADDR'];

// Fallback for localhost testing
if ($user_ip == '127.0.0.1' || $user_ip == '::1') {
    $city = 'Mumbai'; 
} else {
    // Suppress warnings in case ipapi.co is down or rate limits us
    $api_url = "https://ipapi.co/{$user_ip}/json/";
    $response = @file_get_contents($api_url);
    if ($response) {
        $data = json_decode($response, true);
        $city = $data['city'] ?? 'Mumbai';
    } else {
        $city = 'Mumbai';
    }
}

// Ensure first letter is capitalized for consistent matching
$city = ucfirst(strtolower($city));

// Store the detected city in a cookie for 30 days
setcookie('medcare_user_city', $city, time() + (86400 * 30), "/");

// Redirect the user to the specific city shops page
header("Location: shop.html?city=" . urlencode($city));
exit;
?>
