<?php
/* ONE-TIME additive migration: orders.admin_notes + coupons.is_public.
   Only ADDs columns — no data is touched. Safe to re-run. Self-deletes. */
require __DIR__ . '/inc/functions.php';

if (($_GET['key'] ?? '') !== 'wellsetup2-2026') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = db();
    if (!$pdo->query("SHOW COLUMNS FROM orders LIKE 'admin_notes'")->fetch()) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN admin_notes TEXT NULL AFTER notes");
        echo "orders.admin_notes added\n";
    } else { echo "orders.admin_notes already present\n"; }

    if (!$pdo->query("SHOW COLUMNS FROM coupons LIKE 'is_public'")->fetch()) {
        $pdo->exec("ALTER TABLE coupons ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 1 AFTER code");
        echo "coupons.is_public added (existing coupons default to public)\n";
    } else { echo "coupons.is_public already present\n"; }

    echo "products: " . (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn() . "\n";
    echo "orders:   " . (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() . "\n";
    @unlink(__FILE__);
    echo "OK — done, setup2.php removed.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "FAILED: " . $e->getMessage() . "\n";
}
