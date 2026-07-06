<?php
require __DIR__ . '/inc/functions.php';
$PAGE_TITLE = 'About — ' . setting('store_name', 'WELL SHOP');
$ACTIVE = 'Shop All';
$HEAD_CSS = <<<CSS
<style>
  .ab-hero{background:var(--hero-grad)}
  .ab-hero .wrap{display:grid;grid-template-columns:5fr 7fr;gap:40px;align-items:center;padding-block:48px 56px}
  .ab-hero h1{font-family:var(--fp);font-size:48px;font-weight:700;line-height:1.08;margin:14px 0 18px}
  .ab-hero .img{position:relative;border-radius:28px;overflow:hidden;aspect-ratio:4/3;box-shadow:var(--sh-lg)}
  .ab-hero .img img{width:100%;height:100%;object-fit:cover}
  .ab-hero .gl{position:absolute;bottom:16px;left:16px;background:rgba(255,255,255,.7);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.6);border-radius:14px;padding:12px 16px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px}
  .ab-mission{background:var(--ink);color:#fff;border-radius:28px;padding:48px;text-align:center}
  .ab-mission h2{color:#fff;font-size:32px;max-width:24ch;margin:0 auto}
  .ab-mission .script{color:var(--rose)}
  .statstrip{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
  .statc{text-align:center;padding:24px;background:#fff;border:1px solid var(--border-2);border-radius:18px}
  .statc .n{font-family:var(--fp);font-size:34px;font-weight:700;line-height:1} .statc .l{font-size:13px;color:var(--text-muted);margin-top:6px}
  .values{display:grid;grid-template-columns:repeat(5,1fr);gap:16px}
  .valc{background:#fff;border:1px solid var(--border-2);border-radius:18px;padding:22px;text-align:center}
  .valc .ic{width:48px;height:48px;border-radius:14px;background:var(--blush-tint);color:var(--rose-deep);display:flex;align-items:center;justify-content:center;margin:0 auto 12px}
  .valc h4{font-size:15px;margin:0 0 6px} .valc p{font-size:12.5px;color:var(--text-muted);margin:0}
  .ab-promise{background:var(--mint-tint);border-radius:28px;padding:40px;display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:center}
  .ab-promise .ic{width:64px;height:64px;border-radius:16px;background:#fff;color:var(--mint);display:flex;align-items:center;justify-content:center;margin-bottom:16px}
  .team{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
  .teamc{text-align:center}
  .teamc .ph{aspect-ratio:1;border-radius:18px;overflow:hidden;background:var(--cream-2);margin-bottom:12px}
  .teamc .ph img{width:100%;height:100%;object-fit:cover}
  .teamc b{font-size:14.5px;display:block} .teamc span{font-size:12.5px;color:var(--text-muted)}
  .dark-cta{background:var(--ink);color:#fff;border-radius:28px;padding:48px;text-align:center}
  .dark-cta input{height:52px;border-radius:9999px;border:0;padding:0 20px;width:300px;max-width:70vw;font-family:inherit}
  @media(max-width:900px){ .ab-hero .wrap{grid-template-columns:1fr} .statstrip{grid-template-columns:repeat(2,1fr)} .values{grid-template-columns:repeat(2,1fr)} .ab-promise{grid-template-columns:1fr} .team{grid-template-columns:repeat(2,1fr)} }
</style>
CSS;
include __DIR__ . '/inc/head.php';
?>
<section class="ab-hero"><div class="wrap">
  <div>
    <span class="eyebrow" style="color:var(--rose-deep)">Our Story</span>
    <h1>Where clinical care meets <span class="script">Wellness</span></h1>
    <p class="body-lg" style="color:var(--ink-soft)"><?= e(setting('footer_about','Born in Beirut, we fuse real pharmacist expertise with clean, trend-forward beauty. Real results. Real confidence. Powered by science. Loved by you. ♡')) ?></p>
    <div class="row" style="gap:12px;margin-top:24px"><a class="btn btn-primary" href="skincare">Shop The Well</a><a class="btn btn-ghost" href="quiz">Take the Skin Quiz</a></div>
  </div>
  <div class="img graded" data-imgwrap><img class="gimg" data-grade id="abImg" alt=""><div class="gl" id="abGl"></div></div>
</div></section>

<section class="wrap section-tight"><div class="ab-mission"><h2 class="h2">We believe wellness should be <span class="script">honest</span>, expert-led, and made for real life.</h2></div></section>
<section class="wrap section-tight"><div class="statstrip" id="stats"></div></section>
<section class="wrap section-tight">
  <div class="sec-head" style="justify-content:center;text-align:center;flex-direction:column"><span class="eyebrow">What we stand for</span><h2 class="h2">Our <span class="script">Values</span></h2></div>
  <div class="values" id="values"></div>
</section>
<section class="wrap section-tight"><div class="ab-promise">
  <div><span class="ic" id="promiseIc"></span><span class="eyebrow" style="color:#5c5942">Authenticity Promise</span><h2 class="h2" style="margin:8px 0 12px">100% genuine, every time</h2><p class="muted">Every product is sourced directly from trusted brands and quality-checked by licensed pharmacists. No grey-market, no fakes — just authentic wellness you can trust.</p></div>
  <div class="row" style="flex-wrap:wrap;gap:10px"><span class="chip chip-mint">✓ Sourced direct</span><span class="chip chip-mint">✓ Pharmacist-checked</span><span class="chip chip-mint">✓ Batch-traced</span><span class="chip chip-mint">✓ Sealed &amp; safe</span></div>
</div></section>
<section class="wrap section-tight">
  <div class="sec-head" style="justify-content:center;text-align:center;flex-direction:column"><span class="eyebrow">The people behind the care</span><h2 class="h2">Meet the <span class="script">experts</span></h2></div>
  <div class="team" id="team"></div>
</section>
<section class="wrap section-tight"><div class="dark-cta">
  <h2 class="h2" style="color:#fff;margin-bottom:8px">Join THE WELL COMMUNITY</h2>
  <p style="opacity:.85;margin:0 0 20px">Get 10% off your first order, expert tips &amp; early access to drops.</p>
  <form id="abSub" style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap"><input id="abEmail" type="email" placeholder="Your email"><button class="btn btn-coral" type="submit">Subscribe</button></form>
</div></section>

<div id="usp"></div>
<?php
$csrf = csrf_token();
$PAGE_JS = <<<JS
<script>
  var W = WELL, \$ = s=>document.querySelector(s);
  \$('#abImg').src=W.IMG.pharmacist; \$('#abGl').innerHTML = W.icon('shield')+' Licensed pharmacists, on your side.';
  \$('#promiseIc').innerHTML = W.icon('shield');
  \$('#stats').innerHTML = [['2M+','Happy customers'],['4.8/5 ★','7,000+ reviews'],['100%','Authentic products'],['500+','Trusted brands']].map(([n,l])=>`<div class="statc"><div class="n">\${n}</div><div class="l">\${l}</div></div>`).join('');
  \$('#values').innerHTML = [['shield','Expertise First','Every product vetted by licensed pharmacists.'],['check','Radical Authenticity','100% genuine, sourced direct from brands.'],['heart','Made for You','Personalised, judgment-free wellness.'],['chat','Always Here','Real experts, real answers, anytime.'],['truck','Lebanon-Wide','Fast, reliable delivery + COD everywhere.']].map(([ic,t,p])=>`<div class="valc"><span class="ic">\${W.icon(ic)}</span><h4>\${t}</h4><p>\${p}</p></div>`).join('');
  const team=[[W.IMG.teamWoman,'Dr. Lara Haddad','Skincare Lead, PharmD'],[W.IMG.pharmacist,'Dr. Rami Nassar','Clinical Pharmacist'],[W.AV[0],'Yara Khalil','Wellness Advisor'],[W.AV[3],'Omar Saad','Customer Care Lead']];
  \$('#team').innerHTML = team.map(([img,n,r])=>`<div class="teamc"><div class="ph graded" data-imgwrap><img class="gimg" data-grade src="\${img}" alt=""></div><b>\${n}</b><span>\${r}</span></div>`).join('');
  \$('#abSub').addEventListener('submit',function(e){ e.preventDefault(); var em=\$('#abEmail').value.trim(); if(!/^[^@\\s]+@[^@\\s]+\\.[^@\\s]+\$/.test(em)){\$('#abEmail').focus();return;} var fd=new FormData(); fd.append('email',em); fd.append('csrf','{$csrf}'); fetch('actions/subscribe.php',{method:'POST',body:fd}).finally(function(){ W.toast('Subscribed — welcome to the community ✦',{ok:true}); \$('#abEmail').value=''; }); });
  \$('#usp').innerHTML = W.uspHTML();
  W.guardImages(document);
</script>
JS;
include __DIR__ . '/inc/foot.php';
