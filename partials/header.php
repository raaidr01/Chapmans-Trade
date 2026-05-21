<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$pageTitle = $pageTitle ?? APP_NAME;
$currentUser = current_user();
$categories = $categories ?? get_categories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/styles.css')) ?>">
</head>
<body>
    <header class="site-header">
        <div class="container nav-shell">
            <a class="brand" href="<?= e(url('index.php')) ?>">
                <span class="brand-mark">CT</span>
                <span>
                    <strong><?= e(APP_NAME) ?></strong>
                    <small>Township-first C2C marketplace</small>
                </span>
            </a>

            <button class="nav-toggle" type="button" data-nav-toggle aria-label="Toggle navigation">Menu</button>

            <nav class="site-nav" data-nav>
                <a href="<?= e(url('catalog.php')) ?>">Browse</a>
                <a href="<?= e(url('seller/dashboard.php')) ?>">Sell</a>
                <a href="<?= e(url('orders.php')) ?>">Orders</a>
                <a href="<?= e(url('cart.php')) ?>">Cart (<?= cart_count() ?>)</a>
                <?php if ($currentUser): ?>
                    <?php if (is_admin_user($currentUser)): ?>
                        <a href="<?= e(url('admin/dashboard.php')) ?>">Admin</a>
                    <?php endif; ?>
                    <span class="nav-user">Hi, <?= e($currentUser['full_name']) ?></span>
                    <form class="nav-inline-form" method="post" action="<?= e(url('logout.php')) ?>">
                        <?= csrf_field() ?>
                        <button class="nav-link-button" type="submit">Logout</button>
                    </form>
                <?php else: ?>
                    <a href="<?= e(url('login.php')) ?>">Login</a>
                    <a class="nav-button" href="<?= e(url('register.php')) ?>">Join free</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main>
        <?php if ($message = flash('success')): ?>
            <div class="container">
                <div class="flash flash-success"><?= e($message) ?></div>
            </div>
        <?php endif; ?>
        <?php if ($message = flash('error')): ?>
            <div class="container">
                <div class="flash flash-error"><?= e($message) ?></div>
            </div>
        <?php endif; ?>
