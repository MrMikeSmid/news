<div class="page-header">
  <div>
    <h1>Instellingen</h1>
    <p>Beheer je homebase.</p>
  </div>
</div>

<div class="section-title" style="margin-top:0">Algemeen</div>
<div class="card card-pad" style="margin-bottom:20px">
  <form method="post" action="<?= e(url('settings')) ?>">
    <?= csrf_field() ?>
    <div class="field">
      <label for="site_name">Naam van je homebase</label>
      <input type="text" id="site_name" name="site_name" value="<?= e($siteName) ?>" required>
    </div>
    <div class="field">
      <label for="api_endpoint_url">API-endpoint voor blogs</label>
      <input type="url" id="api_endpoint_url" name="api_endpoint_url" placeholder="https://mijnanderesite.nl/api/blogs" value="<?= e($apiEndpoint) ?>">
      <p class="hint">Hier stuurt de knop "Versturen naar site" op een blog een POST-request met JSON naartoe.</p>
    </div>
    <div class="field">
      <label for="api_key">API-sleutel</label>
      <input type="text" id="api_key" name="api_key" placeholder="Wordt meegestuurd als Bearer-token" value="<?= e($apiKey) ?>">
    </div>
    <button class="btn btn-primary" type="submit"><?= icon('check') ?> Opslaan</button>
  </form>
</div>

<div class="section-title">Automatisch verversen</div>
<div class="card card-pad" style="margin-bottom:20px">
  <p style="color:var(--text-muted);font-size:13.5px;margin-top:0">Cron-URL om RSS-bronnen automatisch te verversen:</p>
  <code style="display:block;background:var(--bg-inset);padding:10px 12px;border-radius:8px;font-size:12.5px;word-break:break-all"><?= e(url('cron/fetch?token=' . $cronToken)) ?></code>
  <form method="post" action="<?= e(url('settings/cron-token')) ?>" style="margin-top:12px">
    <?= csrf_field() ?>
    <button class="btn btn-secondary btn-sm" type="submit"><?= icon('refresh') ?> Nieuwe token genereren</button>
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
