<?php
/* ============================================================
   Place an order (Cash on Delivery).
   Accepts JSON: { items:[{id,qty}], customer:{name,phone,email,address,governorate,city,notes},
                   payment_method, coupon_code, csrf }
   All prices, discounts and shipping are recomputed server-side from the DB.
   ============================================================ */
require __DIR__ . '/../inc/functions.php';
require __DIR__ . '/../inc/customer.php';   // optional: login is NEVER required to order
require_once __DIR__ . '/../inc/gifts.php';
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

/* The browser checks these too, but never trust it — a bypassed form must not
   create an order, move stock or send any email. */
if ($name === '' || $phone === '' || $address === '') fail('Please fill in your name, phone and address.');
if ($email === '') fail('Please enter your email address so we can send your receipt.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('That email address does not look right.');
if ($gov === '' || !in_array($gov, lebanon_governorates(), true)) fail('Please choose a valid delivery area.');

/* ---- payment method ---- */
$pay = ($in['payment_method'] ?? 'cod') === 'areeba' && setting('areeba_enabled') === '1' ? 'areeba' : 'cod';
if ($pay === 'cod' && setting('cod_enabled', '1') !== '1') fail('Cash on Delivery is currently unavailable.');

/* ---- place the order atomically ----
   Each product row is locked with SELECT … FOR UPDATE, so two shoppers checking out at the
   same time can't both buy the last unit. We take only what's actually in stock and recompute
   every price/discount/shipping server-side from the DB. ---- */
$order_no = new_order_no();
$adjust   = [];   // human-readable notes about items reduced/removed due to stock

/* Gift offers the shop is showing right now, read BEFORE this order takes any stock.
   An offer whose gift has run out is hidden from the storefront (gifts_live_map()), so
   only these products earn a gift: nobody gets a "sorry, the gift ran out" note for a
   gift they were never offered. A gift that runs out during this order still gets one.
   gifts_seen (optional) lists the products whose gift row the checkout page showed, so a
   gift that ran out between page load and submit also gets its note instead of vanishing.
   It is only a hint: gifts_for_order() still decides from the DB, under lock, so a crafted
   list can earn nothing a live offer would not give anyway (at most a "ran out" note). */
$liveGifts = gifts_live_map();
$giftsSeen = [];
foreach ((array) ($in['gifts_seen'] ?? []) as $gpid) if (is_string($gpid) && $gpid !== '') $giftsSeen[$gpid] = true;

try {
    $pdo = db();
    $pdo->beginTransaction();

    $lines = []; $subtotal = 0.0;
    $paidForGifts = [];   // [pid, units actually taken] per paid line with a live or shown offer, for gifts_for_order()
    $unpriced = [];       // names removed by the $0 guard below
    $noOption = [];       // names removed because their colour/size/flavour is missing or no longer offered
    foreach ($items as $it) {
        $pid    = (string) ($it['id'] ?? '');
        $reqQty = max(1, (int) ($it['qty'] ?? 1));
        if ($pid === '') continue;
        $p = row("SELECT * FROM products WHERE id = ? AND status='active' FOR UPDATE", [$pid]);   // lock the row
        if (!$p) continue;
        /* variants: validate the chosen color/size/flavor and take the price from the DB, never the client.
           A crafted non-string option (an array) counts as none, not as "Array" plus a PHP warning. */
        $color  = is_scalar($it['color'] ?? null)  ? trim((string) $it['color'])  : '';
        $size   = is_scalar($it['size'] ?? null)   ? trim((string) $it['size'])   : '';
        $flavor = is_scalar($it['flavor'] ?? null) ? trim((string) $it['flavor']) : '';
        $vr = variant_resolve($p, $color, $size, $flavor);
        if (!$vr['ok']) {
            /* the option was renamed or removed after the line went in the bag, or the product
               asks for one now: name what the shopper had picked, if anything */
            $had = implode(" \u{00B7} ", array_filter([$color, $flavor, $size], 'strlen'));   // the order label's separator
            $adjust[] = $had !== '' ? "{$p['name']} ({$had}): this option is no longer available, removed"
                                    : "{$p['name']}: please choose an option on its page, removed";
            $noOption[$p['name']] = true; continue;
        }
        /* two options of one product are two lines sharing its stock: say which one a note is about */
        $nm = $p['name'] . ($vr['label'] !== '' ? ' (' . $vr['label'] . ')' : '');
        /* never sell at $0: the browser refuses "price coming soon" products, but a crafted
           request (or a bag saved before the price was cleared) must not get round that */
        if ($vr['price'] <= 0) { $adjust[] = "{$p['name']}: not available to order yet, removed"; $unpriced[$p['name']] = true; continue; }
        $avail = (int) $p['stock'];
        if ($avail <= 0) { $adjust[] = "{$nm} sold out — removed"; continue; }
        $take = min($reqQty, $avail);
        if ($take < $reqQty) $adjust[] = "{$nm}: only {$take} left — quantity reduced";
        q("UPDATE products SET stock = stock - ? WHERE id = ?", [$take, $p['id']]);  // safe under the row lock
        $unit = $vr['price'];
        $line = round($unit * $take, 2);
        $subtotal += $line;
        $lines[] = ['p' => $p, 'qty' => $take, 'line' => $line, 'unit' => $unit, 'variant' => $vr['label']];
        if (isset($liveGifts[(string) $p['id']]) || isset($giftsSeen[(string) $p['id']])) $paidForGifts[] = ['pid' => (string) $p['id'], 'qty' => $take];
    }
    /* the bag keeps an unpriced line, so a retry would fail the same way forever: name it */
    if (!$lines && $unpriced) {
        $pdo->rollBack();
        $names = array_keys($unpriced); $last = array_pop($names);
        fail('Sorry, ' . ($names ? implode(', ', $names) . ' and ' . $last . ' are' : $last . ' is')
             . ' not available to order yet. Please remove ' . ($names ? 'them' : 'it') . ' from your bag and try again.');
    }
    /* same for a line whose option is missing or was renamed/removed since it was added */
    if (!$lines && $noOption) {
        $pdo->rollBack();
        $names = array_keys($noOption); $last = array_pop($names);
        fail('Please choose an option for ' . ($names ? implode(', ', $names) . ' and ' . $last : $last)
             . ': remove ' . ($names ? 'them' : 'it') . ' from your bag and add ' . ($names ? 'them' : 'it') . ' again from the product page.');
    }
    if (!$lines) { $pdo->rollBack(); fail('Sorry — the items in your bag just sold out. Please try again.'); }
    $subtotal = round($subtotal, 2);

    /* free gifts, worked out AFTER every paid line has taken its stock so a gift that is also
       in the bag (or earned by two products) sees what is really left. A gift that ran out
       only adds a note, it never blocks the order. Gifts never touch the totals below. */
    [$giftLines, $giftNotes] = gifts_for_order($paidForGifts);
    foreach ($giftNotes as $n) $adjust[] = rtrim($n, '.');   // the session note adds its own '.'

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
    $giftsFor = [];   // gift lines keyed by the product that earned them
    foreach ($giftLines as $g) $giftsFor[$g['gift_for']][] = $g;
    foreach ($lines as $l) {
        $p = $l['p'];
        q("INSERT INTO order_items (order_id,product_id,name,variant,brand,price,qty,line_total,is_gift,gift_for,image) VALUES (?,?,?,?,?,?,?,?,0,'','')",
           [$oid, $p['id'], $p['name'], $l['variant'] ?? '', $p['brand'], $l['unit'] ?? $p['price'], $l['qty'], $l['line']]);
        /* the gift goes right under the first line of the product that earned it */
        foreach ($giftsFor[$p['id']] ?? [] as $g) {
            q("INSERT INTO order_items (order_id,product_id,name,variant,brand,price,qty,line_total,is_gift,gift_for,image) VALUES (?,?,?,'Free gift',?,0,?,0,1,?,?)",
               [$oid, $g['product_id'], $g['name'], $g['brand'], $g['qty'], $g['gift_for'], $g['image']]);
        }
        unset($giftsFor[$p['id']]);
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
    $oitems = rows("SELECT * FROM order_items WHERE order_id = ? ORDER BY id", [$oid]);
    send_order_confirmation($order, $oitems);   // email is required at checkout; the mailer still guards for old rows
    send_admin_order_alert($order, $oitems);    // admin hears about guest AND account orders
} catch (Throwable $e) { /* ignore — the order stands */ }

/* the saved bag has become an order */
if ($cid) { try { q("DELETE FROM customer_cart WHERE customer_id = ?", [$cid]); } catch (Throwable $e) {} }

/* remember for the confirmation page (scoped to this visitor's session) */
$_SESSION['last_order'] = $order_no;
/* stock is the usual reason, but an option that went or a free gift that ran out are noted here too */
if ($adjust) $_SESSION['order_note'] = 'Heads up, some items in your bag were adjusted: ' . implode('; ', $adjust) . '.';
else unset($_SESSION['order_note']);
echo json_encode(['ok' => true, 'order_no' => $order_no, 'adjusted' => $adjust]);
