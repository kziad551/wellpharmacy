<?php
require __DIR__ . '/inc/layout.php';

/* field => [group, type, label, hint] */
$FIELDS = [
    'store_name'           => ['store','text','Store name','Shown as the logo wordmark + page titles'],
    'store_tagline'        => ['store','text','Tagline','Under the logo'],
    'store_email'          => ['store','text','Contact email',''],
    'store_phone'          => ['store','text','Contact phone',''],
    'whatsapp_number'      => ['store','text','WhatsApp number','International format, no +, e.g. 9613627766'],
    'store_address'        => ['store','text','Store address','Shown on the contact page + Get Directions'],
    'free_ship_threshold'  => ['store','text','Free shipping over ($)','Cart bar + drawer threshold'],
    'currency_label'       => ['store','text','Currency label','e.g. $ USD'],
    'announce_1'           => ['store','text','Announcement bar — line 1',''],
    'announce_2'           => ['store','text','Announcement bar — line 2',''],
    'footer_about'         => ['store','textarea','Footer about text',''],
    'hero_eyebrow'         => ['content','text','Hero eyebrow','Small label above the homepage headline'],
    'hero_title'           => ['content','text','Hero title','Main homepage headline'],
    'hero_title_accent'    => ['content','text','Hero title — accent word','Shown in the accent colour'],
    'hero_sub'             => ['content','textarea','Hero subtitle',''],
    'promise_line1'        => ['content','text','Promise — line 1','The big lowercase line near the bottom of the home page'],
    'promise_accent'       => ['content','text','Promise — accent word',''],
    'promise_sub'          => ['content','textarea','Promise — subtitle',''],
    'social_instagram'     => ['social','text','Instagram URL',''],
    'social_tiktok'        => ['social','text','TikTok URL',''],
    'social_facebook'      => ['social','text','Facebook URL',''],
    'social_youtube'       => ['social','text','YouTube URL',''],
    'social_pinterest'     => ['social','text','Pinterest URL',''],
    'opening_hours'        => ['hours','textarea','Opening hours','One row per line, format: <b>Label | Hours</b> (e.g. Mon – Sat | 9am – 9pm)'],
    'hours_status'         => ['hours','text','Status badge','e.g. "Open now" — leave blank to hide the badge'],
    'ship_fee_beirut'      => ['delivery','text','Beirut shipping fee ($)',''],
    'ship_fee_outside'     => ['delivery','text','Outside Beirut fee ($)',''],
    'delivery_beirut_text' => ['delivery','text','Beirut delivery promise',''],
    'delivery_outside_text'=> ['delivery','text','Outside Beirut promise',''],
    'smtp_host'            => ['email','text','SMTP host','e.g. smtp-relay.brevo.com — leave BLANK and no mail is sent (it is saved to storage/mail instead)'],
    'smtp_port'            => ['email','text','SMTP port','587 for TLS, 465 for SSL'],
    'smtp_secure'          => ['email','text','Encryption','tls (port 587) or ssl (port 465)'],
    'smtp_user'            => ['email','text','SMTP username','Usually your full email address or provider login'],
    'smtp_pass'            => ['email','text','SMTP password / API key','Kept server-side only — never shown on the storefront'],
    'mail_from'            => ['email','text','Send from address','Must be a domain the SMTP provider lets you send as, e.g. orders@wellpharmacy.com'],
    'mail_from_name'       => ['email','text','Send from name','e.g. Well Pharmacy'],
    'admin_notify_email'   => ['email','text','New-order alerts go to','Where YOU get told about every order (guest or account)'],
    'areeba_merchant_id'   => ['payment','text','Areeba Merchant ID','From your Areeba / MPGS account'],
    'areeba_api_password'  => ['payment','text','Areeba API password','Kept server-side only'],
    'areeba_gateway_url'   => ['payment','text','Areeba gateway URL',''],
];
$TOGGLES = [
    'cod_enabled'    => ['payment','Cash on Delivery enabled'],
    'areeba_enabled' => ['payment','Areeba card payment enabled'],
];

if (is_post()) {
    csrf_check();
    foreach ($FIELDS as $key => [$grp]) set_setting($key, (string) input($key), $grp);
    foreach ($TOGGLES as $key => [$grp]) set_setting($key, input($key) ? '1' : '0', $grp);
    flash('Settings saved.');
    redirect('settings');
}

$groups = [
    'store'    => ['Store', 'Identity, contact &amp; announcement bar'],
    'content'  => ['Homepage', 'Hero &amp; promise text on the storefront home page'],
    'social'   => ['Social media', 'Links shown in the footer (only filled-in ones appear)'],
    'hours'    => ['Opening hours', 'Shown on the contact page'],
    'delivery' => ['Delivery', 'Shipping fees &amp; delivery promises by area'],
    'payment'  => ['Payments', 'Cash on Delivery &amp; the Areeba card gateway'],
    'email'    => ['Email (SMTP)', 'Order confirmations, new-order alerts &amp; sign-up codes. Leave SMTP host blank while testing — messages are then written to <code>storage/mail/</code> instead of being sent.'],
];

admin_head('Settings', 'settings', 'Store information, delivery and payment options.');
?>
<form method="post" action="settings">
  <?= csrf_field() ?>
  <div class="page-actions"><div class="spacer"></div><button class="btn btn-primary">Save settings</button></div>

  <?php foreach ($groups as $g => [$gt, $gd]): ?>
    <div class="a-card" style="margin-bottom:18px">
      <div class="hd"><h2><?= $gt ?></h2><span class="muted" style="font-size:12.5px"><?= $gd ?></span></div>
      <div class="bd">
        <?php foreach ($TOGGLES as $key => [$grp, $label]): if ($grp !== $g) continue; ?>
          <label class="switch" style="margin-bottom:14px"><input type="checkbox" name="<?= e($key) ?>" value="1" <?= setting($key)==='1'?'checked':'' ?>> <?= e($label) ?></label><br>
        <?php endforeach; ?>
        <div class="f-row">
        <?php $i=0; foreach ($FIELDS as $key => [$grp,$type,$label,$hint]): if ($grp !== $g) continue;
            $full = $type === 'textarea'; ?>
          <div class="field" style="<?= $full ? 'grid-column:1/-1' : '' ?>">
            <label><?= e($label) ?></label>
            <?php if ($type === 'textarea'): ?>
              <textarea class="input" name="<?= e($key) ?>" rows="3"><?= e(setting($key)) ?></textarea>
            <?php else: ?>
              <input class="input" name="<?= e($key) ?>" value="<?= e(setting($key)) ?>">
            <?php endif; ?>
            <?php if ($hint): ?><div class="hint"><?= $hint ?></div><?php endif; ?>
          </div>
        <?php $i++; endforeach; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <div class="page-actions"><div class="spacer"></div><button class="btn btn-primary">Save settings</button></div>
</form>
<?php admin_foot();
