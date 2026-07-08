<?php
require __DIR__ . '/inc/layout.php';

$BADGES = ['' => '— none —','derm'=>'Derm Pick','best'=>'Bestseller','trend'=>'Trending','trusted'=>'Trusted','new'=>'New','vegan'=>'Vegan','ff'=>'Frag-Free'];
$cats   = array_column(rows("SELECT name FROM categories ORDER BY sort"), 'name');
$brandList = array_column(rows("SELECT name FROM brands ORDER BY name"), 'name');

$id = (string) input('id');
$editing = $id !== '' && ($p = row("SELECT * FROM products WHERE id = ?", [$id]));
if ($id !== '' && !$editing) { flash('Product not found.', 'err'); redirect('products'); }

if (is_post()) {
    csrf_check();
    $name  = trim((string) input('name'));
    $newId = $editing ? $id : (trim((string) input('id')) ?: slugify($name));
    $price = (float) input('price');
    $was   = input('was') !== '' ? (float) input('was') : null;
    $sale  = input('sale_pct') !== '' ? (int) input('sale_pct') : null;

    $image = trim((string) input('image'));
    $hover = trim((string) input('hover_image'));
    $upErr = null; $upErr2 = null;
    if ($u = save_upload('image_file', $upErr)) $image = $u;
    if ($u = save_upload('hover_file', $upErr2)) $hover = $u;
    if (!$upErr && $upErr2) $upErr = $upErr2;

    $data = [
        'name'=>$name, 'brand'=>trim((string)input('brand')), 'category'=>(string)input('category'),
        'price'=>$price, 'was'=>$was, 'sale_pct'=>$sale, 'badge'=>(string)input('badge'),
        'stock'=>(int)input('stock'), 'low_stock'=>(int)input('low_stock'),
        'kw'=>trim((string)input('kw')), 'descr'=>trim((string)input('descr')),
        'long_desc'=>(string)input('long_desc'), 'image'=>$image, 'hover_image'=>$hover,
        'barcode'=>trim((string)input('barcode')), 'sku'=>trim((string)input('sku')), 'size'=>trim((string)input('size')),
        'how_to_use'=>(string)input('how_to_use'), 'ingredients'=>(string)input('ingredients'), 'benefits'=>(string)input('benefits'),
        'keywords'=>(string)input('keywords'),
        'feat_latest'=> input('feat_latest') ? 1 : 0, 'feat_wellness'=> input('feat_wellness') ? 1 : 0,
        'home_sort'=>(int)input('home_sort'), 'status'=> input('status')==='draft'?'draft':'active',
    ];

    if ($name === '' || $newId === '') { flash('Name is required.', 'err'); redirect($editing ? "product-edit?id=$id" : 'product-edit'); }

    if ($editing) {
        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $data['id'] = $id;
        q("UPDATE products SET $sets WHERE id = :id", $data);
        flash($upErr ? 'Product updated — but the image was not changed: ' . $upErr : 'Product updated.', $upErr ? 'err' : 'ok');
    } else {
        if (row("SELECT id FROM products WHERE id = ?", [$newId])) { flash('A product with that ID already exists.', 'err'); redirect('product-edit'); }
        $data['id'] = $newId;
        $cols = implode(', ', array_keys($data));
        $ph   = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
        q("INSERT INTO products ($cols) VALUES ($ph)", $data);
        flash($upErr ? 'Product created — but no image was added: ' . $upErr : 'Product created.', $upErr ? 'err' : 'ok');
    }
    redirect('products');
}

/* defaults for the form */
$v = $editing ? $p : ['id'=>'','name'=>'','brand'=>'','category'=>$cats[0]??'','price'=>'','was'=>'','sale_pct'=>'',
    'badge'=>'','rating'=>'4.8','reviews'=>'0','stock'=>'0','low_stock'=>'5','kw'=>'','descr'=>'','long_desc'=>'',
    'barcode'=>'','sku'=>'','size'=>'','how_to_use'=>'','ingredients'=>'','benefits'=>'','keywords'=>'',
    'image'=>'','hover_image'=>'','feat_latest'=>0,'feat_wellness'=>0,'home_sort'=>0,'status'=>'active'];

admin_head($editing ? 'Edit product' : 'Add product', 'products', $editing ? $v['name'] : 'New product');
?>
<form method="post" action="<?= $editing ? "product-edit?id=".e($id) : "product-edit" ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="page-actions"><a class="btn btn-ghost" href="products">← Back</a><div class="spacer"></div><button class="btn btn-primary">Save product</button></div>

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
        <div class="field"><label>Product ID / slug <span class="faint">(optional)</span></label><input class="input" name="id" placeholder="auto from name, e.g. lumiere-vitc"><div class="hint">Used in the product URL. Leave blank to generate automatically.</div></div>
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
    </div>

    <div style="display:flex;flex-direction:column;gap:18px">
      <div class="a-card"><div class="hd"><h2>Pricing &amp; stock</h2></div><div class="bd">
        <div class="f-row">
          <div class="field"><label>Price ($)</label><input class="input" type="number" step="0.01" name="price" value="<?= e($v['price']) ?>" required></div>
          <div class="field"><label>Was ($)</label><input class="input" type="number" step="0.01" name="was" value="<?= e($v['was']) ?>" placeholder="if on sale"></div>
        </div>
        <div class="f-row">
          <div class="field"><label>Sale badge (%)</label><input class="input" type="number" name="sale_pct" value="<?= e($v['sale_pct']) ?>" placeholder="e.g. 20"></div>
          <div class="field"><label>Badge</label><select class="input" name="badge">
            <?php foreach ($BADGES as $bk=>$bl): ?><option value="<?= e($bk) ?>" <?= $bk===$v['badge']?'selected':'' ?>><?= e($bl) ?></option><?php endforeach; ?>
          </select></div>
        </div>
        <div class="f-row">
          <div class="field"><label>Stock</label><input class="input" type="number" name="stock" value="<?= e($v['stock']) ?>"></div>
          <div class="field"><label>Low-stock warning at</label><input class="input" type="number" name="low_stock" value="<?= e($v['low_stock']) ?>"><div class="hint">Shows "Only X left" when stock hits this.</div></div>
        </div>
        <p class="muted" style="font-size:12.5px;margin:4px 0 0">⭐ Rating &amp; review count come from real customer reviews on the product page — not set here.</p>
      </div></div>

      <div class="a-card"><div class="hd"><h2>Catalog</h2></div><div class="bd">
        <div class="f-row">
          <div class="field"><label>Barcode (EAN)</label><input class="input" name="barcode" value="<?= e($v['barcode'] ?? '') ?>"></div>
          <div class="field"><label>SKU / item #</label><input class="input" name="sku" value="<?= e($v['sku'] ?? '') ?>"></div>
        </div>
        <div class="field"><label>Size</label><input class="input" name="size" value="<?= e($v['size'] ?? '') ?>" placeholder="e.g. 150 ml"></div>
      </div></div>

      <div class="a-card"><div class="hd"><h2>Visibility</h2></div><div class="bd">
        <div class="field"><label>Status</label><select class="input" name="status">
          <option value="active" <?= $v['status']==='active'?'selected':'' ?>>Active (visible)</option>
          <option value="draft"  <?= $v['status']==='draft'?'selected':'' ?>>Draft (hidden)</option>
        </select></div>
        <label class="switch" style="margin-bottom:12px"><input type="checkbox" name="feat_latest" value="1" <?= $v['feat_latest']?'checked':'' ?>> Show in homepage “New Arrivals”</label>
        <div class="field" style="margin-top:8px"><label>Homepage order</label><input class="input" type="number" name="home_sort" value="<?= e($v['home_sort']) ?>"></div>
      </div></div>
    </div>
  </div>
  <div class="page-actions" style="margin-top:18px"><div class="spacer"></div><button class="btn btn-primary">Save product</button></div>
</form>
<?php admin_foot();
