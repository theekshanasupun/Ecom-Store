(function () {
  var $ = function (s, r) { return (r || document).querySelector(s); };
  // QR on the membership card
  var card = $('#loy-card');
  if (card && window.KQR) {
    var q = $('[data-qr]', card); if (q) q.innerHTML = KQR.svg(card.dataset.id, 96);
    var dl = $('[data-card-dl]', card);
    if (dl) dl.addEventListener('click', function () {
      var c = document.createElement('canvas'); c.width = 900; c.height = 540; var x = c.getContext('2d');
      var g = x.createLinearGradient(0, 0, 900, 540); g.addColorStop(0, '#16161b'); g.addColorStop(1, card.dataset.color || '#c6ff3d'); x.fillStyle = g; x.fillRect(0, 0, 900, 540);
      x.fillStyle = '#fff'; x.font = '600 28px sans-serif'; x.fillText((card.dataset.brand || '').toUpperCase(), 48, 70);
      x.font = '800 64px sans-serif'; x.fillText(card.dataset.tier, 48, 170); x.font = '600 36px sans-serif'; x.fillText(card.dataset.name, 48, 380);
      x.font = '500 30px monospace'; x.fillText(card.dataset.id, 48, 430); x.font = '400 22px sans-serif'; x.fillText('Member since ' + card.dataset.since, 48, 480);
      var img = new Image(); img.onload = function () { x.fillStyle = '#fff'; x.fillRect(600, 250, 260, 260); x.drawImage(img, 610, 260, 240, 240); var a = document.createElement('a'); a.download = 'membership-card.png'; a.href = c.toDataURL('image/png'); a.click(); };
      img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(KQR.svg(card.dataset.id, 240));
    });
  }
  // copy buttons
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]'); if (!b) return; var i = $(b.dataset.copy); if (!i) return; i.select();
    try { navigator.clipboard.writeText(i.value); } catch (er) { document.execCommand('copy'); } b.textContent = 'Copied ✓';
  });
  function post(game) {
    var fd = new FormData(); fd.append('game', game); fd.append('_token', window.KIOSK ? KIOSK.csrf : '');
    return fetch('/account/loyalty/play', { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  // spin the wheel
  var wheel = $('#wheel'), spinBtn = $('[data-spin]'), msg = $('[data-game-msg]');
  var labels = []; if (wheel) { try { labels = JSON.parse(wheel.dataset.prizes); } catch (e) { } }
  var angle = 0;
  function draw() {
    if (!wheel || !labels.length) return; var x = wheel.getContext('2d'), n = labels.length, a = 2 * Math.PI / n, cx = 120, cy = 120;
    x.clearRect(0, 0, 240, 240);
    labels.forEach(function (l, i) { x.beginPath(); x.moveTo(cx, cy); x.arc(cx, cy, 116, angle + i * a, angle + (i + 1) * a); x.fillStyle = i % 2 ? '#1b1b21' : '#c6ff3d'; x.fill(); x.save(); x.translate(cx, cy); x.rotate(angle + i * a + a / 2); x.fillStyle = i % 2 ? '#fff' : '#0b0b0d'; x.font = '600 11px sans-serif'; x.textAlign = 'right'; x.fillText(l.slice(0, 18), 108, 4); x.restore(); });
    x.beginPath(); x.moveTo(236, 120); x.lineTo(216, 110); x.lineTo(216, 130); x.fillStyle = '#ff6b7d'; x.fill();
  }
  draw();
  if (spinBtn) spinBtn.addEventListener('click', function () {
    spinBtn.disabled = true;
    post('spin').then(function (r) {
      if (!r.ok) { if (msg) msg.textContent = r.msg; return; }
      var n = labels.length, a = 2 * Math.PI / n, target = -(r.index * a + a / 2) + 2 * Math.PI * 5, start = angle, t0 = null;
      function step(t) { if (!t0) t0 = t; var p = Math.min(1, (t - t0) / 3500), e = 1 - Math.pow(1 - p, 3); angle = start + (target - start) * e; draw(); if (p < 1) requestAnimationFrame(step); else { if (msg) msg.textContent = r.msg; spinBtn.textContent = 'Spin the wheel (' + r.left + ' left)'; spinBtn.disabled = r.left <= 0; } }
      requestAnimationFrame(step);
    }).catch(function () { spinBtn.disabled = false; });
  });
  var sb = $('[data-scratch]'), sc = $('[data-scratch-card]');
  if (sb) sb.addEventListener('click', function () { sb.disabled = true; post('scratch').then(function (r) { sc.hidden = false; sc.textContent = r.ok ? r.msg : r.msg; sc.classList.add('rev'); }); });
})();
