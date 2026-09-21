<?php
require __DIR__ . '/inc/auth.php';
admin_security_headers();
if (current_admin()) redirect('dashboard');
$err = '';
if (is_post()) {
    csrf_check();
    $uname = trim((string) input('username'));
    login_attempts_table();
    $wait = login_locked_for($uname);
    if ($wait > 0) {
        /* Deliberately vague: never confirm whether the username was real. */
        $err = 'Too many failed attempts. Try again in ' . max(1, (int) ceil($wait / 60)) . ' minute(s).';
    } elseif (admin_login($uname, (string) input('password'))) {
        redirect('dashboard');
    } else {
        $err = 'Invalid username or password.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in — WELL Admin</title>
<link rel="stylesheet" href="<?= asset('assets/admin.css') ?>">
</head>
<body>
<div class="a-login">
  <form class="box" method="post" action="login">
    <?php $llogo = brand_image('store_logo'); ?>
    <?php if ($llogo !== ''): ?>
      <img class="mark mark-img" src="<?= e(asrc($llogo)) ?>" alt="" onerror="this.remove()">
    <?php else: ?>
      <div class="mark"><?= e(strtoupper(substr(setting('store_name','W'), 0, 1))) ?></div>
    <?php endif; ?>
    <h1><?= e(setting('store_name','WELL PHARMACY')) ?></h1>
    <p>Sign in to your store admin panel.</p>
    <?php if ($err): ?><div class="flash flash-err"><?= e($err) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <div class="field"><label>Username</label><input class="input" name="username" autocomplete="username" autofocus></div>
    <div class="field"><label>Password</label><input class="input" type="password" name="password" autocomplete="current-password"></div>
    <button class="btn btn-primary" style="width:100%;height:46px;margin-top:6px">Sign in</button>
  </form>
</div>
</body>
</html>
