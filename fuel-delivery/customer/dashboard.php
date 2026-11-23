<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/dashboard_layout.php';
$user = require_role('customer');
ensure_realtime_schema();

$statsStatement = db()->prepare("SELECT COUNT(*) total_orders, COALESCE(SUM(quantity_liters),0) total_liters, COALESCE(SUM(CASE WHEN payment_status='Paid' THEN total_amount ELSE 0 END),0) total_spend, COALESCE(SUM(order_status NOT IN ('Completed','Cancelled')),0) active_orders FROM orders WHERE customer_id=?");
$statsStatement->execute([$user['id']]);
$stats = $statsStatement->fetch();
$addressStatement = db()->prepare('SELECT COUNT(*) FROM delivery_addresses WHERE customer_id=?');
$addressStatement->execute([$user['id']]);
$addressCount = (int) $addressStatement->fetchColumn();
$recentStatement = db()->prepare('SELECT o.*, u.full_name agent_name, live.latitude current_lat, live.longitude current_lng, live.updated_at location_updated_at FROM orders o LEFT JOIN users u ON u.id=o.delivery_agent_id LEFT JOIN agent_live_locations live ON live.agent_id=o.delivery_agent_id WHERE o.customer_id=? ORDER BY o.created_at DESC LIMIT 5');
$recentStatement->execute([$user['id']]);
$orders = $recentStatement->fetchAll();
$latest = sf_query("SELECT o.*,u.full_name agent_name FROM orders o LEFT JOIN users u ON u.id=o.delivery_agent_id WHERE o.customer_id=? AND o.order_status NOT IN ('Completed','Cancelled') ORDER BY o.id DESC LIMIT 1", [$user['id']])->fetch();

dashboard_start('Customer Command', 'dashboard', $user);
?>
<section class="welcome-panel"><div><span class="eyebrow">Customer operations</span><h2>Good to see you, <?= e(first_name($user['full_name'])) ?>.</h2><p>Request fuel, monitor active dispatches and manage every delivery from one command surface.</p></div><a class="btn" data-magnetic href="<?= url('customer/place_order.php') ?>"><i class="fa-solid fa-gas-pump"></i> Instant Refuel</a></section>
<section class="metrics-grid">
    <article class="metric-card" data-tilt><span class="metric-icon"><i class="fa-solid fa-truck-fast"></i></span><div><strong data-counter="<?= (int) $stats['active_orders'] ?>">0</strong><span>Active Orders</span></div></article>
    <article class="metric-card" data-tilt><span class="metric-icon"><i class="fa-solid fa-droplet"></i></span><div><strong data-counter="<?= (float) $stats['total_liters'] ?>" data-decimals="1" data-suffix=" L">0 L</strong><span>Fuel Ordered (all statuses)</span></div></article>
    <article class="metric-card" data-tilt><span class="metric-icon"><i class="fa-solid fa-indian-rupee-sign"></i></span><div><strong data-counter="<?= (float) $stats['total_spend'] ?>" data-prefix="₹">₹0</strong><span>Total Spend</span></div></article>
    <article class="metric-card" data-tilt><span class="metric-icon"><i class="fa-solid fa-location-dot"></i></span><div><strong data-counter="<?= $addressCount ?>">0</strong><span>Saved Addresses</span></div></article>
</section>
<?php if ($latest): ?>
<section class="panel"><div class="panel-head"><div><span class="eyebrow">Latest dispatch</span><h2>#<?= e($latest['tracking_code']) ?> · <?= e($latest['order_status']) ?></h2></div><a href="<?= url('customer/track_order.php?code=' . urlencode($latest['tracking_code'])) ?>">Open live tracking <i class="fa-solid fa-arrow-right"></i></a></div><?php $stages=['Pending','Accepted','Dispatched','Arrived','Pumping','Completed'];$current=array_search($latest['order_status'],$stages,true); ?><div class="tracking-pipeline"><?php foreach($stages as $index=>$stage): $class=$current!==false&&$index<$current?'done':($index===$current?'current':''); ?><div class="track-stage <?= $class ?>"><span class="track-dot"><i class="fa-solid <?= $index<=(int)$current?'fa-check':'fa-circle' ?>"></i></span><strong><?= e($stage) ?></strong></div><?php endforeach; ?></div></section>
<section class="panel" data-real-tracking data-code="<?= e($latest['tracking_code']) ?>">
    <div class="panel-head"><h2>Your delivery map</h2><span class="badge info" data-gps-state>Connecting…</span></div>
    <div class="sf-map" data-delivery-map></div>
    <div class="sf-metrics"><div><small>Estimated arrival</small><strong data-real-eta>Waiting for GPS</strong></div><div><small>Partner</small><strong data-real-agent>Awaiting assignment</strong></div></div>
    <p data-coordinate-fallback></p>
    <p class="sf-note">GPS-based arrival estimate. Open tracking for the delivery code, full timeline and chat.</p>
</section>
<?php endif; ?>
<section class="dashboard-grid">
    <div class="panel"><div class="panel-head"><h2>Recent Orders</h2><a href="<?= url('customer/order_history.php') ?>">Complete history <i class="fa-solid fa-arrow-right"></i></a></div><?php if(!$orders): ?><div class="empty-state"><i class="fa-solid fa-gas-pump"></i><h3>No orders yet</h3><p>Your first delivery will appear here.</p></div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Tracking</th><th>Fuel</th><th>Litres</th><th>Total</th><th>Agent</th><th>Status</th></tr></thead><tbody><?php foreach($orders as $order): ?><tr><td><strong><?= e($order['tracking_code']) ?></strong></td><td><?= e($order['fuel_type']) ?></td><td><?= e($order['quantity_liters']) ?> L</td><td class="money-tag"><?= money($order['total_amount']) ?></td><td><?= e($order['agent_name'] ?: 'Assigning') ?></td><td><span class="badge <?= status_class($order['order_status']) ?>"><?= e($order['order_status']) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
    <aside class="panel"><div class="panel-head"><h2>Quick Actions</h2></div><div class="quick-actions"><a class="quick-action" href="<?= url('customer/place_order.php?emergency=1') ?>"><i class="fa-solid fa-bolt"></i><div><strong>Emergency Refuel</strong><small>Subject to online partner availability</small></div></a><a class="quick-action" href="<?= url('customer/place_order.php') ?>"><i class="fa-solid fa-plus"></i><div><strong>New Fuel Order</strong><small>Live price calculator</small></div></a><a class="quick-action" href="<?= url('customer/track_order.php') ?>"><i class="fa-solid fa-location-crosshairs"></i><div><strong>Track Delivery</strong><small>Follow each stage</small></div></a><a class="quick-action" href="<?= url('customer/order_history.php') ?>"><i class="fa-solid fa-file-invoice"></i><div><strong>Invoices</strong><small>Order payment details</small></div></a><a class="quick-action" href="<?= url('customer/profile.php') ?>"><i class="fa-solid fa-car"></i><div><strong>Vehicles & Addresses</strong><small>Manage saved information</small></div></a></div></aside>
</section>
<?php dashboard_end(); ?>
