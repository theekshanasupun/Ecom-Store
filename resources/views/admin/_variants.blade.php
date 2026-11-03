<?php
/** @var ?\App\Models\Product $p  Advanced options & variants editor (v2.6). */
$opts = $p ? $p['options_list'] : [];
$vars = [];
if ($p) foreach ($p->variantList() as $v) { $v['price'] = $v['price'] !== null ? from_cents($v['price']) : ''; $v['compare'] = $v['compare'] !== null ? from_cents($v['compare']) : ''; $vars[] = $v; }
$imgs = $p ? array_values(array_filter(array_merge([$p['image']], $p['gallery_list']))) : [];
$data = ['options' => $opts, 'variants' => $vars, 'images' => array_map(fn ($i) => ['path' => $i, 'url' => upload_url($i)], $imgs), 'sym' => (string) setting('currency_symbol', '$')];
?>
<section class="apanel" id="sec-variants" data-variants-editor>
  <div class="ph"><h2>Options &amp; variants</h2><span class="muted small">Colours, sizes… each combination can have its own price, stock and SKU</span></div>
  <script type="application/json" data-vdata><?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <input type="hidden" name="options_json" data-ojson><input type="hidden" name="variants_json" data-vjson>
  <div class="vopts" data-vopts></div>
  <div class="vtoolbar"><button type="button" class="btn sm ghost" data-add-option>＋ Add option (e.g. Colour, Size)</button><span class="muted small" data-vhint></span></div>
  <div class="vtable-wrap" data-vwrap hidden>
    <div class="vbulk"><b>Bulk edit</b>
      <input type="number" step="0.01" min="0" placeholder="Price" data-bulk="price"><input type="number" step="0.01" min="0" placeholder="Compare-at" data-bulk="compare"><input type="number" min="0" placeholder="Stock" data-bulk="stock">
      <button type="button" class="btn sm ghost" data-bulk-apply>Apply to all</button></div>
    <table class="tbl vtable"><thead><tr><th>On</th><th>Variant</th><th>Price</th><th>Compare-at</th><th>Stock</th><th>SKU</th><th>Image</th></tr></thead><tbody data-vrows></tbody></table>
    <p class="muted small" style="margin:8px 0 0">Leave <b>Price</b> empty to use the product price. Leave <b>Stock</b> empty to share the product-level stock. Untick <b>On</b> to hide a combination.</p>
  </div>
</section>
