<?php
require __DIR__ . '/inc/functions.php';

$cat   = trim((string) input('cat'));
$brand = trim((string) input('brand'));
$q     = trim((string) input('q'));
$offers = !empty($OFFERS);   // set by offers.php — show only on-sale items

$validCats = array_column(rows("SELECT name FROM categories ORDER BY sort"), 'name');
if ($cat !== '' && !in_array($cat, $validCats, true)) $cat = '';

/* count for the hero */
$cntSql = "SELECT COUNT(*) FROM products WHERE status='active'";
$cntArg = [];
if ($cat !== '')   { $cntSql .= " AND category = ?"; $cntArg[] = $cat; }
if ($q !== '')     { $cntSql .= " AND (name LIKE ? OR brand LIKE ?)"; $cntArg[] = "%$q%"; $cntArg[] = "%$q%"; }
if ($offers)       { $cntSql .= " AND (was IS NOT NULL OR sale_pct IS NOT NULL)"; }
$count = (int) val($cntSql, $cntArg);

$title  = $offers ? 'Offers & Sale' : ($cat !== '' ? $cat : ($q !== '' ? "Search: $q" : 'Shop All'));
$ACTIVE = $offers ? 'Offers' : ($cat !== '' ? $cat : 'Shop All');
$PAGE_TITLE = "$title — " . setting('store_name', 'WELL SHOP');
$USE_PLP = true;

/* category quick-pills */
$pillCats = array_column(rows("SELECT name FROM categories WHERE in_nav=1 ORDER BY sort"), 'name');

include __DIR__ . '/inc/head.php';
?>
<section class="cat-hero">
  <div class="wrap">
    <nav class="crumb"><a href="index">Home</a><span class="sep">›</span><b><?= e($title) ?></b></nav>
    <span class="chip chip-glass"><?= $count ?> product<?= $count===1?'':'s' ?></span>
    <h1 class="h1" style="font-size:52px"><?= e($title) ?></h1>
    <p class="sub">Pharmacist-picked, derm-loved — sourced direct from trusted brands &amp; quality-checked for every wellness goal.</p>
    <div class="subcat-pills" id="subPills">
      <a class="chip <?= $cat===''&&$q===''?'chip-active':'' ?>" href="skincare">All</a>
      <?php foreach ($pillCats as $pc): ?>
        <a class="chip <?= $cat===$pc?'chip-active':'' ?>" href="skincare?cat=<?= urlencode($pc) ?>"><?= e($pc) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div class="plp-toolbar">
  <div class="wrap">
    <button class="btn btn-ghost btn-sm" id="filterToggle" style="height:40px">⚙ Filters</button>
    <div id="chips" class="row wrapf" style="gap:8px"></div>
    <div class="grow"></div>
    <span class="count"><b data-count>0</b> results</span>
    <select class="sortsel" id="sortSel">
      <option value="rec">Sort: Recommended</option>
      <option value="reviews">Bestselling</option>
      <option value="price-asc">Price: Low to High</option>
      <option value="price-desc">Price: High to Low</option>
      <option value="rating">Top Rated</option>
      <option value="discount">Biggest Discount</option>
    </select>
    <div class="viewtoggle">
      <button data-view="grid" class="on" aria-label="Grid"><svg viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></button>
      <button data-view="list" aria-label="List"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg></button>
    </div>
  </div>
</div>

<div class="wrap plp-body">
  <aside class="filters" id="filters">
    <details class="fgroup" open>
      <summary>Concern <span class="ar">▾</span></summary>
      <div class="fbody" id="fConcern"></div>
    </details>
    <details class="fgroup" open>
      <summary>Brand <span class="ar">▾</span></summary>
      <div class="fbody"><input class="fsearch" data-brand-search placeholder="Search brands…"><div id="fBrand"></div></div>
    </details>
    <details class="fgroup" open>
      <summary>Price <span class="ar">▾</span></summary>
      <div class="fbody price-slider">
        <input type="range" min="0" max="50" value="50" data-price>
        <div class="vals"><span>$0</span><span data-price-val>$50</span></div>
      </div>
    </details>
    <details class="fgroup">
      <summary>Skin Type <span class="ar">▾</span></summary>
      <div class="fbody" id="fSkin"></div>
    </details>
    <details class="fgroup">
      <summary>Rating <span class="ar">▾</span></summary>
      <div class="fbody stars-filter" id="fRating"></div>
    </details>
    <details class="fgroup" open>
      <summary>On Sale <span class="ar">▾</span></summary>
      <div class="fbody"><label class="fcheck"><input type="checkbox" data-f="sale"><span class="box">✓</span> Show only on sale</label></div>
    </details>
  </aside>

  <main>
    <div class="plp-grid" id="grid"></div>
  </main>
</div>

<section class="seo-band">
  <div class="wrap">
    <h2 class="h2">Why shop at <span class="script">The Well</span></h2>
    <p class="measure muted">Every product is sourced direct from trusted brands and quality-checked by licensed pharmacists. Build a routine that's backed by science and loved by you — with expert advice a tap away.</p>
    <div class="pts">
      <span class="chip chip-mint">100% Authentic</span>
      <span class="chip chip-blue">Derm-tested</span>
      <span class="chip">Delivery across Lebanon</span>
      <span class="chip">COD available</span>
    </div>
  </div>
</section>

<div class="filter-sheet-btn">
  <button class="btn btn-outline btn-block" id="mFilter">⚙ Filters</button>
  <button class="btn btn-primary btn-block" onclick="document.getElementById('sortSel').focus()">Apply (<span data-count>0</span>)</button>
</div>

<div id="usp"></div>
<?php
$jcat = json_encode($cat); $jq = json_encode($q); $jbrand = json_encode($brand);
$joffers = $offers ? 'true' : 'false';
$PAGE_JS = <<<JS
<script>
  const W = WELL, \$ = s=>document.querySelector(s);
  const CAT = {$jcat}, Q = {$jq}, BRAND = {$jbrand}, OFFERS = {$joffers};
  let products = W.PRODUCTS.slice();
  if (CAT) products = products.filter(p => p.cat === CAT);
  if (Q) { const q = Q.toLowerCase(); products = products.filter(p => (p.name+' '+p.brand).toLowerCase().includes(q)); }
  if (OFFERS) products = products.filter(p => p.was || p.sale);

  // filter options (concern/skin decorative as in design; brand + price + rating + sale are functional)
  const ck = (f,v,ct)=>`<label class="fcheck"><input type="checkbox" data-f="\${f}" data-val="\${v}"><span class="box">\${W.icon('check')}</span> \${v}\${ct!=null?`<span class="ct">\${ct}</span>`:''}</label>`;
  \$('#fConcern').innerHTML = ['Acne','Anti-Aging','Hyperpigmentation','Dryness','Sensitivity','Pores','Dullness'].map(c=>ck('concern',c)).join('');
  const brands = [...new Set(products.map(p=>p.brand))].sort();
  \$('#fBrand').innerHTML = brands.map(b=>`<div data-brand-row="\${b}">\${ck('brand',b,products.filter(p=>p.brand===b).length)}</div>`).join('');
  \$('#fSkin').innerHTML = ['Oily','Dry','Combination','Normal','Sensitive'].map(s=>ck('skin',s)).join('');
  \$('#fRating').innerHTML = [4.5,4,3.5].map(r=>`<label class="fcheck"><input type="radio" name="rt" data-f="rating" data-val="\${r}"><span class="box">\${W.icon('check')}</span> <span class="s">★</span> \${r} & up</label>`).join('');

  const banner = `<div class="plp-banner"><div><span class="ey">Not sure where to start?</span><h3>Build Your Routine</h3><p>Chat with our licensed pharmacists for a derm-matched AM/PM routine.</p></div><a class="btn btn-rosegold" href="contact">Ask an Expert</a></div>`;

  WELL.initPLP({
    products, gridEl:\$('#grid'),
    filtersEl:\$('#filters'), chipsEl:\$('#chips'),
    countEls:[...document.querySelectorAll('[data-count]')],
    sortEl:\$('#sortSel'),
    banner,
    seed: BRAND ? [['brand', BRAND]] : null
  });

  \$('#filterToggle').addEventListener('click', ()=>{ const f=\$('#filters'); f.style.display = (getComputedStyle(f).display==='none')?'block':''; });
  \$('#mFilter').addEventListener('click', ()=>{ const f=\$('#filters'); f.style.cssText='display:block;position:static;max-height:none'; f.scrollIntoView(); });

  \$('#usp').innerHTML = W.uspHTML();
  W.guardImages(document);
</script>
JS;
include __DIR__ . '/inc/foot.php';
