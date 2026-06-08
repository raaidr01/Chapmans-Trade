<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$totals = cart_totals();
if ($totals['rows'] === []) {
    flash('error', 'Your cart is empty.');
    redirect(url('catalog.php'));
}

// ── Stripe success callback ────────────────────────────────────────────────
if (isset($_GET['stripe_success'], $_GET['order_ref'])) {
    $orderRef = preg_replace('/[^A-Z0-9\-]/', '', strtoupper((string) $_GET['order_ref']));
    flash('success', 'Payment successful. Order reference: ' . $orderRef . '.');
    clear_cart();
    redirect(url('orders.php'));
}

// ── Stripe cancel callback ─────────────────────────────────────────────────
if (isset($_GET['stripe_cancel'])) {
    flash('error', 'Payment was cancelled. Your cart is still saved.');
    redirect(url('checkout.php'));
}

$errors        = [];
$deliveryMethod  = '';
$deliveryAddress = '';
$paymentMethod   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_redirect(url('checkout.php'));

    $deliveryMethod  = $_POST['delivery_method'] ?? '';
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $paymentMethod   = $_POST['payment_method'] ?? '';

    if (!in_array($deliveryMethod, ['pickup', 'courier'], true)) {
        $errors[] = 'Please choose a delivery method.';
    }
    if ($deliveryMethod === 'courier' && $deliveryAddress === '') {
        $errors[] = 'Please provide a delivery address for courier orders.';
    }
    if (!in_array($paymentMethod, ['stripe', 'cash_on_collection'], true)) {
        $errors[] = 'Please choose a payment method.';
    }

    if ($errors === []) {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $user           = current_user();
            $orderReference = generate_order_reference();

            // 1. Insert order
            $orderStmt = $pdo->prepare(
                'INSERT INTO orders (
                    buyer_id, order_reference, subtotal, delivery_fee, total_amount,
                    delivery_method, delivery_address, payment_method, payment_status, order_status
                 ) VALUES (
                    :buyer_id, :order_reference, :subtotal, :delivery_fee, :total_amount,
                    :delivery_method, :delivery_address, :payment_method, :payment_status, "processing"
                 )'
            );
            $orderStmt->execute([
                'buyer_id'         => $user['id'],
                'order_reference'  => $orderReference,
                'subtotal'         => $totals['subtotal'],
                'delivery_fee'     => $totals['delivery'],
                'total_amount'     => $totals['total'],
                'delivery_method'  => $deliveryMethod,
                'delivery_address' => $deliveryMethod === 'courier' ? $deliveryAddress : null,
                'payment_method'   => $paymentMethod,
                'payment_status'   => 'pending',
            ]);
            $orderId = (int) $pdo->lastInsertId();

            // 2. Insert order items and deduct stock
            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, listing_id, seller_id, quantity, unit_price, item_title, item_image_url, seller_name)
                 VALUES (:order_id, :listing_id, :seller_id, :quantity, :unit_price, :item_title, :item_image_url, :seller_name)'
            );
            $stockStmt = $pdo->prepare(
                'UPDATE listings
                 SET stock_quantity = stock_quantity - :qty,
                     status = CASE WHEN stock_quantity - :qty2 <= 0 THEN "sold" ELSE status END
                 WHERE id = :id AND stock_quantity >= :qty3'
            );

            foreach ($totals['rows'] as $row) {
                // Re-fetch with user_id (seller) — get_cart_listing_rows() doesn't include it
                $listing = get_listing_by_id((int) $row['id']);
                if ($listing === null) {
                    throw new RuntimeException('A listing in your cart is no longer available.');
                }
                if ((int) $listing['stock_quantity'] < (int) $row['quantity']) {
                    throw new RuntimeException('"' . $listing['title'] . '" has insufficient stock.');
                }

                $itemStmt->execute([
                    'order_id'      => $orderId,
                    'listing_id'    => $row['id'],
                    'seller_id'     => $listing['user_id'],
                    'quantity'      => $row['quantity'],
                    'unit_price'    => $row['price'],
                    'item_title'    => $row['title'],
                    'item_image_url'=> $listing['image_url'],
                    'seller_name'   => $listing['seller_name'],
                ]);

                $affected = $stockStmt->execute([
                    'qty'  => $row['quantity'],
                    'qty2' => $row['quantity'],
                    'qty3' => $row['quantity'],
                    'id'   => $row['id'],
                ]);
                if ($stockStmt->rowCount() === 0) {
                    throw new RuntimeException('Stock update failed for "' . $row['title'] . '". Please refresh your cart.');
                }
            }

            // 3. Payment record
            $pdo->prepare(
                'INSERT INTO payments (order_id, amount, payment_method, gateway_name, transaction_reference, status, paid_at)
                 VALUES (:order_id, :amount, :payment_method, :gateway_name, :transaction_reference, "pending", NULL)'
            )->execute([
                'order_id'              => $orderId,
                'amount'                => $totals['total'],
                'payment_method'        => $paymentMethod,
                'gateway_name'          => $paymentMethod === 'stripe' ? 'Stripe' : 'Cash On Collection',
                'transaction_reference' => $orderReference . '-PAY',
            ]);

            // 4. Status history
            $pdo->prepare(
                'INSERT INTO order_status_history (order_id, status, note, changed_by_user_id)
                 VALUES (:order_id, "processing", :note, :user_id)'
            )->execute([
                'order_id' => $orderId,
                'note'     => $paymentMethod === 'stripe'
                    ? 'Order placed, awaiting Stripe payment.'
                    : 'Order placed via cash on collection.',
                'user_id'  => $user['id'],
            ]);

            $pdo->commit();

            // 5. Stripe redirect
            if ($paymentMethod === 'stripe') {
                $stripeKey = getenv('STRIPE_SECRET_KEY') ?: '';
                if ($stripeKey === '') {
                    flash('error', 'Stripe is not configured. Contact support. Reference: ' . $orderReference);
                    redirect(url('orders.php'));
                }

                $host       = $_SERVER['HTTP_HOST'] ?? '';
                $proto      = (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
                $baseUrl    = $proto . '://' . $host;
                $successUrl = $baseUrl . '/orders.php?stripe_success=1&order_ref=' . urlencode($orderReference);
                $cancelUrl  = $baseUrl . '/checkout.php?stripe_cancel=1';

                $postFields = [
                    'payment_method_types[0]' => 'card',
                    'mode'                    => 'payment',
                    'success_url'             => $successUrl,
                    'cancel_url'              => $cancelUrl,
                    'client_reference_id'     => $orderReference,
                ];

                $i = 0;
                foreach ($totals['rows'] as $row) {
                    $postFields["line_items[{$i}][price_data][currency]"]               = 'zar';
                    $postFields["line_items[{$i}][price_data][product_data][name]"]     = $row['title'];
                    $postFields["line_items[{$i}][price_data][unit_amount]"]            = (int) round((float) $row['price'] * 100);
                    $postFields["line_items[{$i}][quantity]"]                           = (int) $row['quantity'];
                    $i++;
                }
                if ($totals['delivery'] > 0) {
                    $postFields["line_items[{$i}][price_data][currency]"]               = 'zar';
                    $postFields["line_items[{$i}][price_data][product_data][name]"]     = 'Delivery fee';
                    $postFields["line_items[{$i}][price_data][unit_amount]"]            = (int) round((float) $totals['delivery'] * 100);
                    $postFields["line_items[{$i}][quantity]"]                           = 1;
                }

                $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => http_build_query($postFields),
                    CURLOPT_HTTPHEADER     => [
                        'Authorization: Bearer ' . $stripeKey,
                        'Content-Type: application/x-www-form-urlencoded',
                        'Stripe-Version: 2024-06-20',
                    ],
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $session = json_decode((string) $response, true);

                if ($httpCode === 200 && isset($session['url'])) {
                    header('Location: ' . $session['url']);
                    exit;
                }

                log_application_exception(
                    new RuntimeException('Stripe session failed (HTTP ' . $httpCode . '): ' . ($response ?: 'no response')),
                    'stripe_checkout'
                );
                flash('error', 'Payment gateway error. Your order is saved — reference: ' . $orderReference . '. Contact support.');
                redirect(url('orders.php'));
            }

            // Cash on collection
            flash('success', 'Order placed. Reference: ' . $orderReference . '.');
            clear_cart();
            redirect(url('orders.php'));

        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_application_exception($exception, 'checkout');
            $errors[] = $exception->getMessage() ?: 'We could not place your order. Please try again.';
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

            <form class="stack-form" method="post" id="checkout-form">
                <?= csrf_field() ?>
                <label>
                    Delivery method
                    <select name="delivery_method" id="delivery_method" required>
                        <option value="">Choose one</option>
                        <option value="pickup" <?= $deliveryMethod === 'pickup' ? 'selected' : '' ?>>Community pickup</option>
                        <option value="courier" <?= $deliveryMethod === 'courier' ? 'selected' : '' ?>>Courier delivery</option>
                    </select>
                </label>
                <div id="address-wrap" style="display:<?= $deliveryMethod === 'courier' ? 'block' : 'none' ?>">
                    <label>
                        Delivery address
                        <textarea name="delivery_address" rows="3" placeholder="Street address, suburb, township"><?= e($deliveryAddress) ?></textarea>
                    </label>
                </div>
                <label>
                    Payment method
                    <select name="payment_method" required>
                        <option value="">Choose one</option>
                        <option value="stripe" <?= $paymentMethod === 'stripe' ? 'selected' : '' ?>>Pay by card (Stripe)</option>
                        <option value="cash_on_collection" <?= $paymentMethod === 'cash_on_collection' ? 'selected' : '' ?>>Cash on collection</option>
                    </select>
                </label>
                <small style="color:var(--muted)">Card payments are processed securely by Stripe. You will be redirected to complete payment.</small>
                <button class="button" type="submit">Place order</button>
            </form>
        </div>

        <aside class="summary-card">
            <h2>Order summary</h2>
            <?php foreach ($totals['rows'] as $row): ?>
                <div class="summary-line">
                    <span><?= e($row['title']) ?> ×<?= e((string) $row['quantity']) ?></span>
                    <strong><?= e(format_currency((float) $row['subtotal'])) ?></strong>
                </div>
            <?php endforeach; ?>
            <div class="summary-line">
                <span>Delivery</span>
                <strong><?= $totals['delivery'] > 0 ? e(format_currency((float) $totals['delivery'])) : 'Free' ?></strong>
            </div>
            <div class="summary-line summary-total">
                <span>Total</span>
                <strong><?= e(format_currency((float) $totals['total'])) ?></strong>
            </div>
        </aside>
    </div>
</section>
<script>
document.getElementById('delivery_method').addEventListener('change', function () {
    document.getElementById('address-wrap').style.display = this.value === 'courier' ? 'block' : 'none';
});
</script>
<?php require __DIR__ . '/partials/footer.php'; ?>