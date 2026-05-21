<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$pageTitle = 'Order Tracking';
$user = current_user();
$stmt = db()->prepare(
    'SELECT o.*, COUNT(oi.id) AS items_count
     FROM orders o
     LEFT JOIN order_items oi ON oi.order_id = o.id
     WHERE o.buyer_id = :buyer_id
     GROUP BY o.id
     ORDER BY o.created_at DESC'
);
$stmt->execute(['buyer_id' => $user['id']]);
$orders = $stmt->fetchAll();

require __DIR__ . '/partials/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Orders</span>
                <h1>Track your purchases</h1>
            </div>
        </div>

        <?php if ($orders === []): ?>
            <div class="empty-state">
                <h2>No orders yet.</h2>
                <p>Your completed checkouts will appear here with their current status.</p>
            </div>
        <?php else: ?>
            <div class="orders-grid">
                <?php foreach ($orders as $order): ?>
                    <article class="order-card">
                        <div class="order-top">
                            <div>
                                <span class="eyebrow"><?= e($order['order_reference'] ?: ('Order #' . (string) $order['id'])) ?></span>
                                <h2><?= e(ucfirst(str_replace('_', ' ', $order['order_status']))) ?></h2>
                            </div>
                            <strong><?= e(format_currency((float) $order['total_amount'])) ?></strong>
                        </div>
                        <div class="order-meta">
                            <span><?= e((string) $order['items_count']) ?> items</span>
                            <span><?= e(ucfirst($order['delivery_method'])) ?></span>
                            <span><?= e(ucfirst(str_replace('_', ' ', $order['payment_method']))) ?></span>
                            <span>Payment: <?= e(ucfirst($order['payment_status'])) ?></span>
                            <span><?= e(date('d M Y', strtotime($order['created_at']))) ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
