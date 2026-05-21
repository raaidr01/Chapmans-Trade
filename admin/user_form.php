<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_admin_access();

$currentAdmin = current_user();
$userId = (int) ($_GET['id'] ?? 0);
$editing = $userId > 0;

if ($editing) {
    require_permission('users.edit', url('admin/login.php'));
} else {
    require_permission('users.create', url('admin/login.php'));
}

$pageTitle = $editing ? 'Edit User' : 'Create User';
$errors = [];
$roles = array_values(array_filter(
    get_roles_catalog(),
    static fn (array $role): bool => actor_can_manage_role_slug((string) $role['slug'])
));

$form = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'township' => '',
    'language_pref' => 'English',
    'verification_status' => 'pending',
    'account_status' => 'active',
];
$selectedRoles = ['buyer', 'seller'];
$existingRoleSlugs = $selectedRoles;

if ($editing) {
    $stmt = db()->prepare(
        'SELECT u.*
         FROM users u
         WHERE u.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $userId]);
    $existingUser = $stmt->fetch();

    if (!$existingUser) {
        flash('error', 'User not found.');
        redirect(url('admin/users.php'));
    }

    $existingRoleSlugs = get_user_role_slugs($userId);

    if (!actor_can_manage_role_slugs($existingRoleSlugs, $currentAdmin)) {
        flash('error', 'You are not allowed to manage that user.');
        redirect(url('admin/users.php'));
    }

    $form = [
        'full_name' => (string) $existingUser['full_name'],
        'email' => (string) $existingUser['email'],
        'phone' => (string) $existingUser['phone'],
        'township' => (string) $existingUser['township'],
        'language_pref' => (string) $existingUser['language_pref'],
        'verification_status' => (string) $existingUser['verification_status'],
        'account_status' => (string) $existingUser['account_status'],
    ];
    $selectedRoles = $existingRoleSlugs;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_redirect($editing ? url('admin/user_form.php?id=' . (string) $userId) : url('admin/user_form.php'));

    $form = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email' => normalize_email($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'township' => trim($_POST['township'] ?? ''),
        'language_pref' => trim($_POST['language_pref'] ?? 'English'),
        'verification_status' => trim($_POST['verification_status'] ?? 'pending'),
        'account_status' => trim($_POST['account_status'] ?? 'active'),
    ];
    $password = $_POST['password'] ?? '';
    $selectedRoles = array_values(array_unique(array_map(
        static fn ($role): string => trim((string) $role),
        $_POST['roles'] ?? []
    )));

    if (!is_super_admin($currentAdmin)) {
        $selectedRoles = array_values(array_filter(
            $selectedRoles,
            static fn (string $role): bool => $role !== 'super_admin'
        ));
    }

    foreach ($form as $key => $value) {
        if ($value === '') {
            $errors[] = 'Please complete all required user fields.';
            break;
        }
    }

    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if ($editing) {
        if ($password !== '' && (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password))) {
            $errors[] = 'Password resets must be at least 8 characters and include uppercase, lowercase, and a number.';
        }
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, and a number.';
    }

    if ($selectedRoles === []) {
        $errors[] = 'Please assign at least one role.';
    }

    if (!actor_can_manage_role_slugs($selectedRoles, $currentAdmin)) {
        $errors[] = 'You selected a role that your account is not allowed to assign.';
    }

    if ($editing && $userId === (int) $currentAdmin['id'] && in_array('super_admin', $existingRoleSlugs, true) && !in_array('super_admin', $selectedRoles, true)) {
        $errors[] = 'You cannot remove the Super Admin role from your own account.';
    }

    $uniqueStmt = db()->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
    $uniqueStmt->execute([
        'email' => $form['email'],
        'id' => $editing ? $userId : 0,
    ]);
    if ($uniqueStmt->fetch()) {
        $errors[] = 'That email address is already registered.';
    }

    if ($errors === []) {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            if ($editing) {
                $params = [
                    'full_name' => $form['full_name'],
                    'email' => $form['email'],
                    'phone' => $form['phone'],
                    'township' => $form['township'],
                    'language_pref' => $form['language_pref'],
                    'verification_status' => $form['verification_status'],
                    'account_status' => $form['account_status'],
                    'id' => $userId,
                ];

                $sql = 'UPDATE users
                        SET full_name = :full_name,
                            email = :email,
                            phone = :phone,
                            township = :township,
                            language_pref = :language_pref,
                            verification_status = :verification_status,
                            account_status = :account_status';

                if ($password !== '') {
                    $sql .= ', password_hash = :password_hash';
                    $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }

                $sql .= ' WHERE id = :id';

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (full_name, email, phone, township, language_pref, password_hash, verification_status, account_status)
                     VALUES (:full_name, :email, :phone, :township, :language_pref, :password_hash, :verification_status, :account_status)'
                );
                $stmt->execute([
                    'full_name' => $form['full_name'],
                    'email' => $form['email'],
                    'phone' => $form['phone'],
                    'township' => $form['township'],
                    'language_pref' => $form['language_pref'],
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'verification_status' => $form['verification_status'],
                    'account_status' => $form['account_status'],
                ]);
                $userId = (int) $pdo->lastInsertId();
            }

            sync_user_roles($userId, $selectedRoles, (int) $currentAdmin['id']);

            if (in_array('seller', $selectedRoles, true)) {
                ensure_seller_profile($userId, $form['full_name'], $form['township']);
            }

            $pdo->commit();
            flash('success', $editing ? 'User updated successfully.' : 'User created successfully.');
            redirect(url('admin/users.php'));
        } catch (Throwable $exception) {
            try {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (Throwable $rollbackException) {
                log_application_exception($rollbackException, 'admin_user_form_rollback');
            }

            log_application_exception($exception, 'admin_user_form');
            $errors[] = 'We could not save the user right now. Please try again.';
        }
    }
}

require __DIR__ . '/_header.php';
?>
<section class="section">
    <div class="container admin-shell">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Users</span>
                <h1><?= $editing ? 'Edit user account' : 'Create a new user account' ?></h1>
            </div>
        </div>

        <div class="panel admin-panel">
            <?php if ($errors !== []): ?>
                <div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div>
            <?php endif; ?>

            <form class="stack-form two-column" method="post">
                <?= csrf_field() ?>
                <label>
                    Full name
                    <input type="text" name="full_name" value="<?= e($form['full_name']) ?>" required>
                </label>
                <label>
                    Email address
                    <input type="email" name="email" value="<?= e($form['email']) ?>" required>
                </label>
                <label>
                    Phone number
                    <input type="text" name="phone" value="<?= e($form['phone']) ?>" required>
                </label>
                <label>
                    Township or area
                    <input type="text" name="township" value="<?= e($form['township']) ?>" required>
                </label>
                <label>
                    Preferred language
                    <select name="language_pref" required>
                        <?php foreach (['English', 'isiXhosa', 'Afrikaans', 'isiZulu'] as $language): ?>
                            <option value="<?= e($language) ?>" <?= $form['language_pref'] === $language ? 'selected' : '' ?>>
                                <?= e($language) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <?= $editing ? 'Reset password (optional)' : 'Password' ?>
                    <input type="password" name="password" <?= $editing ? '' : 'required' ?>>
                </label>
                <label>
                    Verification status
                    <select name="verification_status" required>
                        <?php foreach (['pending', 'verified', 'rejected'] as $status): ?>
                            <option value="<?= e($status) ?>" <?= $form['verification_status'] === $status ? 'selected' : '' ?>>
                                <?= e(ucfirst($status)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Account status
                    <select name="account_status" required>
                        <?php foreach (['active', 'suspended'] as $status): ?>
                            <option value="<?= e($status) ?>" <?= $form['account_status'] === $status ? 'selected' : '' ?>>
                                <?= e(ucfirst($status)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <fieldset class="full-span admin-fieldset">
                    <legend>Assigned roles</legend>
                    <div class="checkbox-grid">
                        <?php foreach ($roles as $role): ?>
                            <label class="checkbox-card">
                                <input type="checkbox" name="roles[]" value="<?= e($role['slug']) ?>" <?= in_array($role['slug'], $selectedRoles, true) ? 'checked' : '' ?>>
                                <span>
                                    <strong><?= e($role['name']) ?></strong>
                                    <small><?= e($role['description'] ?? '') ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="full-span form-actions">
                    <button class="button" type="submit"><?= $editing ? 'Update user' : 'Create user' ?></button>
                    <a class="button-secondary" href="<?= e(url('admin/users.php')) ?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
