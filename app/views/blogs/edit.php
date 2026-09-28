<?php
/** @var array|null $blog */
$isNew = $blog === null;
$action = $isNew ? url('blogs') : url('blogs/' . $blog['id']);
?>
<div class="page-header">
  <div>
    <h1><?= $isNew ? 'Nieuwe blog' : 'Blog bewerken' ?></h1>
    <p><?= $isNew ? 'Schrijf een nieuwe blogpost.' : 'Wijzig je blogpost of verstuur hem naar je andere site.' ?></p>
  </div>
  <a class="btn btn-secondary" href="<?= e(url('blogs')) ?>">← Terug naar blogs</a>
</div>

<div class="two-col">
  <div>
    <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="field">
        <label for="title">Titel</label>
        <input type="text" id="title" name="title" value="<?= e($blog['title'] ?? '') ?>" required>
      </div>
      <div class="field">
        <label for="excerpt">Samenvatting</label>
        <textarea id="excerpt" name="excerpt" style="min-height:60px" placeholder="Korte samenvatting voor overzichten…"><?= e($blog['excerpt'] ?? '') ?></textarea>
      </div>
      <div class="field">
        <label for="content-editor">Inhoud</label>
        <div class="editor-toolbar">
          <button type="button" data-cmd="bold"><b>B</b></button>
          <button type="button" data-cmd="italic"><i>I</i></button>
          <button type="button" data-cmd="formatBlock" onclick="document.execCommand('formatBlock',false,'h2');return false;">H2</button>
          <button type="button" data-cmd="insertUnorderedList">• Lijst</button>
          <button type="button" data-cmd="createLink"><?= icon('link', 'icon') ?></button>
        </div>
        <div id="content-editor" contenteditable="true" class="card" style="padding:16px;border-top:none;min-height:320px"></div>
        <input type="hidden" name="content" id="content-input" value="<?= e($blog['content'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="cover_image">Coverafbeelding</label>
        <?php if (!empty($blog['cover_image'])): ?>
          <div class="post-media" style="max-width:260px;margin-bottom:8px"><img src="<?= e($blog['cover_image']) ?>" alt=""></div>
        <?php endif; ?>
        <input type="file" id="cover_image" name="cover_image" accept="image/png,image/jpeg,image/gif,image/webp">
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="draft" <?= ($blog['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Concept</option>
          <option value="published" <?= ($blog['status'] ?? '') === 'published' ? 'selected' : '' ?>>Gepubliceerd</option>
        </select>
      </div>
      <button class="btn btn-primary" type="submit"><?= icon('check') ?> Opslaan</button>
    </form>
  </div>

  <div class="stack-sm">
    <?php if (!$isNew): ?>
    <div class="card card-pad">
      <div class="section-title" style="margin-top:0">Versturen</div>
      <p style="color:var(--text-muted);font-size:13.5px">Stuur deze blog via een API-call naar je andere website (endpoint stel je in bij Instellingen).</p>
      <form method="post" action="<?= e(url('blogs/' . $blog['id'] . '/send')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-primary btn-block" type="submit"><?= icon('send') ?> Versturen naar site</button>
      </form>
      <?php if ($blog['sent_at']): ?>
        <p class="hint">Laatst verstuurd <?= e(time_ago($blog['sent_at'])) ?> — status: <?= e($blog['send_status'] ?? '') ?></p>
      <?php endif; ?>
    </div>
    <div class="card card-pad">
      <div class="section-title" style="margin-top:0">Verwijderen</div>
      <form method="post" action="<?= e(url('blogs/' . $blog['id'] . '/delete')) ?>" data-confirm="Deze blog permanent verwijderen?">
        <?= csrf_field() ?>
        <button class="btn btn-danger btn-block" type="submit"><?= icon('trash') ?> Blog verwijderen</button>
      </form>
    </div>
    <?php else: ?>
    <div class="card card-pad">
      <p style="color:var(--text-muted);font-size:13.5px;margin:0">Sla de blog eerst op — daarna kun je hem versturen naar je andere website.</p>
    </div>
    <?php endif; ?>
  </div>
</div>
