<?php
/* Coupon validation endpoint (JSON) — used for the live checkout preview */
require __DIR__ . '/../inc/functions.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_post()) { echo json_encode(['ok' => false, 'err' => 'Method not allowed.']); exit; }

$token = $_POST['csrf'] ?? '';
if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
    http_response_code(419); echo json_encode(['ok' => false, 'err' => 'Session expired — refresh the page.']); exit;
}

$res = coupon_validate((string) input('code'), (float) input('subtotal'));
if (!$res['ok']) { echo json_encode(['ok' => false, 'err' => $res['err']]); exit; }

echo json_encode([
    'ok'       => true,
    'code'     => $res['code'],
    'discount' => $res['discount'],
    'freeship' => $res['freeship'],
    'type'     => $res['coupon']['type'],
]);
