<?php
/* ============================================================
   WELL SHOP: seasonal event popups (Halloween, Mother's Day, ...).
   Table `popups` (db/phase4-gifts-popups.sql), managed in admin/popups.php.

   inc/foot.php calls popup_render_page() BEFORE the newsletter popup and
   under the same empty($NO_POPUP) guard. At most one popup per page view:
   the campaign claims window.WELL_POP while the page is still parsing, and
   the newsletter skips any page view the campaign has taken. The first time
   a visitor sees a campaign, the newsletter also sits out the rest of that
   visit (sessionStorage 'well_pop_sess'), so a first visit never gets two
   modals in a row. That happens once per browser (localStorage
   'well_pop_held'): after it, the newsletter only skips the page views a
   campaign takes, so a long 'once per visit' campaign can't shut it out.

   Times are Beirut wall time (config.php sets the zone) and are always
   compared with PHP date() passed as a parameter, never MySQL NOW().
   ============================================================ */
require_once __DIR__ . '/functions.php';

/**
 * Theme presets: key => label, bg, accent, ink (CSS colours), art (one emoji
 * as an HTML entity), particles (emoji entities that fall in the backdrop),
 * and a suggested eyebrow / headline for the admin form.
 * A row's color_bg / color_accent / color_ink override bg / accent / ink.
 * Every accent reaches 4.5:1 on its bg so the eyebrow can use it (Christmas
 * red on green can't, so its eyebrow and focus ring use the ink; see popup_style()).
 * `custom` follows the store theme (Appearance), so it needs no upkeep.
 */
function popup_presets(): array {
    return [
        'halloween'    => ['label' => 'Halloween', 'bg' => '#241627', 'accent' => '#EE7F2D', 'ink' => '#F7E9D7',
                           'art' => '&#x1F383;', 'particles' => ['&#x1F383;', '&#x1F987;', '&#x1F342;'],
                           'eyebrow' => 'Halloween', 'headline' => 'Treats, no tricks'],
        'mothers_day'  => ['label' => "Mother's Day (flowers)", 'bg' => '#F8E8E3', 'accent' => '#567052', 'ink' => '#3B2A27',
                           'art' => '&#x1F490;', 'particles' => ['&#x1F338;', '&#x1F337;', '&#x1F343;'],
                           'eyebrow' => "Mother's Day", 'headline' => 'For the one who does it all'],
        'valentine'    => ['label' => "Valentine's Day", 'bg' => '#FBEAEC', 'accent' => '#A8293F', 'ink' => '#3A1D24',
                           'art' => '&#x1F49D;', 'particles' => ['&#x1F495;', '&#x1F339;', '&#x1F497;'],
                           'eyebrow' => "Valentine's Day", 'headline' => 'Little gifts, big love'],
        'spring'       => ['label' => 'Spring', 'bg' => '#F3F2E4', 'accent' => '#527632', 'ink' => '#2C3324',
                           'art' => '&#x1F337;', 'particles' => ['&#x1F338;', '&#x1F33C;', '&#x1F343;'],
                           'eyebrow' => 'The spring edit', 'headline' => 'Fresh skin season'],
        'easter'       => ['label' => 'Easter', 'bg' => '#F4EFF8', 'accent' => '#6E4F9A', 'ink' => '#2E2637',
                           'art' => '&#x1F423;', 'particles' => ['&#x1F338;', '&#x1F95A;', '&#x1F337;'],
                           'eyebrow' => 'Happy Easter', 'headline' => 'Fresh starts, fresh skin'],
        'ramadan'      => ['label' => 'Ramadan', 'bg' => '#13203A', 'accent' => '#D8AE58', 'ink' => '#F4ECDA',
                           'art' => '&#x1F319;', 'particles' => ['&#x2728;', '&#x2B50;', '&#x1F319;'],
                           'eyebrow' => 'Ramadan Kareem', 'headline' => 'Glow through the holy month'],
        'eid'          => ['label' => 'Eid', 'bg' => '#F6F0E2', 'accent' => '#1D6A55', 'ink' => '#1F2A26',
                           'art' => '&#x1F54C;', 'particles' => ['&#x2728;', '&#x1F319;', '&#x2B50;'],
                           'eyebrow' => 'Eid Mubarak', 'headline' => 'Celebrate with a little extra glow'],
        'fathers_day'  => ['label' => "Father's Day", 'bg' => '#E9EDF0', 'accent' => '#2F4A5E', 'ink' => '#1E2A33',
                           'art' => '&#x1F381;', 'particles' => ['&#x2728;', '&#x1F499;'],
                           'eyebrow' => "Father's Day", 'headline' => 'For the man who has everything'],
        'summer'       => ['label' => 'Summer', 'bg' => '#FDF0DC', 'accent' => '#B0501D', 'ink' => '#3B2618',
                           'art' => '&#x2600;&#xFE0F;', 'particles' => ['&#x1F33A;', '&#x1F41A;', '&#x2600;&#xFE0F;'],
                           'eyebrow' => 'Summer', 'headline' => 'Sun-ready skin'],
        'black_friday' => ['label' => 'Black Friday', 'bg' => '#131211', 'accent' => '#D4AE79', 'ink' => '#F4EEE4',
                           'art' => '&#x1F6CD;&#xFE0F;', 'particles' => ['&#x2728;', '&#x1F3F7;&#xFE0F;'],
                           'eyebrow' => 'Black Friday', 'headline' => 'Our biggest offers of the year'],
        'christmas'    => ['label' => 'Christmas', 'bg' => '#163A2E', 'accent' => '#D0454F', 'ink' => '#F7F0E3',
                           'art' => '&#x1F384;', 'particles' => ['&#x2744;&#xFE0F;', '&#x2744;&#xFE0F;', '&#x2728;'],
                           'eyebrow' => 'Holiday season', 'headline' => 'Wrapped up with care'],
        'new_year'     => ['label' => 'New Year', 'bg' => '#1A1A22', 'accent' => '#E5C677', 'ink' => '#F5F1E8',
                           'art' => '&#x1F942;', 'particles' => ['&#x2728;', '&#x1F38A;', '&#x1F31F;'],
                           'eyebrow' => 'Happy new year', 'headline' => 'New year, new routine'],
        'custom'       => ['label' => 'Store theme (custom)', 'bg' => 'var(--cream,#EBE8DF)', 'accent' => 'var(--rose-deep,#7A6244)', 'ink' => 'var(--ink,#2C261F)',
                           'art' => '&#x2728;', 'particles' => ['&#x2728;'],
                           'eyebrow' => '', 'headline' => ''],
    ];
}

/** One preset, falling back to `custom` for an unknown key. */
function popup_preset(string $key): array {
    $all = popup_presets();
    return $all[$key] ?? $all['custom'];
}

const POPUP_FREQ  = ['once' => 'Once per visitor', 'daily' => 'Once a day', 'visit' => 'Once per visit'];
const POPUP_PAGES = ['all' => 'Every page', 'home' => 'Home page only'];

/** '#RRGGBB' upper-cased, or '' when the value isn't a 6-digit hex colour. */
function popup_hex($v): string {
    $v = trim((string) $v);
    return preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtoupper($v) : '';
}

/** Concrete hex behind a preset colour. Store-theme vars resolve through the
    Appearance settings, so the derived colours below match what render_theme() prints. */
function popup_theme_hex(string $css): string {
    if ($h = popup_hex($css)) return $h;
    if (!preg_match('/^var\(--([a-z-]+),(#[0-9a-fA-F]{6})\)$/', $css, $m)) return '#2C261F';
    $map = ['cream' => 'theme_cream', 'rose-deep' => 'theme_primary_deep', 'ink' => 'theme_ink'];
    $set = isset($map[$m[1]]) ? popup_hex(setting($map[$m[1]], '')) : '';
    return $set !== '' ? $set : strtoupper($m[2]);
}

/** "r,g,b" for rgba(var(--x-rgb), alpha) in the CSS. */
function popup_rgb(string $hex): string {
    return hexdec(substr($hex, 1, 2)) . ',' . hexdec(substr($hex, 3, 2)) . ',' . hexdec(substr($hex, 5, 2));
}

/** WCAG contrast ratio of two hex colours. */
function popup_contrast(string $a, string $b): float {
    $lum = function (string $h): float {
        $c = [];
        foreach ([1, 3, 5] as $i) {
            $v = hexdec(substr($h, $i, 2)) / 255;
            $c[] = $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    };
    $x = $lum($a); $y = $lum($b);
    return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
}

/** Text colour for the CTA: whichever palette colour reads best on the accent,
    else plain white / near-black. Mirrored in admin/popup-edit.php (onColor). */
function popup_on_color(string $ac, string $bg, string $ink): string {
    $best = popup_contrast($ac, $bg) >= popup_contrast($ac, $ink) ? $bg : $ink;
    if (popup_contrast($ac, $best) >= 4.5) return $best;
    return popup_contrast($ac, '#FFFFFF') >= popup_contrast($ac, '#1C1814') ? '#FFFFFF' : '#1C1814';
}

/** CSS custom properties for one popup row (preset + validated overrides). */
function popup_style(array $r): string {
    $p = popup_preset((string) ($r['theme'] ?? 'custom'));
    $css = []; $hex = [];
    foreach (['bg' => 'color_bg', 'accent' => 'color_accent', 'ink' => 'color_ink'] as $k => $col) {
        $o = popup_hex($r[$col] ?? '');
        $css[$k] = $o !== '' ? $o : $p[$k];
        $hex[$k] = $o !== '' ? $o : popup_theme_hex($p[$k]);
    }
    /* the eyebrow is small text (11.5px) in the accent colour: below AA's 4.5 on bg it falls back to the ink.
       The focus ring is a non-text indicator, so it needs 3:1 on bg (WCAG 1.4.11) before it can use the accent. */
    $acBg = popup_contrast($hex['accent'], $hex['bg']);
    return '--cp-bg:' . $css['bg'] . ';--cp-ac:' . $css['accent'] . ';--cp-ink:' . $css['ink']
         . ';--cp-on:' . popup_on_color($hex['accent'], $hex['bg'], $hex['ink'])
         . ';--cp-ey:' . ($acBg >= 4.5 ? $css['accent'] : $css['ink'])
         . ';--cp-focus:' . ($acBg >= 3 ? $css['accent'] : $css['ink'])
         . ';--cp-bg-rgb:' . popup_rgb($hex['bg']) . ';--cp-ac-rgb:' . popup_rgb($hex['accent'])
         . ';--cp-ink-rgb:' . popup_rgb($hex['ink']);
}

/** CTA target rule (checked at save, and again before rendering): a site path
    with no scheme and no leading // (or backslash tricks), or an http(s) URL. */
function popup_cta_ok(string $url): bool {
    if ($url === '' || strlen($url) > 500 || preg_match('/[\s\\\\<>"\x00-\x1F\x7F]/', $url)) return false;
    if (preg_match('~^https?://[^/?#]+~i', $url)) return true;
    if (str_starts_with($url, '//')) return false;
    return !preg_match('~^[a-z][a-z0-9+.-]*:~i', $url);
}

/** Image path rule: uploads/... (or any site path) or an http(s) URL. */
function popup_image_ok(string $v): bool {
    if ($v === '') return true;
    if (strlen($v) > 500 || preg_match('/[\s\\\\<>"\x00-\x1F\x7F]/', $v)) return false;
    if (preg_match('~^https?://[^/?#]+~i', $v)) return true;
    return !str_starts_with($v, '//') && !preg_match('~^[a-z][a-z0-9+.-]*:~i', $v);
}

/** live | scheduled | ended | off, using PHP Beirut time. Ignores the pages scope. */
function popup_status(array $row, ?string $now = null): string {
    $now = $now ?? date('Y-m-d H:i:s');
    if (!(int) $row['active']) return 'off';
    if (!empty($row['starts_at']) && $row['starts_at'] > $now) return 'scheduled';
    if (!empty($row['ends_at'])   && $row['ends_at'] <= $now)  return 'ended';
    return 'live';
}

/** The popup that wins right now for a home (true) or any other (false) page:
    highest priority, then the latest start, then the newest row. */
function popup_pick(bool $home, ?string $now = null): ?array {
    $now = $now ?? date('Y-m-d H:i:s');
    return row("SELECT * FROM popups
                WHERE active = 1
                  AND (starts_at IS NULL OR starts_at <= ?)
                  AND (ends_at IS NULL OR ends_at > ?)" . ($home ? '' : " AND pages = 'all'") . "
                ORDER BY priority DESC, starts_at DESC, id DESC
                LIMIT 1", [$now, $now]);
}

/** Is this request the storefront home page (index.php at the site root)? */
function popup_is_home(): bool {
    $f = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    return $f !== false && $f === realpath(dirname(__DIR__) . '/index.php');
}

/** The live popup for this page view, or null. One query per request. */
function popup_live(): ?array {
    static $done = false, $row = null;
    if ($done) return $row;
    $done = true;
    try {
        $row = popup_pick(popup_is_home());
    } catch (Throwable $e) {
        $row = null;            // table missing (migration not run yet): no popup, no crash
    }
    return $row;
}

function popup_by_id(int $id): ?array {
    return $id > 0 ? row("SELECT * FROM popups WHERE id = ?", [$id]) : null;
}

/** Real admin session check for ?popup_preview (same rules as require_login,
    minus the redirect and the side effects). auth.php is loaded only here. */
function popup_viewer_is_admin(): bool {
    if (empty($_SESSION['admin']['id'])) return false;          // ordinary visitors stop here
    require_once dirname(__DIR__) . '/admin/inc/auth.php';
    if (!current_admin()) return false;
    $seen = (int) ($_SESSION['admin_seen'] ?? 0);
    return !$seen || (time() - $seen) <= ADMIN_IDLE_TIMEOUT;
}

/** Emoji entities to real characters, for JSON / textContent. */
function popup_chars(string $entities): string {
    return html_entity_decode($entities, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * The dialog card. Options:
 *   id      element id prefix (headline gets <id>H)
 *   preview show the "Preview" ribbon
 *   img     image src to print as-is (admin passes asrc()); default: lazy data-src
 *   all     admin live preview: emit every optional part, hidden when empty
 */
function popup_card_html(array $r, array $o = []): string {
    $o += ['id' => 'cPop', 'preview' => false, 'img' => null, 'all' => false];
    $p     = popup_preset((string) ($r['theme'] ?? 'custom'));
    $all   = (bool) $o['all'];
    $image = trim((string) ($r['image'] ?? ''));
    $ey    = trim((string) ($r['eyebrow'] ?? ''));
    $head  = trim((string) ($r['headline'] ?? ''));
    $body  = trim((string) ($r['body'] ?? ''));
    $code  = trim((string) ($r['coupon_code'] ?? ''));
    $label = trim((string) ($r['cta_label'] ?? ''));
    $url   = trim((string) ($r['cta_url'] ?? ''));
    if ($url !== '' && !popup_cta_ok($url)) $url = '';
    if ($image !== '' && !popup_image_ok($image)) $image = '';
    $hid = function (bool $show) { return $show ? '' : ' hidden'; };
    $arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';

    $h  = '<div class="cpop-card" role="dialog" aria-modal="true" aria-labelledby="' . e($o['id']) . 'H"'
        . ($body !== '' ? ' aria-describedby="' . e($o['id']) . 'T"' : '') . ' tabindex="-1">';
    if ($o['preview']) $h .= '<span class="cpop-rib">Preview</span>';
    $h .= '<button class="cpop-x" type="button" data-cp-close aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>';

    $h .= '<div class="cpop-art' . ($image !== '' ? ' cpop-art--img' : '') . '" aria-hidden="true">'
        . '<span class="cpop-glyph">' . $p['art'] . '</span>';
    if ($o['img'] !== null || $all) {                 // admin: plain src, swapped live by the form
        $src = (string) ($o['img'] ?? '');
        $h .= '<img class="cpop-img in"' . ($src !== '' ? ' src="' . e($src) . '"' : '') . ' alt=""' . $hid($src !== '') . '>';
    } elseif ($image !== '') {                         // storefront: fetched only when the popup opens
        $h .= '<img class="cpop-img" data-src="' . e($image) . '" alt="">';
    }
    $h .= '</div>';

    $h .= '<div class="cpop-body">';
    if ($ey !== '' || $all) $h .= '<span class="cpop-ey"' . $hid($ey !== '') . '>' . e($ey) . '</span>';
    $h .= '<h2 class="cpop-h' . (mb_strlen($head) > 55 ? ' cpop-h--long' : '') . '" id="' . e($o['id']) . 'H">' . e($head) . '</h2>';
    if ($body !== '' || $all) $h .= '<p class="cpop-txt" id="' . e($o['id']) . 'T"' . $hid($body !== '') . '>' . nl2br(e($body), false) . '</p>';
    if ($code !== '' || $all) {
        $h .= '<div class="cpop-code"' . $hid($code !== '') . '><span class="cpop-code-k">Code</span>'
            . '<b class="cpop-code-v">' . e($code) . '</b>'
            . '<button class="cpop-copy" type="button" data-cp-copy="' . e($code) . '">Copy</button></div>';
    }
    $h .= '<div class="cpop-acts">';
    if ($url !== '') {
        $host = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        $ext  = preg_match('~^https?://([^/?#:]+)~i', $url, $m) && strcasecmp($m[1], $host) !== 0;   // other sites open in a new tab
        $h .= '<a class="cpop-cta" href="' . e($url) . '" data-cp-cta' . ($ext ? ' target="_blank" rel="noopener"' : '') . '>'
            . '<span>' . e($label !== '' ? $label : 'Shop now') . '</span>' . $arrow . '</a>';
    } elseif ($label !== '' || $all) {
        $h .= '<button class="cpop-cta" type="button" data-cp-cta data-cp-close' . $hid($label !== '') . '><span>' . e($label) . '</span>' . $arrow . '</button>';
    }
    $h .= '<button class="cpop-no" type="button" data-cp-close>No thanks</button></div>';
    $h .= '<span class="cpop-sr" aria-live="polite"></span>';
    $h .= '</div></div>';
    return $h;
}

/**
 * Scoped CSS. $narrowClass also emits the phone rules under `.cpop-narrow`
 * so the admin preview can show the phone layout at any window width.
 * The one-column layout starts at 720px: below that the 288px art column
 * leaves the coupon chip too little room to show the code.
 */
function popup_css(bool $narrowClass = false): string {
    $narrow = '%1$s.cpop-card{grid-template-columns:1fr;max-width:480px;border-radius:24px}'
            . '%1$s.cpop-art{min-height:0;height:132px;box-shadow:inset 0 -1px 0 rgba(var(--cp-ink-rgb),.07)}'
            . '%1$s.cpop-art::before{width:180px;height:180px}'
            . '%1$s.cpop-art::after{width:96px;height:96px;box-shadow:0 0 0 11px rgba(var(--cp-ink-rgb),.035),0 0 0 12px rgba(var(--cp-ink-rgb),.1)}'
            . '%1$s.cpop-glyph{font-size:54px}'
            . '%1$s.cpop-body{padding:24px 22px 18px;align-items:stretch}'
            . '%1$s.cpop-ey{align-self:flex-start}'
            . '%1$s.cpop-h{font-size:27px}%1$s.cpop-h--long{font-size:23px}'
            . '%1$s.cpop-txt{font-size:14.5px;margin-bottom:18px}'
            . '%1$s.cpop-code{max-width:none;margin-bottom:16px}'
            . '%1$s.cpop-acts{flex-direction:column;align-items:stretch;gap:4px}'
            . '%1$s.cpop-cta{width:100%%}'
            . '%1$s.cpop-no{align-self:center}';
    $css = <<<'CSS'
  .cpop{position:fixed;inset:0;z-index:125;display:none;align-items:center;justify-content:center;padding:24px;font-family:var(--fs,inherit)}
  .cpop.open{display:flex}
  .cpop-back{position:absolute;inset:0;background:radial-gradient(60% 55% at 50% 45%,rgba(var(--cp-ac-rgb),.16),transparent 70%),rgba(26,21,17,.6);-webkit-backdrop-filter:blur(4px);backdrop-filter:blur(4px);animation:cpFade .35s ease both}
  .cpop-fx{position:absolute;inset:0;overflow:hidden;pointer-events:none}
  .cpop-p{position:absolute;top:0;font-style:normal;line-height:1;will-change:transform;animation:cpFall 12s linear infinite}
  .cpop-p i{display:block;font-style:normal;animation:cpSway 4s ease-in-out infinite alternate}
  .cpop-card{position:relative;display:grid;grid-template-columns:288px 1fr;width:100%;max-width:780px;max-height:calc(100vh - 32px);max-height:calc(100dvh - 32px);overflow:auto;overscroll-behavior:contain;background:var(--cp-bg);color:var(--cp-ink);border-radius:28px;box-shadow:0 50px 100px -20px rgba(20,15,10,.55),0 0 0 1px rgba(var(--cp-ink-rgb),.06);animation:cpUp .5s cubic-bezier(.2,.8,.2,1) both;outline:none;text-align:left}
  .cpop-card [hidden]{display:none!important}
  .cpop-art{position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center;min-height:300px;background:linear-gradient(165deg,rgba(var(--cp-ac-rgb),.3),rgba(var(--cp-ac-rgb),.07) 72%),var(--cp-bg);box-shadow:inset -1px 0 0 rgba(var(--cp-ink-rgb),.07)}
  .cpop-art::before{content:"";position:absolute;left:50%;top:50%;width:280px;height:280px;transform:translate(-50%,-50%);border-radius:50%;background:radial-gradient(closest-side,rgba(var(--cp-ac-rgb),.55),rgba(var(--cp-ac-rgb),0))}
  .cpop-art::after{content:"";position:absolute;left:50%;top:50%;width:176px;height:176px;transform:translate(-50%,-50%);border-radius:50%;border:1px solid rgba(var(--cp-ink-rgb),.22);box-shadow:0 0 0 18px rgba(var(--cp-ink-rgb),.035),0 0 0 19px rgba(var(--cp-ink-rgb),.12)}
  .cpop-glyph{position:relative;z-index:1;font-size:88px;line-height:1;filter:drop-shadow(0 14px 20px rgba(0,0,0,.28));animation:cpFloat 6s ease-in-out infinite}
  .cpop-img{position:absolute;inset:0;z-index:2;width:100%;height:100%;max-width:none;object-fit:cover;opacity:0;transition:opacity .4s ease}
  .cpop-img.in{opacity:1}
  .cpop-art--img::before,.cpop-art--img::after,.cpop-art--img .cpop-glyph{visibility:hidden}
  .cpop-body{position:relative;display:flex;flex-direction:column;align-items:flex-start;min-width:0;padding:46px 44px 34px}
  .cpop-ey{display:inline-flex;align-items:center;gap:10px;font-size:11.5px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--cp-ey,var(--cp-ac))}
  .cpop-ey::before{content:"";width:22px;height:1.5px;background:currentColor}
  .cpop-h{margin:12px 0;font-family:var(--fp,Georgia,serif);font-size:36px;font-weight:600;line-height:1.06;letter-spacing:-.015em;color:var(--cp-ink);text-wrap:balance;overflow-wrap:anywhere}
  .cpop-h--long{font-size:29px}
  .cpop-txt{margin:0 0 22px;max-width:40ch;font-size:15px;line-height:1.6;color:var(--cp-ink);opacity:.82;overflow-wrap:anywhere}
  .cpop-code{display:flex;align-items:center;gap:12px;width:100%;max-width:340px;margin:0 0 22px;padding:7px 7px 7px 16px;border:1.5px dashed rgba(var(--cp-ac-rgb),.75);border-radius:14px;background:rgba(var(--cp-ink-rgb),.045)}
  .cpop-code-k{font-size:10.5px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;opacity:.74}
  .cpop-code-v{flex:1;min-width:0;font-size:17px;font-weight:700;line-height:1.3;letter-spacing:.14em;overflow-wrap:anywhere;user-select:all;-webkit-user-select:all}
  .cpop-copy{flex:none;height:36px;padding:0 16px;border:0;border-radius:10px;background:var(--cp-ink);color:var(--cp-bg);font:inherit;font-size:12.5px;font-weight:700;letter-spacing:.04em;cursor:pointer;transition:background .2s,color .2s}
  .cpop-copy.is-done{background:var(--cp-ac);color:var(--cp-on)}
  .cpop-acts{display:flex;align-items:center;flex-wrap:wrap;gap:8px 22px}
  .cpop-cta{display:inline-flex;align-items:center;justify-content:center;gap:10px;height:52px;padding:0 30px;border:0;border-radius:999px;background:var(--cp-ac);color:var(--cp-on);font:inherit;font-size:15px;font-weight:600;letter-spacing:.01em;text-decoration:none;cursor:pointer;box-shadow:0 14px 30px -10px rgba(var(--cp-ac-rgb),.7);transition:transform .2s ease,box-shadow .2s ease,filter .2s ease}
  .cpop-cta:hover{transform:translateY(-2px);filter:brightness(1.06);box-shadow:0 18px 36px -10px rgba(var(--cp-ac-rgb),.8)}
  .cpop-cta svg{width:17px;height:17px;flex:none}
  .cpop-no{padding:6px 0;border:0;background:none;color:var(--cp-ink);opacity:.74;font:inherit;font-size:13px;text-decoration:underline;text-underline-offset:3px;cursor:pointer}
  .cpop-no:hover{opacity:.95}
  .cpop-x{position:absolute;top:14px;right:14px;z-index:4;display:flex;align-items:center;justify-content:center;width:38px;height:38px;padding:0;border:0;border-radius:50%;background:rgba(var(--cp-bg-rgb),.72);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);color:var(--cp-ink);cursor:pointer;box-shadow:0 0 0 1px rgba(var(--cp-ink-rgb),.1)}
  .cpop-x:hover{background:rgba(var(--cp-bg-rgb),.95)}
  .cpop-x svg{width:18px;height:18px}
  .cpop-rib{position:absolute;top:16px;left:16px;z-index:4;padding:5px 11px;border-radius:999px;background:rgba(17,14,11,.86);color:#fff;font-size:10.5px;font-weight:700;letter-spacing:.16em;text-transform:uppercase}
  .cpop-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
  .cpop-card :focus-visible{outline:3px solid var(--cp-focus,var(--cp-ac));outline-offset:3px}
  .cpop.closing .cpop-card{animation:cpDown .22s ease forwards}
  .cpop.closing .cpop-back,.cpop.closing .cpop-fx{animation:cpOut .22s ease forwards}
  @keyframes cpFade{from{opacity:0}to{opacity:1}}
  @keyframes cpOut{to{opacity:0}}
  @keyframes cpUp{from{opacity:0;transform:translateY(24px) scale(.97)}to{opacity:1;transform:none}}
  @keyframes cpDown{to{opacity:0;transform:translateY(12px) scale(.985)}}
  @keyframes cpFloat{0%,100%{transform:translateY(-4px) rotate(-3deg)}50%{transform:translateY(6px) rotate(3deg)}}
  @keyframes cpFall{from{transform:translate3d(0,-12vh,0)}to{transform:translate3d(var(--dx,0px),112vh,0)}}
  @keyframes cpSway{from{transform:translateX(-14px) rotate(calc(var(--r,30deg) * -1))}to{transform:translateX(14px) rotate(var(--r,30deg))}}
  @media (prefers-reduced-motion:reduce){.cpop-fx{display:none}.cpop-glyph{animation:none}}
  @media (max-height:520px) and (min-width:721px){.cpop-art{min-height:0}.cpop-body{padding:28px 34px 24px}}
CSS;
    $css .= "\n  @media(max-width:720px){.cpop{padding:14px}" . sprintf($narrow, '') . '}';
    /* landscape phones: a slimmer banner leaves the height to the message */
    $css .= "\n  @media(max-width:720px) and (max-height:520px){.cpop-art{height:76px}.cpop-art::after{display:none}.cpop-glyph{font-size:38px}.cpop-body{padding-top:20px}}";
    if ($narrowClass) $css .= "\n  " . sprintf($narrow, '.cpop-narrow ');
    return $css . "\n";
}

/** Markup + CSS + script for one popup. Rendered by inc/foot.php. */
function popup_render(array $r, bool $preview = false): void {
    $p   = popup_preset((string) $r['theme']);
    $cfg = [
        'id'      => (int) $r['id'],
        'freq'    => array_key_exists($r['frequency'], POPUP_FREQ) ? $r['frequency'] : 'once',
        'delay'   => max(0, min(60, (int) $r['delay_sec'])),
        'day'     => date('Y-m-d'),
        'preview' => $preview,
        'fx'      => (int) $r['effect'] ? array_map('popup_chars', $p['particles']) : [],
    ];
    ?>
<!-- seasonal event popup (admin: Site > Popups) -->
<div class="cpop" id="cPop" aria-hidden="true" style="<?= e(popup_style($r)) ?>">
  <div class="cpop-back" data-cp-close></div>
  <div class="cpop-fx" aria-hidden="true"></div>
  <?= popup_card_html($r, ['preview' => $preview]) ?>

</div>
<style>
<?= popup_css() ?></style>
<script>
(function(){
  var C=<?= json_encode($cfg, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var POP=window.WELL_POP=window.WELL_POP||{taken:null};
  var root=document.getElementById('cPop'); if(!root) return;
  var card=root.querySelector('.cpop-card'), fx=root.querySelector('.cpop-fx'), sr=root.querySelector('.cpop-sr');
  var KEY='well_pop_v1', id=String(C.id), last=null, saved=null;

  function load(name){ try{ var v=JSON.parse(window[name].getItem(KEY)||'{}'); return (v&&typeof v==='object')?v:{}; }catch(e){ return null; } }
  /* once: per campaign, forever. daily: once per Beirut calendar day. visit: once per browser session */
  function seen(){
    var m=load('localStorage'); if(!m) return true;          /* no storage: never nag (same rule as the newsletter) */
    if(C.freq==='visit'){ var s=load('sessionStorage'); return !s || !!s[id]; }
    if(C.freq==='daily') return m[id]===C.day;
    return Object.prototype.hasOwnProperty.call(m,id);
  }
  function markSeen(){
    if(C.preview) return;
    var m=load('localStorage')||{}, s=load('sessionStorage')||{};
    m[id]=C.day; s[id]=1;
    try{ localStorage.setItem(KEY,JSON.stringify(m)); }catch(e){}
    try{ sessionStorage.setItem(KEY,JSON.stringify(s)); }catch(e){}
  }

  /* one popup per page view: claim the slot now, while the page is still parsing */
  if(!C.preview && (POP.taken || seen())) return;
  POP.taken='camp';

  /* wait while someone is typing, or the bag / menu / search / another overlay is open */
  function busy(){
    if(document.hidden) return true;
    var a=document.activeElement;
    if(a && (a.tagName==='INPUT' || a.tagName==='TEXTAREA' || a.tagName==='SELECT' || a.isContentEditable)) return true;
    return !!document.querySelector('#cartDrawer.open,#siteHeader.nav-open,body.sugg-on,.nl-pop.open,#reviewModal:not([hidden]),#socLb.open,#lightbox.open');
  }
  function rain(){
    var n=window.innerWidth<560?10:16;
    for(var i=0;i<n;i++){
      var o=document.createElement('i'), s=document.createElement('i'), d=Math.random(), dur=15-d*6+Math.random()*3;
      o.className='cpop-p';
      o.style.left=(Math.random()*100).toFixed(1)+'%';
      o.style.fontSize=Math.round(14+d*22)+'px';
      o.style.opacity=(0.35+d*0.55).toFixed(2);
      o.style.animationDuration=dur.toFixed(1)+'s';
      o.style.animationDelay=(-Math.random()*dur).toFixed(1)+'s';
      o.style.setProperty('--dx',Math.round((Math.random()-0.5)*160)+'px');
      s.style.animationDuration=(3+Math.random()*3).toFixed(1)+'s';
      s.style.setProperty('--r',Math.round((Math.random()<0.5?-1:1)*(20+Math.random()*50))+'deg');
      s.textContent=C.fx[i%C.fx.length];
      o.appendChild(s); fx.appendChild(o);
    }
  }
  function focusables(){
    return Array.prototype.filter.call(card.querySelectorAll('a[href],button:not([disabled])'), function(el){ return el.offsetWidth || el.offsetHeight; });
  }
  function onKey(e){
    if(e.key==='Escape'){ e.preventDefault(); close(); return; }
    if(e.key!=='Tab') return;
    var f=focusables(); if(!f.length){ e.preventDefault(); return; }
    var a=document.activeElement, first=f[0], end=f[f.length-1];
    if(!card.contains(a) || a===card){ e.preventDefault(); (e.shiftKey?end:first).focus(); }
    else if(e.shiftKey && a===first){ e.preventDefault(); end.focus(); }
    else if(!e.shiftKey && a===end){ e.preventDefault(); first.focus(); }
  }
  function open(){
    if(root.classList.contains('open')) return;
    var img=root.querySelector('img[data-src]');
    if(img){
      img.onload=function(){ img.classList.add('in'); };
      img.onerror=function(){ img.parentNode.classList.remove('cpop-art--img'); img.remove(); };
      img.src=img.getAttribute('data-src'); img.removeAttribute('data-src');
    }
    last=document.activeElement;
    var b=document.body, gap=window.innerWidth-document.documentElement.clientWidth;
    saved={o:b.style.overflow, p:b.style.paddingRight};
    b.style.overflow='hidden';
    if(gap>0) b.style.paddingRight=gap+'px';
    root.classList.add('open'); root.setAttribute('aria-hidden','false');
    if(C.fx.length && !(window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches)) rain();
    markSeen();
    /* the first campaign a browser sees also holds the newsletter (inc/foot.php) back until a later
       visit; only once, or a 'once per visit' campaign would hold it back for as long as it runs */
    if(!C.preview){ try{ if(!localStorage.getItem('well_pop_held')){ sessionStorage.setItem('well_pop_sess','1'); localStorage.setItem('well_pop_held','1'); } }catch(e){} }
    try{ card.focus({preventScroll:true}); }catch(e){ card.focus(); }
    document.addEventListener('keydown',onKey,true);
  }
  function close(){
    if(!root.classList.contains('open') || root.classList.contains('closing')) return;
    markSeen();
    document.removeEventListener('keydown',onKey,true);
    root.classList.add('closing'); root.setAttribute('aria-hidden','true');
    setTimeout(function(){
      root.classList.remove('open','closing'); fx.textContent='';
      if(saved){ document.body.style.overflow=saved.o; document.body.style.paddingRight=saved.p; saved=null; }
    },220);
    if(last && last.focus && document.documentElement.contains(last)){ try{ last.focus({preventScroll:true}); }catch(e){} }
  }

  root.querySelectorAll('[data-cp-close]').forEach(function(b){ b.addEventListener('click',close); });
  var cta=root.querySelector('a[data-cp-cta]');
  cta && cta.addEventListener('click',function(){ markSeen(); if(cta.target==='_blank' || cta.getAttribute('href').charAt(0)==='#') close(); });
  var copy=root.querySelector('[data-cp-copy]');
  copy && copy.addEventListener('click',function(){
    var code=copy.getAttribute('data-cp-copy');
    function done(ok){
      markSeen();
      if(!ok){ var r=document.createRange(); r.selectNodeContents(root.querySelector('.cpop-code-v')); var s=getSelection(); s.removeAllRanges(); s.addRange(r); }
      copy.textContent=ok?'Copied':'Selected'; copy.classList.toggle('is-done',ok);
      sr.textContent=ok?'Code '+code+' copied.':'Code selected, press copy on your keyboard.';
      setTimeout(function(){ copy.textContent='Copy'; copy.classList.remove('is-done'); },2200);
    }
    function legacy(){
      var t=document.createElement('textarea'), ok=false;
      t.value=code; t.setAttribute('readonly',''); t.style.cssText='position:fixed;top:0;left:0;opacity:0';
      card.appendChild(t); t.select();
      try{ ok=document.execCommand('copy'); }catch(e){}
      t.remove(); copy.focus(); return ok;
    }
    if(navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(code).then(function(){ done(true); },function(){ done(legacy()); });
    else done(legacy());
  });

  (function wait(ms){ setTimeout(function(){ busy() ? wait(1500) : open(); }, ms); })(C.preview ? 400 : C.delay*1000);
})();
</script>
<?php
}

/** Entry point for inc/foot.php: the preview (signed-in admins only), else the live popup. */
function popup_render_page(): void {
    $row = null; $preview = false;
    $pid = $_GET['popup_preview'] ?? null;
    try {
        if (is_string($pid) && ctype_digit($pid) && strlen($pid) <= 9 && popup_viewer_is_admin()) {
            $row = popup_by_id((int) $pid);
            $preview = $row !== null;
        }
    } catch (Throwable $e) {
        $row = null;            // a broken preview must never take the footer down
    }
    if (!$row) $row = popup_live();
    if ($row) popup_render($row, $preview);
}
