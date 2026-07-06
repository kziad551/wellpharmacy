<?php
require __DIR__ . '/inc/layout.php';

if (is_post() && input('action') === 'delete') {
    csrf_check();
    q("DELETE FROM pages WHERE id = ?", [(int) input('id')]);
    flash('Page deleted.');
    redirect('pages');
}

$list = rows("SELECT * FROM pages ORDER BY sort, title");
admin_head('Content pages', 'pages', 'Editable info pages — Shipping, Returns, FAQ and any custom page.');
?>
<div class="page-actions">
  <div class="spacer"></div>
  <a class="btn btn-primary" href="page-edit"><?= aicon('plus') ?> Add page</a>
</div>
<div class="a-card"><div class="bd" style="padding:0">
  <?php if (!$list): ?><div class="empty">No pages yet.</div><?php else: ?>
  <table class="a-table">
    <thead><tr><th>Title</th><th>URL</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $p): ?>
      <tr>
        <td><a class="nm" href="page-edit?id=<?= (int)$p['id'] ?>"><?= e($p['title']) ?></a><div class="br"><?= e($p['intro']) ?></div></td>
        <td><a class="br" href="../<?= e($p['slug']) ?>" target="_blank">/<?= e($p['slug']) ?></a></td>
        <td><span class="pill <?= $p['status']==='published'?'pill-good':'pill-muted' ?>"><?= e($p['status']) ?></span></td>
        <td style="text-align:right;white-space:nowrap">
          <a class="btn btn-ghost btn-sm" href="page-edit?id=<?= (int)$p['id'] ?>">Edit</a>
          <form method="post" action="pages" style="display:inline" onsubmit="return confirm('Delete this page?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
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
