<?php
/* ============================================================
   WELL PHARMACY — shared helpers (escaping, money, settings, CSRF, session)
   ============================================================ */
require_once __DIR__ . '/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* ---- output escaping + formatting ---- */
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n): string { return '$' . number_format((float)$n, 2); }
function slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}
/* cache-busted asset url — versioned by the file's own mtime so any deployed
   change busts caches automatically (no manual ASSET_VER bump needed);
   falls back to ASSET_VER for missing files / external URLs. */
function asset(string $path): string {
    if (preg_match('~^(https?:)?//~', $path)) return $path;   // external URL — leave as-is
    $abs = dirname(__DIR__) . '/' . ltrim($path, '/');
    $v   = is_file($abs) ? filemtime($abs) : ASSET_VER;
    return $path . '?v=' . $v;
}

/* a few inline SVG icons for server-rendered pages (match chrome.js set) */
function svg_icon(string $n): string {
    $p = [
        'whatsapp' => '<path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.8 4.9-1.3A10 10 0 1 0 12 2Zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-2.9.8.8-2.8-.2-.3A8 8 0 1 1 12 20Zm4.4-5.6c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.5.1l-.7.9c-.1.2-.3.2-.5.1a6.5 6.5 0 0 1-3.2-2.8c-.1-.2 0-.4.1-.5l.4-.5c.1-.1.1-.3 0-.5l-.7-1.7c-.2-.4-.4-.4-.5-.4h-.5a1 1 0 0 0-.7.3 2.8 2.8 0 0 0-.9 2.1c0 1.6 1.2 3.2 1.4 3.4.2.2 2.4 3.6 5.8 5 .8.3 1.5.5 2 .4.6-.1 1.4-.6 1.6-1.1.2-.6.2-1 .1-1.1l-.4-.2Z" fill="currentColor"/>',
        'phone'    => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
        'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="m3 7 9 6 9-6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>',
        'chat'     => '<path d="M21 11.5a8.5 8.5 0 0 1-12 7.7L3 21l1.8-6A8.5 8.5 0 1 1 21 11.5Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
    ];
    return '<svg width="22" height="22" viewBox="0 0 24 24">' . ($p[$n] ?? '') . '</svg>';
}

/* ---- settings (key/value, cached once per request) ---- */
function settings_all(): array {
    static $all = null;
    if ($all === null) {
        $all = [];
        foreach (rows("SELECT skey, sval FROM settings") as $r) $all[$r['skey']] = $r['sval'];
    }
    return $all;
}
function setting(string $key, string $default = ''): string {
    $all = settings_all();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}
function set_setting(string $key, string $val, string $group = 'general'): void {
    q("INSERT INTO settings (skey, sval, sgroup) VALUES (?,?,?)
       ON DUPLICATE KEY UPDATE sval = VALUES(sval), sgroup = VALUES(sgroup)", [$key, $val, $group]);
}

/* ---- CSRF ---- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals($_SESSION['csrf'] ?? '', $t)) {
        http_response_code(419);
        exit('Session expired or invalid request token. Please go back and try again.');
    }
}

/* ---- auto-optimize an uploaded image ----
   Only shrinks images LARGER than $maxEdge px on the long side, down to $maxEdge, at
   near-lossless quality. Images already <= $maxEdge are left byte-for-byte untouched, so
   a 1920x1080 stays exactly 1920x1080. No-op if the GD library isn't installed, so uploads
   keep working regardless. */
function optimize_image(string $path, int $maxEdge = 2000, int $jpegQ = 88, int $webpQ = 85): void {
    if (!function_exists('imagecreatetruecolor')) return;          // GD not available — leave the original
    $info = @getimagesize($path);
    if (!$info) return;
    [$w, $h] = $info; $type = $info[2];
    if (max($w, $h) <= $maxEdge) return;                           // already web-sized — keep original quality

    switch ($type) {
        case IMAGETYPE_JPEG: if (!function_exists('imagecreatefromjpeg')) return; $src = @imagecreatefromjpeg($path); break;
        case IMAGETYPE_PNG:  if (!function_exists('imagecreatefrompng'))  return; $src = @imagecreatefrompng($path);  break;
        case IMAGETYPE_WEBP: if (!function_exists('imagecreatefromwebp')) return; $src = @imagecreatefromwebp($path); break;
        case IMAGETYPE_GIF:  if (!function_exists('imagecreatefromgif'))  return; $src = @imagecreatefromgif($path);  break;
        default: return;                                           // AVIF etc. — leave as-is
    }
    if (!$src) return;

    $scale = $maxEdge / max($w, $h);
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) { imagealphablending($dst, false); imagesavealpha($dst, true); }  // keep transparency
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    switch ($type) {
        case IMAGETYPE_JPEG: imagejpeg($dst, $path, $jpegQ); break;
        case IMAGETYPE_PNG:  imagepng($dst, $path, 6);       break;   // zlib level, lossless
        case IMAGETYPE_WEBP: imagewebp($dst, $path, $webpQ); break;
        case IMAGETYPE_GIF:  imagegif($dst, $path);          break;
    }
    imagedestroy($dst);
}

/* ---- image upload (returns relative uploads/ url, or null).
       On rejection, $err is set to a human-readable reason so the caller can show it. ---- */
function save_upload(string $field, ?string &$err = null): ?string {
    $err = null;
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;  // nothing chosen — not an error
    $f = $_FILES[$field];
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) { $err = 'that image is too large for the server to accept. Please use one under 10 MB.'; return null; }
    if ($f['error'] !== UPLOAD_ERR_OK) { $err = 'the image could not be uploaded (error ' . (int)$f['error'] . '). Please try again.'; return null; }
    if ($f['size'] > 10 * 1024 * 1024) { $err = 'that image is ' . number_format($f['size'] / 1048576, 1) . ' MB — the maximum is 10 MB. Please resize it or pick a smaller one.'; return null; }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $ok  = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','gif'=>'image/gif','avif'=>'image/avif'];
    $mime = function_exists('mime_content_type') ? mime_content_type($f['tmp_name']) : ($ok[$ext] ?? '');
    if (!isset($ok[$ext]) || !in_array($mime, $ok, true)) { $err = 'that file type is not supported. Please upload a JPG, PNG, WebP, GIF or AVIF image.'; return null; }
    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $name)) { $err = 'the server could not save the image. Please try again.'; return null; }
    optimize_image(UPLOAD_DIR . '/' . $name);   // shrink only if huge; keeps quality
    return UPLOAD_URL . '/' . $name;
}

/* ---- commerce: governorates, shipping, coupons, order numbers ---- */
function lebanon_governorates(): array {
    return ['Beirut','Mount Lebanon','North Lebanon','South Lebanon','Bekaa','Nabatieh','Baalbek-Hermel','Akkar','Keserwan-Jbeil'];
}

/* delivery fee for a governorate given the cart subtotal (0 if free-shipping applies) */
function shipping_fee(string $gov, float $subtotal, bool $freeship = false): float {
    if ($freeship) return 0.0;
    $threshold = (float) setting('free_ship_threshold', '49');
    if ($threshold > 0 && $subtotal >= $threshold) return 0.0;
    $beirut  = (float) setting('ship_fee_beirut', '3');
    $outside = (float) setting('ship_fee_outside', '5');
    return strcasecmp(trim($gov), 'Beirut') === 0 ? $beirut : $outside;
}

/* validate a coupon against a subtotal — used by both the checkout preview and order placement */
function coupon_validate(string $code, float $subtotal): array {
    $code = strtoupper(trim($code));
    if ($code === '') return ['ok'=>false, 'err'=>'Enter a coupon code.'];
    $c = row("SELECT * FROM coupons WHERE code = ? AND active = 1", [$code]);
    if (!$c)                                                    return ['ok'=>false, 'err'=>'Invalid or inactive code.'];
    if ($c['expires_at'] && $c['expires_at'] < date('Y-m-d'))   return ['ok'=>false, 'err'=>'This code has expired.'];
    if ($c['usage_limit'] !== null && (int)$c['used_count'] >= (int)$c['usage_limit']) return ['ok'=>false, 'err'=>'This code is no longer available.'];
    if ($subtotal < (float)$c['min_spend'])                     return ['ok'=>false, 'err'=>'Spend at least ' . money($c['min_spend']) . ' to use this code.'];
    $discount = 0.0; $freeship = false;
    if ($c['type'] === 'percent')      $discount = round($subtotal * ((float)$c['value'] / 100), 2);
    elseif ($c['type'] === 'fixed')    $discount = min((float)$c['value'], $subtotal);
    elseif ($c['type'] === 'freeship') $freeship = true;
    return ['ok'=>true, 'coupon'=>$c, 'code'=>$code, 'discount'=>$discount, 'freeship'=>$freeship];
}

/* unique, human-friendly order number, e.g. WS-2026-A1B2C3 */
function new_order_no(): string {
    for ($i = 0; $i < 6; $i++) {
        $no = 'WS-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        if (!row("SELECT id FROM orders WHERE order_no = ?", [$no])) return $no;
    }
    return 'WS-' . date('Ymd-His');
}

/* ---- misc ---- */
function redirect(string $to): void { header('Location: ' . $to); exit; }
function input(string $key, $default = '') { return $_POST[$key] ?? $_GET[$key] ?? $default; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
