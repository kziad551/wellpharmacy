<?php
require __DIR__ . '/inc/layout.php';

if (is_post() && input('action') === 'delete') {
    csrf_check();
    $id  = (int) input('id');
    $cat = row("SELECT * FROM categories WHERE id = ?", [$id]);
    if ($cat) {
        $inUse = (int) val("SELECT COUNT(*) FROM products WHERE category = ?", [$cat['name']]);
        if ($inUse > 0) {
            flash("Can't delete “{$cat['name']}” — {$inUse} product(s) still use it. Reassign or remove those products first.", 'err');
        } else {
            q("DELETE FROM categories WHERE id = ?", [$id]);
            flash('Category deleted.');
        }
    }
    redirect('categories');
}

$search = trim((string) input('q'));
$where = ''; $args = [];
if ($search !== '') { $where = "WHERE c.name LIKE ?"; $args = ["%$search%"]; }

$PER   = list_per();
$page  = list_page();
$total = (int) val("SELECT COUNT(*) FROM categories c $where", $args);
$list  = rows("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category = c.name) AS n
               FROM categories c $where ORDER BY c.sort, c.name LIMIT $PER OFFSET " . list_offset(), $args);

function category_row(array $c): void { ?>
  <tr>
    <td class="c-img"><img class="thumb" src="<?= e(asrc($c['image'])) ?>" alt="" loading="lazy" onerror="this.style.visibility='hidden'"></td>
    <td class="c-main">
      <a class="nm" href="category-edit?id=<?= (int)$c['id'] ?><?= e(admin_here_qs()) ?>"><?= e($c['name']) ?></a>
      <div class="br"><span class="faint">/skincare?cat=<?= e(urlencode($c['name'])) ?></span></div>
    </td>
    <td data-label="Products"><?php if ((int)$c['n']): ?><a class="btn btn-ghost btn-sm" href="products?cat=<?= rawurlencode($c['name']) ?>" title="Manage this category's products"><?= (int)$c['n'] ?> &rsaquo;</a><?php else: ?><span class="faint">0</span><?php endif; ?></td>
    <td data-label="In nav"><?php if ($c['in_nav']): ?><span class="pill pill-good">Yes</span><?php else: ?><span class="pill pill-muted">Hidden</span><?php endif; ?></td>
    <td data-label="Flags" style="white-space:nowrap">
      <?php if ($c['is_cross']): ?><span class="pill pill-muted">cross</span><?php endif; ?>
      <?php if ($c['is_sale']): ?><span class="pill pill-warn">sale</span><?php endif; ?>
      <?php if (!$c['is_cross'] && !$c['is_sale']): ?><span class="faint">—</span><?php endif; ?>
    </td>
    <td class="c-act" style="text-align:right;white-space:nowrap">
      <a class="btn btn-ghost btn-sm" href="category-edit?id=<?= (int)$c['id'] ?><?= e(admin_here_qs()) ?>">Edit</a>
      <form method="post" action="categories" style="display:inline" onsubmit="return confirm('Delete &quot;<?= e($c['name']) ?>&quot;?')">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <button class="btn btn-bad btn-sm">Delete</button>
      </form>
    </td>
  </tr>
<?php }

if (list_partial()) { foreach ($list as $c) category_row($c); exit; }

admin_head('Categories', 'categories', list_count_label($total, 'category', 'categories'));
?>
<div class="page-actions">
  <?php admin_search('categories', $search, 'Search categories…'); ?>
  <div class="spacer"></div>
  <a class="btn btn-primary" href="category-edit<?= e(admin_here_qs('?')) ?>"><?= aicon('plus') ?> Add category</a>
</div>

<div class="a-card">
  <div class="bd" style="padding:0">
    <?php if (!$list): ?>
      <div class="empty">No categories found.<?= $search ? ' Try a different search.' : '' ?></div>
    <?php else: ?>
    <table class="a-table">
      <thead><tr><th></th><th>Category</th><th>Products</th><th>In nav</th><th>Flags</th><th></th></tr></thead>
      <tbody><?php foreach ($list as $c) category_row($c); ?></tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php list_sentinel($total, $page); ?>
</div>
<?php admin_foot();
