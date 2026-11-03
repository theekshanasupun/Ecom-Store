<?php /** @var mixed $o model or null ; @var string $urlPrefix */
$g = fn ($k) => old($k, $o[$k] ?? ''); ?>
<section class="apanel"><h2>SEO</h2>
  <label class="fld"><span>SEO title</span><input name="seo_title" value="<?= e($g('seo_title')) ?>" data-count="60" placeholder="Defaults to the name"></label>
  <label class="fld"><span>Meta description</span><textarea name="seo_description" rows="3" data-count="160" placeholder="Defaults to the intro text"><?= e($g('seo_description')) ?></textarea></label>
  <?= field_check('seo_noindex', 'Hide from search engines (noindex)', (bool) ($o['seo_noindex'] ?? false)) ?>
</section>
