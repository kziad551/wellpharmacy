<?php
/* ============================================================
   WELL PHARMACY admin — shared layout (sidebar + topbar)
   ============================================================ */
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__, 2) . '/inc/theme.php';

/* gate every admin page that includes this layout (login.php never does) */
require_login();

/* admin lives one level under site root, so site-relative image paths
   (e.g. "uploads/x.jpg") need a "../" prefix; full URLs pass through. */
function asrc(string $v): string {
    return ($v === '' || preg_match('~^(https?:|/|data:)~', $v)) ? $v : '../' . $v;
}

function aicon(string $n): string {
    $i = [
        'dash'   => '<path d="M3 3h7v7H3zM14 3h7v4h-7zM14 10h7v11h-7zM3 14h7v7H3z"/>',
        'box'    => '<path d="M21 16V8a2 2 0 0 0-1-1.7l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.7l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/>',
        'grid'   => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'tag'    => '<path d="M20.6 13.4 12 22l-9-9V4a1 1 0 0 1 1-1h8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'cart'   => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>',
        'ticket' => '<path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4z"/>',
        'pen'    => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'brush'  => '<path d="M9.06 11.9 1.5 19.5 4.5 22.5l7.6-7.56M14 7l3 3M7 17a4 4 0 0 1-4 4M20.5 3.5a2.1 2.1 0 0 0-3 0L9 12l3 3 8.5-8.5a2.1 2.1 0 0 0 0-3z"/>',
        'cog'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-2.7-1.1l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.1-2.7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>',
        'out'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'eye'    => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
        'plus'   => '<path d="M12 5v14M5 12h14"/>',
        'doc'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        'store'  => '<path d="M3 9 4 4h16l1 5M4 9v11h16V9M9 20v-6h6v6"/>',
        'mail'   => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'users'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'layout' => '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="14" width="18" height="6" rx="1.5"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . ($i[$n] ?? '') . '</svg>';
}

function admin_head(string $title, string $current = '', string $subtitle = ''): void {
    require_login();
    $me = current_admin();
    $unread = (int) val("SELECT COUNT(*) FROM messages WHERE is_read = 0");
    $nav = [
        ['Main', [
            ['dashboard', 'Dashboard', 'dash'],
            ['orders',    'Orders',    'cart'],
            ['products',  'Products',  'box'],
        ]],
        ['Catalog', [
            ['categories','Categories','grid'],
            ['brands',    'Brands',    'store'],
            ['coupons',   'Coupons',   'ticket'],
            ['journal',   'Journal',   'pen'],
        ]],
        ['Inbox', [
            ['messages',    $unread ? "Messages ($unread)" : 'Messages', 'mail'],
            ['customers',   'Customers', 'users'],
            ['subscribers', 'Subscribers', 'users'],
        ]],
        ['Site', [
            ['home-sections','Home Sections','layout'],
            ['appearance','Appearance','brush'],
            ['pages',     'Content',   'doc'],
            ['settings',  'Settings',  'cog'],
        ]],
    ];
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — WELL Admin</title>
<link rel="stylesheet" href="<?= asset('assets/admin.css') ?>">
</head>
<body>
<div class="a-shell">
  <aside class="a-side" id="aSide">
    <div class="a-brand">
      <span class="mark">W</span>
      <span><b><?= e(setting('store_name','WELL')) ?></b><span>admin panel</span></span>
    </div>
    <nav class="a-nav">
      <?php foreach ($nav as [$grp, $items]): ?>
        <div class="lbl"><?= e($grp) ?></div>
        <?php foreach ($items as [$slug, $label, $ic]): ?>
          <a href="<?= e($slug) ?>" class="<?= $current === $slug ? 'on' : '' ?>"><?= aicon($ic) ?><?= e($label) ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <div class="lbl">Session</div>
      <a href="../" target="_blank"><?= aicon('eye') ?>View store</a>
      <a href="logout"><?= aicon('out') ?>Sign out</a>
    </nav>
    <div class="foot">© <?= date('Y') ?> WELL PHARMACY</div>
  </aside>
  <div class="a-side-bd" id="aSideBd" hidden></div>
  <main class="a-main">
    <div class="a-top">
      <button class="a-burger" id="aBurger" type="button" aria-label="Open menu" aria-expanded="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
      <div class="a-top-title"><h1><?= e($title) ?></h1><?php if ($subtitle): ?><div class="sub"><?= e($subtitle) ?></div><?php endif; ?></div>
      <div class="who"><span class="who-name"><?= e($me['name']) ?></span><span class="av"><?= e(strtoupper(substr($me['name'],0,1))) ?></span></div>
    </div>
    <div class="a-body">
    <?php if ($f = take_flash()): ?>
      <div class="a-toast a-toast-<?= $f['t']==='err'?'err':'ok' ?>" id="aToast" role="status">
        <span class="a-toast-ic"><?= $f['t']==='err' ? '!' : '&#10003;' ?></span>
        <span class="a-toast-msg"><?= e($f['m']) ?></span>
        <button class="a-toast-x" type="button" aria-label="Dismiss">&times;</button>
      </div>
    <?php endif; ?>
<?php
}

function admin_foot(): void {
    ?>
    </div>
  </main>
</div>

<style>
  .rt{border:1px solid var(--a-border2,#e6e1d6);border-radius:10px;overflow:hidden;background:#fff}
  .rt-bar{display:flex;gap:2px;padding:6px;border-bottom:1px solid var(--a-border2,#e6e1d6);background:#faf9f6;flex-wrap:wrap}
  .rt-b{border:0;background:transparent;padding:6px 9px;border-radius:6px;cursor:pointer;font-size:13.5px;line-height:1;color:#3a352d;min-width:30px}
  .rt-b:hover{background:#ece7dc}
  .rt-b.sep{pointer-events:none;color:#cbc4b6;padding:6px 2px}
  .rt-area{min-height:200px;max-height:520px;overflow:auto;padding:14px 16px;outline:none;font-size:14px;line-height:1.65;color:#2c261f}
  .rt-area:empty:before{content:attr(data-ph);color:#a79f90}
  .rt-area h3{font-size:18px;font-weight:700;margin:16px 0 8px}
  .rt-area p{margin:0 0 10px}
  .rt-area ul,.rt-area ol{margin:8px 0 10px 22px}
  .rt-area a{color:#7a6244;text-decoration:underline}
  .imgwrap{position:relative;display:inline-block;margin-bottom:8px;line-height:0}
  .imgwrap .imgtrash{position:absolute;top:6px;right:6px;width:30px;height:30px;border:0;border-radius:8px;background:rgba(176,74,47,.92);color:#fff;cursor:pointer;display:none;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.28)}
  .imgwrap:hover .imgtrash{display:flex}
  .imgwrap .imgtrash:hover{background:#b04a2f}
  .imgwrap .imgtrash svg{width:16px;height:16px}
  .a-toast{position:fixed;right:20px;bottom:20px;z-index:300;display:flex;align-items:center;gap:11px;min-width:260px;max-width:400px;padding:13px 14px;border-radius:12px;background:#fff;box-shadow:0 14px 38px rgba(44,38,31,.24);border:1px solid #e6e1d6;font-size:14px;font-weight:600;color:#2c261f}
  .a-toast:not(.hide){animation:aToastIn .38s cubic-bezier(.2,.85,.25,1)}
  .a-toast.hide{animation:aToastOut .28s ease forwards}
  .a-toast-ic{flex:none;width:24px;height:24px;border-radius:50%;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700}
  .a-toast-ok .a-toast-ic{background:#4a7a3a}
  .a-toast-err{border-color:#e7c4bb}
  .a-toast-err .a-toast-ic{background:#b04a2f}
  .a-toast-msg{flex:1;line-height:1.4}
  .a-toast-x{flex:none;border:0;background:transparent;color:#a99f90;font-size:20px;line-height:1;cursor:pointer;padding:0 2px;opacity:0;transition:opacity .15s}
  .a-toast:hover .a-toast-x{opacity:1}
  @keyframes aToastIn{from{opacity:0;transform:translateY(20px) scale(.97)}to{opacity:1;transform:none}}
  @keyframes aToastOut{to{opacity:0;transform:translateY(12px)}}
</style>
<script>
(function(){
  // ---- flash toast: pops up bottom-right, auto-dismisses after 5s, hover reveals the ✕ ----
  (function(){
    var toast=document.getElementById('aToast'); if(!toast) return;
    var x=toast.querySelector('.a-toast-x'), t;
    function dismiss(){ toast.classList.add('hide'); setTimeout(function(){ if(toast&&toast.parentNode) toast.remove(); },280); }
    function arm(ms){ clearTimeout(t); t=setTimeout(dismiss,ms); }
    if(x) x.addEventListener('click',function(){ clearTimeout(t); dismiss(); });
    toast.addEventListener('mouseenter',function(){ clearTimeout(t); });
    toast.addEventListener('mouseleave',function(){ arm(2500); });
    arm(5000);
  })();

  // ---- visual (rich-text) editor: turns textarea[data-rich] into a formatting box ----
  document.querySelectorAll('textarea[data-rich]').forEach(function(ta){
    try{ document.execCommand('styleWithCSS',false,false); }catch(e){}
    try{ document.execCommand('defaultParagraphSeparator',false,'p'); }catch(e){}
    var wrap=document.createElement('div'); wrap.className='rt';
    var bar=document.createElement('div'); bar.className='rt-bar';
    var area=document.createElement('div'); area.className='rt-area'; area.contentEditable='true';
    area.setAttribute('data-ph','Start writing…'); area.innerHTML=ta.value||'';
    function sync(){ var h=area.innerHTML.replace(/<br>(\s*<\/(h3|p|li|ul|ol)>)/gi,'$1'); ta.value=(h==='<br>'||h==='<p><br></p>'||h==='<div><br></div>')?'':h; }
    var tools=[
      ['<b>B</b>','Bold',              function(){ document.execCommand('bold'); }],
      ['<i>I</i>','Italic',            function(){ document.execCommand('italic'); }],
      ['sep'],
      ['H','Turn line into a heading', function(){ document.execCommand('formatBlock',false,'<h3>'); }],
      ['&para;','Turn line into normal text', function(){ document.execCommand('formatBlock',false,'<p>'); }],
      ['sep'],
      ['&bull; List','Bulleted list',  function(){ document.execCommand('insertUnorderedList'); }],
      ['1. List','Numbered list',      function(){ document.execCommand('insertOrderedList'); }],
      ['sep'],
      ['&#128279; Link','Add a link (select text first)', function(){ var u=prompt('Link URL:','https://'); if(u) document.execCommand('createLink',false,u); }],
      ['&#10005; Clear','Reset the selected text to plain', function(){ document.execCommand('removeFormat'); document.execCommand('unlink'); document.execCommand('formatBlock',false,'<p>'); }]
    ];
    tools.forEach(function(t){
      var b=document.createElement('button'); b.type='button';
      if(t[0]==='sep'){ b.className='rt-b sep'; b.textContent='|'; bar.appendChild(b); return; }
      b.className='rt-b'; b.title=t[1]; b.innerHTML=t[0];
      // mousedown+preventDefault keeps the text selection so formatting always applies
      b.addEventListener('mousedown',function(ev){ ev.preventDefault(); });
      b.addEventListener('click',function(ev){ ev.preventDefault(); area.focus(); t[2](); sync(); });
      bar.appendChild(b);
    });
    area.addEventListener('input',sync); area.addEventListener('blur',sync);
    ta.style.display='none'; ta.parentNode.insertBefore(wrap,ta); wrap.appendChild(bar); wrap.appendChild(area);
    var form=ta.closest('form'); if(form) form.addEventListener('submit',sync);
    sync();
  });

  // ---- image uploads: clean picker + live preview + hover-to-remove (trash) + Undo ----
  document.querySelectorAll('input[type="file"][data-maxmb]').forEach(function(inp){
    var maxMB=parseFloat(inp.getAttribute('data-maxmb'))||10;
    var box=inp.closest('.field')||inp.parentNode;
    var urlField=box.querySelector('input.input');          // the "https://…" text field for this image
    var preview=box.querySelector('img');
    var origSrc=preview?preview.getAttribute('src'):null;    // saved image, to revert to
    var origUrl=urlField?urlField.value:'';
    var objUrl=null;

    inp.style.display='none';
    var row=document.createElement('div'); row.style.marginTop='10px';
    var pick=document.createElement('button'); pick.type='button'; pick.className='btn btn-ghost btn-sm';
    var undoBtn=document.createElement('button'); undoBtn.type='button'; undoBtn.className='btn btn-ghost btn-sm'; undoBtn.textContent='✕ Undo'; undoBtn.style.cssText='margin-left:8px;display:none';
    row.appendChild(pick); row.appendChild(undoBtn);
    var status=document.createElement('div'); status.style.cssText='margin-top:6px;font-size:12.5px;line-height:1.5';
    inp.parentNode.insertBefore(row,inp); inp.parentNode.insertBefore(status,inp);

    function decorate(img){                                  // wrap an <img> and overlay a hover trash button
      if(!img || (img.parentNode && img.parentNode.className==='imgwrap')) return;
      img.style.cssText='max-width:220px;max-height:160px;width:auto;height:auto;border-radius:10px;border:1px solid #e5e0d6;display:block';
      var w=document.createElement('div'); w.className='imgwrap';
      img.parentNode.insertBefore(w,img); w.appendChild(img);
      var tr=document.createElement('button'); tr.type='button'; tr.className='imgtrash'; tr.title='Remove image';
      tr.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"/></svg>';
      tr.addEventListener('click',function(ev){ ev.preventDefault(); removeImage(); });
      w.appendChild(tr);
    }
    function ensurePreview(){
      if(preview) return preview;
      preview=document.createElement('img');
      var anchor=(box.firstChild&&box.firstChild.tagName==='LABEL')?box.firstChild.nextSibling:box.firstChild;
      box.insertBefore(preview,anchor);
      decorate(preview);
      return preview;
    }
    function dropPreview(){ if(preview){ var w=preview.parentNode; if(w&&w.className==='imgwrap') w.remove(); else preview.remove(); preview=null; } }
    function idle(){ pick.textContent=origSrc?'Replace image':'Choose image'; undoBtn.style.display='none'; status.style.color='#8a7d6e'; status.textContent=origSrc?'Saved image — hover it to remove, or pick a file to replace it.':'No image chosen yet.'; }

    function removeImage(){                                  // same effect as clearing the URL + Save
      inp.value=''; if(urlField) urlField.value='';
      if(objUrl){ URL.revokeObjectURL(objUrl); objUrl=null; }
      dropPreview();
      pick.textContent='Choose image'; undoBtn.style.display='';
      status.style.color='#b04a2f'; status.textContent='✕ Image will be removed when you click Save.';
    }
    function revert(){                                       // restore the originally-saved image
      inp.value=''; if(urlField) urlField.value=origUrl;
      if(objUrl){ URL.revokeObjectURL(objUrl); objUrl=null; }
      dropPreview();
      if(origSrc){ ensurePreview().src=origSrc; }
      idle();
    }

    pick.addEventListener('click',function(){ inp.click(); });
    undoBtn.addEventListener('click',function(ev){ ev.preventDefault(); revert(); });
    if(preview) decorate(preview);
    idle();

    inp.addEventListener('change',function(){
      var f=inp.files&&inp.files[0]; if(!f) return;
      var mb=f.size/1048576, typeOk=/\.(jpe?g|png|webp|gif|avif)$/i.test(f.name);
      if(!typeOk){ status.style.color='#b04a2f'; status.textContent='✕ Unsupported file type — use JPG, PNG, WebP, GIF or AVIF.'; undoBtn.style.display='none'; inp.value=''; return; }
      if(mb>maxMB){ status.style.color='#b04a2f'; status.textContent='✕ That image is '+mb.toFixed(1)+' MB — the maximum is '+maxMB+' MB. Please pick a smaller one.'; undoBtn.style.display='none'; inp.value=''; return; }
      if(objUrl) URL.revokeObjectURL(objUrl);
      objUrl=URL.createObjectURL(f);
      ensurePreview().src=objUrl;
      pick.textContent='Change image'; undoBtn.style.display='';
      status.style.color='#4a7a3a'; status.textContent='✓ '+f.name+' ('+mb.toFixed(1)+' MB) — preview only. Click Save to keep it.';
    });
  });
})();

/* ---- mobile sidebar drawer: burger opens, backdrop / link / Esc closes ---- */
(function(){
  var side=document.getElementById('aSide'), bd=document.getElementById('aSideBd'), bg=document.getElementById('aBurger');
  if(!side||!bd||!bg) return;
  function open(){ side.classList.add('open'); bd.hidden=false; requestAnimationFrame(function(){bd.classList.add('show');}); bg.setAttribute('aria-expanded','true'); document.body.style.overflow='hidden'; }
  function close(){ side.classList.remove('open'); bd.classList.remove('show'); bg.setAttribute('aria-expanded','false'); document.body.style.overflow=''; setTimeout(function(){bd.hidden=true;},200); }
  bg.addEventListener('click', function(){ side.classList.contains('open')?close():open(); });
  bd.addEventListener('click', close);
  side.querySelectorAll('a').forEach(function(a){ a.addEventListener('click', close); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') close(); });
})();
</script>
</body>
</html>
<?php
}
