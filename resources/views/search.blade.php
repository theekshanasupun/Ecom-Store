<?php /** @var string $q @var array $results @var ?string $dym @var array $popular */ ?>
<section class="block narrow-top">
  <h1 class="h1">Search</h1>
  <form class="searchbar" method="get" action="<?= e(url('search')) ?>" role="search" data-typeahead><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search toys, tees, keys…" autofocus autocomplete="off" aria-label="Search" role="combobox" aria-expanded="false" aria-autocomplete="list"><button class="btn">Search</button></form>
  <?php if ($q !== ''): ?>
    <p class="muted"><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?> for “<?= e($q) ?>”</p>
    <?php if ($results): ?><div class="pgrid"><?php foreach ($results as $p) echo partial('partials/product_card', ['p' => $p]); ?></div>
    <?php else: ?>
      <div class="card nores"><p><b>Nothing matched “<?= e($q) ?>”.</b></p>
      <?php if ($dym): ?><p>Did you mean <a class="dym" href="<?= e(url('search?q=' . urlencode($dym))) ?>"><?= e($dym) ?></a>?</p><?php endif; ?>
      <?php if ($popular): ?><p class="muted small">Popular searches</p><div class="chips2"><?php foreach ($popular as $t): ?><a href="<?= e(url('search?q=' . urlencode($t))) ?>"><?= e($t) ?></a><?php endforeach; ?></div><?php endif; ?></div>
    <?php endif; ?>
  <?php endif; ?>
</section>
