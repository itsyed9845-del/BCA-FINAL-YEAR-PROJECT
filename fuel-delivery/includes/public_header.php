<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
$pageTitle = $pageTitle ?? APP_NAME;
$activePage = $activePage ?? '';
$flashMessage = pull_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SmartFuel One provides safe on-demand fuel delivery with live dispatch tracking.">
    <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/custom.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/upgrade.css') ?>">
</head>
<body>
<header class="site-header">
    <div class="container nav-row">
        <a class="brand" href="<?= url('index.php') ?>">
            <span class="brand-mark"><i class="fa-solid fa-bolt"></i></span>
            <span>SmartFuel <em>One</em></span>
        </a>
        <button class="mobile-menu" type="button" data-menu-toggle aria-label="Open menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <nav class="main-nav" data-main-nav>
            <a class="<?= $activePage === 'home' ? 'active' : '' ?>" href="<?= url('index.php') ?>">Home</a>
            <a class="<?= $activePage === 'about' ? 'active' : '' ?>" href="<?= url('about.php') ?>">About Us</a>
            <a class="<?= $activePage === 'services' ? 'active' : '' ?>" href="<?= url('services.php') ?>">Services</a>
            <a class="<?= $activePage === 'contact' ? 'active' : '' ?>" href="<?= url('contact.php') ?>">Contact Us</a>
            <a href="<?= url('auth/login.php') ?>">Sign in</a>
            <a class="btn btn-small" data-magnetic href="<?= url('auth/register.php') ?>">Get started</a>
        </nav>
    </div>
</header>
<?php if ($flashMessage): ?>
    <div class="toast <?= e($flashMessage['type']) ?>" data-toast>
        <i class="fa-solid <?= $flashMessage['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
        <span><?= e($flashMessage['message']) ?></span>
        <button type="button" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
    </div>
<?php endif; ?>
