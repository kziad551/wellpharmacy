<?php
/* ============================================================
   WELL PHARMACY — PDO database layer + tiny query helpers
   ============================================================ */
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/* prepared-statement helpers — ALWAYS pass user data via $args (never string-concat) */
function q(string $sql, array $args = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st;
}
function rows(string $sql, array $args = []): array { return q($sql, $args)->fetchAll(); }
function row(string $sql, array $args = []) { $r = q($sql, $args)->fetch(); return $r === false ? null : $r; }
function val(string $sql, array $args = []) { return q($sql, $args)->fetchColumn(); }
function last_id() { return db()->lastInsertId(); }
