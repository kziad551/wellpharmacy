<?php /* shared footer + script bootstrap — expects optional: $ACTIVE, $USE_PLP, $PAGE_JS, $NO_POPUP */ ?>
<div id="chrome-foot"></div>

<?php if (empty($NO_POPUP)): ?>
<!-- first-visit newsletter popup -->
<div class="nl-pop" id="nlPop" aria-hidden="true">
  <div class="nl-back" data-nl-close></div>
  <div class="nl-card" role="dialog" aria-label="Newsletter signup">
    <button class="nl-x" data-nl-close aria-label="Close">&times;</button>
    <div class="nl-art" aria-hidden="true"><span>✦</span></div>
    <div class="nl-body">
      <span class="nl-ey">the well community</span>
      <h3 class="nl-h">Get <span>10% off</span> your first order</h3>
      <p class="nl-sub">Join for pharmacist tips, new drops &amp; members-only deals. No spam — just glow. ♡</p>
      <form id="nlForm" novalidate>
        <input type="email" id="nlEmail" placeholder="Your email address" aria-label="Email" required>
        <button class="btn btn-primary" type="submit">Get my 10% off</button>
      </form>
      <button class="nl-no" data-nl-close>No thanks, I'll pay full price</button>
      <div class="nl-done" id="nlDone" hidden>
        <div class="nl-check">✓</div>
        <b>You're in!</b>
        <p>Use code <span class="nl-code">WELL10</span> at checkout for 10% off.</p>
      </div>
    </div>
  </div>
</div>
<style>
  .nl-pop{position:fixed;inset:0;z-index:120;display:none;align-items:center;justify-content:center;padding:20px}
  .nl-pop.open{display:flex}
  .nl-back{position:absolute;inset:0;background:rgba(44,38,31,.55);backdrop-filter:blur(3px);animation:nlFade .3s ease}
  .nl-card{position:relative;display:grid;grid-template-columns:200px 1fr;max-width:660px;width:100%;background:var(--cream,#EBE8DF);border-radius:24px;overflow:hidden;box-shadow:0 40px 90px rgba(44,38,31,.4);animation:nlUp .35s cubic-bezier(.2,.8,.2,1)}
  .nl-art{background:linear-gradient(160deg,var(--rose,#9C8158),var(--rose-deep,#7A6244));display:flex;align-items:center;justify-content:center;color:#fff;font-size:60px}
  .nl-body{padding:34px 32px;position:relative}
  .nl-ey{font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--rose-deep,#7A6244)}
  .nl-h{font-family:var(--fp,'Clash Display',sans-serif);font-size:30px;font-weight:600;text-transform:lowercase;line-height:1.05;margin:8px 0 10px;color:var(--ink,#2C261F)}
  .nl-h span{color:var(--coral-deep,#7E5730)}
  .nl-sub{font-size:14px;color:var(--ink-soft,#4B3F35);line-height:1.55;margin:0 0 18px;max-width:42ch}
  #nlForm{display:flex;flex-direction:column;gap:10px}
  #nlEmail{height:50px;border-radius:12px;border:1px solid var(--border-2,#E4DFD3);padding:0 16px;font:inherit;font-size:15px;background:#fff}
  #nlEmail:focus{outline:none;border-color:var(--rose,#9C8158);box-shadow:0 0 0 4px rgba(156,129,88,.2)}
  #nlForm .btn{height:50px}
  .nl-no{display:block;width:100%;margin-top:12px;background:none;border:0;color:var(--text-muted,#8A7D6E);font:inherit;font-size:12.5px;text-decoration:underline;cursor:pointer}
  .nl-x{position:absolute;top:14px;right:16px;z-index:2;width:34px;height:34px;border:0;background:rgba(255,255,255,.6);border-radius:50%;font-size:22px;line-height:1;color:var(--ink,#2C261F);cursor:pointer}
  .nl-done{position:absolute;inset:0;background:var(--cream,#EBE8DF);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:30px;gap:6px}
  .nl-done[hidden]{display:none}
  .nl-done .nl-check{width:54px;height:54px;border-radius:50%;background:var(--mint,#7D7A5E);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;margin-bottom:8px}
  .nl-done b{font-family:var(--fp,sans-serif);font-size:22px}
  .nl-code{font-weight:700;color:var(--coral-deep,#7E5730);letter-spacing:.5px}
  @keyframes nlFade{from{opacity:0}to{opacity:1}}
  @keyframes nlUp{from{opacity:0;transform:translateY(20px) scale(.98)}to{opacity:1;transform:none}}
  @media(max-width:560px){.nl-card{grid-template-columns:1fr}.nl-art{display:none}}
</style>
<script>
(function(){
  var KEY='well_nl_v1', pop=document.getElementById('nlPop');
  if(!pop) return;
  function close(){ pop.classList.remove('open'); try{localStorage.setItem(KEY,'1')}catch(e){} }
  if(!(function(){try{return localStorage.getItem(KEY)}catch(e){return 1}})()){
    setTimeout(function(){ pop.classList.add('open'); }, 2200);
  }
  pop.querySelectorAll('[data-nl-close]').forEach(function(b){ b.addEventListener('click', close); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape' && pop.classList.contains('open')) close(); });
  var form=document.getElementById('nlForm');
  form && form.addEventListener('submit', function(e){
    e.preventDefault();
    var email=document.getElementById('nlEmail').value.trim();
    if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)){ document.getElementById('nlEmail').focus(); return; }
    var fd=new FormData(); fd.append('email',email); fd.append('csrf','<?= e(csrf_token()) ?>');
    fetch('actions/subscribe.php',{method:'POST',body:fd}).then(function(r){return r.json()}).catch(function(){return{ok:true}}).then(function(res){
      document.getElementById('nlDone').hidden=false;
      try{localStorage.setItem(KEY,'1')}catch(e){}
    });
  });
})();
</script>
<?php endif; ?>

<script src="<?= asset('assets/data.php') ?>"></script>
<script src="<?= asset('assets/chrome.js') ?>"></script>
<?php if (!empty($USE_PLP)): ?><script src="<?= asset('assets/plp.js') ?>"></script><?php endif; ?>
<script>WELL.mountChrome({ active: <?= json_encode($ACTIVE ?? 'Shop All') ?> });</script>
<?= $PAGE_JS ?? '' ?>
</body>
</html>
