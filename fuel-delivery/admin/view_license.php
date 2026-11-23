<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/dashboard_layout.php';

$admin = require_role('admin');

$userId = (int)($_GET['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(400);
    exit('Invalid delivery agent.');
}

$agent = sf_query(
    "SELECT u.full_name, p.license_document
     FROM users u
     INNER JOIN delivery_agent_profiles p ON p.user_id = u.id
     WHERE u.id = ? AND u.role = 'delivery_agent'",
    [$userId]
)->fetch();

if (!$agent || empty($agent['license_document'])) {
    http_response_code(404);
    exit('Driving licence document not found.');
}

$relativePath = (string)$agent['license_document'];

if (!preg_match('/^licenses\/[a-f0-9]{32}\.(pdf|jpg|png)$/i', $relativePath)) {
    http_response_code(404);
    exit('Invalid document path.');
}

$uploadRoot = dirname(__DIR__, 3) . '/smartfuel_private_uploads';
$fullPath = $uploadRoot . '/' . $relativePath;

$realRoot = realpath($uploadRoot);
$realFile = realpath($fullPath);

if (
    $realRoot === false ||
    $realFile === false ||
    !is_file($realFile) ||
    strpos($realFile, $realRoot . DIRECTORY_SEPARATOR) !== 0
) {
    http_response_code(404);
    exit('Driving licence file is unavailable.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($realFile);

$allowedTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png'
];

if (!in_array($mime, $allowedTypes, true)) {
    http_response_code(415);
    exit('Unsupported document type.');
}

$extension = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));

if (
    ($extension === 'pdf' && $mime !== 'application/pdf') ||
    ($extension === 'jpg' && $mime !== 'image/jpeg') ||
    ($extension === 'png' && $mime !== 'image/png')
) {
    http_response_code(415);
    exit('Document type mismatch.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realFile));
header('Content-Disposition: inline; filename="driving-licence.' . $extension . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');

readfile($realFile);
exit;
