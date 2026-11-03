<?php
/** @var int $page @var int $pages */
if ($pages <= 1) return;
$qs = $_GET; unset($qs['page']);
$link = fn(int $p) => '?' . http_build_query($qs + ['page' => $p]);
?>
<nav class="pager">
  <?php if ($page > 1): ?><a href="<?= e($link($page - 1)) ?>">← Prev</a><?php endif; ?>
  <span>Page <?= $page ?> of <?= $pages ?></span>
  <?php if ($page < $pages): ?><a href="<?= e($link($page + 1)) ?>">Next →</a><?php endif; ?>
</nav>
