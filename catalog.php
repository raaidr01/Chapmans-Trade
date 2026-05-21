<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Browse Listings';
$search = trim($_GET['search'] ?? '');
$categorySlug = trim($_GET['category'] ?? '');
$categories = get_categories();

$sql = 'SELECT l.*, c.name AS category_name, c.slug AS category_slug, u.full_name AS seller_name, u.township, u.verification_status
        FROM listings l
        INNER JOIN categories c ON c.id = l.category_id
        INNER JOIN users u ON u.id = l.user_id
        WHERE l.status = "active"';
$params = [];

if ($search !== '') {
    $sql .= ' AND (l.title LIKE :search_title OR l.description LIKE :search_description OR u.township LIKE :search_township)';
    $searchValue = '%' . $search . '%';
    $params['search_title'] = $searchValue;
    $params['search_description'] = $searchValue;
    $params['search_township'] = $searchValue;
}

if ($categorySlug !== '') {
    $sql .= ' AND c.slug = :category';
    $params['category'] = $categorySlug;
}

$sql .= ' ORDER BY l.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();

require __DIR__ . '/partials/header.php';
?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Marketplace</span>
                <h1>Browse active listings</h1>
            </div>
            <span><?= e((string) count($listings)) ?> results</span>
        </div>

        <form class="filter-bar" method="get">
            <input type="search" name="search" placeholder="Search clothing, furniture, phones, townships..." value="<?= e($search) ?>">
            <select name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category['slug']) ?>" <?= $categorySlug === $category['slug'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="button" type="submit">Search</button>
        </form>

        <div class="card-grid">
            <?php foreach ($listings as $listing): ?>
                <article class="listing-card">
                    <img src="<?= e($listing['image_url']) ?>" alt="<?= e($listing['title']) ?>">
                    <div class="listing-body">
                        <div class="listing-topline">
                            <span class="tag"><?= e($listing['category_name']) ?></span>
                            <?php if ($listing['verification_status'] === 'verified'): ?>
                                <span class="trust-badge">Verified seller</span>
                            <?php endif; ?>
                        </div>
                        <h3><a href="<?= e(url('product.php?id=' . (string) $listing['id'])) ?>"><?= e($listing['title']) ?></a></h3>
                        <p><?= e(excerpt($listing['description'], 110)) ?></p>
                        <div class="listing-meta">
                            <strong><?= e(format_currency((float) $listing['price'])) ?></strong>
                            <span><?= e($listing['seller_name']) ?> • <?= e($listing['township']) ?></span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($listings === []): ?>
            <div class="empty-state">
                <h2>No listings matched your search.</h2>
                <p>Try a different category or search for a broader item name.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
