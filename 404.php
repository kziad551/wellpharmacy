<?php
require __DIR__ . '/inc/functions.php';
http_response_code(404);

$PAGE_TITLE = 'Page not found — ' . setting('store_name', 'WELL SHOP');
$ACTIVE = 'Shop All';
$HEAD_CSS = <<<CSS
<style>
  .nf{max-width:640px;margin-inline:auto;text-align:center;padding-block:70px 90px}
  .nf .big{font-family:var(--fp);font-size:clamp(80px,18vw,150px);font-weight:600;line-height:.9;color:var(--rose);letter-spacing:-.02em}
  .nf h1{font-family:var(--fp);font-size:clamp(24px,3vw,34px);font-weight:600;text-transform:lowercase;margin:8px 0 10px}
  .nf p{color:var(--ink-soft);margin:0 0 24px;font-size:16px}
  .nf .btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
</style>
CSS;

include __DIR__ . '/inc/head.php';
?>
<div class="wrap nf">
  <div class="big">404</div>
  <h1>page not found</h1>
  <p>Sorry — the page you're looking for doesn't exist or has moved.</p>
  <div class="btns">
    <a class="btn btn-primary" href="index">Back home</a>
    <a class="btn btn-outline" href="skincare">Shop all products</a>
    <a class="btn btn-ghost" href="contact">Contact us</a>
  </div>
</div>

<div id="usp"></div>
<?php
$PAGE_JS = "<script>document.getElementById('usp').innerHTML = WELL.uspHTML(); WELL.guardImages(document);</script>";
include __DIR__ . '/inc/foot.php';
