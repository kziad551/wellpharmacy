<?php
/* ONE-TIME: add homepage brand rails for the 9 remaining K-beauty brands on LIVE.
   Idempotent (skip-if-exists by brand). Self-deletes. Run: /kb-rails-deploy?key=kbrails-2026-07 */
require __DIR__ . '/inc/functions.php';
if (($_GET['key'] ?? '') !== 'kbrails-2026-07') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

$src = __DIR__ . '/db/kb-rails.json';
if (!is_file($src)) exit("MISSING db/kb-rails.json — already run?\n");
$rows = json_decode(file_get_contents($src), true);
if (!$rows) exit("BAD JSON\n");

$added = $skip = 0;
foreach ($rows as $r) {
    if (!val("SELECT COUNT(*) FROM brands WHERE name=?", [$r['brand']])) { echo "!! no brand: {$r['brand']}\n"; continue; }
    if (val("SELECT COUNT(*) FROM home_sections WHERE type='brand' AND brand=?", [$r['brand']])) { $skip++; continue; }
    q("INSERT INTO home_sections (type,brand,eyebrow,title,subtitle,show_title,item_count,cols,enabled,sort)
       VALUES (?,?,?,?,?,?,?,?,?,?)",
      [$r['type'],$r['brand'],$r['eyebrow'],$r['title'],$r['subtitle'],
       $r['show_title'],$r['item_count'],$r['cols'],$r['enabled'],$r['sort']]);
    $added++;
    echo "added rail: {$r['brand']} (sort {$r['sort']})\n";
}
echo "\nadded $added, skipped $skip (already existed)\n";
echo "total brand rails now: " . (int)val("SELECT COUNT(*) FROM home_sections WHERE type='brand'") . "\n";
echo "\nall enabled rails:\n";
foreach (rows("SELECT brand,sort FROM home_sections WHERE type='brand' AND enabled=1 ORDER BY sort") as $r)
    printf("  sort=%-4d %s (%d products)\n", $r['sort'], $r['brand'], (int)val("SELECT COUNT(*) FROM products WHERE brand=?", [$r['brand']]));

@unlink($src);
@unlink(__FILE__);
echo "\nDONE — script + json removed from server.\n";
