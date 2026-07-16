<?php
/* ============================================================
   ONE-TIME, ADDITIVE deploy of the PFDC catalogue to LIVE.
   - creates "Oral Care" category + the 6 brand rows (reuse where present)
   - remaps the pre-existing mis-filed categories (Skincare/Personal Care/Haircare)
   - inserts the 254 new products (skip-if-exists = idempotent/resumable)
   Never DROPs or DELETEs; the existing catalogue is only re-categorised, never removed.
   Guarded by ?key=… and self-deletes on success.
   ============================================================ */
require __DIR__ . '/inc/functions.php';
ini_set('memory_limit', '512M');

$KEY = 'pfdc-2026-golive';
if (($_GET['key'] ?? '') !== $KEY) { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

$build = json_decode(@file_get_contents(__DIR__ . '/db/pfdc-build.json'), true);
if (!is_array($build)) { http_response_code(500); exit("ERROR: db/pfdc-build.json missing\n"); }

$BMAP = [
    'Eau Thermale Avène' => 'Avène', 'Avene' => 'Avène',
    'Rene Furterer' => 'René Furterer', 'A-derma' => 'A-Derma',
    'Klorane' => 'Klorane', 'Ducray' => 'Ducray', 'Elgydium' => 'Elgydium',
];
$BCOLOR = ['Avène'=>'#0d6ab5','Klorane'=>'#8bbf3f','Ducray'=>'#0a3d62','A-Derma'=>'#2e7d5b','René Furterer'=>'#6d4c92','Elgydium'=>'#d81f6a'];
function pf_slug($s){ $s=strtolower($s); $s=strtr($s,['è'=>'e','é'=>'e','ê'=>'e','à'=>'a','â'=>'a','ç'=>'c','ô'=>'o','û'=>'u','î'=>'i','ï'=>'i','ö'=>'o','ü'=>'u']); return trim(preg_replace('/-+/','-',preg_replace('/[^a-z0-9]+/','-',$s)),'-'); }

try {
    $pdo = db();
    $before = (int) val("SELECT COUNT(*) FROM products");

    /* 1) Oral Care category */
    if (!val("SELECT COUNT(*) FROM categories WHERE name=?", ['Oral Care'])) {
        $sort = (int) val("SELECT COALESCE(MAX(sort),0)+1 FROM categories");
        q("INSERT INTO categories (name,slug,in_nav,sort) VALUES (?,?,1,?)", ['Oral Care','oral-care',$sort]);
        echo "+ category Oral Care\n";
    } else echo "= Oral Care exists\n";

    /* 2) brands */
    $existing = array_column(rows("SELECT name FROM brands"), 'name');
    $displayBrands = [];
    foreach ($build as $p) $displayBrands[$BMAP[$p['brand']] ?? $p['brand']] = true;
    $bsort = (int) val("SELECT COALESCE(MAX(sort),0) FROM brands");
    foreach (array_keys($displayBrands) as $bn) {
        if (in_array($bn, $existing, true)) { q("UPDATE brands SET featured=1 WHERE name=?", [$bn]); echo "= brand $bn (featured)\n"; }
        else { $bsort++; q("INSERT INTO brands (name,slug,color,featured,sort,logo_mode) VALUES (?,?,?,1,?, 'name')", [$bn, pf_slug($bn), $BCOLOR[$bn] ?? '#6b4737', $bsort]); echo "+ brand $bn\n"; }
    }

    /* 3) re-file the pre-existing mis-categorised products (idempotent) */
    q("UPDATE products SET category='Hair Care' WHERE category='Haircare'");
    q("UPDATE products SET category='Deodorant' WHERE category='Personal Care'");
    q("UPDATE products SET category='Body Care' WHERE category='Skincare' AND (LOWER(name) LIKE '%foot%' OR LOWER(name) LIKE '% body%' OR LOWER(name) LIKE '%hand%' OR LOWER(name) LIKE '%lipikar%')");
    q("UPDATE products SET category='Face Care' WHERE category='Skincare'");
    echo "re-filed legacy categories\n";

    /* 4) insert the 254 new products (skip-if-exists) */
    $sortBase = (int) val("SELECT COALESCE(MAX(sort),0) FROM products");
    $ins = 0; $skip = 0;
    $pdo->beginTransaction();
    foreach ($build as $i => $p) {
        if (val("SELECT COUNT(*) FROM products WHERE id=?", [$p['id']])) { $skip++; continue; }
        $brand = $BMAP[$p['brand']] ?? $p['brand'];
        $name  = strtr($p['name'], $BMAP);
        $kw    = strtolower(trim($p['range'] ?? ''));
        q("INSERT INTO products
            (id,name,brand,category,price,was,sale_pct,badge,rating,reviews,stock,low_stock,kw,descr,long_desc,barcode,sku,size,how_to_use,ingredients,benefits,keywords,arabic,image,hover_image,gallery,feat_latest,feat_wellness,home_sort,sort,status)
            VALUES (?,?,?,?,?, NULL,NULL,'',0,0, ?,5, ?,?,?, ?,?,?, '','','', ?, '', ?,?,?, 0,0,0, ?, 'active')",
            [$p['id'],$name,$brand,$p['category'],$p['price'], $p['stock'], $kw,$p['descr'],$p['long_desc'],
             $p['barcode'],$p['sku'],$p['size'], $p['keywords'], $p['image'],$p['hover_image'],$p['gallery'], $sortBase+$i+1]);
        $ins++;
    }
    $pdo->commit();

    $after = (int) val("SELECT COUNT(*) FROM products");
    $arabic = (int) val("SELECT COUNT(*) FROM products WHERE name REGEXP '[\\x{0600}-\\x{06FF}]' OR descr REGEXP '[\\x{0600}-\\x{06FF}]'");
    echo "\ninserted: $ins | skipped(existing): $skip\n";
    echo "products $before -> $after\n";
    echo "arabic-in-displayed: $arabic (want 0)\n";
    echo "legacy categories remaining (want 0): " . (int) val("SELECT COUNT(*) FROM products WHERE category IN ('Skincare','Personal Care','Haircare')") . "\n";

    @unlink(__DIR__ . '/db/pfdc-build.json');
    @unlink(__FILE__);
    echo "\nOK — deploy complete; script + build.json removed from the server.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo "FAILED: " . $e->getMessage() . "\n";
}
