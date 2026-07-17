<?php
/* ONE-TIME: apply the gaps we could fill from our OWN files (no client input):
     - 4 Elgydium products were live named only "Elgydium" with no description —
       real name/description/size recovered from the 'Elgydium' sheet of the PFDC file.
     - 148 sizes lifted out of the product names (the k-beauty sheets have no size column).
     - 1 Vichy deodorant photo that was already in items-images/.
   Only UPDATEs rows that exist; idempotent (re-running writes the same values). Self-deletes.
   Run: /catalog-fix?key=catfix-2026-07 */
require __DIR__ . '/inc/functions.php';

if (($_GET['key'] ?? '') !== 'catfix-2026-07') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(300);

$src = __DIR__ . '/db/catalog-fix.json';
if (!is_file($src)) exit("MISSING db/catalog-fix.json — already run?\n");
$data = json_decode(file_get_contents($src), true);
if (!$data) exit("BAD JSON\n");

$cols = ['name','descr','long_desc','size','kw','keywords','image'];
$n = $skip = 0; $noimg = [];
foreach ($data as $r) {
    if (!val("SELECT COUNT(*) FROM products WHERE id=?", [$r['id']])) { $skip++; continue; }
    q("UPDATE products SET name=?,descr=?,long_desc=?,size=?,kw=?,keywords=?,image=? WHERE id=?",
      [$r['name'], $r['descr'], $r['long_desc'], $r['size'], $r['kw'], $r['keywords'], $r['image'], $r['id']]);
    $n++;
    if ($r['image'] && !is_file(__DIR__ . '/' . $r['image'])) $noimg[] = $r['id'];
}
echo "rows updated: $n   skipped (not found): $skip\n";
echo "images referenced but missing on server: " . count($noimg) . "\n\n";

echo "--- verify ---\n";
$bad = 0;
foreach (rows("SELECT name, brand FROM products") as $r) {
    $rest = trim(str_ireplace(explode(' ', $r['brand']), '', $r['name']));
    if (mb_strlen(trim($r['name'])) < 6 || mb_strlen($rest) < 3) $bad++;
}
printf("products named only by their brand : %d  (want 0)\n", $bad);
printf("products with a size               : %d / %d\n",
    (int)val("SELECT COUNT(*) FROM products WHERE size<>''"), (int)val("SELECT COUNT(*) FROM products"));
printf("photo-pending placeholder          : %d\n", (int)val("SELECT COUNT(*) FROM products WHERE image LIKE '%photo-pending%'"));
printf("empty descriptions                 : %d\n", (int)val("SELECT COUNT(*) FROM products WHERE long_desc='' OR long_desc IS NULL"));
printf("total products                     : %d\n", (int)val("SELECT COUNT(*) FROM products"));

echo "\nthe 4 repaired names:\n";
foreach (rows("SELECT name, size FROM products WHERE brand='Elgydium' AND barcode IN
        ('3577056001314','3577056004797','3577056023569','3577057054487')") as $r)
    printf("  %-44s %s\n", $r['name'], $r['size']);

@unlink($src);
@unlink(__FILE__);
echo "\nDONE — script + json removed from the server.\n";
