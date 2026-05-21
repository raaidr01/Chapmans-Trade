<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_redirect(url('cart.php'));

    foreach ($_POST['quantities'] ?? [] as $listingId => $quantity) {
        update_cart_item((int) $listingId, (int) $quantity);
    }

    flash('success', 'Cart updated.');
    redirect(url('cart.php'));
}

$pageTitle = 'Your Cart';
$totals = cart_totals();
require __DIR__ . '/partials/header.php';
?>
<section class="section">
    <div class="container cart-layout">
        <div class="panel">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Cart</span>
                    <h1>Your selected items</h1>
                </div>
            </div>

            <?php if ($totals['rows'] === []): ?>
                <div class="empty-state">
                    <h2>Your cart is empty.</h2>
                    <p>Add some listings to begin checkout.</p>
                    <a class="button" href="<?= e(url('catalog.php')) ?>">Browse listings</a>
                </div>
            <?php else: ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="cart-table">
                        <?php foreach ($totals['rows'] as $row): ?>
                            <article class="cart-row">
                                <img src="<?= e($row['image_url']) ?>" alt="<?= e($row['title']) ?>">
                                <div>
                                    <h3><?= e($row['title']) ?></h3>
                                    <p>Seller: <?= e($row['seller_name']) ?></p>
                                    <p><?= e(format_currency((float) $row['price'])) ?></p>
                                </div>
                                <label>
                                    Qty
                                    <input type="number" min="0" max="<?= e((string) $row['stock_quantity']) ?>" name="quantities[<?= e((string) $row['id']) ?>]" value="<?= e((string) $row['quantity']) ?>">
                                </label>
                                <strong><?= e(format_currency((float) $row['subtotal'])) ?></strong>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <button class="button button-secondary" type="submit">Update cart</button>
                </form>
            <?php endif; ?>
        </div>

        <aside class="summary-card">
            <h2>Order summary</h2>
            <div class="summary-line"><span>Subtotal</span><strong><?= e(format_currency((float) $totals['subtotal'])) ?></strong></div>
            <div class="summary-line"><span>Delivery</span><strong><?= $totals['delivery'] > 0 ? e(format_currency((float) $totals['delivery'])) : 'Free' ?></strong></div>
            <div class="summary-line summary-total"><span>Total</span><strong><?= e(format_currency((float) $totals['total'])) ?></strong></div>
            <a class="button <?= $totals['rows'] === [] ? 'button-disabled' : '' ?>" href="<?= e(url('checkout.php')) ?>">Continue to checkout</a>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
