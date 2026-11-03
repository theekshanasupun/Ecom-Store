/* KIOSK visual builder v2.6 — grouped palette (click or drag to an exact spot), live preview inside the real store shell,
   drag-reorder, inspector, undo, templates, reset to original. */
(function () {
  'use strict';
  var B = window.BUILDER, d = document;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var blocks = (B.blocks || []).slice();
  var sel = null, history = [], dirty = false, timer = null, saving = false, seq = 0, drag = null, pctl = null, tpls = (B.templates || []).slice();
  var wrap = $('#devwrap'), shield = $('#shield'), dropline = $('#dropline'), frame = null;
  var blist = $('#blist'), insp = $('#inspector'), state = $('#state'), dot = $('#dot');

  function uid() { return Math.random().toString(36).slice(2, 10); }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function find(id) { for (var i = 0; i < blocks.length; i++) if (blocks[i].id === id) return i; return -1; }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function setState(t, bad, isDirty) { state.textContent = t; state.style.color = bad ? '#ff6b7d' : ''; dot.classList.toggle('dirty', !!isDirty); }
  function snapshot() { history.push(JSON.stringify(blocks)); if (history.length > 50) history.shift(); $('#undo').disabled = false; }
  function changed(noSnap) { if (!noSnap) snapshot(); dirty = true; setState('Unsaved changes', false, true); renderList(); schedulePreview(); }
  function styleFor(type) { return Object.assign({ bg: '', color: '', pad: 'md', align: 'left', width: 'box', hide: '' }, (B.reg[type] && B.reg[type].style) || {}); }

  /* ---------- preview (double-buffered: no flash, scroll preserved) ---------- */
  function schedulePreview() { clearTimeout(timer); timer = setTimeout(preview, 120); }
  function preview() {
    var my = ++seq, y = 0;
    try { y = frame ? frame.contentWindow.scrollY : 0; } catch (e) {}
    if (pctl) pctl.abort(); pctl = window.AbortController ? new AbortController() : null;
    var fd = new FormData(); fd.append('layout', JSON.stringify(blocks)); fd.append('target', B.target); fd.append('_token', B.csrf);
    fetch(B.previewUrl, { method: 'POST', body: fd, credentials: 'same-origin', signal: pctl ? pctl.signal : undefined }).then(function (r) { return r.text(); }).then(function (h) {
      if (my !== seq) return;
      var nf = d.createElement('iframe'); nf.className = 'bld-frame fresh'; nf.title = 'Page preview';
      var done = false, tries = 0;
      function ready() {
        if (done) return;
        if (my !== seq) { done = true; clearInterval(iv); nf.remove(); return; }
        var ok = false; try { var dc = nf.contentDocument; ok = dc && dc.body && dc.readyState !== 'loading' && dc.querySelector('main, .bb-page, [data-bid]'); } catch (e) {}
        if (!ok && ++tries < 300) return;
        done = true; clearInterval(iv);
        try { nf.contentDocument.documentElement.style.scrollBehavior = 'auto'; nf.contentWindow.scrollTo(0, y); } catch (e) {}
        nf.classList.remove('fresh');
        var old = frame; frame = nf; if (old) old.remove();
        if (sel) { try { frame.contentWindow.postMessage({ kioskSelect: sel }, '*'); } catch (e) {} }
      }
      var iv = setInterval(ready, 40); nf.onload = ready;
      wrap.insertBefore(nf, shield); nf.srcdoc = h;
    }).catch(function () {});
  }
  window.addEventListener('message', function (e) {
    if (!e.data) return;
    if (e.data.kioskBlock) select(e.data.kioskBlock, false);
    if (e.data.kioskDbl) {
      select(e.data.kioskDbl, false);
      var t = (e.data.text || '').trim().slice(0, 40).toLowerCase(); if (!t) return;
      var els = insp.querySelectorAll('input[type=text],textarea');
      for (var i = 0; i < els.length; i++) if (els[i].value.toLowerCase().indexOf(t) >= 0) { els[i].focus(); try { var v = els[i].value, k = v.toLowerCase().indexOf(t); els[i].setSelectionRange(k, k + t.length); } catch (x) {} break; }
    }
  });

  /* ---------- palette (grouped, draggable) ---------- */
  var pal = $('#palette');
  Object.keys(B.groups).forEach(function (g) {
    var types = B.groups[g].filter(function (t) { return B.reg[t]; });
    if (!types.length) return;
    var box = d.createElement('details'); box.className = 'palette-g'; if (g === 'Hero & content' || g === 'Current home') box.open = true; box.innerHTML = '<summary>' + esc(g) + ' <em>' + types.length + '</em></summary>';
    var grid = d.createElement('div'); grid.className = 'palette';
    types.forEach(function (k) {
      var b = d.createElement('button'); b.type = 'button'; b.draggable = true; b.title = 'Click to add, or drag onto the page';
      b.innerHTML = '<i>' + B.reg[k].icon + '</i>' + esc(B.reg[k].label.replace(' (current)', ''));
      b.addEventListener('click', function () { add(k); });
      b.addEventListener('dragstart', function (e) { drag = { kind: 'new', type: k }; startDrag(e, 'copy'); });
      b.addEventListener('dragend', endDrag);
      grid.appendChild(b);
    });
    box.appendChild(grid); pal.appendChild(box);
  });
  function startDrag(e, eff) { try { e.dataTransfer.setData('text/plain', 'kiosk'); } catch (x) {} e.dataTransfer.effectAllowed = eff; setTimeout(function () { d.body.classList.add('dragging'); }, 0); }
  function endDrag() { drag = null; d.body.classList.remove('dragging'); clearMarks(); }
  function clearMarks() { dropline.style.display = 'none'; Array.prototype.forEach.call(blist.children, function (l) { l.classList.remove('ins-before', 'ins-after'); }); }

  function add(type, at) {
    var def = B.reg[type];
    var blk = { id: uid(), type: type, props: clone(def.defaults), style: styleFor(type) };
    snapshot();
    if (typeof at === 'number') blocks.splice(at, 0, blk); else blocks.push(blk);
    sel = blk.id; changed(true); renderInspector();
    setTimeout(function () { try { frame.contentWindow.postMessage({ kioskSelect: sel, scroll: true }, '*'); } catch (e) {} }, 500);
  }
  function move(id, to) {
    var from = find(id); if (from < 0) return;
    if (to > from) to--;
    if (to === from) return;
    snapshot(); blocks.splice(to, 0, blocks.splice(from, 1)[0]); changed(true);
  }
  function dropAt(idx) {
    if (!drag) return;
    if (drag.kind === 'new') add(drag.type, idx); else move(drag.id, idx);
  }

  /* ---------- structure list ---------- */
  function listIndex(y) {
    var kids = blist.children;
    for (var i = 0; i < kids.length; i++) { var r = kids[i].getBoundingClientRect(); if (y < r.top + r.height / 2) return i; }
    return kids.length;
  }
  function markList(i) {
    clearMarks(); var kids = blist.children; if (!kids.length) return;
    if (i >= kids.length) kids[kids.length - 1].classList.add('ins-after'); else kids[i].classList.add('ins-before');
  }
  [blist, $('#dropzone')].forEach(function (zone) {
    zone.addEventListener('dragover', function (e) { if (!drag) return; e.preventDefault(); e.dataTransfer.dropEffect = drag.kind === 'new' ? 'copy' : 'move'; if (zone === blist) markList(listIndex(e.clientY)); });
    zone.addEventListener('dragleave', function (e) { if (!zone.contains(e.relatedTarget)) clearMarks(); });
    zone.addEventListener('drop', function (e) { e.preventDefault(); var i = zone === blist ? listIndex(e.clientY) : blocks.length; clearMarks(); dropAt(i); endDrag(); });
  });
  function renderList() {
    blist.innerHTML = '';
    $('#empty').hidden = blocks.length > 0;
    blocks.forEach(function (b) {
      var def = B.reg[b.type]; if (!def) return;
      var li = d.createElement('li'); li.draggable = true; li.dataset.id = b.id; if (b.id === sel) li.className = 'on';
      li.innerHTML = '<span class="grip" title="Drag to reorder">⠿</span><span class="nm">' + esc(def.label.replace(' (current)', '')) + (b.current ? ' <span class="cur">CURRENT</span>' : '') + '</span>' +
        '<button type="button" data-a="up" title="Move up">↑</button><button type="button" data-a="down" title="Move down">↓</button><button type="button" data-a="dup" title="Duplicate">⧉</button><button type="button" data-a="del" title="Delete">✕</button>';
      li.addEventListener('click', function (e) { var a = e.target.getAttribute('data-a'); if (!a) { select(b.id, true); return; } e.stopPropagation(); action(a, b.id); });
      li.addEventListener('dragstart', function (e) { drag = { kind: 'move', id: b.id }; li.classList.add('drag'); startDrag(e, 'move'); });
      li.addEventListener('dragend', function () { li.classList.remove('drag'); endDrag(); });
      blist.appendChild(li);
    });
  }
  function action(a, id) {
    var i = find(id); if (i < 0) return;
    if (a === 'up' && i > 0) { snapshot(); blocks.splice(i - 1, 0, blocks.splice(i, 1)[0]); changed(true); }
    else if (a === 'down' && i < blocks.length - 1) { snapshot(); blocks.splice(i + 1, 0, blocks.splice(i, 1)[0]); changed(true); }
    else if (a === 'dup') { snapshot(); var c = clone(blocks[i]); c.id = uid(); delete c.current; blocks.splice(i + 1, 0, c); sel = c.id; changed(true); renderInspector(); }
    else if (a === 'del') { if (!confirm('Delete this block?')) return; snapshot(); blocks.splice(i, 1); if (sel === id) { sel = null; renderInspector(); } changed(true); }
  }
  function select(id, scroll) {
    sel = id; renderList(); renderInspector();
    try { frame.contentWindow.postMessage({ kioskSelect: id, scroll: !!scroll }, '*'); } catch (e) {}
  }

  /* ---------- drop directly on the live preview ---------- */
  shield.addEventListener('dragover', function (e) {
    if (!drag || !frame) return; e.preventDefault();
    var r = frame.getBoundingClientRect(), sc = r.width / (frame.offsetWidth || r.width) || 1;
    var x = (e.clientX - r.left) / sc, y = (e.clientY - r.top) / sc, idx = blocks.length, top = null;
    try {
      var el = frame.contentDocument.elementFromPoint(x, y), bl = el && el.closest ? el.closest('[data-bid]') : null;
      if (bl) { var br = bl.getBoundingClientRect(), i = find(bl.getAttribute('data-bid')), before = y < br.top + br.height / 2; idx = i + (before ? 0 : 1); top = (before ? br.top : br.bottom) * sc; }
      else { var all = frame.contentDocument.querySelectorAll('[data-bid]'); if (all.length) top = all[all.length - 1].getBoundingClientRect().bottom * sc; }
    } catch (x2) {}
    shield.dataset.idx = idx;
    if (top !== null) { dropline.style.display = 'block'; dropline.style.top = Math.max(0, Math.min(top, wrap.offsetHeight - 4)) + 'px'; }
  });
  shield.addEventListener('dragleave', function () { dropline.style.display = 'none'; });
  shield.addEventListener('drop', function (e) { e.preventDefault(); var i = parseInt(shield.dataset.idx, 10); dropline.style.display = 'none'; dropAt(isNaN(i) ? blocks.length : i); endDrag(); });

  /* ---------- inspector ---------- */
  function field(f, val, scope) {
    var key = f[0], label = f[1], type = f[2], h = '<label class="fld"><span>' + esc(label) + '</span>';
    if (type === 'area') h += '<textarea data-' + scope + '="' + key + '" rows="5">' + esc(val) + '</textarea>';
    else if (type === 'number') h += '<input type="number" data-' + scope + '="' + key + '" value="' + esc(val) + '">';
    else if (type === 'select') { h += '<select data-' + scope + '="' + key + '">'; Object.keys(f[3]).forEach(function (o) { h += '<option value="' + esc(o) + '"' + (String(val) === o ? ' selected' : '') + '>' + esc(f[3][o]) + '</option>'; }); h += '</select>'; }
    else if (type === 'category') { h += '<select data-' + scope + '="' + key + '"><option value="">— choose —</option>'; B.cats.forEach(function (c) { h += '<option value="' + esc(c[0]) + '"' + (String(val) === c[0] ? ' selected' : '') + '>' + esc(c[1]) + '</option>'; }); h += '</select>'; }
    else h += '<input type="text" data-' + scope + '="' + key + '" value="' + esc(val) + '">';
    return h + '</label>';
  }
  function sopt(map, cur) { var h = ''; Object.keys(map).forEach(function (k) { h += '<option value="' + k + '"' + (cur === k ? ' selected' : '') + '>' + map[k] + '</option>'; }); return h; }
  function renderInspector() {
    var i = sel ? find(sel) : -1;
    if (i < 0) { insp.innerHTML = '<p class="bld-empty">Select a block in the preview or in the list to edit it. Double-click text in the preview to jump to its field.</p>'; return; }
    var b = blocks[i], def = B.reg[b.type], st = b.style || {};
    var h = '<h3>' + esc(def.label.replace(' (current)', '')) + ' — content</h3>';
    if (b.type.indexOf('home_') === 0) h += '<p class="hint2">Part of your current home page. ' + (b.type === 'home_hero' ? 'These texts are the same as Settings → General → Hero.' : (b.type === 'home_panels' ? 'Built automatically from your departments (edit them under Departments).' : 'Content comes from your products; change the options below.')) + '</p>';
    def.fields.forEach(function (f) { h += field(f, b.props[f[0]], 'p'); });
    h += '<h3>Layout &amp; style</h3>' +
      '<label class="fld"><span>Spacing</span><select data-s="pad">' + sopt({ none: 'None', sm: 'Small', md: 'Medium', lg: 'Large', xl: 'Extra large' }, st.pad || 'md') + '</select></label>' +
      '<label class="fld"><span>Text alignment</span><select data-s="align">' + sopt({ left: 'Left', center: 'Center', right: 'Right' }, st.align || 'left') + '</select></label>' +
      '<label class="fld"><span>Width</span><select data-s="width">' + sopt({ box: 'Boxed', narrow: 'Narrow', full: 'Full width' }, st.width || 'box') + '</select></label>' +
      '<label class="fld"><span>Visibility</span><select data-s="hide">' + sopt({ '': 'Show on all devices', mobile: 'Hide on mobile', desktop: 'Hide on desktop' }, st.hide || '') + '</select></label>' +
      '<div class="g2"><label class="fld"><span>Background</span><input type="color" data-s="bg" value="' + (st.bg || '#0b0b0d') + '"></label><label class="fld"><span>Text colour</span><input type="color" data-s="color" value="' + (st.color || '#f3f1ec') + '"></label></div>' +
      '<button type="button" class="btn sm ghost" id="clearcolors">Reset colours</button>';
    insp.innerHTML = h;
  }
  insp.addEventListener('input', function (e) {
    var i = find(sel); if (i < 0) return; var b = blocks[i], t = e.target;
    if (t.dataset.p !== undefined) b.props[t.dataset.p] = t.value;
    else if (t.dataset.s !== undefined) { b.style = b.style || {}; b.style[t.dataset.s] = t.value; }
    else return;
    if (!insp.dataset.snap) { snapshot(); insp.dataset.snap = '1'; setTimeout(function () { delete insp.dataset.snap; }, 1200); }
    changed(true);
  });
  insp.addEventListener('change', function (e) { if (e.target.tagName === 'SELECT') insp.dispatchEvent(new Event('input')); });
  insp.addEventListener('click', function (e) {
    if (e.target.id !== 'clearcolors') return;
    var i = find(sel); if (i < 0) return; snapshot(); blocks[i].style.bg = ''; blocks[i].style.color = ''; changed(true); renderInspector();
  });

  /* ---------- toolbar ---------- */
  $('#devices').addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    Array.prototype.forEach.call(this.children, function (x) { x.classList.toggle('on', x === b); });
    wrap.className = 'devwrap ' + (b.dataset.w || '');
  });
  $('#undo').addEventListener('click', function () {
    if (!history.length) return; blocks = JSON.parse(history.pop()); $('#undo').disabled = !history.length;
    if (sel && find(sel) < 0) sel = null; dirty = true; setState('Unsaved changes', false, true); renderList(); renderInspector(); schedulePreview();
  });
  function save() {
    if (saving) return; saving = true; setState('Saving…');
    var fd = new FormData();
    fd.append('_token', B.csrf); fd.append('target', B.target); fd.append('id', B.id || ''); fd.append('layout', JSON.stringify(blocks)); fd.append('use_builder', $('#use').checked ? '1' : '0');
    fetch(B.saveUrl, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); }).then(function (j) { saving = false; if (j.ok) { dirty = false; blocks.forEach(function (b) { delete b.current; }); renderList(); setState('Saved ' + j.saved); } else setState('Save failed', true); })
      .catch(function () { saving = false; setState('Save failed', true); });
  }
  $('#save').addEventListener('click', save);
  $('#use').addEventListener('change', function () { dirty = true; setState('Unsaved changes', false, true); });
  d.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); save(); } if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !/input|textarea/i.test(e.target.tagName)) { e.preventDefault(); $('#undo').click(); } });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  /* reset + templates */
  $('#reset').addEventListener('click', function () {
    if (!confirm('Reset to the original layout? Your current blocks will be replaced (you can undo).')) return;
    snapshot(); blocks = clone(B.original); sel = null; changed(true); renderInspector();
  });
  function renderTpls() {
    var h = ''; tpls.forEach(function (t) { h += '<div class="tpl-item"><button type="button" class="btn sm ghost use" data-id="' + esc(t.id) + '">⤓ ' + esc(t.name) + '</button><button type="button" class="btn sm ghost del" data-del="' + esc(t.id) + '" title="Delete template">✕</button></div>'; });
    $('#tpls').innerHTML = h;
  }
  function tplPost(url, fd) { fd.append('_token', B.csrf); return fetch(url, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }); }
  $('#savetpl').addEventListener('click', function () {
    var n = prompt('Name this template:', B.target === 'home' ? 'Home layout' : 'Page layout'); if (n === null) return;
    var fd = new FormData(); fd.append('name', n); fd.append('layout', JSON.stringify(blocks));
    tplPost(B.tplUrl, fd).then(function (j) { if (j.ok) { tpls = j.templates; renderTpls(); } });
  });
  $('#tpls').addEventListener('click', function (e) {
    var u = e.target.closest('.use'), x = e.target.closest('[data-del]');
    if (u) {
      var t = tpls.filter(function (z) { return z.id === u.dataset.id; })[0]; if (!t) return;
      if (!confirm('Load “' + t.name + '”? It replaces the current blocks (undo available).')) return;
      snapshot(); blocks = clone(t.layout).map(function (b) { b.id = uid(); return b; }); sel = null; changed(true); renderInspector();
    } else if (x) {
      if (!confirm('Delete this template?')) return;
      var fd = new FormData(); fd.append('id', x.dataset.del); tplPost(B.tplDelUrl, fd).then(function (j) { if (j.ok) { tpls = j.templates; renderTpls(); } });
    }
  });

  renderList(); renderTpls(); preview();
  if (B.seeded) setState('Current layout loaded', false, false);
})();
