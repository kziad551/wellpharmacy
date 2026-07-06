-- ============================================================
-- WELL PHARMACY — Phase 2 migration: admin-managed home sections
-- Run ONCE on an existing database (schema.sql already includes these for fresh installs).
--   mysql -u USER -p DBNAME < db/phase2.sql
-- ============================================================
SET NAMES utf8mb4;

-- 1) Home sections: admin-ordered homepage product sections (New Arrivals + per-brand)
CREATE TABLE IF NOT EXISTS home_sections (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  type        ENUM('new_arrivals','brand') NOT NULL DEFAULT 'brand',
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

-- 2) Brand display mode for the homepage "trusted brands" strip
--    NOTE: run once. MySQL has no "ADD COLUMN IF NOT EXISTS"; re-running will error harmlessly.
ALTER TABLE brands ADD COLUMN logo_mode ENUM('auto','logo','name','both') NOT NULL DEFAULT 'auto';

-- 3) Seed sensible default sections (only when none exist yet)
INSERT INTO home_sections (type, eyebrow, title, subtitle, item_count, cols, sort, enabled)
SELECT 'new_arrivals', 'just dropped', '', 'Fresh picks, hand-selected by our pharmacists.', 8, 4, 10, 1
WHERE NOT EXISTS (SELECT 1 FROM home_sections);

INSERT INTO home_sections (type, brand, item_count, cols, sort, enabled)
SELECT 'brand', t.brand, 5, 5, 20, 1
FROM (SELECT brand FROM products WHERE status='active' AND brand<>''
      GROUP BY brand HAVING COUNT(*) >= 2 ORDER BY COUNT(*) DESC, brand) t
WHERE NOT EXISTS (SELECT 1 FROM home_sections WHERE type='brand');
