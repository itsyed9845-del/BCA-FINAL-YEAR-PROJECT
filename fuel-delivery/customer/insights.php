<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/dashboard_layout.php';
$user = require_role('customer');
$monthly = sf_query("SELECT DATE_FORMAT(created_at,'%Y-%m') month,SUM(quantity_liters) liters,SUM(total_amount) amount,COUNT(*) trips FROM orders WHERE customer_id=? AND order_status='Completed' GROUP BY month ORDER BY month DESC LIMIT 12", [$user['id']])->fetchAll();
$vehicles = sf_query("SELECT vehicle_number,COUNT(*) trips,SUM(quantity_liters) liters,SUM(total_amount) amount FROM orders WHERE customer_id=? AND order_status='Completed' GROUP BY vehicle_number ORDER BY amount DESC", [$user['id']])->fetchAll();
$max = max(array_merge([1],array_map('floatval',array_column($monthly,'amount'))));
dashboard_start('Fuel Insights', 'insights', $user);
?>
<section class="welcome-panel"><div><span class="eyebrow">Your fuel, in focus</span><h2>Understand every litre.</h2><p>Completed deliveries only. Spending includes recorded fuel, delivery fees and tax.</p></div></section>
<div class="dashboard-grid equal">
    <section class="panel"><h2>Monthly spending</h2>
        <?php if (!$monthly): ?><p>Your first completed delivery will appear here.</p><?php endif; ?>
        <?php foreach ($monthly as $month): ?><div class="sf-bar-row"><div><strong><?= e($month['month']) ?></strong><span><?= money($month['amount']) ?> · <?= e($month['liters']) ?> L</span></div><meter min="0" max="<?= e($max) ?>" value="<?= e($month['amount']) ?>"><?= money($month['amount']) ?></meter></div><?php endforeach; ?>
    </section>
    <section class="panel"><h2>By vehicle</h2><div class="table-wrap"><table><thead><tr><th>Vehicle</th><th>Deliveries</th><th>Litres</th><th>Spend</th></tr></thead><tbody>
        <?php foreach ($vehicles as $vehicle): ?><tr><td><?= e($vehicle['vehicle_number']) ?></td><td><?= (int)$vehicle['trips'] ?></td><td><?= e($vehicle['liters']) ?></td><td><?= money($vehicle['amount']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></section>
</div>
<?php dashboard_end(); ?>
