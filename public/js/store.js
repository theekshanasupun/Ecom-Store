/* KIOSK storefront interactions — vanilla JS, no build step */
(function () {
  'use strict';
  var d = document, root = d.documentElement;
  root.classList.add('js');
  var K = window.KIOSK || { base: '', csrf: '' };
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  function post(path, data) {
    var fd = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) for (var k in data) fd.append(k, data[k]);
    fd.set('_token', K.csrf);
    return fetch(K.base + path, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Unexpected response' }; }); });
  }

  /* ---- toasts ---- */
  function toast(msg, err) {
    var box = $('#toasts'); if (!box) return;
    var t = d.createElement('div'); t.className = 'toast' + (err ? ' err' : ''); t.textContent = msg; box.appendChild(t);
    setTimeout(function () { t.classList.add('out'); setTimeout(function () { t.remove(); }, 350); }, 2600);
  }

  /* ---- drawer ---- */
  var drawer = $('#drawer'), scrim = $('#scrim');
  function openDrawer() { drawer.classList.add('open'); drawer.setAttribute('aria-hidden', 'false'); scrim.hidden = false; requestAnimationFrame(function () { scrim.classList.add('on'); }); d.body.style.overflow = 'hidden'; }
  function closeDrawer() { drawer.classList.remove('open'); drawer.setAttribute('aria-hidden', 'true'); scrim.classList.remove('on'); setTimeout(function () { scrim.hidden = true; }, 300); d.body.style.overflow = ''; }
  function applyCart(r) {
    if (!r) return;
    $$('[data-cart-count]').forEach(function (c) { c.textContent = r.count; c.classList.remove('bump'); void c.offsetWidth; c.classList.add('bump'); });
    var b = $('#drawer-body'); if (b && r.html != null) b.innerHTML = r.html;
  }
  if ($('#cart-open')) {
    $('#cart-open').addEventListener('click', function () { openDrawer(); });
    $('#cart-close').addEventListener('click', closeDrawer);
    scrim.addEventListener('click', closeDrawer);
  }
  d.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { if (drawer && drawer.classList.contains('open')) closeDrawer(); closeSearch(); }
  });
  d.addEventListener('click', function (e) {
    var t = e.target.closest('[data-cart]'); if (!t) return;
    var act = t.getAttribute('data-cart');
    var p = act === 'remove' ? post('/cart/remove', { key: t.dataset.key }) : post('/cart/update', { key: t.dataset.key, qty: t.dataset.qty });
    p.then(applyCart);
  });

  /* ---- add to cart ---- */
  function addToCart(fd, btn, keepOpen) {
    if (btn) btn.disabled = true;
    return post('/cart/add', fd).then(function (r) {
      if (btn) btn.disabled = false;
      if (!r.ok) { toast(r.error || 'Could not add that', true); return; }
      applyCart(r); toast((r.name ? r.name + ' · ' : '') + r.message);
      if (btn) { btn.classList.add('added'); setTimeout(function () { btn.classList.remove('added'); }, 1200); }
      if (!keepOpen && drawer) setTimeout(openDrawer, 250);
    }).catch(function () { if (btn) btn.disabled = false; toast('Network error', true); });
  }
  d.addEventListener('click', function (e) {
    var b = e.target.closest('[data-add]'); if (!b) return;
    e.preventDefault();
    var fd = new FormData(); fd.append('product_id', b.dataset.add); fd.append('qty', 1);
    addToCart(fd, b);
  });
  $$('[data-add-form]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      var s = e.submitter;
      if (s && s.name === 'buy_now') return; // normal navigation to checkout
      if (f.querySelector('[name=buy_now][type=hidden]')) return;
      e.preventDefault();
      var fd = new FormData(f); addToCart(fd, s || f.querySelector('button[type=submit]'));
    });
  });

  /* ---- cart page forms ---- */
  d.addEventListener('submit', function (e) {
    var f = e.target.closest('[data-cart-form]'); if (!f) return;
    e.preventDefault();
    post(f.getAttribute('action').replace(K.base, ''), new FormData(f)).then(function (r) {
      applyCart(r);
      if (r.error) toast(r.error, true);
      var rootEl = $('#cart-root'); if (!rootEl) return;
      fetch(K.base + '/cart?partial=1', { credentials: 'same-origin' }).then(function (x) { return x.text(); }).then(function (h) { rootEl.innerHTML = h; });
    });
  });

  /* ---- chip filters ---- */
  $$('[data-filter-group]').forEach(function (g) {
    var grid = $(g.dataset.filterGroup), tagMode = g.dataset.mode === 'tags';
    if (!grid) return;
    var shown = $('#shown');
    function update() { if (shown) shown.textContent = $$('.pcard:not(.is-hidden)', grid).length + ' shown'; }
    g.addEventListener('click', function (e) {
      var c = e.target.closest('.chip'); if (!c) return;
      $$('.chip', g).forEach(function (x) { x.classList.toggle('on', x === c); });
      var v = c.dataset.filter;
      $$('.pcard', grid).forEach(function (card) {
        var ok = v === '*' || (tagMode ? (card.dataset.tags || '').split('|').indexOf(v) > -1 : card.dataset.cat === v);
        card.classList.toggle('is-hidden', !ok);
        if (ok && !reduce) { card.animate([{ opacity: 0, transform: 'translateY(14px)' }, { opacity: 1, transform: 'none' }], { duration: 400, easing: 'cubic-bezier(.2,.8,.2,1)' }); }
      });
      update();
    });
    update();
  });

  /* ---- home panels accordion ---- */
  var panels = $$('.panel');
  panels.forEach(function (p) {
    function open() { panels.forEach(function (x) { var on = x === p; x.classList.toggle('open', on); x.setAttribute('aria-expanded', on); }); }
    p.addEventListener('click', function (e) { if (!p.classList.contains('open') && !e.target.closest('a')) open(); });
    p.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
    if (window.matchMedia('(hover:hover) and (min-width:821px)').matches) p.addEventListener('mouseenter', function () { if (!p.classList.contains('open')) open(); });
  });

  /* ---- reveal on scroll ---- */
  var rv = $$('[data-reveal]');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function (es) { es.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } }); }, { threshold: .12, rootMargin: '0px 0px -40px 0px' });
    rv.forEach(function (el) { io.observe(el); });
  } else rv.forEach(function (el) { el.classList.add('in'); });

  /* ---- header ---- */
  var hdr = $('#hdr');
  function onScroll() { if (hdr) hdr.classList.toggle('stuck', window.scrollY > 8); }
  onScroll(); window.addEventListener('scroll', onScroll, { passive: true });
  var burger = $('#burger'), nav = $('#nav');
  if (burger && nav) burger.addEventListener('click', function () { nav.classList.toggle('open'); });

  /* ---- live search ---- */
  var sv = $('#search'), si = $('#search-input'), sr = $('#search-results'), timer;
  function openSearch() { if (!sv) return; sv.hidden = false; d.body.style.overflow = 'hidden'; setTimeout(function () { si.focus(); }, 30); }
  function closeSearch() { if (!sv || sv.hidden) return; sv.hidden = true; d.body.style.overflow = drawer && drawer.classList.contains('open') ? 'hidden' : ''; }
  if ($('#search-open')) {
    $('#search-open').addEventListener('click', openSearch);
    $('#search-close').addEventListener('click', closeSearch);
    sv.addEventListener('click', function (e) { if (e.target === sv) closeSearch(); });
    d.addEventListener('keydown', function (e) { if (e.key === '/' && !/input|textarea|select/i.test(e.target.tagName)) { e.preventDefault(); openSearch(); } });
    si.addEventListener('input', function () {
      clearTimeout(timer);
      var q = si.value.trim();
      if (q.length < 2) { sr.innerHTML = ''; return; }
      timer = setTimeout(function () {
        fetch(K.base + '/search.json?q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
          sr.innerHTML = j.results.length ? j.results.map(function (p) {
            return '<a class="sr" href="' + p.url + '" style="--c:' + p.color + '"><span class="sr-media">' + p.media + '</span><b>' + esc(p.name) + '<small>' + esc(p.cat) + '</small></b><span>' + esc(p.price) + '</span></a>';
          }).join('') : '<p class="muted">No matches for “' + esc(q) + '”.</p>';
        });
      }, 180);
    });
  }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  /* ---- colour options recolour the artwork ---- */
  var NAMED = { bone: '#f3f1ec', black: '#1a1a1f', moss: '#4b5b3d', sky: '#8db7ff', tomato: '#ff7a2f', sand: '#e4dcc8', navy: '#1d2b53', white: '#f6f6f6', red: '#e5383b', green: '#3a9d5d', blue: '#3b6cff', pink: '#ff5aa5', yellow: '#ffd60a', grey: '#8d8d95', gray: '#8d8d95', olive: '#6b7a3a', cream: '#efe6d2', purple: '#8b7bff', orange: '#ff7a2f' };
  function cssColor(v) { v = (v || '').toLowerCase(); if (NAMED[v]) return NAMED[v]; var s = new Option().style; s.color = v; return s.color ? v : null; }
  $$('[data-feature]').forEach(function (box) {
    var art = $('[data-art] svg', box.closest('section') || box) || $('[data-art] svg');
    $$('input[data-color]', box).forEach(function (inp) {
      var c = cssColor(inp.dataset.color);
      if (c && inp.parentNode) inp.parentNode.style.setProperty('--sw', c);
      inp.addEventListener('change', function () {
        if (!c || !art) return;
        var shape = art.querySelector('path'); if (shape && /art-(tee|hoodie|pants)/.test(art.getAttribute('class'))) { shape.style.transition = 'fill .4s'; shape.setAttribute('fill', c); }
      });
    });
  });
  // sync swatch colours everywhere (page + feature)
  $$('input[data-color]').forEach(function (inp) { var c = cssColor(inp.dataset.color); if (c && inp.parentNode) inp.parentNode.style.setProperty('--sw', c); });
  // Apply the initial colour to the artwork too
  $$('input[data-color]:checked').forEach(function (inp) { inp.dispatchEvent(new Event('change')); });

  /* ---- qty stepper on product page ---- */
  $$('.pdp-buy .qty').forEach(function (q) {
    var i = $('input', q);
    $$('button', q).forEach(function (b) { b.addEventListener('click', function () { var n = Math.max(1, Math.min(+i.max || 99, (+i.value || 1) + (+b.dataset.q))); i.value = n; }); });
  });

  /* ---- gallery ---- */
  $$('[data-gallery] .thumbs button').forEach(function (b) {
    b.addEventListener('click', function () {
      var st = $('#stage img'); if (!st) { var s = $('#stage'); var svg = $('svg', s); if (svg) svg.remove(); st = d.createElement('img'); s.appendChild(st); }
      st.src = b.dataset.src; $$('.thumbs button').forEach(function (x) { x.classList.toggle('on', x === b); });
    });
  });

  /* ---- copy keys ---- */
  d.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]'); if (!b) return;
    var code = b.parentNode.querySelector('code'); if (!code) return;
    var done = function () { var o = b.textContent; b.textContent = 'Copied ✓'; setTimeout(function () { b.textContent = o; }, 1400); };
    if (navigator.clipboard) navigator.clipboard.writeText(code.textContent).then(done); else { var r = d.createRange(); r.selectNodeContents(code); var s = getSelection(); s.removeAllRanges(); s.addRange(r); d.execCommand('copy'); done(); }
  });

  /* ---- subtle card tilt (fine pointers only) ---- */
  if (!reduce && window.matchMedia('(hover:hover) and (pointer:fine)').matches) {
    $$('.pcard').forEach(function (c) {
      var m = $('.pmedia', c); if (!m) return;
      c.addEventListener('mousemove', function (e) { var r = m.getBoundingClientRect(); var x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5; c.style.setProperty('--tilt-y', (x * 8).toFixed(2)); c.style.setProperty('--tilt-x', (-y * 8).toFixed(2)); });
      c.addEventListener('mouseleave', function () { c.style.setProperty('--tilt-x', 0); c.style.setProperty('--tilt-y', 0); });
    });
  }

  /* ---- checkout ---- */
  var mk = $('#mkacct'), pw = $('#acct-pass');
  if (mk && pw) mk.addEventListener('change', function () { pw.hidden = !mk.checked; });
  var bank = $('.bankinfo');
  var pm = $$('input[name=method]');
  function syncPay() { if (!bank) return; var v = (pm.filter(function (x) { return x.checked; })[0] || {}).value; bank.style.display = v === 'bank' ? 'block' : 'none'; }
  pm.forEach(function (r) { r.addEventListener('change', syncPay) }); syncPay();
  var co = $('#co-form');
  if (co) co.addEventListener('submit', function () { var b = $('#co-submit'); if (b) { b.disabled = true; b.textContent = 'Placing order…'; } });
  try { if (sessionStorage.getItem('k_after')) sessionStorage.removeItem('k_after'); } catch (e) {}
})();

/* ===== v2.5: bars, popups, newsletter forms, countdowns, checkout email capture ===== */
(function () {
  'use strict';
  var d = document, K = window.KIOSK || {};
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  function store(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }
  function sstore(k, v) { try { if (v === undefined) return sessionStorage.getItem(k); sessionStorage.setItem(k, v); } catch (e) { return null; } }
  function post(path, data) {
    var fd = new FormData(); for (var k in data) fd.append(k, data[k]); fd.set('_token', K.csrf);
    return fetch((K.base || '') + path, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json().catch(function () { return {}; }); });
  }

  /* announcement bar */
  var ann = $('#annbar');
  if (ann) {
    var key = 'ann_' + ann.dataset.key;
    if (store(key) === '1') ann.remove();
    else {
      var x = $('.ann-x', ann); if (x) x.addEventListener('click', function () { store(key, '1'); ann.remove(); });
      if (ann.classList.contains('ann-rotate')) {
        var msgs = $$('.ann-msg', ann), i = 0;
        if (msgs.length > 1) setInterval(function () { msgs[i].classList.remove('on'); i = (i + 1) % msgs.length; msgs[i].classList.add('on'); }, 4200);
      }
    }
  }

  /* free-delivery bar live update (hooks into every cart response) */
  var fd = $('#fdbar');
  function renderFree(f) {
    if (!fd || !f) return;
    var tpl = f.empty ? fd.dataset.tplEmpty : (f.unlocked ? fd.dataset.tplDone : fd.dataset.tplProgress);
    $('.fd-txt', fd).textContent = (tpl || '').split('{amount}').join(f.left_txt).split('{min}').join(f.min_txt);
    $('.fd-track i', fd).style.width = (f.empty ? 0 : f.pct) + '%';
    fd.classList.toggle('done', !!f.unlocked);
  }
  var _fetch = window.fetch;
  window.fetch = function () {
    var p = _fetch.apply(this, arguments);
    try {
      var u = String(arguments[0] && arguments[0].url || arguments[0]);
      if (/\/cart\/(add|update|remove|coupon|mini)/.test(u)) p.then(function (r) { return r.clone().json().then(function (j) { if (j && j.free) renderFree(j.free); }); }).catch(function () {});
    } catch (e) {}
    return p;
  };

  /* popups */
  function popupAllowed(c) {
    var mobile = window.matchMedia('(max-width:820px)').matches;
    if (c.dev === 'desktop' && mobile) return false;
    if (c.dev === 'mobile' && !mobile) return false;
    if (c.preview) return true;
    var k = 'pop_' + c.id;
    if (c.f === 'session') return sstore(k) !== '1';
    if (c.f === 'once') return store(k) === null;
    if (c.f === 'days') { var t = parseInt(store(k) || '0', 10); return !t || Date.now() - t > c.d * 864e5; }
    return true;
  }
  function openPopup(el, c) {
    if (el.dataset.shown) return; el.dataset.shown = '1';
    el.hidden = false; requestAnimationFrame(function () { el.classList.add('show'); });
    if (c.f === 'session') sstore('pop_' + c.id, '1'); else if (c.f === 'once') store('pop_' + c.id, '1'); else if (c.f === 'days') store('pop_' + c.id, String(Date.now()));
    post('/popup/track', { id: c.id, event: 'view' });
  }
  function closePopup(el) { el.classList.remove('show'); setTimeout(function () { el.hidden = true; }, 350); }
  $$('.pop[data-popup]').forEach(function (el) {
    var c; try { c = JSON.parse(el.dataset.popup); } catch (e) { return; }
    if (c.preview) return;
    var close = function () { closePopup(el); };
    $('.pop-x', el).addEventListener('click', close);
    $('.pop-scrim', el).addEventListener('click', close);
    d.addEventListener('keydown', function (e) { if (e.key === 'Escape' && el.classList.contains('show')) close(); });
    var go = function () { if (popupAllowed(c)) openPopup(el, c); };
    if (c.t === 'load') go();
    else if (c.t === 'delay') setTimeout(go, Math.max(0, c.v) * 1000);
    else if (c.t === 'scroll') { var onS = function () { var h = d.documentElement; if ((h.scrollTop + innerHeight) / h.scrollHeight * 100 >= Math.max(1, c.v)) { removeEventListener('scroll', onS); go(); } }; addEventListener('scroll', onS, { passive: true }); }
    else if (c.t === 'exit') {
      var done = false; d.addEventListener('mouseout', function (e) { if (!done && e.clientY <= 0 && !e.relatedTarget) { done = true; go(); } });
      if (matchMedia('(max-width:820px)').matches) setTimeout(go, 25000); // no mouse on touch screens
    } else if (c.t === 'click') {
      d.addEventListener('click', function (e) { var a = e.target.closest('a[href="#popup-' + c.id + '"]'); if (a) { e.preventDefault(); el.dataset.shown = ''; el.hidden = false; requestAnimationFrame(function () { el.classList.add('show'); }); } });
    }
    var cta = $('[data-popup-click]', el); if (cta) cta.addEventListener('click', function () { post('/popup/track', { id: c.id, event: 'click' }); });
  });
  d.addEventListener('click', function (e) {
    var cp = e.target.closest('[data-copy]'); if (!cp) return;
    var code = cp.dataset.copy; if (navigator.clipboard) navigator.clipboard.writeText(code).catch(function () {});
    var s = $('small', cp); if (s) s.textContent = 'copied!';
  });

  /* newsletter / popup subscribe forms */
  d.addEventListener('submit', function (e) {
    var f = e.target.closest('[data-subscribe],[data-popup-subscribe]'); if (!f) return;
    e.preventDefault();
    var pid = f.getAttribute('data-popup-subscribe');
    var btn = $('button', f); if (btn) btn.disabled = true;
    post('/popup/subscribe', { email: $('input[type=email]', f).value, website: ($('.hp', f) || {}).value || '', popup: pid || '' }).then(function (r) {
      if (btn) btn.disabled = false;
      if (pid) {
        var ok = f.parentNode.querySelector('.pop-ok');
        if (r.ok && ok) { f.hidden = true; ok.hidden = false; var el = f.closest('.pop'); var c = JSON.parse(el.dataset.popup); if (c.f !== 'always') store('pop_' + c.id, String(Date.now())); }
        else if (!r.ok) alert(r.error || 'Please check your email address.');
      } else {
        var m = $('.sub-msg', f); if (m) { m.hidden = false; m.textContent = r.ok ? r.message : (r.error || 'Please check your email address.'); }
        if (r.ok) $('input[type=email]', f).value = '';
      }
    });
  });

  /* countdown blocks */
  $$('.bb-count').forEach(function (c) {
    var end = +c.dataset.end; if (!end) return;
    function tick() { var s = Math.max(0, end - Date.now()) / 1000; c.querySelector('[data-u=d]').textContent = Math.floor(s / 86400); c.querySelector('[data-u=h]').textContent = Math.floor(s % 86400 / 3600); c.querySelector('[data-u=m]').textContent = Math.floor(s % 3600 / 60); c.querySelector('[data-u=s]').textContent = Math.floor(s % 60); }
    tick(); setInterval(tick, 1000);
  });

  /* close account menu on outside click */
  d.addEventListener('click', function (e) { var a = $('#acct[open]'); if (a && !a.contains(e.target)) a.removeAttribute('open'); });

  /* abandoned-cart capture: remember the checkout email as soon as it is typed */
  var em = $('.co-grid input[name=email], form[action$="/checkout"] input[name=email]');
  if (em) em.addEventListener('change', function () { if (/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em.value)) post('/checkout/capture', { email: em.value }); });
})();
