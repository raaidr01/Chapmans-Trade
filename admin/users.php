<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin_access();
require_permission('users.view', url('admin/login.php'));

$pageTitle = 'Manage Users';
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$roles = get_roles_catalog();
$currentAdmin = current_user();

$sql = 'SELECT
            u.*,
            GROUP_CONCAT(DISTINCT r.name ORDER BY r.sort_order SEPARATOR ", ") AS role_names,
            GROUP_CONCAT(DISTINCT r.slug ORDER BY r.sort_order SEPARATOR ",") AS role_slugs
        FROM users u
        LEFT JOIN user_roles ur ON ur.user_id = u.id
        LEFT JOIN roles r ON r.id = ur.role_id
        WHERE 1 = 1';
$params = [];

if ($search !== '') {
    $sql .= ' AND (u.full_name LIKE :search OR u.email LIKE :search OR u.township LIKE :search)';
    $params['search'] = '%' . $search . '%';
}

if ($statusFilter !== '') {
    $sql .= ' AND u.account_status = :account_status';
    $params['account_status'] = $statusFilter;
}

if ($roleFilter !== '') {
    $sql .= ' AND EXISTS (
                SELECT 1
                FROM user_roles ur_filter
                INNER JOIN roles r_filter ON r_filter.id = ur_filter.role_id
                WHERE ur_filter.user_id = u.id AND r_filter.slug = :role_slug
             )';
    $params['role_slug'] = $roleFilter;
}

$sql .= ' GROUP BY u.id ORDER BY u.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

require __DIR__ . '/_header.php';
?>
<section class="section">
    <div class="container admin-shell">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Users</span>
                <h1>Manage marketplace accounts</h1>
            </div>
            <?php if (user_has_permission('users.create')): ?>
                <a class="button" href="<?= e(url('admin/user_form.php')) ?>">Create user</a>
            <?php endif; ?>
        </div>

        <form class="filter-bar admin-toolbar" method="get">
            <input type="search" name="search" placeholder="Search by name, email, or township..." value="<?= e($search) ?>">
            <select name="role">
                <option value="">All roles</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= e($role['slug']) ?>" <?= $roleFilter === $role['slug'] ? 'selected' : '' ?>>
                        <?= e($role['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="status">
                <option value="">All statuses</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
            </select>
            <button class="button" type="submit">Filter</button>
        </form>

        <div class="panel admin-panel">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Roles</th>
                            <th>Verification</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php $roleSlugs = $user['role_slugs'] !== null ? array_filter(explode(',', (string) $user['role_slugs'])) : []; ?>
                            <tr>
                                <td>
                                    <strong><?= e($user['full_name']) ?></strong>
                                    <div class="table-subtext">
                                        <span><?= e($user['email']) ?></span>
                                        <span><?= e($user['township']) ?></span>
                                    </div>
                                </td>
                                <td><?= e($user['role_names'] ?: 'No roles assigned') ?></td>
                                <td><?= e(ucfirst((string) $user['verification_status'])) ?></td>
                                <td><span class="status-pill status-pill-inline"><?= e(ucfirst((string) $user['account_status'])) ?></span></td>
                                <td><?= e(date('d M Y', strtotime((string) $user['created_at']))) ?></td>
                                <td>
                                    <div class="table-actions">
                                        <?php if (user_has_permission('users.edit') && actor_can_manage_role_slugs($roleSlugs, $currentAdmin)): ?>
                                            <a href="<?= e(url('admin/user_form.php?id=' . (string) $user['id'])) ?>">Edit</a>
                                        <?php endif; ?>
                                        <?php if (user_has_permission('users.delete') && (int) $user['id'] !== (int) $currentAdmin['id'] && actor_can_manage_role_slugs($roleSlugs, $currentAdmin)): ?>
                                            <form method="post" action="<?= e(url('admin/user_delete.php')) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                                                <button class="nav-link-button danger-link" type="submit" data-confirm="Delete this user permanently?">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($users === []): ?>
                <div class="empty-state">
                    <h2>No users matched your filters.</h2>
                    <p>Try broadening the search or changing the role filter.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
