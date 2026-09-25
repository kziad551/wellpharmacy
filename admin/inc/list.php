<?php
/* ============================================================
   WELL PHARMACY admin — shared list paging (infinite scroll)

   Every admin list page loads one page of rows at a time instead of dumping the
   whole table into the HTML (the products table alone was 1,730 rows / ~200,000px
   tall). The browser then asks for the next page as the operator scrolls.

   A page using this does three things:

     $PER   = list_per();
     $page  = list_page();
     $list  = rows("SELECT … LIMIT $PER OFFSET " . list_offset(), $args);
     if (list_partial()) { foreach ($list as $r) render_row($r); exit; }   // AJAX slice
     …normal page…
     list_sentinel($total, $page);                                          // loader anchor

   list_sentinel() prints the element the front-end watches; admin.js appends the
   next slice to the same <tbody> when it scrolls into view.
   ============================================================ */

/** rows per slice */
function list_per(): int { return 20; }

/** 1-based page number from the query string */
function list_page(): int { return max(1, (int) input('page', 1)); }

/** SQL OFFSET for the current page */
function list_offset(): int { return (list_page() - 1) * list_per(); }

/** true when the browser is asking for just the next slice of rows */
function list_partial(): bool { return input('partial') === '1'; }

/**
 * Loader anchor placed right after the table.
 * $total = total matching records, $page = the page just rendered.
 * Prints nothing once every record has been sent.
 */
function list_sentinel(int $total, int $page): void {
    if ($page * list_per() >= $total) return;          // nothing left to fetch
    $qs = $_GET;
    unset($qs['partial']);
    $qs['page'] = $page + 1;
    $base = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php');
    echo '<div class="list-more" data-list-more data-next="' . e($base . '?' . http_build_query($qs) . '&partial=1') . '">'
       . '<span class="list-more-spin" aria-hidden="true"></span>'
       . '<button class="btn btn-ghost btn-sm" type="button" data-list-more-btn>Load more</button>'
       . '</div>';
}

/** "Showing 20 of 1,730" caption for a list header */
function list_count_label(int $total, string $noun, ?string $plural = null): string {
    $plural = $plural ?? $noun . 's';
    return number_format($total) . ' ' . ($total === 1 ? $noun : $plural);
}

/* ============================================================
   One search toolbar, used by every admin list page.

   Before this the pages disagreed with each other: Products had a bare input,
   Categories/Brands/Journal/Coupons/Customers each drew their own "Search" +
   "Clear" buttons, and Orders had an input with neither. Same job, four looks.

   The shared control is an input with the magnifier inside it and a clear "×"
   that only appears while a search is active. Enter submits — no button takes up
   a row of its own, which is what made the phone layout look so heavy.

     $action      page to submit to, e.g. 'products'
     $q           the current query
     $placeholder field placeholder
     $extra       extra controls rendered inside the form (e.g. the sort select)
     $keep        query params to carry through as hidden fields (e.g. a status filter)
   ============================================================ */
function admin_search(string $action, string $q, string $placeholder = 'Search…', string $extra = '', array $keep = []): void {
    $q = trim($q);
    ?>
    <form class="toolbar" method="get" action="<?= e($action) ?>" role="search">
      <?php foreach ($keep as $k => $v): if ($v === '' || $v === null) continue; ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e((string) $v) ?>">
      <?php endforeach; ?>
      <div class="tb-field">
        <span class="tb-ic" aria-hidden="true"><?= aicon('search') ?></span>
        <input class="input tb-search" type="search" name="q" value="<?= e($q) ?>"
               placeholder="<?= e($placeholder) ?>" aria-label="<?= e($placeholder) ?>">
        <?php if ($q !== ''): ?>
          <?php /* keep every other active filter; only the query is cleared */
                $rest = $_GET; unset($rest['q'], $rest['page'], $rest['partial']);
                $clearHref = $action . ($rest ? '?' . http_build_query($rest) : ''); ?>
          <a class="tb-clear" href="<?= e($clearHref) ?>" aria-label="Clear search" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>
      <?= $extra ?>
    </form>
    <?php
}

/* ============================================================
   RETURN CONTEXT ("ret") — don't lose the operator's place on save

   Saving an edit used to end in redirect('products'), which threw away the
   search, the category/brand filter and the row they came from. They landed at
   the top of the unfiltered list and had to hunt for the record again just to
   make a second change. Two pieces fix that:

     list pages  stamp their active filters onto every Edit link   (admin_here_qs)
     edit pages  carry that through the POST and hand it to Back   (admin_ret_*)

   Saving now returns to the SAME edit screen. "Save & back to list" is the
   explicit way out, and it lands on the filtered list, not a bare one.

   The value rides in on the query string, so it is never trusted: the page part
   must be one of ADMIN_RET_PAGES and the query is rebuilt from parsed pairs, so
   "//evil.com", "javascript:" and header injection cannot survive the round trip.
   'page' is dropped deliberately — these lists are infinite-scroll, so page=3 on
   its own would render rows 41-60 with nothing above them.
   ============================================================ */
const ADMIN_RET_PAGES = ['products','brands','categories','coupons','journal','pages','orders',
                         'customers','messages','home-sections','subscribers','restock','social',
                         'appearance','dashboard'];

/** Reduce a return target to "<known page>[?<safe query>]", or '' if it isn't one. */
function admin_ret_clean(string $raw): string {
    $raw = trim($raw);
    if ($raw === '' || strlen($raw) > 300) return '';
    $bits = explode('?', $raw, 2);
    $page = $bits[0];
    if (!in_array($page, ADMIN_RET_PAGES, true)) return '';
    if (($bits[1] ?? '') === '') return $page;
    parse_str($bits[1], $qs);
    unset($qs['partial'], $qs['page'], $qs['ret']);
    foreach ($qs as $k => $v) if (!is_scalar($v) || (string) $v === '') unset($qs[$k]);
    return $qs ? $page . '?' . http_build_query($qs) : $page;
}

/** The list context this edit page was opened from, already validated. '' when there is none. */
function admin_ret(): string { return admin_ret_clean((string) input('ret', '')); }

/** Hidden field so the context survives the form POST. */
function admin_ret_field(): string {
    $r = admin_ret();
    return $r === '' ? '' : '<input type="hidden" name="ret" value="' . e($r) . '">';
}

/** Where "Back" and "Save & back to list" should land. */
function admin_back_href(string $fallback): string {
    $r = admin_ret();
    return $r !== '' ? $r : $fallback;
}

/** "&ret=..." to re-attach when an edit page redirects to itself; '' when there is no context. */
function admin_ret_qs(string $sep = '&'): string {
    $r = admin_ret();
    return $r === '' ? '' : $sep . 'ret=' . rawurlencode($r);
}

/** This list page plus its active filters, for stamping onto Edit links. */
function admin_here(): string {
    $qs = $_GET;
    unset($qs['partial'], $qs['page'], $qs['ret']);
    $base = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php');
    return $qs ? $base . '?' . http_build_query($qs) : $base;
}

/** "&ret=..." (pass '?' for the first param) to append to an Edit link on a list page. */
function admin_here_qs(string $sep = '&'): string { return $sep . 'ret=' . rawurlencode(admin_here()); }
