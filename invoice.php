<?php
/* Printable invoice for one of the signed-in customer's own orders. */
require __DIR__ . '/inc/functions.php';
require __DIR__ . '/inc/customer.php';
require_customer();

$cid = customer_id();
$no  = (string) input('order', '');
$o   = row("SELECT * FROM orders WHERE order_no = ? AND customer_id = ?", [$no, $cid]);   // scoped: never another shopper's order
if (!$o) { http_response_code(404); $PAGE_TITLE = 'Invoice not found'; include __DIR__ . '/404.php'; exit; }
$items = rows("SELECT * FROM order_items WHERE order_id = ?", [(int) $o['id']]);

$PAGE_TITLE = 'Invoice ' . $o['order_no'];
$ACTIVE = ''; $NO_POPUP = true;
require __DIR__ . '/inc/auth-css.php';
$HEAD_CSS = $AUTH_CSS . <<<CSS
<style>
  .inv{max-width:720px;margin-inline:auto}
  .inv table{width:100%;border-collapse:collapse;margin:18px 0}
  .inv th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted);
    border-bottom:1px solid var(--border-2);padding:0 0 8px}
  .inv td{padding:10px 0;font-size:14px;border-bottom:1px solid var(--border-2)}
  .inv td.r,.inv th.r{text-align:right}
  .tot{display:flex;justify-content:space-between;font-size:14px;padding:4px 0}
  .tot.grand{font-family:var(--fp);font-size:20px;font-weight:600;border-top:1px solid var(--border);margin-top:8px;padding-top:12px}
  @media print{ .noprint,#chrome-top,#chrome-foot{display:none!important} .panel{border:0;padding:0} body{background:#fff} }
</style>
CSS;
include __DIR__ . '/inc/head.php';
?>
<div class="authwrap wide inv">
  <div class="acct-head noprint">
    <div><h1>invoice</h1><p class="muted" style="margin:6px 0 0;font-size:14px"><?= e($o['order_no']) ?></p></div>
    <div style="display:flex;gap:8px">
      <a class="btn btn-ghost btn-sm" href="account?tab=orders">back to orders</a>
      <button class="btn btn-primary btn-sm" onclick="window.print()">print / save PDF</button>
    </div>
  </div>

  <div class="panel">
    <div style="display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap">
      <div>
        <b style="font-family:var(--fp);font-size:20px"><?= e(setting('store_name', 'Well Pharmacy')) ?></b>
        <p class="muted" style="font-size:12.5px;margin:4px 0 0"><?= e(setting('store_address', 'Beirut, Lebanon')) ?></p>
      </div>
      <div style="text-align:right">
        <div style="font-family:var(--fp);font-size:18px;font-weight:600"><?= e($o['order_no']) ?></div>
        <div class="muted" style="font-size:12.5px"><?= e(date('j M Y, H:i', strtotime($o['created_at']))) ?></div>
        <span class="pill <?= e($o['order_status']) ?>" style="margin-top:6px"><?= e($o['order_status']) ?></span>
      </div>
    </div>

    <div style="margin-top:20px;font-size:13.5px;line-height:1.7">
      <b>Billed to</b><br>
      <?= e($o['customer_name']) ?><br>
      <?= e($o['phone']) ?><?= $o['email'] ? '<br>' . e($o['email']) : '' ?><br>
      <?= e($o['address']) ?><br><?= e(trim($o['city'] . ' ' . $o['governorate'])) ?>
    </div>

    <table>
      <thead><tr><th>Item</th><th class="r">Qty</th><th class="r">Price</th><th class="r">Total</th></tr></thead>
      <tbody>
        <?php foreach ($items as $i): ?>
          <tr>
            <td><?= e($i['name']) ?><br><span class="muted" style="font-size:12px"><?= e($i['brand']) ?></span></td>
            <td class="r"><?= (int) $i['qty'] ?></td>
            <td class="r"><?= e(money($i['price'])) ?></td>
            <td class="r"><?= e(money($i['line_total'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div style="max-width:280px;margin-left:auto">
      <div class="tot"><span class="muted">Subtotal</span><span><?= e(money($o['subtotal'])) ?></span></div>
      <?php if ((float) $o['discount'] > 0): ?>
        <div class="tot"><span class="muted">Discount<?= $o['coupon_code'] ? ' (' . e($o['coupon_code']) . ')' : '' ?></span><span>-<?= e(money($o['discount'])) ?></span></div>
      <?php endif; ?>
      <div class="tot"><span class="muted">Shipping</span><span><?= (float) $o['shipping'] > 0 ? e(money($o['shipping'])) : 'Free' ?></span></div>
      <div class="tot grand"><span>Total</span><span><?= e(money($o['total'])) ?></span></div>
      <p class="muted" style="font-size:12px;margin-top:10px">Payment: <?= $o['payment_method'] === 'cod' ? 'Cash on Delivery' : 'Card' ?> · <?= e($o['payment_status']) ?></p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/inc/foot.php'; ?>
