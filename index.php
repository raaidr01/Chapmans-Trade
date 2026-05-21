<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Home';
$featuredListings = db()->query(
    'SELECT l.*, c.name AS category_name, u.full_name AS seller_name, u.verification_status
     FROM listings l
     INNER JOIN categories c ON c.id = l.category_id
     INNER JOIN users u ON u.id = l.user_id
     WHERE l.status = "active"
     ORDER BY l.created_at DESC
     LIMIT 6'
)->fetchAll();

$stats = [
    'active_listings' => db()->query('SELECT COUNT(*) FROM listings WHERE status = "active"')->fetchColumn(),
    'verified_sellers' => db()->query("SELECT COUNT(*) FROM users WHERE verification_status = 'verified'")->fetchColumn(),
    'townships' => db()->query('SELECT COUNT(DISTINCT township) FROM users WHERE township IS NOT NULL AND township <> ""')->fetchColumn(),
];

require __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <div class="container hero-grid">
        <div>
            <span class="eyebrow">Designed for the South African informal economy</span>
            <h1>Buy and sell everyday goods with safer township-friendly commerce.</h1>
            <p class="hero-copy">Chapmans Trade helps local customers list furniture, fashion, electronics, and household items with verified seller badges, low-data pages, and simple checkout flows.</p>
            <div class="hero-actions">
                <a class="button" href="<?= e(url('catalog.php')) ?>">Start shopping</a>
                <a class="button button-secondary" href="<?= e(url('seller/dashboard.php')) ?>">Start selling</a>
            </div>
            <div class="hero-trust">
                <span>Trusted by local side-hustlers</span>
                <span>ZAR pricing</span>
                <span>Pickup or courier</span>
            </div>
        </div>
        <div class="hero-card">
            <h2>Marketplace snapshot</h2>
            <div class="stat-grid">
                <article>
                    <strong><?= e((string) $stats['active_listings']) ?></strong>
                    <span>Active listings</span>
                </article>
                <article>
                    <strong><?= e((string) $stats['verified_sellers']) ?></strong>
                    <span>Verified sellers</span>
                </article>
                <article>
                    <strong><?= e((string) $stats['townships']) ?></strong>
                    <span>Townships served</span>
                </article>
            </div>
            <p>Built for mobile phones, low bandwidth, and community-based trade where trust matters as much as price.</p>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Featured listings</span>
                <h2>Popular categories for local buying and reselling</h2>
            </div>
            <a href="<?= e(url('catalog.php')) ?>">See all listings</a>
        </div>

        <div class="card-grid">
            <?php foreach ($featuredListings as $listing): ?>
                <article class="listing-card">
                    <img src="<?= e($listing['image_url']) ?>" alt="<?= e($listing['title']) ?>">
                    <div class="listing-body">
                        <span class="tag"><?= e($listing['category_name']) ?></span>
                        <h3><a href="<?= e(url('product.php?id=' . (string) $listing['id'])) ?>"><?= e($listing['title']) ?></a></h3>
                        <p><?= e(excerpt($listing['description'], 96)) ?></p>
                        <div class="listing-meta">
                            <strong><?= e(format_currency((float) $listing['price'])) ?></strong>
                            <span><?= e($listing['seller_name']) ?><?php if ($listing['verification_status'] === 'verified'): ?> • Verified<?php endif; ?></span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-accent">
    <div class="container feature-grid">
        <article>
            <h3>Secure buyer flow</h3>
            <p>Passwords are hashed, checkout requires a signed-in account, and each order keeps a clear delivery or pickup trail.</p>
        </article>
        <article>
            <h3>Local trust signals</h3>
            <p>Seller verification status, township location, and local language preference help buyers decide faster and safer.</p>
        </article>
        <article>
            <h3>Built to scale from side hustle to micro-business</h3>
            <p>Users can add listings, manage stock, and track orders in one dashboard without needing formal infrastructure.</p>
        </article>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
