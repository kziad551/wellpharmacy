-- Phase 4 (2026-10-02): free gift with purchase + seasonal popups.
-- Run ONCE by hand on the server (MySQL 8.0 has no ADD COLUMN IF NOT EXISTS).
-- Every table states its collation: without it MySQL 8 picks utf8mb4_0900_ai_ci,
-- and joins against products.id (utf8mb4_unicode_ci) then fail.

CREATE TABLE IF NOT EXISTS product_gifts (
  id              INT NOT NULL AUTO_INCREMENT,
  product_id      VARCHAR(64)  NOT NULL,                 -- the product that earns the gift
  gift_type       ENUM('product','custom') NOT NULL DEFAULT 'product',
  gift_product_id VARCHAR(64)  DEFAULT NULL,             -- gift_type = product: any catalog product, drafts included
  gift_name       VARCHAR(200) NOT NULL DEFAULT '',      -- gift_type = custom: an item not sold in the store
  gift_image      VARCHAR(500) NOT NULL DEFAULT '',      -- gift_type = custom
  note            VARCHAR(200) NOT NULL DEFAULT '',      -- short line shown to shoppers
  gift_qty        INT NOT NULL DEFAULT 1,
  per_unit        TINYINT(1) NOT NULL DEFAULT 0,          -- 1 = gift_qty for EACH unit bought, 0 = gift_qty per order
  stock           INT DEFAULT NULL,                       -- custom gifts only; NULL = unlimited
  starts_on       DATE DEFAULT NULL,
  ends_on         DATE DEFAULT NULL,                      -- inclusive
  active          TINYINT(1) NOT NULL DEFAULT 1,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gift_product (product_id),
  KEY idx_gift_pid (gift_product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE order_items
  ADD COLUMN is_gift  TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN gift_for VARCHAR(64)  NOT NULL DEFAULT '',   -- parent product id that earned it
  ADD COLUMN image    VARCHAR(500) NOT NULL DEFAULT '';   -- snapshot; set for gift lines

CREATE TABLE IF NOT EXISTS popups (
  id           INT NOT NULL AUTO_INCREMENT,
  name         VARCHAR(120) NOT NULL,                     -- internal, admin only
  theme        VARCHAR(24)  NOT NULL DEFAULT 'custom',    -- preset key, see popup_presets()
  eyebrow      VARCHAR(80)  NOT NULL DEFAULT '',
  headline     VARCHAR(160) NOT NULL DEFAULT '',
  body         TEXT,
  image        VARCHAR(500) NOT NULL DEFAULT '',
  cta_label    VARCHAR(60)  NOT NULL DEFAULT '',
  cta_url      VARCHAR(500) NOT NULL DEFAULT '',
  coupon_code  VARCHAR(40)  NOT NULL DEFAULT '',
  color_bg     VARCHAR(7)   NOT NULL DEFAULT '',          -- '' = preset colour
  color_accent VARCHAR(7)   NOT NULL DEFAULT '',
  color_ink    VARCHAR(7)   NOT NULL DEFAULT '',
  effect       TINYINT(1)   NOT NULL DEFAULT 1,           -- falling decorations
  starts_at    DATETIME DEFAULT NULL,                     -- Beirut wall time, compared with PHP date()
  ends_at      DATETIME DEFAULT NULL,
  frequency    ENUM('once','daily','visit') NOT NULL DEFAULT 'once',
  delay_sec    INT NOT NULL DEFAULT 3,
  pages        ENUM('all','home') NOT NULL DEFAULT 'all',
  priority     INT NOT NULL DEFAULT 0,
  active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_live (active, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
