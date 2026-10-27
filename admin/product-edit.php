<?php
require __DIR__ . '/inc/layout.php';
require_once dirname(__DIR__) . '/inc/mailer.php';   // notify_restock() on a 0 -> in-stock save
require_once dirname(__DIR__) . '/inc/gifts.php';    // the "Free gift" card (product_gifts)

/** A request field as a trimmed string; an array (crafted request) reads as ''. */
function product_gift_field(string $k): string { $v = input($k); return is_scalar($v) ? trim((string) $v) : ''; }

/**
 * Save the "Free gift" card for product $pid. Runs AFTER the product has saved, so
 * a gift problem never costs the operator their product changes.
 * Returns [problem, message]: problem is an 'err' sentence (the offer is left exactly
 * as it was, except a custom image that failed to upload), message a short success
 * note. Either may be null. Switching the offer off keeps the row with active = 0.
 */
function product_gift_save(string $pid): array {
    $old = row("SELECT * FROM product_gifts WHERE product_id = ?", [$pid]);
    $on  = input('gift_on') ? 1 : 0;
    if (!$on && !$old) return [null, null];                       // no offer, and none wanted

    $type = product_gift_field('gift_type') === 'custom' ? 'custom' : 'product';
    $d = [
        'gift_type' => $type, 'gift_product_id' => null, 'gift_name' => '', 'gift_image' => '', 'stock' => null,
        'note'      => mb_substr(product_gift_field('gift_note'), 0, 200),
        'gift_qty'  => min(20, max(1, (int) product_gift_field('gift_qty'))),
        'per_unit'  => product_gift_field('gift_per_unit') === '1' ? 1 : 0,
        'starts_on' => null, 'ends_on' => null, 'active' => $on,
    ];
    $bad = null;
    /* dates reach a DATE column, and a malformed one is a PDOException (blank 500) */
    foreach (['starts_on' => 'start', 'ends_on' => 'end'] as $k => $word) {
        $s = product_gift_field('gift_' . $k);
        if ($s === '') continue;
        $dt = DateTime::createFromFormat('!Y-m-d', $s);
        if (!$dt || $dt->format('Y-m-d') !== $s || $s < '2000-01-01' || $s > '2099-12-31') { $bad = $bad ?? "the $word date is not a real date"; continue; }
        $d[$k] = $s;
    }
    if (!$bad && $d['starts_on'] && $d['ends_on'] && $d['ends_on'] < $d['starts_on']) $bad = 'the end date is before the start date';

    if ($type === 'product') {
        $gp = product_gift_field('gift_product_id');
        if ($gp === '') $bad = $bad ?? 'choose the store product to give away';
        elseif (!($gpRow = row("SELECT id FROM products WHERE id = ?", [$gp]))) $bad = $bad ?? 'the chosen gift product no longer exists, choose another';
        else $d['gift_product_id'] = (string) $gpRow['id'];   // the stored spelling, see $id below
    } else {
        $d['gift_name'] = mb_substr(product_gift_field('gift_name'), 0, 200);
        if ($d['gift_name'] === '') $bad = $bad ?? 'give the custom gift a name';
        $st = product_gift_field('gift_stock');
        if ($old && $old['gift_type'] === 'custom' && $st === product_gift_field('gift_stock_was')) {
            $d['stock'] = $old['stock'];      // field untouched: keep what orders have used up since the page was opened
        } elseif ($st !== '') {
            if (!preg_match('/^\d{1,6}$/', $st)) $bad = $bad ?? '"Gifts available" must be a whole number, or blank for unlimited';
            else $d['stock'] = (int) $st;
        }
        $d['gift_image'] = product_gift_field('gift_image');
        if (strlen($d['gift_image']) > 500) $bad = $bad ?? 'the gift image URL is too long';
    }

    if ($bad) {
        /* switching OFF always works, even when the hidden details no longer check out
           (e.g. the gift product was deleted since) */
        if (!$on && $old) {
            if (!(int) $old['active']) return [null, null];
            q("UPDATE product_gifts SET active = 0 WHERE id = ?", [(int) $old['id']]);
            return [null, 'Free gift switched off.'];
        }
        return ['Free gift not saved: ' . $bad . '.', null];
    }

    $imgProblem = null;
    if ($type === 'custom') {
        $upErr = null;
        if ($u = save_upload('gift_image_file', $upErr)) $d['gift_image'] = $u;
        elseif ($upErr) $imgProblem = 'The gift image was not changed: ' . $upErr;
    }

    if ($old) {
        $same = true;
        foreach ($d as $k => $v) if ((string) $old[$k] !== (string) $v) { $same = false; break; }
        if ($same) return [$imgProblem, null];
        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($d)));
        q("UPDATE product_gifts SET $sets WHERE id = :id", $d + ['id' => (int) $old['id']]);
    } else {
        $d['product_id'] = $pid;
        $cols = implode(', ', array_keys($d));
        $ph   = implode(', ', array_map(fn($k) => ":$k", array_keys($d)));
        q("INSERT INTO product_gifts ($cols) VALUES ($ph)", $d);
    }
    if (!$on) return [$imgProblem, 'Free gift switched off.'];

    $now = row(gift_select_sql() . " WHERE g.product_id = ?", [$pid]);
    $msg = [
        'live'      => 'Free gift is live.',
        'scheduled' => 'Free gift saved. It starts on ' . date('M j, Y', strtotime((string) $d['starts_on'])) . '.',
        'ended'     => "Free gift saved, but its end date has passed, so shoppers won't see it.",
        'out'       => "Free gift saved, but it has run out, so shoppers won't see it.",
    ][$now ? gift_status($now) : ''] ?? 'Free gift saved.';
    return [$imgProblem, $msg];
}

/**
 * The Product ID made from the name when the ID field is left blank. products.id is
 * VARCHAR(64) and the live MySQL is non-strict, so a longer id used to be cut to 64 on
 * INSERT, after the "already exists" check had looked for the long one: it could land on
 * an existing product's id (a blank 500). A long slug is cut at a dash to leave room for
 * "-" + 6 hex of md5(full slug). The same name always gives the same id, so a second
 * submit is caught as "already exists", and two long names that share their first 64
 * characters still get different ids. Ids already saved are never renamed.
 */
function product_auto_id(string $slug): string {
    if (strlen($slug) <= 64) return $slug;              // slugify() output is ASCII
    $head = substr($slug, 0, 58);                       // 57 + the next character, to see a word end
    $cut  = strrpos($head, '-');
    $head = $cut !== false && $cut >= 30 ? substr($head, 0, $cut) : substr($head, 0, 57);
    return rtrim($head, '-') . '-' . substr(md5($slug), 0, 6);
}

/**
 * Clean the Flavours editor's value: one flavour per line, "Label" or "Label|surcharge"
 * (the opt_colors format, read by parse_variant_opts()). Blank lines and repeats go
 * (case-insensitively, the first one stays), a 0 surcharge is dropped and the rest is
 * written like the editor writes it ("2.5"). A flavour that can't be stored is left out
 * and described. Returns [value to save, list of problems].
 */
function product_flavors_clean(string $raw): array {
    if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
    $keep = []; $seen = []; $bad = []; $over = [];
    /* split on real line ends only, as the editor joins with "\n": a byte-mode \R also
       takes the 0x85 byte inside letters like the Arabic meem and cut the name in two */
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        /* any other line separator or control character in a name becomes a space, so
           no reader of the saved value can split it differently */
        $line = trim(preg_replace('/[\p{Cc}\x{2028}\x{2029}]+/u', ' ', $line) ?? $line);
        if ($line === '') continue;
        /* the surcharge is what follows the LAST "|" when it reads as a number; otherwise
           the "|" belongs to the name, which is refused below */
        $label = $line; $sur = '';
        if (($bar = strrpos($line, '|')) !== false) {
            $tail = trim(substr($line, $bar + 1));
            if ($tail === '' || is_numeric($tail)) { $label = trim(substr($line, 0, $bar)); $sur = $tail; }
        }
        if ($label === '') continue;
        $show = '"' . (mb_strlen($label) > 28 ? rtrim(mb_substr($label, 0, 25)) . '...' : $label) . '"';
        if (strpos($label, '|') !== false) { $bad[] = $show . ' has a | in its name'; continue; }
        if (mb_strlen($label) > 60)        { $bad[] = $show . ' is longer than 60 characters'; continue; }
        $n = $sur === '' ? 0.0 : round((float) $sur, 2);
        if ($n < 0 || !is_finite($n) || $n > 99999) { $bad[] = $show . ($n < 0 ? ' has a +$ below 0' : ' has a +$ that is too large'); continue; }
        $key = mb_strtolower($label);
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        if (count($keep) >= 40) { $over[] = $show; continue; }
        $keep[] = $n > 0 ? $label . '|' . rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') : $label;
    }
    if ($over) $bad[] = 'a product can have up to 40, so ' . (count($over) === 1 ? $over[0] . ' was' : count($over) . ' were') . ' left out';
    return [implode("\n", $keep), $bad];
}

/**
 * A signed-in shopper's saved bag (customer_cart.variant) compares text the MySQL way,
 * utf8mb4_unicode_ci: case AND accents are ignored, so "Cafe" and "Cafe" with an accent
 * would share one saved-bag row. MySQL gives each label its weight under that collation;
 * a label that weighs the same as an earlier one is left out and named. If the query
 * fails the flavours are kept as they are (the browser bag still tells them apart).
 */
function product_flavors_distinct(string $value): array {
    if ($value === '') return ['', []];
    $lines = explode("\n", $value); $sel = []; $labels = [];
    foreach ($lines as $i => $line) {
        $labels[$i] = explode('|', $line, 2)[0];
        $sel[] = 'SELECT ' . $i . ' AS i, WEIGHT_STRING(CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci) AS w';
    }
    try { $w = array_column(rows(implode(' UNION ALL ', $sel), array_values($labels)), 'w', 'i'); }
    catch (PDOException $ex) { return [$value, []]; }
    $keep = []; $seen = []; $bad = [];
    foreach ($lines as $i => $line) {
        $k = (string) ($w[$i] ?? $labels[$i]);
        if (isset($seen[$k])) {
            /* say why once: the owner sees two different names, the saved bag sees one */
            $bad[] = '"' . $labels[$i] . '" is too close to "' . $seen[$k] . '"'
                   . ($bad ? '' : ' (names that differ only in accents, capital letters or which emoji they use count as one flavour, so change a word)');
            continue;
        }
        $seen[$k] = $labels[$i]; $keep[] = $line;
    }
    return [implode("\n", $keep), $bad];
}

/** What shoppers see above the flavour choices ("Scent", "Type", ...); '' means "Flavour". */
function product_flavor_name_clean(string $raw): string {
    if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
    $s = preg_replace('/\s+/u', ' ', str_replace('|', '', strip_tags($raw))) ?? '';
    return trim(mb_substr(trim($s), 0, 40));
}

$BADGES = ['' => '— none —','derm'=>'Derm Pick','best'=>'Bestseller','trend'=>'Trending','trusted'=>'Trusted','new'=>'New','vegan'=>'Vegan','ff'=>'Frag-Free'];
$cats   = array_column(rows("SELECT name FROM categories ORDER BY sort"), 'name');
$brandList = array_column(rows("SELECT name FROM brands ORDER BY name"), 'name');

/* The record being edited comes from the URL only (the Edit links / the editing form's
   action carry ?id=). The ADD form posts a typed "id" field: reading that here made a new
   product whose typed ID already existed silently overwrite the existing one. */
$id = (string) ($_GET['id'] ?? '');
$editing = $id !== '' && ($p = row("SELECT * FROM products WHERE id = ?", [$id]));
/* from here on, the id exactly as stored: SQL matches ids case- and trailing-space-
   insensitively, so ?id=SOME-ID opens the product too, but the gift lookups in PHP
   (gifts_live_map, the Products list) key on the stored string */
if ($editing) $id = (string) $p['id'];
// only guard when OPENING an edit page for a missing product (GET). On POST the id is the
// NEW product's slug (which of course doesn't exist yet) — let the insert handler run.
if (!is_post() && $id !== '' && !$editing) { flash('Product not found.', 'err'); redirect('products'); }

if (is_post()) {
    csrf_check();
    $name    = trim((string) input('name'));
    $typedId = $editing ? '' : trim((string) input('id'));
    $newId   = $editing ? $id : ($typedId !== '' ? $typedId : slugify($name));
    /* gift-<n> is the id a custom free gift carries on order lines (gift_custom_pid()). A
       product holding one would be taken for that gift when an order is cancelled, so a
       new product never gets one: a typed ID is refused, a generated one gets "-1". */
    if (!$editing && preg_match('/^gift-\d+$/i', $newId)) {
        if ($typedId !== '') { flash('Product IDs like "' . $newId . '" are kept for free gifts. Choose a different Product ID.', 'err'); redirect('product-edit' . admin_ret_qs('?')); }
        $newId .= '-1';
    }
    /* products.id holds 64 characters and the live MySQL cuts a longer one without an
       error (see product_auto_id). A typed ID is refused rather than shortened behind
       the operator's back; a generated one is shortened the same way every time. */
    if (!$editing && mb_strlen($typedId) > 64) {
        flash('That Product ID is ' . mb_strlen($typedId) . ' characters long, and the most is 64. Shorten it, or leave it blank to make one from the name.', 'err');
        redirect('product-edit' . admin_ret_qs('?'));
    }
    if (!$editing && $typedId === '') $newId = product_auto_id($newId);
    $price = (float) input('price');
    $was   = null;   // no "Was" box any more: a crossed-out price comes only from a sale %; saving clears an old one
    $sale  = input('sale_pct') !== '' ? (int) input('sale_pct') : null;
    if ($sale !== null && ($sale <= 0 || $sale >= 100)) $sale = null;   // a sale is 1-99 %; 0 or blank = no sale
    /* Price left blank while the sizes carry prices (the owner priced the options, not the
       product): the cheapest priced size becomes the price, so the product is for sale
       instead of reading "Price coming soon" everywhere. The Default size then sells at
       that price — the flash says so, in case that is not what was meant. */
    $priceNote = '';
    if ($price <= 0) {
        $pricedSizes = [];
        foreach (parse_variant_opts((string) input('opt_sizes')) as $o) if ($o['price'] !== null && $o['price'] > 0) $pricedSizes[] = (float) $o['price'];
        if ($pricedSizes) {
            $price = round(min($pricedSizes), 2);
            $priceNote = ' The price was blank, so it was set to $' . number_format($price, 2) . ' (the cheapest size). The Default size sells at it — change the price if that is wrong.';
        }
    }

    $image = trim((string) input('image'));
    $hover = trim((string) input('hover_image'));
    $upErr = null; $upErr2 = null;
    if ($u = save_upload('image_file', $upErr)) $image = $u;
    if ($u = save_upload('hover_file', $upErr2)) $hover = $u;
    if (!$upErr && $upErr2) $upErr = $upErr2;

    $galPaths = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) input('gallery')))));
    $galErrs  = [];
    /* gallery_keys names each uploaded file, in order: an option photo picked from the new
       photos says new:<key>, and is pointed at the file's saved path below */
    $galKeys = preg_split('/\r\n|\r|\n/', (string) input('gallery_keys'));
    $newPath = [];
    if (!empty($_FILES['gallery_files']['name']) && is_array($_FILES['gallery_files']['name'])) {
        foreach ($_FILES['gallery_files']['name'] as $gi => $gnm) {
            if (($_FILES['gallery_files']['error'][$gi] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            /* a photo PHP refused (over its size limit) is named in the flash, not dropped in silence */
            $_FILES['_gf'] = ['name'=>$gnm,'type'=>$_FILES['gallery_files']['type'][$gi],'tmp_name'=>$_FILES['gallery_files']['tmp_name'][$gi],'error'=>$_FILES['gallery_files']['error'][$gi],'size'=>$_FILES['gallery_files']['size'][$gi]];
            $ge=null; if ($gu = save_upload('_gf', $ge)) { $galPaths[] = $gu; if (($galKeys[$gi] ?? '') !== '') $newPath[$galKeys[$gi]] = $gu; }
            elseif ($ge) $galErrs[] = $gnm . ': ' . $ge;
        }
    }
    if ($galErrs) { $upErr = ($upErr ? $upErr . ' ' : '') . 'Gallery photo(s) not added — ' . implode(' · ', $galErrs); }
    // de-duplicate: drop repeated paths/URLs and pixel-identical local uploads (safety net for the client-side guard)
    $seenStr = []; $seenHash = []; $uniqueGal = []; $galAlias = [];
    foreach ($galPaths as $gpath) {
        if (isset($seenStr[$gpath])) continue;
        $seenStr[$gpath] = true;
        if (!preg_match('~^(https?:|/|data:)~', $gpath)) {              // local, site-relative path → compare file contents
            $abs = dirname(__DIR__) . '/' . $gpath;
            if (is_file($abs) && ($h = md5_file($abs)) !== false) {
                if (isset($seenHash[$h])) { $galAlias[$gpath] = $seenHash[$h]; continue; }   // an option photo pointing here uses the kept copy
                $seenHash[$h] = $gpath;
            }
        }
        $uniqueGal[] = $gpath;
    }
    $galPaths = $uniqueGal;
    /* no main photo but a gallery: its first photo becomes the main one (cards were blank) */
    if ($image === '' && $galPaths) $image = array_shift($galPaths);

    /* Flavours: one that can't be stored is left out and named in the flash, the rest of
       the product still saves, and the editor reopens at the card (see below). */
    [$flavors, $flvBad] = product_flavors_clean(product_gift_field('opt_flavors'));
    [$flavors, $flvSame] = product_flavors_distinct($flavors);
    $flvBad = array_merge($flvBad, $flvSame);

    /* Option photos: {"color":{label:photo},"flavor":{…},"size":{…}} from the editor. A new
       photo (new:<key>) becomes its saved path; only options that are being saved and only
       real photo paths / URLs are kept. */
    $optImgs = ['color' => [], 'flavor' => [], 'size' => []];
    $rawImgs = json_decode((string) input('opt_images'), true);
    if (is_array($rawImgs)) {
        $labelsOf = [
            'color'  => array_column(parse_variant_opts((string) input('opt_colors')), 'label'),
            'flavor' => array_column(parse_variant_opts($flavors), 'label'),
            'size'   => array_merge(array_column(parse_variant_opts((string) input('opt_sizes')), 'label'), [trim((string) input('size'))]),
        ];
        foreach ($optImgs as $g => $_) {
            foreach ((array) ($rawImgs[$g] ?? []) as $label => $img) {
                $label = trim((string) $label); $img = trim((string) $img);
                if ($label === '' || !in_array($label, $labelsOf[$g], true)) continue;
                if (strncmp($img, 'new:', 4) === 0) $img = $newPath[substr($img, 4)] ?? '';
                $img = $galAlias[$img] ?? $img;
                if ($img !== '' && option_image_ok($img)) $optImgs[$g][$label] = $img;
            }
        }
    }
    /* the admin's own colours (name + #hex), kept so an unticked one stays in the swatch row */
    if (is_array($rawImgs) && is_array($rawImgs['palette'] ?? null)) {
        $pal = [];
        foreach (array_slice($rawImgs['palette'], 0, 40) as $c) {
            $n = mb_substr(trim(str_replace('|', '', (string) ($c['name'] ?? ''))), 0, 40); $h = strtolower((string) ($c['hex'] ?? ''));
            if ($n !== '' && preg_match('/^#[0-9a-f]{6}$/', $h)) $pal[] = ['name' => $n, 'hex' => $h];
        }
        if ($pal) $optImgs['palette'] = $pal;
    }
    $optImgsJson = array_filter($optImgs) ? json_encode($optImgs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

    /* short columns are cut to their width here: the live MySQL runs strict, where a value
       one character too long is a blank 500 instead of a saved product */
    $cut = fn(string $k, int $n) => mb_substr(trim((string) input($k)), 0, $n);
    $data = [
        'name'=>mb_substr($name, 0, 200), 'brand'=>trim((string)input('brand')), 'category'=>(string)input('category'),
        'price'=>$price, 'was'=>$was, 'sale_pct'=>$sale, 'badge'=>(string)input('badge'),
        'stock'=>(int)input('stock'), 'low_stock'=>(int)input('low_stock'),
        'kw'=>$cut('kw', 60), 'descr'=>$cut('descr', 180),
        'long_desc'=>(string)input('long_desc'), 'image'=>$image, 'hover_image'=>$hover, 'gallery'=>implode("\n",$galPaths),
        'barcode'=>$cut('barcode', 32), 'sku'=>$cut('sku', 64), 'size'=>$cut('size', 48), 'unit'=>$cut('unit', 24),
        'opt_colors'=>trim((string)input('opt_colors')), 'opt_sizes'=>trim((string)input('opt_sizes')),
        'opt_flavors'=>$flavors, 'opt_flavor_name'=>product_flavor_name_clean(product_gift_field('opt_flavor_name')),
        'how_to_use'=>(string)input('how_to_use'), 'ingredients'=>(string)input('ingredients'), 'benefits'=>(string)input('benefits'),
        'keywords'=>(string)input('keywords'),
        'feat_latest'=> input('feat_latest') ? 1 : 0, 'feat_trending'=> input('feat_trending') ? 1 : 0, 'feat_wellness'=> input('feat_wellness') ? 1 : 0,
        'home_sort'=>(int)input('home_sort'), 'status'=> input('status')==='draft'?'draft':'active',
    ];
    /* option photos need db/migrate-option-images.php; until it has run the product still saves */
    if (product_has_col('opt_images')) $data['opt_images'] = $optImgsJson;
    if (product_has_col('sale_opts'))  $data['sale_opts']  = input('sale_opts') ? 1 : 0;   // does the sale cover sizes + extras

    /* The base price is the STANDARD (default) size's price and is stored as typed —
       never overwritten. The cheapest option is shown as "from $X" on cards / the product
       page (computed at display time via variant_from_price), so this field stays the real
       price of the default size, which variant_resolve() uses for it. */

    if ($name === '' || $newId === '') { flash($name === '' ? 'Name is required.' : 'Type a Product ID: this name has no plain letters or numbers to make one from.', 'err'); redirect($editing ? 'product-edit?id=' . rawurlencode($id) . admin_ret_qs() : 'product-edit' . admin_ret_qs('?')); }

    if ($editing) {
        /* remember the stock we're replacing so we can spot a 0 -> in-stock crossing */
        $stockBefore = (int) val("SELECT stock FROM products WHERE id = ?", [$id]);

        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $data['id'] = $id;
        q("UPDATE products SET $sets WHERE id = :id", $data);

        /* RESTOCK: went from out-of-stock to in-stock -> email everyone waiting.
           notify_restock() stamps each row, so re-saving the product won't re-send. */
        $note = '';
        if ($stockBefore <= 0 && (int) $data['stock'] > 0) {
            $sent = notify_restock($id);
            if ($sent > 0) $note = " Back-in-stock alert emailed to $sent " . ($sent === 1 ? 'person' : 'people') . '.';
        }
        flash(($upErr ? 'Product updated — but the image was not changed: ' . $upErr : 'Product updated.') . $note . $priceNote, $upErr ? 'err' : 'ok');
    } else {
        $taken = 'A product with the ID "' . $newId . '" already exists. Find it under Products, or type a different Product ID.';
        if (row("SELECT id FROM products WHERE id = ?", [$newId])) { flash($taken, 'err'); redirect('product-edit' . admin_ret_qs('?')); }
        $data['id'] = $newId;
        $cols = implode(', ', array_keys($data));
        $ph   = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
        try {
            q("INSERT INTO products ($cols) VALUES ($ph)", $data);
        } catch (PDOException $ex) {
            /* the same form arriving twice at once: the other request created it a moment ago */
            if ((int) ($ex->errorInfo[1] ?? 0) !== 1062) throw $ex;
            flash($taken, 'err'); redirect('product-edit' . admin_ret_qs('?'));
        }
        flash(($upErr ? 'Product created — but no image was added: ' . $upErr : 'Product created.') . $priceNote, $upErr ? 'err' : 'ok');
    }
    if ($flvBad) {
        $f = $_SESSION['flash'] ?? ['m' => '', 't' => 'ok'];
        $more = count($flvBad) > 3 ? '; and ' . (count($flvBad) - 3) . ' more' : '';
        flash(trim($f['m'] . ' Some flavours were not saved: ' . implode('; ', array_slice($flvBad, 0, 3)) . $more . '. Fix them under Options / variants and save again.'), 'err');
    }

    /* Free gift: saved only now, against the id the product was saved under. Its note
       joins the product's flash; a problem with it reopens the editor at the card
       (even on "Save & back to list") so the operator sees what needs fixing.
       gift_form is only posted by the card, so a form without it never touches gifts. */
    if (input('gift_form') === '1' && admin_gifts_ready()) {
        [$giftProblem, $giftMsg] = product_gift_save($newId);
        if ($giftProblem || $giftMsg) {
            $f = $_SESSION['flash'] ?? ['m' => '', 't' => 'ok'];
            flash(implode(' ', array_filter([$f['m'], $giftMsg, $giftProblem])), $giftProblem ? 'err' : $f['t']);
        }
        if ($giftProblem) redirect('product-edit?id=' . rawurlencode($newId) . admin_ret_qs() . '#gift');
    }
    if ($flvBad) redirect('product-edit?id=' . rawurlencode($newId) . admin_ret_qs() . '#options');   // even on "Save & back to list"

    /* Stay on the record just saved: an operator editing a product usually has more
       to change on it, and the old redirect to the bare list meant re-finding it by
       hand every time. "Save & back to list" is the explicit way out and keeps the
       filters they arrived with. */
    redirect(input('after') === 'list'
        ? admin_back_href('products')
        : 'product-edit?id=' . rawurlencode($newId) . admin_ret_qs());
}

/* defaults for the form */
$v = $editing ? $p : ['id'=>'','name'=>'','brand'=>'','category'=>$cats[0]??'','price'=>'','was'=>'','sale_pct'=>'',
    'badge'=>'','rating'=>'4.8','reviews'=>'0','stock'=>'0','low_stock'=>'5','kw'=>'','descr'=>'','long_desc'=>'',
    'barcode'=>'','sku'=>'','size'=>'','unit'=>'','opt_colors'=>'','opt_sizes'=>'','opt_flavors'=>'','opt_flavor_name'=>'','how_to_use'=>'','ingredients'=>'','benefits'=>'','keywords'=>'',
    'image'=>'','hover_image'=>'','gallery'=>'','feat_latest'=>0,'feat_trending'=>0,'feat_wellness'=>0,'home_sort'=>0,'status'=>'active'];

/* "+ Add product to <brand>" on a brand's admin screen opens this with ?brand= (?category=
   also works): start the new product in that brand, on the shelf most of the brand's
   products sit on. Only names that really exist are taken. */
if (!$editing) {
    $preBrand = trim((string) input('brand'));
    if ($preBrand !== '' && (in_array($preBrand, $brandList, true) || val("SELECT 1 FROM products WHERE brand = ? LIMIT 1", [$preBrand])))
        $v['brand'] = $preBrand;
    $preCat = trim((string) input('category'));
    if ($preCat === '' && $v['brand'] !== '')
        $preCat = (string) val("SELECT category FROM products WHERE brand = ? GROUP BY category ORDER BY COUNT(*) DESC LIMIT 1", [$v['brand']]);
    if (in_array($preCat, $cats, true)) $v['category'] = $preCat;
}
/* opened from a brand's screen: the way back says so */
$backTo = strncmp(admin_ret(), 'brand-edit', 10) === 0 ? 'brand' : '';

/* the free-gift card: the saved offer (joined with its gift product), else blank defaults.
   Without the product_gifts table (migration not run yet) the card only says so. */
$giftsReady = admin_gifts_ready();
$g  = $editing && $giftsReady ? row(gift_select_sql() . " WHERE g.product_id = ?", [$id]) : null;
$gv = $g ?: ['active'=>0,'gift_type'=>'product','gift_product_id'=>'','gift_name'=>'','gift_image'=>'','note'=>'',
    'gift_qty'=>1,'per_unit'=>0,'stock'=>null,'starts_on'=>'','ends_on'=>'','gp_name'=>null];
$gState = $g ? admin_gift_state(gift_status($g)) : null;
/* the chosen store product, for the picker to draw (missing = the gift product was deleted) */
$gPick = null;
if ($gv['gift_type'] !== 'custom' && (string) $gv['gift_product_id'] !== '') {
    $gPick = $gv['gp_name'] === null
        ? ['id' => (string) $gv['gift_product_id'], 'missing' => true]
        : ['id' => (string) $gv['gift_product_id'], 'name' => (string) $gv['gp_name'], 'brand' => (string) $gv['gp_brand'],
           'img' => asrc((string) $gv['gp_image'] !== '' ? (string) $gv['gp_image'] : 'uploads/photo-pending.png'),
           'stock' => (int) $gv['gp_stock'], 'status' => (string) $gv['gp_status']];
}

admin_head($editing ? 'Edit product' : 'Add product', 'products', $editing ? $v['name'] : 'New product');
?>
<form id="productForm" method="post" action="<?= $editing ? e('product-edit?id=' . rawurlencode($id)) : "product-edit" ?>" enctype="multipart/form-data">
  <?= csrf_field() ?><?= admin_ret_field() ?>
  <div class="page-actions"><a class="btn btn-ghost" href="<?= e(admin_back_href('products')) ?>">← Back<?= $backTo !== '' ? ' to ' . $backTo : '' ?></a><div class="spacer"></div><button class="btn btn-primary">Save product</button><button class="btn btn-ghost" name="after" value="list">Save &amp; back to <?= $backTo !== '' ? $backTo : 'list' ?></button></div>

  <div class="a-grid" style="grid-template-columns:1.5fr 1fr">
    <div style="display:flex;flex-direction:column;gap:18px">
      <div class="a-card"><div class="hd"><h2>Details</h2></div><div class="bd">
        <div class="field"><label>Product name</label><input class="input" name="name" value="<?= e($v['name']) ?>" required></div>
        <div class="f-row">
          <div class="field"><label>Brand</label><select class="input" name="brand">
            <option value="">— choose brand —</option>
            <?php $bl=$brandList; if($v['brand'] && !in_array($v['brand'],$bl,true)) $bl[]=$v['brand']; sort($bl); foreach ($bl as $b): ?><option <?= $b===$v['brand']?'selected':'' ?>><?= e($b) ?></option><?php endforeach; ?>
          </select><div class="hint">Manage the list under <b>Brands</b>.</div></div>
          <div class="field"><label>Category</label><select class="input" name="category">
            <?php foreach ($cats as $c): ?><option <?= $c===$v['category']?'selected':'' ?>><?= e($c) ?></option><?php endforeach; ?>
          </select></div>
        </div>
        <?php if (!$editing): ?>
        <div class="field"><label>Product ID / slug <span class="faint">(optional)</span></label><input class="input" name="id" maxlength="64" placeholder="auto from name, e.g. lumiere-vitc"><div class="hint">Used in the product URL, up to 64 characters. Leave blank to generate automatically.</div></div>
        <?php endif; ?>
        <div class="f-row">
          <div class="field"><label>Card title (kw)</label><input class="input" name="kw" value="<?= e($v['kw']) ?>" placeholder="glow"></div>
          <div class="field"><label>Short descriptor</label><input class="input" name="descr" value="<?= e($v['descr']) ?>" placeholder="Brightening serum"></div>
        </div>
        <div class="field"><label>Full description <span class="faint">(product page “Description” tab)</span></label><textarea class="input" name="long_desc" rows="4"><?= e($v['long_desc']) ?></textarea></div>
        <div class="field"><label>How to use <span class="faint">(“How to Use” tab)</span></label><textarea class="input" name="how_to_use" rows="3"><?= e($v['how_to_use'] ?? '') ?></textarea></div>
        <div class="field"><label>Ingredients <span class="faint">(“Ingredients” tab)</span></label><textarea class="input" name="ingredients" rows="3"><?= e($v['ingredients'] ?? '') ?></textarea></div>
        <div class="field"><label>Benefits <span class="faint">(one per line — shown as bullets)</span></label><textarea class="input" name="benefits" rows="3"><?= e($v['benefits'] ?? '') ?></textarea></div>
        <div class="field"><label>Search keywords <span class="faint">(not shown; helps the product appear in search)</span></label><textarea class="input" name="keywords" rows="2"><?= e($v['keywords'] ?? '') ?></textarea></div>
      </div></div>

      <div class="a-card"><div class="hd"><h2>Images</h2></div><div class="bd">
        <div class="f-row">
          <div class="field"><label>Main image</label>
            <?php if ($v['image']): ?><img src="<?= e(asrc($v['image'])) ?>" style="width:74px;height:74px;border-radius:10px;object-fit:cover;margin-bottom:8px;border:1px solid var(--a-border2)"><?php endif; ?>
            <input class="input" name="image" value="<?= e($v['image']) ?>" placeholder="https://… or upload below">
            <input type="file" name="image_file" accept="image/*" data-maxmb="10" style="margin-top:8px;font-size:12.5px"><div class="hint">Paste a URL or upload a photo (JPG/PNG/WebP, max 10 MB — auto-optimized).</div>
          </div>
          <div class="field"><label>Hover image</label>
            <?php if ($v['hover_image']): ?><img src="<?= e(asrc($v['hover_image'])) ?>" style="width:74px;height:74px;border-radius:10px;object-fit:cover;margin-bottom:8px;border:1px solid var(--a-border2)"><?php endif; ?>
            <input class="input" name="hover_image" value="<?= e($v['hover_image']) ?>" placeholder="optional 2nd image">
            <input type="file" name="hover_file" accept="image/*" data-maxmb="10" style="margin-top:8px;font-size:12.5px"><div class="hint">Shown on hover (rhode-style swap).</div>
          </div>
        </div>
      </div></div>

      <div class="a-card"><div class="hd"><h2>Gallery images</h2></div><div class="bd">
        <?php $gp = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($v['gallery'] ?? ''))))); ?>
        <div class="field"><label>Extra photos <span class="faint">(thumbnails on the product page, after main + hover)</span></label>
          <div class="gal" data-gallery>
            <div class="gal-grid" data-gal-grid></div>
            <textarea name="gallery" data-gal-store hidden><?= e(implode("\n", $gp)) ?></textarea>
            <input type="file" name="gallery_files[]" accept="image/*" multiple data-gal-files hidden>
            <div class="gal-actions">
              <button type="button" class="btn btn-ghost btn-sm" data-gal-pick>＋ Add photos</button>
              <div class="gal-url">
                <input type="text" class="input" data-gal-url placeholder="…or paste an image URL">
                <button type="button" class="btn btn-ghost btn-sm" data-gal-url-add>Add URL</button>
              </div>
            </div>
            <div class="gal-note" data-gal-note></div>
            <div class="hint">Pick several at once — previews appear instantly and stack left→right. Hover a photo and click ✕ to remove it. Duplicate images are skipped automatically. Nothing is saved until you click <b>Save product</b>.
              Big phone photos are shrunk before upload. This server accepts <b><?= e((string) ini_get('upload_max_filesize')) ?></b> per photo and <b><?= e((string) ini_get('post_max_size')) ?></b> per save.</div>
          </div>
        </div>
      </div></div>
    </div>

    <div style="display:flex;flex-direction:column;gap:18px">
      <div class="a-card"><div class="hd"><h2>Pricing &amp; stock</h2></div><div class="bd">
        <div class="f-row">
          <div class="field"><label>Price ($)</label><input class="input" type="number" step="0.01" name="price" value="<?= e($v['price']) ?>" required><div class="hint">The regular price — its <b>primary</b> size. Extra sizes carry their own price under <b>Options</b>. Left blank while the sizes have prices, it becomes the cheapest size's price on save.</div></div>
          <div class="field"><label>Badge</label><select class="input" name="badge">
            <?php foreach ($BADGES as $bk=>$bl): ?><option value="<?= e($bk) ?>" <?= $bk===$v['badge']?'selected':'' ?>><?= e($bl) ?></option><?php endforeach; ?>
          </select></div>
        </div>
        <?php /* the sale: its own strip — the %, then (while a sale is on) what it sells at and whether it covers the options */
              $saleOptsOn = !array_key_exists('sale_opts', $v) || $v['sale_opts'] === null || (int) $v['sale_opts'] === 1; ?>
        <div class="field sale-strip">
          <label for="salePct">Sale (%)</label>
          <div class="hint">Blank = no sale. With a %, the price drops by that much and the regular price shows crossed out (Price $10 + 20% sells at $8). The -% badge shows on the product. <b>Also discount sizes &amp; extras</b>: the sale also lowers the sizes' own prices and the colour / flavour extras (Black +$5 becomes +$4).</div>
          <div class="sale-line">
            <div class="sale-in"><input class="input" type="number" min="0" max="99" step="1" name="sale_pct" id="salePct" value="<?= e($v['sale_pct']) ?>" placeholder="none"><span>%</span></div>
            <div class="sale-side">
              <div class="sale-now" id="saleNow"></div>
              <label class="switch sale-opts" id="saleOptsWrap"><input type="checkbox" name="sale_opts" value="1" id="saleOpts" <?= $saleOptsOn ? 'checked' : '' ?>> Also discount sizes &amp; extras</label>
            </div>
          </div>
        </div>
        <div class="f-row">
          <div class="field"><label>Stock</label><input class="input" type="number" name="stock" value="<?= e($v['stock']) ?>"></div>
          <div class="field"><label>Low-stock warning at</label><input class="input" type="number" name="low_stock" value="<?= e($v['low_stock']) ?>"><div class="hint">Shows "Only X left" when stock hits this.</div></div>
        </div>
        <p class="hint">⭐ Rating &amp; review count come from real customer reviews on the product page — not set here.</p>
      </div></div>

      <div class="a-card gift-card" id="gift"><div class="hd"><h2>Free gift</h2><?php if ($gState): ?><span class="pill pill-<?= e($gState[1]) ?>"><?= e($gState[0]) ?></span><?php endif; ?></div>
      <?php if (!$giftsReady): ?><div class="bd">
        <p class="hint" style="margin:0">Free gifts are not set up yet. The site database needs its one-time update first (db/phase4-gifts-popups.sql).</p>
      <?php else: ?><div class="bd" data-gift>
        <input type="hidden" name="gift_form" value="1">
        <label class="switch switch-block"><input type="checkbox" name="gift_on" value="1" data-gift-on <?= (int)$gv['active'] ? 'checked' : '' ?>><span class="sw-txt"><b>Give a free gift with this product</b><span class="muted">Shoppers see a gift tag on it, and the gift is added to their order automatically.</span></span></label>
        <?php if ($g && !(int)$g['active']): ?><p class="hint gift-saved" data-gift-saved>Switched off. Shoppers don't see this offer, but it's kept so you can switch it back on.</p><?php endif; ?>
        <div class="gift-body<?= $g && !(int)$g['active'] ? ' is-off' : '' ?>" data-gift-body data-gift-saved-offer="<?= $g ? 1 : 0 ?>" <?= !$g && !(int)$gv['active'] ? 'hidden' : '' ?>>
          <div class="field"><label id="giftTypeL">The gift is</label>
            <div class="gift-types" role="radiogroup" aria-labelledby="giftTypeL">
              <label class="switch"><input type="radio" name="gift_type" value="product" data-gift-type <?= $gv['gift_type'] !== 'custom' ? 'checked' : '' ?>> A product from the store</label>
              <label class="switch"><input type="radio" name="gift_type" value="custom" data-gift-type <?= $gv['gift_type'] === 'custom' ? 'checked' : '' ?>> A custom item (not sold)</label>
            </div></div>

          <div class="field" data-gift-for="product" <?= $gv['gift_type'] === 'custom' ? 'hidden' : '' ?>><label for="giftQ">Gift product</label>
            <div class="gift-pick" data-gift-pick data-self="<?= e($editing ? $id : '') ?>" data-gp-init="<?= e(json_encode($gPick, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)) ?>">
              <input type="hidden" name="gift_product_id" value="<?= e((string) $gv['gift_product_id']) ?>" data-gp-id>
              <div class="gp-chosen" data-gp-chosen hidden></div>
              <div class="gp-search" data-gp-search>
                <input class="input" id="giftQ" type="search" autocomplete="off" placeholder="Search by name, brand or ID" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="giftQList" data-gp-q>
                <div class="gp-list" id="giftQList" role="listbox" aria-label="Matching products" hidden data-gp-list></div>
              </div>
              <div class="hint" data-gp-hint aria-live="polite">Drafts are included, so a gift-only item can stay hidden from the shop. Pick this same product for buy one, get one free.</div>
            </div>
          </div>

          <div data-gift-for="custom" <?= $gv['gift_type'] === 'custom' ? '' : 'hidden' ?>>
            <div class="field"><label>Gift name</label><input class="input" name="gift_name" maxlength="200" value="<?= e($gv['gift_name']) ?>" placeholder="e.g. Travel-size hand cream"></div>
            <div class="field"><label>Gift image <span class="faint">(optional)</span></label>
              <?php if ($gv['gift_image']): ?><img src="<?= e(asrc($gv['gift_image'])) ?>" alt="" style="width:74px;height:74px;border-radius:10px;object-fit:cover;margin-bottom:8px;border:1px solid var(--a-border2)"><?php endif; ?>
              <input class="input" name="gift_image" value="<?= e($gv['gift_image']) ?>" placeholder="https://... or upload below">
              <input type="file" name="gift_image_file" accept="image/*" data-maxmb="10" style="margin-top:8px;font-size:12.5px"><div class="hint">JPG, PNG or WebP, max 10 MB. Without one, shoppers see a gift icon.</div>
            </div>
            <div class="field"><label>Gifts available <span class="faint">(blank = unlimited)</span></label>
              <input class="input" type="number" name="gift_stock" min="0" max="999999" step="1" value="<?= $gv['stock'] === null ? '' : (int) $gv['stock'] ?>" placeholder="Unlimited" data-gift-int>
              <input type="hidden" name="gift_stock_was" value="<?= $gv['stock'] === null ? '' : (int) $gv['stock'] ?>">
              <div class="hint">Counts down as orders use them. The offer hides itself at 0.</div></div>
          </div>

          <div class="f-row">
            <div class="field"><label>Quantity</label><input class="input" type="number" name="gift_qty" min="1" max="20" step="1" value="<?= (int) $gv['gift_qty'] ?>" data-gift-int></div>
            <div class="field"><label>Given for</label><select class="input" name="gift_per_unit">
              <option value="1" <?= (int)$gv['per_unit'] ? 'selected' : '' ?>>Every unit bought</option>
              <option value="0" <?= (int)$gv['per_unit'] ? '' : 'selected' ?>>Each order</option>
            </select></div>
          </div>
          <p class="hint gift-rule" data-gift-rule></p>
          <div class="field"><label>Note for shoppers <span class="faint">(optional)</span></label><input class="input" name="gift_note" maxlength="200" value="<?= e($gv['note']) ?>" placeholder="Added to your order automatically, while supplies last."><div class="hint">Shown with the gift on the product page. Up to 200 characters.</div></div>
          <div class="f-row">
            <div class="field"><label>Starts <span class="faint">(optional)</span></label><input class="input" type="date" name="gift_starts_on" value="<?= e((string) $gv['starts_on']) ?>"></div>
            <div class="field"><label>Ends <span class="faint">(optional)</span></label><input class="input" type="date" name="gift_ends_on" value="<?= e((string) $gv['ends_on']) ?>"></div>
          </div>
          <p class="hint" style="margin:-8px 0 0">Leave the dates blank to run it until you switch it off. The end date counts as a gift day.</p>
        </div>
      <?php endif; ?></div></div>

      <div class="a-card"><div class="hd"><h2>Catalog</h2></div><div class="bd">
        <div class="f-row">
          <div class="field"><label>Barcode (EAN)</label><input class="input" name="barcode" value="<?= e($v['barcode'] ?? '') ?>"></div>
          <div class="field"><label>SKU / item #</label><input class="input" name="sku" value="<?= e($v['sku'] ?? '') ?>"></div>
        </div>
        <div class="f-row">
          <div class="field"><label>Size</label><input class="input" name="size" value="<?= e($v['size'] ?? '') ?>" placeholder="e.g. 150 ml"></div>
          <div class="field"><label>Sold by <span class="faint">(the unit a customer buys)</span></label>
            <?php $unit=trim((string)($v['unit'] ?? '')); $units=[''=>'Each / standard','sachet'=>'Sachet','sheet'=>'Sheet','box'=>'Box','pack'=>'Pack']; if($unit!=='' && !isset($units[$unit])) $units[$unit]=ucfirst($unit); ?>
            <select class="input" name="unit">
              <?php foreach($units as $uk=>$ul): ?><option value="<?= e($uk) ?>" <?= $unit===$uk?'selected':'' ?>><?= e($ul) ?></option><?php endforeach; ?>
            </select>
            <div class="hint">e.g. mask sheets are bought per <b>sachet</b>. “Each / standard” shows no unit label.</div></div>
        </div>
      </div></div>

      <div class="a-card var-card" id="options"><div class="hd"><h2>Options / variants <span class="faint" style="font-weight:400;font-size:12.5px">(optional)</span></h2></div><div class="bd">
        <p class="hint" style="margin:0 0 14px">Colours, flavours and/or sizes the customer picks before adding to bag. <b>The size sets the price; a colour or flavour can add a surcharge on top.</b> The <b>Default</b> size always uses the product's base price above. The <b>★ Primary</b> colour and flavour are selected when the product page opens, and their extra is in the price shoppers first see (base + primary colour + primary flavour). Click <b>☆</b> on another row to make it primary. Click the <b>camera</b> on any row to give that option its own photo; it shows when the shopper picks it. For models or types, add them as <b>Flavours</b> and rename the group (e.g. “Model”).</p>
        <div class="var-groups">
          <div class="field"><label>Colours</label>
            <input type="hidden" name="opt_colors" id="optColors" value="<?= e($v['opt_colors'] ?? '') ?>">
            <div id="colorRows" class="var-rows"></div>
            <div class="swatch-pick" id="swatchPick"></div>
            <div class="hint">Click a colour to add it. Set a <b>+$</b> if it costs extra (0 = no surcharge).</div></div>
          <div class="field"><label>Flavours</label>
            <input type="hidden" name="opt_flavors" id="optFlavors" value="<?= e((string) ($v['opt_flavors'] ?? '')) ?>">
            <div class="flv-head" id="flvHead">
              <div class="flv-name"><label for="optFlavorName">Group name</label><input class="input" id="optFlavorName" name="opt_flavor_name" maxlength="40" value="<?= e((string) ($v['opt_flavor_name'] ?? '')) ?>" placeholder="Flavour"></div>
              <div class="hint flv-name-hint">What shoppers see above the choices, e.g. Flavour, Scent, Type.</div>
            </div>
            <div id="flavorRows" class="var-rows"></div>
            <button type="button" class="btn btn-ghost btn-sm" id="addFlavor"><?= aicon('plus') ?> Add flavour</button>
            <div class="hint flv-limit" id="flvLimit" hidden>That's 40, the most one product can have.</div>
            <div class="hint">Shoppers pick one before adding to bag. Set a <b>+$</b> if a flavour costs extra (blank = same price). All flavours share the stock above.</div></div>
          <div class="field"><label>Sizes</label>
            <input type="hidden" name="opt_sizes" id="optSizes" value="<?= e($v['opt_sizes'] ?? '') ?>">
            <input type="hidden" name="opt_images" id="optImages" value="<?= e((string) ($v['opt_images'] ?? '')) ?>">
            <div id="sizeRows" class="var-rows"></div>
            <button type="button" class="btn btn-ghost btn-sm" id="addSize"><?= aicon('plus') ?> Add size</button>
            <div class="hint">The <b>★ Primary</b> size is the product at its price above (its name is the <b>Size</b> field); the product opens with it. Extra sizes get their own price — click <b>☆</b> on one to make it the primary size. Sizes only apply once you add at least one extra size. Remove the primary row (×) when every size has its own price.</div></div>
        </div>
      </div></div>

      <div class="a-card"><div class="hd"><h2>Visibility</h2></div><div class="bd">
        <div class="field"><label>Status</label><select class="input" name="status">
          <option value="active" <?= $v['status']==='active'?'selected':'' ?>>Active (visible)</option>
          <option value="draft"  <?= $v['status']==='draft'?'selected':'' ?>>Draft (hidden)</option>
        </select></div>
        <label class="switch" style="margin-bottom:10px"><input type="checkbox" name="feat_latest" value="1" <?= $v['feat_latest']?'checked':'' ?>> Show in homepage “New Arrivals”</label><br>
        <label class="switch" style="margin-bottom:12px"><input type="checkbox" name="feat_trending" value="1" <?= $v['feat_trending']?'checked':'' ?>> Show in homepage “Trending Now”</label>
        <div class="field" style="margin-top:8px"><label>Homepage order</label><input class="input" type="number" name="home_sort" value="<?= e($v['home_sort']) ?>"></div>
      </div></div>
    </div>
  </div>
  <div class="page-actions" style="margin-top:18px"><div class="spacer"></div><button class="btn btn-primary">Save product</button><button class="btn btn-ghost" name="after" value="list">Save &amp; back to list</button></div>
</form>

<style>
  .gal-grid{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px}
  .gal-grid:empty{display:none}
  .gal-tile{position:relative;width:92px}
  .gal-tile .ph{width:92px;height:92px;border-radius:10px;object-fit:cover;border:1px solid var(--a-border2,#e6e1d6);display:block;background:#f4f1ea}
  .gal-tile .cap{margin-top:4px;font-size:10.5px;line-height:1.3;color:#8a7d6e;word-break:break-all;max-height:28px;overflow:hidden}
  .gal-tile .x{position:absolute;top:5px;right:5px;width:26px;height:26px;border:0;border-radius:7px;background:rgba(176,74,47,.92);color:#fff;cursor:pointer;display:none;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.28);padding:0}
  .gal-tile:hover .x{display:flex}
  .gal-tile .x:hover{background:#b04a2f}
  .gal-tile .x svg{width:14px;height:14px}
  .gal-tile.up .ph{border-style:dashed;border-color:#8fae7e}
  .gal-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
  .gal-url{display:flex;gap:8px;flex:1;min-width:240px}
  .gal-url .input{flex:1}
  .gal-note{margin-top:8px;font-size:12.5px;line-height:1.5}
  .gal-note:empty{display:none}
  /* free gift card */
  .gift-card{scroll-margin-top:84px}
  .gift-card:target{box-shadow:0 0 0 3px var(--a-primary-tint),var(--a-shadow)}
  .gift-saved{margin:10px 0 0}
  .gift-body{margin-top:16px;padding-top:16px;border-top:1px solid var(--a-border2);transition:opacity .15s}
  .gift-body.is-off{opacity:.55}
  .gift-types{display:flex;flex-wrap:wrap;gap:6px 18px}
  /* the options sit inside a .field, whose caption rule (.field label: block, 13px, bold)
     would otherwise beat .switch; look like every other label.switch in the admin */
  .gift-types .switch{display:inline-flex;margin:0;font-size:inherit;font-weight:500}
  .gift-rule{margin:-6px 0 16px}
  .gift-rule:empty{display:none}
  .gp-search{position:relative}
  .gp-list{position:absolute;left:0;right:0;top:calc(100% + 6px);z-index:30;max-height:330px;overflow:auto;padding:6px;
    background:#fff;border:1px solid var(--a-border);border-radius:12px;box-shadow:0 14px 38px rgba(44,38,31,.16)}
  .gp-opt{display:flex;align-items:center;gap:10px;padding:7px 8px;border-radius:9px;cursor:pointer}
  .gp-opt.on{background:var(--a-primary-tint)}
  .gp-empty{padding:10px;font-size:13px;color:var(--a-faint)}
  .gp-thumb{width:42px;height:42px;flex:none;border-radius:8px;object-fit:contain;background:#fff;border:1px solid var(--a-border2)}
  .gp-miss{display:flex;align-items:center;justify-content:center;background:var(--a-bad-tint);color:var(--a-bad);font-weight:700}
  .gp-txt{flex:1;min-width:0;display:flex;flex-direction:column;font-size:13.5px;line-height:1.35}
  .gp-txt b{font-weight:600;overflow-wrap:anywhere}
  .gp-txt span{font-size:12px;color:var(--a-soft)}
  .gp-txt .gp-out{color:var(--a-bad)}
  .gp-txt .gp-bogo{margin-top:3px;font-weight:600;color:var(--a-primary)}
  .gp-chosen{display:flex;align-items:center;gap:12px;padding:10px 12px;border:1px solid var(--a-border);border-radius:12px;background:var(--a-primary-tint)}
  .gp-chosen[hidden]{display:none}
  /* options card: colours, flavours and sizes, one group under the other */
  .var-card{scroll-margin-top:84px}
  .var-card:target{box-shadow:0 0 0 3px var(--a-primary-tint),var(--a-shadow)}
  .var-groups>.field{margin-bottom:0}
  .var-groups>.field+.field{margin-top:18px;padding-top:18px;border-top:1px solid var(--a-border2)}
  .flv-head[hidden]{display:none}
  .flv-head{margin-bottom:16px}
  .flv-name{display:flex;align-items:center;gap:10px}
  .field .flv-name label{flex:none;margin:0;font-size:12.5px;font-weight:500;color:var(--a-soft)}
  .flv-name .input{flex:1;min-width:0;max-width:240px}
  .flv-name-hint{margin:5px 0 12px}
  #flavorRows .input:invalid{border-color:var(--a-bad)}
  #addFlavor:disabled{opacity:.5;cursor:not-allowed}
  .field .flv-limit{color:var(--a-warn)}
  .is-saving .page-actions button.btn{opacity:.65;cursor:progress}
</style>
<script>
(function(){
  var box=document.querySelector('[data-gallery]'); if(!box) return;
  var grid=box.querySelector('[data-gal-grid]');
  var store=box.querySelector('[data-gal-store]');
  var fileInput=box.querySelector('[data-gal-files]');
  var pickBtn=box.querySelector('[data-gal-pick]');
  var urlInput=box.querySelector('[data-gal-url]');
  var urlAdd=box.querySelector('[data-gal-url-add]');
  var note=box.querySelector('[data-gal-note]');
  if(typeof DataTransfer==='undefined') return;   // very old browser: leave the (hidden) native inputs as-is

  // hidden picker: opens the file dialog; its selections are merged into `files` (the named input keeps them all)
  var picker=document.createElement('input');
  picker.type='file'; picker.accept='image/*'; picker.multiple=true; picker.style.display='none';
  box.appendChild(picker);

  var urls=(store.value||'').split(/\r\n|\r|\n/).map(function(s){return s.trim();}).filter(Boolean);
  urls=urls.filter(function(u,i){return urls.indexOf(u)===i;});   // drop any pre-existing repeats
  var files=[];                       // [{file, key, url}]
  var seen=Object.create(null);       // content-hash (or name:size) -> true, for de-dupe

  function asrc(v){ return (!v||/^(https?:|\/|data:|blob:)/.test(v)) ? v : '../'+v; }
  function trashSvg(){ return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"/></svg>'; }
  function say(msg,ok){ note.style.color=ok?'#4a7a3a':'#b04a2f'; note.textContent=msg||''; }
  var keysField=document.createElement('textarea'); keysField.name='gallery_keys'; keysField.hidden=true; box.appendChild(keysField);
  function syncStore(){ store.value=urls.join('\n'); if(window.prodPhotosChanged) window.prodPhotosChanged(); }
  /* gallery_keys lines up with the uploaded files, in order, so a colour / flavour / size that
     was given one of these new photos (new:<key>) can be pointed at its saved path */
  function syncFiles(){ var dt=new DataTransfer(); files.forEach(function(f){dt.items.add(f.file);}); fileInput.files=dt.files; keysField.value=files.map(function(f){return f.key;}).join('\n'); budget(); if(window.prodPhotosChanged) window.prodPhotosChanged(); }
  /* the server takes post_max_size per save: say so BEFORE the save is lost, not after */
  function budget(){
    var lim=window.adminUploadLimits(), total=files.reduce(function(s,f){return s+f.file.size;},0);
    if(lim.post && total > lim.post*0.85) say('These new photos add up to '+(total/1048576).toFixed(1)+' MB — the server takes about '+(lim.post/1048576*0.85).toFixed(0)+' MB per save. Save now, then add the rest.',false);
    else if(lim.files && files.length > lim.files-3) say('That is '+files.length+' new photos — save now, then add more (the server takes '+lim.files+' files per save).',false);
  }
  function hex(buf){ return Array.prototype.map.call(new Uint8Array(buf),function(b){return b.toString(16).padStart(2,'0');}).join(''); }

  function tile(src,cap,isUp,onRemove){
    var t=document.createElement('div'); t.className='gal-tile'+(isUp?' up':'');
    var img=document.createElement('img'); img.className='ph'; img.src=src; img.loading='lazy'; t.appendChild(img);
    var x=document.createElement('button'); x.type='button'; x.className='x'; x.title='Remove'; x.innerHTML=trashSvg();
    x.addEventListener('click',function(e){e.preventDefault();onRemove();}); t.appendChild(x);
    var c=document.createElement('div'); c.className='cap'; c.textContent=cap; t.appendChild(c);
    grid.appendChild(t);
  }
  function render(){
    grid.innerHTML='';
    urls.forEach(function(u,i){ tile(asrc(u),u,false,function(){ urls.splice(i,1); syncStore(); render(); say('Removed.',true); }); });
    files.forEach(function(f,i){ tile(f.url,f.file.name+' (new)',true,function(){ delete seen[f.key]; URL.revokeObjectURL(f.url); files.splice(i,1); syncFiles(); render(); say('Removed.',true); }); });
  }

  function keyFor(file){
    if(window.crypto&&crypto.subtle&&file.arrayBuffer){
      return file.arrayBuffer()
        .then(function(buf){ return crypto.subtle.digest('SHA-256',buf); })
        .then(function(h){ return hex(h); })
        .catch(function(){ return 'ns:'+file.name+':'+file.size; });
    }
    return Promise.resolve('ns:'+file.name+':'+file.size);
  }

  // best-effort: fingerprint already-saved same-origin images so re-uploading one is caught too
  urls.forEach(function(u){
    if(!(window.crypto&&crypto.subtle)) return;
    try{
      fetch(asrc(u)).then(function(r){ return r.ok?r.blob():null; })
        .then(function(b){ return (b&&b.arrayBuffer)?b.arrayBuffer():null; })
        .then(function(buf){ return buf?crypto.subtle.digest('SHA-256',buf):null; })
        .then(function(h){ if(h) seen[hex(h)]=true; }).catch(function(){});
    }catch(e){}
  });

  pickBtn.addEventListener('click',function(){ picker.click(); });
  picker.addEventListener('change',function(){
    var chosen=Array.prototype.slice.call(picker.files||[]); picker.value='';
    if(!chosen.length) return;
    say('Adding…',true);
    var added=0,dup=0,bad=0,pending=chosen.length;
    function done(){
      if(--pending>0) return;
      var parts=[];
      if(added) parts.push(added+' added');
      if(dup)   parts.push(dup+' duplicate'+(dup>1?'s':'')+' skipped');
      if(bad)   parts.push(bad+' unsupported/too-large skipped');
      say(parts.join(' · ')||'Nothing added', !(dup||bad));
    }
    /* one after the other, so the photos land in the order they were picked */
    chosen.reduce(function(chain,file0){ return chain.then(function(){
      if(!/\.(jpe?g|png|webp|gif|avif)$/i.test(file0.name)||file0.size>25*1048576){ bad++; done(); return; }
      /* the duplicate check reads the ORIGINAL; what is uploaded is the shrunk copy */
      return keyFor(file0).then(function(k){
        if(seen[k]){ dup++; done(); return; }
        seen[k]=true;
        return window.adminShrink(file0).then(function(file){
          var lim=window.adminUploadLimits();
          if(lim.file && file.size>lim.file){ delete seen[k]; bad++; done(); return; }
          files.push({file:file,key:k.slice(0,40),url:URL.createObjectURL(file)}); added++; syncFiles(); render(); done();
        });
      });
    }); }, Promise.resolve());
  });

  /* the product's photos, for the colour / flavour / size photo pickers below:
     saved ones by path, photos added in this visit as new:<key> */
  window.prodPhotos={
    list:function(){
      var out=[], seenV={};
      function add(v,src){ if(v&&!seenV[v]){ seenV[v]=1; out.push({val:v,src:src||asrc(v)}); } }
      var main=document.querySelector('input[name="image"]'), hov=document.querySelector('input[name="hover_image"]');
      if(main) add(main.value.trim()); if(hov) add(hov.value.trim());
      urls.forEach(function(u){ add(u); });
      files.forEach(function(f){ add('new:'+f.key, f.url); });
      return out;
    },
    src:function(v){ if(!v) return ''; if(v.indexOf('new:')===0){ var f=files.find(function(x){return 'new:'+x.key===v;}); return f?f.url:''; } return asrc(v); },
    /* a photo picked from the option's own button joins the gallery; resolves to its new:<key> */
    add:function(file0){
      return keyFor(file0).then(function(k){
        var hit=files.find(function(x){return x.key===k.slice(0,40);}); if(hit) return 'new:'+hit.key;
        return window.adminShrink(file0).then(function(file){
          seen[k]=true; var f={file:file,key:k.slice(0,40),url:URL.createObjectURL(file)};
          files.push(f); syncFiles(); render(); say('Photo added to the gallery — save to keep it.',true); return 'new:'+f.key;
        });
      });
    }
  };

  function addUrl(){
    var u=(urlInput.value||'').trim();
    if(!u){ say('Enter an image URL first.',false); return; }
    if(urls.indexOf(u)!==-1){ say('That URL is already in the gallery.',false); return; }
    urls.push(u); syncStore(); render(); urlInput.value=''; say('URL added.',true);
  }
  urlAdd.addEventListener('click',function(e){ e.preventDefault(); addUrl(); });
  urlInput.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); addUrl(); } });

  syncStore(); render();
})();

/* ---- variant editors: sizes (permanent Default + extras) + colours (swatch palette) ---- */
(function(){
  var priceInput = document.querySelector('input[name="price"]');
  var sizeInput  = document.querySelector('input[name="size"]');
  function base(){ var v=parseFloat(priceInput?priceInput.value:'0'); return isNaN(v)?0:v; }
  function esc(t){ return String(t==null?'':t).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
  function fmt(n){ return (Math.round(n*100)/100).toString(); }
  function parseOpts(str){
    return String(str||'').split(/\r\n|\r|\n/).map(function(l){return l.trim();}).filter(Boolean).map(function(l){
      var i=l.indexOf('|'); if(i<0) return {label:l, price:null};
      var pr=l.slice(i+1).trim(); return {label:l.slice(0,i).trim(), price: pr===''?null:parseFloat(pr)};
    }).filter(function(o){return o.label;});
  }
  /* ===== SALE: the % lowers the price; optionally the sizes' prices and the extras too.
     Each priced row shows what it sells at during the sale ("→ $4"). ===== */
  var salePct=document.getElementById('salePct'), saleOpts=document.getElementById('saleOpts'),
      saleOptsWrap=document.getElementById('saleOptsWrap'), saleNow=document.getElementById('saleNow');
  function saleF(){ var s=parseInt(salePct&&salePct.value,10); return (s>0&&s<100)?(100-s)/100:1; }
  function saleOnOpts(){ return saleF()<1 && !!(saleOpts&&saleOpts.checked); }
  function onSale(v){ return Math.round(v*saleF()*100)/100; }
  /* the "→ $x" after a row's price input (sizes' own prices and the extras) */
  function rowSale(row,v){
    var old=row.querySelector('.vr-sale'); if(old) old.remove();
    var n=parseFloat(v); if(!saleOnOpts()||!(n>0)) return;
    var s=document.createElement('span'); s.className='vr-sale'; s.title='Price during the sale'; s.textContent='→ $'+fmt(onSale(n));
    var pi=row.querySelector('.vr-price'); if(pi) pi.insertAdjacentElement('afterend',s);
  }
  /* the primary size's price is the product price: the sale always applies to it */
  function baseSale(row){
    var old=row.querySelector('.vr-sale'); if(old) old.remove();
    var n=base(); if(saleF()>=1||!(n>0)) return;
    var s=document.createElement('span'); s.className='vr-sale'; s.title='Price during the sale'; s.textContent='→ $'+fmt(onSale(n));
    var pi=row.querySelector('.vr-price'); if(pi) pi.insertAdjacentElement('afterend',s);
  }
  function paintSale(){
    var f=saleF();
    if(saleOptsWrap) saleOptsWrap.hidden = f>=1;
    if(saleNow) saleNow.innerHTML = (f<1 && base()>0)
      ? 'Sells at <b>$'+fmt(onSale(base()))+'</b> with <s>$'+fmt(base())+'</s> crossed out.' : '';
    document.querySelectorAll('.var-rows').forEach(function(r){ r.classList.toggle('has-sale', f<1); });
  }
  function saleChanged(){ paintSale(); renderSizes(); renderCols(); renderFlvs(); }
  if(salePct) salePct.addEventListener('input',saleChanged);
  if(saleOpts) saleOpts.addEventListener('change',saleChanged);
  if(priceInput) priceInput.addEventListener('input',paintSale);

  /* ===== SIZES: one permanent Default (base price, no remove) + extra priced sizes ===== */
  /* ===== OPTION PHOTOS: a photo per colour / flavour / size, shown when the shopper picks it =====
     Each row keeps its photo (row.img); saveImgs() writes {"color":{label:photo},…} to opt_images.
     A photo is one of the product's own (main, hover, gallery) or a new one, which joins the gallery. */
  var imgHidden=document.getElementById('optImages');
  var IMG={color:{},flavor:{},size:{}};
  try{ var ij=JSON.parse((imgHidden&&imgHidden.value)||'{}'); ['color','flavor','size'].forEach(function(g){ if(ij&&ij[g]&&typeof ij[g]==='object') IMG[g]=ij[g]; }); if(ij&&Array.isArray(ij.palette)) IMG.palette=ij.palette; }catch(e){}
  var defImg='';
  function saveImgs(){
    if(!imgHidden) return;
    var o={color:{},flavor:{},size:{}};
    (cols||[]).forEach(function(c){ var l=String(c.label).trim(); if(l&&c.img) o.color[l]=c.img; });
    (flvs||[]).forEach(function(f){ var l=String(f.label).trim(); if(l&&f.img) o.flavor[l]=f.img; });
    if(!noDefault && defImg){ o.size[curDef()||'Standard']=defImg; }
    (extras||[]).forEach(function(x){ var l=String(x.label).trim(); if(l&&x.img) o.size[l]=x.img; });
    if(typeof CUSTOM!=='undefined' && CUSTOM.length) o.palette=CUSTOM;   // own colours stay in the swatch row
    imgHidden.value=JSON.stringify(o);
  }
  var CAM='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>';
  function imgBtn(get,set){
    var b=document.createElement('button'); b.type='button'; b.className='vr-img';
    b._paint=function(){
      var v=get(), src=v&&window.prodPhotos?window.prodPhotos.src(v):'';
      if(v && !src) { set(''); v=''; }                                         // its new photo was removed from the gallery
      b.innerHTML=src?'<img src="'+esc(src)+'" alt="">':CAM;
      b.classList.toggle('has',!!src);
      b.title=src?'Photo shown for this option — click to change':'Add a photo for this option';
    };
    b.addEventListener('click',function(e){ e.preventDefault(); openPicker(b,get,set); });
    b._paint(); return b;
  }
  /* PRIMARY colour / flavour: the first row is the one the product page opens with (its extra
     is in the price shoppers first see). "Make primary" moves a row to the top. */
  function primBtn(idx,makePrimary){
    if(idx===0){ var s=document.createElement('span'); s.className='vr-prim icon on'; s.title='Primary: the product page opens with this one selected, and its extra is in the first price shown'; s.setAttribute('aria-label','Primary'); s.textContent='★'; return s; }
    var b=document.createElement('button'); b.type='button'; b.className='vr-prim icon'; b.textContent='☆';
    b.title='Make primary: the product page opens with this one selected'; b.setAttribute('aria-label','Make primary');
    b.addEventListener('click',function(e){ e.preventDefault(); makePrimary(); });
    return b;
  }
  window.prodPhotosChanged=function(){ document.querySelectorAll('.vr-img').forEach(function(b){ if(b._paint) b._paint(); }); };
  var pop=null;
  function closePicker(){ if(pop){ pop.remove(); pop=null; } }
  function openPicker(btn,get,set){
    closePicker();
    var list=window.prodPhotos?window.prodPhotos.list():[], cur=get();
    pop=document.createElement('div'); pop.className='vr-pop';
    pop.innerHTML='<div class="vr-pop-h">Photo shown when this option is picked</div>'+
      (list.length?'<div class="vr-pop-g">'+list.map(function(p,i){ return '<button type="button" data-i="'+i+'" class="'+(p.val===cur?'on':'')+'"><img src="'+esc(p.src)+'" alt=""></button>'; }).join('')+'</div>'
                  :'<div class="vr-pop-e">This product has no photos yet — upload one.</div>')+
      '<div class="vr-pop-a"><button type="button" class="btn btn-ghost btn-sm" data-up>＋ Upload photo</button>'+(cur?'<button type="button" class="btn btn-ghost btn-sm" data-none>No photo</button>':'')+'</div>';
    document.body.appendChild(pop);
    var r=btn.getBoundingClientRect();
    pop.style.top=(window.scrollY+r.bottom+6)+'px'; pop.style.left=Math.max(8,Math.min(window.scrollX+r.left, window.scrollX+document.documentElement.clientWidth-pop.offsetWidth-8))+'px';
    pop.querySelectorAll('[data-i]').forEach(function(t){ t.addEventListener('click',function(){ set(list[+t.dataset.i].val); btn._paint(); closePicker(); }); });
    var none=pop.querySelector('[data-none]'); if(none) none.addEventListener('click',function(){ set(''); btn._paint(); closePicker(); });
    pop.querySelector('[data-up]').addEventListener('click',function(){
      var fi=document.createElement('input'); fi.type='file'; fi.accept='image/*';
      fi.addEventListener('change',function(){ var f=fi.files&&fi.files[0]; if(!f||!window.prodPhotos) return;
        window.prodPhotos.add(f).then(function(v){ set(v); btn._paint(); }); closePicker(); });
      fi.click();
    });
  }
  document.addEventListener('click',function(e){ if(pop && !pop.contains(e.target) && !e.target.closest('.vr-img')) closePicker(); });
  document.addEventListener('keydown',function(e){ if(e.key==='Escape') closePicker(); });

  var sizeWrap=document.getElementById('sizeRows'), sizeHidden=document.getElementById('optSizes');
  var DEF_EMPTY='Standard size — set it in the “Size” field above';
  var defLabel='', extras=[];
  parseOpts(sizeHidden.value).forEach(function(o){ if(o.price===null && defLabel==='') defLabel=o.label; else extras.push({label:o.label, price:o.price==null?base():o.price, img:IMG.size[o.label]||''}); });
  defImg = IMG.size[defLabel] || (sizeInput && IMG.size[sizeInput.value.trim()]) || '';
  /* a product whose every size carries its own price (an import like "30 ml|9.90 / 50 ml|14.50")
     has NO default size. Opening it used to invent one from the "Size" field — a new $0 option
     the shop then sold. Such a product keeps its list as it is; the owner can add a default. */
  var noDefault = extras.length>0 && defLabel==='';
  /* the standard size name IS the "Size" field — mirror them so there's one place to type it */
  if(sizeInput && !noDefault){ if(sizeInput.value.trim()) defLabel=sizeInput.value.trim(); else if(defLabel) sizeInput.value=defLabel; }
  function curDef(){ return (sizeInput && sizeInput.value.trim()) || defLabel.trim() || ''; }
  function saveSizes(){
    var kept=extras.filter(function(x){return x.label.trim();}).map(function(x){return x.label.trim()+'|'+fmt(x.price||0);});
    if(!kept.length){ sizeHidden.value=''; saveImgs(); return; }
    sizeHidden.value = (noDefault ? kept : [curDef() || 'Standard'].concat(kept)).join('\n');
    saveImgs();
  }
  function renderSizes(){
    sizeWrap.innerHTML='';
    var d=document.createElement('div'); d.className='var-row is-default';
    var nm=curDef();
    if(noDefault){
      d.innerHTML='<span class="vr-dot vr-star">*</span><span class="vr-name vr-name-empty">No default size — every size below has its own price</span>'+
        '<button type="button" class="btn btn-ghost btn-sm" id="addDefault">Add a default size</button>';
      d.querySelector('#addDefault').addEventListener('click',function(){ noDefault=false; if(sizeInput&&sizeInput.value.trim()) defLabel=sizeInput.value.trim(); renderSizes(); if(sizeInput&&!sizeInput.value.trim()) sizeInput.focus(); });
    } else {
      /* the primary size: a row like every other option row. Its name IS the "Size" field and
         its price IS the product "Price" — typing in either place updates the other */
      d.className='var-row is-primary';
      d.innerHTML='<span class="vr-dot"></span>'+
        '<input class="input vr-label" placeholder="e.g. 50 ml" value="'+esc(nm)+'" aria-label="Primary size name">'+
        '<span class="vr-pfx">$</span><input class="input vr-price" type="number" step="0.01" min="0" value="'+(base()>0?esc(fmt(base())):'')+'" placeholder="price" aria-label="Primary size price (the product price)">'+
        (extras.length ? '<button type="button" class="vr-x" title="No primary size — sell only the sizes below">&times;</button>' : '');
      d.insertBefore(imgBtn(function(){return defImg;},function(v){ defImg=v; saveImgs(); }), d.querySelector('.vr-label'));
      d.insertBefore(primBtn(0,function(){}), d.querySelector('.vr-pfx'));
      var dl=d.querySelector('.vr-label'), dp=d.querySelector('.vr-price');
      dl.addEventListener('input',function(){ if(sizeInput) sizeInput.value=dl.value; defLabel=dl.value.trim(); saveSizes(); });
      dp.addEventListener('input',function(){ if(priceInput) priceInput.value=dp.value; paintSale(); baseSale(d); });
      baseSale(d);
      var dx=d.querySelector('.vr-x'); if(dx) dx.addEventListener('click',function(){ noDefault=true; renderSizes(); });
    }
    sizeWrap.appendChild(d);
    extras.forEach(function(x,idx){
      var row=document.createElement('div'); row.className='var-row';
      row.innerHTML='<span class="vr-dot"></span>'+
        '<input class="input vr-label" placeholder="e.g. 50 ml" value="'+esc(x.label)+'">'+
        '<span class="vr-pfx">$</span><input class="input vr-price" type="number" step="0.01" min="0" value="'+(x.price!=null?esc(x.price):'')+'" placeholder="price">'+
        '<button type="button" class="vr-x" title="Remove">&times;</button>';
      row.insertBefore(imgBtn(function(){return extras[idx].img||'';},function(v){ extras[idx].img=v; saveImgs(); }), row.querySelector('.vr-label'));
      row.querySelector('.vr-label').addEventListener('input',function(e){ extras[idx].label=e.target.value; saveSizes(); });
      row.querySelector('.vr-price').addEventListener('input',function(e){ extras[idx].price=e.target.value===''?0:parseFloat(e.target.value); saveSizes(); rowSale(row,extras[idx].price); });
      rowSale(row,x.price);
      /* ☆ makes this size the primary one: it becomes the product's price + "Size", and the old primary joins the list */
      row.insertBefore(primBtn(idx+1,function(){ makePrimarySize(idx); }), row.querySelector('.vr-pfx'));
      row.querySelector('.vr-x').addEventListener('click',function(){ extras.splice(idx,1); saveSizes(); renderSizes(); });
      sizeWrap.appendChild(row);
    });
    saveSizes();
  }
  document.getElementById('addSize').addEventListener('click',function(){ extras.push({label:'',price:base()}); renderSizes(); var l=sizeWrap.querySelector('.var-row:last-child .vr-label'); if(l) l.focus(); });
  if(priceInput) priceInput.addEventListener('input',renderSizes);   // the Default row shows the price (and warns while it is blank)
  function makePrimarySize(idx){
    var x=extras.splice(idx,1)[0];
    if(!noDefault) extras.unshift({label:curDef()||'Standard', price:base(), img:defImg});
    noDefault=false; defImg=x.img||''; defLabel=x.label;
    if(sizeInput) sizeInput.value=x.label;
    if(priceInput) priceInput.value=fmt(x.price||0);
    renderSizes(); paintSale();
  }
  if(sizeInput) sizeInput.addEventListener('input',function(){ defLabel=sizeInput.value.trim(); var n=sizeWrap.querySelector('.is-primary .vr-label'); if(n && document.activeElement!==n) n.value=sizeInput.value; saveSizes(); });
  renderSizes();

  /* ===== COLOURS: click a swatch to add it (name auto-filled), optional +$ surcharge ===== */
  var PALETTE=[['White','#ffffff'],['Black','#222222'],['Grey','#9ca3af'],['Silver','#cdd2d8'],['Red','#e23b3b'],['Pink','#f3a7c4'],['Orange','#f39a3e'],['Yellow','#f2d34e'],['Green','#4caf72'],['Teal','#16b8a6'],['Blue','#3b82f6'],['Navy','#25407a'],['Purple','#8b5cf6'],['Brown','#8a5a2b'],['Beige','#e7d5b8'],['Gold','#c9a24a']];
  var HEX={}; PALETTE.forEach(function(pl){ HEX[pl[0].toLowerCase()]=pl[1]; });
  HEX.gray=HEX.grey; HEX.gold=HEX.gold||'#c9a24a';   // "Gray" and "Grey" are the same swatch
  function hexOf(name){ return HEX[String(name).toLowerCase()] || (/^#/.test(name)?name:'#d8cfc0'); }
  var colWrap=document.getElementById('colorRows'), colHidden=document.getElementById('optColors'), pick=document.getElementById('swatchPick');
  var cols=parseOpts(colHidden.value).map(function(o){return {label:o.label, price:(o.price!=null&&o.price>0)?o.price:0, img:IMG.color[o.label]||''};});
  function saveCols(){ colHidden.value = cols.filter(function(c){return String(c.label).trim();}).map(function(c){ return c.price>0 ? (String(c.label).trim()+'|'+fmt(c.price)) : String(c.label).trim(); }).join('\n'); saveImgs(); }
  function renderCols(){
    colWrap.innerHTML='';
    cols.forEach(function(c,idx){
      var row=document.createElement('div'); row.className='var-row';
      row.innerHTML='<span class="vr-dot" style="background:'+esc(hexOf(c.label))+'"></span>'+
        '<input class="input vr-label" value="'+esc(c.label)+'" placeholder="colour name">'+
        '<span class="vr-pfx">+$</span><input class="input vr-price" type="number" step="0.01" min="0" value="'+(c.price>0?esc(c.price):'')+'" placeholder="0">'+
        '<button type="button" class="vr-x" title="Remove">&times;</button>';
      row.querySelector('.vr-label').addEventListener('input',function(e){ cols[idx].label=e.target.value; saveCols(); });
      row.querySelector('.vr-price').addEventListener('input',function(e){ cols[idx].price=e.target.value===''?0:parseFloat(e.target.value); saveCols(); rowSale(row,cols[idx].price); });
      rowSale(row,c.price);
      row.querySelector('.vr-x').addEventListener('click',function(){ cols.splice(idx,1); saveCols(); renderCols(); renderPalette(); });
      row.insertBefore(imgBtn(function(){return cols[idx].img||'';},function(v){ cols[idx].img=v; saveImgs(); }), row.querySelector('.vr-label'));
      if(cols.length>1) row.insertBefore(primBtn(idx,function(){ cols.unshift(cols.splice(idx,1)[0]); saveCols(); renderCols(); renderPalette(); }), row.querySelector('.vr-pfx'));
      colWrap.appendChild(row);
    });
    saveCols();
  }
  /* Swatches toggle: ✓ = this product has the colour (click again to take it off). Custom
     colours (the + swatch: pick in the wheel, name it, Add) stay in the row even when not
     ticked, so they are never lost for this product — kept in opt_images.palette. */
  var CUSTOM=[];
  (IMG.palette||[]).forEach(function(c){ if(c&&c.name&&/^#[0-9a-f]{6}$/i.test(c.hex||'')) CUSTOM.push({name:String(c.name), hex:c.hex}); });
  cols.forEach(function(c){   // a colour on the product that isn't a standard swatch (e.g. "#ff8800") joins the row
    var l=String(c.label), known=PALETTE.some(function(pl){return pl[0].toLowerCase()===l.toLowerCase();}) || CUSTOM.some(function(x){return x.name.toLowerCase()===l.toLowerCase();});
    if(!known && /^#[0-9a-f]{6}$/i.test(l)) CUSTOM.push({name:l, hex:l});
  });
  CUSTOM.forEach(function(c){ HEX[c.name.toLowerCase()]=c.hex; });
  function same(a,b){ a=String(a).toLowerCase().replace('gray','grey'); b=String(b).toLowerCase().replace('gray','grey'); return a===b; }
  function isUsed(name){ return cols.some(function(c){return same(c.label,name);}); }
  function toggleColour(name){
    if(isUsed(name)) cols=cols.filter(function(c){return !same(c.label,name);});
    else cols.push({label:name, price:0, img:''});
    saveCols(); renderCols(); renderPalette();
  }
  var addPanel=null;
  function renderPalette(){
    pick.innerHTML='';
    PALETTE.map(function(pl){return {name:pl[0], hex:pl[1]};}).concat(CUSTOM).forEach(function(sw){
      var used=isUsed(sw.name);
      var b=document.createElement('button'); b.type='button'; b.className='sw'+(used?' used':''); b.style.background=sw.hex;
      b.title=sw.name+(used?' — click to take it off':' — click to add');
      b.addEventListener('click',function(){ toggleColour(sw.name); });
      pick.appendChild(b);
    });
    var cw=document.createElement('button'); cw.type='button'; cw.className='sw sw-custom'+(addPanel?' open':''); cw.title='Add your own colour'; cw.textContent='+';
    cw.addEventListener('click',function(){ addPanel=!addPanel; renderPalette(); });
    pick.appendChild(cw);
    if(addPanel){
      var pn=document.createElement('div'); pn.className='sw-add';
      pn.innerHTML='<input type="color" value="#c06c84" aria-label="Pick the colour"><input class="input" maxlength="40" placeholder="Name (optional), e.g. Rose gold" aria-label="Colour name"><button type="button" class="btn btn-primary btn-sm">Add colour</button>';
      var wheel=pn.querySelector('input[type=color]'), nm=pn.querySelector('.input');
      function add(){
        var hex=wheel.value.toLowerCase(), name=(nm.value||'').trim().replace(/\|/g,'')||hex;
        if(!CUSTOM.some(function(x){return x.name.toLowerCase()===name.toLowerCase();}) && !PALETTE.some(function(pl){return pl[0].toLowerCase()===name.toLowerCase();})) CUSTOM.push({name:name, hex:hex});
        HEX[name.toLowerCase()]=hex;
        addPanel=false;
        if(!isUsed(name)) cols.push({label:name, price:0, img:''});
        saveCols(); renderCols(); renderPalette();
      }
      pn.querySelector('button').addEventListener('click',add);
      nm.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); add(); } });
      pick.appendChild(pn);
      setTimeout(function(){ nm.focus(); },0);
    }
  }
  renderCols(); renderPalette();

  /* ===== FLAVOURS: a named list (Flavour, Scent, Type...), each with an optional +$ surcharge ===== */
  var FLV_MAX=40;
  var flvWrap=document.getElementById('flavorRows'), flvHidden=document.getElementById('optFlavors'), addFlv=document.getElementById('addFlavor');
  var flvHead=document.getElementById('flvHead'), flvLimit=document.getElementById('flvLimit'), flvName=document.getElementById('optFlavorName');
  var flvs=parseOpts(flvHidden.value).map(function(o){return {label:o.label, price:(o.price!=null&&o.price>0)?String(o.price):'', img:IMG.flavor[o.label]||''};});
  function saveFlvs(){
    flvHidden.value = flvs.filter(function(f){return f.label.trim();}).map(function(f){
      var n=parseFloat(f.price); return (n && !isNaN(n)) ? f.label.trim()+'|'+fmt(n) : f.label.trim();   // a negative one is sent on, for the server to name
    }).join('\n');
    saveImgs();
  }
  /* "|" separates the name from its price when saved, so a name can't hold one */
  function checkFlv(inp){ inp.setCustomValidity(inp.value.indexOf('|')>=0 ? "A name can't contain the | character." : ''); }
  function renderFlvs(focusIdx){
    flvWrap.innerHTML='';
    flvs.forEach(function(f,idx){
      var row=document.createElement('div'); row.className='var-row';
      row.innerHTML='<span class="vr-dot"></span>'+
        '<input class="input vr-label" maxlength="60" value="'+esc(f.label)+'" placeholder="e.g. Vanilla" aria-label="Flavour name">'+
        '<span class="vr-pfx">+$</span><input class="input vr-price" type="number" step="0.01" min="0" value="'+esc(f.price)+'" placeholder="0" aria-label="Extra price for this flavour">'+
        '<button type="button" class="vr-x" title="Remove" aria-label="Remove this flavour">&times;</button>';
      var lab=row.querySelector('.vr-label'), pr=row.querySelector('.vr-price');
      row.insertBefore(imgBtn(function(){return flvs[idx].img||'';},function(v){ flvs[idx].img=v; saveImgs(); }), lab);
      if(flvs.length>1) row.insertBefore(primBtn(idx,function(){ flvs.unshift(flvs.splice(idx,1)[0]); renderFlvs(); }), row.querySelector('.vr-pfx'));
      checkFlv(lab);
      lab.addEventListener('input',function(){ flvs[idx].label=lab.value; checkFlv(lab); saveFlvs(); });
      pr.addEventListener('input',function(){ flvs[idx].price=pr.value; saveFlvs(); rowSale(row,pr.value); });
      rowSale(row,f.price);
      lab.addEventListener('keydown',function(e){ flvEnter(e,idx); });
      pr.addEventListener('keydown',function(e){ flvEnter(e,idx); });
      row.querySelector('.vr-x').addEventListener('click',function(){ flvs.splice(idx,1); renderFlvs(Math.min(idx,flvs.length-1)); });
      flvWrap.appendChild(row);
    });
    flvHead.hidden=!flvs.length;
    addFlv.disabled=flvs.length>=FLV_MAX; flvLimit.hidden=flvs.length<FLV_MAX;
    saveFlvs();
    if(focusIdx!=null){ var l=flvWrap.querySelectorAll('.vr-label')[focusIdx]; (l||addFlv).focus(); }
  }
  function isEnter(e){ return e.key==='Enter' && !e.isComposing && e.keyCode!==229; }   // not the Enter that ends an IME word
  /* Enter in a flavour adds the next one instead of submitting the whole product form */
  function flvEnter(e,idx){
    if(!isEnter(e)) return;
    e.preventDefault();
    if(!flvs[idx].label.trim()) return;                                       // nothing typed yet
    if(flvs[idx+1] && !flvs[idx+1].label.trim()){ flvWrap.querySelectorAll('.vr-label')[idx+1].focus(); return; }
    if(flvs.length>=FLV_MAX) return;                                          // the limit note is showing
    flvs.splice(idx+1,0,{label:'',price:''}); renderFlvs(idx+1);
  }
  addFlv.addEventListener('click',function(){
    var last=flvs.length-1;
    if(last>=0 && !flvs[last].label.trim()){ renderFlvs(last); return; }      // reuse the empty row at the end
    if(flvs.length>=FLV_MAX) return;
    flvs.push({label:'',price:''}); renderFlvs(flvs.length-1);
  });
  flvName.addEventListener('keydown',function(e){ if(isEnter(e)){ e.preventDefault(); var l=flvWrap.querySelector('.vr-label'); if(l) l.focus(); } });
  renderFlvs();
  paintSale();
})();

/* ---- free gift card: on/off, gift type, and a single-product picker fed by admin/product-search ---- */
(function(){
  var box=document.querySelector('[data-gift]'); if(!box) return;
  var on=box.querySelector('[data-gift-on]'), body=box.querySelector('[data-gift-body]'), saved=box.querySelector('[data-gift-saved]');
  var hasOffer=body.getAttribute('data-gift-saved-offer')==='1';
  var types=Array.prototype.slice.call(box.querySelectorAll('[data-gift-type]'));
  var qty=box.querySelector('[name="gift_qty"]'), per=box.querySelector('[name="gift_per_unit"]'), rule=box.querySelector('[data-gift-rule]');
  function esc(t){ return String(t==null?'':t).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function type(){ var t='product'; types.forEach(function(r){ if(r.checked) t=r.value; }); return t; }

  /* An invalid number the operator can no longer see would block the PRODUCT save
     ("invalid form control is not focusable"), so anything hidden is put back. */
  function resetHidden(){
    box.querySelectorAll('input').forEach(function(i){ if(i.closest('[hidden]') && i.validity && !i.validity.valid) i.value=i.defaultValue; });
  }
  function sync(){
    body.hidden=!on.checked && !hasOffer;                      // no offer yet: just the switch
    body.classList.toggle('is-off',!on.checked);
    if(saved) saved.hidden=on.checked;
    var t=type();
    box.querySelectorAll('[data-gift-for]').forEach(function(el){ el.hidden=el.getAttribute('data-gift-for')!==t; });
    resetHidden();
  }
  function ruleText(){
    var n=Math.max(1,Math.min(20,parseInt(qty.value,10)||1));
    rule.textContent = per.value==='1' ? ('A shopper buying 3 gets '+(n*3)+' free.') : ('A shopper gets '+n+' free per order, however many they buy.');
  }
  on.addEventListener('change',sync);
  types.forEach(function(r){ r.addEventListener('change',sync); });
  qty.addEventListener('input',ruleText); per.addEventListener('change',ruleText);
  /* whole numbers, kept inside min/max as soon as the field is left */
  box.querySelectorAll('[data-gift-int]').forEach(function(inp){
    inp.addEventListener('change',function(){
      if(inp.value===''){ if(inp.min==='1') inp.value='1'; return; }   // blank stock = unlimited; blank quantity = 1
      var n=Math.round(parseFloat(inp.value)); if(isNaN(n)) n=+inp.min||0;
      if(inp.min!=='' && n<+inp.min) n=+inp.min;
      if(inp.max!=='' && n>+inp.max) n=+inp.max;
      inp.value=n; ruleText();
    });
  });

  /* ---- store-product picker ---- */
  var pick=box.querySelector('[data-gift-pick]');
  var hid=pick.querySelector('[data-gp-id]'), chosen=pick.querySelector('[data-gp-chosen]'), sbox=pick.querySelector('[data-gp-search]');
  var q=pick.querySelector('[data-gp-q]'), list=pick.querySelector('[data-gp-list]'), hint=pick.querySelector('[data-gp-hint]');
  var SELF=pick.getAttribute('data-self')||'', HINT=hint.textContent;
  var cur=null; try{ cur=JSON.parse(pick.getAttribute('data-gp-init')||'null'); }catch(e){}
  var items=[], act=-1, seq=0, timer=null;

  function meta(p){
    var bits=[];
    if(p.brand) bits.push(esc(p.brand));
    bits.push(p.stock>0 ? esc(p.stock)+' in stock' : '<span class="gp-out">out of stock</span>');
    if(p.status==='draft') bits.push('draft, hidden from the shop');
    return bits.join(' &middot; ');
  }
  function showChosen(){
    if(cur && cur.id){
      hid.value=cur.id;
      chosen.innerHTML=(cur.missing
          ? '<span class="gp-thumb gp-miss">!</span><span class="gp-txt"><b>This product no longer exists</b><span>'+esc(cur.id)+' &middot; remove it and choose another gift</span></span>'
          : '<img class="gp-thumb" src="'+esc(cur.img)+'" alt=""><span class="gp-txt"><b>'+esc(cur.name)+'</b><span>'+meta(cur)+'</span>'
            +(SELF && cur.id===SELF ? '<span class="gp-bogo">Same product: buy one, get one free</span>' : '')+'</span>')
        +'<button type="button" class="btn btn-ghost btn-sm" data-gp-remove aria-label="Remove the gift product">Remove</button>';
      chosen.hidden=false; sbox.hidden=true; hint.hidden=true;
    } else {
      hid.value=''; chosen.innerHTML=''; chosen.hidden=true; sbox.hidden=false; hint.hidden=false;
    }
  }
  function close(){ list.hidden=true; q.setAttribute('aria-expanded','false'); q.removeAttribute('aria-activedescendant'); }
  function setAct(i){
    var opts=list.querySelectorAll('.gp-opt'); act=i;
    opts.forEach(function(o,k){ o.classList.toggle('on',k===i); o.setAttribute('aria-selected',k===i?'true':'false'); });
    if(opts[i]){ q.setAttribute('aria-activedescendant',opts[i].id); opts[i].scrollIntoView({block:'nearest'}); }
  }
  function render(){
    list.innerHTML = items.length ? items.map(function(p,i){
      return '<div class="gp-opt" role="option" id="giftQo'+i+'" data-i="'+i+'" aria-selected="false">'
        +'<img class="gp-thumb" src="'+esc(p.img)+'" alt="" loading="lazy"><span class="gp-txt"><b>'+esc(p.name)+'</b>'
        +'<span>'+meta(p)+(SELF && p.id===SELF ? ' &middot; this product (buy one, get one)' : '')+'</span></span></div>';
    }).join('') : '<div class="gp-empty">No products match. Try a shorter word, a brand or the product ID.</div>';
    list.hidden=false; q.setAttribute('aria-expanded','true');
    setAct(items.length ? 0 : -1);
  }
  function search(){
    clearTimeout(timer);
    var term=q.value.trim(), my=++seq;
    if(!term){ items=[]; close(); hint.textContent=HINT; return; }
    hint.textContent='Searching...';
    fetch('product-search?q='+encodeURIComponent(term),{credentials:'same-origin',cache:'no-store'})
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(d){
        if(my!==seq) return;                                     // a newer search has started
        if(!d || !d.ok){ hint.textContent='Search failed. Reload the page (you may have been signed out).'; return; }
        items=d.items||[]; render();
        hint.textContent = items.length>=20 ? 'Showing the first 20 matches. Type more to narrow it down.' : HINT;
      })
      .catch(function(){ if(my===seq) hint.textContent='Search failed. Check the connection and try again.'; });
  }
  function choose(i){
    if(!items[i]) return;
    cur=items[i]; items=[]; q.value=''; close(); showChosen();
    var rm=chosen.querySelector('[data-gp-remove]'); if(rm) rm.focus();
  }

  q.addEventListener('input',function(){ clearTimeout(timer); timer=setTimeout(search,220); });
  q.addEventListener('keydown',function(e){
    if(e.key==='Enter'){                                         // never submit the product form from here
      e.preventDefault();
      if(!list.hidden && act>=0) choose(act); else search();
    } else if(e.key==='ArrowDown' || e.key==='ArrowUp'){
      if(list.hidden || !items.length) return;
      e.preventDefault();
      setAct((act+(e.key==='ArrowDown'?1:-1)+items.length)%items.length);
    } else if(e.key==='Escape' && !list.hidden){
      e.preventDefault(); close();
    }
  });
  q.addEventListener('focus',function(){ if(items.length) render(); });
  q.addEventListener('blur',function(){ setTimeout(close,150); });
  list.addEventListener('mousedown',function(e){ e.preventDefault(); });   // keep focus in the search box
  list.addEventListener('click',function(e){ var o=e.target.closest('.gp-opt'); if(o) choose(+o.getAttribute('data-i')); });
  chosen.addEventListener('click',function(e){
    if(!e.target.closest('[data-gp-remove]')) return;
    cur=null; showChosen(); q.focus();
  });

  showChosen(); sync(); ruleText();
})();

/* ---- one save per page view: a second click or Enter while the first save is on its
   way posted the form again (on Add, the same product twice). The buttons are NOT
   disabled: a disabled button leaves the submission, and "Save & back to list" travels
   as its after=list. The second submit is refused instead. ---- */
(function(){
  var form=document.getElementById('productForm'); if(!form) return;
  var busy=false, btn=null, label='', timer=null;
  function ready(){                                                // the buttons save again
    busy=false; clearTimeout(timer); form.classList.remove('is-saving'); form.removeAttribute('aria-busy');
    if(btn && btn.tagName==='BUTTON') btn.textContent=label;
    btn=null;
  }
  document.addEventListener('submit',function(e){                 // runs after the form's own submit handlers
    if(e.target!==form || e.defaultPrevented) return;              // stopped by another handler: nothing was sent
    if(busy){ e.preventDefault(); return; }
    busy=true; form.classList.add('is-saving'); form.setAttribute('aria-busy','true');
    btn=e.submitter||null;
    if(btn && btn.tagName==='BUTTON'){ label=btn.textContent; btn.textContent='Saving...'; }
    /* still on this page 10 s later: the save was stopped (Esc, the Stop button, a lost
       connection) and no answer is coming, so another save is allowed */
    timer=setTimeout(ready,10000);
  });
  /* Esc stops a page that is still loading, and with it the save */
  document.addEventListener('keydown',function(e){ if(busy && e.key==='Escape' && !e.defaultPrevented) ready(); });
  /* back / forward cache brings the page back as it was left: ready to save again */
  window.addEventListener('pageshow',function(e){ if(e.persisted && busy) ready(); });
})();
</script>
<?php admin_foot();
