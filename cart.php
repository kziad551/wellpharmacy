<?php
require __DIR__ . '/inc/functions.php';

$PAGE_TITLE = 'Your Bag — ' . setting('store_name', 'WELL SHOP');
$ACTIVE = 'Shop All';
$NO_POPUP = true;
$freeThreshold = (float) setting('free_ship_threshold', '49');

$HEAD_CSS = <<<CSS
<style>
  .cartpg{max-width:1100px;margin-inline:auto;padding-block:34px 60px}
  .cartpg h1{font-family:var(--fp);font-size:clamp(28px,3.4vw,40px);font-weight:600;text-transform:lowercase;margin:0 0 20px}
  .cart-layout{display:grid;grid-template-columns:1fr 340px;gap:28px;align-items:start}
  .cart-list{display:flex;flex-direction:column;gap:14px}
  .crow{display:grid;grid-template-columns:88px 1fr auto;gap:16px;align-items:center;background:#fff;border:1px solid var(--border-2,#E4DFD3);border-radius:16px;padding:14px}
  .crow img{width:88px;height:88px;object-fit:cover;border-radius:12px;background:var(--cream-2)}
  .crow .br{font-size:12px;color:var(--text-muted)}
  .crow .ti{font-weight:600;color:var(--ink);margin:2px 0 8px;line-height:1.2}
  .crow .stepper{display:inline-flex;align-items:center;border:1px solid var(--border-2,#E4DFD3);border-radius:10px;overflow:hidden}
  .crow .stepper button{width:32px;height:32px;border:0;background:#fff;font-size:17px;cursor:pointer;color:var(--ink)}
  .crow .stepper button[disabled]{opacity:.35;cursor:not-allowed}
  .crow .cnote{font-size:11.5px;color:var(--coral-deep,#b04a2f);font-weight:600;margin-top:6px}
  .crow .stepper .q{min-width:32px;text-align:center;font-weight:600}
  .crow .r{display:flex;flex-direction:column;align-items:flex-end;gap:8px}
  .crow .pr{font-weight:700;color:var(--ink)}
  .crow .rm{background:none;border:0;color:var(--text-muted);font-size:12.5px;text-decoration:underline;cursor:pointer}
  .csum{background:#fff;border:1px solid var(--border-2,#E4DFD3);border-radius:18px;padding:22px;position:sticky;top:150px}
  .csum h3{font-family:var(--fp);font-size:20px;margin:0 0 14px}
  .csum .line{display:flex;justify-content:space-between;font-size:14px;padding:7px 0;color:var(--ink-soft)}
  .csum .line.total{border-top:1px solid var(--border-2,#E4DFD3);margin-top:6px;padding-top:12px;font-size:18px;font-weight:700;color:var(--ink)}
  .csum .free{background:var(--cream);border-radius:12px;padding:12px 14px;font-size:12.5px;color:var(--ink-soft);margin-bottom:14px}
  .csum .track{height:6px;border-radius:6px;background:var(--cream-2);overflow:hidden;margin-top:8px}
  .csum .track .fill{height:100%;background:var(--rose);transition:width .3s}
  .cart-empty2{text-align:center;padding:60px 20px;background:#fff;border:1px solid var(--border-2,#E4DFD3);border-radius:18px}
  @media(max-width:820px){.cart-layout{grid-template-columns:1fr}.csum{position:static}}
</style>
CSS;

include __DIR__ . '/inc/head.php';
?>
<div class="wrap cartpg">
  <nav class="crumb"><a href="index">Home</a><span class="sep">›</span><b>Your Bag</b></nav>
  <h1>your bag</h1>
  <div id="cartPage"></div>
</div>

<div id="usp"></div>
<?php
ob_start(); ?>
<script>
(function () {
  var W = WELL, box = document.getElementById('cartPage');
  var FREE = <?= json_encode($freeThreshold) ?>;
  var money = W.money;

  function render() {
    var cart = W.cart(), rows = [], sub = 0;
    cart.forEach(function (l) {
      var p = W.BY_ID[l.id]; if (!p) return;
      var lt = p.price * l.qty; sub += lt;
      var stock = p.stock | 0, low = p.low | 0, atMax = l.qty >= stock;
      var note = atMax ? '<div class="cnote">' + (stock <= low ? 'Only ' + stock + ' left' : 'Max reached') + '</div>'
                       : (stock <= low ? '<div class="cnote">Only ' + stock + ' left</div>' : '');
      rows.push(
        '<div class="crow" data-id="' + p.id + '">' +
          '<img class="gimg" data-grade src="' + p.img + '" alt="">' +
          '<div><div class="br">' + p.brand + '</div><div class="ti">' + p.name + '</div>' +
            '<span class="stepper"><button data-cdec="' + p.id + '">−</button><span class="q">' + l.qty + '</span><button data-cinc="' + p.id + '"' + (atMax ? ' disabled' : '') + '>+</button></span>' + note + '</div>' +
          '<div class="r"><span class="pr">' + money(lt) + '</span><button class="rm" data-crm="' + p.id + '">Remove</button></div>' +
        '</div>'
      );
    });

    if (!rows.length) {
      box.innerHTML = '<div class="cart-empty2"><b style="font-family:var(--fp);font-size:22px">Your bag is empty.</b>' +
        '<p class="muted" style="margin:8px 0 18px">Discover derm-loved essentials to get glowing.</p>' +
        '<a class="btn btn-primary" href="skincare">Start shopping</a></div>';
      return;
    }

    var remain = Math.max(0, FREE - sub), pct = FREE > 0 ? Math.min(100, sub / FREE * 100) : 100;
    var freeMsg = (FREE > 0 && remain > 0)
      ? "You're " + money(remain) + " away from FREE SHIPPING ♡<div class='track'><div class='fill' style='width:" + pct + "%'></div></div>"
      : "You've unlocked FREE SHIPPING ✦";

    box.innerHTML =
      '<div class="cart-layout">' +
        '<div class="cart-list">' + rows.join('') + '</div>' +
        '<aside class="csum">' +
          '<h3>Order summary</h3>' +
          '<div class="free">' + freeMsg + '</div>' +
          '<div class="line"><span>Subtotal</span><b>' + money(sub) + '</b></div>' +
          '<div class="line"><span>Shipping</span><span>Calculated at checkout</span></div>' +
          '<div class="line total"><span>Total</span><span>' + money(sub) + '</span></div>' +
          '<a class="btn btn-primary btn-block" style="margin-top:14px" href="checkout">Proceed to checkout</a>' +
          '<a class="btn btn-ghost btn-block" style="margin-top:8px" href="skincare">Continue shopping</a>' +
        '</aside>' +
      '</div>';
    W.guardImages(box);
  }

  box.addEventListener('click', function (e) {
    var inc = e.target.closest('[data-cinc]'), dec = e.target.closest('[data-cdec]'), rm = e.target.closest('[data-crm]');
    if (inc) { var l = W.cart().find(function (x) { return x.id === inc.dataset.cinc; }); W.setQty(inc.dataset.cinc, (l ? l.qty : 0) + 1); render(); }
    if (dec) { var d = W.cart().find(function (x) { return x.id === dec.dataset.cdec; }); W.setQty(dec.dataset.cdec, (d ? d.qty : 0) - 1); render(); }
    if (rm) { W.removeFromCart(rm.dataset.crm); render(); }
  });

  render();
  document.getElementById('usp').innerHTML = W.uspHTML();
  W.guardImages(document);
})();
</script>
<?php $PAGE_JS = ob_get_clean();
include __DIR__ . '/inc/foot.php';
