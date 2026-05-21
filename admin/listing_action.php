<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin_access();
require_permission('listings.moderate', url('admin/login.php'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('admin/listings.php'));
}

verify_csrf_or_redirect(url('admin/listings.php'));

$listingId = (int) ($_POST['listing_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$allowedStatuses = ['draft', 'active', 'paused', 'sold'];

if ($listingId <= 0 || !in_array($status, $allowedStatuses, true)) {
    flash('error', 'Invalid listing moderation request.');
    redirect(url('admin/listings.php'));
}

$stmt = db()->prepare('UPDATE listings SET status = :status WHERE id = :id');
$stmt->execute([
    'status' => $status,
    'id' => $listingId,
]);

flash('success', 'Listing status updated.');
redirect(url('admin/listings.php'));
