<?php
require __DIR__ . '/inc/layout.php';

if (is_post() && input('action') === 'delete') {
    csrf_check();
    q("DELETE FROM products WHERE id = ?", [(string) input('id')]);
    flash('Product deleted.');
    redirect('products');
}

/* ---- bulk stock actions: restock / out of stock for many products at once ----
   Two modes: an explicit list of ticked ids, OR "all matching" (every product in the
   current search, across all infinite-scroll pages) via all=1. ---- */
if (is_post() && input('action') === 'bulk') {
    csrf_check();
    $op = (string) input('op');
    if (!in_array($op, ['restock', 'outofstock'], true)) { flash('Nothing selected.', 'err'); redirect('products'); }
    $set  = $op === 'outofstock' ? 'stock = 0' : 'stock = GREATEST(stock, 20)';  // restock never lowers a higher stock
    $word = $op === 'outofstock' ? 'marked out of stock' : 'restocked';

    if (input('all') === '1') {
        /* "all matching" must honour EXACTLY the filters the list shows (search +
           category + brand), or the count on the button won't match the rows updated.
           cat/brand are whitelisted against the real lists so a tampered field can't
           widen the UPDATE beyond what the operator can see. */
        $q    = trim((string) input('q'));
        $bcat = (string) input('cat');
        $bbr  = (string) input('brand');
        $catOk   = array_column(rows("SELECT name FROM categories"), 'name');
        $brandOk = array_column(rows("SELECT DISTINCT brand FROM products WHERE brand <> ''"), 'brand');
        if ($bcat !== '' && !in_array($bcat, $catOk, true))   $bcat = '';
        if ($bbr  !== '' && !in_array($bbr,  $brandOk, true)) $bbr  = '';
        $cond = []; $a = [];
        if ($q !== '')    { $cond[] = "(name LIKE ? OR brand LIKE ? OR id LIKE ?)"; $s = "%$q%"; array_push($a, $s, $s, $s); }
        if ($bcat !== '') { $cond[] = "category = ?"; $a[] = $bcat; }
        if ($bbr  !== '') { $cond[] = "brand = ?";    $a[] = $bbr; }
        $w = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';
        $cnt = (int) val("SELECT COUNT(*) FROM products $w", $a);
        q("UPDATE products SET $set $w", $a);
        flash("$cnt product(s) $word.");
    } else {
        $ids = array_values(array_filter(array_map('strval', (array) ($_POST['ids'] ?? []))));
        if ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            q("UPDATE products SET $set WHERE id IN ($ph)", $ids);
            flash(count($ids) . " product(s) $word.");
        } else {
            flash('Nothing selected.', 'err');
        }
    }
    redirect('products' . ($_GET ? '?' . http_build_query($_GET) : ''));
}

$search = trim((string) input('q'));

/* Shelf / brand filters, so an operator can work through one supplier or one
   category at a time instead of scrolling 1,745 rows. Both are whitelisted
   against the real lists, so an edited query string can never reach SQL. */
$catList   = array_column(rows("SELECT name FROM categories ORDER BY sort"), 'name');
$brandList = array_column(rows("SELECT DISTINCT brand FROM products WHERE brand <> '' ORDER BY brand"), 'brand');
$cat   = (string) input('cat');
$brand = (string) input('brand');
if ($cat !== ''   && !in_array($cat, $catList, true))     $cat = '';
if ($brand !== '' && !in_array($brand, $brandList, true)) $brand = '';

$cond = []; $args = [];
if ($search !== '') { $cond[] = '(name LIKE ? OR brand LIKE ? OR id LIKE ?)'; $s = "%$search%"; array_push($args, $s, $s, $s); }
if ($cat !== '')    { $cond[] = 'category = ?'; $args[] = $cat; }
if ($brand !== '')  { $cond[] = 'brand = ?';    $args[] = $brand; }
$where = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';

/* Sort options. Whitelisted → the value can never reach SQL unchecked. */
$SORTS = [
    'default'   => ['Default order',        'sort, name'],
    'stock_asc' => ['Stock: low → high',    'stock ASC, name'],      // what to reorder
    'stock_desc'=> ['Stock: high → low',    'stock DESC, name'],     // what you're sitting on
    'price_asc' => ['Price: low → high',    'price ASC, name'],
    'price_desc'=> ['Price: high → low',    'price DESC, name'],
    'name'      => ['Name: A → Z',          'name ASC'],
    'newest'    => ['Newest first',         'created_at DESC, id DESC'],
];
$sort = (string) input('sort', 'default');
if (!isset($SORTS[$sort])) $sort = 'default';

/* one slice at a time — the full 1,730-row table made a 200,000px-tall page */
$PER   = list_per();
$page  = list_page();
$total = (int) val("SELECT COUNT(*) FROM products $where", $args);
$list  = rows("SELECT * FROM products $where ORDER BY {$SORTS[$sort][1]} LIMIT $PER OFFSET " . list_offset(), $args);

/** One product row — reused by the first paint and by each infinite-scroll slice. */
function product_row(array $p): void { ?>
  <tr>
    <td class="c-sel"><input type="checkbox" class="rowsel" value="<?= e($p['id']) ?>" aria-label="Select <?= e($p['name']) ?>"></td>
    <td class="c-img"><img class="thumb thumb-fit" src="<?= e(asrc($p['image'])) ?>" alt="" loading="lazy" onerror="this.style.visibility='hidden'"></td>
    <td class="c-main">
      <a class="nm" href="product-edit?id=<?= e($p['id']) ?><?= e(admin_here_qs()) ?>"><?= e($p['name']) ?></a>
      <div class="br"><?= e($p['brand']) ?> · <span class="faint"><?= e($p['id']) ?></span></div>
    </td>
    <td class="c-hide"><?= e($p['category']) ?></td>
    <td data-label="Price">
      <?= money($p['price']) ?>
      <?php if ($p['was']): ?><span class="br" style="text-decoration:line-through"><?= money($p['was']) ?></span><?php endif; ?>
    </td>
    <td data-label="Stock">
      <?php if ($p['stock'] == 0): ?><span class="pill pill-bad">Out</span>
      <?php elseif ($p['stock'] <= $p['low_stock']): ?><span class="pill pill-warn">Only <?= (int)$p['stock'] ?></span>
      <?php else: ?><span class="pill pill-good"><?= (int)$p['stock'] ?></span><?php endif; ?>
    </td>
    <td data-label="Status"><span class="pill <?= $p['status']==='active'?'pill-good':'pill-muted' ?>"><?= e($p['status']) ?></span></td>
    <td class="c-act" style="text-align:right;white-space:nowrap">
      <a class="btn btn-ghost btn-sm" href="product-edit?id=<?= e($p['id']) ?><?= e(admin_here_qs()) ?>">Edit</a>
      <form method="post" action="products" style="display:inline" onsubmit="return confirm('Delete &quot;<?= e($p['name']) ?>&quot;?')">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($p['id']) ?>">
        <button class="btn btn-bad btn-sm">Delete</button>
      </form>
    </td>
  </tr>
<?php }

/* infinite scroll asks for just the rows */
if (list_partial()) { foreach ($list as $p) product_row($p); exit; }

/* low-stock count over the WHOLE result set, not just the slice on screen.
   Mirrors the old per-row test: a blank/zero low_stock threshold means 5. */
$lowCount = (int) val("SELECT COUNT(*) FROM products " . ($where ? $where . ' AND' : 'WHERE')
                    . " stock <= IF(low_stock > 0, low_stock, 5)", $args);

$sub = list_count_label($total, 'product');
if ($cat !== '')   $sub .= ' in ' . $cat;
if ($brand !== '') $sub .= ' by ' . $brand;
if ($lowCount) $sub .= ' · ' . $lowCount . ' low on stock';
admin_head('Products', 'products', $sub);
?>
<div class="page-actions">
  <?php
    ob_start(); ?>
    <select class="input tb-sort" name="cat" onchange="this.form.submit()" aria-label="Filter by category">
      <option value="">All categories</option>
      <?php foreach ($catList as $c): ?><option value="<?= e($c) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
    </select>
    <select class="input tb-sort" name="brand" onchange="this.form.submit()" aria-label="Filter by brand">
      <option value="">All brands</option>
      <?php foreach ($brandList as $b): ?><option value="<?= e($b) ?>" <?= $brand === $b ? 'selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?>
    </select>
    <select class="input tb-sort" name="sort" onchange="this.form.submit()">
      <?php foreach ($SORTS as $k => [$lbl]): ?>
        <option value="<?= e($k) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-ghost btn-sm">Go</button></noscript>
    <?php admin_search('products', $search, 'Search products, brands…', ob_get_clean());
  ?>
  <div class="spacer"></div>
  <a class="btn btn-primary" href="product-edit<?= e(admin_here_qs('?')) ?>"><?= aicon('plus') ?> Add product</a>
</div>

<?php if ($cat !== '' || $brand !== '' || $search !== ''): ?>
  <div class="page-actions" style="margin-top:-6px">
    <?php if ($cat !== ''): ?><span class="pill pill-info">Category: <?= e($cat) ?></span><?php endif; ?>
    <?php if ($brand !== ''): ?><span class="pill pill-info">Brand: <?= e($brand) ?></span><?php endif; ?>
    <?php if ($search !== ''): ?><span class="pill pill-muted">Search: <?= e($search) ?></span><?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="products">Clear filters</a>
  </div>
<?php endif; ?>
<!-- bulk-action bar: appears once you tick one or more products -->
<form method="post" action="products<?= $_GET ? '?' . e(http_build_query($_GET)) : '' ?>" id="bulkForm">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="bulk">
  <input type="hidden" name="op" id="bulkOp" value="">
  <input type="hidden" name="all" id="bulkAll" value="">
  <input type="hidden" name="q" value="<?= e($search) ?>">
  <input type="hidden" name="cat" value="<?= e($cat) ?>">
  <input type="hidden" name="brand" value="<?= e($brand) ?>">
  <div id="bulkIds"></div>
</form>
<div class="bulkbar" id="bulkBar" hidden>
  <span class="bulkbar-n"><b id="bulkCount">0</b> selected</span>
  <button type="button" class="btn btn-ghost btn-sm" id="selectAllMatching" hidden>Select all <?= (int) $total ?></button>
  <div class="spacer"></div>
  <button type="button" class="btn btn-ghost btn-sm" data-bulk="restock"><?= aicon('box') ?> Restock</button>
  <button type="button" class="btn btn-bad btn-sm" data-bulk="outofstock">Mark out of stock</button>
  <button type="button" class="btn btn-ghost btn-sm" id="bulkClear">Clear</button>
</div>

<div class="a-card">
  <div class="bd" style="padding:0">
    <?php if (!$list): ?>
      <div class="empty">No products match these filters.
        <?php if ($cat !== '' || $brand !== '' || $search !== ''): ?><br><a href="products">Clear filters</a><?php endif; ?></div>
    <?php else: ?>
    <table class="a-table" id="prodTable">
      <thead><tr>
        <th class="c-sel"><input type="checkbox" id="selAll" aria-label="Select all on screen"></th>
        <th></th><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($list as $p) product_row($p); ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php list_sentinel($total, $page); ?>
</div>

<style>
  .c-sel{width:38px;text-align:center}
  .c-sel input{width:17px;height:17px;cursor:pointer;accent-color:var(--a-primary)}
  .bulkbar{position:sticky;top:64px;z-index:20;display:flex;align-items:center;gap:10px;flex-wrap:wrap;
    margin:0 0 14px;padding:11px 16px;border:1px solid var(--a-border2);border-radius:12px;
    background:var(--a-bg,#fff);box-shadow:var(--a-sh-sm,0 6px 20px rgba(0,0,0,.08))}
  .bulkbar-n{font-size:13.5px;color:var(--a-soft)}
  .bulkbar-n b{color:var(--a-ink)}
</style>
<script>
(function () {
  var table = document.getElementById('prodTable');
  if (!table) return;
  var TOTAL = <?= (int) $total ?>;                       // every product matching the current view
  var bar = document.getElementById('bulkBar'),
      countEl = document.getElementById('bulkCount'),
      selAll = document.getElementById('selAll'),
      allBtn = document.getElementById('selectAllMatching'),
      form = document.getElementById('bulkForm'),
      idsBox = document.getElementById('bulkIds'),
      opField = document.getElementById('bulkOp'),
      allField = document.getElementById('bulkAll');
  var allMode = false;   // true = "every matching product, across all pages" is selected

  function boxes() { return Array.prototype.slice.call(table.querySelectorAll('.rowsel')); }
  function checkedBoxes() { return boxes().filter(function (b) { return b.checked; }); }

  function refresh() {
    var all = boxes(), n = checkedBoxes().length;
    if (allMode) {
      countEl.textContent = TOTAL;
      bar.hidden = false;
      allBtn.hidden = true;
      selAll.checked = true; selAll.indeterminate = false;
    } else {
      countEl.textContent = n;
      bar.hidden = n === 0;
      selAll.checked = all.length > 0 && n === all.length;
      selAll.indeterminate = n > 0 && n < all.length;
      // offer "select all N" only when every loaded row is ticked but more pages exist
      allBtn.hidden = !(n > 0 && n === all.length && all.length < TOTAL);
    }
  }

  // ticking/unticking a single row always drops out of "all matching" mode
  table.addEventListener('change', function (e) {
    if (!e.target.classList.contains('rowsel')) return;
    allMode = false;
    refresh();
  });

  selAll.addEventListener('change', function () {
    allMode = false;
    boxes().forEach(function (b) { b.checked = selAll.checked; });
    refresh();
  });

  allBtn.addEventListener('click', function () {
    allMode = true;
    boxes().forEach(function (b) { b.checked = true; });
    refresh();
  });

  document.getElementById('bulkClear').addEventListener('click', function () {
    allMode = false;
    boxes().forEach(function (b) { b.checked = false; });
    refresh();
  });

  // when "all matching" is on, auto-tick rows as they stream in from infinite scroll
  new MutationObserver(function () {
    if (allMode) boxes().forEach(function (b) { b.checked = true; });
    refresh();
  }).observe(table.querySelector('tbody'), { childList: true });

  document.querySelectorAll('[data-bulk]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var op = btn.getAttribute('data-bulk');
      var count = allMode ? TOTAL : checkedBoxes().length;
      if (!count) return;
      var scope = allMode ? ('ALL ' + TOTAL + ' product(s) matching this view') : (count + ' product(s)');
      var msg = op === 'outofstock'
        ? 'Mark ' + scope + ' as OUT OF STOCK?\n\nThey will show as sold out and can’t be ordered until you restock them.'
        : 'Restock ' + scope + '?\n\nAnything sold out or low is set back to 20 in stock (products already higher are left as they are).';
      if (!confirm(msg)) return;
      opField.value = op;
      if (allMode) {
        allField.value = '1';
        idsBox.innerHTML = '';
      } else {
        allField.value = '';
        idsBox.innerHTML = checkedBoxes().map(function (b) {
          var i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = b.value; return i.outerHTML;
        }).join('');
      }
      form.submit();
    });
  });
  refresh();
})();
</script>
<?php admin_foot();
