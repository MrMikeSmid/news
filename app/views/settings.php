<div class="page-header">
  <div>
    <h1>Instellingen</h1>
    <p>Beheer je publish hub.</p>
  </div>
</div>

<div class="section-title" style="margin-top:0">Algemeen</div>
<div class="card card-pad" style="margin-bottom:20px">
  <form method="post" action="<?= e(url('settings')) ?>">
    <?= csrf_field() ?>
    <div class="field">
      <label for="site_name">Naam van je hub</label>
      <input type="text" id="site_name" name="site_name" value="<?= e($siteName) ?>" required>
    </div>
    <button class="btn btn-primary" type="submit"><?= icon('check') ?> Opslaan</button>
  </form>
</div>

<div class="section-title">Wachtwoord wijzigen</div>
<div class="card card-pad">
  <form method="post" action="<?= e(url('settings/password')) ?>">
    <?= csrf_field() ?>
    <div class="field">
      <label for="current_password">Huidig wachtwoord</label>
      <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
    </div>
    <div class="form-row">
      <div class="field">
        <label for="new_password">Nieuw wachtwoord</label>
        <input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>
      </div>
      <div class="field">
        <label for="new_password_confirmation">Herhaal nieuw wachtwoord</label>
        <input type="password" id="new_password_confirmation" name="new_password_confirmation" autocomplete="new-password" minlength="8" required>
      </div>
    </div>
    <button class="btn btn-secondary" type="submit">Wachtwoord wijzigen</button>
  </form>
</div>
