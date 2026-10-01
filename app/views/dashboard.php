<?php
global $config;
$kindIcons = ['calendar' => 'calendar', 'tasks' => 'tasks', 'mail' => 'mail', 'news' => 'news'];
$recent = array_slice($reports, 0, 6);
?>
<div class="page-header">
  <div>
    <h1><?= $report ? e(slot_label($report['slot'])) : 'Verslag' ?></h1>
    <div class="sub"><?= $report ? e(format_dt($report['report_date'] . ' 12:00', 'date')) . ' · bijgewerkt ' . e(format_dt($report['updated_at'])) : 'Nog geen verslag ontvangen' ?></div>
  </div>
  <?php if (count($recent) > 1): ?>
  <div class="range-toggle" role="group" aria-label="Kies een verslag">
    <?php foreach ($recent as $r): $active = $report && $r['id'] === $report['id']; ?>
      <a class="<?= $active ? 'active' : '' ?>" aria-pressed="<?= $active ? 'true' : 'false' ?>" href="<?= e(url('rapport/' . $r['report_date'] . '/' . $r['slot'])) ?>"><?= e(format_dt($r['report_date'] . ' 12:00', 'date')) ?> · <?= $r['slot'] === 'morning' ? 'ochtend' : 'avond' ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php if (!$report): ?>
  <div class="card empty-state">
    <div class="empty-icon"><?= icon('bell') ?></div>
    <h2>Je eerste verslag komt eraan</h2>
    <p>Het volgende verslag wordt verwacht om <?= e($nextSlot['at']->format('H:i')) ?> (<?= $nextSlot['slot'] === 'morning' ? 'ochtend' : 'avond' ?>).</p>
    <?php if (admin_user()): ?><a class="btn btn-secondary" href="<?= e(url('settings')) ?>">Naar instellingen</a><?php endif; ?>
  </div>
<?php else: ?>
  <div class="grid">
    <?php foreach ($config['kinds'] as $kind => $label): $s = $sections[$kind] ?? null; ?>
      <section class="card" id="<?= e($kind) ?>">
        <div class="card-head">
          <span class="head-icon"><?= icon($kindIcons[$kind]) ?></span>
          <h2><?= e($label) ?></h2>
          <?php if ($s && $s['items']): ?><span class="badge badge-accent"><?= count($s['items']) ?></span><?php endif; ?>
        </div>
        <?php if (!$s): ?>
          <div class="empty-inline">Nog niet ontvangen.</div>
        <?php else: ?>
          <?php if ($s['period_from'] && $s['period_to']): ?>
            <div class="stat-sub" style="margin:0 0 10px">Periode <?= e(format_dt($s['period_from'])) ?> – <?= e(format_dt($s['period_to'])) ?></div>
          <?php endif; ?>
          <?php if ($s['summary'] !== ''): ?><div class="text"><?= text_html($s['summary']) ?></div><?php endif; ?>
          <?php if (!$s['items'] && $s['summary'] === ''): ?><div class="empty-inline">Niets te melden.</div><?php endif; ?>
          <?php if ($s['items']): ?>
            <div class="row-list">
              <?php foreach ($s['items'] as $it): ?>
                <div class="item">
                  <div class="item-top">
                    <div class="item-title"><?php if (!empty($it['url'])): ?><a href="<?= e($it['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($it['title'] ?? $it['url']) ?></a><?php else: ?><?= e($it['title'] ?? '') ?><?php endif; ?></div>
                    <?php if (!empty($it['time'])): ?><div class="item-time"><?= e($it['time']) ?></div><?php endif; ?>
                  </div>
                  <?php if (!empty($it['subtitle'])): ?><div class="item-subtitle"><?= e($it['subtitle']) ?></div><?php endif; ?>
                  <?php if (!empty($it['text'])): ?><div class="item-text"><?= e($it['text']) ?></div><?php endif; ?>
                  <?php if (!empty($it['badge'])): ?><div class="badges"><span class="badge badge-<?= e($it['tone'] ?? 'neutral') ?>"><?= e($it['badge']) ?></span></div><?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
  <div class="stat-sub">Volgend verslag om <?= e($nextSlot['at']->format('H:i')) ?>.</div>
<?php endif; ?>
