<?php
/* newsletter subscribe endpoint (JSON) */
require __DIR__ . '/../inc/functions.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_post()) { echo json_encode(['ok' => false]); exit; }
$token = $_POST['csrf'] ?? '';
if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
    http_response_code(419); echo json_encode(['ok' => false, 'err' => 'token']); exit;
}
$email = trim((string) input('email'));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok' => false, 'err' => 'email']); exit; }

try { q("INSERT IGNORE INTO subscribers (email, source) VALUES (?, 'popup')", [$email]); }
catch (Throwable $e) { /* duplicate or other — treat as success for UX */ }

echo json_encode(['ok' => true]);
