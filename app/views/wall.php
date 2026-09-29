<div class="page-header">
  <div>
    <h1>Wall</h1>
    <p>Alles uit jouw bronnen, op één plek.</p>
  </div>
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
      <p>Voeg een bron toe bij <a href="<?= e(url('sources')) ?>" style="color:var(--accent);font-weight:600">Bronnen</a> om je wall te vullen.</p>
    </div>
  <?php endif; ?>

  <?php foreach ($posts as $post): ?>
    <?= render_partial('partials/post-card', ['post' => $post]) ?>
  <?php endforeach; ?>
</div>
