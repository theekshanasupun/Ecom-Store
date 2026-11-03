<form method="post" action="<?= e(url('admin/profile')) ?>" class="apanel narrow"><?= csrf_field() ?>
<h2>My profile</h2>
<?= field_text('name', 'Name', $u['name'], ['required' => true]) ?>
<?= field_text('email', 'Email', $u['email'], ['type' => 'email', 'required' => true]) ?>
<h2>Change password</h2>
<div class="g2"><?= field_text('current_password', 'Current password', '', ['type' => 'password', 'autocomplete' => 'current-password']) ?><?= field_text('new_password', 'New password (8+)', '', ['type' => 'password', 'autocomplete' => 'new-password']) ?></div>
<div class="actions"><button class="btn">Save profile</button></div></form>
