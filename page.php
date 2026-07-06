<?php
require __DIR__ . '/inc/functions.php';

$slug = $slug ?? (string) input('slug');
$pg = row("SELECT * FROM pages WHERE slug = ? AND status='published'", [$slug]);
if (!$pg) {
    http_response_code(404);
    $PAGE_TITLE = 'Page not found';
    include __DIR__ . '/inc/head.php';
    echo '<div class="wrap section" style="text-align:center"><h1 class="h2">Page not found</h1><p class="muted">That page doesn\'t exist or was unpublished.</p><a class="btn btn-primary" href="index">Back home</a></div>';
    include __DIR__ . '/inc/foot.php';
    exit;
}

$PAGE_TITLE = $pg['title'] . ' — ' . setting('store_name', 'WELL SHOP');
$ACTIVE = 'Shop All';
$HEAD_CSS = <<<CSS
<style>
  .legal-hero{background:var(--hero-grad);border-bottom:1px solid var(--border)}
  .legal-hero .wrap{padding-block:44px 40px}
  .legal-hero h1{font-family:var(--fp);font-size:clamp(32px,4vw,48px);font-weight:600;text-transform:lowercase;margin:10px 0 8px;letter-spacing:-.02em}
  .legal-hero .sub{color:var(--ink-soft);font-size:16px;max-width:60ch}
  .legal{max-width:780px;margin-inline:auto;padding-block:48px 24px}
  .legal h3{font-family:var(--fp);font-size:22px;font-weight:600;margin:30px 0 10px;letter-spacing:-.01em}
  .legal h3:first-child{margin-top:0}
  .legal p,.legal li{font-size:15.5px;line-height:1.75;color:var(--ink-soft)}
  .legal ul,.legal ol{margin:8px 0 8px 22px;display:flex;flex-direction:column;gap:6px}
  .legal a{color:var(--rose-deep);text-decoration:underline;font-weight:600}
  .legal b{color:var(--ink)}
  .legal-cta{background:var(--cream);border:1px solid var(--border);border-radius:var(--r-lg);padding:28px;text-align:center;margin:34px auto 0;max-width:780px}
</style>
CSS;

include __DIR__ . '/inc/head.php';
?>
<section class="legal-hero">
  <div class="wrap">
    <nav class="crumb"><a href="index">Home</a><span class="sep">›</span><b><?= e($pg['title']) ?></b></nav>
    <h1><?= e($pg['title']) ?></h1>
    <?php if ($pg['intro']): ?><p class="sub"><?= e($pg['intro']) ?></p><?php endif; ?>
  </div>
</section>

<div class="wrap"><div class="legal"><?= $pg['body'] ?></div>
  <div class="legal-cta">
    <h3 class="h3" style="margin-bottom:8px">Still have a question?</h3>
    <p class="muted" style="margin:0 0 16px">Our pharmacists are happy to help — privately and quickly.</p>
    <a class="btn btn-primary" href="contact">Contact us</a>
    <a class="btn btn-ghost" href="https://wa.me/<?= e(setting('whatsapp_number','9613627766')) ?>" target="_blank" rel="noopener">WhatsApp</a>
  </div>
</div>

<div id="usp"></div>
<?php
$PAGE_JS = "<script>document.getElementById('usp').innerHTML = WELL.uspHTML(); WELL.guardImages(document);</script>";
include __DIR__ . '/inc/foot.php';
