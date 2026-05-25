Chapmans Trade
A township-first C2C marketplace for the South African informal economy.

Overview:
Chapmans Trade is a database-driven C2C e-commerce platform built for the South African informal economy. It helps local traders list, browse, and purchase everyday goods with verified seller badges, secure checkout, and mobile-first design.

Features:
•	Buyer and seller account registration with hashed passwords and email verification
•	Product browsing with category filtering and keyword search
•	Product detail pages with seller trust signals and verification badges
•	Session-based cart and full checkout flow
•	Order tracking for buyers
•	Seller dashboard for creating and managing listings with image uploads
•	Admin portal with full RBAC for Super Admin, Admin, and Moderator roles
•	Login rate limiting and brute-force protection
•	Mobile-first, low-data-friendly interface with local ZAR pricing

Tech Stack:
•	Frontend: HTML, CSS, JavaScript
•	Backend: PHP 8.1+ with PDO
•	Database: MySQL 8
•	Local development: XAMPP
•	Deployment: Railway (GitHub auto-deploy)

Project Structure:
index.php             – Home page
catalog.php           – Searchable product catalogue9651
product.php           – Listing detail and add-to-cart
cart.php              – Session cart
checkout.php          – Checkout flow
orders.php            – Order tracking
seller/dashboard.php  – Seller overview
seller/listing_form.php – Create or edit listing
admin/               – RBAC-protected management portal
database/schema.sql  – Normalised schema and seed data
database/rbac_migration.sql – Non-destructive RBAC migration

Local Setup:
1.	Copy the project folder into C:\xampp\htdocs\
2.	Import database/schema.sql into MySQL via phpMyAdmin, then run database/rbac_migration.sql
3.	Update database credentials in config.php to match your local MySQL settings
4.	Visit http://localhost/chapmans-trade/index.php

Database Overview:
•	users – buyer/seller accounts, language preferences, verification state
•	seller_profiles and seller_verifications – trust and anti-fraud workflows
•	user_addresses – local delivery and collection addresses
•	categories, listings, listing_images – catalogue
•	orders, order_items, payments, order_status_history – checkout and fulfilment
•	roles, permissions, user_roles, role_permissions – RBAC
•	seller_reviews – seller reputation

Demo Accounts:
Seeded demo accounts are available for testing. Contact the project owner for login credentials, or refer to the project brief for the demo password policy.
Admin accounts (Super Admin, Admin, Moderator) are seeded after importing the RBAC migration.

Security:
•	All queries use PDO prepared statements
•	Passwords hashed with bcrypt via password_hash()
•	CSRF tokens on every form
•	Session hardening: httponly cookies, SameSite=Lax, strict mode
•	Login rate limiting with IP + email throttle
•	File uploads validated by MIME type and magic bytes
•	CSP, X-Frame-Options, and other security headers set on every response

Environment Variables (Production)
On the live server, set the following environment variables instead of editing config.php directly:
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASS
