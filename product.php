<?php
require __DIR__ . '/inc/functions.php';

$id = (string) input('id');
$p  = row("SELECT * FROM products WHERE id = ? AND status='active'", [$id]);
if (!$p) { http_response_code(404); $PAGE_TITLE='Not found'; include __DIR__.'/inc/head.php'; echo '<div class="wrap section" style="text-align:center"><h1 class="h2">Product not found</h1><p class="muted">This product doesn\'t exist or is no longer available.</p><a class="btn btn-primary" href="skincare">Back to shop</a></div>'; include __DIR__.'/inc/foot.php'; exit; }

/* ---- per-browser reviewer identity (guest site, no accounts): a persistent token cookie
        lets us tie a review to this browser so it can be edited, not duplicated ---- */
$reviewerTok = (string)($_COOKIE['wp_reviewer'] ?? '');
if ($reviewerTok === '' || !ctype_xdigit($reviewerTok) || strlen($reviewerTok) !== 32) {
    $reviewerTok = bin2hex(random_bytes(16));
    setcookie('wp_reviewer', $reviewerTok, time() + 63072000, '/');   // 2 years
    $_COOKIE['wp_reviewer'] = $reviewerTok;
}

/* ---- reviews: handle a submission (insert new OR update this browser's own), then load ---- */
if (is_post() && input('action') === 'review') {
    csrf_check();
    $rAuthor = trim((string) input('author'));
    $rRating = max(1, min(5, (int) input('rating')));
    $rTitle  = trim((string) input('title'));
    $rBody   = trim((string) input('body'));
    if ($rAuthor !== '' && $rBody !== '') {
        $mine = row("SELECT id FROM reviews WHERE product_id=? AND reviewer_token=? LIMIT 1", [$p['id'], $reviewerTok]);
        if ($mine) {
            q("UPDATE reviews SET author=?, rating=?, title=?, body=? WHERE id=?", [$rAuthor, $rRating, $rTitle, $rBody, $mine['id']]);
        } else {
            q("INSERT INTO reviews (product_id, author, rating, title, body, reviewer_token) VALUES (?,?,?,?,?,?)", [$p['id'], $rAuthor, $rRating, $rTitle, $rBody, $reviewerTok]);
        }
        // refresh the cached rating + count on the product so cards/listings stay in sync
        $agg = row("SELECT COUNT(*) c, COALESCE(AVG(rating),0) a FROM reviews WHERE product_id = ? AND status='published'", [$p['id']]);
        q("UPDATE products SET reviews = ?, rating = ? WHERE id = ?", [(int)$agg['c'], round((float)$agg['a'], 1), $p['id']]);
    }
    redirect("product?id=" . urlencode($p['id']) . "&reviewed=" . ($mine ? 'upd' : '1') . "#reviews");
}
$reviews  = rows("SELECT * FROM reviews WHERE product_id = ? AND status='published' ORDER BY created_at DESC LIMIT 100", [$p['id']]);
$revCount = (int) $p['reviews'];
$revAvg   = (float) $p['rating'];
$dist = [5=>0,4=>0,3=>0,2=>0,1=>0];
foreach ($reviews as $r) { $k = (int) $r['rating']; if (isset($dist[$k])) $dist[$k]++; }
$stars5 = function ($v): string { $n = (int) round((float)$v); return str_repeat('★', max(0,min(5,$n))) . str_repeat('☆', 5 - max(0,min(5,$n))); };
$myReview = row("SELECT * FROM reviews WHERE product_id=? AND reviewer_token=? AND status='published' ORDER BY id DESC LIMIT 1", [$p['id'], $reviewerTok]);

$BADGES = ['derm'=>['badge-derm','DERM PICK'],'best'=>['badge-best','BESTSELLER'],'trend'=>['badge-trend','TRENDING'],'trusted'=>['badge-trusted','TRUSTED'],'new'=>['badge-new','NEW'],'vegan'=>['badge-vegan','VEGAN'],'ff'=>['badge-ff','FRAG-FREE']];
$badge = $p['badge'] && isset($BADGES[$p['badge']]) ? $BADGES[$p['badge']] : null;
$stock = (int)$p['stock'];
$low   = (int)$p['low_stock'];
$noPrice = ((float)$p['price'] <= 0);   // price not set yet — show "coming soon", block ordering

$related = rows("SELECT id FROM products WHERE status='active' AND category=? AND id<>? ORDER BY reviews DESC LIMIT 5", [$p['category'], $p['id']]);
if (count($related) < 5) $related = rows("SELECT id FROM products WHERE status='active' AND id<>? ORDER BY reviews DESC LIMIT 5", [$p['id']]);

$PAGE_TITLE = $p['name'] . ' — ' . setting('store_name','WELL SHOP');
$ACTIVE = $p['category'];
$HEAD_CSS = <<<CSS
<style>
  .pdp{display:grid; grid-template-columns:minmax(0,560px) 1fr; gap:48px; padding-top:8px; align-items:start}
  .wrap.pdp-w{max-width:1200px}   /* contain product detail + tabs + frequently-bought; "you may also love" stays full-width */
  .gallery{display:grid; grid-template-columns:72px 1fr; gap:16px}
  .thumbs{display:flex; flex-direction:column; gap:12px}
  .thumb-btn{width:72px; height:72px; border-radius:14px; overflow:hidden; border:2px solid transparent; background:var(--cream-2); padding:0; cursor:pointer}
  .thumb-btn.on{border-color:var(--rose)}
  .thumb-btn img{width:100%; height:100%; object-fit:cover}
  .main-img{position:relative; aspect-ratio:1/1; border-radius:24px; overflow:hidden; background:var(--cream-2); cursor:zoom-in}
  .main-img img{width:100%; height:100%; object-fit:cover; transition:transform .4s}
  .main-img:hover img{transform:scale(1.08)}
  .main-img .badge{position:absolute; top:16px; left:16px}
  .main-img .wish{top:16px; right:16px; width:44px; height:44px; position:absolute; background:#fff; border:1px solid var(--border-2); border-radius:50%; display:flex; align-items:center; justify-content:center}
  .main-img .wish svg{width:22px;height:22px}
  .buybox .eyebrow{color:var(--clinic-blue)}
  .buybox h1{font-family:var(--fp); font-size:34px; font-weight:600; line-height:1.1; margin:8px 0 12px}
  .rate-row{display:flex; align-items:center; gap:10px; font-size:14px; margin-bottom:16px}
  .rate-row .stars{color:var(--star); letter-spacing:1px}
  .rate-row a{color:var(--rose-deep); text-decoration:underline; font-weight:600}
  .price-row{display:flex; align-items:center; gap:14px; margin-bottom:14px}
  .price-row .p{font-family:var(--fp); font-size:30px; font-weight:600}
  .price-row .p.p-tba{font-size:22px; font-weight:500; color:var(--text-muted); font-style:italic}
  .price-row .was{font-size:18px; color:var(--text-faint); text-decoration:line-through}
  .instock{display:inline-flex; align-items:center; gap:7px; font-size:13px; font-weight:600; color:var(--mint)}
  .instock .dot{width:8px; height:8px; border-radius:50%; background:var(--mint)}
  .promise{font-size:15px; color:var(--ink-soft); line-height:1.6; margin:0 0 18px; max-width:46ch}
  .pdp-benefits{list-style:none; margin:2px 0 18px; padding:0; display:flex; flex-direction:column; gap:8px; max-width:52ch}
  .pdp-benefits li{position:relative; padding-left:26px; font-size:14.5px; color:var(--ink-soft); line-height:1.45}
  .pdp-benefits li::before{content:"✓"; position:absolute; left:0; top:1px; color:var(--mint); font-weight:700}
  .trust-chips{display:flex; gap:9px; flex-wrap:wrap; margin-bottom:22px}
  .buy-actions{display:flex; gap:12px; margin-bottom:14px}
  .qty-stepper{display:inline-flex; align-items:center; border:1.5px solid var(--border-2); border-radius:9999px; height:52px}
  .qty-stepper button{width:46px; height:50px; border:0; background:none; font-size:20px; color:var(--ink)}
  .qty-stepper .q{min-width:30px; text-align:center; font-weight:700}
  .urgency{display:flex; align-items:center; gap:8px; font-size:13.5px; font-weight:600; color:var(--coral-deep); margin-bottom:18px}
  .trust-row{display:grid; grid-template-columns:1fr 1fr; gap:10px 18px; padding:18px 0; border-top:1px solid var(--border-2)}
  .trust-row .ti{display:flex; align-items:center; gap:9px; font-size:13px; color:var(--ink-soft)}
  .trust-row .ti svg{width:17px; height:17px; color:var(--mint); flex:none}
  .pdp-tabs{background:var(--cream); border-bottom:1px solid var(--border); padding-block:14px}
  .pdp-tabs .pill-tabs{margin-left:-18px}
  .tab-panel{padding:32px 0; max-width:760px}
  .tab-panel h3{font-family:var(--fp); font-size:24px; font-weight:600; margin:0 0 14px}
  .tab-panel p{font-size:15.5px; line-height:1.7; color:var(--ink-soft)}
  .pharm-card{display:flex; gap:18px; background:var(--clinic-blue-tint); border-radius:20px; padding:24px}
  .pharm-card img{width:64px; height:64px; border-radius:50%; object-fit:cover; flex:none; border:2px solid #fff}
  .pharm-card .ey{font-size:11px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; color:var(--clinic-blue)}
  .pharm-card p{font-style:italic; margin:8px 0 6px; color:var(--ink-soft)}
  .rev-summary{display:grid; grid-template-columns:200px 1fr; gap:32px; align-items:center; padding:24px; background:#fff; border:1px solid var(--border-2); border-radius:20px; margin-bottom:24px}
  .rev-big{text-align:center} .rev-big .n{font-family:var(--fp); font-size:52px; font-weight:700; line-height:1}
  .rev-bars{display:flex; flex-direction:column; gap:7px}
  .rev-bar{display:flex; align-items:center; gap:10px; font-size:12.5px}
  .rev-bar .track{flex:1; height:7px; background:var(--cream-2); border-radius:9999px; overflow:hidden}
  .rev-bar .fill{height:100%; background:var(--star)}
  .rev-item{padding:18px 0; border-bottom:1px solid var(--border-2)}
  .rev-item .top{display:flex; align-items:center; gap:10px; margin-bottom:6px}
  .rev-item .av{width:38px;height:38px;border-radius:50%;object-fit:cover}
  .rev-item .vb{font-size:11px; font-weight:600; color:var(--mint); background:var(--mint-tint); padding:2px 8px; border-radius:9999px}
  .fbt{display:flex; align-items:center; gap:16px; flex-wrap:wrap; background:#fff; border:1px solid var(--border-2); border-radius:20px; padding:24px}
  .fbt .item{display:flex; flex-direction:column; align-items:center; gap:8px; width:120px}
  .fbt .item img{width:96px;height:96px;border-radius:14px;object-fit:cover;background:var(--cream-2)}
  .fbt .item .pr{font-family:var(--fp); font-weight:600; font-size:14px}
  .fbt .item .nm{font-size:11.5px; text-align:center; color:var(--text-muted); line-height:1.3}
  .fbt .plus{font-size:22px; color:var(--text-faint)}
  .fbt .tot{margin-left:auto; text-align:right}
  .fbt .tot .t{font-family:var(--fp); font-size:24px; font-weight:600}
  .mini-bar{position:fixed; bottom:0; left:0; right:0; background:#fff; border-top:1px solid var(--border-2); border-radius:16px 16px 0 0; box-shadow:0 -6px 26px rgba(44,38,31,.14); z-index:50; transform:translateY(110%); transition:transform .3s; padding-bottom:env(safe-area-inset-bottom)}
  .mini-bar.show{transform:translateY(0)}
  .mini-bar .wrap{display:flex; align-items:center; gap:14px; padding-block:12px}
  .mini-img{width:48px;height:48px;border-radius:10px;object-fit:cover; flex:none}
  .mini-title{font-family:var(--fs); font-size:14px; font-weight:600; min-width:0; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap}
  .mini-meta{display:flex; align-items:baseline; gap:8px; flex:none; min-width:0}
  .mini-meta .pr{font-family:var(--fp); font-size:20px; font-weight:600}
  .mini-meta .mini-brand{font-size:12px; color:var(--text-muted); max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap}
  .mini-btn{flex:none; white-space:nowrap}
  .lightbox{position:fixed; inset:0; background:rgba(44,38,31,.85); z-index:100; display:none; align-items:center; justify-content:center; padding:40px}
  .lightbox.open{display:flex} .lightbox img{max-width:90vw; max-height:90vh; border-radius:16px}
  .lightbox .x{position:absolute; top:24px; right:24px; color:#fff; width:44px;height:44px;border:0;background:rgba(255,255,255,.15);border-radius:50%}
  @media(max-width:900px){
    .pdp{grid-template-columns:1fr; gap:28px}
    .gallery{position:static; grid-template-columns:1fr}
    .thumbs{flex-direction:row; order:2; overflow-x:auto}
    .thumb-btn{flex:none}
    .rev-summary{grid-template-columns:1fr}
    .fbt .tot{margin-left:0; width:100%; text-align:left}
  }
  /* real reviews + write-a-review form */
  .rev-stars{display:flex; gap:4px; line-height:1; margin-bottom:2px}
  .rev-stars .rs{border:0; background:none; cursor:pointer; color:var(--star); font-size:32px; padding:0; line-height:1}
  .rev-form{max-width:640px}
  .rev-form .input{width:100%}
  .rev-form textarea.input{min-height:96px}
  .rev-item .top{align-items:center}
  /* calmer product gallery + tabs (smaller photo, more breathing room) */
  .main-img{max-height:560px}
  .pdp-tabs-wrap{margin-top:44px}
  .tab-panel{padding:40px 0 30px}

  /* ============ RESPONSIVE: small desktop → tablet → phone ============ */
  @media (min-width:901px) and (max-width:1100px){
    .wrap.pdp-w{max-width:980px}
    .pdp{grid-template-columns:minmax(0,420px) 1fr; gap:36px}
    .buybox h1{font-size:29px}
    .price-row .p{font-size:26px}
    .main-img{max-height:480px}
  }
  @media (max-width:900px){
    .buybox h1{font-size:27px; margin:6px 0 10px}
    .price-row .p{font-size:25px}
    .pdp-benefits li{font-size:14px}
    .tab-panel h3{font-size:21px}
    .tab-panel p{font-size:14.5px}
    /* tabs band: give the pills the page gutter instead of the desktop -18px pull */
    .pdp-tabs .pill-tabs{margin-left:0; flex-wrap:wrap}
    .pdp-tabs-wrap{margin-top:30px}
    .tab-panel{padding:24px 0 20px}
  }
  @media (max-width:560px){
    .buybox h1{font-size:23px; line-height:1.15; margin:4px 0 10px}
    .price-row .p{font-size:23px}
    .buybox .eyebrow{font-size:12px}
    .rate-row{font-size:13px; margin-bottom:12px}
    .trust-chips .chip{font-size:11.5px; padding:5px 9px}
    .pdp-benefits li{font-size:13.5px; padding-left:24px}
    .qty-stepper{height:48px} .qty-stepper button{height:46px}
    .buy-actions .btn{height:48px}
    .pdp{gap:20px}
    .main-img{border-radius:18px}
    .tab-panel h3{font-size:19px}
    /* mini-bar → tidy 2 rows × 2 cols: [img | title] / [price·brand | Add to Bag] */
    .mini-bar .wrap{display:grid; grid-template-columns:minmax(42px,auto) 1fr; grid-template-areas:"img title" "meta btn"; gap:5px 12px; padding-block:9px}
    .mini-img{grid-area:img; width:42px; height:42px; align-self:center}
    .mini-title{grid-area:title; white-space:normal; display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; align-self:center}
    .mini-meta{grid-area:meta; align-self:center}
    .mini-meta .pr{font-size:17px}
    .mini-btn{grid-area:btn; justify-self:end; align-self:center; height:42px; padding-inline:22px}
  }

  /* review CTA header, your-review card, and the write/edit modal */
  .rev-head{display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; margin-bottom:16px}
  .my-review{background:var(--clinic-blue-tint); border:1px solid var(--border-2); border-radius:14px; padding:14px 16px; margin-bottom:22px}
  .my-review .top{display:flex; align-items:center; gap:10px; margin-bottom:2px}
  .review-modal{position:fixed; inset:0; z-index:120; display:flex; align-items:center; justify-content:center; padding:20px}
  .review-modal[hidden]{display:none}
  .rm-backdrop{position:absolute; inset:0; background:rgba(44,38,31,.55)}
  .rm-box{position:relative; background:#fff; border-radius:20px; padding:26px 24px; width:min(480px,100%); max-height:90vh; overflow-y:auto; box-shadow:0 30px 80px rgba(44,38,31,.4)}
  .rm-x{position:absolute; top:14px; right:14px; width:34px; height:34px; border:0; background:var(--cream-2); border-radius:50%; font-size:15px; color:var(--ink); cursor:pointer}
  .rm-title{font-family:var(--fp); font-size:22px; font-weight:600; margin:0}
  .rm-sub{margin:2px 0 16px; font-size:13px}
  .rm-lbl{display:block; font-size:13px; font-weight:600; margin-bottom:6px}
  .rm-grid{display:grid; grid-template-columns:1fr 1fr; gap:12px; margin:14px 0}
  .rm-submit{margin-top:14px; width:100%}
  @media (max-width:480px){ .rm-grid{grid-template-columns:1fr} .rm-box{padding:22px 18px} }
</style>
CSS;

include __DIR__ . '/inc/head.php';
?>
<div class="wrap pdp-w">
  <nav class="crumb"><a href="index">Home</a><span class="sep">›</span><a href="skincare?cat=<?= urlencode($p['category']) ?>"><?= e($p['category']) ?></a><span class="sep">›</span><b><?= e($p['name']) ?></b></nav>
  <div class="pdp">
    <div class="gallery">
      <div class="thumbs" id="thumbs"></div>
      <div class="main-img graded" data-imgwrap id="mainImg">
        <?php if ($badge): ?><span class="badge <?= e($badge[0]) ?>" style="z-index:2"><?= e($badge[1]) ?></span><?php endif; ?>
        <button class="wish" data-wish="<?= e($p['id']) ?>" aria-label="Wishlist">♡</button>
        <img class="gimg" data-grade id="mainPhoto" alt="<?= e($p['name']) ?>">
      </div>
    </div>
    <div class="buybox">
      <span class="eyebrow"><?= e($p['brand']) ?></span>
      <h1><?= e($p['name']) ?></h1>
      <div class="rate-row"><?php if ($revCount > 0): ?><span class="stars"><?= $stars5($revAvg) ?></span> <b><?= number_format($revAvg,1) ?></b> <a href="#reviews"><?= $revCount ?> review<?= $revCount===1?'':'s' ?></a> <span class="muted">·</span> <a href="#reviews" class="js-review-open" style="color:var(--rose-deep);font-weight:600"><?= $myReview ? 'Edit your review' : 'Write a review' ?></a><?php else: ?><span class="muted">No reviews yet — <a href="#reviews" class="js-review-open" style="color:var(--rose-deep);text-decoration:underline;font-weight:600">be the first to review</a></span><?php endif; ?></div>
      <div class="price-row">
        <?php if ($noPrice): ?><span class="p p-tba">Price coming soon</span>
        <?php else: ?><span class="p"><?= money($p['price']) ?></span>
        <?php if ($p['was']): ?><span class="was"><?= money($p['was']) ?></span><?php endif; ?><?php endif; ?>
        <span class="instock" id="stockLine"></span>
      </div>
      <?php if (!empty($p['size'])): ?><div class="muted" style="font-size:13px;margin:-2px 0 14px">Size: <b><?= e($p['size']) ?></b></div><?php endif; ?>
      <?php if (!empty($p['benefits'])): ?>
      <ul class="pdp-benefits">
        <?php foreach (preg_split('/\r\n|\r|\n/', $p['benefits']) as $b): $b=trim(preg_replace('/^[\s•·\-]+/u','',$b)); if($b==='') continue; ?><li><?= e($b) ?></li><?php endforeach; ?>
      </ul>
      <?php else: ?>
      <p class="promise"><?= e($p['descr'] ?: $p['long_desc']) ?></p>
      <?php endif; ?>
      <div class="trust-chips">
        <span class="chip chip-mint">✓ 100% Authentic</span>
        <span class="chip chip-mint">Pharmacist-vetted</span>
        <span class="chip chip-mint">COD available</span>
      </div>
      <div class="buy-actions">
        <div class="qty-stepper"><button id="qd">−</button><span class="q" id="qty">1</span><button id="qi">+</button></div>
        <button class="btn btn-primary" style="flex:1" id="addBtn" <?= ($stock===0||$noPrice)?'aria-disabled="true"':'' ?>><?= $noPrice?'Price coming soon':($stock===0?'Out of stock':'Add to Bag') ?></button>
        <button class="btn btn-outline" data-wish="<?= e($p['id']) ?>">♡</button>
      </div>
      <div class="trust-row" id="trustRow"></div>
    </div>
  </div>
</div>

<div class="pdp-tabs-wrap">
<div class="pdp-tabs" id="pdpTabs">
  <div class="wrap pdp-w"><div class="pill-tabs">
    <button class="pill-tab" data-tab="desc">Description</button>
    <button class="pill-tab" data-tab="use">How to Use</button>
    <button class="pill-tab" data-tab="ingr">Ingredients</button>
    <button class="pill-tab" data-tab="pharm">Pharmacist Note</button>
    <button class="pill-tab" data-tab="rev">Reviews</button>
  </div></div>
</div>
<div class="wrap pdp-w">
  <div class="tab-panel" data-panel="desc" hidden>
    <h3>About this product</h3>
    <p><?= nl2br(e($p['long_desc'] ?: $p['descr'])) ?></p>
  </div>
  <div class="tab-panel" data-panel="use" hidden>
    <h3>How to Use</h3>
    <p><?= !empty($p['how_to_use']) ? nl2br(e($p['how_to_use'])) : 'Follow the directions on the pack. Introduce gradually if your skin is sensitive, and patch-test new actives. For external use only — keep out of reach of children. Speak to our pharmacists for personalised guidance.' ?></p>
  </div>
  <div class="tab-panel" data-panel="ingr" hidden>
    <h3>Ingredients</h3>
    <p><?= !empty($p['ingredients']) ? nl2br(e($p['ingredients'])) : 'The full ingredient list is printed on the pack. Ask our pharmacists about any specific sensitivities.' ?></p>
  </div>
  <div class="tab-panel" data-panel="pharm" hidden>
    <h3>Pharmacist Note</h3>
    <div class="pharm-card">
      <img class="gimg" data-grade id="pharmAv" alt="">
      <div><span class="ey">From our clinical team</span><p>"This is a pharmacist-vetted product we're happy to recommend. Reach out anytime — our team is here to help you choose what's right for you."</p><b>— Well Pharmacy Clinical Team</b></div>
    </div>
  </div>
  <div class="tab-panel" data-panel="rev" hidden id="reviews">
    <?php if (input('reviewed') === '1' || input('reviewed') === 'upd'): ?>
      <div style="background:var(--mint-tint);border:1px solid #cfd3b8;border-radius:14px;padding:14px 16px;margin-bottom:20px;font-weight:600">✓ Thanks! Your review has been <?= input('reviewed') === 'upd' ? 'updated' : 'posted' ?>.</div>
    <?php endif; ?>

    <div class="rev-head">
      <h3 style="margin:0"><?= $revCount > 0 ? number_format($revAvg,1).' out of 5 — '.$revCount.' review'.($revCount===1?'':'s') : 'No reviews yet' ?></h3>
      <button type="button" class="btn btn-outline js-review-open"><?= $myReview ? 'Edit your review' : 'Write a review' ?></button>
    </div>

    <?php if ($myReview): ?>
      <div class="my-review">
        <div class="top"><span class="stars" style="color:var(--star)"><?= $stars5($myReview['rating']) ?></span> <b>Your review</b> <span class="muted" style="font-size:12px">· <?= (int)$myReview['rating'] ?>/5</span> <a href="#reviews" class="js-review-open" style="margin-left:auto;color:var(--rose-deep);font-weight:600">Edit</a></div>
        <?php if ($myReview['title']): ?><b style="font-size:14.5px"><?= e($myReview['title']) ?></b><?php endif; ?>
        <p class="muted" style="margin:4px 0 0;font-size:14px"><?= nl2br(e($myReview['body'])) ?></p>
      </div>
    <?php endif; ?>

    <?php if ($revCount > 0): ?>
      <div class="rev-summary">
        <div class="rev-big"><div class="n"><?= number_format($revAvg,1) ?></div><div class="stars" style="color:var(--star)"><?= $stars5($revAvg) ?></div><div class="muted" style="font-size:13px;margin-top:4px"><?= $revCount ?> review<?= $revCount===1?'':'s' ?></div></div>
        <div class="rev-bars">
          <?php for ($s = 5; $s >= 1; $s--): $pct = $revCount ? round($dist[$s] / $revCount * 100) : 0; ?>
            <div class="rev-bar"><span><?= $s ?>★</span><div class="track"><div class="fill" style="width:<?= $pct ?>%"></div></div><span class="muted"><?= $pct ?>%</span></div>
          <?php endfor; ?>
        </div>
      </div>
      <div id="revList">
        <?php foreach ($reviews as $r): if ($myReview && (int)$r['id'] === (int)$myReview['id']) continue; ?>
          <div class="rev-item">
            <div class="top"><span class="stars" style="color:var(--star)"><?= $stars5($r['rating']) ?></span> <b><?= e($r['author']) ?></b> <span class="muted" style="font-size:12px;margin-left:auto"><?= e(date('M j, Y', strtotime($r['created_at']))) ?></span></div>
            <?php if ($r['title']): ?><b style="font-size:14.5px"><?= e($r['title']) ?></b><?php endif; ?>
            <p class="muted" style="margin:4px 0 0;font-size:14px"><?= nl2br(e($r['body'])) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php elseif (!$myReview): ?>
      <p class="muted" style="margin-bottom:8px">Be the first to review <?= e($p['name']) ?>.</p>
    <?php endif; ?>
  </div>
</div>
</div>

<section class="wrap section-tight">
  <div class="sec-head"><h2 class="h2">You may also <span class="script">love</span></h2><a class="view-all" href="skincare">view all</a></div>
  <div class="grid g5" id="related"></div>
</section>

<section class="wrap section-tight"><div id="sp"></div></section>
<div id="usp"></div>

<div class="mini-bar" id="miniBar">
  <div class="wrap">
    <img class="gimg mini-img" data-grade id="miniImg">
    <div class="mini-title"><?= e($p['name']) ?></div>
    <div class="mini-meta"><span class="pr" id="miniPrice"><?= $noPrice?'Price coming soon':money($p['price']) ?></span><span class="mini-brand"><?= e($p['brand']) ?></span></div>
    <button class="btn btn-primary mini-btn" id="miniAdd" <?= ($stock===0||$noPrice)?'aria-disabled="true"':'' ?>><?= $noPrice?'Price coming soon':'Add to Bag' ?></button>
  </div>
</div>
<div class="lightbox" id="lightbox"><button class="x" id="lbX">✕</button><img id="lbImg"></div>

<div class="review-modal" id="reviewModal" hidden>
  <div class="rm-backdrop" data-review-close></div>
  <div class="rm-box" role="dialog" aria-modal="true" aria-label="Write a review">
    <button class="rm-x" type="button" data-review-close aria-label="Close">✕</button>
    <h3 class="rm-title"><?= $myReview ? 'Edit your review' : 'Write a review' ?></h3>
    <p class="muted rm-sub"><?= e($p['name']) ?></p>
    <form method="post" action="product?id=<?= e($p['id']) ?>#reviews">
      <?= csrf_field() ?><input type="hidden" name="action" value="review">
      <label class="rm-lbl">Your rating</label>
      <div class="rev-stars" id="revStars">
        <?php for ($s = 1; $s <= 5; $s++): ?><button type="button" class="rs" data-v="<?= $s ?>">☆</button><?php endfor; ?>
        <input type="hidden" name="rating" id="ratingInput" value="<?= $myReview ? (int)$myReview['rating'] : 5 ?>">
      </div>
      <div class="rm-grid">
        <input class="input" name="author" placeholder="Your name" value="<?= $myReview ? e($myReview['author']) : '' ?>" required>
        <input class="input" name="title" placeholder="Review title (optional)" value="<?= $myReview ? e($myReview['title']) : '' ?>">
      </div>
      <textarea class="input" name="body" placeholder="Share your experience with this product…" rows="4" required><?= $myReview ? e($myReview['body']) : '' ?></textarea>
      <button class="btn btn-primary rm-submit"><?= $myReview ? 'Update review' : 'Post review' ?></button>
    </form>
  </div>
</div>
<?php
$pid      = json_encode($p['id']);
$jrelated = json_encode(array_column($related,'id'), JSON_UNESCAPED_SLASHES);
$PAGE_JS = <<<JS
<script>
  const W = WELL, \$ = s=>document.querySelector(s), \$\$ = s=>[...document.querySelectorAll(s)];
  document.querySelectorAll('[data-wish]').forEach(b=>{ if(b.textContent.trim()==='♡') b.innerHTML = W.icon('heart'); });
  const p = W.BY_ID[{$pid}];

  const gal = [...new Set([p.img, p.hover, ...(p.gallery||[])].filter(Boolean))];
  \$('#thumbs').innerHTML = gal.map((g,i)=>`<button class="thumb-btn \${i===0?'on':''}" data-i="\${i}"><img class="gimg" data-grade src="\${g}" alt=""></button>`).join('');
  function setPhoto(i){ \$('#mainPhoto').dataset.failed=''; \$('#mainPhoto').src=gal[i]; \$\$('.thumb-btn').forEach((b,j)=>b.classList.toggle('on',j===i)); W.guardImages(\$('#mainImg')); }
  setPhoto(0);
  \$\$('.thumb-btn').forEach(b=>{ b.addEventListener('mouseenter',()=>setPhoto(+b.dataset.i)); b.addEventListener('click',()=>setPhoto(+b.dataset.i)); });

  \$('#mainImg').addEventListener('click',e=>{ if(e.target.closest('.wish'))return; \$('#lbImg').src=\$('#mainPhoto').src; \$('#lightbox').classList.add('open'); });
  \$('#lbX').addEventListener('click',()=>\$('#lightbox').classList.remove('open'));
  \$('#lightbox').addEventListener('click',e=>{ if(e.target.id==='lightbox')\$('#lightbox').classList.remove('open'); });
  \$('#miniImg').src = p.img;
  \$('#pharmAv').src = W.IMG.teamWoman;

  \$('#trustRow').innerHTML = ['Cash on Delivery available','100% Authentic','Free shipping over \$'+(W.SETTINGS?W.SETTINGS.free_ship:49),'Easy returns','Same-day dispatch in Beirut','Pharmacist support'].map(t=>`<div class="ti">\${W.icon('check')} \${t}</div>`).join('');

  const STOCK = p.stock|0, LOW = p.low|0;
  let qty = Math.max(1, Math.min(W.cartQtyOf(p.id) || 1, STOCK || 1));
  function updateStock(){
    const el = \$('#stockLine'); if(!el) return;
    const inBag = W.cartQtyOf(p.id);
    let txt, color;
    if (STOCK <= 0)          { txt = 'Out of stock';                          color = 'var(--text-faint)'; }
    else if (inBag >= STOCK) { txt = 'Max in your bag — all ' + STOCK + ' added'; color = 'var(--coral-deep)'; }
    else if (STOCK <= LOW)   { txt = 'Only ' + STOCK + ' left';               color = 'var(--coral-deep)'; }
    else                     { txt = 'In stock';                              color = 'var(--mint)'; }
    el.style.color = color;
    el.innerHTML = '<span class="dot" style="background:' + color + '"></span> ' + txt;
  }
  const NOPRICE = !(p.price > 0);
  function paint(){
    \$('#qty').textContent = qty;
    if (!NOPRICE) \$('#miniPrice').textContent = W.money(p.price*qty);
    const inBag = W.cartQtyOf(p.id) > 0;
    const label = NOPRICE ? 'Price coming soon' : (STOCK<=0 ? 'Out of stock' : (inBag ? 'Update bag' : 'Add to Bag'));
    \$('#addBtn').textContent = label; \$('#miniAdd').textContent = label;
    \$('#qi').disabled = qty >= STOCK; \$('#qd').disabled = qty <= 1;
    updateStock();
  }
  \$('#qi').addEventListener('click',()=>{ if(qty < STOCK){ qty++; paint(); } });
  \$('#qd').addEventListener('click',()=>{ if(qty > 1){ qty--; paint(); } });
  function add(){ if(NOPRICE){ W.toast&&W.toast('Price coming soon — not available to order yet'); return; } if(STOCK<=0) return; qty = W.setCartQty(p.id, qty) || qty; paint(); W.openCart(); }
  \$('#addBtn').addEventListener('click',add);
  \$('#miniAdd').addEventListener('click',add);
  paint();

  // interactive star picker for the "write a review" form
  const rs = \$\$('.rs'), ri = \$('#ratingInput');
  if (rs.length && ri) {
    const paintStars = v => rs.forEach((b,i)=> b.textContent = (i < v ? '★' : '☆'));
    rs.forEach((b,i)=>{ b.addEventListener('click',()=>{ ri.value = i+1; paintStars(i+1); }); b.addEventListener('mouseenter',()=>paintStars(i+1)); });
    \$('#revStars').addEventListener('mouseleave',()=>paintStars(+ri.value));
    paintStars(+ri.value || 5);
  }

  // tabs are collapsed by default — click a tab to open it, click it again to close (accordion)
  function showTab(name){
    \$\$('.pill-tab').forEach(x=>x.classList.toggle('active', x.dataset.tab===name));
    \$\$('.tab-panel').forEach(pn=>pn.hidden = pn.dataset.panel!==name);
  }
  function closeTabs(){
    \$\$('.pill-tab').forEach(x=>x.classList.remove('active'));
    \$\$('.tab-panel').forEach(pn=>pn.hidden = true);
  }
  \$\$('.pill-tab').forEach(t=>t.addEventListener('click',()=>{
    if (t.classList.contains('active')) closeTabs(); else showTab(t.dataset.tab);
  }));
  if (location.hash === '#reviews') showTab('rev');

  // review write/edit modal
  const revModal = \$('#reviewModal');
  if (revModal) {
    const openRev = e => { if(e && e.preventDefault) e.preventDefault(); showTab('rev'); revModal.hidden=false; document.body.style.overflow='hidden'; };
    const closeRev = () => { revModal.hidden=true; document.body.style.overflow=''; };
    \$\$('.js-review-open').forEach(b=>b.addEventListener('click', openRev));
    revModal.querySelectorAll('[data-review-close]').forEach(x=>x.addEventListener('click', closeRev));
    document.addEventListener('keydown', e=>{ if(e.key==='Escape' && !revModal.hidden) closeRev(); });
  }

  W.renderProducts(\$('#related'), {$jrelated}.map(id=>W.BY_ID[id]).filter(Boolean));

  const io = new IntersectionObserver(es=>{ \$('#miniBar').classList.toggle('show', !es[0].isIntersecting); }, {threshold:0});
  io.observe(\$('#addBtn'));

  \$('#sp').innerHTML = W.socialProofHTML();
  \$('#usp').innerHTML = W.uspHTML();
  W.guardImages(document);
</script>
JS;
include __DIR__ . '/inc/foot.php';
