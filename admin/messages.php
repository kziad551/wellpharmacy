<?php
require __DIR__ . '/inc/layout.php';

if (is_post()) {
    csrf_check();
    $act = (string) input('action');
    $id  = (int) input('id');
    if ($act === 'read')       q("UPDATE messages SET is_read = 1 WHERE id = ?", [$id]);
    elseif ($act === 'unread') q("UPDATE messages SET is_read = 0 WHERE id = ?", [$id]);
    elseif ($act === 'delete') { q("DELETE FROM messages WHERE id = ?", [$id]); flash('Message deleted.'); }
    redirect('messages');
}

$list = rows("SELECT * FROM messages ORDER BY is_read ASC, created_at DESC");
$unread = 0; foreach ($list as $m) if (!$m['is_read']) $unread++;

admin_head('Messages', 'messages', count($list) . ' message' . (count($list) === 1 ? '' : 's') . ($unread ? " · $unread unread" : ''));
?>
<?php if (!$list): ?>
  <div class="a-card"><div class="empty">No messages yet. Contact-form submissions land here.</div></div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:14px">
  <?php foreach ($list as $m): ?>
    <div class="a-card" style="<?= $m['is_read'] ? '' : 'border-left:3px solid var(--rose,#9C8158)' ?>">
      <div class="bd">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
          <div>
            <b style="font-size:15px"><?= e($m['name']) ?></b>
            <?php if (!$m['is_read']): ?><span class="pill pill-info" style="margin-left:6px">new</span><?php endif; ?>
            <div class="br" style="margin-top:3px">
              <?php if ($m['email']): ?><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><?php endif; ?>
              <?php if ($m['phone']): ?> · <a href="https://wa.me/<?= e(preg_replace('/\D/', '', $m['phone'])) ?>" target="_blank" rel="noopener"><?= e($m['phone']) ?></a><?php endif; ?>
            </div>
          </div>
          <div class="br" style="text-align:right;white-space:nowrap"><?= e(date('M j, Y · H:i', strtotime($m['created_at']))) ?></div>
        </div>
        <div style="margin:10px 0">
          <?php if ($m['topic']): ?><span class="pill pill-muted"><?= e($m['topic']) ?></span><?php endif; ?>
          <?php if ($m['order_no']): ?><span class="pill pill-muted">Order <?= e($m['order_no']) ?></span><?php endif; ?>
        </div>
        <p style="margin:0;white-space:pre-wrap;line-height:1.6"><?= e($m['body']) ?></p>
        <div style="display:flex;gap:8px;margin-top:14px">
          <form method="post" action="messages" style="display:inline">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <input type="hidden" name="action" value="<?= $m['is_read'] ? 'unread' : 'read' ?>">
            <button class="btn btn-ghost btn-sm"><?= $m['is_read'] ? 'Mark unread' : 'Mark read' ?></button>
          </form>
          <form method="post" action="messages" style="display:inline" onsubmit="return confirm('Delete this message?')">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="action" value="delete">
            <button class="btn btn-bad btn-sm">Delete</button>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php admin_foot();
