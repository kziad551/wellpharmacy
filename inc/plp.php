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

const PLP_PER_PAGE = 24;

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
    $price = (float) $p['price'];
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

    if ($noPrice) {
        $priceHtml = '<span class="price price-tba">Price coming soon</span>';
    } elseif ($was !== null) {
        $priceHtml = '<span class="price sale"><span class="now">' . well_money($price)
                   . '</span><span class="was">' . well_money($was) . '</span></span>';
    } else {
        $priceHtml = '<span class="price">' . well_money($price) . '</span>';
    }

    $buyPrice = well_money($price) . ($was !== null ? ' <s>' . well_money($was) . '</s>' : '');
    if ($soldOut)      { $addBtn = '<button class="btn" disabled>Sold out</button>';
                         $buyBtn = '<button class="buybtn" disabled>Sold out</button>'; }
    elseif ($noPrice)  { $addBtn = '<button class="btn" disabled>Price coming soon</button>';
                         $buyBtn = '<button class="buybtn" disabled>Price coming soon</button>'; }
    else               { $addBtn = '<button class="btn" data-add="' . e($id) . '">add to bag</button>';
                         $buyBtn = '<button class="buybtn" data-add="' . e($id) . '">buy — ' . $buyPrice . '</button>'; }

    $stockNote = (!$soldOut && $stock <= $low) ? '<span class="pc-stock">Only ' . $stock . ' left</span>' : '';
    $starsHtml = $rev > 0
        ? '<span class="s">' . PLP_STAR . '</span> ' . number_format($rate, 1)
          . ' <span class="muted">(' . number_format($rev) . ')</span>'
        : '<span class="muted" style="font-size:12px">No reviews yet</span>';

    $alt = e(trim($brand . ' ' . $name));
    $href = 'product?id=' . rawurlencode($id);

    return '<article class="pcard' . ($soldOut ? ' is-sold' : '') . ($hover !== '' ? '' : ' no-hover') . '" data-pid="' . e($id) . '">
      <div class="media graded" data-imgwrap>
        <a class="media-link" href="' . e($href) . '" aria-label="' . $alt . '"></a>
        <div class="pc-top"><div class="badge-slot">' . $badges . '</div></div>
        <img class="gimg pc-a" data-grade src="' . e($img) . '" alt="' . $alt . '" loading="lazy">
        ' . ($hover !== '' ? '<img class="gimg pc-b" data-grade src="' . e($hover) . '" alt="" loading="lazy">' : '') . '
        <div class="add">' . $addBtn . '</div>
      </div>
      <div class="body">
        <span class="stars">' . $starsHtml . $stockNote . '</span>
        <a class="name" href="' . e($href) . '">' . e($name) . '</a>
        <div class="pprice">' . $priceHtml . '</div>
        ' . $buyBtn . '
      </div>
    </article>';
}

/* ---- filters -> SQL ------------------------------------------------------- */

function well_plp_sorts(): array {
    return [
        'rec'        => ['Sort: Recommended',    'sort, name'],
        'reviews'    => ['Bestselling',          'reviews DESC, name'],
        'price-asc'  => ['Price: Low to High',   'price ASC, name'],
        'price-desc' => ['Price: High to Low',   'price DESC, name'],
        'rating'     => ['Top Rated',            'rating DESC, name'],
        'discount'   => ['Biggest Discount',     'COALESCE(sale_pct,0) DESC, name'],
    ];
}

/* Returns [sqlWhere, args]. Every value is bound; nothing is concatenated. */
function well_plp_where(array $f): array {
    $w = ["status = 'active'"];
    $a = [];
    if (($f['cat'] ?? '') !== '')  { $w[] = 'category = ?';            $a[] = $f['cat']; }
    if (($f['q'] ?? '') !== '')    { $w[] = '(name LIKE ? OR brand LIKE ? OR COALESCE(keywords,"") LIKE ?)';
                                     $like = '%' . $f['q'] . '%'; array_push($a, $like, $like, $like); }
    if (!empty($f['brands']))      { $w[] = 'brand IN (' . implode(',', array_fill(0, count($f['brands']), '?')) . ')';
                                     foreach ($f['brands'] as $b) $a[] = $b; }
    if (($f['max'] ?? null) !== null) { $w[] = 'price <= ?';           $a[] = $f['max']; }
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
