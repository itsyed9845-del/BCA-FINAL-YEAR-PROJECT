<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
$user=require_role('delivery_agent');

if($_SERVER['REQUEST_METHOD']!=='POST'){
    redirect('delivery/dashboard.php');
}

verify_csrf();
if (!sf_approved((int) $user['id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success'=>false,'message'=>'Admin approval is required before going online.']);
    exit;
}
$statement=db()->prepare('UPDATE delivery_agent_profiles SET is_online=IF(is_online=1,0,1) WHERE user_id=?');
$statement->execute([$user['id']]);
$statusStatement=db()->prepare('SELECT is_online FROM delivery_agent_profiles WHERE user_id=?');
$statusStatement->execute([$user['id']]);
$online=(bool)$statusStatement->fetchColumn();
if (!$online) {
    sf_query('DELETE FROM agent_live_locations WHERE agent_id=?', [$user['id']]);
}
$message='You are now '.($online?'online and available for dispatch.':'offline. New jobs are paused.');

if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'online' => $online, 'message' => $message]);
    exit;
}

flash('success',$message);
redirect('delivery/dashboard.php');
