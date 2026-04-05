<?php
require_once 'config.php';
require_once 'firebase.php';
header('Content-Type: application/json');

$orders = FirebaseDB::get('orders');
$res = [];
foreach($orders as $k => $o) {
    $cid = $o['customer_id'] ?? '';
    // Let's get the user profile
    $profile = [];
    if($cid) {
        $profile = FirebaseDB::get('users/'.$cid) ?: [];
    }
    $res[$k] = [
        'customer_id' => $cid,
        'order_phone' => $o['customer_phone'] ?? 'NOT_SET',
        'order_name' => $o['customer_name'] ?? 'NOT_SET',
        'profile_found' => !empty($profile),
        'profile_phone' => $profile['phone'] ?? 'NOT_SET',
        'profile_name' => $profile['name'] ?? 'NOT_SET',
        'profile_raw' => $profile
    ];
}
echo json_encode($res, JSON_PRETTY_PRINT);
?>
