-- ============================================================
-- WELL PHARMACY — database schema (MySQL 8 / 5.7+ compatible)
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS brands;
DROP TABLE IF EXISTS journal_posts;
DROP TABLE IF EXISTS coupons;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS admin_users;
DROP TABLE IF EXISTS pages;
DROP TABLE IF EXISTS subscribers;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS home_sections;

-- ---- catalog ------------------------------------------------
CREATE TABLE categories (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL,
  slug        VARCHAR(80)  NOT NULL,
  image       VARCHAR(500) DEFAULT '',
  in_nav      TINYINT      NOT NULL DEFAULT 1,
  is_cross    TINYINT      NOT NULL DEFAULT 0,   -- "Health Conditions" + icon
  is_sale     TINYINT      NOT NULL DEFAULT 0,   -- "Offers/SALE" highlight
  sort        INT          NOT NULL DEFAULT 0,
  UNIQUE KEY uq_cat_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE brands (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  slug        VARCHAR(120) NOT NULL,
  color       VARCHAR(16)  DEFAULT '',           -- signature colour (interim wordmark)
  logo        VARCHAR(500) DEFAULT '',           -- real logo path (overrides wordmark)
  logo_mode   ENUM('auto','logo','name','both') NOT NULL DEFAULT 'auto', -- strip display: auto|logo|name|both
  featured    TINYINT      NOT NULL DEFAULT 0,    -- homepage "trusted brands" strip
  sort        INT          NOT NULL DEFAULT 0,
  UNIQUE KEY uq_brand_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id            VARCHAR(64) PRIMARY KEY,          -- slug id, e.g. 'lumiere-vitc'
  name          VARCHAR(200) NOT NULL,
  brand         VARCHAR(120) NOT NULL DEFAULT '',
  category      VARCHAR(80)  NOT NULL DEFAULT '',
  price         DECIMAL(10,2) NOT NULL DEFAULT 0,
  was           DECIMAL(10,2) NULL,               -- original price if on sale
  sale_pct      INT          NULL,                -- "-20%" badge
  badge         VARCHAR(20)  DEFAULT '',          -- derm|best|trend|trusted|new|vegan|ff
  rating        DECIMAL(2,1) NOT NULL DEFAULT 5.0,
  reviews       INT          NOT NULL DEFAULT 0,
  stock         INT          NOT NULL DEFAULT 0,
  low_stock     INT          NOT NULL DEFAULT 5,  -- "Only X left" threshold
  kw            VARCHAR(60)  DEFAULT '',          -- big overlaid card title
  descr         VARCHAR(180) DEFAULT '',          -- one-line card descriptor
  long_desc     TEXT         NULL,                -- product-page body
  barcode       VARCHAR(32)  NOT NULL DEFAULT '', -- EAN barcode
  sku           VARCHAR(64)  NOT NULL DEFAULT '', -- item number
  size          VARCHAR(48)  NOT NULL DEFAULT '', -- e.g. "150 ml"
  how_to_use    TEXT         NULL,                -- product-page "How to Use" tab
  ingredients   TEXT         NULL,                -- product-page "Ingredients"
  benefits      TEXT         NULL,                -- benefit bullet points
  keywords      TEXT         NULL,                -- extra search terms (not shown)
  image         VARCHAR(500) DEFAULT '',
  hover_image   VARCHAR(500) DEFAULT '',
  gallery       TEXT         NULL,                -- extra product photos (one path/URL per line)
  feat_latest   TINYINT      NOT NULL DEFAULT 0,  -- homepage "latest arrivals" rail
  feat_wellness TINYINT      NOT NULL DEFAULT 0,  -- homepage "shop wellness" rail
  home_sort     INT          NOT NULL DEFAULT 0,
  sort          INT          NOT NULL DEFAULT 0,
  status        ENUM('active','draft') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cat (category),
  KEY idx_status (status),
  KEY idx_feat (feat_latest, feat_wellness)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- admin-managed homepage sections (New Arrivals + per-brand rails) -------
CREATE TABLE home_sections (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  type        ENUM('new_arrivals','brand','category') NOT NULL DEFAULT 'brand',
  brand       VARCHAR(120) NOT NULL DEFAULT '',      -- brand name (matches products.brand) when type='brand'
  eyebrow     VARCHAR(120) NOT NULL DEFAULT '',      -- small label above the title (optional)
  title       VARCHAR(160) NOT NULL DEFAULT '',      -- blank = default ('New Arrivals' / the brand name)
  subtitle    VARCHAR(300) NOT NULL DEFAULT '',      -- optional line under the title
  show_title  TINYINT      NOT NULL DEFAULT 1,       -- allow hiding the whole title
  item_count  INT          NOT NULL DEFAULT 5,       -- how many products; 0 = all
  cols        INT          NOT NULL DEFAULT 5,       -- products per row (4 or 5)
  enabled     TINYINT      NOT NULL DEFAULT 1,
  sort        INT          NOT NULL DEFAULT 0,
  KEY idx_enabled (enabled, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- editorial ---------------------------------------------
CREATE TABLE journal_posts (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(200) NOT NULL,
  slug         VARCHAR(200) NOT NULL,
  category     VARCHAR(60)  DEFAULT '',
  excerpt      VARCHAR(300) DEFAULT '',
  body         MEDIUMTEXT   NULL,
  image        VARCHAR(500) DEFAULT '',
  author       VARCHAR(120) DEFAULT '',
  read_min     INT          NOT NULL DEFAULT 5,
  status       ENUM('published','draft') NOT NULL DEFAULT 'published',
  published_at DATE         NULL,
  sort         INT          NOT NULL DEFAULT 0,
  UNIQUE KEY uq_post_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- promotions --------------------------------------------
CREATE TABLE coupons (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(40)  NOT NULL,
  type        ENUM('percent','fixed','freeship') NOT NULL DEFAULT 'percent',
  value       DECIMAL(10,2) NOT NULL DEFAULT 0,
  min_spend   DECIMAL(10,2) NOT NULL DEFAULT 0,
  expires_at  DATE         NULL,
  usage_limit INT          NULL,
  used_count  INT          NOT NULL DEFAULT 0,
  active      TINYINT      NOT NULL DEFAULT 1,
  UNIQUE KEY uq_coupon_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- orders -------------------------------------------------
CREATE TABLE orders (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  order_no       VARCHAR(20)  NOT NULL,
  customer_name  VARCHAR(160) NOT NULL,
  email          VARCHAR(160) DEFAULT '',
  phone          VARCHAR(40)  NOT NULL,
  address        VARCHAR(400) NOT NULL,
  governorate    VARCHAR(80)  DEFAULT '',
  city           VARCHAR(120) DEFAULT '',
  payment_method ENUM('cod','areeba') NOT NULL DEFAULT 'cod',
  payment_status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  order_status   ENUM('new','confirmed','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'new',
  subtotal       DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount       DECIMAL(10,2) NOT NULL DEFAULT 0,
  shipping       DECIMAL(10,2) NOT NULL DEFAULT 0,
  total          DECIMAL(10,2) NOT NULL DEFAULT 0,
  coupon_code    VARCHAR(40)  DEFAULT '',
  notes          TEXT         NULL,
  gateway_ref    VARCHAR(120) DEFAULT '',         -- Areeba/MPGS order/session ref
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_order_no (order_no),
  KEY idx_status (order_status),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  order_id    INT NOT NULL,
  product_id  VARCHAR(64) NOT NULL,
  name        VARCHAR(200) NOT NULL,
  brand       VARCHAR(120) DEFAULT '',
  price       DECIMAL(10,2) NOT NULL,
  qty         INT NOT NULL,
  line_total  DECIMAL(10,2) NOT NULL,
  KEY idx_order (order_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- key/value settings: theme tokens, store info, homepage content -------
CREATE TABLE settings (
  skey   VARCHAR(80) PRIMARY KEY,
  sval   TEXT,
  sgroup VARCHAR(40) NOT NULL DEFAULT 'general'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- admin login -------------------------------------------
CREATE TABLE admin_users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name          VARCHAR(120) DEFAULT '',
  role          ENUM('admin','staff') NOT NULL DEFAULT 'admin',
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admin_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- editable content pages (Shipping, Returns, FAQ, Privacy, Terms…) -------
CREATE TABLE pages (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  slug    VARCHAR(120) NOT NULL,
  title   VARCHAR(200) NOT NULL,
  intro   VARCHAR(400) DEFAULT '',
  body    MEDIUMTEXT   NULL,                 -- HTML
  status  ENUM('published','draft') NOT NULL DEFAULT 'published',
  sort    INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_page_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- newsletter subscribers --------------------------------------
CREATE TABLE subscribers (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(160) NOT NULL,
  source     VARCHAR(40)  DEFAULT 'popup',
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sub_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- contact form messages ---------------------------------------
CREATE TABLE messages (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(160) NOT NULL,
  email      VARCHAR(160) DEFAULT '',
  phone      VARCHAR(40)  DEFAULT '',
  topic      VARCHAR(80)  DEFAULT '',
  order_no   VARCHAR(40)  DEFAULT '',
  body       TEXT         NOT NULL,
  is_read    TINYINT      NOT NULL DEFAULT 0,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- product reviews (real customer reviews drive rating + count) -------
CREATE TABLE reviews (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  product_id  VARCHAR(64)  NOT NULL,
  author      VARCHAR(120) NOT NULL,
  rating      TINYINT      NOT NULL,          -- 1..5
  title       VARCHAR(160) DEFAULT '',
  body        TEXT         NOT NULL,
  status      ENUM('published','hidden') NOT NULL DEFAULT 'published',
  reviewer_token VARCHAR(64) DEFAULT NULL,        -- per-browser identity (guest, no accounts): 1 editable review per product
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_prod (product_id, status),
  KEY idx_rev_tok (product_id, reviewer_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
