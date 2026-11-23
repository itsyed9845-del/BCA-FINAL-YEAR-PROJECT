<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
$pageTitle = 'Our Services';
$activePage = 'services';
$inventory = db()->query('SELECT * FROM fuel_inventory ORDER BY id')->fetchAll();
require __DIR__ . '/includes/public_header.php';
?>
<main>
    <section class="page-hero services-hero">
        <span class="liquid-orb cyan"></span>
        <span class="liquid-orb violet"></span>
        <div class="container page-hero-inner">
            <span class="status-pill"><i class="pulse-dot"></i> On-demand dispatch</span>
            <h1>Reliable fuel delivery for <span class="gradient-text">every journey.</span></h1>
            <p>Request fuel for a roadside emergency, home vehicle or local fleet and follow every stage from acceptance to completion.</p>
            <div class="hero-actions"><a class="btn" data-magnetic href="<?= url('auth/register.php') ?>"><i class="fa-solid fa-gas-pump"></i> Order Fuel</a><a class="btn btn-secondary" data-magnetic href="<?= url('contact.php') ?>">Talk to Us</a></div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-heading"><div><span class="eyebrow">Available fuels</span><h2>Live prices from current inventory.</h2></div><span class="badge success">Updated live</span></div>
            <div class="rates-grid">
                <?php foreach ($inventory as $index => $fuel): ?>
                    <article class="rate-card"><i class="fa-solid <?= ['fa-gas-pump', 'fa-truck-droplet', 'fa-fire-flame-curved', 'fa-gauge-high'][$index] ?? 'fa-droplet' ?>"></i><span><?= e($fuel['fuel_type']) ?></span><strong><?= money($fuel['price_per_liter']) ?></strong><small>per litre · <?= number_format((float) $fuel['stock_liters']) ?> L available</small></article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section section-alt">
        <div class="container">
            <div class="section-heading"><div><span class="eyebrow">How it works</span><h2>Four clear steps. One visible journey.</h2></div></div>
            <div class="process-grid">
                <article class="process-card"><span class="process-number">01</span><i class="fa-solid fa-location-crosshairs"></i><b>Pin your location</b><p>Provide the precise delivery address and pincode.</p></article>
                <article class="process-card"><span class="process-number">02</span><i class="fa-solid fa-sliders"></i><b>Choose fuel and litres</b><p>View the complete cost before confirming your request.</p></article>
                <article class="process-card"><span class="process-number">03</span><i class="fa-solid fa-truck-fast"></i><b>Track dispatch</b><p>Follow the delivery partner through each live order stage.</p></article>
                <article class="process-card"><span class="process-number">04</span><i class="fa-solid fa-circle-check"></i><b>Complete refueling</b><p>Receive measured fuel and digital completion confirmation.</p></article>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="section-heading"><div><span class="eyebrow">Service coverage</span><h2>Designed for real-world fuel needs.</h2></div></div>
            <div class="safety-grid">
                <article class="safety-card"><i class="fa-solid fa-car-burst"></i><h3>Roadside assistance</h3><p>Urgent delivery when a vehicle runs out of fuel during a journey.</p></article>
                <article class="safety-card"><i class="fa-solid fa-house"></i><h3>Home delivery</h3><p>Scheduled refueling for eligible vehicles at your saved address.</p></article>
                <article class="safety-card"><i class="fa-solid fa-truck-ramp-box"></i><h3>Local fleet support</h3><p>Structured ordering and transparent records for operational vehicles.</p></article>
            </div>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
