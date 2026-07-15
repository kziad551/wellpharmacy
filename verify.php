<?php
require __DIR__ . '/inc/functions.php';
require __DIR__ . '/inc/customer.php';
if (logged_in()) redirect('account');

$id = (int) ($_SESSION['pending_verify'] ?? 0);
$c  = $id ? row("SELECT * FROM customers WHERE id = ?", [$id]) : null;
if (!$c) redirect('register');

$PAGE_TITLE = 'Confirm your email — ' . setting('store_name', 'WELL SHOP');
$ACTIVE = ''; $NO_POPUP = true;
require __DIR__ . '/inc/auth-css.php';
$HEAD_CSS = $AUTH_CSS;
$f = take_cflash();
include __DIR__ . '/inc/head.php';
?>
<div class="authwrap">
  <div class="authcard">
    <h1>check your email</h1>
    <p class="sub">We sent a 6-digit code to <b><?= e($c['email']) ?></b>. It expires in 10 minutes.</p>
    <?php if ($f): ?><div class="flash <?= e($f['t']) ?>"><?= e($f['m']) ?></div><?php endif; ?>
    <?php if (!smtp_configured()): ?>
      <div class="optnote"><b>Dev mode:</b> no SMTP is configured yet, so the email wasn't actually sent — it was saved to
        <code>storage/mail/</code>. Open the newest file there to read your code.</div>
    <?php endif; ?>
    <form method="post" action="actions/account.php" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="do" value="verify">
      <div class="field">
        <label for="code">6-digit code</label>
        <input class="input otpbox" id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus autocomplete="one-time-code">
      </div>
      <button class="btn btn-primary" type="submit">confirm my email</button>
    </form>
    <form method="post" action="actions/account.php" style="margin-top:10px">
      <?= csrf_field() ?>
      <input type="hidden" name="do" value="resend">
      <button class="btn btn-ghost" type="submit" style="width:100%;height:44px">send a new code</button>
    </form>
    <p class="swap"><a href="register">Use a different email</a></p>
  </div>
</div>
<?php include __DIR__ . '/inc/foot.php'; ?>
