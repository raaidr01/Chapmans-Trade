<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin_access();
require_permission('users.delete', url('admin/login.php'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('admin/users.php'));
}

verify_csrf_or_redirect(url('admin/users.php'));

$currentAdmin = current_user();
$userId = (int) ($_POST['user_id'] ?? 0);

if ($userId <= 0) {
    flash('error', 'Invalid user selection.');
    redirect(url('admin/users.php'));
}

if ($userId === (int) $currentAdmin['id']) {
    flash('error', 'You cannot delete your own admin account.');
    redirect(url('admin/users.php'));
}

$targetUserStmt = db()->prepare('SELECT id, full_name FROM users WHERE id = :id LIMIT 1');
$targetUserStmt->execute(['id' => $userId]);
$targetUser = $targetUserStmt->fetch();

if (!$targetUser) {
    flash('error', 'User not found.');
    redirect(url('admin/users.php'));
}

$targetRoleSlugs = get_user_role_slugs($userId);
if (!actor_can_manage_role_slugs($targetRoleSlugs, $currentAdmin)) {
    flash('error', 'You are not allowed to delete that user.');
    redirect(url('admin/users.php'));
}

try {
    $stmt = db()->prepare('DELETE FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    flash('success', 'User deleted successfully.');
} catch (Throwable $exception) {
    log_application_exception($exception, 'admin_user_delete');
    flash('error', 'This user could not be deleted because the account is linked to marketplace records. Suspend the account instead.');
}

redirect(url('admin/users.php'));
