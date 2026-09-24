<?php
require __DIR__ . '/inc/layout.php';

/* All hero content lives in settings (group 'content'). This editor is reached
   from the Home Sections list — the hero is the top banner of the home page. */

/* key => [type, label, default, hint]  (type: text | textarea) */
$TEXT = [
    // headline
    'hero_eyebrow'      => ['text', 'Eyebrow', 'clinically trusted', 'Small pill above the headline'],
    'hero_title'        => ['text', 'Title', 'next-gen', 'Main headline'],
    'hero_title_accent' => ['text', 'Title — accent word', 'wellness', 'Shown in the accent colour'],
    'hero_sub'          => ['textarea', 'Subtitle', 'Real results. Real confidence. Powered by science, dispensed with care — your everyday glow, distilled. ♡', ''],
    // buttons
    'hero_cta1_label'   => ['text', 'Button 1 — label', 'shop bestsellers', 'Leave blank to hide this button'],
    'hero_cta1_link'    => ['text', 'Button 1 — link', 'skincare', 'A page path (e.g. skincare) or full URL'],
    'hero_cta2_label'   => ['text', 'Button 2 — label', 'talk to an expert', 'Leave blank to hide this button'],
    'hero_cta2_link'    => ['text', 'Button 2 — link', 'contact', 'A page path (e.g. contact) or full URL'],
    // stats
    'hero_stat1_k'      => ['text', 'Stat 1 — value', '100%', 'Leave value blank to hide this stat'],
    'hero_stat1_l'      => ['text', 'Stat 1 — label', 'authentic products', ''],
    'hero_stat2_k'      => ['text', 'Stat 2 — value', '4.8★', ''],
    'hero_stat2_l'      => ['text', 'Stat 2 — label', '7,000+ reviews', ''],
    'hero_stat3_k'      => ['text', 'Stat 3 — value', '24h', ''],
    'hero_stat3_l'      => ['text', 'Stat 3 — label', 'beirut delivery', ''],
    // badges on the photo
    'hero_tag1_sm'      => ['text', 'Badge 1 — small line', 'new in', 'Top-left badge on the photo'],
    'hero_tag1_bg'      => ['text', 'Badge 1 — big line', 'glow serum', ''],
    'hero_tag2_sm'      => ['text', 'Badge 2 — small line', 'loved by 7,000+', 'Bottom-right badge on the photo'],
    'hero_tag2_bg'      => ['text', 'Badge 2 — big line', '★★★★★', 'Shown in the star colour'],
];
$IMAGES = ['hero_img_1', 'hero_img_2', 'hero_img_3', 'hero_img_4'];

if (is_post()) {
    csrf_check();
    foreach ($TEXT as $key => $_) set_setting($key, trim((string) input($key)), 'content');

    $imgErrs = [];
    foreach ($IMAGES as $key) {
        if (input('remove_' . $key)) { set_setting($key, '', 'content'); continue; }
        $err = null;
        if ($up = save_upload($key . '_file', $err)) {
            set_setting($key, $up, 'content');
        } elseif ($err) {
            $imgErrs[] = 'Slide ' . substr($key, -1) . ': ' . $err;
        } else {
            set_setting($key, trim((string) input($key . '_url')), 'content');
        }
    }
    if ($imgErrs) flash('Hero saved, but some slides were not changed — ' . implode(' · ', $imgErrs), 'err');
    else          flash('Hero saved — your homepage banner has been updated.');
    redirect('hero-edit');
}

$slides = array_values(array_filter(array_map(fn($k) => trim(setting($k, '')), $IMAGES)));

admin_head('Edit Hero', 'home-sections', 'The banner at the top of your home page — text, buttons, stats, badges and slide images.');
?>
<form method="post" action="hero-edit" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="page-actions">
    <a class="btn btn-ghost" href="home-sections">← Home Sections</a>
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="../" target="_blank"><?= aicon('eye') ?> Preview store</a>
    <button class="btn btn-primary">Save hero</button>
  </div>

  <?php
  $groups = [
    ['Headline', 'The words on the left side of the banner', ['hero_eyebrow','hero_title','hero_title_accent','hero_sub']],
    ['Buttons', 'The two call-to-action buttons — clear a label to hide that button', ['hero_cta1_label','hero_cta1_link','hero_cta2_label','hero_cta2_link']],
    ['Stats', 'The three numbers under the buttons — clear a value to hide that stat', ['hero_stat1_k','hero_stat1_l','hero_stat2_k','hero_stat2_l','hero_stat3_k','hero_stat3_l']],
    ['Photo badges', 'The two little cards floating over the photo', ['hero_tag1_sm','hero_tag1_bg','hero_tag2_sm','hero_tag2_bg']],
  ];
  foreach ($groups as [$gt, $gd, $keys]): ?>
    <div class="a-card" style="margin-bottom:18px">
      <div class="hd"><h2><?= e($gt) ?></h2><span class="muted" style="font-size:12.5px"><?= e($gd) ?></span></div>
      <div class="bd">
        <div class="f-row">
        <?php foreach ($keys as $key): [$type,$label,$def,$hint] = $TEXT[$key]; $full = $type === 'textarea'; ?>
          <div class="field" style="<?= $full ? 'grid-column:1/-1' : '' ?>">
            <label><?= e($label) ?></label>
            <?php if ($type === 'textarea'): ?>
              <textarea class="input" name="<?= e($key) ?>" rows="3"><?= e(setting($key, $def)) ?></textarea>
            <?php else: ?>
              <input class="input" name="<?= e($key) ?>" value="<?= e(setting($key, $def)) ?>">
            <?php endif; ?>
            <?php if ($hint): ?><div class="hint"><?= e($hint) ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="a-card">
    <div class="hd"><h2>Slide images</h2><span class="muted" style="font-size:12.5px">The photo rotates through these. Leave them all empty to use the built-in defaults; add one for a single still image, or up to four for a slideshow.</span></div>
    <div class="bd">
      <div class="brand-imgs">
        <?php foreach ($IMAGES as $i => $key): $cur = trim(setting($key, '')); ?>
          <div class="brand-img">
            <label class="bi-label">Slide <?= $i + 1 ?></label>
            <div class="bi-preview">
              <?php if ($cur !== ''): ?>
                <img src="<?= e(asrc($cur)) ?>" alt="" onerror="this.closest('.bi-preview').classList.add('is-broken')">
              <?php else: ?>
                <span class="bi-empty">No image</span>
              <?php endif; ?>
            </div>
            <input type="file" name="<?= e($key) ?>_file" accept="image/*" class="bi-file" data-maxmb="10">
            <input class="input bi-url" name="<?= e($key) ?>_url" value="<?= e($cur) ?>" placeholder="…or paste an image URL">
            <?php if ($cur !== ''): ?>
              <label class="switch bi-remove"><input type="checkbox" name="remove_<?= e($key) ?>" value="1"> Remove this image</label>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="hint" style="margin-top:12px">A tall portrait photo (about 4:5) looks best. Max upload 10 MB — large pictures are optimised automatically.</div>
    </div>
  </div>

  <div class="page-actions"><div class="spacer"></div><button class="btn btn-primary">Save hero</button></div>
</form>
<?php admin_foot();
