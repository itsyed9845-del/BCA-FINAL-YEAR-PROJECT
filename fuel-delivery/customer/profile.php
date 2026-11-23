<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/dashboard_layout.php';
$user = require_role('customer');
$error='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=(string)($_POST['action']??'profile');
    try{
        if($action==='profile'){
            $name=trim((string)($_POST['full_name']??''));$phone=preg_replace('/\D+/','',(string)($_POST['phone']??''));
            if(mb_strlen($name)<3||strlen($phone)<10)throw new RuntimeException('Enter a valid name and mobile number.');
            $statement=db()->prepare('UPDATE users SET full_name=?,phone=? WHERE id=?');$statement->execute([$name,$phone,$user['id']]);$_SESSION['full_name']=$name;
        }elseif($action==='vehicle'){
            $name=trim((string)($_POST['vehicle_name']??''));$number=strtoupper(trim((string)($_POST['vehicle_number']??'')));$fuel=(string)($_POST['fuel_type']??'');
            if($name===''||$number===''||!in_array($fuel,['Petrol/Gasoline','Diesel','Premium Octane','CNG'],true))throw new RuntimeException('Complete all vehicle fields.');
            $statement=db()->prepare('INSERT INTO customer_vehicles(customer_id,vehicle_name,vehicle_number,fuel_type) VALUES(?,?,?,?)');$statement->execute([$user['id'],$name,$number,$fuel]);
        }elseif($action==='address'){
            $label=trim((string)($_POST['label']??''));$address=trim((string)($_POST['full_address']??''));$pincode=preg_replace('/\D+/','',(string)($_POST['pincode']??''));
            if($label===''||mb_strlen($address)<10||strlen($pincode)!==6)throw new RuntimeException('Enter a complete address and six-digit pincode.');
            $statement=db()->prepare('INSERT INTO delivery_addresses(customer_id,label,full_address,pincode) VALUES(?,?,?,?)');$statement->execute([$user['id'],$label,$address,$pincode]);
        }
        flash('success','Profile information updated successfully.');redirect('customer/profile.php');
    }catch(Throwable $exception){$error=sf_error($exception);}
}
$vehicleStatement=db()->prepare('SELECT * FROM customer_vehicles WHERE customer_id=? ORDER BY is_default DESC,id');$vehicleStatement->execute([$user['id']]);$vehicles=$vehicleStatement->fetchAll();
$addressStatement=db()->prepare('SELECT * FROM delivery_addresses WHERE customer_id=? ORDER BY is_default DESC,id');$addressStatement->execute([$user['id']]);$addresses=$addressStatement->fetchAll();
dashboard_start('Profile & Preferences','profile',$user);
?>
<section class="welcome-panel"><div><span class="eyebrow">Personal workspace</span><h2>Your details, vehicles and places.</h2><p>Keep delivery information accurate for faster dispatch.</p></div></section>
<?php if($error): ?><div class="alert error"><i class="fa-solid fa-circle-exclamation"></i><?= e($error) ?></div><?php endif; ?>
<div class="dashboard-grid equal"><form class="panel" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="profile"><div class="panel-head"><h2>Personal Details</h2></div><div class="form-group"><label>Full name</label><input name="full_name" value="<?= e($user['full_name']) ?>" required></div><div class="form-group"><label>Email</label><input value="<?= e($user['email']) ?>" disabled></div><div class="form-group"><label>Phone</label><input name="phone" value="<?= e($user['phone']) ?>" required></div><button class="btn btn-small">Save Details</button></form><form class="panel" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="vehicle"><div class="panel-head"><h2>Add Vehicle</h2></div><div class="form-group"><label>Vehicle name</label><input name="vehicle_name" placeholder="Honda City Sedan" required></div><div class="form-group"><label>Vehicle number</label><input name="vehicle_number" placeholder="KA03AB1234" required></div><div class="form-group"><label>Fuel type</label><select name="fuel_type"><option>Petrol/Gasoline</option><option>Diesel</option><option>Premium Octane</option><option>CNG</option></select></div><button class="btn btn-small">Add Vehicle</button></form></div>
<div class="dashboard-grid equal"><section class="panel"><div class="panel-head"><h2>Saved Vehicles</h2></div><div class="quick-actions"><?php foreach($vehicles as $vehicle): ?><div class="quick-action"><i class="fa-solid fa-car"></i><div><strong><?= e($vehicle['vehicle_name']) ?> · <?= e($vehicle['vehicle_number']) ?></strong><small><?= e($vehicle['fuel_type']) ?><?= $vehicle['is_default']?' · Default':'' ?></small></div></div><?php endforeach; ?></div></section><section class="panel"><div class="panel-head"><h2>Saved Addresses</h2></div><div class="quick-actions"><?php foreach($addresses as $address): ?><div class="quick-action"><i class="fa-solid fa-location-dot"></i><div><strong><?= e($address['label']) ?> · <?= e($address['pincode']) ?></strong><small><?= e($address['full_address']) ?></small></div></div><?php endforeach; ?></div><form method="post" style="margin-top:18px"><?= csrf_field() ?><input type="hidden" name="action" value="address"><div class="form-grid"><div class="form-group"><label>Label</label><input name="label" placeholder="Home" required></div><div class="form-group"><label>Pincode</label><input name="pincode" maxlength="6" required></div><div class="form-group full"><label>Full address</label><textarea name="full_address" required></textarea></div></div><button class="btn btn-small">Add Address</button></form></section></div>
<?php dashboard_end(); ?>
