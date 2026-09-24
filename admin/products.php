<?php
require __DIR__ . '/inc/layout.php';

if (is_post() && input('action') === 'delete') {
    csrf_check();
    q("DELETE FROM products WHERE id = ?", [(string) input('id')]);
    flash('Product deleted.');
    redirect('products');
}

/* ---- bulk stock actions: restock / out of stock for many products at once ---- */
if (is_post() && input('action') === 'bulk') {
    csrf_check();
    $ids = array_values(array_filter(array_map('strval', (array) ($_POST['ids'] ?? []))));
    $op  = (string) input('op');
    if ($ids && in_array($op, ['restock', 'outofstock'], true)) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if ($op === 'outofstock') {
            q("UPDATE products SET stock = 0 WHERE id IN ($ph)", $ids);
            flash(count($ids) . ' product(s) marked out of stock.');
        } else {
            /* restock: bring anything at/below its low-stock line back up to a healthy
               default, without ever lowering a product that already has more. */
            q("UPDATE products SET stock = GREATEST(stock, 20) WHERE id IN ($ph)", $ids);
            flash(count($ids) . ' product(s) restocked.');
        }
    } else {
        flash('Nothing selected.', 'err');
    }
    redirect('products' . ($_GET ? '?' . http_build_query($_GET) : ''));
}

$search = trim((string) input('q'));
$where = ''; $args = [];
if ($search !== '') { $where = "WHERE (name LIKE ? OR brand LIKE ? OR id LIKE ?)"; $s = "%$search%"; $args = [$s, $s, $s]; }

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
      <a class="nm" href="product-edit?id=<?= e($p['id']) ?>"><?= e($p['name']) ?></a>
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
      <a class="btn btn-ghost btn-sm" href="product-edit?id=<?= e($p['id']) ?>">Edit</a>
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
if ($lowCount) $sub .= ' · ' . $lowCount . ' low on stock';
admin_head('Products', 'products', $sub);
?>
<div class="page-actions">
  <?php
    ob_start(); ?>
    <select class="input tb-sort" name="sort" onchange="this.form.submit()">
      <?php foreach ($SORTS as $k => [$lbl]): ?>
        <option value="<?= e($k) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-ghost btn-sm">Go</button></noscript>
    <?php admin_search('products', $search, 'Search products, brands…', ob_get_clean());
  ?>
  <div class="spacer"></div>
  <a class="btn btn-primary" href="product-edit"><?= aicon('plus') ?> Add product</a>
</div>

<!-- bulk-action bar: appears once you tick one or more products -->
<form method="post" action="products<?= $_GET ? '?' . e(http_build_query($_GET)) : '' ?>" id="bulkForm">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="bulk">
  <input type="hidden" name="op" id="bulkOp" value="">
  <div id="bulkIds"></div>
</form>
<div class="bulkbar" id="bulkBar" hidden>
  <span class="bulkbar-n"><b id="bulkCount">0</b> selected</span>
  <div class="spacer"></div>
  <button type="button" class="btn btn-ghost btn-sm" data-bulk="restock"><?= aicon('box') ?> Restock</button>
  <button type="button" class="btn btn-bad btn-sm" data-bulk="outofstock">Mark out of stock</button>
  <button type="button" class="btn btn-ghost btn-sm" id="bulkClear">Clear</button>
</div>

<div class="a-card">
  <div class="bd" style="padding:0">
    <?php if (!$list): ?>
      <div class="empty">No products found.<?= $search ? ' Try a different search.' : '' ?></div>
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
  var bar = document.getElementById('bulkBar'),
      countEl = document.getElementById('bulkCount'),
      selAll = document.getElementById('selAll'),
      form = document.getElementById('bulkForm'),
      idsBox = document.getElementById('bulkIds'),
      opField = document.getElementById('bulkOp');

  function selected() { return Array.prototype.slice.call(table.querySelectorAll('.rowsel:checked')); }
  function refresh() {
    var n = selected().length;
    countEl.textContent = n;
    bar.hidden = n === 0;
    var boxes = table.querySelectorAll('.rowsel');
    selAll.checked = boxes.length > 0 && n === boxes.length;
    selAll.indeterminate = n > 0 && n < boxes.length;
  }
  // event delegation so infinite-scroll rows are covered too
  table.addEventListener('change', function (e) { if (e.target.classList.contains('rowsel')) refresh(); });
  selAll.addEventListener('change', function () {
    table.querySelectorAll('.rowsel').forEach(function (b) { b.checked = selAll.checked; });
    refresh();
  });
  document.getElementById('bulkClear').addEventListener('click', function () {
    table.querySelectorAll('.rowsel').forEach(function (b) { b.checked = false; });
    refresh();
  });
  // re-sync after each infinite-scroll slice loads
  new MutationObserver(refresh).observe(table.querySelector('tbody'), { childList: true });

  document.querySelectorAll('[data-bulk]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var ids = selected().map(function (b) { return b.value; });
      if (!ids.length) return;
      var op = btn.getAttribute('data-bulk');
      var msg = op === 'outofstock'
        ? 'Mark ' + ids.length + ' product(s) as OUT OF STOCK?\n\nThey will show as sold out and can’t be ordered until you restock them.'
        : 'Restock ' + ids.length + ' product(s)?\n\nAny that are sold out or low will be set back to 20 in stock (products already higher are left as they are).';
      if (!confirm(msg)) return;
      opField.value = op;
      idsBox.innerHTML = ids.map(function (id) {
        var i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = id; return i.outerHTML;
      }).join('');
      form.submit();
    });
  });
  refresh();
})();
</script>
<?php admin_foot();
