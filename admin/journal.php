<?php
require __DIR__ . '/inc/layout.php';

if (is_post() && input('action') === 'delete') {
    csrf_check();
    q("DELETE FROM journal_posts WHERE id = ?", [(int) input('id')]);
    flash('Post deleted.');
    redirect('journal');
}

$list = rows("SELECT * FROM journal_posts ORDER BY sort, published_at DESC, id DESC");
admin_head('Journal', 'journal', count($list) . ' post' . (count($list) === 1 ? '' : 's'));
?>
<div class="page-actions">
  <div class="spacer"></div>
  <a class="btn btn-primary" href="journal-edit"><?= aicon('plus') ?> Add post</a>
</div>

<div class="a-card"><div class="bd" style="padding:0">
  <?php if (!$list): ?>
    <div class="empty">No journal posts yet.</div>
  <?php else: ?>
  <table class="a-table">
    <thead><tr><th></th><th>Title</th><th>Category</th><th>Published</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $p): ?>
      <tr>
        <td><img class="thumb" src="<?= e(asrc($p['image'])) ?>" alt="" onerror="this.style.visibility='hidden'"></td>
        <td>
          <a class="nm" href="journal-edit?id=<?= (int)$p['id'] ?>"><?= e($p['title']) ?></a>
          <div class="br"><a class="br" href="../journal-post?slug=<?= e(urlencode($p['slug'])) ?>" target="_blank">/journal-post?slug=<?= e($p['slug']) ?></a></div>
        </td>
        <td><?= e($p['category']) ?></td>
        <td><?= $p['published_at'] ? e($p['published_at']) : '—' ?></td>
        <td><span class="pill <?= $p['status']==='published'?'pill-good':'pill-muted' ?>"><?= e($p['status']) ?></span></td>
        <td style="text-align:right;white-space:nowrap">
          <a class="btn btn-ghost btn-sm" href="journal-edit?id=<?= (int)$p['id'] ?>">Edit</a>
          <form method="post" action="journal" style="display:inline" onsubmit="return confirm('Delete &quot;<?= e($p['title']) ?>&quot;?')">
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
