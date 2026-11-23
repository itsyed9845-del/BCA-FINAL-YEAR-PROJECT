<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/dashboard_layout.php';
$user = require_role('delivery_agent');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
    $vehiclePlate = strtoupper(trim((string) ($_POST['vehicle_plate'] ?? '')));
    $licenseNumber = strtoupper(trim((string) ($_POST['license_number'] ?? '')));

    if (mb_strlen($fullName) < 3 || strlen($phone) < 10 || strlen($phone) > 15 || $vehiclePlate === '' || $licenseNumber === '') {
        $error = 'Enter a valid name, mobile number, vehicle plate and license number.';
    } else {
        $connection = null;
        try {
            $connection = db();
            $connection->beginTransaction();
            $updateUser = $connection->prepare('UPDATE users SET full_name=?,phone=? WHERE id=?');
            $updateUser->execute([$fullName,$phone,$user['id']]);
            $oldProfile = sf_query('SELECT vehicle_plate,license_number FROM delivery_agent_profiles WHERE user_id=? FOR UPDATE', [$user['id']])->fetch();
            $updateProfile = $connection->prepare('UPDATE delivery_agent_profiles SET vehicle_plate=?,license_number=? WHERE user_id=?');
            $updateProfile->execute([$vehiclePlate,$licenseNumber,$user['id']]);
            if ($oldProfile && ($oldProfile['vehicle_plate'] !== $vehiclePlate || $oldProfile['license_number'] !== $licenseNumber)) {
                sf_query("UPDATE sf_agent_approvals SET approval_status='Pending',reviewed_at=NULL WHERE user_id=?", [$user['id']]);
                sf_query('UPDATE delivery_agent_profiles SET is_online=0 WHERE user_id=?', [$user['id']]);
                sf_query('DELETE FROM agent_live_locations WHERE agent_id=?', [$user['id']]);
                sf_notify_admins('Partner credentials changed: ' . $fullName, 'admin/manage_users.php');
            }
            $connection->commit();
            $_SESSION['full_name'] = $fullName;
            flash('success','Your professional profile has been updated.');
            redirect('delivery/profile.php');
        } catch (Throwable $exception) {
            safe_rollback($connection);
            $error = friendly_database_error($exception,'That phone, vehicle plate or license number is already registered.');
        }
    }
}

$profileStatement=db()->prepare('SELECT * FROM delivery_agent_profiles WHERE user_id=?');$profileStatement->execute([$user['id']]);$profile=$profileStatement->fetch();
$reviewAverage = sf_query('SELECT AVG(rating) FROM reviews_ratings WHERE agent_id=?', [$user['id']])->fetchColumn();
$profile['rating'] = $reviewAverage !== null && $reviewAverage !== false ? number_format((float)$reviewAverage,2) : 'Not rated';
$performanceStatement=db()->prepare("SELECT COUNT(*) total_jobs,COALESCE(SUM(order_status='Completed'),0) completed_jobs,COALESCE(SUM(order_status='Cancelled'),0) cancelled_jobs,COALESCE(SUM(CASE WHEN order_status='Completed' THEN quantity_liters ELSE 0 END),0) delivered_liters FROM orders WHERE delivery_agent_id=?");$performanceStatement->execute([$user['id']]);$performance=$performanceStatement->fetch();
$completionRate=(int)$performance['total_jobs']>0?round(((int)$performance['completed_jobs']/(int)$performance['total_jobs'])*100):0;
$level=1+(int)floor((int)$performance['completed_jobs']/5);

dashboard_start('Delivery Partner Profile','profile',$user);
?>
<section class="profile-hero-card">
    <div class="profile-hero-identity"><div class="profile-orbit"><span><?= e(strtoupper(substr($user['full_name'],0,1))) ?></span><i></i><b></b></div><div><span class="badge success"><i class="fa-solid fa-shield-check"></i> <?= sf_approved((int)$user['id']) ? 'Admin approved' : 'Approval pending' ?></span><h2><?= e($user['full_name']) ?></h2><p>SmartFuel Road Captain · Level <?= $level ?> · Member since <?= e(date('M Y',strtotime($user['created_at']))) ?></p></div></div>
    <div class="profile-score"><span>Completed deliveries</span><strong><?= (int)$performance['completed_jobs'] ?></strong><small>Recorded in your delivery history</small></div>
</section>
<?php if($error): ?><div class="alert error"><i class="fa-solid fa-circle-exclamation"></i><?= e($error) ?></div><?php endif; ?>
<div class="dashboard-grid equal">
    <section class="panel"><div class="panel-head"><h2>Professional Identity</h2><span class="badge info">Secure profile</span></div><form method="post" class="form-grid"><?= csrf_field() ?><div class="form-group full"><label for="full_name">Full name</label><input id="full_name" name="full_name" value="<?= e($_POST['full_name']??$user['full_name']) ?>" required></div><div class="form-group"><label for="email">Login email</label><input id="email" value="<?= e($user['email']) ?>" disabled></div><div class="form-group"><label for="phone">Mobile number</label><input id="phone" name="phone" value="<?= e($_POST['phone']??$user['phone']) ?>" inputmode="numeric" required></div><div class="form-group"><label for="vehicle_plate">Vehicle plate</label><input id="vehicle_plate" name="vehicle_plate" value="<?= e($_POST['vehicle_plate']??($profile['vehicle_plate']??'')) ?>" required></div><div class="form-group"><label for="license_number">License number</label><input id="license_number" name="license_number" value="<?= e($_POST['license_number']??($profile['license_number']??'')) ?>" required></div><div class="form-group full"><button class="btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save Profile</button></div></form></section>
    <aside class="panel"><div class="panel-head"><h2>Performance Passport</h2><span class="badge success">Level <?= $level ?></span></div><div class="passport-grid"><div><i class="fa-solid fa-route"></i><strong><?= (int)$performance['total_jobs'] ?></strong><span>Total assignments</span></div><div><i class="fa-solid fa-circle-check"></i><strong><?= $completionRate ?>%</strong><span>Completion rate</span></div><div><i class="fa-solid fa-droplet"></i><strong><?= number_format((float)$performance['delivered_liters'],1) ?> L</strong><span>Fuel delivered</span></div><div><i class="fa-solid fa-star"></i><strong><?= e($profile['rating']??'5.00') ?></strong><span>Customer rating</span></div></div></aside>
</div>
<section class="panel"><div class="panel-head"><h2>Achievement Garage</h2><span class="eyebrow">Earned through service</span></div><div class="achievement-grid"><article class="achievement unlocked"><i class="fa-solid fa-shield-halved"></i><div><strong>Partner account</strong><span>Profile registered</span></div></article><article class="achievement <?= (int)$performance['completed_jobs']>=1?'unlocked':'locked' ?>"><i class="fa-solid fa-flag-checkered"></i><div><strong>First Mission</strong><span>Complete your first delivery</span></div></article><article class="achievement <?= (float)$performance['delivered_liters']>=100?'unlocked':'locked' ?>"><i class="fa-solid fa-gauge-high"></i><div><strong>Century Carrier</strong><span>Complete 100 litres of deliveries</span></div></article><article class="achievement <?= $completionRate>=95&&(int)$performance['total_jobs']>=5?'unlocked':'locked' ?>"><i class="fa-solid fa-crown"></i><div><strong>Precision Captain</strong><span>Maintain 95% across 5 jobs</span></div></article></div></section>
<section class="panel"><h2>Customer feedback</h2>
<?php $reviews = sf_query('SELECT r.rating,r.review_text,r.created_at FROM reviews_ratings r WHERE agent_id=? ORDER BY id DESC LIMIT 20', [$user['id']])->fetchAll(); ?>
<?php if (!$reviews): ?><p>No ratings yet. Your first reviewed delivery will appear here.</p><?php endif; ?>
<?php foreach ($reviews as $review): ?><article class="sf-notice"><div><strong><?= (int)$review['rating'] ?>/5</strong><p><?= e($review['review_text']) ?></p><small><?= e($review['created_at']) ?></small></div></article><?php endforeach; ?>
</section>
<?php dashboard_end(); ?>
