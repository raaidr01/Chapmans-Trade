    </main>

    <footer class="site-footer">
        <div class="container footer-grid">
            <div>
                <h3><?= e(APP_NAME) ?></h3>
                <p>A low-data marketplace helping South African side-hustlers sell safely, locally, and with confidence.</p>
            </div>
            <div>
                <h4>Marketplace</h4>
                <a href="<?= e(url('catalog.php')) ?>">Shop listings</a>
                <a href="<?= e(url('seller/dashboard.php')) ?>">Seller dashboard</a>
                <a href="<?= e(url('orders.php')) ?>">Track orders</a>
            </div>
            <div>
                <h4>Trust and support</h4>
                <p>Verified sellers, secure passwords, local pickup options, and simple delivery choices for township and peri-urban buyers.</p>
            </div>
        </div>
        <div class="container footer-bottom">
            <span>&copy; <?= e(current_year()) ?> <?= e(APP_NAME) ?></span>
        </div>
    </footer>

    <script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
