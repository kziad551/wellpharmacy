<?php
/* ONE-TIME: the k-beauty import omitted `rating`, so those 412 rows took the schema
   default of 5.0 while every other product uses 0 until it has real reviews. That made
   them the only products matching the "4 stars & up" filter and sorted them to the top
   of "sort by rating" — a 5-star claim with zero reviews. Reset them to 0.
   Idempotent. Self-deletes.  Run: /kb-rating-fix?key=kbrating-2026-07 */
require __DIR__ . '/inc/functions.php';

if (($_GET['key'] ?? '') !== 'kbrating-2026-07') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

$KB = ['COSRX','Medicube','SKIN1004','Axis-Y','Anua','Equallberry',
       'Beauty of Joseon','APRILSKIN','Dr. Melaxin','Biodance','Some By Mi','Dr.Althea'];
$in = "'" . implode("','", array_map(fn($b) => str_replace("'", "''", $b), $KB)) . "'";

echo "before: rating<>0 with 0 reviews = " . (int)val("SELECT COUNT(*) FROM products WHERE rating<>0 AND reviews=0") . "\n";
q("UPDATE products SET rating=0 WHERE brand IN ($in) AND reviews=0 AND rating<>0");
echo "after : rating<>0 with 0 reviews = " . (int)val("SELECT COUNT(*) FROM products WHERE rating<>0 AND reviews=0") . "\n\n";
foreach (rows("SELECT rating, COUNT(*) c FROM products GROUP BY rating ORDER BY c DESC") as $r)
    printf("  rating %-4s : %d products\n", $r['rating'], $r['c']);
echo "\nproducts: " . (int)val("SELECT COUNT(*) FROM products") . "\n";

@unlink(__FILE__);
echo "DONE — script removed from the server.\n";
