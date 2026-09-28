<div class="center-page">
  <div class="card card-pad login-card">
    <span class="brand-mark"><?= icon('sparkle', 'icon-lg') ?></span>
    <h1>Welkom terug</h1>
    <p class="sub">Log in op je homebase.</p>

    <?php if ($error ?? null): ?>
      <div class="alert alert-error"><?= icon('x') ?><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('login')) ?>">
      <?= csrf_field() ?>
      <div class="field">
        <label for="username">Gebruikersnaam</label>
        <input type="text" id="username" name="username" value="<?= old('username') ?>" autocomplete="username" required autofocus>
      </div>
      <div class="field">
        <label for="password">Wachtwoord</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Inloggen →</button>
    </form>
  </div>
</div>
