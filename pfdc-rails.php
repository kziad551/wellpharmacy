<?php
/* one-time: add homepage brand rails for the 6 PFDC brands (idempotent, self-deletes) */
require __DIR__ . '/inc/functions.php';
if (($_GET['key'] ?? '') !== 'pfdc-rails-2026') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

$new = [
    ['Avène','soothing thermal spring water','Dermatologist-recommended care for sensitive skin, powered by Avène Thermal Spring Water.'],
    ['Klorane','botanical expertise since 1965','Gentle plant-based hair & skin care, backed by botanical science.'],
    ['Ducray','dermatological hair & skin','Targeted solutions for hair, scalp and skin concerns.'],
    ['A-Derma','born from Rhealba oat','Gentle care for fragile and atopic-prone skin.'],
    ['René Furterer','botanical hair rituals','Premium plant-powered hair & scalp care from Provence.'],
    ['Elgydium','expert oral care','Targeted toothpastes and oral care, from the pharmacy.'],
];
try {
    $s = (int) val("SELECT COALESCE(MAX(sort),0) FROM home_sections");
    foreach ($new as [$b, $ey, $sub]) {
        if (val("SELECT COUNT(*) FROM home_sections WHERE type='brand' AND brand=?", [$b])) { echo "= $b exists\n"; continue; }
        $s += 10;
        q("INSERT INTO home_sections (type,brand,eyebrow,title,subtitle,show_title,item_count,cols,enabled,sort)
           VALUES ('brand',?,?,'',?,1,5,5,1,?)", [$b, $ey, $sub, $s]);
        echo "+ rail $b\n";
    }
    echo "\nbrand rails now: " . (int) val("SELECT COUNT(*) FROM home_sections WHERE type='brand'") . "\n";
    @unlink(__FILE__);
    echo "done; script removed.\n";
} catch (Throwable $e) { http_response_code(500); echo 'FAILED: ' . $e->getMessage() . "\n"; }
