<div class="empty-state">
  <div class="empty-icon"><?= icon('alert') ?></div>
  <h2><?= e($title ?? 'Melding') ?></h2>
  <p><?= e($message ?? '') ?></p>
  <a class="btn btn-secondary" href="<?= e(url('/')) ?>">Naar het overzicht</a>
</div>
