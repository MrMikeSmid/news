<h1>Inloggen</h1>
<p class="sub">Alleen nodig voor je instellingen. Verslagen lezen kan met je sleutel-link.</p>
<?php if ($error ?? null): ?><div class="alert alert-error"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
<form method="post" action="<?= e(url('login')) ?>">
  <?= csrf_field() ?>
  <div class="field"><label for="username">Gebruikersnaam</label><input type="text" id="username" name="username" value="<?= old('username') ?>" autocomplete="username" required autofocus></div>
  <div class="field"><label for="password">Wachtwoord</label><input type="password" id="password" name="password" autocomplete="current-password" required></div>
  <button class="btn btn-primary btn-block" type="submit">Inloggen</button>
</form>
