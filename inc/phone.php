<?php
/* ============================================================
   Phone input: country dial-code picker + national number.

   Stored as ONE E.164 string ("+96181614877") in a single DB column, so every
   existing wa.me / tel: link keeps working untouched. The split is presentation
   only — the two fields are recombined before the value is used.

   Lebanon first (the shop's home market), then the wider world.
   ============================================================ */

function phone_countries(): array {
    /* [dial, iso2, name] — Lebanon + the Gulf/Levant it ships to most, then the rest.
       Longest dial codes must be tried first when splitting (see phone_split). */
    return [
        ['961', 'lb', 'Lebanon'],
        ['971', 'ae', 'UAE'],
        ['966', 'sa', 'Saudi Arabia'],
        ['974', 'qa', 'Qatar'],
        ['965', 'kw', 'Kuwait'],
        ['973', 'bh', 'Bahrain'],
        ['968', 'om', 'Oman'],
        ['962', 'jo', 'Jordan'],
        ['963', 'sy', 'Syria'],
        ['964', 'iq', 'Iraq'],
        ['20', 'eg', 'Egypt'],
        ['90', 'tr', 'Turkey'],
        ['357', 'cy', 'Cyprus'],
        ['1', 'us', 'USA / Canada'],
        ['44', 'gb', 'United Kingdom'],
        ['33', 'fr', 'France'],
        ['49', 'de', 'Germany'],
        ['39', 'it', 'Italy'],
        ['34', 'es', 'Spain'],
        ['31', 'nl', 'Netherlands'],
        ['32', 'be', 'Belgium'],
        ['41', 'ch', 'Switzerland'],
        ['43', 'at', 'Austria'],
        ['46', 'se', 'Sweden'],
        ['47', 'no', 'Norway'],
        ['45', 'dk', 'Denmark'],
        ['351', 'pt', 'Portugal'],
        ['30', 'gr', 'Greece'],
        ['48', 'pl', 'Poland'],
        ['40', 'ro', 'Romania'],
        ['7', 'ru', 'Russia / Kazakhstan'],
        ['380', 'ua', 'Ukraine'],
        ['61', 'au', 'Australia'],
        ['64', 'nz', 'New Zealand'],
        ['91', 'in', 'India'],
        ['92', 'pk', 'Pakistan'],
        ['880', 'bd', 'Bangladesh'],
        ['63', 'ph', 'Philippines'],
        ['62', 'id', 'Indonesia'],
        ['60', 'my', 'Malaysia'],
        ['65', 'sg', 'Singapore'],
        ['66', 'th', 'Thailand'],
        ['84', 'vn', 'Vietnam'],
        ['86', 'cn', 'China'],
        ['81', 'jp', 'Japan'],
        ['82', 'kr', 'South Korea'],
        ['852', 'hk', 'Hong Kong'],
        ['27', 'za', 'South Africa'],
        ['234', 'ng', 'Nigeria'],
        ['254', 'ke', 'Kenya'],
        ['233', 'gh', 'Ghana'],
        ['212', 'ma', 'Morocco'],
        ['213', 'dz', 'Algeria'],
        ['216', 'tn', 'Tunisia'],
        ['218', 'ly', 'Libya'],
        ['249', 'sd', 'Sudan'],
        ['251', 'et', 'Ethiopia'],
        ['55', 'br', 'Brazil'],
        ['54', 'ar', 'Argentina'],
        ['56', 'cl', 'Chile'],
        ['57', 'co', 'Colombia'],
        ['52', 'mx', 'Mexico'],
        ['51', 'pe', 'Peru'],
        ['58', 've', 'Venezuela'],
        ['98', 'ir', 'Iran'],
        ['93', 'af', 'Afghanistan'],
        ['994', 'az', 'Azerbaijan'],
        ['995', 'ge', 'Georgia'],
        ['374', 'am', 'Armenia'],
        ['972', 'il', 'Israel'],
        ['353', 'ie', 'Ireland'],
        ['358', 'fi', 'Finland'],
        ['420', 'cz', 'Czechia'],
        ['36', 'hu', 'Hungary'],
        ['359', 'bg', 'Bulgaria'],
        ['385', 'hr', 'Croatia'],
        ['381', 'rs', 'Serbia'],
    ];
}

/**
 * Split a stored number back into [dial, national] for editing.
 *
 * Only an EXPLICITLY international number ("+961…" or "00961…") is split. A bare
 * local number can't be attributed to a country — "70123456" would otherwise match
 * Russia (+7) and silently mangle a Lebanese number — so it keeps the default dial
 * and is treated wholly as the national part.
 */
function phone_split(string $stored, string $default = '961'): array {
    $raw    = trim($stored);
    $digits = preg_replace('/\D/', '', $raw);
    if ($digits === '') return [$default, ''];

    $intl = str_starts_with($raw, '+') || str_starts_with($digits, '00');
    if (!$intl) return [$default, $digits];
    if (str_starts_with($digits, '00')) $digits = substr($digits, 2);

    $dials = array_column(phone_countries(), 0);
    usort($dials, fn($a, $b) => strlen($b) <=> strlen($a));   // longest match wins: 961 before 96, 1 last
    foreach ($dials as $d) {
        if (str_starts_with($digits, $d)) return [$d, substr($digits, strlen($d))];
    }
    return [$default, $digits];
}

/** Recombine a submitted dial + national number into E.164. Returns '' if unusable. */
function phone_join($dial, $national): string {
    $dial = preg_replace('/\D/', '', (string) $dial);
    $nat  = ltrim(preg_replace('/\D/', '', (string) $national), '0');   // drop the trunk 0: 03 → 3
    if ($dial === '' || $nat === '') return '';
    return '+' . $dial . $nat;
}

/**
 * Render the paired field. $name is the base; posts as {$name}_dial + {$name}.
 * Server code should call phone_join(input($name.'_dial'), input($name)).
 */
/** 24x18 flag bitmap. Windows renders NO flag emoji (it shows "LB"/"AE" letters instead),
 *  and a native <option> can't hold an image — hence real images + a custom dropdown. */
function phone_flag(string $iso, string $cls = 'flag'): string {
    $iso = strtolower($iso);
    return '<img class="' . e($cls) . '" loading="lazy" alt="" width="24" height="18"'
         . ' src="https://flagcdn.com/24x18/' . e($iso) . '.png"'
         . ' srcset="https://flagcdn.com/48x36/' . e($iso) . '.png 2x">';
}

function phone_field(string $name, string $stored = '', bool $required = true, string $label = 'Phone'): string {
    [$dial, $nat] = phone_split($stored);
    $cur = null;
    $list = '';
    foreach (phone_countries() as [$d, $iso, $cname]) {
        if ($d === $dial && $cur === null) $cur = [$d, $iso, $cname];
        $list .= '<li><button type="button" class="dial-opt" role="option" data-d="' . e($d) . '" data-iso="' . e($iso) . '"'
               . ' data-s="' . e(strtolower($cname . ' ' . $d)) . '"' . ($d === $dial ? ' aria-selected="true"' : '') . '>'
               . phone_flag($iso) . '<span class="nm">' . e($cname) . '</span><span class="dl">+' . e($d) . '</span></button></li>';
    }
    if (!$cur) $cur = ['961', 'lb', 'Lebanon'];
    $req = $required ? ' required' : '';

    return '<div class="field">
        <label for="' . e($name) . '">' . e($label)
        . ($required ? ' *' : ' <span class="muted" style="font-weight:400">(optional)</span>') . '</label>
        <div class="phone-row">
          <div class="dialpick" data-dialpick>
            <input type="hidden" name="' . e($name) . '_dial" value="' . e($cur[0]) . '" data-dial-value>
            <button type="button" class="input dial-btn" aria-haspopup="listbox" aria-expanded="false" aria-label="Country code">
              ' . phone_flag($cur[1]) . '<span class="dial">+' . e($cur[0]) . '</span>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="chev"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="dial-pop" hidden>
              <input type="text" class="dial-search" placeholder="Search country…" aria-label="Search country">
              <ul role="listbox">' . $list . '</ul>
              <p class="dial-none" hidden>No match</p>
            </div>
          </div>
          <input class="input phone-nat" type="tel" id="' . e($name) . '" name="' . e($name) . '"
                 value="' . e($nat) . '" placeholder="70 123 456" inputmode="tel" autocomplete="tel-national"' . $req . '>
        </div>
      </div>';
}
