/* ============================================================
   PLP / Search listing controller (shared)
   WELL.initPLP({ products, gridEl, countEls, ... })
   ============================================================ */
(function (W) {
  'use strict';
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => [...(r || document).querySelectorAll(s)];

  W.initPLP = function (cfg) {
    const all = cfg.products.slice();
    const grid = cfg.gridEl;
    const state = { concern:new Set(), brand:new Set(), skin:new Set(), ingr:new Set(), rating:0, sale:false, max:50, sort:'rec', view:'grid', sub:'All' };
    const chipsEl = cfg.chipsEl, countEls = cfg.countEls || [];

    function passes(p) {
      if (state.brand.size && !state.brand.has(p.brand)) return false;
      if (state.sale && !p.was) return false;
      if (state.rating && p.rating < state.rating) return false;
      if (p.price > state.max) return false;
      if (state.sub !== 'All' && cfg.subMatch && !cfg.subMatch(p, state.sub)) return false;
      return true;
    }
    function sortList(list) {
      const s = state.sort;
      const c = [...list];
      if (s === 'price-asc') c.sort((a,b)=>a.price-b.price);
      else if (s === 'price-desc') c.sort((a,b)=>b.price-a.price);
      else if (s === 'rating') c.sort((a,b)=>b.rating-a.rating);
      else if (s === 'reviews') c.sort((a,b)=>b.reviews-a.reviews);
      else if (s === 'discount') c.sort((a,b)=>(b.sale||0)-(a.sale||0));
      return c;
    }
    function render(skeleton) {
      const list = sortList(all.filter(passes));
      countEls.forEach(e => e.textContent = list.length);
      if (skeleton) {
        grid.classList.remove('listview');
        grid.innerHTML = Array.from({length:8}).map(()=>'<div class="skeleton"></div>').join('');
        setTimeout(() => paint(list), 280);
      } else paint(list);
      renderChips();
    }
    function paint(list) {
      grid.classList.toggle('listview', state.view === 'list');
      if (!list.length) {
        grid.innerHTML = `<div class="plp-empty"><span class="ic">${W.icon('dropper')}</span><h3 class="h3">No matches — try fewer filters</h3><p class="muted">Clear a filter or two and we'll find your glow.</p><button class="btn btn-ghost" data-clear-all>Clear all filters</button></div>`;
        return;
      }
      let html = list.map((p,i) => {
        let card = W.productCard(p);
        return card;
      }).join('');
      // inline banner after 8 cards
      if (cfg.banner && list.length > 8) {
        const cards = list.map(p => W.productCard(p));
        cards.splice(8, 0, cfg.banner);
        html = cards.join('');
      }
      grid.innerHTML = html;
      W.guardImages(grid);
    }
    function renderChips() {
      if (!chipsEl) return;
      const chips = [];
      state.concern.forEach(c => chips.push(['concern', c, 'Concern: ' + c]));
      state.brand.forEach(b => chips.push(['brand', b, 'Brand: ' + b]));
      state.skin.forEach(s => chips.push(['skin', s, s]));
      state.ingr.forEach(s => chips.push(['ingr', s, s]));
      if (state.sale) chips.push(['sale', '1', 'On Sale']);
      if (state.rating) chips.push(['rating', '1', '★ ' + state.rating + ' & up']);
      if (state.max < 50) chips.push(['max', '1', 'Under $' + state.max]);
      chipsEl.innerHTML = chips.map(([k,v,label]) => `<span class="chip-rm">${label}<button data-rmchip="${k}" data-val="${v}" aria-label="Remove">✕</button></span>`).join('')
        + (chips.length ? `<button class="btn btn-ghost btn-sm" data-clear-all style="height:32px">Clear all</button>` : '');
    }

    // wire filter inputs
    $$('[data-f]', cfg.filtersEl).forEach(inp => {
      inp.addEventListener('change', () => {
        const f = inp.dataset.f, v = inp.dataset.val;
        if (f === 'sale') state.sale = inp.checked;
        else if (f === 'rating') state.rating = inp.checked ? Number(v) : 0;
        else if (state[f] && state[f].add) { inp.checked ? state[f].add(v) : state[f].delete(v); }
        render(true);
      });
    });
    // price slider
    const slider = $('[data-price]', cfg.filtersEl);
    if (slider) slider.addEventListener('input', () => { state.max = Number(slider.value); $('[data-price-val]').textContent = '$' + state.max; render(false); });
    // brand search
    const bsearch = $('[data-brand-search]', cfg.filtersEl);
    if (bsearch) bsearch.addEventListener('input', () => { const q = bsearch.value.toLowerCase(); $$('[data-brand-row]', cfg.filtersEl).forEach(r => r.style.display = r.dataset.brandRow.toLowerCase().includes(q) ? '' : 'none'); });

    // subcat pills
    if (cfg.subEl) $$('[data-sub]', cfg.subEl).forEach(b => b.addEventListener('click', () => {
      $$('[data-sub]', cfg.subEl).forEach(x => x.classList.remove('chip-active'));
      b.classList.add('chip-active'); state.sub = b.dataset.sub; render(true);
    }));

    // sort + view
    if (cfg.sortEl) cfg.sortEl.addEventListener('change', () => { state.sort = cfg.sortEl.value; render(false); });
    $$('[data-view]', document).forEach(b => b.addEventListener('click', () => {
      $$('[data-view]').forEach(x => x.classList.toggle('on', x === b)); state.view = b.dataset.view; render(false);
    }));

    // chip remove + clear all (delegated)
    document.addEventListener('click', e => {
      const rc = e.target.closest('[data-rmchip]');
      if (rc) {
        const k = rc.dataset.rmchip, v = rc.dataset.val;
        if (k === 'sale') state.sale = false;
        else if (k === 'rating') state.rating = 0;
        else if (k === 'max') state.max = 50;
        else if (state[k] && state[k].delete) state[k].delete(v);
        syncInputs(); render(true);
      }
      if (e.target.closest('[data-clear-all]')) {
        state.concern.clear(); state.brand.clear(); state.skin.clear(); state.ingr.clear();
        state.rating = 0; state.sale = false; state.max = 50;
        syncInputs(); render(true);
      }
    });
    function syncInputs() {
      $$('[data-f]', cfg.filtersEl).forEach(inp => {
        const f = inp.dataset.f, v = inp.dataset.val;
        if (f === 'sale') inp.checked = state.sale;
        else if (f === 'rating') inp.checked = state.rating === Number(v);
        else if (state[f] && state[f].has) inp.checked = state[f].has(v);
      });
      if (slider) { slider.value = state.max; const pv = $('[data-price-val]'); if (pv) pv.textContent = '$' + state.max; }
    }

    // seed initial filters from cfg
    if (cfg.seed) { cfg.seed.forEach(([k,v]) => { if (k==='sale') state.sale=true; else if (state[k]&&state[k].add) state[k].add(v); }); syncInputs(); }

    render(false);
    return { render, state };
  };
})(window.WELL = window.WELL || {});
