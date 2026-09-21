<?php
/* shared <head> — expects optional: $PAGE_TITLE, $ACTIVE, $USE_PLP, $HEAD_CSS */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/theme.php';
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
<link rel="stylesheet" href="<?= asset('assets/well.css') ?>">
<?php if (!empty($USE_PLP)): ?><link rel="stylesheet" href="<?= asset('assets/plp.css') ?>"><?php endif; ?>
<?php render_theme(); ?>
<?= $HEAD_CSS ?? '' ?>
</head>
<body>
<div id="chrome-top"></div>
