<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$totals = cart_totals();
if ($totals['rows'] === []) {
    flash('error', 'Your cart is empty.');
    redirect(url('catalog.php'));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_redirect(url('checkout.php'));

    $deliveryMethod = $_POST['delivery_method'] ?? '';
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? '';

    if (!in_array($deliveryMethod, ['pickup', 'courier'], true)) {
        $errors[] = 'Please choose a delivery method.';
    }

    if ($deliveryMethod === 'courier' && $deliveryAddress === '') {
        $errors[] = 'Please provide a delivery address.';
    }

    if (!in_array($paymentMethod, ['eft', 'cash_on_collection', 'card_demo'], true)) {
        $errors[] = 'Please choose a payment method.';
    }

    if ($errors === []) {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $user = current_user();
            $orderReference = generate_order_reference();
            $paymentStatus = $paymentMethod === 'cash_on_collection' ? 'pending' : 'paid';

            $orderStmt = $pdo->prepare(
                'INSERT INTO orders (
                    buyer_id, order_reference, subtotal, delivery_fee, total_amount, delivery_method,
                    delivery_address, payment_method, payment_status, order_status
                 ) VALUES (
                    :buyer_id, :order_reference, :subtotal, :delivery_fee, :total_amount, :delivery_method,
                    :delivery_address, :payment_method, :payment_status, :order_status
                 )'
            );
            $orderStmt->execute([
                'buyer_id' => $user['id'],
                'order_reference' => $orderReference,
                'subtotal' => $totals['subtotal'],
                'delivery_fee' => $totals['delivery'],
                'total_amount' => $totals['total'],
                'delivery_method' => $deliveryMethod,
                'delivery_address' => $deliveryAddress,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'order_status' => 'processing',
            ]);

            $orderId = (int) $pdo->lastInsertId();
            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, listing_id, seller_id, quantity, unit_price, item_title, item_image_url, seller_name)
                 VALUES (:order_id, :listing_id, :seller_id, :quantity, :unit_price, :item_title, :item_image_url, :seller_name)'
            );
            $stockStmt = $pdo->prepare(
                'UPDATE listings
                 SET stock_quantity = stock_quantity - :stock_quantity_to_deduct,
                     status = CASE WHEN stock_quantity - :stock_quantity_for_status <= 0 THEN "sold" ELSE status END
                 WHERE id = :id'
            );
            $paymentStmt = $pdo->prepare(
                'INSERT INTO payments (order_id, amount, payment_method, gateway_name, transaction_reference, status, paid_at)
                 VALUES (:order_id, :amount, :payment_method, :gateway_name, :transaction_reference, :status, :paid_at)'
            );
            $historyStmt = $pdo->prepare(
                'INSERT INTO order_status_history (order_id, status, note, changed_by_user_id)
                 VALUES (:order_id, :status, :note, :changed_by_user_id)'
            );

            foreach ($totals['rows'] as $row) {
                $listing = get_listing_by_id((int) $row['id']);
                if ($listing === null) {
                    throw new RuntimeException('One of the selected listings is no longer available.');
                }

                 if ((int) $row['quantity'] > (int) $listing['stock_quantity']) {
                    throw new RuntimeException('One of the selected quantities is no longer in stock.');
                }

                $itemStmt->execute([
                    'order_id' => $orderId,
                    'listing_id' => $row['id'],
                    'seller_id' => $listing['user_id'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['price'],
                    'item_title' => $row['title'],
                    'item_image_url' => $listing['image_url'],
                    'seller_name' => $listing['seller_name'],
                ]);

                $stockStmt->execute([
                    'stock_quantity_to_deduct' => $row['quantity'],
                    'stock_quantity_for_status' => $row['quantity'],
                    'id' => $row['id'],
                ]);
            }

            $paymentStmt->execute([
                'order_id' => $orderId,
                'amount' => $totals['total'],
                'payment_method' => $paymentMethod,
                'gateway_name' => $paymentMethod === 'eft'
                    ? 'Instant EFT Demo'
                    : ($paymentMethod === 'card_demo' ? 'Card Sandbox' : 'Cash On Collection'),
                'transaction_reference' => $orderReference . '-PAY',
                'status' => $paymentStatus,
                'paid_at' => $paymentStatus === 'paid' ? date('Y-m-d H:i:s') : null,
            ]);

            $historyStmt->execute([
                'order_id' => $orderId,
                'status' => 'processing',
                'note' => $deliveryMethod === 'pickup'
                    ? 'Order placed and awaiting seller confirmation for collection.'
                    : 'Order placed and queued for courier coordination.',
                'changed_by_user_id' => $user['id'],
            ]);

            $pdo->commit();
            clear_cart();
            flash('success', 'Order placed successfully. Reference: ' . $orderReference . '.');
            redirect(url('orders.php'));
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            log_application_exception($exception, 'checkout');
            $errors[] = 'We could not place your order right now. Please try again.';
        }
    }
}

$pageTitle = 'Checkout';
require __DIR__ . '/partials/header.php';
?>
<section class="section">
    <div class="container cart-layout">
        <div class="panel">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Checkout</span>
                    <h1>Complete your order</h1>
                </div>
            </div>

            <?php if ($errors !== []): ?>
                <div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div>
            <?php endif; ?>

            <form class="stack-form" method="post">
                <?= csrf_field() ?>
                <label>
                    Delivery method
                    <select name="delivery_method" required>
                        <option value="">Choose one</option>
                        <option value="pickup">Community pickup</option>
                        <option value="courier">Courier delivery</option>
                    </select>
                </label>
                <label>
                    Delivery address
                    <textarea name="delivery_address" rows="4" placeholder="Enter a full address for courier orders"></textarea>
                </label>
                <label>
                    Payment method
                    <select name="payment_method" required>
                        <option value="">Choose one</option>
                        <option value="eft">Instant EFT</option>
                        <option value="cash_on_collection">Cash on collection</option>
                        <option value="card_demo">Card payment demo</option>
                    </select>
                </label>
                <button class="button" type="submit">Place order</button>
            </form>
        </div>

        <aside class="summary-card">
            <h2>Order summary</h2>
            <?php foreach ($totals['rows'] as $row): ?>
                <div class="summary-line"><span><?= e($row['title']) ?> x <?= e((string) $row['quantity']) ?></span><strong><?= e(format_currency((float) $row['subtotal'])) ?></strong></div>
            <?php endforeach; ?>
            <div class="summary-line"><span>Delivery</span><strong><?= $totals['delivery'] > 0 ? e(format_currency((float) $totals['delivery'])) : 'Free' ?></strong></div>
            <div class="summary-line summary-total"><span>Total</span><strong><?= e(format_currency((float) $totals['total'])) ?></strong></div>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>


