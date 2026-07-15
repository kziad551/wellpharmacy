<?php
require __DIR__ . '/inc/functions.php';
require __DIR__ . '/inc/customer.php';
require_customer();

$c   = current_customer();
$cid = (int) $c['id'];
$tab = (string) input('tab', 'profile');
if (!in_array($tab, ['profile', 'orders', 'password'], true)) $tab = 'profile';

$orders = rows("SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC", [$cid]);

$PAGE_TITLE = 'My account — ' . setting('store_name', 'WELL SHOP');
$ACTIVE = ''; $NO_POPUP = true;
require __DIR__ . '/inc/auth-css.php';
$HEAD_CSS = $AUTH_CSS;
$f = take_flash();
include __DIR__ . '/inc/head.php';
?>
<div class="authwrap wide">
  <div class="acct-head">
    <div>
      <h1>hi, <?= e($c['first_name'] ?: 'there') ?></h1>
      <p class="muted" style="margin:6px 0 0;font-size:14px"><?= e($c['email']) ?>
        <?php if (!(int) $c['verified']): ?><span class="pill" style="margin-left:6px">unverified</span><?php endif; ?>
      </p>
    </div>
    <form method="post" action="actions/account.php">
      <?= csrf_field() ?><input type="hidden" name="do" value="logout">
      <button class="btn btn-ghost btn-sm" type="submit">sign out</button>
    </form>
  </div>

  <div class="acct-tabs">
    <a href="account?tab=profile"  class="<?= $tab === 'profile' ? 'on' : '' ?>">my details</a>
    <a href="account?tab=orders"   class="<?= $tab === 'orders' ? 'on' : '' ?>">my orders (<?= count($orders) ?>)</a>
    <a href="account?tab=password" class="<?= $tab === 'password' ? 'on' : '' ?>">password</a>
    <a href="wishlist">my favourites</a>
  </div>

  <?php if ($f): ?><div class="flash <?= e($f['t']) ?>"><?= e($f['m']) ?></div><?php endif; ?>

  <?php if ($tab === 'profile'): ?>
    <div class="panel">
      <h2>my details</h2>
      <form method="post" action="actions/account.php" novalidate>
        <?= csrf_field() ?><input type="hidden" name="do" value="profile">
        <div class="two">
          <div class="field"><label>First name</label><input class="input" name="first_name" value="<?= e($c['first_name']) ?>" required></div>
          <div class="field"><label>Last name</label><input class="input" name="last_name" value="<?= e($c['last_name']) ?>" required></div>
        </div>
        <div class="field"><label>Email</label><input class="input" value="<?= e($c['email']) ?>" disabled>
          <span class="caption">Contact us if you need to change your email.</span></div>
        <div class="field"><label>Phone</label><input class="input" name="phone" value="<?= e($c['phone']) ?>"></div>
        <div class="field"><label>Address</label><input class="input" name="address" value="<?= e($c['address']) ?>"></div>
        <div class="two">
          <div class="field"><label>Governorate</label>
            <select class="select" name="governorate">
              <option value="">Select…</option>
              <?php foreach (lebanon_governorates() as $g): ?>
                <option value="<?= e($g) ?>" <?= $c['governorate'] === $g ? 'selected' : '' ?>><?= e($g) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label>City / area</label><input class="input" name="city" value="<?= e($c['city']) ?>"></div>
        </div>
        <button class="btn btn-primary" type="submit" style="width:auto;padding:0 28px">save changes</button>
      </form>
    </div>

  <?php elseif ($tab === 'orders'): ?>
    <div class="panel">
      <h2>my orders</h2>
      <?php if (!$orders): ?>
        <div class="empty"><b>No orders yet</b>Once you place an order it'll show up here.
          <div style="margin-top:16px"><a class="btn btn-primary btn-sm" href="skincare">start shopping</a></div></div>
      <?php else: foreach ($orders as $o):
        $items = rows("SELECT name, qty FROM order_items WHERE order_id = ?", [(int) $o['id']]); ?>
        <div class="ordrow">
          <div class="top">
            <div>
              <div class="no"><?= e($o['order_no']) ?></div>
              <div class="when"><?= e(date('j M Y, H:i', strtotime($o['created_at']))) ?></div>
            </div>
            <div style="display:flex;align-items:center;gap:10px">
              <span class="pill <?= e($o['order_status']) ?>"><?= e($o['order_status']) ?></span>
              <b style="font-family:var(--fp);font-size:18px"><?= e(money($o['total'])) ?></b>
              <a class="btn btn-ghost btn-sm" href="invoice?order=<?= urlencode($o['order_no']) ?>">invoice</a>
            </div>
          </div>
          <div class="li">
            <?php foreach ($items as $i): ?>• <?= e($i['name']) ?> × <?= (int) $i['qty'] ?><br><?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>

  <?php else: ?>
    <div class="panel" style="max-width:520px">
      <h2>change password</h2>
      <form method="post" action="actions/account.php" novalidate>
        <?= csrf_field() ?><input type="hidden" name="do" value="password">
        <div class="field"><label>Current password</label><input class="input" type="password" name="current_password" required autocomplete="current-password"></div>
        <div class="field"><label>New password</label><input class="input" type="password" name="new_password" required minlength="8" autocomplete="new-password"></div>
        <div class="field"><label>Confirm new password</label><input class="input" type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></div>
        <button class="btn btn-primary" type="submit" style="width:auto;padding:0 28px">change password</button>
      </form>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/inc/foot.php'; ?>
