<?php
$platforms = platforms();
$isNew = $post === null;
$action = $isNew ? url('apps/' . $app['id'] . '/posts') : url('posts/' . $post['id']);
?>
<div class="page-header">
  <div>
    <h1><?= $isNew ? 'Nieuwe post' : 'Post bewerken' ?></h1>
    <p><?= e($app['name']) ?> — kies kanalen, de vereisten worden automatisch toegepast.</p>
  </div>
  <a class="btn btn-secondary" href="<?= e(url('apps/' . $app['id'])) ?>">← Terug naar app</a>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" id="post-form">
  <?= csrf_field() ?>
  <div class="card card-pad" style="margin-bottom:16px">
    <div class="field"><label for="title">Titel</label>
      <input type="text" id="title" name="title" value="<?= e($post['title'] ?? '') ?>" placeholder="Nodig voor o.a. Reddit">
    </div>
    <div class="field"><label for="body">Basistekst</label>
      <textarea id="body" name="body" style="min-height:140px" placeholder="Schrijf je bericht. Per kanaal kun je het hieronder aanpassen."><?= e($post['body'] ?? '') ?></textarea>
    </div>
    <div class="form-row">
      <div class="field"><label for="link">Link (optioneel)</label><input type="url" id="link" name="link" value="<?= e($post['link'] ?? '') ?>" placeholder="https://…"></div>
      <div class="field"><label for="image">Afbeelding</label>
        <input type="file" id="image" name="image" accept="image/png,image/jpeg,image/gif,image/webp">
        <?php if (!empty($post['image_path'])): ?>
          <div class="post-media" style="max-width:180px;margin-top:8px"><img src="<?= e($post['image_path']) ?>" alt=""></div>
          <label class="checkbox-row" style="margin-top:6px;font-weight:500"><input type="checkbox" name="remove_image" value="1" id="remove-image"> Afbeelding verwijderen</label>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="section-title">Publiceren op</div>
  <?php if (!count($channels)): ?>
    <div class="card empty-state"><?= icon('send', 'icon-lg') ?><h3>Geen actieve kanalen</h3>
      <p>Koppel eerst kanalen bij <a href="<?= e(url('apps/' . $app['id'])) ?>" style="color:var(--accent);font-weight:600">de app</a>.</p></div>
  <?php else: ?>
    <div class="chip-row" style="margin-bottom:14px">
      <?php foreach ($channels as $channel): $p = $platforms[$channel['platform']] ?? null; if (!$p) continue; ?>
        <label class="channel-toggle" style="--dot:<?= e($p['color']) ?>">
          <input type="checkbox" name="channels[]" value="<?= (int) $channel['id'] ?>" data-target="channel-<?= (int) $channel['id'] ?>" <?= isset($postChannels[$channel['id']]) ? 'checked' : '' ?>>
          <?= e($channel['label']) ?>
        </label>
      <?php endforeach; ?>
    </div>

    <?php foreach ($channels as $channel):
      $p = $platforms[$channel['platform']] ?? null; if (!$p) continue;
      $pc = $postChannels[$channel['id']] ?? null; ?>
      <div class="card card-pad channel-card" id="channel-<?= (int) $channel['id'] ?>" hidden
        data-max="<?= (int) $p['body_max'] ?>" data-url-len="<?= (int) ($p['url_len'] ?? 0) ?>" data-link="<?= e($p['link']) ?>"
        data-media="<?= e($p['media']) ?>" data-title="<?= e($p['title']) ?>" data-title-max="<?= (int) ($p['title_max'] ?? 0) ?>"
        data-subreddit="<?= $p['subreddit'] ? '1' : '0' ?>" data-hashtag-max="<?= (int) ($p['hashtag_max'] ?? 0) ?>">
        <div class="channel-head">
          <div class="source-dot" style="background:<?= e($p['color']) ?>"><?= e(mb_strtoupper(mb_substr($p['name'], 0, 1))) ?></div>
          <div class="grow"><div class="title"><?= e($channel['label']) ?> <span class="badge"><?= e($p['name']) ?></span>
            <?php if ($pc): ?><span class="badge badge-<?= e($pc['status']) ?>"><?= ['draft' => 'concept', 'ready' => 'klaar', 'published' => 'gepubliceerd', 'failed' => 'mislukt'][$pc['status']] ?? e($pc['status']) ?></span><?php endif; ?></div>
            <div class="sub"><?= e($p['note']) ?></div></div>
          <span class="counter" data-counter></span>
        </div>

        <?php if ($p['title'] !== 'none'): ?>
          <div class="field"><label>Titel<?= $p['title'] === 'required' ? ' *' : '' ?></label>
            <input type="text" name="ch_title[<?= (int) $channel['id'] ?>]" value="<?= e($pc['title'] ?? '') ?>" placeholder="Leeg = titel van de post" data-field="title"></div>
        <?php endif; ?>
        <?php if ($p['subreddit']): ?>
          <div class="field"><label>Subreddit *</label>
            <input type="text" name="ch_sub[<?= (int) $channel['id'] ?>]" value="<?= e($pc['subreddit'] ?? '') ?>" placeholder="bijv. webdev" data-field="subreddit"></div>
        <?php endif; ?>
        <div class="field"><label>Tekst voor dit kanaal</label>
          <textarea name="ch_body[<?= (int) $channel['id'] ?>]" data-field="body" placeholder="Leeg = basistekst"><?= e($pc['body'] ?? '') ?></textarea></div>

        <ul class="issues" data-issues></ul>
        <?php if ($pc && $pc['result']): ?><p class="hint">Laatste resultaat: <?= e($pc['result']) ?></p><?php endif; ?>
        <button type="button" class="btn btn-ghost btn-sm" data-copy><?= icon('check') ?> Kopieer tekst</button>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:18px">
    <button class="btn btn-primary" type="submit"><?= icon('check') ?> Opslaan</button>
  </div>
</form>

<?php if (!$isNew): ?>
<div class="two-col" style="margin-top:20px">
  <div class="card card-pad">
    <div class="section-title" style="margin-top:0">Publiceren</div>
    <p style="color:var(--text-muted);font-size:13.5px;margin-top:0">Verstuurt alle geldige kanalen met een webhook. Kanalen zonder webhook blijven op "klaar" staan om handmatig te plaatsen.</p>
    <form method="post" action="<?= e(url('posts/' . $post['id'] . '/publish')) ?>"><?= csrf_field() ?>
      <button class="btn btn-primary btn-block" type="submit"><?= icon('send') ?> Nu publiceren</button></form>
  </div>
  <div class="card card-pad">
    <div class="section-title" style="margin-top:0">Verwijderen</div>
    <form method="post" action="<?= e(url('posts/' . $post['id'] . '/delete')) ?>" data-confirm="Deze post verwijderen?"><?= csrf_field() ?>
      <button class="btn btn-danger btn-block" type="submit"><?= icon('trash') ?> Post verwijderen</button></form>
  </div>
</div>
<?php endif; ?>
