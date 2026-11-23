<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isset($_SESSION['user_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['customer','delivery_agent'], true)) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Please sign in again.']);
    exit;
}

if (!ensure_realtime_schema()) {
    http_response_code(503);
    echo json_encode(['success'=>false,'message'=>'Messaging is temporarily unavailable.']);
    exit;
}

$userId=(int)$_SESSION['user_id'];
$role=(string)$_SESSION['role'];
$user = require_role($role);
$code=trim((string)($_REQUEST['code']??''));
$orderStatement=db()->prepare('SELECT id,customer_id,delivery_agent_id,order_status FROM orders WHERE tracking_code=? LIMIT 1');
$orderStatement->execute([$code]);
$order=$orderStatement->fetch();
$authorized=$order&&(($role==='customer'&&(int)$order['customer_id']===$userId)||($role==='delivery_agent'&&(int)$order['delivery_agent_id']===$userId));

if(!$authorized){http_response_code(403);echo json_encode(['success'=>false,'message'=>'You cannot access this order conversation.']);exit;}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $message=trim((string)($_POST['message']??''));
    if($message===''||mb_strlen($message)>500){http_response_code(422);echo json_encode(['success'=>false,'message'=>'Enter a message between 1 and 500 characters.']);exit;}
    if(in_array($order['order_status'],['Completed','Cancelled'],true)){http_response_code(409);echo json_encode(['success'=>false,'message'=>'This order conversation is closed.']);exit;}
    $insert=db()->prepare('INSERT INTO order_messages(order_id,sender_id,message) VALUES(?,?,?)');$insert->execute([$order['id'],$userId,$message]);
    $recipient = $role === 'customer' ? (int)$order['delivery_agent_id'] : (int)$order['customer_id'];
    if ($recipient) {
        sf_notify($recipient, 'New order message', $role === 'customer' ? 'delivery/active_deliveries.php' : 'customer/track_order.php?code=' . urlencode($code));
    }
}

$messagesStatement=db()->prepare('SELECT m.id,m.sender_id,m.message,m.created_at,u.full_name,u.role FROM order_messages m JOIN users u ON u.id=m.sender_id WHERE m.order_id=? ORDER BY m.created_at DESC LIMIT 60');
$messagesStatement->execute([$order['id']]);$messages=array_reverse($messagesStatement->fetchAll());
echo json_encode(['success'=>true,'current_user_id'=>$userId,'closed'=>in_array($order['order_status'],['Completed','Cancelled'],true),'messages'=>$messages]);
