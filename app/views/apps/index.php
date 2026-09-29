<div class="page-header">
  <div>
    <h1>Apps</h1>
    <p>Elke app heeft eigen kanalen waarop je berichten publiceert.</p>
  </div>
</div>

<div class="card card-pad" style="margin-bottom:20px">
  <div class="section-title">Nieuwe app</div>
  <form method="post" action="<?= e(url('apps')) ?>">
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="field"><label for="name">Naam</label><input type="text" id="name" name="name" placeholder="Bijv. Mijn Sportapp" required></div>
      <div class="field"><label for="description">Omschrijving</label><input type="text" id="description" name="description" placeholder="Waar gaat de app over?"></div>
      <div class="field" style="flex:0 0 80px"><label for="color">Kleur</label><input type="color" id="color" name="color" value="#da7756"></div>
    </div>
    <button class="btn btn-primary" type="submit"><?= icon('plus') ?> App aanmaken</button>
  </form>
</div>

<div class="card">
  <?php if (!count($apps)): ?>
    <div class="empty-state"><?= icon('wall', 'icon-lg') ?><h3>Nog geen apps</h3><p>Maak hierboven je eerste app aan.</p></div>
  <?php endif; ?>
  <?php foreach ($apps as $app): ?>
    <a class="list-row" href="<?= e(url('apps/' . $app['id'])) ?>">
      <div class="source-dot" style="background:<?= e($app['color']) ?>"><?= e(mb_strtoupper(mb_substr($app['name'], 0, 1))) ?></div>
      <div class="grow">
        <div class="title"><?= e($app['name']) ?></div>
        <div class="sub"><?= e($app['description'] ?: 'Geen omschrijving') ?> · <?= (int) $app['channel_count'] ?> kanalen · <?= (int) $app['post_count'] ?> posts</div>
      </div>
      <?= icon('external') ?>
    </a>
  <?php endforeach; ?>
</div>
