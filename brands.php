<?php
require __DIR__ . '/inc/functions.php';

$featured = rows("SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.brand = b.name) AS n
                  FROM brands b WHERE b.featured = 1 ORDER BY b.sort, b.name");
$all      = rows("SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.brand = b.name) AS n
                  FROM brands b ORDER BY b.name");

$PAGE_TITLE = 'Shop by Brand — ' . setting('store_name', 'WELL SHOP');
$ACTIVE = 'Brands';
$HEAD_CSS = <<<CSS
<style>
  .br-hero{background:var(--hero-grad);border-bottom:1px solid var(--border)}
  .br-hero .wrap{padding-block:44px 40px;text-align:center}
  .br-hero h1{font-family:var(--fp);font-size:clamp(32px,4vw,48px);font-weight:600;text-transform:lowercase;margin:10px 0 8px;letter-spacing:-.02em}
  .br-hero .sub{color:var(--ink-soft);font-size:16px;max-width:56ch;margin-inline:auto}
  .brandgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;padding-block:36px 12px}
  .brandcard{position:relative;display:flex;align-items:center;justify-content:center;height:118px;padding:22px 24px;
    background:#fff;border:1px solid var(--border);border-radius:var(--r-card);box-shadow:var(--sh-xs);transition:transform .25s,box-shadow .25s,border-color .25s;text-align:center}
  .brandcard:hover{transform:translateY(-5px);box-shadow:var(--sh-rose);border-color:var(--rose)}
  .brandcard .brand-logo-text{font-family:var(--fp);font-weight:700;font-size:22px;line-height:1.1;letter-spacing:.2px;color:var(--ink);transition:color .25s}
  .brandcard:hover .brand-logo-text{color:var(--rose-deep)}
  .brandcard .brand-logo{max-height:64px;max-width:100%;width:auto;object-fit:contain}
  .brandcard .ct{position:absolute;top:10px;right:12px;font-size:11px;color:var(--text-muted)}
  .br-sec-h{display:flex;align-items:center;gap:12px;margin-top:26px}
  .br-sec-h .eyebrow{white-space:nowrap}
  .br-sec-h .ln{flex:1;height:1px;background:var(--border)}
  @media(max-width:980px){.brandgrid{grid-template-columns:repeat(3,1fr)}}
  @media(max-width:620px){.brandgrid{grid-template-columns:repeat(2,1fr)}}
</style>
CSS;

/* renders a single brand tile linking to its filtered listing */
function brand_tile(array $b): string {
    $href = 'skincare?brand=' . urlencode($b['name']);
    $inner = $b['logo']
        ? '<img class="brand-logo" src="' . e($b['logo']) . '" alt="' . e($b['name']) . '" loading="lazy">'
        : '<span class="brand-logo-text"' . ($b['color'] ? ' style="color:' . e($b['color']) . '"' : '') . '>' . e($b['name']) . '</span>';
    $count = $b['n'] > 0 ? '<span class="ct">' . (int)$b['n'] . '</span>' : '';
    return '<a class="brandcard" href="' . e($href) . '" aria-label="' . e($b['name']) . '">' . $count . $inner . '</a>';
}

include __DIR__ . '/inc/head.php';
?>
<section class="br-hero">
  <div class="wrap">
    <nav class="crumb" style="justify-content:center"><a href="index">Home</a><span class="sep">›</span><b>Brands</b></nav>
    <span class="eyebrow">authentic, always</span>
    <h1>shop trusted brands</h1>
    <p class="sub">Every brand we carry is sourced directly and quality-checked by licensed pharmacists — no fakes, ever.</p>
  </div>
</section>

<div class="wrap">
  <?php if ($featured): ?>
    <div class="br-sec-h"><span class="eyebrow">featured</span><span class="ln"></span></div>
    <div class="brandgrid"><?php foreach ($featured as $b) echo brand_tile($b); ?></div>
  <?php endif; ?>

  <div class="br-sec-h"><span class="eyebrow">all brands (<?= count($all) ?>)</span><span class="ln"></span></div>
  <?php if (!$all): ?>
    <div class="section" style="text-align:center"><p class="muted">No brands yet.</p></div>
  <?php else: ?>
    <div class="brandgrid" style="padding-bottom:36px"><?php foreach ($all as $b) echo brand_tile($b); ?></div>
  <?php endif; ?>
</div>

<div id="usp"></div>
<?php
$PAGE_JS = "<script>document.getElementById('usp').innerHTML = WELL.uspHTML(); WELL.guardImages(document);</script>";
include __DIR__ . '/inc/foot.php';
