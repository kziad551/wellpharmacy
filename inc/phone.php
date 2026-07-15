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
        ['961', 'Lebanon (+961)'],
        ['971', 'UAE (+971)'],
        ['966', 'Saudi Arabia (+966)'],
        ['974', 'Qatar (+974)'],
        ['965', 'Kuwait (+965)'],
        ['973', 'Bahrain (+973)'],
        ['968', 'Oman (+968)'],
        ['962', 'Jordan (+962)'],
        ['963', 'Syria (+963)'],
        ['964', 'Iraq (+964)'],
        ['20',  'Egypt (+20)'],
        ['90',  'Turkey (+90)'],
        ['357', 'Cyprus (+357)'],
        ['1',   'USA / Canada (+1)'],
        ['44',  'United Kingdom (+44)'],
        ['33',  'France (+33)'],
        ['49',  'Germany (+49)'],
        ['39',  'Italy (+39)'],
        ['34',  'Spain (+34)'],
        ['31',  'Netherlands (+31)'],
        ['32',  'Belgium (+32)'],
        ['41',  'Switzerland (+41)'],
        ['43',  'Austria (+43)'],
        ['46',  'Sweden (+46)'],
        ['47',  'Norway (+47)'],
        ['45',  'Denmark (+45)'],
        ['351', 'Portugal (+351)'],
        ['30',  'Greece (+30)'],
        ['48',  'Poland (+48)'],
        ['40',  'Romania (+40)'],
        ['7',   'Russia / Kazakhstan (+7)'],
        ['380', 'Ukraine (+380)'],
        ['61',  'Australia (+61)'],
        ['64',  'New Zealand (+64)'],
        ['91',  'India (+91)'],
        ['92',  'Pakistan (+92)'],
        ['880', 'Bangladesh (+880)'],
        ['63',  'Philippines (+63)'],
        ['62',  'Indonesia (+62)'],
        ['60',  'Malaysia (+60)'],
        ['65',  'Singapore (+65)'],
        ['66',  'Thailand (+66)'],
        ['84',  'Vietnam (+84)'],
        ['86',  'China (+86)'],
        ['81',  'Japan (+81)'],
        ['82',  'South Korea (+82)'],
        ['852', 'Hong Kong (+852)'],
        ['27',  'South Africa (+27)'],
        ['234', 'Nigeria (+234)'],
        ['254', 'Kenya (+254)'],
        ['233', 'Ghana (+233)'],
        ['212', 'Morocco (+212)'],
        ['213', 'Algeria (+213)'],
        ['216', 'Tunisia (+216)'],
        ['218', 'Libya (+218)'],
        ['249', 'Sudan (+249)'],
        ['251', 'Ethiopia (+251)'],
        ['55',  'Brazil (+55)'],
        ['54',  'Argentina (+54)'],
        ['56',  'Chile (+56)'],
        ['57',  'Colombia (+57)'],
        ['52',  'Mexico (+52)'],
        ['51',  'Peru (+51)'],
        ['58',  'Venezuela (+58)'],
        ['98',  'Iran (+98)'],
        ['93',  'Afghanistan (+93)'],
        ['994', 'Azerbaijan (+994)'],
        ['995', 'Georgia (+995)'],
        ['374', 'Armenia (+374)'],
        ['972', 'Israel (+972)'],
        ['353', 'Ireland (+353)'],
        ['358', 'Finland (+358)'],
        ['420', 'Czechia (+420)'],
        ['36',  'Hungary (+36)'],
        ['359', 'Bulgaria (+359)'],
        ['385', 'Croatia (+385)'],
        ['381', 'Serbia (+381)'],
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
    foreach (phone_countries() as [$d, $lbl]) {
        $opts .= '<option value="' . e($d) . '"' . ($d === $dial ? ' selected' : '') . '>' . e($lbl) . '</option>';
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
