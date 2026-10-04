-- Phase 5 (2026-10-02): flavour options on products + per-variant saved bags.
-- Run ONCE by hand on the server (MySQL 8.0 has no ADD COLUMN IF NOT EXISTS).

-- A third option group next to colours and sizes. Same line format as opt_colors:
-- one option per line, "Label" or "Label|surcharge" (surcharge added on top of the price).
-- opt_flavor_name is what the shop calls the group ('' = "Flavour"; e.g. "Scent").
ALTER TABLE products
  ADD COLUMN opt_flavors     TEXT NULL AFTER opt_sizes,
  ADD COLUMN opt_flavor_name VARCHAR(40) NOT NULL DEFAULT '' AFTER opt_flavors;

-- A signed-in shopper's saved bag was unique per (customer, product), so two options of
-- one product (two flavours, or two sizes) collapsed into a single row. Key it per variant.
ALTER TABLE customer_cart
  DROP INDEX uq_cart,
  ADD UNIQUE KEY uq_cart (customer_id, product_id, variant);
