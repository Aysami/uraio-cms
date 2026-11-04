-- Uraio Business OS — canonical database schema
-- MySQL 8 / MariaDB 10, InnoDB, utf8mb4
-- This file is the source of truth. install.php applies it idempotently.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  module VARCHAR(80) NOT NULL DEFAULT 'core'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL UNIQUE,
  role_id INT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(40) NULL,
  password_hash VARCHAR(255) NOT NULL,
  avatar VARCHAR(500) NULL,
  is_super_admin TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','suspended','pending') NOT NULL DEFAULT 'active',
  email_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  INDEX idx_users_role (role_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  INDEX idx_sessions_user (user_id),
  INDEX idx_sessions_token (token_hash),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_verifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(40) NOT NULL DEFAULT 'email',
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_verif_token (token_hash),
  CONSTRAINT fk_verif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reset_token (token_hash),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  successful TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attempts_email (email, attempted_at),
  INDEX idx_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bucket VARCHAR(190) NOT NULL UNIQUE,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  window_start DATETIME NOT NULL,
  expires_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(500) NULL,
  icon VARCHAR(60) NULL,
  category_group VARCHAR(80) NULL,
  is_core TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  sort_order INT NOT NULL DEFAULT 0,
  version VARCHAR(20) NOT NULL DEFAULT '1.0.0',
  config JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS website_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(500) NULL,
  category_group VARCHAR(80) NULL,
  preview_image VARCHAR(500) NULL,
  layout JSON NULL,
  is_premium TINYINT(1) NOT NULL DEFAULT 0,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  description VARCHAR(500) NULL,
  icon VARCHAR(80) NULL,
  image VARCHAR(500) NULL,
  color VARCHAR(20) NULL,
  default_template_id INT UNSIGNED NULL,
  features JSON NULL,
  dashboard_config JSON NULL,
  is_popular TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  source VARCHAR(40) NOT NULL DEFAULT 'uraio',
  source_id VARCHAR(80) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_categories_parent (parent_id),
  INDEX idx_categories_status (status),
  CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_categories_template FOREIGN KEY (default_template_id) REFERENCES website_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS category_modules (
  category_id INT UNSIGNED NOT NULL,
  module_id INT UNSIGNED NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (category_id, module_id),
  CONSTRAINT fk_cm_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
  CONSTRAINT fk_cm_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS businesses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL UNIQUE,
  owner_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  category_id INT UNSIGNED NULL,
  description TEXT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  whatsapp VARCHAR(40) NULL,
  country VARCHAR(80) NOT NULL DEFAULT 'Nigeria',
  state VARCHAR(80) NULL,
  city VARCHAR(120) NULL,
  address VARCHAR(500) NULL,
  logo VARCHAR(500) NULL,
  cover VARCHAR(500) NULL,
  opening_hours JSON NULL,
  socials JSON NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
  timezone VARCHAR(60) NOT NULL DEFAULT 'Africa/Lagos',
  status ENUM('pending','active','suspended','closed') NOT NULL DEFAULT 'active',
  onboarding_step VARCHAR(40) NOT NULL DEFAULT 'category',
  published_at DATETIME NULL,
  embed_token VARCHAR(64) NULL UNIQUE,
  embed_origins TEXT NULL,
  embed_enabled TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  INDEX idx_businesses_owner (owner_id),
  INDEX idx_businesses_category (category_id),
  INDEX idx_businesses_embed (embed_token),
  CONSTRAINT fk_businesses_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_businesses_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_members (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  role ENUM('owner','manager','staff') NOT NULL DEFAULT 'staff',
  permissions JSON NULL,
  status ENUM('active','invited','disabled') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_member (business_id, user_id),
  INDEX idx_members_user (user_id),
  CONSTRAINT fk_members_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_categories (
  business_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (business_id, category_id),
  CONSTRAINT fk_bc_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_bc_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_modules (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  module_id INT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  settings JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_business_module (business_id, module_id),
  CONSTRAINT fk_bm_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_bm_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS business_websites (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL UNIQUE,
  template_id INT UNSIGNED NULL,
  title VARCHAR(190) NULL,
  tagline VARCHAR(255) NULL,
  primary_color VARCHAR(20) NOT NULL DEFAULT '#0f766e',
  secondary_color VARCHAR(20) NOT NULL DEFAULT '#0b1220',
  accent_color VARCHAR(20) NOT NULL DEFAULT '#f59e0b',
  font_heading VARCHAR(80) NOT NULL DEFAULT 'Sora',
  font_body VARCHAR(80) NOT NULL DEFAULT 'Inter',
  logo_position ENUM('left','center','right') NOT NULL DEFAULT 'left',
  homepage_layout VARCHAR(80) NOT NULL DEFAULT 'classic',
  seo_title VARCHAR(190) NULL,
  seo_description VARCHAR(320) NULL,
  og_image VARCHAR(500) NULL,
  custom_css TEXT NULL,
  settings JSON NULL,
  status ENUM('draft','published','unpublished') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_websites_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_websites_template FOREIGN KEY (template_id) REFERENCES website_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS website_pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  website_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL DEFAULT 'home',
  type VARCHAR(60) NOT NULL DEFAULT 'custom',
  meta_title VARCHAR(190) NULL,
  meta_description VARCHAR(320) NULL,
  is_home TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('published','draft') NOT NULL DEFAULT 'published',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_page_slug (business_id, slug),
  INDEX idx_pages_website (website_id),
  CONSTRAINT fk_pages_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_pages_website FOREIGN KEY (website_id) REFERENCES business_websites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS website_sections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_id INT UNSIGNED NOT NULL,
  type VARCHAR(60) NOT NULL,
  title VARCHAR(190) NULL,
  subtitle VARCHAR(500) NULL,
  content JSON NULL,
  settings JSON NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_sections_page (page_id, sort_order),
  CONSTRAINT fk_sections_page FOREIGN KEY (page_id) REFERENCES website_pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS website_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  website_id INT UNSIGNED NOT NULL,
  setting_key VARCHAR(80) NOT NULL,
  setting_value TEXT NULL,
  UNIQUE KEY uq_website_setting (website_id, setting_key),
  CONSTRAINT fk_ws_website FOREIGN KEY (website_id) REFERENCES business_websites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS website_domains (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  domain VARCHAR(190) NOT NULL UNIQUE,
  type ENUM('subdomain','custom') NOT NULL DEFAULT 'custom',
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  verification_token VARCHAR(80) NULL,
  status ENUM('pending','verified','failed') NOT NULL DEFAULT 'pending',
  ssl_status ENUM('none','pending','active','failed') NOT NULL DEFAULT 'none',
  dns_instructions TEXT NULL,
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_domains_business (business_id),
  CONSTRAINT fk_domains_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS website_visits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  referrer VARCHAR(500) NULL,
  user_agent VARCHAR(255) NULL,
  ip_hash CHAR(64) NULL,
  visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_visits_business (business_id, visited_at),
  CONSTRAINT fk_visits_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  whatsapp VARCHAR(40) NULL,
  address VARCHAR(500) NULL,
  notes TEXT NULL,
  tags JSON NULL,
  total_spent DECIMAL(14,2) NOT NULL DEFAULT 0,
  last_order_at DATETIME NULL,
  status ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  INDEX idx_customers_business (business_id),
  INDEX idx_customers_email (business_id, email),
  CONSTRAINT fk_customers_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  description VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  UNIQUE KEY uq_prodcat (business_id, slug),
  CONSTRAINT fk_prodcat_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  sku VARCHAR(80) NULL,
  description TEXT NULL,
  price DECIMAL(14,2) NOT NULL DEFAULT 0,
  compare_price DECIMAL(14,2) NULL,
  cost_price DECIMAL(14,2) NULL,
  type ENUM('product','service','menu','room','course','property') NOT NULL DEFAULT 'product',
  image VARCHAR(500) NULL,
  gallery JSON NULL,
  stock INT NULL,
  track_stock TINYINT(1) NOT NULL DEFAULT 0,
  unit VARCHAR(40) NULL,
  duration_minutes INT NULL,
  attributes JSON NULL,
  status ENUM('active','inactive','draft') NOT NULL DEFAULT 'active',
  featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  UNIQUE KEY uq_product_slug (business_id, slug),
  INDEX idx_products_business (business_id),
  INDEX idx_products_type (business_id, type),
  CONSTRAINT fk_products_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT NOT NULL DEFAULT 0,
  low_stock_threshold INT NOT NULL DEFAULT 5,
  expiry_date DATE NULL,
  batch VARCHAR(80) NULL,
  supplier VARCHAR(190) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_inventory_business (business_id),
  INDEX idx_inventory_product (product_id),
  CONSTRAINT fk_inventory_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_inventory_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  number VARCHAR(40) NOT NULL UNIQUE,
  type ENUM('order','booking','reservation','request') NOT NULL DEFAULT 'order',
  status ENUM('pending','confirmed','processing','ready','completed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  payment_status ENUM('unpaid','paid','partial','refunded') NOT NULL DEFAULT 'unpaid',
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
  channel VARCHAR(40) NOT NULL DEFAULT 'dashboard',
  notes TEXT NULL,
  meta JSON NULL,
  placed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_orders_business (business_id, created_at),
  INDEX idx_orders_status (business_id, status),
  CONSTRAINT fk_orders_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  meta JSON NULL,
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  product_id INT UNSIGNED NULL,
  staff_id INT UNSIGNED NULL,
  reference VARCHAR(40) NOT NULL UNIQUE,
  customer_name VARCHAR(190) NOT NULL,
  customer_email VARCHAR(190) NULL,
  customer_phone VARCHAR(40) NULL,
  booking_date DATE NOT NULL,
  start_time TIME NULL,
  end_time TIME NULL,
  party_size INT NULL,
  status ENUM('pending','confirmed','checked_in','completed','cancelled','no_show') NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  meta JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_bookings_business (business_id, booking_date),
  INDEX idx_bookings_status (business_id, status),
  CONSTRAINT fk_bookings_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_bookings_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_bookings_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  order_id INT UNSIGNED NULL,
  number VARCHAR(40) NOT NULL UNIQUE,
  issue_date DATE NOT NULL,
  due_date DATE NULL,
  status ENUM('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  tax DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_invoices_business (business_id),
  CONSTRAINT fk_invoices_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_invoices_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_invitems_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  role_title VARCHAR(120) NULL,
  bio TEXT NULL,
  photo VARCHAR(500) NULL,
  permissions JSON NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_staff_business (business_id),
  CONSTRAINT fk_staff_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  author_name VARCHAR(190) NOT NULL,
  rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
  title VARCHAR(190) NULL,
  body TEXT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  source VARCHAR(40) NOT NULL DEFAULT 'website',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reviews_business (business_id, status),
  CONSTRAINT fk_reviews_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  subject VARCHAR(190) NULL,
  body TEXT NOT NULL,
  status ENUM('new','read','replied','archived') NOT NULL DEFAULT 'new',
  source VARCHAR(32) NOT NULL DEFAULT 'website',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_messages_business (business_id, status),
  CONSTRAINT fk_messages_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  business_id INT UNSIGNED NULL,
  type VARCHAR(60) NOT NULL DEFAULT 'system',
  title VARCHAR(190) NOT NULL,
  body VARCHAR(500) NULL,
  url VARCHAR(500) NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifications_user (user_id, read_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  variables JSON NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS support_tickets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  subject VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_tickets_status (status),
  CONSTRAINT fk_tickets_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL,
  CONSTRAINT fk_tickets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NULL,
  entity_id VARCHAR(80) NULL,
  meta JSON NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_business (business_id, created_at),
  INDEX idx_activity_user (user_id, created_at),
  CONSTRAINT fk_activity_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(500) NULL,
  price_monthly DECIMAL(14,2) NOT NULL DEFAULT 0,
  price_quarterly DECIMAL(14,2) NOT NULL DEFAULT 0,
  price_annual DECIMAL(14,2) NOT NULL DEFAULT 0,
  currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
  trial_days INT NOT NULL DEFAULT 0,
  limits JSON NULL,
  features JSON NULL,
  is_popular TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_features (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_id INT UNSIGNED NOT NULL,
  feature_key VARCHAR(80) NOT NULL,
  feature_value VARCHAR(255) NULL,
  limit_value INT NULL,
  UNIQUE KEY uq_plan_feature (plan_id, feature_key),
  CONSTRAINT fk_sf_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscriptions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  status ENUM('trialing','active','past_due','canceled','expired') NOT NULL DEFAULT 'trialing',
  billing_cycle ENUM('monthly','quarterly','annual') NOT NULL DEFAULT 'monthly',
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
  starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ends_at DATETIME NULL,
  trial_ends_at DATETIME NULL,
  canceled_at DATETIME NULL,
  meta JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_subscriptions_business (business_id),
  INDEX idx_subscriptions_status (status),
  CONSTRAINT fk_subs_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_subs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_subs_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  subscription_id INT UNSIGNED NULL,
  order_id INT UNSIGNED NULL,
  invoice_id INT UNSIGNED NULL,
  provider ENUM('paystack','flutterwave','manual') NOT NULL DEFAULT 'manual',
  reference VARCHAR(120) NOT NULL UNIQUE,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
  status ENUM('pending','successful','failed','refunded') NOT NULL DEFAULT 'pending',
  paid_at DATETIME NULL,
  meta JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_payments_business (business_id),
  INDEX idx_payments_status (status),
  CONSTRAINT fk_payments_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL,
  CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_payments_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
  CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subscription_id INT UNSIGNED NOT NULL,
  payment_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sub_payment (subscription_id, payment_id),
  CONSTRAINT fk_sp_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
  CONSTRAINT fk_sp_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  code VARCHAR(60) NOT NULL,
  type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
  value DECIMAL(14,2) NOT NULL DEFAULT 0,
  min_order DECIMAL(14,2) NOT NULL DEFAULT 0,
  usage_limit INT NULL,
  used_count INT NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  expires_at DATETIME NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  UNIQUE KEY uq_coupon (business_id, code),
  CONSTRAINT fk_coupons_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  description TEXT NULL,
  price DECIMAL(14,2) NOT NULL DEFAULT 0,
  image VARCHAR(500) NULL,
  dietary VARCHAR(120) NULL,
  prep_time INT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_menu_business (business_id),
  CONSTRAINT fk_menu_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_menu_category FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  room_type VARCHAR(120) NULL,
  description TEXT NULL,
  price DECIMAL(14,2) NOT NULL DEFAULT 0,
  capacity INT NOT NULL DEFAULT 2,
  beds VARCHAR(120) NULL,
  amenities JSON NULL,
  image VARCHAR(500) NULL,
  gallery JSON NULL,
  status ENUM('available','unavailable','maintenance') NOT NULL DEFAULT 'available',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_rooms_business (business_id),
  CONSTRAINT fk_rooms_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NULL,
  plate_number VARCHAR(40) NOT NULL,
  make VARCHAR(80) NULL,
  model VARCHAR(80) NULL,
  year INT NULL,
  vin VARCHAR(80) NULL,
  mileage INT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_vehicles_business (business_id),
  CONSTRAINT fk_vehicles_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_vehicles_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_cards (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  vehicle_id INT UNSIGNED NULL,
  customer_id INT UNSIGNED NULL,
  reference VARCHAR(40) NOT NULL UNIQUE,
  description TEXT NULL,
  status ENUM('open','in_progress','awaiting_parts','completed','cancelled') NOT NULL DEFAULT 'open',
  assigned_staff_id INT UNSIGNED NULL,
  labor_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  parts_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at DATETIME NULL,
  INDEX idx_jobs_business (business_id, status),
  CONSTRAINT fk_jobs_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_jobs_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
  CONSTRAINT fk_jobs_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS properties (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  listing_type ENUM('sale','rent','shortlet','lease') NOT NULL DEFAULT 'sale',
  property_type VARCHAR(80) NULL,
  price DECIMAL(16,2) NOT NULL DEFAULT 0,
  bedrooms INT NULL,
  bathrooms INT NULL,
  area VARCHAR(80) NULL,
  address VARCHAR(500) NULL,
  city VARCHAR(120) NULL,
  state VARCHAR(120) NULL,
  description TEXT NULL,
  images JSON NULL,
  agent_id INT UNSIGNED NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('available','sold','rented','draft') NOT NULL DEFAULT 'available',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_property_slug (business_id, slug),
  INDEX idx_properties_business (business_id, status),
  CONSTRAINT fk_properties_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  code VARCHAR(60) NULL,
  description TEXT NULL,
  fee DECIMAL(14,2) NOT NULL DEFAULT 0,
  duration VARCHAR(80) NULL,
  schedule VARCHAR(190) NULL,
  capacity INT NULL,
  image VARCHAR(500) NULL,
  status ENUM('active','inactive','full') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_courses_business (business_id),
  CONSTRAINT fk_courses_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enrollments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NULL,
  student_name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  status ENUM('pending','enrolled','completed','withdrawn') NOT NULL DEFAULT 'pending',
  amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_enrollments_business (business_id),
  CONSTRAINT fk_enrollments_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_enrollments_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  location VARCHAR(255) NULL,
  image VARCHAR(500) NULL,
  status ENUM('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_events_business (business_id, starts_at),
  CONSTRAINT fk_events_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  body TEXT NOT NULL,
  audience VARCHAR(80) NOT NULL DEFAULT 'public',
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_announcements_business (business_id),
  CONSTRAINT fk_announcements_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS donations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  donor_name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  purpose VARCHAR(190) NULL,
  reference VARCHAR(80) NOT NULL UNIQUE,
  status ENUM('pending','successful','failed') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_donations_business (business_id),
  CONSTRAINT fk_donations_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  goal_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  raised_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('active','completed','paused') NOT NULL DEFAULT 'active',
  image VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_projects_business (business_id),
  CONSTRAINT fk_projects_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS volunteers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  skills VARCHAR(255) NULL,
  availability VARCHAR(190) NULL,
  status ENUM('new','active','inactive') NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_volunteers_business (business_id),
  CONSTRAINT fk_volunteers_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS medicines (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  name VARCHAR(190) NOT NULL,
  generic_name VARCHAR(190) NULL,
  brand VARCHAR(190) NULL,
  category VARCHAR(120) NULL,
  unit VARCHAR(40) NULL,
  price DECIMAL(14,2) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  low_stock_threshold INT NOT NULL DEFAULT 5,
  expiry_date DATE NULL,
  batch VARCHAR(80) NULL,
  supplier VARCHAR(190) NULL,
  prescription_required TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_medicines_business (business_id),
  INDEX idx_medicines_expiry (business_id, expiry_date),
  CONSTRAINT fk_medicines_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_providers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  model VARCHAR(120) NULL,
  api_key TEXT NULL,
  base_url VARCHAR(500) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'inactive',
  priority INT NOT NULL DEFAULT 0,
  config JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_usage (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  provider VARCHAR(60) NOT NULL,
  model VARCHAR(120) NULL,
  purpose VARCHAR(80) NOT NULL,
  tokens_in INT NOT NULL DEFAULT 0,
  tokens_out INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ai_usage_business (business_id, created_at),
  CONSTRAINT fk_ai_usage_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_usage_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS integrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  provider VARCHAR(60) NOT NULL,
  external_id VARCHAR(120) NOT NULL,
  status ENUM('pending','connected','verified','failed') NOT NULL DEFAULT 'pending',
  credentials JSON NULL,
  last_synced_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_integration (provider, external_id),
  INDEX idx_integrations_business (business_id),
  CONSTRAINT fk_integrations_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhook_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(60) NOT NULL,
  event_id VARCHAR(190) NOT NULL,
  payload MEDIUMTEXT NULL,
  status ENUM('received','processed','failed','ignored') NOT NULL DEFAULT 'received',
  error VARCHAR(500) NULL,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_webhook_event (provider, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  setting_type VARCHAR(30) NOT NULL DEFAULT 'string',
  setting_group VARCHAR(60) NOT NULL DEFAULT 'general',
  is_public TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
