# Chapmans Trade

Chapmans Trade is a database-driven C2C e-commerce website built for the South African informal economy using HTML, CSS, JavaScript, PHP, and MySQL.

## Features

- Buyer and seller account registration with hashed passwords
- Product browsing with category filtering and keyword search
- Product detail pages with trust signals and seller verification
- Session-based cart and checkout flow
- Order tracking for buyers
- Seller dashboard for creating and updating listings
- Admin portal with RBAC for Super Admin, Admin, and Moderator staff accounts
- Seller verification, address, payment, and order-history tables for a fuller C2C schema
- Mobile-first, low-data-friendly interface with local ZAR pricing

## Project structure

- `index.php` home page
- `catalog.php` searchable product catalogue
- `product.php` listing details and add-to-cart
- `cart.php` session cart
- `checkout.php` checkout flow
- `orders.php` order tracking
- `seller/dashboard.php` seller overview
- `seller/listing_form.php` create or edit listing
- `admin/` RBAC-protected management portal for staff workflows
- `database/schema.sql` normalized schema and demo seed data
- `database/rbac_migration.sql` non-destructive RBAC migration for existing databases

## Setup

1. Copy the project into a folder inside `C:\xampp\htdocs\`.
2. Import `database/schema.sql` into MySQL using phpMyAdmin or:
   `C:\xampp\mysql\bin\mysql.exe -u root < C:\path\to\database\schema.sql`
   If you already have marketplace data that you want to keep, run `database/rbac_migration.sql` instead of resetting the whole schema.
3. Update the database credentials in `config.php` if your local MySQL settings differ. `BASE_URL` now auto-detects the current XAMPP folder name.
4. Visit `http://localhost/<your-folder-name>/index.php`.

## Database overview

- `users` stores buyer/seller accounts, language preferences, and verification state
- `seller_profiles` and `seller_verifications` support trust and anti-fraud workflows
- `user_addresses` stores local delivery and collection addresses
- `categories`, `listings`, and `listing_images` power the catalogue
- `orders`, `order_items`, `payments`, and `order_status_history` track checkout and fulfilment
- `seller_reviews` supports seller reputation and long-term buyer trust

## Demo accounts

All seeded users use the same demo password:

- `nomsa@chapmanstrade.test`
- `sipho@chapmanstrade.test`
- `ayesha@chapmanstrade.test`

Password: `Password123`

Admin demo accounts after importing the RBAC schema or migration:

- `superadmin@chapmanstrade.test`
- `admin@chapmanstrade.test`
- `moderator@chapmanstrade.test`

Password: `Password123`
