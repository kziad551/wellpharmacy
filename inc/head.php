<?php
/* shared <head> — expects optional: $PAGE_TITLE, $ACTIVE, $USE_PLP, $HEAD_CSS */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/theme.php';
$store = setting('store_name', 'WELL SHOP');
$ttl   = $PAGE_TITLE ?? ($store . ' — ' . setting('store_tagline', ''));
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($ttl) ?></title>
<link rel="stylesheet" href="<?= asset('assets/well.css') ?>">
<?php if (!empty($USE_PLP)): ?><link rel="stylesheet" href="<?= asset('assets/plp.css') ?>"><?php endif; ?>
<?php render_theme(); ?>
<?= $HEAD_CSS ?? '' ?>
</head>
<body>
<div id="chrome-top"></div>
