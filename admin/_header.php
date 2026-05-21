<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$pageTitle = $pageTitle ?? 'Admin Portal';
$adminCurrentUser = current_user();
$adminRoleNames = [];

if ($adminCurrentUser !== null) {
    foreach (($adminCurrentUser['roles'] ?? []) as $role) {
        if ((int) ($role['admin_area_access'] ?? 0) === 1) {
            $adminRoleNames[] = (string) $role['name'];
        }
    }
}

$adminNavItems = [
    ['label' => 'Dashboard', 'path' => 'admin/dashboard.php', 'permission' => 'admin.dashboard.view'],
    ['label' => 'Users', 'path' => 'admin/users.php', 'permission' => 'users.view'],
    ['label' => 'Roles', 'path' => 'admin/roles.php', 'permission' => 'roles.view'],
    ['label' => 'Listings', 'path' => 'admin/listings.php', 'permission' => 'listings.view'],
    ['label' => 'Orders', 'path' => 'admin/orders.php', 'permission' => 'orders.view'],
    ['label' => 'Verifications', 'path' => 'admin/verifications.php', 'permission' => 'verifications.view'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/styles.css')) ?>">
</head>
<body class="admin-body">
    <header class="site-header admin-header">
        <div class="container nav-shell">
            <a class="brand" href="<?= e(url('admin/dashboard.php')) ?>">
                <span class="brand-mark">CT</span>
                <span>
                    <strong><?= e(APP_NAME) ?> Admin</strong>
                    <small>RBAC management portal</small>
                </span>
            </a>

            <button class="nav-toggle" type="button" data-nav-toggle aria-label="Toggle navigation">Menu</button>

            <nav class="site-nav" data-nav>
                <?php if ($adminCurrentUser !== null): ?>
                    <?php foreach ($adminNavItems as $item): ?>
                        <?php if (user_has_permission($item['permission'], $adminCurrentUser)): ?>
                            <a href="<?= e(url($item['path'])) ?>"><?= e($item['label']) ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <a href="<?= e(url('index.php')) ?>">Marketplace</a>
                <?php if ($adminCurrentUser !== null): ?>
                    <span class="nav-user">
                        <?= e($adminCurrentUser['full_name']) ?>
                        <?php if ($adminRoleNames !== []): ?>
                            (<?= e(implode(', ', $adminRoleNames)) ?>)
                        <?php endif; ?>
                    </span>
                    <form class="nav-inline-form" method="post" action="<?= e(url('logout.php')) ?>">
                        <?= csrf_field() ?>
                        <button class="nav-link-button" type="submit">Logout</button>
                    </form>
                <?php else: ?>
                    <a href="<?= e(url('login.php')) ?>">Customer login</a>
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
