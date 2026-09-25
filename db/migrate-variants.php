<?php
/* Migration for: product variants (colours & sizes), the "sold by" unit, hand-picked
   home-section products, and the variant fields the (dormant) customer cart carries.
   Idempotent — safe to run again, and it is the SAME script we run on the server.

   These columns had been added to the dev DB by hand and never captured in a migration,
   so the live DB was missing them. This backfills them everywhere. */
$root    = $argv[1] ?? 'c:/xampp/htdocs/wellpharmacy';
$siteUrl = $argv[2] ?? 'http://localhost/wellpharmacy';
require $root . '/inc/functions.php';

$hasTable = fn(string $t) => (bool) val(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", [$t]);
$hasCol = fn(string $t, string $c) => (bool) val(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$t, $c]);

/* table => [ [column, definition], ... ] */
$plan = [
    'products' => [
        ['unit',       "VARCHAR(24) NOT NULL DEFAULT ''"],
        ['opt_colors', "TEXT NULL"],
        ['opt_sizes',  "TEXT NULL"],
    ],
    'order_items' => [
        ['variant',    "VARCHAR(160) NOT NULL DEFAULT ''"],
    ],
    'home_sections' => [
        ['product_ids', "TEXT NULL"],
    ],
    'customer_cart' => [
        ['variant', "VARCHAR(160) NOT NULL DEFAULT ''"],
        ['vprice',  "DECIMAL(10,2) NULL"],
    ],
];

foreach ($plan as $table => $cols) {
    echo "-- $table --\n";
    if (!$hasTable($table)) { echo "   skip (no such table)\n"; continue; }
    foreach ($cols as [$col, $def]) {
        if ($hasCol($table, $col)) { echo "   skip (exists): $col\n"; continue; }
        q("ALTER TABLE `$table` ADD COLUMN `$col` $def");
        echo "   + column: $col ($def)\n";
    }
}

echo "\ndone.\n";
