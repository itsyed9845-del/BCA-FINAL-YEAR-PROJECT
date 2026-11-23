<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
$pageTitle = 'About Us';
$activePage = 'about';
require __DIR__ . '/includes/public_header.php';
?>
<main>
    <section class="page-hero">
        <span class="liquid-orb cyan"></span>
        <span class="liquid-orb violet"></span>
        <div class="container page-hero-inner">
            <span class="status-pill"><i class="pulse-dot"></i> Built for safer mobility</span>
            <h1>Fuel emergencies deserve a <span class="gradient-text">smarter response.</span></h1>
            <p>SmartFuel One connects customers with verified delivery partners through transparent pricing, protected workflows and live order visibility.</p>
        </div>
    </section>

    <section class="section">
        <div class="container story-grid">
            <div>
                <span class="eyebrow">Our purpose</span>
                <h2>Keeping people and businesses moving.</h2>
                <p>A dry tank can interrupt a journey, delay a delivery or stop an entire workday. We designed SmartFuel One to make emergency and scheduled refueling simple, traceable and dependable.</p>
                <p>From the first request to final confirmation, every stage is designed around clarity: the selected fuel, quantity, price, delivery partner and order status remain visible.</p>
            </div>
            <div class="mission-card" data-tilt>
                <i class="fa-solid fa-route"></i>
                <span>Our mission</span>
                <strong>Deliver the right fuel, to the right place, with complete confidence.</strong>
                <div class="mission-stats">
                    <div><b>24/7</b><small>Dispatch ready</small></div>
                    <div><b>100%</b><small>Trackable flow</small></div>
                    <div><b>3</b><small>Connected roles</small></div>
                </div>
            </div>
        </div>
    </section>

    <section class="section section-alt">
        <div class="container">
            <div class="section-heading"><div><span class="eyebrow">What guides us</span><h2>Technology with practical purpose.</h2></div></div>
            <div class="safety-grid">
                <article class="safety-card"><i class="fa-solid fa-shield-halved"></i><h3>Safety first</h3><p>Verified partners, controlled order stages and accountable delivery records support every refuel.</p></article>
                <article class="safety-card"><i class="fa-solid fa-eye"></i><h3>Full transparency</h3><p>Customers see pricing, quantity, tracking progress and payment information without hidden surprises.</p></article>
                <article class="safety-card"><i class="fa-solid fa-bolt"></i><h3>Fast coordination</h3><p>One connected system keeps customers, delivery agents and administrators working from the same live order.</p></article>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container cta-band"><div><span class="eyebrow">Move with confidence</span><h2>Smart refueling begins here.</h2></div><a class="btn" data-magnetic href="<?= url('auth/register.php') ?>">Create Account <i class="fa-solid fa-arrow-right"></i></a></div>
    </section>
</main>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
