<?php
/* shared <head> — expects optional: $PAGE_TITLE, $ACTIVE, $USE_PLP, $HEAD_CSS */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/customer.php';                       // account pill in the header
if (is_file(__DIR__ . '/chrome.php')) require_once __DIR__ . '/chrome.php';
/* The page already carries no-store from PHP's session cache limiter. Vary tells any
   cache that later learns to key on cookies that this HTML is per-visitor, because the
   server-rendered header now contains the signed-in shopper's first name. */
if (!headers_sent()) header('Vary: Cookie');
$store = setting('store_name', 'WELL SHOP');
$ttl   = $PAGE_TITLE ?? ($store . ' — ' . setting('store_tagline', ''));

/* branding images — all optional, set in admin → Appearance */
$favicon = brand_image('store_favicon');
$share   = brand_image('store_share_image');
$desc    = trim(setting('meta_description', '')) ?: trim(setting('store_tagline', ''));
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($ttl) ?></title>
<?php if ($desc !== ''): ?><meta name="description" content="<?= e($desc) ?>">
<?php endif; ?>
<?php if ($favicon !== ''): ?>
<link rel="icon" type="<?= e(favicon_type($favicon)) ?>" href="<?= e(asset($favicon)) ?>">
<link rel="apple-touch-icon" href="<?= e(asset($favicon)) ?>">
<?php endif; ?>
<?php /* link previews — WhatsApp, Facebook, iMessage, Twitter all read these */ ?>
<meta property="og:site_name" content="<?= e($store) ?>">
<meta property="og:title" content="<?= e($ttl) ?>">
<meta property="og:type" content="website">
<?php if ($desc !== ''): ?><meta property="og:description" content="<?= e($desc) ?>">
<?php endif; ?>
<?php if ($share !== ''): ?>
<meta property="og:image" content="<?= e(abs_url($share)) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="<?= e(abs_url($share)) ?>">
<?php else: ?>
<meta name="twitter:card" content="summary">
<?php endif; ?>
<?php /* The Google Fonts stylesheet is render-blocking and on a third-party origin,
         so the browser pays DNS + TLS before it can even ask for it. Preconnect
         opens those connections in parallel with the rest of the head. */ ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= asset('assets/well.css') ?>">
<?php if (!empty($USE_PLP)): ?><link rel="stylesheet" href="<?= asset('assets/plp.css') ?>"><?php endif; ?>
<?php /* Start the two big blocking scripts downloading during parse instead of
         at end-of-body. data.php alone is ~900KB and blocks the header. This only
         warms the cache; execution order in foot.php is untouched. */ ?>
<link rel="preload" as="script" href="<?= e(asset('assets/data.php')) ?>">
<link rel="preload" as="script" href="<?= e(asset('assets/chrome.js')) ?>">
<?php if (!empty($USE_PLP)): ?><link rel="preload" as="script" href="<?= e(asset('assets/plp.js')) ?>"><?php endif; ?>
<?php render_theme(); ?>
<?= $HEAD_CSS ?? '' ?>
</head>
<body>
<div id="chrome-top"><?php if (function_exists('well_chrome_top')) echo well_chrome_top($ACTIVE ?? 'Shop All'); ?></div>
