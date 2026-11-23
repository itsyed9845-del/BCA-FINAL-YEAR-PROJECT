<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    redirect(role_home((string) $_SESSION['role']));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter a valid email address and password.';
    } else {
        $statement = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'The email address or password is incorrect.';
        } elseif ($user['status'] !== 'active') {
            $error = 'This account is suspended. Contact an administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            redirect(role_home($user['role']));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Sign In | <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/custom.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/upgrade.css') ?>">
</head>
<body class="auth-page">
<main class="auth-shell">
    <section class="auth-visual"><a class="brand" href="<?= url('index.php') ?>"><span class="brand-mark"><i class="fa-solid fa-bolt"></i></span><span>SmartFuel <em>One</em></span></a><div class="auth-message"><span class="status-pill"><i class="pulse-dot"></i> Secure operations network</span><h1>One login.<br><span class="gradient-text">Three powerful workspaces.</span></h1><p>Customers, verified delivery partners and administrators are automatically routed to their protected command centers.</p></div></section>
    <section class="auth-panel">
        <form class="auth-card" method="post" novalidate>
            <?= csrf_field() ?>
            <a class="btn btn-secondary btn-small" href="<?= url('index.php') ?>" style="display:flex;width:fit-content;min-height:44px;margin-bottom:24px;color:#67e8f9">
                <span aria-hidden="true">&larr;</span> Back to Home
            </a>
            <span class="eyebrow">Welcome back</span>
            <h2>Sign in securely</h2>
            <p>Use your SmartFuel One account credentials.</p>
            <?php if ($error): ?><div class="alert error"><i class="fa-solid fa-circle-exclamation"></i><?= e($error) ?></div><?php endif; ?>
            <?php $flashMessage = pull_flash(); if ($flashMessage): ?><div class="alert <?= e($flashMessage['type']) ?>"><i class="fa-solid fa-circle-check"></i><?= e($flashMessage['message']) ?></div><?php endif; ?>
            <div class="form-group"><label for="email">Email address</label><input id="email" name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" required placeholder="name@example.com"></div>
            <div class="form-group"><label for="password">Password</label><div class="password-wrap"><input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password"><button type="button" data-password-toggle="password" aria-label="Show password"><i class="fa-solid fa-eye"></i></button></div></div>
            
            <button class="btn" type="submit" style="width:100%"><i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In</button>
            <div class="auth-link">New to SmartFuel? <a href="<?= url('auth/register.php') ?>">Create an account</a></div>
        </form>
    </section>
</main>
<script src="<?= url('assets/js/main.js') ?>"></script>
</body>
</html>
