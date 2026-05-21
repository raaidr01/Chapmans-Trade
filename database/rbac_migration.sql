USE chapmans_trade;

CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(60) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    admin_area_access TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(80) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    module VARCHAR(80) NOT NULL DEFAULT 'general',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
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

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission
        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, slug, description, admin_area_access, sort_order) VALUES
('Buyer', 'buyer', 'Marketplace role for purchasing products and tracking orders.', 0, 10),
('Seller', 'seller', 'Marketplace role for creating listings and receiving orders.', 0, 20),
('Moderator', 'moderator', 'Admin role focused on reviewing listings and seller verification requests.', 1, 30),
('Admin', 'admin', 'Admin role for day-to-day platform management and staff tasks.', 1, 40),
('Super Admin', 'super_admin', 'Highest-privilege role with full administrative access and deletion rights.', 1, 50)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    admin_area_access = VALUES(admin_area_access),
    sort_order = VALUES(sort_order);

INSERT INTO permissions (name, slug, description, module) VALUES
('Access admin portal', 'admin.access', 'Allows a user to sign into the admin website.', 'admin'),
('View admin dashboard', 'admin.dashboard.view', 'Allows viewing the administrative summary dashboard.', 'admin'),
('View users', 'users.view', 'Allows viewing the user directory and individual role assignments.', 'users'),
('Create users', 'users.create', 'Allows creating new users from the admin portal.', 'users'),
('Edit users', 'users.edit', 'Allows updating user details and account states.', 'users'),
('Delete users', 'users.delete', 'Allows deleting user accounts when it is safe to do so.', 'users'),
('View roles', 'roles.view', 'Allows viewing the platform role and permission matrix.', 'roles'),
('Assign roles', 'roles.assign', 'Allows assigning or changing roles for user accounts.', 'roles'),
('View listings', 'listings.view', 'Allows viewing all marketplace listings in the admin portal.', 'listings'),
('Moderate listings', 'listings.moderate', 'Allows changing listing status for moderation or quality control.', 'listings'),
('View orders', 'orders.view', 'Allows viewing all orders placed on the marketplace.', 'orders'),
('View verifications', 'verifications.view', 'Allows viewing seller verification submissions.', 'verifications'),
('Review verifications', 'verifications.review', 'Allows approving or rejecting seller verification submissions.', 'verifications')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    module = VALUES(module);

INSERT INTO users (full_name, email, phone, township, language_pref, password_hash, verification_status, account_status)
SELECT 'Lerato Mokoena', 'superadmin@chapmanstrade.test', '0849001111', 'Johannesburg CBD', 'English', '$2y$10$YAst.tCeQ/pQHYZGh8OKU.4X5PL8q5/J0ucnx65SvWKvKGye.w.2a', 'verified', 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'superadmin@chapmanstrade.test'
);

INSERT INTO users (full_name, email, phone, township, language_pref, password_hash, verification_status, account_status)
SELECT 'Zanele Khumalo', 'admin@chapmanstrade.test', '0849002222', 'Soweto', 'English', '$2y$10$YAst.tCeQ/pQHYZGh8OKU.4X5PL8q5/J0ucnx65SvWKvKGye.w.2a', 'verified', 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'admin@chapmanstrade.test'
);

INSERT INTO users (full_name, email, phone, township, language_pref, password_hash, verification_status, account_status)
SELECT 'Mandla Nene', 'moderator@chapmanstrade.test', '0849003333', 'Tembisa', 'isiZulu', '$2y$10$YAst.tCeQ/pQHYZGh8OKU.4X5PL8q5/J0ucnx65SvWKvKGye.w.2a', 'verified', 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'moderator@chapmanstrade.test'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
INNER JOIN permissions p ON (
    (r.slug = 'moderator' AND p.slug IN ('admin.access', 'admin.dashboard.view', 'listings.view', 'listings.moderate', 'orders.view', 'verifications.view', 'verifications.review'))
    OR
    (r.slug = 'admin' AND p.slug IN ('admin.access', 'admin.dashboard.view', 'users.view', 'users.create', 'users.edit', 'roles.view', 'roles.assign', 'listings.view', 'listings.moderate', 'orders.view', 'verifications.view', 'verifications.review'))
    OR
    (r.slug = 'super_admin' AND p.slug IN ('admin.access', 'admin.dashboard.view', 'users.view', 'users.create', 'users.edit', 'users.delete', 'roles.view', 'roles.assign', 'listings.view', 'listings.moderate', 'orders.view', 'verifications.view', 'verifications.review'))
);

INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id
FROM users u
INNER JOIN roles r ON r.slug = 'buyer'
LEFT JOIN user_roles ur ON ur.user_id = u.id AND ur.role_id = r.id
WHERE ur.user_id IS NULL
  AND u.email NOT IN ('superadmin@chapmanstrade.test', 'admin@chapmanstrade.test', 'moderator@chapmanstrade.test');

INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id
FROM users u
INNER JOIN roles r ON r.slug = 'seller'
LEFT JOIN user_roles ur ON ur.user_id = u.id AND ur.role_id = r.id
WHERE ur.user_id IS NULL
  AND u.email NOT IN ('superadmin@chapmanstrade.test', 'admin@chapmanstrade.test', 'moderator@chapmanstrade.test');

INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id
FROM users u
INNER JOIN roles r ON r.slug = 'super_admin'
LEFT JOIN user_roles ur ON ur.user_id = u.id AND ur.role_id = r.id
WHERE u.email = 'superadmin@chapmanstrade.test'
  AND ur.user_id IS NULL;

INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id
FROM users u
INNER JOIN roles r ON r.slug = 'admin'
LEFT JOIN user_roles ur ON ur.user_id = u.id AND ur.role_id = r.id
WHERE u.email = 'admin@chapmanstrade.test'
  AND ur.user_id IS NULL;

INSERT IGNORE INTO user_roles (user_id, role_id)
SELECT u.id, r.id
FROM users u
INNER JOIN roles r ON r.slug = 'moderator'
LEFT JOIN user_roles ur ON ur.user_id = u.id AND ur.role_id = r.id
WHERE u.email = 'moderator@chapmanstrade.test'
  AND ur.user_id IS NULL;
