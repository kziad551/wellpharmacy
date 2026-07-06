<?php
require __DIR__ . '/inc/layout.php';

if (is_post() && input('action') === 'delete') {
    csrf_check();
    q("DELETE FROM subscribers WHERE id = ?", [(int) input('id')]);
    flash('Subscriber removed.');
    redirect('subscribers');
}

$list = rows("SELECT * FROM subscribers ORDER BY created_at DESC");
$emails = implode(', ', array_column($list, 'email'));

admin_head('Subscribers', 'subscribers', count($list) . ' newsletter subscriber' . (count($list) === 1 ? '' : 's'));
?>
<?php if (!$list): ?>
  <div class="a-card"><div class="empty">No subscribers yet. Newsletter sign-ups land here.</div></div>
<?php else: ?>
<div class="a-card" style="margin-bottom:18px"><div class="hd"><h2>Export</h2><span class="muted" style="font-size:12.5px">Copy all emails for your mailing tool</span></div>
  <div class="bd"><textarea class="input" rows="3" readonly onclick="this.select()" style="font-size:12.5px"><?= e($emails) ?></textarea></div>
</div>
<div class="a-card"><div class="bd" style="padding:0">
  <table class="a-table">
    <thead><tr><th>Email</th><th>Source</th><th>Joined</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $s): ?>
      <tr>
        <td><a class="nm" href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a></td>
        <td><span class="pill pill-muted"><?= e($s['source']) ?></span></td>
        <td><?= e(date('M j, Y', strtotime($s['created_at']))) ?></td>
        <td style="text-align:right">
          <form method="post" action="subscribers" style="display:inline" onsubmit="return confirm('Remove this subscriber?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="btn btn-bad btn-sm">Remove</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div></div>
<?php endif; ?>
<?php admin_foot();
