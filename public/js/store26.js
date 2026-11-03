/* KIOSK v2.6 storefront: search typeahead, newsletter signup. */
(function () {
  'use strict';
  var d = document, K = window.KIOSK || { base: '' };
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function hl(text, q) {
    var t = esc(text); if (!q) return t;
    var i = String(text).toLowerCase().indexOf(q.toLowerCase()); if (i < 0) return t;
    return esc(text.slice(0, i)) + '<mark>' + esc(text.slice(i, i + q.length)) + '</mark>' + esc(text.slice(i + q.length));
  }

  /* ---------- recent searches ---------- */
  var RK = 'kiosk_recent';
  function recent() { try { return JSON.parse(localStorage.getItem(RK) || '[]').slice(0, 6); } catch (e) { return []; } }
  function remember(q) { q = (q || '').trim(); if (q.length < 2) return; try { var r = recent().filter(function (x) { return x.toLowerCase() !== q.toLowerCase(); }); r.unshift(q); localStorage.setItem(RK, JSON.stringify(r.slice(0, 6))); } catch (e) {} }
  function clearRecent() { try { localStorage.removeItem(RK); } catch (e) {} }

  /* ---------- typeahead ---------- */
  function Typeahead(input, panel, opts) {
    opts = opts || {};
    var form = input.form, ctl = null, timer = null, items = [], idx = -1, lastQ = null;
    panel.setAttribute('role', 'listbox');
    input.setAttribute('aria-expanded', 'false');
    function show(html) { panel.innerHTML = html; panel.hidden = !html; input.setAttribute('aria-expanded', html ? 'true' : 'false'); collect(); }
    function collect() { items = $$('[data-ta]', panel); idx = -1; }
    function setActive(i) {
      items.forEach(function (el, n) { el.classList.toggle('on', n === i); el.setAttribute('aria-selected', n === i ? 'true' : 'false'); });
      idx = i; if (items[i]) { items[i].scrollIntoView({ block: 'nearest' }); }
    }
    function empty() {
      var r = recent(), h = '';
      if (r.length) h += '<div class="ta-h"><span>Recent</span><button type="button" data-clear>Clear</button></div><div class="ta-chips">' + r.map(function (t) { return '<a href="' + K.base + '/search?q=' + encodeURIComponent(t) + '" data-ta data-term="' + esc(t) + '">↺ ' + esc(t) + '</a>'; }).join('') + '</div>';
      return h;
    }
    function popular(list) {
      if (!list || !list.length) return '';
      return '<div class="ta-h"><span>Popular</span></div><div class="ta-chips">' + list.map(function (t) { return '<a href="' + K.base + '/search?q=' + encodeURIComponent(t) + '" data-ta data-term="' + esc(t) + '">' + esc(t) + '</a>'; }).join('') + '</div>';
    }
    function render(j, q) {
      var h = '';
      if (j.terms && j.terms.length) h += '<div class="ta-h"><span>Suggestions</span></div><div class="ta-terms">' + j.terms.map(function (t) { return '<a href="' + K.base + '/search?q=' + encodeURIComponent(t) + '" data-ta data-term="' + esc(t) + '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><span>' + hl(t, q) + '</span></a>'; }).join('') + '</div>';
      if (j.cats && j.cats.length) h += '<div class="ta-h"><span>Departments</span></div><div class="ta-cats">' + j.cats.map(function (c) { return '<a href="' + c.url + '" data-ta style="--c:' + esc(c.color) + '"><i></i><b>' + hl(c.name, q) + '</b><small>' + esc(c.tagline) + '</small></a>'; }).join('') + '</div>';
      if (j.products && j.products.length) {
        if (j.did_you_mean) h += '<div class="ta-dym">No exact match — showing results for <a href="' + K.base + '/search?q=' + encodeURIComponent(j.did_you_mean) + '" data-ta data-term="' + esc(j.did_you_mean) + '">' + esc(j.did_you_mean) + '</a></div>';
        h += '<div class="ta-h"><span>Products</span></div><div class="ta-prods">' + j.products.map(function (p) {
          return '<a class="sr" href="' + p.url + '" data-ta style="--c:' + esc(p.color) + '"><span class="sr-media">' + p.media + '</span><b>' + hl(p.name, q) + '<small>' + esc(p.cat || '') + (p.instant ? ' · ⚡ instant' : '') + '</small></b><span class="sr-price">' + esc(p.price) + (p.compare ? ' <s>' + esc(p.compare) + '</s>' : '') + '</span></a>';
        }).join('') + '</div>';
      }
      if (j.total > 0) h += '<a class="ta-all" href="' + K.base + '/search?q=' + encodeURIComponent(q) + '" data-ta data-term="' + esc(q) + '">See all ' + j.total + ' result' + (j.total === 1 ? '' : 's') + ' for “' + esc(q) + '” →</a>';
      if (!j.total && !(j.products && j.products.length) && !(j.cats && j.cats.length)) {
        h = '<div class="ta-none"><b>No results for “' + esc(q) + '”.</b>' + (j.did_you_mean ? '<br>Did you mean <a href="' + K.base + '/search?q=' + encodeURIComponent(j.did_you_mean) + '" data-ta data-term="' + esc(j.did_you_mean) + '">' + esc(j.did_you_mean) + '</a>?' : '<br><span class="muted">Try a shorter word or check the spelling.</span>') + '</div>' + popular(j.popular);
      }
      show(h);
    }
    function query() {
      var q = input.value.trim();
      if (q === lastQ) return; lastQ = q;
      if (ctl) ctl.abort();
      if (q.length < 2) {
        var h0 = empty(); show(h0);
        fetchJson('', function (j) { if (input.value.trim().length < 2) show(h0 + popular(j.popular)); });
        return;
      }
      fetchJson(q, function (j) { render(j, q); });
    }
    function fetchJson(q, cb) {
      ctl = window.AbortController ? new AbortController() : null;
      fetch(K.base + '/search.json?q=' + encodeURIComponent(q), { credentials: 'same-origin', signal: ctl ? ctl.signal : undefined }).then(function (r) { return r.json(); }).then(cb).catch(function () {});
    }
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(query, 120); });
    input.addEventListener('focus', function () { lastQ = null; query(); });
    input.addEventListener('keydown', function (e) {
      if (panel.hidden || !items.length) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); setActive((idx + 1) % items.length); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(idx <= 0 ? items.length - 1 : idx - 1); }
      else if (e.key === 'Enter' && idx >= 0) { e.preventDefault(); go(items[idx]); }
    });
    form.addEventListener('submit', function () { remember(input.value); });
    panel.addEventListener('mousemove', function (e) { var a = e.target.closest('[data-ta]'); if (a) { var i = items.indexOf(a); if (i !== idx) setActive(i); } });
    panel.addEventListener('click', function (e) {
      if (e.target.closest('[data-clear]')) { clearRecent(); lastQ = null; query(); return; }
      var a = e.target.closest('[data-ta]'); if (!a) return;
      if (a.dataset.term) { e.preventDefault(); remember(a.dataset.term); location.href = a.href; return; }
      remember(input.value);
    });
    function go(a) { if (a.dataset.term) remember(a.dataset.term); location.href = a.href; }
    return { reset: function () { lastQ = null; input.value = ''; show(''); }, refresh: function () { lastQ = null; query(); }, hide: function () { show(''); } };
  }

  /* overlay: stop the legacy store.js handler, run ours */
  var si0 = $('#search-input');
  if (si0) { var si1 = si0.cloneNode(true); si0.parentNode.replaceChild(si1, si0); }
  var si = $('#search-input'), sr = $('#search-results'), sv = $('#search');
  if (si && sr) {
    var ta = Typeahead(si, sr);
    var open = $('#search-open');
    function refocus() { setTimeout(function () { si.focus(); ta.refresh(); }, 60); }
    if (open) open.addEventListener('click', refocus);
    d.addEventListener('keydown', function (e) { if (e.key === '/' && !/input|textarea|select/i.test(e.target.tagName)) refocus(); });
    if (sv) new MutationObserver(function () { if (sv.hidden) ta.hide(); }).observe(sv, { attributes: true, attributeFilter: ['hidden'] });
  }
  /* inline field on /search */
  $$('form[data-typeahead]').forEach(function (f) {
    var inp = $('input[type=search]', f); if (!inp) return;
    var p = d.createElement('div'); p.className = 'ta-drop'; p.hidden = true; f.appendChild(p);
    var t = Typeahead(inp, p, { noPopular: false });
    d.addEventListener('click', function (e) { if (!f.contains(e.target)) t.hide(); });
    inp.addEventListener('keydown', function (e) { if (e.key === 'Escape') t.hide(); });
  });

  /* ---------- newsletter forms ---------- */
  d.addEventListener('submit', function (e) {
    var f = e.target.closest ? e.target.closest('form[data-news]') : null; if (!f) return;
    e.preventDefault();
    var btn = $('button', f), msg = $('.sub-msg', f), fd = new FormData(f);
    fd.append('_token', K.csrf);
    if (btn) btn.disabled = true;
    fetch(K.base + '/newsletter/subscribe', { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { if (r.status === 429) j = { error: 'Too many attempts — please try again in a minute.' }; return { ok: r.ok && j.ok, j: j }; }); })
      .then(function (x) {
        if (msg) { msg.hidden = false; msg.textContent = x.ok ? x.j.message : (x.j.error || 'Something went wrong.'); msg.className = 'sub-msg ' + (x.ok ? 'ok' : 'bad'); }
        if (x.ok) { f.classList.add('done'); $$('input[type=email]', f).forEach(function (i) { i.value = ''; }); }
        if (btn) btn.disabled = false;
      }).catch(function () { if (msg) { msg.hidden = false; msg.textContent = 'Network error — try again.'; msg.className = 'sub-msg bad'; } if (btn) btn.disabled = false; });
  }, true);
})();
