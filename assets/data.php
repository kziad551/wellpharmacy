<?php
/* ============================================================
   WELL PHARMACY — dynamic catalog (drop-in replacement for data.js)
   Loaded via <script src="assets/data.php?v=..."></script>.
   Rebuilds window.WELL from the database so chrome.js renders
   the EXACT same markup, but every product is now editable in admin.
   ============================================================ */
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/customer.php';
header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: private, no-store');   // carries who is signed in — never cache publicly

/* who's shopping (null for guests) + whatever they've saved to their account */
$me = current_customer();
$USER = $me ? [
    'id'    => (int) $me['id'],
    'first' => $me['first_name'],
    'name'  => trim($me['first_name'] . ' ' . $me['last_name']),
    'email' => $me['email'],
    'phone' => $me['phone'],
    'address' => $me['address'],
    'governorate' => $me['governorate'],
    'city'  => $me['city'],
    'wish'  => wishlist_ids((int) $me['id']),
    'cart'  => cart_rows((int) $me['id']),
] : null;

$JE = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

$products = [];
foreach (rows("SELECT * FROM products WHERE status='active' ORDER BY sort, id") as $p) {
    $products[] = [
        'id'      => $p['id'],
        'brand'   => $p['brand'],
        'name'    => $p['name'],
        'price'   => (float)$p['price'],
        'badge'   => $p['badge'] ?: '',
        'rating'  => (float)$p['rating'],
        'reviews' => (int)$p['reviews'],
        'cat'     => $p['category'],
        'img'     => $p['image'],
        'hover'   => $p['hover_image'],
        'gallery' => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($p['gallery'] ?? ''))))),
        'kw'      => $p['kw'],
        'desc'    => $p['descr'],
        'keywords'=> $p['keywords'] ?? '',
        'size'    => $p['size'] ?? '',
        'was'     => $p['was'] !== null ? (float)$p['was'] : null,
        'sale'    => $p['sale_pct'] !== null ? (int)$p['sale_pct'] : null,
        'stock'   => (int)$p['stock'],
        'low'     => (int)$p['low_stock'],
    ];
}
$navCats = array_column(rows("SELECT name FROM categories WHERE in_nav=1 ORDER BY sort"), 'name');
$NAV = array_merge(['Shop All', 'Brands', 'Offers'], $navCats);

$cats = [];
foreach (rows("SELECT name, image, is_cross FROM categories ORDER BY sort") as $c) {
    $cats[] = ['name' => $c['name'], 'img' => $c['image'], 'cross' => (bool)$c['is_cross']];
}
$brands = array_column(rows("SELECT name FROM brands ORDER BY featured DESC, sort"), 'name');

$SET = [
    'store_name'   => setting('store_name', 'WELL SHOP'),
    'tagline'      => setting('store_tagline', 'where Wellness meets You!'),
    'whatsapp'     => setting('whatsapp_number', '9613627766'),
    'phone'        => setting('store_phone', ''),
    'address'      => setting('store_address', ''),
    'free_ship'    => (float) setting('free_ship_threshold', '49'),
    'announce_1'   => setting('announce_1', 'FREE SHIPPING on orders above $49'),
    'announce_2'   => setting('announce_2', 'Authentic Products • Expert Care • Secure Checkout'),
    'footer_about' => setting('footer_about', ''),
    'currency'     => setting('currency_label', '$ USD'),
    'social'       => [
        'instagram' => setting('social_instagram', ''),
        'tiktok'    => setting('social_tiktok', ''),
        'facebook'  => setting('social_facebook', ''),
        'youtube'   => setting('social_youtube', ''),
        'pinterest' => setting('social_pinterest', ''),
    ],
];
?>
/* WELL PHARMACY — dynamic catalog (generated from DB) */
(function (W) {
  'use strict';
  const U = (id, w) => { const url = `https://images.unsplash.com/photo-${id}?auto=format&fit=crop&w=${w||800}&q=80`; return (typeof window !== 'undefined' && window.__resources && window.__resources[url]) || url; };

  const IMG = {
    heroModel:   U('1576426863848-c21f53c60b19', 1100),
    heroSerum:   U('1612817288484-6f916006741a', 1100),
    quizFace:    U('1502823403499-6ccfcf4fb453', 700),
    pharmacist:  U('1559839734-2b71ea197ec2', 1000),
    teamWoman:   U('1582750433449-648ed127bb54', 500),
    appHand:     U('1556656793-08538906a9f8', 800),
    journalFlat: U('1583209814683-c023dd293cc6', 1000),
    aboutShop:   U('1587854692152-cbe660dbde88', 1000),
    texture1:    U('1601049541289-9b1b7bbbfe19', 800),
    texture2:    U('1608248597279-f99d160bfcbc', 800),
    citrus:      U('1611080626919-7cf5a9dbab5b', 800),
    flatlay1:    U('1583209814683-c023dd293cc6', 900),
    flatlay2:    U('1596755389378-c31d21fd1273', 900),
    catSkin:     U('1620916566398-39f1143ab7be', 400),
    catHair:     U('1522338242992-e1a54906a8da', 400),
    catWellness: U('1584017911766-d451b3d0e843', 400),
    catMakeup:   U('1596462502278-27bfdc403348', 400),
    catPersonal: U('1556228453-efd6c1ff04f6', 400),
    catBaby:     U('1515488042361-ee00e0ddd4e4', 400),
    catSexual:   U('1571875257727-256c39da42af', 400),
    catBrands:   U('1612817288484-6f916006741a', 400),
    blog1:       U('1556228720-195a672e8a03', 800),
    blog2:       U('1620916297397-a4a5402a3c6c', 800),
    blog3:       U('1522335789203-aabd1fc54bc9', 800),
  };
  const SHOTS = ['1556228720-195a672e8a03','1620916566398-39f1143ab7be','1601049541289-9b1b7bbbfe19','1611930022073-b7a4ba5fcccd','1571781926291-c477ebfd024b','1598440947619-2c35fc9aa908','1556228720-195a672e8a03','1620916566398-39f1143ab7be','1601049541289-9b1b7bbbfe19','1611930022073-b7a4ba5fcccd','1571781926291-c477ebfd024b','1598440947619-2c35fc9aa908'];
  const shot = (i) => U(SHOTS[i % SHOTS.length], 600);

  const BADGE = {
    derm:    { cls:'badge-derm',    label:'DERM PICK' },
    best:    { cls:'badge-best',    label:'BESTSELLER' },
    trend:   { cls:'badge-trend',   label:'TRENDING' },
    trusted: { cls:'badge-trusted', label:'TRUSTED' },
    new:     { cls:'badge-new',     label:'NEW' },
    vegan:   { cls:'badge-vegan',   label:'VEGAN' },
    ff:      { cls:'badge-ff',      label:'FRAG-FREE' },
  };
  const AV = [
    U('1494790108377-be9c29b29330',120), U('1500648767791-00dcc994a43e',120),
    U('1438761681033-6461ffad8d80',120), U('1507003211169-0a1dd7228f2d',120),
    U('1534528741775-53994a69daeb',120), U('1531123897727-8f129e1688ce',120),
  ];

  /* ---- dynamic (from database) ---- */
  W.PRODUCTS   = <?= json_encode($products, $JE) ?>;
  const byId = {}; W.PRODUCTS.forEach(p => byId[p.id] = p); W.BY_ID = byId;
  W.NAV        = <?= json_encode($NAV, $JE) ?>;
  W.CATEGORIES = <?= json_encode($cats, $JE) ?>;
  W.BRANDS     = <?= json_encode($brands, $JE) ?>;
  W.SETTINGS   = <?= json_encode($SET, $JE) ?>;
  W.USER       = <?= json_encode($USER, $JE) ?>;   // null = guest (guests can still order)

  W.IMG = IMG; W.BADGE = BADGE; W.AV = AV; W.shot = shot; W.U = U;
})(window.WELL = window.WELL || {});
