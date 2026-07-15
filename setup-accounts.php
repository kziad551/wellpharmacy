<?php
/* ============================================================
   ONE-TIME, ADDITIVE migration for the LIVE database.
   Creates the customer-account tables and adds orders.customer_id.
   It only ADDS — no DROP, no DELETE, no data is touched. Safe to re-run.
   Self-deletes on success.
   ============================================================ */
require __DIR__ . '/inc/functions.php';

$KEY = 'wellsetup-2026-accounts';
if (($_GET['key'] ?? '') !== $KEY) { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = db();

    $sql   = file_get_contents(__DIR__ . '/db/accounts.sql');
    $clean = implode("\n", array_filter(
        array_map('rtrim', explode("\n", $sql)),
        fn($l) => !str_starts_with(ltrim($l), '--')
    ));
    foreach (array_filter(array_map('trim', explode(';', $clean))) as $stmt) {
        if ($stmt !== '') $pdo->exec($stmt);
    }
    echo "tables ready: customers, customer_wishlist, customer_cart\n";

    if (!$pdo->query("SHOW COLUMNS FROM orders LIKE 'customer_id'")->fetch()) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN customer_id INT NULL AFTER id, ADD KEY k_orders_customer (customer_id)");
        echo "orders.customer_id added\n";
    } else {
        echo "orders.customer_id already present\n";
    }

    /* prove it worked rather than just claiming it */
    foreach (['customers', 'customer_wishlist', 'customer_cart'] as $t) {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "  $t OK ($n rows)\n";
    }
    echo "products still present: " . (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn() . "\n";
    echo "orders still present:   " . (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() . "\n";

    @unlink(__FILE__);
    echo "\nOK — migration complete, setup-accounts.php removed.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "FAILED: " . $e->getMessage() . "\n";
}
