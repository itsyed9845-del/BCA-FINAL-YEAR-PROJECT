<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

const APP_NAME = 'SmartFuel One';
$local = is_file(__DIR__ . '/local.php') ? require __DIR__ . '/local.php' : [];
$appRoot = str_replace('\\', '/', dirname(__DIR__));
$documentRoot = rtrim(str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$detectedBase = $documentRoot !== '' && str_starts_with(strtolower($appRoot), strtolower($documentRoot) . '/')
    ? substr($appRoot, strlen($documentRoot))
    : ($appRoot === $documentRoot ? '' : '/fuel-delivery');
define('BASE_URL', rtrim((string)($local['base_url'] ?? $detectedBase), '/'));
define('DB_HOST', (string)($local['db_host'] ?? '127.0.0.1'));
define('DB_NAME', (string)($local['db_name'] ?? 'fuel_delivery_db'));
define('DB_USER', (string)($local['db_user'] ?? 'root'));
define('DB_PASS', (string)($local['db_pass'] ?? ''));
date_default_timezone_set('Asia/Kolkata');

ini_set('display_errors', '0');
ini_set('log_errors', '1');

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $exception) {
        render_unexpected_error($exception);
    }

    sf_schema($pdo);
    return $pdo;
}

function safe_rollback(?PDO $connection): void
{
    if (!$connection instanceof PDO) {
        return;
    }

    try {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    } catch (Throwable $rollbackError) {
        error_log('SmartFuel rollback warning: ' . $rollbackError->getMessage());
    }
}

function database_was_disconnected(Throwable $exception): bool
{
    $driverCode = $exception instanceof PDOException
        ? (int) ($exception->errorInfo[1] ?? 0)
        : 0;
    $message = strtolower($exception->getMessage());

    return in_array($driverCode, [2006, 2013], true)
        || str_contains($message, 'server has gone away')
        || str_contains($message, 'lost connection');
}

function friendly_database_error(Throwable $exception, string $fallback): string
{
    error_log('SmartFuel database error: ' . $exception->getMessage());

    if (database_was_disconnected($exception)) {
        return 'The MySQL connection was interrupted. Restart MySQL in XAMPP, refresh this page and try again.';
    }

    return $fallback;
}

function ensure_realtime_schema(): bool
{
    static $ready = false;

    if ($ready) {
        $ready = true;
        return true;
    }

    try {
        $connection = db();
        $connection->exec("CREATE TABLE IF NOT EXISTS agent_live_locations (
            agent_id INT UNSIGNED PRIMARY KEY,
            latitude DECIMAL(10,7) NOT NULL,
            longitude DECIMAL(10,7) NOT NULL,
            accuracy_meters DECIMAL(8,2) NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_live_location_agent FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        $connection->exec("CREATE TABLE IF NOT EXISTS order_status_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            order_status ENUM('Pending','Accepted','Dispatched','Arrived','Pumping','Completed','Cancelled') NOT NULL,
            changed_by INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_status_history_order_time (order_id, created_at),
            CONSTRAINT fk_status_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_status_history_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");
        $connection->exec("CREATE TABLE IF NOT EXISTS order_messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            sender_id INT UNSIGNED NOT NULL,
            message VARCHAR(500) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_order_messages_order_time (order_id, created_at),
            CONSTRAINT fk_order_message_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_order_message_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB");
        $connection->exec("INSERT INTO order_status_history (order_id,order_status,changed_by,created_at)
            SELECT o.id,o.order_status,NULL,CURRENT_TIMESTAMP FROM orders o
            WHERE NOT EXISTS (SELECT 1 FROM order_status_history h WHERE h.order_id=o.id)");
        $ready = true;
        $_SESSION['realtime_schema_ready'] = true;
    } catch (Throwable $exception) {
        error_log('SmartFuel realtime schema warning: ' . $exception->getMessage());
        return false;
    }

    return true;
}

function render_unexpected_error(Throwable $exception): void
{
    error_log('SmartFuel uncaught error: ' . $exception->getMessage());
    $isDatabaseError = $exception instanceof PDOException || database_was_disconnected($exception);
    $message = $isDatabaseError
        ? 'The database is temporarily unavailable. Make sure MySQL is running in XAMPP, then try again.'
        : 'Something unexpected happened. Please refresh the page and try again.';

    if (!headers_sent()) {
        http_response_code(500);
    }

    if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }

    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $homeUrl = htmlspecialchars(BASE_URL . '/', ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SmartFuel Notice</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#060913;color:#f8fafc;font-family:Arial,sans-serif}.card{width:min(520px,calc(100% - 40px));border:1px solid #334155;border-radius:20px;background:#111827;padding:32px;box-shadow:0 24px 70px #0008}.icon{color:#f59e0b;font-size:36px}h1{margin:14px 0 8px;font-size:26px}p{color:#cbd5e1;line-height:1.6}.actions{display:flex;gap:10px;margin-top:22px;flex-wrap:wrap}a{display:inline-block;border-radius:11px;background:#06b6d4;padding:11px 16px;color:#061018;text-decoration:none;font-weight:700}.secondary{background:#1e293b;color:#f8fafc}</style></head><body><main class="card"><div class="icon">&#9888;</div><h1>We could not complete that request</h1><p>' . $safeMessage . '</p><div class="actions"><a href="">Try Again</a><a class="secondary" href="' . $homeUrl . '">Homepage</a></div></main></body></html>';
    exit;
}

set_exception_handler('render_unexpected_error');

function url(string $path = ''): string
{
    return BASE_URL . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Your session token expired. Go back, refresh the page and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function require_role(string $role): array
{
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        flash('error', 'Please sign in to continue.');
        redirect('auth/login.php');
    }

    if ($_SESSION['role'] !== $role) {
        redirect(role_home((string) $_SESSION['role']));
    }

    $statement = db()->prepare('SELECT * FROM users WHERE id = ? AND status = \'active\' LIMIT 1');
    $statement->execute([(int) $_SESSION['user_id']]);
    $user = $statement->fetch();

    if (!$user || $user['role'] !== $role) {
        session_unset();
        session_destroy();
        redirect('auth/login.php');
    }

    return $user;
}

function role_home(string $role): string
{
    return match ($role) {
        'admin' => 'admin/dashboard.php',
        'delivery_agent' => 'delivery/dashboard.php',
        default => 'customer/dashboard.php',
    };
}

function money(float|string $amount): string
{
    return '₹' . number_format((float) $amount, 2);
}

function status_class(string $status): string
{
    return match ($status) {
        'Completed', 'Paid', 'Processed', 'active' => 'success',
        'Cancelled', 'Refunded', 'suspended' => 'danger',
        'Dispatched', 'Arrived', 'Pumping' => 'info',
        default => 'warning',
    };
}

function tracking_code(): string
{
    return 'FD-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

function first_name(string $name): string
{
    return explode(' ', trim($name))[0] ?: $name;
}

require_once __DIR__ . '/upgrade.php';
