/* ============================================================
   WELL PHARMACY admin — "take me back to where I was"

   Going into an editor used to cost the operator their place: coming back put
   them at the top of the list (or the brand) and they had to scroll and filter
   all over again. This keeps a short trail in sessionStorage (per browser tab):

     1. RETURN SPOT   clicking from a page into an editor records that page's
                      scroll, inner-box scroll, typed filters and how many
                      infinite-scroll slices were open. Coming back to it (Back,
                      Save & back, the pill, a breadcrumb, the browser's back
                      button) puts all of that back.
     2. HIGHLIGHT     the record you edited last glows on the list, with
                      "✓ Saved" when it was saved.
     3. BACK PILL     every editor gets a "← Back to …" pill, bottom centre.
     4. PREV / NEXT   an editor opened from a list steps through that list in
                      its order and filter, fetching more rows past the end.
     6. BREADCRUMB    "Brands › CeraVe › <product>" above the editor title.

   It only reads the page (links, titles, scroll) — no PHP page needs to know
   about it. With storage blocked or JS off, everything works as before.
   ============================================================ */
(function () {
  'use strict';

  var main = document.querySelector('.a-main');
  if (!main || !window.URL || !window.URLSearchParams) return;

  var T_TRAIL = 'adminNav.trail', T_LEAVE = 'adminNav.leave', T_SAVED = 'adminNav.saved';
  function get(k) { try { return JSON.parse(sessionStorage.getItem(k)); } catch (e) { return null; } }
  function set(k, v) {
    try { if (v == null) sessionStorage.removeItem(k); else sessionStorage.setItem(k, JSON.stringify(v)); } catch (e) {}
  }

  /* ---------- pages + keys ------------------------------------------------ */
  var PAGE = document.body.getAttribute('data-page') || '';
  function isEditor(p) { return /-edit$/.test(p) || p === 'order'; }
  var EDITOR = isEditor(PAGE);
  var DIR = location.pathname.replace(/[^\/]*$/, '');

  function url(h) { try { return new URL(h, location.href); } catch (e) { return null; } }
  function pageOf(u) { return u.pathname.split('/').pop().replace(/\.php$/, ''); }
  function inAdmin(u) { return !!u && u.origin === location.origin && u.pathname.replace(/[^\/]*$/, '') === DIR; }
  /* one spelling per record/list: ignores paging, the ret= context, empty params and order */
  function keyOf(h) {
    var u = url(h); if (!u) return '';
    var pairs = [];
    new URLSearchParams(u.search).forEach(function (v, k) {
      if (v !== '' && k !== 'page' && k !== 'partial' && k !== 'ret') pairs.push([k, v]);
    });
    pairs.sort(function (a, b) { return a[0] < b[0] ? -1 : a[0] > b[0] ? 1 : (a[1] < b[1] ? -1 : 1); });
    return pageOf(u) + (pairs.length ? '?' + pairs.map(function (p) {
      return encodeURIComponent(p[0]) + '=' + encodeURIComponent(p[1]);
    }).join('&') : '');
  }
  var HERE = keyOf(location.href);
  var HERE_URL = location.pathname.split('/').pop() + location.search;

  function txt(sel) { var el = document.querySelector(sel); return el ? el.textContent.replace(/\s+/g, ' ').trim() : ''; }
  var H1 = txt('.a-top-title h1'), SUB = txt('.a-top-title .sub');

  /* what this page is called in a breadcrumb / on the pill */
  function hereLabel() {
    /* the record's name (the subtitle); a subtitle that is a sentence describes the page instead */
    if (EDITOR) return (/^(Edit|Add) /.test(H1) && SUB && !/\.\s*$/.test(SUB)) ? SUB : H1;
    var bits = [];
    new URLSearchParams(location.search).forEach(function (v, k) {
      if (v && ['page', 'partial', 'ret', 'sort', 'id', 'seed'].indexOf(k) < 0) bits.push(k === 'q' ? '“' + v + '”' : v);
    });
    return H1 + (bits.length ? ' · ' + bits.join(', ') : '');
  }
  function short(s) { return s.length > 42 ? s.slice(0, 40).trim() + '…' : s; }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  /* the record links of one editor page, in page order (prev/next follows this) */
  function seqFrom(root, target) {
    var seen = {}, list = [];
    root.querySelectorAll('a[href]').forEach(function (a) {
      if (a.closest('[data-nav-ui]')) return;
      var u = url(a.getAttribute('href'));
      if (!inAdmin(u) || pageOf(u) !== target || !u.searchParams.get('id')) return;
      var k = keyOf(u.href), name = a.textContent.replace(/\s+/g, ' ').trim();
      if (/^\W*(edit|open|view)\W*$/i.test(name)) name = '';
      if (seen[k] !== undefined) { if (name && !list[seen[k]].n) list[seen[k]].n = name; return; }
      seen[k] = list.length;
      list.push({ k: k, h: a.getAttribute('href').replace(/#.*$/, ''), n: name });
    });
    return list;
  }
  function moreUrl() { var m = document.querySelector('[data-list-more]'); return m ? m.getAttribute('data-next') : null; }
  function totalHere() {   /* "2,522 products" under a list title */
    var m = /^([\d,]+)\s/.exec(SUB); return m ? parseInt(m[1].replace(/,/g, ''), 10) : null;
  }
  function rowsHere() { var tb = main.querySelector('.a-table tbody'); return tb ? tb.children.length : 0; }

  /* everything needed to put this page back the way it is now */
  function snapshot(to) {
    var inner = {}, inputs = {};
    main.querySelectorAll('[id]').forEach(function (el) { if (el.scrollTop > 0) inner[el.id] = el.scrollTop; });
    main.querySelectorAll('input[type="search"][id]').forEach(function (el) { if (el.value) inputs[el.id] = el.value; });
    return {
      url: HERE_URL, key: HERE, page: PAGE, label: hereLabel(), to: to,
      y: Math.round(window.scrollY), inner: inner, inputs: inputs, rows: rowsHere(),
      seq: seqFrom(main, to), more: EDITOR ? null : moreUrl(), total: EDITOR ? null : totalHere()
    };
  }

  /* ---------- unsaved-changes guard (editors) ----------------------------- */
  var dirty = false;
  function markDirty(e) {
    var t = e.target;
    if (!t || !t.closest || !t.closest('form[method="post"],form[method="POST"]')) return;
    if (t.type === 'search' || t.hasAttribute('data-no-dirty')) return;
    dirty = true;
  }
  if (EDITOR) {
    main.addEventListener('input', markDirty, true);
    main.addEventListener('change', markDirty, true);
  }

  /* ---------- leaving a page ---------------------------------------------- */
  function noteLeave(via, saved) {
    if (!EDITOR) return;
    set(T_LEAVE, { item: HERE, via: via, saved: !!saved || get(T_SAVED) === HERE, t: Date.now() });
  }

  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a || e.defaultPrevented) return;
    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || (a.target && a.target !== '_self')) return;
    var u = url(a.getAttribute('href'));
    if (!inAdmin(u)) return;

    if (EDITOR && dirty && a.hasAttribute('data-leave') &&
        !confirm('You have unsaved changes here. Leave without saving them?')) { e.preventDefault(); return; }

    var to = pageOf(u), key = keyOf(u.href);
    if (key === HERE) return;                                   // same record (a tab / anchor link)

    var trail = get(T_TRAIL) || [], item = null;
    /* going to a page that is already on the trail is going BACK up it, never deeper —
       whatever the link (crumb, pill, Back button, a plain link to the brand): drop what's
       above that page, so arriving there pops it and its own "Back to …" is the step before */
    var ci = -1;
    for (var t = trail.length - 1; t >= 0; t--) if (trail[t].key === key) { ci = t; break; }
    if (ci >= 0) {
      item = trail[ci + 1] ? trail[ci + 1].key : null;          // light up the record you were inside
      trail = trail.slice(0, ci + 1);
    } else if (isEditor(to)) {
      if (!EDITOR) trail = [snapshot(to)];                      // list → editor: a fresh trail
      else if (to !== PAGE) trail = trail.concat([snapshot(to)]).slice(-6);   // editor → another kind of editor
      /* editor → same kind of editor (prev / next): the trail stays */
    }
    set(T_TRAIL, trail);
    noteLeave(ci >= 0 || a.hasAttribute('data-nav-back') ? 'back' : 'link');
    if (item && item !== HERE) { var l = get(T_LEAVE); if (l) { l.item = item; l.saved = false; set(T_LEAVE, l); } }
  });

  if (EDITOR) {
    main.addEventListener('submit', function (e) {
      if (e.target.matches && !e.target.matches('form[method="post"],form[method="POST"]')) return;
      var sb = e.submitter;
      dirty = false;
      noteLeave(sb && sb.name === 'after' && sb.value === 'list' ? 'back' : 'submit', sb && sb.value === 'list');
    });
    /* the browser's own back button, the sidebar, closing… */
    window.addEventListener('pagehide', function () {
      if (!get(T_LEAVE)) noteLeave('other');   // a click / submit on this page already said how we left
    });
    if (document.querySelector('.a-toast-ok')) set(T_SAVED, HERE);   // a save just landed on this record
  }

  /* ---------- arriving: restore the spot + highlight ---------------------- */
  function findItem(key) {
    if (!key) return null;
    var hit = null;
    main.querySelectorAll('a[href]').forEach(function (a) {
      if (hit || a.closest('[data-nav-ui],.page-actions')) return;
      if (keyOf(a.getAttribute('href')) === key) hit = a;
    });
    return hit ? (hit.closest('tr,.bp-item,li') || hit) : null;
  }
  function ensureVisible(el) {
    var r = el.getBoundingClientRect(), vh = window.innerHeight || 0;
    var box = el.closest('.bp-list,[data-scroll-box]');
    var off = false;
    if (box) { var b = box.getBoundingClientRect(); off = r.top < b.top || r.bottom > b.bottom; }
    if (off || r.top < 80 || r.bottom > vh - 90) el.scrollIntoView({ block: 'center' });
  }
  function highlight(el, saved) {
    el.classList.remove('a-nav-hl'); void el.offsetWidth; el.classList.add('a-nav-hl');
    setTimeout(function () { el.classList.remove('a-nav-hl'); }, 3600);
    if (!saved) return;
    var chip = document.createElement('span');
    chip.className = 'a-nav-saved'; chip.setAttribute('data-nav-ui', ''); chip.textContent = '✓ Saved';
    var spot = el.matches('tr') ? (el.querySelector('td a[href]') || el.querySelector('td')) : (el.querySelector('.bp-name') || el);
    if (spot.matches('a,.bp-name')) spot.insertAdjacentElement('afterend', chip); else spot.appendChild(chip);
    setTimeout(function () { chip.classList.add('out'); setTimeout(function () { chip.remove(); }, 400); }, 5000);
  }

  function restore(spot, item, saved, persisted) {
    Object.keys(spot.inputs || {}).forEach(function (id) {
      var el = document.getElementById(id);
      if (el && el.value !== spot.inputs[id]) { el.value = spot.inputs[id]; el.dispatchEvent(new Event('input', { bubbles: true })); }
    });
    /* rows to have on the page: the slices that were open, or further when prev / next walked
       past them (the record's place in the list is known from the trail; a record that isn't
       in it — deleted, filtered out — never makes this load extra) */
    var pos = -1;
    (spot.seq || []).forEach(function (s, i) { if (s.k === item) pos = i; });
    var want = Math.max(spot.rows || 0, pos + 1), tries = 0;
    function step() {
      if (!window.adminListMore || tries++ > 150 || rowsHere() >= want) return Promise.resolve();
      return window.adminListMore().then(function (added) { if (added) return step(); });
    }
    window.adminListBusy = true;
    (persisted ? Promise.resolve() : step()).then(function () {
      window.adminListBusy = false;
      if (!persisted) {
        Object.keys(spot.inner || {}).forEach(function (id) { var el = document.getElementById(id); if (el) el.scrollTop = spot.inner[id]; });
        window.scrollTo(0, spot.y || 0);
      }
      var el = findItem(item);
      if (el) { ensureVisible(el); highlight(el, saved); }
      set(T_SAVED, null);
    });
  }

  function arrive(persisted) {
    var leave = get(T_LEAVE); set(T_LEAVE, null);
    if (leave && Date.now() - leave.t > 6 * 3600 * 1000) leave = null;
    var trail = get(T_TRAIL) || [];
    var last = trail[trail.length - 1];
    if (!last || !leave) return;
    if (last.key === HERE) {
      trail.pop(); set(T_TRAIL, trail);
      try { if (!persisted) history.scrollRestoration = 'manual'; } catch (e) {}
      restore(last, leave.item, leave.saved, persisted);
    } else if (leave.via === 'back' && last.page === PAGE && !location.search && last.url.indexOf('?') > 0) {
      /* an editor whose Save & back drops the list's search/filter: go to the filtered list instead */
      set(T_LEAVE, leave);
      location.replace(last.url);
    }
  }
  arrive(false);
  window.addEventListener('pageshow', function (e) { if (e.persisted) arrive(true); });

  /* ---------- editor chrome: back pill, breadcrumb, prev / next ----------- */
  if (!EDITOR) return;

  var trail = get(T_TRAIL) || [];
  var last = trail[trail.length - 1];
  var valid = !!(last && last.to === PAGE);
  var side = document.querySelector('.a-nav a.on');
  var sideLabel = side ? side.textContent.replace(/\s+/g, ' ').trim() : '';

  var backLink = null;
  main.querySelectorAll('.page-actions a').forEach(function (a) {
    if (!backLink && /^\s*←/.test(a.textContent)) backLink = a;
  });

  var backHref, backLabel;
  if (valid) { backHref = last.url; backLabel = last.label; }
  else if (backLink) { backHref = backLink.getAttribute('href'); backLabel = sideLabel || 'list'; }
  else if (side) { backHref = side.getAttribute('href'); backLabel = sideLabel; }

  if (backLink) {
    backLink.setAttribute('data-nav-back', ''); backLink.setAttribute('data-leave', '');
    if (valid) { backLink.setAttribute('href', backHref); backLink.textContent = '← Back to ' + short(backLabel); }
  }

  if (backHref) {
    var pill = document.createElement('a');
    pill.className = 'a-nav-pill'; pill.href = backHref;
    pill.setAttribute('data-nav-ui', ''); pill.setAttribute('data-nav-back', ''); pill.setAttribute('data-leave', '');
    pill.innerHTML = '<span aria-hidden="true">←</span><span class="t">Back to <b>' + esc(short(backLabel)) + '</b></span>';
    document.body.appendChild(pill);
    document.body.classList.add('has-nav-pill');
  }

  /* breadcrumb */
  var title = document.querySelector('.a-top-title');
  if (title) {
    var parts = [];
    if (valid) trail.forEach(function (t, i) { parts.push('<a href="' + esc(t.url) + '" data-crumb="' + i + '" data-leave>' + esc(short(t.label)) + '</a>'); });
    else if (side) parts.push('<a href="' + esc(side.getAttribute('href')) + '" data-leave>' + esc(sideLabel) + '</a>');
    if (parts.length) {
      parts.push('<span aria-current="page">' + esc(short(hereLabel())) + '</span>');
      var nav = document.createElement('nav');
      nav.className = 'a-crumbs'; nav.setAttribute('aria-label', 'Breadcrumb'); nav.setAttribute('data-nav-ui', '');
      nav.innerHTML = parts.join('<span class="sep" aria-hidden="true">›</span>');
      title.insertBefore(nav, title.firstChild);
    }
  }

  /* previous / next through the list this editor was opened from */
  if (!valid || !last.seq || !last.seq.length) return;
  var seq = last.seq, idx = -1;
  for (var i = 0; i < seq.length; i++) if (seq[i].k === HERE) { idx = i; break; }
  var actions = main.querySelector('.page-actions');
  if (idx < 0 || !actions) return;

  var bar = document.createElement('div');
  bar.className = 'a-pn'; bar.setAttribute('data-nav-ui', '');
  function total() { return last.more ? (last.total && last.total > seq.length ? last.total : seq.length + '+') : seq.length; }
  function btn(rel, item) {
    var lbl = rel === 'prev' ? '‹ Previous' : 'Next ›';
    if (!item) return '<span class="a-pn-b off" aria-disabled="true">' + lbl + '</span>';
    return '<a class="a-pn-b" data-leave rel="' + rel + '" href="' + esc(item.h) + '" title="' + esc(item.n || '') + '">' + lbl + '</a>';
  }
  var nextItem = seq[idx + 1] || null;
  bar.innerHTML = btn('prev', seq[idx - 1] || null)
    + '<span class="a-pn-n">' + (idx + 1) + ' of ' + total() + '</span>'
    + (nextItem || !last.more ? btn('next', nextItem)
       : '<a class="a-pn-b" data-leave data-pn-more rel="next" href="#">Next ›</a>');
  if (backLink && backLink.parentNode === actions) backLink.insertAdjacentElement('afterend', bar);
  else actions.insertBefore(bar, actions.firstChild);

  /* past the last loaded row: fetch the list's next slice, extend the trail, go on */
  var moreBtn = bar.querySelector('[data-pn-more]');
  if (moreBtn) moreBtn.addEventListener('click', function (e) {
    e.preventDefault();
    if (dirty && !confirm('You have unsaved changes here. Leave without saving them?')) return;
    if (moreBtn.classList.contains('off')) return;
    moreBtn.classList.add('off'); moreBtn.textContent = 'Loading…';
    fetch(last.more, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.ok ? r.text() : ''; })
      .then(function (html) {
        var doc = new DOMParser().parseFromString('<table><tbody>' + html + '</tbody></table>', 'text/html');
        var add = seqFrom(doc.body, PAGE).filter(function (s) { return !seq.some(function (x) { return x.k === s.k; }); });
        last.seq = seq.concat(add);
        last.more = add.length ? last.more.replace(/([?&]page=)(\d+)/, function (_, p, n) { return p + (parseInt(n, 10) + 1); }) : null;
        trail[trail.length - 1] = last; set(T_TRAIL, trail);
        if (add.length) { dirty = false; noteLeave('link'); location.href = add[0].h; }
        else { moreBtn.textContent = 'Next ›'; }
      })
      .catch(function () { moreBtn.classList.remove('off'); moreBtn.textContent = 'Next ›'; });
  });
})();
