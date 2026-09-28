<?php
/* Live search suggestions for the header search box (JSON, GET).

   ?q=t            -> word suggestions ("toothbrush", "tablets"…), matching brands and
                      categories, the first 20 products and the total match count
   ?q=t&offset=20  -> just the next 20 products (the dropdown loads more as you scroll)

   Short queries (1–2 letters) only match at the START of a word, otherwise "t"
   would match nearly every product. From 3 letters on it's a substring match,
   the same as the full results page. Every word typed must match. */
require __DIR__ . '/../inc/functions.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');

const SUGG_PER = 20;

$q      = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) input('q'))), 0, 60);
$offset = max(0, min(2000, (int) input('offset', 0)));
if ($q === '') { echo json_encode(['q' => '', 'total' => 0, 'products' => []]); exit; }

$words = array_values(array_filter(explode(' ', mb_strtolower($q)), 'strlen'));
$likeEsc = fn(string $s) => addcslashes($s, '%_\\');

$w = ["status = 'active'"];
$a = [];
foreach ($words as $word) {
    $x = $likeEsc($word);
    $conds = [];
    foreach (['name', 'brand', 'COALESCE(keywords,"")'] as $col) {
        if (mb_strlen($word) >= 3) { $conds[] = "$col LIKE ?"; $a[] = "%$x%"; }
        else { array_push($conds, "$col LIKE ?", "$col LIKE ?", "$col LIKE ?");
               array_push($a, "$x%", "% $x%", "%-$x%"); }
    }
    $w[] = '(' . implode(' OR ', $conds) . ')';
}
$where = 'WHERE ' . implode(' AND ', $w);

/* names that start with what was typed first, then brands that do, then the rest;
   in-stock before sold-out */
$qx = $likeEsc(mb_strtolower($q));
$order = "CASE WHEN name LIKE ? THEN 0 WHEN brand LIKE ? THEN 1 WHEN name LIKE ? THEN 2 ELSE 3 END,
          (stock > 0) DESC, sort, name";
$oa = ["$qx%", "$qx%", "% $qx%"];

$prods = rows("SELECT id, name, brand, image, price, was, stock FROM products $where ORDER BY $order
               LIMIT " . SUGG_PER . " OFFSET $offset", array_merge($a, $oa));
$out = [
    'q'        => $q,
    'products' => array_map(fn($p) => [
        'id'    => (string) $p['id'],
        'name'  => (string) $p['name'],
        'brand' => (string) $p['brand'],
        'image' => (string) $p['image'],
        'price' => (float) $p['price'],
        'was'   => $p['was'] !== null && $p['was'] !== '' ? (float) $p['was'] : null,
        'out'   => (int) $p['stock'] <= 0,
    ], $prods),
    'next'     => count($prods) === SUGG_PER ? $offset + SUGG_PER : null,
];

if ($offset === 0) {
    $out['total'] = (int) val("SELECT COUNT(*) FROM products $where", $a);

    /* Word completions: the words in matching product names that begin with the
       last word typed, most common first. The earlier words are kept, so
       "la roche p" suggests "la roche posay". */
    $last   = end($words);
    $prefix = count($words) > 1 ? implode(' ', array_slice($words, 0, -1)) . ' ' : '';
    $freq   = [];
    $stop   = array_flip(['the','and','for','with','from','this','that','your','you','all','per','les','des','pour','avec','une','aux']);
    foreach (rows("SELECT name FROM products $where", $a) as $r) {
        $seen = [];
        foreach (preg_split('/[^\p{L}]+/u', mb_strtolower($r['name']), -1, PREG_SPLIT_NO_EMPTY) as $tok) {
            if (mb_strlen($tok) < 3 || isset($seen[$tok])) continue;
            if (!str_starts_with($tok, $last) || $tok === $last || isset($stop[$tok])) continue;
            $seen[$tok] = 1;
            $freq[$tok] = ($freq[$tok] ?? 0) + 1;
        }
    }
    arsort($freq);
    $out['terms'] = array_map(fn($t) => $prefix . $t, array_slice(array_keys($freq), 0, 6));

    $out['brands'] = array_map(fn($r) => ['name' => $r['brand'], 'count' => (int) $r['c']],
        rows("SELECT brand, COUNT(*) c FROM products WHERE status = 'active' AND brand <> ''
              AND (REPLACE(brand,'-',' ') LIKE ? OR REPLACE(brand,'-',' ') LIKE ?)
              GROUP BY brand ORDER BY (REPLACE(brand,'-',' ') LIKE ?) DESC, c DESC LIMIT 4",
             ["$qx%", "% $qx%", "$qx%"]));

    $out['cats'] = array_column(rows("SELECT name FROM categories WHERE name LIKE ? OR name LIKE ? ORDER BY sort LIMIT 3",
                                     ["$qx%", "% $qx%"]), 'name');
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
