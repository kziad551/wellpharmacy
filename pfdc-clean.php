<?php
/* one-time: apply the PFDC data cleanup (strip HTML from descriptions, repair blank/
   paragraph names) to LIVE, keyed by product id. Idempotent, guarded, self-deletes. */
require __DIR__ . '/inc/functions.php';
ini_set('memory_limit', '512M');
if (($_GET['key'] ?? '') !== 'pfdc-clean-2026') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

$clean = json_decode(@file_get_contents(__DIR__ . '/db/pfdc-clean.json'), true);
if (!is_array($clean)) { http_response_code(500); exit("ERROR: db/pfdc-clean.json missing\n"); }

try {
    $pdo = db();
    $pdo->beginTransaction();
    $n = 0;
    foreach ($clean as $id => $v) {
        q("UPDATE products SET name=?, descr=?, long_desc=? WHERE id=?", [$v['name'], $v['descr'], $v['long_desc'], $id]);
        $n++;
    }
    $pdo->commit();
    $PF = "'Avène','Ducray','Klorane','A-Derma','René Furterer','Elgydium'";
    $html = (int) val("SELECT COUNT(*) FROM products WHERE (descr REGEXP '<[a-zA-Z/]' OR long_desc REGEXP '<[a-zA-Z/]') AND brand IN ($PF)");
    echo "updated: $n\n";
    echo "HTML remaining in PFDC descriptions: $html (want 0)\n";
    echo "brand-only names remaining: " . (int) val("SELECT COUNT(*) FROM products WHERE brand IN ($PF) AND CHAR_LENGTH(TRIM(name))<8") . "\n";
    @unlink(__DIR__ . '/db/pfdc-clean.json');
    @unlink(__FILE__);
    echo "done; script + data removed.\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo 'FAILED: ' . $e->getMessage() . "\n";
}
