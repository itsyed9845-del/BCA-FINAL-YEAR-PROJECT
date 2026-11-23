<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/dashboard_layout.php';

$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    try {
        $id = (int)($_POST['user_id'] ?? 0);

        db()->beginTransaction();

        $target = sf_query(
            "SELECT * FROM users WHERE id=? AND role!='admin' FOR UPDATE",
            [$id]
        )->fetch();

        if (!$target) {
            throw new RuntimeException('Choose a customer or delivery partner.');
        }

        if (($_POST['action'] ?? '') === 'approval') {
            $approval = (string)($_POST['approval'] ?? '');

            if (
                $target['role'] !== 'delivery_agent' ||
                !in_array($approval, ['Approved', 'Rejected'], true)
            ) {
                throw new RuntimeException('Invalid approval action.');
            }

            // An agent must have a valid, existing licence document before approval.
            if ($approval === 'Approved') {
                $profile = sf_query(
                    'SELECT license_document FROM delivery_agent_profiles WHERE user_id=?',
                    [$id]
                )->fetch();

                $licensePath = (string)($profile['license_document'] ?? '');

                if (
                    !preg_match(
                        '/^licenses\/[a-f0-9]{32}\.(pdf|jpg|png)$/i',
                        $licensePath
                    )
                ) {
                    throw new RuntimeException(
                        'Cannot approve this delivery agent. A valid driving licence document is required.'
                    );
                }

                $privateRoot = dirname(__DIR__, 3) . '/smartfuel_private_uploads';
                $fullLicensePath = $privateRoot . '/' . $licensePath;

                if (!is_file($fullLicensePath)) {
                    throw new RuntimeException(
                        'The uploaded driving licence file could not be found. Please check the document.'
                    );
                }
            }

            sf_query(
                'UPDATE sf_agent_approvals SET approval_status=?,reviewed_by=?,reviewed_at=NOW() WHERE user_id=?',
                [$approval, $user['id'], $id]
            );

            sf_notify(
                $id,
                'Your delivery account review: ' . $approval,
                'delivery/profile.php'
            );

            if ($approval === 'Rejected') {
                sf_query(
                    'UPDATE delivery_agent_profiles SET is_online=0 WHERE user_id=?',
                    [$id]
                );

                sf_query(
                    'DELETE FROM agent_live_locations WHERE agent_id=?',
                    [$id]
                );
            }
        } else {
            $status = (string)($_POST['status'] ?? '');

            if (!in_array($status, ['active', 'suspended'], true)) {
                throw new RuntimeException('Invalid account status.');
            }

            sf_query(
                'UPDATE users SET status=? WHERE id=?',
                [$status, $id]
            );

            if ($status === 'suspended') {
                sf_query(
                    'UPDATE delivery_agent_profiles SET is_online=0 WHERE user_id=?',
                    [$id]
                );

                sf_query(
                    'DELETE FROM agent_live_locations WHERE agent_id=?',
                    [$id]
                );
            }
        }

        db()->commit();
        flash('success', 'Account updated.');

    } catch (Throwable $error) {
        safe_rollback(db());
        flash('error', sf_error($error));
    }

    redirect('admin/manage_users.php');
}

$users = sf_query(
    "SELECT
        u.*,
        p.vehicle_plate,
        p.license_number,
        p.license_document,
        p.is_online,
        a.approval_status
    FROM users u
    LEFT JOIN delivery_agent_profiles p ON p.user_id=u.id
    LEFT JOIN sf_agent_approvals a ON a.user_id=u.id
    WHERE u.role!='admin'
    ORDER BY a.approval_status='Pending' DESC,u.id DESC"
)->fetchAll();

dashboard_start('Users & Fleet', 'users', $user);
?>

<section class="welcome-panel">
    <div>
        <span class="eyebrow">Account management</span>
        <h2>The people behind each delivery.</h2>
        <p>
            Review new partner credentials before approving dispatch access.
            Previously active partners retain approval during upgrade.
        </p>
    </div>
</section>

<section class="panel">

    <div class="toolbar">
        <input
            data-table-filter="#users-table"
            placeholder="Search people, vehicle or email"
            aria-label="Search users"
        >
    </div>

    <div class="table-wrap">
        <table id="users-table">

            <thead>
                <tr>
                    <th>Person</th>
                    <th>Contact</th>
                    <th>Vehicle & licence</th>
                    <th>Partner approval</th>
                    <th>Account</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($users as $account): ?>

                <tr>

                    <td>
                        <strong><?= e($account['full_name']) ?></strong>
                        <br>
                        <?= e(str_replace('_', ' ', $account['role'])) ?>
                    </td>

                    <td>
                        <?= e($account['email']) ?>
                        <br>
                        <?= e($account['phone']) ?>
                    </td>

                    <td>
                        <?= e($account['vehicle_plate'] ?: '—') ?>
                        <br>
                        <?= e($account['license_number'] ?: 'No licence number') ?>

                        <br>

                        <?php if (
                            $account['role'] === 'delivery_agent' &&
                            !empty($account['license_document'])
                        ): ?>

                            <a
                                href="view_license.php?user_id=<?= (int)$account['id'] ?>"
                                target="_blank"
                                rel="noopener"
                            >
                                View Driving Licence
                            </a>

                        <?php elseif ($account['role'] === 'delivery_agent'): ?>

                            <span class="badge warning">Document not uploaded</span>

                        <?php endif; ?>
                    </td>

                    <td>

                        <?php if ($account['role'] === 'delivery_agent'): ?>

                            <span class="badge info">
                                <?= e($account['approval_status'] ?? 'Pending') ?>
                            </span>

                            <form method="post" class="sf-action-form">

                                <?= csrf_field() ?>

                                <input
                                    type="hidden"
                                    name="user_id"
                                    value="<?= (int)$account['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="approval"
                                >

                                <select name="approval" aria-label="Partner approval">
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                </select>

                                <button class="btn btn-small">
                                    Save review
                                </button>

                            </form>

                        <?php else: ?>

                            Not required

                        <?php endif; ?>

                    </td>

                    <td>

                        <span class="badge <?= status_class($account['status']) ?>">
                            <?= e($account['status']) ?>
                        </span>

                        <form method="post" class="sf-action-form">

                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="user_id"
                                value="<?= (int)$account['id'] ?>"
                            >

                            <input
                                type="hidden"
                                name="status"
                                value="<?= $account['status'] === 'active' ? 'suspended' : 'active' ?>"
                            >

                            <button class="btn btn-secondary btn-small">
                                <?= $account['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                            </button>

                        </form>

                    </td>

                </tr>

                <?php endforeach; ?>

            </tbody>

        </table>
    </div>

</section>

<?php dashboard_end(); ?>