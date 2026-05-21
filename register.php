<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(url('index.php'));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_redirect(url('register.php'));

    $data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email' => normalize_email($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'township' => trim($_POST['township'] ?? ''),
        'language_pref' => trim($_POST['language_pref'] ?? 'English'),
    ];
    $password = $_POST['password'] ?? '';

    remember_input($data);

    foreach ($data as $value) {
        if ($value === '') {
            $errors[] = 'Please complete all fields.';
            break;
        }
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, and a number.';
    }

    $existing = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $existing->execute(['email' => $data['email']]);
    if ($existing->fetch()) {
        $errors[] = 'That email address is already registered.';
    }

    if ($errors === []) {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (full_name, email, phone, township, language_pref, password_hash, verification_status)
                 VALUES (:full_name, :email, :phone, :township, :language_pref, :password_hash, :verification_status)'
            );
            $stmt->execute([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'township' => $data['township'],
                'language_pref' => $data['language_pref'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'verification_status' => 'pending',
            ]);

            $userId = (int) $pdo->lastInsertId();

            $profileStmt = $pdo->prepare(
                'INSERT INTO seller_profiles (user_id, display_name, collection_area)
                 VALUES (:user_id, :display_name, :collection_area)'
            );
            $profileStmt->execute([
                'user_id' => $userId,
                'display_name' => $data['full_name'],
                'collection_area' => $data['township'],
            ]);

            sync_user_roles($userId, ['buyer', 'seller'], $userId);

            $userStmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
            $userStmt->execute(['id' => $userId]);
            $user = $userStmt->fetch();

            $pdo->commit();
            clear_old_input();
            login_user($user);
            flash('success', 'Your account is ready. You can now shop or add listings.');
            redirect(url('index.php'));
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            log_application_exception($exception, 'register');
            $errors[] = 'We could not create your account right now. Please try again.';
        }
    }
}

$pageTitle = 'Register';
require __DIR__ . '/partials/header.php';
?>
<section class="section auth-section">
    <div class="container auth-card auth-card-wide">
        <span class="eyebrow">Register</span>
        <h1>Create your buyer and seller account</h1>
        <?php if ($errors !== []): ?>
            <div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div>
        <?php endif; ?>
        <form class="stack-form two-column" method="post">
            <?= csrf_field() ?>
            <label>
                Full name
                <input type="text" name="full_name" value="<?= e(old('full_name')) ?>" required>
            </label>
            <label>
                Email address
                <input type="email" name="email" value="<?= e(old('email')) ?>" required>
            </label>
            <label>
                Phone number
                <input type="text" name="phone" value="<?= e(old('phone')) ?>" required>
            </label>
            <label>
                Township or area
                <input type="text" name="township" value="<?= e(old('township')) ?>" required>
            </label>
            <label>
                Preferred language
                <select name="language_pref" required>
                    <option value="English" <?= old('language_pref', 'English') === 'English' ? 'selected' : '' ?>>English</option>
                    <option value="isiXhosa" <?= old('language_pref') === 'isiXhosa' ? 'selected' : '' ?>>isiXhosa</option>
                    <option value="Afrikaans" <?= old('language_pref') === 'Afrikaans' ? 'selected' : '' ?>>Afrikaans</option>
                    <option value="isiZulu" <?= old('language_pref') === 'isiZulu' ? 'selected' : '' ?>>isiZulu</option>
                </select>
            </label>
            <label>
                Password
                <input type="password" name="password" required>
            </label>
            <button class="button" type="submit">Create account</button>
        </form>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
