<?php
/* Favourites. Works for guests (localStorage) AND logged-in shoppers (DB).
   The grid is rendered client-side from W.wish() so both paths share one view. */
require __DIR__ . '/inc/functions.php';
require __DIR__ . '/inc/customer.php';

$PAGE_TITLE = 'My favourites — ' . setting('store_name', 'WELL SHOP');
$ACTIVE = ''; $NO_POPUP = true;
require __DIR__ . '/inc/auth-css.php';
$HEAD_CSS = $AUTH_CSS . <<<CSS
<style>
  .wishgrid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:20px}
  @media(max-width:1080px){.wishgrid{grid-template-columns:repeat(3,minmax(0,1fr))}}
  @media(max-width:760px){.wishgrid{grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}}
</style>
CSS;
include __DIR__ . '/inc/head.php';
?>
<div class="authwrap wide">
  <div class="acct-head">
    <div>
      <h1>my favourites</h1>
      <p class="muted" style="margin:6px 0 0;font-size:14px" id="wishSub">The things you've hearted.</p>
    </div>
    <?php if (logged_in()): ?>
      <a class="btn btn-ghost btn-sm" href="account">my account</a>
    <?php else: ?>
      <a class="btn btn-ghost btn-sm" href="login?next=wishlist">sign in to save these</a>
    <?php endif; ?>
  </div>

  <?php if (!logged_in()): ?>
    <div class="optnote">You're browsing as a guest — your favourites are saved on this device.
      <a href="login?next=wishlist" style="color:inherit;font-weight:700;text-decoration:underline">Sign in</a> to keep them on your account forever.</div>
  <?php endif; ?>

  <div id="wishGrid" class="wishgrid"></div>
  <div id="wishEmpty" class="empty" hidden>
    <b>Nothing saved yet</b>
    Tap the ♡ on any product to keep it here.
    <div style="margin-top:16px"><a class="btn btn-primary btn-sm" href="skincare">browse products</a></div>
  </div>
</div>

<?php ob_start(); ?>
<script>
(function () {
  var grid = document.getElementById('wishGrid'),
      empty = document.getElementById('wishEmpty'),
      sub = document.getElementById('wishSub');

  function render() {
    var ids = WELL.wish() || [];
    var list = ids.map(function (id) { return WELL.BY_ID[id]; }).filter(Boolean);
    if (!list.length) { grid.innerHTML = ''; empty.hidden = false; sub.textContent = 'Nothing saved yet.'; return; }
    empty.hidden = true;
    sub.textContent = list.length + (list.length === 1 ? ' saved item' : ' saved items');
    grid.innerHTML = list.map(function (p) { return WELL.productCard(p); }).join('');
    WELL.guardImages(grid);
  }

  // re-render whenever a heart is toggled anywhere on this page
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-wish]')) setTimeout(render, 0);
  });
  window.addEventListener('well:wish', render);

  render();
  document.getElementById('usp') && (document.getElementById('usp').innerHTML = WELL.uspHTML());
})();
</script>
<?php $PAGE_JS = ob_get_clean();
include __DIR__ . '/inc/foot.php'; ?>
