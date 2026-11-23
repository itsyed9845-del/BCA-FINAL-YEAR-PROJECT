<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
$user = require_role((string)($_SESSION['role'] ?? 'customer'));
header('Content-Type: application/json');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'POST required.']);
    exit;
}
verify_csrf();
$values = $_POST['ids'] ?? [];
if (!is_array($values) || count($values)>100) {
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>'Invalid notification selection.']);
    exit;
}
$ids = array_values(array_unique(array_filter(array_map(static fn($value) => is_scalar($value) ? (int)$value : 0, $values),fn($id)=>$id>0)));
if ($ids) {
    $placeholders = implode(',',array_fill(0,count($ids),'?'));
    sf_query('UPDATE sf_notifications SET is_read=1 WHERE user_id=? AND id IN ('.$placeholders.')',array_merge([$user['id']],$ids));
}
$unread = (int)sf_query('SELECT COUNT(*) FROM sf_notifications WHERE user_id=? AND is_read=0',[$user['id']])->fetchColumn();
echo json_encode(['success'=>true,'unread'=>$unread]);
