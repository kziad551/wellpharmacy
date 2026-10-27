<?php
/* ============================================================
   WELL PHARMACY admin — shared layout (sidebar + topbar)
   ============================================================ */
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__, 2) . '/inc/theme.php';
require_once __DIR__ . '/list.php';      // infinite-scroll paging helpers

/* gate every admin page that includes this layout (login.php never does) */
require_login();

function aicon(string $n): string {
    $i = [
        'dash'   => '<path d="M3 3h7v7H3zM14 3h7v4h-7zM14 10h7v11h-7zM3 14h7v7H3z"/>',
        'box'    => '<path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/>',
        'grid'   => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'tag'    => '<path d="M20.6 13.4 12 22l-9-9V4a1 1 0 0 1 1-1h8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'cart'   => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>',
        'ticket' => '<path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4z"/>',
        'pen'    => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'brush'  => '<path d="M9.06 11.9 1.5 19.5 4.5 22.5l7.6-7.56M14 7l3 3M7 17a4 4 0 0 1-4 4M20.5 3.5a2.1 2.1 0 0 0-3 0L9 12l3 3 8.5-8.5a2.1 2.1 0 0 0 0-3z"/>',
        'cog'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-2.7-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.1-2.7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>',
        'out'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'eye'    => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
        'plus'   => '<path d="M12 5v14M5 12h14"/>',
        'doc'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        'store'  => '<path d="M3 9 4 4h16l1 5M4 9v11h16V9M9 20v-6h6v6"/>',
        'mail'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'users'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'layout' => '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="14" width="18" height="6" rx="1.5"/>',
        'play'   => '<circle cx="12" cy="12" r="9"/><path d="M10 8.5v7l6-3.5z"/>',
        'bell'   => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'gift'   => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C9.5 3 11 5.5 12 8c1-2.5 2.5-5 4.5-5a2.5 2.5 0 0 1 0 5"/>',
        'spark'  => '<path d="M9.94 15.5a2 2 0 0 0-1.44-1.44l-6.13-1.58a.5.5 0 0 1 0-.96L8.5 9.94A2 2 0 0 0 9.94 8.5l1.58-6.13a.5.5 0 0 1 .96 0l1.58 6.13a2 2 0 0 0 1.44 1.44l6.13 1.58a.5.5 0 0 1 0 .96l-6.13 1.58a2 2 0 0 0-1.44 1.44l-1.58 6.13a.5.5 0 0 1-.96 0z"/><path d="M20 3v4M22 5h-4M4 17v2M5 18H3"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . ($i[$n] ?? '') . '</svg>';
}

/* Free-gift offer state (gift_status() in inc/gifts.php) as [label, pill colour, short
   text], so Products, Free gifts and the product editor always describe an offer the
   same way. The short text is the Products-list pill, which has no "Free gift" heading
   around it and must not rely on colour alone. */
function admin_gift_state(string $st): array {
    return [
        'live'      => ['Live',         'good',  'Gift'],
        'scheduled' => ['Scheduled',    'info',  'Gift scheduled'],
        'ended'     => ['Ended',        'muted', 'Gift ended'],
        'off'       => ['Off',          'muted', 'Gift off'],
        'out'       => ['Out of gifts', 'warn',  'Gift ran out'],
        'missing'   => ['Gift missing', 'bad',   'Gift missing'],
    ][$st] ?? ['Off', 'muted', 'Gift off'];
}

/* Has db/phase4-gifts-popups.sql been run? If the code reaches a server first, the gift
   UI steps aside instead of turning Products and every product page into a blank 500
   (no exception handler). One probe per request. */
function admin_gifts_ready(): bool {
    static $ok = null;
    if ($ok === null) {
        try { val("SELECT 1 FROM product_gifts LIMIT 1"); $ok = true; }
        catch (Throwable $e) { $ok = false; }
    }
    return $ok;
}

function admin_head(string $title, string $current = '', string $subtitle = ''): void {
    require_login();
    $me = current_admin();
    /* unread/new counts are no longer queried here — live.php polls them into the badges */
    $nav = [
        ['Main', [
            ['dashboard', 'Dashboard', 'dash'],
            ['orders',    'Orders',    'cart'],
            ['products',  'Products',  'box'],
        ]],
        ['Catalog', [
            ['categories','Categories','grid'],
            ['brands',    'Brands',    'store'],
            ['coupons',   'Coupons',   'ticket'],
            ['gifts',     'Free gifts','gift'],
            ['journal',   'Journal',   'pen'],
        ]],
        ['Inbox', [
            ['messages',    'Messages', 'mail'],
            ['restock',     'Restock Alerts', 'bell'],
            ['customers',   'Customers', 'users'],
            ['subscribers', 'Subscribers', 'users'],
        ]],
        ['Site', [
            ['home-sections','Home Sections','layout'],
            ['popups',    'Popups',    'spark'],
            ['social',    'Social Videos','play'],
            ['appearance','Appearance','brush'],
            ['pages',     'Content',   'doc'],
            ['settings',  'Settings',  'cog'],
        ]],
    ];
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — WELL Admin</title>
<link rel="stylesheet" href="<?= asset('assets/admin.css') ?>">
</head>
<?php
    /* the server's upload limits, in bytes, for the uploaders' own checks (admin_ini_bytes) */
    $iniBytes = function (string $k): int {
        $v = trim((string) ini_get($k)); $n = (int) $v;
        switch (strtolower(substr($v, -1))) { case 'g': $n *= 1024; case 'm': $n *= 1024; case 'k': $n *= 1024; }
        return $n;
    };
?>
<body data-page="<?= e(basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php')) ?>" data-upmax="<?= $iniBytes('upload_max_filesize') ?>" data-postmax="<?= $iniBytes('post_max_size') ?>" data-maxfiles="<?= (int) ini_get('max_file_uploads') ?>">
<div class="a-shell">
  <aside class="a-side" id="aSide">
    <div class="a-brand">
      <?php $alogo = brand_image('store_logo'); ?>
      <?php if ($alogo !== ''): ?>
        <img class="mark mark-img" src="<?= e(asrc($alogo)) ?>" alt="" onerror="this.remove()">
      <?php else: ?>
        <span class="mark"><?= e(strtoupper(substr(setting('store_name','W'), 0, 1))) ?></span>
      <?php endif; ?>
      <span><b><?= e(setting('store_name','WELL')) ?></b><span>admin panel</span></span>
    </div>
    <nav class="a-nav">
      <?php foreach ($nav as [$grp, $items]): ?>
        <div class="lbl"><?= e($grp) ?></div>
        <?php foreach ($items as [$slug, $label, $ic]): ?>
          <a href="<?= e($slug) ?>" class="<?= $current === $slug ? 'on' : '' ?>"><?= aicon($ic) ?><?= e($label) ?><?php if ($slug === 'orders'): ?><span class="a-badge" data-live="unseen" hidden>0</span><?php endif; ?><?php if ($slug === 'messages'): ?><span class="a-badge" data-live="unread" hidden>0</span><?php endif; ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <div class="lbl">Session</div>
      <a href="../" target="_blank"><?= aicon('eye') ?>View store</a>
      <a href="logout"><?= aicon('out') ?>Sign out</a>
    </nav>
    <div class="foot">© <?= date('Y') ?> WELL PHARMACY</div>
  </aside>
  <div class="a-side-bd" id="aSideBd" hidden></div>
  <main class="a-main">
    <div class="a-top">
      <button class="a-burger" id="aBurger" type="button" aria-label="Open menu" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
      <div class="a-top-title"><h1><?= e($title) ?></h1><?php if ($subtitle): ?><div class="sub"><?= e($subtitle) ?></div><?php endif; ?></div>
      <div class="who"><span class="who-name"><?= e($me['name']) ?></span><span class="av"><?= e(strtoupper(substr($me['name'],0,1))) ?></span></div>
    </div>
    <div class="a-body">
    <?php if ($f = take_flash()): ?>
      <div class="a-toast a-toast-<?= $f['t']==='err'?'err':'ok' ?>" id="aToast" role="status">
        <span class="a-toast-ic"><?= $f['t']==='err' ? '!' : '&#10003;' ?></span>
        <span class="a-toast-msg"><?= e($f['m']) ?></span>
        <button class="a-toast-x" type="button" aria-label="Dismiss">&times;</button>
      </div>
    <?php endif; ?>
<?php
}

function admin_foot(): void {
    ?>
    </div>
  </main>
</div>

<script>
/* Live admin counters — polls a tiny JSON endpoint so new orders / messages / low stock
   appear without a refresh. Polling beats websockets here: shared hosting can't hold
   persistent sockets, and this costs a few COUNT()s every 10s.
   Pauses while the tab is hidden so a parked tab isn't hammering the DB. */
(function () {
  var EVERY = 10000, timer = null, first = true;

  function paint(d) {
    document.querySelectorAll('[data-live]').forEach(function (el) {
      var key = el.dataset.live;
      if (!(key in d)) return;
      var val = d[key];
      /* badges and count-pills disappear at zero; stat numbers always show */
      var hideAtZero = el.classList.contains('a-badge') || el.classList.contains('pill');
      var text = (key === 'revenue') ? '$' + Number(val).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : val;
      var changed = el.textContent !== String(text);
      el.textContent = text;
      if (hideAtZero) el.hidden = !val;
      if (changed && !first) { el.classList.remove('pop'); void el.offsetWidth; el.classList.add('pop'); }
    });
    first = false;
  }

  function tick() {
    fetch('live.php', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (d && d.ok) paint(d); })
      .catch(function () { /* offline / logged out — try again next tick */ });
  }

  function start() { stop(); tick(); timer = setInterval(tick, EVERY); }
  function stop() { if (timer) { clearInterval(timer); timer = null; } }

  document.addEventListener('visibilitychange', function () { document.hidden ? stop() : start(); });
  start();
})();
</script>

<style>
  .rt{border:1px solid var(--a-border2,#e6e1d6);border-radius:10px;overflow:hidden;background:#fff}
  .rt-bar{display:flex;gap:2px;padding:6px;border-bottom:1px solid var(--a-border2,#e6e1d6);background:#faf9f6;flex-wrap:wrap}
  .rt-b{border:0;background:transparent;padding:6px 9px;border-radius:6px;cursor:pointer;font-size:13.5px;line-height:1;color:#3a352d;min-width:30px}
  .rt-b:hover{background:#ece7dc}
  .rt-b.sep{pointer-events:none;color:#cbc4b6;padding:6px 2px}
  .rt-area{min-height:200px;max-height:520px;overflow:auto;padding:14px 16px;outline:none;font-size:14px;line-height:1.65;color:#2c261f}
  .rt-area:empty:before{content:attr(data-ph);color:#a79f90}
  .rt-area h3{font-size:18px;font-weight:700;margin:16px 0 8px}
  .rt-area p{margin:0 0 10px}
  .rt-area ul,.rt-area ol{margin:8px 0 10px 22px}
  .rt-area a{color:#7a6244;text-decoration:underline}
  .imgwrap{position:relative;display:inline-block;margin-bottom:8px;line-height:0}
  .imgwrap .imgtrash{position:absolute;top:6px;right:6px;width:30px;height:30px;border:0;border-radius:8px;background:rgba(176,74,47,.92);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.28);opacity:.85;transition:opacity .15s}
  .imgwrap:hover .imgtrash{opacity:1}
  .imgwrap .imgtrash:hover{background:#b04a2f;opacity:1}
  .imgwrap .imgtrash svg{width:16px;height:16px}
  .a-toast{position:fixed;right:20px;bottom:20px;z-index:300;display:flex;align-items:center;gap:11px;min-width:260px;max-width:400px;padding:13px 14px;border-radius:12px;background:#fff;box-shadow:0 14px 38px rgba(44,38,31,.24);border:1px solid #e6e1d6;font-size:14px;font-weight:600;color:#2c261f}
  .a-toast:not(.hide){animation:aToastIn .38s cubic-bezier(.2,.85,.25,1)}
  .a-toast.hide{animation:aToastOut .28s ease forwards}
  .a-toast-ic{flex:none;width:24px;height:24px;border-radius:50%;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700}
  .a-toast-ok .a-toast-ic{background:#4a7a3a}
  .a-toast-err{border-color:#e7c4bb}
  .a-toast-err .a-toast-ic{background:#b04a2f}
  .a-toast-msg{flex:1;line-height:1.4}
  .a-toast-x{flex:none;border:0;background:transparent;color:#a99f90;font-size:20px;line-height:1;cursor:pointer;padding:0 2px;opacity:0;transition:opacity .15s}
  .a-toast:hover .a-toast-x{opacity:1}
  /* ---- ⓘ field descriptions (shown on hover / focus / tap) ---- */
  .a-info{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;margin-left:5px;padding:0;border-radius:50%;color:#a39783;
    cursor:help;vertical-align:-4px;flex:none;user-select:none;transition:color .15s}
  .a-info svg{width:16px;height:16px;display:block}
  .a-info:hover,.a-info:focus,.a-info.open{color:#7a6244;outline:none}
  .a-info:focus-visible{box-shadow:0 0 0 2px rgba(122,98,68,.35)}
  .a-card > .hd.has-info{display:flex;align-items:center;gap:8px}
  .a-card > .hd.has-info > .a-info{margin-left:auto}
  .a-tip{position:fixed;z-index:500;max-width:min(320px,calc(100vw - 16px));padding:10px 12px;border-radius:10px;background:#2c261f;color:#f4f0e7;
    font-size:12.5px;line-height:1.55;box-shadow:0 12px 30px rgba(44,38,31,.28);pointer-events:none}
  .a-tip b{color:#fff} .a-tip hr{border:0;border-top:1px solid rgba(244,240,231,.18);margin:8px 0}
  /* ---- admin-nav.js: back pill, breadcrumb, previous/next, "you were here" highlight ---- */
  .a-nav-pill{position:fixed;left:50%;bottom:18px;transform:translateX(-50%);z-index:250;display:inline-flex;align-items:center;gap:7px;max-width:calc(100vw - 32px);
    height:44px;padding:0 20px;border-radius:999px;background:#2c261f;color:#f4f0e7;font-size:14px;font-weight:600;text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    box-shadow:0 10px 28px rgba(44,38,31,.32);transition:background .15s,transform .15s}
  .a-nav-pill .t{overflow:hidden;text-overflow:ellipsis}
  .a-nav-pill b{font-weight:800}
  .a-nav-pill:hover{background:#7a6244;transform:translateX(-50%) translateY(-2px)}
  body.has-nav-pill .a-body{padding-bottom:84px}
  .a-crumbs{display:flex;flex-wrap:wrap;align-items:center;gap:2px 6px;margin-bottom:4px;font-size:12.5px;line-height:1.4;color:#a99f90}
  .a-crumbs a{color:#7a6244;font-weight:600;text-decoration:none}
  .a-crumbs a:hover{text-decoration:underline}
  .a-crumbs span[aria-current]{color:#8a7d6e;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .a-crumbs .sep{color:#cbc4b6}
  .a-pn{display:inline-flex;align-items:center;gap:4px;padding:3px;border:1px solid #e6e1d6;border-radius:999px;background:#fff}
  .a-pn-b{display:inline-flex;align-items:center;height:32px;padding:0 13px;border-radius:999px;font-size:13px;font-weight:700;color:#3a352d;text-decoration:none;white-space:nowrap}
  .a-pn-b:hover{background:#f1ece3}
  .a-pn-b.off{color:#cbc4b6;pointer-events:none}
  .a-pn-n{font-size:12.5px;color:#8a7d6e;padding:0 4px;white-space:nowrap}
  @keyframes aNavHl{0%,55%{background-color:#f3e3c3}100%{background-color:transparent}}
  tr.a-nav-hl>td,.a-nav-hl:not(tr){animation:aNavHl 3.6s ease}
  .a-nav-saved{display:inline-flex;align-items:center;margin-left:8px;padding:2px 9px;border-radius:999px;background:#4a7a3a;color:#fff;font-size:11.5px;font-weight:700;vertical-align:middle;transition:opacity .4s}
  .a-nav-saved.out{opacity:0}
  body.has-nav-pill .a-toast{bottom:76px}
  @media(max-width:640px){ .a-nav-pill{bottom:12px;height:42px;font-size:13.5px} .a-pn-b{padding:0 10px} }
  @keyframes aToastIn{from{opacity:0;transform:translateY(20px) scale(.97)}to{opacity:1;transform:none}}
  @keyframes aToastOut{to{opacity:0;transform:translateY(12px)}}
</style>
<script>
/* ---- photo shrinking, before upload ----
   The live server takes 2 MB a photo and 8 MB a save; a phone photo is 3-5 MB, so photos
   were refused and a few at once lost the whole save. Every admin uploader now passes its
   photos through here: anything over ~1.2 MB or 2000px is redrawn at 2000px max, JPEG 86%
   (PNG -> WebP so see-through backgrounds stay see-through) — about 300-600 KB, the same
   size the server would have shrunk it to anyway. GIFs and small photos pass untouched. */
window.adminShrink = function (file) {
  var MAX = 2000, LIMIT = 1.2 * 1048576;
  if (!file || !/^image\/(jpeg|png|webp|avif)$/i.test(file.type) || !window.createImageBitmap) return Promise.resolve(file);
  return createImageBitmap(file).then(function (bmp) {
    var big = Math.max(bmp.width, bmp.height);
    if (file.size <= LIMIT && big <= MAX) { if (bmp.close) bmp.close(); return file; }
    var s = Math.min(1, MAX / big), w = Math.round(bmp.width * s), h = Math.round(bmp.height * s);
    var cv = document.createElement('canvas'); cv.width = w; cv.height = h;
    var cx = cv.getContext('2d');
    var png = /png/i.test(file.type), type = png ? 'image/webp' : 'image/jpeg';
    if (!png) { cx.fillStyle = '#fff'; cx.fillRect(0, 0, w, h); }   // a JPEG has no transparency
    cx.drawImage(bmp, 0, 0, w, h); if (bmp.close) bmp.close();
    return new Promise(function (res) { cv.toBlob(res, type, 0.86); }).then(function (blob) {
      if (!blob || blob.size >= file.size) return file;            // no gain: keep the original
      var ext = blob.type === 'image/webp' ? 'webp' : blob.type === 'image/png' ? 'png' : 'jpg';
      return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.' + ext, { type: blob.type, lastModified: Date.now() });
    });
  }).catch(function () { return file; });
};
/* what the server accepts, from <body data-…> (bytes) */
window.adminUploadLimits = function () {
  var b = document.body.dataset;
  return { file: +b.upmax || 0, post: +b.postmax || 0, files: +b.maxfiles || 20 };
};
(function(){
  // ---- flash toast: pops up bottom-right, auto-dismisses after 5s, hover reveals the ✕ ----
  (function(){
    var toast=document.getElementById('aToast'); if(!toast) return;
    var x=toast.querySelector('.a-toast-x'), t;
    function dismiss(){ toast.classList.add('hide'); setTimeout(function(){ if(toast&&toast.parentNode) toast.remove(); },280); }
    function arm(ms){ clearTimeout(t); t=setTimeout(dismiss,ms); }
    if(x) x.addEventListener('click',function(){ clearTimeout(t); dismiss(); });
    toast.addEventListener('mouseenter',function(){ clearTimeout(t); });
    toast.addEventListener('mouseleave',function(){ arm(2500); });
    arm(5000);
  })();

  // ---- visual (rich-text) editor: turns textarea[data-rich] into a formatting box ----
  document.querySelectorAll('textarea[data-rich]').forEach(function(ta){
    try{ document.execCommand('styleWithCSS',false,false); }catch(e){}
    try{ document.execCommand('defaultParagraphSeparator',false,'p'); }catch(e){}
    var wrap=document.createElement('div'); wrap.className='rt';
    var bar=document.createElement('div'); bar.className='rt-bar';
    var area=document.createElement('div'); area.className='rt-area'; area.contentEditable='true';
    area.setAttribute('data-ph','Start writing…'); area.innerHTML=ta.value||'';
    function sync(){ var h=area.innerHTML.replace(/<br>(\s*<\/(h3|p|li|ul|ol)>)/gi,'$1'); ta.value=(h==='<br>'||h==='<p><br></p>'||h==='<div><br></div>')?'':h; }
    var tools=[
      ['<b>B</b>','Bold',              function(){ document.execCommand('bold'); }],
      ['<i>I</i>','Italic',            function(){ document.execCommand('italic'); }],
      ['sep'],
      ['H','Turn line into a heading', function(){ document.execCommand('formatBlock',false,'<h3>'); }],
      ['&para;','Turn line into normal text', function(){ document.execCommand('formatBlock',false,'<p>'); }],
      ['sep'],
      ['&bull; List','Bulleted list',  function(){ document.execCommand('insertUnorderedList'); }],
      ['1. List','Numbered list',      function(){ document.execCommand('insertOrderedList'); }],
      ['sep'],
      ['&#128279; Link','Add a link (select text first)', function(){ var u=prompt('Link URL:','https://'); if(u) document.execCommand('createLink',false,u); }],
      ['&#10005; Clear','Reset the selected text to plain', function(){ document.execCommand('removeFormat'); document.execCommand('unlink'); document.execCommand('formatBlock',false,'<p>'); }]
    ];
    tools.forEach(function(t){
      var b=document.createElement('button'); b.type='button';
      if(t[0]==='sep'){ b.className='rt-b sep'; b.textContent='|'; bar.appendChild(b); return; }
      b.className='rt-b'; b.title=t[1]; b.innerHTML=t[0];
      // mousedown+preventDefault keeps the text selection so formatting always applies
      b.addEventListener('mousedown',function(ev){ ev.preventDefault(); });
      b.addEventListener('click',function(ev){ ev.preventDefault(); area.focus(); t[2](); sync(); });
      bar.appendChild(b);
    });
    area.addEventListener('input',sync); area.addEventListener('blur',sync);
    ta.style.display='none'; ta.parentNode.insertBefore(wrap,ta); wrap.appendChild(bar); wrap.appendChild(area);
    var form=ta.closest('form'); if(form) form.addEventListener('submit',sync);
    sync();
  });

  // ---- image uploads: clean picker + live preview + hover-to-remove (trash) + Undo ----
  document.querySelectorAll('input[type="file"][data-maxmb]').forEach(function(inp){
    var maxMB=parseFloat(inp.getAttribute('data-maxmb'))||10;
    var box=inp.closest('.field')||inp.parentNode;
    var urlField=box.querySelector('input.input');          // the "https://…" text field for this image
    var preview=box.querySelector('img');
    var origSrc=preview?preview.getAttribute('src'):null;    // saved image, to revert to
    var origUrl=urlField?urlField.value:'';
    var objUrl=null;

    inp.style.display='none';
    var row=document.createElement('div'); row.style.marginTop='10px';
    var pick=document.createElement('button'); pick.type='button'; pick.className='btn btn-ghost btn-sm';
    var undoBtn=document.createElement('button'); undoBtn.type='button'; undoBtn.className='btn btn-ghost btn-sm'; undoBtn.textContent='✕ Undo'; undoBtn.style.cssText='margin-left:8px;display:none';
    row.appendChild(pick); row.appendChild(undoBtn);
    var status=document.createElement('div'); status.style.cssText='margin-top:6px;font-size:12.5px;line-height:1.5';
    inp.parentNode.insertBefore(row,inp); inp.parentNode.insertBefore(status,inp);

    function decorate(img){                                  // wrap an <img> and overlay a hover trash button
      if(!img || (img.parentNode && img.parentNode.className==='imgwrap')) return;
      img.style.cssText='max-width:220px;max-height:160px;width:auto;height:auto;border-radius:10px;border:1px solid #e5e0d6;display:block';
      var w=document.createElement('div'); w.className='imgwrap';
      img.parentNode.insertBefore(w,img); w.appendChild(img);
      var tr=document.createElement('button'); tr.type='button'; tr.className='imgtrash'; tr.title='Remove image';
      tr.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"/></svg>';
      tr.addEventListener('click',function(ev){ ev.preventDefault(); removeImage(); });
      w.appendChild(tr);
    }
    function ensurePreview(){
      if(preview) return preview;
      preview=document.createElement('img');
      var anchor=(box.firstChild&&box.firstChild.tagName==='LABEL')?box.firstChild.nextSibling:box.firstChild;
      box.insertBefore(preview,anchor);
      decorate(preview);
      return preview;
    }
    function dropPreview(){ if(preview){ var w=preview.parentNode; if(w&&w.className==='imgwrap') w.remove(); else preview.remove(); preview=null; } }
    function idle(){ pick.textContent=origSrc?'Replace image':'Choose image'; undoBtn.style.display='none'; status.style.color='#8a7d6e'; status.textContent=origSrc?'Saved image — click the 🗑 to remove it, or pick a file to replace it.':'No image chosen yet.'; }

    function removeImage(){                                  // same effect as clearing the URL + Save
      inp.value=''; if(urlField) urlField.value='';
      if(objUrl){ URL.revokeObjectURL(objUrl); objUrl=null; }
      dropPreview();
      pick.textContent='Choose image'; undoBtn.style.display='';
      status.style.color='#b04a2f'; status.textContent='✕ Image will be removed when you click Save.';
    }
    function revert(){                                       // restore the originally-saved image
      inp.value=''; if(urlField) urlField.value=origUrl;
      if(objUrl){ URL.revokeObjectURL(objUrl); objUrl=null; }
      dropPreview();
      if(origSrc){ ensurePreview().src=origSrc; }
      idle();
    }

    pick.addEventListener('click',function(){ inp.click(); });
    undoBtn.addEventListener('click',function(ev){ ev.preventDefault(); revert(); });
    if(preview) decorate(preview);
    idle();

    inp.addEventListener('change',function(){
      var f0=inp.files&&inp.files[0]; if(!f0) return;
      if(inp._shrunk===f0){ inp._shrunk=null; } else if(/\.(jpe?g|png|webp|avif)$/i.test(f0.name)){
        /* shrink first (see adminShrink), put the smaller file in the input, then run this again */
        status.style.color='#8a7d6e'; status.textContent='Preparing photo…';
        window.adminShrink(f0).then(function(f1){
          if(f1!==f0){ var dt=new DataTransfer(); dt.items.add(f1); inp.files=dt.files; }
          inp._shrunk=inp.files[0]; inp.dispatchEvent(new Event('change'));
        });
        return;
      }
      var f=inp.files[0];
      var mb=f.size/1048576, typeOk=/\.(jpe?g|png|webp|gif|avif)$/i.test(f.name);
      var lim=window.adminUploadLimits();
      if(lim.file && f.size>lim.file){ status.style.color='#b04a2f'; status.textContent='✕ Even after shrinking, that image is '+mb.toFixed(1)+' MB — the server takes '+(lim.file/1048576).toFixed(0)+' MB at most. Please pick a smaller one.'; undoBtn.style.display='none'; inp.value=''; return; }
      if(!typeOk){ status.style.color='#b04a2f'; status.textContent='✕ Unsupported file type — use JPG, PNG, WebP, GIF or AVIF.'; undoBtn.style.display='none'; inp.value=''; return; }
      if(mb>maxMB){ status.style.color='#b04a2f'; status.textContent='✕ That image is '+mb.toFixed(1)+' MB — the maximum is '+maxMB+' MB. Please pick a smaller one.'; undoBtn.style.display='none'; inp.value=''; return; }
      if(objUrl) URL.revokeObjectURL(objUrl);
      objUrl=URL.createObjectURL(f);
      ensurePreview().src=objUrl;
      pick.textContent='Change image'; undoBtn.style.display='';
      status.style.color='#4a7a3a'; status.textContent='✓ '+f.name+' ('+mb.toFixed(1)+' MB) — preview only. Click Save to keep it.';
    });
  });
})();

/* ---- ⓘ instead of always-on descriptions ----
   Every static field description (.hint) becomes a small ⓘ next to the field's label; hover,
   focus or tap shows it. A card-level description goes to an ⓘ at the right of the card title.
   Kept on screen: hints with an id (live text such as "Sells at $8", counters, limits) and
   hints in a box without any input (they ARE the content, e.g. "Save the brand first"). */
(function(){
  var tip=null, cur=null;
  function hide(){ if(tip){ tip.remove(); tip=null; } if(cur){ cur.classList.remove('open'); cur=null; } }
  function show(ic){
    hide(); cur=ic; ic.classList.add('open');
    tip=document.createElement('div'); tip.className='a-tip'; tip.setAttribute('role','tooltip'); tip.innerHTML=ic._html;
    document.body.appendChild(tip);
    var r=ic.getBoundingClientRect(), w=tip.offsetWidth, h=tip.offsetHeight, vw=document.documentElement.clientWidth;
    var left=Math.max(8, Math.min(r.left+r.width/2-w/2, vw-w-8)), top=r.bottom+8;
    if(top+h>window.innerHeight-8) top=Math.max(8, r.top-h-8);
    tip.style.left=left+'px'; tip.style.top=top+'px';
  }
  function icon(html){
    var s=document.createElement('span'); s.className='a-info'; s.tabIndex=0; s.setAttribute('role','button'); s.setAttribute('aria-label','More information'); s._html=html;
    /* drawn, not a letter: a font's "i" never sits in the middle of its circle */
    s.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>';
    s.addEventListener('mouseenter',function(){ show(s); }); s.addEventListener('mouseleave',hide);
    s.addEventListener('focus',function(){ show(s); }); s.addEventListener('blur',hide);
    /* a tap (phones) toggles it; never toggles a checkbox the ⓘ sits in */
    s.addEventListener('click',function(e){ e.preventDefault(); e.stopPropagation(); if(cur===s) hide(); else show(s); });
    return s;
  }
  window.addEventListener('scroll',hide,{passive:true});
  document.addEventListener('keydown',function(e){ if(e.key==='Escape') hide(); });
  var made=new Map();
  function attach(target,html,atEnd){
    var ic=made.get(target);
    if(ic){ ic._html+='<hr>'+html; return; }
    ic=icon(html); made.set(target,ic);
    target.appendChild(ic);
    if(atEnd) target.classList.add('has-info');
  }
  document.querySelectorAll('.a-main .hint').forEach(function(h){
    if(h.id || !h.textContent.trim()) return;
    var html=h.innerHTML.trim();
    var field=h.closest('.field');
    if(field && field.querySelector('input,select,textarea,button')){
      var label=field.querySelector('label');
      if(label){ attach(label,html,false); h.remove(); return; }
    }
    if(field) return;                                         // a field with no input: the hint is its content
    var card=h.closest('.a-card'), hd=card && card.querySelector(':scope > .hd');
    if(hd && card.querySelector('input,select,textarea,button')){ attach(hd,html,true); h.remove(); }
  });
})();

/* ---- mobile sidebar drawer: burger opens, backdrop / link / Esc closes ---- */
(function(){
  var side=document.getElementById('aSide'), bd=document.getElementById('aSideBd'), bg=document.getElementById('aBurger');
  if(!side||!bd||!bg) return;
  function open(){ side.classList.add('open'); bd.hidden=false; requestAnimationFrame(function(){bd.classList.add('show');}); bg.setAttribute('aria-expanded','true'); document.body.style.overflow='hidden'; }
  function close(){ side.classList.remove('open'); bd.classList.remove('show'); bg.setAttribute('aria-expanded','false'); document.body.style.overflow=''; setTimeout(function(){bd.hidden=true;},200); }
  bg.addEventListener('click', function(){ side.classList.contains('open')?close():open(); });
  bd.addEventListener('click', close);
  side.querySelectorAll('a').forEach(function(a){ a.addEventListener('click', close); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });
})();
</script>
<script src="<?= asset('assets/admin.js') ?>" defer></script>
<script src="<?= asset('assets/admin-nav.js') ?>" defer></script>
</body>
</html>
<?php
}
