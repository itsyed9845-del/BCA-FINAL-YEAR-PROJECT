<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/dashboard_layout.php';
require_once __DIR__ . '/../includes/address_fields.php';
$user = require_role('customer');
$realtimeReady=ensure_realtime_schema();
$inventory = db()->query('SELECT * FROM fuel_inventory ORDER BY id')->fetchAll();
$vehiclesStatement = db()->prepare('SELECT * FROM customer_vehicles WHERE customer_id=? ORDER BY is_default DESC,id');
$vehiclesStatement->execute([$user['id']]);
$vehicles = $vehiclesStatement->fetchAll();
$addressesStatement = db()->prepare('SELECT * FROM delivery_addresses WHERE customer_id=? ORDER BY is_default DESC,id');
$addressesStatement->execute([$user['id']]);
$addresses = $addressesStatement->fetchAll();
$error = '';
$repeat = null;
if (isset($_GET['again'])) {
    $repeat = sf_query('SELECT * FROM orders WHERE id=? AND customer_id=?', [(int) $_GET['again'],$user['id']])->fetch();
}
if (empty($_SESSION['order_request_key'])) {
    $_SESSION['order_request_key'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $requestKey = (string) ($_POST['request_key'] ?? '');
    $duplicate = sf_query('SELECT o.tracking_code FROM orders o JOIN sf_order_details d ON d.order_id=o.id WHERE d.request_key=? AND o.customer_id=?', [$requestKey,$user['id']])->fetchColumn();
    if ($duplicate) {
        redirect('customer/track_order.php?code=' . urlencode($duplicate));
    }
    $fuelId = (int) ($_POST['fuel_id'] ?? 0);
    $liters = (float) ($_POST['quantity_liters'] ?? 0);
    $vehicleNumber = strtoupper(trim((string) ($_POST['vehicle_number'] ?? '')));
    $address = trim((string) ($_POST['delivery_address'] ?? ''));
    $pincode = preg_replace('/\D+/', '', (string) ($_POST['delivery_pincode'] ?? ''));
    $slot = trim((string) ($_POST['delivery_time_slot'] ?? ''));
    $payment = (string) ($_POST['payment_method'] ?? '');

    $lat = trim((string)($_POST['latitude'] ?? '')) === '' ? null : filter_var($_POST['latitude'], FILTER_VALIDATE_FLOAT);
    $lng = trim((string)($_POST['longitude'] ?? '')) === '' ? null : filter_var($_POST['longitude'], FILTER_VALIDATE_FLOAT);
    $landmark = trim((string) ($_POST['landmark'] ?? ''));
    $emergency = $slot === 'Emergency - ASAP';
    $validVehicle = sf_query('SELECT id FROM customer_vehicles WHERE customer_id=? AND vehicle_number=?', [$user['id'],$vehicleNumber])->fetchColumn();
    if (!hash_equals($_SESSION['order_request_key'], $requestKey) || !$validVehicle || !is_finite($liters) || $fuelId < 1 || $liters < 1 || $liters > 100 || mb_strlen($address) < 10 || mb_strlen($address) > 500 || mb_strlen($landmark) > 200 || strlen($pincode) !== 6 || mb_strlen($slot) > 80 || $slot === '' || $lat === false || $lng === false || (($lat === null) !== ($lng === null)) || ($lat !== null && (!is_finite($lat) || abs($lat) > 90)) || ($lng !== null && (!is_finite($lng) || abs($lng) > 180)) || !in_array($payment, ['Card','UPI/Cash','Wallet'], true)) {
        $error = 'Check your address, pincode and vehicle saved in Profile. Quantity must be 1–100 litres.';
    } else {
        $connection = null;
        try {
            $connection = db();
            $connection->beginTransaction();
            if ($emergency && !sf_query("SELECT p.user_id FROM delivery_agent_profiles p JOIN users u ON u.id=p.user_id JOIN sf_agent_approvals a ON a.user_id=u.id WHERE p.is_online=1 AND u.status='active' AND a.approval_status='Approved' LIMIT 1")->fetchColumn()) {
                throw new RuntimeException('No approved partner is online for emergency delivery. Choose a scheduled window.');
            }
            $fuelStatement = $connection->prepare('SELECT * FROM fuel_inventory WHERE id=? FOR UPDATE');
            $fuelStatement->execute([$fuelId]);
            $fuel = $fuelStatement->fetch();

            if (!$fuel || (float) $fuel['stock_liters'] < $liters) {
                throw new RuntimeException('The selected fuel does not have enough available stock.');
            }

            $price = (float) $fuel['price_per_liter'];
            $subtotal = $price * $liters;
            $deliveryFee = $liters >= 20 ? 79.00 : 129.00;
            $tax = round($subtotal * 0.05, 2);
            $total = round($subtotal + $deliveryFee + $tax, 2);
            $paid = 'Pending';
            $code = tracking_code();
            $insert = $connection->prepare('INSERT INTO orders (tracking_code,customer_id,fuel_type,quantity_liters,price_per_liter,delivery_fee,tax_amount,total_amount,delivery_address,delivery_pincode,delivery_time_slot,vehicle_number,order_status,payment_method,payment_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,\'Pending\',?,?)');
            $insert->execute([$code,$user['id'],$fuel['fuel_type'],$liters,$price,$deliveryFee,$tax,$total,$address,$pincode,$slot,$vehicleNumber,$payment,$paid]);
            $orderId=(int)$connection->lastInsertId();
            sf_query('INSERT INTO sf_order_details(order_id,latitude,longitude,landmark,emergency,confirmation_code,request_key) VALUES(?,?,?,?,?,?,?)', [$orderId,$lat,$lng,$landmark,(int)$emergency,(string)random_int(100000,999999),$requestKey]);
            sf_query('INSERT INTO sf_stock_movements(fuel_id,order_id,liters,reason,user_id) VALUES(?,?,?,?,?)', [$fuelId,$orderId,-$liters,'Order reservation',$user['id']]);
            sf_notify_admins(($emergency ? 'Emergency: ' : 'New order: ') . $code, 'admin/manage_orders.php');
            foreach (sf_query("SELECT p.user_id FROM delivery_agent_profiles p JOIN users u ON u.id=p.user_id JOIN sf_agent_approvals a ON a.user_id=u.id WHERE p.is_online=1 AND u.status='active' AND a.approval_status='Approved'")->fetchAll() as $agent) {
                sf_notify((int)$agent['user_id'], 'New request available: ' . $code, 'delivery/active_deliveries.php');
            }
            if($realtimeReady){$history=$connection->prepare("INSERT INTO order_status_history(order_id,order_status,changed_by) VALUES(?,'Pending',?)");$history->execute([$orderId,$user['id']]);}
            $stock = $connection->prepare('UPDATE fuel_inventory SET stock_liters=stock_liters-? WHERE id=?');
            $stock->execute([$liters,$fuelId]);
            $connection->commit();
            unset($_SESSION['order_request_key']);
            flash('success', 'Order ' . $code . ' placed successfully. Dispatch is being assigned.');
            redirect('customer/track_order.php?code=' . urlencode($code));
        } catch (Throwable $exception) {
            safe_rollback($connection);
            $error = sf_error($exception);
        }
    }
}

dashboard_start('Instant Refuel', 'order', $user);
?>
<section class="welcome-panel"><div><span class="eyebrow">Advanced booking</span><h2>Configure your delivery.</h2><p>Every price component updates before you confirm the order.</p></div><span class="badge success">Inventory connected</span></section>
<?php if($error): ?><div class="alert error"><i class="fa-solid fa-circle-exclamation"></i><?= e($error) ?></div><?php endif; ?>
<form method="post" data-order-calculator>
    <?= csrf_field() ?>
    <input type="hidden" name="request_key" value="<?= e($_SESSION['order_request_key']) ?>">
    <?php if ($repeat): ?><div class="alert success">Order again: review the current fuel price and confirm your destination before booking.</div><?php endif; ?>
    <section class="panel">
        <?php sf_address_fields(array_merge([
            'delivery_address' => $repeat['delivery_address'] ?? $addresses[0]['full_address'] ?? '',
            'delivery_pincode' => $repeat['delivery_pincode'] ?? $addresses[0]['pincode'] ?? '',
        ], $_POST)); ?>
    </section>
    <div class="order-layout">
        <section class="panel"><div class="panel-head"><h2>Fuel & Vehicle</h2><span class="badge info">Live calculation</span></div><div class="form-grid">
            <div class="form-group full"><label for="fuel_id">Fuel type</label><select id="fuel_id" name="fuel_id" data-fuel-select required><?php foreach($inventory as $fuel): ?><option value="<?= (int)$fuel['id'] ?>" data-price="<?= e($fuel['price_per_liter']) ?>" <?= (isset($_POST['fuel_id']) ? (int)$_POST['fuel_id']===(int)$fuel['id'] : ($repeat && $repeat['fuel_type']===$fuel['fuel_type'])) ? 'selected' : '' ?>><?= e($fuel['fuel_type']) ?> · <?= money($fuel['price_per_liter']) ?>/L · <?= number_format((float)$fuel['stock_liters']) ?> L available</option><?php endforeach; ?></select></div>
            <div class="form-group full"><label for="liter_slider">Fuel quantity: move the slider</label><input id="liter_slider" type="range" min="1" max="100" value="<?= e($_POST['quantity_liters'] ?? ($repeat['quantity_liters'] ?? 10)) ?>" data-liter-slider></div>
            <div class="form-group"><label for="quantity_liters">Exact litres</label><input id="quantity_liters" name="quantity_liters" type="number" min="1" max="100" step="0.5" value="<?= e($_POST['quantity_liters'] ?? ($repeat['quantity_liters'] ?? 10)) ?>" data-liter-input required></div>
            <div class="form-group"><label for="vehicle_number">Vehicle</label><select id="vehicle_number" name="vehicle_number" required><?php if(!$vehicles): ?><option value="">Add a vehicle from Profile</option><?php else: ?><?php foreach($vehicles as $vehicle): ?><option value="<?= e($vehicle['vehicle_number']) ?>" <?= ($repeat && $repeat['vehicle_number']===$vehicle['vehicle_number']) ? 'selected' : '' ?>><?= e($vehicle['vehicle_name']) ?> · <?= e($vehicle['vehicle_number']) ?></option><?php endforeach; ?><?php endif; ?></select></div>
        </div></section>
        <aside class="panel summary-card"><div class="panel-head"><h2>Order Summary</h2></div><div class="price-row"><span>Price per litre</span><strong data-unit-price>₹0.00</strong></div><div class="price-row"><span>Fuel subtotal</span><strong data-subtotal>₹0.00</strong></div><div class="price-row"><span>Delivery fee</span><strong data-fee>₹0.00</strong></div><div class="price-row"><span>Tax (5%)</span><strong data-tax>₹0.00</strong></div><div class="price-row total"><span>Total amount</span><strong data-total>₹0.00</strong></div><button class="btn" type="submit" style="width:100%;margin-top:15px"><i class="fa-solid fa-lock"></i> Confirm Secure Order</button></aside>
    </div>
    <section class="panel"><div class="panel-head"><h2>Delivery Information</h2></div><div class="form-grid">
        <div class="form-group"><label for="delivery_time_slot">Delivery time slot</label><select id="delivery_time_slot" name="delivery_time_slot" required><option>09:00 AM - 11:00 AM</option><option <?= isset($_GET['emergency']) ? 'selected' : '' ?>>Emergency - ASAP</option><option>11:00 AM - 01:00 PM</option><option>01:00 PM - 03:00 PM</option><option>03:00 PM - 05:00 PM</option><option>05:00 PM - 07:00 PM</option></select></div>
        <div class="form-group"><label for="payment_method">Payment on delivery</label><select id="payment_method" name="payment_method" required><option>UPI/Cash</option></select></div>
    </div></section>
</form>
<?php dashboard_end(); ?>
