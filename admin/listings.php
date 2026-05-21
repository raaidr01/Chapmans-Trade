<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin_access();
require_permission('listings.view', url('admin/login.php'));

$pageTitle = 'Listing Moderation';
$statusFilter = trim($_GET['status'] ?? '');

$sql = 'SELECT l.*, c.name AS category_name, u.full_name AS seller_name
        FROM listings l
        INNER JOIN categories c ON c.id = l.category_id
        INNER JOIN users u ON u.id = l.user_id
        WHERE 1 = 1';
$params = [];

if ($statusFilter !== '') {
    $sql .= ' AND l.status = :status';
    $params['status'] = $statusFilter;
}

$sql .= ' ORDER BY l.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();

require __DIR__ . '/_header.php';
?>
<section class="section">
    <div class="container admin-shell">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Listings</span>
                <h1>Moderate marketplace stock</h1>
            </div>
        </div>

        <form class="filter-bar admin-toolbar" method="get">
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach (['draft', 'active', 'paused', 'sold'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
                        <?= e(ucfirst($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="button" type="submit">Filter</button>
        </form>

        <div class="panel admin-panel">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Listing</th>
                            <th>Seller</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Moderation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listings as $listing): ?>
                            <tr>
                                <td>
                                    <strong><?= e($listing['title']) ?></strong>
                                    <div class="table-subtext">
                                        <span>Stock: <?= e((string) $listing['stock_quantity']) ?></span>
                                        <span><?= e(date('d M Y', strtotime((string) $listing['created_at']))) ?></span>
                                    </div>
                                </td>
                                <td><?= e($listing['seller_name']) ?></td>
                                <td><?= e($listing['category_name']) ?></td>
                                <td><?= e(format_currency((float) $listing['price'])) ?></td>
                                <td><span class="status-pill status-pill-inline"><?= e(ucfirst((string) $listing['status'])) ?></span></td>
                                <td>
                                    <?php if (user_has_permission('listings.moderate')): ?>
                                        <form class="inline-form" method="post" action="<?= e(url('admin/listing_action.php')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="listing_id" value="<?= e((string) $listing['id']) ?>">
                                            <select name="status">
                                                <?php foreach (['draft', 'active', 'paused', 'sold'] as $status): ?>
                                                    <option value="<?= e($status) ?>" <?= $listing['status'] === $status ? 'selected' : '' ?>>
                                                        <?= e(ucfirst($status)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
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
