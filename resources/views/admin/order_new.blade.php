<?php /** @var ?array $customer @var array $methods @var float $taxRate @var string $sym @var int $shipDefault */
$old = fn ($k, $d = '') => old($k, $d);
$c = $customer ?? [];
?>
<form method="post" action="<?= e(url('admin/orders/create')) ?>" id="ob-form" class="ob" autocomplete="off"
  data-lookup="<?= e(url('admin/orders/lookup')) ?>" data-totals="<?= e(url('admin/orders/totals')) ?>" data-sym="<?= e($sym) ?>" data-tax="<?= e($taxRate) ?>" data-ship="<?= e(from_cents($shipDefault)) ?>"
  data-customer="<?= e(json_encode($c ?: null, JSON_UNESCAPED_UNICODE)) ?>"><?= csrf_field() ?>
  <input type="hidden" name="user_id" value="<?= e($c['id'] ?? $old('user_id')) ?>">
  <div class="stack">
    <section class="apanel"><h2>1 · Customer</h2>
      <div id="cust-chip" class="custchip" hidden><b id="cc-name"></b><span class="muted" id="cc-email"></span><span class="badge" id="cc-type"></span><button type="button" id="cc-clear" aria-label="Change customer">Change</button></div>
      <div class="sugg-wrap" id="cust-search"><label class="fld"><span>Find a customer</span><input type="search" id="cust-q" placeholder="Search by name, email or phone…" autocomplete="off"></label><div class="sugg" id="cust-sugg" hidden></div></div>
      <div class="g3"><?= field_text('name', 'Name', $c['name'] ?? $old('name'), ['required' => true]) ?><?= field_text('email', 'Email', $c['email'] ?? $old('email'), ['type' => 'email', 'required' => true]) ?><?= field_text('phone', 'Phone', $c['phone'] ?? $old('phone')) ?></div>
      <label class="chk" id="mk-acct"><input type="hidden" name="create_account" value="0"><input type="checkbox" name="create_account" value="1"><i></i><span>Create a customer account for this person <small class="hint">A password is generated; it's emailed when "Email the customer" is ticked.</small></span></label>
    </section>

    <section class="apanel"><h2>2 · Items</h2>
      <div class="sugg-wrap"><label class="fld" style="margin:0"><span>Add products</span><input type="search" id="prod-q" placeholder="Type a product name or SKU — press Enter to add the top result" autocomplete="off"></label><div class="sugg" id="prod-sugg" hidden></div></div>
      <div class="lines" id="lines"><p class="empty" id="no-lines">No items yet. Search above, or <button type="button" class="btn sm ghost" id="add-custom">add a custom item</button></p></div>
      <div class="row-actions"><button type="button" class="btn sm ghost" id="add-custom2">＋ Custom item</button></div>
    </section>

    <section class="apanel"><h2>3 · Shipping &amp; delivery</h2>
      <div class="g2"><?= field_text('ship_name', 'Recipient', $old('ship_name')) ?><?= field_text('ship_method', 'Delivery method', $old('ship_method'), ['placeholder' => 'Standard, Express, Pickup…']) ?></div>
      <?= field_text('ship_address', 'Address', $old('ship_address')) ?><?= field_text('ship_address2', 'Apartment, suite (optional)', $old('ship_address2')) ?>
      <div class="g3"><?= field_text('ship_city', 'City', $old('ship_city')) ?><?= field_text('ship_state', 'State / region', $old('ship_state')) ?><?= field_text('ship_zip', 'Postal code', $old('ship_zip')) ?></div>
      <?= field_text('ship_country', 'Country', $old('ship_country')) ?>
      <p class="muted small" id="ship-note">Only needed when the order contains physical items. Autofilled from the customer's last order.</p>
      <?= field_area('notes', 'Order note (shown on the order, optional)', $old('notes'), ['rows' => 2]) ?>
    </section>
  </div>

  <aside class="stack ob-sum">
    <section class="apanel"><h2>Summary</h2>
      <div class="g2"><label class="fld"><span>Discount type</span><select name="discount_type"><option value="fixed">Amount (<?= e($sym) ?>)</option><option value="percent">Percent %</option></select></label><?= field_text('discount_value', 'Discount value', $old('discount_value'), ['type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => '0']) ?></div>
      <?= field_text('coupon', 'Coupon code (optional)', $old('coupon'), ['placeholder' => 'WELCOME10']) ?>
      <?= field_text('shipping', 'Shipping cost', $old('shipping'), ['type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => '0.00']) ?>
      <div class="sumrows"><div><span>Subtotal</span><b id="t-sub">—</b></div><div id="t-dr" hidden><span>Discount</span><b id="t-disc"></b></div><div><span>Shipping</span><b id="t-ship">—</b></div><div id="t-tr" hidden><span>Tax</span><b id="t-tax"></b></div><div class="g"><span>Total</span><b id="t-total">—</b></div></div>
      <p class="warn small" id="t-err" hidden></p>
    </section>
    <section class="apanel"><h2>Payment</h2>
      <div class="paycards"><label class="pcard2 on"><input type="radio" name="pay_status" value="unpaid" checked><b>Not paid yet</b><small class="muted">Customer pays later / via link</small></label><label class="pcard2"><input type="radio" name="pay_status" value="paid"><b>Paid</b><small class="muted">Payment already received</small></label></div>
      <?= field_select('pay_method', 'Payment method', $methods, 'bank') ?>
      <div id="pay-ref"><?= field_text('pay_ref', 'Payment reference (optional)', '', ['placeholder' => 'Receipt #, bank ref…']) ?></div>
      <?= field_select('status', 'Order status', ['' => 'Automatic', 'pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped', 'completed' => 'Completed'], '') ?>
      <?= field_check('notify', 'Email the customer (receipt, or invoice with a pay link)', true) ?>
      <button class="btn block" id="ob-submit">Create order</button>
      <p class="muted small" style="margin-top:10px">Digital keys are delivered automatically once the order is marked paid.</p>
    </section>
  </aside>
</form>
<script src="<?= e(asset_v('js/admin-orders26.js')) ?>" defer></script>
