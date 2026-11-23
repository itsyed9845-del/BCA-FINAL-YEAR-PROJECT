<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
$pageTitle = 'On-Demand Fuel Delivery';
$activePage = 'home';
$inventory = db()->query('SELECT * FROM fuel_inventory ORDER BY id')->fetchAll();
require __DIR__ . '/includes/public_header.php';
?>
<main>
    <section class="hero">
        <span class="liquid-orb cyan"></span>
        <span class="liquid-orb violet"></span>
        <div class="container hero-inner hero-focused">
            <div class="hero-copy">
                <span class="status-pill"><i class="pulse-dot"></i> Smart dispatch network online</span>
                <h1>Fuel delivery,<br><span class="gradient-text">built around you.</span></h1>
                <p>Petrol, diesel, premium fuel and CNG delivered through verified partners, transparent pricing and a live dispatch experience.</p>
                <div class="hero-actions">
                    <a class="btn" data-magnetic href="<?= url('auth/register.php') ?>"><i class="fa-solid fa-gas-pump"></i> Order Fuel Now</a>
                    <a class="btn btn-secondary" data-magnetic href="<?= url('auth/register.php?role=delivery_agent') ?>"><i class="fa-solid fa-motorcycle"></i> Become a Delivery Partner</a>
                </div>
                <div class="telemetry">
                    <div><strong data-counter="99.8" data-decimals="1" data-suffix="%">0%</strong><span>Dispatch accuracy</span></div>
                    <div><strong data-counter="12" data-suffix=" min">0 min</strong><span>Average response</span></div>
                    <div><strong data-counter="4.9" data-decimals="1" data-suffix="/5">0/5</strong><span>Partner rating</span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="section dispatch-showcase-section">
        <div class="container dispatch-showcase-grid">
            <div class="showcase-copy">
                <span class="eyebrow">Inside every dispatch</span>
                <h2>Precision you can see. Confidence you can feel.</h2>
                <p>SmartFuel connects inventory, verified delivery partners and customer tracking in one visible journey—from secure loading to measured refueling.</p>
                <div class="showcase-points">
                    <span><i class="fa-solid fa-circle-check"></i> Live status and ETA visibility</span>
                    <span><i class="fa-solid fa-circle-check"></i> Verified agent and vehicle identity</span>
                    <span><i class="fa-solid fa-circle-check"></i> Transparent fuel and payment records</span>
                </div>
                <a class="btn btn-secondary" href="<?= url('services.php') ?>">Explore Our Services <i class="fa-solid fa-arrow-right"></i></a>
            </div>
            <div class="dispatch-card dispatch-card-lowered" data-tilt>
                <div class="dispatch-head">
                    <div><small>Dispatch unit #FD-802</small><strong><span class="pulse-dot"></span> En route to customer</strong></div>
                    <span class="signal-bars"><i></i><i></i><i></i><i></i></span>
                </div>
                <div class="route-map"><i class="route-node start"></i><span class="route-line"></span><i class="route-node end"></i></div>
                <div class="dispatch-grid">
                    <div><span>Fuel Type</span><strong>Premium Octane</strong><small>Verified batch</small></div>
                    <div><span>Payload</span><strong>12.0 litres</strong><small>Secure module</small></div>
                    <div><span>Stability</span><strong>26.4°C</strong><small style="color:var(--emerald)">Nominal</small></div>
                    <div><span>Live ETA</span><strong>12 minutes</strong><small>Traffic optimized</small></div>
                </div>
            </div>
        </div>
    </section>

    <section class="section" id="services">
        <div class="container">
            <div class="section-heading"><div><span class="eyebrow">Live fuel rate ticker</span><h2>Transparent pricing, directly from inventory.</h2></div><span class="badge success">Updated live</span></div>
            <div class="rates-grid">
                <?php foreach ($inventory as $index => $fuel): ?>
                    <article class="rate-card"><i class="fa-solid <?= ['fa-gas-pump', 'fa-truck-droplet', 'fa-fire-flame-curved', 'fa-gauge-high'][$index] ?? 'fa-droplet' ?>"></i><span><?= e($fuel['fuel_type']) ?></span><strong><?= money($fuel['price_per_liter']) ?></strong><small>per litre · <?= number_format((float) $fuel['stock_liters']) ?> L available</small></article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section section-alt" id="process">
        <div class="container">
            <div class="section-heading"><div><span class="eyebrow">Four intelligent steps</span><h2>From your location to contactless refueling.</h2></div></div>
            <div class="process-grid">
                <article class="process-card"><span class="process-number">01</span><i class="fa-solid fa-location-crosshairs"></i><b>Pin location</b><p>Enter your precise address and pincode for accurate local dispatch.</p></article>
                <article class="process-card"><span class="process-number">02</span><i class="fa-solid fa-sliders"></i><b>Choose fuel volume</b><p>Select fuel and litres with a complete real-time cost breakdown.</p></article>
                <article class="process-card"><span class="process-number">03</span><i class="fa-solid fa-truck-fast"></i><b>Tanker dispatched</b><p>A verified agent accepts the request and follows the optimized route.</p></article>
                <article class="process-card"><span class="process-number">04</span><i class="fa-solid fa-shield-halved"></i><b>Contactless refueling</b><p>Receive sealed, measured fuel with digital completion confirmation.</p></article>
            </div>
        </div>
    </section>

    <section class="section" id="safety">
        <div class="container">
            <div class="section-heading"><div><span class="eyebrow">Safety engineered</span><h2>Confidence at every delivery stage.</h2></div></div>
            <div class="safety-grid">
                <article class="safety-card"><i class="fa-solid fa-certificate"></i><h3 style="color:var(--text)">Verified transport</h3><p>Every delivery profile records licensing, vehicle identity and live availability.</p></article>
                <article class="safety-card"><i class="fa-solid fa-temperature-half"></i><h3 style="color:var(--text)">Controlled handling</h3><p>Sealed modules, safe quantity controls and recorded delivery milestones.</p></article>
                <article class="safety-card"><i class="fa-solid fa-route"></i><h3 style="color:var(--text)">Visible journey</h3><p>Customers can monitor the full Pending-to-Completed dispatch pipeline.</p></article>
            </div>
        </div>
    </section>

    <section class="section section-alt">
        <div class="container">
            <div class="section-heading"><div><span class="eyebrow">Customer stories</span><h2>Designed for moments when every minute matters.</h2></div></div>
            <div class="testimonial-grid">
                <article class="testimonial"><div class="stars">★★★★★</div><p>“I could see the exact price, assigned agent and every delivery stage without making a call.”</p><strong>Neha Sharma · Bengaluru</strong></article>
                <article class="testimonial"><div class="stars">★★★★★</div><p>“The delivery partner reached quickly and the digital fuel quantity was completely transparent.”</p><strong>Aditya Rao · Bengaluru</strong></article>
                <article class="testimonial"><div class="stars">★★★★★</div><p>“SmartFuel’s workflow feels like a modern logistics platform, not an ordinary booking form.”</p><strong>Priya Menon · Bengaluru</strong></article>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container cta-band"><div><span class="eyebrow">Ready to move?</span><h2>Your next refuel is one request away.</h2></div><a class="btn" data-magnetic href="<?= url('auth/register.php') ?>">Create Account <i class="fa-solid fa-arrow-right"></i></a></div>
    </section>
</main>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
