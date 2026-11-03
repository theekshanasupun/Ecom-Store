/*
 * KIOSK admin v2.6 — shared UI components
 * ------------------------------------------------------------------------------------------
 * 1. Sidebar: accordion groups (active group open), auto-fold to an icon rail when idle (pin button keeps it open).
 * 2. DRAWER   <button type="button" data-drawer-open="#id">New</button>
 *             <div class="drawer-panel [dp-wide]" id="id" hidden [data-autoopen]>
 *               <form method="post" …>   (optional — form uses display:contents so head/body/foot lay out normally)
 *                 <div class="dp-head"><h2>Title</h2><button type="button" data-drawer-close>✕</button></div>
 *                 <div class="dp-body">…</div>
 *                 <div class="dp-foot"><button class="btn">Save</button></div>
 *               </form></div>
 *             Opens from the right with a scrim; Esc / scrim click closes; focus is trapped. Opens automatically
 *             when the panel has data-autoopen or when location.hash === '#id'. Closing removes ?edit= / ?new= from the URL.
 * 3. TABS     <div class="tabs2" data-tabs><button type="button" data-tab="a" class="on">Basics</button>…</div>
 *             <section data-pane="a">…</section><section data-pane="b" hidden>…</section>   (panes = siblings of the tab bar)
 *             A tab gets a red dot when a required/invalid field inside its pane fails validation.
 * 4. STICKY SAVE BAR  <div class="stickybar">…buttons…</div>       LIVE PANE  <aside class="livepane">…</aside>
 *    JUMP CHIPS       <nav class="jump" data-jump><a href="#sec-a">A</a>…</nav> + <details class="apanel fold" id="sec-a" open><summary><h2>A</h2></summary><div class="fold-in">…</div></details>
 * 5. SEARCH-PICK  <div class="sp" data-search-pick> <div class="sp-top"><input type="search" data-pick-filter placeholder="Filter…"></div>
 *                   <div class="pick"><label><input type="checkbox" name="x[]" value="1"><span>Name</span></label>…</div></div>
 *                 JS adds select-visible / clear buttons, selected chips and a counter.
 * 6. KioskAdmin.toast(msg, 'ok'|'err'), KioskAdmin.drawer.open('#id') / .close(), KioskAdmin.post(url, FormData|obj) → Promise<json>
 *    [data-confirm="Message"] on a button/link or form asks before continuing. input[data-tablefilter="#tableId"] live-filters rows.
 * 7. Global admin search (Ctrl+K or "/"), coupon editor (live summary + preview, code generator).
 */
(function () {
  'use strict';
  var d = document, w = window;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var store = {
    get: function (k) { try { return w.localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { w.localStorage.setItem(k, v); } catch (e) {} }
  };
  var csrf = function () { var m = $('input[name=_token]'); return m ? m.value : ''; };

  var K = w.KioskAdmin = w.KioskAdmin || {};

  /* ---------------------------------------------------------------- toast */
  K.toast = function (msg, type) {
    var box = $('#toasts2');
    if (!box) { box = d.createElement('div'); box.id = 'toasts2'; box.className = 'toasts2'; box.setAttribute('aria-live', 'polite'); d.body.appendChild(box); }
    var t = d.createElement('div'); t.className = 'toast2' + (type === 'err' ? ' err' : ''); t.textContent = msg; box.appendChild(t);
    setTimeout(function () { t.classList.add('out'); setTimeout(function () { t.remove(); }, 300); }, 3200);
  };
  K.post = function (url, data) {
    var fd = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData) && data) Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    if (!fd.has('_token')) fd.append('_token', csrf());
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Unexpected response (' + r.status + ')' }; }); });
  };

  /* ---------------------------------------------------------------- confirm */
  d.addEventListener('click', function (e) {
    var el = e.target.closest('[data-confirm]');
    if (!el || el.tagName === 'FORM') return;
    if (!confirm(el.getAttribute('data-confirm'))) { e.preventDefault(); e.stopPropagation(); }
  }, true);
  d.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.matches && f.matches('form[data-confirm]') && !confirm(f.getAttribute('data-confirm'))) e.preventDefault();
  }, true);

  /* [data-post-url] — a button that POSTs (with CSRF) and follows the redirect; usable inside other forms */
  d.addEventListener('click', function (e) {
    var b = e.target.closest('[data-post-url]'); if (!b) return;
    e.preventDefault();
    var f = d.createElement('form'); f.method = 'post'; f.action = b.getAttribute('data-post-url'); f.style.display = 'none';
    var t = d.createElement('input'); t.type = 'hidden'; t.name = '_token'; t.value = csrf(); f.appendChild(t);
    var extra = b.getAttribute('data-post-fields'); if (extra) { try { var o = JSON.parse(extra); Object.keys(o).forEach(function (k) { var i = d.createElement('input'); i.type = 'hidden'; i.name = k; i.value = o[k]; f.appendChild(i); }); } catch (x) {} }
    d.body.appendChild(f); f.submit();
  });

  /* ---------------------------------------------------------------- sidebar */
  (function () {
    var adm = $('#adm'), side = $('#side');
    if (!adm || !side) return;
    var mobile = function () { return w.matchMedia('(max-width:800px)').matches; };
    var groups = $$('.ng', side), pin = $('#pin');
    var pinned = store.get('k26pin') === '1';
    var railT = null, groupT = null;
    function setPin(v) { pinned = v; store.set('k26pin', v ? '1' : '0'); if (pin) pin.setAttribute('aria-pressed', v ? 'true' : 'false'); if (v) { clearTimeout(railT); adm.classList.remove('rail'); side.classList.remove('peek'); } else scheduleRail(2500); }
    function openGroup(g, open) {
      g.classList.toggle('open', open);
      var h = $('.ng-h', g); if (h) h.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    groups.forEach(function (g) {
      var h = $('.ng-h', g);
      h.addEventListener('click', function () {
        var willOpen = !g.classList.contains('open');
        groups.forEach(function (o) { if (o !== g) openGroup(o, false); });
        openGroup(g, willOpen);
        if (adm.classList.contains('rail')) side.classList.add('peek');
        touch();
      });
    });
    function foldGroups() { groups.forEach(function (g) { openGroup(g, g.getAttribute('data-active') === '1'); }); }
    // open groups fall back to the active one after a while with no menu activity
    function touch() { clearTimeout(groupT); groupT = setTimeout(foldGroups, 25000); }
    function scheduleRail(ms) {
      clearTimeout(railT);
      if (pinned || mobile()) return;
      railT = setTimeout(function () { if (!side.matches(':hover') && !side.contains(d.activeElement)) { adm.classList.add('rail'); side.classList.remove('peek'); foldGroups(); } }, ms);
    }
    side.addEventListener('mouseenter', function () { clearTimeout(railT); if (adm.classList.contains('rail')) side.classList.add('peek'); touch(); });
    side.addEventListener('mouseleave', function () { side.classList.remove('peek'); scheduleRail(1500); });
    side.addEventListener('focusin', function () { clearTimeout(railT); if (adm.classList.contains('rail')) side.classList.add('peek'); });
    side.addEventListener('focusout', function () { setTimeout(function () { if (!side.contains(d.activeElement) && !side.matches(':hover')) { side.classList.remove('peek'); scheduleRail(1500); } }, 50); });
    if (pin) { pin.setAttribute('aria-pressed', pinned ? 'true' : 'false'); pin.addEventListener('click', function () { setPin(!pinned); K.toast(pinned ? 'Menu pinned open' : 'Menu will fold away when not in use'); }); }
    w.addEventListener('resize', function () { if (mobile()) { adm.classList.remove('rail'); side.classList.remove('peek'); } });
    scheduleRail(4000);
    touch();
  })();

  /* ---------------------------------------------------------------- drawer */
  var scrim = null, current = null, lastFocus = null;
  function focusables(p) { return $$('a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])', p).filter(function (x) { return x.offsetParent !== null; }); }
  function openDrawer(sel) {
    var p = typeof sel === 'string' ? $(sel) : sel;
    if (!p) return;
    if (current && current !== p) closeDrawer(true);
    lastFocus = d.activeElement;
    if (!scrim) { scrim = d.createElement('div'); scrim.className = 'dscrim'; scrim.addEventListener('click', function () { closeDrawer(); }); d.body.appendChild(scrim); }
    scrim.hidden = false; p.hidden = false; d.body.classList.add('noscroll'); current = p;
    void p.offsetWidth; p.classList.add('open'); scrim.classList.add('on');
    setTimeout(function () { var f = $('[autofocus],input:not([type=hidden]):not([type=checkbox]):not([type=radio]),select,textarea', $('.dp-body', p) || p); if (f && !mobileSmall()) f.focus({ preventScroll: true }); }, 200);
    p.dispatchEvent(new CustomEvent('drawer:open', { bubbles: true }));
  }
  function mobileSmall() { return w.matchMedia('(max-width:640px)').matches; }
  function closeDrawer(instant) {
    var p = current; if (!p) return;
    current = null; p.classList.remove('open'); if (scrim) scrim.classList.remove('on'); d.body.classList.remove('noscroll');
    var done = function () { p.hidden = true; if (scrim && !current) scrim.hidden = true; };
    instant ? done() : setTimeout(done, 280);
    try {
      var u = new URL(location.href), ch = false;
      ['edit', 'new', 'add'].forEach(function (k) { if (u.searchParams.has(k)) { u.searchParams.delete(k); ch = true; } });
      if (u.hash === '#' + p.id) { u.hash = ''; ch = true; }
      if (ch) history.replaceState(null, '', u.pathname + (u.search || '') + u.hash);
    } catch (e) {}
    if (lastFocus && lastFocus.focus) { try { lastFocus.focus({ preventScroll: true }); } catch (e) {} }
    p.dispatchEvent(new CustomEvent('drawer:close', { bubbles: true }));
  }
  K.drawer = { open: openDrawer, close: closeDrawer };
  d.addEventListener('click', function (e) {
    var o = e.target.closest('[data-drawer-open]');
    if (o) { e.preventDefault(); openDrawer(o.getAttribute('data-drawer-open')); return; }
    if (e.target.closest('[data-drawer-close]')) { e.preventDefault(); closeDrawer(); }
  });
  d.addEventListener('keydown', function (e) {
    if (!current) return;
    if (e.key === 'Escape') { e.preventDefault(); closeDrawer(); }
    else if (e.key === 'Tab') {
      var f = focusables(current); if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && d.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && d.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });
  $$('.drawer-panel').forEach(function (p) {
    if (p.hasAttribute('data-autoopen') || (location.hash && location.hash === '#' + p.id)) openDrawer(p);
  });

  /* ---------------------------------------------------------------- tabs */
  $$('[data-tabs]').forEach(function (bar) {
    var scope = bar.parentNode;
    var btns = $$('[data-tab]', bar);
    var panes = $$('[data-pane]', scope).filter(function (p) { return p.parentNode === scope; });
    function show(name) {
      btns.forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-tab') === name); });
      panes.forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== name; });
    }
    btns.forEach(function (b) { b.addEventListener('click', function () { show(b.getAttribute('data-tab')); }); });
    var form = bar.closest('form');
    if (form) form.addEventListener('invalid', function (e) {
      var pane = e.target.closest('[data-pane]'); if (!pane) return;
      var name = pane.getAttribute('data-pane');
      btns.forEach(function (b) { if (b.getAttribute('data-tab') === name) b.classList.add('bad'); });
      if (pane.hidden) show(name);
    }, true);
    if (form) form.addEventListener('input', function (e) {
      var pane = e.target.closest('[data-pane]'); if (!pane || !e.target.validity || !e.target.validity.valid) return;
      if (!$$(':invalid', pane).length) btns.forEach(function (b) { if (b.getAttribute('data-tab') === pane.getAttribute('data-pane')) b.classList.remove('bad'); });
    });
    var start = btns.filter(function (b) { return b.classList.contains('on'); })[0] || btns[0];
    if (start) show(start.getAttribute('data-tab'));
  });

  /* ---------------------------------------------------------------- jump chips (scroll-spy) */
  $$('[data-jump]').forEach(function (nav) {
    var links = $$('a[href^="#"]', nav);
    links.forEach(function (a) {
      a.addEventListener('click', function (e) {
        var t = $(a.getAttribute('href')); if (!t) return; e.preventDefault();
        if (t.tagName === 'DETAILS') t.open = true;
        t.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
    if (!('IntersectionObserver' in w)) return;
    var map = {};
    var io = new IntersectionObserver(function (es) {
      es.forEach(function (en) { map[en.target.id] = en.isIntersecting; });
      var first = links.filter(function (a) { return map[a.getAttribute('href').slice(1)]; })[0];
      if (first) links.forEach(function (a) { a.classList.toggle('on', a === first); });
    }, { rootMargin: '-120px 0px -55% 0px' });
    links.forEach(function (a) { var t = $(a.getAttribute('href')); if (t) io.observe(t); });
  });

  /* ---------------------------------------------------------------- search-pick */
  $$('[data-search-pick]').forEach(function (sp) {
    var filter = $('[data-pick-filter]', sp), list = $('.pick', sp), top = $('.sp-top', sp);
    if (!list) return;
    var chips = d.createElement('div'); chips.className = 'sp-chips'; sp.insertBefore(chips, list);
    var meta = d.createElement('div'); meta.className = 'sp-meta'; sp.appendChild(meta);
    var boxes = function () { return $$('input[type=checkbox]', list); };
    var label = function (b) { var s = $('span', b.closest('label')); return (s ? s.textContent : b.value).trim(); };
    if (top) {
      var all = d.createElement('button'); all.type = 'button'; all.textContent = 'Select visible';
      var none = d.createElement('button'); none.type = 'button'; none.textContent = 'Clear';
      all.addEventListener('click', function () { boxes().forEach(function (b) { if (!b.closest('label').hidden) b.checked = true; }); render(); });
      none.addEventListener('click', function () { boxes().forEach(function (b) { b.checked = false; }); render(); });
      top.appendChild(all); top.appendChild(none);
    }
    function render() {
      var on = boxes().filter(function (b) { return b.checked; });
      chips.innerHTML = '';
      on.slice(0, 40).forEach(function (b) {
        var s = d.createElement('span'); s.innerHTML = esc(label(b).slice(0, 40)) + '<button type="button" aria-label="Remove">✕</button>';
        $('button', s).addEventListener('click', function () { b.checked = false; render(); });
        chips.appendChild(s);
      });
      if (on.length > 40) { var m = d.createElement('span'); m.textContent = '+' + (on.length - 40) + ' more'; chips.appendChild(m); }
      var vis = boxes().filter(function (b) { return !b.closest('label').hidden; }).length;
      meta.innerHTML = '<span>' + on.length + ' selected</span><span>' + vis + ' shown</span>';
      sp.dispatchEvent(new CustomEvent('pick:change', { bubbles: true }));
    }
    if (filter) filter.addEventListener('input', function () {
      var q = filter.value.toLowerCase();
      $$('label', list).forEach(function (l) { l.hidden = q && l.textContent.toLowerCase().indexOf(q) === -1; });
      render();
    });
    list.addEventListener('change', render);
    render();
  });

  /* ---------------------------------------------------------------- table filter */
  $$('input[data-tablefilter]').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var tbl = $(inp.getAttribute('data-tablefilter')); if (!tbl) return;
      var q = inp.value.toLowerCase();
      $$('tbody tr', tbl).forEach(function (tr) { if (tr.querySelector('td[colspan]')) return; tr.hidden = q && tr.textContent.toLowerCase().indexOf(q) === -1; });
    });
  });

  /* ---------------------------------------------------------------- generic switch (AJAX toggle) */
  d.addEventListener('change', function (e) {
    var sw = e.target.closest('[data-switch-url]'); if (!sw) return;
    var wrap = sw.closest('.switch'); if (wrap) wrap.classList.add('busy');
    K.post(sw.getAttribute('data-switch-url'), { value: sw.checked ? '1' : '0' }).then(function (j) {
      if (wrap) wrap.classList.remove('busy');
      if (!j.ok) { sw.checked = !sw.checked; K.toast(j.error || 'Could not update', 'err'); } else K.toast(j.message || 'Updated');
    }).catch(function () { if (wrap) wrap.classList.remove('busy'); sw.checked = !sw.checked; K.toast('Network error', 'err'); });
  });

  /* ---------------------------------------------------------------- global search */
  (function () {
    var box = $('#gsearch'), inp = $('#gs-input'), pop = $('#gs-pop');
    if (!box || !inp) return;
    var url = box.getAttribute('data-url'), timer = null, items = [], idx = -1, seq = 0;
    var RK = 'k26recent';
    var recents = function () { try { return JSON.parse(store.get(RK) || '[]'); } catch (e) { return []; } };
    var addRecent = function (q) { if (!q) return; var r = recents().filter(function (x) { return x !== q; }); r.unshift(q); store.set(RK, JSON.stringify(r.slice(0, 6))); };
    var hl = function (t, q) { t = esc(t); if (!q) return t; var re = new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig'); return t.replace(re, '<mark>$1</mark>'); };
    function open() { pop.hidden = false; inp.setAttribute('aria-expanded', 'true'); }
    function close() { pop.hidden = true; inp.setAttribute('aria-expanded', 'false'); idx = -1; }
    function row(it, q) {
      return '<a class="gs-i" role="option" href="' + esc(it.url) + '" data-q="' + esc(q) + '"><span class="gi">' + (it.img ? '<img src="' + esc(it.img) + '" alt="">' : esc(it.icon || '•')) + '</span><span class="gt"><b>' + hl(it.label, q) + '</b>' + (it.sub ? '<small>' + hl(it.sub, q) + '</small>' : '') + '</span>' + (it.go ? '<span class="go">' + esc(it.go) + '</span>' : '') + '</a>';
    }
    function render(groups, q) {
      var h = '';
      groups.forEach(function (g) { if (!g.items.length) return; h += '<div class="gs-h"><span>' + esc(g.title) + '</span>' + (g.clear ? '<button type="button" data-clear>Clear</button>' : '') + '</div>'; g.items.forEach(function (it) { h += row(it, q); }); });
      pop.innerHTML = h || '<div class="gs-empty">No results for “' + esc(q) + '”</div>';
      items = $$('.gs-i', pop); idx = -1; open();
    }
    function idle() {
      var r = recents().map(function (x) { return { label: x, icon: '↺', url: '#', go: '', recent: x }; });
      var root = url.replace('search.json', '');
      var base = [
        { label: 'Create order', icon: '＋', url: root + 'orders/new', go: 'Sales' },
        { label: 'New product', icon: '＋', url: root + 'products/new', go: 'Catalog' },
        { label: 'New coupon', icon: '＋', url: root + 'coupons?new=1', go: 'Promotions' }
      ];
      var g = [{ title: 'Quick actions', items: base }];
      if (r.length) g.unshift({ title: 'Recent searches', items: r, clear: true });
      render(g, '');
    }
    function run() {
      var q = inp.value.trim();
      if (q.length < 2) { idle(); return; }
      var my = ++seq;
      fetch(url + '?q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) {
        if (my !== seq) return; render(j.groups || [], q);
      }).catch(function () {});
    }
    inp.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(run, 140); });
    inp.addEventListener('focus', function () { if (inp.value.trim().length < 2) idle(); else run(); });
    inp.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { close(); inp.blur(); return; }
      if (pop.hidden || !items.length) { if (e.key === 'Enter') e.preventDefault(); return; }
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault(); idx = (idx + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
        items.forEach(function (x, i) { x.classList.toggle('on', i === idx); }); items[idx].scrollIntoView({ block: 'nearest' });
      } else if (e.key === 'Enter') {
        e.preventDefault(); var t = items[idx >= 0 ? idx : 0]; if (t) t.click();
      }
    });
    pop.addEventListener('mousedown', function (e) { e.preventDefault(); });
    pop.addEventListener('click', function (e) {
      var c = e.target.closest('[data-clear]'); if (c) { store.set(RK, '[]'); idle(); return; }
      var a = e.target.closest('.gs-i'); if (!a) return;
      var href = a.getAttribute('href');
      if (href === '#') { e.preventDefault(); var rr = $('b', a).textContent; inp.value = rr; run(); return; }
      var q = a.getAttribute('data-q'); if (q) addRecent(q);
    });
    d.addEventListener('click', function (e) { if (!box.contains(e.target)) close(); });
    d.addEventListener('keydown', function (e) {
      var tag = (e.target.tagName || '').toLowerCase();
      var typing = tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable;
      if (((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') || (!typing && e.key === '/')) { e.preventDefault(); inp.focus(); inp.select(); }
    });
  })();

  /* ---------------------------------------------------------------- coupon editor */
  (function () {
    var form = $('#coupon-form'); if (!form) return;
    var money = form.getAttribute('data-sym') || '$';
    var val = function (n) { var el = form.elements[n]; return el ? el.value : ''; };
    var chk = function (n) { var els = $$('[name="' + n + '"]', form); return els.some(function (x) { return x.type === 'checkbox' && x.checked; }); };
    var fmt = function (v) { v = parseFloat(v || 0); return money + (v % 1 ? v.toFixed(2) : v.toFixed(0)); };
    var typeCards = $$('.typecard', form), kind = $('input[name=kind]', form);
    function setKind(k) {
      // cart | products | categories | shipping | auto  → maps onto the real fields
      typeCards.forEach(function (c) { c.classList.toggle('on', c.getAttribute('data-kind') === k); });
      kind.value = k;
      var scope = form.elements.scope, fs = form.elements.free_shipping_only, auto = $('input[name=auto_apply][type=checkbox]', form);
      if (k === 'products' || k === 'categories') scope.value = k; else scope.value = 'cart';
      if (fs) fs.value = k === 'shipping' ? '1' : '0';
      if (k === 'auto' && auto) auto.checked = true;
      $$('[data-scope]', form).forEach(function (b) { b.hidden = b.getAttribute('data-scope') !== scope.value; });
      $$('[data-only]', form).forEach(function (b) { b.hidden = b.getAttribute('data-only').split(' ').indexOf(k) === -1; });
      refresh();
    }
    typeCards.forEach(function (c) { c.addEventListener('click', function (e) { e.preventDefault(); setKind(c.getAttribute('data-kind')); }); });
    var gen = $('[data-gen-code]', form);
    if (gen) gen.addEventListener('click', function () {
      var pre = (val('code_prefix') || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
      var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', s = '';
      var arr = new Uint32Array(6); (w.crypto || w.msCrypto).getRandomValues(arr);
      for (var i = 0; i < 6; i++) s += chars[arr[i] % chars.length];
      form.elements.code.value = (pre ? pre + '-' : '') + s; refresh();
    });
    function names(sel) { return $$(sel + ' input:checked + span', form).map(function (x) { return x.textContent.split(' · ')[0]; }); }
    function refresh() {
      var k = kind.value, type = val('type'), v = parseFloat(val('value') || 0);
      var disc = type === 'percent' ? (v ? v + '%' : '…') : (v ? fmt(v) : '…');
      var what = 'the whole cart';
      if (k === 'products') { var n = names('[data-scope=products]'); what = n.length ? (n.length > 2 ? n.slice(0, 2).join(', ') + ' +' + (n.length - 2) + ' more' : n.join(' & ')) : 'selected products'; }
      if (k === 'categories') { var c = names('[data-scope=categories]'); what = c.length ? c.join(' & ') : 'selected departments'; }
      var parts = [];
      var fsOnly = k === 'shipping';
      var head = fsOnly ? 'Free shipping' : (disc + ' off ' + what) + (chk('free_shipping') ? ' + free shipping' : '');
      var rules = [];
      if (val('min_total')) rules.push('min spend ' + fmt(val('min_total')));
      if (parseInt(val('min_qty'), 10) > 0) rules.push('min ' + val('min_qty') + ' items');
      if (val('max_discount')) rules.push('capped at ' + fmt(val('max_discount')));
      if (chk('exclude_sale')) rules.push('not on sale items');
      if (chk('first_order_only')) rules.push('first order only');
      if (parseInt(val('per_user_limit'), 10) > 0) rules.push(val('per_user_limit') + ' use' + (val('per_user_limit') > 1 ? 's' : '') + ' per customer');
      if (parseInt(val('max_uses'), 10) > 0) rules.push(val('max_uses') + ' uses in total');
      if (val('starts_at') || val('ends_at')) rules.push((val('starts_at') || 'now') + ' → ' + (val('ends_at') || 'no end'));
      var sum = $('#cpn-sum'); if (sum) sum.innerHTML = '<b>' + esc(head) + '</b>' + (rules.length ? ' · ' + esc(rules.join(' · ')) : '') + (k === 'auto' || chk('auto_apply') ? ' · <b>applied automatically</b>' : '');
      var card = $('#cpn-card');
      if (card) {
        $('.off', card).textContent = fsOnly ? 'FREE SHIPPING' : (type === 'percent' ? (v || 0) + '% OFF' : fmt(v || 0) + ' OFF');
        $('.what', card).textContent = fsOnly ? 'on your order' : 'on ' + what + (chk('free_shipping') ? ' + free shipping' : '');
        $('.code', card).textContent = (val('code') || 'YOURCODE').toUpperCase();
        $('.code', card).style.display = (k === 'auto' || chk('auto_apply')) ? 'none' : '';
        $('.fine', card).textContent = rules.slice(0, 3).join(' · ') || 'No restrictions';
      }
    }
    form.addEventListener('input', refresh); form.addEventListener('change', refresh); form.addEventListener('pick:change', refresh);
    var initial = kind.value || 'cart'; setKind(initial);
    // auto-open "new" / "edit" via ?new=1 handled by data-autoopen on the panel
  })();

  /* ---------------------------------------------------------------- small live previews */
  (function () {
    var cp = $('#catprev');
    if (cp) {
      var f = cp.closest('form'), t = $('#catprev-t');
      var up = function () { cp.style.background = f.elements.color.value; var h = f.elements.headline.value || f.elements.name.value || 'DEPARTMENT'; t.textContent = h; };
      f.addEventListener('input', up);
    }
    var so = $('[data-slug-out]');
    if (so) {
      var pf = so.closest('form'), slugify = function (x) { return String(x).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''); };
      var up2 = function () { so.textContent = slugify(pf.elements.slug.value || pf.elements.title.value) || '…'; };
      pf.addEventListener('input', up2); up2();
    }
  })();

  /* ---------------------------------------------------------------- legacy hooks: flash → toast (keeps flash visible too) */
})();
