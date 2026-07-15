<?php
require __DIR__ . '/inc/layout.php';

if (is_post() && input('action') === 'delete') {
    csrf_check();
    q("DELETE FROM products WHERE id = ?", [(string) input('id')]);
    flash('Product deleted.');
    redirect('products');
}

$search = trim((string) input('q'));
$where = ''; $args = [];
if ($search !== '') { $where = "WHERE name LIKE ? OR brand LIKE ? OR id LIKE ?"; $s = "%$search%"; $args = [$s, $s, $s]; }

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
$list = rows("SELECT * FROM products $where ORDER BY {$SORTS[$sort][1]}", $args);

$lowCount = 0;
foreach ($list as $p) if ((int) $p['stock'] <= (int) ($p['low_stock'] ?: 5)) $lowCount++;

$sub = count($list) . ' product' . (count($list) === 1 ? '' : 's');
if ($lowCount) $sub .= ' · ' . $lowCount . ' low on stock';
admin_head('Products', 'products', $sub);
?>
<div class="page-actions">
  <form method="get" action="products" style="flex:1;max-width:660px;display:flex;gap:8px">
    <input class="input" name="q" value="<?= e($search) ?>" placeholder="Search products, brands…" style="flex:1">
    <select class="input" name="sort" style="width:190px" onchange="this.form.submit()">
      <?php foreach ($SORTS as $k => [$lbl]): ?>
        <option value="<?= e($k) ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="btn btn-ghost btn-sm">Go</button></noscript>
  </form>
  <div class="spacer"></div>
  <a class="btn btn-primary" href="product-edit"><?= aicon('plus') ?> Add product</a>
</div>

<div class="a-card">
  <div class="bd" style="padding:0">
    <?php if (!$list): ?>
      <div class="empty">No products found.<?= $search ? ' Try a different search.' : '' ?></div>
    <?php else: ?>
    <table class="a-table">
      <thead><tr><th></th><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($list as $p): ?>
        <tr>
          <td><img class="thumb" src="<?= e(asrc($p['image'])) ?>" alt="" onerror="this.style.visibility='hidden'"></td>
          <td>
            <a class="nm" href="product-edit?id=<?= e($p['id']) ?>"><?= e($p['name']) ?></a>
            <div class="br"><?= e($p['brand']) ?> · <span class="faint"><?= e($p['id']) ?></span></div>
          </td>
          <td><?= e($p['category']) ?></td>
          <td>
            <?= money($p['price']) ?>
            <?php if ($p['was']): ?><div class="br" style="text-decoration:line-through"><?= money($p['was']) ?></div><?php endif; ?>
          </td>
          <td>
            <?php if ($p['stock'] == 0): ?><span class="pill pill-bad">Out</span>
            <?php elseif ($p['stock'] <= $p['low_stock']): ?><span class="pill pill-warn">Only <?= (int)$p['stock'] ?></span>
            <?php else: ?><span class="pill pill-good"><?= (int)$p['stock'] ?></span><?php endif; ?>
          </td>
          <td><span class="pill <?= $p['status']==='active'?'pill-good':'pill-muted' ?>"><?= e($p['status']) ?></span></td>
          <td style="text-align:right;white-space:nowrap">
            <a class="btn btn-ghost btn-sm" href="product-edit?id=<?= e($p['id']) ?>">Edit</a>
            <form method="post" action="products" style="display:inline" onsubmit="return confirm('Delete &quot;<?= e($p['name']) ?>&quot;?')">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($p['id']) ?>">
              <button class="btn btn-bad btn-sm">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php admin_foot();
