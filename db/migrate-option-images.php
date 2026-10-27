<?php
/* Migration for per-option photos (2026-10-27):
     products.opt_images — JSON {"color":{"Red":"uploads/x.jpg"},"flavor":{…},"size":{…}}:
     picking that colour / flavour / size shows that photo.

   Idempotent — safe to run again, and it's the SAME script we run on the server:
     php db/migrate-option-images.php [site root] */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('cli only'); }
$root = $argv[1] ?? dirname(__DIR__);
require $root . '/inc/functions.php';

/* also products.sale_opts — does a product's sale % cover its sizes and colour/flavour extras too (1 = yes) */
$has = fn(string $c) => (bool) val("SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = ?", [$c]);
foreach ([['opt_images', "TEXT NULL AFTER opt_flavor_name"], ['sale_opts', "TINYINT NOT NULL DEFAULT 1 AFTER sale_pct"]] as [$col, $def]) {
    if ($has($col)) { echo "products.$col already there\n"; continue; }
    q("ALTER TABLE products ADD COLUMN $col $def");
    echo "added products.$col\n";
}
