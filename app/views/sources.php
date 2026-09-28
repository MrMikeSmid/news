<div class="page-header">
  <div>
    <h1>Bronnen</h1>
    <p>Voeg RSS-feeds en eigen bronnen toe die op je wall verschijnen.</p>
  </div>
  <?php if (count($sources)): ?>
  <form method="post" action="<?= e(url('sources/refresh-all')) ?>">
    <?= csrf_field() ?>
    <button class="btn btn-secondary" type="submit"><?= icon('refresh') ?> Alles verversen</button>
  </form>
  <?php endif; ?>
</div>

<div class="card card-pad" style="margin-bottom:20px">
  <div class="section-title" style="margin-top:0">Nieuwe bron toevoegen</div>
  <form method="post" action="<?= e(url('sources')) ?>">
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="field">
        <label for="name">Naam</label>
        <input type="text" id="name" name="name" placeholder="Bijv. NOS Algemeen" required>
      </div>
      <div class="field">
        <label for="type">Type</label>
        <select id="type" name="type">
          <option value="rss">RSS-feed</option>
          <option value="manual">Handmatig (social / eigen berichten)</option>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="field" style="flex:2">
        <label for="url">Feed-URL</label>
        <input type="url" id="url" name="url" placeholder="https://example.com/rss">
        <p class="hint">Nodig bij type RSS-feed. Ook geschikt voor RSS-bridges van social media.</p>
      </div>
      <div class="field">
        <label for="category">Categorie</label>
        <input type="text" id="category" name="category" placeholder="nieuws / social / sport…" value="nieuws">
      </div>
      <div class="field" style="flex:0 0 80px">
        <label for="color">Kleur</label>
        <input type="color" id="color" name="color" value="#da7756">
      </div>
    </div>
    <button class="btn btn-primary" type="submit"><?= icon('plus') ?> Bron toevoegen</button>
  </form>
</div>

<div class="section-title">Jouw bronnen (<?= count($sources) ?>)</div>
<div class="card">
  <?php if (!count($sources)): ?>
    <div class="empty-state"><?= icon('rss', 'icon-lg') ?><h3>Nog geen bronnen</h3><p>Voeg hierboven je eerste RSS-feed of eigen bron toe.</p></div>
  <?php endif; ?>
  <?php foreach ($sources as $source): ?>
    <div class="list-row<?= $source['is_active'] ? '' : ' status-off' ?>">
      <div class="source-dot" style="background:<?= e($source['color']) ?>"><?= icon($source['type'] === 'manual' ? 'megaphone' : 'rss') ?></div>
      <div class="grow">
        <div class="title"><?= e($source['name']) ?>
          <span class="badge"><?= e($source['category']) ?></span>
          <?php if (!$source['is_active']): ?><span class="badge">gepauzeerd</span><?php endif; ?>
        </div>
        <div class="sub">
          <?php if ($source['type'] === 'rss'): ?>
            <?= e($source['url']) ?>
            <?php if ($source['last_fetched_at']): ?> · laatst opgehaald <?= e(time_ago($source['last_fetched_at'])) ?><?php endif; ?>
            <?php if ($source['last_error']): ?> · <span style="color:var(--danger)">fout: <?= e($source['last_error']) ?></span><?php endif; ?>
          <?php else: ?>
            Handmatige bron
          <?php endif; ?>
        </div>
      </div>
      <div class="actions">
        <?php if ($source['type'] === 'rss'): ?>
        <form method="post" action="<?= e(url('sources/' . $source['id'] . '/refresh')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-ghost btn-icon btn-sm" title="Nu verversen" type="submit"><?= icon('refresh') ?></button>
        </form>
        <?php endif; ?>
        <form method="post" action="<?= e(url('sources/' . $source['id'] . '/toggle')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-ghost btn-icon btn-sm" title="Aan/uit" type="submit"><?= icon($source['is_active'] ? 'eye-off' : 'check') ?></button>
        </form>
        <form method="post" action="<?= e(url('sources/' . $source['id'] . '/delete')) ?>" data-confirm="Bron en alle bijbehorende berichten verwijderen?">
          <?= csrf_field() ?>
          <button class="btn btn-ghost btn-icon btn-sm" title="Verwijderen" type="submit"><?= icon('trash') ?></button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="section-title">Automatisch verversen</div>
<div class="card card-pad">
  <p style="color:var(--text-muted);font-size:13.5px;margin-top:0">Laat je hosting periodiek deze URL aanroepen (bijv. via een cron job) om alle actieve RSS-bronnen automatisch te verversen:</p>
  <code style="display:block;background:var(--bg-inset);padding:10px 12px;border-radius:8px;font-size:12.5px;word-break:break-all"><?= e(url('cron/fetch?token=' . setting('cron_token'))) ?></code>
  <p class="hint">De token vind je ook terug bij Instellingen en kan daar opnieuw gegenereerd worden.</p>
</div>
