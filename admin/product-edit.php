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

    $galPaths = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) input('gallery')))));
    if (!empty($_FILES['gallery_files']['name']) && is_array($_FILES['gallery_files']['name'])) {
        foreach ($_FILES['gallery_files']['name'] as $gi => $gnm) {
            if (($_FILES['gallery_files']['error'][$gi] ?? 4) !== UPLOAD_ERR_OK) continue;
            $_FILES['_gf'] = ['name'=>$gnm,'type'=>$_FILES['gallery_files']['type'][$gi],'tmp_name'=>$_FILES['gallery_files']['tmp_name'][$gi],'error'=>0,'size'=>$_FILES['gallery_files']['size'][$gi]];
            $ge=null; if ($gu = save_upload('_gf', $ge)) $galPaths[] = $gu;
        }
    }
    // de-duplicate: drop repeated paths/URLs and pixel-identical local uploads (safety net for the client-side guard)
    $seenStr = []; $seenHash = []; $uniqueGal = [];
    foreach ($galPaths as $gpath) {
        if (isset($seenStr[$gpath])) continue;
        $seenStr[$gpath] = true;
        if (!preg_match('~^(https?:|/|data:)~', $gpath)) {              // local, site-relative path → compare file contents
            $abs = dirname(__DIR__) . '/' . $gpath;
            if (is_file($abs) && ($h = md5_file($abs)) !== false) {
                if (isset($seenHash[$h])) continue;
                $seenHash[$h] = true;
            }
        }
        $uniqueGal[] = $gpath;
    }
    $galPaths = $uniqueGal;

    $data = [
        'name'=>$name, 'brand'=>trim((string)input('brand')), 'category'=>(string)input('category'),
        'price'=>$price, 'was'=>$was, 'sale_pct'=>$sale, 'badge'=>(string)input('badge'),
        'stock'=>(int)input('stock'), 'low_stock'=>(int)input('low_stock'),
        'kw'=>trim((string)input('kw')), 'descr'=>trim((string)input('descr')),
        'long_desc'=>(string)input('long_desc'), 'image'=>$image, 'hover_image'=>$hover, 'gallery'=>implode("\n",$galPaths),
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
    'image'=>'','hover_image'=>'','gallery'=>'','feat_latest'=>0,'feat_wellness'=>0,'home_sort'=>0,'status'=>'active'];

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

      <div class="a-card"><div class="hd"><h2>Gallery images</h2></div><div class="bd">
        <?php $gp = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($v['gallery'] ?? ''))))); ?>
        <div class="field"><label>Extra photos <span class="faint">(thumbnails on the product page, after main + hover)</span></label>
          <div class="gal" data-gallery>
            <div class="gal-grid" data-gal-grid></div>
            <textarea name="gallery" data-gal-store hidden><?= e(implode("\n", $gp)) ?></textarea>
            <input type="file" name="gallery_files[]" accept="image/*" multiple data-gal-files hidden>
            <div class="gal-actions">
              <button type="button" class="btn btn-ghost btn-sm" data-gal-pick>＋ Add photos</button>
              <div class="gal-url">
                <input type="text" class="input" data-gal-url placeholder="…or paste an image URL">
                <button type="button" class="btn btn-ghost btn-sm" data-gal-url-add>Add URL</button>
              </div>
            </div>
            <div class="gal-note" data-gal-note></div>
            <div class="hint">Pick several at once — previews appear instantly and stack left→right. Hover a photo and click ✕ to remove it. Duplicate images are skipped automatically. Nothing is saved until you click <b>Save product</b>.</div>
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

<style>
  .gal-grid{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px}
  .gal-grid:empty{display:none}
  .gal-tile{position:relative;width:92px}
  .gal-tile .ph{width:92px;height:92px;border-radius:10px;object-fit:cover;border:1px solid var(--a-border2,#e6e1d6);display:block;background:#f4f1ea}
  .gal-tile .cap{margin-top:4px;font-size:10.5px;line-height:1.3;color:#8a7d6e;word-break:break-all;max-height:28px;overflow:hidden}
  .gal-tile .x{position:absolute;top:5px;right:5px;width:26px;height:26px;border:0;border-radius:7px;background:rgba(176,74,47,.92);color:#fff;cursor:pointer;display:none;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.28);padding:0}
  .gal-tile:hover .x{display:flex}
  .gal-tile .x:hover{background:#b04a2f}
  .gal-tile .x svg{width:14px;height:14px}
  .gal-tile.up .ph{border-style:dashed;border-color:#8fae7e}
  .gal-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
  .gal-url{display:flex;gap:8px;flex:1;min-width:240px}
  .gal-url .input{flex:1}
  .gal-note{margin-top:8px;font-size:12.5px;line-height:1.5}
  .gal-note:empty{display:none}
</style>
<script>
(function(){
  var box=document.querySelector('[data-gallery]'); if(!box) return;
  var grid=box.querySelector('[data-gal-grid]');
  var store=box.querySelector('[data-gal-store]');
  var fileInput=box.querySelector('[data-gal-files]');
  var pickBtn=box.querySelector('[data-gal-pick]');
  var urlInput=box.querySelector('[data-gal-url]');
  var urlAdd=box.querySelector('[data-gal-url-add]');
  var note=box.querySelector('[data-gal-note]');
  if(typeof DataTransfer==='undefined') return;   // very old browser: leave the (hidden) native inputs as-is

  // hidden picker: opens the file dialog; its selections are merged into `files` (the named input keeps them all)
  var picker=document.createElement('input');
  picker.type='file'; picker.accept='image/*'; picker.multiple=true; picker.style.display='none';
  box.appendChild(picker);

  var urls=(store.value||'').split(/\r\n|\r|\n/).map(function(s){return s.trim();}).filter(Boolean);
  urls=urls.filter(function(u,i){return urls.indexOf(u)===i;});   // drop any pre-existing repeats
  var files=[];                       // [{file, key, url}]
  var seen=Object.create(null);       // content-hash (or name:size) -> true, for de-dupe

  function asrc(v){ return (!v||/^(https?:|\/|data:|blob:)/.test(v)) ? v : '../'+v; }
  function trashSvg(){ return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"/></svg>'; }
  function say(msg,ok){ note.style.color=ok?'#4a7a3a':'#b04a2f'; note.textContent=msg||''; }
  function syncStore(){ store.value=urls.join('\n'); }
  function syncFiles(){ var dt=new DataTransfer(); files.forEach(function(f){dt.items.add(f.file);}); fileInput.files=dt.files; }
  function hex(buf){ return Array.prototype.map.call(new Uint8Array(buf),function(b){return b.toString(16).padStart(2,'0');}).join(''); }

  function tile(src,cap,isUp,onRemove){
    var t=document.createElement('div'); t.className='gal-tile'+(isUp?' up':'');
    var img=document.createElement('img'); img.className='ph'; img.src=src; img.loading='lazy'; t.appendChild(img);
    var x=document.createElement('button'); x.type='button'; x.className='x'; x.title='Remove'; x.innerHTML=trashSvg();
    x.addEventListener('click',function(e){e.preventDefault();onRemove();}); t.appendChild(x);
    var c=document.createElement('div'); c.className='cap'; c.textContent=cap; t.appendChild(c);
    grid.appendChild(t);
  }
  function render(){
    grid.innerHTML='';
    urls.forEach(function(u,i){ tile(asrc(u),u,false,function(){ urls.splice(i,1); syncStore(); render(); say('Removed.',true); }); });
    files.forEach(function(f,i){ tile(f.url,f.file.name+' (new)',true,function(){ delete seen[f.key]; URL.revokeObjectURL(f.url); files.splice(i,1); syncFiles(); render(); say('Removed.',true); }); });
  }

  function keyFor(file){
    if(window.crypto&&crypto.subtle&&file.arrayBuffer){
      return file.arrayBuffer()
        .then(function(buf){ return crypto.subtle.digest('SHA-256',buf); })
        .then(function(h){ return hex(h); })
        .catch(function(){ return 'ns:'+file.name+':'+file.size; });
    }
    return Promise.resolve('ns:'+file.name+':'+file.size);
  }

  // best-effort: fingerprint already-saved same-origin images so re-uploading one is caught too
  urls.forEach(function(u){
    if(!(window.crypto&&crypto.subtle)) return;
    try{
      fetch(asrc(u)).then(function(r){ return r.ok?r.blob():null; })
        .then(function(b){ return (b&&b.arrayBuffer)?b.arrayBuffer():null; })
        .then(function(buf){ return buf?crypto.subtle.digest('SHA-256',buf):null; })
        .then(function(h){ if(h) seen[hex(h)]=true; }).catch(function(){});
    }catch(e){}
  });

  pickBtn.addEventListener('click',function(){ picker.click(); });
  picker.addEventListener('change',function(){
    var chosen=Array.prototype.slice.call(picker.files||[]); picker.value='';
    if(!chosen.length) return;
    say('Adding…',true);
    var added=0,dup=0,bad=0,pending=chosen.length;
    function done(){
      if(--pending>0) return;
      var parts=[];
      if(added) parts.push(added+' added');
      if(dup)   parts.push(dup+' duplicate'+(dup>1?'s':'')+' skipped');
      if(bad)   parts.push(bad+' unsupported/too-large skipped');
      say(parts.join(' · ')||'Nothing added', !(dup||bad));
    }
    chosen.forEach(function(file){
      if(!/\.(jpe?g|png|webp|gif|avif)$/i.test(file.name)||file.size>10*1048576){ bad++; done(); return; }
      keyFor(file).then(function(k){
        if(seen[k]){ dup++; }
        else { seen[k]=true; files.push({file:file,key:k,url:URL.createObjectURL(file)}); added++; syncFiles(); render(); }
        done();
      });
    });
  });

  function addUrl(){
    var u=(urlInput.value||'').trim();
    if(!u){ say('Enter an image URL first.',false); return; }
    if(urls.indexOf(u)!==-1){ say('That URL is already in the gallery.',false); return; }
    urls.push(u); syncStore(); render(); urlInput.value=''; say('URL added.',true);
  }
  urlAdd.addEventListener('click',function(e){ e.preventDefault(); addUrl(); });
  urlInput.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); addUrl(); } });

  syncStore(); render();
})();
</script>
<?php admin_foot();
