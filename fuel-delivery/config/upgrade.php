<?php
declare(strict_types=1);

// Additive, versioned migration. Existing accounts, orders and inventory are kept.
function sf_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS sf_versions (version INT PRIMARY KEY, installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    if ($pdo->query('SELECT version FROM sf_versions WHERE version=1')->fetchColumn()) {
        return;
    }
    if ((int) $pdo->query("SELECT GET_LOCK('smartfuel_upgrade_v1',15)")->fetchColumn() !== 1) {
        throw new RuntimeException('Upgrade is busy. Refresh in a moment.');
    }
    try {
        if ($pdo->query('SELECT version FROM sf_versions WHERE version=1')->fetchColumn()) {
            return;
        }
        if (!ensure_realtime_schema()) {
            throw new RuntimeException('Could not prepare tracking tables.');
        }
        $statements = [
            "CREATE TABLE IF NOT EXISTS sf_agent_approvals (
                user_id INT UNSIGNED PRIMARY KEY,
                approval_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
                reviewed_by INT UNSIGNED NULL,
                reviewed_at DATETIME NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS sf_order_details (
                order_id INT UNSIGNED PRIMARY KEY,
                latitude DECIMAL(10,7) NULL,
                longitude DECIMAL(10,7) NULL,
                landmark VARCHAR(200) NOT NULL DEFAULT '',
                emergency TINYINT NOT NULL DEFAULT 0,
                confirmation_code CHAR(6) NULL,
                code_failures INT NOT NULL DEFAULT 0,
                code_locked_until DATETIME NULL,
                cancel_reason VARCHAR(300) NULL,
                refund_status ENUM('Not required','Pending','Recorded') NOT NULL DEFAULT 'Not required',
                refund_reference VARCHAR(150) NULL,
                actual_liters DECIMAL(8,2) NULL,
                proof_file VARCHAR(100) NULL,
                completed_at DATETIME NULL,
                request_key CHAR(64) NULL UNIQUE,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS sf_notifications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                message VARCHAR(300) NOT NULL,
                target VARCHAR(200) NOT NULL,
                is_read TINYINT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_notifications_user (user_id,is_read,id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS sf_tickets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                subject VARCHAR(150) NOT NULL,
                status ENUM('Open','In progress','Resolved') NOT NULL DEFAULT 'Open',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS sf_ticket_messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ticket_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (ticket_id) REFERENCES sf_tickets(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS sf_inventory_settings (
                fuel_id INT UNSIGNED PRIMARY KEY,
                low_stock_liters DECIMAL(12,2) NOT NULL DEFAULT 500,
                FOREIGN KEY (fuel_id) REFERENCES fuel_inventory(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS sf_stock_movements (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                fuel_id INT UNSIGNED NOT NULL,
                order_id INT UNSIGNED NULL,
                liters DECIMAL(12,2) NOT NULL,
                reason VARCHAR(200) NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (fuel_id) REFERENCES fuel_inventory(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            ) ENGINE=InnoDB",
            "CREATE TABLE IF NOT EXISTS sf_payout_receipts (
                earning_id INT UNSIGNED PRIMARY KEY,
                reference VARCHAR(150) NOT NULL,
                admin_id INT UNSIGNED NOT NULL,
                paid_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (earning_id) REFERENCES delivery_earnings(id)
            ) ENGINE=InnoDB"
        ];
        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }
        $pdo->exec("INSERT IGNORE INTO sf_agent_approvals(user_id,approval_status) SELECT id,'Approved' FROM users WHERE role='delivery_agent'");
        $pdo->exec('INSERT IGNORE INTO sf_order_details(order_id) SELECT id FROM orders');
        $pdo->exec("UPDATE sf_order_details d JOIN orders o ON o.id=d.order_id SET d.refund_status='Pending' WHERE o.order_status='Cancelled' AND o.payment_status='Paid' AND d.refund_status='Not required'");
        $pdo->exec('INSERT IGNORE INTO sf_inventory_settings(fuel_id) SELECT id FROM fuel_inventory');
        // Previously seeded coordinates cannot be treated as a current GPS fix.
        $pdo->exec('DELETE FROM agent_live_locations');
        $pdo->exec('INSERT INTO sf_versions(version) VALUES(1)');
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('smartfuel_upgrade_v1')");
    }
}

function sf_query(string $sql, array $parameters = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($parameters);
    return $statement;
}

function sf_notify(int $userId, string $message, string $target): void
{
    sf_query('INSERT INTO sf_notifications(user_id,message,target) VALUES(?,?,?)', [$userId,$message,$target]);
}

function sf_notify_admins(string $message, string $target): void
{
    foreach (sf_query("SELECT id FROM users WHERE role='admin' AND status='active'")->fetchAll() as $admin) {
        sf_notify((int) $admin['id'], $message, $target);
    }
}

function sf_approved(int $id): bool
{
    return sf_query("SELECT user_id FROM sf_agent_approvals WHERE user_id=? AND approval_status='Approved'", [$id])->fetchColumn() !== false;
}

function sf_order(int $id, array $user, bool $lock = false): array
{
    $order = sf_query('SELECT * FROM orders WHERE id=?' . ($lock ? ' FOR UPDATE' : ''), [$id])->fetch();
    if (!$order || ($user['role'] === 'customer' && (int) $order['customer_id'] !== (int) $user['id'])
        || ($user['role'] === 'delivery_agent' && (int) $order['delivery_agent_id'] !== (int) $user['id'])) {
        throw new RuntimeException('This order is not available to your account.');
    }
    return $order;
}

function sf_history(array $order, string $status, int $actor): void
{
    sf_query('INSERT INTO order_status_history(order_id,order_status,changed_by) VALUES(?,?,?)', [$order['id'],$status,$actor]);
    sf_notify((int) $order['customer_id'], $order['tracking_code'] . ': ' . $status, 'customer/track_order.php?code=' . urlencode($order['tracking_code']));
    sf_notify_admins($order['tracking_code'] . ': ' . $status, 'admin/manage_orders.php');
    if ($order['delivery_agent_id']) {
        sf_notify((int) $order['delivery_agent_id'], $order['tracking_code'] . ': ' . $status, 'delivery/active_deliveries.php');
    }
}

function sf_assign(int $orderId, int $agentId, array $actor): void
{
    if (!in_array($actor['role'], ['admin','delivery_agent'], true) || ($actor['role'] === 'delivery_agent' && $agentId !== (int) $actor['id'])) {
        throw new RuntimeException('Assignment is not allowed.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $order = sf_query('SELECT * FROM orders WHERE id=? FOR UPDATE', [$orderId])->fetch();
        if (!$order || $order['order_status'] !== 'Pending' || $order['delivery_agent_id']) {
            throw new RuntimeException('This request has already been assigned or changed.');
        }
        $agent = sf_query("SELECT u.id FROM users u JOIN delivery_agent_profiles p ON p.user_id=u.id JOIN sf_agent_approvals a ON a.user_id=u.id WHERE u.id=? AND u.status='active' AND u.role='delivery_agent' AND p.is_online=1 AND a.approval_status='Approved' FOR UPDATE", [$agentId])->fetch();
        if (!$agent) {
            throw new RuntimeException('Choose an approved partner who is online.');
        }
        sf_query("UPDATE orders SET delivery_agent_id=?,order_status='Accepted' WHERE id=?", [$agentId,$orderId]);
        $order['delivery_agent_id'] = $agentId;
        sf_history($order, 'Accepted', (int) $actor['id']);
        $pdo->commit();
    } catch (Throwable $error) {
        safe_rollback($pdo);
        throw $error;
    }
}

function sf_transition(int $id, string $action, array $user): void
{
    $steps = ['dispatch'=>['Accepted','Dispatched'], 'arrive'=>['Dispatched','Arrived'], 'pump'=>['Arrived','Pumping'], 'complete'=>['Pumping','Completed']];
    if ($user['role'] !== 'delivery_agent' || !isset($steps[$action]) || !sf_approved((int) $user['id'])) {
        throw new RuntimeException('This action is not permitted.');
    }
    $pdo = db();
    $proof = null;
    $pdo->beginTransaction();
    try {
        $order = sf_order($id, $user, true);
        [$from,$to] = $steps[$action];
        if ($order['order_status'] !== $from) {
            throw new RuntimeException('Order changed. Refresh before continuing.');
        }
        if ($action === 'complete') {
            $details = sf_query('SELECT * FROM sf_order_details WHERE order_id=? FOR UPDATE', [$id])->fetch();
            if (!$details['confirmation_code']) {
                throw new RuntimeException('Ask the customer to open tracking to generate the delivery code.');
            }
            if ($details['code_locked_until'] && strtotime($details['code_locked_until']) > time()) {
                throw new RuntimeException('Too many incorrect codes. Wait five minutes.');
            }
            if (!hash_equals($details['confirmation_code'], trim((string) ($_POST['confirmation_code'] ?? '')))) {
            sf_query('UPDATE sf_order_details SET code_failures=IF(code_locked_until IS NOT NULL AND code_locked_until<NOW(),1,code_failures+1),code_locked_until=IF(code_failures>=5,DATE_ADD(NOW(),INTERVAL 5 MINUTE),NULL) WHERE order_id=?', [$id]);
                $pdo->commit();
                throw new RuntimeException('The delivery code is incorrect. Ask the customer to check it.');
            }
            $liters = filter_var($_POST['actual_liters'] ?? '', FILTER_VALIDATE_FLOAT);
            if ($liters === false || abs($liters - (float) $order['quantity_liters']) > 0.009) {
                throw new RuntimeException('Delivered litres must match the order. Contact support for quantity disputes.');
            }
            if ($order['payment_status'] !== 'Paid' && empty($_POST['payment_collected'])) {
                throw new RuntimeException('Confirm payment was collected before completing delivery.');
            }
            $file = $_FILES['proof'] ?? null;
            if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) {
                throw new RuntimeException('Upload a JPG, PNG or WebP receipt/meter photo, maximum 5 MB.');
            }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if (!isset($extensions[$mime]) || !getimagesize($file['tmp_name'])) {
                throw new RuntimeException('The proof must be a valid photo.');
            }
            $name = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
            $directory = __DIR__ . '/../storage/proofs';
            if (!is_dir($directory) && !mkdir($directory, 0750, true)) {
                throw new RuntimeException('Receipt storage is unavailable.');
            }
            $proof = $directory . '/' . $name;
            if (!move_uploaded_file($file['tmp_name'], $proof)) {
                throw new RuntimeException('Could not save the receipt photo.');
            }
            sf_query('UPDATE sf_order_details SET actual_liters=?,proof_file=?,completed_at=NOW(),code_failures=0,code_locked_until=NULL WHERE order_id=?', [$liters,$name,$id]);
            // Transparent payout policy: delivery fee, no invented distance bonus.
            sf_query("INSERT INTO delivery_earnings(agent_id,order_id,base_payout,distance_bonus,tip_amount,total_payout,payout_status) VALUES(?,?,?,0,0,?,'Pending')", [$user['id'],$id,$order['delivery_fee'],$order['delivery_fee']]);
            sf_query("UPDATE orders SET payment_status='Paid' WHERE id=?", [$id]);
        }
        sf_query('UPDATE orders SET order_status=? WHERE id=?', [$to,$id]);
        sf_history($order, $to, (int) $user['id']);
        $pdo->commit();
    } catch (Throwable $error) {
        safe_rollback($pdo);
        if ($proof && is_file($proof)) {
            unlink($proof);
        }
        throw $error;
    }
}

function sf_cancel(int $id, array $user, string $reason): void
{
    if (!in_array($user['role'], ['customer','admin'], true) || mb_strlen($reason) < 5 || mb_strlen($reason) > 300) {
        throw new RuntimeException('Provide a cancellation reason of 5–300 characters.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $order = sf_order($id, $user, true);
        if (!in_array($order['order_status'], ['Pending','Accepted'], true)) {
            throw new RuntimeException('Cancellation is available only before dispatch.');
        }
        $fuel = sf_query('SELECT * FROM fuel_inventory WHERE fuel_type=? FOR UPDATE', [$order['fuel_type']])->fetch();
        sf_query('UPDATE fuel_inventory SET stock_liters=stock_liters+? WHERE id=?', [$order['quantity_liters'],$fuel['id']]);
        sf_query('INSERT INTO sf_stock_movements(fuel_id,order_id,liters,reason,user_id) VALUES(?,?,?,?,?)', [$fuel['id'],$id,$order['quantity_liters'],'Cancellation return',$user['id']]);
        sf_query("UPDATE orders SET order_status='Cancelled' WHERE id=?", [$id]);
        sf_query('UPDATE sf_order_details SET cancel_reason=?,refund_status=? WHERE order_id=?', [$reason,$order['payment_status'] === 'Paid' ? 'Pending' : 'Not required',$id]);
        sf_history($order, 'Cancelled', (int) $user['id']);
        $pdo->commit();
    } catch (Throwable $error) {
        safe_rollback($pdo);
        throw $error;
    }
}

function sf_km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $a = sin(deg2rad($lat2-$lat1)/2) ** 2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin(deg2rad($lng2-$lng1)/2) ** 2;
    return 6371 * 2 * atan2(sqrt($a), sqrt(max(0,1-$a)));
}

function sf_error(Throwable $error): string
{
    return $error instanceof RuntimeException && !$error instanceof PDOException
        ? $error->getMessage()
        : friendly_database_error($error, 'The change could not be saved. Refresh and try again.');
}
