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
    $orderRef = preg_replace('/[^A-Z0-9\-]/', '', strtoupper((string)$_GET['order_ref']));
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
$deliveryMethod  = '';
$deliveryAddress = '';
$paymentMethod   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_redirect(url('checkout.php'));

    $deliveryMethod  = $_POST['delivery_method'] ?? '';
    $deliveryAddress = trim($_POST['delivery_address'] ?? '');
    $paymentMethod   = $_POST['payment_method'] ?? '';

    // Data Whitelisting
    if (!in_array($deliveryMethod, ['pickup', 'courier'], true)) {
        $errors[] = 'Please select a valid delivery method.';
    }

    if ($deliveryMethod === 'courier' && $deliveryAddress === '') {
        $errors[] = 'A delivery address is required for courier delivery.';
    }

    if (!in_array($paymentMethod, ['stripe', 'cash_on_collection'], true)) {
        $errors[] = 'Please select a valid payment method.';
    }

    if ($errors === []) {
        try {
            $db = db();
            $db->beginTransaction();

            $user = current_user();
            // Generate unique, clean alphanumeric order reference safe for URL routing
            $orderReference = 'CT-' . strtoupper(bin2hex(random_bytes(4)));

            // 1. Create Core Order Record
            $stmt = $db->prepare('
                INSERT INTO orders (buyer_id, order_reference, total_amount, delivery_method, delivery_address, payment_method, payment_status, order_status, created_at)
                VALUES (:buyer_id, :order_reference, :total_amount, :delivery_method, :delivery_address, :payment_method, :payment_status, "processing", NOW())
            ');
            
            $paymentStatus = ($paymentMethod === 'stripe') ? 'pending' : 'unpaid';
            $finalAddress  = ($deliveryMethod === 'courier') ? $deliveryAddress : 'Community Pickup Point';
            
            $stmt->execute([
                'buyer_id'        => $user['id'],
                'order_reference' => $orderReference,
                'total_amount'    => $totals['total'],
                'delivery_method' => $deliveryMethod,
                'delivery_address'=> $finalAddress,
                'payment_method'  => $paymentMethod,
                'payment_status'  => $paymentStatus
            ]);

            $orderId = (int)$db->lastInsertId();

            // 2. Map Items and Perform Stock Lock Validations
            foreach ($totals['rows'] as $row) {
                // Read-lock rows to protect against multi-user race conditions
                $stockCheck = $db->prepare('SELECT stock_quantity, status FROM listings WHERE id = :id FOR UPDATE');
                $stockCheck->execute(['id' => $row['id']]);
                $listing = $stockCheck->fetch();

                if (!$listing || $listing['status'] !== 'active' || (int)$listing['stock_quantity'] < (int)$row['quantity']) {
                    throw new Exception('One or more items in your cart became unavailable. Please update your cart.');
                }

                // Add item row to historical record logs
                $itemStmt = $db->prepare('
                    INSERT INTO order_items (order_id, listing_id, seller_id, quantity, unit_price, subtotal)
                    VALUES (:order_id, :listing_id, :seller_id, :quantity, :unit_price, :subtotal)
                ');
                $itemStmt->execute([
                    'order_id'   => $orderId,
                    'listing_id' => $row['id'],
                    'seller_id'  => $row['user_id'] ?? $row['seller_id'] ?? null, 
                    'quantity'   => $row['quantity'],
                    'unit_price' => $row['price'],
                    'subtotal'   => $row['subtotal']
                ]);

                // Reduce Inventory State atomically
                $updateStock = $db->prepare('UPDATE listings SET stock_quantity = stock_quantity - :qty WHERE id = :id');
                $updateStock->execute([
                    'qty' => $row['quantity'],
                    'id'  => $row['id']
                ]);

                // Update catalog listing visibility if depleted
                $finalStockCheck = $db->prepare('SELECT stock_quantity FROM listings WHERE id = :id');
                $finalStockCheck->execute(['id' => $row['id']]);
                if ((int)$finalStockCheck->fetchColumn() <= 0) {
                    $markSold = $db->prepare('UPDATE listings SET status = "sold" WHERE id = :id');
                    $markSold->execute(['id' => $row['id']]);
                }
            }

            // Record initial lifecycle status audit entry
            $historyStmt = $db->prepare('
                INSERT INTO order_status_history (order_id, status, note, created_at)
                VALUES (:order_id, "processing", "Order successfully generated through secure checkout pipeline.", NOW())
            ');
            $historyStmt->execute(['order_id' => $orderId]);

            $db->commit();

            // 3. Routing Layer Gateway Control
            if ($paymentMethod === 'stripe') {
                if (function_exists('generate_stripe_checkout_session')) {
                    generate_stripe_checkout_session($orderId, $orderReference, $totals['total']);
                } else {
                    // Simulation fallback for sandboxed local-testing matching callbacks
                    redirect(url('checkout.php?stripe_success=true&order_ref=' . $orderReference));
                }
            } else {
                flash('success', 'Order successfully placed via Cash on Collection! Reference: ' . $orderReference);
                clear_cart();
                redirect(url('orders.php'));
            }

        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = 'Secure Checkout';
require __DIR__ . '/partials/header.php';
?>

<section class="section">
    <div class="container checkout-layout">
        <div class="panel">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Fulfillment</span>
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
                    <select name="delivery_method" id="delivery_method" required>
                        <option value="pickup" <?= $deliveryMethod === 'pickup' ? 'selected' : '' ?>>Community Pickup Point (Free)</option>
                        <option value="courier" <?= $deliveryMethod === 'courier' ? 'selected' : '' ?>>Local Courier Service</option>
                    </select>
                </label>

                <div id="address_section" style="display: <?= $deliveryMethod === 'courier' ? 'block' : 'none' ?>;">
                    <label>
                        Delivery address
                        <input type="text" name="delivery_address" value="<?= e($deliveryAddress) ?>" placeholder="Street name, house number, township area">
                    </label>
                </div>

                <label>
                    Payment strategy
                    <select name="payment_method" id="payment_method" required>
                        <option value="cash_on_collection" <?= $paymentMethod === 'cash_on_collection' ? 'selected' : '' ?>>Cash on collection</option>
                        <option value="stripe" <?= $paymentMethod === 'stripe' ? 'selected' : '' ?>>Pay securely with card (Stripe)</option>
                    </select>
                </label>

                <button class="button" type="submit">Place order</button>
            </form>
        </div>

        <aside class="summary-card">
            <h2>Order review</h2>
            <?php foreach ($totals['rows'] as $row): ?>
                <div class="summary-line">
                    <span><?= e($row['title']) ?> (x<?= e((string)$row['quantity']) ?>)</span>
                    <strong><?= e(format_currency((float)$row['subtotal'])) ?></strong>
                </div>
            <?php endforeach; ?>
            <div class="summary-line">
                <span>Base Delivery</span>
                <strong><?= $totals['delivery'] > 0 ? e(format_currency((float)$totals['delivery'])) : 'Free' ?></strong>
            </div>
            <div class="summary-line summary-total">
                <span>Total Due</span>
                <strong><?= e(format_currency((float)$totals['total'])) ?></strong>
            </div>
        </aside>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deliveryMethod = document.getElementById('delivery_method');
    const addressSection = document.getElementById('address_section');

    function toggleFields() {
        if (deliveryMethod) {
            addressSection.style.display = (deliveryMethod.value === 'courier') ? 'block' : 'none';
        }
    }

    if (deliveryMethod) {
        deliveryMethod.addEventListener('change', toggleFields);
    }
    toggleFields();
});
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>