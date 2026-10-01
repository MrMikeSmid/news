<div class="page-header"><div><h1>Agenda</h1><div class="sub">CalDAV-agenda's (zoals iCloud) die je AI mag lezen.</div></div></div>
<?= $tabs ?>

<details class="collapse" style="margin-bottom:20px">
  <summary class="btn btn-primary"><?= icon('plus') ?> Agenda toevoegen</summary>
  <div class="card form-card"><?php $acc = null; require __DIR__ . '/calendar_form.php'; ?></div>
</details>

<div class="card">
  <?php if (!$accounts): ?>
    <div class="empty-state"><div class="empty-icon"><?= icon('calendar') ?></div><h2>Nog geen agenda</h2><p>Voeg je iCloud-agenda toe met een app-specifiek wachtwoord (appleid.apple.com → Aanmelden en beveiliging).</p></div>
  <?php endif; ?>
  <?php foreach ($accounts as $acc): ?>
    <div class="account<?= $acc['is_active'] ? '' : ' off' ?>" style="flex-wrap:wrap">
      <div class="grow">
        <div class="name"><?= e($acc['label']) ?>
          <?php if (!$acc['is_active']): ?><span class="badge badge-warn">gepauzeerd</span><?php endif; ?>
          <?php if (!$acc['verify_cert']): ?><span class="badge badge-warn">certificaten niet gecontroleerd</span><?php endif; ?></div>
        <div class="meta"><?= e($acc['username']) ?> · <?= e($acc['server_url']) ?><?= $acc['calendar_filter'] ? ' · ' . e($acc['calendar_filter']) : '' ?></div>
      </div>
      <div class="btn-row">
        <form method="post" action="<?= e(url('settings/calendar/' . $acc['id'] . '/test')) ?>" data-busy="Testen…"><?= csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit"><?= icon('refresh') ?> Test</button></form>
        <form method="post" action="<?= e(url('settings/calendar/' . $acc['id'] . '/toggle')) ?>"><?= csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit"><?= $acc['is_active'] ? 'Pauzeer' : 'Activeer' ?></button></form>
        <form method="post" action="<?= e(url('settings/calendar/' . $acc['id'] . '/delete')) ?>" data-confirm="Dit agenda-account verwijderen?"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit"><?= icon('trash') ?></button></form>
      </div>
      <details class="collapse" style="flex-basis:100%">
        <summary class="btn btn-secondary btn-sm"><?= icon('edit') ?> Bewerken</summary>
        <div class="card"><?php require __DIR__ . '/calendar_form.php'; ?></div>
      </details>
    </div>
  <?php endforeach; ?>
</div>
