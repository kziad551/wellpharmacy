<?php
/* ============================================================
   Product lookup for admin pickers (the free-gift product on
   product-edit). GET ?q=...[&exclude=<id>] -> up to 20 matches.

   Unlike the storefront's actions/suggest.php this INCLUDES drafts
   (a gift-only SKU is usually kept hidden from the shop) and sends
   admin-ready image paths (asrc'd, placeholder when empty).
   Every word must match the name, brand or id; an exact id match
   ranks first, then names that start with the query.
   ============================================================ */
require __DIR__ . '/inc/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!current_admin()) { http_response_code(403); echo json_encode(['ok' => false]); exit; }

$raw     = input('q');
$q       = is_string($raw) ? trim(mb_substr($raw, 0, 100)) : '';
$rawEx   = input('exclude');
$exclude = is_string($rawEx) ? trim($rawEx) : '';

$items = [];
if ($q !== '') {
    $esc   = fn(string $s) => addcslashes($s, '%_\\');
    $words = array_slice(preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [$q], 0, 5);
    $cond  = []; $args = [];
    foreach ($words as $w) {
        $like = '%' . $esc($w) . '%';
        $cond[] = '(name LIKE ? OR brand LIKE ? OR id LIKE ?)';
        array_push($args, $like, $like, $like);
    }
    if ($exclude !== '') { $cond[] = 'id <> ?'; $args[] = $exclude; }
    array_push($args, $q, $esc($q) . '%', '%' . $esc($q) . '%', $esc($q) . '%');

    $rows = rows("SELECT id, name, brand, image, price, stock, status FROM products
                  WHERE " . implode(' AND ', $cond) . "
                  ORDER BY CASE WHEN id = ? THEN 0 WHEN name LIKE ? THEN 1 WHEN name LIKE ? THEN 2
                                WHEN brand LIKE ? THEN 3 ELSE 4 END,
                           (status = 'active') DESC, name
                  LIMIT 20", $args);
    foreach ($rows as $r) {
        $items[] = [
            'id'     => (string) $r['id'],
            'name'   => (string) $r['name'],
            'brand'  => (string) $r['brand'],
            'img'    => asrc((string) $r['image'] !== '' ? (string) $r['image'] : 'uploads/photo-pending.png'),
            'price'  => (float) $r['price'],
            'stock'  => (int) $r['stock'],
            'status' => (string) $r['status'],
        ];
    }
}

echo json_encode(['ok' => true, 'q' => $q, 'items' => $items], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
