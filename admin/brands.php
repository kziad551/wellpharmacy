<?php
require __DIR__ . '/inc/layout.php';

if (is_post() && input('action') === 'delete') {
    csrf_check();
    q("DELETE FROM brands WHERE id = ?", [(int) input('id')]);
    flash('Brand deleted.');
    redirect('brands');
}

$list = rows("SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.brand = b.name) AS n
              FROM brands b ORDER BY b.featured DESC, b.sort, b.name");

admin_head('Brands', 'brands', count($list) . ' brand' . (count($list) === 1 ? '' : 's'));
?>
<div class="page-actions">
  <div class="spacer"></div>
  <a class="btn btn-primary" href="brand-edit"><?= aicon('plus') ?> Add brand</a>
</div>

<div class="a-card"><div class="bd" style="padding:0">
  <?php if (!$list): ?>
    <div class="empty">No brands yet.</div>
  <?php else: ?>
  <table class="a-table">
    <thead><tr><th></th><th>Brand</th><th>Products</th><th>Featured</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $b): ?>
      <tr>
        <td>
          <?php if ($b['logo']): ?>
            <img class="thumb" src="<?= e(asrc($b['logo'])) ?>" alt="" onerror="this.style.visibility='hidden'">
          <?php else: ?>
            <span class="thumb" style="display:inline-flex;align-items:center;justify-content:center;background:<?= e($b['color'] ?: '#eee') ?>;color:#fff;font-weight:700"><?= e(strtoupper(substr($b['name'],0,1))) ?></span>
          <?php endif; ?>
        </td>
        <td>
          <a class="nm" href="brand-edit?id=<?= (int)$b['id'] ?>"><?= e($b['name']) ?></a>
          <div class="br"><span class="faint"><?= e($b['slug']) ?></span></div>
        </td>
        <td><?= (int)$b['n'] ?></td>
        <td><?php if ($b['featured']): ?><span class="pill pill-good">Homepage strip</span><?php else: ?><span class="pill pill-muted">Directory</span><?php endif; ?></td>
        <td style="text-align:right;white-space:nowrap">
          <a class="btn btn-ghost btn-sm" href="brand-edit?id=<?= (int)$b['id'] ?>">Edit</a>
          <form method="post" action="brands" style="display:inline" onsubmit="return confirm('Delete &quot;<?= e($b['name']) ?>&quot;?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
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
