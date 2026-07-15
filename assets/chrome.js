/* ============================================================
   THE WELL SHOP — global chrome + interactivity
   mountChrome(), cart/wishlist state, product cards, toasts
   ============================================================ */
(function (W) {
  'use strict';
  const $ = (s, r) => (r || document).querySelector(s);
  const money = n => '$' + (Math.round(n * 100) / 100).toFixed(2);
  W.money = money;

  /* ---------- icons (Lucide-style, 1.6 stroke) ---------- */
  const I = {
    search:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
    heart:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.5-1.5 3-3.2 3-5.5A4.5 4.5 0 0 0 12 5.5 4.5 4.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7Z"/></svg>',
    bag:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 4 6v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6l-2-4z"/><path d="M4 6h16"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>',
    user:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>',
    cross:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
    close:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>',
    menu:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>',
    chevron:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>',
    check:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
    truck:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
    chat:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-12 7.7L3 21l1.8-6A8.5 8.5 0 1 1 21 11.5Z"/></svg>',
    shield:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5 3.5 8.5 8 11 4.5-2.5 8-6 8-11V5z"/><path d="m9 12 2 2 4-4"/></svg>',
    sparkle:'<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8z"/></svg>',
    dropper:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M19 3l2 2-3 3 1 1-7 7H9v-3l7-7 1 1z"/><path d="M9 13l-5 5a2 2 0 0 0 3 3l5-5"/></svg>',
    rotate:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/></svg>',
    star:'<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 6.5 7 .9-5 4.8 1.3 7L12 18l-6.3 3.2L7 14.2 2 9.4l7-.9z"/></svg>',
    appstore:'<svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.5 1.6c.1 1-.3 2-1 2.8-.7.8-1.8 1.4-2.8 1.3-.1-1 .4-2 1-2.7.7-.8 1.9-1.4 2.8-1.4ZM19 17.3c-.5 1.1-.7 1.6-1.3 2.6-.9 1.4-2.1 3.1-3.6 3.1-1.3 0-1.7-.9-3.5-.8-1.8 0-2.2.8-3.5.8-1.5 0-2.7-1.5-3.6-2.9C1 16.4.7 11.7 2.3 9.2 3.4 7.5 5.2 6.4 6.9 6.4c1.7 0 2.8 1 4.2 1 1.4 0 2.2-1 4.2-1 1.5 0 3.1.8 4.2 2.3-3.7 2-3.1 7.3-.5 8.6Z"/></svg>',
    play:'<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 2.5 20 12 3 21.5z"/></svg>',
    phone:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/></svg>',
    mail:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
    whatsapp:'<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.29.173-1.414-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.002-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>',
    ig:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>',
    tiktok:'<svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 3c.3 2.3 1.7 3.9 4 4.1v2.8c-1.4.1-2.7-.3-4-1v6.1c0 3.4-2.5 5.9-5.8 5.9A5.7 5.7 0 0 1 4.5 15c0-3.3 2.9-5.9 6.5-5.2v3a2.8 2.8 0 0 0-3.5 2.6c0 1.5 1.2 2.6 2.7 2.6 1.6 0 2.8-1.2 2.8-3V3z"/></svg>',
    fb:'<svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 9V7c0-1 .5-1.5 1.7-1.5H17V2.2C16.5 2.1 15.4 2 14.3 2 11.6 2 10 3.6 10 6.5V9H7.5v3.5H10V22h4v-9.5h2.7l.4-3.5z"/></svg>',
    yt:'<svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 8s-.2-1.5-.8-2.1c-.8-.8-1.6-.8-2-.9C16.4 4.7 12 4.7 12 4.7s-4.4 0-7.2.3c-.4.1-1.2.1-2 .9C2.2 6.5 2 8 2 8s-.2 1.7-.2 3.5v1c0 1.8.2 3.5.2 3.5s.2 1.5.8 2.1c.8.8 1.8.8 2.3.9 1.7.2 6.9.3 6.9.3s4.4 0 7.2-.3c.4-.1 1.2-.1 2-.9.6-.6.8-2.1.8-2.1s.2-1.7.2-3.5v-1C22.2 9.7 22 8 22 8zM10 14.6V9.4l4.7 2.6z"/></svg>',
    pin:'<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a9 9 0 0 0-3.3 17.4c-.1-.7-.2-1.9 0-2.7l1.1-4.6s-.3-.6-.3-1.4c0-1.3.8-2.3 1.7-2.3.8 0 1.2.6 1.2 1.3 0 .8-.5 2-.8 3.1-.2.9.5 1.7 1.4 1.7 1.7 0 2.9-2.2 2.9-4.7 0-1.9-1.3-3.4-3.7-3.4a4.3 4.3 0 0 0-4.5 4.3c0 .8.3 1.4.7 1.8.2.2.2.3.1.5l-.2.9c0 .3-.2.4-.5.2-1.2-.5-1.8-1.9-1.8-3.5 0-2.6 2.2-5.7 6.5-5.7 3.5 0 5.8 2.5 5.8 5.2 0 3.5-2 6.2-4.9 6.2-1 0-1.9-.5-2.2-1.1l-.6 2.4c-.2.8-.7 1.6-1 2.2A9 9 0 1 0 12 2Z"/></svg>',
  };
  W.icon = (n) => { const s = I[n] || ''; return s.replace('<svg ', '<svg width="1em" height="1em" '); };

  /* ---------- image fallback ---------- */
  const BLANK = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
  function guardImg(img) {
    function fail() {
      if (img.dataset.failed) return;
      img.dataset.failed = '1';
      const wrap = img.closest('.media,.ph .in,.mega-feat,[data-imgwrap]');
      if (wrap && wrap !== img) wrap.classList.add('imgfallback');
      else img.classList.add('imgfallback');
      img.alt = ''; img.src = BLANK;   // kill broken-icon + alt text; gradient shows through
    }
    img.addEventListener('error', fail, { once:true });
    if (img.getAttribute('src') && img.complete && img.naturalWidth === 0) fail();   // already-failed (cached 404)
  }
  W.guardImages = (root) => (root || document).querySelectorAll('img[data-grade],img.gimg').forEach(guardImg);

  /* ---------- state ---------- */
  const LS = { cart:'well_cart_v1', wish:'well_wish_v1' };
  const read = (k, d) => { try { return JSON.parse(localStorage.getItem(k)) || d; } catch (e) { return d; } };
  const write = (k, v) => localStorage.setItem(k, JSON.stringify(v));
  /* just signed out → the bag/favourites belonged to that ACCOUNT, not this
     device. Drop them before reading, so the next visitor (or the next account
     to sign in here) never inherits someone else's saved items. */
  if (W.FLUSH_LOCAL) { try { localStorage.removeItem(LS.cart); localStorage.removeItem(LS.wish); } catch (e) {} }

  let CART = read(LS.cart, []);
  if (!Array.isArray(CART)) CART = [];
  let WISH = read(LS.wish, []);
  if (!Array.isArray(WISH)) WISH = [];

  /* Signed in? The ACCOUNT is the source of truth — seed from it and mirror every
     change back to the DB, so a shopper's bag/favourites follow them to any device.
     Guests keep everything in localStorage on this device only. */
  const ME = W.USER || null;
  function post(action, body) {
    return fetch('actions/account.php?do=' + action, {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body || {}), credentials: 'same-origin'
    }).catch(function () { /* offline — localStorage still holds it */ });
  }
  if (ME) {
    // merge anything built as a guest, then adopt the account's saved state
    const guestCart = CART.slice(), guestWish = WISH.slice();
    const byId = {};
    (ME.cart || []).forEach(function (r) { byId[r.product_id] = (r.qty | 0) || 1; });
    guestCart.forEach(function (l) { byId[l.id] = Math.max(byId[l.id] || 0, l.qty | 0); });
    CART = Object.keys(byId).map(function (id) { return { id: id, qty: byId[id] }; });
    WISH = (ME.wish || []).slice();
    guestWish.forEach(function (id) { if (WISH.indexOf(id) < 0) WISH.push(id); });
    write(LS.cart, CART); write(LS.wish, WISH);
    if (guestCart.length || guestWish.length) {          // push the merged result up once
      post('cart', { cart: CART });
      guestWish.forEach(function (id) { post('wish', { id: id, on: true }); });
    }
  } else if ((CART.length || WISH.length) && /\/(login|register|verify)\b/.test(location.pathname)) {
    // guest is about to sign in — stash what they built so it merges into the account
    post('handoff', { cart: CART, wish: WISH });
  }

  const stockOf = (id) => { const p = W.BY_ID[id]; return p ? (p.stock | 0) : 0; };

  // keep the saved bag honest against live stock (product removed / out of stock / qty too high)
  function reconcileCart() {
    let changed = false;
    CART = CART.filter(function (l) {
      const p = W.BY_ID[l.id];
      if (!p) { changed = true; return false; }        // product gone or set to draft
      const stock = p.stock | 0;
      if (stock <= 0) { changed = true; return false; } // out of stock → drop
      if (l.qty > stock) { l.qty = stock; changed = true; }
      if (l.qty < 1) { l.qty = 1; changed = true; }
      return true;
    });
    if (changed) write(LS.cart, CART);
  }
  reconcileCart();

  const cartCount = () => CART.reduce((n, l) => n + l.qty, 0);
  const cartSubtotal = () => CART.reduce((s, l) => s + (W.BY_ID[l.id] ? W.BY_ID[l.id].price * l.qty : 0), 0);
  const qtyInCart = (id) => { const l = CART.find(x => x.id === id); return l ? l.qty : 0; };
  W.cart = () => CART; W.wish = () => WISH;
  W.cartCount = cartCount; W.cartSubtotal = cartSubtotal;
  W.stockOf = stockOf; W.cartQtyOf = qtyInCart;

  function saveCart() { write(LS.cart, CART); syncBadges(); renderDrawer(); if (ME) post('cart', { cart: CART }); }
  function saveWish() { write(LS.wish, WISH); syncBadges(); window.dispatchEvent(new CustomEvent('well:wish')); }

  // add `add` more of an item, never exceeding available stock
  W.addToCart = function (id, add) {
    const p = W.BY_ID[id]; if (!p) return;
    const stock = stockOf(id);
    if (stock <= 0) { toast('Sorry — this item is out of stock'); return; }
    const l = CART.find(x => x.id === id);
    const cur = l ? l.qty : 0;
    const want = cur + (add || 1);
    const next = Math.min(want, stock);
    if (next === cur) { openDrawer(); toast(`That's all we have — only ${stock} in stock`); return; }
    if (l) l.qty = next; else CART.push({ id, qty: next });
    saveCart(); bumpBag();
    const dr = $('#cartDrawer'); if (dr && !dr.classList.contains('open')) openDrawer();   // don't yank an already-open drawer
    toast(next < want ? `Added — only ${stock} left in stock` : 'Added to bag ♡');
  };
  // set the exact quantity of an item (adds if missing, removes if 0), capped at stock. Returns the applied qty.
  W.setCartQty = function (id, qty) {
    const p = W.BY_ID[id]; if (!p) return 0;
    qty = Math.max(0, Math.min(qty | 0, stockOf(id)));
    const l = CART.find(x => x.id === id);
    if (qty === 0) { if (l) CART = CART.filter(x => x.id !== id); }
    else if (l) l.qty = qty; else CART.push({ id, qty });
    saveCart(); bumpBag();
    return qty;
  };
  W.setQty = function (id, qty) {
    const l = CART.find(x => x.id === id); if (!l) return;
    l.qty = Math.max(0, Math.min(qty, stockOf(id)));
    if (l.qty === 0) CART = CART.filter(x => x.id !== id);
    saveCart();
  };
  W.removeFromCart = function (id) { CART = CART.filter(x => x.id !== id); saveCart(); };
  W.clearCart = function () { CART.length = 0; saveCart(); };
  W.toggleWish = function (id) {
    const i = WISH.indexOf(id);
    if (i >= 0) { WISH.splice(i, 1); } else { WISH.push(id); }
    saveWish();
    const on = WISH.indexOf(id) >= 0;
    if (ME) post('wish', { id: id, on: on });
    return on;
  };

  function syncBadges() {
    document.querySelectorAll('[data-cart-count]').forEach(e => { e.textContent = cartCount(); e.style.display = cartCount() ? '' : 'none'; });
    document.querySelectorAll('[data-wish-count]').forEach(e => { e.textContent = WISH.length; e.style.display = WISH.length ? '' : 'none'; });
    syncWishButtons();
  }
  /* paint every heart on the page to match saved state — without this a product you
     already love renders as an empty heart until you click it */
  function syncWishButtons() {
    document.querySelectorAll('[data-wish]').forEach(b => b.classList.toggle('on', WISH.indexOf(b.dataset.wish) >= 0));
  }
  W.syncWishButtons = syncWishButtons;
  function bumpBag() {
    document.querySelectorAll('[data-cart-count]').forEach(e => { e.style.animation = 'none'; void e.offsetWidth; e.style.animation = 'heartpop .4s ease'; });
  }

  /* ---------- toast ---------- */
  let toastWrap;
  function toast(msg, opts) {
    opts = opts || {};
    if (!toastWrap) { toastWrap = document.createElement('div'); toastWrap.className = 'toast-wrap'; document.body.appendChild(toastWrap); }
    const t = document.createElement('div'); t.className = 'toast';
    t.innerHTML = (opts.ok ? `<span class="ok">${I.check}</span>` : '') + `<span>${msg}</span>` + (opts.action ? ` <a href="#" data-act>${opts.action}</a>` : '');
    toastWrap.appendChild(t); requestAnimationFrame(() => t.classList.add('show'));
    if (opts.action && opts.onAction) $('[data-act]', t).addEventListener('click', e => { e.preventDefault(); opts.onAction(); t.remove(); });
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, opts.dur || 2600);
  }
  W.toast = toast;

  /* ---------- product card ---------- */
  function stars(r) {
    return `<span class="s">${I.star.replace('width','').replace('height','')}</span>`;
  }
  W.productCard = function (p, opts) {
    opts = opts || {};
    const b = p.badge ? W.BADGE[p.badge] : null;
    const saleBadge = p.sale ? `<span class="badge badge-sale">-${p.sale}%</span>` : '';
    const hover = p.hover || p.img2;   // 2nd image for the rhode hover-swap
    const kw = p.kw || (p.name || '').split(' ')[0].toLowerCase();  // big overlaid title
    const buyPrice = `${money(p.price)}${p.was ? ` <s>${money(p.was)}</s>` : ''}`;   // mobile rhode "BUY — $price" pill
    const priceHtml = p.was   // desktop price row (old box)
      ? `<span class="price sale"><span class="now">${money(p.price)}</span><span class="was">${money(p.was)}</span></span>`
      : `<span class="price">${money(p.price)}</span>`;
    const stock = p.stock | 0, low = p.low | 0, soldOut = stock <= 0;
    const soldBadge = soldOut ? `<span class="badge badge-out">SOLD OUT</span>` : '';
    const stockNote = (!soldOut && stock <= low) ? `<span class="pc-stock">Only ${stock} left</span>` : '';
    const addBtn = soldOut ? `<button class="btn" disabled>Sold out</button>` : `<button class="btn" data-add="${p.id}">add to bag</button>`;
    const buyBtn = soldOut ? `<button class="buybtn" disabled>Sold out</button>` : `<button class="buybtn" data-add="${p.id}">buy — ${buyPrice}</button>`;
    return `<article class="pcard${soldOut ? ' is-sold' : ''}${hover ? '' : ' no-hover'}" data-pid="${p.id}">
      <div class="media graded" data-imgwrap>
        <a class="media-link" href="product?id=${p.id}" aria-label="${p.brand} ${p.name}"></a>
        <div class="pc-top"><h3 class="pc-kw">${kw}</h3><div class="badge-slot">${soldBadge}${saleBadge}${b ? `<span class="badge ${b.cls}">${b.label}</span>` : ''}</div></div>
        <img class="gimg pc-a" data-grade src="${p.img}" alt="${p.brand} ${p.name}" loading="lazy">
        ${hover ? `<img class="gimg pc-b" data-grade src="${hover}" alt="" loading="lazy">` : ''}
        <div class="add">${addBtn}</div>
      </div>
      <div class="body">
        <span class="stars">${p.reviews > 0 ? `<span class="s">${I.star}</span> ${p.rating.toFixed(1)} <span class="muted">(${p.reviews.toLocaleString()})</span>` : `<span class="muted" style="font-size:12px">No reviews yet</span>`}${stockNote}</span>
        <a class="name" href="product?id=${p.id}">${p.name}</a>
        ${p.desc ? `<span class="desc">${p.desc}</span>` : ''}
        <div class="pprice">${priceHtml}</div>
        ${buyBtn}
      </div>
    </article>`;
  };
  W.renderProducts = function (container, list) {
    container.innerHTML = list.map(p => W.productCard(p)).join('');
    W.guardImages(container);
    syncWishButtons();   // cards render after mount — paint their hearts too
  };

  /* delegate card interactions globally */
  document.addEventListener('click', function (e) {
    const add = e.target.closest('[data-add]');
    if (add) { e.preventDefault(); W.addToCart(add.dataset.add); return; }
    const wb = e.target.closest('[data-wish]');
    if (wb) {
      e.preventDefault();
      const on = W.toggleWish(wb.dataset.wish);
      wb.classList.toggle('on', on); wb.classList.add('pop');
      setTimeout(() => wb.classList.remove('pop'), 400);
      if (on) toast('Saved to wishlist ♡'); 
      document.querySelectorAll(`[data-wish="${wb.dataset.wish}"]`).forEach(b => b.classList.toggle('on', on));
      return;
    }
  });

  /* ============================================================
     CHROME MARKUP
     ============================================================ */
  function utilBar() {
    return `<div class="utilbar"><div class="wrap">
      <div class="marq">
        <span>${(W.SETTINGS&&W.SETTINGS.announce_1)||'FREE SHIPPING on orders above $49'}</span><span class="dot">·</span>
        <span>${(W.SETTINGS&&W.SETTINGS.announce_2)||'Authentic Products • Expert Care • Secure Checkout'}</span>
      </div>
      <div class="right">
        <a href="contact">Help</a><span class="dot">·</span>
        <span>EN | ${(W.SETTINGS&&W.SETTINGS.currency)||'$ USD'}</span>
      </div>
    </div></div>`;
  }

  function logo() {
    return `<a class="logo" href="index">
      <span class="badge-circle">${I.cross}</span>
      <span><span class="name">${(W.SETTINGS&&W.SETTINGS.store_name)||'WELL SHOP'}</span><br><span class="tag">${(W.SETTINGS&&W.SETTINGS.tagline)||'where Wellness meets You!'}</span></span>
    </a>`;
  }

  /* favourites · account · bag. Rendered TWICE: on the nav row for desktop, and in the
     top row for phones (where the nav row collapses into the hamburger and would hide
     them). Badges/handlers are delegated + querySelectorAll, so both copies stay live. */
  function shopIcons() {
    return `<a class="icon-btn" href="wishlist" aria-label="My favourites">${I.heart}<span class="count wishc" data-wish-count hidden>0</span></a>
      <a class="hdr-acct" href="${W.USER ? 'account' : 'login'}" aria-label="${W.USER ? 'My account' : 'Sign in or register'}">
        ${I.user}
        <span class="t">${W.USER
          ? `<b>hi, ${W.USER.first}</b><span>my account</span>`
          : `<b>sign in</b><span>or register</span>`}</span>
      </a>
      <button class="icon-btn" data-open-cart aria-label="Cart">${I.bag}<span class="count" data-cart-count>0</span></button>`;
  }

  function header() {
    const wa  = (W.SETTINGS && W.SETTINGS.whatsapp) || '9613627766';   // placeholder — change once in admin Settings, updates the whole site
    const soc = (W.SETTINGS && W.SETTINGS.social) || {};
    const ig  = soc.instagram || 'https://www.instagram.com/wellhealthandbeautyy';
    const tt  = soc.tiktok    || 'https://www.tiktok.com/@wellhealthandbeauty';
    return `<header class="site-header" id="siteHeader">
      <div class="wrap hdr-main">
        <button class="nav-toggle" data-nav-toggle aria-label="Menu" aria-expanded="false"><span class="nt-open">${I.menu}</span><span class="nt-close">${I.close}</span></button>
        ${logo()}
        <div class="hdr-search">
          <form class="search" role="search" action="search" method="get">
            ${I.search}
            <input name="q" placeholder="Search for products, brands or concerns…" aria-label="Search">
          </form>
        </div>
        <div class="hdr-right">
          <a class="hdr-expert wa-expert" href="https://wa.me/${wa}" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
            <span class="wa-av">${I.whatsapp}</span>
            <span class="t"><b>Chat with us</b><span>on WhatsApp</span></span>
          </a>
          <a class="icon-btn ig" href="${ig}" target="_blank" rel="noopener" aria-label="Instagram">${I.ig}</a>
          <a class="icon-btn tt" href="${tt}" target="_blank" rel="noopener" aria-label="TikTok">${I.tiktok}</a>
          <div class="shop-icons mobile-icons">${shopIcons()}</div>
        </div>
      </div>
      <div class="nav-row"><div class="wrap nav-wrap">
          <ul class="nav-list" id="navList"></ul>
          <div class="shop-icons desk-icons">${shopIcons()}</div>
        </div>
        <div class="mega" id="megaPanel"></div>
      </div>
    </header>`;
  }

  const MENU = {
    Skincare: {
      cols: [
        { h:'By Category', links:['Cleansers','Toners','Serums & Treatments','Moisturizers','Eye Care','Masks','Sunscreen & SPF','Lip Care','Face Oils','Tools'] },
        { h:'By Concern', links:['Acne','Anti-Aging','Hyperpigmentation','Dryness','Sensitivity','Pores','Dullness'] },
        { h:'By Brand', links:['CeraVe','La Roche-Posay','The Ordinary','Bioderma','Avène','Vichy','Eucerin'] },
      ],
      feat:{ img:W.IMG.flatlay1, ey:'Featured', t:'Derm Picks of the Month', cta:'Build Your Routine' },
    },
    Haircare:{ cols:[{h:'By Category',links:['Shampoo','Conditioner','Scalp Serums','Masks & Treatments','Styling','Tools']},{h:'By Concern',links:['Hair Fall','Dandruff','Dryness','Damage Repair','Volume']},{h:'By Brand',links:['Klorane','Garnier','La Roche-Posay','PHbalance']}], feat:{img:W.IMG.catHair,ey:'Featured',t:'Hair-Fall SOS Routine',cta:'Shop Haircare'} },
    Wellness:{ cols:[{h:'Supplements',links:['Vitamins','Minerals','Omega & Fish Oil','Collagen','Probiotics','Sleep & Calm','Immunity']},{h:'By Goal',links:['Energy','Sleep','Immunity','Skin from Within','Bone Health']},{h:'By Brand',links:['Solgar','Centrum','VitaWell','PureMarine']}], feat:{img:W.IMG.catWellness,ey:'Featured',t:'Magnesium for Sleep',cta:'Shop Wellness'} },
    Makeup:{ cols:[{h:'Face',links:['Foundation','Skin Tint','Concealer','Blush','Setting']},{h:'Lips & Eyes',links:['Lip Balm','Lip Tint','Mascara','Brows']},{h:'By Brand',links:['VeloursBeauty','RoseGoldCo','LipCare+']}], feat:{img:W.IMG.catMakeup,ey:'Featured',t:'Clean Glow Edit',cta:'Shop Makeup'} },
    'Personal Care':{ cols:[{h:'Body',links:['Deodorant','Body Wash','Body Lotion','Hand Care']},{h:'Oral & Daily',links:['Oral Care','Feminine Care','Sun Care']},{h:'By Brand',links:['PureDay','Nivea','Sebamed']}], feat:{img:W.IMG.catPersonal,ey:'Featured',t:'Everyday Essentials',cta:'Shop Personal Care'} },
    'Mom & Baby':{ cols:[{h:'Baby',links:['Diaper Care','Baby Bath','Baby Lotion','Baby Sun']},{h:'Mom',links:['Pregnancy-Safe Skincare','Stretch Mark Care','Nursing']},{h:'By Brand',links:['Mustela','Pigeon','Bepanthen']}], feat:{img:W.IMG.catBaby,ey:'Featured',t:'Gentle Baby Set',cta:'Shop Mom & Baby'} },
    'Sexual Wellness':{ cols:[{h:'Intimate Care',links:['Intimate Wash','Intimate Gel','pH Balance']},{h:'Protection',links:['Condoms','Lubricants','Wellness']},{h:'By Brand',links:['IntimaCare','Durex']}], feat:{img:W.IMG.catSexual,ey:'Discreet & Private',t:'Care, Your Way',cta:'Shop Sexual Wellness'} },
    'Health Conditions':{ cross:true, cols:[{h:'Conditions',links:['Eczema & Dry Skin','Acne-Prone','Rosacea','Allergy & Cold','Digestive','Joint & Bone']},{h:'Care Type',links:['Dermatologist Tested','Pharmacist Recommended','Prescription Support']},{h:'By Brand',links:['La Roche-Posay','Eucerin','Avène','Cetaphil']}], feat:{img:W.IMG.catWellness,ey:'Pharmacist Care',t:'Condition-First Picks',cta:'Talk to an Expert'} },
    Offers:{ offers:true, cols:[{h:"Today's Deals",links:["Today's Deals","Up to 50% Off","Bundle & Save","Clearance"]},{h:'Coupons',links:['WELL10 — 10% Off','GLOW20 — 20% Off','FREESHIP','Student Discount']},{h:'Shop Deals',links:['Skincare Sale','Wellness Sale','Makeup Sale','Mom & Baby Sale']}], feat:{img:W.IMG.heroSerum,ey:'Limited Time',t:'The Well Sale Event',cta:'Shop All Deals'} },
  };

  function megaHTML(name) {
    const m = MENU[name]; if (!m) return '';
    const cols = m.cols.map(c => `<div class="mega-col"><h4>${c.h}</h4><ul>${c.links.map(l => `<li><a href="${name==='Offers'?'offers':'skincare'}">${l}</a></li>`).join('')}</ul></div>`).join('');
    const feat = `<div class="mega-feat graded" data-imgwrap><img class="gimg" data-grade src="${m.feat.img}" alt=""><div class="cap"><span class="ey">${m.feat.ey}</span><b>${m.feat.t}</b><a class="btn btn-rosegold btn-sm" href="${name==='Offers'?'offers':'skincare'}">${m.feat.cta}</a></div></div>`;
    const brands = `<div class="mega-brands"><span class="lab">Top Brands</span>${['CeraVe','La Roche-Posay','The Ordinary','Bioderma','Avène','Vichy','Eucerin'].map(b => `<a class="brand-tile" href="brands">${b}</a>`).join('')}</div>`;
    return `<div class="mega-inner">${cols}${feat}${brands}</div>`;
  }

  function buildNav(active) {
    const ul = $('#navList'); if (!ul) return;
    const hrefFor = (n) => n === 'Shop All' ? 'skincare'
      : n === 'Brands' ? 'brands'
      : n === 'Offers' ? 'offers'
      : 'skincare?cat=' + encodeURIComponent(n);
    ul.innerHTML = W.NAV.map(n => {
      const cross = n === 'Health Conditions' ? `<span class="x">${I.cross}</span>` : '';
      const cls = n === 'Offers' ? 'offers' : '';
      return `<li class="${active === n ? 'active' : ''}" data-menu="${n}"><a class="${cls}" href="${hrefFor(n)}">${cross}${n}</a></li>`;
    }).join('');

    const panel = $('#megaPanel');
    const backdrop = ensureBackdrop();
    let hideT;
    function show(name) {
      const html = megaHTML(name);
      if (!html) { hide(); return; }
      clearTimeout(hideT);
      panel.innerHTML = html; panel.classList.add('open'); backdrop.classList.add('open');
      W.guardImages(panel);
    }
    function hide() { hideT = setTimeout(() => { panel.classList.remove('open'); backdrop.classList.remove('open'); }, 120); }
    ul.querySelectorAll('[data-menu]').forEach(li => {
      const name = li.dataset.menu;
      if (!MENU[name]) return;
      li.addEventListener('mouseenter', () => show(name));
      li.addEventListener('mouseleave', hide);
    });
    panel.addEventListener('mouseenter', () => clearTimeout(hideT));
    panel.addEventListener('mouseleave', hide);
    backdrop.addEventListener('click', () => { panel.classList.remove('open'); backdrop.classList.remove('open'); });
  }
  function ensureBackdrop() {
    let b = $('.mega-backdrop');
    if (!b) { b = document.createElement('div'); b.className = 'mega-backdrop'; document.body.appendChild(b); }
    return b;
  }

  /* (the old accountNavHTML lived here — it was never rendered and pointed at pages
      that don't exist; account.php owns its own tabs now) */

  /* ---------- USP strip ---------- */
  W.uspHTML = function () {
    const items = [
      ['chat','Talk to an Expert','Chat with our licensed pharmacists, anytime.'],
      ['shield','Prescribed with Care','Discreet & private. We respect your wellness.'],
      ['check','Genuine Products','Sourced only from trusted, authentic brands.'],
      ['truck','Delivered to You','Quick, safe & reliable — delivery across Lebanon.'],
      ['rotate','Easy Returns','Changed your mind? Hassle-free returns, no stress.'],
    ];
    return `<section class="usp"><div class="wrap">${items.map(([ic,t,s]) => `<div class="item"><span class="ic">${I[ic]}</span><div><b>${t}</b><span>${s}</span></div></div>`).join('')}</div></section>`;
  };

  /* ---------- social proof ---------- */
  W.socialProofHTML = function () {
    return `<div class="socialproof">
      <div class="stat"><b>2M+</b><span class="muted">Happy customers</span></div>
      <div class="stat"><span style="color:var(--star);display:inline-flex;width:20px">${I.star}</span><b>4.8/5</b><span class="muted">7,000+ reviews</span></div>
      <div class="logos-trust"><span>Google</span><span>·</span><span>Trustpilot</span><span>·</span><span>Facebook</span></div>
      <div class="row"><div class="avstack">${W.AV.map(a => `<img class="gimg" data-grade src="${a}" alt="">`).join('')}</div><span class="caption"><b style="color:var(--ink)">THE WELL COMMUNITY</b> — Join the club</span></div>
    </div>`;
  };

  /* ---------- footer ---------- */
  function footer() {
    const cols = [
      ['Shop',['Skincare','Haircare','Wellness','Makeup','Offers & Sale','Brands']],
      ['Help & Support',['My Account','My Favourites','Shipping & Delivery','Returns & Refunds','FAQ','Contact Us']],
      ['About The Well',['Our Story','Wellness Journal','Authenticity Promise','Careers','The Well Community']],
    ];
    const hrefs = { 'My Account':'account','My Favourites':'wishlist','Contact Us':'contact','Our Story':'about','Wellness Journal':'journal','Offers & Sale':'offers','Brands':'brands','Skincare':'skincare','Haircare':'skincare?cat=Haircare','Wellness':'skincare?cat=Wellness','Makeup':'skincare?cat=Makeup','FAQ':'faq','Shipping & Delivery':'shipping-delivery','Returns & Refunds':'returns-refunds','Authenticity Promise':'about','The Well Community':'about','Careers':'about' };
    const soc = (W.SETTINGS && W.SETTINGS.social) || {};
    const socIcons = { instagram:I.ig, tiktok:I.tiktok, facebook:I.fb, youtube:I.yt, pinterest:I.pin };
    const socialHTML = Object.keys(socIcons).filter(k => soc[k]).map(k => `<a href="${soc[k]}" target="_blank" rel="noopener" aria-label="${k}">${socIcons[k]}</a>`).join('') || `<a href="#">${I.ig}</a><a href="#">${I.tiktok}</a>`;
    return `<footer class="site-footer">
      <div class="foot-news"><div class="wrap">
        <div><h3>Join THE WELL COMMUNITY</h3><p>Get 10% off your first order — pharmacist tips, new drops & deals. No spam, just glow.</p></div>
        <form onsubmit="return false"><input class="pillinput" placeholder="Your email address" aria-label="Email"><button class="btn btn-coral">Subscribe</button></form>
      </div></div>
      <div class="wrap foot-cols">
        <div class="foot-brand">${logo()}<p>${(W.SETTINGS&&W.SETTINGS.footer_about)||'The online home of Well Pharmacy, Beirut — fusing real pharmacist expertise with clean, trend-forward beauty. Real results. Real confidence. Powered by science. Loved by you. ♡'}</p></div>
        ${cols.map(([h,links]) => `<div><h5>${h}</h5>${links.map(l => `<a href="${hrefs[l]||'#'}">${l}</a>`).join('')}</div>`).join('')}
      </div>
      <div class="foot-bottom"><div class="wrap">
        <div class="foot-pay"><span class="pay-badge">VISA</span><span class="pay-badge">Mastercard</span><span class="pay-badge" style="background:var(--mint);color:#fff">COD</span>
        </div>
        <div class="foot-social">${socialHTML}</div>
      </div></div>
      <div class="foot-bottom"><div class="wrap"><span>© ${new Date().getFullYear()} ${(W.SETTINGS&&W.SETTINGS.store_name)||'THE WELL SHOP'} · Beirut · Made with ♡ in Beirut</span><span>Cash on Delivery available across Lebanon</span></div></div>
    </footer>`;
  }

  /* ---------- cart drawer ---------- */
  function drawerShell() {
    const bd = document.createElement('div'); bd.className = 'drawer-backdrop'; bd.id = 'cartBackdrop';
    const dr = document.createElement('aside'); dr.className = 'cart-drawer'; dr.id = 'cartDrawer';
    dr.setAttribute('aria-label', 'Shopping bag');
    document.body.appendChild(bd); document.body.appendChild(dr);
    bd.addEventListener('click', closeDrawer);
    renderDrawer();
  }
  function renderDrawer() {
    const dr = $('#cartDrawer'); if (!dr) return;
    const _ci = dr.querySelector('.cart-items'); const _scroll = _ci ? _ci.scrollTop : 0;   // keep scroll position on re-render
    const sub = cartSubtotal(), FREE = (W.SETTINGS && W.SETTINGS.free_ship) || 49, remain = Math.max(0, FREE - sub), pct = Math.min(100, sub / FREE * 100);
    const met = sub >= FREE;
    let body;
    if (!CART.length) {
      body = `<div class="cart-empty"><span class="ic">${I.dropper}</span><div><b style="font-family:var(--fp);font-size:20px">Your bag is feeling light.</b><p class="muted" style="margin:6px 0 0">Discover derm-loved essentials to get glowing.</p></div><a class="btn btn-primary" href="skincare">Start Shopping</a></div>`;
    } else {
      const items = CART.map(l => { const p = W.BY_ID[l.id]; if (!p) return ''; const b = p.badge ? W.BADGE[p.badge] : null;
        const stock = p.stock | 0, low = p.low | 0, atMax = l.qty >= stock;
        const note = atMax ? `<span class="ci-max">${stock <= low ? 'Only ' + stock + ' left' : 'Max reached'}</span>` : (stock <= low ? `<span class="ci-max">Only ${stock} left</span>` : '');
        return `<div class="citem"><img class="thumb gimg" data-grade src="${p.img}" alt=""><div class="ci-b"><span class="br">${p.brand}</span><div class="ti">${p.name}</div>${b?`<span class="badge ${b.cls}" style="margin-bottom:8px">${b.label}</span>`:''}<div class="ci-foot"><span class="stepper"><button data-dec="${p.id}">−</button><span class="q">${l.qty}</span><button data-inc="${p.id}"${atMax?' disabled':''}>+</button></span><span class="pr">${money(p.price*l.qty)}</span></div>${note}</div><button class="rm" data-rm="${p.id}" aria-label="Remove">${I.close}</button></div>`;
      }).join('');
      body = `<div class="freeship ${met?'met':''}"><p>${met?'Yay! You\'ve unlocked FREE SHIPPING ✦':`You're ${money(remain)} away from FREE SHIPPING! ♡`}</p><div class="track"><div class="fill" style="width:${pct}%"></div></div></div>
        <div class="cart-items">${items}</div>
        <div class="cart-foot"><div class="sub"><span>Subtotal</span><b>${money(sub)}</b></div><div class="cod">${I.truck} COD available across Lebanon</div><a class="btn btn-primary btn-block" href="checkout">Checkout — ${money(sub)}</a><a class="btn btn-ghost btn-block" href="cart">View Bag</a></div>`;
    }
    dr.innerHTML = `<div class="dh"><h3>YOUR BAG (${cartCount()})</h3><button class="icon-btn" data-close-cart aria-label="Close">${I.close}</button></div>${body}`;
    const _ci2 = dr.querySelector('.cart-items'); if (_ci2) _ci2.scrollTop = _scroll;
    W.guardImages(dr);
  }
  function openDrawer() { const dr = $('#cartDrawer'), bd = $('#cartBackdrop'); if (!dr) return; renderDrawer(); dr.classList.add('open'); bd.classList.add('open'); document.body.style.overflow = 'hidden'; }
  function closeDrawer() { const dr = $('#cartDrawer'), bd = $('#cartBackdrop'); if (!dr) return; dr.classList.remove('open'); bd.classList.remove('open'); document.body.style.overflow = ''; }
  W.openCart = openDrawer; W.closeCart = closeDrawer;

  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-open-cart]')) { e.preventDefault(); openDrawer(); }
    if (e.target.closest('[data-close-cart]')) { e.preventDefault(); closeDrawer(); }
    const inc = e.target.closest('[data-inc]'); if (inc) { const p = W.BY_ID[inc.dataset.inc]; W.setQty(inc.dataset.inc, (CART.find(x=>x.id===inc.dataset.inc)||{}).qty + 1); }
    const dec = e.target.closest('[data-dec]'); if (dec) { const cur = (CART.find(x=>x.id===dec.dataset.dec)||{}).qty || 0; W.setQty(dec.dataset.dec, cur - 1); }
    const rm = e.target.closest('[data-rm]'); if (rm) { W.removeFromCart(rm.dataset.rm); }
  });

  /* ---------- header scroll condense ---------- */
  function scrollHeader() {
    const h = $('#siteHeader'); if (!h) return;
    const on = () => h.classList.toggle('scrolled', window.scrollY > 120);
    on(); window.addEventListener('scroll', on, { passive:true });
  }

  /* ---------- reveal on scroll ---------- */
  W.reveal = function () {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const io = new IntersectionObserver(es => es.forEach(en => { if (en.isIntersecting) { en.target.style.animation = 'fadeUp .5s ease both'; io.unobserve(en.target); } }), { threshold:.12 });
    document.querySelectorAll('[data-reveal]').forEach(el => io.observe(el));
  };

  /* ============================================================
     PUBLIC: mountChrome
     ============================================================ */
  W.mountChrome = function (opts) {
    opts = opts || {};
    const top = $('#chrome-top');
    if (top) top.innerHTML = utilBar() + header();
    const foot = $('#chrome-foot');
    if (foot) foot.innerHTML = footer();
    buildNav(opts.active);
    // mobile hamburger toggle
    const tg = $('[data-nav-toggle]'), hdr = $('#siteHeader');
    if (tg && hdr) {
      tg.addEventListener('click', () => { const o = hdr.classList.toggle('nav-open'); tg.setAttribute('aria-expanded', o); });
      const nl = $('#navList');
      if (nl) nl.addEventListener('click', e => { if (e.target.closest('a')) hdr.classList.remove('nav-open'); });
    }
    drawerShell();
    syncBadges();
    scrollHeader();
    W.guardImages(document);
    W.reveal();
  };

  // small runtime styles (fadeUp + stock / sold-out states) injected once
  const st = document.createElement('style');
  st.textContent = '@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}'
    + '.badge-out{background:#6b6b6b !important;color:#fff !important}'
    + '.pcard.is-sold .media img{filter:grayscale(.5);opacity:.72}'
    + '.pc-stock{margin-left:8px;font-size:11.5px;font-weight:600;color:var(--coral-deep,#b04a2f)}'
    + '.pcard .btn[disabled],.pcard .buybtn[disabled]{opacity:.55;cursor:not-allowed;pointer-events:none}'
    + '.ci-max{display:inline-block;margin-top:4px;font-size:11px;font-weight:600;color:var(--coral-deep,#b04a2f)}'
    + '.stepper button[disabled]{opacity:.35;cursor:not-allowed}';
  document.head.appendChild(st);

})(window.WELL = window.WELL || {});
