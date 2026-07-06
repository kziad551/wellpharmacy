<?php
/* ============================================================
   WELL PHARMACY admin — authentication
   ============================================================ */
require_once dirname(__DIR__, 2) . '/inc/functions.php';

function current_admin(): ?array { return $_SESSION['admin'] ?? null; }

function require_login(): void {
    if (empty($_SESSION['admin'])) redirect('login');
}

function admin_login(string $user, string $pass): bool {
    $u = row("SELECT * FROM admin_users WHERE username = ?", [$user]);
    if ($u && password_verify($pass, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = ['id'=>$u['id'], 'username'=>$u['username'], 'name'=>$u['name'] ?: $u['username'], 'role'=>$u['role']];
        return true;
    }
    return false;
}

function admin_logout(): void { unset($_SESSION['admin']); }

/* one-shot flash messages */
function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'] = ['m'=>$msg, 't'=>$type]; }
function take_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
