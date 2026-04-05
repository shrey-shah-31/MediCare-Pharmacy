<?php
// ============================================================
// get_shops.php — Return a list of registered shops by city
// ============================================================
require_once 'config.php';
require_once 'firebase.php';
require_once 'middleware.php';

// Public endpoint, so no strict auth enforced
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $targetCity = $_GET['city'] ?? null;
    
    $allUsers = FirebaseDB::get('users') ?: [];
    
    $shops = [];
    
    if ($targetCity) {
        $targetCity = strtolower(trim($targetCity));
        foreach ($allUsers as $uid => $user) {
            if (isset($user['role']) && $user['role'] === 'retailer') {
                $retailerCity = strtolower(trim($user['city'] ?? ''));
                if ($retailerCity === $targetCity) {
                    $shop = [
                        'id' => $uid,
                        'shop_name' => $user['shop_name'] ?? 'MediCare Pharmacy',
                        'city' => $user['city'] ?? '',
                        'state' => $user['state'] ?? '',
                        'rating' => $user['rating'] ?? 4.8,
                        'shop_status' => $user['shop_status'] ?? 'online'
                    ];
                    $shops[] = $shop;
                }
            }
        }
    }
    
    echo json_encode($shops);
    exit();
}
