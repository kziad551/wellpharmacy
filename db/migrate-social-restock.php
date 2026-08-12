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
