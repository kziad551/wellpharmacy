<?php
/* ============================================================
   Idempotent migration: customer accounts.
   Run from CLI:  php db/migrate-accounts.php
   Creates the account tables and links orders -> customers.
   Safe to re-run; safe to run on live.
   ============================================================ */
require __DIR__ . '/../inc/functions.php';

$pdo = db();

/* 1) tables from accounts.sql — strip `--` comment LINES first, then split on `;`
   (splitting first would leave each chunk starting with its comment block) */
$sql   = file_get_contents(__DIR__ . '/accounts.sql');
$clean = implode("\n", array_filter(
    array_map('rtrim', explode("\n", $sql)),
    fn($l) => !str_starts_with(ltrim($l), '--')
));
$ran = 0;
foreach (array_filter(array_map('trim', explode(';', $clean))) as $stmt) {
    if ($stmt === '') continue;
    $pdo->exec($stmt);
    $ran++;
}
echo "ran {$ran} table statements\n";

/* 2) orders.customer_id — added only if missing so re-runs are safe */
$has = $pdo->query("SHOW COLUMNS FROM orders LIKE 'customer_id'")->fetch();
if (!$has) {
    $pdo->exec("ALTER TABLE orders ADD COLUMN customer_id INT NULL AFTER id, ADD KEY k_orders_customer (customer_id)");
    echo "orders.customer_id added\n";
} else {
    echo "orders.customer_id already present\n";
}

echo "done.\n";
