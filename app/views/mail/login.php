<div class="auth-page">
  <div class="card auth-card">
    <div class="brand"><span class="brand-mark" aria-hidden="true">B</span><span>Homebase</span></div>
    <h1><?= $setup?'Welkom bij Homebase':'Welkom terug' ?></h1>
    <p class="sub"><?= $setup?'Maak je eigen beheeraccount aan.':'Log in om je mailaccounts te beheren.' ?></p>
    <?php if($error):?><div class="alert alert-error" role="alert"><?=icon('alert')?><?=e($error)?></div><?php endif;?>
    <?php if($setup):?><p class="setup-hint">Je installatiecode staat op je hosting in <code>storage/setup-token</code>. Open dit bestand via je hostingbeheer of FTP. Zo kan alleen jij deze app inrichten.</p><?php endif;?>
    <form method="post" data-loading>
      <?=csrf_field()?>
      <?php if($setup):?><div class="field"><label for="setup_token">Installatiecode</label><input id="setup_token" name="setup_token" type="password" required autocomplete="off"></div><?php endif;?>
      <div class="field"><label for="username">Gebruikersnaam</label><input id="username" name="username" autocomplete="username" required maxlength="100"></div>
      <div class="field"><label for="password">Wachtwoord</label><input id="password" name="password" type="password" autocomplete="<?=$setup?'new-password':'current-password'?>" <?=$setup?'minlength="12"':''?> required></div>
      <?php if($setup):?><div class="field"><label for="confirmation">Wachtwoord herhalen</label><input id="confirmation" name="confirmation" type="password" autocomplete="new-password" required minlength="12"></div><?php endif;?>
      <button class="btn btn-primary btn-block" type="submit"><?=$setup?'Beheeraccount maken':'Inloggen'?></button>
    </form>
  </div>
</div>
