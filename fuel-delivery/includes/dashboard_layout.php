<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

function get_dashboard_links(string $role): array
{
    if ($role === 'admin') {
        return [
            ['dashboard', 'admin/dashboard.php', 'fa-chart-pie', 'Command Center'],
            ['orders', 'admin/manage_orders.php', 'fa-boxes-stacked', 'Manage Orders'],
            ['users', 'admin/manage_users.php', 'fa-users-gear', 'Users & Fleet'],
            ['inventory', 'admin/inventory.php', 'fa-gas-pump', 'Fuel Inventory'],
            ['reports', 'admin/financial_reports.php', 'fa-chart-line', 'Financial Reports'],
        ];
    }

    if ($role === 'delivery_agent') {
        return [
            ['dashboard', 'delivery/dashboard.php', 'fa-gauge-high', 'Dispatch Hub'],
            ['active', 'delivery/active_deliveries.php', 'fa-route', 'Active Deliveries'],
            ['earnings', 'delivery/earnings.php', 'fa-wallet', 'Earnings'],
            ['profile', 'delivery/profile.php', 'fa-id-card', 'My Profile'],
        ];
    }

    return [
        ['dashboard', 'customer/dashboard.php', 'fa-house', 'Dashboard'],
        ['order', 'customer/place_order.php', 'fa-gas-pump', 'Place Order'],
        ['track', 'customer/track_order.php', 'fa-location-crosshairs', 'Track Order'],
        ['history', 'customer/order_history.php', 'fa-clock-rotate-left', 'Order History'],
        ['profile', 'customer/profile.php', 'fa-user-gear', 'Profile'],
    ];
}

function dashboard_start(string $title, string $active, array $user): void
{
    $links = get_dashboard_links($user['role']);
    $links[] = ['notifications', 'account/notifications.php', 'fa-bell', 'Notifications'];
    if ($user['role'] === 'customer') {
        $links[] = ['insights', 'customer/insights.php', 'fa-chart-line', 'Fuel Insights'];
    }
    $roleLabel = match ($user['role']) {
        'admin' => 'Administration',
        'delivery_agent' => 'Delivery Partner',
        default => 'Customer Portal',
    };
    $flashMessage = pull_flash();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= url('assets/css/custom.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/upgrade.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/cleanup.css') ?>">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body class="dashboard-body">
<div class="dashboard-shell">
    <aside class="sidebar" data-sidebar>
        <div class="side-head">
            <a class="brand" href="<?= url(role_home($user['role'])) ?>"><span class="brand-mark"><i class="fa-solid fa-bolt"></i></span><span>SmartFuel <em>One</em></span></a>
            <button data-sidebar-close aria-label="Close menu"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <p class="role-label"><?= e($roleLabel) ?></p>
        <nav class="side-nav">
            <?php foreach ($links as [$key, $path, $icon, $label]): ?>
                <a class="<?= $active === $key ? 'active' : '' ?>" href="<?= url($path) ?>"><i class="fa-solid <?= e($icon) ?>"></i><span><?= e($label) ?></span></a>
            <?php endforeach; ?>
        </nav>
        <div class="side-profile">
            <div class="avatar"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></div>
            <div><strong><?= e($user['full_name']) ?></strong><small><?= e(ucwords(str_replace('_', ' ', $user['role']))) ?></small></div>
            <a class="sidebar-logout" href="<?= url('auth/logout.php') ?>"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Log out</a>
        </div>
    </aside>
    <main class="dashboard-main">
        <header class="operations-header">
            <button class="menu-button" data-sidebar-toggle aria-label="Open navigation"><i class="fa-solid fa-bars"></i></button>
            <span class="radar" aria-hidden="true"><i></i><b></b></span>
            <div class="operations-title"><h1><?= e($title) ?></h1><p><span></span> Workspace · <?= date('d M Y') ?></p></div>
            <div class="header-actions">
                <a class="network-pill" href="<?= url('account/notifications.php') ?>"><i></i><span><small>NOTIFICATIONS</small><strong data-unread>0</strong></span></a>
                <?php if ($user['role'] === 'customer'): ?><a class="btn btn-small" data-magnetic href="<?= url('customer/place_order.php') ?>"><i class="fa-solid fa-plus"></i> Quick Order</a><?php endif; ?>
                <?php if ($user['role'] === 'delivery_agent'): ?>
                    <form class="location-header-form" action="<?= url('delivery/update_location.php') ?>" method="post" data-location-form>
                        <?= csrf_field() ?>
                        <button class="btn btn-small btn-secondary" type="button" data-location-button><i class="fa-solid fa-location-crosshairs"></i><span>Share GPS</span></button>
                        <small data-location-status>GPS off</small>
                    </form>
                <?php endif; ?>
            </div>
        </header>
        <div class="dashboard-content" data-sync-content>
            <?php if ($user['role'] === 'delivery_agent' && !sf_approved((int)$user['id'])): ?>
                <div class="alert warning">Delivery approval: <?= e(sf_query('SELECT approval_status FROM sf_agent_approvals WHERE user_id=?', [$user['id']])->fetchColumn() ?: 'Pending') ?>. You can edit your profile and contact your administrator.</div>
            <?php endif; ?>
            <?php if ($flashMessage): ?>
                <div class="alert <?= e($flashMessage['type']) ?>" <?= $flashMessage['type'] === 'success' ? 'data-toast role="status"' : 'role="alert"' ?>><i class="fa-solid <?= $flashMessage['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i><?= e($flashMessage['message']) ?></div>
            <?php endif; ?>
    <?php
}

function dashboard_end(): void
{
    ?>
        </div>
    </main>
</div>
<script src="<?= url('assets/js/main.js') ?>"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>window.SmartFuel = <?= json_encode(['base'=>BASE_URL,'csrf'=>csrf_token(),'userId'=>(int)$_SESSION['user_id'],'role'=>$_SESSION['role']], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
<script src="<?= url('assets/js/upgrade.js') ?>"></script>
<script src="<?= url('assets/js/address.js') ?>"></script>
</body>
</html>
    <?php
}

function sf_chat(string $code, string $title): void
{
    ?>
    <details class="job-conversation">
        <summary><?= e($title) ?></summary>
        <div class="order-chat" data-order-chat data-chat-url="<?= url('api/order_chat.php') ?>" data-chat-code="<?= e($code) ?>">
            <div class="chat-messages" data-chat-messages>Loading conversation…</div>
            <form data-chat-form>
                <?= csrf_field() ?>
                <input name="message" maxlength="500" autocomplete="off" placeholder="Write a message…" aria-label="Message" required>
                <button class="btn btn-small" type="submit">Send</button>
            </form>
        </div>
    </details>
    <?php
}
