<h1>Welkom bij Dagstart</h1>
<p class="sub">Kies een account voor je instellingen. Hier komen later je mail- en agenda-gegevens te staan, dus gebruik een sterk wachtwoord.</p>
<?php if ($error ?? null): ?><div class="alert alert-error"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
<form method="post" action="<?= e(url('setup')) ?>">
  <?= csrf_field() ?>
  <div class="field"><label for="username">Gebruikersnaam</label><input type="text" id="username" name="username" value="<?= old('username') ?>" autocomplete="username" required autofocus></div>
  <div class="field"><label for="password">Wachtwoord</label><input type="password" id="password" name="password" autocomplete="new-password" minlength="10" required>
    <div class="hint">Minimaal 10 tekens.</div></div>
  <div class="field"><label for="password_confirmation">Herhaal wachtwoord</label><input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required></div>
  <button class="btn btn-primary btn-block" type="submit">Account aanmaken</button>
</form>
