<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin_access();
require_permission('verifications.view', url('admin/login.php'));

$pageTitle = 'Seller Verifications';
$verifications = db()->query(
    'SELECT
        sv.*,
        u.full_name,
        u.email,
        u.township
     FROM seller_verifications sv
     INNER JOIN users u ON u.id = sv.user_id
     ORDER BY sv.submitted_at DESC'
)->fetchAll();

require __DIR__ . '/_header.php';
?>
<section class="section">
    <div class="container admin-shell">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Verifications</span>
                <h1>Review seller trust submissions</h1>
            </div>
        </div>

        <div class="panel admin-panel">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Seller</th>
                            <th>Document</th>
                            <th>Status</th>
                            <th>Notes</th>
                            <th>Submitted</th>
                            <th>Review</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($verifications as $verification): ?>
                            <tr>
                                <td>
                                    <strong><?= e($verification['full_name']) ?></strong>
                                    <div class="table-subtext">
                                        <span><?= e($verification['email']) ?></span>
                                        <span><?= e($verification['township']) ?></span>
                                    </div>
                                </td>
                                <td><?= e(ucwords(str_replace('_', ' ', (string) $verification['document_type']))) ?></td>
                                <td><span class="status-pill status-pill-inline"><?= e(ucfirst((string) $verification['status'])) ?></span></td>
                                <td><?= e((string) ($verification['reviewer_notes'] ?: 'No notes')) ?></td>
                                <td><?= e(date('d M Y', strtotime((string) $verification['submitted_at']))) ?></td>
                                <td>
                                    <?php if (user_has_permission('verifications.review')): ?>
                                        <form class="stack-form compact-form" method="post" action="<?= e(url('admin/verification_action.php')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="verification_id" value="<?= e((string) $verification['id']) ?>">
                                            <select name="status">
                                                <?php foreach (['pending', 'approved', 'rejected'] as $status): ?>
                                                    <option value="<?= e($status) ?>" <?= $verification['status'] === $status ? 'selected' : '' ?>>
                                                        <?= e(ucfirst($status)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="text" name="reviewer_notes" value="<?= e((string) ($verification['reviewer_notes'] ?? '')) ?>" placeholder="Review notes">
                                            <button class="button-secondary" type="submit">Save</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
