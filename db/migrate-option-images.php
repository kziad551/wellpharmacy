<?php
/* Migration for per-option photos (2026-10-27):
     products.opt_images — JSON {"color":{"Red":"uploads/x.jpg"},"flavor":{…},"size":{…}}:
     picking that colour / flavour / size shows that photo.

   Idempotent — safe to run again, and it's the SAME script we run on the server:
     php db/migrate-option-images.php [site root] */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('cli only'); }
$root = $argv[1] ?? dirname(__DIR__);
require $root . '/inc/functions.php';

$has = (bool) val("SELECT COUNT(*) FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'opt_images'");
if ($has) { echo "products.opt_images already there — nothing to do.\n"; exit; }
q("ALTER TABLE products ADD COLUMN opt_images TEXT NULL AFTER opt_flavor_name");
echo "added products.opt_images\n";
