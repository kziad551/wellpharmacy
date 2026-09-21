<?php
require __DIR__ . '/inc/layout.php';

if (is_post() && input('action') === 'delete') {
    csrf_check();
    q("DELETE FROM coupons WHERE id = ?", [(int) input('id')]);
    flash('Coupon deleted.');
    redirect('coupons');
}
if (is_post() && input('action') === 'toggle') {
    csrf_check();
    q("UPDATE coupons SET active = 1 - active WHERE id = ?", [(int) input('id')]);
    flash('Coupon updated.');
    redirect('coupons');
}

function coupon_value(array $c): string {
    if ($c['type'] === 'percent')  return (int)$c['value'] . '% off';
    if ($c['type'] === 'fixed')    return money($c['value']) . ' off';
    return 'Free shipping';
}

$search = trim((string) input('q'));
$where = ''; $args = [];
if ($search !== '') { $where = "WHERE code LIKE ?"; $args = ["%$search%"]; }

$PER   = list_per();
$page  = list_page();
$total = (int) val("SELECT COUNT(*) FROM coupons $where", $args);
$list  = rows("SELECT * FROM coupons $where ORDER BY active DESC, code LIMIT $PER OFFSET " . list_offset(), $args);

function coupon_row(array $c): void { ?>
  <tr>
    <td class="c-main"><a class="nm" href="coupon-edit?id=<?= (int)$c['id'] ?>"><?= e($c['code']) ?></a></td>
    <td data-label="Discount"><?= e(coupon_value($c)) ?></td>
    <td data-label="Min spend"><?= $c['min_spend'] > 0 ? money($c['min_spend']) : '—' ?></td>
    <td data-label="Expires"><?= $c['expires_at'] ? e($c['expires_at']) : 'never' ?></td>
    <td data-label="Used"><?= (int)$c['used_count'] ?><?= $c['usage_limit'] !== null ? ' / ' . (int)$c['usage_limit'] : '' ?></td>
    <td data-label="Status">
      <form method="post" action="coupons" style="display:inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <button class="pill <?= $c['active'] ? 'pill-good' : 'pill-muted' ?>" style="border:0;cursor:pointer"><?= $c['active'] ? 'Active' : 'Off' ?></button>
      </form>
    </td>
    <td class="c-act" style="text-align:right;white-space:nowrap">
      <a class="btn btn-ghost btn-sm" href="coupon-edit?id=<?= (int)$c['id'] ?>">Edit</a>
      <form method="post" action="coupons" style="display:inline" onsubmit="return confirm('Delete coupon &quot;<?= e($c['code']) ?>&quot;?')">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <button class="btn btn-bad btn-sm">Delete</button>
      </form>
    </td>
  </tr>
<?php }

if (list_partial()) { foreach ($list as $c) coupon_row($c); exit; }

admin_head('Coupons', 'coupons', list_count_label($total, 'coupon'));
?>
<div class="page-actions">
  <?php admin_search('coupons', $search, 'Search codes…'); ?>
  <div class="spacer"></div>
  <a class="btn btn-primary" href="coupon-edit"><?= aicon('plus') ?> Add coupon</a>
</div>

<div class="a-card">
  <div class="bd" style="padding:0">
    <?php if (!$list): ?>
      <div class="empty">No coupons found.<?= $search ? ' Try a different search.' : '' ?></div>
    <?php else: ?>
    <table class="a-table">
      <thead><tr><th>Code</th><th>Discount</th><th>Min spend</th><th>Expires</th><th>Used</th><th>Status</th><th></th></tr></thead>
      <tbody><?php foreach ($list as $c) coupon_row($c); ?></tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php list_sentinel($total, $page); ?>
</div>
<?php admin_foot();
