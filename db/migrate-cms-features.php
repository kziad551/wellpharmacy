<?php
/* Migration for the 2026-10-12 CMS features:
     1. brands.category            — optional category a brand belongs to (groups the brands page)
     2. products.feat_trending     — "show in homepage Trending" flag (mirrors feat_latest)
     3. home_sections.type         — add the 'trending' section type to the enum (+ create its row)
     4. brands.hero_*              — per-brand hero / offer banner shown at the top of the brand page
                                     (image, title, subtitle, button, 1–2 featured products, accent colour)

   Idempotent — safe to run again, and it's the SAME script we run on the server.
   (An earlier draft put an announcement board on home_sections; that was moved to the brand page,
    so this also drops those home_sections.announce_* columns if a dev DB still has them.) */
$root = $argv[1] ?? 'c:/xampp/htdocs/wellpharmacy';
require $root . '/inc/functions.php';

$hasCol = fn(string $t, string $c) => (bool) val(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$t, $c]);
$colType = fn(string $t, string $c) => (string) val(
    "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", [$t, $c]);

/* ---- 1, 2 & 4: ADD COLUMN (table => [[column, definition], ...]) ---- */
$plan = [
    'brands'   => [
        ['category',       "VARCHAR(80)  NOT NULL DEFAULT '' AFTER slug"],
        // per-brand hero / offer banner (brand page only)
        ['hero_enabled',   "TINYINT      NOT NULL DEFAULT 0"],
        ['hero_title',     "VARCHAR(160) NOT NULL DEFAULT ''"],
        ['hero_subtitle',  "VARCHAR(300) NOT NULL DEFAULT ''"],
        ['hero_image',     "VARCHAR(500) NOT NULL DEFAULT ''"],
        ['hero_cta_label', "VARCHAR(80)  NOT NULL DEFAULT ''"],
        ['hero_cta_link',  "VARCHAR(300) NOT NULL DEFAULT ''"],
        ['hero_products',  "TEXT         NULL"],
        ['hero_bg',        "VARCHAR(16)  NOT NULL DEFAULT ''"],
    ],
    'products' => [
        ['feat_trending', "TINYINT NOT NULL DEFAULT 0 AFTER feat_latest"],
    ],
];
foreach ($plan as $table => $cols) {
    echo "-- $table --\n";
    foreach ($cols as [$col, $def]) {
        if ($hasCol($table, $col)) { echo "   skip (exists): $col\n"; continue; }
        q("ALTER TABLE `$table` ADD COLUMN `$col` $def");
        echo "   + column: $col\n";
    }
}

/* ---- cleanup: an earlier draft added these to home_sections; the feature moved to brands ---- */
echo "-- home_sections cleanup (old announcement board) --\n";
foreach (['announce_title','announce_text','announce_cta_label','announce_cta_link'] as $col) {
    if ($hasCol('home_sections', $col)) { q("ALTER TABLE `home_sections` DROP COLUMN `$col`"); echo "   - dropped: $col\n"; }
}

/* ---- 3: add 'trending' to the home_sections.type enum (keep every existing value) ---- */
echo "-- home_sections.type enum --\n";
if (stripos($colType('home_sections', 'type'), "'trending'") !== false) {
    echo "   skip (already has 'trending')\n";
} else {
    q("ALTER TABLE `home_sections`
         MODIFY COLUMN `type` ENUM('new_arrivals','trending','brand','category','mixed')
         NOT NULL DEFAULT 'brand'");
    echo "   + 'trending' added to enum\n";
}

/* ---- create the single Trending Now section (auto-hidden until it has products) ---- */
echo "-- trending section row --\n";
if (val("SELECT COUNT(*) FROM home_sections WHERE type='trending'")) {
    echo "   skip (exists)\n";
} else {
    $sort = (int) val("SELECT sort FROM home_sections WHERE type='new_arrivals' LIMIT 1");
    $sort = $sort ? $sort + 2 : 12;
    q("INSERT INTO home_sections (type,brand,eyebrow,title,subtitle,show_title,item_count,cols,enabled,sort)
       VALUES ('trending','',?,'','',1,5,5,1,?)", ['what everyone is loving', $sort]);
    echo "   + Trending Now section created (sort $sort)\n";
}

echo "\ndone.\n";
