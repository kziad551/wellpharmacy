<?php
require __DIR__ . '/inc/layout.php';

$STATUSES = ['new','confirmed','processing','shipped','delivered','cancelled'];
$STATUS_PILL = ['new'=>'info','confirmed'=>'info','processing'=>'warn','shipped'=>'warn','delivered'=>'good','cancelled'=>'bad'];

$filter = (string) input('status');
$search = trim((string) input('q'));

$where = []; $args = [];
if (in_array($filter, $STATUSES, true)) { $where[] = "order_status = ?"; $args[] = $filter; }
if ($search !== '') { $where[] = "(order_no LIKE ? OR customer_name LIKE ? OR phone LIKE ?)"; $s = "%$search%"; array_push($args, $s, $s, $s); }
$sql = "SELECT * FROM orders" . ($where ? " WHERE " . implode(' AND ', $where) : '') . " ORDER BY created_at DESC";
$list = rows($sql, $args);

$counts = [];
foreach (rows("SELECT order_status, COUNT(*) n FROM orders GROUP BY order_status") as $r) $counts[$r['order_status']] = (int)$r['n'];
$total = array_sum($counts);

admin_head('Orders', 'orders', $total . ' order' . ($total === 1 ? '' : 's'));
?>
<div class="page-actions" style="flex-wrap:wrap;gap:8px">
  <a class="btn <?= $filter===''?'btn-primary':'btn-ghost' ?> btn-sm" href="orders">All (<?= $total ?>)</a>
  <?php foreach ($STATUSES as $st): ?>
    <a class="btn <?= $filter===$st?'btn-primary':'btn-ghost' ?> btn-sm" href="orders?status=<?= e($st) ?>"><?= ucfirst($st) ?> (<?= $counts[$st] ?? 0 ?>)</a>
  <?php endforeach; ?>
  <div class="spacer"></div>
  <form method="get" action="orders" style="max-width:280px">
    <?php if ($filter): ?><input type="hidden" name="status" value="<?= e($filter) ?>"><?php endif; ?>
    <input class="input" name="q" value="<?= e($search) ?>" placeholder="Search order #, name, phone…">
  </form>
</div>

<div class="a-card"><div class="bd" style="padding:0">
  <?php if (!$list): ?>
    <div class="empty">No orders<?= $filter ? ' with this status' : ' yet' ?>.</div>
  <?php else: ?>
  <table class="a-table">
    <thead><tr><th>Order</th><th>Customer</th><th>Area</th><th>Total</th><th>Payment</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $o): ?>
      <tr>
        <td><a class="nm" href="order?id=<?= (int)$o['id'] ?>">#<?= e($o['order_no']) ?></a><div class="br"><?= e(date('M j, Y H:i', strtotime($o['created_at']))) ?></div></td>
        <td><?= e($o['customer_name']) ?><div class="br"><?= e($o['phone']) ?></div></td>
        <td><?= e($o['governorate'] ?: $o['city'] ?: '—') ?></td>
        <td><?= money($o['total']) ?></td>
        <td><span class="pill pill-muted"><?= strtoupper(e($o['payment_method'])) ?></span> <span class="pill <?= $o['payment_status']==='paid'?'pill-good':'pill-muted' ?>"><?= e($o['payment_status']) ?></span></td>
        <td><span class="pill pill-<?= $STATUS_PILL[$o['order_status']] ?? 'muted' ?>"><?= e($o['order_status']) ?></span></td>
        <td style="text-align:right"><a class="btn btn-ghost btn-sm" href="order?id=<?= (int)$o['id'] ?>">View</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div></div>
<?php admin_foot();
