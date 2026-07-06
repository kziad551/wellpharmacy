<?php
require __DIR__ . '/inc/functions.php';

$posts = rows("SELECT title, slug, category, excerpt, image, author, read_min, published_at
               FROM journal_posts WHERE status='published'
               ORDER BY sort, published_at DESC, id DESC");

$PAGE_TITLE = 'The Well Journal — ' . setting('store_name', 'WELL SHOP');
$ACTIVE = 'Shop All';
$HEAD_CSS = <<<CSS
<style>
  .jr-hero{background:var(--hero-grad);border-bottom:1px solid var(--border)}
  .jr-hero .wrap{padding-block:44px 40px}
  .jr-hero h1{font-family:var(--fp);font-size:clamp(32px,4vw,48px);font-weight:600;text-transform:lowercase;margin:10px 0 8px;letter-spacing:-.02em}
  .jr-hero .sub{color:var(--ink-soft);font-size:16px;max-width:60ch}
  .jr-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;padding-block:40px 20px}
  .blogcard{border:1px solid var(--border);border-radius:var(--r-card);overflow:hidden;background:#fff;transition:transform .25s,box-shadow .25s;display:flex;flex-direction:column}
  .blogcard:hover{transform:translateY(-6px);box-shadow:var(--sh-lg)}
  .blogcard .img{aspect-ratio:16/10;overflow:hidden;background:var(--cream-2)}
  .blogcard .img img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
  .blogcard:hover .img img{transform:scale(1.05)}
  .blogcard .b{padding:20px 22px 24px}
  .blogcard .cat-l{font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--rose-deep)}
  .blogcard h3{font-size:20px;margin:8px 0 8px;line-height:1.15}
  .blogcard p{font-size:13.5px;color:var(--ink-soft);line-height:1.55;margin:0 0 10px}
  .blogcard .meta{font-size:12px;color:var(--text-muted)}
  @media(max-width:980px){.jr-grid{grid-template-columns:repeat(2,1fr)}}
  @media(max-width:640px){.jr-grid{grid-template-columns:1fr}}
</style>
CSS;

include __DIR__ . '/inc/head.php';
?>
<section class="jr-hero">
  <div class="wrap">
    <nav class="crumb"><a href="index">Home</a><span class="sep">›</span><b>Journal</b></nav>
    <span class="eyebrow">✦ the well journal</span>
    <h1>from the wellness journal</h1>
    <p class="sub">Pharmacist-written guides on skincare, wellness and everything glow — backed by science, written to be read.</p>
  </div>
</section>

<div class="wrap">
  <?php if (!$posts): ?>
    <div class="section" style="text-align:center"><p class="muted">No journal posts yet — check back soon.</p></div>
  <?php else: ?>
  <div class="jr-grid">
    <?php foreach ($posts as $p): ?>
      <a class="blogcard" href="journal-post?slug=<?= e(urlencode($p['slug'])) ?>">
        <div class="img graded" data-imgwrap><img class="gimg" data-grade src="<?= e($p['image']) ?>" alt="" loading="lazy"></div>
        <div class="b">
          <span class="cat-l"><?= e($p['category']) ?></span>
          <h3><?= e($p['title']) ?></h3>
          <?php if ($p['excerpt']): ?><p><?= e($p['excerpt']) ?></p><?php endif; ?>
          <span class="meta">By <?= e($p['author']) ?> · <?= (int)$p['read_min'] ?> min read</span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<div id="usp"></div>
<?php
$PAGE_JS = "<script>document.getElementById('usp').innerHTML = WELL.uspHTML(); WELL.guardImages(document);</script>";
include __DIR__ . '/inc/foot.php';
