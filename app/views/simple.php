<div class="center-page" style="min-height:60vh;background:none;padding:0">
  <div class="card card-pad login-card">
    <span class="brand-mark"><?= icon('sparkle', 'icon-lg') ?></span>
    <h1><?= e($title ?? 'Melding') ?></h1>
    <p class="sub"><?= e($message ?? '') ?></p>
    <a class="btn btn-primary btn-block" href="<?= e(url('/')) ?>">Terug naar start</a>
  </div>
</div>
