<?php /** @var array $actions  value => label ; @var array $needs  value => 'number'|'category'|'text' ; @var array $cats */ ?>
<div class="bulkbar" data-bulkbar hidden>
  <b><span data-selcount>0</span> selected</b>
  <select name="action" data-bulk-action required>
    <option value="">Bulk action…</option>
    <?php foreach ($actions as $k => $l): ?><option value="<?= e($k) ?>"<?= isset($needs[$k]) ? ' data-needs="' . e($needs[$k]) . '"' : '' ?><?= $k === 'delete' ? ' data-danger="1"' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
  </select>
  <input type="number" step="any" name="value" placeholder="Value" data-bulk-value="number" disabled hidden>
  <?php if (!empty($cats)): ?><select name="value" data-bulk-value="category" disabled hidden><?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select><?php endif; ?>
  <button class="btn sm">Apply</button>
  <button type="button" class="btn sm ghost" data-bulk-clear>Clear</button>
</div>
