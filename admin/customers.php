<?php
require __DIR__ . '/inc/layout.php';

/* Registered shoppers. Deleting an account never deletes their orders —
   the order keeps its own name/phone/address snapshot, so history stays intact. */
if (is_post() && input('action') === 'delete') {
    csrf_check();
    $cid = (int) input('id');
    q("UPDATE orders SET customer_id = NULL WHERE customer_id = ?", [$cid]);   // keep the orders, unlink them
    q("DELETE FROM customer_wishlist WHERE customer_id = ?", [$cid]);
    q("DELETE FROM customer_cart WHERE customer_id = ?", [$cid]);
    q("DELETE FROM customers WHERE id = ?", [$cid]);
    flash('Account deleted. Their past orders were kept.');
    redirect('customers');
}

$q = trim((string) input('q', ''));
$where = ''; $args = [];
if ($q !== '') {
    $where = "WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $like = "%$q%"; $args = [$like, $like, $like, $like];
}
$PER   = list_per();
$page  = list_page();
$found = (int) val("SELECT COUNT(*) FROM customers $where", $args);
$verified = (int) val("SELECT COUNT(*) FROM customers " . ($where ? $where . ' AND' : 'WHERE') . " verified = 1", $args);
$list  = rows("SELECT * FROM customers $where ORDER BY created_at DESC LIMIT $PER OFFSET " . list_offset(), $args);

/* order count + lifetime spend per customer, in one pass */
$stats = [];
foreach (rows("SELECT customer_id, COUNT(*) n, SUM(total) spent FROM orders WHERE customer_id IS NOT NULL GROUP BY customer_id") as $r) {
    $stats[(int) $r['customer_id']] = $r;
}

/** One customer row — shared by the first paint and each infinite-scroll slice. */
function customer_row(array $c, array $stats): void {
    $s = $stats[(int) $c['id']] ?? null; ?>
  <tr>
    <td class="c-main">
      <span class="nm"><?= e(trim($c['first_name'] . ' ' . $c['last_name'])) ?></span>
      <?php if (!(int) $c['verified']): ?><span class="pill pill-warn" style="margin-left:6px">unverified</span><?php endif; ?>
    </td>
    <td data-label="Contact">
      <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a>
      <?php if ($c['phone']): ?><br><span class="muted" style="font-size:12.5px">
        <a href="https://wa.me/<?= e(preg_replace('/\D/', '', $c['phone'])) ?>" target="_blank" rel="noopener"><?= e($c['phone']) ?></a>
      </span><?php endif; ?>
    </td>
    <td data-label="Area"><span class="muted" style="font-size:12.5px"><?= e(trim($c['city'] . ' ' . $c['governorate'])) ?: '—' ?></span></td>
    <td data-label="Orders">
      <?php if ($s): ?><a href="orders?q=<?= urlencode($c['email']) ?>"><?= (int) $s['n'] ?></a>
      <?php else: ?><span class="muted">0</span><?php endif; ?>
    </td>
    <td data-label="Spent"><?= $s ? e(money($s['spent'])) : '<span class="muted">—</span>' ?></td>
    <td data-label="Joined"><span class="muted" style="font-size:12.5px"><?= e(date('M j, Y', strtotime($c['created_at']))) ?></span></td>
    <td class="c-act" style="text-align:right">
      <form method="post" action="customers" style="display:inline" onsubmit="return confirm('Delete this account? Their past orders are kept.')">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
        <button class="btn btn-bad btn-sm">Delete</button>
      </form>
    </td>
  </tr>
<?php }

if (list_partial()) { foreach ($list as $c) customer_row($c, $stats); exit; }

admin_head('Customers', 'customers', list_count_label($found, 'registered account') . ' · ' . $verified . ' verified');
?>
<div class="page-actions">
  <?php admin_search('customers', $q, 'Search name, email or phone…'); ?>
  <div class="spacer"></div>
</div>

<?php if (!$list): ?>
  <div class="a-card"><div class="empty">
    <?= $q !== '' ? 'No accounts match that search.' : 'No registered accounts yet. Shoppers can also order as guests — those show under Orders.' ?>
  </div></div>
<?php else: ?>
<div class="a-card"><div class="bd" style="padding:0">
  <table class="a-table">
    <thead><tr><th>Customer</th><th>Contact</th><th>Area</th><th>Orders</th><th>Spent</th><th>Joined</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($list as $c) customer_row($c, $stats); ?>
    </tbody>
  </table>
</div>
<?php list_sentinel($found, $page); ?>
</div>
<?php endif; ?>
<?php admin_foot(); ?>
