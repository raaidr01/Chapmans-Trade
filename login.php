<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(url('index.php'));
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_redirect(url('login.php'));

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
            flash('success', 'Welcome back to Chapmans Trade.');
            redirect(url('index.php'));
        }
    }
}

$pageTitle = 'Login';
require __DIR__ . '/partials/header.php';
?>
<section class="section auth-section">
    <div class="container auth-card">
        <span class="eyebrow">Login</span>
        <h1>Sign in to your account</h1>
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
            <button class="button" type="submit">Login</button>
        </form>
        <p>New here? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
