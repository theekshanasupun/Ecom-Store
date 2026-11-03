/* Minimal QR code generator (byte mode, EC level M, versions 1-6, mask 0). window.KQR.svg(text) / window.KQR.matrix(text) */
(function () {
  var ECC = [0, 10, 16, 26, 18, 24, 16], BLK = [0, 1, 1, 1, 2, 2, 4];
  var ALIGN = [[], [], [6, 18], [6, 22], [6, 26], [6, 30], [6, 34]];
  function mul(a, b) { var r = 0; for (var i = 7; i >= 0; i--) { r = (r << 1) ^ ((r >>> 7) * 0x11D); r ^= ((b >>> i) & 1) * a; } return r & 255; }
  function rawModules(v) { var r = (16 * v + 128) * v + 64; if (v >= 2) { var n = Math.floor(v / 7) + 2; r -= (25 * n - 10) * n - 55; } return r; }
  function rsDiv(d) { var r = []; for (var i = 0; i < d - 1; i++) r.push(0); r.push(1); var root = 1; for (i = 0; i < d; i++) { for (var j = 0; j < r.length; j++) { r[j] = mul(r[j], root); if (j + 1 < r.length) r[j] ^= r[j + 1]; } root = mul(root, 2); } return r; }
  function rsRem(data, div) { var r = div.map(function () { return 0; }); data.forEach(function (b) { var f = b ^ r.shift(); r.push(0); div.forEach(function (c, i) { r[i] ^= mul(c, f); }); }); return r; }
  function utf8(s) { return Array.prototype.slice.call(new TextEncoder().encode(s)); }
  function matrix(text) {
    var bytes = utf8(text), v, dataCw;
    for (v = 1; v <= 6; v++) { dataCw = Math.floor(rawModules(v) / 8) - ECC[v] * BLK[v]; if (bytes.length + 2 <= dataCw) break; }
    if (v > 6) throw new Error('QR text too long');
    var bits = []; function put(val, n) { for (var i = n - 1; i >= 0; i--) bits.push((val >>> i) & 1); }
    put(4, 4); put(bytes.length, 8); bytes.forEach(function (b) { put(b, 8); });
    put(0, Math.min(4, dataCw * 8 - bits.length)); while (bits.length % 8) bits.push(0);
    for (var pad = 0xEC; bits.length < dataCw * 8; pad ^= 0xEC ^ 0x11) put(pad, 8);
    var cw = []; for (var i = 0; i < bits.length; i += 8) { var b = 0; for (var k = 0; k < 8; k++) b = (b << 1) | bits[i + k]; cw.push(b); }
    var nb = BLK[v], ecl = ECC[v], raw = Math.floor(rawModules(v) / 8), nShort = nb - raw % nb, sLen = Math.floor(raw / nb), blocks = [], off = 0, div = rsDiv(ecl);
    for (i = 0; i < nb; i++) { var len = sLen - ecl + (i < nShort ? 0 : 1), d = cw.slice(off, off + len); off += len; var e = rsRem(d, div); if (i < nShort) d.push(0); blocks.push(d.concat(e)); }
    var out = []; for (i = 0; i < blocks[0].length; i++) blocks.forEach(function (bl, j) { if (i !== sLen - ecl || j >= nShort) out.push(bl[i]); });
    var size = v * 4 + 17, m = [], fn = []; for (i = 0; i < size; i++) { m.push(new Array(size).fill(false)); fn.push(new Array(size).fill(false)); }
    function set(x, y, d) { m[y][x] = d; fn[y][x] = true; }
    for (i = 0; i < size; i++) { set(6, i, i % 2 === 0); set(i, 6, i % 2 === 0); }
    [[3, 3], [size - 4, 3], [3, size - 4]].forEach(function (c) { for (var dy = -4; dy <= 4; dy++) for (var dx = -4; dx <= 4; dx++) { var x = c[0] + dx, y = c[1] + dy, dist = Math.max(Math.abs(dx), Math.abs(dy)); if (x >= 0 && y >= 0 && x < size && y < size) set(x, y, dist !== 2 && dist !== 4); } });
    var ap = ALIGN[v]; ap.forEach(function (ax, a) { ap.forEach(function (ay, b) { if ((a === 0 && b === 0) || (a === 0 && b === ap.length - 1) || (a === ap.length - 1 && b === 0)) return; for (var dy = -2; dy <= 2; dy++) for (var dx = -2; dx <= 2; dx++) set(ax + dx, ay + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1); }); });
    function fmt(mask) { var data = (0 << 3) | mask, rem = data; for (var i = 0; i < 10; i++) rem = (rem << 1) ^ ((rem >>> 9) * 0x537); var bits = ((data << 10) | rem) ^ 0x5412; function bit(n) { return ((bits >>> n) & 1) !== 0; }
      for (i = 0; i <= 5; i++) set(8, i, bit(i)); set(8, 7, bit(6)); set(8, 8, bit(7)); set(7, 8, bit(8)); for (i = 9; i < 15; i++) set(14 - i, 8, bit(i));
      for (i = 0; i < 8; i++) set(size - 1 - i, 8, bit(i)); for (i = 8; i < 15; i++) set(8, size - 15 + i, bit(i)); set(8, size - 8, true); }
    fmt(0);
    var n = 0; for (var right = size - 1; right >= 1; right -= 2) { if (right === 6) right = 5; for (var vert = 0; vert < size; vert++) for (var j = 0; j < 2; j++) { var x = right - j, up = ((right + 1) & 2) === 0, y = up ? size - 1 - vert : vert; if (!fn[y][x] && n < out.length * 8) { m[y][x] = ((out[n >>> 3] >>> (7 - (n & 7))) & 1) !== 0; n++; } } }
    for (y = 0; y < size; y++) for (x = 0; x < size; x++) if (!fn[y][x] && (x + y) % 2 === 0) m[y][x] = !m[y][x];
    fmt(0);
    return m;
  }
  function svg(text, px) {
    var m = matrix(text), n = m.length, q = 3, d = '';
    for (var y = 0; y < n; y++) for (var x = 0; x < n; x++) if (m[y][x]) d += 'M' + (x + q) + ' ' + (y + q) + 'h1v1h-1z';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + (n + q * 2) + ' ' + (n + q * 2) + '" width="' + (px || 160) + '" height="' + (px || 160) + '" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/><path d="' + d + '" fill="#000"/></svg>';
  }
  window.KQR = { matrix: matrix, svg: svg };
})();
