CREATE TABLE IF NOT EXISTS admins (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','cashier') NOT NULL DEFAULT 'admin',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 last_login_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS settings (
 setting_key VARCHAR(190) PRIMARY KEY,
 setting_value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS categories (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 description TEXT NULL,
 sort_order INT NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS products (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 category_id INT UNSIGNED NOT NULL,
 name VARCHAR(180) NOT NULL,
 description TEXT NULL,
 price DECIMAL(12,2) NOT NULL DEFAULT 0,
 image_path VARCHAR(255) NULL,
 calories_kcal SMALLINT UNSIGNED NULL,
 prep_time_min SMALLINT UNSIGNED NULL,
 allergen_codes VARCHAR(500) NULL,
 sort_order INT NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_products_category(category_id,is_active,sort_order),
 CONSTRAINT fk_products_category FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS dining_areas (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 sort_order INT NOT NULL DEFAULT 0,
 is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS restaurant_tables (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 area_id INT UNSIGNED NOT NULL,
 name VARCHAR(80) NOT NULL,
 capacity SMALLINT UNSIGNED NOT NULL DEFAULT 4,
 status ENUM('empty','open','reserved','cleaning','disabled') NOT NULL DEFAULT 'empty',
 position_x INT NOT NULL DEFAULT 20,
 position_y INT NOT NULL DEFAULT 20,
 width_px SMALLINT UNSIGNED NOT NULL DEFAULT 130,
 height_px SMALLINT UNSIGNED NOT NULL DEFAULT 90,
 shape ENUM('rectangle','round') NOT NULL DEFAULT 'rectangle',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 UNIQUE KEY uq_area_table(area_id,name),
 CONSTRAINT fk_tables_area FOREIGN KEY(area_id) REFERENCES dining_areas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS staff_users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 username VARCHAR(100) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('waiter','manager') NOT NULL DEFAULT 'waiter',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 last_login_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS table_sessions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 table_id INT UNSIGNED NOT NULL,
 opened_by_staff_id INT UNSIGNED NULL,
 guest_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 status ENUM('open','closed','cancelled') NOT NULL DEFAULT 'open',
 opened_at DATETIME NOT NULL,
 closed_at DATETIME NULL,
 INDEX idx_sessions_status(status,opened_at),
 CONSTRAINT fk_sessions_table FOREIGN KEY(table_id) REFERENCES restaurant_tables(id),
 CONSTRAINT fk_sessions_staff FOREIGN KEY(opened_by_staff_id) REFERENCES staff_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 session_id BIGINT UNSIGNED NOT NULL,
 staff_id INT UNSIGNED NULL,
 status ENUM('draft','submitted','completed','cancelled') NOT NULL DEFAULT 'submitted',
 note VARCHAR(500) NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_orders_session(session_id,status),
 CONSTRAINT fk_orders_session FOREIGN KEY(session_id) REFERENCES table_sessions(id) ON DELETE CASCADE,
 CONSTRAINT fk_orders_staff FOREIGN KEY(staff_id) REFERENCES staff_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS order_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 product_id INT UNSIGNED NULL,
 product_name VARCHAR(180) NOT NULL,
 unit_price DECIMAL(12,2) NOT NULL,
 quantity DECIMAL(8,2) NOT NULL DEFAULT 1,
 item_note VARCHAR(500) NULL,
 status ENUM('active','cancelled','complimentary') NOT NULL DEFAULT 'active',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_items_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_items_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 session_id BIGINT UNSIGNED NOT NULL,
 method ENUM('cash','credit_card','meal_card','transfer','other') NOT NULL,
 amount DECIMAL(12,2) NOT NULL,
 received_by_admin_id INT UNSIGNED NOT NULL,
 note VARCHAR(500) NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_payments_session(session_id,created_at),
 CONSTRAINT fk_payments_session FOREIGN KEY(session_id) REFERENCES table_sessions(id),
 CONSTRAINT fk_payments_admin FOREIGN KEY(received_by_admin_id) REFERENCES admins(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_type ENUM('admin','staff','system') NOT NULL,
 actor_id BIGINT UNSIGNED NULL,
 action VARCHAR(120) NOT NULL,
 context_json LONGTEXT NULL,
 ip_hash CHAR(64) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_audit_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
