<?php
require __DIR__ . '/inc/layout.php';

if (is_post() && input('action') === 'delete') {
    csrf_check();
    q("DELETE FROM journal_posts WHERE id = ?", [(int) input('id')]);
    flash('Post deleted.');
    redirect('journal');
}

$search = trim((string) input('q'));
$where = ''; $args = [];
if ($search !== '') { $where = "WHERE (title LIKE ? OR category LIKE ?)"; $args = ["%$search%", "%$search%"]; }

$PER   = list_per();
$page  = list_page();
$total = (int) val("SELECT COUNT(*) FROM journal_posts $where", $args);
$list  = rows("SELECT * FROM journal_posts $where ORDER BY sort, published_at DESC, id DESC LIMIT $PER OFFSET " . list_offset(), $args);

function journal_row(array $p): void { ?>
  <tr>
    <td class="c-img"><img class="thumb" src="<?= e(asrc($p['image'])) ?>" alt="" loading="lazy" onerror="this.style.visibility='hidden'"></td>
    <td class="c-main">
      <a class="nm" href="journal-edit?id=<?= (int)$p['id'] ?>"><?= e($p['title']) ?></a>
      <div class="br"><a class="br" href="../journal-post?slug=<?= e(urlencode($p['slug'])) ?>" target="_blank">/journal-post?slug=<?= e($p['slug']) ?></a></div>
    </td>
    <td data-label="Category"><?= e($p['category']) ?></td>
    <td data-label="Published"><?= $p['published_at'] ? e($p['published_at']) : '—' ?></td>
    <td data-label="Status"><span class="pill <?= $p['status']==='published'?'pill-good':'pill-muted' ?>"><?= e($p['status']) ?></span></td>
    <td class="c-act" style="text-align:right;white-space:nowrap">
      <a class="btn btn-ghost btn-sm" href="journal-edit?id=<?= (int)$p['id'] ?>">Edit</a>
      <form method="post" action="journal" style="display:inline" onsubmit="return confirm('Delete &quot;<?= e($p['title']) ?>&quot;?')">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <button class="btn btn-bad btn-sm">Delete</button>
      </form>
    </td>
  </tr>
<?php }

if (list_partial()) { foreach ($list as $p) journal_row($p); exit; }

admin_head('Journal', 'journal', list_count_label($total, 'post'));
?>
<div class="page-actions">
  <?php admin_search('journal', $search, 'Search posts…'); ?>
  <div class="spacer"></div>
  <a class="btn btn-primary" href="journal-edit"><?= aicon('plus') ?> Add post</a>
</div>

<div class="a-card">
  <div class="bd" style="padding:0">
    <?php if (!$list): ?>
      <div class="empty">No journal posts found.<?= $search ? ' Try a different search.' : '' ?></div>
    <?php else: ?>
    <table class="a-table">
      <thead><tr><th></th><th>Title</th><th>Category</th><th>Published</th><th>Status</th><th></th></tr></thead>
      <tbody><?php foreach ($list as $p) journal_row($p); ?></tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php list_sentinel($total, $page); ?>
</div>
<?php admin_foot();
