<?php
/* ============================================================
   WELL PHARMACY admin — authentication
   ============================================================ */
require_once dirname(__DIR__, 2) . '/inc/functions.php';
require_once dirname(__DIR__, 2) . '/inc/security.php';

/* Ties a session to the exact credential it was issued against. Change the
   password or delete the row and every live session for it stops validating. */
function admin_fingerprint(string $hash): string {
    return substr(hash('sha256', $hash), 0, 32);
}

/* Re-reads admin_users on EVERY request instead of trusting the copy written at
   login. Without this, rotating or deleting an account leaves existing sessions
   fully working, which would make a credential rotation cosmetic. */
function current_admin(): ?array {
    $s = $_SESSION['admin'] ?? null;
    if (!$s || empty($s['id'])) return null;

    $u = row("SELECT id, username, name, role, password_hash FROM admin_users WHERE id = ?", [$s['id']]);
    if (!$u) { unset($_SESSION['admin']); return null; }
    if (!hash_equals((string) ($s['pw'] ?? ''), admin_fingerprint($u['password_hash']))) {
        unset($_SESSION['admin']);
        return null;
    }
    return ['id'=>$u['id'], 'username'=>$u['username'], 'name'=>$u['name'] ?: $u['username'], 'role'=>$u['role']];
}

/* admin lives one level under site root, so site-relative image paths
   (e.g. "uploads/x.jpg") need a "../" prefix; full URLs pass through.
   Kept here (not in layout.php) so the login screen can use it too. */
function asrc(string $v): string {
    return ($v === '' || preg_match('~^(https?:|/|data:)~', $v)) ? $v : '../' . $v;
}

const ADMIN_IDLE_TIMEOUT = 7200;   // sign out after 2 hours of inactivity

function require_login(): void {
    admin_security_headers();
    if (!current_admin()) redirect('login');
    $seen = (int) ($_SESSION['admin_seen'] ?? 0);
    if ($seen && (time() - $seen) > ADMIN_IDLE_TIMEOUT) {
        admin_logout();
        session_regenerate_id(true);
        redirect('login');
    }
    $_SESSION['admin_seen'] = time();
    grant_preview_cookie();   // "View store" should open the real site, not the holding page
}

/* A real bcrypt hash that no password matches. Verifying against it when the
   username does not exist makes an unknown user cost the same as a wrong
   password, so response time no longer reveals which usernames are real. */
const DUMMY_HASH = '$2y$12$9kIXCaHymvDGCPohnJg1H.CG3HPq7n9T/w/TsyCqwVH8gE2pfsGEm';

function admin_login(string $user, string $pass): bool {
    login_attempts_table();
    if (login_locked_for($user) > 0) { login_record($user, false); return false; }

    $u    = row("SELECT * FROM admin_users WHERE username = ?", [$user]);
    $hash = ($u && !empty($u['password_hash'])) ? $u['password_hash'] : DUMMY_HASH;
    $ok   = password_verify($pass, $hash) && $u !== null;

    login_record($user, $ok);
    if (!$ok) return false;

    session_regenerate_id(true);
    $_SESSION['admin'] = ['id'=>$u['id'], 'pw'=>admin_fingerprint($u['password_hash']),
                          'username'=>$u['username'], 'name'=>$u['name'] ?: $u['username'], 'role'=>$u['role']];
    $_SESSION['admin_seen'] = time();
    return true;
}

function admin_logout(): void {
    unset($_SESSION['admin']);
    revoke_preview_cookie();   // signing out drops storefront preview access too
}

/* one-shot flash messages */
function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'] = ['m'=>$msg, 't'=>$type]; }
function take_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
