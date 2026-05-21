<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in() && is_admin_user()) {
    redirect(url('admin/dashboard.php'));
}

redirect(url('admin/login.php'));
