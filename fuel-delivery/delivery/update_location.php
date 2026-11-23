<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

$user = require_role('delivery_agent');
ensure_realtime_schema();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

verify_csrf();
header('Content-Type: application/json');

if (isset($_POST['stop'])) {
    sf_query('DELETE FROM agent_live_locations WHERE agent_id=?', [$user['id']]);
    echo json_encode(['success'=>true]);
    exit;
}
if (!sf_approved((int)$user['id']) || !sf_query('SELECT is_online FROM delivery_agent_profiles WHERE user_id=?', [$user['id']])->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Go online after admin approval to share GPS.']);
    exit;
}

$latitude = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
$accuracy = filter_var($_POST['accuracy'] ?? null, FILTER_VALIDATE_FLOAT);
if ($accuracy !== false && ($accuracy < 0 || $accuracy > 999999)) {
    $accuracy = false;
}

if ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid GPS coordinates.']);
    exit;
}

$statement = db()->prepare('UPDATE delivery_agent_profiles SET current_lat = ?, current_lng = ? WHERE user_id = ?');
$statement->execute([$latitude, $longitude, $user['id']]);
$liveStatement=db()->prepare('INSERT INTO agent_live_locations(agent_id,latitude,longitude,accuracy_meters) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE latitude=VALUES(latitude),longitude=VALUES(longitude),accuracy_meters=VALUES(accuracy_meters),updated_at=CURRENT_TIMESTAMP');
$liveStatement->execute([$user['id'],$latitude,$longitude,$accuracy===false?null:$accuracy]);

echo json_encode([
    'success' => true,
    'latitude' => round((float) $latitude, 6),
    'longitude' => round((float) $longitude, 6),
    'message' => 'Live GPS updated.',
]);
