/* KIOSK visual builder — block palette, drag-to-reorder list, live iframe preview, inspector, undo. */
(function () {
  'use strict';
  var B = window.BUILDER, d = document;
  var $ = function (s, c) { return (c || d).querySelector(s); };
  var blocks = (B.blocks || []).slice();
  var sel = null, history = [], dirty = false, timer = null, saving = false;
  var frame = $('#frame'), blist = $('#blist'), insp = $('#inspector'), state = $('#state');

  function uid() { return Math.random().toString(36).slice(2, 10); }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function find(id) { for (var i = 0; i < blocks.length; i++) if (blocks[i].id === id) return i; return -1; }
  function setState(t, bad) { state.textContent = t; state.style.color = bad ? '#ff6b7d' : ''; }

  function snapshot() { history.push(JSON.stringify(blocks)); if (history.length > 40) history.shift(); $('#undo').disabled = false; }
  function changed(noSnap) { if (!noSnap) snapshot(); dirty = true; setState('Unsaved changes…'); renderList(); schedulePreview(); }

  /* ---------- preview ---------- */
  function schedulePreview() { clearTimeout(timer); timer = setTimeout(preview, 350); }
  function preview() {
    var fd = new FormData(); fd.append('layout', JSON.stringify(blocks)); fd.append('_token', B.csrf);
    fetch(B.previewUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (h) {
      frame.srcdoc = h;
      frame.onload = function () { if (sel) frame.contentWindow.postMessage({ kioskSelect: sel }, '*'); };
    });
  }
  window.addEventListener('message', function (e) {
    if (e.data && e.data.kioskBlock) select(e.data.kioskBlock, false);
  });

  /* ---------- palette ---------- */
  var pal = $('#palette');
  Object.keys(B.reg).forEach(function (k) {
    var b = d.createElement('button'); b.type = 'button'; b.innerHTML = '<i>' + B.reg[k].icon + '</i>' + B.reg[k].label;
    b.addEventListener('click', function () { add(k); });
    pal.appendChild(b);
  });
  function add(type, at) {
    var def = B.reg[type];
    var blk = { id: uid(), type: type, props: clone(def.defaults), style: { bg: '', color: '', pad: 'md', align: type === 'hero' ? 'left' : 'left', width: 'box', hide: '' } };
    snapshot();
    if (typeof at === 'number') blocks.splice(at, 0, blk); else blocks.push(blk);
    sel = blk.id; changed(true); renderInspector();
    setTimeout(function () { try { frame.contentWindow.postMessage({ kioskSelect: sel, scroll: true }, '*'); } catch (e) {} }, 700);
  }

  /* ---------- structure list (drag + buttons) ---------- */
  var dragId = null;
  function renderList() {
    blist.innerHTML = '';
    $('#empty').hidden = blocks.length > 0;
    blocks.forEach(function (b, i) {
      var li = d.createElement('li'); li.draggable = true; li.dataset.id = b.id; if (b.id === sel) li.className = 'on';
      li.innerHTML = '<span class="grip" title="Drag to reorder">⠿</span><span class="nm">' + esc(B.reg[b.type].label) + '</span>' +
        '<button type="button" data-a="up" title="Move up">↑</button><button type="button" data-a="down" title="Move down">↓</button><button type="button" data-a="dup" title="Duplicate">⧉</button><button type="button" data-a="del" title="Delete">✕</button>';
      li.addEventListener('click', function (e) {
        var a = e.target.getAttribute('data-a');
        if (!a) { select(b.id, true); return; }
        e.stopPropagation(); action(a, b.id);
      });
      li.addEventListener('dragstart', function (e) { dragId = b.id; li.classList.add('drag'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', b.id); } catch (x) {} });
      li.addEventListener('dragend', function () { li.classList.remove('drag'); dragId = null; });
      li.addEventListener('dragover', function (e) { e.preventDefault(); li.classList.add('over'); });
      li.addEventListener('dragleave', function () { li.classList.remove('over'); });
      li.addEventListener('drop', function (e) {
        e.preventDefault(); li.classList.remove('over');
        var from = find(dragId), to = find(b.id); if (from < 0 || to < 0 || from === to) return;
        snapshot(); var m = blocks.splice(from, 1)[0]; blocks.splice(to, 0, m); changed(true);
      });
      blist.appendChild(li);
    });
  }
  function action(a, id) {
    var i = find(id); if (i < 0) return;
    if (a === 'up' && i > 0) { snapshot(); blocks.splice(i - 1, 0, blocks.splice(i, 1)[0]); changed(true); }
    else if (a === 'down' && i < blocks.length - 1) { snapshot(); blocks.splice(i + 1, 0, blocks.splice(i, 1)[0]); changed(true); }
    else if (a === 'dup') { snapshot(); var c = clone(blocks[i]); c.id = uid(); blocks.splice(i + 1, 0, c); sel = c.id; changed(true); renderInspector(); }
    else if (a === 'del') { if (!confirm('Delete this block?')) return; snapshot(); blocks.splice(i, 1); if (sel === id) { sel = null; renderInspector(); } changed(true); }
  }
  function select(id, scroll) {
    sel = id; renderList(); renderInspector();
    try { frame.contentWindow.postMessage({ kioskSelect: id, scroll: !!scroll }, '*'); } catch (e) {}
  }

  /* ---------- inspector ---------- */
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
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
    if (i < 0) { insp.innerHTML = '<p class="bld-empty">Select a block in the preview or in the list to edit it.</p>'; return; }
    var b = blocks[i], def = B.reg[b.type], st = b.style || {};
    var h = '<h3>' + esc(def.label) + ' — content</h3>';
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
  insp.addEventListener('click', function (e) {
    if (e.target.id !== 'clearcolors') return;
    var i = find(sel); if (i < 0) return; snapshot(); blocks[i].style.bg = ''; blocks[i].style.color = ''; changed(true); renderInspector();
  });

  /* ---------- toolbar ---------- */
  $('#devices').addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    Array.prototype.forEach.call(this.children, function (x) { x.classList.toggle('on', x === b); });
    frame.className = 'bld-frame ' + (b.dataset.w || '');
  });
  $('#undo').addEventListener('click', function () {
    if (!history.length) return; blocks = JSON.parse(history.pop()); $('#undo').disabled = !history.length;
    if (sel && find(sel) < 0) sel = null; dirty = true; setState('Unsaved changes…'); renderList(); renderInspector(); schedulePreview();
  });
  function save() {
    if (saving) return; saving = true; setState('Saving…');
    var fd = new FormData();
    fd.append('_token', B.csrf); fd.append('target', B.target); fd.append('id', B.id || ''); fd.append('layout', JSON.stringify(blocks)); fd.append('use_builder', $('#use').checked ? '1' : '0');
    fetch(B.saveUrl, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); }).then(function (j) { saving = false; if (j.ok) { dirty = false; setState('Saved ' + j.saved); } else setState('Save failed', true); })
      .catch(function () { saving = false; setState('Save failed', true); });
  }
  $('#save').addEventListener('click', save);
  $('#use').addEventListener('change', function () { dirty = true; setState('Unsaved changes…'); });
  d.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); save(); } });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  renderList(); preview();
})();
