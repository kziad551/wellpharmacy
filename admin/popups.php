<?php
require __DIR__ . '/inc/layout.php';
require_once dirname(__DIR__) . '/inc/popups.php';

/* switch on/off, duplicate, delete: every form carries ret, so the operator
   lands back on the same tab / search they acted from */
if (is_post()) {
    csrf_check();
    $pid = (int) input('id');
    $p   = popup_by_id($pid);
    if (!$p) { flash('Popup not found.', 'err'); redirect(admin_back_href('popups')); }
    $act = input('action');
    switch (is_string($act) ? $act : '') {
        case 'toggle':
            q("UPDATE popups SET active = 1 - active WHERE id = ?", [$pid]);
            flash(((int) $p['active'] ? 'Switched off: ' : 'Switched on: ') . $p['name']);
            break;
        case 'duplicate':
            /* a copy starts OFF, so next year's Halloween can be prepared without going live */
            $cols = ['theme','eyebrow','headline','body','image','cta_label','cta_url','coupon_code','color_bg','color_accent',
                     'color_ink','effect','starts_at','ends_at','frequency','delay_sec','pages','priority'];
            $data = array_intersect_key($p, array_flip($cols));
            $data['name']   = mb_substr('Copy of ' . $p['name'], 0, 120);
            $data['active'] = 0;
            q("INSERT INTO popups (" . implode(',', array_keys($data)) . ") VALUES (:" . implode(',:', array_keys($data)) . ")", $data);
            flash('Copied. The copy is off: set its dates, then switch it on.');
            redirect('popup-edit?id=' . (int) last_id() . admin_ret_qs());
        case 'delete':
            q("DELETE FROM popups WHERE id = ?", [$pid]);
            flash('Popup deleted.');
            break;
    }
    redirect(admin_back_href('popups'));
}

$now = date('Y-m-d H:i:s');
$STATUS = ['live' => 'Live now', 'scheduled' => 'Scheduled', 'ended' => 'Ended', 'off' => 'Off'];
$STATUS_PILL = ['live' => 'good', 'scheduled' => 'info', 'ended' => 'muted', 'off' => 'muted'];
/* same rules as popup_status(), with PHP Beirut time as the parameter */
$STATUS_SQL = [
    'live'      => ["active = 1 AND (starts_at IS NULL OR starts_at <= ?) AND (ends_at IS NULL OR ends_at > ?)", [$now, $now]],
    'scheduled' => ["active = 1 AND starts_at IS NOT NULL AND starts_at > ?", [$now]],
    'ended'     => ["active = 1 AND (starts_at IS NULL OR starts_at <= ?) AND ends_at IS NOT NULL AND ends_at <= ?", [$now, $now]],
    'off'       => ["active = 0", []],
];

$filter = is_string($f = input('status')) && isset($STATUS[$f]) ? $f : '';
$search = is_string($s = input('q')) ? trim($s) : '';

$where = []; $args = [];
if ($filter !== '') { $where[] = $STATUS_SQL[$filter][0]; array_push($args, ...$STATUS_SQL[$filter][1]); }
if ($search !== '') { $where[] = "(name LIKE ? OR headline LIKE ? OR coupon_code LIKE ?)"; $like = "%$search%"; array_push($args, $like, $like, $like); }
$wsql  = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$PER   = list_per();
$page  = list_page();
$found = (int) val("SELECT COUNT(*) FROM popups" . $wsql, $args);
/* live first, then scheduled, ended, off; inside each, the order the storefront picks in */
$list  = rows("SELECT * FROM popups" . $wsql . "
               ORDER BY CASE WHEN active = 0 THEN 3 WHEN starts_at IS NOT NULL AND starts_at > ? THEN 1
                             WHEN ends_at IS NOT NULL AND ends_at <= ? THEN 2 ELSE 0 END,
                        priority DESC, starts_at DESC, id DESC
               LIMIT $PER OFFSET " . list_offset(), array_merge($args, [$now, $now]));

$counts = ['' => (int) val("SELECT COUNT(*) FROM popups")];
foreach ($STATUS_SQL as $k => [$sql, $a]) $counts[$k] = (int) val("SELECT COUNT(*) FROM popups WHERE $sql", $a);

/* which live popup visitors actually get: home page (any scope) and every other page ('all' only) */
$winHome = popup_pick(true, $now); $winAll = popup_pick(false, $now);
$WIN = ['home' => (int) ($winHome['id'] ?? 0), 'all' => (int) ($winAll['id'] ?? 0)];

function popups_when(?string $dt): string { return date('M j, Y H:i', strtotime((string) $dt)); }

/** One popup row: shared by the first paint and each infinite-scroll slice. */
function popups_row(array $r, string $now, array $win, array $label, array $pill): void {
    $st  = popup_status($r, $now);
    $pre = popup_preset((string) $r['theme']);
    $id  = (int) $r['id'];
    $ret = '<input type="hidden" name="ret" value="' . e(admin_here()) . '">';
    $freq = POPUP_FREQ; $pages = POPUP_PAGES;
    $note = '';
    if ($st === 'live') {
        $home = $win['home'] === $id; $all = $win['all'] === $id;
        $note = $home && $all ? 'Showing to visitors' : ($home ? 'Showing on the home page' : ($all ? 'Showing, except on the home page' : 'Waiting: another live popup outranks it'));
    } elseif ($st === 'scheduled') {
        $note = 'Starts ' . popups_when($r['starts_at']);
    } ?>
  <tr>
    <td class="c-img">
      <?php if ($r['image'] !== ''): ?>
        <img class="thumb" src="<?= e(asrc($r['image'])) ?>" alt="" loading="lazy" onerror="this.style.visibility='hidden'">
      <?php else: ?>
        <span class="thumb pp-thumb" style="<?= e(popup_style($r)) ?>" aria-hidden="true"><?= $pre['art'] ?></span>
      <?php endif; ?>
    </td>
    <td class="c-main">
      <a class="nm" href="popup-edit?id=<?= $id ?><?= e(admin_here_qs()) ?>"><?= e($r['name']) ?></a>
      <div class="br"><?= e($pre['label']) ?><?= $r['headline'] !== '' ? ' &middot; <span class="faint">' . e(mb_strimwidth($r['headline'], 0, 70, '...')) . '</span>' : '' ?></div>
    </td>
    <td data-label="Runs">
      <?= $r['starts_at'] ? e(popups_when($r['starts_at'])) : '<span class="faint">Right away</span>' ?>
      <div class="br"><?= $r['ends_at'] ? 'until ' . e(popups_when($r['ends_at'])) : 'no end date' ?></div>
    </td>
    <td data-label="Shows">
      <?= e($freq[$r['frequency']] ?? $r['frequency']) ?>
      <div class="br"><?= e($pages[$r['pages']] ?? $r['pages']) ?>, after <?= (int) $r['delay_sec'] ?>s<?= (int) $r['priority'] ? ', priority ' . (int) $r['priority'] : '' ?></div>
    </td>
    <td data-label="Status">
      <span class="pill pill-<?= $pill[$st] ?>"><?= e($label[$st]) ?></span>
      <?php if ($note !== ''): ?><div class="br"><?= e($note) ?></div><?php endif; ?>
    </td>
    <td data-label="Active">
      <form method="post" action="popups" style="display:inline">
        <?= csrf_field() ?><?= $ret ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $id ?>">
        <button class="pill <?= $r['active'] ? 'pill-good' : 'pill-muted' ?>" style="border:0;cursor:pointer" title="<?= $r['active'] ? 'Switch off' : 'Switch on' ?>"><?= $r['active'] ? 'On' : 'Off' ?></button>
      </form>
    </td>
    <td class="c-act" style="text-align:right;white-space:nowrap">
      <a class="btn btn-ghost btn-sm" href="../?popup_preview=<?= $id ?>" target="_blank" rel="noopener" title="Open the home page with this popup (only you see it)">Preview</a>
      <form method="post" action="popups" style="display:inline">
        <?= csrf_field() ?><?= $ret ?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-ghost btn-sm" title="Copy as a new popup that starts off">Duplicate</button>
      </form>
      <a class="btn btn-ghost btn-sm" href="popup-edit?id=<?= $id ?><?= e(admin_here_qs()) ?>">Edit</a>
      <form method="post" action="popups" style="display:inline" onsubmit="return confirm(<?= e(json_encode('Delete the popup "' . $r['name'] . '"?', JSON_INVALID_UTF8_SUBSTITUTE)) ?>)">
        <?= csrf_field() ?><?= $ret ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-bad btn-sm">Delete</button>
      </form>
    </td>
  </tr>
<?php }

if (list_partial()) { foreach ($list as $r) popups_row($r, $now, $WIN, $STATUS, $STATUS_PILL); exit; }

admin_head('Popups', 'popups', list_count_label($counts[''], 'popup'));
?>
<style>
  .pp-thumb{background:radial-gradient(closest-side,rgba(var(--cp-ac-rgb),.6),rgba(var(--cp-ac-rgb),0)),var(--cp-bg);font-size:30px;line-height:1}
</style>
<div class="page-actions" style="flex-wrap:wrap;gap:8px">
  <a class="btn <?= $filter === '' ? 'btn-primary' : 'btn-ghost' ?> btn-sm" href="popups">All (<?= $counts[''] ?>)</a>
  <?php foreach ($STATUS as $k => $lbl): ?>
    <a class="btn <?= $filter === $k ? 'btn-primary' : 'btn-ghost' ?> btn-sm" href="popups?status=<?= e($k) ?>"><?= e($lbl) ?> (<?= $counts[$k] ?>)</a>
  <?php endforeach; ?>
</div>
<div class="page-actions">
  <?php admin_search('popups', $search, 'Search name, headline, code...', '', ['status' => $filter]); ?>
  <div class="spacer"></div>
  <a class="btn btn-primary" href="popup-edit<?= e(admin_here_qs('?')) ?>"><?= aicon('plus') ?> Add popup</a>
</div>

<div class="a-card">
  <div class="bd" style="padding:0">
    <?php if (!$list): ?>
      <div class="empty"><?php if ($search !== ''): ?>No popups match that search.<?php elseif ($filter !== ''): ?>No popups here right now.<?php else: ?>No popups yet. Add one for the next occasion (Halloween, Mother's Day, Ramadan...) and schedule it ahead of time.<?php endif; ?></div>
    <?php else: ?>
    <table class="a-table">
      <thead><tr><th></th><th>Popup</th><th>Runs</th><th>Shows</th><th>Status</th><th>Active</th><th></th></tr></thead>
      <tbody><?php foreach ($list as $r) popups_row($r, $now, $WIN, $STATUS, $STATUS_PILL); ?></tbody>
    </table>
    <?php endif; ?>
  </div>
  <?php list_sentinel($found, $page); ?>
</div>
<p class="hint" style="margin-top:14px">One popup shows per page view: the live one with the highest priority wins. The newsletter popup steps aside on those pages, and the first time someone sees a seasonal popup it also waits until their next visit. Cart, checkout and account pages never show popups.</p>
<?php admin_foot();
