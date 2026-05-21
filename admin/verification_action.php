<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin_access();
require_permission('verifications.review', url('admin/login.php'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('admin/verifications.php'));
}

verify_csrf_or_redirect(url('admin/verifications.php'));

$verificationId = (int) ($_POST['verification_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$reviewerNotes = trim($_POST['reviewer_notes'] ?? '');
$allowedStatuses = ['pending', 'approved', 'rejected'];
$verifiedAt = in_array($status, ['approved', 'rejected'], true) ? date('Y-m-d H:i:s') : null;

if ($verificationId <= 0 || !in_array($status, $allowedStatuses, true)) {
    flash('error', 'Invalid verification review request.');
    redirect(url('admin/verifications.php'));
}

$stmt = db()->prepare(
    'UPDATE seller_verifications
     SET status = :status,
         reviewer_notes = :reviewer_notes,
         verified_at = :verified_at
     WHERE id = :id'
);
$stmt->execute([
    'status' => $status,
    'reviewer_notes' => $reviewerNotes !== '' ? $reviewerNotes : null,
    'verified_at' => $verifiedAt,
    'id' => $verificationId,
]);

flash('success', 'Verification review updated.');
redirect(url('admin/verifications.php'));
