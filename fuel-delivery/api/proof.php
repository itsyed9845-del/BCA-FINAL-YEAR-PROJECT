<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
$user = require_role((string)($_SESSION['role'] ?? 'customer'));
try {
    $order = sf_order((int)($_GET['order_id'] ?? 0), $user);
    $name = sf_query('SELECT proof_file FROM sf_order_details WHERE order_id=?', [$order['id']])->fetchColumn();
    if (!$name || basename($name) !== $name || !is_file(__DIR__ . '/../storage/proofs/' . $name)) {
        http_response_code(404);
        exit('No delivery photo was recorded for this order.');
    }
    $path = __DIR__ . '/../storage/proofs/' . $name;
    header('Content-Type: ' . (new finfo(FILEINFO_MIME_TYPE))->file($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: inline; filename="delivery-proof.' . pathinfo($name, PATHINFO_EXTENSION) . '"');
    readfile($path);
} catch (RuntimeException $error) {
    http_response_code(403);
    echo e(sf_error($error));
}
