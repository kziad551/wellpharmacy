<?php
require __DIR__ . '/inc/layout.php';

$STATUSES = ['new','confirmed','processing','shipped','delivered','cancelled'];
$STATUS_PILL = ['new'=>'info','confirmed'=>'info','processing'=>'warn','shipped'=>'warn','delivered'=>'good','cancelled'=>'bad'];

$filter = (string) input('status');
$search = trim((string) input('q'));

$where = []; $args = [];
if (in_array($filter, $STATUSES, true)) { $where[] = "order_status = ?"; $args[] = $filter; }
if ($search !== '') { $where[] = "(order_no LIKE ? OR customer_name LIKE ? OR phone LIKE ?)"; $s = "%$search%"; array_push($args, $s, $s, $s); }
$wsql  = $where ? " WHERE " . implode(' AND ', $where) : '';
$PER   = list_per();
$page  = list_page();
$found = (int) val("SELECT COUNT(*) FROM orders" . $wsql, $args);
$list  = rows("SELECT * FROM orders" . $wsql . " ORDER BY created_at DESC LIMIT $PER OFFSET " . list_offset(), $args);

$counts = [];
foreach (rows("SELECT order_status, COUNT(*) n FROM orders GROUP BY order_status") as $r) $counts[$r['order_status']] = (int)$r['n'];
$total = array_sum($counts);

/* opening this page marks every existing order as seen, so the sidebar badge clears;
   anything that arrives afterwards will show up again on the next poll */
$_SESSION['orders_seen_id'] = (int) val("SELECT COALESCE(MAX(id),0) FROM orders");

/** One order row — shared by the first paint and each infinite-scroll slice. */
function order_row(array $o, array $pill): void { ?>
  <tr>
    <td class="c-main"><a class="nm" href="order?id=<?= (int)$o['id'] ?>">#<?= e($o['order_no']) ?></a><div class="br"><?= e(date('M j, Y H:i', strtotime($o['created_at']))) ?></div></td>
    <td data-label="Customer"><?= e($o['customer_name']) ?><div class="br"><?= e($o['phone']) ?></div></td>
    <td data-label="Area"><?= e($o['governorate'] ?: $o['city'] ?: '—') ?></td>
    <td data-label="Total"><?= money($o['total']) ?></td>
    <td data-label="Payment"><span class="pill pill-muted"><?= strtoupper(e($o['payment_method'])) ?></span> <span class="pill <?= $o['payment_status']==='paid'?'pill-good':'pill-muted' ?>"><?= e($o['payment_status']) ?></span></td>
    <td data-label="Status"><span class="pill pill-<?= $pill[$o['order_status']] ?? 'muted' ?>"><?= e($o['order_status']) ?></span></td>
    <td class="c-act" style="text-align:right"><a class="btn btn-ghost btn-sm" href="order?id=<?= (int)$o['id'] ?>">View</a></td>
  </tr>
<?php }

if (list_partial()) { foreach ($list as $o) order_row($o, $STATUS_PILL); exit; }

admin_head('Orders', 'orders', $total . ' order' . ($total === 1 ? '' : 's'));
?>
<div class="page-actions" style="flex-wrap:wrap;gap:8px">
  <a class="btn <?= $filter===''?'btn-primary':'btn-ghost' ?> btn-sm" href="orders">All (<?= $total ?>)</a>
  <?php foreach ($STATUSES as $st): ?>
    <a class="btn <?= $filter===$st?'btn-primary':'btn-ghost' ?> btn-sm" href="orders?status=<?= e($st) ?>"><?= ucfirst($st) ?> (<?= $counts[$st] ?? 0 ?>)</a>
  <?php endforeach; ?>
  <div class="spacer"></div>
  <?php admin_search('orders', $search, 'Search order #, name, phone…', '', ['status' => $filter]); ?>
</div>

<div class="a-card"><div class="bd" style="padding:0">
  <?php if (!$list): ?>
    <div class="empty">No orders<?= $filter ? ' with this status' : ' yet' ?>.</div>
  <?php else: ?>
  <table class="a-table">
    <thead><tr><th>Order</th><th>Customer</th><th>Area</th><th>Total</th><th>Payment</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $o) order_row($o, $STATUS_PILL); ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php list_sentinel($found, $page); ?>
</div>
<?php admin_foot();
