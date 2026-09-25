<?php
require __DIR__ . '/inc/layout.php';

if (is_post() && input('action') === 'delete') {
    csrf_check();
    q("DELETE FROM brands WHERE id = ?", [(int) input('id')]);
    flash('Brand deleted.');
    redirect('brands');
}

$search = trim((string) input('q'));
$where = ''; $args = [];
if ($search !== '') { $where = "WHERE (b.name LIKE ? OR b.slug LIKE ?)"; $args = ["%$search%", "%$search%"]; }

$PER   = list_per();
$page  = list_page();
$total = (int) val("SELECT COUNT(*) FROM brands b $where", $args);
$list  = rows("SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.brand = b.name) AS n
               FROM brands b $where ORDER BY b.featured DESC, b.sort, b.name LIMIT $PER OFFSET " . list_offset(), $args);

function brand_row(array $b): void { ?>
  <tr>
    <td class="c-img">
      <?php if ($b['logo']): ?>
        <img class="thumb thumb-fit" src="<?= e(asrc($b['logo'])) ?>" alt="" loading="lazy" onerror="this.style.visibility='hidden'">
      <?php else: ?>
        <span class="thumb" style="background:<?= e($b['color'] ?: '#eee') ?>;color:#fff;font-weight:700"><?= e(strtoupper(substr($b['name'],0,1))) ?></span>
      <?php endif; ?>
    </td>
    <td class="c-main">
      <a class="nm" href="brand-edit?id=<?= (int)$b['id'] ?><?= e(admin_here_qs()) ?>"><?= e($b['name']) ?></a>
      <div class="br"><span class="faint"><?= e($b['slug']) ?></span></div>
    </td>
    <td data-label="Products"><?php if ((int)$b['n']): ?><a class="btn btn-ghost btn-sm" href="products?brand=<?= rawurlencode($b['name']) ?>" title="Manage this brand's products"><?= (int)$b['n'] ?> &rsaquo;</a><?php else: ?><span class="faint">0</span><?php endif; ?></td>
    <td data-label="Featured"><?php if ($b['featured']): ?><span class="pill pill-good">Homepage strip</span><?php else: ?><span class="pill pill-muted">Directory</span><?php endif; ?></td>
    <td class="c-act" style="text-align:right;white-space:nowrap">
      <a class="btn btn-ghost btn-sm" href="brand-edit?id=<?= (int)$b['id'] ?><?= e(admin_here_qs()) ?>">Edit</a>
      <form method="post" action="brands" style="display:inline" onsubmit="return confirm('Delete &quot;<?= e($b['name']) ?>&quot;?')">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
        <button class="btn btn-bad btn-sm">Delete</button>
      </form>
    </td>
  </tr>
<?php }

if (list_partial()) { foreach ($list as $b) brand_row($b); exit; }

admin_head('Brands', 'brands', list_count_label($total, 'brand'));
?>
<div class="page-actions">
  <?php admin_search('brands', $search, 'Search brands…'); ?>
  <div class="spacer"></div>
  <a class="btn btn-primary" href="brand-edit<?= e(admin_here_qs('?')) ?>"><?= aicon('plus') ?> Add brand</a>
</div>

<div class="a-card">
  <div class="bd" style="padding:0">
    <?php if (!$list): ?>
      <div class="empty">No brands found.<?= $search ? ' Try a different search.' : '' ?></div>
    <?php else: ?>
    <table class="a-table">
      <thead><tr><th></th><th>Brand</th><th>Products</th><th>Featured</th><th></th></tr></thead>
      <tbody><?php foreach ($list as $b) brand_row($b); ?></tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php list_sentinel($total, $page); ?>
</div>
<?php admin_foot();
