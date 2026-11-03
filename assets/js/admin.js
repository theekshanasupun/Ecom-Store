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
})();
