<?php
/* Migration for: mobile layout of the home product sections — how many products mobile
   shows (m_count, NULL = same as desktop view, 0 = all), how many per row (m_cols,
   0 = all in one row; rows = products ÷ per row) and the swiper view, i.e. how many are
   on screen at once (m_per_row, 0 = 2.5). m_rows was an earlier idea, now removed.
   Idempotent — safe to run again, and it is the SAME script we run on the server:
     php db/migrate-home-mobile.php /home/inoxtopwp/public_html */
$root = $argv[1] ?? 'c:/xampp/htdocs/wellpharmacy';
require $root . '/inc/functions.php';

$hasCol = fn(string $t, string $c) => (bool) val(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$t, $c]);

$plan = [
    ['m_per_row', "DECIMAL(3,1) NOT NULL DEFAULT 0"],
    ['m_count',   "INT NULL DEFAULT NULL"],
    ['m_cols',    "INT NOT NULL DEFAULT 0"],
];
foreach ($plan as [$col, $def]) {
    if ($hasCol('home_sections', $col)) { echo "   ok   home_sections.$col (already there)\n"; continue; }
    q("ALTER TABLE home_sections ADD COLUMN $col $def");
    echo "   add  home_sections.$col\n";
}
if ($hasCol('home_sections', 'm_rows')) {
    q("ALTER TABLE home_sections DROP COLUMN m_rows");
    echo "   drop home_sections.m_rows (replaced by m_cols)\n";
}
echo "done\n";
