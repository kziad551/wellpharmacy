<?php
require __DIR__ . '/inc/layout.php';

$id = (int) input('id');
$editing = $id > 0 && ($b = row("SELECT * FROM brands WHERE id = ?", [$id]));
if ($id > 0 && !$editing) { flash('Brand not found.', 'err'); redirect('brands'); }

if (is_post()) {
    csrf_check();
    $name = trim((string) input('name'));
    $slug = slugify((string) (input('slug') ?: $name));
    $color = trim((string) input('color'));
    if ($color !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '';
    $logo = trim((string) input('logo'));
    $upErr = null;
    if ($u = save_upload('logo_file', $upErr)) $logo = $u;

    if ($name === '') { flash('Name is required.', 'err'); redirect($editing ? "brand-edit?id=$id" : 'brand-edit'); }

    $data = [
        'name'     => $name,
        'slug'     => $slug,
        'color'    => $color,
        'logo'     => $logo,
        'logo_mode'=> in_array(input('logo_mode'), ['auto','logo','name','both'], true) ? input('logo_mode') : 'auto',
        'featured' => input('featured') ? 1 : 0,
        'sort'     => (int) input('sort'),
    ];

    if ($editing) {
        // brands are referenced on products by name — keep them connected on rename
        if ($name !== $b['name']) q("UPDATE products SET brand = ? WHERE brand = ?", [$name, $b['name']]);
        $data['id'] = $id;
        q("UPDATE brands SET name=:name, slug=:slug, color=:color, logo=:logo, logo_mode=:logo_mode, featured=:featured, sort=:sort WHERE id=:id", $data);
        flash($upErr ? 'Brand updated — but the logo was not changed: ' . $upErr : 'Brand updated.', $upErr ? 'err' : 'ok');
    } else {
        if (row("SELECT id FROM brands WHERE slug = ?", [$slug])) { flash('A brand with that URL slug already exists.', 'err'); redirect('brand-edit'); }
        q("INSERT INTO brands (name,slug,color,logo,logo_mode,featured,sort) VALUES (:name,:slug,:color,:logo,:logo_mode,:featured,:sort)", $data);
        flash($upErr ? 'Brand created — but no logo was added: ' . $upErr : 'Brand created.', $upErr ? 'err' : 'ok');
    }
    redirect('brands');
}

$v = $editing ? $b : ['id'=>0,'name'=>'','slug'=>'','color'=>'','logo'=>'','logo_mode'=>'auto','featured'=>0,'sort'=>0];
admin_head($editing ? 'Edit brand' : 'Add brand', 'brands', $editing ? $v['name'] : 'New brand');
?>
<form method="post" action="<?= $editing ? "brand-edit?id=".e($id) : "brand-edit" ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="page-actions"><a class="btn btn-ghost" href="brands">← Back</a><div class="spacer"></div><button class="btn btn-primary">Save brand</button></div>

  <div class="a-card"><div class="hd"><h2>Details</h2></div><div class="bd">
    <div class="f-row">
      <div class="field"><label>Name</label><input class="input" name="name" value="<?= e($v['name']) ?>" required></div>
      <div class="field"><label>URL slug</label><input class="input" name="slug" value="<?= e($v['slug']) ?>" placeholder="auto from name"></div>
    </div>
    <div class="f-row">
      <div class="field"><label>Signature colour <span class="faint">(optional)</span></label>
        <div style="display:flex;align-items:center;gap:8px">
          <input type="color" value="<?= e($v['color'] ?: '#9C8158') ?>" oninput="this.nextElementSibling.value=this.value.toUpperCase()">
          <input class="input" name="color" value="<?= e($v['color']) ?>" placeholder="#0057B8" maxlength="7">
        </div>
        <div class="hint">Used for the text wordmark when there's no logo image.</div>
      </div>
      <div class="field"><label>Logo image <span class="faint">(optional)</span></label>
        <?php if ($v['logo']): ?><img src="<?= e(asrc($v['logo'])) ?>" style="height:44px;max-width:160px;object-fit:contain;margin-bottom:8px"><?php endif; ?>
        <input class="input" name="logo" value="<?= e($v['logo']) ?>" placeholder="https://… or upload below">
        <input type="file" name="logo_file" accept="image/*" data-maxmb="10" style="margin-top:8px;font-size:12.5px"><div class="hint">Overrides the wordmark (max 10 MB — auto-optimized).</div>
      </div>
    </div>
    <div class="field"><label>Show in the brands strip as</label>
      <select class="input" name="logo_mode" style="max-width:320px">
        <option value="auto" <?= $v['logo_mode']==='auto'?'selected':'' ?>>Auto — logo if set, otherwise name</option>
        <option value="logo" <?= $v['logo_mode']==='logo'?'selected':'' ?>>Logo only</option>
        <option value="name" <?= $v['logo_mode']==='name'?'selected':'' ?>>Name only</option>
        <option value="both" <?= $v['logo_mode']==='both'?'selected':'' ?>>Logo + name</option>
      </select>
      <div class="hint">How this brand appears in the homepage “trusted brands” strip.</div>
    </div>
    <div class="f-row-3">
      <div class="field"><label>Sort order</label><input class="input" type="number" name="sort" value="<?= e($v['sort']) ?>"></div>
    </div>
    <label class="switch"><input type="checkbox" name="featured" value="1" <?= $v['featured']?'checked':'' ?>> Feature in the homepage “trusted brands” strip</label>
  </div></div>
  <div class="page-actions" style="margin-top:18px"><div class="spacer"></div><button class="btn btn-primary">Save brand</button></div>
</form>
<?php admin_foot();
