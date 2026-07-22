<?php
/* ONE-TIME: import the Beesline brand + 148 products into LIVE as DRAFT (hidden from shop,
   editable in admin). The client fills prices/photos/barcodes in admin, then flips to Active.
   Idempotent (skip-if-exists by id). Self-deletes. Run: /bees-deploy?key=beesline-2026-07 */
require __DIR__ . '/inc/functions.php';
if (($_GET['key'] ?? '') !== 'beesline-2026-07') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(300);

$src = __DIR__ . '/db/bees-build.json';
if (!is_file($src)) exit("MISSING db/bees-build.json — already run?\n");
$data = json_decode(file_get_contents($src), true);
if (!$data) exit("BAD JSON\n");

$before = (int)val("SELECT COUNT(*) FROM products");
echo "products before: $before\n";

/* brand */
$b = $data['brand'];
if ($b && !val("SELECT COUNT(*) FROM brands WHERE name=?", [$b['name']])) {
    q("INSERT INTO brands (name,slug,color,logo,featured,sort,logo_mode) VALUES (?,?,?,?,?,?,?)",
      [$b['name'],$b['slug'],$b['color'],$b['logo'],$b['featured'],$b['sort'],$b['logo_mode']]);
    echo "brand Beesline: inserted\n";
} else { echo "brand Beesline: already exists\n"; }

/* products */
$cols = ['id','name','brand','category','price','rating','reviews','stock','low_stock','kw','descr','long_desc',
         'barcode','sku','size','how_to_use','ingredients','benefits','keywords','arabic',
         'image','hover_image','gallery','feat_latest','feat_wellness','home_sort','sort','status'];
$ph  = implode(',', array_fill(0, count($cols), '?'));
$sql = "INSERT INTO products (".implode(',', $cols).") VALUES ($ph)";
$ins = $skip = 0; $noimg = [];
foreach ($data['products'] as $p) {
    if (val("SELECT COUNT(*) FROM products WHERE id=?", [$p['id']])) { $skip++; continue; }
    $args = []; foreach ($cols as $c) $args[] = $p[$c];
    q($sql, $args); $ins++;
    if ($p['image'] && !is_file(__DIR__ . '/' . $p['image'])) $noimg[] = $p['id'];
}
$after = (int)val("SELECT COUNT(*) FROM products");
printf("products inserted: %d   skipped: %d\n", $ins, $skip);
printf("products after: %d  (delta %d)\n\n", $after, $after - $before);

echo "--- verify ---\n";
printf("Beesline total     : %d\n", (int)val("SELECT COUNT(*) FROM products WHERE brand='Beesline'"));
printf("  draft (hidden)   : %d\n", (int)val("SELECT COUNT(*) FROM products WHERE brand='Beesline' AND status='draft'"));
printf("  active (visible) : %d  (want 0 — client flips these on)\n", (int)val("SELECT COUNT(*) FROM products WHERE brand='Beesline' AND status='active'"));
printf("  rating<>0        : %d  (want 0)\n", (int)val("SELECT COUNT(*) FROM products WHERE brand='Beesline' AND rating<>0"));
printf("  with a photo     : %d\n", (int)val("SELECT COUNT(*) FROM products WHERE brand='Beesline' AND image<>''"));
printf("images missing on server: %d %s\n", count($noimg), implode(',', array_slice($noimg,0,4)));
printf("\nLIVE STOREFRONT still active-only: %d active products (Beesline stays hidden until client publishes)\n",
    (int)val("SELECT COUNT(*) FROM products WHERE status='active'"));

@unlink($src);
@unlink(__FILE__);
echo "\nDONE — script + json removed from server.\n";
