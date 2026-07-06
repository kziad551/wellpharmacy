<?php
require __DIR__ . '/inc/functions.php';

$sent = false; $err = '';
if (is_post()) {
    csrf_check();
    $name = trim((string) input('name'));
    $body = trim((string) input('message'));
    if ($name === '' || $body === '') {
        $err = 'Please add your name and a message.';
    } else {
        q("INSERT INTO messages (name,email,phone,topic,order_no,body) VALUES (?,?,?,?,?,?)", [
            $name, trim((string)input('email')), trim((string)input('phone')),
            (string)input('topic'), trim((string)input('order_no')), $body,
        ]);
        $sent = true;
    }
}

$wa      = setting('whatsapp_number', '9613627766');
$phone   = setting('store_phone', '+961 3 627 766');
$email   = setting('store_email', 'care@wellpharmacy.com');
$address = setting('store_address', 'Beirut, Lebanon');
$hoursRows   = array_filter(array_map('trim', explode("\n", setting('opening_hours', "Mon – Sat | 9am – 9pm\nSunday | 11am – 6pm"))));
$hoursStatus = setting('hours_status', 'Open now');

$PAGE_TITLE = 'Contact & Ask an Expert — ' . setting('store_name', 'WELL SHOP');
$ACTIVE = 'Shop All';
$HEAD_CSS = <<<CSS
<style>
  .ct-hero{background:var(--hero-grad)}
  .ct-hero .wrap{display:grid;grid-template-columns:1.2fr .8fr;gap:32px;align-items:center;padding-block:36px 42px}
  .ct-hero h1{font-family:var(--fp);font-size:42px;font-weight:600;margin:12px 0 10px}
  .ct-chips{display:flex;gap:9px;flex-wrap:wrap;margin-top:14px}
  .ask-card{background:rgba(255,255,255,.7);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,.6);border-radius:20px;padding:24px;box-shadow:var(--sh-lg)}
  .ask-card .h{display:flex;align-items:center;gap:12px;margin-bottom:14px}
  .ask-card img{width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid var(--mint-tint)}
  .ask-bubble{background:#fff;border-radius:14px 14px 14px 4px;padding:12px 14px;font-size:13.5px;color:var(--ink-soft);margin-bottom:10px}
  .quick-tiles{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
  .qt{background:#fff;border:1px solid var(--border-2);border-radius:18px;padding:22px;text-align:center;transition:transform .2s,box-shadow .2s}
  .qt:hover{transform:translateY(-4px);box-shadow:var(--sh-md)}
  .qt .ic{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:#fff}
  .qt h4{font-size:15px;margin:0 0 4px} .qt p{font-size:12.5px;color:var(--text-muted);margin:0}
  .ct-layout{display:grid;grid-template-columns:1fr 360px;gap:32px;align-items:start}
  .ct-form{background:#fff;border:1px solid var(--border-2);border-radius:24px;padding:28px}
  .two{display:grid;grid-template-columns:1fr 1fr;gap:14px}
  .ct-side{position:sticky;top:160px;display:flex;flex-direction:column;gap:18px}
  .chat-preview{background:#fff;border:1px solid var(--border-2);border-radius:20px;padding:20px}
  .hours-card{background:var(--cream);border-radius:18px;padding:20px}
  .hours-card .row{display:flex;justify-content:space-between;font-size:13.5px;padding:6px 0}
  .store-map{aspect-ratio:21/9;border-radius:24px;background:linear-gradient(160deg,#e9e7db,#f4f2ec);position:relative;overflow:hidden}
  .store-map svg{position:absolute;inset:0;width:100%;height:100%}
  .store-map .pin{position:absolute;left:42%;top:50%;transform:translate(-50%,-100%);font-size:32px}
  .store-map .ov{position:absolute;bottom:18px;left:18px;background:rgba(255,255,255,.8);backdrop-filter:blur(14px);border-radius:14px;padding:14px 18px}
  .sent-box{background:var(--mint-tint);border:1px solid #cfd3b8;border-radius:16px;padding:22px;display:flex;gap:14px;align-items:flex-start}
  .sent-box .ic{width:40px;height:40px;border-radius:50%;background:var(--mint);color:#fff;display:flex;align-items:center;justify-content:center;flex:none}
  @media(max-width:900px){ .ct-hero .wrap{grid-template-columns:1fr} .quick-tiles{grid-template-columns:repeat(2,1fr)} .ct-layout{grid-template-columns:1fr} .ct-side{position:static} .two{grid-template-columns:1fr} }
</style>
CSS;

include __DIR__ . '/inc/head.php';
?>
<section class="ct-hero"><div class="wrap">
  <div>
    <span class="chip chip-mint">✓ WE'RE HERE FOR YOU</span>
    <h1>Talk to a real <span class="script">expert</span></h1>
    <p class="body-lg" style="color:var(--ink-soft)">Licensed pharmacists, real answers — discreet &amp; private, always.</p>
    <div class="ct-chips"><span class="chip">💬 WhatsApp in ~2 min</span><span class="chip">📞 Call back same day</span><span class="chip">✉️ Email within 24h</span></div>
  </div>
  <div class="ask-card">
    <div class="h"><img class="gimg" data-grade id="askImg"><div><b style="font-size:14px">Ask our pharmacists</b><div class="muted" style="font-size:12px;color:var(--mint);font-weight:600">● Online now</div></div></div>
    <div class="ask-bubble">Hi! 👋 Whether it's a product question or a wellness concern — we're here to help, privately.</div>
    <a class="btn btn-primary btn-block btn-sm" href="#contactForm">Start a conversation</a>
  </div>
</div></section>

<section class="wrap section-tight">
  <div class="quick-tiles">
    <a class="qt" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><span class="ic" style="background:#25D366"><?= svg_icon('whatsapp') ?></span><h4>WhatsApp</h4><p>Chat with us now</p></a>
    <a class="qt" href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><span class="ic" style="background:#7c7e80"><?= svg_icon('phone') ?></span><h4>Call Us</h4><p><?= e($phone) ?></p></a>
    <a class="qt" href="mailto:<?= e($email) ?>"><span class="ic" style="background:#7a6244"><?= svg_icon('mail') ?></span><h4>Email</h4><p><?= e($email) ?></p></a>
    <a class="qt" href="#contactForm"><span class="ic" style="background:#7d7a5e"><?= svg_icon('chat') ?></span><h4>Message</h4><p>Send a message below</p></a>
  </div>
</section>

<section class="wrap section-tight">
  <div class="ct-layout">
    <div>
      <?php if ($sent): ?>
        <div class="sent-box" id="contactForm">
          <span class="ic">✓</span>
          <div><b style="font-family:var(--fp);font-size:18px">Message sent!</b><p class="muted" style="margin:6px 0 0">Thank you — our team will reply within 24 hours. For anything urgent, message us on WhatsApp.</p></div>
        </div>
      <?php else: ?>
      <form class="ct-form" id="contactForm" method="post" action="contact">
        <?= csrf_field() ?>
        <h3 class="h3" style="margin-bottom:18px">Send us a message</h3>
        <?php if ($err): ?><div class="sent-box" style="background:var(--coral);color:#fff;border:0;margin-bottom:16px"><div><?= e($err) ?></div></div><?php endif; ?>
        <div class="two"><div class="field"><label>Full name</label><input class="input" name="name" placeholder="Layla K." required></div><div class="field"><label>Email</label><input class="input" type="email" name="email" placeholder="layla.k@email.com"></div></div>
        <div class="two"><div class="field"><label>Phone</label><input class="input" name="phone" placeholder="+961 …"></div><div class="field"><label>Topic</label><select class="input" name="topic"><option>Product question</option><option>Order &amp; delivery</option><option>Pharmacist advice</option><option>Returns &amp; refunds</option><option>Other</option></select></div></div>
        <div class="field"><label>Order number <span class="muted">(optional)</span></label><input class="input" name="order_no" placeholder="WS-2026-…"></div>
        <div class="field"><label>Message</label><textarea class="input" name="message" placeholder="How can we help?" required></textarea></div>
        <button class="btn btn-primary btn-lg">Send Message</button>
      </form>
      <?php endif; ?>
    </div>
    <aside class="ct-side">
      <div class="chat-preview"><h4 class="h4" style="margin-bottom:12px">WhatsApp</h4><div class="ask-bubble">Most questions answered in under 2 minutes during opening hours.</div><a class="btn btn-outline btn-block btn-sm" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener">Open WhatsApp</a></div>
      <div class="hours-card"><h4 class="h4" style="margin-bottom:6px">Hours <?php if ($hoursStatus): ?><span class="chip chip-mint" style="height:24px;font-size:11px"><?= e($hoursStatus) ?></span><?php endif; ?></h4>
        <?php foreach ($hoursRows as $r): $parts = array_map('trim', explode('|', $r, 2)); ?>
          <div class="row"><span><?= e($parts[0]) ?></span><b><?= e($parts[1] ?? '') ?></b></div>
        <?php endforeach; ?>
      </div>
    </aside>
  </div>
</section>

<section class="wrap section-tight">
  <div class="store-map">
    <svg viewBox="0 0 800 340"><rect width="800" height="340" fill="#e9e7db"/><path d="M0 0 L180 0 Q140 130 60 220 Q30 290 0 340 Z" fill="#e8e7e4"/><g stroke="#fff" stroke-width="8" fill="none" opacity=".9"><path d="M120 0 L200 180 L420 260 L800 240"/><path d="M0 160 L800 140"/><path d="M360 0 L390 340"/><path d="M560 0 L590 340"/></g><g fill="#e4decf" opacity=".5"><rect x="220" y="50" width="110" height="80" rx="8"/><rect x="430" y="60" width="120" height="90" rx="8"/><rect x="250" y="190" width="120" height="90" rx="8"/><rect x="600" y="180" width="110" height="100" rx="8"/></g></svg>
    <div class="pin">📍</div>
    <div class="ov"><b style="font-family:var(--fp);font-size:17px"><?= e($address) ?></b><div class="muted" style="font-size:12.5px;margin:4px 0 10px">Delivery across Lebanon · COD available</div><a class="btn btn-primary btn-sm" href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($address) ?>" target="_blank" rel="noopener">Get Directions</a></div>
  </div>
</section>

<div id="usp"></div>
<?php
$PAGE_JS = "<script>var W=WELL; document.getElementById('askImg').src=W.IMG.teamWoman; document.getElementById('usp').innerHTML=W.uspHTML(); W.guardImages(document);</script>";
include __DIR__ . '/inc/foot.php';
