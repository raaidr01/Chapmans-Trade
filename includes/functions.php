<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_currency(float $amount): string
{
    return 'R' . number_format($amount, 2);
}

function excerpt(string $text, int $length = 100): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));

    if (strlen($text) <= $length) {
        return $text;
    }

    return substr($text, 0, $length - 3) . '...';
}

function url(string $path = ''): string
{
    $base = rtrim(BASE_URL, '/');
    $path = ltrim($path, '/');

    return $path === '' ? $base . '/' : $base . '/' . $path;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function get_roles_catalog(?bool $adminAreaOnly = null): array
{
    $sql = 'SELECT id, name, slug, description, admin_area_access, sort_order
            FROM roles';
    $params = [];

    if ($adminAreaOnly !== null) {
        $sql .= ' WHERE admin_area_access = :admin_area_access';
        $params['admin_area_access'] = $adminAreaOnly ? 1 : 0;
    }

    $sql .= ' ORDER BY admin_area_access ASC, sort_order ASC, name ASC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function get_user_role_rows(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT r.id, r.name, r.slug, r.description, r.admin_area_access, r.sort_order
         FROM roles r
         INNER JOIN user_roles ur ON ur.role_id = r.id
         WHERE ur.user_id = :user_id
         ORDER BY r.admin_area_access ASC, r.sort_order ASC, r.name ASC'
    );
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll();
}

function get_user_role_slugs(int $userId): array
{
    return array_map(
        static fn (array $role): string => (string) $role['slug'],
        get_user_role_rows($userId)
    );
}

function get_user_permission_slugs(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT DISTINCT p.slug
         FROM permissions p
         INNER JOIN role_permissions rp ON rp.permission_id = p.id
         INNER JOIN user_roles ur ON ur.role_id = rp.role_id
         WHERE ur.user_id = :user_id
         ORDER BY p.slug ASC'
    );
    $stmt->execute(['user_id' => $userId]);

    return array_map(
        static fn (array $permission): string => (string) $permission['slug'],
        $stmt->fetchAll()
    );
}

function sync_user_roles(int $userId, array $roleSlugs, ?int $assignedByUserId = null): void
{
    $normalizedSlugs = array_values(array_unique(array_filter(array_map(
        static fn ($slug): string => trim((string) $slug),
        $roleSlugs
    ))));

    if ($normalizedSlugs === []) {
        $normalizedSlugs = ['buyer'];
    }

    $placeholders = implode(',', array_fill(0, count($normalizedSlugs), '?'));
    $stmt = db()->prepare("SELECT id, slug FROM roles WHERE slug IN ($placeholders)");
    $stmt->execute($normalizedSlugs);
    $roles = $stmt->fetchAll();

    if ($roles === []) {
        throw new RuntimeException('No valid roles were selected.');
    }

    $roleIds = array_map(static fn (array $role): int => (int) $role['id'], $roles);

    $deleteStmt = db()->prepare('DELETE FROM user_roles WHERE user_id = :user_id');
    $deleteStmt->execute(['user_id' => $userId]);

    $insertStmt = db()->prepare(
        'INSERT INTO user_roles (user_id, role_id, assigned_by_user_id)
         VALUES (:user_id, :role_id, :assigned_by_user_id)'
    );

    foreach ($roleIds as $roleId) {
        $insertStmt->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
            'assigned_by_user_id' => $assignedByUserId,
        ]);
    }
}

function ensure_seller_profile(int $userId, string $displayName, string $collectionArea): void
{
    $stmt = db()->prepare('SELECT id FROM seller_profiles WHERE user_id = :user_id LIMIT 1');
    $stmt->execute(['user_id' => $userId]);

    if ($stmt->fetch()) {
        return;
    }

    $insertStmt = db()->prepare(
        'INSERT INTO seller_profiles (user_id, display_name, collection_area)
         VALUES (:user_id, :display_name, :collection_area)'
    );
    $insertStmt->execute([
        'user_id' => $userId,
        'display_name' => $displayName,
        'collection_area' => $collectionArea,
    ]);
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf_or_redirect(string $redirectPath): void
{
    $submittedToken = $_POST['_csrf'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (!is_string($submittedToken) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        flash('error', 'Your session expired or the form was invalid. Please try again.');
        redirect($redirectPath);
    }
}

function client_ip(): string
{
    return trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

function app_root(): string
{
    return dirname(__DIR__);
}

function uploads_relative_directory(): string
{
    return 'assets/uploads/listings';
}

function uploads_directory(): string
{
    $directory = app_root()
        . DIRECTORY_SEPARATOR . 'assets'
        . DIRECTORY_SEPARATOR . 'uploads'
        . DIRECTORY_SEPARATOR . 'listings';

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('The uploads folder could not be prepared.');
    }

    return $directory;
}

function upload_public_path(string $filename): string
{
    return url(uploads_relative_directory() . '/' . $filename);
}

function is_local_upload_url(string $imageUrl): bool
{
    $path = parse_url($imageUrl, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return false;
    }

    $base = rtrim(BASE_URL, '/');
    $prefix = ($base === '' ? '' : $base) . '/' . str_replace('\\', '/', uploads_relative_directory()) . '/';

    return str_starts_with($path, $prefix);
}

function delete_local_upload_if_present(string $imageUrl): void
{
    if (!is_local_upload_url($imageUrl)) {
        return;
    }

    $fileName = basename((string) parse_url($imageUrl, PHP_URL_PATH));
    if ($fileName === '') {
        return;
    }

    $path = uploads_directory() . DIRECTORY_SEPARATOR . $fileName;
    if (is_file($path)) {
        @unlink($path);
    }
}

function upload_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The image is too large. Please upload a file smaller than 2 MB.',
        UPLOAD_ERR_PARTIAL => 'The image upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE => 'No image file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not save the uploaded image. Please try again.',
        default => 'The uploaded image could not be processed.',
    };
}

function process_listing_image_upload(string $fieldName): ?string
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }

    $file = $_FILES[$fieldName];
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message($errorCode));
    }

    $tmpName = $file['tmp_name'] ?? '';
    if (!is_string($tmpName) || $tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('The uploaded image could not be verified.');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 2 * 1024 * 1024) {
        throw new RuntimeException('The image is too large. Please upload a file smaller than 2 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpName);
    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!is_string($mimeType) || !isset($allowedMimeTypes[$mimeType]) || @getimagesize($tmpName) === false) {
        throw new RuntimeException('Only JPG, PNG, and WEBP image files are allowed.');
    }

    $extension = $allowedMimeTypes[$mimeType];
    $fileName = 'listing-' . date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
    $destination = uploads_directory() . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpName, $destination)) {
        throw new RuntimeException('The uploaded image could not be saved. Please try again.');
    }

    @chmod($destination, 0644);

    return upload_public_path($fileName);
}

function cleanup_login_throttle(): void
{
    db()->exec(
        "DELETE FROM login_attempts
         WHERE COALESCE(locked_until, last_attempt_at) < (NOW() - INTERVAL 1 DAY)"
    );
}

function get_login_attempt(string $email): ?array
{
    cleanup_login_throttle();

    $stmt = db()->prepare(
        'SELECT id, failed_attempts, last_attempt_at, locked_until
         FROM login_attempts
         WHERE email = :email AND ip_address = :ip_address
         LIMIT 1'
    );
    $stmt->execute([
        'email' => normalize_email($email),
        'ip_address' => client_ip(),
    ]);

    $attempt = $stmt->fetch();

    return $attempt ?: null;
}

function login_is_rate_limited(string $email): bool
{
    $attempt = get_login_attempt($email);
    if ($attempt === null || empty($attempt['locked_until'])) {
        return false;
    }

    return strtotime((string) $attempt['locked_until']) > time();
}

function login_lockout_remaining(string $email): int
{
    $attempt = get_login_attempt($email);
    if ($attempt === null || empty($attempt['locked_until'])) {
        return 0;
    }

    return max(0, strtotime((string) $attempt['locked_until']) - time());
}

function record_failed_login_attempt(string $email): void
{
    $email = normalize_email($email);
    $ipAddress = client_ip();
    $attempt = get_login_attempt($email);
    $now = time();
    $windowSeconds = 900;
    $maxFailures = 5;

    if ($attempt === null) {
        $stmt = db()->prepare(
            'INSERT INTO login_attempts (email, ip_address, failed_attempts, last_attempt_at, locked_until)
             VALUES (:email, :ip_address, :failed_attempts, NOW(), :locked_until)'
        );
        $stmt->execute([
            'email' => $email,
            'ip_address' => $ipAddress,
            'failed_attempts' => 1,
            'locked_until' => null,
        ]);

        return;
    }

    $lastAttemptAt = isset($attempt['last_attempt_at']) ? strtotime((string) $attempt['last_attempt_at']) : false;
    $failures = ($lastAttemptAt === false || ($now - $lastAttemptAt) > $windowSeconds)
        ? 1
        : ((int) $attempt['failed_attempts'] + 1);
    $lockedUntil = $failures >= $maxFailures ? date('Y-m-d H:i:s', $now + $windowSeconds) : null;

    $stmt = db()->prepare(
        'UPDATE login_attempts
         SET failed_attempts = :failed_attempts,
             last_attempt_at = NOW(),
             locked_until = :locked_until
         WHERE id = :id'
    );
    $stmt->execute([
        'failed_attempts' => $failures,
        'locked_until' => $lockedUntil,
        'id' => $attempt['id'],
    ]);
}

function clear_failed_login_attempts(string $email): void
{
    $stmt = db()->prepare(
        'DELETE FROM login_attempts
         WHERE email = :email AND ip_address = :ip_address'
    );
    $stmt->execute([
        'email' => normalize_email($email),
        'ip_address' => client_ip(),
    ]);
}

function log_application_exception(Throwable $exception, string $context): void
{
    error_log(sprintf(
        '[%s][%s] %s in %s:%d',
        APP_NAME,
        $context,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    ));
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    if (!isset($_SESSION['flash'][$key])) {
        return null;
    }

    $value = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $value;
}

function old(string $key, string $default = ''): string
{
    return $_SESSION['old'][$key] ?? $default;
}

function remember_input(array $input): void
{
    $_SESSION['old'] = $input;
}

function clear_old_input(): void
{
    unset($_SESSION['old']);
}

function cart_items(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_count(): int
{
    return array_sum(cart_items());
}

function add_to_cart(int $listingId, int $quantity): void
{
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $_SESSION['cart'][$listingId] = ($_SESSION['cart'][$listingId] ?? 0) + $quantity;
}

function update_cart_item(int $listingId, int $quantity): void
{
    if ($quantity <= 0) {
        unset($_SESSION['cart'][$listingId]);
        return;
    }

    $_SESSION['cart'][$listingId] = $quantity;
}

function clear_cart(): void
{
    unset($_SESSION['cart']);
}

function generate_order_reference(): string
{
    return 'CT-' . date('Ymd-His') . '-' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
}

function get_categories(): array
{
    return db()->query('SELECT id, name, slug FROM categories ORDER BY name')->fetchAll();
}

function get_listing_by_id(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT l.*, c.name AS category_name, u.full_name AS seller_name, u.township, u.language_pref, u.verification_status
         FROM listings l
         INNER JOIN categories c ON c.id = l.category_id
         INNER JOIN users u ON u.id = l.user_id
         WHERE l.id = :id AND l.status = "active"'
    );
    $stmt->execute(['id' => $id]);
    $listing = $stmt->fetch();

    return $listing ?: null;
}

function get_cart_listing_rows(): array
{
    $items = cart_items();
    if ($items === []) {
        return [];
    }

    $ids = array_keys($items);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT l.id, l.title, l.price, l.stock_quantity, l.image_url, u.full_name AS seller_name
         FROM listings l
         INNER JOIN users u ON u.id = l.user_id
         WHERE l.id IN ($placeholders) AND l.status = 'active'"
    );
    $stmt->execute($ids);
    $rows = [];

    foreach ($stmt->fetchAll() as $row) {
        $row['quantity'] = $items[(int) $row['id']] ?? 0;
        $row['subtotal'] = (float) $row['price'] * (int) $row['quantity'];
        $rows[] = $row;
    }

    return $rows;
}

function cart_totals(): array
{
    $rows = get_cart_listing_rows();
    $subtotal = 0.0;

    foreach ($rows as $row) {
        $subtotal += (float) $row['subtotal'];
    }

    $delivery = $subtotal >= 750 ? 0.0 : ($subtotal > 0 ? 65.0 : 0.0);

    return [
        'rows' => $rows,
        'subtotal' => $subtotal,
        'delivery' => $delivery,
        'total' => $subtotal + $delivery,
    ];
}

function current_year(): string
{
    return date('Y');
}
