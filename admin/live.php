<?php
/* ============================================================
   Live counters for the admin chrome (polled every ~10s).
   Deliberately tiny + read-only: a few COUNT()s, no joins.
   Polling (not websockets) because shared hosting has no
   persistent-socket support — same result, nothing to run.
   ============================================================ */
require __DIR__ . '/inc/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!current_admin()) { http_response_code(403); echo json_encode(['ok' => false]); exit; }

/* Orders you haven't looked at yet: anything newer than the last order id that was on
   screen when you opened the Orders page. Opening Orders resets it to 0; an order that
   lands while you're sitting there still shows, until you reload. */
$seen = (int) ($_SESSION['orders_seen_id'] ?? 0);

echo json_encode([
    'ok'         => true,
    'unseen'     => (int) val("SELECT COUNT(*) FROM orders WHERE id > ?", [$seen]),
    'new_orders' => (int) val("SELECT COUNT(*) FROM orders WHERE order_status = 'new'"),
    'orders'     => (int) val("SELECT COUNT(*) FROM orders"),
    'unread'     => (int) val("SELECT COUNT(*) FROM messages WHERE is_read = 0"),
    'low_stock'  => (int) val("SELECT COUNT(*) FROM products WHERE stock <= low_stock AND status = 'active'"),
    'out_stock'  => (int) val("SELECT COUNT(*) FROM products WHERE stock <= 0 AND status = 'active'"),
    'customers'  => (int) val("SELECT COUNT(*) FROM customers"),
    'products'   => (int) val("SELECT COUNT(*) FROM products WHERE status = 'active'"),
    /* must match dashboard.php's definition exactly, or the number would jump on first poll */
    'revenue'    => (float) val("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status='paid' OR payment_method='cod'"),
]);
