<?php
/* ============================================================
   Admin security helpers: real client IP behind Cloudflare,
   login throttling, and response hardening headers.

   Note on the SQL below: the PDO layer runs with EMULATE_PREPARES off, so a
   placeholder cannot be used inside INTERVAL. The window is a constant defined
   here (never user input), so it is concatenated as an int instead.
   ============================================================ */

const LOGIN_WINDOW   = 900;   // 15 minutes
const LOGIN_MAX_IP   = 8;     // failures from one IP inside the window
const LOGIN_MAX_USER = 12;    // failures against one username inside the window

/* The origin only ever sees Cloudflare's IP in REMOTE_ADDR. Without this every
   visitor shares one address, so a single attacker would lock out everybody.
   CF-Connecting-IP is trusted only when the request really came from Cloudflare. */
function client_ip(): string {
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $cf     = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '');
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP) && cf_edge_ip($remote)) return $cf;
    return $remote !== '' ? $remote : '0.0.0.0';
}

/* Cloudflare's published IPv4 egress ranges. */
function cf_edge_ip(string $ip): bool {
    static $v4 = [
        '173.245.48.0/20','103.21.244.0/22','103.22.200.0/22','103.31.4.0/22',
        '141.101.64.0/18','108.162.192.0/18','190.93.240.0/20','188.114.96.0/20',
        '197.234.240.0/22','198.41.128.0/17','162.158.0.0/15','104.16.0.0/13',
        '104.24.0.0/14','172.64.0.0/13','131.0.72.0/22',
    ];
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }
    $long = ip2long($ip);
    foreach ($v4 as $cidr) {
        $parts = explode('/', $cidr);
        $mask  = -1 << (32 - (int) $parts[1]);
        if ((ip2long($parts[0]) & $mask) === ($long & $mask)) return true;
    }
    return false;
}

function login_attempts_table(): void {
    q("CREATE TABLE IF NOT EXISTS admin_login_attempts (
         id       INT AUTO_INCREMENT PRIMARY KEY,
         ip       VARCHAR(45) NOT NULL,
         username VARCHAR(60) NOT NULL DEFAULT '',
         ok       TINYINT(1)  NOT NULL DEFAULT 0,
         ts       DATETIME    NOT NULL,
         KEY ip_ts (ip, ts),
         KEY user_ts (username, ts)
       ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function login_record(string $user, bool $ok): void {
    q("INSERT INTO admin_login_attempts (ip, username, ok, ts) VALUES (?,?,?,NOW())",
      [client_ip(), substr($user, 0, 60), $ok ? 1 : 0]);
    q("DELETE FROM admin_login_attempts WHERE ts < (NOW() - INTERVAL 1 DAY)");
}

/* Seconds the caller must wait, or 0 when allowed through. */
function login_locked_for(string $user): int {
    $ip   = client_ip();
    $uname = substr($user, 0, 60);
    $w    = (int) LOGIN_WINDOW;

    $ipFails = (int) val(
        "SELECT COUNT(*) FROM admin_login_attempts
          WHERE ip = ? AND ok = 0 AND ts > (NOW() - INTERVAL $w SECOND)", [$ip]);
    $userFails = (int) val(
        "SELECT COUNT(*) FROM admin_login_attempts
          WHERE username = ? AND ok = 0 AND ts > (NOW() - INTERVAL $w SECOND)", [$uname]);

    if ($ipFails < LOGIN_MAX_IP && $userFails < LOGIN_MAX_USER) return 0;

    $last  = (int) val(
        "SELECT UNIX_TIMESTAMP(MAX(ts)) FROM admin_login_attempts
          WHERE ok = 0 AND (ip = ? OR username = ?)", [$ip, $uname]);
    $wait = $w - (time() - $last);
    return $wait > 0 ? $wait : 0;
}

/* Sent on every admin response. */
function admin_security_headers(): void {
    if (headers_sent()) return;
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header_remove('X-Powered-By');
}

/* ---- storefront preview while the coming-soon page is up -------------------
   The value must match the cookie test in .htaccess. Using the token rather
   than a literal "on" matters: a guessable value means anyone can set the
   cookie in their console and walk straight past the holding page. */
const PREVIEW_TOKEN = 'eae76dc95fbbb42b';

function preview_cookie_secure(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (strpos((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''), 'https') !== false);
}

/* Signed-in admins get storefront access automatically, so "View store" in the
   sidebar opens the real site instead of the holding page. */
function grant_preview_cookie(): void {
    if (headers_sent()) return;
    if (($_COOKIE['wellpreview'] ?? '') === PREVIEW_TOKEN) return;
    setcookie('wellpreview', PREVIEW_TOKEN, [
        'expires'  => time() + 2592000,   // 30 days; a session cookie vanished
                                          // on browser restart and broke the page
        'path'     => '/',
        'secure'   => preview_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['wellpreview'] = PREVIEW_TOKEN;
}

function revoke_preview_cookie(): void {
    if (headers_sent()) return;
    setcookie('wellpreview', '', ['expires' => time() - 3600, 'path' => '/']);
    unset($_COOKIE['wellpreview']);
}
