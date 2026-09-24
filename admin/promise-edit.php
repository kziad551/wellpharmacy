<?php
require __DIR__ . '/inc/layout.php';

/* The "promise" banner near the bottom of the home page (the big lowercase line).
   Reached from the Home Sections list. All content lives in settings (group 'content'). */

/* key => [type, label, default, hint] */
$TEXT = [
    'promise_eyebrow'   => ['text', 'Eyebrow', 'where wellness meets you', 'Small label above the big line — clear to hide'],
    'promise_line1'     => ['text', 'Big line', 'glow,', 'The first (dark) part of the big lowercase line'],
    'promise_accent'    => ['text', 'Big line — accent', 'responsibly.', 'The second part, shown in the accent colour'],
    'promise_sub'       => ['textarea', 'Subtitle', 'Beirut-born, science-led skincare & wellness — dispensed with the care of your neighbourhood pharmacy, delivered to your door.', ''],
    'promise_cta_label' => ['text', 'Button — label', 'start shopping', 'Clear to hide the button'],
    'promise_cta_link'  => ['text', 'Button — link', 'skincare', 'A page path (e.g. skincare) or full URL'],
];

if (is_post()) {
    csrf_check();
    foreach ($TEXT as $key => $_) set_setting($key, trim((string) input($key)), 'content');
    flash('Promise banner saved.');
    redirect('promise-edit');
}

admin_head('Edit Promise banner', 'home-sections', 'The big lowercase banner near the bottom of the home page.');
?>
<form method="post" action="promise-edit">
  <?= csrf_field() ?>
  <div class="page-actions">
    <a class="btn btn-ghost" href="home-sections">← Home Sections</a>
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="../" target="_blank"><?= aicon('eye') ?> Preview store</a>
    <button class="btn btn-primary">Save banner</button>
  </div>

  <div class="a-card">
    <div class="hd"><h2>Promise banner</h2><span class="muted" style="font-size:12.5px">The full-width call-out near the bottom of the home page — clear a field to hide that part.</span></div>
    <div class="bd">
      <div class="f-row">
      <?php foreach ($TEXT as $key => [$type,$label,$def,$hint]): $full = $type === 'textarea'; ?>
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

  <div class="page-actions"><div class="spacer"></div><button class="btn btn-primary">Save banner</button></div>
</form>
<?php admin_foot();
