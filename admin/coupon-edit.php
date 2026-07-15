<?php
require __DIR__ . '/inc/layout.php';

$id = (int) input('id');
$editing = $id > 0 && ($c = row("SELECT * FROM coupons WHERE id = ?", [$id]));
if ($id > 0 && !$editing) { flash('Coupon not found.', 'err'); redirect('coupons'); }

$TYPES = ['percent' => '% off order', 'fixed' => '$ off order', 'freeship' => 'Free shipping'];

if (is_post()) {
    csrf_check();
    $code = strtoupper(trim((string) input('code')));
    $type = array_key_exists(input('type'), $TYPES) ? (string) input('type') : 'percent';
    $expires = trim((string) input('expires_at'));
    $limit   = input('usage_limit') !== '' ? (int) input('usage_limit') : null;

    if ($code === '') { flash('Code is required.', 'err'); redirect($editing ? "coupon-edit?id=$id" : 'coupon-edit'); }

    $data = [
        'code'        => $code,
        'type'        => $type,
        'value'       => $type === 'freeship' ? 0 : (float) input('value'),
        'min_spend'   => (float) input('min_spend'),
        'expires_at'  => $expires !== '' ? $expires : null,
        'usage_limit' => $limit,
        'active'      => input('active') ? 1 : 0,
        'is_public'   => input('is_public') ? 1 : 0,
    ];

    // enforce unique code
    $clash = $editing
        ? row("SELECT id FROM coupons WHERE code = ? AND id <> ?", [$code, $id])
        : row("SELECT id FROM coupons WHERE code = ?", [$code]);
    if ($clash) { flash('That coupon code already exists.', 'err'); redirect($editing ? "coupon-edit?id=$id" : 'coupon-edit'); }

    if ($editing) {
        $data['id'] = $id;
        q("UPDATE coupons SET code=:code, type=:type, value=:value, min_spend=:min_spend, expires_at=:expires_at, usage_limit=:usage_limit, active=:active, is_public=:is_public WHERE id=:id", $data);
        flash('Coupon updated.');
    } else {
        q("INSERT INTO coupons (code,type,value,min_spend,expires_at,usage_limit,active,is_public) VALUES (:code,:type,:value,:min_spend,:expires_at,:usage_limit,:active,:is_public)", $data);
        flash('Coupon created.');
    }
    redirect('coupons');
}

$v = $editing ? $c : ['id'=>0,'code'=>'','type'=>'percent','value'=>'10','min_spend'=>'0','expires_at'=>'','usage_limit'=>'','active'=>1,'is_public'=>1];
admin_head($editing ? 'Edit coupon' : 'Add coupon', 'coupons', $editing ? $v['code'] : 'New coupon');
?>
<form method="post" action="<?= $editing ? "coupon-edit?id=".e($id) : "coupon-edit" ?>">
  <?= csrf_field() ?>
  <div class="page-actions"><a class="btn btn-ghost" href="coupons">← Back</a><div class="spacer"></div><button class="btn btn-primary">Save coupon</button></div>

  <div class="a-card"><div class="hd"><h2>Discount</h2></div><div class="bd">
    <div class="f-row">
      <div class="field"><label>Code</label><input class="input" name="code" value="<?= e($v['code']) ?>" placeholder="WELL10" style="text-transform:uppercase" required></div>
      <div class="field"><label>Type</label><select class="input" name="type" id="ctype">
        <?php foreach ($TYPES as $tk=>$tl): ?><option value="<?= e($tk) ?>" <?= $tk===$v['type']?'selected':'' ?>><?= e($tl) ?></option><?php endforeach; ?>
      </select></div>
    </div>
    <div class="f-row">
      <div class="field" id="valWrap"><label>Value <span class="faint">(% or $)</span></label><input class="input" type="number" step="0.01" name="value" value="<?= e($v['value']) ?>"><div class="hint">Ignored for free-shipping coupons.</div></div>
      <div class="field"><label>Minimum spend ($)</label><input class="input" type="number" step="0.01" name="min_spend" value="<?= e($v['min_spend']) ?>" placeholder="0 = no minimum"></div>
    </div>
    <div class="f-row">
      <div class="field"><label>Expires on <span class="faint">(optional)</span></label><input class="input" type="date" name="expires_at" value="<?= e($v['expires_at']) ?>"></div>
      <div class="field"><label>Usage limit <span class="faint">(optional)</span></label><input class="input" type="number" name="usage_limit" value="<?= e($v['usage_limit']) ?>" placeholder="blank = unlimited"></div>
    </div>
    <label class="switch"><input type="checkbox" name="active" value="1" <?= $v['active']?'checked':'' ?>> Active</label>
    <label class="switch" style="margin-top:8px"><input type="checkbox" name="is_public" value="1" <?= $v['is_public']??1?'checked':'' ?>> Show publicly on the Offers page
      <span class="muted" style="display:block;font-size:12px;font-weight:400">Off = private: hidden from Offers, but still works when someone types the code.</span></label>
  </div></div>
  <div class="page-actions" style="margin-top:18px"><div class="spacer"></div><button class="btn btn-primary">Save coupon</button></div>
</form>
<script>
  // hide the value field for free-shipping coupons
  const ct = document.getElementById('ctype'), vw = document.getElementById('valWrap');
  const sync = () => { vw.style.display = ct.value === 'freeship' ? 'none' : ''; };
  ct.addEventListener('change', sync); sync();
</script>
<?php admin_foot();
