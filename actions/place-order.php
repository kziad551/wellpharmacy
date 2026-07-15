<?php
/* ============================================================
   Place an order (Cash on Delivery).
   Accepts JSON: { items:[{id,qty}], customer:{name,phone,email,address,governorate,city,notes},
                   payment_method, coupon_code, csrf }
   All prices, discounts and shipping are recomputed server-side from the DB.
   ============================================================ */
require __DIR__ . '/../inc/functions.php';
require __DIR__ . '/../inc/customer.php';   // optional: login is NEVER required to order
header('Content-Type: application/json; charset=utf-8');

function fail(string $msg, int $code = 422): void { http_response_code($code); echo json_encode(['ok' => false, 'err' => $msg]); exit; }

if (!is_post()) fail('Method not allowed.', 405);

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) fail('Bad request.');

$token = (string) ($in['csrf'] ?? '');
if (!hash_equals($_SESSION['csrf'] ?? '', $token)) fail('Your session expired — please refresh and try again.', 419);

$items = $in['items'] ?? [];
if (!is_array($items) || !$items) fail('Your bag is empty.');

/* ---- customer ---- */
$c       = is_array($in['customer'] ?? null) ? $in['customer'] : [];
$name    = trim((string) ($c['name'] ?? ''));
$phone   = trim((string) ($c['phone'] ?? ''));
$address = trim((string) ($c['address'] ?? ''));
$gov     = trim((string) ($c['governorate'] ?? ''));
$city    = trim((string) ($c['city'] ?? ''));
$email   = trim((string) ($c['email'] ?? ''));
$notes   = trim((string) ($c['notes'] ?? ''));

/* Logged in? Attach the order to the account (still totally optional — guests order fine).
   Fall back to the account's own email/name if the form left them blank. */
$cid  = customer_id();
$acct = $cid ? current_customer() : null;
if ($acct) {
    if ($email === '') $email = (string) $acct['email'];
    if ($name === '')  $name  = trim($acct['first_name'] . ' ' . $acct['last_name']);
}

if ($name === '' || $phone === '' || $address === '') fail('Please fill in your name, phone and address.');
if ($gov !== '' && !in_array($gov, lebanon_governorates(), true)) $gov = '';

/* ---- payment method ---- */
$pay = ($in['payment_method'] ?? 'cod') === 'areeba' && setting('areeba_enabled') === '1' ? 'areeba' : 'cod';
if ($pay === 'cod' && setting('cod_enabled', '1') !== '1') fail('Cash on Delivery is currently unavailable.');

/* ---- place the order atomically ----
   Each product row is locked with SELECT … FOR UPDATE, so two shoppers checking out at the
   same time can't both buy the last unit. We take only what's actually in stock and recompute
   every price/discount/shipping server-side from the DB. ---- */
$order_no = new_order_no();
$adjust   = [];   // human-readable notes about items reduced/removed due to stock

try {
    $pdo = db();
    $pdo->beginTransaction();

    $lines = []; $subtotal = 0.0;
    foreach ($items as $it) {
        $pid    = (string) ($it['id'] ?? '');
        $reqQty = max(1, (int) ($it['qty'] ?? 1));
        if ($pid === '') continue;
        $p = row("SELECT * FROM products WHERE id = ? AND status='active' FOR UPDATE", [$pid]);   // lock the row
        if (!$p) continue;
        $avail = (int) $p['stock'];
        if ($avail <= 0) { $adjust[] = "{$p['name']} sold out — removed"; continue; }
        $take = min($reqQty, $avail);
        if ($take < $reqQty) $adjust[] = "{$p['name']}: only {$take} left — quantity reduced";
        q("UPDATE products SET stock = stock - ? WHERE id = ?", [$take, $p['id']]);  // safe under the row lock
        $line = round((float) $p['price'] * $take, 2);
        $subtotal += $line;
        $lines[] = ['p' => $p, 'qty' => $take, 'line' => $line];
    }
    if (!$lines) { $pdo->rollBack(); fail('Sorry — the items in your bag just sold out. Please try again.'); }
    $subtotal = round($subtotal, 2);

    /* coupon, revalidated against the real subtotal */
    $discount = 0.0; $freeship = false; $couponCode = '';
    $code = trim((string) ($in['coupon_code'] ?? ''));
    if ($code !== '') {
        $cv = coupon_validate($code, $subtotal);
        if ($cv['ok']) { $discount = $cv['discount']; $freeship = $cv['freeship']; $couponCode = $cv['code']; }
    }
    $shipping = shipping_fee($gov, $subtotal, $freeship);
    $total    = round(max(0, $subtotal - $discount) + $shipping, 2);

    q("INSERT INTO orders
        (order_no,customer_id,customer_name,email,phone,address,governorate,city,payment_method,payment_status,order_status,subtotal,discount,shipping,total,coupon_code,notes)
        VALUES (?,?,?,?,?,?,?,?,?, 'pending', 'new', ?,?,?,?,?,?)",
        [$order_no, $cid, $name, $email, $phone, $address, $gov, $city, $pay, $subtotal, $discount, $shipping, $total, $couponCode, $notes]);
    $oid = (int) last_id();
    foreach ($lines as $l) {
        $p = $l['p'];
        q("INSERT INTO order_items (order_id,product_id,name,brand,price,qty,line_total) VALUES (?,?,?,?,?,?,?)",
           [$oid, $p['id'], $p['name'], $p['brand'], $p['price'], $l['qty'], $l['line']]);
    }
    if ($couponCode !== '') q("UPDATE coupons SET used_count = used_count + 1 WHERE code = ?", [$couponCode]);
    $pdo->commit();
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    fail('Sorry — we could not place your order. Please try again.', 500);
}

/* ---- notifications ----
   Best-effort and deliberately AFTER the commit: the order is already safe, so a
   mail server hiccup must never lose it or show the shopper an error. */
try {
    $order  = row("SELECT * FROM orders WHERE id = ?", [$oid]);
    $oitems = rows("SELECT * FROM order_items WHERE order_id = ?", [$oid]);
    send_order_confirmation($order, $oitems);   // no-op if a guest left email blank
    send_admin_order_alert($order, $oitems);    // admin hears about guest AND account orders
} catch (Throwable $e) { /* ignore — the order stands */ }

/* the saved bag has become an order */
if ($cid) { try { q("DELETE FROM customer_cart WHERE customer_id = ?", [$cid]); } catch (Throwable $e) {} }

/* remember for the confirmation page (scoped to this visitor's session) */
$_SESSION['last_order'] = $order_no;
if ($adjust) $_SESSION['order_note'] = 'Heads up — some items were adjusted for stock: ' . implode('; ', $adjust) . '.';
else unset($_SESSION['order_note']);
echo json_encode(['ok' => true, 'order_no' => $order_no, 'adjusted' => $adjust]);
