<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

require_login();

$totals = cart_totals();
if ($totals['rows'] === []) {
    flash('error', 'Your cart is empty.');
    redirect(url('catalog.php'));
}

// ── Handle Stripe success callback ────────────────────────────────────────
if (isset($_GET['stripe_success'], $_GET['order_ref'])) {
    $orderRef = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($_GET['order_ref']));
    flash('success', 'Payment successful. Order reference: ' . $orderRef . '.');
    clear_cart();
    redirect(url('orders.php'));
}

// ── Handle Stripe cancel callback ─────────────────────────────────────────
if (isset($_GET['stripe_cancel'])) {
    flash('error', 'Payment was cancelled. Your cart is still saved.');
    redirect(url('checkout.php'));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_redirect(url('checkout.php'));

    $deliveryMethod  = $_POST['delivery_method'] ?? '';
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $paymentMethod   = $_POST['payment_method'] ?? '';

    if (!in_array($deliveryMethod, ['pickup', 'courier'], true)) {
        $errors[] = 'Please choose a delivery method.';
    }

    if ($deliveryMethod === 'courier' && $deliveryAddress === '') {
        $errors[] = 'Please provide a delivery address.';
    }

    if (!in_array($paymentMethod, ['stripe', 'cash_on_collection'], true)) {
        $errors[] = 'Please choose a payment method.';
    }

    if ($errors === []) {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $user             = current_user();
            $orderReference   = generate_order_reference();
            $paymentStatus    = $paymentMethod === 'cash_on_collection' ? 'pending' : 'pending';

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
                'buyer_id'         => $user['id'],
                'order_reference'  => $orderReference,
                'subtotal'         => $totals['subtotal'],
                'delivery_fee'     => $totals['delivery'],
                'total_amount'     => $totals['total'],
                'delivery_method'  => $deliveryMethod,
                'delivery_address' => $deliveryAddress,
                'payment_method'   => $paymentMethod,
                'payment_status'   => $paymentStatus,
                'order_status'     => 'processing',
            ]);

            $orderId   = (int) $pdo->lastInsertId();
            $itemStmt  = $pdo->prepare(
                'INSERT INTO order_items (order_id, listing_id, seller_id, quantity, unit_price, item_title, item_image_url, seller_name)
                 VALUES (:order_id, :listing_id, :seller_id, :quantity, :unit_price, :item_title, :item_image_url, :seller_name)'
            );
            $stockStmt = $pdo->prepare(
                'UPDATE listings
                 SET stock_quantity = stock_quantity - :qty_deduct,
                     status = CASE WHEN stock_quantity - :qty_status <= 0 THEN "sold" ELSE status END
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
                    'order_id'      => $orderId,
                    'listing_id'    => $row['id'],
                    'seller_id'     => $listing['user_id'],
                    'quantity'      => $row['quantity'],
                    'unit_price'    => $row['price'],
                    'item_title'    => $row['title'],
                    'item_image_url'=> $listing['image_url'],
                    'seller_name'   => $listing['seller_name'],
                ]);

                $stockStmt->execute([
                    'qty_deduct' => $row['quantity'],
                    'qty_status' => $row['quantity'],
                    'id'         => $row['id'],
                ]);
            }

            $paymentStmt->execute([
                'order_id'              => $orderId,
                'amount'                => $totals['total'],
                'payment_method'        => $paymentMethod,
                'gateway_name'          => $paymentMethod === 'stripe' ? 'Stripe' : 'Cash On Collection',
                'transaction_reference' => $orderReference . '-PAY',
                'status'                => $paymentStatus,
                'paid_at'               => null,
            ]);

            $historyStmt->execute([
                'order_id'           => $orderId,
                'status'             => 'processing',
                'note'               => $deliveryMethod === 'pickup'
                    ? 'Order placed and awaiting seller confirmation for collection.'
                    : 'Order placed and queued for courier coordination.',
                'changed_by_user_id' => $user['id'],
            ]);

            $pdo->commit();

            // ── Redirect to Stripe Checkout ────────────────────────────
            if ($paymentMethod === 'stripe') {
                $stripeKey = getenv('STRIPE_SECRET_KEY') ?: '';

                if ($stripeKey === '') {
                    flash('error', 'Stripe is not configured. Please contact support.');
                    redirect(url('orders.php'));
                }

                $baseUrl     = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                $successUrl  = $baseUrl . '/orders.php?stripe_success=1&order_ref=' . urlencode($orderReference);
                $cancelUrl   = $baseUrl . '/checkout.php?stripe_cancel=1';

                $lineItems = [];
                foreach ($totals['rows'] as $row) {
                    $lineItems[] = [
                        'price_data' => [
                            'currency'     => 'zar',
                            'product_data' => ['name' => $row['title']],
                            'unit_amount'  => (int) round((float) $row['price'] * 100),
                        ],
                        'quantity' => (int) $row['quantity'],
                    ];
                }

                if ($totals['delivery'] > 0) {
                    $lineItems[] = [
                        'price_data' => [
                            'currency'     => 'zar',
                            'product_data' => ['name' => 'Delivery fee'],
                            'unit_amount'  => (int) round((float) $totals['delivery'] * 100),
                        ],
                        'quantity' => 1,
                    ];
                }

                $payload = json_encode([
                    'payment_method_types' => ['card'],
                    'line_items'           => $lineItems,
                    'mode'                 => 'payment',
                    'success_url'          => $successUrl,
                    'cancel_url'           => $cancelUrl,
                    'client_reference_id'  => $orderReference,
                ]);

                $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_HTTPHEADER     => [
                        'Authorization: Bearer ' . $stripeKey,
                        'Content-Type: application/json',
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
                    new RuntimeException('Stripe session creation failed: ' . ($response ?: 'no response')),
                    'stripe_checkout'
                );
                flash('error', 'Could not connect to payment gateway. Your order is saved — please contact support with reference: ' . $orderReference);
                redirect(url('orders.php'));
            }

            // ── Cash on collection ─────────────────────────────────────
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
                        <option value="stripe">Pay by card (Stripe)</option>
                        <option value="cash_on_collection">Cash on collection</option>
                    </select>
                </label>
                <div class="stripe-note">
                    <small>Card payments are processed securely by Stripe. You will be redirected to complete payment.</small>
                </div>
                <button class="button" type="submit">Place order</button>
            </form>
        </div>

        <aside class="summary-card">
            <h2>Order summary</h2>
            <?php foreach ($totals['rows'] as $row): ?>
                <div class="summary-line">
                    <span><?= e($row['title']) ?> x <?= e((string) $row['quantity']) ?></span>
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
<?php require __DIR__ . '/partials/footer.php'; ?>
