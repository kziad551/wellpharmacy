<?php
require __DIR__ . '/inc/functions.php';

$PAGE_TITLE = setting('store_name', 'WELL SHOP') . ' — ' . setting('store_tagline', 'where Wellness meets You!');
$ACTIVE = 'Shop All';

/* dynamic homepage data */
$fbrands  = rows("SELECT name, color, logo, logo_mode FROM brands WHERE featured=1 ORDER BY sort");
$jposts   = rows("SELECT title, slug, category, image, author, read_min FROM journal_posts WHERE status='published' ORDER BY sort, id LIMIT 4");

/* "as seen on social" — Instagram / TikTok videos, managed in admin → Social Videos */
$SOCIAL_ON    = setting('social_sec_enabled', '1') === '1';
$SOCIAL_POSTS = $SOCIAL_ON ? social_posts(12) : [];
$SOCIAL_IG    = setting('social_instagram', '');
$SOCIAL_TT    = setting('social_tiktok', '');

/* admin-managed homepage product sections (New Arrivals + per-brand rails) */
function sec_title_html(string $t): string {
    $parts = preg_split('/\s+/', trim($t));
    if (count($parts) <= 1) return '<span class="script">' . e($t) . '</span>';
    $last = array_pop($parts);
    return e(implode(' ', $parts)) . ' <span class="script">' . e($last) . '</span>';
}
$CAT_GRADS = ['linear-gradient(160deg,#F2EFE6,#E7E2D5)','linear-gradient(160deg,#EFEBE0,#E4DFCF)','linear-gradient(160deg,#F1EEE4,#E6E1D2)','linear-gradient(160deg,#EEEADF,#E2DDCC)','linear-gradient(160deg,#F0ECE2,#E5E0D0)'];
$SECTIONS = [];
foreach (rows("SELECT * FROM home_sections WHERE enabled=1 ORDER BY sort, id") as $hs) {
    $n = (int) $hs['item_count'];
    $ids = null; $panels = null;
    if ($hs['type'] === 'category') {
        $cats = rows("SELECT name, image FROM categories ORDER BY sort" . ($n > 0 ? " LIMIT $n" : ""));
        if (!$cats) continue;
        $panels = [];
        foreach ($cats as $k => $c) {
            $panels[] = [
                'name'  => $c['name'],
                'image' => $c['image'],
                'count' => (int) val("SELECT COUNT(*) FROM products WHERE category=? AND status='active'", [$c['name']]),
                'grad'  => $CAT_GRADS[$k % count($CAT_GRADS)],
            ];
        }
        $default = 'Shop by Category'; $viewAll = 'skincare';
    } elseif ($hs['type'] === 'new_arrivals') {
        $sql = "SELECT id FROM products WHERE feat_latest=1 AND status='active' ORDER BY home_sort, sort" . ($n > 0 ? " LIMIT $n" : "");
        $ids = array_column(rows($sql), 'id');
        if (!$ids) continue;
        $default = 'New Arrivals'; $viewAll = 'skincare';
    } elseif ($hs['type'] === 'mixed') {
        /* a shuffled mix of products across the chosen brands (blank = all brands).
           RAND(seed) is seeded by the day so the mix is stable within a day, fresh daily. */
        $seed = (int) date('Ymd');
        $brandList = array_values(array_filter(array_map('trim', explode(',', (string) ($hs['brands'] ?? '')))));
        $where = "status='active' AND price > 0";
        $args  = [];
        if ($brandList) {
            $where .= ' AND brand IN (' . implode(',', array_fill(0, count($brandList), '?')) . ')';
            $args   = $brandList;
        }
        $sql = "SELECT id FROM products WHERE $where ORDER BY RAND($seed)" . ($n > 0 ? " LIMIT $n" : " LIMIT 10");
        $ids = array_column(rows($sql, $args), 'id');
        if (!$ids) continue;
        $default = 'Featured'; $viewAll = $brandList && count($brandList) === 1 ? 'skincare?brand=' . urlencode($brandList[0]) : 'skincare';
    } else {
        $sql = "SELECT id FROM products WHERE brand=? AND status='active' ORDER BY sort, id" . ($n > 0 ? " LIMIT $n" : "");
        $ids = array_column(rows($sql, [$hs['brand']]), 'id');
        if (!$ids) continue;
        $default = $hs['brand']; $viewAll = 'skincare?brand=' . urlencode($hs['brand']);
    }
    $title = $hs['title'] !== '' ? $hs['title'] : $default;
    $SECTIONS[] = [
        'kind'     => $hs['type'] === 'category' ? 'category' : 'products',
        'eyebrow'  => $hs['eyebrow'],
        'title'    => $hs['show_title'] ? $title : '',
        'subtitle' => $hs['subtitle'],
        'cols'     => (int) $hs['cols'],
        'view_all' => $viewAll,
        'ids'      => $ids,
        'panels'   => $panels,
    ];
}

$HEAD_CSS = <<<CSS
<style>
  /* ============ HOMEPAGE (rhode concept) — header/menu stays as-is via chrome.js ============ */
  .hero{background:var(--hero-grad); position:relative; overflow:hidden}
  /* hero stays contained (like the live site) even though the rest of the page is full-width — bg spans full, content re-centers */
  .hero .wrap{max-width:var(--maxw-narrow); display:grid; grid-template-columns:1.05fr .95fr; gap:clamp(24px,4vw,56px); align-items:center; padding-block:clamp(32px,4.5vw,64px) clamp(28px,4vw,52px)}
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
  .prodgrid.c4{grid-template-columns:repeat(4,minmax(0,1fr))}   /* New Arrivals: 4-up on wide screens */
  /* product rails — spacing knobs (adjust the px values):
     .home-rail padding-top        = gap ABOVE the section (separates it from the section above)
     .home-rail .sec-head .lead margin-bottom = gap BELOW the subtitle, before the products
     padding-bottom stays 0 — the next section supplies its own top gap */
  .home-rail{padding-top:50px; padding-bottom:0}
  .home-rail .sec-head{margin-bottom:0}
  .home-rail .sec-head .lead{margin-bottom:6px}
  /* compact (section spills past one row): identical to single-row cards — square uncropped image, range name at the TOP — just tighter text rows */
  .prodgrid.compact{gap:16px 18px}
  .prodgrid.compact .pcard .body{padding-top:8px; gap:5px}
  .prodgrid.compact .pcard .price{font-size:20px}
  .prodgrid.compact .pcard .desc{min-height:0; display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; overflow:hidden}
  .sec-actions{display:flex; align-items:center; gap:10px; flex-shrink:0}
  .cats{display:grid; grid-template-columns:repeat(4,1fr); gap:18px}
  .cats.cc3{grid-template-columns:repeat(3,1fr)}
  .cats.cc5{grid-template-columns:repeat(5,1fr)}
  .cat{position:relative; border-radius:var(--r-lg); border:1px solid var(--border); min-height:330px; padding:28px 28px 0; overflow:hidden; transition:transform .3s ease, box-shadow .3s ease; display:block}
  .cat:hover{transform:translateY(-6px); box-shadow:var(--sh-lg)}
  .cat .pill{position:absolute; z-index:3; top:24px; right:24px; background:var(--ink); color:#F1EDE3; font-size:11px; font-weight:600; letter-spacing:.04em; text-transform:lowercase; padding:7px 13px; border-radius:9999px}
  .cat h3{position:relative; z-index:3; font-size:clamp(30px,3.2vw,44px); line-height:.9; color:var(--ink); margin:4px 0 0}
  .cat .meta{position:relative; z-index:3; font-size:13px; color:var(--text-muted); margin-top:12px}
  .cat .pack{position:absolute; z-index:1; bottom:16px; left:50%; transform:translateX(-50%); width:62%; max-height:172px; object-fit:contain; filter:drop-shadow(0 18px 26px rgba(44,38,31,.18)); transition:transform .4s ease}
  .cat:hover .pack{transform:translateX(-50%) translateY(-8px) scale(1.04)}
  .editorial{max-width:1440px; margin-inline:auto; display:grid; grid-template-columns:1fr 1fr; border-radius:var(--r-lg); overflow:hidden; border:1px solid var(--border); background:var(--cream-2)}
  .editorial .ph{aspect-ratio:1/1; overflow:hidden; background:var(--cream-2)}
  .editorial .ph img{width:100%; height:100%; object-fit:cover}
  .editorial .tx{padding:clamp(28px,4vw,60px); display:flex; flex-direction:column; justify-content:center}
  .editorial .tx p{color:var(--ink-soft); font-size:15px; max-width:40ch; margin:16px 0 0; line-height:1.55}
  /* trusted brands — bordered signature cards (as it was) */
  .brandgrid{display:grid; grid-template-columns:repeat(5,1fr); gap:18px}
  .brandcard{display:flex; align-items:center; justify-content:center; height:108px; padding:22px 24px;
    background:#fff; border:1px solid var(--border); border-radius:var(--r-card); box-shadow:var(--sh-xs); transition:transform .25s,box-shadow .25s,border-color .25s}
  .brandcard:hover{transform:translateY(-5px); box-shadow:var(--sh-rose); border-color:var(--rose)}
  .brandcard .brand-logo-text{font-family:var(--fp); font-weight:700; font-size:23px; text-align:center; line-height:1.1; letter-spacing:.2px; color:var(--ink); transition:color .25s}
  .brandcard:hover .brand-logo-text{color:var(--rose-deep)}
  .brandcard .brand-logo{max-height:60px; max-width:100%; width:auto; object-fit:contain}
  .brandcard.both{flex-direction:column; gap:9px}
  .brandcard.both .brand-logo{max-height:44px}
  .brandcard.both .brand-logo-text{font-size:15px}
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
  @media(max-width:1080px){.prodgrid,.prodgrid.c4{grid-template-columns:repeat(3,minmax(0,1fr))} .cats,.cats.cc3,.cats.cc5{grid-template-columns:repeat(2,1fr)} .brandgrid{grid-template-columns:repeat(3,1fr)}}
  @media(max-width:860px){
    .hero .wrap{grid-template-columns:1fr; padding-block:32px 44px} .hero-visual{order:-1; aspect-ratio:1/.82}
    .editorial{grid-template-columns:1fr}
  }
  @media(max-width:680px){.prodgrid,.prodgrid.c4{grid-template-columns:repeat(2,minmax(0,1fr)); gap:13px} .brandgrid{grid-template-columns:repeat(2,1fr)} .cats,.cats.cc3,.cats.cc5{grid-template-columns:1fr} #blogGrid{grid-template-columns:1fr} .sec-actions .cbtn{display:none}}

  /* ---------- "as seen on social" video rail ---------- */
  .socgrid{display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:14px}
  .soccard{position:relative; display:block; border-radius:16px; overflow:hidden; aspect-ratio:9/16;
    background:var(--cream-2); border:1px solid var(--border); cursor:pointer;
    transition:transform .28s cubic-bezier(.2,.8,.2,1), box-shadow .28s}
  .soccard:hover{transform:translateY(-5px); box-shadow:var(--sh-lg)}
  .soccard img{width:100%; height:100%; object-fit:cover; display:block}
  .soccard .shade{position:absolute; inset:0; background:linear-gradient(transparent 46%, rgba(28,24,20,.72))}
  .soccard .plat{position:absolute; top:9px; right:9px; width:26px; height:26px; border-radius:50%;
    background:rgba(255,255,255,.92); color:var(--ink); display:flex; align-items:center; justify-content:center; box-shadow:0 2px 7px rgba(0,0,0,.2)}
  .soccard .plat svg{width:15px; height:15px}
  .soccard .play{position:absolute; inset:0; margin:auto; width:46px; height:46px; border-radius:50%;
    background:rgba(255,255,255,.9); display:flex; align-items:center; justify-content:center;
    box-shadow:0 4px 16px rgba(0,0,0,.26); transition:transform .25s, background .25s}
  .soccard:hover .play{transform:scale(1.12); background:#fff}
  .soccard .play::after{content:""; border-style:solid; border-width:8px 0 8px 13px; border-color:transparent transparent transparent var(--ink); margin-left:3px}
  .soccard .cap{position:absolute; left:0; right:0; bottom:0; padding:10px 11px; color:#fff; font-size:12px; line-height:1.35;
    display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden}
  .soc-follow{display:flex; gap:10px; justify-content:center; margin-top:26px; flex-wrap:wrap}
  .soc-empty{text-align:center; color:var(--text-muted); font-size:14px; padding:26px 0}
  /* lightbox */
  .soc-lb{position:fixed; inset:0; z-index:130; display:none; align-items:center; justify-content:center; padding:24px}
  .soc-lb.open{display:flex}
  .soc-lb-back{position:absolute; inset:0; background:rgba(28,24,20,.78); backdrop-filter:blur(4px)}
  .soc-lb-card{position:relative; width:min(400px,94vw); background:#000; border-radius:18px; overflow:hidden;
    box-shadow:0 40px 90px rgba(0,0,0,.5); animation:socUp .3s cubic-bezier(.2,.8,.2,1)}
  .soc-lb-card iframe{display:block; width:100%; height:min(710px,82vh); border:0; background:#000}
  .soc-lb-x{position:absolute; top:-46px; right:0; width:36px; height:36px; border-radius:50%; border:0;
    background:rgba(255,255,255,.92); color:var(--ink); font-size:22px; line-height:1; cursor:pointer}
  .soc-lb-out{display:block; text-align:center; padding:11px; background:#fff; font-size:13px; font-weight:600; color:var(--ink)}
  @keyframes socUp{from{opacity:0; transform:translateY(18px) scale(.97)} to{opacity:1; transform:none}}
  @media(max-width:1080px){.socgrid{grid-template-columns:repeat(4,minmax(0,1fr))}}
  @media(max-width:680px){.socgrid{grid-template-columns:repeat(2,minmax(0,1fr)); gap:11px} .soc-lb-x{top:auto; bottom:-46px}}
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

<!-- DYNAMIC HOME SECTIONS (admin-managed: New Arrivals + per-brand rails) -->
<?php foreach ($SECTIONS as $i => $sec): ?>
<section class="section-tight wrap home-rail">
  <div class="sec-head">
    <div>
      <?php if ($sec['eyebrow'] !== ''): ?><span class="eyebrow"><?= e($sec['eyebrow']) ?></span><?php endif; ?>
      <?php if ($sec['title'] !== ''): ?><h2 class="h2"><?= sec_title_html($sec['title']) ?></h2><?php endif; ?>
      <?php if ($sec['subtitle'] !== ''): ?><p class="lead muted" style="margin-top:8px"><?= e($sec['subtitle']) ?></p><?php endif; ?>
    </div>
    <a class="view-all" href="<?= e($sec['view_all']) ?>">view all</a>
  </div>
  <?php if ($sec['kind'] === 'category'): ?>
    <div class="cats<?= $sec['cols']===3?' cc3':($sec['cols']===5?' cc5':'') ?>">
      <?php foreach ($sec['panels'] as $p): ?>
        <a class="cat" href="skincare?cat=<?= urlencode($p['name']) ?>" style="background:<?= $p['grad'] ?>">
          <?php if ($p['count'] > 0): ?><span class="pill"><?= $p['count'] ?> item<?= $p['count']===1?'':'s' ?></span><?php endif; ?>
          <h3><?= e($p['name']) ?></h3>
          <?php if ($p['image'] !== ''): ?><img class="pack gimg" data-grade src="<?= e($p['image']) ?>" alt=""><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <?php $pcols = $sec['cols'] === 4 ? 4 : 5; /* effective desktop per-row count */ ?>
    <div class="prodgrid<?= $sec['cols'] === 4 ? ' c4' : '' ?><?= count($sec['ids']) > $pcols ? ' compact' : '' ?>" id="homeSec<?= $i ?>"></div>
  <?php endif; ?>
</section>
<?php endforeach; ?>

<!-- EDITORIAL -->
<section class="section-tight wrap" style="padding-top:0;margin-top:40px">
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
  <div class="grid g4" id="blogGrid"></div>
</section>

<!-- PROMISE -->
<section class="promise"><div class="wrap">
  <span class="eyebrow">where wellness meets you</span>
  <div class="big"><?= e(setting('promise_line1','glow,')) ?><br><span class="script"><?= e(setting('promise_accent','responsibly.')) ?></span></div>
  <p class="sub"><?= e(setting('promise_sub','Beirut-born, science-led skincare & wellness — dispensed with the care of your neighbourhood pharmacy, delivered to your door.')) ?></p>
  <div class="hero-cta" style="justify-content:center; margin-top:28px"><a class="btn btn-primary btn-lg" href="skincare">start shopping</a></div>
</div></section>

<!-- AS SEEN ON SOCIAL (Instagram / TikTok videos — admin → Social Videos) -->
<?php if ($SOCIAL_ON && ($SOCIAL_POSTS || $SOCIAL_IG || $SOCIAL_TT)): ?>
<section class="section-tight wrap" id="socialSec">
  <div class="sec-head" style="justify-content:center; text-align:center; flex-direction:column; gap:4px; align-items:center">
    <span class="eyebrow"><?= e(setting('social_sec_eyebrow', 'follow the glow')) ?></span>
    <h2 class="h2"><?= sec_title_html(setting('social_sec_title', 'as seen on social')) ?></h2>
    <?php if ($s = setting('social_sec_sub', '')): ?><p style="color:var(--ink-soft); max-width:52ch; margin-top:8px"><?= e($s) ?></p><?php endif; ?>
  </div>

  <?php if ($SOCIAL_POSTS): ?>
    <div class="socgrid" id="socGrid"></div>
  <?php else: ?>
    <p class="soc-empty">Videos are on the way — follow us in the meantime.</p>
  <?php endif; ?>

  <div class="soc-follow">
    <?php if ($SOCIAL_IG): ?><a class="btn btn-outline" href="<?= e($SOCIAL_IG) ?>" target="_blank" rel="noopener">follow on Instagram</a><?php endif; ?>
    <?php if ($SOCIAL_TT): ?><a class="btn btn-outline" href="<?= e($SOCIAL_TT) ?>" target="_blank" rel="noopener">follow on TikTok</a><?php endif; ?>
  </div>
</section>

<!-- video lightbox -->
<div class="soc-lb" id="socLb" aria-hidden="true">
  <div class="soc-lb-back" data-soc-close></div>
  <div class="soc-lb-card" role="dialog" aria-label="Social video">
    <button class="soc-lb-x" data-soc-close aria-label="Close">&times;</button>
    <div id="socLbBody"></div>
    <a class="soc-lb-out" id="socLbOut" href="#" target="_blank" rel="noopener">open on <span></span> ↗</a>
  </div>
</div>
<?php endif; ?>

<div id="usp"></div>
<?php
$SECTIONS_JSON = json_encode(array_map(fn($s) => $s['ids'], $SECTIONS), JSON_UNESCAPED_SLASHES);
$BRANDS_JSON   = json_encode($fbrands, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$BLOGS_JSON    = json_encode($jposts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$SOCIAL_JSON   = json_encode($SOCIAL_POSTS, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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

  // dynamic home sections (from database)
  const pick = ids => ids.map(id=>W.BY_ID[id]).filter(Boolean);
  const SECTIONS = $SECTIONS_JSON;
  SECTIONS.forEach((ids,i)=>{ if(!ids) return; const el=\$('#homeSec'+i); if(el) W.renderProducts(el, pick(ids)); });

  /* ---------- as seen on social ----------
     Cards are thumbnails; clicking opens the real Instagram/TikTok player in a
     lightbox (their public key-less embed). If a URL couldn't be turned into an
     embed we just open the post in a new tab instead. */
  const SOC = $SOCIAL_JSON;
  const socGrid = \$('#socGrid');
  if (socGrid && SOC.length) {
    const platIcon = { instagram: W.ICONS ? W.ICONS.ig : '', tiktok: W.ICONS ? W.ICONS.tiktok : '' };
    socGrid.innerHTML = SOC.map((p,i)=>{
      const thumb = p.thumb ? `<img src="\${p.thumb}" alt="" loading="lazy">` : '';
      const cap   = p.caption ? `<span class="cap">\${p.caption}</span>` : '';
      return `<a class="soccard reveal" data-soc="\${i}" href="\${p.url}" target="_blank" rel="noopener" aria-label="Play video">
                \${thumb}<span class="shade"></span>
                <span class="plat">\${platIcon[p.platform]||''}</span>
                <span class="play"></span>\${cap}
              </a>`;
    }).join('');
    W.guardImages(socGrid);

    const lb = \$('#socLb'), body = \$('#socLbBody'), out = \$('#socLbOut');
    const closeLb = () => { lb.classList.remove('open'); lb.setAttribute('aria-hidden','true'); body.innerHTML=''; document.body.style.overflow=''; };
    socGrid.addEventListener('click', e => {
      const card = e.target.closest('[data-soc]'); if (!card) return;
      const p = SOC[+card.dataset.soc];
      if (!p || !p.embed) return;                      // no embed -> let the link open the post
      e.preventDefault();
      body.innerHTML = `<iframe src="\${p.embed}" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen scrolling="no" title="Social video"></iframe>`;
      out.href = p.url; out.querySelector('span').textContent = p.platform === 'tiktok' ? 'TikTok' : 'Instagram';
      lb.classList.add('open'); lb.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden';
    });
    lb.addEventListener('click', e => { if (e.target.closest('[data-soc-close]')) closeLb(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && lb.classList.contains('open')) closeLb(); });
  }

  // trusted brands (from database) — respects each brand's display mode
  const brands = $BRANDS_JSON;
  \$('#brandGrid').innerHTML = brands.map(b=>{
    const hasLogo = !!(b.logo && b.logo.length), mode = b.logo_mode || 'auto';
    const img = `<img class="brand-logo" src="\${b.logo}" alt="\${b.name}" loading="lazy">`;
    const txt = `<span class="brand-logo-text">\${b.name}</span>`;
    let inner, cls = 'brandcard';
    if(mode==='name') inner = txt;
    else if(mode==='logo') inner = hasLogo ? img : txt;
    else if(mode==='both'){ inner = (hasLogo?img:'') + txt; if(hasLogo) cls += ' both'; }
    else inner = hasLogo ? img : txt;
    return `<a class="\${cls}" href="brands" aria-label="\${b.name}">\${inner}</a>`;
  }).join('');

  // journal (from database)
  const blogs = $BLOGS_JSON;
  \$('#blogGrid').innerHTML = blogs.map(b=>`<a class="blogcard" href="journal-post?slug=\${encodeURIComponent(b.slug)}"><div class="img graded" data-imgwrap><img class="gimg" data-grade src="\${b.image}" alt=""></div><div class="b"><span class="cat-l">\${b.category}</span><h3>\${b.title}</h3><span class="meta">By \${b.author} · \${b.read_min} min read</span></div></a>`).join('');

  document.getElementById('usp').innerHTML = W.uspHTML();
  W.guardImages(document);
</script>
JS;
include __DIR__ . '/inc/foot.php';
