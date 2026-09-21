/* ============================================================
   WELL PHARMACY admin — list behaviour
     1. infinite scroll   — pull the next slice of rows as you scroll
     2. sticky x-scrollbar — a wide table can be scrolled sideways from
        anywhere on the page, not only from its very bottom edge
   ============================================================ */
(function () {
  'use strict';

  /* ---------- 1. infinite scroll ---------------------------------------- */
  function initMore() {
    var box = document.querySelector('[data-list-more]');
    if (!box) return;
    var tbody = document.querySelector('.a-table tbody');
    if (!tbody) return;

    var loading = false, done = false;

    function load() {
      if (loading || done) return;
      var url = box.getAttribute('data-next');
      if (!url) return;
      loading = true;
      box.classList.add('is-loading');

      fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
        .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
        .then(function (html) {
          var t = html.trim();
          if (!t) { finish(); return; }
          /* rows have to be parsed inside a real <table> or the browser drops them */
          var doc = document.createElement('tbody');
          var tmp = document.createElement('table');
          tmp.appendChild(doc);
          doc.innerHTML = t;
          var added = doc.children.length;
          while (doc.firstChild) tbody.appendChild(doc.firstChild);
          if (!added) { finish(); return; }

          /* advance to the following page */
          box.setAttribute('data-next', url.replace(/([?&]page=)(\d+)/, function (_, p, n) {
            return p + (parseInt(n, 10) + 1);
          }));
          loading = false;
          box.classList.remove('is-loading');
          syncBars();
          /* the viewport may still not be full — keep going if the anchor is visible */
          if (io && isVisible(box)) load();
        })
        .catch(function () { loading = false; box.classList.remove('is-loading'); });
    }

    function finish() {
      done = true; loading = false;
      box.classList.remove('is-loading');
      box.remove();
      if (io) io.disconnect();
    }

    function isVisible(el) {
      var r = el.getBoundingClientRect();
      return r.top < (window.innerHeight || 0) + 200 && r.bottom > 0;
    }

    var io = null;
    if ('IntersectionObserver' in window) {
      io = new IntersectionObserver(function (es) {
        es.forEach(function (e) { if (e.isIntersecting) load(); });
      }, { rootMargin: '600px 0px' });
      io.observe(box);
    }
    var btn = box.querySelector('[data-list-more-btn]');
    if (btn) btn.addEventListener('click', load);
  }

  /* ---------- 2. sticky horizontal scrollbar ---------------------------- */
  /* A wide admin table scrolls inside its card. The native scrollbar sits at the
     bottom of the TABLE, so with hundreds of rows you had to scroll all the way
     down before you could pan sideways. This clones the scrollbar and pins it to
     the bottom of the viewport while any part of the table is on screen. */
  var bars = [];

  function makeBar(scroller) {
    var bar = document.createElement('div');
    bar.className = 'xbar';
    var inner = document.createElement('div');
    bar.appendChild(inner);
    scroller.parentNode.insertBefore(bar, scroller.nextSibling);

    var lock = false;
    bar.addEventListener('scroll', function () {
      if (lock) { lock = false; return; }
      lock = true; scroller.scrollLeft = bar.scrollLeft;
    });
    scroller.addEventListener('scroll', function () {
      if (lock) { lock = false; return; }
      lock = true; bar.scrollLeft = scroller.scrollLeft;
    });

    var rec = { scroller: scroller, bar: bar, inner: inner };
    bars.push(rec);
    return rec;
  }

  function syncBars() {
    bars.forEach(function (b) {
      var overflow = b.scroller.scrollWidth > b.scroller.clientWidth + 1;
      b.inner.style.width = b.scroller.scrollWidth + 'px';
      b.bar.classList.toggle('on', overflow);
      if (overflow) position(b);
    });
  }

  /* only show the floating bar while the table is actually in view */
  function position(b) {
    var r = b.scroller.getBoundingClientRect();
    var vh = window.innerHeight || document.documentElement.clientHeight;
    var inView = r.top < vh - 20 && r.bottom > 60;
    /* hide it once the table's own scrollbar is already on screen */
    var ownBarVisible = r.bottom <= vh;
    b.bar.classList.toggle('show', inView && !ownBarVisible);
  }

  function initBars() {
    document.querySelectorAll('.a-card .bd').forEach(function (bd) {
      if (!bd.querySelector('.a-table')) return;
      makeBar(bd);
    });
    syncBars();
    window.addEventListener('scroll', function () { bars.forEach(position); }, { passive: true });
    window.addEventListener('resize', syncBars);
  }

  function boot() { initBars(); initMore(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
