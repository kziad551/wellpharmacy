<?php
/* ============================================================
   WELL PHARMACY — customer (shopper) accounts.
   Separate from admin auth: shoppers live in `customers`,
   staff live in `admin_users`. A shopper is NEVER an admin.
   ============================================================ */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mailer.php';

/* ---------- session ---------- */
function current_customer(): ?array {
    $id = $_SESSION['cust_id'] ?? null;
    if (!$id) return null;
    static $cache = null;
    if ($cache && (int) $cache['id'] === (int) $id) return $cache;
    $c = row("SELECT * FROM customers WHERE id = ?", [(int) $id]);
    if (!$c) { unset($_SESSION['cust_id']); return null; }
    return $cache = $c;
}
function customer_id(): ?int { $c = current_customer(); return $c ? (int) $c['id'] : null; }
function logged_in(): bool { return current_customer() !== null; }
function require_customer(): void { if (!logged_in()) redirect('login?next=account'); }
function customer_name(): string {
    $c = current_customer();
    return $c ? trim($c['first_name'] . ' ' . $c['last_name']) : '';
}

/* Shopper flashes live under their OWN session key. They must never share
   $_SESSION['flash'] with the admin panel — otherwise a shopper signing out
   pops "You have been signed out" inside the admin, which is both confusing
   and none of the admin's business. */
function cflash(string $msg, string $type = 'ok'): void { $_SESSION['cust_flash'] = ['m' => $msg, 't' => $type]; }
function take_cflash(): ?array { $f = $_SESSION['cust_flash'] ?? null; unset($_SESSION['cust_flash']); return $f; }

/* ---------- OTP ---------- */
function otp_generate(): string { return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT); }

/** Issue a fresh OTP for a customer and email it. Throttled to 1 per 60s. */
function otp_issue(array $cust, string $purpose = 'verify'): array {
    $last = $cust['otp_sent_at'] ?? null;
    if ($last && (time() - strtotime($last)) < 60) {
        return ['ok' => false, 'err' => 'Please wait a minute before requesting another code.'];
    }
    $code = otp_generate();
    q("UPDATE customers SET otp_code = ?, otp_expires = DATE_ADD(NOW(), INTERVAL 10 MINUTE), otp_sent_at = NOW(), otp_tries = 0 WHERE id = ?",
        [$code, (int) $cust['id']]);
    $sent = send_otp_email($cust['email'], trim($cust['first_name'] . ' ' . $cust['last_name']), $code);
    return ['ok' => true, 'sent' => $sent, 'code' => $code];
}

/** Check a submitted OTP. Marks the account verified on success. */
function otp_check(array $cust, string $code): array {
    $code = preg_replace('/\D/', '', $code);
    if ((int) $cust['otp_tries'] >= 6) return ['ok' => false, 'err' => 'Too many attempts — please request a new code.'];
    q("UPDATE customers SET otp_tries = otp_tries + 1 WHERE id = ?", [(int) $cust['id']]);
    if (!$cust['otp_code'] || !$cust['otp_expires']) return ['ok' => false, 'err' => 'No code pending — please request one.'];
    if (strtotime($cust['otp_expires']) < time())  return ['ok' => false, 'err' => 'That code has expired — please request a new one.'];
    if (!hash_equals((string) $cust['otp_code'], (string) $code)) return ['ok' => false, 'err' => 'That code is not correct.'];
    q("UPDATE customers SET verified = 1, otp_code = NULL, otp_expires = NULL, otp_tries = 0 WHERE id = ?", [(int) $cust['id']]);
    return ['ok' => true];
}

/* ---------- register / login ---------- */
function customer_register(string $first, string $last, string $email, string $pass, string $phone = ''): array {
    $first = trim($first); $last = trim($last); $email = strtolower(trim($email)); $phone = trim($phone);
    if ($first === '' || $last === '')                     return ['ok' => false, 'err' => 'Please enter your first and last name.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))        return ['ok' => false, 'err' => 'Please enter a valid email address.'];
    if (strlen($pass) < 8)                                 return ['ok' => false, 'err' => 'Password must be at least 8 characters.'];
    if (row("SELECT id FROM customers WHERE email = ?", [$email])) {
        return ['ok' => false, 'err' => 'An account with that email already exists — try logging in.'];
    }
    q("INSERT INTO customers (first_name,last_name,email,phone,password_hash) VALUES (?,?,?,?,?)",
        [$first, $last, $email, $phone, password_hash($pass, PASSWORD_DEFAULT)]);
    $c = row("SELECT * FROM customers WHERE id = ?", [(int) last_id()]);
    otp_issue($c);
    return ['ok' => true, 'customer' => $c];
}

function customer_login(string $email, string $pass): array {
    $email = strtolower(trim($email));
    $c = row("SELECT * FROM customers WHERE email = ?", [$email]);
    if (!$c || !password_verify($pass, $c['password_hash'])) {
        return ['ok' => false, 'err' => 'Email or password is incorrect.'];
    }
    customer_session_start($c);
    return ['ok' => true, 'customer' => $c];
}

function customer_session_start(array $c): void {
    session_regenerate_id(true);
    $_SESSION['cust_id'] = (int) $c['id'];
    merge_guest_data_into_account((int) $c['id']);
}

/* Signing out must also wipe the browser's local copy of the bag/favourites.
   Those belong to the ACCOUNT, not the device — leaving them behind would show
   the next person (or the next account to sign in) someone else's saved items. */
function customer_logout(): void {
    unset($_SESSION['cust_id'], $_SESSION['guest_merge']);
    session_regenerate_id(true);
    $_SESSION['flush_local'] = 1;   // consumed once by assets/data.php
}

/* ---------- saved wishlist / cart ----------
   Guests keep these in localStorage; on login we merge whatever they built
   as a guest into the account so nothing is lost. */
function wishlist_ids(int $cid): array {
    return array_column(rows("SELECT product_id FROM customer_wishlist WHERE customer_id = ? ORDER BY created_at DESC", [$cid]), 'product_id');
}
function wishlist_add(int $cid, string $pid): void {
    q("INSERT IGNORE INTO customer_wishlist (customer_id, product_id) VALUES (?,?)", [$cid, $pid]);
}
function wishlist_remove(int $cid, string $pid): void {
    q("DELETE FROM customer_wishlist WHERE customer_id = ? AND product_id = ?", [$cid, $pid]);
}
function cart_rows(int $cid): array {
    return rows("SELECT product_id, qty FROM customer_cart WHERE customer_id = ?", [$cid]);
}
function cart_put(int $cid, string $pid, int $qty): void {
    if ($qty <= 0) { q("DELETE FROM customer_cart WHERE customer_id = ? AND product_id = ?", [$cid, $pid]); return; }
    q("INSERT INTO customer_cart (customer_id, product_id, qty) VALUES (?,?,?) ON DUPLICATE KEY UPDATE qty = VALUES(qty)", [$cid, $pid, $qty]);
}

/** Pending guest state is handed over by the client at login time. */
function merge_guest_data_into_account(int $cid): void {
    $pend = $_SESSION['guest_merge'] ?? null;
    if (!$pend) return;
    foreach (($pend['wish'] ?? []) as $pid) if (is_string($pid) && $pid !== '') wishlist_add($cid, $pid);
    foreach (($pend['cart'] ?? []) as $line) {
        $pid = (string) ($line['id'] ?? ''); $qty = max(1, (int) ($line['qty'] ?? 1));
        if ($pid === '') continue;
        $cur = row("SELECT qty FROM customer_cart WHERE customer_id = ? AND product_id = ?", [$cid, $pid]);
        cart_put($cid, $pid, max($qty, (int) ($cur['qty'] ?? 0)));   // keep the larger qty
    }
    unset($_SESSION['guest_merge']);
}
