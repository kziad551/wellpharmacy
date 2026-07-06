<?php
require __DIR__ . '/inc/functions.php';

$PAGE_TITLE = setting('store_name', 'WELL SHOP') . ' — ' . setting('store_tagline', 'where Wellness meets You!');
$ACTIVE = 'Shop All';

/* dynamic homepage data */
$latest   = array_column(rows("SELECT id FROM products WHERE feat_latest=1   AND status='active' ORDER BY home_sort, sort"), 'id');
$wellness = array_column(rows("SELECT id FROM products WHERE feat_wellness=1 AND status='active' ORDER BY home_sort, sort"), 'id');
$fbrands  = rows("SELECT name, color, logo FROM brands WHERE featured=1 ORDER BY sort");
$jposts   = rows("SELECT title, slug, category, image, author, read_min FROM journal_posts WHERE status='published' ORDER BY sort, id LIMIT 3");

$HEAD_CSS = <<<CSS
<style>
  /* ============ HOMEPAGE (rhode concept) — header/menu stays as-is via chrome.js ============ */
  .hero{background:var(--hero-grad); position:relative; overflow:hidden}
  .hero .wrap{display:grid; grid-template-columns:1.05fr .95fr; gap:clamp(24px,4vw,56px); align-items:center; padding-block:clamp(40px,6vw,80px) clamp(34px,5vw,64px)}
  .hero-copy .ey{display:inline-flex; align-items:center; gap:9px}
  .hero-copy .h1{margin:20px 0 0}
  .hero-copy .sub{font-size:clamp(15px,1.3vw,18px); color:var(--ink-soft); max-width:34ch; margin:22px 0 0; line-height:1.5}
  .hero-cta{display:flex; gap:13px; margin-top:32px; flex-wrap:wrap}
  .hero-feats{display:flex; gap:26px; margin-top:40px; flex-wrap:wrap}
  .hero-feats .k{font-family:var(--fp); font-weight:600; font-size:26px; color:var(--ink); line-height:1}
  .hero-feats .l{font-size:12.5px; color:var(--text-muted); margin-top:5px}
  .hero-visual{position:relative; aspect-ratio:1/1.02; border-radius:var(--r-lg); overflow:hidden;
    background:radial-gradient(120% 90% at 50% 6%, #FBFAF6 0%, #EFEDE5 58%, #E4DFD2 100%); border:1px solid var(--border); display:flex; align-items:center; justify-content:center}
  .hero-visual>img{width:78%; height:78%; object-fit:cover; border-radius:18px; position:relative; z-index:1; box-shadow:var(--sh-lg)}
  .hero-tag{position:absolute; z-index:2; background:rgba(255,255,255,.8); backdrop-filter:blur(8px); border:1px solid var(--border); border-radius:14px; padding:11px 15px; box-shadow:var(--sh-md)}
  .hero-tag.t1{top:22px; left:22px} .hero-tag.t2{bottom:24px; right:22px}
  .hero-tag .sm{font-size:11px; color:var(--text-muted)}
  .hero-tag .bg{font-family:var(--fp); font-weight:600; font-size:15px; color:var(--ink); display:flex; align-items:center; gap:6px; text-transform:lowercase}
  .hero-tag .s{color:var(--star); letter-spacing:1px; font-size:13px}
  .hero-dots{display:flex; gap:9px; margin-top:28px}
  .hero-dots button{width:9px; height:9px; border-radius:9999px; border:0; background:rgba(44,38,31,.2); padding:0; cursor:pointer; transition:width .3s,background .3s}
  .hero-dots button.on{background:var(--rose-deep); width:26px}
  .strip{border-block:1px solid var(--border); overflow:hidden; background:var(--cream)}
  .strip-track{display:flex; gap:54px; padding:16px 0; white-space:nowrap; animation:marq 30s linear infinite; font-family:var(--fp); text-transform:lowercase; font-weight:500; font-size:21px; color:var(--ink-soft)}
  .strip-track span{display:inline-flex; align-items:center; gap:54px} .strip-track b{color:var(--rose-deep); font-weight:600}
  @keyframes marq{to{transform:translateX(-50%)}}
  @media(prefers-reduced-motion:reduce){.strip-track{animation:none}}
  .prodgrid{display:grid; grid-template-columns:repeat(5,minmax(0,1fr)); gap:20px}
  .sec-actions{display:flex; align-items:center; gap:10px; flex-shrink:0}
  .cats{display:grid; grid-template-columns:repeat(3,1fr); gap:18px}
  .cat{position:relative; border-radius:var(--r-lg); border:1px solid var(--border); min-height:330px; padding:28px 28px 0; overflow:hidden; transition:transform .3s ease, box-shadow .3s ease; display:block}
  .cat:hover{transform:translateY(-6px); box-shadow:var(--sh-lg)}
  .cat .pill{position:absolute; z-index:3; top:24px; right:24px; background:var(--ink); color:#F1EDE3; font-size:11px; font-weight:600; letter-spacing:.04em; text-transform:lowercase; padding:7px 13px; border-radius:9999px}
  .cat h3{position:relative; z-index:3; font-size:clamp(30px,3.2vw,44px); line-height:.9; color:var(--ink); margin:4px 0 0}
  .cat .meta{position:relative; z-index:3; font-size:13px; color:var(--text-muted); margin-top:12px}
  .cat .pack{position:absolute; z-index:1; bottom:16px; left:50%; transform:translateX(-50%); width:62%; max-height:172px; object-fit:contain; filter:drop-shadow(0 18px 26px rgba(44,38,31,.18)); transition:transform .4s ease}
  .cat:hover .pack{transform:translateX(-50%) translateY(-8px) scale(1.04)}
  .editorial{display:grid; grid-template-columns:1fr 1fr; border-radius:var(--r-lg); overflow:hidden; border:1px solid var(--border); background:var(--cream-2)}
  .editorial .ph{aspect-ratio:1/1; overflow:hidden; background:var(--cream-2)}
  .editorial .ph img{width:100%; height:100%; object-fit:cover}
  .editorial .tx{padding:clamp(28px,4vw,60px); display:flex; flex-direction:column; justify-content:center}
  .editorial .tx p{color:var(--ink-soft); font-size:15px; max-width:40ch; margin:16px 0 0; line-height:1.55}
  /* signature brand wall — borderless, elegant, colorises on hover */
  .brandgrid{display:grid; grid-template-columns:repeat(5,1fr); gap:14px}
  .brandcard{display:flex; align-items:center; justify-content:center; height:126px; padding:24px 22px; border-radius:var(--r-card);
    background:transparent; transition:background .25s ease, transform .25s ease}
  .brandcard:hover{background:var(--cream); transform:translateY(-3px)}
  .brandcard .brand-logo-text{font-family:var(--fp); font-weight:600; font-size:25px; text-align:center; line-height:1.06; letter-spacing:.2px; color:var(--ink-soft); transition:color .25s}
  .brandcard:hover .brand-logo-text{background:var(--grad-well); -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent; color:transparent}
  .brandcard .brand-logo{max-height:62px; max-width:100%; width:auto; object-fit:contain; opacity:.88; transition:opacity .25s, transform .25s}
  .brandcard:hover .brand-logo{opacity:1; transform:scale(1.03)}
  .blogcard{border:1px solid var(--border); border-radius:var(--r-card); overflow:hidden; background:#fff; transition:transform .25s,box-shadow .25s; display:flex; flex-direction:column}
  .blogcard:hover{transform:translateY(-6px); box-shadow:var(--sh-lg)}
  .blogcard .img{aspect-ratio:16/10; overflow:hidden; background:var(--cream-2)}
  .blogcard .img img{width:100%; height:100%; object-fit:cover; transition:transform .4s}
  .blogcard:hover .img img{transform:scale(1.05)}
  .blogcard .b{padding:20px 22px 24px}
  .blogcard .cat-l{font-size:11px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--rose-deep)}
  .blogcard h3{font-size:20px; margin:8px 0 8px; line-height:1.1}
  .blogcard .meta{font-size:12px; color:var(--text-muted)}
  .promise{padding:clamp(56px,8vw,120px) 0; text-align:center; background:var(--cream)}
  .promise .big{font-family:var(--fp); font-weight:600; text-transform:lowercase; font-size:clamp(34px,12vw,180px); line-height:.86; color:var(--ink); letter-spacing:-.025em; overflow-wrap:break-word}
  .promise .big .script{color:var(--rose-deep)}
  .promise .sub{color:var(--ink-soft); max-width:46ch; margin:22px auto 0; font-size:16px}
  @media(max-width:1300px){.prodgrid{grid-template-columns:repeat(4,minmax(0,1fr))} .brandgrid{grid-template-columns:repeat(4,1fr)}}
  @media(max-width:1080px){.prodgrid{grid-template-columns:repeat(3,minmax(0,1fr))} .cats{grid-template-columns:1fr} .brandgrid{grid-template-columns:repeat(3,1fr)}}
  @media(max-width:860px){
    .hero .wrap{grid-template-columns:1fr; padding-block:32px 44px} .hero-visual{order:-1; aspect-ratio:1/.82}
    .editorial{grid-template-columns:1fr}
  }
  @media(max-width:680px){.prodgrid{grid-template-columns:repeat(2,minmax(0,1fr)); gap:13px} .brandgrid{grid-template-columns:repeat(2,1fr)} .sec-actions .cbtn{display:none}}
</style>
CSS;

include __DIR__ . '/inc/head.php';
?>
<!-- HERO -->
<section class="hero">
  <div class="wrap">
    <div class="hero-copy">
      <span class="chip chip-blush ey" data-reveal><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg> <?= e(setting('hero_eyebrow','clinically trusted')) ?></span>
      <h1 class="h1"><?= e(setting('hero_title','next-gen')) ?> <span class="script"><?= e(setting('hero_title_accent','wellness')) ?></span></h1>
      <p class="sub"><?= e(setting('hero_sub','Real results. Real confidence. Powered by science, dispensed with care — your everyday glow, distilled. ♡')) ?></p>
      <div class="hero-cta">
        <a class="btn btn-primary btn-lg" href="skincare">shop bestsellers</a>
        <a class="btn btn-outline btn-lg" href="contact">talk to an expert</a>
      </div>
      <div class="hero-feats">
        <div><div class="k">100%</div><div class="l">authentic products</div></div>
        <div><div class="k">4.8★</div><div class="l">7,000+ reviews</div></div>
        <div><div class="k">24h</div><div class="l">beirut delivery</div></div>
      </div>
      <div class="hero-dots" id="heroDots"><button class="on"></button><button></button><button></button><button></button></div>
    </div>
    <div class="hero-visual graded" data-imgwrap>
      <img class="gimg" data-grade id="heroImg" alt="Editorial beauty">
      <div class="hero-tag t1"><div class="sm">new in</div><div class="bg">glow serum</div></div>
      <div class="hero-tag t2"><div class="sm">loved by 7,000+</div><div class="bg"><span class="s">★★★★★</span></div></div>
    </div>
  </div>
</section>

<!-- MARQUEE -->
<div class="strip"><div class="strip-track">
  <span>effortless glow <b>✦</b> clinically backed <b>✦</b> expert guidance <b>✦</b> fast &amp; reliable <b>✦</b> 100% authentic <b>✦</b></span>
  <span>effortless glow <b>✦</b> clinically backed <b>✦</b> expert guidance <b>✦</b> fast &amp; reliable <b>✦</b> 100% authentic <b>✦</b></span>
</div></div>

<!-- CATEGORY PANELS -->
<section class="section-tight wrap">
  <div class="sec-head">
    <div><span class="eyebrow">shop by ritual</span><h2 class="h2">find your <span class="script">formula</span></h2></div>
    <a class="view-all" href="skincare">all categories</a>
  </div>
  <div class="cats">
    <a class="cat" href="skincare" style="background:linear-gradient(160deg,#F2EFE6,#E7E2D5)">
      <span class="pill">first access</span><h3>glow</h3><div class="meta">serums · vitamin c · exfoliants</div>
      <img class="pack gimg" data-grade src="https://images.unsplash.com/photo-1601049541289-9b1b7bbbfe19?auto=format&fit=crop&w=500&q=80" alt="">
    </a>
    <a class="cat" href="skincare" style="background:linear-gradient(160deg,#EFEBE0,#E4DFCF)">
      <span class="pill">bestseller</span><h3>repair</h3><div class="meta">retinol · peptides · ceramides</div>
      <img class="pack gimg" data-grade src="https://images.unsplash.com/photo-1612817288484-6f916006741a?auto=format&fit=crop&w=500&q=80" alt="">
    </a>
    <a class="cat" href="skincare" style="background:linear-gradient(160deg,#F1EEE4,#E6E1D2)">
      <span class="pill">only at well</span><h3>protect</h3><div class="meta">spf · barrier · antioxidants</div>
      <img class="pack gimg" data-grade src="https://images.unsplash.com/photo-1556228720-195a672e8a03?auto=format&fit=crop&w=500&q=80" alt="">
    </a>
  </div>
</section>

<!-- LATEST ARRIVALS -->
<section class="section-tight wrap" style="padding-top:0">
  <div class="sec-head">
    <div><span class="eyebrow">✦ just dropped</span><h2 class="h2">latest <span class="script">arrivals</span></h2><p class="lead muted" style="margin-top:8px">Small-batch, derm-loved, ready to glow — hover a product to see it in action.</p></div>
    <a class="view-all" href="skincare">view all</a>
  </div>
  <div class="prodgrid" id="latestRail"></div>
</section>

<!-- CATEGORY: WELLNESS -->
<section class="section-tight wrap" style="padding-top:0">
  <div class="sec-head">
    <div><span class="eyebrow">feel good from within</span><h2 class="h2">shop <span class="script">wellness</span></h2></div>
    <a class="view-all" href="skincare">view all</a>
  </div>
  <div class="prodgrid" id="wellnessRail"></div>
</section>

<!-- EDITORIAL -->
<section class="section-tight wrap" style="padding-top:0">
  <div class="editorial">
    <div class="ph graded" data-imgwrap><img class="gimg" data-grade src="https://images.unsplash.com/photo-1596755389378-c31d21fd1273?auto=format&fit=crop&w=900&q=80" alt=""></div>
    <div class="tx">
      <span class="eyebrow">the well difference</span>
      <h2 class="h2" style="margin-top:12px">backed by <span class="script">pharmacists</span></h2>
      <p>Every product on our shelves is vetted by licensed pharmacists — no hype, no filler. Just clean, clinically-backed formulas chosen for results you can see and feel.</p>
      <div class="hero-cta" style="margin-top:26px"><a class="btn btn-outline" href="about">meet the experts</a></div>
    </div>
  </div>
</section>

<!-- BRAND STRIP -->
<section class="section-tight wrap" style="padding-top:0">
  <div class="sec-head" style="justify-content:center; text-align:center; flex-direction:column; gap:4px; align-items:center"><span class="eyebrow">authentic, always</span><h2 class="h2">shop trusted <span class="script">brands</span></h2></div>
  <div class="brandgrid" id="brandGrid"></div>
  <div class="center mt24"><a class="view-all" href="brands">view all brands</a></div>
</section>

<!-- JOURNAL -->
<section class="section-tight wrap" style="padding-top:0">
  <div class="sec-head">
    <div><span class="eyebrow">✦ the well journal</span><h2 class="h2">from the wellness <span class="script">journal</span></h2></div>
    <a class="view-all" href="journal">read more</a>
  </div>
  <div class="grid g3" id="blogGrid"></div>
</section>

<!-- PROMISE -->
<section class="promise"><div class="wrap">
  <span class="eyebrow">where wellness meets you</span>
  <div class="big"><?= e(setting('promise_line1','glow,')) ?><br><span class="script"><?= e(setting('promise_accent','responsibly.')) ?></span></div>
  <p class="sub"><?= e(setting('promise_sub','Beirut-born, science-led skincare & wellness — dispensed with the care of your neighbourhood pharmacy, delivered to your door.')) ?></p>
  <div class="hero-cta" style="justify-content:center; margin-top:28px"><a class="btn btn-primary btn-lg" href="skincare">start shopping</a></div>
</div></section>

<div id="usp"></div>
<?php
$LATEST_JSON   = json_encode($latest, JSON_UNESCAPED_SLASHES);
$WELLNESS_JSON = json_encode($wellness, JSON_UNESCAPED_SLASHES);
$BRANDS_JSON   = json_encode($fbrands, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$BLOGS_JSON    = json_encode($jposts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$PAGE_JS = <<<JS
<script>
  const W = WELL, \$ = (s)=>document.querySelector(s);

  // hero carousel
  const heroImgs = [W.IMG.heroModel, W.IMG.heroSerum, W.IMG.pharmacist, W.IMG.quizFace];
  let hi = 0; \$('#heroImg').src = heroImgs[0]; W.guardImages(\$('.hero-visual'));
  const dots = [...document.querySelectorAll('#heroDots button')];
  function setHero(i){ hi=i; const im=\$('#heroImg'); im.dataset.failed=''; im.style.opacity=0; setTimeout(()=>{im.src=heroImgs[i]; im.style.transition='opacity .4s'; im.style.opacity=1; W.guardImages(\$('.hero-visual'));},180); dots.forEach((d,j)=>d.classList.toggle('on',j===i)); }
  dots.forEach((d,i)=>d.addEventListener('click',()=>setHero(i)));
  if(!matchMedia('(prefers-reduced-motion: reduce)').matches) setInterval(()=>setHero((hi+1)%heroImgs.length), 5000);

  // product rails (from database)
  const pick = ids => ids.map(id=>W.BY_ID[id]).filter(Boolean);
  W.renderProducts(\$('#latestRail'),   pick($LATEST_JSON));
  W.renderProducts(\$('#wellnessRail'), pick($WELLNESS_JSON));

  // trusted brands (from database)
  const brands = $BRANDS_JSON;
  \$('#brandGrid').innerHTML = brands.map(b=>`<a class="brandcard" href="brands" aria-label="\${b.name}">\${b.logo?`<img class="brand-logo" src="\${b.logo}" alt="\${b.name}" loading="lazy">`:`<span class="brand-logo-text">\${b.name}</span>`}</a>`).join('');

  // journal (from database)
  const blogs = $BLOGS_JSON;
  \$('#blogGrid').innerHTML = blogs.map(b=>`<a class="blogcard" href="journal-post?slug=\${encodeURIComponent(b.slug)}"><div class="img graded" data-imgwrap><img class="gimg" data-grade src="\${b.image}" alt=""></div><div class="b"><span class="cat-l">\${b.category}</span><h3>\${b.title}</h3><span class="meta">By \${b.author} · \${b.read_min} min read</span></div></a>`).join('');

  document.getElementById('usp').innerHTML = W.uspHTML();
  W.guardImages(document);
</script>
JS;
include __DIR__ . '/inc/foot.php';
