<div class="page-header"><div><h1>Nieuws</h1><div class="sub">Bronnen waaruit je AI zoekt naar nieuws in het verslagvenster.</div></div></div>
<?= $tabs ?>

<details class="collapse" style="margin-bottom:20px">
  <summary class="btn btn-primary"><?= icon('plus') ?> Bron toevoegen</summary>
  <div class="card form-card">
    <form method="post" action="<?= e(url('settings/news')) ?>"><?= csrf_field() ?>
      <div class="form-row">
        <div class="field"><label for="n-name">Naam</label><input type="text" id="n-name" name="name" placeholder="Bijv. NU.nl" required></div>
        <div class="field"><label for="n-url">Website of feed-URL</label><input type="url" id="n-url" name="url" placeholder="https://www.nu.nl" required></div>
      </div>
      <div class="field"><label for="n-notes">Waar moet de AI op letten? (optioneel)</label><input type="text" id="n-notes" name="notes" placeholder="Bijv. alleen tech en economie"></div>
      <button class="btn btn-primary" type="submit"><?= icon('check') ?> Bron toevoegen</button>
    </form>
  </div>
</details>

<div class="card">
  <?php if (!$sources): ?>
    <div class="empty-state"><div class="empty-icon"><?= icon('news') ?></div><h2>Nog geen bronnen</h2><p>Voeg een nieuwssite of RSS-feed toe. De AI zoekt zelf naar wat er in het venster is verschenen.</p></div>
  <?php endif; ?>
  <?php foreach ($sources as $s): ?>
    <div class="account<?= $s['is_active'] ? '' : ' off' ?>" style="flex-wrap:wrap">
      <div class="grow">
        <div class="name"><?= e($s['name']) ?><?php if (!$s['is_active']): ?> <span class="badge badge-warn">gepauzeerd</span><?php endif; ?></div>
        <div class="meta"><?= e($s['url']) ?><?= $s['notes'] ? ' · ' . e($s['notes']) : '' ?></div>
      </div>
      <div class="btn-row">
        <form method="post" action="<?= e(url('settings/news/' . $s['id'] . '/toggle')) ?>"><?= csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit"><?= $s['is_active'] ? 'Pauzeer' : 'Activeer' ?></button></form>
        <form method="post" action="<?= e(url('settings/news/' . $s['id'] . '/delete')) ?>" data-confirm="Deze bron verwijderen?"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit"><?= icon('trash') ?></button></form>
      </div>
      <details class="collapse" style="flex-basis:100%">
        <summary class="btn btn-secondary btn-sm"><?= icon('edit') ?> Bewerken</summary>
        <div class="card form-card">
          <form method="post" action="<?= e(url('settings/news/' . $s['id'])) ?>"><?= csrf_field() ?>
            <div class="form-row">
              <div class="field"><label>Naam</label><input type="text" name="name" value="<?= e($s['name']) ?>" required></div>
              <div class="field"><label>URL</label><input type="url" name="url" value="<?= e($s['url']) ?>" required></div>
            </div>
            <div class="field"><label>Notities</label><input type="text" name="notes" value="<?= e($s['notes'] ?? '') ?>"></div>
            <button class="btn btn-primary" type="submit"><?= icon('check') ?> Opslaan</button>
          </form>
        </div>
      </details>
    </div>
  <?php endforeach; ?>
</div>
