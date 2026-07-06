<?php
/* ============================================================
   WELL PHARMACY — live theme engine
   Emits the font @imports + a :root override built from the
   `theme` settings, so the client controls colours + fonts from
   the admin WITHOUT ever editing well.css.
   Include inside <head>, AFTER the well.css <link>.
   ============================================================ */
require_once __DIR__ . '/functions.php';

/* curated font registry: name => [source, spec, css-stack] */
function theme_fonts(): array {
    return [
        'Clash Display'      => ['fontshare','clash-display@400,500,600,700', "'Clash Display'"],
        'General Sans'       => ['fontshare','general-sans@400,500,600,700', "'General Sans'"],
        'Cabinet Grotesk'    => ['fontshare','cabinet-grotesk@400,500,700,800', "'Cabinet Grotesk'"],
        'Switzer'            => ['fontshare','switzer@400,500,600,700', "'Switzer'"],
        'Satoshi'            => ['fontshare','satoshi@400,500,700,900', "'Satoshi'"],
        'Playfair Display'   => ['google','Playfair+Display:wght@400;600;700', "'Playfair Display'"],
        'Fraunces'           => ['google','Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700', "'Fraunces'"],
        'Cormorant Garamond' => ['google','Cormorant+Garamond:wght@400;500;600;700', "'Cormorant Garamond'"],
        'Lora'               => ['google','Lora:wght@400;500;600;700', "'Lora'"],
        'Inter'              => ['google','Inter:wght@400;500;600;700', "'Inter'"],
        'Poppins'            => ['google','Poppins:wght@400;500;600;700', "'Poppins'"],
        'Montserrat'         => ['google','Montserrat:wght@400;500;600;700', "'Montserrat'"],
        'DM Sans'            => ['google','DM+Sans:wght@400;500;700', "'DM Sans'"],
        'Manrope'            => ['google','Manrope:wght@400;500;600;700', "'Manrope'"],
        'Jost'               => ['google','Jost:wght@400;500;600;700', "'Jost'"],
        'Sora'               => ['google','Sora:wght@400;500;600;700', "'Sora'"],
    ];
}
function theme_font_import(string $name): string {
    $f = theme_fonts();
    if (!isset($f[$name])) return '';
    [$src, $spec] = $f[$name];
    if ($src === 'fontshare') return "https://api.fontshare.com/v2/css?f[]={$spec}&display=swap";
    return 'https://fonts.googleapis.com/css2?family=' . $spec . '&display=swap';
}
function theme_font_stack(string $name, string $kind): string {
    $f = theme_fonts();
    $stack = $f[$name][2] ?? "'$name'";
    $fallback = $kind === 'display' ? ",'General Sans',sans-serif" : ',system-ui,sans-serif';
    return $stack . $fallback;
}

function render_theme(): void {
    $disp = setting('theme_font_display', 'Clash Display');
    $body = setting('theme_font_body', 'General Sans');

    // load chosen fonts (well.css already loads the Clash/General defaults; duplicates are deduped by the browser)
    $imports = array_filter(array_unique([theme_font_import($disp), theme_font_import($body)]));
    foreach ($imports as $url) {
        echo '<link rel="stylesheet" href="' . e($url) . "\">\n";
    }

    $ink   = setting('theme_ink', '#2C261F');
    $inkS  = setting('theme_ink_soft', '#4B3F35');
    $prim  = setting('theme_primary', '#9C8158');
    $primD = setting('theme_primary_deep', '#7A6244');
    $sec   = setting('theme_secondary', '#9A6E3F');
    $secD  = setting('theme_secondary_deep', '#7E5730');
    $cream = setting('theme_cream', '#EBE8DF');
    $cream2= setting('theme_cream2', '#E2DDD0');
    $star  = setting('theme_star', '#B59A5E');
    $fp    = theme_font_stack($disp, 'display');
    $fs    = theme_font_stack($body, 'body');
    ?>
<style id="wp-theme">
:root{
  --ink:<?= e($ink) ?>; --ink-soft:<?= e($inkS) ?>;
  --rose:<?= e($prim) ?>; --rose-deep:<?= e($primD) ?>;
  --coral:<?= e($sec) ?>; --coral-deep:<?= e($secD) ?>;
  --cream:<?= e($cream) ?>; --cream-2:<?= e($cream2) ?>;
  --star:<?= e($star) ?>;
  --fp:<?= $fp ?>; --fscript:<?= $fp ?>; --fs:<?= $fs ?>;
}
</style>
<?php
}
