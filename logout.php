<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Invalid sign-out request.');
    redirect(url('index.php'));
}

verify_csrf_or_redirect(url('index.php'));
logout_user();
flash('success', 'You have been signed out.');
redirect(url('index.php'));
