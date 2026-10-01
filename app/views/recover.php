<h1>Wachtwoord herstellen</h1>
<p class="sub">Vul de herstelcode in die je van mij hebt gekregen en kies een nieuw wachtwoord. Je gebruikersnaam blijft hetzelfde.</p>
<?php if ($error ?? null): ?><div class="alert alert-error"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
<form method="post" action="<?= e(url('recover')) ?>">
  <?= csrf_field() ?>
  <div class="field"><label for="code">Herstelcode</label><input type="text" id="code" name="code" autocomplete="off" required autofocus></div>
  <div class="field"><label for="password">Nieuw wachtwoord</label><input type="password" id="password" name="password" autocomplete="new-password" minlength="10" required>
    <div class="hint">Minimaal 10 tekens.</div></div>
  <div class="field"><label for="password_confirmation">Herhaal wachtwoord</label><input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required></div>
  <button class="btn btn-primary btn-block" type="submit">Wachtwoord instellen</button>
</form>
