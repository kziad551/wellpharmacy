<?php
require __DIR__ . '/inc/layout.php';

if (is_post() && input('action') === 'delete') {
    csrf_check();
    q("DELETE FROM home_sections WHERE id = ?", [(int) input('id')]);
    flash('Section deleted.');
    redirect('home-sections');
}
if (is_post() && input('action') === 'toggle') {
    csrf_check();
    q("UPDATE home_sections SET enabled = 1 - enabled WHERE id = ?", [(int) input('id')]);
    redirect('home-sections');
}

$list = rows("SELECT * FROM home_sections ORDER BY sort, id");

admin_head('Home Sections', 'home-sections', count($list) . ' section' . (count($list) === 1 ? '' : 's'));
?>
<div class="page-actions">
  <div class="spacer"></div>
  <a class="btn btn-primary" href="home-section-edit"><?= aicon('plus') ?> Add section</a>
</div>

<div class="a-card"><div class="bd" style="padding:14px 16px">
  <p class="hint" style="margin:0">The product sections on your homepage, top to bottom — lower <b>sort</b> shows first. <b>New Arrivals</b> pulls the products you flag in the product editor; a <b>Brand</b> section pulls every active product of that brand. Empty sections are skipped automatically.</p>
</div></div>

<div class="a-card"><div class="bd" style="padding:0">
  <?php if (!$list): ?>
    <div class="empty">No sections yet. <a href="home-section-edit">Add one</a>.</div>
  <?php else: ?>
  <table class="a-table">
    <thead><tr><th>Sort</th><th>Section</th><th>Type</th><th>Shows</th><th>Row</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $s):
      $isBrand = $s['type'] === 'brand';
      $name = $s['title'] !== '' ? $s['title'] : ($isBrand ? ($s['brand'] ?: '(no brand)') : 'New Arrivals');
    ?>
      <tr>
        <td><span class="faint"><?= (int)$s['sort'] ?></span></td>
        <td>
          <a class="nm" href="home-section-edit?id=<?= (int)$s['id'] ?>"><?= e($name) ?></a>
          <?php if ($s['eyebrow'] || $s['subtitle']): ?><div class="br"><?= e($s['eyebrow']) ?><?= $s['eyebrow'] && $s['subtitle'] ? ' · ' : '' ?><?= e($s['subtitle']) ?></div><?php endif; ?>
          <?php if (!$s['show_title']): ?><div class="br"><span class="faint">title hidden</span></div><?php endif; ?>
        </td>
        <td><?php if ($isBrand): ?><span class="pill pill-muted">Brand</span><?php else: ?><span class="pill pill-good">New Arrivals</span><?php endif; ?></td>
        <td><?= $s['item_count'] > 0 ? (int)$s['item_count'] . ' items' : 'all items' ?></td>
        <td><?= (int)$s['cols'] ?>-up</td>
        <td>
          <form method="post" action="home-sections" style="display:inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="pill <?= $s['enabled'] ? 'pill-good' : 'pill-muted' ?>" style="border:0;cursor:pointer" title="Click to toggle"><?= $s['enabled'] ? 'Visible' : 'Hidden' ?></button>
          </form>
        </td>
        <td style="text-align:right;white-space:nowrap">
          <a class="btn btn-ghost btn-sm" href="home-section-edit?id=<?= (int)$s['id'] ?>">Edit</a>
          <form method="post" action="home-sections" style="display:inline" onsubmit="return confirm('Delete this section?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
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
