<?php
require __DIR__ . '/inc/layout.php';

/* All hero content lives in settings (group 'content'). This editor is reached
   from the Home Sections list — the hero is the top banner of the home page. */

/* key => [type, label, default, hint]  (type: text | textarea) */
$TEXT = [
    // headline
    'hero_eyebrow'      => ['text', 'Eyebrow', 'clinically trusted', 'Small pill above the headline'],
    'hero_title'        => ['text', 'Title', 'next-gen', 'Main headline'],
    'hero_title_accent' => ['text', 'Title — accent word', 'wellness', 'Shown in the accent colour'],
    'hero_sub'          => ['textarea', 'Subtitle', 'Real results. Real confidence. Powered by science, dispensed with care — your everyday glow, distilled. ♡', ''],
    // buttons
    'hero_cta1_label'   => ['text', 'Button 1 — label', 'shop bestsellers', 'Leave blank to hide this button'],
    'hero_cta1_link'    => ['text', 'Button 1 — link', 'skincare', 'A page path (e.g. skincare) or full URL'],
    'hero_cta2_label'   => ['text', 'Button 2 — label', 'talk to an expert', 'Leave blank to hide this button'],
    'hero_cta2_link'    => ['text', 'Button 2 — link', 'contact', 'A page path (e.g. contact) or full URL'],
    // stats
    'hero_stat1_k'      => ['text', 'Stat 1 — value', '100%', 'Leave value blank to hide this stat'],
    'hero_stat1_l'      => ['text', 'Stat 1 — label', 'authentic products', ''],
    'hero_stat2_k'      => ['text', 'Stat 2 — value', '4.8★', ''],
    'hero_stat2_l'      => ['text', 'Stat 2 — label', '7,000+ reviews', ''],
    'hero_stat3_k'      => ['text', 'Stat 3 — value', '24h', ''],
    'hero_stat3_l'      => ['text', 'Stat 3 — label', 'beirut delivery', ''],
    // badges on the photo
    'hero_tag1_sm'      => ['text', 'Badge 1 — small line', 'new in', 'Top-left badge on the photo'],
    'hero_tag1_bg'      => ['text', 'Badge 1 — big line', 'glow serum', ''],
    'hero_tag2_sm'      => ['text', 'Badge 2 — small line', 'loved by 7,000+', 'Bottom-right badge on the photo'],
    'hero_tag2_bg'      => ['text', 'Badge 2 — big line', '★★★★★', 'Shown in the star colour'],
];

/* Slides: any number, one per line in the `hero_imgs` setting, in the order they play.
   Before this editor there were four fixed slots (hero_img_1..4); a shop that has not
   saved since still shows those (assets/data.php falls back to them the same way). */
const HERO_LEGACY = ['hero_img_1', 'hero_img_2', 'hero_img_3', 'hero_img_4'];
function hero_lines(string $s): array { return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $s)))); }
function hero_slides(): array {
    $list = hero_lines((string) setting('hero_imgs', ''));
    return $list ?: array_values(array_filter(array_map(fn($k) => trim(setting($k, '')), HERO_LEGACY)));
}

if (is_post()) {
    csrf_check();
    foreach ($TEXT as $key => $_) set_setting($key, trim((string) input($key)), 'content');

    /* the kept slides (paths / URLs, in the order the tiles show) + any new uploads after them */
    $slides = hero_lines((string) input('hero_imgs'));
    $imgErrs = [];
    if (!empty($_FILES['hero_files']['name']) && is_array($_FILES['hero_files']['name'])) {
        foreach ($_FILES['hero_files']['name'] as $i => $nm) {
            if (($_FILES['hero_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $_FILES['_hf'] = ['name' => $nm, 'type' => $_FILES['hero_files']['type'][$i], 'tmp_name' => $_FILES['hero_files']['tmp_name'][$i],
                              'error' => $_FILES['hero_files']['error'][$i], 'size' => $_FILES['hero_files']['size'][$i]];
            $err = null;
            if ($up = save_upload('_hf', $err)) $slides[] = $up;
            elseif ($err) $imgErrs[] = $nm . ': ' . $err;
        }
    }
    $slides = array_values(array_unique($slides));
    set_setting('hero_imgs', implode("\n", $slides), 'content');
    foreach (HERO_LEGACY as $k) set_setting($k, '', 'content');   // one list from now on

    if ($imgErrs) flash('Hero saved, but some slides were not added — ' . implode(' · ', $imgErrs), 'err');
    else          flash('Hero saved — your homepage banner has been updated.');
    redirect('hero-edit');
}

$slides = hero_slides();

admin_head('Edit Hero', 'home-sections', 'The banner at the top of your home page — text, buttons, stats, badges and slide images.');
?>
<form method="post" action="hero-edit" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="page-actions">
    <a class="btn btn-ghost" href="home-sections">← Home Sections</a>
    <div class="spacer"></div>
    <a class="btn btn-ghost" href="../" target="_blank"><?= aicon('eye') ?> Preview store</a>
    <button class="btn btn-primary">Save hero</button>
  </div>

  <?php
  $groups = [
    ['Headline', 'The words on the left side of the banner', ['hero_eyebrow','hero_title','hero_title_accent','hero_sub']],
    ['Buttons', 'The two call-to-action buttons — clear a label to hide that button', ['hero_cta1_label','hero_cta1_link','hero_cta2_label','hero_cta2_link']],
    ['Stats', 'The three numbers under the buttons — clear a value to hide that stat', ['hero_stat1_k','hero_stat1_l','hero_stat2_k','hero_stat2_l','hero_stat3_k','hero_stat3_l']],
    ['Photo badges', 'The two little cards floating over the photo', ['hero_tag1_sm','hero_tag1_bg','hero_tag2_sm','hero_tag2_bg']],
  ];
  foreach ($groups as [$gt, $gd, $keys]): ?>
    <div class="a-card" style="margin-bottom:18px">
      <div class="hd"><h2><?= e($gt) ?></h2><span class="muted" style="font-size:12.5px"><?= e($gd) ?></span></div>
      <div class="bd">
        <div class="f-row">
        <?php foreach ($keys as $key): [$type,$label,$def,$hint] = $TEXT[$key]; $full = $type === 'textarea'; ?>
          <div class="field" style="<?= $full ? 'grid-column:1/-1' : '' ?>">
            <label><?= e($label) ?></label>
            <?php if ($type === 'textarea'): ?>
              <textarea class="input" name="<?= e($key) ?>" rows="3"><?= e(setting($key, $def)) ?></textarea>
            <?php else: ?>
              <input class="input" name="<?= e($key) ?>" value="<?= e(setting($key, $def)) ?>">
            <?php endif; ?>
            <?php if ($hint): ?><div class="hint"><?= e($hint) ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="a-card">
    <div class="hd"><h2>Slide images</h2><span class="muted" style="font-size:12.5px">The photo rotates through these, in this order. Add as many as you like; one makes a still image; none uses the built-in defaults.</span></div>
    <div class="bd">
      <div class="hero-gal" data-hero-gal>
        <div class="hg-grid" data-hg-grid></div>
        <textarea name="hero_imgs" data-hg-store hidden><?= e(implode("\n", $slides)) ?></textarea>
        <input type="file" name="hero_files[]" accept="image/*" multiple data-hg-files hidden>
        <div class="hg-actions">
          <button type="button" class="btn btn-ghost btn-sm" data-hg-pick>＋ Add photos</button>
          <div class="hg-url">
            <input type="text" class="input" data-hg-url placeholder="…or paste an image URL">
            <button type="button" class="btn btn-ghost btn-sm" data-hg-url-add>Add URL</button>
          </div>
        </div>
        <div class="hg-note" data-hg-note></div>
        <div class="hint">Pick several at once. Use ‹ › on a photo to change its place in the slideshow and ✕ to drop it. Nothing changes on the site until you click <b>Save hero</b>. A tall portrait photo (about 4:5) looks best; max 10 MB each — large pictures are optimised automatically.</div>
      </div>
    </div>
  </div>

  <div class="page-actions"><div class="spacer"></div><button class="btn btn-primary">Save hero</button></div>
</form>
<style>
  .hg-grid{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:12px}
  .hg-grid:empty{display:none}
  .hg-tile{position:relative;width:120px}
  .hg-tile .ph{width:120px;height:150px;border-radius:10px;object-fit:cover;border:1px solid var(--a-border2,#e6e1d6);display:block;background:#f4f1ea}
  .hg-tile.up .ph{outline:2px solid #4a7a3a;outline-offset:1px}
  .hg-tile .n{position:absolute;top:6px;left:6px;min-width:22px;height:22px;padding:0 6px;border-radius:999px;background:rgba(44,38,31,.82);color:#fff;font-size:11.5px;font-weight:700;display:flex;align-items:center;justify-content:center}
  .hg-tile .x{position:absolute;top:6px;right:6px;width:26px;height:26px;border:0;border-radius:8px;background:rgba(176,74,47,.92);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.28)}
  .hg-tile .x svg{width:14px;height:14px}
  .hg-tile .mv{position:absolute;bottom:6px;left:6px;right:6px;display:flex;justify-content:space-between}
  .hg-tile .mv button{width:26px;height:26px;border:0;border-radius:8px;background:rgba(255,255,255,.92);color:#2c261f;font-size:16px;line-height:1;cursor:pointer;box-shadow:0 2px 6px rgba(0,0,0,.22)}
  .hg-tile .mv button:disabled{opacity:.35;cursor:default}
  .hg-tile .cap{margin-top:4px;font-size:11px;color:#8a7d6e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .hg-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
  .hg-url{display:flex;gap:8px;flex:1;min-width:260px}
  .hg-url .input{flex:1;min-width:0}
  .hg-note{margin-top:8px;font-size:12.5px;min-height:1.2em}
</style>
<script>
(function(){
  var box=document.querySelector('[data-hero-gal]'); if(!box) return;
  var grid=box.querySelector('[data-hg-grid]'), store=box.querySelector('[data-hg-store]'), fileInput=box.querySelector('[data-hg-files]');
  var pickBtn=box.querySelector('[data-hg-pick]'), urlInput=box.querySelector('[data-hg-url]'), urlAdd=box.querySelector('[data-hg-url-add]'), note=box.querySelector('[data-hg-note]');
  if(typeof DataTransfer==='undefined') return;   // very old browser: the hidden inputs stay as they are

  var picker=document.createElement('input'); picker.type='file'; picker.accept='image/*'; picker.multiple=true; picker.style.display='none'; box.appendChild(picker);

  /* one ordered list: saved slides are strings, new uploads are {file, url}. Uploads always
     come after the kept slides on save (the server appends them in this order). */
  var items=(store.value||'').split(/\r\n|\r|\n/).map(function(s){return s.trim();}).filter(Boolean)
            .filter(function(u,i,a){return a.indexOf(u)===i;});
  var seen=Object.create(null);

  function asrc(v){ return (!v||/^(https?:|\/|data:|blob:)/.test(v)) ? v : '../'+v; }
  function say(msg,ok){ note.style.color=ok?'#4a7a3a':'#b04a2f'; note.textContent=msg||''; }
  function sync(){
    var saved=[], dt=new DataTransfer();
    /* kept slides keep their order; uploads are gathered after them in their order */
    items.forEach(function(it){ if(typeof it==='string') saved.push(it); });
    items.forEach(function(it){ if(typeof it!=='string') dt.items.add(it.file); });
    store.value=saved.join('\n'); fileInput.files=dt.files;
  }
  function move(i,d){ var j=i+d; if(j<0||j>=items.length) return; var t=items[i]; items[i]=items[j]; items[j]=t; render(); }
  function render(){
    grid.innerHTML='';
    /* a new upload can only sit after the kept slides (the server appends them), so its
       arrows only swap with other new uploads */
    items.forEach(function(it,i){
      var isUp=typeof it!=='string';
      var t=document.createElement('div'); t.className='hg-tile'+(isUp?' up':'');
      var img=document.createElement('img'); img.className='ph'; img.src=isUp?it.url:asrc(it); img.loading='lazy'; t.appendChild(img);
      var n=document.createElement('span'); n.className='n'; n.textContent=(i+1); t.appendChild(n);
      var x=document.createElement('button'); x.type='button'; x.className='x'; x.title='Remove';
      x.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>';
      x.addEventListener('click',function(){ if(isUp){ delete seen[it.key]; URL.revokeObjectURL(it.url); } items.splice(i,1); render(); say('Removed — save to apply.',true); });
      t.appendChild(x);
      var mv=document.createElement('div'); mv.className='mv';
      var l=document.createElement('button'); l.type='button'; l.textContent='‹'; l.title='Move earlier';
      var r=document.createElement('button'); r.type='button'; r.textContent='›'; r.title='Move later';
      var prevUp=i>0&&typeof items[i-1]!=='string', nextUp=i<items.length-1&&typeof items[i+1]!=='string';
      l.disabled = i===0 || (isUp ? !prevUp : false) || (!isUp && prevUp);
      r.disabled = i===items.length-1 || (!isUp && nextUp);
      l.addEventListener('click',function(){ move(i,-1); }); r.addEventListener('click',function(){ move(i,1); });
      mv.appendChild(l); mv.appendChild(r); t.appendChild(mv);
      var c=document.createElement('div'); c.className='cap'; c.textContent=isUp?(it.file.name+' (new)'):it; t.appendChild(c);
      grid.appendChild(t);
    });
    sync();
  }

  pickBtn.addEventListener('click',function(){ picker.click(); });
  picker.addEventListener('change',function(){
    var chosen=Array.prototype.slice.call(picker.files||[]); picker.value='';
    var added=0,dup=0,bad=0;
    chosen.forEach(function(file){
      if(!/\.(jpe?g|png|webp|gif|avif)$/i.test(file.name)||file.size>10*1048576){ bad++; return; }
      var k='ns:'+file.name+':'+file.size;
      if(seen[k]){ dup++; return; }
      seen[k]=true; items.push({file:file,key:k,url:URL.createObjectURL(file)}); added++;
    });
    render();
    var parts=[]; if(added) parts.push(added+' added'); if(dup) parts.push(dup+' duplicate'+(dup>1?'s':'')+' skipped'); if(bad) parts.push(bad+' unsupported/too-large skipped');
    say(parts.join(' · ')||'Nothing added', !(dup||bad));
  });
  function addUrl(){
    var u=(urlInput.value||'').trim();
    if(!u){ say('Enter an image URL first.',false); return; }
    if(items.indexOf(u)!==-1){ say('That image is already a slide.',false); return; }
    /* a URL goes after the kept slides, before any new uploads (the server keeps that order) */
    var at=items.length; while(at>0&&typeof items[at-1]!=='string') at--;
    items.splice(at,0,u); render(); urlInput.value=''; say('URL added — save to apply.',true);
  }
  urlAdd.addEventListener('click',function(e){ e.preventDefault(); addUrl(); });
  urlInput.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); addUrl(); } });
  render();
})();
</script>
<?php admin_foot();
