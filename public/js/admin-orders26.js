/* KIOSK admin v2.6 — orders: quick actions on the list + the backend "Create order" builder. */
(function () {
  'use strict';
  var d = document, w = window;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var K = w.KioskAdmin || { toast: function (m) { alert(m); }, post: null };
  function post(url, fd) {
    if (!fd.has('_token')) { var t = $('input[name=_token]'); if (t) fd.append('_token', t.value); }
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } }).then(function (r) { return r.json(); });
  }

  /* =========================================================== orders list: quick actions */
  var table = $('#orders-table');
  if (table && w.ORDERS26) {
    var C = w.ORDERS26, pop = null, curRow = null;
    function closePop() { if (pop) { pop.remove(); pop = null; curRow = null; } }
    function place(btn) {
      var r = btn.getBoundingClientRect(), pw = 330;
      pop.style.position = 'fixed'; pop.style.width = pw + 'px';
      var left = Math.min(w.innerWidth - pw - 12, Math.max(12, r.right - pw));
      pop.style.left = left + 'px';
      var h = pop.offsetHeight, top = r.bottom + 6;
      if (top + h > w.innerHeight - 12) top = Math.max(12, r.top - h - 6);
      pop.style.top = top + 'px';
    }
    function menu(m) {
      var h = '<h4>' + esc(m.number) + ' · ' + esc(m.name) + '</h4><div class="qa-list">';
      if (!m.paid && ['cancelled', 'refunded'].indexOf(m.status) < 0) h += '<button type="button" data-q="paid">✓ Mark as paid</button>';
      if (m.physical) h += '<button type="button" data-q="ship">📦 Add shipping details…</button><button type="button" data-q="addr">✎ Edit shipping address…</button>';
      h += '</div><div class="qa-sep"></div><label class="fld"><span>Change status</span><select data-q-status>';
      Object.keys(C.statuses).forEach(function (k) { h += '<option value="' + k + '"' + (m.status === k ? ' selected' : '') + '>' + esc(C.statuses[k]) + '</option>'; });
      h += '</select></label><a class="btn sm ghost block" href="' + C.quick + '/' + m.id + '">Open full order</a>';
      return h;
    }
    function shipForm(m) {
      var h = '<h4>Shipping details</h4><label class="fld"><span>Carrier</span><input name="carrier" list="qa-carriers" value="' + esc(m.carrier) + '" placeholder="DHL, UPS…"><datalist id="qa-carriers">';
      C.carriers.forEach(function (c) { if (c) h += '<option value="' + esc(c) + '">'; });
      h += '</datalist></label><label class="fld"><span>Tracking number</span><input name="tracking" value="' + esc(m.tracking) + '"></label>' +
        '<label class="fld"><span>Tracking link</span><input name="tracking_url" value="' + esc(m.tracking_url) + '" placeholder="https://…"></label>' +
        '<label class="fld"><span>Estimated delivery (optional)</span><input name="eta" placeholder="e.g. 3–5 business days"></label>' +
        '<label class="chk"><input type="checkbox" name="mark_shipped" value="1"' + (['shipped', 'completed', 'cancelled', 'refunded'].indexOf(m.status) < 0 ? ' checked' : '') + '><i></i><span>Mark as shipped</span></label>' +
        '<label class="chk"><input type="checkbox" name="notify" value="1" checked><i></i><span>Email the customer</span></label>' +
        '<div class="row-actions"><button type="button" class="btn sm" data-q-save="shipping">Save</button><button type="button" class="btn sm ghost" data-q-back>Back</button></div>';
      return h;
    }
    function addrForm(m) {
      var f = function (n, l, v) { return '<label class="fld"><span>' + l + '</span><input name="' + n + '" value="' + esc(v) + '"></label>'; };
      return '<h4>Shipping address</h4>' + f('ship_name', 'Recipient', m.ship_name) + f('ship_address', 'Address', m.ship_address) + f('ship_address2', 'Apartment / suite', m.ship_address2) + f('ship_city', 'City', m.ship_city) +
        '<div class="g2">' + f('ship_state', 'State', m.ship_state) + f('ship_zip', 'Postal code', m.ship_zip) + '</div>' + f('ship_country', 'Country', m.ship_country) +
        '<div class="row-actions"><button type="button" class="btn sm" data-q-save="address">Save</button><button type="button" class="btn sm ghost" data-q-back>Back</button></div>';
    }
    function send(m, fields) {
      var fd = new FormData(); Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
      return post(C.quick + '/' + m.id + '/quick', fd).then(function (j) {
        if (!j.ok) { K.toast(j.error || 'Could not update the order', 'err'); return j; }
        K.toast(j.message || 'Updated');
        var tr = curRow;
        if (tr) {
          m.status = j.status; m.paid = j.payment_status === 'paid'; m.carrier = j.carrier; m.tracking = j.tracking; m.tracking_url = j.tracking_url;
          ['ship_name', 'ship_address', 'ship_address2', 'ship_city', 'ship_state', 'ship_zip', 'ship_country'].forEach(function (k) { if (fields[k] !== undefined) m[k] = fields[k]; });
          tr.setAttribute('data-order', JSON.stringify(m));
          var st = $('.js-status', tr); st.className = 'badge s-' + j.status + ' js-status'; st.textContent = j.status_label;
          var py = $('.js-pay', tr); py.className = 'badge s-' + j.payment_status + ' js-pay'; py.textContent = j.payment_status;
          if (m.physical) $('.js-ship', tr).innerHTML = '📦 ' + (j.tracking ? esc(j.carrier || 'Tracking') + '<small>' + esc(j.tracking) + '</small>' : '<small>no tracking</small>');
          tr.classList.remove('flashrow'); void tr.offsetWidth; tr.classList.add('flashrow');
        }
        return j;
      }).catch(function () { K.toast('Network error', 'err'); });
    }
    table.addEventListener('click', function (e) {
      var b = e.target.closest('[data-qa]'); if (!b) return;
      e.stopPropagation();
      var tr = b.closest('tr'); if (pop && curRow === tr) { closePop(); return; }
      closePop(); curRow = tr;
      var m = JSON.parse(tr.getAttribute('data-order'));
      pop = d.createElement('div'); pop.className = 'qa-pop'; pop.innerHTML = menu(m); d.body.appendChild(pop); place(b);
      pop.addEventListener('click', function (ev) {
        ev.stopPropagation();
        var t = ev.target.closest('[data-q],[data-q-save],[data-q-back]'); if (!t) return;
        if (t.hasAttribute('data-q-back')) { pop.innerHTML = menu(m); place(b); return; }
        if (t.getAttribute('data-q') === 'paid') { if (!confirm('Mark ' + m.number + ' as paid? Digital items are delivered and the customer is emailed.')) return; send(m, { do: 'paid' }).then(closePop); return; }
        if (t.getAttribute('data-q') === 'ship') { pop.innerHTML = shipForm(m); place(b); var i = $('input[name=tracking]', pop); if (i) i.focus(); return; }
        if (t.getAttribute('data-q') === 'addr') { pop.innerHTML = addrForm(m); place(b); return; }
        var kind = t.getAttribute('data-q-save');
        if (kind) {
          var fields = { do: kind };
          $$('input,select', pop).forEach(function (el) { if (!el.name) return; if (el.type === 'checkbox') { if (el.checked) fields[el.name] = '1'; } else fields[el.name] = el.value; });
          if (kind === 'shipping' && !fields.mark_shipped) fields.mark_shipped = '';
          send(m, fields).then(function (j) { if (j && j.ok) closePop(); });
        }
      });
      pop.addEventListener('change', function (ev) {
        if (!ev.target.matches('[data-q-status]')) return;
        var v = ev.target.value;
        if (v === 'shipped' && m.physical) { pop.innerHTML = shipForm(m); place(b); return; }
        send(m, { do: 'status', status: v, notify: '1' }).then(function (j) { if (j && j.ok) closePop(); });
      });
    });
    d.addEventListener('click', function (e) { if (pop && !pop.contains(e.target)) closePop(); });
    d.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePop(); });
    w.addEventListener('scroll', closePop, true);
  }

  /* =========================================================== create order builder */
  var form = $('#ob-form');
  if (!form) return;
  var sym = form.getAttribute('data-sym') || '$', taxRate = parseFloat(form.getAttribute('data-tax') || '0'), shipDefault = form.getAttribute('data-ship') || '0';
  var lines = [], uid = 0, shipTouched = false;
  var fmt = function (v) { v = parseFloat(v || 0); return sym + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); };
  var debounce = function (fn, ms) { var t; return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); }; };

  /* ---- customer ---- */
  var custQ = $('#cust-q'), custS = $('#cust-sugg'), chip = $('#cust-chip');
  var setCustomer = function (c) {
    form.elements.user_id.value = c && c.id ? c.id : '';
    if (c) { form.elements.name.value = c.name || ''; form.elements.email.value = c.email || ''; form.elements.phone.value = c.phone || ''; }
    if (c && c.ship && c.ship.ship_address) Object.keys(c.ship).forEach(function (k) { if (form.elements[k] && !form.elements[k].value) form.elements[k].value = c.ship[k] || ''; });
    $('#cc-name').textContent = c ? c.name : ''; $('#cc-email').textContent = c ? c.email : ''; $('#cc-type').textContent = c ? (c.id ? 'Account' : 'Guest') : '';
    chip.hidden = !c; $('#cust-search').hidden = !!c;
    $('#mk-acct').hidden = !!(c && c.id);
    custS.hidden = true;
  };
  $('#cc-clear').addEventListener('click', function () { setCustomer(null); form.elements.user_id.value = ''; custQ.value = ''; custQ.focus(); });
  var findCust = debounce(function () {
    var q = custQ.value.trim(); if (q.length < 2) { custS.hidden = true; return; }
    fetch(form.getAttribute('data-lookup') + '?type=customers&q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      var rs = j.results || [];
      custS.innerHTML = rs.length ? rs.map(function (c, i) { return '<div class="gs-i" data-i="' + i + '"><span class="gi">' + esc((c.name || '?').charAt(0).toUpperCase()) + '</span><span class="gt"><b>' + esc(c.name) + '</b><small>' + esc(c.email) + (c.phone ? ' · ' + esc(c.phone) : '') + '</small></span><span class="go">' + esc(c.type) + '</span></div>'; }).join('') : '<div class="gs-empty">No match — fill in the details below to create a new customer.</div>';
      custS.hidden = false; custS._rs = rs;
    });
  }, 180);
  custQ.addEventListener('input', findCust);
  custS.addEventListener('mousedown', function (e) { e.preventDefault(); });
  custS.addEventListener('click', function (e) { var r = e.target.closest('[data-i]'); if (r) setCustomer(custS._rs[+r.getAttribute('data-i')]); });
  var preset = form.getAttribute('data-customer'); if (preset && preset !== 'null') { try { setCustomer(JSON.parse(preset)); } catch (e) {} }
  else if (form.elements.user_id.value || form.elements.email.value) { $('#cust-search').hidden = false; }

  /* ---- items ---- */
  var prodQ = $('#prod-q'), prodS = $('#prod-sugg'), box = $('#lines');
  function priceFor(l) {
    var p = l.p; if (!p) return l.unit;
    if (p.variants && p.variants.length) {
      for (var i = 0; i < p.variants.length; i++) { var v = p.variants[i], ok = true; Object.keys(v.opts).forEach(function (k) { if (l.opts[k] !== v.opts[k]) ok = false; }); if (ok) return v.price; }
    }
    return p.price;
  }
  function stockFor(l) {
    var p = l.p; if (!p) return null;
    if (p.variants && p.variants.length) { for (var i = 0; i < p.variants.length; i++) { var v = p.variants[i], ok = true; Object.keys(v.opts).forEach(function (k) { if (l.opts[k] !== v.opts[k]) ok = false; }); if (ok && v.stock !== null) return v.stock; } }
    return p.stock;
  }
  function addLine(p) {
    var l = { id: ++uid, p: p, qty: 1, opts: {}, name: p ? p.name : '', unit: p ? p.price / 100 : 0, edited: false };
    if (p) (p.options || []).forEach(function (o) { l.opts[o.name] = (o.values || [])[0] || ''; });
    if (p) l.unit = priceFor(l) / 100;
    var dup = p && lines.filter(function (x) { return x.p && x.p.id === p.id && JSON.stringify(x.opts) === JSON.stringify(l.opts); })[0];
    if (dup) { dup.qty++; render(); return; }
    lines.push(l); render();
    if (p && p.type === 'physical' && !shipTouched && !form.elements.shipping.value) { form.elements.shipping.value = parseFloat(shipDefault) > 0 ? shipDefault : ''; }
  }
  function render() {
    var keepFocus = d.activeElement && d.activeElement.closest && d.activeElement.closest('.line') ? d.activeElement.getAttribute('data-f') + ':' + d.activeElement.closest('.line').getAttribute('data-id') : null;
    if (!lines.length) { box.innerHTML = '<p class="empty" id="no-lines">No items yet. Search above, or <button type="button" class="btn sm ghost" data-custom>add a custom item</button></p>'; totals(); return; }
    box.innerHTML = lines.map(function (l, i) {
      var p = l.p, n = 'lines[' + i + ']', h = '<div class="line" data-id="' + l.id + '">';
      h += '<div class="thumb">' + (p && p.img ? '<img src="' + esc(p.img) + '" alt="">' : '<span>' + esc((l.name || '?').charAt(0).toUpperCase()) + '</span>') + '</div><div class="nm">';
      if (p) {
        h += '<b>' + esc(p.name) + '</b><small>' + esc(p.sku || p.cat || '') + (stockFor(l) !== null ? ' · ' + stockFor(l) + ' in stock' : '') + '</small><input type="hidden" name="' + n + '[product_id]" value="' + p.id + '">';
        (p.options || []).forEach(function (o) {
          h += '<select data-f="opt" data-o="' + esc(o.name) + '" name="' + n + '[opts][' + esc(o.name) + ']" aria-label="' + esc(o.name) + '">' + (o.values || []).map(function (v) { return '<option' + (l.opts[o.name] === v ? ' selected' : '') + '>' + esc(v) + '</option>'; }).join('') + '</select> ';
        });
      } else {
        h += '<input data-f="name" name="' + n + '[name]" value="' + esc(l.name) + '" placeholder="Custom item name" style="text-align:left" aria-label="Item name"><input type="hidden" name="' + n + '[product_id]" value="0">';
      }
      h += '</div><div class="q"><input data-f="qty" type="number" min="1" max="9999" name="' + n + '[qty]" value="' + l.qty + '" aria-label="Quantity"></div>';
      h += '<div class="p"><input data-f="price" type="number" step="0.01" min="0" name="' + n + '[price]" value="' + (+l.unit).toFixed(2) + '" aria-label="Unit price"></div>';
      h += '<div class="tot" data-tot>' + fmt(l.unit * l.qty) + '</div><button type="button" class="rm" data-f="rm" aria-label="Remove">✕</button></div>';
      return h;
    }).join('');
    if (keepFocus) { var kp = keepFocus.split(':'), el = $('.line[data-id="' + kp[1] + '"] [data-f="' + kp[0] + '"]', box); if (el && el.focus) { el.focus(); if (el.select) try { el.setSelectionRange(el.value.length, el.value.length); } catch (e) {} } }
    totals();
  }
  box.addEventListener('input', function (e) {
    var row = e.target.closest('.line'); if (!row) return;
    var l = lines.filter(function (x) { return x.id === +row.getAttribute('data-id'); })[0], f = e.target.getAttribute('data-f');
    if (f === 'qty') l.qty = Math.max(1, parseInt(e.target.value || '1', 10));
    else if (f === 'price') { l.unit = parseFloat(e.target.value || '0'); l.edited = true; }
    else if (f === 'name') l.name = e.target.value;
    $('[data-tot]', row).textContent = fmt(l.unit * l.qty); totals();
  });
  box.addEventListener('change', function (e) {
    var row = e.target.closest('.line'); if (!row) return;
    var l = lines.filter(function (x) { return x.id === +row.getAttribute('data-id'); })[0];
    if (e.target.getAttribute('data-f') === 'opt') { l.opts[e.target.getAttribute('data-o')] = e.target.value; if (!l.edited) l.unit = priceFor(l) / 100; render(); }
  });
  box.addEventListener('click', function (e) {
    if (e.target.hasAttribute('data-custom')) { addLine(null); return; }
    var rm = e.target.closest('[data-f=rm]'); if (!rm) return;
    var id = +rm.closest('.line').getAttribute('data-id'); lines = lines.filter(function (x) { return x.id !== id; }); render();
  });
  var custom = function () { addLine(null); var n = $$('.line [data-f=name]', box).pop(); if (n) n.focus(); };
  $('#add-custom2').addEventListener('click', custom);
  d.addEventListener('click', function (e) { if (e.target.id === 'add-custom') custom(); });

  var prodRes = [], pidx = -1;
  var findProd = debounce(function () {
    var q = prodQ.value.trim();
    fetch(form.getAttribute('data-lookup') + '?type=products&q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      prodRes = j.results || []; pidx = prodRes.length ? 0 : -1;
      prodS.innerHTML = prodRes.length ? prodRes.map(function (p, i) {
        var out = p.stock !== null && p.stock <= 0;
        return '<div class="gs-i' + (out ? ' dis' : '') + (i === 0 ? ' on' : '') + '" data-i="' + i + '"><span class="gi">' + (p.img ? '<img src="' + esc(p.img) + '" alt="">' : esc(p.name.charAt(0).toUpperCase())) + '</span><span class="gt"><b>' + esc(p.name) + '</b><small>' + esc(p.sku || p.cat || '') + (p.status !== 'active' ? ' · draft' : '') + ' · ' + (p.stock === null ? 'unlimited' : p.stock + ' in stock') + '</small></span><span class="go">' + esc(p.price_txt) + (p.options.length ? '+' : '') + '</span></div>';
      }).join('') : '<div class="gs-empty">No products found</div>';
      prodS.hidden = false;
    });
  }, 150);
  prodQ.addEventListener('input', findProd); prodQ.addEventListener('focus', findProd);
  prodQ.addEventListener('keydown', function (e) {
    if (prodS.hidden) return;
    var rows = $$('.gs-i', prodS);
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); pidx = (pidx + (e.key === 'ArrowDown' ? 1 : -1) + rows.length) % rows.length; rows.forEach(function (r, i) { r.classList.toggle('on', i === pidx); }); }
    else if (e.key === 'Enter') { e.preventDefault(); if (pidx >= 0 && prodRes[pidx]) { addLine(prodRes[pidx]); prodQ.value = ''; prodS.hidden = true; } }
    else if (e.key === 'Escape') prodS.hidden = true;
  });
  prodS.addEventListener('mousedown', function (e) { e.preventDefault(); });
  prodS.addEventListener('click', function (e) { var r = e.target.closest('[data-i]'); if (!r || r.classList.contains('dis')) return; addLine(prodRes[+r.getAttribute('data-i')]); prodQ.value = ''; prodS.hidden = true; prodQ.focus(); });
  d.addEventListener('click', function (e) { if (!e.target.closest('.sugg-wrap')) { prodS.hidden = true; custS.hidden = true; } });

  /* ---- totals ---- */
  function localSub() { return lines.reduce(function (a, l) { return a + l.unit * l.qty; }, 0); }
  var ts = 0;
  var serverTotals = debounce(function () {
    var my = ++ts;
    if (!lines.length) { $('#t-err').hidden = true; return; }
    post(form.getAttribute('data-totals'), new FormData(form)).then(function (j) {
      if (my !== ts) return;
      var err = $('#t-err');
      if (!j.ok) { err.textContent = j.error; err.hidden = false; return; }
      err.hidden = true;
      $('#t-sub').textContent = j.subtotal; $('#t-ship').textContent = j.shipping + (j.free_ship ? ' (free via coupon)' : ''); $('#t-total').textContent = j.total;
      $('#t-dr').hidden = !j.has_discount; $('#t-disc').textContent = '−' + j.discount; $('#t-tr').hidden = !j.has_tax; $('#t-tax').textContent = j.tax;
    }).catch(function () {});
  }, 350);
  function totals() {
    var sub = localSub(), ship = parseFloat(form.elements.shipping.value || '0') || 0;
    var dv = parseFloat(form.elements.discount_value.value || '0') || 0, disc = form.elements.discount_type.value === 'percent' ? sub * Math.min(100, dv) / 100 : dv;
    disc = Math.min(sub, disc); var tax = taxRate > 0 ? Math.max(0, sub - disc) * taxRate / 100 : 0;
    $('#t-sub').textContent = fmt(sub); $('#t-ship').textContent = fmt(ship); $('#t-dr').hidden = disc <= 0; $('#t-disc').textContent = '−' + fmt(disc); $('#t-tr').hidden = tax <= 0; $('#t-tax').textContent = fmt(tax); $('#t-total').textContent = fmt(Math.max(0, sub - disc + ship + tax));
    serverTotals();
  }
  ['discount_type', 'discount_value', 'coupon', 'shipping', 'email'].forEach(function (n) { form.elements[n].addEventListener('input', function () { if (n === 'shipping') shipTouched = true; totals(); }); });
  form.elements.discount_type.addEventListener('change', totals);

  /* ---- payment cards ---- */
  var payCards = $$('.pcard2', form);
  function payUi() {
    var paid = form.elements.pay_status.value === 'paid';
    payCards.forEach(function (c) { c.classList.toggle('on', $('input', c).checked); });
    $('#pay-ref').hidden = !paid;
    var sel = form.elements.pay_method;
    if (paid) { if (['stripe', 'paypal'].indexOf(sel.value) >= 0) sel.value = 'cash'; $$('option', sel).forEach(function (o) { o.hidden = o.value === 'stripe' || o.value === 'paypal'; }); }
    else { $$('option', sel).forEach(function (o) { o.hidden = o.value === 'pos'; }); if (sel.value === 'pos') sel.value = 'bank'; }
  }
  form.addEventListener('change', function (e) { if (e.target.name === 'pay_status') payUi(); });
  payUi();

  form.addEventListener('submit', function (e) {
    if (!lines.length) { e.preventDefault(); K.toast('Add at least one item first', 'err'); prodQ.focus(); return; }
    var b = $('#ob-submit'); b.disabled = true; b.textContent = 'Creating…';
  });
  w.addEventListener('beforeunload', function (e) { if (lines.length && !form.__sub) { e.preventDefault(); e.returnValue = ''; } });
  form.addEventListener('submit', function () { form.__sub = true; });
  totals();
})();
