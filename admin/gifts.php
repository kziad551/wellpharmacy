<?php
/* ============================================================
   Free gifts: every "gift with purchase" offer in one list.

   An offer belongs to its product and is edited in that product's
   "Free gift" card (product-edit#gift), so there is no gift-edit
   page. This list is for seeing what runs when, switching offers
   on and off, and catching broken ones (gift product deleted, or
   no gifts left) under Problems.
   ============================================================ */
require __DIR__ . '/inc/layout.php';
require_once dirname(__DIR__) . '/inc/gifts.php';

/* code deployed before db/phase4-gifts-popups.sql: say so instead of a blank 500 */
if (!admin_gifts_ready()) {
    admin_head('Free gifts', 'gifts', 'Not set up yet');
    echo '<div class="a-card"><div class="bd" style="padding:0"><div class="empty"><b style="color:var(--a-ink)">Free gifts are not set up yet.</b><br>'
       . 'The site database needs its one-time update first (db/phase4-gifts-popups.sql).</div></div></div>';
    admin_foot();
    exit;
}

/* toggle / remove land back on the list exactly as it was filtered (ret = admin_here()) */
if (is_post() && in_array(input('action'), ['toggle', 'delete'], true)) {
    csrf_check();
    $back = admin_back_href('gifts');
    $o = row("SELECT g.id, g.active, p.name FROM product_gifts g LEFT JOIN products p ON p.id = g.product_id WHERE g.id = ?", [(int) input('id')]);
    if (!$o) { flash('That offer no longer exists.', 'err'); redirect($back); }
    $pn = (string) ($o['name'] ?? '');
    if (input('action') === 'toggle') {
        /* the wanted state is posted (not "flip it"), so a double click can't undo itself */
        $want = input('on') === '1' ? 1 : 0;
        q("UPDATE product_gifts SET active = ? WHERE id = ?", [$want, (int) $o['id']]);
        flash('Free gift switched ' . ($want ? 'on' : 'off') . ($pn !== '' ? ' for ' . $pn : '') . '.');
    } else {
        q("DELETE FROM product_gifts WHERE id = ?", [(int) $o['id']]);
        flash('Free gift offer removed' . ($pn !== '' ? ' from ' . $pn : '') . '. The product itself is unchanged.');
    }
    redirect($back);
}

$TABS = ['' => 'All', 'live' => 'Live', 'scheduled' => 'Scheduled', 'ended' => 'Ended', 'off' => 'Off', 'problems' => 'Problems'];
$rawF   = input('status');
$filter = is_string($rawF) && isset($TABS[$rawF]) ? $rawF : '';
$rawQ   = input('q');
$search = is_string($rawQ) ? trim($rawQ) : '';

/* gift_status() in SQL, so tabs can filter and page. Same order of tests as the PHP
   version (off, missing, scheduled, ended, out, live); "today" is PHP's Beirut date. */
$today = date('Y-m-d');
$GST = "CASE
    WHEN t.active = 0 THEN 'off'
    WHEN (t.gift_type = 'custom' AND TRIM(t.gift_name) = '')
      OR (t.gift_type <> 'custom' AND (t.gift_product_id IS NULL OR t.gift_product_id = '' OR t.gp_name IS NULL)) THEN 'missing'
    WHEN t.starts_on IS NOT NULL AND t.starts_on > ? THEN 'scheduled'
    WHEN t.ends_on IS NOT NULL AND t.ends_on < ? THEN 'ended'
    WHEN (t.gift_type = 'custom' AND t.stock IS NOT NULL AND t.stock <= 0)
      OR (t.gift_type <> 'custom' AND t.gp_stock <= 0) THEN 'out'
    ELSE 'live' END";
$FROM = "FROM (SELECT t.*, $GST AS gst, p.name AS p_name, p.brand AS p_brand, p.image AS p_image, p.status AS p_status
               FROM (" . gift_select_sql() . ") t LEFT JOIN products p ON p.id = t.product_id) s";
$base = [$today, $today];

$cond = []; $args = $base;
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $cond[] = '(s.p_name LIKE ? OR s.gp_name LIKE ? OR s.gift_name LIKE ? OR s.product_id LIKE ?)';
    array_push($args, $like, $like, $like, $like);
}
$counts = [];
foreach (rows("SELECT s.gst, COUNT(*) n $FROM" . ($cond ? ' WHERE ' . implode(' AND ', $cond) : '') . " GROUP BY s.gst", $args) as $c)
    $counts[$c['gst']] = (int) $c['n'];
$all = array_sum($counts);

if ($filter === 'problems')  $cond[] = "s.gst IN ('missing','out')";
elseif ($filter !== '')      { $cond[] = 's.gst = ?'; $args[] = $filter; }
$where = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';

$PER   = list_per();
$page  = list_page();
$found = (int) val("SELECT COUNT(*) $FROM $where", $args);
$list  = rows("SELECT s.* $FROM $where
               ORDER BY FIELD(s.gst,'live','scheduled','out','missing','ended','off'), s.p_name, s.id
               LIMIT $PER OFFSET " . list_offset(), $args);

/** "Oct 5 to Oct 31, 2026" / "From Oct 5, 2026" / "Until Oct 31, 2026", or '' with no dates */
function offer_dates_label(array $r): string {
    $s = $r['starts_on'] ? strtotime($r['starts_on']) : null;
    $e = $r['ends_on'] ? strtotime($r['ends_on']) : null;
    if ($s && $e) return date(date('Y', $s) === date('Y', $e) ? 'M j' : 'M j, Y', $s) . ' to ' . date('M j, Y', $e);
    if ($s) return 'From ' . date('M j, Y', $s);
    if ($e) return 'Until ' . date('M j, Y', $e);
    return '';
}

/** One offer row, shared by the first paint and each infinite-scroll slice. */
function offer_row(array $r): void {
    [$sl, $sc] = admin_gift_state(gift_status($r));
    $g      = gift_normalise($r);                  // null = nothing a shopper could be given
    $custom = $r['gift_type'] === 'custom';
    $parent = $r['p_name'] !== null;
    $pname  = $parent ? (string) $r['p_name'] : (string) $r['product_id'];
    $edit   = 'product-edit?id=' . rawurlencode((string) $r['product_id']) . admin_here_qs() . '#gift';
    $rule   = gift_rule_label($g ?: ['qty' => max(1, (int) $r['gift_qty']), 'per_unit' => (int) $r['per_unit'] ? 1 : 0]);
    $dates  = offer_dates_label($r);
    $ret    = '<input type="hidden" name="ret" value="' . e(admin_here()) . '">';
    $gimg   = $g ? ($custom ? (string) $r['gift_image'] : ((string) $r['gp_image'] !== '' ? (string) $r['gp_image'] : 'uploads/photo-pending.png')) : '';
    ?>
  <tr>
    <td class="c-img"><img class="thumb thumb-fit" src="<?= e(asrc((string) $r['p_image'] !== '' ? (string) $r['p_image'] : 'uploads/photo-pending.png')) ?>" alt="" loading="lazy" onerror="this.style.visibility='hidden'"></td>
    <td class="c-main">
      <?php if ($parent): ?>
        <a class="nm" href="<?= e($edit) ?>"><?= e($pname) ?></a>
        <div class="br"><?= e($r['p_brand']) ?> &middot; <span class="faint"><?= e($r['product_id']) ?></span><?= $r['p_status'] === 'draft' ? ' &middot; draft' : '' ?></div>
      <?php else: ?>
        <span class="nm">Product deleted</span>
        <div class="br faint"><?= e($r['product_id']) ?></div>
      <?php endif; ?>
    </td>
    <td data-label="Gift">
      <div class="gl-gift">
        <?php if ($gimg !== ''): ?><img class="gl-gimg" src="<?= e(asrc($gimg)) ?>" alt="" loading="lazy" onerror="this.style.visibility='hidden'">
        <?php else: ?><span class="gl-gimg gl-gico<?= $g ? '' : ' gl-gbad' ?>"><?= aicon('gift') ?></span><?php endif; ?>
        <span class="gl-gtxt">
          <?php if ($g): ?>
            <b><?= e($g['name']) ?></b>
            <span class="gl-tag"><?= $custom ? 'Custom' : ((string) $r['gift_product_id'] === (string) $r['product_id'] ? 'Store product, same one (buy one, get one)' : 'Store product' . ($r['gp_status'] === 'draft' ? ', draft' : '')) ?></span>
          <?php elseif ($custom): ?>
            <b class="gl-bad">No gift name</b><span class="gl-tag">Custom</span>
          <?php else: ?>
            <b class="gl-bad"><?= $r['gift_product_id'] ? 'Gift product deleted' : 'No gift product chosen' ?></b><span class="gl-tag">Store product<?= $r['gift_product_id'] ? ': ' . e($r['gift_product_id']) : '' ?></span>
          <?php endif; ?>
        </span>
      </div>
    </td>
    <td data-label="Rule"><?= e($rule) ?></td>
    <td data-label="Dates"><?= $dates !== '' ? e($dates) : '<span class="faint">No dates</span>' ?></td>
    <td data-label="Left">
      <?php if (!$g): ?><span class="faint">n/a</span>
      <?php elseif ($g['left'] === null): ?><span class="faint">Unlimited</span>
      <?php else: ?><span class="<?= $g['left'] <= 0 ? 'gl-bad' : '' ?>"><?= number_format($g['left']) ?></span><?php endif; ?>
    </td>
    <td data-label="Status"><span class="pill pill-<?= e($sc) ?>"><?= e($sl) ?></span></td>
    <td data-label="On">
      <form method="post" action="gifts" class="gl-tog-f">
        <?= csrf_field() ?><?= $ret ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="on" value="<?= (int) $r['active'] ? 0 : 1 ?>">
        <button class="gl-tog<?= (int) $r['active'] ? ' is-on' : '' ?>" role="switch" aria-checked="<?= (int) $r['active'] ? 'true' : 'false' ?>" aria-label="Free gift on <?= e($pname) ?>" title="<?= (int) $r['active'] ? 'On: click to switch off' : 'Off: click to switch on' ?>"><span></span></button>
      </form>
    </td>
    <td class="c-act" style="text-align:right;white-space:nowrap">
      <?php if ($parent): ?><a class="btn btn-ghost btn-sm" href="<?= e($edit) ?>">Edit</a><?php endif; ?>
      <form method="post" action="gifts" style="display:inline" onsubmit="return confirm(<?= e(json_encode('Remove the free gift offer on "' . $pname . '"? The product itself is not changed.', JSON_INVALID_UTF8_SUBSTITUTE)) ?>)">
        <?= csrf_field() ?><?= $ret ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <button class="btn btn-bad btn-sm">Remove offer</button>
      </form>
    </td>
  </tr>
<?php }

if (list_partial()) { foreach ($list as $r) offer_row($r); exit; }

$sub = list_count_label($all, 'offer') . (!empty($counts['live']) ? ', ' . $counts['live'] . ' live now' : '');
admin_head('Free gifts', 'gifts', $sub);
?>
<div class="page-actions" style="flex-wrap:wrap;gap:8px">
  <?php foreach ($TABS as $k => $lbl):
        $n = $k === '' ? $all : ($k === 'problems' ? ($counts['missing'] ?? 0) + ($counts['out'] ?? 0) : ($counts[$k] ?? 0));
        $qs = http_build_query(array_filter(['status' => $k, 'q' => $search], 'strlen')); ?>
    <a class="btn <?= $filter === $k ? 'btn-primary' : 'btn-ghost' ?> btn-sm<?= $k === 'problems' && $n ? ' gl-tab-bad' : '' ?>" href="gifts<?= $qs !== '' ? '?' . e($qs) : '' ?>"><?= e($lbl) ?> (<?= $n ?>)</a>
  <?php endforeach; ?>
  <div class="spacer"></div>
  <?php admin_search('gifts', $search, 'Search product or gift...', '', ['status' => $filter]); ?>
</div>

<div class="a-card">
  <div class="bd" style="padding:0">
    <?php if (!$list && $filter === '' && $search === ''): ?>
      <div class="empty">
        <b style="color:var(--a-ink)">No free gifts yet.</b><br>
        To add one, open a product, switch on <b>Free gift</b> in its Free gift card, choose the gift and save.<br>
        <a class="btn btn-ghost btn-sm" href="products" style="margin-top:14px"><?= aicon('box') ?> Go to products</a>
      </div>
    <?php elseif (!$list): ?>
      <div class="empty">No offers match<?= $filter !== '' ? ' in ' . e($TABS[$filter]) : '' ?><?= $search !== '' ? ' &ldquo;' . e($search) . '&rdquo;' : '' ?>.<br><a href="gifts">Show all offers</a></div>
    <?php else: ?>
    <table class="a-table">
      <thead><tr><th></th><th>Product</th><th>Gift</th><th>Rule</th><th>Dates</th><th>Left</th><th>Status</th><th>On</th><th></th></tr></thead>
      <tbody><?php foreach ($list as $r) offer_row($r); ?></tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php list_sentinel($found, $page); ?>
</div>
<?php if ($all || $filter !== '' || $search !== ''): ?>
  <p class="hint" style="margin-top:12px">To add an offer, open a product in <a href="products" style="text-decoration:underline">Products</a> and switch on its <b>Free gift</b> card.</p>
<?php endif; ?>

<style>
  .gl-gift{display:flex;align-items:center;gap:10px}
  /* desktop only: in the phone card layout the column is narrower than this (166px at 320px) */
  @media(min-width:761px){ .gl-gift{min-width:190px} }
  .gl-gimg{width:38px;height:38px;flex:none;border-radius:8px;object-fit:contain;background:#fff;border:1px solid var(--a-border2)}
  .gl-gico{display:flex;align-items:center;justify-content:center;background:var(--a-primary-tint);color:var(--a-primary)}
  .gl-gico svg{width:19px;height:19px}
  .gl-gico.gl-gbad{background:var(--a-bad-tint);color:var(--a-bad)}
  .gl-gtxt{display:flex;flex-direction:column;min-width:0;line-height:1.35}
  .gl-gtxt b{font-weight:600;overflow-wrap:anywhere}
  .gl-tag{font-size:11.5px;color:var(--a-faint)}
  .gl-bad{color:var(--a-bad)}
  .gl-tab-bad:not(.btn-primary){color:var(--a-bad);border-color:var(--a-bad-tint)}
  .gl-tog-f{display:inline-flex;margin:0}
  .gl-tog{position:relative;width:40px;height:23px;flex:none;padding:0;border:0;border-radius:9999px;background:#D9D2C5;cursor:pointer;transition:background .15s}
  .gl-tog span{position:absolute;top:3px;left:3px;width:17px;height:17px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:transform .15s}
  .gl-tog.is-on{background:var(--a-good)}
  .gl-tog.is-on span{transform:translateX(17px)}
  .gl-tog:focus-visible{outline:2px solid var(--a-primary);outline-offset:2px}
</style>
<?php admin_foot();
