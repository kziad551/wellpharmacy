<?php
/* ONE-TIME: publish Beesline on LIVE — flip the 148 draft products to active, point the
   photoless ones at the photo-pending placeholder, apply the photo-first ordering, add the
   homepage rail + feature the brand. Idempotent. Self-deletes. Run: /bees-activate?key=beesact-2026-07 */
require __DIR__ . '/inc/functions.php';
if (($_GET['key'] ?? '') !== 'beesact-2026-07') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(300);

$src = __DIR__ . '/db/bees-activate.json';
if (!is_file($src)) exit("MISSING db/bees-activate.json — already run?\n");
$data = json_decode(file_get_contents($src), true);
if (!$data) exit("BAD JSON\n");

$activeBefore = (int)val("SELECT COUNT(*) FROM products WHERE status='active'");

$upd = 0;
foreach ($data['products'] as $p) {
    if (!val("SELECT COUNT(*) FROM products WHERE id=?", [$p['id']])) continue;
    q("UPDATE products SET status=?, image=?, sort=? WHERE id=?", [$p['status'], $p['image'], $p['sort'], $p['id']]);
    $upd++;
}
q("UPDATE brands SET featured=1 WHERE name='Beesline'");

$rail = $data['rail'];
$railAdded = 0;
if ($rail && !val("SELECT COUNT(*) FROM home_sections WHERE type='brand' AND brand='Beesline'")) {
    q("INSERT INTO home_sections (type,brand,eyebrow,title,subtitle,show_title,item_count,cols,enabled,sort)
       VALUES (?,?,?,?,?,?,?,?,?,?)",
      [$rail['type'],$rail['brand'],$rail['eyebrow'],$rail['title'],$rail['subtitle'],
       $rail['show_title'],$rail['item_count'],$rail['cols'],$rail['enabled'],$rail['sort']]);
    $railAdded = 1;
}

echo "product rows updated: $upd\n";
echo "brand featured      : " . (int)val("SELECT featured FROM brands WHERE name='Beesline'") . "\n";
echo "rail added          : " . ($railAdded ? "yes" : "already existed") . "\n\n";

echo "--- verify ---\n";
printf("Beesline active     : %d\n", (int)val("SELECT COUNT(*) FROM products WHERE brand='Beesline' AND status='active'"));
printf("Beesline draft      : %d  (want 0)\n", (int)val("SELECT COUNT(*) FROM products WHERE brand='Beesline' AND status='draft'"));
printf("  real photo        : %d\n", (int)val("SELECT COUNT(*) FROM products WHERE brand='Beesline' AND image<>'' AND image NOT LIKE '%photo-pending%'"));
printf("  photo-pending     : %d\n", (int)val("SELECT COUNT(*) FROM products WHERE brand='Beesline' AND image LIKE '%photo-pending%'"));
printf("  price=0 (coming soon): %d\n", (int)val("SELECT COUNT(*) FROM products WHERE brand='Beesline' AND price<=0"));
printf("total active products (site): %d  (was %d)\n", (int)val("SELECT COUNT(*) FROM products WHERE status='active'"), $activeBefore);
printf("brand rails now     : %d\n", (int)val("SELECT COUNT(*) FROM home_sections WHERE type='brand'"));

@unlink($src);
@unlink(__FILE__);
echo "\nDONE — script + json removed from server.\n";
