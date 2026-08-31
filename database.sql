CREATE TABLE IF NOT EXISTS articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    category VARCHAR(80) NOT NULL DEFAULT 'Umum',
    excerpt VARCHAR(255) NOT NULL,
    image VARCHAR(255) NULL,
    image_alt VARCHAR(180) NULL,
    image_title VARCHAR(180) NULL,
    author VARCHAR(120) NOT NULL DEFAULT 'Admin',
    published_at DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'published',
    reading_time VARCHAR(30) NULL,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    keywords TEXT NULL,
    meta_title VARCHAR(180) NULL,
    meta_description VARCHAR(255) NULL,
    meta_keywords TEXT NULL,
    canonical_url VARCHAR(255) NULL,
    og_title VARCHAR(180) NULL,
    og_description VARCHAR(255) NULL,
    focus_keyword VARCHAR(120) NULL,
    robots VARCHAR(50) NOT NULL DEFAULT 'index, follow',
    breadcrumb_title VARCHAR(180) NULL,
    schema_type VARCHAR(40) NOT NULL DEFAULT 'Article',
    faq_json MEDIUMTEXT NULL,
    whatsapp_label VARCHAR(80) NULL,
    whatsapp_phone VARCHAR(30) NULL,
    whatsapp_text VARCHAR(255) NULL,
    content MEDIUMTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    source VARCHAR(40) NOT NULL DEFAULT 'admin',
    INDEX idx_published_at (published_at),
    INDEX idx_status (status),
    INDEX idx_category (category),
    INDEX idx_featured (featured),
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade untuk database lama dari versi sebelumnya.
ALTER TABLE articles ADD COLUMN IF NOT EXISTS image_alt VARCHAR(180) NULL AFTER image;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS image_title VARCHAR(180) NULL AFTER image_alt;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS meta_title VARCHAR(180) NULL AFTER keywords;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS meta_description VARCHAR(255) NULL AFTER meta_title;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS meta_keywords TEXT NULL AFTER meta_description;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS canonical_url VARCHAR(255) NULL AFTER meta_keywords;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS og_title VARCHAR(180) NULL AFTER canonical_url;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS og_description VARCHAR(255) NULL AFTER og_title;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS focus_keyword VARCHAR(120) NULL AFTER og_description;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS robots VARCHAR(50) NOT NULL DEFAULT 'index, follow' AFTER focus_keyword;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS breadcrumb_title VARCHAR(180) NULL AFTER robots;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS schema_type VARCHAR(40) NOT NULL DEFAULT 'Article' AFTER breadcrumb_title;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS faq_json MEDIUMTEXT NULL AFTER schema_type;

ALTER TABLE articles ADD COLUMN IF NOT EXISTS whatsapp_label VARCHAR(80) NULL AFTER faq_json;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS whatsapp_phone VARCHAR(30) NULL AFTER whatsapp_label;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS whatsapp_text VARCHAR(255) NULL AFTER whatsapp_phone;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS source VARCHAR(40) NOT NULL DEFAULT 'admin' AFTER updated_at;
ALTER TABLE articles ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'published' AFTER published_at;

-- V12 dynamic marketplace products table
CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  source VARCHAR(40) NOT NULL DEFAULT 'admin',
  sku VARCHAR(100) NULL,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  category VARCHAR(120) NULL,
  item_type_key VARCHAR(40) NULL,
  subcategory VARCHAR(120) NULL,
  animal_type VARCHAR(80) NULL,
  breed VARCHAR(120) NULL,
  tier VARCHAR(80) NULL,
  excerpt TEXT NULL,
  description TEXT NULL,
  content LONGTEXT NULL,
  price BIGINT UNSIGNED NOT NULL DEFAULT 0,
  sale_price BIGINT UNSIGNED NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'IDR',
  stock VARCHAR(50) NULL,
  stock_status VARCHAR(50) NOT NULL DEFAULT 'in_stock',
  stock_tracking_enabled TINYINT(1) NOT NULL DEFAULT 0,
  stock_reserved_manual INT UNSIGNED NOT NULL DEFAULT 0,
  stock_low_threshold INT UNSIGNED NOT NULL DEFAULT 3,
  stock_allow_backorder TINYINT(1) NOT NULL DEFAULT 0,
  stock_auto_status TINYINT(1) NOT NULL DEFAULT 1,
  stock_note TEXT NULL,
  weight VARCHAR(100) NULL,
  age VARCHAR(100) NULL,
  location VARCHAR(180) NULL,
  shipping_origin_id VARCHAR(80) NULL,
  shipping_origin_note VARCHAR(220) NULL,
  shipping_rule_mode VARCHAR(40) NOT NULL DEFAULT 'global',
  payment_rule_mode VARCHAR(40) NOT NULL DEFAULT 'global',
  allowed_payment_gateways JSON NULL,
  checkout_rule_note TEXT NULL,
  preorder_note TEXT NULL,
  preorder_eta VARCHAR(120) NULL,
  image TEXT NULL,
  image_alt VARCHAR(255) NULL,
  gallery JSON NULL,
  features JSON NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  whatsapp_text TEXT NULL,
  cta_primary_label VARCHAR(120) NULL,
  cta_secondary_label VARCHAR(120) NULL,
  digital_delivery_type VARCHAR(60) NULL,
  digital_access_mode VARCHAR(60) NULL,
  digital_file_url TEXT NULL,
  digital_access_url TEXT NULL,
  digital_instructions TEXT NULL,
  download_limit INT UNSIGNED NOT NULL DEFAULT 0,
  access_duration_days INT UNSIGNED NOT NULL DEFAULT 0,
  member_area_enabled TINYINT(1) NOT NULL DEFAULT 0,
  course_modules JSON NULL,
  course_modules_raw MEDIUMTEXT NULL,
  license_enabled TINYINT(1) NOT NULL DEFAULT 0,
  license_type VARCHAR(60) NULL,
  license_seats INT UNSIGNED NOT NULL DEFAULT 1,
  license_activation_limit INT UNSIGNED NOT NULL DEFAULT 1,
  license_duration_days INT UNSIGNED NOT NULL DEFAULT 365,
  license_note TEXT NULL,
  license_validation_mode VARCHAR(30) NULL,
  license_domain_lock TINYINT(1) NOT NULL DEFAULT 0,
  central_license_product_id VARCHAR(120) NULL,
  subscription_enabled TINYINT(1) NOT NULL DEFAULT 0,
  subscription_billing_cycle VARCHAR(40) NULL,
  subscription_duration_days INT UNSIGNED NOT NULL DEFAULT 0,
  subscription_grace_days INT UNSIGNED NOT NULL DEFAULT 3,
  subscription_renewal_mode VARCHAR(60) NULL,
  subscription_note TEXT NULL,
  published_at DATETIME NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  seo JSON NULL,
  INDEX idx_products_slug (slug),
  INDEX idx_products_filters (category, animal_type, tier, location, stock_status),
  INDEX idx_products_inventory (stock_status, stock_tracking_enabled, stock_low_threshold),
  INDEX idx_products_item_type (item_type_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Inventory/stock control columns for existing MySQL installations.
-- Run these ALTER statements only if the columns do not exist yet.
-- ALTER TABLE products ADD COLUMN stock_tracking_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER stock_status;
-- ALTER TABLE products ADD COLUMN stock_reserved_manual INT UNSIGNED NOT NULL DEFAULT 0 AFTER stock_tracking_enabled;
-- ALTER TABLE products ADD COLUMN stock_low_threshold INT UNSIGNED NOT NULL DEFAULT 3 AFTER stock_reserved_manual;
-- ALTER TABLE products ADD COLUMN stock_allow_backorder TINYINT(1) NOT NULL DEFAULT 0 AFTER stock_low_threshold;
-- ALTER TABLE products ADD COLUMN stock_auto_status TINYINT(1) NOT NULL DEFAULT 1 AFTER stock_allow_backorder;
-- ALTER TABLE products ADD COLUMN stock_note TEXT NULL AFTER stock_auto_status;
-- ALTER TABLE products ADD INDEX idx_products_inventory (stock_status, stock_tracking_enabled, stock_low_threshold);

-- digital catalog columns for existing MySQL installations.
-- Run these ALTER statements only if the columns do not exist yet.
-- ALTER TABLE products ADD COLUMN item_type_key VARCHAR(40) NULL AFTER category;
-- ALTER TABLE products ADD COLUMN cta_primary_label VARCHAR(120) NULL AFTER whatsapp_text;
-- ALTER TABLE products ADD COLUMN cta_secondary_label VARCHAR(120) NULL AFTER cta_primary_label;
-- ALTER TABLE products ADD COLUMN digital_delivery_type VARCHAR(60) NULL AFTER cta_secondary_label;
-- ALTER TABLE products ADD COLUMN digital_access_mode VARCHAR(60) NULL AFTER digital_delivery_type;
-- ALTER TABLE products ADD COLUMN digital_file_url TEXT NULL AFTER digital_access_mode;
-- ALTER TABLE products ADD COLUMN digital_access_url TEXT NULL AFTER digital_file_url;
-- ALTER TABLE products ADD COLUMN digital_instructions TEXT NULL AFTER digital_access_url;
-- ALTER TABLE products ADD COLUMN download_limit INT UNSIGNED NOT NULL DEFAULT 0 AFTER digital_instructions;
-- ALTER TABLE products ADD COLUMN access_duration_days INT UNSIGNED NOT NULL DEFAULT 0 AFTER download_limit;
-- ALTER TABLE products ADD COLUMN member_area_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER access_duration_days;
-- ALTER TABLE products ADD INDEX idx_products_item_type (item_type_key);


-- multi-origin shipping columns for existing MySQL installations.
-- Run these ALTER statements only if the columns do not exist yet.
-- ALTER TABLE products ADD COLUMN shipping_origin_id VARCHAR(80) NULL AFTER location;
-- ALTER TABLE products ADD COLUMN shipping_origin_note VARCHAR(220) NULL AFTER shipping_origin_id;
-- ALTER TABLE products ADD INDEX idx_products_shipping_origin (shipping_origin_id);


-- commerce rules / product-level checkout policy columns for existing MySQL installations.
-- Run these ALTER statements only if the columns do not exist yet.
-- ALTER TABLE products ADD COLUMN shipping_rule_mode VARCHAR(40) NOT NULL DEFAULT 'global' AFTER shipping_origin_note;
-- ALTER TABLE products ADD COLUMN payment_rule_mode VARCHAR(40) NOT NULL DEFAULT 'global' AFTER shipping_rule_mode;
-- ALTER TABLE products ADD COLUMN allowed_payment_gateways JSON NULL AFTER payment_rule_mode;
-- ALTER TABLE products ADD COLUMN checkout_rule_note TEXT NULL AFTER allowed_payment_gateways;
-- ALTER TABLE products ADD COLUMN preorder_note TEXT NULL AFTER checkout_rule_note;
-- ALTER TABLE products ADD COLUMN preorder_eta VARCHAR(120) NULL AFTER preorder_note;
-- ALTER TABLE products ADD INDEX idx_products_commerce_policy (shipping_rule_mode, payment_rule_mode);


-- member area, course modules, and license access columns for existing MySQL installations.
-- Run these ALTER statements only if the columns do not exist yet.
-- ALTER TABLE products ADD COLUMN course_modules JSON NULL AFTER member_area_enabled;
-- ALTER TABLE products ADD COLUMN course_modules_raw MEDIUMTEXT NULL AFTER course_modules;
-- ALTER TABLE products ADD COLUMN license_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER course_modules_raw;
-- ALTER TABLE products ADD COLUMN license_type VARCHAR(60) NULL AFTER license_enabled;
-- ALTER TABLE products ADD COLUMN license_seats INT UNSIGNED NOT NULL DEFAULT 1 AFTER license_type;
-- ALTER TABLE products ADD COLUMN license_activation_limit INT UNSIGNED NOT NULL DEFAULT 1 AFTER license_seats;
-- ALTER TABLE products ADD COLUMN license_duration_days INT UNSIGNED NOT NULL DEFAULT 365 AFTER license_activation_limit;
-- ALTER TABLE products ADD COLUMN license_note TEXT NULL AFTER license_duration_days;
-- ALTER TABLE products ADD INDEX idx_products_license_enabled (license_enabled);


-- buyer account, domain-locked license bridge, and subscription columns for existing MySQL installations.
-- Run these ALTER statements only if the columns do not exist yet.
-- ALTER TABLE products ADD COLUMN license_validation_mode VARCHAR(30) NULL AFTER license_note;
-- ALTER TABLE products ADD COLUMN license_domain_lock TINYINT(1) NOT NULL DEFAULT 0 AFTER license_validation_mode;
-- ALTER TABLE products ADD COLUMN central_license_product_id VARCHAR(120) NULL AFTER license_domain_lock;
-- ALTER TABLE products ADD COLUMN subscription_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER central_license_product_id;
-- ALTER TABLE products ADD COLUMN subscription_billing_cycle VARCHAR(40) NULL AFTER subscription_enabled;
-- ALTER TABLE products ADD COLUMN subscription_duration_days INT UNSIGNED NOT NULL DEFAULT 0 AFTER subscription_billing_cycle;
-- ALTER TABLE products ADD COLUMN subscription_grace_days INT UNSIGNED NOT NULL DEFAULT 3 AFTER subscription_duration_days;
-- ALTER TABLE products ADD COLUMN subscription_renewal_mode VARCHAR(60) NULL AFTER subscription_grace_days;
-- ALTER TABLE products ADD COLUMN subscription_note TEXT NULL AFTER subscription_renewal_mode;
-- ALTER TABLE products ADD INDEX idx_products_subscription_enabled (subscription_enabled);
