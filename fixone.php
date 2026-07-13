<?php
/* one-time surgical fix: replace the Arabic descr/benefits of the LRP Effaclar
   Micropeeling product with the English text from the source Excel. Self-deletes. */
require __DIR__ . '/inc/functions.php';

$KEY = 'wellfix-2026-08265';
if (($_GET['key'] ?? '') !== $KEY) { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

$id    = 'la-roche-posay-effaclar-micropeeling-cleansing-g-08265';
$descr = 'Effaclar Micropeeling gel helps remove dead skin cells without breakouts.';
$benefits = implode("\n", [
    'Effaclar Micropeeling gel helps remove dead skin cells without breakouts.',
    'It also helps to remove impurities, get rid of excess sebum, and open pores deeply.',
    'Reduces the appearance of blackheads and controls shine.',
    'Leaves skin soft, clean and refreshed.',
]);

$st = db()->prepare('UPDATE products SET descr=?, benefits=? WHERE id=?');
$st->execute([$descr, $benefits, $id]);
echo 'rows updated: ' . $st->rowCount() . "\n";

$p = row('SELECT descr,benefits,name FROM products WHERE id=?', [$id]);
$leak = $p ? preg_match('/[\x{0600}-\x{06FF}]/u', $p['descr'] . $p['benefits'] . $p['name']) : -1;
echo 'this product still has Arabic in displayed fields: ' . ($leak ? 'YES' : 'no') . "\n";

@unlink(__FILE__);
echo "fixone.php removed.\n";
