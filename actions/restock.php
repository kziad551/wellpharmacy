<?php
/* Back-in-stock alert signup (JSON).
   Stores one pending row per (product, email). Re-subscribing after a previous
   notification re-arms the same row rather than creating a duplicate. */
require __DIR__ . '/../inc/functions.php';
require __DIR__ . '/../inc/customer.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_post()) { echo json_encode(['ok' => false, 'err' => 'Bad request.']); exit; }

$token = $_POST['csrf'] ?? '';
if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'err' => 'Your session expired — refresh the page and try again.']);
    exit;
}

$pid   = trim((string) input('product_id'));
$email = trim((string) input('email'));
$me    = current_customer();

/* a signed-in shopper always uses their own address — you can't sign someone else up */
if ($me) $email = (string) $me['email'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'err' => 'That email address doesn\'t look right.']); exit;
}

$p = row("SELECT id, name, stock FROM products WHERE id = ? AND status='active'", [$pid]);
if (!$p) { echo json_encode(['ok' => false, 'err' => 'Product not found.']); exit; }
if ((int) $p['stock'] > 0) {
    echo json_encode(['ok' => false, 'err' => 'Good news — this is back in stock already. Refresh the page.']); exit;
}

/* light abuse guard: one address can watch at most 50 products */
$pending = (int) val("SELECT COUNT(*) FROM restock_alerts WHERE email = ? AND notified_at IS NULL", [$email]);
if ($pending >= 50) {
    echo json_encode(['ok' => false, 'err' => 'You\'re already watching a lot of products — we\'ll email you as they land.']); exit;
}

try {
    q("INSERT INTO restock_alerts (product_id, email, customer_id, notified_at) VALUES (?,?,?,NULL)
       ON DUPLICATE KEY UPDATE notified_at = NULL, customer_id = VALUES(customer_id), created_at = NOW()",
      [$p['id'], $email, $me ? (int) $me['id'] : null]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'err' => 'Could not save that — please try again.']); exit;
}

echo json_encode(['ok' => true, 'msg' => "You're on the list — we'll email you when it's back."]);
