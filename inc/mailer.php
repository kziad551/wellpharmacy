<?php
/* ============================================================
   WELL PHARMACY — outgoing mail.

   Transport is chosen from Settings (admin → Settings → Email):
     smtp_host/port/user/pass/secure  → real SMTP
     nothing configured               → DEV MODE: the message is written to
                                        storage/mail/*.html instead of sent,
                                        so OTP + order mail can be tested with
                                        no mail server. Nothing is silently lost.
   No composer/PHPMailer dependency — plain stream SMTP (AUTH LOGIN, TLS/SSL).
   ============================================================ */
require_once __DIR__ . '/functions.php';

function mail_from_address(): string { return setting('mail_from', setting('store_email', 'no-reply@wellpharmacy.local')); }
function mail_from_name(): string    { return setting('mail_from_name', setting('store_name', 'Well Pharmacy')); }
function admin_notify_email(): string { return setting('admin_notify_email', setting('store_email', '')); }
function smtp_configured(): bool     { return setting('smtp_host') !== '' && setting('smtp_user') !== ''; }

/** Write the message to disk instead of sending (dev / unconfigured). */
function mail_dev_dump(string $to, string $subject, string $html): bool {
    $dir = dirname(__DIR__) . '/storage/mail';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $file = $dir . '/' . date('Ymd-His') . '-' . substr(preg_replace('/[^a-z0-9]+/i', '-', $to), 0, 40) . '.html';
    $meta = "<!-- TO: {$to}\n     SUBJECT: {$subject}\n     AT: " . date('c') . "\n     (dev dump — SMTP not configured) -->\n";
    return (bool) @file_put_contents($file, $meta . $html);
}

/**
 * Send one HTML email. Returns true if handed to SMTP (or dev-dumped).
 * $err is filled with the SMTP failure reason when it returns false.
 */
function send_mail(string $to, string $toName, string $subject, string $html, ?string &$err = null): bool {
    $to = trim($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { $err = 'invalid recipient'; return false; }
    if (!smtp_configured()) { mail_dev_dump($to, $subject, $html); return true; }

    $host   = setting('smtp_host');
    $secure = strtolower(setting('smtp_secure', 'tls'));           // tls | ssl | none
    $port   = (int) setting('smtp_port', $secure === 'ssl' ? '465' : '587');
    $user   = setting('smtp_user');
    $pass   = setting('smtp_pass');
    $from   = mail_from_address();
    $fname  = mail_from_name();

    $dsn = ($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp  = @stream_socket_client($dsn, $eno, $estr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { $err = "connect failed: $estr ($eno)"; return false; }
    stream_set_timeout($fp, 20);

    $read = function () use ($fp) {
        $out = '';
        while (($line = fgets($fp, 515)) !== false) { $out .= $line; if (strlen($line) < 4 || $line[3] === ' ') break; }
        return $out;
    };
    $cmd = function (string $c) use ($fp, $read) { fwrite($fp, $c . "\r\n"); return $read(); };
    $code = fn(string $r) => (int) substr(trim($r), 0, 3);

    if ($code($read()) !== 220) { $err = 'bad greeting'; fclose($fp); return false; }
    $ehloHost = parse_url(setting('site_url', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';
    $cmd("EHLO $ehloHost");

    if ($secure === 'tls') {
        if ($code($cmd('STARTTLS')) !== 220) { $err = 'STARTTLS refused'; fclose($fp); return false; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { $err = 'TLS handshake failed'; fclose($fp); return false; }
        $cmd("EHLO $ehloHost");
    }
    if ($user !== '') {
        if ($code($cmd('AUTH LOGIN')) !== 334)                 { $err = 'AUTH not accepted'; fclose($fp); return false; }
        if ($code($cmd(base64_encode($user))) !== 334)          { $err = 'username rejected'; fclose($fp); return false; }
        if ($code($cmd(base64_encode($pass))) !== 235)          { $err = 'login failed (check user/password)'; fclose($fp); return false; }
    }
    if ($code($cmd("MAIL FROM:<$from>")) !== 250) { $err = 'sender rejected'; fclose($fp); return false; }
    if ($code($cmd("RCPT TO:<$to>"))    !== 250) { $err = 'recipient rejected'; fclose($fp); return false; }
    if ($code($cmd('DATA'))             !== 354) { $err = 'DATA refused'; fclose($fp); return false; }

    $headers = [
        'From: ' . mb_encode_mimeheader($fname) . " <$from>",
        'To: ' . ($toName !== '' ? mb_encode_mimeheader($toName) . " <$to>" : $to),
        'Subject: ' . mb_encode_mimeheader($subject),
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'Date: ' . date('r'),
    ];
    $body = preg_replace('/^\./m', '..', $html);        // dot-stuffing
    fwrite($fp, implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.\r\n");
    if ($code($read()) !== 250) { $err = 'message rejected'; fclose($fp); return false; }
    $cmd('QUIT'); fclose($fp);
    return true;
}

/* ---------- shared shell so every mail looks like the brand ---------- */
function mail_layout(string $heading, string $bodyHtml): string {
    $store = e(setting('store_name', 'Well Pharmacy'));
    return '<div style="background:#EBE8DF;padding:28px;font-family:Helvetica,Arial,sans-serif;color:#2C261F">
      <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:14px;padding:28px">
        <div style="font-size:20px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#5C3A21">' . $store . '</div>
        <h1 style="font-size:22px;margin:18px 0 12px;color:#2C261F">' . e($heading) . '</h1>'
        . $bodyHtml .
      '</div>
      <p style="max-width:560px;margin:14px auto 0;font-size:11px;color:#8A7D6E;text-align:center">Sent by ' . $store . ' · Beirut</p>
    </div>';
}

function send_otp_email(string $to, string $name, string $code): bool {
    $html = mail_layout('Confirm your email', '
        <p style="font-size:14px;line-height:1.6">Hi ' . e($name ?: 'there') . ', use this code to finish setting up your account:</p>
        <div style="font-size:32px;font-weight:700;letter-spacing:8px;background:#F4F1E9;border-radius:10px;padding:16px;text-align:center;margin:18px 0">' . e($code) . '</div>
        <p style="font-size:12px;color:#8A7D6E">This code expires in 10 minutes. If you didn\'t create an account, you can ignore this email.</p>');
    return send_mail($to, $name, 'Your verification code: ' . $code, $html);
}

/** Build the shared order summary table. */
function order_items_table(array $order, array $items): string {
    $rows = '';
    foreach ($items as $it) {
        $rows .= '<tr><td style="padding:8px 0;font-size:13px">' . e($it['name']) . ' <span style="color:#8A7D6E">× ' . (int) $it['qty'] . '</span></td>
                      <td style="padding:8px 0;font-size:13px;text-align:right">' . e(money($it['line_total'])) . '</td></tr>';
    }
    $line = fn($l, $v) => '<tr><td style="padding:3px 0;font-size:13px;color:#8A7D6E">' . $l . '</td><td style="padding:3px 0;font-size:13px;text-align:right">' . e($v) . '</td></tr>';
    return '<table style="width:100%;border-collapse:collapse;margin:14px 0">' . $rows .
        '<tr><td colspan="2" style="border-top:1px solid #E4DFD3;padding-top:8px"></td></tr>'
        . $line('Subtotal', money($order['subtotal']))
        . ((float) $order['discount'] > 0 ? $line('Discount', '-' . money($order['discount'])) : '')
        . $line('Shipping', (float) $order['shipping'] > 0 ? money($order['shipping']) : 'Free')
        . '<tr><td style="padding-top:8px;font-size:15px;font-weight:700">Total</td>
               <td style="padding-top:8px;font-size:15px;font-weight:700;text-align:right">' . e(money($order['total'])) . '</td></tr>
        </table>';
}

function send_order_confirmation(array $order, array $items): bool {
    if (trim((string) $order['email']) === '') return false;      // guest without an email — nothing to send to
    $html = mail_layout('Thanks — your order is confirmed', '
        <p style="font-size:14px;line-height:1.6">Hi ' . e($order['customer_name']) . ', we\'ve got your order
        <b>' . e($order['order_no']) . '</b> and we\'re getting it ready.</p>'
        . order_items_table($order, $items) . '
        <p style="font-size:13px;line-height:1.6"><b>Delivering to</b><br>' . e($order['address']) . '<br>'
        . e(trim($order['city'] . ' ' . $order['governorate'])) . '</p>
        <p style="font-size:13px">Payment: <b>' . ($order['payment_method'] === 'cod' ? 'Cash on Delivery' : 'Card') . '</b></p>
        <p style="font-size:12px;color:#8A7D6E">We\'ll call you to confirm delivery. Keep your order number handy: ' . e($order['order_no']) . '</p>');
    return send_mail($order['email'], $order['customer_name'], 'Order confirmed — ' . $order['order_no'], $html);
}

function send_admin_order_alert(array $order, array $items): bool {
    $to = admin_notify_email();
    if ($to === '') return false;
    $who = (int) ($order['customer_id'] ?? 0) > 0 ? 'Registered customer' : 'Guest checkout';
    $html = mail_layout('New order: ' . $order['order_no'], '
        <p style="font-size:14px"><b>' . $who . '</b></p>
        <p style="font-size:13px;line-height:1.7">
          <b>' . e($order['customer_name']) . '</b><br>
          Phone: ' . e($order['phone']) . '<br>'
          . ($order['email'] ? 'Email: ' . e($order['email']) . '<br>' : '') .
          e($order['address']) . '<br>' . e(trim($order['city'] . ' ' . $order['governorate'])) . '
        </p>'
        . order_items_table($order, $items)
        . ($order['notes'] ? '<p style="font-size:12px"><b>Note:</b> ' . e($order['notes']) . '</p>' : '') . '
        <p style="font-size:12px;color:#8A7D6E">Open the admin → Orders to confirm and pack it.</p>');
    return send_mail($to, 'Well Pharmacy', 'New order ' . $order['order_no'] . ' — ' . money($order['total']), $html);
}
