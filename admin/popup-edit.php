<?php
require __DIR__ . '/inc/layout.php';
require_once dirname(__DIR__) . '/inc/popups.php';

$PRESETS = popup_presets();
$id = (int) input('id');
$editing = $id > 0 && ($row = popup_by_id($id));
if ($id > 0 && !$editing) { flash('Popup not found.', 'err'); redirect('popups'); }

/** datetime-local value ('2026-10-25T09:00') to 'Y-m-d H:i:s'; '' when blank, null when invalid.
    Parsed as UTC so a Beirut DST gap can't shift it: the column stores wall time as typed. */
function popup_dt_in(string $s): ?string {
    $s = trim($s);
    if ($s === '') return '';
    foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $fmt) {
        $d = DateTime::createFromFormat('!' . $fmt, $s, new DateTimeZone('UTC'));
        if ($d && $d->format($fmt) === $s) {
            $y = (int) $d->format('Y');
            return $y >= 2000 && $y <= 2100 ? $d->format('Y-m-d H:i:s') : null;
        }
    }
    return null;
}
/** DATETIME column to a datetime-local value. */
function popup_dt_out(?string $v): string { return $v ? substr(str_replace(' ', 'T', $v), 0, 16) : ''; }

if (is_post()) {
    csrf_check();
    $str = function (string $k, int $max): string {
        $v = input($k);
        return is_string($v) ? mb_substr(trim(str_replace("\r\n", "\n", mb_scrub($v, 'UTF-8'))), 0, $max) : '';
    };
    $pick = function (string $k, array $allowed, string $def): string {
        $v = input($k);
        return is_string($v) && isset($allowed[$v]) ? $v : $def;
    };
    $v = [
        'name'         => $str('name', 120),
        'theme'        => $pick('theme', $PRESETS, 'custom'),
        'eyebrow'      => $str('eyebrow', 80),
        'headline'     => $str('headline', 160),
        'body'         => $str('body', 2000),
        'image'        => $str('image', 500),
        'cta_label'    => $str('cta_label', 60),
        'cta_url'      => $str('cta_url', 500),
        'coupon_code'  => mb_substr(strtoupper((string) preg_replace('/\s+/', '', $str('coupon_code', 60))), 0, 40),
        'color_bg'     => $str('color_bg', 20),
        'color_accent' => $str('color_accent', 20),
        'color_ink'    => $str('color_ink', 20),
        'effect'       => input('effect') ? 1 : 0,
        'starts_at'    => $str('starts_at', 30),
        'ends_at'      => $str('ends_at', 30),
        'frequency'    => $pick('frequency', POPUP_FREQ, 'once'),
        'delay_sec'    => max(0, min(60, (int) input('delay_sec'))),
        'pages'        => $pick('pages', POPUP_PAGES, 'all'),
        'priority'     => max(-999, min(999, (int) input('priority'))),
        'active'       => input('active') ? 1 : 0,
    ];

    /* validate everything before touching the DB (a PDOException is a blank 500) or saving an upload */
    $errs = [];
    if ($v['headline'] === '') $errs[] = 'A headline is required.';
    if ($v['cta_url'] !== '' && !popup_cta_ok($v['cta_url'])) $errs[] = 'The button link must be a page on this site (like /offers) or a full https:// address.';
    if (!popup_image_ok($v['image'])) $errs[] = 'The image must be an uploaded file or a full https:// address.';
    foreach (['color_bg' => 'Background', 'color_accent' => 'Accent', 'color_ink' => 'Text'] as $k => $lbl) {
        if ($v[$k] !== '' && popup_hex($v[$k]) === '') $errs[] = $lbl . ' colour must look like #1A2B3C, or be left blank to use the theme.';
        else $v[$k] = popup_hex($v[$k]);
    }
    $starts = popup_dt_in($v['starts_at']);
    $ends   = popup_dt_in($v['ends_at']);
    if ($starts === null) $errs[] = 'The start date is not a valid date and time.';
    if ($ends === null)   $errs[] = 'The end date is not a valid date and time.';
    if ($starts && $ends && $ends <= $starts) $errs[] = 'The end must be after the start.';

    if ($errs) {
        $_SESSION['popup_old'] = ['id' => $editing ? $id : 0, 'v' => $v];      // keep what they typed
        flash(implode(' ', $errs), 'err');
        redirect($editing ? 'popup-edit?id=' . $id . admin_ret_qs() : 'popup-edit' . admin_ret_qs('?'));
    }

    $upErr = null;
    if ($u = save_upload('image_file', $upErr)) $v['image'] = $u;
    if ($v['name'] === '') $v['name'] = mb_substr($v['headline'], 0, 120);
    $v['starts_at'] = $starts ?: null;
    $v['ends_at']   = $ends ?: null;

    /* a coupon that doesn't work would turn the popup into a broken promise: warn, but still save */
    $warn = [];
    if ($v['coupon_code'] !== '') {
        $c = row("SELECT * FROM coupons WHERE code = ?", [$v['coupon_code']]);
        $code = $v['coupon_code'];
        if (!$c)                         $warn[] = "There's no coupon " . $code . ' yet, so shoppers would see "Invalid code". Add it under Coupons.';
        elseif (!(int) $c['active'])     $warn[] = 'Coupon ' . $code . ' is switched off, so it will not work at checkout.';
        elseif ($c['expires_at'] && $c['expires_at'] < date('Y-m-d')) $warn[] = 'Coupon ' . $code . ' expired on ' . date('M j, Y', strtotime($c['expires_at'])) . '.';
        elseif ($c['usage_limit'] !== null && (int) $c['used_count'] >= (int) $c['usage_limit']) $warn[] = 'Coupon ' . $code . ' has reached its usage limit.';
        elseif ($c['expires_at'] && $v['ends_at'] && $c['expires_at'] < substr($v['ends_at'], 0, 10)) $warn[] = 'Coupon ' . $code . ' expires on ' . date('M j', strtotime($c['expires_at'])) . ', before this popup ends.';
    }

    if ($editing) {
        $v['id'] = $id;
        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys(array_diff_key($v, ['id' => 1]))));
        q("UPDATE popups SET $sets WHERE id = :id", $v);
        $savedId = $id;
    } else {
        q("INSERT INTO popups (" . implode(',', array_keys($v)) . ") VALUES (:" . implode(',:', array_keys($v)) . ")", $v);
        $savedId = (int) last_id();
    }

    $msg = $editing ? 'Popup saved.' : 'Popup created.';
    if ($upErr) $msg .= ' The image was not changed: ' . $upErr;
    if ($warn)  $msg .= ' ' . implode(' ', $warn);
    $saved = popup_by_id($savedId);
    $st = popup_status($saved);
    if ($st === 'live') {
        /* same order as the storefront (priority, then the later start, then the newer row) */
        $why = function (array $w) use ($saved): string {
            if ((int) $w['priority'] > (int) $saved['priority']) return 'has a higher priority';
            return (string) $w['starts_at'] !== (string) $saved['starts_at'] ? 'has the same priority and a later start' : 'has the same priority and is newer';
        };
        $win = popup_pick($saved['pages'] === 'home');
        if ($win && (int) $win['id'] !== $savedId) {
            $msg .= ' Note: "' . $win['name'] . '" ' . $why($win) . ', so visitors see that one instead.';
        } elseif ($saved['pages'] === 'all' && ($winHome = popup_pick(true)) && (int) $winHome['id'] !== $savedId) {
            /* a home-only popup can outrank this one on the home page alone */
            $msg .= ' Note: on the home page, "' . $winHome['name'] . '" ' . $why($winHome) . ', so it shows there instead.';
        }
    } elseif ($st === 'ended') {
        $msg .= ' Note: its end date has passed, so it will not show.';
    }
    flash($msg, ($upErr || $warn) ? 'err' : 'ok');
    redirect(input('after') === 'list'
        ? admin_back_href('popups')
        : 'popup-edit?id=' . $savedId . admin_ret_qs());
}

/* a new popup starts on the occasion this month is most likely about */
$SEASON = [1 => 'new_year', 2 => 'valentine', 3 => 'mothers_day', 4 => 'spring', 5 => 'spring', 6 => 'fathers_day',
           7 => 'summer', 8 => 'summer', 9 => 'custom', 10 => 'halloween', 11 => 'black_friday', 12 => 'christmas'];
$v = $editing ? $row : [
    'id' => 0, 'name' => '', 'theme' => $SEASON[(int) date('n')], 'eyebrow' => '', 'headline' => '', 'body' => '', 'image' => '',
    'cta_label' => '', 'cta_url' => '', 'coupon_code' => '', 'color_bg' => '', 'color_accent' => '', 'color_ink' => '',
    'effect' => 1, 'starts_at' => null, 'ends_at' => null, 'frequency' => 'once', 'delay_sec' => 3, 'pages' => 'all',
    'priority' => 0, 'active' => 1,
];
$v['starts_at'] = popup_dt_out($v['starts_at']);
$v['ends_at']   = popup_dt_out($v['ends_at']);
$old = $_SESSION['popup_old'] ?? null;
unset($_SESSION['popup_old']);
if ($old && (int) $old['id'] === ($editing ? $id : 0)) $v = array_merge($v, $old['v']);
$pre = popup_preset((string) $v['theme']);

$coupons = rows("SELECT code, type, value, expires_at FROM coupons
                 WHERE active = 1 AND (expires_at IS NULL OR expires_at >= ?) ORDER BY code", [date('Y-m-d')]);

/* preset data for the live preview: CSS values, the hex behind them, art and suggested copy */
$JS = [];
foreach ($PRESETS as $k => $p) {
    $JS[$k] = ['bg' => $p['bg'], 'ac' => $p['accent'], 'ink' => $p['ink'],
               'bgHex' => popup_theme_hex($p['bg']), 'acHex' => popup_theme_hex($p['accent']), 'inkHex' => popup_theme_hex($p['ink']),
               'art' => popup_chars($p['art']), 'fx' => array_map('popup_chars', $p['particles']), 'ey' => $p['eyebrow'], 'h' => $p['headline']];
}

$status = $editing ? popup_status($row) : '';
$STATUS_LABEL = ['live' => 'Live now', 'scheduled' => 'Scheduled', 'ended' => 'Ended', 'off' => 'Off'];
admin_head($editing ? 'Edit popup' : 'Add popup', 'popups', $editing ? $row['name'] : 'New popup');
render_theme();   // the store's fonts and colours, so the preview matches the shop
?>
<form method="post" action="<?= $editing ? 'popup-edit?id=' . $id : 'popup-edit' ?>" enctype="multipart/form-data" id="ppForm">
  <?= csrf_field() ?><?= admin_ret_field() ?>
  <div class="page-actions"><a class="btn btn-ghost" href="<?= e(admin_back_href('popups')) ?>">&larr; Back</a><?php if ($editing): ?><a class="btn btn-ghost" href="../?popup_preview=<?= $id ?>" target="_blank" rel="noopener"><?= aicon('eye') ?> Preview on site</a><?php endif; ?><div class="spacer"></div><button class="btn btn-primary">Save popup</button><button class="btn btn-ghost" name="after" value="list">Save &amp; back to list</button></div>

  <div class="a-grid" style="grid-template-columns:1.5fr 1fr;align-items:start">
    <div style="display:flex;flex-direction:column;gap:18px;min-width:0">

      <div class="a-card"><div class="hd"><h2>Message</h2><button type="button" class="btn btn-ghost btn-sm" id="ppSuggest">Use suggested text</button></div><div class="bd">
        <div class="field"><label>Name <span class="faint">(only you see this)</span></label><input class="input" name="name" value="<?= e($v['name']) ?>" maxlength="120" placeholder="e.g. Halloween <?= date('Y') ?>"></div>
        <div class="f-row">
          <div class="field"><label>Small line above the headline <span class="faint">(optional)</span></label><input class="input" name="eyebrow" value="<?= e($v['eyebrow']) ?>" maxlength="80" placeholder="<?= e($pre['eyebrow']) ?>"></div>
          <div class="field"><label>Headline</label><input class="input" name="headline" value="<?= e($v['headline']) ?>" maxlength="160" required placeholder="<?= e($pre['headline']) ?>"></div>
        </div>
        <div class="field"><label>Text <span class="faint">(optional)</span></label><textarea class="input" name="body" rows="4" maxlength="2000" placeholder="A sentence or two about the offer. Line breaks are kept."><?= e($v['body']) ?></textarea></div>
        <div class="f-row">
          <div class="field"><label>Button label</label><input class="input" name="cta_label" value="<?= e($v['cta_label']) ?>" maxlength="60" placeholder="Shop now"></div>
          <div class="field"><label>Button link</label><input class="input" name="cta_url" value="<?= e($v['cta_url']) ?>" maxlength="500" placeholder="/offers or https://..."><div class="hint">A page on this site or a full https:// address. With a label but no link, the button just closes the popup.</div></div>
        </div>
        <div class="field" style="margin-bottom:0"><label>Coupon code <span class="faint">(optional)</span></label>
          <input class="input" name="coupon_code" value="<?= e($v['coupon_code']) ?>" maxlength="40" list="ppCoupons" autocomplete="off" style="max-width:260px;text-transform:uppercase" placeholder="e.g. SPOOKY20">
          <datalist id="ppCoupons"><?php foreach ($coupons as $c): ?><option value="<?= e($c['code']) ?>"><?= e($c['type'] === 'percent' ? (int) $c['value'] . '% off' : ($c['type'] === 'fixed' ? money($c['value']) . ' off' : 'Free shipping')) ?></option><?php endforeach; ?></datalist>
          <div class="hint">Shown with a Copy button. Pick one of your active coupons; a code that doesn't exist yet is saved with a warning.</div>
        </div>
      </div></div>

      <div class="a-card"><div class="hd"><h2>Look</h2></div><div class="bd">
        <div class="field"><label>Theme</label>
          <select class="input" name="theme" id="ppTheme" style="max-width:320px">
            <?php foreach ($PRESETS as $k => $p): ?><option value="<?= e($k) ?>" <?= $v['theme'] === $k ? 'selected' : '' ?>><?= e($p['label']) ?></option><?php endforeach; ?>
          </select>
          <div class="hint">Sets the colours, the art and the falling decorations. Store theme follows your Appearance colours.</div>
        </div>
        <div class="field"><label>Colours <span class="faint">(blank = theme colour)</span></label>
          <div class="pp-colors">
            <?php foreach (['color_bg' => ['Background', 'bg'], 'color_accent' => ['Accent (button, line)', 'accent'], 'color_ink' => ['Text', 'ink']] as $k => [$lbl, $pk]): $ph = popup_theme_hex($pre[$pk]); ?>
            <div class="pp-col">
              <input type="color" value="<?= e(popup_hex($v[$k]) ?: $ph) ?>" data-color="<?= $k ?>" aria-label="<?= e($lbl) ?> colour">
              <span class="pp-col-l"><?= e($lbl) ?></span>
              <input class="input" name="<?= $k ?>" value="<?= e($v[$k]) ?>" maxlength="7" placeholder="<?= e($ph) ?>" data-hex="<?= $k ?>" aria-label="<?= e($lbl) ?> hex">
              <button type="button" class="btn btn-ghost btn-sm" data-reset="<?= $k ?>">Reset</button>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <label class="switch switch-block"><input type="checkbox" name="effect" value="1" id="ppEffect" <?= $v['effect'] ? 'checked' : '' ?>>
          <span class="sw-txt"><b>Falling decorations</b><span class="muted"> <span id="ppFxList"><?= implode(' ', $pre['particles']) ?></span> drift down behind the popup. Skipped for visitors who turn off animations.</span></span></label>
      </div></div>

      <div class="a-card"><div class="hd"><h2>Image <span class="faint" style="font-weight:500;font-size:13px">(optional)</span></h2></div><div class="bd">
        <div class="field" id="ppImgField" style="margin-bottom:0">
          <?php if ($v['image'] !== ''): ?><img src="<?= e(asrc($v['image'])) ?>" alt="" style="width:120px;height:150px;border-radius:10px;object-fit:cover;margin-bottom:8px;border:1px solid var(--a-border2)"><?php endif; ?>
          <input class="input" name="image" value="<?= e($v['image']) ?>" maxlength="500" placeholder="https://... or upload below">
          <input type="file" name="image_file" accept="image/*" data-maxmb="10" style="margin-top:8px;font-size:12.5px">
          <div class="hint">Fills the art panel (a short banner on phones). Without one, the theme's art is used. Portrait photos work best, about 800 &times; 1000 (max 10 MB, auto-optimized).</div>
        </div>
      </div></div>

      <div class="a-card"><div class="hd"><h2>Schedule &amp; visibility</h2></div><div class="bd">
        <div class="f-row">
          <div class="field"><label>Starts</label><input class="input" type="datetime-local" name="starts_at" value="<?= e($v['starts_at']) ?>"><div class="hint">Beirut time. Blank = right away.</div></div>
          <div class="field"><label>Ends</label><input class="input" type="datetime-local" name="ends_at" value="<?= e($v['ends_at']) ?>"><div class="hint">Blank = until you switch it off.</div></div>
        </div>
        <div class="f-row-3">
          <div class="field"><label>How often</label>
            <select class="input" name="frequency"><?php foreach (POPUP_FREQ as $k => $lbl): ?><option value="<?= $k ?>" <?= $v['frequency'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select>
          </div>
          <div class="field"><label>Show on</label>
            <select class="input" name="pages"><?php foreach (POPUP_PAGES as $k => $lbl): ?><option value="<?= $k ?>" <?= $v['pages'] === $k ? 'selected' : '' ?>><?= e($lbl) ?></option><?php endforeach; ?></select>
          </div>
          <div class="field"><label>Delay <span class="faint">(seconds)</span></label><input class="input" type="number" name="delay_sec" min="0" max="60" value="<?= (int) $v['delay_sec'] ?>"></div>
        </div>
        <div class="f-row-3">
          <div class="field"><label>Priority</label><input class="input" type="number" name="priority" min="-999" max="999" value="<?= (int) $v['priority'] ?>"></div>
        </div>
        <div class="hint" style="margin:-6px 0 14px">Only one popup shows per page view: when two are live, the higher priority wins. Cart, checkout and account pages never show popups. The newsletter popup never shows on the same page as one, and the first time someone sees a seasonal popup it also waits until their next visit.</div>
        <label class="switch switch-block"><input type="checkbox" name="active" value="1" <?= $v['active'] ? 'checked' : '' ?>>
          <span class="sw-txt"><b>Active</b><span class="muted"> Switch off to pause it without losing the schedule.</span></span></label>
      </div></div>
    </div>

    <div class="pp-side">
      <div class="a-card"><div class="hd"><h2>Preview</h2>
        <div class="pp-seg" role="group" aria-label="Preview size"><button type="button" class="on" data-pv="desk">Desktop</button><button type="button" data-pv="phone">Phone</button></div></div>
        <div class="bd">
          <div class="pp-stage" id="ppStage" style="<?= e(popup_style($v)) ?>" inert aria-hidden="true">
            <div class="pp-fx" id="ppFx"></div>
            <div class="pp-scale" id="ppScale"><?= popup_card_html($v, ['id' => 'ppCard', 'all' => true, 'img' => asrc((string) $v['image'])]) ?></div>
          </div>
          <div class="hint" style="margin-top:10px">
            <?php if ($editing): ?>
              <span class="pill pill-<?= $status === 'live' ? 'good' : ($status === 'scheduled' ? 'info' : 'muted') ?>"><?= e($STATUS_LABEL[$status]) ?></span>
              Updates as you type. <a href="../?popup_preview=<?= $id ?>" target="_blank" rel="noopener" style="text-decoration:underline">Preview on site</a> shows the saved version with its timing and animation, to you only.
            <?php else: ?>
              Updates as you type. After saving, use Preview on site to see it on the shop (only you will see it).
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="page-actions" style="margin-top:18px"><div class="spacer"></div><button class="btn btn-primary">Save popup</button><button class="btn btn-ghost" name="after" value="list">Save &amp; back to list</button></div>
</form>

<style>
<?= popup_css(true) ?>
  .pp-side{position:sticky;top:18px;min-width:0}
  .pp-stage{position:relative;overflow:hidden;border-radius:12px;font-family:var(--fs,inherit);line-height:1.6;background:radial-gradient(60% 55% at 50% 45%,rgba(var(--cp-ac-rgb),.18),transparent 70%),#3b332b;pointer-events:none;user-select:none}
  .pp-fx{position:absolute;inset:0;overflow:hidden}
  .pp-p{position:absolute;top:-10%;font-style:normal;line-height:1;animation:ppFall 10s linear infinite}
  .pp-p i{display:block;font-style:normal;animation:cpSway 4s ease-in-out infinite alternate}
  @keyframes ppFall{from{top:-10%;transform:translateX(0)}to{top:105%;transform:translateX(var(--dx,0px))}}
  .pp-scale{position:absolute;top:16px;left:16px;transform-origin:0 0}
  .pp-scale .cpop-card{max-height:none;overflow:hidden;animation:none}
  .pp-scale .cpop-ph{opacity:.42}
  .pp-seg{display:inline-flex;padding:3px;border-radius:999px;background:var(--a-border2)}
  .pp-seg button{border:0;background:none;padding:5px 12px;border-radius:999px;font:inherit;font-size:12.5px;font-weight:600;color:var(--a-soft);cursor:pointer}
  .pp-seg button.on{background:#fff;color:var(--a-ink);box-shadow:0 1px 2px rgba(34,30,24,.12)}
  .pp-colors{display:flex;flex-direction:column;gap:8px}
  .pp-col{display:flex;align-items:center;gap:10px}
  .pp-col input[type=color]{flex:none;width:40px;height:40px;padding:2px;border:1px solid var(--a-border);border-radius:10px;background:#fff;cursor:pointer}
  .pp-col-l{flex:1;min-width:0;font-size:13.5px}
  .pp-col .input{flex:none;width:112px;text-transform:uppercase}
  @media(max-width:900px){.pp-side{position:static}}
  @media (prefers-reduced-motion:reduce){.pp-fx{display:none}}
</style>
<script>
(function(){
  var P=<?= json_encode($JS, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var form=document.getElementById('ppForm'), stage=document.getElementById('ppStage'), scale=document.getElementById('ppScale'), fx=document.getElementById('ppFx');
  if(!form||!stage) return;
  var card=scale.querySelector('.cpop-card'), art=card.querySelector('.cpop-art'), glyph=card.querySelector('.cpop-glyph'), img=card.querySelector('.cpop-img'),
      ey=card.querySelector('.cpop-ey'), h=card.querySelector('.cpop-h'), txt=card.querySelector('.cpop-txt'),
      code=card.querySelector('.cpop-code'), cta=card.querySelector('.cpop-cta'), fileIn=form.querySelector('input[name=image_file]');
  var mode=window.innerWidth<=720?'phone':'desk', fxKey=null, objUrl=null;   /* the shop's phone rules apply on a phone anyway */

  function el(n){ return form.elements[n]; }
  function val(n){ var f=el(n); return f ? String(f.value).trim() : ''; }
  function hex(v){ return /^#[0-9a-fA-F]{6}$/.test(v) ? v.toUpperCase() : ''; }
  function asrc(v){ return (!v || /^(https?:|\/|data:|blob:)/.test(v)) ? v : '../'+v; }
  /* same maths as popup_contrast() / popup_on_color() in inc/popups.php */
  function lum(x){ var c=[1,3,5].map(function(i){ var v=parseInt(x.substr(i,2),16)/255; return v<=.03928 ? v/12.92 : Math.pow((v+.055)/1.055,2.4); }); return .2126*c[0]+.7152*c[1]+.0722*c[2]; }
  function contrast(a,b){ var x=lum(a), y=lum(b); return (Math.max(x,y)+.05)/(Math.min(x,y)+.05); }
  function onColor(ac,bg,ink){ var best=contrast(ac,bg)>=contrast(ac,ink)?bg:ink; if(contrast(ac,best)>=4.5) return best; return contrast(ac,'#FFFFFF')>=contrast(ac,'#1C1814')?'#FFFFFF':'#1C1814'; }
  function rgb(x){ return [1,3,5].map(function(i){ return parseInt(x.substr(i,2),16); }).join(','); }
  function theme(){ return P[val('theme')] || P.custom; }

  function rain(t){
    var key=el('effect').checked ? val('theme') : '';
    if(key===fxKey) return;
    fxKey=key; fx.textContent='';
    if(!key) return;
    for(var i=0;i<12;i++){
      var o=document.createElement('i'), s=document.createElement('i'), d=Math.random(), dur=11-d*4+Math.random()*2;
      o.className='pp-p'; o.style.left=(Math.random()*96).toFixed(1)+'%'; o.style.fontSize=Math.round(11+d*13)+'px';
      o.style.opacity=(.4+d*.5).toFixed(2); o.style.animationDuration=dur.toFixed(1)+'s'; o.style.animationDelay=(-Math.random()*dur).toFixed(1)+'s';
      o.style.setProperty('--dx',Math.round((Math.random()-.5)*60)+'px');
      s.style.setProperty('--r',Math.round((Math.random()<.5?-1:1)*(20+Math.random()*40))+'deg'); s.style.animationDuration=(3+Math.random()*2).toFixed(1)+'s';
      s.textContent=t.fx[i%t.fx.length]; o.appendChild(s); fx.appendChild(o);
    }
  }
  function fit(){
    var phone=mode==='phone', nat=phone?360:780;
    scale.style.width=nat+'px';
    scale.classList.toggle('cpop-narrow',phone);
    if(card.offsetWidth && card.offsetWidth<nat){ nat=card.offsetWidth; scale.style.width=nat+'px'; }   /* 720px and under: the shop's one-column rule caps the card */
    var avail=stage.clientWidth-32, s=Math.min(1,avail/nat);
    scale.style.transform='scale('+s+')';
    scale.style.left=Math.max(16,(stage.clientWidth-nat*s)/2)+'px';
    stage.style.height=Math.ceil(card.offsetHeight*s+32)+'px';
  }
  function render(){
    var t=theme(), css={}, hx={};
    [['bg','color_bg','bgHex'],['ac','color_accent','acHex'],['ink','color_ink','inkHex']].forEach(function(c){
      var o=hex(val(c[1])); css[c[0]]=o||t[c[0]]; hx[c[0]]=o||t[c[2]];
    });
    var acBg=contrast(hx.ac,hx.bg);
    var vars={'--cp-bg':css.bg,'--cp-ac':css.ac,'--cp-ink':css.ink,'--cp-on':onColor(hx.ac,hx.bg,hx.ink),
              '--cp-ey':acBg>=4.5?css.ac:css.ink,'--cp-focus':acBg>=3?css.ac:css.ink,'--cp-bg-rgb':rgb(hx.bg),'--cp-ac-rgb':rgb(hx.ac),'--cp-ink-rgb':rgb(hx.ink)};
    for(var k in vars) stage.style.setProperty(k,vars[k]);

    glyph.textContent=t.art;
    ey.textContent=val('eyebrow'); ey.hidden=!val('eyebrow');
    var head=val('headline');
    h.textContent=head||t.h||'Your headline'; h.classList.toggle('cpop-ph',!head); h.classList.toggle('cpop-h--long',h.textContent.length>55);
    txt.textContent=''; txt.hidden=!val('body');
    val('body').split('\n').forEach(function(line,i){ if(i) txt.appendChild(document.createElement('br')); txt.appendChild(document.createTextNode(line)); });
    var cc=val('coupon_code').toUpperCase().replace(/\s+/g,'');
    code.hidden=!cc; code.querySelector('.cpop-code-v').textContent=cc;
    cta.hidden=!val('cta_label') && !val('cta_url');
    cta.querySelector('span').textContent=val('cta_label')||'Shop now';

    var f=fileIn && fileIn.files && fileIn.files[0], src='';
    if(objUrl){ URL.revokeObjectURL(objUrl); objUrl=null; }
    if(f && /^image\//.test(f.type)){ objUrl=URL.createObjectURL(f); src=objUrl; }
    else if(val('image')) src=asrc(val('image'));
    if(src){ img.src=src; } else img.removeAttribute('src');
    img.hidden=!src; art.classList.toggle('cpop-art--img',!!src);

    rain(t);
    fit();
  }

  /* theme change: colour pickers, placeholders and the decorations hint follow the preset */
  function applyTheme(){
    var t=theme();
    [['color_bg','bgHex'],['color_accent','acHex'],['color_ink','inkHex']].forEach(function(c){
      var tx=form.querySelector('[data-hex='+c[0]+']'), pk=form.querySelector('[data-color='+c[0]+']');
      tx.placeholder=t[c[1]]; if(!hex(tx.value)) pk.value=t[c[1]].toLowerCase();
    });
    el('eyebrow').placeholder=t.ey; el('headline').placeholder=t.h||'Your headline';
    document.getElementById('ppFxList').textContent=t.fx.join(' ');
    render();
  }

  form.querySelectorAll('[data-color]').forEach(function(pk){
    var tx=form.querySelector('[data-hex='+pk.dataset.color+']');
    pk.addEventListener('input',function(){ tx.value=pk.value.toUpperCase(); render(); });
    tx.addEventListener('input',function(){ var o=hex(tx.value.trim()); pk.value=(o||theme()[{color_bg:'bgHex',color_accent:'acHex',color_ink:'inkHex'}[pk.dataset.color]]).toLowerCase(); render(); });
  });
  form.querySelectorAll('[data-reset]').forEach(function(b){
    b.addEventListener('click',function(){ var tx=form.querySelector('[data-hex='+b.dataset.reset+']'); tx.value=''; tx.dispatchEvent(new Event('input')); });
  });
  document.getElementById('ppTheme').addEventListener('change',applyTheme);
  document.getElementById('ppSuggest').addEventListener('click',function(){
    var t=theme(); if(!t.h){ el('headline').focus(); return; }
    el('eyebrow').value=t.ey; el('headline').value=t.h; render();
  });
  ['name','eyebrow','headline','body','cta_label','cta_url','coupon_code','image'].forEach(function(n){ el(n) && el(n).addEventListener('input',render); });
  el('effect').addEventListener('change',render);
  fileIn && fileIn.addEventListener('change',function(){ setTimeout(render,0); });
  /* the image enhancer clears the URL field without an input event (remove / undo) */
  document.getElementById('ppImgField').addEventListener('click',function(){ setTimeout(render,0); });
  stage.parentNode.parentNode.querySelectorAll('[data-pv]').forEach(function(b){
    b.classList.toggle('on',b.dataset.pv===mode);
    b.addEventListener('click',function(){
      mode=b.dataset.pv;
      b.parentNode.querySelectorAll('button').forEach(function(x){ x.classList.toggle('on',x===b); });
      fit();
    });
  });
  if(window.ResizeObserver) new ResizeObserver(fit).observe(stage); else window.addEventListener('resize',fit);
  img.addEventListener('load',fit);
  if(document.fonts && document.fonts.ready) document.fonts.ready.then(fit);
  render();
})();
</script>
<?php admin_foot();
