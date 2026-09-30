<div class="page-header">
  <div><h1><?= e($title ?? 'Melding') ?></h1></div>
</div>
<div class="card empty-state">
  <div class="empty-state-icon"><?= icon('alert') ?></div>
  <p><?= e($message ?? '') ?></p>
  <a class="btn btn-primary" href="<?= e(url()) ?>"><?= icon('arrowLeft') ?>Terug naar start</a>
</div>
