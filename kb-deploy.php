<?php
/* ONE-TIME: import the Korean-skincare catalogue (412 products / 12 brands) into the
   LIVE db, plus 3 homepage brand rails. Idempotent + resumable: every write is
   skip-if-exists, so a half-run can just be re-run. Deletes itself when finished.
   Run:  /kb-deploy?key=kbeauty-2026-07 */
require __DIR__ . '/inc/functions.php';

if (($_GET['key'] ?? '') !== 'kbeauty-2026-07') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(600);

$src = __DIR__ . '/db/kb-build.json';
if (!is_file($src)) exit("MISSING db/kb-build.json — already run?\n");
$data = json_decode(file_get_contents($src), true);
if (!$data) exit("BAD JSON\n");

$before = (int)val("SELECT COUNT(*) FROM products");
echo "products before: $before\n\n";

/* ---------- brands ---------- */
$nb = 0;
foreach ($data['brands'] as $b) {
    if (val("SELECT COUNT(*) FROM brands WHERE name=?", [$b['name']])) continue;
    q("INSERT INTO brands (name,slug,color,logo,featured,sort,logo_mode) VALUES (?,?,?,?,?,?,?)",
      [$b['name'], $b['slug'], $b['color'], $b['logo'], $b['featured'], $b['sort'], $b['logo_mode']]);
    $nb++;
}
echo "brands inserted: $nb\n";

/* ---------- products ---------- */
$cols = ['id','name','brand','category','price','stock','low_stock','kw','descr','long_desc',
         'barcode','sku','size','how_to_use','ingredients','benefits','keywords','arabic',
         'image','hover_image','gallery','feat_latest','feat_wellness','home_sort','sort','status'];
$ph  = implode(',', array_fill(0, count($cols), '?'));
$sql = "INSERT INTO products (" . implode(',', $cols) . ") VALUES ($ph)";

$np = $sp = 0; $noimg = [];
foreach ($data['products'] as $p) {
    if (val("SELECT COUNT(*) FROM products WHERE id=?", [$p['id']])) { $sp++; continue; }
    $args = [];
    foreach ($cols as $c) $args[] = $p[$c];
    q($sql, $args);
    $np++;
    if (!is_file(__DIR__ . '/' . $p['image'])) $noimg[] = $p['id'];
}
echo "products inserted: $np   skipped(existing): $sp\n";

/* ---------- homepage rails ---------- */
$nr = 0;
foreach ($data['rails'] as $r) {
    if (val("SELECT COUNT(*) FROM home_sections WHERE type='brand' AND brand=?", [$r['brand']])) continue;
    q("INSERT INTO home_sections (type,brand,eyebrow,title,subtitle,show_title,item_count,cols,enabled,sort)
       VALUES (?,?,?,?,?,?,?,?,?,?)",
      [$r['type'], $r['brand'], $r['eyebrow'], $r['title'], $r['subtitle'],
       $r['show_title'], $r['item_count'], $r['cols'], $r['enabled'], $r['sort']]);
    $nr++;
}
echo "rails inserted: $nr\n";

/* ---------- verification (PHP-side checks only — live MySQL REGEXP differs) ---------- */
$after = (int)val("SELECT COUNT(*) FROM products");
echo "\nproducts after: $after   (delta " . ($after - $before) . ")\n";

$names = array_column($data['brands'], 'name');
$in = "'" . implode("','", array_map(fn($b) => str_replace("'", "''", $b), $names)) . "'";
echo "k-beauty rows : " . (int)val("SELECT COUNT(*) FROM products WHERE brand IN ($in)") . "\n";

$html = $ar = 0;
foreach (rows("SELECT name,descr,long_desc FROM products WHERE brand IN ($in)") as $r) {
    $t = $r['name'] . $r['descr'] . $r['long_desc'];
    if (preg_match('/<[a-zA-Z\/]/', $t)) $html++;
    if (preg_match('/[\x{0600}-\x{06FF}]/u', $t)) $ar++;
}
echo "html in text  : $html\nara" . "bic in text : $ar\n";
echo "images missing on server: " . count($noimg) . " " . implode(',', array_slice($noimg, 0, 5)) . "\n";

echo "\nper brand:\n";
foreach (rows("SELECT brand, COUNT(*) c FROM products WHERE brand IN ($in) GROUP BY brand ORDER BY c DESC") as $r)
    printf("  %-20s %d\n", $r['brand'], $r['c']);
echo "\nper category:\n";
foreach (rows("SELECT category, COUNT(*) c FROM products GROUP BY category ORDER BY c DESC") as $r)
    printf("  %-20s %d\n", $r['category'], $r['c']);

/* ---------- self-destruct ---------- */
@unlink($src);
@unlink(__FILE__);
echo "\nDONE — script + json removed from the server.\n";
