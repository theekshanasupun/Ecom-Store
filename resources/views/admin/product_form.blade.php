<?php
use App\Models\Product as ProductModel;
/** @var ?array $p @var array $cats @var array $arts @var array $keys @var array $keyStats */
$v = fn(string $k, $d = '') => old($k, $p[$k] ?? $d);
$optText = '';
if ($p) foreach ($p['options_list'] as $o) $optText .= $o['name'] . ': ' . implode(', ', $o['values']) . "\n";
$optText = old('options_text', trim($optText));
$isPool = $p && in_array($p['type'], ['key', 'account'], true);
?>
<form method="post" action="<?= e(url('admin/products/save')) ?>" enctype="multipart/form-data" class="pform">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
  <div class="cols">
    <div class="stack wide">
      <section class="apanel">
        <h2>Basics</h2>
        <?= field_text('name', 'Name', $v('name'), ['required' => true]) ?>
        <div class="g2"><?= field_text('tagline', 'Short tagline', $v('tagline'), ['placeholder' => 'e.g. Soft beeping robot · or · PC · Global key']) ?>
        <?= field_text('slug', 'URL slug', $v('slug'), ['placeholder' => 'auto-generated', 'hint' => 'Leave blank to auto-generate']) ?></div>
        <?= field_area('description', 'Description', $v('description'), ['rows' => 6]) ?>
        <?= field_area('details', 'Bullet points (one per line)', $v('details'), ['rows' => 4, 'placeholder' => "Free shipping over $75\n30-day returns"]) ?>
      </section>

      <section class="apanel">
        <h2>Type &amp; pricing</h2>
        <div class="g2">
          <?= field_select('type', 'Product type', ProductModel::TYPES, $v('type', 'physical'), ['hint' => 'Keys/accounts are delivered automatically from a pool you fill below.']) ?>
          <?= field_select('category_id', 'Department', $cats->pluck('name', 'id')->all(), $v('category_id', $cats->first()?->id ?? 0)) ?>
        </div>
        <div class="g3">
          <?= field_text('price', 'Price', from_cents((int) ($p['price'] ?? 0)) , ['type' => 'number', 'step' => '0.01', 'min' => '0', 'required' => true]) ?>
          <?= field_text('compare_price', 'Compare-at price', $p && $p['compare_price'] ? from_cents((int) $p['compare_price']) : '', ['type' => 'number', 'step' => '0.01', 'min' => '0', 'hint' => 'Shows a strike-through']) ?>
          <?= field_text('sku', 'SKU', $v('sku')) ?>
        </div>
        <div class="g3" data-show-for="physical">
          <?= field_text('stock', 'Stock quantity', $v('stock', 0), ['type' => 'number', 'min' => '0']) ?>
          <div class="fld"><span>&nbsp;</span><?= field_check('unlimited', 'Unlimited stock', (bool) $v('unlimited', 0)) ?></div>
        </div>
        <?= field_area('options_text', 'Options (one per line)', $optText, ['rows' => 3, 'placeholder' => "Colour: Bone, Black, Moss\nSize: S, M, L, XL", 'hint' => 'Format <code>Name: value, value</code>. Customers choose these on the product page. Options named “Colour” show as swatches.']) ?>
        <div class="g2"><?= field_text('badge', 'Badge', $v('badge'), ['placeholder' => 'NEW · LIMITED · -50%', 'maxlength' => 30]) ?>
        <?= field_text('tags', 'Filter tags (comma separated)', $v('tags'), ['placeholder' => 'Ages 1-3, Tees, PC']) ?></div>
        <?= field_text('delivery_note', 'Delivery note', $v('delivery_note'), ['placeholder' => 'Shown on the product page, e.g. “Instant · key on screen + email”']) ?>
      </section>

      <section class="apanel" data-show-for="key account">
        <h2>Digital stock <small class="muted"><?= $isPool ? $keyStats['available'] . ' available · ' . $keyStats['sold'] . ' sold' : '' ?></small></h2>
        <?= field_area('new_keys', $p ? 'Add keys / accounts (one per line)' : 'Initial keys / accounts (one per line)', '', ['rows' => 6, 'placeholder' => "AAAAA-BBBBB-CCCCC\nuser@mail.com:password123", 'hint' => 'Stored encrypted (AES-256). Each line is delivered as one unit. Duplicates in the paste are ignored.']) ?>
      </section>

      <section class="apanel" data-show-for="file">
        <h2>Downloadable file</h2>
        <label class="fld"><span>Upload file</span><input type="file" name="file"></label>
        <?php if ($p && $p['file_path']): ?><p class="muted">Current: <b><?= e(preg_replace('/^[a-f0-9]{20}_/', '', $p['file_path'])) ?></b>. Upload again to replace it. Stored privately, served only to paying customers.</p><?php endif; ?>
      </section>
    </div>

    <div class="stack">
      <section class="apanel">
        <h2>Publish</h2>
        <?= field_select('status', 'Status', ['active' => 'Active (visible)', 'draft' => 'Draft (hidden)'], $v('status', 'active')) ?>
        <?= field_check('featured', 'Feature in its department', (bool) $v('featured', 0), 'Shown in the big colour panel') ?>
        <div class="actions"><button class="btn block">Save product</button></div>
        <?php if ($p): ?><p class="muted small" style="margin:12px 0 0"><a href="<?= e(url('product/' . $p['slug'])) ?>" target="_blank">View on store ↗</a></p><?php endif; ?>
      </section>
      <section class="apanel">
        <h2>Images</h2>
        <?php if ($p && $p['image']): ?><div class="imgprev"><img src="<?= e(upload_url($p['image'])) ?>" alt=""><label class="chk"><input type="checkbox" name="remove_image" value="1"><i></i><span>Remove main image</span></label></div><?php endif; ?>
        <label class="fld"><span>Main image</span><input type="file" name="image" accept="image/*" data-preview></label>
        <label class="fld"><span>Gallery (multiple)</span><input type="file" name="gallery[]" accept="image/*" multiple></label>
        <?php if ($p && $p['gallery_list']): ?><div class="imgrid"><?php foreach ($p['gallery_list'] as $g): ?><label class="gimg"><img src="<?= e(upload_url($g)) ?>" alt=""><input type="checkbox" name="remove_gallery[]" value="<?= e($g) ?>"><span>Remove</span></label><?php endforeach; ?></div><?php endif; ?>
        <hr>
        <p class="muted small">No photo? A vector illustration is used automatically.</p>
        <?= field_select('art', 'Illustration', ['' => 'Automatic'] + $arts, $v('art')) ?>
        <label class="fld"><span>Illustration colour</span><input type="color" name="art_color" value="<?= e($v('art_color') ?: '#7a62ff') ?>"></label>
      </section>
    </div>
  </div>
</form>

<?php if ($p && $isPool): ?>
<section class="apanel" id="keys">
  <div class="ph"><h2>Key inventory</h2><span class="muted"><?= $keyStats['available'] ?> available · <?= $keyStats['sold'] ?> sold</span></div>
  <form method="post" action="<?= e(url('admin/products/' . $p['id'] . '/keys')) ?>" class="inline-add"><?= csrf_field() ?>
    <textarea name="keys" rows="3" placeholder="Paste new keys, one per line"></textarea><button class="btn">Add to pool</button></form>
  <?php if ($keys): ?>
  <form method="post" action="<?= e(url('admin/products/' . $p['id'] . '/keys/delete')) ?>" onsubmit="return confirm('Delete the selected unsold keys?')"><?= csrf_field() ?>
    <div class="keylist"><?php foreach ($keys as $k): ?><label><input type="checkbox" name="key_ids[]" value="<?= $k['id'] ?>"><code><?= e($k['text']) ?></code></label><?php endforeach; ?></div>
    <div class="actions"><button class="btn sm danger">Delete selected</button> <button class="btn sm ghost danger" name="all" value="1" onclick="return confirm('Delete ALL unsold keys for this product?')">Delete all unsold</button></div>
  </form>
  <?php else: ?><p class="muted">No unsold keys. The product shows as sold out until you add some.</p><?php endif; ?>
</section>
<?php endif; ?>

<?php if ($p): ?>
<form method="post" action="<?= e(url('admin/products/' . $p['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this product? Products with orders are hidden instead.')" style="margin-top:20px"><?= csrf_field() ?><button class="btn sm ghost danger">Delete product</button></form>
<?php endif; ?>
