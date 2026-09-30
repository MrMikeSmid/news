<?php $platforms = platforms(); ?>
<div class="page-header">
  <div>
    <h1><?= e($app['name']) ?></h1>
    <p><?= e($app['description'] ?: 'Kanalen en posts van deze app.') ?></p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn btn-secondary" href="<?= e(url('apps')) ?>">← Apps</a>
    <a class="btn btn-primary" href="<?= e(url('apps/' . $app['id'] . '/posts/new')) ?>"><?= icon('plus') ?> Nieuwe post</a>
  </div>
</div>

<div class="section-title">Publish-kanalen</div>
<div class="card" style="margin-bottom:12px">
  <?php if (!count($channels)): ?>
    <div class="empty-state"><?= icon('send', 'icon-lg') ?><h3>Nog geen kanalen</h3><p>Koppel hieronder je eerste platform.</p></div>
  <?php endif; ?>
  <?php foreach ($channels as $channel): $p = $platforms[$channel['platform']] ?? null; ?>
    <div class="list-row<?= $channel['is_active'] ? '' : ' status-off' ?>">
      <div class="source-dot" style="background:<?= e($p['color'] ?? '#888') ?>"><?= e(mb_strtoupper(mb_substr($p['name'] ?? '?', 0, 1))) ?></div>
      <div class="grow">
        <div class="title"><?= e($channel['label']) ?> <span class="badge"><?= e($p['name'] ?? $channel['platform']) ?></span>
          <?php if (!$channel['is_active']): ?><span class="badge">gepauzeerd</span><?php endif; ?></div>
        <div class="sub"><?= e($channel['handle'] ?: 'geen account opgegeven') ?> · <?= $channel['webhook_url'] ? 'webhook gekoppeld' : 'handmatig plaatsen' ?><?= $p ? ' · ' . e($p['note']) : '' ?></div>
      </div>
      <div class="actions">
        <form method="post" action="<?= e(url('channels/' . $channel['id'] . '/toggle')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-icon btn-sm" title="Aan/uit"><?= icon($channel['is_active'] ? 'eye-off' : 'check') ?></button></form>
        <form method="post" action="<?= e(url('channels/' . $channel['id'] . '/delete')) ?>" data-confirm="Kanaal ontkoppelen?"><?= csrf_field() ?><button class="btn btn-ghost btn-icon btn-sm" title="Ontkoppelen"><?= icon('trash') ?></button></form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<details class="collapse" style="margin-bottom:24px">
<summary class="btn btn-secondary"><?= icon('plus') ?> Kanaal koppelen</summary>
<div class="card card-pad" style="margin-top:12px">
  <form method="post" action="<?= e(url('apps/' . $app['id'] . '/channels')) ?>">
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="field"><label for="platform">Platform</label>
        <select id="platform" name="platform"><?php foreach ($platforms as $key => $p): ?><option value="<?= e($key) ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="label">Naam (optioneel)</label><input type="text" id="label" name="label" placeholder="Bijv. X van de app"></div>
      <div class="field"><label for="handle">Account</label><input type="text" id="handle" name="handle" placeholder="@gebruikersnaam"></div>
    </div>
    <div class="field"><label for="webhook_url">Webhook-URL (optioneel)</label>
      <input type="url" id="webhook_url" name="webhook_url" placeholder="https://hooks.example.com/…">
      <p class="hint">Bij publiceren sturen we de kant-en-klare post als JSON hierheen (bijv. Zapier, Make of n8n). Zonder webhook plaats je handmatig.</p></div>
    <button class="btn btn-secondary" type="submit"><?= icon('check') ?> Koppelen</button>
  </form>
</div>
</details>

<div class="section-title">Posts (<?= count($posts) ?>)</div>
<div class="card" style="margin-bottom:24px">
  <?php if (!count($posts)): ?>
    <div class="empty-state"><?= icon('newspaper', 'icon-lg') ?><h3>Nog geen posts</h3><p>Schrijf je eerste post en kies de kanalen.</p></div>
  <?php endif; ?>
  <?php foreach ($posts as $post): ?>
    <a class="list-row" href="<?= e(url('posts/' . $post['id'] . '/edit')) ?>">
      <div class="grow">
        <div class="title"><?= e($post['title'] ?: mb_substr($post['body'], 0, 60)) ?>
          <span class="badge badge-<?= e($post['status']) ?>"><?= ['draft' => 'concept', 'ready' => 'klaar', 'published' => 'gepubliceerd'][$post['status']] ?? e($post['status']) ?></span></div>
        <div class="sub">Bijgewerkt <?= e(time_ago($post['updated_at'])) ?></div>
        <div class="chip-row">
          <?php foreach ($chipsByPost[$post['id']] ?? [] as $chip): $p = $platforms[$chip['platform']] ?? null; ?>
            <span class="chip chip-<?= e($chip['status']) ?>" style="--dot:<?= e($p['color'] ?? '#888') ?>"><?= e($p['name'] ?? $chip['platform']) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?= icon('edit') ?>
    </a>
  <?php endforeach; ?>
</div>

<form method="post" action="<?= e(url('apps/' . $app['id'] . '/delete')) ?>" data-confirm="App met alle kanalen en posts verwijderen?">
  <?= csrf_field() ?>
  <button class="btn btn-danger btn-sm" type="submit"><?= icon('trash') ?> App verwijderen</button>
</form>
