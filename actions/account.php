<?php
/* ============================================================
   Customer account actions: register · verify · login · logout ·
   profile · password · wishlist/cart sync.
   Form posts redirect back with a flash; sync calls answer JSON.
   ============================================================ */
require __DIR__ . '/../inc/functions.php';
require __DIR__ . '/../inc/customer.php';

/* This file lives in /actions/, so a bare relative Location ("account") would resolve to
   /actions/account. Step one level up instead — that keeps working both at the domain
   root and in a /wellpharmacy/ subfolder (we deliberately have no RewriteBase). */
function go(string $to): void { redirect('../' . ltrim($to, '/')); }

$do = (string) input('do', '');

/* ---- JSON endpoints (called by chrome.js) ---- */
if (in_array($do, ['sync', 'wish', 'cart', 'handoff'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    $in = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $ok = fn($d = []) => print(json_encode(['ok' => true] + $d));

    /* stash guest state so it can be merged the moment they log in */
    if ($do === 'handoff') {
        $_SESSION['guest_merge'] = ['wish' => $in['wish'] ?? [], 'cart' => $in['cart'] ?? []];
        $ok(); exit;
    }
    $cid = customer_id();
    if (!$cid) { echo json_encode(['ok' => false, 'guest' => true]); exit; }

    if ($do === 'sync') {                       // hand the account's saved state to the browser
        $ok(['wish' => wishlist_ids($cid), 'cart' => cart_rows($cid)]); exit;
    }
    if ($do === 'wish') {
        $pid = (string) ($in['id'] ?? '');
        if ($pid !== '') (($in['on'] ?? false) ? wishlist_add($cid, $pid) : wishlist_remove($cid, $pid));
        $ok(['wish' => wishlist_ids($cid)]); exit;
    }
    if ($do === 'cart') {
        foreach (($in['cart'] ?? []) as $l) {
            $pid = (string) ($l['id'] ?? ''); if ($pid === '') continue;
            cart_put($cid, $pid, (int) ($l['qty'] ?? 0));
        }
        // drop anything the browser no longer has
        $keep = array_column($in['cart'] ?? [], 'id');
        foreach (cart_rows($cid) as $r) if (!in_array($r['product_id'], $keep, true)) cart_put($cid, $r['product_id'], 0);
        $ok(); exit;
    }
}

/* ---- form posts ---- */
if (!is_post()) go('login');
csrf_check();
$next = (string) input('next', '');

switch ($do) {
    case 'register': {
        $r = customer_register(input('first_name'), input('last_name'), input('email'), (string) input('password'), (string) input('phone'));
        if (!$r['ok']) { cflash($r['err'], 'err'); $_SESSION['form_email'] = input('email'); go('register'); }
        $_SESSION['pending_verify'] = (int) $r['customer']['id'];
        cflash('We sent a 6-digit code to ' . $r['customer']['email'] . '.');
        go('verify');
    }
    case 'verify': {
        $id = (int) ($_SESSION['pending_verify'] ?? 0);
        $c  = $id ? row("SELECT * FROM customers WHERE id = ?", [$id]) : null;
        if (!$c) { cflash('Please register first.', 'err'); go('register'); }
        $r = otp_check($c, (string) input('code'));
        if (!$r['ok']) { cflash($r['err'], 'err'); go('verify'); }
        unset($_SESSION['pending_verify']);
        customer_session_start($c);
        cflash('Welcome to ' . setting('store_name', 'Well Pharmacy') . ' — your account is ready.');
        go('account');
    }
    case 'resend': {
        $id = (int) ($_SESSION['pending_verify'] ?? 0);
        $c  = $id ? row("SELECT * FROM customers WHERE id = ?", [$id]) : null;
        if (!$c) go('register');
        $r = otp_issue($c);
        cflash($r['ok'] ? 'A new code is on its way.' : $r['err'], $r['ok'] ? 'ok' : 'err');
        go('verify');
    }
    case 'login': {
        $r = customer_login((string) input('email'), (string) input('password'));
        if (!$r['ok']) { cflash($r['err'], 'err'); $_SESSION['form_email'] = input('email'); go('login'); }
        /* unverified accounts can still shop — we just nudge them to confirm */
        if (!(int) $r['customer']['verified']) {
            $_SESSION['pending_verify'] = (int) $r['customer']['id'];
            otp_issue($r['customer']);
            cflash('Please confirm your email — we sent you a new code.');
            go('verify');
        }
        go($next !== '' && !preg_match('#^https?://#i', $next) ? $next : 'account');
    }
    case 'logout': customer_logout(); cflash('You have been signed out.'); go('index');

    case 'profile': {
        require_customer(); $cid = customer_id();
        q("UPDATE customers SET first_name=?, last_name=?, phone=?, address=?, governorate=?, city=? WHERE id=?", [
            trim((string) input('first_name')), trim((string) input('last_name')), trim((string) input('phone')),
            trim((string) input('address')), trim((string) input('governorate')), trim((string) input('city')), $cid,
        ]);
        cflash('Your details were saved.');
        go('account');
    }
    case 'password': {
        require_customer(); $c = current_customer();
        if (!password_verify((string) input('current_password'), $c['password_hash'])) { cflash('Your current password is not correct.', 'err'); go('account?tab=password'); }
        $new = (string) input('new_password');
        if (strlen($new) < 8) { cflash('New password must be at least 8 characters.', 'err'); go('account?tab=password'); }
        if ($new !== (string) input('confirm_password')) { cflash('The two new passwords do not match.', 'err'); go('account?tab=password'); }
        q("UPDATE customers SET password_hash = ? WHERE id = ?", [password_hash($new, PASSWORD_DEFAULT), (int) $c['id']]);
        cflash('Your password was changed.');
        go('account?tab=password');
    }
}
go('index');
