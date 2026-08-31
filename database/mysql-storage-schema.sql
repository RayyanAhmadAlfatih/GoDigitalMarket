-- MySQL storage schema for gradual data migration and runtime bridge.
-- Jalankan setelah database.sql. Runtime tetap file-based sampai admin mengaktifkan collection secara bertahap.

CREATE TABLE IF NOT EXISTS ugrowth_storage_records (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  collection VARCHAR(80) NOT NULL,
  record_key VARCHAR(190) NOT NULL,
  record_ref VARCHAR(190) NULL,
  record_payload JSON NOT NULL,
  source VARCHAR(40) NOT NULL DEFAULT 'file-migration',
  status VARCHAR(40) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_collection_record_key (collection, record_key),
  KEY idx_collection (collection),
  KEY idx_record_ref (record_ref),
  KEY idx_status (status),
  KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ugrowth_storage_migrations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  migration_key VARCHAR(120) NOT NULL,
  collection VARCHAR(80) NOT NULL,
  source_driver VARCHAR(40) NOT NULL DEFAULT 'file',
  target_driver VARCHAR(40) NOT NULL DEFAULT 'mysql',
  total_records INT UNSIGNED NOT NULL DEFAULT 0,
  migrated_records INT UNSIGNED NOT NULL DEFAULT 0,
  skipped_records INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(40) NOT NULL DEFAULT 'planned',
  notes TEXT NULL,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_migration_key (migration_key),
  KEY idx_collection (collection),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ugrowth_sync_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sync_target VARCHAR(80) NOT NULL,
  collection VARCHAR(80) NOT NULL,
  sync_mode VARCHAR(40) NOT NULL DEFAULT 'manual',
  status VARCHAR(40) NOT NULL DEFAULT 'pending',
  total_records INT UNSIGNED NOT NULL DEFAULT 0,
  synced_records INT UNSIGNED NOT NULL DEFAULT 0,
  error_message TEXT NULL,
  meta JSON NULL,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_target_collection (sync_target, collection),
  KEY idx_status (status),
  KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Typed tables for gradual migration. Most fields use JSON payload columns
-- so old/new module fields can be preserved without breaking shared-hosting installs.
CREATE TABLE IF NOT EXISTS ugrowth_landing_pages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'draft',
  payload JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status (status),
  KEY idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ugrowth_custom_forms (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(190) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'active',
  payload JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ugrowth_form_submissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  form_slug VARCHAR(190) NULL,
  name VARCHAR(190) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(60) NULL,
  source VARCHAR(190) NULL,
  payload JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_form_slug (form_slug),
  KEY idx_created_at (created_at),
  KEY idx_email (email),
  KEY idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ugrowth_inquiries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  inquiry_ref VARCHAR(190) NOT NULL UNIQUE,
  name VARCHAR(190) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(60) NULL,
  status VARCHAR(80) NOT NULL DEFAULT 'Baru',
  source VARCHAR(190) NULL,
  payload JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_status (status),
  KEY idx_source (source),
  KEY idx_created_at (created_at),
  KEY idx_phone (phone),
  KEY idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ugrowth_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_ref VARCHAR(190) NOT NULL UNIQUE,
  customer_name VARCHAR(190) NULL,
  customer_email VARCHAR(190) NULL,
  customer_phone VARCHAR(60) NULL,
  payment_status VARCHAR(80) NULL,
  order_status VARCHAR(80) NULL,
  total BIGINT UNSIGNED NOT NULL DEFAULT 0,
  payload JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_payment_status (payment_status),
  KEY idx_order_status (order_status),
  KEY idx_created_at (created_at),
  KEY idx_customer_phone (customer_phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ugrowth_order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_ref VARCHAR(190) NOT NULL,
  product_slug VARCHAR(190) NULL,
  product_title VARCHAR(255) NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  subtotal BIGINT UNSIGNED NOT NULL DEFAULT 0,
  payload JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_order_ref (order_ref),
  KEY idx_product_slug (product_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ugrowth_payment_proofs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_ref VARCHAR(190) NULL,
  payer_name VARCHAR(190) NULL,
  amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(80) NOT NULL DEFAULT 'pending',
  file_path VARCHAR(255) NULL,
  payload JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_order_ref (order_ref),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ugrowth_buyer_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NULL,
  phone VARCHAR(60) NULL,
  name VARCHAR(190) NULL,
  password_hash VARCHAR(255) NULL,
  status VARCHAR(80) NOT NULL DEFAULT 'active',
  payload JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_email (email),
  KEY idx_phone (phone),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ugrowth_member_access (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  buyer_ref VARCHAR(190) NULL,
  order_ref VARCHAR(190) NULL,
  product_slug VARCHAR(190) NULL,
  access_status VARCHAR(80) NOT NULL DEFAULT 'active',
  starts_at DATETIME NULL,
  expires_at DATETIME NULL,
  payload JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_buyer_ref (buyer_ref),
  KEY idx_order_ref (order_ref),
  KEY idx_product_slug (product_slug),
  KEY idx_access_status (access_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ugrowth_analytics_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_name VARCHAR(120) NULL,
  source VARCHAR(190) NULL,
  page_path VARCHAR(255) NULL,
  session_id VARCHAR(190) NULL,
  payload JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_event_name (event_name),
  KEY idx_created_at (created_at),
  KEY idx_page_path (page_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ugrowth_email_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  recipient VARCHAR(190) NULL,
  subject VARCHAR(255) NULL,
  status VARCHAR(80) NOT NULL DEFAULT 'queued',
  payload JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_recipient (recipient),
  KEY idx_status (status),
  KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ugrowth_activity_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor VARCHAR(190) NULL,
  action VARCHAR(120) NULL,
  target VARCHAR(190) NULL,
  payload JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_actor (actor),
  KEY idx_action (action),
  KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
