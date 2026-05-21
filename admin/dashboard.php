<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin_access();
require_permission('admin.dashboard.view', url('admin/login.php'));

$pageTitle = 'Admin Dashboard';

$userStats = db()->query(
    'SELECT
        COUNT(*) AS total_users,
        SUM(account_status = "active") AS active_users,
        SUM(verification_status = "pending") AS pending_verifications
     FROM users'
)->fetch();

$listingStats = db()->query(
    'SELECT
        COUNT(*) AS total_listings,
        SUM(status = "active") AS active_listings,
        SUM(status = "paused") AS paused_listings
     FROM listings'
)->fetch();

$orderStats = db()->query(
    'SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(total_amount), 0) AS total_order_value,
        SUM(payment_status = "pending") AS pending_payments
     FROM orders'
)->fetch();

$staffStats = db()->query(
    'SELECT
        SUM(r.slug = "super_admin") AS super_admins,
        SUM(r.slug = "admin") AS admins,
        SUM(r.slug = "moderator") AS moderators
     FROM user_roles ur
     INNER JOIN roles r ON r.id = ur.role_id
     WHERE r.admin_area_access = 1'
)->fetch();

$recentUsers = db()->query(
    'SELECT u.id, u.full_name, u.email, u.account_status, u.created_at,
            GROUP_CONCAT(DISTINCT r.name ORDER BY r.sort_order SEPARATOR ", ") AS role_names
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN roles r ON r.id = ur.role_id
     GROUP BY u.id
     ORDER BY u.created_at DESC
     LIMIT 5'
)->fetchAll();

$recentListings = db()->query(
    'SELECT l.id, l.title, l.status, l.created_at, u.full_name AS seller_name
     FROM listings l
     INNER JOIN users u ON u.id = l.user_id
     ORDER BY l.created_at DESC
     LIMIT 5'
)->fetchAll();

require __DIR__ . '/_header.php';
?>
<section class="section">
    <div class="container admin-shell">
        <div class="dashboard-hero">
            <div>
                <span class="eyebrow">Admin dashboard</span>
                <h1>Monitor the marketplace at a glance</h1>
                <p>Use the RBAC-controlled portal to oversee staff permissions, customer accounts, listings, orders, and trust workflows.</p>
            </div>
        </div>

        <div class="admin-metric-grid">
            <article class="admin-metric-card">
                <span>Total users</span>
                <strong><?= e((string) ($userStats['total_users'] ?? 0)) ?></strong>
                <small><?= e((string) ($userStats['active_users'] ?? 0)) ?> active accounts</small>
            </article>
            <article class="admin-metric-card">
                <span>Total listings</span>
                <strong><?= e((string) ($listingStats['total_listings'] ?? 0)) ?></strong>
                <small><?= e((string) ($listingStats['active_listings'] ?? 0)) ?> active, <?= e((string) ($listingStats['paused_listings'] ?? 0)) ?> paused</small>
            </article>
            <article class="admin-metric-card">
                <span>Total orders</span>
                <strong><?= e((string) ($orderStats['total_orders'] ?? 0)) ?></strong>
                <small><?= e(format_currency((float) ($orderStats['total_order_value'] ?? 0))) ?> lifetime order value</small>
            </article>
            <article class="admin-metric-card">
                <span>Staff roles</span>
                <strong><?= e((string) (($staffStats['super_admins'] ?? 0) + ($staffStats['admins'] ?? 0) + ($staffStats['moderators'] ?? 0))) ?></strong>
                <small><?= e((string) ($staffStats['super_admins'] ?? 0)) ?> super admin, <?= e((string) ($staffStats['admins'] ?? 0)) ?> admin, <?= e((string) ($staffStats['moderators'] ?? 0)) ?> moderator</small>
            </article>
        </div>

        <div class="admin-panel-grid">
            <section class="panel admin-panel">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Recent users</span>
                        <h2>Latest account activity</h2>
                    </div>
                    <?php if (user_has_permission('users.view')): ?>
                        <a class="button-secondary" href="<?= e(url('admin/users.php')) ?>">Open users</a>
                    <?php endif; ?>
                </div>

                <div class="admin-list">
                    <?php foreach ($recentUsers as $user): ?>
                        <article class="admin-list-item">
                            <div>
                                <strong><?= e($user['full_name']) ?></strong>
                                <p><?= e($user['email']) ?></p>
                            </div>
                            <div>
                                <span class="status-pill"><?= e($user['role_names'] ?: 'No roles') ?></span>
                                <small><?= e(date('d M Y', strtotime($user['created_at']))) ?></small>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="panel admin-panel">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow">Recent listings</span>
                        <h2>Newest marketplace stock</h2>
                    </div>
                    <?php if (user_has_permission('listings.view')): ?>
                        <a class="button-secondary" href="<?= e(url('admin/listings.php')) ?>">Open listings</a>
                    <?php endif; ?>
                </div>

                <div class="admin-list">
                    <?php foreach ($recentListings as $listing): ?>
                        <article class="admin-list-item">
                            <div>
                                <strong><?= e($listing['title']) ?></strong>
                                <p><?= e($listing['seller_name']) ?></p>
                            </div>
                            <div>
                                <span class="status-pill"><?= e(ucfirst($listing['status'])) ?></span>
                                <small><?= e(date('d M Y', strtotime($listing['created_at']))) ?></small>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <section class="panel admin-panel">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Workflow flags</span>
                    <h2>Action items for the team</h2>
                </div>
            </div>

            <div class="admin-alert-grid">
                <article class="admin-alert-card">
                    <strong><?= e((string) ($userStats['pending_verifications'] ?? 0)) ?></strong>
                    <span>Users still waiting for verification</span>
                </article>
                <article class="admin-alert-card">
                    <strong><?= e((string) ($orderStats['pending_payments'] ?? 0)) ?></strong>
                    <span>Orders with pending payment status</span>
                </article>
                <article class="admin-alert-card">
                    <strong><?= e((string) ($listingStats['paused_listings'] ?? 0)) ?></strong>
                    <span>Listings currently paused or under moderation</span>
                </article>
            </div>
        </section>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
