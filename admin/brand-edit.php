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

    if ($name === '') { flash('Name is required.', 'err'); redirect($editing ? 'brand-edit?id=' . $id . admin_ret_qs() : 'brand-edit' . admin_ret_qs('?')); }

    /* optional category — only accept a real category name, otherwise leave it unset */
    $catNames = array_column(rows("SELECT name FROM categories ORDER BY sort, name"), 'name');
    $category = trim((string) input('category'));
    if ($category !== '' && !in_array($category, $catNames, true)) $category = '';

    /* per-brand hero / offer banner (shown at the top of the brand page) */
    $heroImage = trim((string) input('hero_image'));
    $heroErr = null;
    if ($hu = save_upload('hero_image_file', $heroErr)) $heroImage = $hu;
    $heroBg = trim((string) input('hero_bg'));
    if ($heroBg !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $heroBg)) $heroBg = '';
    // featured products: keep only ids that are real products of THIS brand (by name), in picked order
    $heroPickRaw = array_values(array_filter(array_map('trim', explode(',', (string) input('hero_products')))));
    $heroProducts = '';
    if ($heroPickRaw) {
        $ph  = implode(',', array_fill(0, count($heroPickRaw), '?'));
        $okIds = array_column(rows("SELECT id FROM products WHERE id IN ($ph) AND brand = ?", array_merge($heroPickRaw, [$name])), 'id');
        $heroProducts = implode(',', array_values(array_filter($heroPickRaw, fn($x) => in_array($x, $okIds, true))));
    }

    $data = [
        'name'     => $name,
        'slug'     => $slug,
        'category' => $category,
        'color'    => $color,
        'logo'     => $logo,
        'logo_mode'=> in_array(input('logo_mode'), ['auto','logo','name','both'], true) ? input('logo_mode') : 'auto',
        'featured' => input('featured') ? 1 : 0,
        'hero_enabled'   => input('hero_enabled') ? 1 : 0,
        'hero_title'     => trim((string) input('hero_title')),
        'hero_subtitle'  => trim((string) input('hero_subtitle')),
        'hero_image'     => $heroImage,
        'hero_cta_label' => trim((string) input('hero_cta_label')),
        'hero_cta_link'  => trim((string) input('hero_cta_link')),
        'hero_products'  => $heroProducts,
        'hero_bg'        => $heroBg,
        'sort'     => (int) input('sort'),
    ];
    $warn = trim(($upErr ? "logo not saved: $upErr. " : '') . ($heroErr ? "hero image not saved: $heroErr." : ''));

    if ($editing) {
        // brands are referenced on products by name — keep them connected on rename
        if ($name !== $b['name']) q("UPDATE products SET brand = ? WHERE brand = ?", [$name, $b['name']]);
        $data['id'] = $id;
        q("UPDATE brands SET name=:name, slug=:slug, category=:category, color=:color, logo=:logo, logo_mode=:logo_mode, featured=:featured,
              hero_enabled=:hero_enabled, hero_title=:hero_title, hero_subtitle=:hero_subtitle, hero_image=:hero_image,
              hero_cta_label=:hero_cta_label, hero_cta_link=:hero_cta_link, hero_products=:hero_products, hero_bg=:hero_bg,
              sort=:sort WHERE id=:id", $data);
        flash($warn ? "Brand updated — but $warn" : 'Brand updated.', $warn ? 'err' : 'ok');
        $savedId = $id;
    } else {
        if (row("SELECT id FROM brands WHERE slug = ?", [$slug])) { flash('A brand with that URL slug already exists.', 'err'); redirect('brand-edit' . admin_ret_qs('?')); }
        q("INSERT INTO brands (name,slug,category,color,logo,logo_mode,featured,hero_enabled,hero_title,hero_subtitle,hero_image,hero_cta_label,hero_cta_link,hero_products,hero_bg,sort)
           VALUES (:name,:slug,:category,:color,:logo,:logo_mode,:featured,:hero_enabled,:hero_title,:hero_subtitle,:hero_image,:hero_cta_label,:hero_cta_link,:hero_products,:hero_bg,:sort)", $data);
        flash($warn ? "Brand created — but $warn" : 'Brand created.', $warn ? 'err' : 'ok');
        $savedId = (int) last_id();
    }
    /* Back to the same brand, not to the top of the list. See inc/list.php. */
    redirect(input('after') === 'list'
        ? admin_back_href('brands')
        : 'brand-edit?id=' . $savedId . admin_ret_qs());
}

$v = $editing ? $b : ['id'=>0,'name'=>'','slug'=>'','category'=>'','color'=>'','logo'=>'','logo_mode'=>'auto','featured'=>0,
    'hero_enabled'=>0,'hero_title'=>'','hero_subtitle'=>'','hero_image'=>'','hero_cta_label'=>'','hero_cta_link'=>'','hero_products'=>'','hero_bg'=>'','sort'=>0];
$catList = array_column(rows("SELECT name FROM categories ORDER BY sort, name"), 'name');
$heroProducts = $editing ? rows("SELECT id, name, image FROM products WHERE brand = ? AND status='active' ORDER BY sort, name", [$v['name']]) : [];
admin_head($editing ? 'Edit brand' : 'Add brand', 'brands', $editing ? $v['name'] : 'New brand');
?>
<form method="post" action="<?= $editing ? "brand-edit?id=".e($id) : "brand-edit" ?>" enctype="multipart/form-data">
  <?= csrf_field() ?><?= admin_ret_field() ?>
  <div class="page-actions"><a class="btn btn-ghost" href="<?= e(admin_back_href('brands')) ?>">← Back</a><div class="spacer"></div><button class="btn btn-primary">Save brand</button><button class="btn btn-ghost" name="after" value="list">Save &amp; back to list</button></div>

  <div class="a-card"><div class="hd"><h2>Details</h2></div><div class="bd">
    <div class="f-row">
      <div class="field"><label>Name</label><input class="input" name="name" value="<?= e($v['name']) ?>" required></div>
      <div class="field"><label>URL slug</label><input class="input" name="slug" value="<?= e($v['slug']) ?>" placeholder="auto from name"></div>
    </div>
    <div class="f-row">
      <div class="field"><label>Category <span class="faint">(optional)</span></label>
        <select class="input" name="category" style="max-width:320px">
          <option value="">— no category —</option>
          <?php foreach ($catList as $cn): ?><option value="<?= e($cn) ?>" <?= ($v['category'] ?? '')===$cn?'selected':'' ?>><?= e($cn) ?></option><?php endforeach; ?>
        </select>
        <div class="hint">Put a whole-brand-of-one-category brand under that category — it groups the brands page by category.</div>
      </div>
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

  <div class="a-card"><div class="hd"><h2>Brand page banner <span class="faint" style="font-weight:400;font-size:13px">— optional hero / offer shown at the top of this brand’s page</span></h2></div><div class="bd">
    <label class="switch" style="margin-bottom:14px"><input type="checkbox" name="hero_enabled" value="1" <?= $v['hero_enabled']?'checked':'' ?>> Show this banner on the brand page</label>
    <div class="f-row">
      <div class="field"><label>Title <span class="faint">(e.g. a headline or offer)</span></label>
        <input class="input" name="hero_title" value="<?= e($v['hero_title']) ?>" placeholder="e.g. Buy one, get one free"></div>
      <div class="field"><label>Subtitle <span class="faint">(optional)</span></label>
        <input class="input" name="hero_subtitle" value="<?= e($v['hero_subtitle']) ?>" placeholder="e.g. On all <?= e($v['name'] ?: 'this brand') ?> this week — while stocks last"></div>
    </div>
    <div class="f-row">
      <div class="field"><label>Button label <span class="faint">(optional)</span></label>
        <input class="input" name="hero_cta_label" value="<?= e($v['hero_cta_label']) ?>" placeholder="e.g. Shop the offer"></div>
      <div class="field"><label>Button link <span class="faint">(optional)</span></label>
        <input class="input" name="hero_cta_link" value="<?= e($v['hero_cta_link']) ?>" placeholder="e.g. skincare?brand=<?= e($v['name']) ?>"></div>
    </div>
    <div class="f-row">
      <div class="field"><label>Banner image <span class="faint">(optional)</span></label>
        <?php if ($v['hero_image']): ?><img src="<?= e(asrc($v['hero_image'])) ?>" style="height:64px;max-width:240px;object-fit:cover;border-radius:10px;margin-bottom:8px;display:block"><?php endif; ?>
        <input class="input" name="hero_image" value="<?= e($v['hero_image']) ?>" placeholder="https://… or upload below">
        <input type="file" name="hero_image_file" accept="image/*" data-maxmb="10" style="margin-top:8px;font-size:12.5px"><div class="hint">Shown beside the text (max 10 MB — auto-optimized).</div>
      </div>
      <div class="field"><label>Accent colour <span class="faint">(optional)</span></label>
        <div style="display:flex;align-items:center;gap:8px">
          <input type="color" value="<?= e($v['hero_bg'] ?: ($v['color'] ?: '#9C8158')) ?>" oninput="this.nextElementSibling.value=this.value.toUpperCase()">
          <input class="input" name="hero_bg" value="<?= e($v['hero_bg']) ?>" placeholder="brand colour" maxlength="7">
        </div>
        <div class="hint">Tints the banner. Blank = the brand’s signature colour.</div>
      </div>
    </div>
    <div class="field">
      <?php /* this brand's products open in the product editor and come back here (ret=) */
        $hereRet = 'ret=' . rawurlencode('brand-edit?id=' . $id); ?>
      <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:6px">
        <label style="margin:0">Featured products <span class="faint">(optional — pick as many as you like; they scroll sideways in the banner)</span></label>
        <?php if ($editing): ?><a class="btn btn-primary btn-sm" data-leave href="<?= e('product-edit?' . http_build_query(['brand' => $v['name']]) . '&' . $hereRet) ?>">+ Add product to <?= e($v['name']) ?></a><?php endif; ?>
      </div>
      <?php if (!$editing): ?>
        <div class="hint">Save the brand first, then come back here to pick products.</div>
      <?php elseif (!$heroProducts): ?>
        <div class="hint">This brand has no active products to feature yet.</div>
      <?php else: ?>
        <input type="hidden" name="hero_products" id="heroProducts" value="<?= e($v['hero_products'] ?? '') ?>">
        <div class="brand-picker">
          <div class="bp-search"><input class="input" id="heroSearch" type="search" placeholder="Filter products…" autocomplete="off"></div>
          <div class="bp-list bp-list-prod" id="heroList"></div>
        </div>
        <div class="hint" id="heroCount"></div>
      <?php endif; ?>
    </div>
  </div></div>

  <div class="page-actions" style="margin-top:18px"><div class="spacer"></div><button class="btn btn-primary">Save brand</button><button class="btn btn-ghost" name="after" value="list">Save &amp; back to list</button></div>
</form>
<?php if ($editing && $heroProducts): ?>
<style>
  .brand-picker{border:1px solid var(--line,#dcd6c9);border-radius:12px;overflow:hidden}
  .brand-picker .bp-search{padding:10px 12px;border-bottom:1px solid var(--line,#dcd6c9);background:rgba(0,0,0,.02)}
  .brand-picker .bp-search .input{width:100%}
  .brand-picker .bp-list-prod{display:block;max-height:300px;overflow:auto;padding:8px 12px}
  .brand-picker .bp-item{display:flex;align-items:center;gap:11px;padding:7px 6px;border-radius:8px;line-height:1.35;cursor:pointer;font-size:14px}
  .brand-picker .bp-item:hover{background:rgba(0,0,0,.03)}
  .brand-picker .bp-item.on{background:var(--a-primary-tint,#f1ece3)}
  .brand-picker input[type=checkbox]{width:16px;height:16px;flex:none;cursor:pointer}
  .brand-picker .bp-thumb{width:42px;height:42px;flex:none;border-radius:8px;object-fit:cover;background:#f2efe9;border:1px solid var(--a-border,#e7e2d8)}
  .brand-picker .bp-name{flex:1;min-width:0}
  .brand-picker .bp-edit{flex:none;display:inline-flex;align-items:center;gap:5px;height:30px;padding:0 12px;border-radius:999px;
    border:1px solid var(--a-border,#e7e2d8);background:#fff;color:#3a352d;font-size:12.5px;font-weight:700;text-decoration:none}
  .brand-picker .bp-edit:hover{background:#3a352d;border-color:#3a352d;color:#fff}
</style>
<script>
  (function(){
    var ALL = <?= json_encode(array_map(fn($r)=>['id'=>$r['id'],'name'=>$r['name'],'img'=>($r['image']!==''?asrc($r['image']):'')], $heroProducts), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    var RET = <?= json_encode($hereRet, JSON_UNESCAPED_SLASHES) ?>;
    var list=document.getElementById('heroList'), hidden=document.getElementById('heroProducts'),
        countEl=document.getElementById('heroCount'), search=document.getElementById('heroSearch');
    if(!list||!hidden) return;
    function esc(t){ return String(t).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
    var picked={}, order=[];
    hidden.value.split(',').map(function(x){return x.trim();}).filter(Boolean).forEach(function(id){ picked[id]=true; order.push(id); });
    function write(){
      var ids=order.filter(function(id){ return picked[id]; });
      hidden.value=ids.join(',');
      countEl.textContent = ids.length ? (ids.length+' product'+(ids.length===1?'':'s')+' picked — shown in this order, scrolling sideways.') : 'None picked — the banner shows just the text/image.';
    }
    function render(){
      var q=(search&&search.value?search.value:'').trim().toLowerCase();
      var rows=ALL.filter(function(p){ return !q||p.name.toLowerCase().indexOf(q)>=0; });
      list.innerHTML = rows.length ? rows.map(function(p){
        var on=!!picked[p.id];
        var thumb=p.img?'<img class="bp-thumb" src="'+esc(p.img)+'" alt="" loading="lazy" onerror="this.style.visibility=\'hidden\'">':'<span class="bp-thumb"></span>';
        var edit='<a class="bp-edit" data-leave href="product-edit?id='+encodeURIComponent(p.id)+'&'+RET+'">&#9998; Edit</a>';
        return '<label class="bp-item'+(on?' on':'')+'"><input type="checkbox" class="pk" value="'+esc(p.id)+'"'+(on?' checked':'')+'>'+thumb+'<span class="bp-name">'+esc(p.name)+'</span>'+edit+'</label>';
      }).join('') : '<span class="faint" style="padding:8px">No products match.</span>';
    }
    list.addEventListener('change',function(e){
      if(!e.target.classList.contains('pk')) return;
      var id=e.target.value;
      if(e.target.checked){ if(!picked[id]){ picked[id]=true; order.push(id); } } else { delete picked[id]; }
      var lbl=e.target.closest('.bp-item'); if(lbl) lbl.classList.toggle('on', e.target.checked);
      write();
    });
    if(search) search.addEventListener('input', render);
    render(); write();
  })();
</script>
<?php endif; ?>
<script>
  /* "Edit" / "+ Add product" leave this page: warn first if the brand form has unsaved changes */
  (function(){
    var form=document.querySelector('form[action^="brand-edit"]'), dirty=false;
    if(!form) return;
    form.addEventListener('input',function(){ dirty=true; });
    form.addEventListener('change',function(){ dirty=true; });
    form.addEventListener('submit',function(){ dirty=false; });
    document.addEventListener('click',function(e){
      var a=e.target.closest('a[data-leave]');
      if(a && dirty && !confirm('You have unsaved changes to this brand. Leave without saving them?')) e.preventDefault();
    });
  })();
</script>
<?php admin_foot();
