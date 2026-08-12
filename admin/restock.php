<?php
/* Admin → Restock Alerts: who is waiting for which out-of-stock product.
   Emails go out automatically when a product's stock is saved above 0 — this
   screen is for visibility (and a manual re-send if ever needed). */
require __DIR__ . '/inc/layout.php';
require_once dirname(__DIR__) . '/inc/mailer.php';

if (is_post()) {
    csrf_check();
    $act = (string) input('action');

    if ($act === 'delete') {
        q("DELETE FROM restock_alerts WHERE id = ?", [(int) input('id')]);
        flash('Request removed.');
        redirect('restock');
    }
    if ($act === 'notify') {
        $pid  = (string) input('product_id');
        $prod = row("SELECT id, name, stock FROM products WHERE id = ?", [$pid]);
        if (!$prod)                      flash('Product not found.', 'err');
        elseif ((int) $prod['stock'] <= 0) flash('Put some stock on "' . $prod['name'] . '" first — then the alert can go out.', 'err');
        else {
            $n = notify_restock($pid);
            flash($n > 0 ? "Emailed $n " . ($n === 1 ? 'person' : 'people') . '.' : 'Nobody left to notify for that product.');
        }
        redirect('restock');
    }
}

/* grouped by product: the useful view is "what should I reorder" */
$groups = rows("SELECT r.product_id, p.name, p.stock, p.image,
                       COUNT(*) AS waiting, MAX(r.created_at) AS latest
                FROM restock_alerts r
                LEFT JOIN products p ON p.id = r.product_id
                WHERE r.notified_at IS NULL
                GROUP BY r.product_id, p.name, p.stock, p.image
                ORDER BY waiting DESC, latest DESC");
$done   = rows("SELECT r.*, p.name FROM restock_alerts r LEFT JOIN products p ON p.id = r.product_id
                WHERE r.notified_at IS NOT NULL ORDER BY r.notified_at DESC LIMIT 50");
$totalWaiting = (int) val("SELECT COUNT(*) FROM restock_alerts WHERE notified_at IS NULL");

admin_head('Restock Alerts', 'restock', $totalWaiting . ' shopper' . ($totalWaiting === 1 ? '' : 's') . ' waiting');
?>

<?php if (!smtp_configured()): ?>
<div class="a-card" style="border-color:#E0B84C;background:#FDF7E7;margin-bottom:18px"><div class="bd">
  <b>Email is in test mode.</b> No SMTP server is configured yet, so restock emails are written to
  <code>storage/mail/</code> on the server instead of being delivered. Add the SMTP details in
  <a href="settings">Settings → Email</a> to switch on real sending.
</div></div>
<?php endif; ?>

<div class="a-card"><div class="hd"><h2>Waiting for stock</h2></div><div class="bd" style="padding:0">
  <?php if (!$groups): ?>
    <div class="empty">Nobody is waiting right now. Requests appear here when a shopper clicks
      &ldquo;Notify me&rdquo; on an out-of-stock product.</div>
  <?php else: ?>
  <table class="a-table">
    <thead><tr><th></th><th>Product</th><th>Waiting</th><th>Stock</th><th>Latest request</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($groups as $g): ?>
      <tr>
        <td><?php if (!empty($g['image'])): ?><img class="thumb" src="<?= e(asrc($g['image'])) ?>" alt="" onerror="this.style.visibility='hidden'"><?php endif; ?></td>
        <td>
          <a class="nm" href="product-edit?id=<?= e(urlencode($g['product_id'])) ?>"><?= e($g['name'] ?: $g['product_id']) ?></a>
          <div class="br"><span class="faint"><?= e($g['product_id']) ?></span></div>
        </td>
        <td><b><?= (int) $g['waiting'] ?></b></td>
        <td>
          <?php if ((int) $g['stock'] > 0): ?><span class="pill pill-good"><?= (int) $g['stock'] ?> in stock</span>
          <?php else: ?><span class="pill pill-muted">out of stock</span><?php endif; ?>
        </td>
        <td><span class="faint"><?= e(date('j M Y', strtotime($g['latest']))) ?></span></td>
        <td style="text-align:right">
          <?php if ((int) $g['stock'] > 0): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Email <?= (int)$g['waiting'] ?> shopper(s) now?')">
              <?= csrf_field() ?><input type="hidden" name="action" value="notify"><input type="hidden" name="product_id" value="<?= e($g['product_id']) ?>">
              <button class="btn btn-primary btn-sm">Send alert now</button>
            </form>
          <?php else: ?>
            <a class="btn btn-ghost btn-sm" href="product-edit?id=<?= e(urlencode($g['product_id'])) ?>">Add stock</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div></div>

<div class="a-card" style="margin-top:20px"><div class="hd"><h2>Already notified</h2></div><div class="bd" style="padding:0">
  <?php if (!$done): ?>
    <div class="empty">Nothing sent yet.</div>
  <?php else: ?>
  <table class="a-table">
    <thead><tr><th>Product</th><th>Email</th><th>Notified</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($done as $d): ?>
      <tr>
        <td><?= e($d['name'] ?: $d['product_id']) ?></td>
        <td><span class="faint"><?= e($d['email']) ?></span></td>
        <td><span class="faint"><?= e(date('j M Y, H:i', strtotime($d['notified_at']))) ?></span></td>
        <td style="text-align:right">
          <form method="post" style="display:inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <button class="btn btn-bad btn-sm">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div></div>
<?php admin_foot(); ?>
