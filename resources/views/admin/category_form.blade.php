<?php /** @var ?array $c */ $v = fn($k, $d = '') => old($k, $c[$k] ?? $d); ?>
<form method="post" action="<?= e(url('admin/categories/save')) ?>" enctype="multipart/form-data" class="cols">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($c['id'] ?? 0) ?>">
  <section class="apanel wide">
    <h2>Department</h2>
    <div class="g2"><?= field_text('name', 'Name', $v('name'), ['required' => true]) ?><?= field_text('slug', 'URL slug', $v('slug'), ['placeholder' => 'auto']) ?></div>
    <div class="g2"><?= field_text('headline', 'Giant headline', $v('headline'), ['maxlength' => 40, 'hint' => 'Shown huge on the department page, e.g. TOYS, WEAR, KEYS']) ?><?= field_text('eyebrow', 'Eyebrow line', $v('eyebrow'), ['placeholder' => 'DEPARTMENT 01 · SQUISHY, STACKABLE, BEEPY']) ?></div>
    <?= field_area('tagline', 'Intro text', $v('tagline'), ['rows' => 3]) ?>
    <?= field_area('description', 'Longer description (SEO)', $v('description'), ['rows' => 3]) ?>
  </section>
  <section class="apanel">
    <h2>Style</h2>
    <label class="fld"><span>Colour</span><input type="color" name="color" value="<?= e($v('color', '#ff5aa5')) ?>"></label>
    <?= field_text('sort_order', 'Sort order', $v('sort_order', 0), ['type' => 'number']) ?>
    <?= field_select('status', 'Status', ['active' => 'Active', 'hidden' => 'Hidden'], $v('status', 'active')) ?>
    <div class="actions"><button class="btn block">Save department</button></div>
  </section>
  <div class="wide"><?= partial('admin/_seo_fields', ['o' => $c]) ?></div>
</form>
<?php if ($c): ?><form method="post" action="<?= e(url('admin/categories/' . $c['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this department?')" style="margin-top:20px"><?= csrf_field() ?><button class="btn sm ghost danger">Delete department</button></form><?php endif; ?>
