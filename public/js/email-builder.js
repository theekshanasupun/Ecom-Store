/* KIOSK email builder — drag & drop blocks, live preview, inspector, undo/redo, templates, test send. */
(function () {
  'use strict';
  var B = window.EMAILB, d = document;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var blocks = (B.blocks || []).slice();
  var sel = null, undo = [], redo = [], dirty = false, saving = false, timer = null, seq = 0, ready = false;
  var drag = null, dropIdx = -1, lastFocus = null, pcache = {};
  var frame = $('#eb-frame'), list = $('#eb-list'), insp = $('#eb-insp'), state = $('#eb-state'), overlay = $('#eb-overlay'), cline = $('#eb-cline');
  var subj = $('#eb-subject'), pre = $('#eb-pre');

  function uid() { return Math.random().toString(36).slice(2, 10); }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function find(id) { for (var i = 0; i < blocks.length; i++) if (blocks[i].id === id) return i; return -1; }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function setState(t, bad) { state.textContent = t; state.style.color = bad ? '#ff6b7d' : ''; }
  function post(url, data) {
    var fd = new FormData(); fd.append('_token', B.csrf);
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json, text/html' } });
  }

  /* ---------- history ---------- */
  function syncHist() { $('#eb-undo').disabled = !undo.length; $('#eb-redo').disabled = !redo.length; }
  function snapshot() { undo.push(JSON.stringify(blocks)); if (undo.length > 60) undo.shift(); redo = []; syncHist(); }
  function changed(noSnap) { if (!noSnap) snapshot(); dirty = true; setState('Unsaved changes…'); renderList(); schedulePreview(); }
  function doUndo() { if (!undo.length) return; redo.push(JSON.stringify(blocks)); blocks = JSON.parse(undo.pop()); afterHist(); }
  function doRedo() { if (!redo.length) return; undo.push(JSON.stringify(blocks)); blocks = JSON.parse(redo.pop()); afterHist(); }
  function afterHist() { if (sel && find(sel) < 0) sel = null; syncHist(); dirty = true; setState('Unsaved changes…'); renderList(); renderInspector(); schedulePreview(); }

  /* ---------- live preview (patches the iframe body in place: no flicker, scroll preserved) ---------- */
  function schedulePreview() { clearTimeout(timer); timer = setTimeout(preview, 150); }
  function preview() {
    var my = ++seq;
    post(B.previewUrl, { layout: JSON.stringify(blocks), subject: subj.value, preheader: pre.value })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        if (my !== seq) return;
        var doc = frame.contentDocument;
        if (!ready || !doc || !doc.body) { frame.srcdoc = html; return; }
        var p = new DOMParser().parseFromString(html, 'text/html');
        var y = frame.contentWindow.scrollY;
        doc.body.innerHTML = p.body.innerHTML;
        doc.body.setAttribute('style', p.body.getAttribute('style') || '');
        markSel(false);
        frame.contentWindow.scrollTo(0, y);
      }).catch(function () { setState('Preview failed', true); });
  }
  frame.addEventListener('load', function () {
    var doc = frame.contentDocument; if (!doc) return;
    ready = true;
    var st = doc.createElement('style');
    st.textContent = '[data-bid]{cursor:pointer;outline:2px solid transparent;outline-offset:-3px;transition:outline-color .12s}[data-bid]:hover{outline-color:rgba(120,160,20,.55)}.eb-sel{outline-color:#c6ff3d!important;box-shadow:inset 0 0 0 4px rgba(198,255,61,.25)}';
    doc.head.appendChild(st);
    doc.addEventListener('click', function (e) {
      e.preventDefault();
      var t = e.target.closest ? e.target.closest('[data-bid]') : null;
      if (t) select(t.getAttribute('data-bid'), false);
    }, true);
    doc.addEventListener('submit', function (e) { e.preventDefault(); }, true);
    markSel(false);
  });
  function markSel(scroll) {
    var doc = frame.contentDocument; if (!doc) return;
    var els = doc.querySelectorAll('[data-bid]');
    Array.prototype.forEach.call(els, function (x) { x.classList.toggle('eb-sel', x.getAttribute('data-bid') === sel); });
    if (scroll && sel) { var el = doc.querySelector('[data-bid="' + sel + '"]'); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
  }

  /* ---------- palette ---------- */
  var pal = $('#eb-palette');
  Object.keys(B.reg).forEach(function (k) {
    var b = d.createElement('button'); b.type = 'button'; b.draggable = true; b.dataset.type = k;
    b.innerHTML = '<i>' + esc(B.reg[k].icon) + '</i>' + esc(B.reg[k].label);
    b.addEventListener('click', function () { add(k); });
    b.addEventListener('dragstart', function (e) { startDrag(e, { kind: 'new', type: k }); });
    b.addEventListener('dragend', endDrag);
    pal.appendChild(b);
  });
  function newBlock(type) {
    var def = B.reg[type];
    return { id: uid(), type: type, props: clone(def.defaults), style: { bg: '', color: '', pad: 'md', align: type === 'header' ? 'center' : 'left', size: '', btn_bg: '', btn_color: '', radius: '10' } };
  }
  function add(type, at) {
    var blk = newBlock(type);
    snapshot();
    if (typeof at === 'number' && at >= 0 && at <= blocks.length) blocks.splice(at, 0, blk); else blocks.push(blk);
    sel = blk.id; changed(true); renderInspector();
    setTimeout(function () { markSel(true); }, 700);
  }

  /* ---------- drag & drop (palette → list / preview, reorder) ---------- */
  function startDrag(e, info) {
    drag = info;
    try { e.dataTransfer.effectAllowed = info.kind === 'new' ? 'copy' : 'move'; e.dataTransfer.setData('text/plain', info.kind + ':' + (info.type || info.id)); } catch (x) {}
    setTimeout(function () { d.body.classList.add('eb-dragging'); }, 0);
  }
  function endDrag() {
    drag = null; dropIdx = -1; d.body.classList.remove('eb-dragging'); cline.style.display = 'none';
    var l = $('.eb-line', list); if (l) l.remove();
    Array.prototype.forEach.call(list.querySelectorAll('.drag'), function (x) { x.classList.remove('drag'); });
  }
  function commitDrop() {
    if (!drag || dropIdx < 0) return endDrag();
    var idx = dropIdx, info = drag; endDrag();
    if (info.kind === 'new') { add(info.type, idx); return; }
    var from = find(info.id); if (from < 0) return;
    if (idx > from) idx--;
    if (idx === from) return;
    snapshot(); var m = blocks.splice(from, 1)[0]; blocks.splice(idx, 0, m); changed(true);
  }
  // structure list as drop target (indicator line between rows)
  function listIndex(y) {
    var items = Array.prototype.filter.call(list.children, function (c) { return c.tagName === 'LI' && !c.classList.contains('eb-line'); });
    for (var i = 0; i < items.length; i++) { var r = items[i].getBoundingClientRect(); if (y < r.top + r.height / 2) return i; }
    return items.length;
  }
  list.addEventListener('dragover', function (e) {
    if (!drag) return; e.preventDefault();
    var idx = listIndex(e.clientY); if (idx === dropIdx && $('.eb-line', list)) return;
    dropIdx = idx;
    var old = $('.eb-line', list); if (old) old.remove();
    var line = d.createElement('li'); line.className = 'eb-line';
    var items = Array.prototype.filter.call(list.children, function (c) { return c.tagName === 'LI'; });
    if (idx >= items.length) list.appendChild(line); else list.insertBefore(line, items[idx]);
  });
  list.addEventListener('drop', function (e) { e.preventDefault(); commitDrop(); });
  list.addEventListener('dragleave', function (e) { if (!list.contains(e.relatedTarget)) { var l = $('.eb-line', list); if (l) l.remove(); dropIdx = -1; } });
  // preview canvas as drop target (overlay on top of the iframe while dragging)
  overlay.addEventListener('dragover', function (e) {
    if (!drag) return; e.preventDefault();
    var doc = frame.contentDocument, fr = frame.getBoundingClientRect(), ov = overlay.getBoundingClientRect();
    var els = doc ? doc.querySelectorAll('[data-bid]') : [], idx = els.length, yLine = null;
    for (var i = 0; i < els.length; i++) {
      var r = els[i].getBoundingClientRect(), top = r.top + fr.top;
      if (e.clientY < top + r.height / 2) { idx = i; yLine = top; break; }
    }
    if (yLine === null) { if (els.length) { var lr = els[els.length - 1].getBoundingClientRect(); yLine = lr.bottom + fr.top; } else yLine = fr.top + 8; }
    dropIdx = idx;
    cline.style.display = 'block'; cline.style.top = Math.max(0, Math.min(ov.height - 4, yLine - ov.top - 2)) + 'px';
    // overlay is positioned inside the stage; line is relative to overlay
  });
  overlay.addEventListener('drop', function (e) { e.preventDefault(); commitDrop(); });
  d.addEventListener('dragend', endDrag);

  /* ---------- structure list ---------- */
  function summary(b) {
    var p = b.props || {}, t = p.title || p.text || p.body || p.label || p.quote || p.code || '';
    if (b.type === 'product' && p.product_id && pcache[p.product_id]) t = pcache[p.product_id].name;
    return String(t).replace(/<[^>]*>/g, '').slice(0, 40);
  }
  function renderList() {
    list.innerHTML = '';
    blocks.forEach(function (b) {
      var li = d.createElement('li'); li.draggable = true; li.dataset.id = b.id; if (b.id === sel) li.className = 'on';
      li.innerHTML = '<span class="grip" title="Drag to reorder">⠿</span><span class="nm">' + esc(B.reg[b.type].label) + '<small>' + esc(summary(b)) + '</small></span>' +
        '<button type="button" data-a="up" title="Move up">↑</button><button type="button" data-a="down" title="Move down">↓</button><button type="button" data-a="dup" title="Duplicate">⧉</button><button type="button" data-a="del" title="Delete">✕</button>';
      li.addEventListener('click', function (e) { var a = e.target.getAttribute('data-a'); if (!a) { select(b.id, true); return; } e.stopPropagation(); action(a, b.id); });
      li.addEventListener('dragstart', function (e) { li.classList.add('drag'); startDrag(e, { kind: 'move', id: b.id }); });
      li.addEventListener('dragend', endDrag);
      list.appendChild(li);
    });
    if (!blocks.length) { var p = d.createElement('li'); p.style.cssText = 'cursor:default;color:var(--mut);justify-content:center'; p.textContent = 'Drop blocks here'; p.className = 'empty'; list.appendChild(p); }
  }
  function action(a, id) {
    var i = find(id); if (i < 0) return;
    if (a === 'up' && i > 0) { snapshot(); blocks.splice(i - 1, 0, blocks.splice(i, 1)[0]); changed(true); }
    else if (a === 'down' && i < blocks.length - 1) { snapshot(); blocks.splice(i + 1, 0, blocks.splice(i, 1)[0]); changed(true); }
    else if (a === 'dup') { snapshot(); var c = clone(blocks[i]); c.id = uid(); blocks.splice(i + 1, 0, c); sel = c.id; changed(true); renderInspector(); }
    else if (a === 'del') { snapshot(); blocks.splice(i, 1); if (sel === id) { sel = null; renderInspector(); } changed(true); }
  }
  function select(id, scroll) { sel = id; renderList(); renderInspector(); markSel(!!scroll); }

  /* ---------- inspector ---------- */
  function sopt(map, cur) { var h = ''; Object.keys(map).forEach(function (k) { h += '<option value="' + esc(k) + '"' + (String(cur) === k ? ' selected' : '') + '>' + esc(map[k]) + '</option>'; }); return h; }
  function field(f, val) {
    var key = f[0], label = f[1], type = f[2], h = '<label class="fld"><span>' + esc(label) + '</span>';
    if (type === 'area') h += '<textarea data-p="' + key + '" rows="5">' + esc(val) + '</textarea>';
    else if (type === 'number') h += '<input type="number" data-p="' + key + '" value="' + esc(val) + '">';
    else if (type === 'select') { h += '<select data-p="' + key + '">'; Object.keys(f[3]).forEach(function (o) { h += '<option value="' + esc(o) + '"' + (String(val) === o ? ' selected' : '') + '>' + esc(f[3][o]) + '</option>'; }); h += '</select>'; }
    else if (type === 'category') { h += '<select data-p="' + key + '"><option value="">— choose —</option>'; B.cats.forEach(function (c) { h += '<option value="' + esc(c[0]) + '"' + (String(val) === c[0] ? ' selected' : '') + '>' + esc(c[1]) + '</option>'; }); h += '</select>'; }
    else if (type === 'product') {
      var cur = val && pcache[val] ? pcache[val].name : (val ? 'Product #' + val : 'None selected');
      return '<div class="fld eb-pp" data-pp="' + key + '"><span>' + esc(label) + '</span><div class="cur">' + esc(cur) + '</div><input type="search" placeholder="Search products…" data-search><div class="res"></div></div>';
    }
    else h += '<input type="text" data-p="' + key + '" value="' + esc(val) + '"' + (type === 'image' ? ' placeholder="https://… or /uploads/…"' : '') + '>';
    return h + '</label>';
  }
  function colorField(label, key, val, dflt) {
    return '<div class="fld"><span>' + esc(label) + '</span><div class="colorrow"><input type="color" data-s="' + key + '" value="' + esc(val || dflt) + '"><button type="button" data-clear="' + key + '" title="Use default">✕</button></div></div>';
  }
  function renderInspector() {
    var i = sel ? find(sel) : -1;
    if (i < 0) { insp.innerHTML = '<p class="eb-empty">Select a block in the preview or the structure list to edit it.</p>'; return; }
    var b = blocks[i], def = B.reg[b.type], st = b.style || {};
    var h = '<h3>' + esc(def.label) + ' — content</h3>';
    def.fields.forEach(function (f) { h += field(f, b.props[f[0]]); });
    h += '<h3>Style</h3><div class="row2">' +
      '<label class="fld"><span>Spacing</span><select data-s="pad">' + sopt({ none: 'None', sm: 'Small', md: 'Medium', lg: 'Large' }, st.pad || 'md') + '</select></label>' +
      '<label class="fld"><span>Align</span><select data-s="align">' + sopt({ left: 'Left', center: 'Center', right: 'Right' }, st.align || 'left') + '</select></label></div>' +
      '<div class="row2">' + colorField('Background', 'bg', st.bg, '#ffffff') + colorField('Text colour', 'color', st.color, '#1a1a1f') + '</div>' +
      '<label class="fld"><span>Font size (px, 0 = automatic)</span><input type="number" min="0" max="48" data-s="size" value="' + esc(st.size || 0) + '"></label>';
    if ((def.caps || []).indexOf('btn') >= 0) {
      h += '<div class="row2">' + colorField('Button colour', 'btn_bg', st.btn_bg, B.accent) + colorField('Button text', 'btn_color', st.btn_color, '#0b0b0d') + '</div>' +
        '<label class="fld"><span>Button corner radius (px)</span><input type="number" min="0" max="40" data-s="radius" value="' + esc(st.radius === '' || st.radius == null ? 10 : st.radius) + '"></label>';
    }
    insp.innerHTML = h;
  }
  var histTimer = null;
  function histOnce() { if (histTimer) return; snapshot(); histTimer = setTimeout(function () { histTimer = null; }, 1200); }
  insp.addEventListener('input', function (e) {
    var i = find(sel); if (i < 0) return; var b = blocks[i], t = e.target;
    if (t.hasAttribute('data-search')) { searchProducts(t); return; }
    if (t.dataset.p === undefined && t.dataset.s === undefined) return;
    histOnce(); // snapshot BEFORE mutating so undo restores the previous value
    if (t.dataset.p !== undefined) b.props[t.dataset.p] = t.value;
    else { b.style = b.style || {}; b.style[t.dataset.s] = t.value; }
    changed(true);
  });
  insp.addEventListener('click', function (e) {
    var i = find(sel); if (i < 0) return;
    var c = e.target.closest('[data-clear]');
    if (c) { snapshot(); blocks[i].style[c.dataset.clear] = ''; changed(true); renderInspector(); return; }
    var pick = e.target.closest('[data-pick]');
    if (pick) {
      var pp = pick.closest('[data-pp]'), pid = pick.dataset.pick;
      pcache[pid] = { name: pick.dataset.name };
      snapshot(); blocks[i].props[pp.dataset.pp] = pid; changed(true); renderInspector();
    }
  });
  var sTimer = null;
  function searchProducts(inp) {
    clearTimeout(sTimer);
    sTimer = setTimeout(function () {
      fetch(B.productsUrl + '?q=' + encodeURIComponent(inp.value), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (rows) {
        var res = inp.parentNode.querySelector('.res'); res.innerHTML = '';
        rows.forEach(function (p) {
          pcache[p.id] = { name: p.name };
          var b = d.createElement('button'); b.type = 'button'; b.dataset.pick = p.id; b.dataset.name = p.name;
          b.innerHTML = (p.image ? '<img src="' + esc(p.image) + '" alt="">' : '') + '<span>' + esc(p.name) + ' <small>' + esc(p.price) + '</small></span>';
          res.appendChild(b);
        });
        if (!rows.length) res.innerHTML = '<small class="muted">No products found.</small>';
      });
    }, 200);
  }
  // resolve names of already-selected products
  (function () {
    var ids = []; blocks.forEach(function (b) { if (b.type === 'product' && b.props.product_id) ids.push(b.props.product_id); });
    if (!ids.length) return;
    fetch(B.productsUrl + '?ids=' + ids.join(','), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (rows) { rows.forEach(function (p) { pcache[p.id] = { name: p.name }; }); renderList(); renderInspector(); });
  })();

  /* ---------- merge tags ---------- */
  var tagbar = $('#eb-tags');
  B.tags.forEach(function (t) {
    var b = d.createElement('button'); b.type = 'button'; b.textContent = t;
    b.addEventListener('mousedown', function (e) { e.preventDefault(); });
    b.addEventListener('click', function () {
      var el = lastFocus; if (!el || !d.body.contains(el)) el = subj;
      var s = el.selectionStart == null ? el.value.length : el.selectionStart, en = el.selectionEnd == null ? s : el.selectionEnd;
      el.value = el.value.slice(0, s) + t + el.value.slice(en);
      el.focus(); try { el.setSelectionRange(s + t.length, s + t.length); } catch (x) {}
      el.dispatchEvent(new Event('input', { bubbles: true }));
    });
    tagbar.appendChild(b);
  });
  d.addEventListener('focusin', function (e) { var t = e.target; if ((t.tagName === 'INPUT' || t.tagName === 'TEXTAREA') && t.type !== 'color' && t.type !== 'search') lastFocus = t; });
  [subj, pre].forEach(function (el) { el.addEventListener('input', function () { dirty = true; setState('Unsaved changes…'); schedulePreview(); }); });

  /* ---------- toolbar ---------- */
  $('#eb-devices').addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    Array.prototype.forEach.call(this.children, function (x) { x.classList.toggle('on', x === b); });
    frame.className = 'eb-frame ' + (b.dataset.w || '');
  });
  $('#eb-undo').addEventListener('click', doUndo);
  $('#eb-redo').addEventListener('click', doRedo);
  function save() {
    if (saving) return; saving = true; setState('Saving…');
    post(B.saveUrl, { layout: JSON.stringify(blocks), subject: subj.value, preheader: pre.value, step: B.step || 0 })
      .then(function (r) { return r.json(); })
      .then(function (j) { saving = false; if (j.ok) { dirty = false; setState('Saved ' + j.saved); } else setState(j.error || 'Save failed', true); })
      .catch(function () { saving = false; setState('Save failed', true); });
  }
  $('#eb-save').addEventListener('click', save);
  d.addEventListener('keydown', function (e) {
    var mod = e.ctrlKey || e.metaKey;
    if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); save(); return; }
    var tag = (e.target.tagName || '').toLowerCase();
    if (mod && e.key.toLowerCase() === 'z' && tag !== 'input' && tag !== 'textarea' && tag !== 'select') { e.preventDefault(); e.shiftKey ? doRedo() : doUndo(); }
    else if (mod && e.key.toLowerCase() === 'y' && tag !== 'input' && tag !== 'textarea') { e.preventDefault(); doRedo(); }
    if (e.key === 'Escape') { closeModals(); }
  });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  /* ---------- modals: templates + test ---------- */
  function closeModals() { Array.prototype.forEach.call(d.querySelectorAll('.eb-modal'), function (m) { m.hidden = true; }); }
  Array.prototype.forEach.call(d.querySelectorAll('.eb-modal'), function (m) {
    m.addEventListener('click', function (e) { if (e.target === m || e.target.hasAttribute('data-close')) m.hidden = true; });
  });
  var tplm = $('#eb-tplm'), tpls = $('#eb-tpls');
  Object.keys(B.templates).forEach(function (k) {
    var t = B.templates[k], b = d.createElement('button'); b.type = 'button';
    var mini = '<div class="mini">' + t.blocks.slice(0, 6).map(function (x) { var dk = x.style && x.style.bg === '#0b0b0d' ? 'd' : (x.style && x.style.bg === '#c6ff3d' ? 'a' : ''); return '<i class="' + dk + '" style="height:' + (x.type === 'hero' ? 26 : x.type === 'product_grid' ? 20 : 9) + 'px"></i>'; }).join('') + '</div>';
    b.innerHTML = mini + '<b>' + esc(t.label) + '</b><small>' + esc(t.desc) + '</small>';
    b.addEventListener('click', function () {
      if (blocks.length && k !== 'blank' && !confirm('Replace the current design with this template?')) return;
      if (blocks.length && k === 'blank' && !confirm('Clear the design and start blank?')) return;
      snapshot();
      blocks = clone(t.blocks).map(function (x) { x.id = uid(); x.style = Object.assign({ bg: '', color: '', pad: 'md', align: 'left', size: '', btn_bg: '', btn_color: '', radius: '10' }, x.style || {}); return x; });
      if (!subj.value.trim() && t.subject) subj.value = t.subject;
      sel = null; tplm.hidden = true; changed(true); renderInspector();
    });
    tpls.appendChild(b);
  });
  $('#eb-tpl').addEventListener('click', function () { tplm.hidden = false; });
  var testm = $('#eb-testm');
  $('#eb-test').addEventListener('click', function () { $('#eb-testmsg').textContent = 'Uses sample customer, order and product data.'; testm.hidden = false; });
  $('#eb-testgo').addEventListener('click', function () {
    var btn = this, msg = $('#eb-testmsg'); btn.disabled = true; msg.textContent = 'Sending…';
    post(B.testUrl, { to: $('#eb-to').value, layout: JSON.stringify(blocks), subject: subj.value, preheader: pre.value })
      .then(function (r) { return r.json(); }).then(function (j) { btn.disabled = false; msg.textContent = j.ok ? 'Test email sent ✓' : (j.error || 'Failed'); msg.style.color = j.ok ? '' : '#ff6b7d'; })
      .catch(function () { btn.disabled = false; msg.textContent = 'Failed to send.'; });
  });

  /* ---------- go ---------- */
  renderList(); renderInspector(); preview();
  if (!blocks.length) tplm.hidden = false;
})();
