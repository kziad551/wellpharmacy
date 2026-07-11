<?php
/* ONE-TIME idempotent migration — adds reviews.reviewer_token on live. DELETE after running. */
require __DIR__ . '/inc/config.php';
if (!hash_equals('d6e83502bbd32bc644ef', (string)($_GET['key'] ?? ''))) { http_response_code(403); exit('forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int) DB_PORT);
if ($db->connect_errno) { http_response_code(500); exit('connect failed: ' . $db->connect_error); }
$db->set_charset('utf8mb4');
$has = $db->query("SHOW COLUMNS FROM reviews LIKE 'reviewer_token'");
if ($has && $has->num_rows === 0) {
    $db->query("ALTER TABLE reviews ADD COLUMN reviewer_token VARCHAR(64) DEFAULT NULL, ADD KEY idx_rev_tok (product_id, reviewer_token)");
    echo $db->errno ? "ALTER failed [{$db->errno}]: {$db->error}\n" : "OK - reviewer_token column added.\n";
} else {
    echo "reviewer_token already exists - nothing to do.\n";
}
echo "Delete migrate.php now.\n";
