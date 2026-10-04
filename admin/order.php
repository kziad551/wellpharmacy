<?php
require __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/../inc/gifts.php';

$STATUSES     = ['new','confirmed','processing','shipped','delivered','cancelled'];
$PAY_STATUSES = ['pending','paid','failed','refunded'];
$STATUS_PILL  = ['new'=>'info','confirmed'=>'info','processing'=>'warn','shipped'=>'warn','delivered'=>'good','cancelled'=>'bad'];

/* A custom gift line (product_id from gift_custom_pid()) is never a product, even if a
   product now has the same id (one named "Gift 11" slugs to gift-11): it must not move
   that product's stock, link to it or borrow its image. */
$customGift = fn(array $li): bool => !empty($li['is_gift']) && preg_match('/\d+$/', (string) $li['product_id'], $m)
                                     && $li['product_id'] === gift_custom_pid((int) $m[0]);

$id = (int) input('id');
$o  = $id > 0 ? row("SELECT * FROM orders WHERE id = ?", [$id]) : null;
if (!$o) { flash('Order not found.', 'err'); redirect('orders'); }

if (is_post()) {
    csrf_check();
    $act = (string) input('action');
    if ($act === 'update') {
        $os = in_array(input('order_status'), $STATUSES, true) ? (string) input('order_status') : $o['order_status'];
        $ps = in_array(input('payment_status'), $PAY_STATUSES, true) ? (string) input('payment_status') : $o['payment_status'];
        $was = $o['order_status'];
        $note = '';

        /* Stock moves only on a real CHANGE of cancelled-ness, never on a re-save —
           otherwise saving a cancelled order twice would credit the stock twice. */
        $pdo = db();
        $pdo->beginTransaction();
        try {
            /* `notes` is the CUSTOMER's own note from checkout — never overwrite it.
               Staff notes go in admin_notes. */
            q("UPDATE orders SET order_status = ?, payment_status = ?, admin_notes = ? WHERE id = ?",
              [$os, $ps, trim((string) input('admin_notes')), $id]);

            /* Catalog gift lines carry a real product_id and were taken from products.stock
               at checkout, so the loops below move them too. Custom gifts (gift-N) are skipped
               there; their own counter in product_gifts moves via gift_restock_custom(). */
            if ($was !== 'cancelled' && $os === 'cancelled') {
                $lis = rows("SELECT product_id, qty, is_gift FROM order_items WHERE order_id = ?", [$id]);
                foreach ($lis as $li) {
                    if ($customGift($li)) continue;
                    q("UPDATE products SET stock = stock + ? WHERE id = ?", [(int) $li['qty'], $li['product_id']]);
                }
                gift_restock_custom($lis, +1);
                $note = ' Stock was returned to inventory.';
            } elseif ($was === 'cancelled' && $os !== 'cancelled') {
                $lis = rows("SELECT product_id, qty, is_gift FROM order_items WHERE order_id = ?", [$id]);
                foreach ($lis as $li) {
                    if ($customGift($li)) continue;
                    q("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?", [(int) $li['qty'], $li['product_id']]);
                }
                gift_restock_custom($lis, -1);
                $note = ' Stock was taken back out of inventory.';
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('Could not update the order.', 'err');
            redirect("order?id=$id");
        }
        flash('Order updated.' . $note);
    }
    redirect("order?id=$id");
}

/* p_id is NULL when the line's product no longer exists (deleted, or a custom gift-N) */
$items = rows("SELECT oi.*, p.image AS p_image, p.id AS p_id FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ? ORDER BY oi.id", [$id]);
foreach ($items as $k => $it) if ($customGift($it)) $items[$k]['p_id'] = $items[$k]['p_image'] = null;

admin_head('Order #' . $o['order_no'], 'orders', date('M j, Y · H:i', strtotime($o['created_at'])));
?>
<div class="page-actions"><a class="btn btn-ghost" href="orders">← All orders</a><div class="spacer"></div>
  <span class="pill pill-<?= $STATUS_PILL[$o['order_status']] ?? 'muted' ?>" style="font-size:13px"><?= e($o['order_status']) ?></span>
</div>

<div class="a-grid" style="grid-template-columns:1.6fr 1fr">
  <div style="display:flex;flex-direction:column;gap:18px">
    <div class="a-card"><div class="hd"><h2>Items</h2></div><div class="bd" style="padding:0">
      <table class="a-table">
        <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th style="text-align:right">Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): $gift = !empty($it['is_gift']); ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:11px">
                <img src="<?= e(asrc(order_item_image($it))) ?>" alt=""
                     style="width:44px;height:44px;object-fit:contain;background:#fff;border:1px solid var(--a-border2);border-radius:8px;padding:3px;flex:none">
                <span><?php if ($it['p_id'] !== null): ?><a class="nm" href="product-edit?id=<?= e($it['product_id']) ?>"><?= e($it['name']) ?></a><?php else: ?><span class="nm"><?= e($it['name']) ?></span><?php endif; ?><?php if ($gift): ?> <span class="pill pill-good">Free gift</span><?php elseif(!empty($it['variant'])): ?> <span class="pill pill-muted"><?= e($it['variant']) ?></span><?php endif; ?><div class="br"><?= e($it['brand']) ?></div></span>
              </div>
            </td>
            <td><?= $gift ? 'FREE' : money($it['price']) ?></td>
            <td><?= (int)$it['qty'] ?></td>
            <td style="text-align:right"><?= $gift ? 'FREE' : money($it['line_total']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="bd">
        <div style="max-width:280px;margin-left:auto">
          <div style="display:flex;justify-content:space-between;padding:5px 0;color:var(--a-muted,#666)"><span>Subtotal</span><span><?= money($o['subtotal']) ?></span></div>
          <?php if ($o['discount'] > 0): ?><div style="display:flex;justify-content:space-between;padding:5px 0;color:var(--a-muted,#666)"><span>Discount<?= $o['coupon_code'] ? ' ('.e($o['coupon_code']).')' : '' ?></span><span>-<?= money($o['discount']) ?></span></div><?php endif; ?>
          <div style="display:flex;justify-content:space-between;padding:5px 0;color:var(--a-muted,#666)"><span>Shipping</span><span><?= $o['shipping'] > 0 ? money($o['shipping']) : 'FREE' ?></span></div>
          <div style="display:flex;justify-content:space-between;padding:10px 0 0;font-weight:700;font-size:17px;border-top:1px solid var(--a-border2,#eee);margin-top:6px"><span>Total</span><span><?= money($o['total']) ?></span></div>
        </div>
      </div>
    </div></div>
  </div>

  <div style="display:flex;flex-direction:column;gap:18px">
    <form method="post" action="order?id=<?= (int)$id ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="update">
      <div class="a-card"><div class="hd"><h2>Manage</h2></div><div class="bd">
        <div class="field"><label>Order status</label><select class="input" name="order_status">
          <?php foreach ($STATUSES as $st): ?><option value="<?= e($st) ?>" <?= $st===$o['order_status']?'selected':'' ?>><?= ucfirst($st) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>Payment status</label><select class="input" name="payment_status">
          <?php foreach ($PAY_STATUSES as $ps): ?><option value="<?= e($ps) ?>" <?= $ps===$o['payment_status']?'selected':'' ?>><?= ucfirst($ps) ?></option><?php endforeach; ?>
        </select></div>
        <?php if (trim((string) $o['notes']) !== ''): ?>
          <div class="field">
            <label>Customer's note <span class="muted" style="font-weight:400">(read-only — their words)</span></label>
            <div style="background:var(--cream,#F4F1E9);border-radius:10px;padding:10px 12px;font-size:13px;line-height:1.5"><?= e($o['notes']) ?></div>
          </div>
        <?php endif; ?>
        <div class="field"><label>Internal notes <span class="muted" style="font-weight:400">(staff only)</span></label>
          <textarea class="input" name="admin_notes" rows="3" placeholder="Notes for your team…"><?= e($o['admin_notes'] ?? '') ?></textarea></div>
        <button class="btn btn-primary btn-block">Save changes</button>
      </div></div>
    </form>

    <div class="a-card"><div class="hd"><h2>Customer</h2></div><div class="bd">
      <div class="field"><label>Name</label><div><?= e($o['customer_name']) ?></div></div>
      <div class="field"><label>Phone</label><div><a href="tel:<?= e($o['phone']) ?>"><?= e($o['phone']) ?></a> · <a href="https://wa.me/<?= e(preg_replace('/\D/', '', $o['phone'])) ?>" target="_blank" rel="noopener">WhatsApp</a></div></div>
      <?php if ($o['email']): ?><div class="field"><label>Email</label><div><a href="mailto:<?= e($o['email']) ?>"><?= e($o['email']) ?></a></div></div><?php endif; ?>
      <div class="field"><label>Address</label><div><?= e($o['address']) ?><?= $o['city'] ? ', ' . e($o['city']) : '' ?><?= $o['governorate'] ? ', ' . e($o['governorate']) : '' ?></div></div>
      <div class="field"><label>Payment method</label><div><?= $o['payment_method'] === 'cod' ? 'Cash on Delivery' : 'Card payment' ?></div></div>
    </div></div>
  </div>
</div>
<?php admin_foot();
