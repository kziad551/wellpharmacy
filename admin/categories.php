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

$list = rows("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category = c.name) AS n
              FROM categories c ORDER BY c.sort, c.name");

admin_head('Categories', 'categories', count($list) . ' categor' . (count($list) === 1 ? 'y' : 'ies'));
?>
<div class="page-actions">
  <div class="spacer"></div>
  <a class="btn btn-primary" href="category-edit"><?= aicon('plus') ?> Add category</a>
</div>

<div class="a-card"><div class="bd" style="padding:0">
  <?php if (!$list): ?>
    <div class="empty">No categories yet.</div>
  <?php else: ?>
  <table class="a-table">
    <thead><tr><th></th><th>Category</th><th>Products</th><th>In nav</th><th>Flags</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $c): ?>
      <tr>
        <td><img class="thumb" src="<?= e(asrc($c['image'])) ?>" alt="" onerror="this.style.visibility='hidden'"></td>
        <td>
          <a class="nm" href="category-edit?id=<?= (int)$c['id'] ?>"><?= e($c['name']) ?></a>
          <div class="br"><span class="faint">/skincare?cat=<?= e(urlencode($c['name'])) ?></span></div>
        </td>
        <td><?= (int)$c['n'] ?></td>
        <td><?php if ($c['in_nav']): ?><span class="pill pill-good">Yes</span><?php else: ?><span class="pill pill-muted">Hidden</span><?php endif; ?></td>
        <td style="white-space:nowrap">
          <?php if ($c['is_cross']): ?><span class="pill pill-muted">cross</span><?php endif; ?>
          <?php if ($c['is_sale']): ?><span class="pill pill-warn">sale</span><?php endif; ?>
        </td>
        <td style="text-align:right;white-space:nowrap">
          <a class="btn btn-ghost btn-sm" href="category-edit?id=<?= (int)$c['id'] ?>">Edit</a>
          <form method="post" action="categories" style="display:inline" onsubmit="return confirm('Delete &quot;<?= e($c['name']) ?>&quot;?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="btn btn-bad btn-sm">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div></div>
<?php admin_foot();
