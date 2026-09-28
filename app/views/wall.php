<div class="page-header">
  <div>
    <h1>Wall</h1>
    <p>Alles uit jouw bronnen, op één plek.</p>
  </div>
</div>

<div class="card card-pad composer" style="margin-bottom:20px">
  <form method="post" action="<?= e(url('wall/post')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="field">
      <label for="body">Nieuw bericht</label>
      <textarea id="body" name="body" placeholder="Wat wil je delen op je wall?"></textarea>
    </div>
    <div class="field">
      <label for="link">Link (optioneel)</label>
      <input type="url" id="link" name="link" placeholder="https://…">
    </div>
    <div class="composer-actions">
      <div class="composer-attach">
        <label class="attach-label"><?= icon('image') ?> Afbeelding<input type="file" name="image" accept="image/png,image/jpeg,image/gif,image/webp"></label>
        <label class="attach-label"><?= icon('video') ?> Video<input type="file" name="video" accept="video/mp4,video/webm,video/quicktime"></label>
      </div>
      <button class="btn btn-primary" type="submit"><?= icon('send') ?> Plaatsen</button>
    </div>
    <div class="file-chip" data-for="image"></div>
    <div class="file-chip" data-for="video"></div>
  </form>
</div>

<?php if (count($categories) > 1): ?>
<div class="filter-bar">
  <a class="filter-pill<?= $activeCategory === '' ? ' active' : '' ?>" href="<?= e(url('wall')) ?>">Alles</a>
  <?php foreach ($categories as $cat): ?>
    <a class="filter-pill<?= $activeCategory === $cat ? ' active' : '' ?>" href="<?= e(url('wall?category=' . urlencode($cat))) ?>"><?= e($cat) ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="wall-grid">
  <?php if (!count($posts)): ?>
    <div class="card empty-state">
      <?php echo icon('wall', 'icon-lg'); ?>
      <h3>Nog geen berichten</h3>
      <p>Voeg een bron toe bij <a href="<?= e(url('sources')) ?>" style="color:var(--accent);font-weight:600">Bronnen</a> of plaats hierboven je eerste bericht.</p>
    </div>
  <?php endif; ?>

  <?php foreach ($posts as $post): ?>
    <?= render_partial('partials/post-card', ['post' => $post]) ?>
  <?php endforeach; ?>
</div>
