<?php
/* ============================================================
   WELL SHOP: free gift with purchase.
   One optional gift offer per product (table product_gifts, see
   db/phase4-gifts-popups.sql). The gift is either another catalog
   product (drafts allowed, so a gift-only SKU can stay hidden from
   the shop) or a custom item that is not sold at all.

   The gift is NEVER a cart line. Browsers only ever see it as a
   label on its parent product (data.php "gift" field); the order
   gets its gift lines from gifts_for_order(), computed server-side
   inside the place-order transaction.
   ============================================================ */
require_once __DIR__ . '/functions.php';

/* Lucide-style gift icon, same stroke language as the header icons.
   Inlined (not well_icon) because skincare.php?partial=1 and data.php
   run without inc/chrome.php. Keep in step with `gift` in chrome.js I. */
const GIFT_SVG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C9.5 3 11 5.5 12 8c1-2.5 2.5-5 4.5-5a2.5 2.5 0 0 1 0 5"/></svg>';

/** Synthetic order_items.product_id for a custom gift. Never '' (admin/order.php
    would link '' to the ADD-product form). Matches no products row on purpose. */
function gift_custom_pid(int $giftId): string { return 'gift-' . $giftId; }

/** Is the offer inside its date window today (Beirut, inclusive)? Ignores stock. */
function gift_in_window(array $g, ?string $today = null): bool {
    $today = $today ?? date('Y-m-d');
    if (!empty($g['starts_on']) && $g['starts_on'] > $today) return false;
    if (!empty($g['ends_on'])   && $g['ends_on']   < $today) return false;
    return true;
}

/**
 * Normalise a product_gifts row (optionally joined with the gift product as
 * gp_name / gp_brand / gp_image / gp_stock / gp_status) into the shape every
 * caller uses:
 *   ['id','product_id','type','gift_product_id','name','brand','image','note',
 *    'qty','per_unit','left' (int|null = unlimited)]
 * Returns null when the offer can't be shown (gift product deleted, empty name).
 */
function gift_normalise(array $g): ?array {
    $type = $g['gift_type'] === 'custom' ? 'custom' : 'product';
    if ($type === 'product') {
        if (empty($g['gift_product_id']) || !isset($g['gp_name']) || $g['gp_name'] === null) return null;
        $name = (string) $g['gp_name'];
        $brand = (string) ($g['gp_brand'] ?? '');
        $image = (string) ($g['gp_image'] ?? '');
        $left = max(0, (int) ($g['gp_stock'] ?? 0));
    } else {
        $name = trim((string) $g['gift_name']);
        if ($name === '') return null;
        $brand = '';
        $image = (string) $g['gift_image'];
        $left = $g['stock'] === null ? null : max(0, (int) $g['stock']);
    }
    return [
        'id'              => (int) $g['id'],
        'product_id'      => (string) $g['product_id'],
        'type'            => $type,
        'gift_product_id' => $type === 'product' ? (string) $g['gift_product_id'] : '',
        'name'            => $name,
        'brand'           => $brand,
        'image'           => $image,
        'note'            => trim((string) $g['note']),
        'qty'             => max(1, (int) $g['gift_qty']),
        'per_unit'        => (int) $g['per_unit'] ? 1 : 0,
        'left'            => $left,
    ];
}

/** SELECT used by the storefront and the admin list: offer + live gift product. */
function gift_select_sql(): string {
    return "SELECT g.*, gp.name AS gp_name, gp.brand AS gp_brand, gp.image AS gp_image,
                   gp.stock AS gp_stock, gp.status AS gp_status
            FROM product_gifts g
            LEFT JOIN products gp ON gp.id = g.gift_product_id";
}

/**
 * Every offer that is live right now AND still has gifts left, keyed by the
 * parent product id. One query per request (static cache), so data.php, the
 * PLP cards and the PDP can all call it freely.
 */
function gifts_live_map(): array {
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    $today = date('Y-m-d');
    try {
        $rows = rows(gift_select_sql() . "
            WHERE g.active = 1
              AND (g.starts_on IS NULL OR g.starts_on <= ?)
              AND (g.ends_on IS NULL OR g.ends_on >= ?)", [$today, $today]);
    } catch (Throwable $e) {
        return $map;            // table missing (migration not run yet): no gifts, no crash
    }
    foreach ($rows as $r) {
        $g = gift_normalise($r);
        if (!$g) continue;
        if ($g['left'] !== null && $g['left'] <= 0) continue;   // ran out: hide the offer
        $map[$g['product_id']] = $g;
    }
    return $map;
}

/** The live offer for one product, or null. */
function gift_for_product(string $pid): ?array {
    $m = gifts_live_map();
    return $m[$pid] ?? null;
}

/** Compact JSON shape for assets/data.php (`gift` on a product). Keys are short
    because data.php ships ~1,800 products on every page view.
    `l` = gifts left, only on limited offers: the bag (W.giftLines) caps its gift
    rows there, as gifts_for_order() does when the order is placed.
    `p` = the gift product's id, only on catalog gifts: the bag shares that stock
    with paid units of the same product and with every other product giving it. */
function gift_public(array $g): array {
    $out = ['n' => $g['name'], 'i' => $g['image'], 'q' => $g['qty'], 'u' => $g['per_unit']];
    if ($g['note'] !== '') $out['t'] = $g['note'];
    if ($g['left'] !== null) $out['l'] = $g['left'];
    if ($g['gift_product_id'] !== '') $out['p'] = $g['gift_product_id'];
    return $out;
}

/** How many gifts a purchase of $qty units earns. */
function gift_qty_for(array $g, int $qty): int {
    return $g['per_unit'] ? $g['qty'] * max(1, $qty) : $g['qty'];
}

/** Human line for the offer, e.g. "1 free with every unit" / "2 free per order". */
function gift_rule_label(array $g): string {
    return $g['qty'] . ' free ' . ($g['per_unit'] ? 'with every unit' : 'per order');
}

/**
 * Admin status of a raw product_gifts row joined via gift_select_sql():
 *   off | scheduled | ended | missing (gift product deleted / no name) | out | live
 */
function gift_status(array $row, ?string $today = null): string {
    $today = $today ?? date('Y-m-d');
    if (!(int) $row['active']) return 'off';
    $g = gift_normalise($row);
    if (!$g) return 'missing';
    if (!empty($row['starts_on']) && $row['starts_on'] > $today) return 'scheduled';
    if (!empty($row['ends_on'])   && $row['ends_on']   < $today) return 'ended';
    if ($g['left'] !== null && $g['left'] <= 0) return 'out';
    return 'live';
}

/**
 * Gift lines for an order. Call INSIDE the place-order transaction, AFTER every
 * paid line has been stock-checked and decremented, so a gift that is also in
 * the bag (or the gift of several products) sees the true remaining stock.
 *
 * $paid: list of ['pid' => product id, 'qty' => units actually taken].
 * Locks the offer rows and gift products FOR UPDATE and decrements gift stock.
 * Returns [lines, notes]. Each line:
 *   ['product_id','name','brand','image','qty','gift_for']
 * Notes are shopper-facing sentences for $_SESSION['order_note'].
 * A missing or exhausted gift never fails the order; it just adds a note.
 * (place-order passes only products whose offer is live, or whose gift row the
 * checkout page showed, so an offer that ran out before the page loaded adds no note.)
 */
function gifts_for_order(array $paid): array {
    $byPid = [];
    foreach ($paid as $l) {
        $pid = (string) ($l['pid'] ?? '');
        if ($pid === '') continue;
        $byPid[$pid] = ($byPid[$pid] ?? 0) + max(0, (int) ($l['qty'] ?? 0));
    }
    $lines = []; $notes = [];
    if (!$byPid) return [$lines, $notes];
    $today = date('Y-m-d');

    foreach ($byPid as $pid => $units) {
        if ($units <= 0) continue;
        $row = row("SELECT * FROM product_gifts
                    WHERE product_id = ? AND active = 1
                      AND (starts_on IS NULL OR starts_on <= ?)
                      AND (ends_on IS NULL OR ends_on >= ?)
                    FOR UPDATE", [$pid, $today, $today]);
        if (!$row) continue;
        $want = max(1, (int) $row['gift_qty']) * ((int) $row['per_unit'] ? $units : 1);

        if ($row['gift_type'] === 'product') {
            $gp = $row['gift_product_id']
                ? row("SELECT id, name, brand, image, stock FROM products WHERE id = ? FOR UPDATE", [$row['gift_product_id']])
                : null;
            if (!$gp) continue;                                   // gift product deleted: skip quietly
            $take = min($want, max(0, (int) $gp['stock']));
            if ($take <= 0) { $notes[] = 'The free gift (' . $gp['name'] . ') has run out, sorry.'; continue; }
            q("UPDATE products SET stock = stock - ? WHERE id = ?", [$take, $gp['id']]);
            if ($take < $want) $notes[] = 'Only ' . $take . ' of the free gift (' . $gp['name'] . ') was left.';
            $lines[] = ['product_id' => (string) $gp['id'], 'name' => (string) $gp['name'],
                        'brand' => (string) $gp['brand'], 'image' => (string) $gp['image'],
                        'qty' => $take, 'gift_for' => $pid];
        } else {
            $name = trim((string) $row['gift_name']);
            if ($name === '') continue;
            $take = $want;
            if ($row['stock'] !== null) {
                $take = min($want, max(0, (int) $row['stock']));
                if ($take <= 0) { $notes[] = 'The free gift (' . $name . ') has run out, sorry.'; continue; }
                q("UPDATE product_gifts SET stock = stock - ? WHERE id = ?", [$take, $row['id']]);
                if ($take < $want) $notes[] = 'Only ' . $take . ' of the free gift (' . $name . ') was left.';
            }
            $lines[] = ['product_id' => gift_custom_pid((int) $row['id']), 'name' => $name,
                        'brand' => '', 'image' => (string) $row['gift_image'],
                        'qty' => $take, 'gift_for' => $pid];
        }
    }
    return [$lines, $notes];
}

/**
 * Put custom-gift stock back (sign +1) or take it again (sign -1) when an order
 * is cancelled / un-cancelled in admin/order.php. Catalog gift lines carry a
 * real product_id, so the existing products.stock loop already handles them.
 * $items: order_items rows (need product_id, qty, is_gift).
 */
function gift_restock_custom(array $items, int $sign): void {
    foreach ($items as $it) {
        if (empty($it['is_gift'])) continue;
        if (!preg_match('/^gift-(\d+)$/', (string) $it['product_id'], $m)) continue;
        $n = max(0, (int) $it['qty']);
        if ($sign > 0) q("UPDATE product_gifts SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL", [$n, (int) $m[1]]);
        else           q("UPDATE product_gifts SET stock = GREATEST(0, stock - ?) WHERE id = ? AND stock IS NOT NULL", [$n, (int) $m[1]]);
    }
}

/** Image for an order line: the snapshot on the line (gifts), else the live
    product image (selected as p_image), else the placeholder. */
function order_item_image(array $it): string {
    if (!empty($it['image']))   return (string) $it['image'];
    if (!empty($it['p_image'])) return (string) $it['p_image'];
    return 'uploads/photo-pending.png';
}
