<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    if (is_admin_user()) {
        redirect(url('admin/dashboard.php'));
    }

    flash('error', 'Your current account does not have access to the admin portal.');
    redirect(url('index.php'));
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_redirect(url('admin/login.php'));

    $email = normalize_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    } elseif (login_is_rate_limited($email)) {
        $minutes = max(1, (int) ceil(login_lockout_remaining($email) / 60));
        $errors[] = 'Too many login attempts. Please wait ' . $minutes . ' minute(s) before trying again.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash']) || ($user['account_status'] ?? 'active') !== 'active') {
            record_failed_login_attempt($email);
            $errors[] = 'Invalid login details.';
        } else {
            clear_failed_login_attempts($email);
            login_user($user);

            if (!is_admin_user(current_user())) {
                logout_user();
                $errors[] = 'Your account is valid, but it is not assigned to the admin portal.';
            } else {
                flash('success', 'Welcome to the admin portal.');
                redirect(url('admin/dashboard.php'));
            }
        }
    }
}

$pageTitle = 'Admin Login';
require __DIR__ . '/_header.php';
?>
<section class="section auth-section">
    <div class="container auth-card">
        <span class="eyebrow">Admin access</span>
        <h1>Sign in to the management portal</h1>
        <p class="admin-intro-copy">Use your staff account to manage users, roles, listings, orders, and seller verification records.</p>
        <?php if ($errors !== []): ?>
            <div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div>
        <?php endif; ?>
        <form class="stack-form" method="post">
            <?= csrf_field() ?>
            <label>
                Email address
                <input type="email" name="email" value="<?= e($email) ?>" required>
            </label>
            <label>
                Password
                <input type="password" name="password" required>
            </label>
            <button class="button" type="submit">Enter admin portal</button>
        </form>
        <div class="admin-note-card">
            <strong>Seeded staff accounts</strong>
            <p>Use `superadmin@chapmanstrade.test`, `admin@chapmanstrade.test`, or `moderator@chapmanstrade.test` with the demo password `Password123` after importing the updated schema.</p>
        </div>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
