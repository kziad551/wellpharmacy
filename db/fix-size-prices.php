<?php
/* One-off data fix (idempotent, CLI only): products whose base price is blank (0) while
   their sizes carry prices were "Price coming soon" everywhere, although the owner had
   priced them. The site now sells such a product at its cheapest priced size (see
   product_base_price()), and the editor fills the price in the same way on save; this
   writes that price into the rows that already exist, so the SQL-level price checks
   (home rails, price sort / filter) see it too.

   Same script locally and on the server:  php db/fix-size-prices.php [site root] */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('cli only'); }
$root = $argv[1] ?? dirname(__DIR__);
require $root . '/inc/functions.php';

$n = 0;
foreach (rows("SELECT id, name, price, opt_sizes FROM products WHERE price <= 0 AND COALESCE(opt_sizes,'') <> ''") as $p) {
    $new = product_base_price($p);
    if ($new <= 0) continue;                       // sizes without prices: still genuinely unpriced
    q("UPDATE products SET price = ? WHERE id = ?", [$new, $p['id']]);
    echo str_pad($p['id'], 50), " price 0 -> $new  ({$p['name']})\n";
    $n++;
}
echo "$n product(s) updated.\n";
