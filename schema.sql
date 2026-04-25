CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    erp_product_id INT UNSIGNED NOT NULL UNIQUE,
    sku VARCHAR(100) NOT NULL DEFAULT '',
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    category_name VARCHAR(150) NOT NULL DEFAULT '',
    brand_name VARCHAR(150) NOT NULL DEFAULT '',
    unit_short_name VARCHAR(20) NOT NULL DEFAULT 'Pc',
    image_url VARCHAR(500) NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    stock_qty DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_products_name (name),
    INDEX idx_products_sku (sku),
    INDEX idx_products_category (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_overrides (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL UNIQUE,
    override_slug VARCHAR(255) NULL,
    override_title VARCHAR(255) NULL,
    override_description TEXT NULL,
    override_image_url VARCHAR(500) NULL,
    override_price DECIMAL(12,2) NULL,
    override_badge VARCHAR(100) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_overrides_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_override_slug (override_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(80) NOT NULL,
    customer_email VARCHAR(255) NULL,
    payment_method VARCHAR(50) NOT NULL,
    notes TEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    erp_sync_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    erp_sell_id VARCHAR(100) NULL,
    sync_error TEXT NULL,
    synced_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_orders_sync (erp_sync_status),
    INDEX idx_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    erp_product_id INT UNSIGNED NOT NULL,
    sku VARCHAR(100) NOT NULL DEFAULT '',
    quantity DECIMAL(12,3) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_order_items_order (order_id),
    INDEX idx_order_items_erp_product (erp_product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
