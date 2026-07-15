<?php
require __DIR__ . '/inc/functions.php';
require __DIR__ . '/inc/customer.php';
if (logged_in()) redirect('account');

$PAGE_TITLE = 'Sign in — ' . setting('store_name', 'WELL SHOP');
$ACTIVE = ''; $NO_POPUP = true;
require __DIR__ . '/inc/auth-css.php';
$HEAD_CSS = $AUTH_CSS;
$f = take_cflash();
$email = $_SESSION['form_email'] ?? ''; unset($_SESSION['form_email']);
$next  = (string) input('next', '');
include __DIR__ . '/inc/head.php';
?>
<div class="authwrap">
  <div class="authcard">
    <h1>welcome back</h1>
    <p class="sub">Sign in to see your orders and favourites.</p>
    <?php if ($f): ?><div class="flash <?= e($f['t']) ?>"><?= e($f['m']) ?></div><?php endif; ?>
    <form method="post" action="actions/account.php" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="do" value="login">
      <input type="hidden" name="next" value="<?= e($next) ?>">
      <div class="field">
        <label for="email">Email</label>
        <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button class="btn btn-primary" type="submit">sign in</button>
    </form>
    <p class="swap">New here? <a href="register">Create an account</a></p>
    <p class="swap" style="margin-top:8px">You don't need an account to order — <a href="skincare">just shop</a>.</p>
  </div>
</div>
<?php include __DIR__ . '/inc/foot.php'; ?>
