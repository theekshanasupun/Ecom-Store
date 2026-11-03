<section class="block narrow center-form">
  <h1 class="h1">Track an order</h1>
  <form method="post" action="<?= e(url('track')) ?>" class="card authcard"><?= csrf_field() ?>
    <label class="fld"><span>Order number</span><input name="number" placeholder="K240101-ABCDE" required></label>
    <label class="fld"><span>Email used at checkout</span><input type="email" name="email" required></label>
    <button class="btn btn-block">Find my order →</button>
  </form>
</section>
