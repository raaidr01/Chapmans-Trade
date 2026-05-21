<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$listingId = (int) ($_GET['id'] ?? 0);
$listing = get_listing_by_id($listingId);

if ($listing === null) {
    flash('error', 'Listing not found.');
    redirect(url('catalog.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_redirect(url('product.php?id=' . (string) $listingId));

    $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
    $quantity = min($quantity, (int) $listing['stock_quantity']);
    add_to_cart($listingId, $quantity);
    flash('success', 'Item added to your cart.');
    redirect(url('cart.php'));
}

$pageTitle = $listing['title'];
require __DIR__ . '/partials/header.php';
?>
<section class="section">
    <div class="container product-layout">
        <div class="product-media">
            <img src="<?= e($listing['image_url']) ?>" alt="<?= e($listing['title']) ?>">
        </div>
        <div class="product-info">
            <span class="tag"><?= e($listing['category_name']) ?></span>
            <h1><?= e($listing['title']) ?></h1>
            <div class="product-price"><?= e(format_currency((float) $listing['price'])) ?></div>
            <p><?= nl2br(e($listing['description'])) ?></p>

            <div class="trust-panel">
                <div><strong>Seller</strong><span><?= e($listing['seller_name']) ?></span></div>
                <div><strong>Status</strong><span><?= e(ucfirst($listing['verification_status'])) ?></span></div>
                <div><strong>Location</strong><span><?= e($listing['township']) ?></span></div>
                <div><strong>Language</strong><span><?= e($listing['language_pref']) ?></span></div>
                <div><strong>Condition</strong><span><?= e(ucfirst($listing['item_condition'])) ?></span></div>
                <div><strong>Available stock</strong><span><?= e((string) $listing['stock_quantity']) ?></span></div>
            </div>

            <form class="purchase-card" method="post">
                <?= csrf_field() ?>
                <label for="quantity">Quantity</label>
                <input id="quantity" type="number" name="quantity" min="1" max="<?= e((string) $listing['stock_quantity']) ?>" value="1">
                <button class="button" type="submit">Add to cart</button>
            </form>
        </div>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
