<?php
require __DIR__ . '/inc/layout.php';

$id = (int) input('id');
$editing = $id > 0 && ($c = row("SELECT * FROM categories WHERE id = ?", [$id]));
if ($id > 0 && !$editing) { flash('Category not found.', 'err'); redirect('categories'); }

if (is_post()) {
    csrf_check();
    $name = trim((string) input('name'));
    $slug = slugify((string) (input('slug') ?: $name));
    $image = trim((string) input('image'));
    $upErr = null;
    if ($u = save_upload('image_file', $upErr)) $image = $u;

    if ($name === '') { flash('Name is required.', 'err'); redirect($editing ? "category-edit?id=$id" : 'category-edit'); }

    $data = [
        'name'     => $name,
        'slug'     => $slug,
        'image'    => $image,
        'in_nav'   => input('in_nav')   ? 1 : 0,
        'is_cross' => input('is_cross') ? 1 : 0,
        'is_sale'  => input('is_sale')  ? 1 : 0,
        'sort'     => (int) input('sort'),
    ];

    if ($editing) {
        // categories are referenced on products by name — keep them connected on rename
        if ($name !== $c['name']) q("UPDATE products SET category = ? WHERE category = ?", [$name, $c['name']]);
        $data['id'] = $id;
        q("UPDATE categories SET name=:name, slug=:slug, image=:image, in_nav=:in_nav, is_cross=:is_cross, is_sale=:is_sale, sort=:sort WHERE id=:id", $data);
        flash($upErr ? 'Category updated — but the image was not changed: ' . $upErr : 'Category updated.', $upErr ? 'err' : 'ok');
    } else {
        if (row("SELECT id FROM categories WHERE slug = ?", [$slug])) { flash('A category with that URL slug already exists.', 'err'); redirect('category-edit'); }
        q("INSERT INTO categories (name,slug,image,in_nav,is_cross,is_sale,sort) VALUES (:name,:slug,:image,:in_nav,:is_cross,:is_sale,:sort)", $data);
        flash($upErr ? 'Category created — but no image was added: ' . $upErr : 'Category created.', $upErr ? 'err' : 'ok');
    }
    redirect('categories');
}

$v = $editing ? $c : ['id'=>0,'name'=>'','slug'=>'','image'=>'','in_nav'=>1,'is_cross'=>0,'is_sale'=>0,'sort'=>0];
admin_head($editing ? 'Edit category' : 'Add category', 'categories', $editing ? $v['name'] : 'New category');
?>
<form method="post" action="<?= $editing ? "category-edit?id=".e($id) : "category-edit" ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="page-actions"><a class="btn btn-ghost" href="categories">← Back</a><div class="spacer"></div><button class="btn btn-primary">Save category</button></div>

  <div class="a-card"><div class="hd"><h2>Details</h2></div><div class="bd">
    <div class="f-row">
      <div class="field"><label>Name</label><input class="input" name="name" value="<?= e($v['name']) ?>" required></div>
      <div class="field"><label>URL slug</label><input class="input" name="slug" value="<?= e($v['slug']) ?>" placeholder="auto from name"><div class="hint">Used in filters &amp; links. Leave blank to generate.</div></div>
    </div>
    <div class="field"><label>Image</label>
      <?php if ($v['image']): ?><img src="<?= e(asrc($v['image'])) ?>" style="width:74px;height:74px;border-radius:10px;object-fit:cover;margin-bottom:8px;border:1px solid var(--a-border2)"><?php endif; ?>
      <input class="input" name="image" value="<?= e($v['image']) ?>" placeholder="https://… or upload below">
      <input type="file" name="image_file" accept="image/*" data-maxmb="10" style="margin-top:8px;font-size:12.5px"><div class="hint">Shown on category cards (JPG/PNG/WebP, max 10 MB — auto-optimized).</div>
    </div>
    <div class="f-row-3">
      <div class="field"><label>Sort order</label><input class="input" type="number" name="sort" value="<?= e($v['sort']) ?>"></div>
    </div>
    <label class="switch" style="margin-bottom:12px"><input type="checkbox" name="in_nav" value="1" <?= $v['in_nav']?'checked':'' ?>> Show in the top navigation &amp; PLP filter pills</label><br>
    <label class="switch" style="margin-bottom:12px"><input type="checkbox" name="is_cross" value="1" <?= $v['is_cross']?'checked':'' ?>> Health-conditions style (cross icon)</label><br>
    <label class="switch"><input type="checkbox" name="is_sale" value="1" <?= $v['is_sale']?'checked':'' ?>> Highlight as a sale / offers category</label>
  </div></div>
  <div class="page-actions" style="margin-top:18px"><div class="spacer"></div><button class="btn btn-primary">Save category</button></div>
</form>
<?php admin_foot();
