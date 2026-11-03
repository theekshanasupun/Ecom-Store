<?php /** @var \App\Models\Popup $p */ $id = (int) $p->id; $v = fn ($k) => $p->$k; ?>
<form method="post" action="<?= e(url('admin/popups/save')) ?>" enctype="multipart/form-data" data-popup-form data-preview="<?= e(url('admin/popups/preview')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
<div class="split2">
<div>
  <section class="apanel"><h2>Content</h2>
    <div class="g2"><?= field_text('name', 'Internal name', $v('name'), ['required' => true, 'placeholder' => 'Welcome 10% off']) ?><?= field_select('status', 'Status', ['active' => 'Active (live)', 'draft' => 'Draft'], $v('status')) ?></div>
    <?= field_text('title', 'Headline', $v('title')) ?>
    <?= field_area('body', 'Text (HTML allowed)', $v('body'), ['rows' => 4]) ?>
    <label class="fld"><span>Image (optional)</span><input type="file" name="image" accept="image/*"></label>
    <?php if ($p->image): ?><div class="imgprev"><img src="<?= e(upload_url($p->image)) ?>" alt=""><label class="chk"><input type="checkbox" name="remove_image" value="1"><i></i><span>Remove image</span></label></div><?php endif; ?>
    <?= field_check('capture_email', 'Collect an email address (adds to Subscribers)', (bool) $p->capture_email) ?>
    <div class="g2"><?= field_text('button_text', 'Button text', $v('button_text')) ?><?= field_text('button_url', 'Button link (when not collecting email)', $v('button_url'), ['placeholder' => '/shop/toys']) ?></div>
    <div class="g2"><?= field_text('coupon_code', 'Show coupon code', $v('coupon_code'), ['hint' => 'Revealed after signup (or shown directly).']) ?><?= field_text('success_text', 'Thank-you message', $v('success_text'), ['placeholder' => 'Thanks — your code:']) ?></div>
  </section>

  <section class="apanel"><h2>Display rules</h2>
    <div class="g2"><?= field_select('trigger', 'Trigger', ['delay' => 'After a delay', 'scroll' => 'After scrolling', 'exit' => 'Exit intent (leaving the page)', 'load' => 'Immediately', 'click' => 'When a #popup-link is clicked'], $v('trigger')) ?>
      <div data-when-trigger="delay scroll"><?= field_text('trigger_value', 'Seconds / scroll %', $v('trigger_value'), ['type' => 'number', 'min' => '0']) ?></div></div>
    <p class="muted small" data-when-trigger="click">Add a link anywhere: <code>&lt;a href="#popup-<?= $id ?: 'ID' ?>"&gt;Open&lt;/a&gt;</code></p>
    <div class="g2"><?= field_select('frequency', 'How often', ['once' => 'Once per visitor', 'session' => 'Once per session', 'days' => 'Every N days', 'always' => 'Every page view'], $v('frequency')) ?>
      <div data-when-freq="days"><?= field_text('frequency_days', 'Days between', $v('frequency_days'), ['type' => 'number', 'min' => '1']) ?></div></div>
    <div class="g3"><?= field_select('devices', 'Devices', ['all' => 'All', 'desktop' => 'Desktop only', 'mobile' => 'Mobile only'], $v('devices')) ?>
      <?= field_select('audience', 'Audience', ['all' => 'Everyone', 'guests' => 'Signed-out visitors', 'customers' => 'Signed-in customers'], $v('audience')) ?>
      <?= field_select('pages', 'Pages', ['all' => 'All pages', 'home' => 'Home page', 'products' => 'Product & department pages', 'cart' => 'Cart', 'custom' => 'Custom paths…'], $v('pages')) ?></div>
    <div data-when-pages="custom"><?= field_area('page_rules', 'Paths (one per line, * wildcard)', $v('page_rules'), ['rows' => 3, 'placeholder' => "/shop/*\n/p/about"]) ?></div>
    <div class="g2"><?= field_text('starts_at', 'Start date', $p->starts_at ? $p->starts_at->format('Y-m-d') : '', ['type' => 'date']) ?><?= field_text('ends_at', 'End date', $p->ends_at ? $p->ends_at->format('Y-m-d') : '', ['type' => 'date']) ?></div>
  </section>

  <section class="apanel"><h2>Design</h2>
    <div class="g2"><?= field_select('layout', 'Layout', ['modal' => 'Centered modal', 'slide' => 'Slide-in corner card', 'bar' => 'Banner bar', 'fullscreen' => 'Full screen'], $v('layout')) ?>
      <?= field_select('position', 'Position', ['center' => 'Center', 'bottom-right' => 'Bottom right', 'bottom-left' => 'Bottom left', 'top' => 'Top', 'bottom' => 'Bottom'], $v('position')) ?></div>
    <div class="g3"><label class="fld"><span>Background</span><input type="color" name="bg" value="<?= e($v('bg')) ?>"></label><label class="fld"><span>Text</span><input type="color" name="fg" value="<?= e($v('fg')) ?>"></label>
      <label class="fld"><span>Button</span><input type="color" name="btn_bg" value="<?= e($v('btn_bg')) ?>"></label></div>
    <div class="g3"><label class="fld"><span>Button text colour</span><input type="color" name="btn_fg" value="<?= e($v('btn_fg')) ?>"></label><?= field_text('width', 'Width (px)', $v('width'), ['type' => 'number', 'min' => '280', 'max' => '900']) ?><?= field_text('radius', 'Corner radius', $v('radius'), ['type' => 'number', 'min' => '0', 'max' => '40']) ?></div>
    <?= field_text('overlay', 'Overlay darkness (0–90)', $v('overlay'), ['type' => 'number', 'min' => '0', 'max' => '90']) ?>
  </section>
  <div class="stickybar"><span class="grow muted small">Preview updates live on the right.</span><a class="btn ghost" href="<?= e(url('admin/popups')) ?>">Back</a><button class="btn">Save popup</button></div>
</div>
<aside class="sticky-r"><section class="apanel"><h2>Live preview</h2><iframe id="popup-preview" class="pv" title="Popup preview"></iframe><p class="muted small" style="margin-top:8px">Preview updates as you type. Triggers and frequency apply on the live site only.</p></section></aside>
</div></form>
