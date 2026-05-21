<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_login();

$user = current_user();
$categories = get_categories();
$listingId = (int) ($_GET['id'] ?? 0);
$editing = $listingId > 0;
$errors = [];

$listing = [
    'title' => '',
    'category_id' => '',
    'price' => '',
    'stock_quantity' => '1',
    'item_condition' => 'used',
    'image_url' => '',
    'description' => '',
    'status' => 'active',
];
$currentImageUrl = '';

if ($editing) {
    $stmt = db()->prepare('SELECT * FROM listings WHERE id = :id AND user_id = :user_id LIMIT 1');
    $stmt->execute([
        'id' => $listingId,
        'user_id' => $user['id'],
    ]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flash('error', 'Listing not found.');
        redirect(url('seller/dashboard.php'));
    }

    $listing = $existing;
    $currentImageUrl = (string) ($listing['image_url'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_redirect($editing ? url('seller/listing_form.php?id=' . (string) $listingId) : url('seller/listing_form.php'));

    $currentImageUrl = trim($_POST['current_image_url'] ?? $currentImageUrl);
    $listing = [
        'title' => trim($_POST['title'] ?? ''),
        'category_id' => (int) ($_POST['category_id'] ?? 0),
        'price' => trim($_POST['price'] ?? ''),
        'stock_quantity' => (int) ($_POST['stock_quantity'] ?? 1),
        'item_condition' => $_POST['item_condition'] ?? 'used',
        'image_url' => $currentImageUrl,
        'description' => trim($_POST['description'] ?? ''),
        'status' => $_POST['status'] ?? 'active',
    ];
    $uploadedImageUrl = null;

    try {
        $uploadedImageUrl = process_listing_image_upload('image_file');
    } catch (RuntimeException $exception) {
        $errors[] = $exception->getMessage();
    }

    if ($uploadedImageUrl !== null) {
        $listing['image_url'] = $uploadedImageUrl;
    }

    if ($listing['title'] === '' || $listing['category_id'] === 0 || $listing['price'] === '' || $listing['image_url'] === '' || $listing['description'] === '') {
        $errors[] = 'Please complete all required fields.';
    }


    if ($listing['stock_quantity'] < 1) {
        $errors[] = 'Stock quantity must be at least 1.';
    }

    if ($errors !== [] && $uploadedImageUrl !== null) {
        delete_local_upload_if_present($uploadedImageUrl);
        $listing['image_url'] = $currentImageUrl;
    }

    if ($errors === []) {
        try {
            if ($editing) {
                $stmt = db()->prepare(
                    'UPDATE listings
                     SET category_id = :category_id, title = :title, description = :description, price = :price,
                         stock_quantity = :stock_quantity, item_condition = :item_condition, image_url = :image_url, status = :status
                     WHERE id = :id AND user_id = :user_id'
                );
                $stmt->execute([
                    'category_id' => $listing['category_id'],
                    'title' => $listing['title'],
                    'description' => $listing['description'],
                    'price' => $listing['price'],
                    'stock_quantity' => $listing['stock_quantity'],
                    'item_condition' => $listing['item_condition'],
                    'image_url' => $listing['image_url'],
                    'status' => $listing['status'],
                    'id' => $listingId,
                    'user_id' => $user['id'],
                ]);
                flash('success', 'Listing updated successfully.');
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO listings (user_id, category_id, title, description, price, stock_quantity, item_condition, image_url, status)
                     VALUES (:user_id, :category_id, :title, :description, :price, :stock_quantity, :item_condition, :image_url, :status)'
                );
                $stmt->execute([
                    'user_id' => $user['id'],
                    'category_id' => $listing['category_id'],
                    'title' => $listing['title'],
                    'description' => $listing['description'],
                    'price' => $listing['price'],
                    'stock_quantity' => $listing['stock_quantity'],
                    'item_condition' => $listing['item_condition'],
                    'image_url' => $listing['image_url'],
                    'status' => $listing['status'],
                ]);
                flash('success', 'Listing created successfully.');
            }

            if ($listing['image_url'] !== $currentImageUrl) {
                delete_local_upload_if_present($currentImageUrl);
            }

            redirect(url('seller/dashboard.php'));
        } catch (Throwable $exception) {
            if ($uploadedImageUrl !== null) {
                delete_local_upload_if_present($uploadedImageUrl);
            }

            log_application_exception($exception, 'listing_form');
            $errors[] = 'We could not save your listing right now. Please try again.';
        }
    }

    $currentImageUrl = (string) $listing['image_url'];
}

$pageTitle = $editing ? 'Edit Listing' : 'Add Listing';
require __DIR__ . '/../partials/header.php';
?>
<section class="section auth-section">
    <div class="container auth-card auth-card-wide">
        <span class="eyebrow">Seller tools</span>
        <h1><?= $editing ? 'Edit your listing' : 'Create a new listing' ?></h1>
        <?php if ($errors !== []): ?>
            <div class="flash flash-error"><?= e(implode(' ', $errors)) ?></div>
        <?php endif; ?>
        <form class="stack-form two-column" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="current_image_url" value="<?= e($currentImageUrl) ?>">
            <label>
                Listing title
                <input type="text" name="title" value="<?= e((string) $listing['title']) ?>" required>
            </label>
            <label>
                Category
                <select name="category_id" required>
                    <option value="">Choose one</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['id']) ?>" <?= (string) $listing['category_id'] === (string) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Price (ZAR)
                <input type="number" min="1" step="0.01" name="price" value="<?= e((string) $listing['price']) ?>" required>
            </label>
            <label>
                Stock quantity
                <input type="number" min="1" name="stock_quantity" value="<?= e((string) $listing['stock_quantity']) ?>" required>
            </label>
            <label>
                Item condition
                <select name="item_condition" required>
                    <option value="new" <?= $listing['item_condition'] === 'new' ? 'selected' : '' ?>>New</option>
                    <option value="used" <?= $listing['item_condition'] === 'used' ? 'selected' : '' ?>>Used</option>
                    <option value="refurbished" <?= $listing['item_condition'] === 'refurbished' ? 'selected' : '' ?>>Refurbished</option>
                </select>
            </label>
            <label>
                Listing status
                <select name="status" required>
                    <option value="active" <?= $listing['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="paused" <?= $listing['status'] === 'paused' ? 'selected' : '' ?>>Paused</option>
                    <option value="sold" <?= $listing['status'] === 'sold' ? 'selected' : '' ?>>Sold</option>
                </select>
            </label>
            <label class="full-span">
                Upload image from device
                <input type="file" name="image_file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                <small class="field-note">Allowed types: JPG, PNG, WEBP. Maximum size: 2 MB. When editing, your current image stays in place until you upload a replacement.</small>
            </label>
            <?php if ($currentImageUrl !== ''): ?>
                <div class="full-span listing-preview">
                    <strong>Current image</strong>
                    <img src="<?= e($currentImageUrl) ?>" alt="Current listing image preview">
                </div>
            <?php endif; ?>
            <label class="full-span">
                Description
                <textarea name="description" rows="6" required><?= e((string) $listing['description']) ?></textarea>
            </label>
            <button class="button" type="submit"><?= $editing ? 'Update listing' : 'Create listing' ?></button>
        </form>
    </div>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
