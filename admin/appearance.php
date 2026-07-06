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

if (is_post()) {
    csrf_check();
    foreach ($COLORS as [$key]) {
        $v = trim((string) input($key));
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $v)) set_setting($key, $v, 'theme');
    }
    $fonts = theme_fonts();
    foreach (['theme_font_display', 'theme_font_body'] as $fk) {
        $v = (string) input($fk);
        if (isset($fonts[$v])) set_setting($fk, $v, 'theme');
    }
    flash('Theme saved — your storefront has been re-styled.');
    redirect('appearance');
}

$fonts = array_keys(theme_fonts());
$curDisplay = setting('theme_font_display', 'Clash Display');
$curBody    = setting('theme_font_body', 'General Sans');

admin_head('Appearance', 'appearance', 'Control your store\'s colours and fonts — changes apply site-wide instantly.');
?>
<form method="post" action="appearance">
  <?= csrf_field() ?>
  <div class="page-actions">
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="../" target="_blank"><?= aicon('eye') ?> Preview store</a>
    <button class="btn btn-primary"><?= aicon('brush') ?> Save theme</button>
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
