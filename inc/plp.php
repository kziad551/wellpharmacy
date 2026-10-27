<?php
/* ============================================================
   Server-side product listing: query building + card rendering.

   Replaces the old approach where assets/data.php shipped all 1742 products to
   every visitor and assets/plp.js filtered them in the browser. That cost 12MB of
   PHP memory and a full-catalogue SELECT on EVERY page view, and left the product
   grid empty in the HTML so search engines indexed nothing.

   well_product_card() is a port of W.productCard() in assets/chrome.js. Keep the
   markup in step with it: well.css styles both, and chrome.js binds [data-add].
   Unlike the JS original this escapes admin-entered text, which renders the same
   but is safe if a product name ever contains a quote or angle bracket.
   ============================================================ */

require_once __DIR__ . '/gifts.php';   // gift_for_product() + GIFT_SVG for the card chip

const PLP_PER_PAGE = 50;    // first screen + each infinite-scroll batch

/* Inlined rather than calling well_icon(): the ?partial=1 branch of skincare.php
   returns cards and exits before inc/head.php has loaded inc/chrome.php. */
const PLP_CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
const PLP_STAR = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 .9-5 4.8 1.3 7L12 18l-6.3 3.2L7 14.2 2 9.4l7-.9z"/></svg>';

function well_money($n): string {
    return '$' . number_format(round(((float) $n) * 100) / 100, 2);
}

/* mirrors the BADGE map in assets/data.php */
function well_badge(string $k): ?array {
    static $B = [
        'derm'    => ['badge-derm',    'DERM PICK'],
        'best'    => ['badge-best',    'BESTSELLER'],
        'trend'   => ['badge-trend',   'TRENDING'],
        'trusted' => ['badge-trusted', 'TRUSTED'],
        'new'     => ['badge-new',     'NEW'],
        'vegan'   => ['badge-vegan',   'VEGAN'],
        'ff'      => ['badge-ff',      'FRAG-FREE'],
    ];
    return $B[$k] ?? null;
}

function well_product_card(array $p): string {
    $id    = (string) $p['id'];
    $name  = (string) $p['name'];
    $brand = (string) $p['brand'];
    $img   = (string) $p['image'];
    $hover = (string) ($p['hover_image'] ?? '');
    $price = product_base_price($p);   // the default size's price (or the cheapest priced size when the base is blank)
    $was   = $p['was'] !== null && $p['was'] !== '' ? (float) $p['was'] : null;
    $sale  = $p['sale_pct'] !== null && $p['sale_pct'] !== '' ? (int) $p['sale_pct'] : 0;
    $stock = (int) $p['stock'];
    $low   = (int) $p['low_stock'];
    $rev   = (int) $p['reviews'];
    $rate  = (float) $p['rating'];

    $soldOut = $stock <= 0;
    $noPrice = !($price > 0);

    $badges = '';
    if ($soldOut) $badges .= '<span class="badge badge-out">SOLD OUT</span>';
    if ($sale)    $badges .= '<span class="badge badge-sale">-' . $sale . '%</span>';
    if ($b = well_badge((string) ($p['badge'] ?? ''))) {
        $badges .= '<span class="badge ' . $b[0] . '">' . $b[1] . '</span>';
    }

    /* the card shows the default item's price. When no option sells at it (every flavour or
       colour costs extra), it shows the cheapest one instead, as "from $X" */
    $from    = $noPrice ? $price : variant_from_price($p);
    $fromLbl = $from > $price ? '<span class="from">from</span> ' : '';
    if ($fromLbl !== '') $price = $from;

    if ($noPrice) {
        $priceHtml = '<span class="price price-tba">Price coming soon</span>';
    } elseif ($was !== null) {
        $priceHtml = '<span class="price sale"><span class="now">' . $fromLbl . well_money($price)
                   . '</span><span class="was">' . well_money($was) . '</span></span>';
    } else {
        $priceHtml = '<span class="price">' . $fromLbl . well_money($price) . '</span>';
    }

    $buyPrice = well_money($price) . ($was !== null ? ' <s>' . well_money($was) . '</s>' : '');
    /* a colour or flavour has to be chosen on the product page; sizes are chosen right on the
       card (the chips below), so a sizes-only product adds to the bag from here */
    $needsPage = (bool) (parse_variant_opts($p['opt_colors'] ?? '') || parse_variant_opts($p['opt_flavors'] ?? ''));
    $href     = 'product?id=' . rawurlencode($id);
    if ($soldOut)      { $addBtn = '<button class="btn" disabled>Sold out</button>';
                         $buyBtn = '<button class="buybtn" disabled>Sold out</button>'; }
    elseif ($noPrice)  { $soon   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>Coming soon';
                         $addBtn = '<span class="btn soon" title="Price coming soon">' . $soon . '</span>';
                         $buyBtn = '<span class="buybtn soon" title="Price coming soon">' . $soon . '</span>'; }
    elseif ($needsPage){ /* colours/flavours need a choice — the button opens the product page; the price shows the default */
                         $addBtn = '<a class="btn" href="' . e($href) . '">add to bag</a>';
                         $buyBtn = '<a class="buybtn" href="' . e($href) . '">buy — ' . $buyPrice . '</a>'; }
    else               { $addBtn = '<button class="btn" data-add="' . e($id) . '">add to bag</button>';
                         $buyBtn = '<button class="buybtn" data-add="' . e($id) . '">buy — ' . $buyPrice . '</button>'; }
    $chips = well_card_size_chips($p, $price, $soldOut || $noPrice, $href);

    $stockNote = (!$soldOut && $stock <= $low) ? '<span class="pc-stock">Only ' . $stock . ' left</span>' : '';
    /* free gift chip: the 2nd child of .pc-top so space-between puts it top-right.
       GIFT_SVG, not well_icon(): ?partial=1 renders cards without inc/chrome.php.
       Not shown while the product can't be bought (sold out, price coming soon). */
    $gift     = ($soldOut || $noPrice) ? null : gift_for_product($id);
    $giftChip = $gift ? '<span class="pc-gift" role="img" aria-label="Free gift: ' . e($gift['name']) . '">' . GIFT_SVG . '<span>Gift</span></span>' : '';
    $starsHtml = $rev > 0
        ? '<span class="s">' . PLP_STAR . '</span> ' . number_format($rate, 1)
          . ' <span class="muted">(' . number_format($rev) . ')</span>'
        : '<span class="muted" style="font-size:12px">No reviews yet</span>';

    $alt = e(trim($brand . ' ' . $name));

    return '<article class="pcard' . ($soldOut ? ' is-sold' : '') . ($hover !== '' ? '' : ' no-hover') . '" data-pid="' . e($id) . '">
      <div class="media graded" data-imgwrap>
        <a class="media-link" href="' . e($href) . '" aria-label="' . $alt . '"></a>
        <div class="pc-top"><div class="badge-slot">' . $badges . '</div>' . $giftChip . '</div>
        <img class="gimg pc-a" data-grade src="' . e($img) . '" alt="' . $alt . '" loading="lazy">
        ' . ($hover !== '' ? '<img class="gimg pc-b" data-grade src="' . e($hover) . '" alt="" loading="lazy">' : '') . '
        <div class="add">' . $addBtn . '</div>
      </div>
      <div class="body">
        <span class="stars">' . $starsHtml . $stockNote . '</span>
        <a class="name" href="' . e($href) . '">' . e($name) . '</a>' . $chips . '
        <div class="pprice">' . $priceHtml . '</div>
        ' . $buyBtn . '
      </div>
    </article>';
}

/**
 * Size chips for a card: a product whose sizes set the price ("Box $30 / 1 sheet $3")
 * shows them under its name, so the shopper sees every price at a glance and can pick
 * one right there — chrome.js swaps the card's price and adds that size to the bag.
 * The chip selling at the price the card shows starts selected. Up to 3 chips; the
 * rest wait on the product page ("+N"). Nothing while the product can't be bought.
 * W.productCard() in chrome.js builds the same markup — keep them in step.
 */
function well_card_size_chips(array $p, float $shown, bool $unbuyable, string $href): string {
    $sizes = product_sizes($p);
    if (!$sizes || $unbuyable) return '';
    $base = product_base_price($p);
    $priceOf = fn(array $o): float => $o['price'] !== null ? (float) $o['price'] : $base;
    /* the chip for the shown price must be among the ones drawn: pull it forward if needed */
    $sel = null;
    foreach ($sizes as $i => $o) if (abs($priceOf($o) - $shown) < 0.005) { $sel = $i; break; }
    $show = array_slice($sizes, 0, 3, true);
    if ($sel !== null && !isset($show[$sel])) { array_pop($show); $show[$sel] = $sizes[$sel]; }
    if ($sel === null) $sel = array_key_first($show);
    $html = '';
    $imgs = product_option_images($p)['size'];   // a size with its own photo swaps the card's picture
    foreach ($show as $i => $o) {
        $op = $priceOf($o);
        $im = $imgs[$o['label']] ?? '';
        $html .= '<button type="button" class="pc-opt' . ($i === $sel ? ' on" data-def="1' : '') . '" data-size="' . e($o['label']) . '" data-price="' . number_format($op, 2, '.', '') . '"'
               . ($im !== '' ? ' data-img="' . e($im) . '"' : '') . '>'
               . e($o['label']) . '<b>' . well_money($op) . '</b></button>';
    }
    if (count($sizes) > count($show)) $html .= '<a class="pc-opt more" href="' . e($href) . '" title="All sizes">+' . (count($sizes) - count($show)) . '</a>';
    return '<div class="pc-opts" data-opts>' . $html . '</div>';
}

/* ---- "You may also love" ---------------------------------------------------
   A fresh shuffle of the whole shop — every brand, every shelf — in batches, so the
   product page keeps loading more as the shopper scrolls. The product itself and
   sold-out items (nothing to buy) stay out. The seed fixes the order for one visit,
   so later batches never repeat or skip a product. */
const PLP_RELATED_PER = 15;

function well_related_page(string $excludeId, int $seed, int $page, int $per = PLP_RELATED_PER): array {
    $off = max(0, ($page - 1) * $per);
    return rows("SELECT * FROM products WHERE status = 'active' AND stock > 0 AND id <> ?
                 ORDER BY " . well_plp_shuffle($seed) . " LIMIT " . (int) $per . " OFFSET " . (int) $off, [$excludeId]);
}

/* A seeded shuffle that belongs to each ROW, not to its position in the scan: MySQL's
   RAND(seed) hands out values by scan position, so a product added or taken off sale
   between two batches used to reshuffle everything after it (duplicates and gaps while
   paging). Hashing the id with the seed gives every product its own fixed place. */
function well_plp_shuffle(int $seed): string {
    return 'CRC32(CONCAT(id, "-", ' . (int) $seed . ')), id';
}

function well_related_count(string $excludeId): int {
    return (int) val("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock > 0 AND id <> ?", [$excludeId]);
}

/* ---- filters -> SQL ------------------------------------------------------- */

/* Every order ends in `id`: with LIMIT/OFFSET paging, rows that tie on everything else
   could otherwise come back in a different order per batch (a product shown twice,
   another skipped). "Price coming soon" ($0) items go last in a price sort. */
function well_plp_sorts(): array {
    return [
        'rec'        => ['Sort: Recommended',    'sort, name, id'],
        'reviews'    => ['Bestselling',          'reviews DESC, name, id'],
        'price-asc'  => ['Price: Low to High',   '(price <= 0), price ASC, name, id'],
        'price-desc' => ['Price: High to Low',   'price DESC, name, id'],
        'rating'     => ['Top Rated',            'rating DESC, name, id'],
        'discount'   => ['Biggest Discount',     'COALESCE(sale_pct,0) DESC, name, id'],
    ];
}

/* The search clause, the same way the header's suggestions match (actions/suggest.php):
   every word must appear, each in the name, the brand or the keywords — so what the
   dropdown promises ("see all 12 results") is what the results page shows. Short words
   match at a word start only, so "sa" doesn't light up every "Rosa". */
function well_plp_search_clause(string $q): array {
    $words = array_values(array_filter(explode(' ', mb_strtolower(trim(preg_replace('/\s+/u', ' ', $q)))), 'strlen'));
    $w = []; $a = [];
    foreach ($words as $word) {
        $x = addcslashes($word, '%_\\');
        $conds = [];
        foreach (['name', 'brand', 'COALESCE(keywords,"")'] as $col) {
            if (mb_strlen($word) >= 3) { $conds[] = "$col LIKE ?"; $a[] = "%$x%"; }
            else { array_push($conds, "$col LIKE ?", "$col LIKE ?", "$col LIKE ?"); array_push($a, "$x%", "% $x%", "%-$x%"); }
        }
        $w[] = '(' . implode(' OR ', $conds) . ')';
    }
    return [$w ? implode(' AND ', $w) : '1', $a];
}

/* Returns [sqlWhere, args]. Every value is bound; nothing is concatenated. */
function well_plp_where(array $f): array {
    $w = ["status = 'active'"];
    $a = [];
    if (($f['cat'] ?? '') !== '')  { $w[] = 'category = ?';            $a[] = $f['cat']; }
    if (($f['q'] ?? '') !== '')    { [$sw, $sa] = well_plp_search_clause((string) $f['q']); $w[] = "($sw)"; foreach ($sa as $v) $a[] = $v; }
    if (!empty($f['brands']))      { $w[] = 'brand IN (' . implode(',', array_fill(0, count($f['brands']), '?')) . ')';
                                     foreach ($f['brands'] as $b) $a[] = $b; }
    if (($f['max'] ?? null) !== null) { $w[] = 'price <= ? AND price > 0';   $a[] = $f['max']; }   // "under $X" never means "unpriced"
    if (($f['rating'] ?? 0) > 0)   { $w[] = 'rating >= ?';             $a[] = $f['rating']; }
    if (!empty($f['sale']))        { $w[] = '(was IS NOT NULL OR sale_pct IS NOT NULL)'; }
    if (!empty($f['offers']))      { $w[] = '(was IS NOT NULL OR sale_pct IS NOT NULL)'; }
    return ['WHERE ' . implode(' AND ', $w), $a];
}

/* One page of products. LIMIT/OFFSET are inlined as ints because the PDO layer
   runs with EMULATE_PREPARES off, which cannot bind them. */
function well_plp_page(array $f, int $page, int $per = PLP_PER_PAGE): array {
    [$where, $args] = well_plp_where($f);
    $sorts = well_plp_sorts();
    $order = $sorts[$f['sort'] ?? 'rec'][1] ?? $sorts['rec'][1];
    /* "Recommended" = a fresh shuffle on every visit (searches keep their natural order).
       The seed rides along with the next-page requests so later batches never repeat. */
    if (($f['sort'] ?? 'rec') === 'rec' && ($f['q'] ?? '') === '') $order = well_plp_shuffle((int) ($f['seed'] ?? 1));
    $off   = max(0, ($page - 1) * $per);
    return rows("SELECT * FROM products $where ORDER BY $order LIMIT " . (int) $per . " OFFSET " . (int) $off, $args);
}

function well_plp_count(array $f): int {
    [$where, $args] = well_plp_where($f);
    return (int) val("SELECT COUNT(*) FROM products $where", $args);
}

/* Brand list for the sidebar, scoped to everything EXCEPT the brand filter itself
   so the counts stay meaningful while brands are ticked. */
function well_plp_brands(array $f): array {
    $g = $f; unset($g['brands']);
    [$where, $args] = well_plp_where($g);
    return rows("SELECT brand, COUNT(*) c FROM products $where AND brand <> '' GROUP BY brand ORDER BY brand", $args);
}

function well_plp_price_ceiling(array $f): int {
    $g = $f; unset($g['max']);
    [$where, $args] = well_plp_where($g);
    $mx = (float) val("SELECT COALESCE(MAX(price),0) FROM products $where", $args);
    return max(50, (int) ceil($mx / 10) * 10);
}

/* Normalises raw request input into the filter array the functions above expect. */
function well_plp_input(array $validCats, bool $offers = false): array {
    $cat = trim((string) input('cat'));
    if ($cat !== '' && !in_array($cat, $validCats, true)) $cat = '';
    $brands = input('brand');
    if (is_string($brands) && trim($brands) !== '') $brands = [trim($brands)];   // brands page links ?brand=Name
    $brands = is_array($brands) ? array_values(array_filter(array_map('strval', $brands), 'strlen')) : [];
    $max    = input('max');
    $rating = (float) input('rating', 0);
    return [
        'cat'    => $cat,
        'q'      => trim((string) input('q')),
        'brands' => array_slice($brands, 0, 40),
        'max'    => ($max === null || $max === '') ? null : (float) $max,
        'rating' => ($rating > 0 && $rating <= 5) ? $rating : 0,
        'sale'   => input('sale') === '1',
        'offers' => $offers,
        'sort'   => array_key_exists((string) input('sort'), well_plp_sorts()) ? (string) input('sort') : 'rec',
        'seed'   => ($sd = (int) input('seed')) > 0 ? $sd : random_int(1, 2147483646),   // shuffle seed, see well_plp_page()
    ];
}

/* LIKE is substring matching, so "battery" misses products named "BATTERIES".
   Rather than guess at stemming up front (which causes false positives), the page
   runs the real query first and only falls back to this looser form when a search
   returned nothing. Trims common English endings off each word of 4+ chars. */
function well_plp_stem(string $q): string {
    $out = [];
    foreach (preg_split('/\s+/', trim($q)) as $w) {
        if ($w === '') continue;
        $lw = mb_strtolower($w);
        foreach (['ies', 'es', 's', 'y'] as $suf) {
            if (mb_strlen($lw) >= 5 && str_ends_with($lw, $suf)) { $lw = mb_substr($lw, 0, -mb_strlen($suf)); break; }
        }
        $out[] = $lw;
    }
    return implode(' ', $out);
}

/* Rebuilds the querystring, overriding some keys. Used for Load more and filters. */
function well_plp_qs(array $f, array $override = []): string {
    $qs = [];
    if ($f['cat'] !== '')    $qs['cat'] = $f['cat'];
    if ($f['q'] !== '')      $qs['q'] = $f['q'];
    if ($f['brands'])        $qs['brand'] = $f['brands'];
    if ($f['max'] !== null)  $qs['max'] = $f['max'];
    if ($f['rating'])        $qs['rating'] = $f['rating'];
    if ($f['sale'])          $qs['sale'] = '1';
    if ($f['sort'] !== 'rec')$qs['sort'] = $f['sort'];
    foreach ($override as $k => $v) { if ($v === null) unset($qs[$k]); else $qs[$k] = $v; }
    return http_build_query($qs);
}
