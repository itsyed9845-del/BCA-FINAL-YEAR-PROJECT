<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/dashboard_layout.php';
$user = require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        if (($_POST['action'] ?? '') === 'assign') {
            sf_assign((int)($_POST['order_id'] ?? 0), (int)($_POST['agent_id'] ?? 0), $user);
        } elseif (($_POST['action'] ?? '') === 'cancel') {
            sf_cancel((int)($_POST['order_id'] ?? 0), $user, trim((string)($_POST['reason'] ?? '')));
        } else {
            throw new RuntimeException('Unknown action.');
        }
        flash('success', 'Order updated across all portals.');
    } catch (Throwable $error) {
        flash('error', sf_error($error));
    }
    redirect('admin/manage_orders.php');
}
$status = (string)($_GET['status'] ?? '');
$statuses = ['Pending','Accepted','Dispatched','Arrived','Pumping','Completed','Cancelled'];
$where = in_array($status, $statuses, true) ? ' WHERE o.order_status=?' : '';
$orders = sf_query("SELECT o.*,c.full_name customer_name,a.full_name agent_name,d.latitude,d.longitude,d.emergency FROM orders o JOIN users c ON c.id=o.customer_id LEFT JOIN users a ON a.id=o.delivery_agent_id LEFT JOIN sf_order_details d ON d.order_id=o.id" . $where . ' ORDER BY d.emergency DESC,o.created_at DESC', $where ? [$status] : [])->fetchAll();
$agents = sf_query("SELECT u.id,u.full_name,l.latitude,l.longitude,TIMESTAMPDIFF(SECOND,l.updated_at,NOW()) gps_age,(SELECT COUNT(*) FROM orders x WHERE x.delivery_agent_id=u.id AND x.order_status NOT IN ('Completed','Cancelled')) workload FROM users u JOIN delivery_agent_profiles p ON p.user_id=u.id JOIN sf_agent_approvals a ON a.user_id=u.id LEFT JOIN agent_live_locations l ON l.agent_id=u.id WHERE u.role='delivery_agent' AND u.status='active' AND p.is_online=1 AND a.approval_status='Approved'")->fetchAll();
dashboard_start('Manage Orders', 'orders', $user);
?>
<section class="welcome-panel"><div><span class="eyebrow">Command center</span><h2>One clear view of every delivery.</h2><p>Suggestions rank online partners by workload, then available GPS distance. You confirm the assignment.</p></div></section>
<section class="panel">
    <div class="toolbar">
        <input data-table-filter="#orders-table" placeholder="Search orders" aria-label="Search orders">
        <form method="get"><select name="status" aria-label="Order status"><option value="">All statuses</option><?php foreach ($statuses as $s): ?><option <?= $s === $status ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select><button class="btn btn-small">Filter</button></form>
    </div>
    <div class="table-wrap"><table id="orders-table">
        <thead><tr><th>Order</th><th>Customer / fuel</th><th>Value</th><th>Status</th><th>Partner</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
        <tr>
            <td><strong><?= e($order['tracking_code']) ?></strong><br><?= e($order['created_at']) ?><?= $order['emergency'] ? '<br><span class="badge danger">Emergency</span>' : '' ?></td>
            <td><?= e($order['customer_name']) ?><br><?= e($order['fuel_type']) ?> · <?= e($order['quantity_liters']) ?> L</td>
            <td><?= money($order['total_amount']) ?><br><?= e($order['payment_status']) ?></td>
            <td><span class="badge <?= status_class($order['order_status']) ?>"><?= e($order['order_status']) ?></span></td>
            <td>
            <?php if ($order['order_status'] === 'Pending' && !$order['delivery_agent_id']): ?>
                <?php
                $ranked = $agents;
                foreach ($ranked as &$agent) {
                    $agent['distance'] = $order['latitude'] !== null && $agent['latitude'] !== null && $agent['gps_age'] <= 90
                        ? sf_km((float)$order['latitude'],(float)$order['longitude'],(float)$agent['latitude'],(float)$agent['longitude']) : null;
                }
                unset($agent);
                usort($ranked, function ($a, $b) {
                    return ((int)$a['workload'] <=> (int)$b['workload']) ?: (($a['distance'] ?? INF) <=> ($b['distance'] ?? INF));
                });
                ?>
                <form method="post" class="sf-assign">
                    <?= csrf_field() ?><input type="hidden" name="action" value="assign"><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                    <select name="agent_id" required aria-label="Choose partner">
                        <option value="">Choose online partner</option>
                        <?php foreach ($ranked as $i=>$agent): ?>
                        <option value="<?= (int)$agent['id'] ?>"><?= $i === 0 ? 'Suggested · ' : '' ?><?= e($agent['full_name']) ?> · <?= (int)$agent['workload'] ?> jobs · <?= $agent['distance'] === null ? 'GPS unavailable' : number_format($agent['distance'],1).' km straight line' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-small" <?= !$ranked ? 'disabled' : '' ?>>Assign</button>
                </form>
            <?php else: ?><?= e($order['agent_name'] ?: 'Unassigned') ?><?php endif; ?>
            </td>
            <td>
                <?php if (in_array($order['order_status'], ['Pending','Accepted'], true)): ?>
                <details><summary>Cancel order</summary><form method="post">
                    <?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                    <input name="reason" minlength="5" maxlength="300" placeholder="Cancellation reason" required>
                    <button class="btn btn-secondary btn-small">Confirm cancel</button>
                </form></details>
                <?php elseif ($order['order_status'] === 'Completed'): ?>
                <a href="<?= url('api/proof.php?order_id='.(int)$order['id']) ?>">View delivery proof</a>
                <?php else: ?><small>Updated by the assigned partner</small><?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<?php dashboard_end(); ?>
