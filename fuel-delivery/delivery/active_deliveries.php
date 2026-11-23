<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/dashboard_layout.php';
$user = require_role('delivery_agent');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $id = (int) ($_POST['order_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'accept') {
            sf_assign($id, (int)$user['id'], $user);
        } else {
            sf_transition($id, $action, $user);
        }
        flash('success', 'Delivery updated successfully.');
    } catch (Throwable $error) {
        flash('error', sf_error($error));
    }
    redirect('delivery/active_deliveries.php');
}
$active = sf_query("SELECT o.*,u.full_name customer_name,u.phone customer_phone,d.latitude,d.longitude,d.landmark,d.emergency FROM orders o JOIN users u ON u.id=o.customer_id LEFT JOIN sf_order_details d ON d.order_id=o.id WHERE o.delivery_agent_id=? AND o.order_status NOT IN ('Completed','Cancelled') ORDER BY d.emergency DESC,o.created_at", [$user['id']])->fetchAll();
$eligible = sf_approved((int)$user['id']) && sf_query('SELECT is_online FROM delivery_agent_profiles WHERE user_id=?', [$user['id']])->fetchColumn();
$available = $eligible ? sf_query("SELECT o.*,d.emergency FROM orders o LEFT JOIN sf_order_details d ON d.order_id=o.id WHERE o.delivery_agent_id IS NULL AND o.order_status='Pending' ORDER BY d.emergency DESC,o.created_at")->fetchAll() : [];
dashboard_start('Active Deliveries', 'active', $user);
?>
<section class="welcome-panel">
    <div><span class="eyebrow">Delivery workspace</span><h2>Your next move, made clear.</h2><p>Completion needs the customer's delivery code, payment confirmation and a meter/receipt photo.</p></div>
    <span class="badge info"><?= count($active) ?> active jobs</span>
</section>
<?php if (!$eligible): ?><div class="alert warning">Admin approval and online duty are required to receive new requests.</div><?php endif; ?>
<section class="panel">
    <div class="panel-head"><h2>Your active jobs</h2></div>
    <?php if (!$active): ?><p>No assigned jobs yet.</p><?php endif; ?>
    <?php foreach ($active as $job): ?>
    <article class="job-card">
        <div class="panel-head">
            <div><span class="eyebrow"><?= e($job['tracking_code']) ?></span><h3><?= e($job['fuel_type']) ?> · <?= e($job['quantity_liters']) ?> L</h3></div>
            <span class="badge <?= status_class($job['order_status']) ?>"><?= e($job['order_status']) ?></span>
        </div>
        <?php if ($job['emergency']): ?><span class="badge danger">Emergency request</span><?php endif; ?>
        <p><strong><?= e($job['customer_name']) ?></strong> · <?= e($job['vehicle_number']) ?></p>
        <p><?= e($job['delivery_address']) ?> · <?= e($job['delivery_pincode']) ?></p>
        <p>Landmark: <?= e($job['landmark'] ?: 'Not provided') ?> · <?= e($job['delivery_time_slot']) ?></p>
        <div class="action-row">
            <a class="btn btn-secondary btn-small" href="tel:<?= e($job['customer_phone']) ?>">Call customer</a>
            <a class="btn btn-secondary btn-small" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= urlencode($job['latitude'] !== null ? $job['latitude'].','.$job['longitude'] : $job['delivery_address'].' '.$job['delivery_pincode']) ?>">Open directions</a>
        </div>
        <?php $actions = ['Accepted'=>['dispatch','Start route'],'Dispatched'=>['arrive','Arrived at location'],'Arrived'=>['pump','Start fueling'],'Pumping'=>['complete','Confirm delivery']]; ?>
        <?php if (isset($actions[$job['order_status']])): ?>
        <form method="post" enctype="multipart/form-data" class="sf-action-form">
            <?= csrf_field() ?>
            <input type="hidden" name="order_id" value="<?= (int)$job['id'] ?>">
            <input type="hidden" name="action" value="<?= e($actions[$job['order_status']][0]) ?>">
            <?php if ($job['order_status'] === 'Pumping'): ?>
            <div class="form-grid">
                <div class="form-group"><label>Customer delivery code<input name="confirmation_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off" required></label></div>
                <div class="form-group"><label>Actual delivered litres<input name="actual_liters" type="number" step="0.01" min="1" value="<?= e($job['quantity_liters']) ?>" required></label></div>
                <div class="form-group full"><label>Receipt or meter photo (up to 5 MB)<input type="file" name="proof" accept="image/jpeg,image/png,image/webp" required></label></div>
            </div>
            <?php if ($job['payment_status'] !== 'Paid'): ?><label class="sf-check"><input type="checkbox" name="payment_collected" value="1" required> I collected <?= money($job['total_amount']) ?>.</label><?php endif; ?>
            <?php endif; ?>
            <button class="btn"><?= e($actions[$job['order_status']][1]) ?></button>
        </form>
        <?php endif; ?>
        <?php sf_chat($job['tracking_code'], 'Customer conversation'); ?>
    </article>
    <?php endforeach; ?>
</section>
<section class="panel">
    <div class="panel-head"><h2>Available requests</h2><span class="badge warning"><?= count($available) ?> waiting</span></div>
    <?php if (!$available): ?><p>No available requests right now. New requests appear in notifications.</p><?php endif; ?>
    <?php foreach ($available as $job): ?>
    <article class="job-card">
        <h3><?= e($job['tracking_code']) ?> <?= $job['emergency'] ? '· Emergency' : '' ?></h3>
        <p><?= e($job['fuel_type']) ?> · <?= e($job['quantity_liters']) ?> L · Pincode <?= e($job['delivery_pincode']) ?></p>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int)$job['id'] ?>"><input type="hidden" name="action" value="accept">
            <button class="btn btn-small">Accept order</button>
        </form>
    </article>
    <?php endforeach; ?>
</section>
<?php dashboard_end(); ?>
