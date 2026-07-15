<?php
require __DIR__ . '/inc/functions.php';
require __DIR__ . '/inc/customer.php';
require __DIR__ . '/inc/phone.php';
if (logged_in()) redirect('account');

$PAGE_TITLE = 'Create an account — ' . setting('store_name', 'WELL SHOP');
$ACTIVE = ''; $NO_POPUP = true;
require __DIR__ . '/inc/auth-css.php';
$HEAD_CSS = $AUTH_CSS;
$f = take_cflash();
$email = $_SESSION['form_email'] ?? ''; unset($_SESSION['form_email']);
include __DIR__ . '/inc/head.php';
?>
<div class="authwrap">
  <div class="authcard">
    <h1>create your account</h1>
    <p class="sub">Save your favourites, track your orders and check out faster.</p>
    <?php if ($f): ?><div class="flash <?= e($f['t']) ?>"><?= e($f['m']) ?></div><?php endif; ?>
    <form method="post" action="actions/account.php" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="do" value="register">
      <div class="two">
        <div class="field">
          <label for="first_name">First name</label>
          <input class="input" id="first_name" name="first_name" required autocomplete="given-name">
        </div>
        <div class="field">
          <label for="last_name">Last name</label>
          <input class="input" id="last_name" name="last_name" required autocomplete="family-name">
        </div>
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
      </div>
      <?= phone_field('phone', '', true) ?>
      <div class="field">
        <label for="password">Password</label>
        <input class="input" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        <span class="caption">At least 8 characters.</span>
      </div>
      <button class="btn btn-primary" type="submit">create account</button>
    </form>
    <p class="swap">Already have an account? <a href="login">Sign in</a></p>
    <p class="swap" style="margin-top:8px">You don't need an account to order — <a href="skincare">just shop</a>.</p>
  </div>
</div>
<?php include __DIR__ . '/inc/foot.php'; ?>
