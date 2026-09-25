<?php
require __DIR__ . '/inc/functions.php';
require __DIR__ . '/inc/plp.php';

$offers    = !empty($OFFERS);                 // set by offers.php
$validCats = array_column(rows("SELECT name FROM categories ORDER BY sort"), 'name');
$F         = well_plp_input($validCats, $offers);
$page      = max(1, (int) input('page', 1));

/* ---- Load more asks for just the cards, same filters, no page chrome ---- */
if (input('partial') === '1') {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store');
    foreach (well_plp_page($F, $page) as $p) echo well_product_card($p);
    exit;
}

$count  = well_plp_count($F);

/* Nothing matched a search? Retry once with a loosened term before giving up, so
   "battery" still finds BATTERIES. Only ever runs on an otherwise-empty result. */
$didYouMean = '';
if ($count === 0 && $F['q'] !== '') {
    $stem = well_plp_stem($F['q']);
    if ($stem !== '' && $stem !== mb_strtolower($F['q'])) {
        $G = $F; $G['q'] = $stem;
        if (well_plp_count($G) > 0) { $didYouMean = $F['q']; $F = $G; $count = well_plp_count($F); }
    }
}

$items  = well_plp_page($F, $page);
$brands = well_plp_brands($F);
$ceil   = well_plp_price_ceiling($F);
$shown  = ($page - 1) * PLP_PER_PAGE + count($items);
$hasMore = $shown < $count;

$title  = $offers ? 'Offers & Sale'
        : ($F['cat'] !== '' ? $F['cat'] : ($F['q'] !== '' ? 'Search: ' . $F['q'] : 'Shop All'));
$ACTIVE = $offers ? 'Offers' : ($F['cat'] !== '' ? $F['cat'] : 'Shop All');
$PAGE_TITLE = "$title — " . setting('store_name', 'WELL SHOP');
$USE_PLP = true;

/* current path so offers/search keep their own url when paging */
$SELF = strtok($_SERVER['REQUEST_URI'] ?? 'skincare', '?');
$SELF = ltrim($SELF, '/') ?: 'skincare';

$pillCats = array_column(rows("SELECT name FROM categories WHERE in_nav=1 ORDER BY sort"), 'name');
include __DIR__ . '/inc/head.php';
?>
<section class="cat-hero">
  <div class="wrap">
    <nav class="crumb"><a href="index">Home</a><span class="sep">›</span><b><?= e($title) ?></b></nav>
    <span class="chip chip-glass"><?= $count ?> product<?= $count === 1 ? '' : 's' ?></span>
    <h1 class="h1" style="font-size:52px"><?= e($title) ?></h1>
    <p class="sub">Pharmacist-picked, derm-loved — sourced direct from trusted brands &amp; quality-checked for every wellness goal.</p>
    <div class="subcat-pills" id="subPills">
      <a class="chip <?= $F['cat'] === '' && $F['q'] === '' ? 'chip-active' : '' ?>" href="skincare">All</a>
      <?php foreach ($pillCats as $pc): ?>
        <a class="chip <?= $F['cat'] === $pc ? 'chip-active' : '' ?>" href="skincare?cat=<?= rawurlencode($pc) ?>"><?= e($pc) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div class="plp-toolbar">
  <div class="wrap">
    <button class="btn btn-ghost btn-sm" id="filterToggle" style="height:40px" type="button">⚙ Filters</button>
    <div id="chips" class="row wrapf" style="gap:8px">
      <?php if ($didYouMean !== ''): ?><span class="chip chip-glass">No exact match for &ldquo;<?= e($didYouMean) ?>&rdquo; — showing &ldquo;<?= e($F['q']) ?>&rdquo;</span><?php endif; ?>
      <?php if ($F['q'] !== ''): ?><span class="chip-rm">Search: <?= e($F['q']) ?><a href="<?= e($SELF . '?' . well_plp_qs($F, ['q' => null])) ?>" aria-label="Remove">✕</a></span><?php endif; ?>
      <?php foreach ($F['brands'] as $b): ?><span class="chip-rm">Brand: <?= e($b) ?><a href="<?= e($SELF . '?' . well_plp_qs(['cat'=>$F['cat'],'q'=>$F['q'],'brands'=>array_values(array_diff($F['brands'],[$b])),'max'=>$F['max'],'rating'=>$F['rating'],'sale'=>$F['sale'],'sort'=>$F['sort']])) ?>" aria-label="Remove">✕</a></span><?php endforeach; ?>
      <?php if ($F['sale']): ?><span class="chip-rm">On Sale<a href="<?= e($SELF . '?' . well_plp_qs($F, ['sale' => null])) ?>" aria-label="Remove">✕</a></span><?php endif; ?>
      <?php if ($F['rating']): ?><span class="chip-rm">★ <?= e($F['rating']) ?> &amp; up<a href="<?= e($SELF . '?' . well_plp_qs($F, ['rating' => null])) ?>" aria-label="Remove">✕</a></span><?php endif; ?>
      <?php if ($F['max'] !== null): ?><span class="chip-rm">Under $<?= (int) $F['max'] ?><a href="<?= e($SELF . '?' . well_plp_qs($F, ['max' => null])) ?>" aria-label="Remove">✕</a></span><?php endif; ?>
      <?php if ($F['q'] !== '' || $F['brands'] || $F['sale'] || $F['rating'] || $F['max'] !== null): ?>
        <a class="btn btn-ghost btn-sm" style="height:32px" href="<?= e($SELF . ($F['cat'] !== '' ? '?cat=' . rawurlencode($F['cat']) : '')) ?>">Clear all</a>
      <?php endif; ?>
    </div>
    <div class="grow"></div>
    <span class="count"><b data-count><?= $count ?></b> results</span>
    <select class="sortsel" id="sortSel" form="plpFilters" name="sort">
      <?php foreach (well_plp_sorts() as $k => [$lbl]): ?>
        <option value="<?= e($k) ?>" <?= $F['sort'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
      <?php endforeach; ?>
    </select>
    <div class="viewtoggle">
      <button data-view="grid" class="on" aria-label="Grid" type="button"><svg viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg></button>
      <button data-view="list" aria-label="List" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg></button>
    </div>
  </div>
</div>

<div class="wrap plp-body">
  <aside class="filters" id="filters">
    <form id="plpFilters" method="get" action="<?= e($SELF) ?>">
      <?php if ($F['cat'] !== ''): ?><input type="hidden" name="cat" value="<?= e($F['cat']) ?>"><?php endif; ?>

      <details class="fgroup" open>
        <summary>Search <?= $F['cat'] !== '' ? 'in ' . e($F['cat']) : 'products' ?> <span class="ar">▾</span></summary>
        <div class="fbody">
          <input class="fsearch" type="search" name="q" value="<?= e($F['q']) ?>"
                 placeholder="<?= $F['cat'] !== '' ? 'Search in ' . e($F['cat']) . '…' : 'Search products…' ?>">
          <div class="hint" style="margin-top:6px">Searches all <?= $count ?> matching item<?= $count === 1 ? '' : 's' ?>, not just the ones shown.</div>
        </div>
      </details>

      <details class="fgroup" open>
        <summary>Brand <span class="ar">▾</span></summary>
        <div class="fbody">
          <input class="fsearch" data-brand-search placeholder="Search brands…" type="search" autocomplete="off">
          <div id="fBrand">
            <?php foreach ($brands as $b): ?>
              <div data-brand-row="<?= e($b['brand']) ?>">
                <label class="fcheck"><input type="checkbox" name="brand[]" value="<?= e($b['brand']) ?>" <?= in_array($b['brand'], $F['brands'], true) ? 'checked' : '' ?>>
                  <span class="box"><?= PLP_CHECK ?></span> <?= e($b['brand']) ?><span class="ct"><?= (int) $b['c'] ?></span></label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </details>

      <details class="fgroup" open>
        <summary>Price <span class="ar">▾</span></summary>
        <div class="fbody price-slider">
          <input type="range" min="0" max="<?= $ceil ?>" value="<?= $F['max'] !== null ? (int) $F['max'] : $ceil ?>" name="max" data-price>
          <div class="vals"><span>$0</span><span data-price-val>$<?= $F['max'] !== null ? (int) $F['max'] : $ceil ?></span></div>
        </div>
      </details>

      <details class="fgroup">
        <summary>Rating <span class="ar">▾</span></summary>
        <div class="fbody stars-filter">
          <?php foreach ([4.5, 4, 3.5] as $r): ?>
            <label class="fcheck"><input type="radio" name="rating" value="<?= $r ?>" <?= (float) $F['rating'] === (float) $r ? 'checked' : '' ?>>
              <span class="box"><?= PLP_CHECK ?></span> <span class="s">★</span> <?= $r ?> &amp; up</label>
          <?php endforeach; ?>
        </div>
      </details>

      <details class="fgroup" open>
        <summary>On Sale <span class="ar">▾</span></summary>
        <div class="fbody"><label class="fcheck"><input type="checkbox" name="sale" value="1" <?= $F['sale'] ? 'checked' : '' ?>><span class="box"><?= PLP_CHECK ?></span> Show only on sale</label></div>
      </details>

      <button class="btn btn-primary btn-block" type="submit" id="plpApply" style="margin-top:12px">Apply filters</button>
    </form>
  </aside>

  <main>
    <div class="plp-grid" id="grid">
      <?php if (!$items): ?>
        <div class="plp-empty"><h3 class="h3">No matches — try fewer filters</h3>
          <p class="muted">Clear a filter or two and we'll find your glow.</p>
          <a class="view-all" href="<?= e($SELF . ($F['cat'] !== '' ? '?cat=' . rawurlencode($F['cat']) : '')) ?>">Clear all filters</a></div>
      <?php else: foreach ($items as $p) echo well_product_card($p); endif; ?>
    </div>

    <?php if ($hasMore): ?>
      <div class="plp-more" style="text-align:center;margin:28px 0 8px">
        <a class="btn btn-outline" id="loadMore"
           data-next="<?= e($SELF . '?' . well_plp_qs($F, ['page' => $page + 1])) ?>"
           href="<?= e($SELF . '?' . well_plp_qs($F, ['page' => $page + 1])) ?>">
          Load more <span class="muted">(<?= $shown ?> of <?= $count ?>)</span>
        </a>
      </div>
    <?php elseif ($count > 0): ?>
      <p class="muted" style="text-align:center;margin:28px 0 8px">All <?= $count ?> item<?= $count === 1 ? '' : 's' ?> shown.</p>
    <?php endif; ?>
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
  <button class="btn btn-outline btn-block" id="mFilter" type="button">⚙ Filters</button>
  <button class="btn btn-primary btn-block" type="submit" form="plpFilters">Apply (<span data-count><?= $count ?></span>)</button>
</div>

<div id="usp"></div>
<?php
$PAGE_JS = <<<'JS'
<script>
(function () {
  const W = WELL, $ = s => document.querySelector(s);
  const form = $('#plpFilters');
  const grid = $('#grid');

  /* Build the target url from the form. Values equal to their "unset" state are
     dropped so the url stays short and the active-filter chips stay honest. */
  function urlFromForm() {
    const fd = new FormData(form);
    const p = new URLSearchParams();
    const slider = form.querySelector('[data-price]');
    for (const [k, v] of fd.entries()) {
      if (v === '' || v == null) continue;
      if (k === 'max' && slider && String(v) === String(slider.max)) continue;  // full range = no filter
      p.append(k, v);
    }
    const qs = p.toString();
    return location.pathname + (qs ? '?' + qs : '');
  }

  /* Swap only the regions the server recomputed. Fetching the whole page keeps
     one source of truth (the same PHP that renders a normal visit), so counts,
     chips and facet numbers can never drift from the grid. */
  const SWAP = ['#grid', '#chips', '#fBrand', '.plp-more'];
  let seq = 0;

  async function load(url, push) {
    const mine = ++seq;
    grid.classList.add('is-loading');
    try {
      const res = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
      if (!res.ok) throw new Error(res.status);
      const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
      if (mine !== seq) return;                      // a newer click already won

      SWAP.forEach(sel => {
        const next = doc.querySelector(sel), cur = document.querySelector(sel);
        if (next && cur) cur.replaceWith(next);
        else if (!next && cur) cur.remove();         // e.g. Load more disappears on the last page
        else if (next && !cur) $('main').appendChild(next);
      });
      const n = doc.querySelector('[data-count]');
      if (n) document.querySelectorAll('[data-count]').forEach(e => e.textContent = n.textContent);

      if (push) history.pushState({ plp: 1 }, '', url);
      applyBrandSearch();
      wireLoadMore();
      W.guardImages(document);
    } catch (e) {
      location.href = url;                           // never leave the user stuck
    } finally {
      if (mine === seq) grid.classList.remove('is-loading');
    }
  }

  /* Filters no longer reload the page. The Apply button and a no-JS visitor still
     get a normal form GET, so nothing depends on this running. */
  if (form) {
    form.addEventListener('submit', e => { e.preventDefault(); load(urlFromForm(), true); });
    form.addEventListener('change', e => {
      if (e.target.matches('[data-brand-search]')) return;
      load(urlFromForm(), true);
    });
    let t;
    const q = form.querySelector('input[name="q"]');
    if (q) q.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => load(urlFromForm(), true), 400); });
  }

  const slider = form && form.querySelector('[data-price]');
  if (slider) {
    const out = $('[data-price-val]');
    slider.addEventListener('input', () => { if (out) out.textContent = '$' + slider.value; });
  }

  /* The brand box only hides rows visually; re-apply it after a swap replaces them. */
  function applyBrandSearch() {
    const b = form && form.querySelector('[data-brand-search]');
    if (!b || !b.value) return;
    const v = b.value.toLowerCase();
    document.querySelectorAll('[data-brand-row]').forEach(r =>
      r.style.display = r.dataset.brandRow.toLowerCase().includes(v) ? '' : 'none');
  }
  const bs = form && form.querySelector('[data-brand-search]');
  if (bs) bs.addEventListener('input', applyBrandSearch);

  /* Chips and "clear all" are plain links; intercept so they swap too. */
  document.addEventListener('click', e => {
    const a = e.target.closest('#chips a[href], .plp-empty a[href]');
    if (!a || a.target === '_blank') return;
    e.preventDefault();
    load(a.getAttribute('href'), true);
  });

  function wireLoadMore() {
    const more = $('#loadMore');
    if (!more || more.dataset.wired) return;
    more.dataset.wired = '1';
    more.addEventListener('click', async ev => {
      ev.preventDefault();
      const next = more.dataset.next;
      if (!next || more.dataset.busy) return;
      more.dataset.busy = '1';
      const label = more.innerHTML;
      more.innerHTML = 'Loading…';
      try {
        const res = await fetch(next + '&partial=1', { credentials: 'same-origin' });
        if (!res.ok) throw new Error(res.status);
        grid.insertAdjacentHTML('beforeend', await res.text());
        W.guardImages(grid);
        const u = new URL(next, location.href);
        const page = Number(u.searchParams.get('page') || 2);
        const shown = grid.querySelectorAll('.pcard').length;
        const total = Number(($('[data-count]') || {}).textContent || 0);
        history.replaceState({ plp: 1 }, '', u.pathname + u.search);
        if (shown >= total) { const w = more.closest('.plp-more'); if (w) w.innerHTML = '<p class="muted">All ' + total + ' items shown.</p>'; return; }
        u.searchParams.set('page', page + 1);
        more.dataset.next = u.pathname + u.search;
        more.href = u.pathname + u.search;
        more.innerHTML = 'Load more <span class="muted">(' + shown + ' of ' + total + ')</span>';
      } catch (err) { more.innerHTML = label; location.href = next; }
      finally { delete more.dataset.busy; }
    });
  }
  wireLoadMore();

  window.addEventListener('popstate', () => load(location.pathname + location.search, false));

  $('#filterToggle').addEventListener('click', () => {
    const f = $('#filters');
    f.style.display = (getComputedStyle(f).display === 'none') ? 'block' : '';
  });
  $('#mFilter').addEventListener('click', () => {
    const f = $('#filters');
    f.style.cssText = 'display:block;position:static;max-height:none';
    f.scrollIntoView();
  });
  document.querySelectorAll('[data-view]').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll('[data-view]').forEach(x => x.classList.toggle('on', x === b));
    grid.classList.toggle('listview', b.dataset.view === 'list');
  }));

  $('#usp').innerHTML = W.uspHTML();
  W.guardImages(document);
})();
</script>
JS;
include __DIR__ . '/inc/foot.php';
