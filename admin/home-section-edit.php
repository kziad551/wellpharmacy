<?php
require __DIR__ . '/inc/layout.php';

$id = (int) input('id');
$editing = $id > 0 && ($s = row("SELECT * FROM home_sections WHERE id = ?", [$id]));
if ($id > 0 && !$editing) { flash('Section not found.', 'err'); redirect('home-sections'); }

if (is_post()) {
    csrf_check();
    $type = input('type') === 'new_arrivals' ? 'new_arrivals' : 'brand';
    $data = [
        'type'       => $type,
        'brand'      => $type === 'brand' ? trim((string) input('brand')) : '',
        'eyebrow'    => trim((string) input('eyebrow')),
        'title'      => trim((string) input('title')),
        'subtitle'   => trim((string) input('subtitle')),
        'show_title' => input('show_title') ? 1 : 0,
        'item_count' => max(0, (int) input('item_count')),
        'cols'       => (int) input('cols') === 4 ? 4 : 5,
        'enabled'    => input('enabled') ? 1 : 0,
        'sort'       => (int) input('sort'),
    ];
    if ($type === 'brand' && $data['brand'] === '') {
        flash('Pick a brand for a brand section.', 'err');
        redirect($editing ? "home-section-edit?id=$id" : 'home-section-edit');
    }

    if ($editing) {
        $data['id'] = $id;
        q("UPDATE home_sections SET type=:type, brand=:brand, eyebrow=:eyebrow, title=:title, subtitle=:subtitle,
              show_title=:show_title, item_count=:item_count, cols=:cols, enabled=:enabled, sort=:sort WHERE id=:id", $data);
        flash('Section updated.');
    } else {
        q("INSERT INTO home_sections (type,brand,eyebrow,title,subtitle,show_title,item_count,cols,enabled,sort)
           VALUES (:type,:brand,:eyebrow,:title,:subtitle,:show_title,:item_count,:cols,:enabled,:sort)", $data);
        flash('Section created.');
    }
    redirect('home-sections');
}

$v = $editing ? $s : ['id'=>0,'type'=>'brand','brand'=>'','eyebrow'=>'','title'=>'','subtitle'=>'','show_title'=>1,'item_count'=>5,'cols'=>5,'enabled'=>1,'sort'=>0];

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
      <div class="field"><label>Type</label>
        <select class="input" name="type" id="secType" onchange="document.getElementById('brandRow').style.display=this.value==='brand'?'':'none'">
          <option value="brand" <?= $v['type']==='brand'?'selected':'' ?>>Brand — all products of one brand</option>
          <option value="new_arrivals" <?= $v['type']==='new_arrivals'?'selected':'' ?>>New Arrivals — products you flag</option>
        </select>
      </div>
      <div class="field" id="brandRow" style="<?= $v['type']==='brand'?'':'display:none' ?>"><label>Brand</label>
        <select class="input" name="brand">
          <option value="">— pick a brand —</option>
          <?php foreach ($brandNames as $bn): ?><option value="<?= e($bn) ?>" <?= $v['brand']===$bn?'selected':'' ?>><?= e($bn) ?></option><?php endforeach; ?>
        </select>
        <div class="hint">A section only appears if the brand has active products.</div>
      </div>
    </div>

    <div class="f-row">
      <div class="field"><label>Eyebrow <span class="faint">(small label above the title, optional)</span></label>
        <input class="input" name="eyebrow" value="<?= e($v['eyebrow']) ?>" placeholder="e.g. just dropped"></div>
      <div class="field"><label>Title <span class="faint">(optional)</span></label>
        <input class="input" name="title" value="<?= e($v['title']) ?>" placeholder="<?= $v['type']==='brand'?'defaults to the brand name':'New Arrivals' ?>">
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
        </select></div>
      <div class="field"><label>Sort order</label><input class="input" type="number" name="sort" value="<?= e($v['sort']) ?>">
        <div class="hint">Lower shows first.</div></div>
    </div>

    <label class="switch" style="margin-bottom:12px"><input type="checkbox" name="show_title" value="1" <?= $v['show_title']?'checked':'' ?>> Show the eyebrow / title header</label><br>
    <label class="switch"><input type="checkbox" name="enabled" value="1" <?= $v['enabled']?'checked':'' ?>> Visible on the homepage</label>
  </div></div>
  <div class="page-actions" style="margin-top:18px"><div class="spacer"></div><button class="btn btn-primary">Save section</button></div>
</form>
<?php admin_foot();
