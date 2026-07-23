<?php
require __DIR__ . '/inc/layout.php';

$id = (int) input('id');
$editing = $id > 0 && ($s = row("SELECT * FROM home_sections WHERE id = ?", [$id]));
if ($id > 0 && !$editing) { flash('Section not found.', 'err'); redirect('home-sections'); }

$FIXED_TYPES = ['new_arrivals','category'];   // singletons: fixed, toggle-able, not addable/deletable
$ADDABLE_TYPES = ['brand','mixed'];           // the only types you can create

if (is_post()) {
    csrf_check();
    if ($editing && in_array($s['type'], $FIXED_TYPES, true)) {
        $type = $s['type'];   // a fixed section keeps its type — can't be switched
    } else {
        $type = in_array(input('type'), $ADDABLE_TYPES, true) ? input('type') : 'brand';
    }
    $brandList = array_values(array_filter(array_map('trim', (array) ($_POST['brand_list'] ?? []))));
    $data = [
        'type'       => $type,
        'brand'      => $type === 'brand' ? trim((string) input('brand')) : '',
        'brands'     => $type === 'mixed' ? implode(',', $brandList) : '',   // blank = all brands
        'eyebrow'    => trim((string) input('eyebrow')),
        'title'      => trim((string) input('title')),
        'subtitle'   => trim((string) input('subtitle')),
        'show_title' => input('show_title') ? 1 : 0,
        'item_count' => max(0, (int) input('item_count')),
        'cols'       => in_array((int) input('cols'), [3,4,5], true) ? (int) input('cols') : 5,
        'enabled'    => input('enabled') ? 1 : 0,
        'sort'       => (int) input('sort'),
    ];
    if ($type === 'brand' && $data['brand'] === '') {
        flash('Pick a brand for a brand section.', 'err');
        redirect($editing ? "home-section-edit?id=$id" : 'home-section-edit');
    }

    if ($editing) {
        $data['id'] = $id;
        q("UPDATE home_sections SET type=:type, brand=:brand, brands=:brands, eyebrow=:eyebrow, title=:title, subtitle=:subtitle,
              show_title=:show_title, item_count=:item_count, cols=:cols, enabled=:enabled, sort=:sort WHERE id=:id", $data);
        flash('Section updated.');
    } else {
        q("INSERT INTO home_sections (type,brand,brands,eyebrow,title,subtitle,show_title,item_count,cols,enabled,sort)
           VALUES (:type,:brand,:brands,:eyebrow,:title,:subtitle,:show_title,:item_count,:cols,:enabled,:sort)", $data);
        flash('Section created.');
    }
    redirect('home-sections');
}

$v = $editing ? $s : ['id'=>0,'type'=>'brand','brand'=>'','brands'=>'','eyebrow'=>'','title'=>'','subtitle'=>'','show_title'=>1,'item_count'=>5,'cols'=>5,'enabled'=>1,'sort'=>0];
$pickedBrands = array_filter(array_map('trim', explode(',', (string)($v['brands'] ?? ''))));   // for the Mixed multi-select

/* brand options: every brand that exists in the brands table OR is used by a product */
$brandNames = array_values(array_unique(array_merge(
    array_column(rows("SELECT name FROM brands ORDER BY name"), 'name'),
    array_column(rows("SELECT DISTINCT brand FROM products WHERE brand <> '' ORDER BY brand"), 'brand')
)));
sort($brandNames, SORT_FLAG_CASE | SORT_STRING);

admin_head($editing ? 'Edit section' : 'Add section', 'home-sections', $editing ? 'Home section' : 'New home section');
?>
<form method="post" action="<?= $editing ? "home-section-edit?id=" . e($id) : "home-section-edit" ?>">
  <?= csrf_field() ?>
  <div class="page-actions"><a class="btn btn-ghost" href="home-sections">← Back</a><div class="spacer"></div><button class="btn btn-primary">Save section</button></div>

  <div class="a-card"><div class="hd"><h2>Section</h2></div><div class="bd">
    <div class="f-row">
      <?php $isFixed = $editing && in_array($v['type'], $FIXED_TYPES, true); ?>
      <div class="field"><label>Type</label>
        <?php if ($isFixed): ?>
          <input class="input" value="<?= $v['type']==='new_arrivals' ? 'New Arrivals' : 'Category tiles' ?>" disabled>
          <input type="hidden" name="type" value="<?= e($v['type']) ?>">
          <div class="hint">A fixed section — always here, can’t be deleted or changed to another type. Turn it on or off with “Visible on the homepage” below, and customise its title/count as usual.</div>
        <?php else: ?>
          <select class="input" name="type" id="secType" onchange="secTypeChange(this.value)">
            <option value="brand" <?= $v['type']==='brand'?'selected':'' ?>>Brand — all products of one brand</option>
            <option value="mixed" <?= $v['type']==='mixed'?'selected':'' ?>>Mixed — a shuffle of products across brands</option>
          </select>
        <?php endif; ?>
      </div>
      <div class="field" id="brandRow" style="<?= $v['type']==='brand'?'':'display:none' ?>"><label>Brand</label>
        <select class="input" name="brand">
          <option value="">— pick a brand —</option>
          <?php foreach ($brandNames as $bn): ?><option value="<?= e($bn) ?>" <?= $v['brand']===$bn?'selected':'' ?>><?= e($bn) ?></option><?php endforeach; ?>
        </select>
        <div class="hint">A section only appears if the brand has active products.</div>
      </div>
      <div class="field" id="brandsRow" style="<?= $v['type']==='mixed'?'':'display:none' ?>"><label>Brands to mix <span class="faint">(tick the brands you want — tick none for ALL brands)</span></label>
        <div class="brand-picker">
          <label class="bp-all"><input type="checkbox" id="bpAll"> <b>Select all</b></label>
          <div class="bp-list">
            <?php foreach ($brandNames as $bn): ?><label class="bp-item"><input type="checkbox" name="brand_list[]" value="<?= e($bn) ?>" <?= in_array($bn,$pickedBrands,true)?'checked':'' ?>> <?= e($bn) ?></label><?php endforeach; ?>
          </div>
        </div>
        <div class="hint">Shows a shuffled mix of products from these brands (a few from each). None ticked = mix from every brand.</div>
      </div>
    </div>

    <div class="f-row">
      <div class="field"><label>Eyebrow <span class="faint">(small label above the title, optional)</span></label>
        <input class="input" name="eyebrow" value="<?= e($v['eyebrow']) ?>" placeholder="e.g. just dropped"></div>
      <div class="field"><label>Title <span class="faint">(optional)</span></label>
        <input class="input" name="title" value="<?= e($v['title']) ?>" placeholder="<?= $v['type']==='brand'?'defaults to the brand name':($v['type']==='category'?'Shop by Category':'New Arrivals') ?>">
        <div class="hint">Blank = default (brand name / “New Arrivals”). The last word is styled in the accent colour.</div></div>
    </div>

    <div class="field"><label>Subtitle <span class="faint">(optional line under the title)</span></label>
      <input class="input" name="subtitle" value="<?= e($v['subtitle']) ?>"></div>

    <div class="f-row-3">
      <div class="field"><label>Products to show</label><input class="input" type="number" name="item_count" min="0" value="<?= e($v['item_count']) ?>">
        <div class="hint">0 = all. 5 = one row, 10 = two rows…</div></div>
      <div class="field"><label>Per row</label>
        <select class="input" name="cols">
          <option value="5" <?= (int)$v['cols']===5?'selected':'' ?>>5 per row</option>
          <option value="4" <?= (int)$v['cols']===4?'selected':'' ?>>4 per row</option>
          <option value="3" <?= (int)$v['cols']===3?'selected':'' ?>>3 per row</option>
        </select></div>
      <div class="field"><label>Sort order</label><input class="input" type="number" name="sort" value="<?= e($v['sort']) ?>">
        <div class="hint">Lower shows first.</div></div>
    </div>

    <label class="switch" style="margin-bottom:12px"><input type="checkbox" name="show_title" value="1" <?= $v['show_title']?'checked':'' ?>> Show the eyebrow / title header</label><br>
    <label class="switch"><input type="checkbox" name="enabled" value="1" <?= $v['enabled']?'checked':'' ?>> Visible on the homepage</label>
  </div></div>
  <div class="page-actions" style="margin-top:18px"><div class="spacer"></div><button class="btn btn-primary">Save section</button></div>
</form>
<style>
  .brand-picker{border:1px solid var(--line,#dcd6c9);border-radius:12px;overflow:hidden}
  .brand-picker .bp-all{display:flex;align-items:center;gap:8px;padding:10px 14px;border-bottom:1px solid var(--line,#dcd6c9);background:rgba(0,0,0,.03);cursor:pointer;font-size:14px}
  .brand-picker .bp-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:2px 14px;max-height:230px;overflow:auto;padding:10px 14px}
  .brand-picker .bp-item{display:flex;align-items:center;gap:8px;padding:5px 0;cursor:pointer;font-size:14px;white-space:nowrap}
  .brand-picker input[type=checkbox]{width:16px;height:16px;flex:none;cursor:pointer}
</style>
<script>
  function secTypeChange(t){
    document.getElementById('brandRow').style.display  = (t==='brand') ? '' : 'none';
    document.getElementById('brandsRow').style.display = (t==='mixed') ? '' : 'none';
  }
  var _st = document.getElementById('secType');
  if (_st) secTypeChange(_st.value);

  // "Select all" tick for the brands-to-mix picker
  (function(){
    var all = document.getElementById('bpAll');
    if (!all) return;
    var boxes = Array.prototype.slice.call(document.querySelectorAll('#brandsRow .bp-item input[type=checkbox]'));
    function syncAll(){ all.checked = boxes.length > 0 && boxes.every(function(b){ return b.checked; }); }
    all.addEventListener('change', function(){ boxes.forEach(function(b){ b.checked = all.checked; }); });
    boxes.forEach(function(b){ b.addEventListener('change', syncAll); });
    syncAll();
  })();
</script>
<?php admin_foot();
