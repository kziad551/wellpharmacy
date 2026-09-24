<?php
require __DIR__ . '/inc/layout.php';

if (is_post() && input('action') === 'delete') {
    csrf_check();
    // New Arrivals & Category are fixed singletons — never delete them (toggle hidden instead)
    q("DELETE FROM home_sections WHERE id = ? AND type NOT IN ('new_arrivals','category')", [(int) input('id')]);
    flash('Section deleted.');
    redirect('home-sections');
}
if (is_post() && input('action') === 'toggle') {
    csrf_check();
    q("UPDATE home_sections SET enabled = 1 - enabled WHERE id = ?", [(int) input('id')]);
    redirect('home-sections');
}

$PER   = list_per();
$page  = list_page();
$total = (int) val("SELECT COUNT(*) FROM home_sections");
$list  = rows("SELECT * FROM home_sections ORDER BY sort, id LIMIT $PER OFFSET " . list_offset());

/** One home-section row — shared by the first paint and each infinite-scroll slice. */
function home_section_row(array $s): void {
    $isBrand = $s['type'] === 'brand';
    $isFixed = in_array($s['type'], ['new_arrivals','category'], true);   // fixed singletons — can't be deleted
    $name = $s['title'] !== '' ? $s['title'] : ($isBrand ? ($s['brand'] ?: '(no brand)') : ($s['type'] === 'category' ? 'Shop by Category' : 'New Arrivals'));
?>
      <tr>
        <td data-label="Sort"><span class="faint"><?= (int)$s['sort'] ?></span></td>
        <td class="c-main">
          <a class="nm" href="home-section-edit?id=<?= (int)$s['id'] ?>"><?= e($name) ?></a>
          <?php if ($s['eyebrow'] || $s['subtitle']): ?><div class="br"><?= e($s['eyebrow']) ?><?= $s['eyebrow'] && $s['subtitle'] ? ' · ' : '' ?><?= e($s['subtitle']) ?></div><?php endif; ?>
          <?php if (!$s['show_title']): ?><div class="br"><span class="faint">title hidden</span></div><?php endif; ?>
        </td>
        <td data-label="Type"><?php if ($isBrand): ?><span class="pill pill-muted">Brand</span><?php elseif ($s['type']==='mixed'): ?><span class="pill pill-muted">Mixed</span><?php elseif ($s['type']==='category'): ?><span class="pill pill-warn">Category</span><?php else: ?><span class="pill pill-good">New Arrivals</span><?php endif; ?></td>
        <td data-label="Shows"><?= $s['item_count'] > 0 ? (int)$s['item_count'] . ' items' : 'all items' ?></td>
        <td data-label="Row"><?= (int)$s['cols'] ?>-up</td>
        <td data-label="Status">
          <form method="post" action="home-sections" style="display:inline">
            <?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="pill <?= $s['enabled'] ? 'pill-good' : 'pill-muted' ?>" style="border:0;cursor:pointer" title="Click to toggle"><?= $s['enabled'] ? 'Visible' : 'Hidden' ?></button>
          </form>
        </td>
        <td class="c-act" style="text-align:right;white-space:nowrap">
          <a class="btn btn-ghost btn-sm" href="home-section-edit?id=<?= (int)$s['id'] ?>">Edit</a>
          <?php if (!$isFixed): ?>
          <form method="post" action="home-sections" style="display:inline" onsubmit="return confirm('Delete this section?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="btn btn-bad btn-sm">Delete</button>
          </form>
          <?php else: ?><span class="pill pill-muted" title="Fixed section — toggle it hidden instead of deleting">Fixed</span><?php endif; ?>
        </td>
      </tr>
<?php }

if (list_partial()) { foreach ($list as $s) home_section_row($s); exit; }

admin_head('Home Sections', 'home-sections', list_count_label($total, 'section'));
?>
<div class="page-actions">
  <div class="spacer"></div>
  <a class="btn btn-primary" href="home-section-edit"><?= aicon('plus') ?> Add section</a>
</div>

<div class="a-card"><div class="bd" style="padding:14px 16px">
  <p class="hint" style="margin:0">The product sections on your homepage, top to bottom — lower <b>sort</b> shows first. <b>New Arrivals</b> pulls the products you flag in the product editor; a <b>Brand</b> section pulls every active product of that brand. Empty sections are skipped automatically. The <b>Hero banner</b> at the top is the big home-page header — edit its text, buttons, stats, badges and slide images there.</p>
</div></div>

<div class="a-card"><div class="bd" style="padding:0">
  <?php if (!$list): ?>
    <div class="empty">No sections yet. <a href="home-section-edit">Add one</a>.</div>
  <?php else: ?>
  <table class="a-table">
    <thead><tr><th>Sort</th><th>Section</th><th>Type</th><th>Shows</th><th>Row</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <tr>
        <td data-label="Sort"><span class="faint">top</span></td>
        <td class="c-main">
          <a class="nm" href="hero-edit">Hero banner</a>
          <div class="br">the big banner at the very top of the home page — text, buttons, stats, badges &amp; slides</div>
        </td>
        <td data-label="Type"><span class="pill pill-warn">Hero</span></td>
        <td data-label="Shows">top of page</td>
        <td data-label="Row">—</td>
        <td data-label="Status"><span class="pill pill-good">Always on</span></td>
        <td class="c-act" style="text-align:right;white-space:nowrap">
          <a class="btn btn-ghost btn-sm" href="hero-edit">Edit</a>
          <span class="pill pill-muted" title="Fixed section — the hero always shows">Fixed</span>
        </td>
      </tr>
    <?php foreach ($list as $s) home_section_row($s); ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php list_sentinel($total, $page); ?>
</div>
<?php admin_foot();
