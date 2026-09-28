<?php
/* Migration for: phone layout of the home product sections — how many rows the
   swiper has on a phone (m_rows, 0 = as many as needed), how many cards show across
   (m_per_row, 0 = auto) and how many products a phone shows (m_count, NULL = same as computer).
   Idempotent — safe to run again, and it is the SAME script we run on the server:
     php db/migrate-home-mobile.php /home/inoxtopwp/public_html */
$root = $argv[1] ?? 'c:/xampp/htdocs/wellpharmacy';
require $root . '/inc/functions.php';

$hasCol = fn(string $t, string $c) => (bool) val(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$t, $c]);

$plan = [
    ['m_rows',    "TINYINT NOT NULL DEFAULT 1"],
    ['m_per_row', "DECIMAL(3,1) NOT NULL DEFAULT 0"],
    ['m_count',   "INT NULL DEFAULT NULL"],   // products on a phone; NULL = same as computer, 0 = all
];
foreach ($plan as [$col, $def]) {
    if ($hasCol('home_sections', $col)) { echo "   ok   home_sections.$col (already there)\n"; continue; }
    q("ALTER TABLE home_sections ADD COLUMN $col $def");
    echo "   add  home_sections.$col\n";
}
echo "done\n";
