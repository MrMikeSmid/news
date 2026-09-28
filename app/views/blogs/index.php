<div class="page-header">
  <div>
    <h1>Blogs</h1>
    <p>Schrijf blogs en verstuur ze later via een API-call naar je andere site.</p>
  </div>
  <a class="btn btn-primary" href="<?= e(url('blogs/new')) ?>"><?= icon('plus') ?> Nieuwe blog</a>
</div>

<div class="card">
  <?php if (!count($blogs)): ?>
    <div class="empty-state"><?= icon('newspaper', 'icon-lg') ?><h3>Nog geen blogs</h3><p>Schrijf je eerste blogpost.</p></div>
  <?php endif; ?>
  <?php foreach ($blogs as $blog): ?>
    <a class="list-row" href="<?= e(url('blogs/' . $blog['id'] . '/edit')) ?>" style="cursor:pointer">
      <div class="grow">
        <div class="title"><?= e($blog['title']) ?>
          <span class="badge"><?= $blog['status'] === 'published' ? 'gepubliceerd' : 'concept' ?></span>
          <?php if ($blog['sent_at']): ?><span class="badge" style="color:var(--success)"><?= icon('check') ?> verstuurd</span><?php endif; ?>
        </div>
        <div class="sub">Bijgewerkt <?= e(time_ago($blog['updated_at'])) ?></div>
      </div>
      <?= icon('edit') ?>
    </a>
  <?php endforeach; ?>
</div>
