<?php
/* ============================================================
   Phone input: country dial-code picker + national number.

   Stored as ONE E.164 string ("+96181614877") in a single DB column, so every
   existing wa.me / tel: link keeps working untouched. The split is presentation
   only — the two fields are recombined before the value is used.

   Lebanon first (the shop's home market), then the wider world.
   ============================================================ */

function phone_countries(): array {
    /* [dial, label] — Lebanon + the Gulf/Levant it actually ships to most, then the rest.
       Longest dial codes must be tried first when splitting (see phone_split). */
    return [
        ['961', '🇱🇧 +961', 'Lebanon'],
        ['971', '🇦🇪 +971', 'UAE'],
        ['966', '🇸🇦 +966', 'Saudi Arabia'],
        ['974', '🇶🇦 +974', 'Qatar'],
        ['965', '🇰🇼 +965', 'Kuwait'],
        ['973', '🇧🇭 +973', 'Bahrain'],
        ['968', '🇴🇲 +968', 'Oman'],
        ['962', '🇯🇴 +962', 'Jordan'],
        ['963', '🇸🇾 +963', 'Syria'],
        ['964', '🇮🇶 +964', 'Iraq'],
        ['20', '🇪🇬 +20', 'Egypt'],
        ['90', '🇹🇷 +90', 'Turkey'],
        ['357', '🇨🇾 +357', 'Cyprus'],
        ['1', '🇺🇸 +1', 'USA / Canada'],
        ['44', '🇬🇧 +44', 'United Kingdom'],
        ['33', '🇫🇷 +33', 'France'],
        ['49', '🇩🇪 +49', 'Germany'],
        ['39', '🇮🇹 +39', 'Italy'],
        ['34', '🇪🇸 +34', 'Spain'],
        ['31', '🇳🇱 +31', 'Netherlands'],
        ['32', '🇧🇪 +32', 'Belgium'],
        ['41', '🇨🇭 +41', 'Switzerland'],
        ['43', '🇦🇹 +43', 'Austria'],
        ['46', '🇸🇪 +46', 'Sweden'],
        ['47', '🇳🇴 +47', 'Norway'],
        ['45', '🇩🇰 +45', 'Denmark'],
        ['351', '🇵🇹 +351', 'Portugal'],
        ['30', '🇬🇷 +30', 'Greece'],
        ['48', '🇵🇱 +48', 'Poland'],
        ['40', '🇷🇴 +40', 'Romania'],
        ['7', '🇷🇺 +7', 'Russia / Kazakhstan'],
        ['380', '🇺🇦 +380', 'Ukraine'],
        ['61', '🇦🇺 +61', 'Australia'],
        ['64', '🇳🇿 +64', 'New Zealand'],
        ['91', '🇮🇳 +91', 'India'],
        ['92', '🇵🇰 +92', 'Pakistan'],
        ['880', '🇧🇩 +880', 'Bangladesh'],
        ['63', '🇵🇭 +63', 'Philippines'],
        ['62', '🇮🇩 +62', 'Indonesia'],
        ['60', '🇲🇾 +60', 'Malaysia'],
        ['65', '🇸🇬 +65', 'Singapore'],
        ['66', '🇹🇭 +66', 'Thailand'],
        ['84', '🇻🇳 +84', 'Vietnam'],
        ['86', '🇨🇳 +86', 'China'],
        ['81', '🇯🇵 +81', 'Japan'],
        ['82', '🇰🇷 +82', 'South Korea'],
        ['852', '🇭🇰 +852', 'Hong Kong'],
        ['27', '🇿🇦 +27', 'South Africa'],
        ['234', '🇳🇬 +234', 'Nigeria'],
        ['254', '🇰🇪 +254', 'Kenya'],
        ['233', '🇬🇭 +233', 'Ghana'],
        ['212', '🇲🇦 +212', 'Morocco'],
        ['213', '🇩🇿 +213', 'Algeria'],
        ['216', '🇹🇳 +216', 'Tunisia'],
        ['218', '🇱🇾 +218', 'Libya'],
        ['249', '🇸🇩 +249', 'Sudan'],
        ['251', '🇪🇹 +251', 'Ethiopia'],
        ['55', '🇧🇷 +55', 'Brazil'],
        ['54', '🇦🇷 +54', 'Argentina'],
        ['56', '🇨🇱 +56', 'Chile'],
        ['57', '🇨🇴 +57', 'Colombia'],
        ['52', '🇲🇽 +52', 'Mexico'],
        ['51', '🇵🇪 +51', 'Peru'],
        ['58', '🇻🇪 +58', 'Venezuela'],
        ['98', '🇮🇷 +98', 'Iran'],
        ['93', '🇦🇫 +93', 'Afghanistan'],
        ['994', '🇦🇿 +994', 'Azerbaijan'],
        ['995', '🇬🇪 +995', 'Georgia'],
        ['374', '🇦🇲 +374', 'Armenia'],
        ['972', '🇮🇱 +972', 'Israel'],
        ['353', '🇮🇪 +353', 'Ireland'],
        ['358', '🇫🇮 +358', 'Finland'],
        ['420', '🇨🇿 +420', 'Czechia'],
        ['36', '🇭🇺 +36', 'Hungary'],
        ['359', '🇧🇬 +359', 'Bulgaria'],
        ['385', '🇭🇷 +385', 'Croatia'],
        ['381', '🇷🇸 +381', 'Serbia'],
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
function phone_field(string $name, string $stored = '', bool $required = true, string $label = 'Phone'): string {
    [$dial, $nat] = phone_split($stored);
    $opts = '';
    foreach (phone_countries() as [$d, $lbl, $name]) {
        /* the option shows only "🇱🇧 +961" so the closed select stays narrow;
           the country name rides along as a title for hover findability */
        $opts .= '<option value="' . e($d) . '" title="' . e($name) . '"' . ($d === $dial ? ' selected' : '') . '>' . e($lbl) . '</option>';
    }
    $req = $required ? ' required' : '';
    return '<div class="field">
        <label for="' . e($name) . '">' . e($label) . ($required ? ' *' : ' <span class="muted" style="font-weight:400">(optional)</span>') . '</label>
        <div class="phone-row">
          <select class="input phone-dial" name="' . e($name) . '_dial" aria-label="Country code">' . $opts . '</select>
          <input class="input phone-nat" type="tel" id="' . e($name) . '" name="' . e($name) . '"
                 value="' . e($nat) . '" placeholder="70 123 456" inputmode="tel" autocomplete="tel-national"' . $req . '>
        </div>
      </div>';
}
