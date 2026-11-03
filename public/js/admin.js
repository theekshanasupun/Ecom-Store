(function () {
  'use strict';
  var d = document;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || d).querySelectorAll(s)); };

  // mobile sidebar
  var m = $('#menu'), s = $('#side');
  if (m && s) { m.addEventListener('click', function (e) { e.stopPropagation(); s.classList.toggle('open'); }); d.addEventListener('click', function (e) { if (!s.contains(e.target)) s.classList.remove('open'); }); }

  // product form: show sections by product type
  var type = $('select[name=type]');
  function sync() {
    if (!type) return;
    $$('[data-show-for]').forEach(function (el) { el.hidden = el.dataset.showFor.split(' ').indexOf(type.value) === -1; });
  }
  if (type) { type.addEventListener('change', sync); sync(); }

  // image preview
  $$('input[data-preview]').forEach(function (i) {
    i.addEventListener('change', function () {
      if (!i.files[0]) return;
      var img = i.closest('.fld').parentNode.querySelector('.imgprev img') || (function () { var w = d.createElement('div'); w.className = 'imgprev'; var im = d.createElement('img'); w.appendChild(im); i.closest('.fld').parentNode.insertBefore(w, i.closest('.fld')); return im; })();
      img.src = URL.createObjectURL(i.files[0]);
    });
  });

  // update dropzone
  var dz = $('#dz'), pkg = $('#pkg');
  if (dz && pkg) {
    var name = $('#dz-name'), go = $('#dz-go');
    function pick() { if (pkg.files[0]) { name.textContent = pkg.files[0].name; go.disabled = false; } }
    pkg.addEventListener('change', pick);
    ['dragenter', 'dragover'].forEach(function (ev) { dz.addEventListener(ev, function () { dz.classList.add('over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { dz.addEventListener(ev, function () { dz.classList.remove('over'); }); });
    dz.addEventListener('submit', function (e) {
      if (!confirm('Install this update now? A backup of replaced files is created automatically.')) { e.preventDefault(); return; }
      go.disabled = true; go.textContent = 'Installing… please wait';
    });
  }

  // ===== v2.5 =====
  // bulk select (shift-click ranges, select-all, action bar)
  $$('form[data-bulk]').forEach(function (form) {
    var bar = $('[data-bulkbar]', form), all = $('[data-selall]', form), cnt = $('[data-selcount]', form), last = null;
    var rows = function () { return $$('.rowchk', form); };
    function refresh() {
      var sel = rows().filter(function (c) { return c.checked; });
      rows().forEach(function (c) { var tr = c.closest('tr'); if (tr) tr.classList.toggle('sel', c.checked); });
      if (cnt) cnt.textContent = sel.length;
      if (bar) bar.hidden = sel.length === 0;
      if (all) { all.checked = sel.length > 0 && sel.length === rows().length; all.indeterminate = sel.length > 0 && sel.length < rows().length; }
    }
    form.addEventListener('click', function (e) {
      var c = e.target.closest('.rowchk'); if (!c) return;
      if (e.shiftKey && last && last !== c) {
        var list = rows(), a = list.indexOf(last), b = list.indexOf(c), lo = Math.min(a, b), hi = Math.max(a, b);
        for (var i = lo; i <= hi; i++) list[i].checked = c.checked;
      }
      last = c; refresh();
    });
    if (all) all.addEventListener('change', function () { rows().forEach(function (c) { c.checked = all.checked; }); refresh(); });
    var act = $('[data-bulk-action]', form);
    if (act) act.addEventListener('change', function () {
      var need = (act.selectedOptions[0] && act.selectedOptions[0].dataset.needs) || '';
      $$('[data-bulk-value]', form).forEach(function (el) { var on = el.dataset.bulkValue === need; el.hidden = !on; el.disabled = !on; });
    });
    var clr = $('[data-bulk-clear]', form);
    if (clr) clr.addEventListener('click', function () { rows().forEach(function (c) { c.checked = false; }); refresh(); });
    form.addEventListener('submit', function (e) {
      if (!act) return;
      if (!act.value) { e.preventDefault(); alert('Choose a bulk action first.'); return; }
      var o = act.selectedOptions[0], n = rows().filter(function (c) { return c.checked; }).length;
      if (o && o.dataset.danger && !confirm('Delete ' + n + ' selected item(s)? This cannot be undone.')) e.preventDefault();
    });
    refresh();
  });

  // product quick edit (inline, autosaves)
  var qeForm = $('form[data-qe-url]');
  if (qeForm) {
    var base = qeForm.dataset.qeUrl, token = qeForm.dataset.qeToken;
    var send = function (el, id, field, value) {
      el.classList.add('saving'); el.classList.remove('saved', 'err');
      var fd = new FormData(); fd.append('field', field); fd.append('value', value); fd.append('_token', token);
      return fetch(base + '/' + id + '/quick', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); }).then(function (j) {
          el.classList.remove('saving');
          if (!j.ok) { el.classList.add('err'); return j; }
          el.classList.add('saved'); setTimeout(function () { el.classList.remove('saved'); }, 1400);
          return j;
        }).catch(function () { el.classList.remove('saving'); el.classList.add('err'); });
    };
    qeForm.addEventListener('change', function (e) {
      var el = e.target.closest('.qe'); if (!el) return;
      var tr = el.closest('tr'), id = tr.dataset.id, f = el.dataset.field;
      send(el, id, f, el.value).then(function (j) {
        if (!j || !j.ok) return;
        if (f === 'price') el.value = j.price;
        if (f === 'compare_price') el.value = j.compare;
        if (f === 'stock') { el.value = j.stock; el.classList.toggle('warn', j.stock <= 3); }
        if (f === 'status') el.className = el.className.replace(/s-(on|off)/, j.status === 'active' ? 's-on' : 's-off');
      });
    });
    qeForm.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.classList.contains('qe')) { e.preventDefault(); e.target.blur(); } });
    qeForm.addEventListener('click', function (e) {
      var st = e.target.closest('[data-feature]'); if (!st) return;
      var tr = st.closest('tr'), on = !st.classList.contains('on');
      send(st, tr.dataset.id, 'featured', on ? '1' : '0').then(function (j) { if (j && j.ok) st.classList.toggle('on', j.featured); });
    });
  }

  // coupon scope boxes
  var scope = $('select[name=scope]');
  if (scope) {
    var showScope = function () { $$('[data-scope]').forEach(function (b) { b.hidden = b.dataset.scope !== scope.value; }); };
    scope.addEventListener('change', showScope); showScope();
  }
  $$('.pickfilter').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var q = inp.value.toLowerCase();
      $$('label', inp.parentNode.querySelector('.pick')).forEach(function (l) { l.hidden = q && l.textContent.toLowerCase().indexOf(q) === -1; });
    });
  });

  // character counters for SEO fields
  $$('[data-count]').forEach(function (inp) {
    var max = +inp.dataset.count, c = d.createElement('span'); c.className = 'cnt';
    inp.closest('.fld').querySelector('span').appendChild(c);
    var u = function () { c.textContent = inp.value.length + '/' + max; c.classList.toggle('bad', inp.value.length > max); };
    inp.addEventListener('input', u); u();
  });

  // live popup preview
  var pvForm = $('form[data-popup-form]'), pv = $('#popup-preview');
  if (pvForm && pv) {
    var t = null;
    var refreshPv = function () {
      clearTimeout(t);
      t = setTimeout(function () {
        var fd = new FormData(pvForm);
        fetch(pvForm.dataset.preview, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (h) { pv.srcdoc = h; });
      }, 300);
    };
    pvForm.addEventListener('input', refreshPv); pvForm.addEventListener('change', refreshPv); refreshPv();
    var tr2 = $('select[name=trigger]'), fr = $('select[name=frequency]'), pg = $('select[name=pages]');
    var sync2 = function () {
      $$('[data-when-trigger]').forEach(function (e) { e.hidden = e.dataset.whenTrigger.split(' ').indexOf(tr2.value) === -1; });
      $$('[data-when-freq]').forEach(function (e) { e.hidden = e.dataset.whenFreq !== fr.value; });
      $$('[data-when-pages]').forEach(function (e) { e.hidden = e.dataset.whenPages !== pg.value; });
    };
    [tr2, fr, pg].forEach(function (x) { if (x) x.addEventListener('change', sync2); }); sync2();
  }

  // click-to-insert merge tags in the automation editor
  $$('.tags code').forEach(function (c) {
    c.addEventListener('click', function () {
      var ta = c.closest('.flow-body').querySelector('textarea[name=body]'); if (!ta) return;
      var s = ta.selectionStart || ta.value.length; ta.value = ta.value.slice(0, s) + c.textContent + ta.value.slice(s); ta.focus();
    });
  });
})();
