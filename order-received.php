<?php
require __DIR__ . '/inc/functions.php';

$order_no = (string) ($_SESSION['last_order'] ?? '');
$order = $order_no ? row("SELECT * FROM orders WHERE order_no = ?", [$order_no]) : null;

$PAGE_TITLE = 'Order confirmed — ' . setting('store_name', 'WELL SHOP');
$ACTIVE = 'Shop All';
$NO_POPUP = true;
$wa = setting('whatsapp_number', '9613627766');

$HEAD_CSS = <<<CSS
<style>
  .oc{max-width:760px;margin-inline:auto;padding-block:44px 60px}
  .oc-top{text-align:center;margin-bottom:26px}
  .oc-check{width:64px;height:64px;border-radius:50%;background:var(--mint,#7D7A5E);color:#fff;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 14px}
  .oc-top h1{font-family:var(--fp);font-size:clamp(26px,3.4vw,38px);font-weight:600;text-transform:lowercase;margin:0 0 6px}
  .oc-top .no{font-size:14px;color:var(--ink-soft)}
  .oc-top .no b{color:var(--ink)}
  .oc-card{background:#fff;border:1px solid var(--border-2,#E4DFD3);border-radius:18px;padding:24px;margin-bottom:18px}
  .oc-card h3{font-family:var(--fp);font-size:18px;margin:0 0 14px}
  .oc-it{display:grid;grid-template-columns:1fr auto;gap:10px;font-size:14px;padding:8px 0;border-bottom:1px solid var(--cream-2)}
  .oc-it .q{color:var(--text-muted);font-size:12.5px}
  .oc-line{display:flex;justify-content:space-between;font-size:14px;padding:6px 0;color:var(--ink-soft)}
  .oc-line.total{border-top:1px solid var(--border-2,#E4DFD3);margin-top:6px;padding-top:12px;font-size:19px;font-weight:700;color:var(--ink)}
  .oc-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 20px;font-size:13.5px;color:var(--ink-soft)}
  .oc-grid b{color:var(--ink);font-weight:600}
</style>
CSS;

include __DIR__ . '/inc/head.php';

if (!$order):
?>
<div class="wrap oc">
  <div class="oc-top">
    <h1>no recent order</h1>
    <p class="muted">We couldn't find a recent order for this session. If you just ordered, check your phone for our confirmation — or contact us.</p>
    <div style="margin-top:18px"><a class="btn btn-primary" href="index">Back home</a> <a class="btn btn-ghost" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp us</a></div>
  </div>
</div>
<?php else:
  $items = rows("SELECT * FROM order_items WHERE order_id = ?", [$order['id']]);
  $note  = $_SESSION['order_note'] ?? ''; unset($_SESSION['order_note']);
?>
<div class="wrap oc">
  <div class="oc-top">
    <div class="oc-check">✓</div>
    <h1>thank you — order confirmed!</h1>
    <p class="no">Your order <b>#<?= e($order['order_no']) ?></b> has been placed. We'll contact you shortly to confirm delivery.</p>
  </div>
  <?php if ($note): ?><div class="oc-card" style="border-color:#e7c4bb;background:#fdf6f3"><div style="color:#b04a2f;font-size:13.5px"><?= e($note) ?></div></div><?php endif; ?>

  <div class="oc-card">
    <h3>Order summary</h3>
    <?php foreach ($items as $it): ?>
      <div class="oc-it"><div><?= e($it['name']) ?><div class="q"><?= e($it['brand']) ?> · Qty <?= (int)$it['qty'] ?></div></div><b><?= money($it['line_total']) ?></b></div>
    <?php endforeach; ?>
    <div style="margin-top:14px">
      <div class="oc-line"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
      <?php if ($order['discount'] > 0): ?><div class="oc-line"><span>Discount<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></span><span>-<?= money($order['discount']) ?></span></div><?php endif; ?>
      <div class="oc-line"><span>Shipping</span><span><?= $order['shipping'] > 0 ? money($order['shipping']) : 'FREE' ?></span></div>
      <div class="oc-line total"><span>Total</span><span><?= money($order['total']) ?></span></div>
    </div>
  </div>

  <div class="oc-card">
    <h3>Delivery &amp; payment</h3>
    <div class="oc-grid">
      <span>Name</span><b><?= e($order['customer_name']) ?></b>
      <span>Phone</span><b><?= e($order['phone']) ?></b>
      <span>Address</span><b><?= e($order['address']) ?><?= $order['city'] ? ', ' . e($order['city']) : '' ?><?= $order['governorate'] ? ', ' . e($order['governorate']) : '' ?></b>
      <span>Payment</span><b><?= $order['payment_method'] === 'cod' ? 'Cash on Delivery' : 'Card payment' ?></b>
    </div>
  </div>

  <div style="text-align:center"><a class="btn btn-primary" href="skincare">Continue shopping</a> <a class="btn btn-ghost" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">Questions? WhatsApp us</a></div>
</div>
<?php endif; ?>

<div id="usp"></div>
<?php
$PAGE_JS = "<script>document.getElementById('usp').innerHTML = WELL.uspHTML(); WELL.guardImages(document);</script>";
include __DIR__ . '/inc/foot.php';
