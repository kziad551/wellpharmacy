<?php
require __DIR__ . '/inc/layout.php';

if (is_post() && input('action') === 'delete') {
    csrf_check();
    q("DELETE FROM products WHERE id = ?", [(string) input('id')]);
    flash('Product deleted.');
    redirect('products');
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

<div class="a-card">
  <div class="bd" style="padding:0">
    <?php if (!$list): ?>
      <div class="empty">No products match these filters.
        <?php if ($cat !== '' || $brand !== '' || $search !== ''): ?><br><a href="products">Clear filters</a><?php endif; ?></div>
    <?php else: ?>
    <table class="a-table">
      <thead><tr><th></th><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($list as $p) product_row($p); ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php list_sentinel($total, $page); ?>
</div>
<?php admin_foot();
