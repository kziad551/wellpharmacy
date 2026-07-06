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

$list = rows("SELECT * FROM coupons ORDER BY active DESC, code");

function coupon_value(array $c): string {
    if ($c['type'] === 'percent')  return (int)$c['value'] . '% off';
    if ($c['type'] === 'fixed')    return money($c['value']) . ' off';
    return 'Free shipping';
}

admin_head('Coupons', 'coupons', count($list) . ' coupon' . (count($list) === 1 ? '' : 's'));
?>
<div class="page-actions">
  <div class="spacer"></div>
  <a class="btn btn-primary" href="coupon-edit"><?= aicon('plus') ?> Add coupon</a>
</div>

<div class="a-card"><div class="bd" style="padding:0">
  <?php if (!$list): ?>
    <div class="empty">No coupons yet.</div>
  <?php else: ?>
  <table class="a-table">
    <thead><tr><th>Code</th><th>Discount</th><th>Min spend</th><th>Expires</th><th>Used</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $c): ?>
      <tr>
        <td><a class="nm" href="coupon-edit?id=<?= (int)$c['id'] ?>"><?= e($c['code']) ?></a></td>
        <td><?= e(coupon_value($c)) ?></td>
        <td><?= $c['min_spend'] > 0 ? money($c['min_spend']) : '—' ?></td>
        <td><?= $c['expires_at'] ? e($c['expires_at']) : 'never' ?></td>
        <td><?= (int)$c['used_count'] ?><?= $c['usage_limit'] !== null ? ' / ' . (int)$c['usage_limit'] : '' ?></td>
        <td>
          <form method="post" action="coupons" style="display:inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="pill <?= $c['active'] ? 'pill-good' : 'pill-muted' ?>" style="border:0;cursor:pointer"><?= $c['active'] ? 'Active' : 'Off' ?></button>
          </form>
        </td>
        <td style="text-align:right;white-space:nowrap">
          <a class="btn btn-ghost btn-sm" href="coupon-edit?id=<?= (int)$c['id'] ?>">Edit</a>
          <form method="post" action="coupons" style="display:inline" onsubmit="return confirm('Delete coupon &quot;<?= e($c['code']) ?>&quot;?')">
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
<p class="muted" style="font-size:12.5px;margin-top:14px">Coupons will be applied at checkout once the cart &amp; checkout are built (Phase B). You can create and manage the codes now.</p>
<?php admin_foot();
