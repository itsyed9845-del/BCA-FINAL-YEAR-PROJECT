<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
$user = require_role((string)($_SESSION['role'] ?? 'customer'));
header('Content-Type: application/json');
header('Cache-Control: no-store');
$unread = (int)sf_query('SELECT COUNT(*) FROM sf_notifications WHERE user_id=? AND is_read=0', [$user['id']])->fetchColumn();
$notifications = sf_query('SELECT id,message,target FROM sf_notifications WHERE user_id=? AND is_read=0 ORDER BY id DESC LIMIT 5', [$user['id']])->fetchAll();
$condition = $user['role'] === 'customer' ? 'WHERE customer_id=?' : ($user['role'] === 'delivery_agent' ? 'WHERE delivery_agent_id=? OR (delivery_agent_id IS NULL AND order_status=\'Pending\')' : '');
$orders = sf_query('SELECT id,order_status,payment_status,delivery_agent_id FROM orders ' . $condition . ' ORDER BY id', $condition ? [$user['id']] : [])->fetchAll();
$earningCondition = $user['role'] === 'delivery_agent' ? ' WHERE agent_id=?' : '';
$earnings = $user['role'] === 'customer' ? [] : sf_query('SELECT id,payout_status FROM delivery_earnings' . $earningCondition . ' ORDER BY id', $earningCondition ? [$user['id']] : [])->fetchAll();
$state = [
    sf_query('SELECT COUNT(*),COALESCE(SUM(is_online),0) FROM delivery_agent_profiles')->fetch(PDO::FETCH_NUM),
    sf_query('SELECT COUNT(*) FROM users')->fetchColumn(),
    sf_query('SELECT id,stock_liters,price_per_liter FROM fuel_inventory ORDER BY id')->fetchAll(),
    sf_query('SELECT user_id,approval_status FROM sf_agent_approvals ORDER BY user_id')->fetchAll(),
];
echo json_encode(['success'=>true,'unread'=>$unread,'notifications'=>$notifications,'revision'=>hash('sha256',json_encode([$orders,$earnings,$state]))]);
