<?php
/* ONE-TIME on LIVE: enable the "Mixed" homepage section type.
   1) add 'mixed' to home_sections.type ENUM   2) add the `brands` column
   3) seed one demo Mixed section so the feature is visible.
   All idempotent. Self-deletes. Run: /mixed-migrate?key=mixed-2026-07 */
require __DIR__ . '/inc/functions.php';
if (($_GET['key'] ?? '') !== 'mixed-2026-07') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

/* 1) enum */
$typeCol = row("SHOW COLUMNS FROM home_sections WHERE Field='type'");
if (strpos((string)$typeCol['Type'], "'mixed'") === false) {
    q("ALTER TABLE home_sections MODIFY COLUMN type ENUM('new_arrivals','brand','category','mixed') NOT NULL DEFAULT 'brand'");
    echo "type ENUM: added 'mixed'\n";
} else { echo "type ENUM: already has 'mixed'\n"; }

/* 2) brands column */
$hasBrands = (int) val("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='home_sections' AND COLUMN_NAME='brands'");
if (!$hasBrands) {
    q("ALTER TABLE home_sections ADD COLUMN brands TEXT NULL AFTER brand");
    echo "brands column: added\n";
} else { echo "brands column: already exists\n"; }

/* 3) demo mixed section (skip if any mixed section already exists) */
if (!val("SELECT COUNT(*) FROM home_sections WHERE type='mixed'")) {
    q("INSERT INTO home_sections (type,brand,brands,eyebrow,title,subtitle,show_title,item_count,cols,enabled,sort)
       VALUES ('mixed','','','handpicked mix','Essentials Edition','A little of everything our pharmacists love.',1,10,5,1,15)");
    echo "demo Mixed section: created (sort 15)\n";
} else { echo "demo Mixed section: a mixed section already exists — skipped\n"; }

echo "\n--- verify ---\n";
echo "type now: " . row("SHOW COLUMNS FROM home_sections WHERE Field='type'")['Type'] . "\n";
foreach (rows("SELECT id,type,title,brands,enabled,sort FROM home_sections WHERE type='mixed'") as $r)
    echo "  mixed section: " . json_encode($r) . "\n";
echo "total enabled sections: " . (int)val("SELECT COUNT(*) FROM home_sections WHERE enabled=1") . "\n";

@unlink(__FILE__);
echo "\nDONE — script removed from server.\n";
