<section class="block narrow center">
  <p class="eyebrow lime">Error <?= (int) $code ?></p>
  <h1 class="mega" style="font-size:clamp(48px,12vw,140px)"><?= e($heading) ?></h1>
  <?php if ($message): ?><p class="lede"><?= e($message) ?></p><?php endif; ?>
  <p><a class="btn" href="<?= e(url('')) ?>">Back to the store</a></p>
</section>
