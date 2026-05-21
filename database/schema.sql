CREATE DATABASE IF NOT EXISTS chapmans_trade CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE chapmans_trade;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS seller_reviews;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS listing_images;
DROP TABLE IF EXISTS listings;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS seller_verifications;
DROP TABLE IF EXISTS seller_profiles;
DROP TABLE IF EXISTS user_addresses;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- Core marketplace users. In a C2C platform every account can buy and sell.
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    phone VARCHAR(30) NOT NULL,
    township VARCHAR(120) NOT NULL,
    language_pref VARCHAR(50) NOT NULL DEFAULT 'English',
    password_hash VARCHAR(255) NOT NULL,
    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    account_status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_users_township (township),
    KEY idx_users_verification (verification_status),
    KEY idx_users_account_status (account_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role catalogue used by the admin portal to separate marketplace roles from
-- internal staff roles.
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(60) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    admin_area_access TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(80) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    module VARCHAR(80) NOT NULL DEFAULT 'general',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_by_user_id INT DEFAULT NULL,
    assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_assigned_by
        FOREIGN KEY (assigned_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_user_roles_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission
        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Saved delivery and pickup addresses for local courier or collection workflows.
CREATE TABLE user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    label VARCHAR(40) NOT NULL DEFAULT 'Primary',
    recipient_name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    address_line_1 VARCHAR(150) NOT NULL,
    address_line_2 VARCHAR(150) DEFAULT NULL,
    suburb VARCHAR(100) DEFAULT NULL,
    township VARCHAR(120) NOT NULL,
    city VARCHAR(120) NOT NULL,
    province VARCHAR(120) NOT NULL,
    postal_code VARCHAR(12) DEFAULT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_addresses_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_user_addresses_user (user_id),
    KEY idx_user_addresses_primary (user_id, is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seller-specific profile metadata used for trust and local collection details.
CREATE TABLE seller_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    display_name VARCHAR(120) DEFAULT NULL,
    short_bio VARCHAR(255) DEFAULT NULL,
    collection_area VARCHAR(120) DEFAULT NULL,
    preferred_payout_method ENUM('bank_transfer', 'cash', 'mobile_wallet') NOT NULL DEFAULT 'bank_transfer',
    payout_reference VARCHAR(120) DEFAULT NULL,
    rating_average DECIMAL(3, 2) NOT NULL DEFAULT 0.00,
    rating_count INT NOT NULL DEFAULT 0,
    completed_sales_count INT NOT NULL DEFAULT 0,
    response_time_hours INT NOT NULL DEFAULT 24,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_seller_profiles_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_seller_profile_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verification evidence used to approve sellers and reduce fraud risk.
CREATE TABLE seller_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_type ENUM('south_african_id', 'passport', 'proof_of_address', 'selfie') NOT NULL,
    document_number VARCHAR(60) DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewer_notes VARCHAR(255) DEFAULT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_seller_verifications_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_seller_verification (user_id, document_type),
    KEY idx_seller_verifications_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 1,
    item_condition ENUM('new', 'used', 'refurbished') NOT NULL DEFAULT 'used',
    image_url VARCHAR(255) NOT NULL,
    delivery_available TINYINT(1) NOT NULL DEFAULT 1,
    pickup_available TINYINT(1) NOT NULL DEFAULT 1,
    pickup_township VARCHAR(120) DEFAULT NULL,
    status ENUM('draft', 'active', 'paused', 'sold') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_listings_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_listings_category
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    KEY idx_listings_user_status (user_id, status),
    KEY idx_listings_category_status (category_id, status),
    KEY idx_listings_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional gallery table kept separate to preserve normalization while the UI
-- still reads the primary image_url directly from listings.
CREATE TABLE listing_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 1,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_listing_images_listing
        FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    KEY idx_listing_images_listing (listing_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    order_reference VARCHAR(30) DEFAULT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    delivery_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    service_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10, 2) NOT NULL,
    delivery_method ENUM('pickup', 'courier') NOT NULL,
    delivery_address TEXT NULL,
    buyer_note VARCHAR(255) DEFAULT NULL,
    payment_method ENUM('eft', 'cash_on_collection', 'card_demo') NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    order_status ENUM('processing', 'ready_for_pickup', 'out_for_delivery', 'completed', 'cancelled') NOT NULL DEFAULT 'processing',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_orders_buyer
        FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_order_reference (order_reference),
    KEY idx_orders_buyer_created (buyer_id, created_at),
    KEY idx_orders_status (order_status, payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    listing_id INT NULL,
    seller_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    item_title VARCHAR(150) NOT NULL,
    item_image_url VARCHAR(255) DEFAULT NULL,
    seller_name VARCHAR(120) DEFAULT NULL,
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_listing
        FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
    CONSTRAINT fk_order_items_seller
        FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE RESTRICT,
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_seller (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tracks the payment attempt separately from the order so the app can support
-- more than one local gateway later without redesigning the core order table.
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('eft', 'cash_on_collection', 'card_demo') NOT NULL,
    gateway_name VARCHAR(80) DEFAULT NULL,
    transaction_reference VARCHAR(80) DEFAULT NULL,
    status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    paid_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_order
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    UNIQUE KEY unique_payment_transaction_reference (transaction_reference),
    KEY idx_payments_order (order_id),
    KEY idx_payments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    status ENUM('processing', 'ready_for_pickup', 'out_for_delivery', 'completed', 'cancelled') NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    changed_by_user_id INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_status_history_order
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_status_history_user
        FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_order_status_history_order (order_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seller_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_item_id INT NOT NULL,
    seller_id INT NOT NULL,
    buyer_id INT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    review_text VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_seller_reviews_order_item
        FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_seller_reviews_seller
        FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_seller_reviews_buyer
        FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_seller_review_order_item (order_item_id),
    KEY idx_seller_reviews_seller (seller_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(120) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    failed_attempts INT NOT NULL DEFAULT 0,
    last_attempt_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_until TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_login_attempt_identity (email, ip_address),
    KEY idx_login_attempts_lockout (locked_until),
    KEY idx_login_attempts_last_attempt (last_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (id, name, slug, description, sort_order) VALUES
(1, 'Fashion', 'fashion', 'Pre-loved clothing, shoes, and accessories for resale or personal use.', 1),
(2, 'Electronics', 'electronics', 'Phones, accessories, small appliances, and second-hand gadgets.', 2),
(3, 'Furniture', 'furniture', 'Home and office furniture for township homes, flats, and student spaces.', 3),
(4, 'Homeware', 'homeware', 'Cookware, decor, storage, and household essentials.', 4),
(5, 'Kids', 'kids', 'Baby and children products including travel cots, toys, and clothing bundles.', 5),
(6, 'Groceries', 'groceries', 'Small trader bundles, pantry basics, and resale starter packs.', 6),
(7, 'Beauty', 'beauty', 'Affordable beauty and personal care products.', 7),
(8, 'Books', 'books', 'School books, novels, and study guides.', 8);

INSERT INTO users (
    id, full_name, email, phone, township, language_pref, password_hash, verification_status, account_status
) VALUES
(1, 'Nomsa Jacobs', 'nomsa@chapmanstrade.test', '0815552101', 'Khayelitsha', 'isiXhosa', '$2y$10$YAst.tCeQ/pQHYZGh8OKU.4X5PL8q5/J0ucnx65SvWKvKGye.w.2a', 'verified', 'active'),
(2, 'Sipho Dlamini', 'sipho@chapmanstrade.test', '0825559184', 'Mitchells Plain', 'English', '$2y$10$YAst.tCeQ/pQHYZGh8OKU.4X5PL8q5/J0ucnx65SvWKvKGye.w.2a', 'verified', 'active'),
(3, 'Ayesha Daniels', 'ayesha@chapmanstrade.test', '0832221194', 'Gugulethu', 'Afrikaans', '$2y$10$YAst.tCeQ/pQHYZGh8OKU.4X5PL8q5/J0ucnx65SvWKvKGye.w.2a', 'pending', 'active'),
(4, 'Lerato Mokoena', 'superadmin@chapmanstrade.test', '0849001111', 'Johannesburg CBD', 'English', '$2y$10$YAst.tCeQ/pQHYZGh8OKU.4X5PL8q5/J0ucnx65SvWKvKGye.w.2a', 'verified', 'active'),
(5, 'Zanele Khumalo', 'admin@chapmanstrade.test', '0849002222', 'Soweto', 'English', '$2y$10$YAst.tCeQ/pQHYZGh8OKU.4X5PL8q5/J0ucnx65SvWKvKGye.w.2a', 'verified', 'active'),
(6, 'Mandla Nene', 'moderator@chapmanstrade.test', '0849003333', 'Tembisa', 'isiZulu', '$2y$10$YAst.tCeQ/pQHYZGh8OKU.4X5PL8q5/J0ucnx65SvWKvKGye.w.2a', 'verified', 'active');

INSERT INTO roles (id, name, slug, description, admin_area_access, sort_order) VALUES
(1, 'Buyer', 'buyer', 'Marketplace role for purchasing products and tracking orders.', 0, 10),
(2, 'Seller', 'seller', 'Marketplace role for creating listings and receiving orders.', 0, 20),
(3, 'Moderator', 'moderator', 'Admin role focused on reviewing listings and seller verification requests.', 1, 30),
(4, 'Admin', 'admin', 'Admin role for day-to-day platform management and staff tasks.', 1, 40),
(5, 'Super Admin', 'super_admin', 'Highest-privilege role with full administrative access and deletion rights.', 1, 50);

INSERT INTO permissions (id, name, slug, description, module) VALUES
(1, 'Access admin portal', 'admin.access', 'Allows a user to sign into the admin website.', 'admin'),
(2, 'View admin dashboard', 'admin.dashboard.view', 'Allows viewing the administrative summary dashboard.', 'admin'),
(3, 'View users', 'users.view', 'Allows viewing the user directory and individual role assignments.', 'users'),
(4, 'Create users', 'users.create', 'Allows creating new users from the admin portal.', 'users'),
(5, 'Edit users', 'users.edit', 'Allows updating user details and account states.', 'users'),
(6, 'Delete users', 'users.delete', 'Allows deleting user accounts when it is safe to do so.', 'users'),
(7, 'View roles', 'roles.view', 'Allows viewing the platform role and permission matrix.', 'roles'),
(8, 'Assign roles', 'roles.assign', 'Allows assigning or changing roles for user accounts.', 'roles'),
(9, 'View listings', 'listings.view', 'Allows viewing all marketplace listings in the admin portal.', 'listings'),
(10, 'Moderate listings', 'listings.moderate', 'Allows changing listing status for moderation or quality control.', 'listings'),
(11, 'View orders', 'orders.view', 'Allows viewing all orders placed on the marketplace.', 'orders'),
(12, 'View verifications', 'verifications.view', 'Allows viewing seller verification submissions.', 'verifications'),
(13, 'Review verifications', 'verifications.review', 'Allows approving or rejecting seller verification submissions.', 'verifications');

INSERT INTO role_permissions (role_id, permission_id) VALUES
(3, 1), (3, 2), (3, 9), (3, 10), (3, 11), (3, 12), (3, 13),
(4, 1), (4, 2), (4, 3), (4, 4), (4, 5), (4, 7), (4, 8), (4, 9), (4, 10), (4, 11), (4, 12), (4, 13),
(5, 1), (5, 2), (5, 3), (5, 4), (5, 5), (5, 6), (5, 7), (5, 8), (5, 9), (5, 10), (5, 11), (5, 12), (5, 13);

INSERT INTO user_roles (user_id, role_id, assigned_by_user_id) VALUES
(1, 1, 4), (1, 2, 4),
(2, 1, 4), (2, 2, 4),
(3, 1, 4), (3, 2, 4),
(4, 5, 4),
(5, 4, 4),
(6, 3, 4);

INSERT INTO user_addresses (
    id, user_id, label, recipient_name, phone, address_line_1, suburb, township, city, province, postal_code, is_primary
) VALUES
(1, 1, 'Home', 'Nomsa Jacobs', '0815552101', '14 Makhaza Street', 'Site C', 'Khayelitsha', 'Cape Town', 'Western Cape', '7784', 1),
(2, 2, 'Home', 'Sipho Dlamini', '0825559184', '22 Alpine Road', 'Portlands', 'Mitchells Plain', 'Cape Town', 'Western Cape', '7785', 1),
(3, 3, 'Home', 'Ayesha Daniels', '0832221194', '8 NY5 Avenue', 'Gugulethu', 'Gugulethu', 'Cape Town', 'Western Cape', '7750', 1);

INSERT INTO seller_profiles (
    id, user_id, display_name, short_bio, collection_area, preferred_payout_method, payout_reference, rating_average, rating_count, completed_sales_count, response_time_hours
) VALUES
(1, 1, 'Nomsa Finds', 'Pre-loved jackets, grocery bundles, and compact furniture for local buyers.', 'Khayelitsha taxi rank pickup', 'bank_transfer', 'ABSA-ENDING-9012', 5.00, 1, 2, 6),
(2, 2, 'Sipho Tech & Baby', 'Affordable electronics and family essentials sold with meetup options.', 'Mitchells Plain Town Centre', 'bank_transfer', 'FNB-ENDING-1148', 4.00, 1, 1, 8),
(3, 3, 'Ayesha Home Store', 'Household basics and cookware for first-time renters and students.', 'Gugulethu Mall', 'cash', NULL, 0.00, 0, 0, 12);

INSERT INTO seller_verifications (
    id, user_id, document_type, document_number, status, reviewer_notes, submitted_at, verified_at
) VALUES
(1, 1, 'south_african_id', '9001015801088', 'approved', 'ID matched the submitted selfie.', DATE_SUB(NOW(), INTERVAL 40 DAY), DATE_SUB(NOW(), INTERVAL 38 DAY)),
(2, 2, 'south_african_id', '9103145405082', 'approved', 'Seller identity confirmed and address verified.', DATE_SUB(NOW(), INTERVAL 25 DAY), DATE_SUB(NOW(), INTERVAL 23 DAY)),
(3, 3, 'proof_of_address', NULL, 'pending', 'Awaiting a clearer proof of address upload.', DATE_SUB(NOW(), INTERVAL 5 DAY), NULL);

INSERT INTO listings (
    id, user_id, category_id, title, description, price, stock_quantity, item_condition, image_url, delivery_available, pickup_available, pickup_township, status
) VALUES
(1, 1, 1, 'Pre-loved denim jacket', 'Clean unisex jacket in great condition. Ideal for winter resale or everyday wear.', 220.00, 2, 'used', 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=900&q=80', 1, 1, 'Khayelitsha', 'active'),
(2, 2, 2, 'Samsung A14 smartphone', 'Dual SIM phone with charger included. Works well for WhatsApp business and photos.', 2300.00, 1, 'used', 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=80', 1, 1, 'Mitchells Plain', 'active'),
(3, 1, 3, 'Compact study desk', 'Strong wooden desk suitable for students or home office corners.', 850.00, 1, 'used', 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=80', 0, 1, 'Khayelitsha', 'active'),
(4, 3, 4, '7-piece cookware set', 'Affordable starter cookware for first-time households or student accommodation.', 499.00, 3, 'new', 'https://images.unsplash.com/photo-1584990347449-a0d4c72f1dd0?auto=format&fit=crop&w=900&q=80', 1, 1, 'Gugulethu', 'active'),
(5, 2, 5, 'Baby travel cot', 'Foldable travel cot with carrying bag. Lightly used and easy to collect.', 780.00, 1, 'used', 'https://images.unsplash.com/photo-1515488764276-beab7607c1e6?auto=format&fit=crop&w=900&q=80', 0, 1, 'Mitchells Plain', 'active'),
(6, 1, 6, 'Bulk spice starter pack', 'Small trader bundle with curry powder, paprika, and mixed herbs for household resale.', 175.00, 10, 'new', 'https://images.unsplash.com/photo-1509358271058-acd22cc93898?auto=format&fit=crop&w=900&q=80', 1, 1, 'Khayelitsha', 'active');

INSERT INTO listing_images (id, listing_id, image_url, sort_order, is_primary) VALUES
(1, 1, 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=900&q=80', 1, 1),
(2, 1, 'https://images.unsplash.com/photo-1523398002811-999ca8dec234?auto=format&fit=crop&w=900&q=80', 2, 0),
(3, 2, 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=80', 1, 1),
(4, 3, 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=80', 1, 1),
(5, 4, 'https://images.unsplash.com/photo-1584990347449-a0d4c72f1dd0?auto=format&fit=crop&w=900&q=80', 1, 1),
(6, 5, 'https://images.unsplash.com/photo-1515488764276-beab7607c1e6?auto=format&fit=crop&w=900&q=80', 1, 1),
(7, 6, 'https://images.unsplash.com/photo-1509358271058-acd22cc93898?auto=format&fit=crop&w=900&q=80', 1, 1),
(8, 6, 'https://images.unsplash.com/photo-1502741338009-cac2772e18bc?auto=format&fit=crop&w=900&q=80', 2, 0);

INSERT INTO orders (
    id, buyer_id, order_reference, subtotal, delivery_fee, service_fee, total_amount, delivery_method, delivery_address, buyer_note, payment_method, payment_status, order_status, created_at
) VALUES
(1, 2, 'CT-20260403-001', 220.00, 65.00, 0.00, 285.00, 'courier', '22 Alpine Road, Portlands, Mitchells Plain, Cape Town', 'Please call before delivery.', 'eft', 'paid', 'completed', DATE_SUB(NOW(), INTERVAL 18 DAY)),
(2, 3, 'CT-20260411-002', 350.00, 0.00, 0.00, 350.00, 'pickup', NULL, 'I will collect after work.', 'cash_on_collection', 'pending', 'ready_for_pickup', DATE_SUB(NOW(), INTERVAL 10 DAY)),
(3, 1, 'CT-20260416-003', 2300.00, 0.00, 0.00, 2300.00, 'pickup', NULL, 'Need it before the weekend if possible.', 'card_demo', 'paid', 'completed', DATE_SUB(NOW(), INTERVAL 5 DAY));

INSERT INTO order_items (
    id, order_id, listing_id, seller_id, quantity, unit_price, item_title, item_image_url, seller_name
) VALUES
(1, 1, 1, 1, 1, 220.00, 'Pre-loved denim jacket', 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=900&q=80', 'Nomsa Jacobs'),
(2, 2, 6, 1, 2, 175.00, 'Bulk spice starter pack', 'https://images.unsplash.com/photo-1509358271058-acd22cc93898?auto=format&fit=crop&w=900&q=80', 'Nomsa Jacobs'),
(3, 3, 2, 2, 1, 2300.00, 'Samsung A14 smartphone', 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=80', 'Sipho Dlamini');

INSERT INTO payments (
    id, order_id, amount, payment_method, gateway_name, transaction_reference, status, paid_at, created_at
) VALUES
(1, 1, 285.00, 'eft', 'Instant EFT Demo', 'EFT-DEMO-1001', 'paid', DATE_SUB(NOW(), INTERVAL 18 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY)),
(2, 2, 350.00, 'cash_on_collection', 'Cash On Collection', 'COC-DEMO-1002', 'pending', NULL, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(3, 3, 2300.00, 'card_demo', 'Card Sandbox', 'CARD-DEMO-1003', 'paid', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY));

INSERT INTO order_status_history (
    id, order_id, status, note, changed_by_user_id, created_at
) VALUES
(1, 1, 'processing', 'Order created and payment confirmed.', 2, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(2, 1, 'out_for_delivery', 'Courier handed the parcel to the buyer route.', 1, DATE_SUB(NOW(), INTERVAL 17 DAY)),
(3, 1, 'completed', 'Buyer confirmed safe delivery.', 2, DATE_SUB(NOW(), INTERVAL 16 DAY)),
(4, 2, 'processing', 'Order created and seller notified for pickup.', 3, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(5, 2, 'ready_for_pickup', 'Seller packed the order for community pickup.', 1, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(6, 3, 'processing', 'Order created with card demo payment.', 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(7, 3, 'completed', 'Buyer collected the phone in person.', 1, DATE_SUB(NOW(), INTERVAL 4 DAY));

INSERT INTO seller_reviews (
    id, order_item_id, seller_id, buyer_id, rating, review_text, created_at
) VALUES
(1, 1, 1, 2, 5, 'Fast handover and the jacket matched the photos.', DATE_SUB(NOW(), INTERVAL 16 DAY)),
(2, 3, 2, 1, 4, 'Phone worked well and collection was easy to arrange.', DATE_SUB(NOW(), INTERVAL 4 DAY));
