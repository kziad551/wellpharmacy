<?php
/* Migration for: social videos section + restock alerts.
   Idempotent — safe to run again, and it is the SAME script we run on the server. */
$root    = $argv[1] ?? 'c:/xampp/htdocs/wellpharmacy';
$siteUrl = $argv[2] ?? 'http://localhost/wellpharmacy';
require $root . '/inc/functions.php';

echo "-- social_posts --\n";
q("CREATE TABLE IF NOT EXISTS social_posts (
     id INT AUTO_INCREMENT PRIMARY KEY,
     platform ENUM('instagram','tiktok') NOT NULL DEFAULT 'instagram',
     url VARCHAR(500) NOT NULL DEFAULT '',
     thumb VARCHAR(500) NOT NULL DEFAULT '',
     caption VARCHAR(200) NOT NULL DEFAULT '',
     sort INT NOT NULL DEFAULT 0,
     enabled TINYINT(1) NOT NULL DEFAULT 1,
     created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$hasCol = fn(string $c) => (bool) val("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'social_posts' AND COLUMN_NAME = ?", [$c]);

/* like-count badge shown on hover (matches the reference design) */
if (!$hasCol('likes')) {
    q("ALTER TABLE social_posts ADD COLUMN likes VARCHAR(16) NOT NULL DEFAULT '' AFTER caption");
    echo "   + column: likes\n";
}
/* auto-sync bookkeeping: which post this is on the platform, and who created the row.
   `source` keeps hand-added rows safe — a sync only ever touches its own rows. */
if (!$hasCol('platform_id')) {
    /* NULL for hand-added rows on purpose: a UNIQUE index permits many NULLs, so the
       key below de-dupes synced posts without ever blocking a second manual row. */
    q("ALTER TABLE social_posts ADD COLUMN platform_id VARCHAR(64) DEFAULT NULL AFTER platform");
    echo "   + column: platform_id\n";
}
if (!$hasCol('source')) {
    q("ALTER TABLE social_posts ADD COLUMN source ENUM('manual','instagram','tiktok') NOT NULL DEFAULT 'manual' AFTER platform_id");
    echo "   + column: source\n";
}
q("UPDATE social_posts SET platform_id = NULL WHERE platform_id = ''");
if (!val("SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='social_posts' AND INDEX_NAME='uniq_platform_post'")) {
    /* drop any duplicate synced rows first so the unique key can be created */
    q("DELETE s1 FROM social_posts s1 JOIN social_posts s2
       WHERE s1.platform_id IS NOT NULL AND s1.platform_id = s2.platform_id
         AND s1.platform = s2.platform AND s1.id > s2.id");
    q("ALTER TABLE social_posts ADD UNIQUE KEY uniq_platform_post (platform, platform_id)");
    echo "   + unique key: (platform, platform_id)\n";
}
echo "   ok (rows: " . val("SELECT COUNT(*) FROM social_posts") . ")\n";

echo "-- restock_alerts --\n";
/* one row per (product, email). Re-subscribing after a notification just re-arms the
   same row (notified_at back to NULL) instead of piling up duplicates. */
q("CREATE TABLE IF NOT EXISTS restock_alerts (
     id INT AUTO_INCREMENT PRIMARY KEY,
     product_id VARCHAR(64) NOT NULL,
     email VARCHAR(160) NOT NULL,
     customer_id INT NULL,
     created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
     notified_at DATETIME NULL,
     UNIQUE KEY uniq_product_email (product_id, email),
     KEY idx_pending (product_id, notified_at)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "   ok (rows: " . val("SELECT COUNT(*) FROM restock_alerts") . ")\n";

echo "-- settings --\n";
$defaults = [
  ['social_sec_enabled', '1',                              'social'],
  ['social_sec_eyebrow', 'follow the glow',                'social'],
  ['social_sec_title',   'as seen on social',              'social'],
  ['social_sec_sub',     'Real routines, real results — straight from our Instagram and TikTok.', 'social'],
  ['social_handle',      '',                                'social'],   // blank = derived from the Instagram URL
  ['social_followers',   '',                                'social'],   // auto-filled by the sync; editable
  /* Instagram auto-sync (Instagram API with Instagram Login). The token is a
     long-lived one (60 days) and the sync refreshes it on every run, so it never
     expires as long as the site is syncing. */
  ['ig_access_token',    '',                                'social'],
  ['ig_sync_enabled',    '0',                               'social'],
  ['ig_last_sync',       '',                                'social'],
  ['ig_last_result',     '',                                'social'],
  ['ig_username',        '',                                'social'],
  ['social_sync_key',    bin2hex(random_bytes(12)),         'social'],   // guards the cron URL
];
foreach ($defaults as [$k, $v, $g]) {
    if (val("SELECT COUNT(*) FROM settings WHERE skey=?", [$k])) { echo "   skip (exists): $k\n"; continue; }
    q("INSERT INTO settings (skey, sval, sgroup) VALUES (?,?,?)", [$k, $v, $g]);
    echo "   add: $k = $v\n";
}

/* site_url — restock/order emails need an ABSOLUTE link back to the product.
   Differs per environment, so it's always (re)set to the value passed in. */
q("INSERT INTO settings (skey, sval, sgroup) VALUES ('site_url', ?, 'store')
   ON DUPLICATE KEY UPDATE sval = VALUES(sval)", [$siteUrl]);
echo "   set: site_url = $siteUrl\n";

echo "\ndone.\n";
