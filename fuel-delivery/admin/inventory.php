<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/dashboard_layout.php';
$user = require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $id = (int)($_POST['fuel_id'] ?? 0);
        db()->beginTransaction();
        $fuel = sf_query('SELECT * FROM fuel_inventory WHERE id=? FOR UPDATE', [$id])->fetch();
        if (!$fuel) {
            throw new RuntimeException('Fuel unavailable.');
        }
        if (($_POST['action'] ?? '') === 'refill') {
            $liters = filter_var($_POST['liters'] ?? '', FILTER_VALIDATE_FLOAT);
            $reason = trim((string)($_POST['reason'] ?? ''));
            $reserved = (float)sf_query("SELECT COALESCE(SUM(quantity_liters),0) FROM orders WHERE fuel_type=? AND order_status NOT IN ('Completed','Cancelled')", [$fuel['fuel_type']])->fetchColumn();
            if ($liters === false || $liters <= 0 || $liters + (float)$fuel['stock_liters'] + $reserved > (float)$fuel['tank_capacity'] || mb_strlen($reason)<3 || mb_strlen($reason)>200) {
                throw new RuntimeException('Refill must fit tank capacity, including reserved fuel. Enter a receipt/reference of 3–200 characters.');
            }
            sf_query('UPDATE fuel_inventory SET stock_liters=stock_liters+? WHERE id=?', [$liters,$id]);
            sf_query('INSERT INTO sf_stock_movements(fuel_id,liters,reason,user_id) VALUES(?,?,?,?)', [$id,$liters,'Refill: '.$reason,$user['id']]);
        } else {
            $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);
            $threshold = filter_var($_POST['threshold'] ?? '', FILTER_VALIDATE_FLOAT);
            if ($price === false || $price <= 0 || $price > 99999 || $threshold === false || $threshold<0 || $threshold>(float)$fuel['tank_capacity']) {
                throw new RuntimeException('Enter a valid price and a low-stock threshold within tank capacity.');
            }
            sf_query('UPDATE fuel_inventory SET price_per_liter=? WHERE id=?', [$price,$id]);
            sf_query('UPDATE sf_inventory_settings SET low_stock_liters=? WHERE fuel_id=?', [$threshold,$id]);
        }
        db()->commit();
        flash('success', 'Inventory updated.');
    } catch (Throwable $error) {
        safe_rollback(db());
        flash('error', sf_error($error));
    }
    redirect('admin/inventory.php');
}
$fuels = sf_query('SELECT f.*,s.low_stock_liters FROM fuel_inventory f JOIN sf_inventory_settings s ON s.fuel_id=f.id ORDER BY f.id')->fetchAll();
$ledger = sf_query('SELECT m.*,f.fuel_type,u.full_name FROM sf_stock_movements m JOIN fuel_inventory f ON f.id=m.fuel_id JOIN users u ON u.id=m.user_id ORDER BY m.id DESC LIMIT 100')->fetchAll();
dashboard_start('Fuel Inventory', 'inventory', $user);
?>
<section class="welcome-panel"><div><span class="eyebrow">Inventory control</span><h2>Keep the city moving.</h2><p>Available stock excludes order reservations. Refills and cancellations leave a stock movement record.</p></div></section>
<div class="dashboard-grid equal">
    <?php foreach ($fuels as $fuel): ?>
    <section class="panel">
        <div class="panel-head"><h2><?= e($fuel['fuel_type']) ?></h2><span class="badge <?= (float)$fuel['stock_liters'] <= (float)$fuel['low_stock_liters'] ? 'danger' : 'success' ?>"><?= number_format((float)$fuel['stock_liters'],1) ?> L available</span></div>
        <?php if ((float)$fuel['stock_liters'] <= (float)$fuel['low_stock_liters']): ?><div class="alert error">Low stock. Arrange a refill.</div><?php endif; ?>
        <p>Tank capacity: <?= e($fuel['tank_capacity']) ?> L</p>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="fuel_id" value="<?= (int)$fuel['id'] ?>">
            <div class="form-grid">
                <div class="form-group"><label>Price per litre<input name="price" type="number" min="0.01" step="0.01" value="<?= e($fuel['price_per_liter']) ?>" required></label></div>
                <div class="form-group"><label>Low-stock threshold (L)<input name="threshold" type="number" min="0" step="0.01" value="<?= e($fuel['low_stock_liters']) ?>" required></label></div>
            </div>
            <button class="btn btn-secondary btn-small">Save price & threshold</button>
        </form>
        <details class="sf-action-form"><summary>Record stock refill</summary><form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="refill"><input type="hidden" name="fuel_id" value="<?= (int)$fuel['id'] ?>">
            <div class="form-group"><label>Refilled litres<input name="liters" type="number" min="0.01" step="0.01" required></label></div>
            <div class="form-group"><label>Receipt/reference<input name="reason" minlength="3" maxlength="190" required></label></div>
            <button class="btn btn-small">Record refill</button>
        </form></details>
    </section>
    <?php endforeach; ?>
</div>
<section class="panel"><h2>Stock movement ledger</h2><div class="table-wrap"><table>
    <thead><tr><th>Time</th><th>Fuel</th><th>Change (L)</th><th>Reason</th><th>Recorded by</th></tr></thead><tbody>
    <?php foreach ($ledger as $item): ?><tr><td><?= e($item['created_at']) ?></td><td><?= e($item['fuel_type']) ?></td><td><?= e($item['liters']) ?></td><td><?= e($item['reason']) ?></td><td><?= e($item['full_name']) ?></td></tr><?php endforeach; ?>
    </tbody>
</table></div></section>
<?php dashboard_end(); ?>
