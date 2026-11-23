<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
$user = require_role('customer');
header('Content-Type: application/json');
header('Cache-Control: no-store');
$order = sf_query("SELECT o.id,o.order_status,o.delivery_agent_id,d.latitude destination_lat,d.longitude destination_lng,u.full_name,u.phone,p.vehicle_plate,l.latitude,l.longitude,l.accuracy_meters,TIMESTAMPDIFF(SECOND,l.updated_at,NOW()) gps_age FROM orders o LEFT JOIN sf_order_details d ON d.order_id=o.id LEFT JOIN users u ON u.id=o.delivery_agent_id LEFT JOIN delivery_agent_profiles p ON p.user_id=u.id LEFT JOIN agent_live_locations l ON l.agent_id=u.id WHERE o.tracking_code=? AND o.customer_id=?", [(string)($_GET['code'] ?? ''),$user['id']])->fetch();
if (!$order) {
    http_response_code(404);
    echo json_encode(['success'=>false,'message'=>'Order not found.']);
    exit;
}
$live = in_array($order['order_status'], ['Dispatched','Arrived','Pumping'], true) && $order['latitude'] !== null && $order['gps_age'] !== null && (int)$order['gps_age'] <= 90;
$eta = null;
$distance = null;
if ($live && $order['destination_lat'] !== null) {
    $distance = sf_km((float)$order['latitude'],(float)$order['longitude'],(float)$order['destination_lat'],(float)$order['destination_lng']);
    $eta = $order['order_status'] === 'Dispatched' ? max(1,(int)ceil($distance * 1.35 / 25 * 60)) : 0;
}
$history = sf_query("SELECT order_status,DATE_FORMAT(created_at,'%d %b %Y, %H:%i:%s') recorded_at FROM order_status_history WHERE order_id=? ORDER BY id", [$order['id']])->fetchAll();
echo json_encode([
    'success'=>true,'status'=>$order['order_status'],'live'=>$live,
    'agent'=>['name'=>$order['full_name'],'phone'=>$order['phone'],'plate'=>$order['vehicle_plate']],
    'position'=>$live ? [(float)$order['latitude'],(float)$order['longitude']] : null,
    'destination'=>$order['destination_lat'] !== null ? [(float)$order['destination_lat'],(float)$order['destination_lng']] : null,
    'gps_age'=>$order['gps_age'],'accuracy'=>$live ? $order['accuracy_meters'] : null,
    'eta_minutes'=>$eta,'distance_km'=>$distance,'history'=>$history
]);
