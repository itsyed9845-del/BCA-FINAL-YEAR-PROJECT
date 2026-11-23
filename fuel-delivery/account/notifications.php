<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/dashboard_layout.php';
$user = require_role((string)($_SESSION['role'] ?? 'customer'));
$history = ($_GET['view'] ?? '') === 'history';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['action'] ?? '') === 'clear_history') {
        sf_query('DELETE FROM sf_notifications WHERE user_id=? AND is_read=1', [$user['id']]);
    } else {
        sf_query('UPDATE sf_notifications SET is_read=1 WHERE user_id=? AND is_read=0', [$user['id']]);
    }
    redirect('account/notifications.php' . ($history ? '?view=history' : ''));
}
$items = sf_query('SELECT * FROM sf_notifications WHERE user_id=? AND is_read=? ORDER BY id DESC LIMIT 100', [$user['id'], (int)$history])->fetchAll();
dashboard_start('Notifications', 'notifications', $user);
?>
<section class="welcome-panel" data-notifications-page>
    <div><span class="eyebrow">Your activity feed</span><h2><?= $history ? 'Read history' : 'New updates' ?></h2><p><?= $history ? 'Previously opened updates. Clear this history whenever you like.' : 'Updates shown here move to read history after five seconds. New arrivals appear when you refresh.' ?></p></div>
    <a class="btn btn-secondary btn-small" href="<?= url('account/notifications.php' . ($history ? '' : '?view=history')) ?>"><?= $history ? 'New updates' : 'Read history' ?></a>
</section>
<section class="panel" <?= !$history ? 'data-notification-inbox' : '' ?>>
    <p data-inbox-state role="status"></p>
    <p data-inbox-empty <?= $items ? 'hidden' : '' ?>><?= $history ? 'No read notifications.' : 'All caught up. Any remaining or new updates will appear when you refresh.' ?></p>
    <?php foreach ($items as $item): ?>
    <article class="sf-notice <?= !$history ? 'unread' : '' ?>" data-notification-id="<?= (int)$item['id'] ?>">
        <div><a href="<?= url($item['target']) ?>"><?= e($item['message']) ?></a><small><?= e($item['created_at']) ?></small></div>
    </article>
    <?php endforeach; ?>
    <?php if ($items): ?>
    <form method="post" class="sf-action-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $history ? 'clear_history' : 'mark_read' ?>">
        <button class="btn btn-secondary btn-small"><?= $history ? 'Clear read history' : 'Mark all read' ?></button>
    </form>
    <?php endif; ?>
</section>
<?php dashboard_end(); ?>
