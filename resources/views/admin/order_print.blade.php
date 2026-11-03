<?php /** @var \App\Models\Order $o @var array $items */ ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Packing slip <?= e($o['number']) ?></title>
<style>body{font:14px/1.5 Arial,Helvetica,sans-serif;color:#111;margin:0;padding:32px;max-width:820px}h1{margin:0;font-size:24px}table{width:100%;border-collapse:collapse;margin-top:18px}th,td{padding:9px 8px;border-bottom:1px solid #ddd;text-align:left;vertical-align:top}th{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#666}.r{text-align:right}.cols{display:flex;gap:40px;margin-top:22px}.cols>div{flex:1}small{color:#666}.bar{margin-bottom:18px}button{padding:8px 16px;border-radius:99px;border:1px solid #111;background:#111;color:#fff;cursor:pointer}@media print{.bar{display:none}}</style></head><body>
<div class="bar"><button onclick="window.print()">Print</button></div>
<h1><?= e(setting('store_name')) ?> — Packing slip</h1>
<p>Order <b><?= e($o['number']) ?></b> · <?= e(fmt_date($o['created_at'])) ?><?= $o['ship_method'] ? ' · ' . e($o['ship_method']) : '' ?></p>
<div class="cols"><div><b>Ship to</b><br><?= e($o['ship_name'] ?: $o['name']) ?><br><?= e($o['ship_address']) ?><?= $o['ship_address2'] ? '<br>' . e($o['ship_address2']) : '' ?><br><?= e(trim($o['ship_city'] . ', ' . $o['ship_state'] . ' ' . $o['ship_zip'], ', ')) ?><br><?= e($o['ship_country']) ?></div>
<div><b>Customer</b><br><?= e($o['name']) ?><br><?= e($o['email']) ?><?= $o['phone'] ? '<br>' . e($o['phone']) : '' ?><?php if ($o['carrier'] || $o['tracking']): ?><br><br><b>Carrier</b> <?= e($o['carrier']) ?> <?= e($o['tracking']) ?><?php endif; ?></div></div>
<table><thead><tr><th>Item</th><th>SKU / options</th><th class="r">Qty</th></tr></thead><tbody>
<?php foreach ($items as $it): ?><tr><td><b><?= e($it['name']) ?></b></td><td><small><?= e($it['options']) ?></small></td><td class="r"><?= (int) $it['qty'] ?></td></tr><?php endforeach; ?></tbody></table>
<?php if ($o['notes']): ?><p><b>Customer note:</b> <?= e($o['notes']) ?></p><?php endif; ?>
</body></html>
