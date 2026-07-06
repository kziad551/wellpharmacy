<?php
require __DIR__ . '/inc/layout.php';

$nProducts = (int) val("SELECT COUNT(*) FROM products");
$nActive   = (int) val("SELECT COUNT(*) FROM products WHERE status='active'");
$nOrders   = (int) val("SELECT COUNT(*) FROM orders");
$nNew      = (int) val("SELECT COUNT(*) FROM orders WHERE order_status='new'");
$revenue   = (float) val("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status='paid' OR payment_method='cod'");
$lowStock  = (int) val("SELECT COUNT(*) FROM products WHERE stock <= low_stock AND status='active'");
$recent    = rows("SELECT * FROM orders ORDER BY created_at DESC LIMIT 8");
$lowList   = rows("SELECT id, name, stock, low_stock FROM products WHERE stock <= low_stock AND status='active' ORDER BY stock ASC LIMIT 6");

$STATUS_PILL = ['new'=>'info','confirmed'=>'info','processing'=>'warn','shipped'=>'warn','delivered'=>'good','cancelled'=>'bad'];

admin_head('Dashboard', 'dashboard', 'Welcome back — here\'s your store at a glance.');
?>
<div class="a-grid a-stats" style="margin-bottom:18px">
  <div class="stat"><div class="ic"><?= aicon('cart') ?></div><div class="k"><?= $nOrders ?></div><div class="l">Total orders</div></div>
  <div class="stat"><div class="ic"><?= aicon('tag') ?></div><div class="k"><?= money($revenue) ?></div><div class="l">Revenue</div></div>
  <div class="stat"><div class="ic"><?= aicon('box') ?></div><div class="k"><?= $nActive ?>/<?= $nProducts ?></div><div class="l">Active products</div></div>
  <div class="stat"><div class="ic"><?= aicon('ticket') ?></div><div class="k"><?= $nNew ?></div><div class="l">New orders</div></div>
</div>

<div class="a-grid" style="grid-template-columns:1.6fr 1fr">
  <div class="a-card">
    <div class="hd"><h2>Recent orders</h2><a class="btn btn-ghost btn-sm" href="orders">View all</a></div>
    <div class="bd" style="padding:0">
      <?php if (!$recent): ?>
        <div class="empty">No orders yet. They'll appear here as customers check out.</div>
      <?php else: ?>
      <table class="a-table">
        <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $o): ?>
          <tr>
            <td><a class="nm" href="order?id=<?= (int)$o['id'] ?>">#<?= e($o['order_no']) ?></a><div class="br"><?= e(date('M j, H:i', strtotime($o['created_at']))) ?></div></td>
            <td><?= e($o['customer_name']) ?><div class="br"><?= e($o['governorate'] ?: $o['city']) ?></div></td>
            <td><?= money($o['total']) ?></td>
            <td><span class="pill pill-muted"><?= strtoupper(e($o['payment_method'])) ?></span></td>
            <td><span class="pill pill-<?= $STATUS_PILL[$o['order_status']] ?? 'muted' ?>"><?= e($o['order_status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="a-card">
    <div class="hd"><h2>Low stock <?php if($lowStock): ?><span class="pill pill-warn"><?= $lowStock ?></span><?php endif; ?></h2><a class="btn btn-ghost btn-sm" href="products">Manage</a></div>
    <div class="bd" style="padding:0">
      <?php if (!$lowList): ?>
        <div class="empty">All products are well stocked. 🎉</div>
      <?php else: ?>
      <table class="a-table">
        <tbody>
        <?php foreach ($lowList as $p): ?>
          <tr>
            <td><a class="nm" href="product-edit?id=<?= e($p['id']) ?>"><?= e($p['name']) ?></a></td>
            <td style="text-align:right"><span class="pill <?= $p['stock']==0?'pill-bad':'pill-warn' ?>"><?= $p['stock']==0?'Out':'Only '.$p['stock'].' left' ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php admin_foot();
