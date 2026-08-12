<?php
/* Guarded sync endpoint for cPanel cron.
   Usage:  curl -s "https://…/actions/social-sync?key=<social_sync_key>"
   The key lives in settings and is shown in admin → Social Videos.
   Triggering this only refreshes the social grid — it exposes no data. */
require __DIR__ . '/../inc/functions.php';
require __DIR__ . '/../inc/social-sync.php';

header('Content-Type: text/plain; charset=utf-8');

$key = setting('social_sync_key', '');
$got = (string) ($_GET['key'] ?? '');
if ($key === '' || !hash_equals($key, $got)) {
    http_response_code(403);
    exit("Forbidden\n");
}

/* a sync hits two external APIs and downloads images — give it room */
@set_time_limit(120);
ignore_user_abort(true);

$res = social_sync_all();
echo "instagram: " . $res['instagram']['msg'] . "\n";
echo "tiktok:    " . $res['tiktok']['msg'] . "\n";
echo "at:        " . date('c') . "\n";
