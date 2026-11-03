/* KIOSK v2.6 admin — variants editor, discount/offer/gift-card helpers. */
(function () {
  'use strict';
  var d = document;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  /* ================= variants editor ================= */
  var root = $('[data-variants-editor]');
  if (root) (function () {
    var D = JSON.parse($('[data-vdata]', root).textContent);
    var opts = (D.options || []).map(function (o) { return { name: o.name, type: o.type || 'button', values: (o.values || []).slice(), colors: Object.assign({}, o.colors || {}) }; });
    var vars = {}; (D.variants || []).forEach(function (v) { vars[v.key] = v; });
    var oBox = $('[data-vopts]', root), rows = $('[data-vrows]', root), wrap = $('[data-vwrap]', root), hint = $('[data-vhint]', root);
    var oj = $('[data-ojson]', root), vj = $('[data-vjson]', root);
    var NAMED = { bone: '#f3f1ec', black: '#1a1a1f', white: '#ffffff', red: '#e5383b', green: '#3a9d5d', blue: '#3b6cff', pink: '#ff5aa5', yellow: '#ffd60a', grey: '#8d8d95', gray: '#8d8d95', navy: '#1d2b53', moss: '#4b5b3d', sky: '#8db7ff', tomato: '#ff7a2f', sand: '#e4dcc8', orange: '#ff7a2f', purple: '#8b7bff' };
    function guess(v) { return NAMED[String(v).toLowerCase()] || '#8d8d95'; }

    function combos() {
      var live = opts.filter(function (o) { return o.name && o.values.length; });
      if (!live.length) return [];
      var out = [[]];
      live.forEach(function (o) { var n = []; out.forEach(function (c) { o.values.forEach(function (v) { n.push(c.concat([[o.name, v]])); }); }); out = n; });
      return out.slice(0, 300).map(function (c) { var op = {}; c.forEach(function (p) { op[p[0]] = p[1]; }); return { key: c.map(function (p) { return p[1]; }).join('|'), opts: op }; });
    }
    function sync() {
      var list = combos();
      list.forEach(function (c) { var old = vars[c.key]; vars[c.key] = Object.assign({ price: '', compare: '', stock: '', sku: '', image: '', active: true }, old || {}, { key: c.key, opts: c.opts }); });
      oj.value = JSON.stringify(opts.filter(function (o) { return o.name && o.values.length; }));
      vj.value = JSON.stringify(list.map(function (c) { return vars[c.key]; }));
      wrap.hidden = !list.length;
      hint.textContent = list.length ? list.length + ' variant' + (list.length === 1 ? '' : 's') : 'No options yet — this product has a single price & stock.';
      return list;
    }
    function renderRows(list) {
      var imgs = D.images || [];
      rows.innerHTML = list.map(function (c) {
        var v = vars[c.key], label = Object.keys(c.opts).map(function (k) { return c.opts[k]; }).join(' / ');
        var sw = opts.map(function (o) { return o.type === 'color' && c.opts[o.name] ? '<i class="vsw" style="background:' + esc(o.colors[c.opts[o.name]] || guess(c.opts[o.name])) + '"></i>' : ''; }).join('');
        var im = '<select data-f="image"><option value="">—</option>' + imgs.map(function (i) { return '<option value="' + esc(i.path) + '"' + (v.image === i.path ? ' selected' : '') + '>' + esc(i.path.split('/').pop().slice(-18)) + '</option>'; }).join('') + '</select>';
        return '<tr data-k="' + esc(c.key) + '"><td><input type="checkbox" data-f="active"' + (v.active !== false ? ' checked' : '') + '></td><td><b>' + sw + esc(label) + '</b></td>' +
          '<td><input type="number" step="0.01" min="0" data-f="price" value="' + esc(v.price) + '" placeholder="' + esc(D.sym) + '"></td>' +
          '<td><input type="number" step="0.01" min="0" data-f="compare" value="' + esc(v.compare) + '"></td>' +
          '<td><input type="number" min="0" data-f="stock" value="' + esc(v.stock) + '" placeholder="shared"></td>' +
          '<td><input data-f="sku" value="' + esc(v.sku) + '"></td><td>' + (imgs.length ? im : '<span class="muted small">save images first</span>') + '</td></tr>';
      }).join('');
    }
    function renderOpts() {
      oBox.innerHTML = opts.map(function (o, i) {
        var chips = o.values.map(function (v, j) {
          return '<span class="vchip">' + (o.type === 'color' ? '<input type="color" data-i="' + i + '" data-j="' + j + '" data-act="col" value="' + esc(o.colors[v] || guess(v)) + '">' : '') + esc(v) + '<button type="button" data-act="rmval" data-i="' + i + '" data-j="' + j + '" aria-label="Remove">✕</button></span>';
        }).join('');
        return '<div class="vopt" data-i="' + i + '"><div class="vopt-head"><input data-act="name" data-i="' + i + '" value="' + esc(o.name) + '" placeholder="Option name (Colour, Size…)"><select data-act="type" data-i="' + i + '"><option value="button"' + (o.type === 'button' ? ' selected' : '') + '>Buttons</option><option value="color"' + (o.type === 'color' ? ' selected' : '') + '>Colour swatches</option><option value="select"' + (o.type === 'select' ? ' selected' : '') + '>Dropdown</option></select><button type="button" class="btn sm ghost danger" data-act="rm" data-i="' + i + '">Remove</button></div>' +
          '<div class="vvals">' + chips + '<input class="vadd" data-act="addval" data-i="' + i + '" placeholder="Type a value and press Enter (or paste: Red, Blue, Green)"></div></div>';
      }).join('');
    }
    function all() { var l = sync(); renderRows(l); }
    oBox.addEventListener('keydown', function (e) {
      if (e.target.dataset.act === 'addval' && (e.key === 'Enter' || e.key === ',')) {
        e.preventDefault(); var o = opts[+e.target.dataset.i];
        e.target.value.split(',').map(function (x) { return x.trim(); }).filter(Boolean).forEach(function (v) { if (o.values.indexOf(v) < 0) { o.values.push(v); if (o.type === 'color') o.colors[v] = guess(v); } });
        renderOpts(); all(); var f = $('.vadd[data-i="' + e.target.dataset.i + '"]', oBox); if (f) f.focus();
      }
    });
    oBox.addEventListener('input', function (e) {
      var a = e.target.dataset.act, o = opts[+e.target.dataset.i]; if (!o) return;
      if (a === 'name') { o.name = e.target.value; clearTimeout(oBox._t); oBox._t = setTimeout(function () { var l = sync(); renderRows(l); }, 300); }
      if (a === 'col') { o.colors[o.values[+e.target.dataset.j]] = e.target.value; var l = sync(); renderRows(l); }
    });
    oBox.addEventListener('change', function (e) {
      var a = e.target.dataset.act, o = opts[+e.target.dataset.i]; if (!o) return;
      if (a === 'type') { o.type = e.target.value; if (o.type === 'color') o.values.forEach(function (v) { if (!o.colors[v]) o.colors[v] = guess(v); }); renderOpts(); all(); }
    });
    oBox.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-act]'); if (!b) return; var i = +b.dataset.i;
      if (b.dataset.act === 'rm') { opts.splice(i, 1); }
      if (b.dataset.act === 'rmval') { var o = opts[i]; o.values.splice(+b.dataset.j, 1); }
      renderOpts(); all();
    });
    $('[data-add-option]', root).addEventListener('click', function () {
      if (opts.length >= 3) { alert('Up to 3 options per product.'); return; }
      opts.push({ name: opts.length ? '' : 'Colour', type: opts.length ? 'button' : 'color', values: [], colors: {} }); renderOpts(); all();
      var f = $('.vopt:last-child input', oBox); if (f) f.focus();
    });
    rows.addEventListener('input', function (e) {
      var tr = e.target.closest('tr'), f = e.target.dataset.f; if (!tr || !f) return;
      var v = vars[tr.dataset.k]; v[f] = e.target.type === 'checkbox' ? e.target.checked : e.target.value; sync();
    });
    rows.addEventListener('change', function (e) { var tr = e.target.closest('tr'), f = e.target.dataset.f; if (tr && f) { vars[tr.dataset.k][f] = e.target.type === 'checkbox' ? e.target.checked : e.target.value; sync(); } });
    $('[data-bulk-apply]', root).addEventListener('click', function () {
      ['price', 'compare', 'stock'].forEach(function (f) { var val = $('[data-bulk=' + f + ']', root).value; if (val !== '') Object.keys(vars).forEach(function (k) { vars[k][f] = val; }); });
      all();
    });
    var f = root.closest('form'); if (f) f.addEventListener('submit', sync);
    renderOpts(); all();
  })();

  /* ================= generic admin helpers for the v2.6 screens ================= */
  // live "summary sentence" for discount rules / offers: <form data-live-summary="#id">
  $$('[data-pickfilter]').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var q = inp.value.toLowerCase(), box = inp.parentNode.querySelector('.pick');
      $$('label', box).forEach(function (l) { l.style.display = l.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none'; });
    });
  });
  // show / hide fields by the value of a select: data-showwhen="select#name=val1,val2"
  function showWhen(scope) {
    $$('[data-showwhen]', scope || d).forEach(function (el) {
      var m = el.dataset.showwhen.split('='), f = el.closest('form') || d, sel = f.querySelector('[name="' + m[0] + '"]');
      if (!sel) return; var vals = m[1].split(','), cur;
      if (sel.type === 'radio') { var rc = f.querySelector('[name="' + m[0] + '"]:checked'); cur = rc ? rc.value : ''; }
      else cur = sel.type === 'checkbox' ? (sel.checked ? '1' : '0') : sel.value;
      el.hidden = vals.indexOf(cur) < 0;
    });
  }
  d.addEventListener('change', function (e) { var f = e.target.closest('form'); if (f) showWhen(f); });
  d.addEventListener('DOMContentLoaded', function () { showWhen(d); });
  showWhen(d);
  // product search pickers (offers/discounts): <div data-prodpick data-name="offer_product_ids" data-url="/admin/products/search.json">
  $$('[data-prodpick]').forEach(function (box) {
    var name = box.dataset.name, url = box.dataset.url, sel = {}, list = $('[data-chips]', box), q = $('input[type=search]', box), res = $('[data-results]', box), hid = $('input[type=hidden]', box), t;
    try { JSON.parse(box.dataset.init || '[]').forEach(function (p) { sel[p.id] = p; }); } catch (e) {}
    function out() { hid.value = Object.keys(sel).join(','); hid.dispatchEvent(new Event('input', { bubbles: true })); list.innerHTML = Object.keys(sel).map(function (id) { return '<span class="vchip">' + esc(sel[id].name) + '<button type="button" data-rm="' + id + '" aria-label="Remove">✕</button></span>'; }).join('') || '<span class="muted small">Nothing selected yet</span>'; }
    q.addEventListener('input', function () {
      clearTimeout(t); t = setTimeout(function () {
        if (q.value.trim().length < 1) { res.innerHTML = ''; return; }
        fetch(url + '?q=' + encodeURIComponent(q.value), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) {
          res.innerHTML = (j.results || []).map(function (p) { return '<button type="button" data-add=\'' + esc(JSON.stringify({ id: p.id, name: p.name })) + '\'><b>' + esc(p.name) + '</b><small>' + esc(p.price) + (p.sku ? ' · ' + esc(p.sku) : '') + '</small></button>'; }).join('') || '<span class="muted small">No matches</span>';
        });
      }, 160);
    });
    res.addEventListener('click', function (e) { var b = e.target.closest('[data-add]'); if (!b) return; var p = JSON.parse(b.dataset.add); sel[p.id] = p; out(); q.value = ''; res.innerHTML = ''; q.focus(); });
    list.addEventListener('click', function (e) { var b = e.target.closest('[data-rm]'); if (b) { delete sel[b.dataset.rm]; out(); } });
    out();
  });
  // copy-to-clipboard buttons
  d.addEventListener('click', function (e) { var b = e.target.closest('[data-copy]'); if (!b) return; if (navigator.clipboard) navigator.clipboard.writeText(b.dataset.copy); b.textContent = 'Copied'; setTimeout(function () { b.textContent = 'Copy'; }, 1200); });
})();

/* ================= v2.6 admin: typecards, live summaries, gift card preview ================= */
(function () {
  'use strict';
  var d = document;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function m(sym, v) { v = +v || 0; return sym + (v % 1 === 0 ? v : v.toFixed(2)); }

  // radio-style type cards: <label class="typecard" data-radio="name"><input type="radio" hidden>
  d.addEventListener('click', function (e) {
    var c = e.target.closest('.typecard[data-radio]'); if (!c) return;
    var r = $('input[type=radio]', c); if (!r) return; e.preventDefault();
    r.checked = true; var f = c.closest('form');
    $$('.typecard[data-radio="' + c.dataset.radio + '"]', f).forEach(function (x) { x.classList.toggle('on', x === c); });
    r.dispatchEvent(new Event('change', { bubbles: true })); live(f);
  });

  function val(f, n) { var el = f.querySelector('[name="' + n + '"]'); return el ? el.value : ''; }
  function checked(f, n) { var el = f.querySelector('input[name="' + n + '"][type=checkbox]'); return el && el.checked; }
  function radio(f, n) { var el = f.querySelector('input[name="' + n + '"]:checked'); return el ? el.value : ''; }

  function liveDiscount(f) {
    var sym = f.dataset.sym || '$', scope = radio(f, 'scope') || 'all', type = val(f, 'type'), v = parseFloat(val(f, 'value')) || 0, q = parseInt(val(f, 'min_qty')) || 1;
    var off = type === 'percent' ? v + '% off' : m(sym, v) + ' off';
    var what = scope === 'all' ? 'every product in the store' : (scope === 'categories' ? ($$('input[name="category_ids[]"]:checked', f).length + ' department(s)') : (($('input[name=product_ids]', f).value || '').split(',').filter(Boolean).length + ' product(s)'));
    var aud = val(f, 'audience'), parts = [];
    parts.push('<b>' + off + '</b> on ' + what + (q > 1 ? ', when buying <b>' + q + ' or more</b>' : '') + '.');
    if (aud === 'customers') parts.push('Only for signed-in customers.'); if (aud === 'guests') parts.push('Only for guests.');
    if (checked(f, 'skip_sale')) parts.push('Skips items already on sale.');
    var s = val(f, 'starts_at'), e2 = val(f, 'ends_at'); if (s || e2) parts.push('Runs ' + (s ? 'from ' + s : '') + (e2 ? ' until ' + e2 : '') + '.');
    parts.push('Prices change automatically — no code needed.');
    $('#disc-sum').innerHTML = parts.map(function (p) { return '<p>' + p + '</p>'; }).join('');
    var ex = {}; try { ex = JSON.parse(f.dataset.sample || '{}'); } catch (x) {}
    var price = (ex.price || 5000) / 100, now = type === 'percent' ? price * (1 - Math.min(100, v) / 100) : Math.max(0, price - v);
    $('[data-ex-name]', f).textContent = ex.name || 'Sample product'; $('[data-ex-now]', f).textContent = m(sym, now);
    $('[data-ex-was]', f).textContent = v > 0 ? m(sym, price) : ''; $('[data-ex-save]', f).textContent = v > 0 ? 'Save ' + m(sym, price - now) : '';
  }
  function liveOffer(f) {
    var sym = f.dataset.sym || '$', kind = radio(f, 'kind') || 'crosssell', dt = val(f, 'discount_type'), dv = parseFloat(val(f, 'discount_value')) || 0;
    var ids = ($$('input[type=hidden]', f).filter(function (i) { return i.name === 'offer_product_ids'; })[0] || { value: '' }).value.split(',').filter(Boolean).length;
    var trig = ($$('input[type=hidden]', f).filter(function (i) { return i.name === 'trigger_product_ids'; })[0] || { value: '' }).value.split(',').filter(Boolean).length + $$('input[name="trigger_category_ids[]"]:checked', f).length;
    var pl = $$('input[name="placement[]"]:checked', f).map(function (x) { return x.parentNode.textContent.trim(); });
    var dd = dt === 'none' ? 'no discount' : (dt === 'percent' ? dv + '% off' : m(sym, dv) + ' off');
    var t = kind === 'bundle' ? '<p><b>Bundle</b> of ' + ids + ' product(s) with <b>' + dd + '</b> when all are in the cart.</p>'
      : '<p><b>' + (kind === 'upsell' ? 'Upsell' : 'Cross-sell') + '</b>: recommends ' + ids + ' product(s) ' + (trig ? 'when ' + trig + ' trigger(s) match' : 'everywhere') + ', with <b>' + dd + '</b>.</p>';
    $('#offer-sum').innerHTML = t + '<p>Shown on: ' + (pl.length ? esc(pl.join(', ')) : 'nowhere yet — pick a placement') + '.</p>';
    var demo = $('#offer-demo'); if (demo) { var n = Math.max(2, Math.min(3, ids || 2)), h = ''; for (var i = 0; i < n; i++) h += '<div class="od"><i></i>Product ' + (i + 1) + '<span>' + m(sym, 20 * (i + 1)) + '</span></div>'; demo.innerHTML = h + '<div class="od-total"><span>' + (kind === 'bundle' ? 'Bundle price' : 'Selected total') + '</span><span>' + m(sym, 20 * (n * (n + 1) / 2) * (dt === 'percent' ? 1 - dv / 100 : 1)) + '</span></div>'; }
  }
  function liveGift(f) {
    var card = $('#gcp'); if (!card) return;
    var sym = f.dataset.sym || '$', a = parseFloat(val(f, 'amount')) || 0, dsg = f.querySelector('input[name=design]:checked');
    $('[data-gcp-amt]', card).textContent = a ? m(sym, a) : sym + '—';
    if (dsg) { card.style.setProperty('--g1', dsg.dataset.c1); card.style.setProperty('--g2', dsg.dataset.c2); card.style.setProperty('--gfg', dsg.dataset.fg); }
    var n = val(f, 'recipient_name'); $('[data-gcp-to]', card).textContent = n ? 'For ' + n : '';
    var qty = parseInt(val(f, 'qty')) || 1, single = $('[data-single]', f); if (single) single.style.opacity = qty > 1 ? .4 : 1; if (single) $$('input,textarea', single).forEach(function (i) { i.disabled = qty > 1; });
  }
  function live(f) { if (!f) return; var id = f.getAttribute('id'); if (id === 'disc-form') liveDiscount(f); else if (id === 'offer-form') liveOffer(f); else if (id === 'gc-issue') liveGift(f); }
  ['disc-form', 'offer-form', 'gc-issue'].forEach(function (id) {
    var f = d.getElementById(id); if (!f) return;
    f.addEventListener('input', function () { live(f); }); f.addEventListener('change', function () { live(f); });
    setTimeout(function () { live(f); }, 50);
  });
})();
