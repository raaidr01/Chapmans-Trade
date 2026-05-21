<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_login();

$user = current_user();
$pageTitle = 'Seller Dashboard';

$stmt = db()->prepare(
    'SELECT l.*, c.name AS category_name
     FROM listings l
     INNER JOIN categories c ON c.id = l.category_id
     WHERE l.user_id = :user_id
     ORDER BY l.created_at DESC'
);
$stmt->execute(['user_id' => $user['id']]);
$listings = $stmt->fetchAll();

$salesStmt = db()->prepare(
    'SELECT COUNT(DISTINCT oi.order_id) AS orders_count, COALESCE(SUM(oi.quantity * oi.unit_price), 0) AS sales_total
     FROM order_items oi
     WHERE oi.seller_id = :seller_id'
);
$salesStmt->execute(['seller_id' => $user['id']]);
$sales = $salesStmt->fetch();

require __DIR__ . '/../partials/header.php';
?>
<section class="section">
    <div class="container">
        <div class="dashboard-hero">
            <div>
                <span class="eyebrow">Seller dashboard</span>
                <h1>Manage your listings and sales</h1>
                <p>Keep your side hustle organized with one place to update products, track stock, and grow local buyer trust.</p>
            </div>
            <a class="button" href="<?= e(url('seller/listing_form.php')) ?>">Add new listing</a>
        </div>

        <div class="stat-grid seller-stats">
            <article>
                <strong><?= e((string) count($listings)) ?></strong>
                <span>Your listings</span>
            </article>
            <article>
                <strong><?= e((string) $sales['orders_count']) ?></strong>
                <span>Orders received</span>
            </article>
            <article>
                <strong><?= e(format_currency((float) $sales['sales_total'])) ?></strong>
                <span>Total sales</span>
            </article>
        </div>

        <div class="panel">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Listings</span>
                    <h2>Your current stock</h2>
                </div>
            </div>

            <?php if ($listings === []): ?>
                <div class="empty-state">
                    <h3>No listings yet.</h3>
                    <p>Create your first product to start selling.</p>
                </div>
            <?php else: ?>
                <div class="seller-listings">
                    <?php foreach ($listings as $listing): ?>
                        <article class="seller-row">
                            <img src="<?= e($listing['image_url']) ?>" alt="<?= e($listing['title']) ?>">
                            <div>
                                <h3><?= e($listing['title']) ?></h3>
                                <p><?= e($listing['category_name']) ?> • <?= e(ucfirst($listing['status'])) ?></p>
                            </div>
                            <strong><?= e(format_currency((float) $listing['price'])) ?></strong>
                            <span>Stock: <?= e((string) $listing['stock_quantity']) ?></span>
                            <a href="<?= e(url('seller/listing_form.php?id=' . (string) $listing['id'])) ?>">Edit</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
