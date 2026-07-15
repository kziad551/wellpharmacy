<?php
require __DIR__ . '/inc/functions.php';
if (($_GET['key'] ?? '') !== 'wellclean-2026') { http_response_code(403); exit('Forbidden'); }
header('Content-Type: text/plain; charset=utf-8');
q("DELETE FROM customers WHERE email = ?", ['livecheck@example.com']);
echo "removed test account. customers now: " . (int) val("SELECT COUNT(*) FROM customers") . "\n";
@unlink(__FILE__);
echo "cleanup-test.php removed.\n";
