<?php
declare(strict_types=1);
require_once __DIR__ . '/config/db.php';
$pageTitle = 'Contact Us';
$activePage = 'contact';
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $message = trim((string) ($_POST['message'] ?? ''));

    if (mb_strlen($name) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($message) < 10) {
        $error = 'Please enter your name, a valid email and a message of at least 10 characters.';
    } else {
        $statement = db()->prepare('INSERT INTO contact_messages (full_name, email, message) VALUES (?, ?, ?)');
        $statement->execute([$name, strtolower($email), $message]);
        $success = 'Thank you, ' . $name . '. Your message has been received.';
        $_POST = [];
    }
}

require __DIR__ . '/includes/public_header.php';
?>
<main>
    <section class="page-hero contact-hero">
        <span class="liquid-orb cyan"></span>
        <span class="liquid-orb violet"></span>
        <div class="container page-hero-inner">
            <span class="status-pill"><i class="pulse-dot"></i> Support team online</span>
            <h1>Let’s solve your fuel need <span class="gradient-text">together.</span></h1>
            <p>Questions about an order, delivery partnership or SmartFuel operations? Reach our team through the details below.</p>
        </div>
    </section>

    <section class="section">
        <div class="container contact-grid">
            <div class="contact-details">
                <span class="eyebrow">Contact SmartFuel</span>
                <h2>We’re ready to help.</h2>
                <p>Our operations desk supports customers and delivery partners throughout the dispatch journey.</p>
                <a class="contact-item" href="tel:+919876543210"><i class="fa-solid fa-phone"></i><span><small>Call us</small><strong>+91 98765 43210</strong></span></a>
                <a class="contact-item" href="mailto:ops@smartfuel.test"><i class="fa-solid fa-envelope"></i><span><small>Email us</small><strong>ops@smartfuel.test</strong></span></a>
                <div class="contact-item"><i class="fa-solid fa-location-dot"></i><span><small>Operations center</small><strong>Bengaluru, India</strong></span></div>
            </div>
            <form class="contact-form glass-card" method="post" novalidate>
                <?= csrf_field() ?>
                <span class="eyebrow">Send a message</span>
                <h2>How can we help?</h2>
                <?php if ($error): ?><div class="alert error"><i class="fa-solid fa-circle-exclamation"></i><?= e($error) ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i><?= e($success) ?></div><?php endif; ?>
                <div class="form-group"><label for="name">Full name</label><input id="name" name="name" value="<?= e($_POST['name'] ?? '') ?>" required placeholder="Your name"></div>
                <div class="form-group"><label for="contact_email">Email address</label><input id="contact_email" name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" required placeholder="name@example.com"></div>
                <div class="form-group"><label for="message">Message</label><textarea id="message" name="message" rows="5" required placeholder="Tell us what you need help with"><?= e($_POST['message'] ?? '') ?></textarea></div>
                <button class="btn" type="submit"><i class="fa-solid fa-paper-plane"></i> Send Message</button>
            </form>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/public_footer.php'; ?>
