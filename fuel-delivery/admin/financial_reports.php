<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/dashboard_layout.php';
$user = require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $reference = trim((string)($_POST['reference'] ?? ''));
        if (mb_strlen($reference)<3 || mb_strlen($reference)>150) {
            throw new RuntimeException('Enter the actual payment/refund reference, 3–150 characters.');
        }
        db()->beginTransaction();
        if (($_POST['action'] ?? '') === 'payout') {
            $earning = sf_query('SELECT * FROM delivery_earnings WHERE id=? FOR UPDATE', [(int)($_POST['earning_id'] ?? 0)])->fetch();
            if (!$earning || $earning['payout_status'] !== 'Pending') {
                throw new RuntimeException('This payout was already processed or is unavailable.');
            }
            sf_query("UPDATE delivery_earnings SET payout_status='Processed' WHERE id=?", [$earning['id']]);
            sf_query('INSERT INTO sf_payout_receipts(earning_id,reference,admin_id) VALUES(?,?,?)', [$earning['id'],$reference,$user['id']]);
            sf_notify((int)$earning['agent_id'], 'Payout recorded: ' . money($earning['total_payout']), 'delivery/earnings.php');
        } elseif (($_POST['action'] ?? '') === 'refund') {
            $order = sf_order((int)($_POST['order_id'] ?? 0), $user, true);
            $details = sf_query('SELECT * FROM sf_order_details WHERE order_id=? FOR UPDATE', [$order['id']])->fetch();
            if ($order['order_status'] !== 'Cancelled' || $details['refund_status'] !== 'Pending' || $order['payment_status'] !== 'Paid') {
                throw new RuntimeException('This order does not have a pending refund.');
            }
            sf_query("UPDATE sf_order_details SET refund_status='Recorded',refund_reference=? WHERE order_id=?", [$reference,$order['id']]);
            sf_query("UPDATE orders SET payment_status='Refunded' WHERE id=?", [$order['id']]);
            sf_notify((int)$order['customer_id'], 'Refund recorded for ' . $order['tracking_code'], 'customer/track_order.php?code='.urlencode($order['tracking_code']));
        } else {
            throw new RuntimeException('Unknown action.');
        }
        db()->commit();
        flash('success', 'The external payment record was saved.');
    } catch (Throwable $error) {
        safe_rollback(db());
        flash('error', sf_error($error));
    }
    redirect('admin/financial_reports.php');
}
$from = (string)($_GET['from'] ?? date('Y-m-01'));
$to = (string)($_GET['to'] ?? date('Y-m-d'));
$validDate = function (string $date): bool {
    $parsed = DateTime::createFromFormat('!Y-m-d',$date);
    return $parsed && $parsed->format('Y-m-d') === $date;
};
if (!$validDate($from) || !$validDate($to) || $from>$to) {
    $from = date('Y-m-01');
    $to = date('Y-m-d');
}
$until = (new DateTime($to))->modify('+1 day')->format('Y-m-d');
$orders = sf_query('SELECT * FROM orders WHERE created_at>=? AND created_at<? ORDER BY id DESC', [$from,$until])->fetchAll();
$payouts = sf_query('SELECT e.*,u.full_name,o.tracking_code,p.reference FROM delivery_earnings e JOIN users u ON u.id=e.agent_id JOIN orders o ON o.id=e.order_id LEFT JOIN sf_payout_receipts p ON p.earning_id=e.id WHERE e.created_at>=? AND e.created_at<? ORDER BY e.id DESC', [$from,$until])->fetchAll();
$refunds = sf_query("SELECT o.*,d.refund_status,d.refund_reference FROM orders o JOIN sf_order_details d ON d.order_id=o.id WHERE d.refund_status!='Not required' ORDER BY o.id DESC")->fetchAll();
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="smartfuel-orders-'.$from.'-'.$to.'.csv"');
    $output = fopen('php://output','w');
    fwrite($output,"\xEF\xBB\xBF");
    fputcsv($output,['Tracking','Created','Fuel','Litres','Total INR','Delivery fee INR','Tax INR','Order status','Payment status']);
    foreach ($orders as $order) {
        $row = [$order['tracking_code'],$order['created_at'],$order['fuel_type'],$order['quantity_liters'],$order['total_amount'],$order['delivery_fee'],$order['tax_amount'],$order['order_status'],$order['payment_status']];
        $row = array_map(function ($value) {
            return preg_match('/^[=+@\-\t\r]/',(string)$value) ? "'".$value : $value;
        },$row);
        fputcsv($output,$row);
    }
    fclose($output);
    exit;
}
$revenue=0.0;
$liters=0.0;
$fees=0.0;
$paid=0.0;
$owed=0.0;
foreach ($orders as $order) {
    if ($order['order_status']==='Completed') {
        $liters+=(float)$order['quantity_liters'];
        if ($order['payment_status']==='Paid') {
            $revenue+=(float)$order['total_amount'];
            $fees+=(float)$order['delivery_fee'];
        }
    }
}
foreach ($payouts as $payout) {
    if ($payout['payout_status']==='Processed') {
        $paid+=(float)$payout['total_payout'];
    } else {
        $owed+=(float)$payout['total_payout'];
    }
}
dashboard_start('Financial Reports', 'reports', $user);
?>
<section class="welcome-panel"><div><span class="eyebrow">Financial clarity</span><h2>Delivered. Earned. Paid.</h2><p>Completed trips and unpaid payouts are separate. Record a payout only after paying the partner externally.</p></div></section>
<section class="panel">
    <form method="get" class="toolbar">
        <label>From <input type="date" name="from" value="<?= e($from) ?>" required></label>
        <label>To <input type="date" name="to" value="<?= e($to) ?>" required></label>
        <button class="btn btn-small">Apply dates</button>
        <button class="btn btn-secondary btn-small" name="export" value="csv">Download CSV</button>
    </form>
    <p class="sf-note">Order totals are grouped by order date; earnings by the date earned. Refund queue below covers all dates.</p>
</section>
<section class="sf-metrics panel">
    <div><small>Completed paid order value</small><strong><?= money($revenue) ?></strong></div>
    <div><small>Completed litres</small><strong><?= number_format($liters,1) ?> L</strong></div>
    <div><small>Paid partner earnings</small><strong><?= money($paid) ?></strong></div>
    <div><small>Unpaid partner earnings</small><strong><?= money($owed) ?></strong></div>
</section>
<section class="panel">
    <h2>Delivery partner payouts</h2>
    <p>Delivery fees: <?= money($fees) ?>. Operating profit is not calculated because fuel purchase and operating costs are not recorded.</p>
    <div class="table-wrap"><table><thead><tr><th>Partner / trip</th><th>Earned</th><th>Amount</th><th>Payout status</th><th>Payment record</th></tr></thead><tbody>
        <?php foreach ($payouts as $payout): ?>
        <tr>
            <td><?= e($payout['full_name']) ?><br><?= e($payout['tracking_code']) ?></td>
            <td><?= e($payout['created_at']) ?></td>
            <td class="money-tag"><?= money($payout['total_payout']) ?></td>
            <td><span class="badge <?= $payout['payout_status']==='Processed' ? 'success' : 'warning' ?>"><?= $payout['payout_status']==='Processed' ? 'Paid' : 'Unpaid payout' ?></span></td>
            <td>
                <?php if ($payout['payout_status']==='Pending'): ?>
                <form method="post" class="sf-assign">
                    <?= csrf_field() ?><input type="hidden" name="action" value="payout"><input type="hidden" name="earning_id" value="<?= (int)$payout['id'] ?>">
                    <input name="reference" minlength="3" maxlength="150" placeholder="Bank/UPI/cash receipt reference" required>
                    <button class="btn btn-small">Record paid payout</button>
                </form>
                <?php else: ?><?= e($payout['reference'] ?: 'Previously processed') ?><?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody></table></div>
</section>
<section class="panel"><h2>Refund queue · all dates</h2><p>These controls record external refunds; they do not transfer money.</p>
    <?php if (!$refunds): ?><p>No refunds required.</p><?php endif; ?>
    <?php foreach ($refunds as $refund): ?>
    <article class="sf-notice">
        <div><strong><?= e($refund['tracking_code']) ?></strong><p><?= money($refund['total_amount']) ?> · <?= e($refund['refund_status']) ?></p><small><?= e($refund['refund_reference'] ?? '') ?></small></div>
        <?php if ($refund['refund_status']==='Pending'): ?>
        <form method="post" class="sf-assign">
            <?= csrf_field() ?><input type="hidden" name="action" value="refund"><input type="hidden" name="order_id" value="<?= (int)$refund['id'] ?>">
            <input name="reference" minlength="3" maxlength="150" placeholder="Refund reference" required>
            <button class="btn btn-secondary btn-small">Record completed refund</button>
        </form>
        <?php endif; ?>
    </article>
    <?php endforeach; ?>
</section>
<?php dashboard_end(); ?>
