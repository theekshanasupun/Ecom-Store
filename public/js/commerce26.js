/* KIOSK v2.6 storefront — variant pricing, offers, stepper checkout, gift card preview. Vanilla JS. */
(function () {
  'use strict';
  var d = document, K = window.KIOSK || { base: '', csrf: '' };
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };
  function post(path, data) {
    var fd = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) for (var k in data) fd.append(k, data[k]);
    fd.set('_token', K.csrf);
    return fetch((K.base || '') + path, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Unexpected response' }; }); });
  }
  function toast(msg, err) {
    var box = $('#toasts'); if (!box) return;
    var t = d.createElement('div'); t.className = 'toast' + (err ? ' err' : ''); t.textContent = msg; box.appendChild(t);
    setTimeout(function () { t.classList.add('out'); setTimeout(function () { t.remove(); }, 350); }, 2600);
  }
  function money(c, sym, short) {
    var neg = c < 0; c = Math.abs(Math.round(c)); var whole = String(Math.floor(c / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, ','), cents = c % 100;
    return (neg ? '-' : '') + sym + ((short && cents === 0) ? whole : whole + '.' + (cents < 10 ? '0' : '') + cents);
  }
  function applyCart(r) {
    if (!r) return;
    $$('[data-cart-count]').forEach(function (c) { c.textContent = r.count; });
    var b = $('#drawer-body'); if (b && r.html != null) b.innerHTML = r.html;
  }

  /* ================= product page: variants ================= */
  var dataEl = $('#pdp-data');
  if (dataEl) (function () {
    var D; try { D = JSON.parse(dataEl.textContent); } catch (e) { return; }
    var box = $('[data-pbox]'), form = $('[data-add-form]');
    if (!box || !form) return;
    var now = $('[data-now]', box), was = $('[data-was]', box), save = $('[data-save]', box), sku = $('[data-sku]', box);
    var btn = $('[data-addbtn]', form), btnPrice = $('[data-btnprice]', form), tag = $('[data-stocktag]'), qty = $('input[name=qty]', form);
    var stage = $('#stage');
    var baseImg = stage ? stage.innerHTML : '';
    function sel() {
      var o = {};
      D.options.forEach(function (op) {
        var r = $('input[name="opt[' + op.name + ']"]:checked', form), s = $('select[name="opt[' + op.name + ']"]', form);
        o[op.name] = r ? r.value : (s ? s.value : '');
      });
      return o;
    }
    function match(v, o) { for (var k in v.opts) if (o[k] !== v.opts[k]) return false; return true; }
    function find(o) { for (var i = 0; i < D.variants.length; i++) if (match(D.variants[i], o)) return D.variants[i]; return null; }
    function fmt(c) { return money(c, D.sym, true); }
    var earnBox = $('[data-earn]'), lePts = $('[data-le-pts]'), leVal = $('[data-le-val]'), leBar = $('[data-le-bar]'), leBarWrap = $('[data-le-barwrap]'), leNote = $('[data-le-note]'), leShown = null;
    function countTo(el, to) { var from = leShown === null ? to : leShown; leShown = to; if (from === to) { el.textContent = to.toLocaleString(); return; } var t0 = null; function step(t) { if (t0 === null) t0 = t; var p = Math.min(1, (t - t0) / 450); el.textContent = Math.round(from + (to - from) * (1 - Math.pow(1 - p, 3))).toLocaleString(); if (p < 1) requestAnimationFrame(step); } requestAnimationFrame(step); }
    function earnUpdate(price) {
      if (!earnBox || !D.loy) return;
      var L = D.loy, q = qty ? Math.max(1, parseInt(qty.value, 10) || 1) : 1, raw = price * q / 100 * L.rate;
      var pts = L.mode === 'ceil' ? Math.ceil(raw - 1e-9) : (L.mode === 'round' ? Math.round(raw) : Math.floor(raw + 1e-9));
      earnBox.classList.toggle('le-zero', pts <= 0);
      countTo(lePts, Math.max(0, pts)); earnBox.classList.remove('le-pop'); void earnBox.offsetWidth; earnBox.classList.add('le-pop');
      leVal.textContent = pts > 0 ? '≈ ' + fmt(Math.floor(pts * L.pv)) + ' off a future order' : '';
      var note = '';
      if (pts <= 0) {
        var need = Math.max(0, Math.ceil(L.spend / L.pts * 1 - 1e-9));
        note = 'Earn <b>' + L.pts + '</b> point' + (L.pts === 1 ? '' : 's') + ' for every <b>' + fmt(Math.round(L.spend * 100)) + '</b> you spend' + (price * q < L.spend * 100 ? ' — add more to your cart to start earning.' : '.');
        if (leBarWrap) leBarWrap.hidden = true;
      } else if (L.guest) {
        note = '<a href="' + L.login + '">Sign in</a> or <a href="' + L.join + '">join free</a> to collect these points' + (q > 1 ? ' (' + q + ' items)' : '') + '.';
        if (leBarWrap) leBarWrap.hidden = true;
      } else if (L.goal) {
        var tot = L.bal + pts, pct = Math.min(100, Math.floor(tot / L.goal.cost * 100));
        if (leBarWrap) leBarWrap.hidden = false; if (leBar) leBar.style.width = pct + '%';
        note = tot >= L.goal.cost ? '🎉 You\'ll be able to claim <b>' + L.goal.name + '</b>' : '<b>' + (L.goal.cost - tot).toLocaleString() + '</b> pts from <b>' + L.goal.name + '</b> after this order';
      } else { if (leBarWrap) leBarWrap.hidden = true; note = 'Points are added after your order is completed' + (q > 1 ? ' (' + q + ' items)' : '') + '.'; }
      leNote.innerHTML = note;
    }
    function update() {
      var o = sel(), v = D.variants.length ? find(o) : null;
      var price = v ? v.price : D.base.price, cmp = v ? v.compare : D.base.compare, avail = v ? v.stock : D.base.avail, active = v ? v.active : true;
      if (D.variants.length && !v) { active = false; avail = 0; }
      now.textContent = fmt(price); earnUpdate(price);
      if (cmp > price) { was.hidden = false; was.textContent = fmt(cmp); save.hidden = false; save.textContent = 'Save ' + fmt(cmp - price) + ' · ' + Math.round(100 - price / cmp * 100) + '%'; }
      else { was.hidden = true; save.hidden = true; }
      if (sku) sku.textContent = (v && v.sku) ? 'SKU ' + v.sku : (D.base.sku ? 'SKU ' + D.base.sku : '');
      var out = !active || avail === 0;
      if (btn) { btn.classList.toggle('is-off', out); btn.disabled = out; if (btnPrice) { btnPrice.textContent = fmt(price); btnPrice.hidden = out; } btn.firstChild.nodeValue = out ? 'Sold out' : 'Add to cart · '; }
      if (qty) qty.max = avail > 0 && avail < 99 ? avail : 99;
      if (tag) tag.innerHTML = out ? '<span class="tag bad">Sold out</span>' : (avail > 0 && avail <= D.low ? '<span class="tag warn">Only ' + avail + ' left</span>' : '');
      $$('[data-optpick]').forEach(function (e) { e.textContent = o[e.getAttribute('data-optpick')] || ''; });
      // dim unavailable values given the other choices
      if (D.variants.length) D.options.forEach(function (op) {
        $$('input[name="opt[' + op.name + ']"]', form).forEach(function (inp) {
          var test = {}; for (var k in o) test[k] = o[k]; test[op.name] = inp.value;
          var vv = find(test), na = !vv || !vv.active || vv.stock === 0;
          if (inp.parentNode) inp.parentNode.classList.toggle('na', na);
        });
      });
      if (stage) {
        if (v && v.image) { stage.dataset.vimg = '1'; var im = stage.querySelector('img.vimg'); if (!im) { stage.insertAdjacentHTML('beforeend', '<img class="vimg" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">'); im = stage.querySelector('img.vimg'); } im.src = v.image; }
        else { var old = stage.querySelector('img.vimg'); if (old) old.remove(); }
      }
      box.classList.remove('flash'); void box.offsetWidth; box.classList.add('flash');
    }
    form.addEventListener('change', function (e) { if (e.target.closest('.optset, .optsel')) update(); });
    if (qty) { qty.addEventListener('input', update); form.addEventListener('click', function (e) { if (e.target.closest('[data-q]')) setTimeout(update, 0); }); }
    update();
  })();

  /* ================= offers: totals + add many ================= */
  $$('.offer-fbt, .offer-upsell, .offer-crosssell').forEach(function (b) {
    var sym = (window.KIOSK && K.sym) || ($('#pdp-data') ? JSON.parse($('#pdp-data').textContent).sym : '$');
    var out = $('[data-offer-total]', b);
    function sum() { var t = 0; $$('input[data-id]:checked', b).forEach(function (i) { t += +i.dataset.price || 0; }); if (out) out.textContent = money(t, sym, true); }
    b.addEventListener('change', sum); sum();
  });
  d.addEventListener('click', function (e) {
    var t = e.target.closest('[data-addmany]'); if (!t) return;
    var box = t.closest('.offer'), ids = [];
    if (t.dataset.ids) ids = t.dataset.ids.split(',');
    else $$('input[data-id]:checked', box).forEach(function (i) { ids.push(i.dataset.id); });
    if (!ids.length) return;
    var fd = new FormData(); ids.forEach(function (i) { fd.append('ids[]', i); }); fd.append('offer', t.dataset.offer || '');
    t.disabled = true;
    post('/cart/add-many', fd).then(function (r) {
      t.disabled = false;
      if (!r.ok) { toast(r.error || 'Could not add those', true); return; }
      applyCart(r); toast(r.message);
      if (box.classList.contains('offer-cart')) { var root = $('#cart-root'); if (root) fetch((K.base || '') + '/cart?partial=1', { credentials: 'same-origin' }).then(function (x) { return x.text(); }).then(function (h) { root.innerHTML = h; }); }
      else if (box.classList.contains('offer-checkout')) location.reload();
      else { var o = $('#cart-open'); if (o) setTimeout(function () { o.click(); }, 250); }
    }).catch(function () { t.disabled = false; toast('Network error', true); });
  });

  /* ================= gift card page ================= */
  var gc = $('#gc-form');
  if (gc) {
    var card = $('#gc-card'), amt = $('[data-gc-amt]'), to = $('[data-gc-to]');
    var sym = (card && card.querySelector('[data-gc-amt]').textContent.replace(/[0-9.,\s]/g, '')) || '$';
    function gcUpdate() {
      var cu = $('input[name=custom]', gc), r = $('input[name=amount]:checked', gc), v = 0;
      if (cu && cu.value) v = Math.round(parseFloat(cu.value) * 100); else if (r) v = +r.value;
      amt.textContent = v > 0 ? money(v, sym, true) : '—';
      var len = amt.textContent.length; amt.style.fontSize = len > 12 ? 'clamp(18px,2.6vw,26px)' : (len > 9 ? 'clamp(24px,3.4vw,36px)' : '');
      if (cu) { var mn = parseFloat(cu.min) || 0, mx = parseFloat(cu.max) || 0, w = $('[data-gc-warn]', gc);
        if (!w) { w = document.createElement('small'); w.setAttribute('data-gc-warn', ''); w.className = 'gc-warn'; cu.closest('.gc-amounts').insertAdjacentElement('afterend', w); }
        var val = cu.value ? parseFloat(cu.value) : null;
        w.textContent = (val !== null && ((mx && val > mx) || (mn && val < mn))) ? 'Choose an amount between ' + money(mn * 100, sym, true) + ' and ' + money(mx * 100, sym, true) + '.' : ''; }
      var dsg = $('input[name=design]:checked', gc); if (dsg) { card.style.setProperty('--g1', dsg.dataset.c1); card.style.setProperty('--g2', dsg.dataset.c2); card.style.setProperty('--gfg', dsg.dataset.fg); }
      var n = $('input[name=to_name]', gc).value; to.textContent = n ? 'For ' + n : '';
    }
    gc.addEventListener('input', function (e) { if (e.target.name === 'custom' && e.target.value) $$('input[name=amount]', gc).forEach(function (r) { r.checked = false; }); gcUpdate(); });
    gc.addEventListener('change', function (e) { if (e.target.name === 'amount') { var cu = $('input[name=custom]', gc); if (cu) cu.value = ''; } gcUpdate(); });
    gcUpdate();
  }

  /* ================= stepper checkout ================= */
  var co = $('#co26');
  if (co) (function () {
    var form = $('#co-form'), steps = $$('.cstep', form), bar = $$('[data-cs-steps] li'), cur = 0, sym = (function () { var m = $('#co-summary .total b'); return m ? m.textContent.replace(/[0-9.,\s-]/g, '') : '$'; })();
    var sumBox = $('#co-summary'), msg = function () { return $('[data-apply-msg]', sumBox); };

    function field(el) { return el.closest('.fld') || el.parentNode; }
    function clearErr(el) { el.classList.remove('bad-f'); var e = field(el).querySelector('.f-err'); if (e) e.remove(); }
    function setErr(el, text) { el.classList.add('bad-f'); var f = field(el); if (!f.querySelector('.f-err')) { var s = d.createElement('span'); s.className = 'f-err'; s.textContent = text; f.appendChild(s); } }
    function validate(i) {
      var ok = true, first = null;
      $$('input[required], select[required]', steps[i]).forEach(function (el) {
        clearErr(el);
        var v = (el.value || '').trim(), bad = !v;
        if (!bad && el.type === 'email' && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v)) { bad = true; setErr(el, 'Enter a valid email address.'); }
        else if (bad) setErr(el, 'This field is required.');
        if (bad) { ok = false; first = first || el; }
      });
      var pw = $('input[name=password]', steps[i]), mode = $('input[name=account_mode]:checked', steps[i]);
      if (pw && mode && mode.value === 'password') { clearErr(pw); if (pw.value.length < 8) { ok = false; setErr(pw, 'Use at least 8 characters.'); first = first || pw; } }
      if (first) first.focus();
      return ok;
    }
    function summary(i) {
      var s = $('[data-sum]', steps[i]); if (!s) return;
      var n = steps[i].dataset.name;
      if (n === 'contact') s.textContent = ($('input[name=email]', form).value || '').trim();
      else if (n === 'delivery') {
        var sh = $('input[name=ship]:checked', form);
        s.textContent = [$('input[name=ship_address]', form).value, $('input[name=ship_city]', form).value].filter(Boolean).join(', ') + (sh ? ' · ' + sh.dataset.label : '');
      }
    }
    function show(i, scroll) {
      cur = i;
      steps.forEach(function (st, k) { st.dataset.state = k < i ? 'done' : (k === i ? 'open' : 'todo'); if (k < i) summary(k); });
      bar.forEach(function (b, k) { b.classList.toggle('on', k === i); b.classList.toggle('done', k < i); });
      if (scroll) setTimeout(function () { steps[i].scrollIntoView({ behavior: 'smooth', block: 'start' }); var f = $('input:not([type=hidden]):not([type=radio]):not([type=checkbox]),select', steps[i]); if (f && !f.value) f.focus({ preventScroll: true }); }, 60);
    }
    function next() { if (!validate(cur)) return; if (cur < steps.length - 1) { show(cur + 1, true); if (cur === 1 || steps.length === 2) capture(); } }
    function capture() { var e = $('input[name=email]', form); if (e && /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(e.value)) post('/checkout/capture', { email: e.value }); }

    form.addEventListener('click', function (e) {
      if (e.target.closest('[data-next]')) { e.preventDefault(); next(); return; }
      var ed = e.target.closest('[data-edit]'), head = e.target.closest('.cstep-head');
      var st = (ed || head) && e.target.closest('.cstep');
      if (st && st.dataset.state === 'done') { show(steps.indexOf(st), false); }
      var fill = e.target.closest('[data-fill]');
      if (fill) { var o = JSON.parse(fill.dataset.fill); for (var k in o) { var el = $('[name=' + k + ']', form); if (el) { el.value = o[k]; clearErr(el); } } }
    });
    form.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && e.target.tagName === 'INPUT' && e.target.type !== 'submit' && cur < steps.length - 1) { e.preventDefault(); next(); }
    });
    form.addEventListener('input', function (e) { if (e.target.classList.contains('bad-f')) clearErr(e.target); persist(); });
    form.addEventListener('submit', function (e) {
      for (var i = 0; i < steps.length - 1; i++) if (!validate(i)) { e.preventDefault(); show(i, true); return; }
      var b = $('#co-submit'); if (b) { b.disabled = true; $('[data-submit-label]', b).textContent = 'Processing…'; }
      try { sessionStorage.removeItem('co26'); } catch (x) {}
    });
    // account mode → password field
    $$('input[name=account_mode]', form).forEach(function (r) { r.addEventListener('change', function () { var p = $('#acct-pass'); if (p) p.hidden = r.value !== 'password' || !r.checked; if (p && !p.hidden) $('input', p).focus(); }); });
    // bank info toggle
    var bank = $('.bankinfo'); function bankSync() { if (!bank) return; var m = $('input[name=method]:checked', form); bank.style.display = m && m.value === 'bank' ? 'block' : 'none'; }
    $$('input[name=method]', form).forEach(function (r) { r.addEventListener('change', bankSync) }); bankSync();

    // draft persistence (never the password)
    var skip = { password: 1, _token: 1 };
    function persist() { try { var o = {}; $$('input,select', form).forEach(function (el) { if (!el.name || skip[el.name] || el.type === 'radio' || el.type === 'checkbox') return; o[el.name] = el.value; }); sessionStorage.setItem('co26', JSON.stringify(o)); } catch (e) {} }
    try { var dr = JSON.parse(sessionStorage.getItem('co26') || 'null'); if (dr) for (var k in dr) { var el = $('[name="' + k + '"]', form); if (el && !el.value && dr[k]) el.value = dr[k]; } } catch (e) {}

    // initial state: first step open; if the server bounced us with an error, open everything
    var hadErr = !!$('.flash-error'); show(hadErr ? steps.length - 1 : 0, false);
    if (hadErr) steps.forEach(function (st, k) { st.dataset.state = k === steps.length - 1 ? 'open' : 'done'; summary(k); });
    // returning customers with everything filled: jump to the first incomplete step
    if (!hadErr) { for (var i = 0; i < steps.length - 1; i++) { var ok = true; $$('input[required], select[required]', steps[i]).forEach(function (el) { if (!(el.value || '').trim()) ok = false; }); if (!ok) { show(i, false); break; } if (i === steps.length - 2) show(steps.length - 1, false); } }

    // ---- live summary updates: coupon / gift card / credit / shipping ----
    function refresh(r) {
      var lb = $('[data-loy-box]', sumBox), lbOpen = lb ? lb.open : null;
      if (r.html != null) sumBox.innerHTML = r.html;
      var lb2 = $('[data-loy-box]', sumBox); if (lb2 && lbOpen) lb2.open = true;
      var m = msg(); if (m) { m.textContent = r.error || ''; m.className = 'apply-msg' + (r.error ? '' : ' ok'); }
      var due = r.due || '', free = !!r.free, pay = $('[data-pay-section]'), note = $('[data-free-note]');
      if (pay) pay.hidden = free; if (note) note.hidden = !free;
      var lab = $('[data-submit-label]'); if (lab) lab.textContent = free ? 'Place order →' : 'Pay ' + due + ' →';
      var t = $('[data-sum-total]'); if (t) t.textContent = due;
      if (typeof r.cart_count === 'number') $$('[data-cart-count]').forEach(function (c) { c.textContent = r.cart_count; });
    }
    function apply(action, data) { data = data || {}; data.action = action; return post('/checkout/apply', data).then(refresh); }
    sumBox.addEventListener('submit', function (e) {
      var f = e.target.closest('[data-apply]'); if (!f) return; e.preventDefault();
      apply(f.dataset.apply, { code: ($('input[name=code]', f).value || '').trim() });
    });
    sumBox.addEventListener('click', function (e) { var c = e.target.closest('[data-apply-clear]'); if (c) apply(c.dataset.applyClear, { code: '' }); });
    sumBox.addEventListener('change', function (e) {
      if (e.target.hasAttribute('data-apply-points')) apply('points', { points: parseInt(e.target.value, 10) || 0 });
      if (e.target.hasAttribute('data-apply-cashback')) apply('cashback', { on: e.target.checked ? 1 : 0 });
    });
    sumBox.addEventListener('input', function (e) { var r = e.target; if (!r.hasAttribute || !r.hasAttribute('data-lc-range')) return; var w = r.closest('.lc-redeem'), out = $('[data-lc-out]', w), pts = parseInt(r.value, 10) || 0; out.textContent = pts.toLocaleString() + ' pts · −' + money(Math.floor(pts * parseFloat(w.dataset.pv)), w.dataset.sym, true); r.style.setProperty('--p', (r.max > 0 ? pts / r.max * 100 : 0) + '%'); });
    sumBox.addEventListener('click', function (e) { var b = e.target.closest('[data-points-set]'); if (b) apply('points', { points: parseInt(b.dataset.pointsSet, 10) || 0 }); });
    sumBox.addEventListener('change', function (e) { if (e.target.hasAttribute('data-apply-credit')) apply('credit', { on: e.target.checked ? 1 : 0 }); });
    $$('input[name=ship]', form).forEach(function (r) { r.addEventListener('change', function () { apply('ship', { id: r.value }); }); });
    var tog = $('[data-sumtoggle]'); if (tog) tog.addEventListener('click', function () { var a = tog.closest('.co26-aside'); var o = a.classList.toggle('open'); tog.setAttribute('aria-expanded', o); });
  })();
})();
