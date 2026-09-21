<?php
require __DIR__ . '/inc/layout.php';

$COLORS = [
    ['theme_primary',      'Primary accent',     'Links, eyebrows, icons, highlights'],
    ['theme_primary_deep', 'Primary (deep)',     'Hover / stronger accent shade'],
    ['theme_secondary',    'Secondary / sale',   'Sale prices & caramel CTAs'],
    ['theme_secondary_deep','Secondary (deep)',  'Hover shade for secondary'],
    ['theme_ink',          'Main text',          'Headings & body text'],
    ['theme_ink_soft',     'Soft text',          'Sub-text & captions'],
    ['theme_cream',        'Background band',    'Hero / section backgrounds'],
    ['theme_cream2',       'Background (alt)',   'Editorial & cards'],
    ['theme_star',         'Rating stars',       'Product star colour'],
];

/* the three brand pictures: setting key => [field label, hint] */
$IMAGES = [
    'store_logo'        => ['Site logo',  'Shown in the header and footer, next to (or instead of) the store name.'],
    'store_favicon'     => ['Browser icon (favicon)', 'The tiny picture on the browser tab and on a phone home screen. Square works best. Leave empty to reuse the logo.'],
    'store_share_image' => ['Link preview / profile picture', 'The picture that appears when the site link is shared on WhatsApp, Facebook or Instagram. 1200×630 works best. Leave empty to reuse the logo.'],
];

if (is_post()) {
    csrf_check();

    /* ---- branding pictures: upload, paste-a-URL, or tick remove ---- */
    $imgErrs = [];
    foreach ($IMAGES as $key => [$label]) {
        if (input('remove_' . $key)) { set_setting($key, '', 'theme'); continue; }
        $err = null;
        if ($up = save_upload($key . '_file', $err)) {
            set_setting($key, $up, 'theme');
        } elseif ($err) {
            $imgErrs[] = $label . ': ' . $err;
        } else {
            /* no new file — keep whatever the URL box says (blank clears it) */
            set_setting($key, trim((string) input($key . '_url')), 'theme');
        }
    }
    $mode = (string) input('logo_mode');
    set_setting('logo_mode', in_array($mode, ['auto','logo','name','both'], true) ? $mode : 'auto', 'theme');

    foreach ($COLORS as [$key]) {
        $v = trim((string) input($key));
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $v)) set_setting($key, $v, 'theme');
    }
    $fonts = theme_fonts();
    foreach (['theme_font_display', 'theme_font_body'] as $fk) {
        $v = (string) input($fk);
        if (isset($fonts[$v])) set_setting($fk, $v, 'theme');
    }

    if ($imgErrs) flash('Theme saved, but some pictures were not changed — ' . implode(' · ', $imgErrs), 'err');
    else          flash('Theme saved — your storefront has been re-styled.');
    redirect('appearance');
}

$fonts = array_keys(theme_fonts());
$curDisplay = setting('theme_font_display', 'Clash Display');
$curBody    = setting('theme_font_body', 'General Sans');
$logoMode   = setting('logo_mode', 'auto');

admin_head('Appearance', 'appearance', 'Your logo, colours and fonts — changes apply site-wide instantly.');
?>
<form method="post" action="appearance" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="page-actions">
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="../" target="_blank"><?= aicon('eye') ?> Preview store</a>
    <button class="btn btn-primary"><?= aicon('brush') ?> Save theme</button>
  </div>

  <div class="a-card" style="margin-bottom:18px">
    <div class="hd"><h2>Logo &amp; pictures</h2><span class="muted" style="font-size:12.5px">Upload once — you can change or remove them any time</span></div>
    <div class="bd">
      <div class="brand-imgs">
        <?php foreach ($IMAGES as $key => [$label, $hint]):
            $cur = trim(setting($key)); ?>
          <div class="brand-img">
            <label class="bi-label"><?= e($label) ?></label>
            <div class="bi-preview">
              <?php if ($cur !== ''): ?>
                <img src="<?= e(asrc($cur)) ?>" alt="" onerror="this.closest('.bi-preview').classList.add('is-broken')">
              <?php else: ?>
                <span class="bi-empty">No picture yet</span>
              <?php endif; ?>
            </div>
            <input type="file" name="<?= e($key) ?>_file" accept="image/*" class="bi-file" data-maxmb="10">
            <input class="input bi-url" name="<?= e($key) ?>_url" value="<?= e($cur) ?>" placeholder="…or paste an image URL">
            <?php if ($cur !== ''): ?>
              <label class="switch bi-remove"><input type="checkbox" name="remove_<?= e($key) ?>" value="1"> Remove this picture</label>
            <?php endif; ?>
            <div class="hint"><?= e($hint) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="field" style="margin:18px 0 0;max-width:420px">
        <label>How the header should show your brand</label>
        <select class="input" name="logo_mode">
          <option value="auto" <?= $logoMode==='auto'?'selected':'' ?>>Auto — logo if one is uploaded, otherwise the store name</option>
          <option value="logo" <?= $logoMode==='logo'?'selected':'' ?>>Logo picture only</option>
          <option value="name" <?= $logoMode==='name'?'selected':'' ?>>Store name only</option>
          <option value="both" <?= $logoMode==='both'?'selected':'' ?>>Logo picture + store name</option>
        </select>
        <div class="hint">The store name itself is edited in <a href="settings" style="text-decoration:underline">Settings</a>. Max upload size 10 MB — large pictures are optimised automatically.</div>
      </div>
    </div>
  </div>

  <div class="a-card" style="margin-bottom:18px">
    <div class="hd"><h2>Colours</h2><span class="muted" style="font-size:12.5px">Click a swatch to pick, or type a hex code</span></div>
    <div class="bd">
      <div class="swatches">
        <?php foreach ($COLORS as [$key, $label, $hint]):
            $val = setting($key, '#000000'); ?>
          <div class="swatch">
            <span class="chip" data-chip="<?= e($key) ?>" style="background:<?= e($val) ?>"></span>
            <div class="meta" style="flex:1">
              <b><?= e($label) ?></b>
              <div class="hx muted" style="font-size:11.5px"><?= e($hint) ?></div>
              <div style="display:flex;align-items:center;gap:8px;margin-top:8px">
                <input type="color" value="<?= e($val) ?>" data-color="<?= e($key) ?>">
                <input type="text" name="<?= e($key) ?>" value="<?= e($val) ?>" data-hex="<?= e($key) ?>" maxlength="7">
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="a-card">
    <div class="hd"><h2>Typography</h2></div>
    <div class="bd">
      <div class="f-row">
        <div class="field">
          <label>Heading / display font</label>
          <select class="input" name="theme_font_display">
            <?php foreach ($fonts as $f): ?><option <?= $f===$curDisplay?'selected':'' ?>><?= e($f) ?></option><?php endforeach; ?>
          </select>
          <div class="hint">Used for all titles &amp; the logo wordmark.</div>
        </div>
        <div class="field">
          <label>Body font</label>
          <select class="input" name="theme_font_body">
            <?php foreach ($fonts as $f): ?><option <?= $f===$curBody?'selected':'' ?>><?= e($f) ?></option><?php endforeach; ?>
          </select>
          <div class="hint">Used for paragraphs, buttons &amp; UI text.</div>
        </div>
      </div>
      <p class="muted" style="font-size:12.5px;margin:4px 0 0">Fonts load automatically from Fontshare / Google Fonts. After saving, refresh the storefront to see the change.</p>
    </div>
  </div>
</form>

<script>
  // keep colour picker, hex text and preview chip in sync
  document.querySelectorAll('[data-color]').forEach(picker => {
    const key = picker.dataset.color;
    const hex = document.querySelector(`[data-hex="${key}"]`);
    const chip = document.querySelector(`[data-chip="${key}"]`);
    const apply = v => { chip.style.background = v; };
    picker.addEventListener('input', () => { hex.value = picker.value.toUpperCase(); apply(picker.value); });
    hex.addEventListener('input', () => { if (/^#[0-9a-fA-F]{6}$/.test(hex.value)) { picker.value = hex.value; apply(hex.value); } });
  });
</script>
<?php admin_foot();
