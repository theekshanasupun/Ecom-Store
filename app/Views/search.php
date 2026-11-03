<section class="block narrow-top">
  <h1 class="h1">Search</h1>
  <form class="searchbar" method="get" action="<?= e(url('search')) ?>"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search toys, tees, keys…" autofocus><button class="btn">Search</button></form>
  <?php if ($q !== ''): ?>
    <p class="muted"><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?> for “<?= e($q) ?>”</p>
    <?php if ($results): ?><div class="pgrid"><?php foreach ($results as $p) echo partial('partials/product_card', ['p' => $p]); ?></div>
    <?php else: ?><p>Nothing matched. Try a shorter word, or browse a department from the menu.</p><?php endif; ?>
  <?php endif; ?>
</section>
