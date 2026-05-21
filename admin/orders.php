<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin_access();
require_permission('orders.view', url('admin/login.php'));

$pageTitle = 'Orders Overview';
$orders = db()->query(
    'SELECT
        o.*,
        u.full_name AS buyer_name,
        COUNT(oi.id) AS items_count
     FROM orders o
     INNER JOIN users u ON u.id = o.buyer_id
     LEFT JOIN order_items oi ON oi.order_id = o.id
     GROUP BY o.id
     ORDER BY o.created_at DESC'
)->fetchAll();

require __DIR__ . '/_header.php';
?>
<section class="section">
    <div class="container admin-shell">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Orders</span>
                <h1>View all marketplace orders</h1>
            </div>
        </div>

        <div class="panel admin-panel">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Buyer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Delivery</th>
                            <th>Payment</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <strong><?= e($order['order_reference'] ?: ('Order #' . (string) $order['id'])) ?></strong>
                                    <div class="table-subtext">
                                        <span><?= e(date('d M Y', strtotime((string) $order['created_at']))) ?></span>
                                    </div>
                                </td>
                                <td><?= e($order['buyer_name']) ?></td>
                                <td><?= e((string) $order['items_count']) ?></td>
                                <td><?= e(format_currency((float) $order['total_amount'])) ?></td>
                                <td><?= e(ucfirst((string) $order['delivery_method'])) ?></td>
                                <td><?= e(ucfirst(str_replace('_', ' ', (string) $order['payment_status']))) ?></td>
                                <td><span class="status-pill status-pill-inline"><?= e(ucfirst(str_replace('_', ' ', (string) $order['order_status']))) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
