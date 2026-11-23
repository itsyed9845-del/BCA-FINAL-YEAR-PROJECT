<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/dashboard_layout.php';
require_once __DIR__ . '/../includes/address_fields.php';
$user = require_role('customer');
$code = trim((string)($_GET['code'] ?? ''));
$order = $code !== ''
    ? sf_query('SELECT * FROM orders WHERE tracking_code=? AND customer_id=?', [$code,$user['id']])->fetch()
    : sf_query("SELECT * FROM orders WHERE customer_id=? AND order_status NOT IN ('Completed','Cancelled') ORDER BY id DESC LIMIT 1", [$user['id']])->fetch();
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'cancel') {
            sf_cancel((int)$order['id'], $user, trim((string)($_POST['reason'] ?? '')));
        } elseif ($action === 'review') {
            $rating = filter_var($_POST['rating'] ?? '', FILTER_VALIDATE_INT);
            $review = trim((string)($_POST['review'] ?? ''));
            if (!$rating || $rating < 1 || $rating > 5 || mb_strlen($review) > 1000 || !$order['delivery_agent_id']) {
                throw new RuntimeException('Choose a rating from 1 to 5 and a review up to 1,000 characters.');
            }
            db()->beginTransaction();
            $locked = sf_order((int)$order['id'], $user, true);
            if ($locked['order_status'] !== 'Completed') {
                throw new RuntimeException('You can review only completed deliveries.');
            }
            if (sf_query('SELECT id FROM reviews_ratings WHERE order_id=?', [$order['id']])->fetchColumn()) {
                throw new RuntimeException('You already reviewed this delivery.');
            }
            sf_query('SELECT user_id FROM delivery_agent_profiles WHERE user_id=? FOR UPDATE', [$order['delivery_agent_id']])->fetch();
            sf_query('INSERT INTO reviews_ratings(order_id,customer_id,agent_id,rating,review_text) VALUES(?,?,?,?,?)', [$order['id'],$user['id'],$order['delivery_agent_id'],$rating,$review]);
            sf_query('UPDATE delivery_agent_profiles SET rating=(SELECT ROUND(AVG(rating),2) FROM reviews_ratings WHERE agent_id=?) WHERE user_id=?', [$order['delivery_agent_id'],$order['delivery_agent_id']]);
            sf_notify((int)$order['delivery_agent_id'], 'A customer rated your delivery ' . $rating . '/5.', 'delivery/profile.php');
            db()->commit();
        } elseif ($action === 'address') {
            $lat = trim((string)($_POST['latitude'] ?? '')) === '' ? null : filter_var($_POST['latitude'], FILTER_VALIDATE_FLOAT);
            $lng = trim((string)($_POST['longitude'] ?? '')) === '' ? null : filter_var($_POST['longitude'], FILTER_VALIDATE_FLOAT);
            $landmark = trim((string)($_POST['landmark'] ?? ''));
            $address = trim((string)($_POST['delivery_address'] ?? ''));
            $pincode = trim((string)($_POST['delivery_pincode'] ?? ''));
            if ($lat === false || $lng === false || (($lat === null) !== ($lng === null)) || ($lat !== null && (!is_finite($lat) || abs($lat)>90)) || ($lng !== null && (!is_finite($lng) || abs($lng)>180)) || mb_strlen($landmark)>200 || mb_strlen($address)<10 || mb_strlen($address)>500 || !preg_match('/^[0-9]{6}$/', $pincode)) {
                throw new RuntimeException('Enter a complete address, a six-digit pincode and a landmark up to 200 characters.');
            }
            db()->beginTransaction();
            $locked = sf_order((int)$order['id'], $user, true);
            if (in_array($locked['order_status'], ['Completed','Cancelled'], true)) {
                throw new RuntimeException('This order is closed.');
            }
            sf_query('UPDATE orders SET delivery_address=?,delivery_pincode=? WHERE id=? AND customer_id=?', [$address,$pincode,$order['id'],$user['id']]);
            sf_query('UPDATE sf_order_details SET latitude=?,longitude=?,landmark=? WHERE order_id=?', [$lat,$lng,$landmark,$order['id']]);
            if ($locked['delivery_agent_id']) {
                sf_notify((int)$locked['delivery_agent_id'], 'Customer updated the delivery address for ' . $order['tracking_code'], 'delivery/active_deliveries.php');
            }
            db()->commit();
        } else {
            throw new RuntimeException('Unknown action.');
        }
        flash('success', 'Saved successfully.');
    } catch (Throwable $error) {
        safe_rollback(db());
        flash('error', sf_error($error));
    }
    redirect('customer/track_order.php?code=' . urlencode($order['tracking_code']));
}
if ($order) {
    if (!in_array($order['order_status'], ['Completed','Cancelled'], true)) {
        sf_query('UPDATE sf_order_details SET confirmation_code=? WHERE order_id=? AND confirmation_code IS NULL', [(string)random_int(100000,999999),$order['id']]);
    }
    $details = sf_query('SELECT * FROM sf_order_details WHERE order_id=?', [$order['id']])->fetch();
    $reviewed = sf_query('SELECT rating,review_text FROM reviews_ratings WHERE order_id=?', [$order['id']])->fetch();
}
dashboard_start('Track Your Delivery', 'track', $user);
?>
<section class="welcome-panel">
    <div><span class="eyebrow">Live delivery</span><h2>From dispatch to your doorstep.</h2><p>Actual shared GPS, recorded milestones and direct contact with your partner.</p></div>
    <form class="toolbar" method="get"><input name="code" value="<?= e($code) ?>" placeholder="Tracking code" aria-label="Tracking code"><button class="btn btn-small">Find order</button></form>
</section>
<?php if (!$order): ?>
<section class="panel"><h2>No matching active order</h2><p>Use a tracking code from Order History.</p></section>
<?php else: ?>
<section class="panel">
    <div class="panel-head"><div><span class="eyebrow"><?= e($order['tracking_code']) ?></span><h2><?= e($order['fuel_type']) ?> · <?= e($order['quantity_liters']) ?> L</h2></div><span class="badge info" data-live-status><?= e($order['order_status']) ?></span></div>
    <p><?= e($order['delivery_address']) ?> · <?= e($order['delivery_pincode']) ?> · <?= e($order['vehicle_number']) ?></p>
    <p>Requested window: <?= e($order['delivery_time_slot']) ?> · Total: <?= money($order['total_amount']) ?></p>
    <a class="btn btn-secondary btn-small" href="<?= url('customer/place_order.php?again='.(int)$order['id']) ?>">Order again at current prices</a>
</section>
<section class="panel" data-real-tracking data-code="<?= e($order['tracking_code']) ?>">
    <div class="panel-head"><h2>Delivery map</h2><span class="badge warning" data-gps-state>Connecting…</span></div>
    <div class="sf-map" data-delivery-map></div>
    <div class="sf-metrics">
        <div><small>Estimated arrival</small><strong data-real-eta>Waiting for GPS</strong></div>
        <div><small>GPS signal</small><strong data-real-age>Not available</strong></div>
        <div><small>Accuracy</small><strong data-real-accuracy>—</strong></div>
        <div><small>Partner</small><strong data-real-agent>Awaiting assignment</strong></div>
    </div>
    <p data-coordinate-fallback></p>
    <p class="sf-note">Arrival is an estimate based on straight-line distance, a road-distance allowance and 25 km/h. It is not traffic-aware. Stale GPS pauses the estimate.</p>
    <a class="btn btn-secondary btn-small" data-agent-call hidden>Call partner</a>
    <ol class="sf-timeline" data-real-history></ol>
</section>
<?php if (!in_array($order['order_status'], ['Completed','Cancelled'], true)): ?>
<div class="dashboard-grid equal">
    <section class="panel">
        <span class="eyebrow">Delivery confirmation</span><h2 class="sf-code"><?= e($details['confirmation_code']) ?></h2>
        <p>Share this code with your partner only after checking your delivered fuel and receipt. It is visible only in your customer account.</p>
        <?php sf_chat($order['tracking_code'], 'Message your delivery partner'); ?>
    </section>
    <section class="panel">
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="address">
            <?php sf_address_fields(array_merge($order, $details ?: [])); ?>
            <button class="btn btn-secondary btn-small">Save address</button>
        </form>
    </section>
</div>
<?php endif; ?>
<?php if (in_array($order['order_status'], ['Pending','Accepted'], true)): ?>
<section class="panel"><details><summary>Cancel before dispatch</summary><form method="post" class="sf-action-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="cancel">
    <div class="form-group"><label>Reason<input name="reason" minlength="5" maxlength="300" required></label></div>
    <button class="btn btn-secondary">Confirm cancellation</button>
</form></details></section>
<?php endif; ?>
<?php if ($order['order_status'] === 'Cancelled'): ?>
<section class="panel"><h2>Cancellation & refund</h2><p><?= e($details['cancel_reason'] ?: 'Cancelled') ?></p><p>Refund: <?= e($details['refund_status']) ?></p><p><?= e($details['refund_reference'] ?? '') ?></p></section>
<?php endif; ?>
<?php if ($order['order_status'] === 'Completed'): ?>
<section class="panel">
    <h2>Delivery receipt</h2>
    <?php if ($details['proof_file']): ?><p><?= e($details['actual_liters']) ?> litres delivered · <?= e($details['completed_at']) ?></p><a class="btn btn-secondary btn-small" href="<?= url('api/proof.php?order_id='.(int)$order['id']) ?>" target="_blank" rel="noopener">View receipt photo</a><?php else: ?><p>No photo was recorded for this older delivery.</p><?php endif; ?>
    <?php if ($reviewed): ?><p>Your rating: <?= (int)$reviewed['rating'] ?>/5 · <?= e($reviewed['review_text']) ?></p><?php elseif ($order['delivery_agent_id']): ?>
    <form method="post" class="sf-action-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="review">
        <div class="form-group"><label>Rate your delivery<select name="rating" required><option value="">Choose rating</option><?php for ($i=5;$i>=1;$i--): ?><option value="<?= $i ?>"><?= $i ?> / 5</option><?php endfor; ?></select></label></div>
        <div class="form-group"><label>Your review<textarea name="review" maxlength="1000" rows="3"></textarea></label></div>
        <button class="btn">Submit review</button>
    </form>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php endif; ?>
<?php dashboard_end(); ?>
