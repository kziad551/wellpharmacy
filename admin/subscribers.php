<?php
require __DIR__ . '/inc/layout.php';

if (is_post() && input('action') === 'delete') {
    csrf_check();
    q("DELETE FROM subscribers WHERE id = ?", [(int) input('id')]);
    flash('Subscriber removed.');
    redirect('subscribers');
}

$PER   = list_per();
$page  = list_page();
$found = (int) val("SELECT COUNT(*) FROM subscribers");
$list  = rows("SELECT * FROM subscribers ORDER BY created_at DESC LIMIT $PER OFFSET " . list_offset());
$emails = implode(', ', array_column($list, 'email'));

/** One subscriber row — shared by the first paint and each infinite-scroll slice. */
function subscriber_row(array $s): void { ?>
  <tr>
    <td class="c-main"><a class="nm" href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a></td>
    <td data-label="Source"><span class="pill pill-muted"><?= e($s['source']) ?></span></td>
    <td data-label="Joined"><?= e(date('M j, Y', strtotime($s['created_at']))) ?></td>
    <td class="c-act" style="text-align:right">
      <form method="post" action="subscribers" style="display:inline" onsubmit="return confirm('Remove this subscriber?')">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
        <button class="btn btn-bad btn-sm">Remove</button>
      </form>
    </td>
  </tr>
<?php }

if (list_partial()) { foreach ($list as $s) subscriber_row($s); exit; }

admin_head('Subscribers', 'subscribers', list_count_label($found, 'newsletter subscriber'));
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
    <?php foreach ($list as $s) subscriber_row($s); ?>
    </tbody>
  </table>
</div>
<?php list_sentinel($found, $page); ?>
</div>
<?php endif; ?>
<?php admin_foot();
