<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
$user = require_role((string)($_SESSION['role'] ?? 'customer'));
redirect(role_home($user['role']));
