<?php /** @var ?array $props */
$pr = $props ?? [];
$l1 = (string) ($pr['line1'] ?? '') !== '' ? $pr['line1'] : (string) setting('hero_line1');
$l2 = (string) ($pr['line2'] ?? '') !== '' ? $pr['line2'] : (string) setting('hero_line2');
$tx = (string) ($pr['text'] ?? '') !== '' ? $pr['text'] : (string) setting('hero_text');
?>
<section class="hero">
  <h1 class="mega hero-h" data-reveal><span><?= e($l1) ?></span> <em><?= e($l2) ?></em></h1>
  <p class="lede" data-reveal><?= e($tx) ?></p>
</section>
