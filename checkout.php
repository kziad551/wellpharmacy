<?php
require __DIR__ . '/inc/functions.php';
require __DIR__ . '/inc/customer.php';   // login stays OPTIONAL — this only prefills
require __DIR__ . '/inc/phone.php';

/* Signed in? Pre-fill from the saved profile so checkout is a formality.
   Everything stays editable — this order might be going somewhere else. */
$me = current_customer();
$pf = [
    'name' => $me ? trim($me['first_name'] . ' ' . $me['last_name']) : '',
    'phone' => $me['phone'] ?? '',
    'email' => $me['email'] ?? '',
    'address' => $me['address'] ?? '',
    'governorate' => $me['governorate'] ?? '',
    'city' => $me['city'] ?? '',
];

$PAGE_TITLE = 'Checkout — ' . setting('store_name', 'WELL SHOP');
$ACTIVE = 'Shop All';
$NO_POPUP = true;

$govs          = lebanon_governorates();
$codOn         = setting('cod_enabled', '1') === '1';
$areebaOn      = setting('areeba_enabled', '0') === '1';
$freeThreshold = (float) setting('free_ship_threshold', '49');
$feeBeirut     = (float) setting('ship_fee_beirut', '3');
$feeOutside    = (float) setting('ship_fee_outside', '5');
$wa            = setting('whatsapp_number', '9613627766');
$deliveryBeirut  = setting('delivery_beirut_text', 'Beirut — same-day delivery');
$deliveryOutside = setting('delivery_outside_text', 'Outside Beirut — 2-day delivery');

$HEAD_CSS = <<<CSS
<style>
  .copg{max-width:1100px;margin-inline:auto;padding-block:34px 60px}
  .copg h1{font-family:var(--fp);font-size:clamp(28px,3.4vw,40px);font-weight:600;text-transform:lowercase;margin:0 0 20px}
  .co-layout{display:grid;grid-template-columns:1fr 380px;gap:28px;align-items:start}
  .co-card{background:#fff;border:1px solid var(--border-2,#E4DFD3);border-radius:18px;padding:24px;margin-bottom:18px}
  .co-card h3{font-family:var(--fp);font-size:19px;margin:0 0 16px}
  .co-two{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .pay-opt{display:flex;gap:12px;align-items:flex-start;border:1px solid var(--border-2,#E4DFD3);border-radius:12px;padding:14px;cursor:pointer;margin-bottom:10px}
  .pay-opt.on{border-color:var(--rose);box-shadow:0 0 0 3px rgba(156,129,88,.15)}
  .pay-opt input{margin-top:3px}
  .pay-opt b{font-size:14.5px} .pay-opt p{margin:2px 0 0;font-size:12.5px;color:var(--text-muted)}
  .co-sum{background:#fff;border:1px solid var(--border-2,#E4DFD3);border-radius:18px;padding:22px;position:sticky;top:150px}
  .co-sum h3{font-family:var(--fp);font-size:19px;margin:0 0 14px}
  .co-items{display:flex;flex-direction:column;gap:12px;margin-bottom:14px;max-height:280px;overflow:auto}
  .co-it{display:grid;grid-template-columns:52px 1fr auto;gap:10px;align-items:center;font-size:13px}
  .co-it img{width:52px;height:52px;object-fit:cover;border-radius:9px;background:var(--cream-2)}
  .co-it .q{color:var(--text-muted)}
  .co-line{display:flex;justify-content:space-between;font-size:14px;padding:6px 0;color:var(--ink-soft)}
  .co-line.total{border-top:1px solid var(--border-2,#E4DFD3);margin-top:6px;padding-top:12px;font-size:19px;font-weight:700;color:var(--ink)}
  .co-line.disc b{color:var(--coral-deep,#7E5730)}
  .co-coupon{display:flex;gap:8px;margin:6px 0 14px}
  .co-coupon input{flex:1}
  .co-msg{font-size:12.5px;margin-top:6px}
  .co-msg.ok{color:#4a7a3a} .co-msg.err{color:var(--coral-deep,#b04a2f)}
  .co-empty{text-align:center;padding:50px 20px}
  /* in-site confirm — replaces the browser's "localhost says" alert */
  .ask{position:fixed;inset:0;z-index:130;display:none;align-items:center;justify-content:center;padding:20px}
  .ask.open{display:flex}
  .ask-bd{position:absolute;inset:0;background:rgba(44,38,31,.55);backdrop-filter:blur(3px)}
  .ask-card{position:relative;background:#fff;border-radius:20px;padding:28px;max-width:440px;width:100%;
    box-shadow:0 30px 70px rgba(44,38,31,.35);animation:askUp .28s cubic-bezier(.2,.8,.2,1)}
  @keyframes askUp{from{opacity:0;transform:translateY(14px) scale(.98)}to{opacity:1;transform:none}}
  .ask-card h4{font-family:var(--fp);font-size:22px;font-weight:600;text-transform:lowercase;margin:0 0 8px}
  .ask-card p{font-size:14px;line-height:1.6;color:var(--ink-soft);margin:0 0 20px}
  .ask-card .code{font-weight:700;color:var(--coral-deep)}
  .ask-btns{display:flex;gap:10px;flex-wrap:wrap}
  .ask-btns .btn{flex:1;min-width:150px}
  @media(max-width:820px){.co-layout{grid-template-columns:1fr}.co-sum{position:static}.co-two{grid-template-columns:1fr}}
</style>
CSS;

include __DIR__ . '/inc/head.php';
?>
<div class="wrap copg">
  <nav class="crumb"><a href="index">Home</a><span class="sep">›</span><a href="cart">Bag</a><span class="sep">›</span><b>Checkout</b></nav>
  <h1>checkout</h1>

  <!-- in-site confirm dialog (coupon typed but not applied) -->
  <div class="ask" id="coAsk" role="dialog" aria-modal="true" aria-labelledby="coAskT">
    <div class="ask-bd" data-ask-no></div>
    <div class="ask-card">
      <h4 id="coAskT">your coupon isn't applied</h4>
      <p>You typed <span class="code" id="coAskCode"></span> but didn't press <b>Apply</b>, so it won't be discounted.</p>
      <div class="ask-btns">
        <button type="button" class="btn btn-outline" data-ask-no>back &amp; apply it</button>
        <button type="button" class="btn btn-primary" data-ask-yes>order without it</button>
      </div>
    </div>
  </div>

  <div id="coEmpty" class="co-card co-empty" style="display:none">
    <b style="font-family:var(--fp);font-size:22px">Your bag is empty.</b>
    <p class="muted" style="margin:8px 0 18px">Add something you love before checking out.</p>
    <a class="btn btn-primary" href="skincare">Start shopping</a>
  </div>

  <form id="coForm" class="co-layout" style="display:none" autocomplete="on">
    <div>
      <div class="co-card">
        <h3>Delivery details</h3>
        <div class="co-two">
          <div class="field"><label>Full name *</label><input class="input" name="name" value="<?= e($pf['name']) ?>" required></div>
          <?= phone_field('phone', $pf['phone'], true) ?>
        </div>
        <div class="field"><label>Email <span class="muted">(optional — for the receipt)</span></label><input class="input" type="email" name="email" value="<?= e($pf['email']) ?>"></div>
        <div class="field"><label>Address *</label><input class="input" name="address" value="<?= e($pf['address']) ?>" placeholder="Street, building, floor" required></div>
        <div class="co-two">
          <div class="field"><label>Governorate *</label>
            <select class="input" name="governorate" id="coGov" required>
              <option value="">Select area…</option>
              <?php foreach ($govs as $g): ?><option value="<?= e($g) ?>" <?= $pf['governorate'] === $g ? 'selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label>City / town</label><input class="input" name="city" value="<?= e($pf['city']) ?>"></div>
        </div>
        <div class="field"><label>Order notes <span class="muted">(optional)</span></label><textarea class="input" name="notes" rows="2" placeholder="Delivery instructions, landmark…"></textarea></div>
        <p class="muted" style="font-size:12.5px;margin:6px 0 0">🚚 <?= e($deliveryBeirut) ?> &nbsp;·&nbsp; <?= e($deliveryOutside) ?></p>
      </div>

      <div class="co-card">
        <h3>Payment</h3>
        <?php if ($codOn): ?>
        <label class="pay-opt on"><input type="radio" name="payment_method" value="cod" checked>
          <span><b>Cash on Delivery</b><p>Pay in cash when your order arrives — available across Lebanon.</p></span>
        </label>
        <?php endif; ?>
        <?php if ($areebaOn): ?>
        <label class="pay-opt <?= $codOn ? '' : 'on' ?>"><input type="radio" name="payment_method" value="areeba" <?= $codOn ? '' : 'checked' ?>>
          <span><b>Card payment</b><p>Secure card payment. We'll contact you to complete it.</p></span>
        </label>
        <?php endif; ?>
        <?php if (!$codOn && !$areebaOn): ?><p class="muted">No payment methods are enabled. Please contact us to order.</p><?php endif; ?>
      </div>
    </div>

    <aside class="co-sum">
      <h3>Your order</h3>
      <div class="co-items" id="coItems"></div>

      <div class="co-coupon">
        <input class="input" id="coCode" placeholder="Coupon code" style="text-transform:uppercase">
        <button type="button" class="btn btn-outline" id="coApply">Apply</button>
      </div>
      <div class="co-msg" id="coCouponMsg"></div>

      <div class="co-line"><span>Subtotal</span><b id="coSub">$0.00</b></div>
      <div class="co-line disc" id="coDiscRow" style="display:none"><span>Discount <span id="coCodeLbl" class="muted"></span></span><b id="coDisc">-$0.00</b></div>
      <div class="co-line"><span>Shipping</span><b id="coShip">—</b></div>
      <div class="co-line total"><span>Total</span><span id="coTotal">$0.00</span></div>

      <button type="submit" class="btn btn-primary btn-block" id="coPlace" style="margin-top:14px">Place order</button>
      <div class="co-msg err" id="coErr" style="margin-top:10px"></div>
      <p class="muted" style="font-size:12px;margin:12px 0 0">By placing your order you agree to our <a href="returns-refunds">returns policy</a>. Need help? <a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp us</a>.</p>
    </aside>
  </form>
</div>

<?php
ob_start(); ?>
<script>
(function () {
  var W = WELL, money = W.money;
  var CFG = {
    csrf: <?= json_encode(csrf_token()) ?>,
    freeThreshold: <?= json_encode($freeThreshold) ?>,
    feeBeirut: <?= json_encode($feeBeirut) ?>,
    feeOutside: <?= json_encode($feeOutside) ?>
  };
  var form = document.getElementById('coForm'), empty = document.getElementById('coEmpty');
  var applied = null;   // {code, discount, freeship}
  var skipCouponWarn = false;   // set once the shopper knowingly places an order without applying a typed code

  /* in-site confirm — one callback, answered by the dialog buttons / Esc / backdrop */
  function askCoupon(code, done) {
    var box = document.getElementById('coAsk');
    document.getElementById('coAskCode').textContent = '"' + code + '"';
    box.classList.add('open');
    function close(answer) {
      box.classList.remove('open');
      box.removeEventListener('click', onClick);
      document.removeEventListener('keydown', onKey);
      done(answer);
    }
    function onClick(e) {
      if (e.target.closest('[data-ask-yes]')) close(true);
      else if (e.target.closest('[data-ask-no]')) close(false);
    }
    function onKey(e) { if (e.key === 'Escape') close(false); }
    box.addEventListener('click', onClick);
    document.addEventListener('keydown', onKey);
    var y = box.querySelector('[data-ask-yes]'); y && y.focus();
  }

  function items() { return W.cart().map(function (l) { var p = W.BY_ID[l.id]; return p ? { p: p, qty: l.qty } : null; }).filter(Boolean); }
  function subtotal() { return items().reduce(function (s, x) { return s + x.p.price * x.qty; }, 0); }

  function shipping(sub) {
    if (applied && applied.freeship) return 0;
    if (CFG.freeThreshold > 0 && sub >= CFG.freeThreshold) return 0;
    var gov = document.getElementById('coGov').value;
    if (!gov) return null;                       // not chosen yet
    return gov === 'Beirut' ? CFG.feeBeirut : CFG.feeOutside;
  }

  function render() {
    var its = items();
    if (!its.length) { form.style.display = 'none'; empty.style.display = ''; return; }
    form.style.display = ''; empty.style.display = 'none';

    document.getElementById('coItems').innerHTML = its.map(function (x) {
      return '<div class="co-it"><img class="gimg" data-grade src="' + x.p.img + '" alt="">' +
        '<div>' + x.p.name + '<div class="q">Qty ' + x.qty + '</div></div>' +
        '<b>' + money(x.p.price * x.qty) + '</b></div>';
    }).join('');

    var sub = subtotal();
    var disc = applied ? Math.min(applied.discount, sub) : 0;
    var ship = shipping(sub);

    document.getElementById('coSub').textContent = money(sub);
    var dr = document.getElementById('coDiscRow');
    if (disc > 0) { dr.style.display = ''; document.getElementById('coDisc').textContent = '-' + money(disc); document.getElementById('coCodeLbl').textContent = applied ? '(' + applied.code + ')' : ''; }
    else dr.style.display = 'none';
    document.getElementById('coShip').textContent = ship === null ? 'Select area' : (ship === 0 ? 'FREE' : money(ship));
    document.getElementById('coTotal').textContent = money(Math.max(0, sub - disc) + (ship || 0));
    W.guardImages(document.getElementById('coItems'));
  }

  // coupon
  document.getElementById('coApply').addEventListener('click', function () {
    var code = document.getElementById('coCode').value.trim(), msg = document.getElementById('coCouponMsg');
    if (!code) { applied = null; msg.textContent = ''; render(); return; }
    var fd = new FormData(); fd.append('code', code); fd.append('subtotal', subtotal()); fd.append('csrf', CFG.csrf);
    fetch('actions/coupon.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (res) {
      if (res.ok) { applied = { code: res.code, discount: res.discount, freeship: res.freeship }; msg.className = 'co-msg ok'; msg.textContent = 'Coupon "' + res.code + '" applied ✓'; }
      else { applied = null; msg.className = 'co-msg err'; msg.textContent = res.err || 'Invalid code.'; }
      render();
    }).catch(function () { msg.className = 'co-msg err'; msg.textContent = 'Could not check the code.'; });
  });

  document.getElementById('coGov').addEventListener('change', render);

  // place order
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('coPlace'), err = document.getElementById('coErr');
    err.textContent = '';
    var f = form;
    /* the phone is two controls (dial + national) but travels as one E.164 string */
    function fullPhone() {
      var d = String(f.phone_dial.value || '').replace(/\D/g, '');
      var n = String(f.phone.value || '').replace(/\D/g, '').replace(/^0+/, '');
      return (d && n) ? '+' + d + n : '';
    }

    /* Required-field gate. Nothing below this runs unless it passes — so a failed
       check means NO order row, NO stock change and NO emails. */
    var required = [
      ['name',        'Please enter your full name.'],
      ['phone',       'Please enter a phone number so we can confirm delivery.'],
      ['address',     'Please enter your delivery address.'],
      ['governorate', 'Please choose your area.']
    ];
    form.querySelectorAll('.input, .select').forEach(function (el) { el.classList.remove('err'); });
    var bad = null;
    required.forEach(function (r) {
      var el = f[r[0]];
      if (el && !String(el.value).trim()) { el.classList.add('err'); if (!bad) bad = [el, r[1]]; }
    });
    if (bad) {
      err.textContent = bad[1];
      W.toast(bad[1]);
      bad[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
      try { bad[0].focus({ preventScroll: true }); } catch (e) { bad[0].focus(); }
      return;
    }
    /* Coupon typed but never applied → don't silently ignore it and charge full price.
       Asked via an in-site dialog (never the browser's alert). Answering "order without
       it" is remembered, so we don't nag twice. */
    var codeEl = document.getElementById('coCode');
    var typed = codeEl ? codeEl.value.trim() : '';
    if (typed && (!applied || applied.code.toUpperCase() !== typed.toUpperCase()) && !skipCouponWarn) {
      err.textContent = '';
      askCoupon(typed, function (goAhead) {
        if (!goAhead) {
          codeEl.classList.add('err');
          codeEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
          W.toast('Press Apply to use your coupon');
          return;
        }
        skipCouponWarn = true;                 // they knowingly chose to continue
        form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', {cancelable:true}));
      });
      return;   // wait for their answer
    }

    var pm = form.querySelector('input[name="payment_method"]:checked');
    var payload = {
      csrf: CFG.csrf,
      items: W.cart().map(function (l) { return { id: l.id, qty: l.qty }; }),
      customer: { name: f.name.value, phone: fullPhone(), email: f.email.value, address: f.address.value, governorate: f.governorate.value, city: f.city.value, notes: f.notes.value },
      payment_method: pm ? pm.value : 'cod',
      coupon_code: applied ? applied.code : ''
    };
    btn.disabled = true; btn.textContent = 'Placing your order…';
    fetch('actions/place-order.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.ok) { W.clearCart(); location.href = 'order-received'; }
        else { err.textContent = res.err || 'Something went wrong.'; btn.disabled = false; btn.textContent = 'Place order'; }
      })
      .catch(function () { err.textContent = 'Network error — please try again.'; btn.disabled = false; btn.textContent = 'Place order'; });
  });

  render();
})();
</script>
<?php $PAGE_JS = ob_get_clean();
include __DIR__ . '/inc/foot.php';
