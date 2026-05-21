<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function current_user(bool $refresh = false): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;
    static $cachedUserId = null;
    $sessionUserId = (int) $_SESSION['user_id'];

    if (!$refresh && $user !== null && $cachedUserId === $sessionUserId) {
        return $user;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $sessionUserId]);
    $user = $stmt->fetch() ?: null;

    if ($user !== null) {
        $user['roles'] = get_user_role_rows((int) $user['id']);
        $user['role_slugs'] = get_user_role_slugs((int) $user['id']);
        $user['permission_slugs'] = get_user_permission_slugs((int) $user['id']);
        $cachedUserId = (int) $user['id'];
    }

    return $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function user_has_role(string $roleSlug, ?array $user = null): bool
{
    $user ??= current_user();

    if ($user === null) {
        return false;
    }

    $roleSlugs = $user['role_slugs'] ?? [];

    return in_array($roleSlug, $roleSlugs, true);
}

function user_has_any_role(array $roleSlugs, ?array $user = null): bool
{
    foreach ($roleSlugs as $roleSlug) {
        if (user_has_role((string) $roleSlug, $user)) {
            return true;
        }
    }

    return false;
}

function user_has_permission(string $permissionSlug, ?array $user = null): bool
{
    $user ??= current_user();

    if ($user === null) {
        return false;
    }

    $permissions = $user['permission_slugs'] ?? [];

    return in_array($permissionSlug, $permissions, true);
}

function is_admin_user(?array $user = null): bool
{
    return user_has_permission('admin.access', $user);
}

function is_super_admin(?array $user = null): bool
{
    return user_has_role('super_admin', $user);
}

function actor_can_manage_role_slug(string $roleSlug, ?array $actor = null): bool
{
    $actor ??= current_user();

    if ($actor === null) {
        return false;
    }

    if ($roleSlug === 'super_admin') {
        return is_super_admin($actor);
    }

    return user_has_permission('roles.assign', $actor) || is_super_admin($actor);
}

function actor_can_manage_role_slugs(array $roleSlugs, ?array $actor = null): bool
{
    foreach ($roleSlugs as $roleSlug) {
        if (!actor_can_manage_role_slug((string) $roleSlug, $actor)) {
            return false;
        }
    }

    return true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Please sign in to continue.');
        redirect(url('login.php'));
    }
}

function require_role(string $roleSlug, string $redirectPath = ''): void
{
    require_login();

    if (!user_has_role($roleSlug)) {
        flash('error', 'You do not have access to that page.');
        redirect($redirectPath !== '' ? $redirectPath : url('index.php'));
    }
}

function require_permission(string $permissionSlug, string $redirectPath = ''): void
{
    require_login();

    if (!user_has_permission($permissionSlug)) {
        flash('error', 'You do not have permission to perform that action.');
        redirect($redirectPath !== '' ? $redirectPath : url('index.php'));
    }
}

function require_admin_access(): void
{
    if (!is_logged_in()) {
        flash('error', 'Please sign in to access the admin portal.');
        redirect(url('admin/login.php'));
    }

    if (!is_admin_user()) {
        flash('error', 'Your account does not have admin access.');
        redirect(url('index.php'));
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    current_user(true);
}

function logout_user(): void
{
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
}
