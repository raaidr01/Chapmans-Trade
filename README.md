# Chapmans Trade

A township-first C2C marketplace for the South African informal economy.

## Overview

Chapmans Trade is a database-driven C2C e-commerce platform built for local traders in South Africa. It helps buyers and sellers list, browse, and purchase everyday goods with verified seller badges, secure card and cash checkout, and a mobile-first low-data interface.

Live site: [chapman-trade.up.railway.app](https://chapman-trade.up.railway.app)

## Features

- Buyer and seller account registration with bcrypt password hashing
- Product catalogue with category filtering and keyword search
- Product detail pages with seller trust signals and verification badges
- Session-based cart with delivery fee calculation
- Full checkout flow with Stripe card payments (ZAR) and cash on collection
- Order tracking for buyers
- Seller dashboard for creating and managing listings with image URL support
- Admin portal with full RBAC for Super Admin, Admin, and Moderator roles
- Login rate limiting and brute-force lockout (5 attempts, 15-minute window)
- CSRF protection on every form
- Security headers: CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy
- Mobile-first, low-data-friendly interface with local ZAR pricing

## Tech Stack

- **Frontend:** HTML, CSS, JavaScript
- **Backend:** PHP 8.4 (procedural, PDO)
- **Database:** MySQL 9.4
- **Payments:** Stripe Checkout (test mode)
- **Local development:** XAMPP
- **Deployment:** Railway (Docker, GitHub auto-deploy)

## Project Structure

```
index.php                   Home page
catalog.php                 Searchable product catalogue
product.php                 Listing detail and add-to-cart
cart.php                    Session cart
checkout.php                Checkout — Stripe and cash on collection
orders.php                  Order tracking
seller/
  dashboard.php             Seller overview and stock management
  listing_form.php          Create or edit a listing
admin/
  dashboard.php             Platform metrics and recent activity
  users.php                 User management
  user_form.php             Create or edit a user
  roles.php                 RBAC role and permission matrix
  listings.php              Listing moderation
  orders.php                Order overview
  verifications.php         Seller verification review
includes/
  auth.php                  Session, login, RBAC helpers
  functions.php             Shared utilities, cart, flash, CSRF
  db.php                    PDO singleton
partials/
  header.php                Site navigation and flash messages
  footer.php                Footer
database/
  schema.sql                Full normalised schema with seed data
  rbac_migration.sql        Non-destructive RBAC migration
config.railway.php          Production config (env vars, session, headers)
Dockerfile                  PHP 8.4 CLI with pdo_mysql and curl
```

## Local Setup

1. Clone the repo into `C:\xampp\htdocs\chapmans-trade`
2. Import `database/schema.sql` into MySQL via phpMyAdmin
3. Run `database/rbac_migration.sql` to seed roles, permissions, and staff accounts
4. Copy `config.railway.php` to `config.php` and update the DB credentials at the bottom
5. Visit `http://localhost/chapmans-trade/index.php`

## Database Overview

| Table | Purpose |
|---|---|
| `users` | Buyer/seller accounts, language preferences, verification state |
| `seller_profiles` | Display name, collection area, payout method, ratings |
| `seller_verifications` | ID and address document submissions for trust workflow |
| `user_addresses` | Saved delivery and pickup addresses |
| `categories` | Product categories |
| `listings` | Marketplace listings with stock, condition, and image URL |
| `listing_images` | Additional gallery images per listing |
| `orders` | Checkout orders with delivery and payment method |
| `order_items` | Line items with seller attribution and snapshot pricing |
| `payments` | Payment gateway records per order |
| `order_status_history` | Audit trail of order status changes |
| `seller_reviews` | Buyer reviews of sellers per completed order item |
| `roles` | Role catalogue (Buyer, Seller, Moderator, Admin, Super Admin) |
| `permissions` | Named permission slugs per module |
| `user_roles` | Many-to-many user to role assignments |
| `role_permissions` | Many-to-many role to permission assignments |
| `login_attempts` | IP + email throttle records for brute-force protection |

## Demo Accounts

After importing the schema and RBAC migration, the following accounts are available:

| Role | Email | Password |
|---|---|---|
| Super Admin | superadmin@chapmanstrade.test | *(set on first run)* |
| Admin | admin@chapmanstrade.test | *(set on first run)* |
| Moderator | moderator@chapmanstrade.test | *(set on first run)* |
| Buyer / Seller | nomsa@chapmanstrade.test | *(set on first run)* |

## Security

- All queries use PDO prepared statements — no SQL injection surface
- Passwords hashed with bcrypt via `password_hash()`
- CSRF tokens on every POST form using `hash_equals()` for timing-safe comparison
- Session hardening: `httponly`, `SameSite=Lax`, strict mode, ID regeneration on login
- Login rate limiting: 5 failures triggers a 15-minute lockout per IP + email pair
- Image input accepts URLs only — no file upload to ephemeral filesystem
- Content Security Policy allowlists Stripe domains for card checkout
- `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`
- `config.php` excluded from git — credentials set via Railway environment variables
- Stripe secret key stored as Railway env var, never committed to source

## Environment Variables (Production)

Set these in Railway → your PHP service → Variables:

| Variable | Description |
|---|---|
| `DB_HOST` | MySQL internal hostname |
| `DB_PORT` | MySQL port (default 3306) |
| `DB_NAME` | Database name |
| `DB_USER` | Database user |
| `DB_PASS` | Database password |
| `STRIPE_SECRET_KEY` | Stripe secret key (`sk_test_` or `sk_live_`) |
| `STRIPE_PUBLISHABLE_KEY` | Stripe publishable key (`pk_test_` or `pk_live_`) |

## Stripe Testing

Use these test card details on the Stripe Checkout page:

| Field | Value |
|---|---|
| Card number | `4242 4242 4242 4242` |
| Expiry | Any future date |
| CVC | Any 3 digits |
| Country | South Africa |

To go live, replace the `sk_test_` and `pk_test_` keys with your Stripe live keys in Railway Variables. No code changes required.

## Deployment

The app deploys automatically to Railway on every push to `main` via GitHub integration. The Dockerfile installs PHP 8.4 CLI with the `pdo_mysql` and `curl` extensions and serves the app on the Railway-injected `$PORT`.

```dockerfile
FROM php:8.4-cli
RUN docker-php-ext-install pdo pdo_mysql
RUN apt-get update && apt-get install -y libcurl4-openssl-dev && \
    docker-php-ext-install curl && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY . .
RUN cp config.railway.php config.php
CMD php -S 0.0.0.0:$PORT
```

Note: Railway uses an ephemeral container filesystem. Uploaded files do not persist across redeploys. Use external image URLs (Unsplash, Imgur) for listing images.
