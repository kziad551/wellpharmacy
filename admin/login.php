<?php
require __DIR__ . '/inc/auth.php';
if (current_admin()) redirect('dashboard');
$err = '';
if (is_post()) {
    csrf_check();
    if (admin_login(trim((string)input('username')), (string)input('password'))) redirect('dashboard');
    $err = 'Invalid username or password.';
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
    <div class="mark">W</div>
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
