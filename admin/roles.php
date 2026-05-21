<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin_access();
require_permission('roles.view', url('admin/login.php'));

$pageTitle = 'Role Matrix';
$roles = db()->query(
    'SELECT
        r.id,
        r.name,
        r.slug,
        r.description,
        r.admin_area_access,
        r.sort_order,
        COUNT(DISTINCT ur.user_id) AS users_count,
        GROUP_CONCAT(DISTINCT p.name ORDER BY p.module, p.name SEPARATOR ", ") AS permission_names
     FROM roles r
     LEFT JOIN user_roles ur ON ur.role_id = r.id
     LEFT JOIN role_permissions rp ON rp.role_id = r.id
     LEFT JOIN permissions p ON p.id = rp.permission_id
     GROUP BY r.id
     ORDER BY r.admin_area_access ASC, r.sort_order ASC, r.name ASC'
)->fetchAll();

require __DIR__ . '/_header.php';
?>
<section class="section">
    <div class="container admin-shell">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Roles and permissions</span>
                <h1>RBAC access matrix</h1>
            </div>
        </div>

        <div class="card-grid admin-role-grid">
            <?php foreach ($roles as $role): ?>
                <article class="panel admin-role-card">
                    <div class="listing-topline">
                        <span class="tag"><?= e($role['admin_area_access'] ? 'Admin portal' : 'Marketplace') ?></span>
                        <span class="status-pill status-pill-inline"><?= e((string) $role['users_count']) ?> users</span>
                    </div>
                    <h2><?= e($role['name']) ?></h2>
                    <p><?= e($role['description'] ?? 'No description provided.') ?></p>
                    <div class="role-permission-list">
                        <?php foreach (explode(', ', (string) ($role['permission_names'] ?? '')) as $permissionName): ?>
                            <?php if ($permissionName !== ''): ?>
                                <span class="status-pill status-pill-inline"><?= e($permissionName) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
