<?php
require __DIR__ . '/inc/functions.php';

$STEPS = ['new'=>'Received','confirmed'=>'Confirmed','processing'=>'Preparing','shipped'=>'Out for delivery','delivered'=>'Delivered'];
$order = null; $err = '';

if (is_post()) {
    csrf_check();
    $no    = trim((string) input('order_no'));
    $phone = trim((string) input('phone'));
    if ($no === '' || $phone === '') {
        $err = 'Enter your order number and the phone you ordered with.';
    } else {
        $order = row("SELECT * FROM orders WHERE order_no = ? AND phone = ?", [$no, $phone]);
        if (!$order) $err = "We couldn't find an order with that number and phone.";
    }
}

$PAGE_TITLE = 'Track your order — ' . setting('store_name', 'WELL SHOP');
$ACTIVE = 'Shop All';
$wa = setting('whatsapp_number', '9613627766');
$HEAD_CSS = <<<CSS
<style>
  .trk{max-width:640px;margin-inline:auto;padding-block:40px 60px}
  .trk h1{font-family:var(--fp);font-size:clamp(28px,3.4vw,40px);font-weight:600;text-transform:lowercase;margin:10px 0 6px}
  .trk .sub{color:var(--ink-soft);margin:0 0 22px}
  .trk-card{background:#fff;border:1px solid var(--border-2,#E4DFD3);border-radius:18px;padding:24px}
  .trk-form .field{margin-bottom:14px}
  .trk-err{background:var(--coral,#c96);color:#fff;border-radius:12px;padding:12px 14px;font-size:13.5px;margin-bottom:14px}
  .steps{display:flex;flex-direction:column;gap:0;margin:8px 0 6px}
  .step{display:flex;gap:14px;align-items:flex-start;position:relative;padding-bottom:22px}
  .step:not(:last-child):before{content:"";position:absolute;left:13px;top:26px;bottom:0;width:2px;background:var(--cream-2)}
  .step.done:not(:last-child):before{background:var(--rose)}
  .step .dot{width:28px;height:28px;border-radius:50%;flex:none;display:flex;align-items:center;justify-content:center;background:var(--cream-2);color:var(--text-muted);font-size:14px;z-index:1}
  .step.done .dot{background:var(--rose);color:#fff}
  .step .lbl{font-weight:600;color:var(--ink);padding-top:3px}
  .step.done .lbl{color:var(--ink)} .step:not(.done) .lbl{color:var(--text-muted)}
  .trk-meta{display:grid;grid-template-columns:1fr 1fr;gap:6px 18px;font-size:13.5px;color:var(--ink-soft);margin-top:8px}
  .trk-meta b{color:var(--ink)}
  .cancelled-box{background:var(--cream);border-radius:12px;padding:16px;text-align:center;color:var(--ink-soft)}
</style>
CSS;

include __DIR__ . '/inc/head.php';
?>
<div class="wrap trk">
  <nav class="crumb"><a href="index">Home</a><span class="sep">›</span><b>Track Order</b></nav>
  <h1>track your order</h1>
  <p class="sub">Enter your order number and the phone number you used to check its status.</p>

  <div class="trk-card">
    <?php if ($err): ?><div class="trk-err"><?= e($err) ?></div><?php endif; ?>

    <?php if ($order): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <div><div class="muted" style="font-size:12.5px">Order</div><b style="font-family:var(--fp);font-size:20px">#<?= e($order['order_no']) ?></b></div>
        <div style="text-align:right"><div class="muted" style="font-size:12.5px">Placed</div><b><?= e(date('M j, Y', strtotime($order['created_at']))) ?></b></div>
      </div>

      <?php if ($order['order_status'] === 'cancelled'): ?>
        <div class="cancelled-box"><b style="color:var(--ink)">This order was cancelled.</b><p style="margin:6px 0 0">If you think this is a mistake, please contact us.</p></div>
      <?php else:
        $order_keys = array_keys($STEPS);
        $curIdx = array_search($order['order_status'], $order_keys, true);
        if ($curIdx === false) $curIdx = 0; ?>
        <div class="steps">
          <?php foreach ($STEPS as $k => $label): $i = array_search($k, $order_keys, true); $done = $i <= $curIdx; ?>
            <div class="step <?= $done ? 'done' : '' ?>"><span class="dot"><?= $done ? '✓' : ($i + 1) ?></span><span class="lbl"><?= e($label) ?></span></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="trk-meta">
        <span>Status</span><b><?= e(ucfirst($order['order_status'])) ?></b>
        <span>Payment</span><b><?= $order['payment_method']==='cod' ? 'Cash on Delivery' : 'Card' ?> · <?= e(ucfirst($order['payment_status'])) ?></b>
        <span>Deliver to</span><b><?= e($order['governorate'] ?: $order['city'] ?: '—') ?></b>
        <span>Total</span><b><?= money($order['total']) ?></b>
      </div>
      <div style="margin-top:18px"><a class="btn btn-ghost btn-block" href="order-tracking">Track another order</a></div>

    <?php else: ?>
      <form class="trk-form" method="post" action="order-tracking">
        <?= csrf_field() ?>
        <div class="field"><label>Order number</label><input class="input" name="order_no" placeholder="WS-2026-…" value="<?= e((string)input('order_no')) ?>" required></div>
        <div class="field"><label>Phone number</label><input class="input" name="phone" placeholder="+961 …" value="<?= e((string)input('phone')) ?>" required></div>
        <button class="btn btn-primary btn-block">Track order</button>
        <p class="muted" style="font-size:12.5px;margin:12px 0 0;text-align:center">Can't find it? <a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">Message us on WhatsApp</a>.</p>
      </form>
    <?php endif; ?>
  </div>
</div>

<div id="usp"></div>
<?php
$PAGE_JS = "<script>document.getElementById('usp').innerHTML = WELL.uspHTML(); WELL.guardImages(document);</script>";
include __DIR__ . '/inc/foot.php';
