<?php /** @var array $k @var array $tiers */
$g = fn ($key, $d = '') => setting($key, $d);
$bool = fn ($key, $label, $hint = '') => field_check($key, $label, setting($key) === '1', $hint);
$txt = fn ($key, $label, $o = []) => field_text($key, $label, setting($key), $o);
$num = fn ($key, $label, $o = []) => field_text($key, $label, setting($key), $o + ['type' => 'number', 'min' => '0', 'step' => '1']);
$money = fn ($key, $label, $o = []) => field_text($key, $label, from_cents((int) setting($key)), $o + ['type' => 'number', 'min' => '0', 'step' => '0.01']);
$sel = fn ($key, $label, $opts, $o = []) => field_select($key, $label, $opts, setting($key), $o);
?>
<form method="post" action="<?= e(url('admin/loyalty/settings')) ?>"><?= csrf_field() ?>
<div class="tabs2" data-tabs><?php foreach (['prog' => 'Programme', 'earn' => 'Earning', 'redeem' => 'Redemption', 'tier' => 'Tiers & expiry', 'cash' => 'Cashback', 'ref' => 'Referrals', 'game' => 'Gamification & bonuses', 'sec' => 'Security & API', 'terms' => 'Terms'] as $t => $l): ?><button type="button" data-tab="<?= $t ?>" class="<?= $t === 'prog' ? 'on' : '' ?>"><?= $l ?></button><?php endforeach; ?></div>

<section class="apanel" data-pane="prog"><h2>Programme</h2>
  <?= $bool('loyalty_on', 'Loyalty programme enabled', 'Turning it off hides it from customers and stops new earning. Balances are kept.') ?>
  <div class="g2"><?= $txt('loyalty_name', 'Programme name') ?><?= field_text('loyalty_color', 'Brand colour', setting('loyalty_color', '#c6ff3d'), ['type' => 'color']) ?></div>
  <?= field_area('loyalty_desc', 'Description', setting('loyalty_desc'), ['rows' => 2]) ?>
  <?= $bool('loyalty_exclude_staff', 'Exclude staff / admin accounts') ?>
  <?= $bool('loyalty_guest_earn', 'Guest checkouts earn into a matching member account (same email)') ?>
  <?= $bool('loyalty_notify_email', 'Send loyalty notification emails') ?>
  <p class="muted small">Customer groups: eligibility is limited to customer accounts; staff exclusion above. Currency is the store currency (Settings).</p></section>

<section class="apanel" data-pane="earn" hidden><h2>Points earning</h2>
  <div class="g3"><?= $txt('loyalty_earn_spend', 'For every spend of', ['type' => 'number', 'step' => '0.01', 'min' => '0.01', 'hint' => 'Currency amount, e.g. 100']) ?>
    <?= $txt('loyalty_earn_points', 'customers earn (points)', ['type' => 'number', 'step' => '0.01', 'min' => '0', 'hint' => 'e.g. 1 → spend 100, earn 1 point']) ?>
    <?= $sel('loyalty_round', 'Rounding', ['floor' => 'Round down', 'round' => 'Nearest', 'ceil' => 'Round up']) ?></div>
  <p class="muted small">Product/category rates (Campaigns &amp; rules) are expressed per the same spend block.</p>
  <div class="g2"><?= $sel('loyalty_earn_status', 'Points become available when the order is', ['paid' => 'Paid', 'processing' => 'Processing', 'shipped' => 'Shipped', 'completed' => 'Completed']) ?>
    <?= $num('loyalty_hold_days', 'Extra hold after that (days, pending approval period)') ?></div>
  <div class="g2"><?= $txt('loyalty_excl_products', 'Excluded product IDs (comma)') ?><?= $txt('loyalty_excl_categories', 'Excluded department IDs (comma)') ?></div>
  <p class="muted small">Shipping, tax, gift-card purchases and the part paid with points/cashback never earn. Cancelled and refunded orders reverse their points automatically (partial refunds proportionally).</p></section>

<section class="apanel" data-pane="redeem" hidden><h2>Points redemption</h2>
  <div class="g2"><?= $txt('loyalty_redeem_rate', 'Points that equal 1.00 of currency', ['type' => 'number', 'step' => '0.01', 'min' => '0.01', 'hint' => 'Points → LKR conversion: 100 means 100 points = 1.00. Use 1 for 1 point = 1 LKR.']) ?>
    <?= $num('loyalty_min_redeem', 'Minimum points to redeem') ?></div>
  <div class="g2"><?= $num('loyalty_max_redeem', 'Maximum points per order (0 = no limit)') ?><?= $num('loyalty_max_redeem_pct', 'Maximum share of an order payable with points (%)') ?></div>
  <?= $bool('loyalty_stack_coupons', 'Points can be used together with discount codes') ?>
  <div class="g2"><?= $num('loyalty_redeem_approval', 'Reward redemptions at or above this many points need manual approval (0 = off)') ?><?= $num('loyalty_staff_adjust_limit', 'Staff manual-adjustment limit (points; admins unlimited)') ?></div></section>

<section class="apanel" data-pane="tier" hidden><h2>Tiers &amp; expiry</h2>
  <div class="g2"><?= $num('loyalty_tier_period', 'Qualification period (months)') ?><?= $num('loyalty_expiry_months', 'Points expire after (months, 0 = never)') ?></div>
  <?= $bool('loyalty_tier_auto_up', 'Automatic tier upgrades') ?><?= $bool('loyalty_tier_auto_down', 'Automatic tier downgrades (after renewal period + grace)') ?>
  <?= $num('loyalty_expiry_remind_days', 'Send expiry reminder this many days before (0 = off)') ?></section>

<section class="apanel" data-pane="cash" hidden><h2>Cashback wallet &amp; store credit</h2>
  <?= $bool('cashback_on', 'Cashback enabled') ?>
  <div class="g2"><?= $txt('cashback_pct', 'Base cashback % on purchases', ['type' => 'number', 'step' => '0.01', 'min' => '0']) ?><?= $num('cashback_expiry_months', 'Cashback expires after (months, 0 = never)') ?></div>
  <div class="g2"><?= $money('cashback_min_redeem', 'Minimum cashback to redeem') ?><?= $num('cashback_max_pct', 'Maximum share of an order payable with cashback (%)') ?></div>
  <p class="muted small">Cashback is released on the same order-status rule as points. Store credit is a separate wallet managed under Promotions → Store credit (and per member here).</p></section>

<section class="apanel" data-pane="ref" hidden><h2>Referral programme</h2>
  <?= $bool('ref_on', 'Referral programme enabled') ?><?= $bool('ref_approve', 'Admin must approve referral rewards') ?>
  <div class="g2"><?= $num('ref_referrer_points', 'Referrer reward (points)') ?><?= $money('ref_referrer_cashback', 'Referrer reward (cashback)') ?></div>
  <div class="g2"><?= $num('ref_referee_points', 'Referee welcome points') ?><?= $num('ref_referee_pct', 'Referee welcome voucher (% off, 0 = none)') ?></div>
  <div class="g2"><?= $money('ref_min_order', 'Minimum qualifying purchase') ?><?= $num('ref_max_per_user', 'Max referrals per customer (0 = unlimited)') ?></div>
  <div class="g2"><?= field_text('ref_start', 'Campaign start', setting('ref_start'), ['type' => 'date']) ?><?= field_text('ref_end', 'Campaign end', setting('ref_end'), ['type' => 'date']) ?></div>
  <p class="muted small">Ambassadors (set per member) earn a cashback % on every order of their referrals. Duplicate/same-IP/alias-email referrals are blocked and flagged automatically.</p></section>

<section class="apanel" data-pane="game" hidden><h2>Bonuses &amp; gamification</h2>
  <div class="g3"><?= $num('pts_welcome', 'Registration points') ?><?= $num('pts_first_purchase', 'First purchase bonus') ?><?= $num('pts_birthday', 'Birthday points (+ tier bonus)') ?></div>
  <div class="g3"><?= $num('pts_anniversary', 'Anniversary points') ?><?= $num('pts_comeback', 'Win-back points') ?><?= $num('comeback_days', 'Inactive after (days)') ?></div>
  <div class="g3"><?= $num('pts_review', 'Verified review') ?><?= $num('pts_photo_review', 'Photo review') ?><?= $num('pts_video_review', 'Video review') ?></div>
  <div class="g2"><?= $num('pts_qa', 'Answering a product question') ?><?= $num('pts_social', 'Approved social activity') ?></div>
  <h3>Daily check-in</h3><?= $bool('checkin_on', 'Daily check-in enabled') ?>
  <div class="g3"><?= $num('checkin_points', 'Points per check-in') ?><?= $num('checkin_streak_every', 'Streak bonus every N days') ?><?= $num('checkin_streak_bonus', 'Streak bonus points') ?></div>
  <h3>Spin the wheel</h3><?= $bool('spin_on', 'Enabled') ?><?= $num('spin_per_day', 'Plays per day') ?>
  <?= field_area('spin_prizes', 'Prizes — one per line: label|points or cashback or nothing|amount (cashback in cents)|weight', setting('spin_prizes'), ['rows' => 5]) ?>
  <h3>Scratch &amp; win / mystery rewards</h3><?= $bool('scratch_on', 'Enabled') ?><?= $num('scratch_per_day', 'Plays per day') ?>
  <?= field_area('scratch_prizes', 'Prizes (same format)', setting('scratch_prizes'), ['rows' => 5]) ?>
  <?= $num('loyalty_max_points_day', 'Daily limit of bonus points per customer (0 = off)') ?></section>

<section class="apanel" data-pane="sec" hidden><h2>Security, API &amp; webhooks</h2>
  <?= $txt('loyalty_webhook_url', 'Webhook URL (receives points.earned, tier.changed, reward.redeemed, referral.rewarded…)') ?>
  <div class="g2"><?= field_text('loyalty_webhook_secret', 'Webhook signing secret (HMAC-SHA256 in X-Loyalty-Signature)', setting('loyalty_webhook_secret')) ?><div style="padding-top:22px"><?= field_check('gen_secret', 'Generate a new secret') ?></div></div>
  <div class="g2"><?= field_text('loyalty_api_token', 'Read-only API token — GET /api/loyalty/member?email=…', setting('loyalty_api_token')) ?><div style="padding-top:22px"><?= field_check('gen_token', 'Generate a new token') ?></div></div>
  <p class="muted small">Send the token as <code>Authorization: Bearer …</code>. Empty token = API disabled. All admin actions are written to the audit log (Security &amp; fraud).</p></section>

<section class="apanel" data-pane="terms" hidden><h2>Terms &amp; policies (shown to customers)</h2>
  <?= field_area('loyalty_terms', 'Programme terms and conditions', setting('loyalty_terms'), ['rows' => 5]) ?>
  <?= field_area('loyalty_expiry_policy', 'Points expiry policy', setting('loyalty_expiry_policy'), ['rows' => 3]) ?>
  <?= field_area('loyalty_redeem_policy', 'Redemption policy', setting('loyalty_redeem_policy'), ['rows' => 3]) ?></section>
<div class="dp-foot" style="position:sticky;bottom:0;background:var(--bg);padding:12px 0"><span class="grow"></span><button class="btn">Save settings</button></div>
</form>
