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
          <a class="tb-clear" href="<?= e($action) ?>" aria-label="Clear search" title="Clear search">&times;</a>
        <?php endif; ?>
      </div>
      <?= $extra ?>
    </form>
    <?php
}
