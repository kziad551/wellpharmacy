<?php
/* =========================================================================
   ONE-TIME DB importer — full local → live replace.
   Loads wellpharmacy_db_export.sql into the LIVE database, then self-deletes.
   Guarded by ?key=... . Safe to delete this file (and the .sql) after running.
   ========================================================================= */
require __DIR__ . '/inc/config.php';   // defines DB_* (config.prod.php on the live server)

$KEY = 'wellsync-2026-b62ea56';
if (($_GET['key'] ?? '') !== $KEY) { http_response_code(403); exit('Forbidden'); }

header('Content-Type: text/plain; charset=utf-8');

$sqlFile = __DIR__ . '/wellpharmacy_db_export.sql';
if (!is_file($sqlFile)) { http_response_code(500); exit("ERROR: dump file not found next to refresh.php\n"); }

$sql = file_get_contents($sqlFile);
if ($sql === false || trim($sql) === '') { http_response_code(500); exit("ERROR: dump file is empty/unreadable\n"); }

mysqli_report(MYSQLI_REPORT_OFF);
$port = defined('DB_PORT') ? (int) DB_PORT : 3306;
$db = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, $port);
if ($db->connect_errno) { http_response_code(500); exit('ERROR: DB connect failed: ' . $db->connect_error . "\n"); }
$db->set_charset('utf8mb4');

$full = "SET FOREIGN_KEY_CHECKS=0;\n" . $sql . "\nSET FOREIGN_KEY_CHECKS=1;\n";

$importErr = '';
if ($db->multi_query($full)) {
    do {
        if ($res = $db->store_result()) { $res->free(); }
    } while ($db->more_results() && $db->next_result());
}
if ($db->errno) { $importErr = '[' . $db->errno . '] ' . $db->error; }

$count = 0;
if ($r = $db->query('SELECT COUNT(*) c FROM products')) { $count = (int) $r->fetch_object()->c; }

echo $importErr === '' ? "OK — import finished cleanly.\n" : "IMPORT ERROR: $importErr\n";
echo "products now in live DB: $count\n";

/* self-destruct so this can't be run again and the dump doesn't linger publicly */
@unlink($sqlFile);
@unlink(__FILE__);
echo "cleaned up refresh.php + dump on the server.\n";
