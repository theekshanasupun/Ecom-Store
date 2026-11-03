<?php /** @var string $cur sub-nav current key */
$groups = [
  'campaigns' => ['campaigns' => 'Campaigns', 'rules' => 'Earning rules'],
  'challenges' => ['challenges' => 'Challenges', 'badges' => 'Badges', 'competitions' => 'Competitions'],
];
foreach ($groups as $g) if (isset($g[$cur])) { echo '<div class="tabs">'; foreach ($g as $k => $l) echo '<a href="' . e(url('admin/loyalty/' . $k)) . '" class="' . ($k === $cur ? 'on' : '') . '">' . e($l) . '</a>'; echo '</div>'; }
