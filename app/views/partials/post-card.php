<?php
/** @var array $post */
$sourceName = $post['source_name'] ?? 'Eigen bericht';
$color = $post['source_color'] ?? '#da7756';
$initial = mb_strtoupper(mb_substr($sourceName, 0, 1));
?>
<article class="card post-card">
  <div class="post-head">
    <div class="source-dot" style="background:<?= e($color) ?>"><?= e($initial) ?></div>
    <div class="post-meta">
      <div class="post-source"><?= e($sourceName) ?>
        <?php if (!empty($post['source_category'])): ?><span class="badge"><?= e($post['source_category']) ?></span><?php endif; ?>
        <?php if (($post['media_type'] ?? '') === 'youtube'): ?><span class="badge badge-youtube"><?= icon('play') ?> YouTube</span><?php endif; ?>
      </div>
      <div class="post-time"><?= e(time_ago($post['published_at'])) ?></div>
    </div>
    <form method="post" action="<?= e(url('posts/' . $post['id'] . '/delete')) ?>" data-confirm="Dit bericht verwijderen?">
      <?= csrf_field() ?>
      <button class="btn btn-ghost btn-icon btn-sm" title="Verwijderen" type="submit"><?= icon('trash') ?></button>
    </form>
  </div>

  <?php if (!empty($post['title'])): ?><h3 class="post-title"><?= e($post['title']) ?></h3><?php endif; ?>
  <?php if (!empty($post['body'])): ?><p class="post-body"><?= e($post['body']) ?></p><?php endif; ?>

  <?php if (($post['media_type'] ?? '') === 'youtube' && !empty($post['video_path'])): ?>
    <div class="post-media-embed">
      <iframe src="<?= e($post['video_path']) ?>" title="<?= e($post['title'] ?? 'YouTube video') ?>" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
    </div>
  <?php elseif (!empty($post['image_path'])): ?>
    <div class="post-media"><img src="<?= e($post['image_path']) ?>" alt="" loading="lazy"></div>
  <?php elseif (!empty($post['video_path'])): ?>
    <div class="post-media"><video src="<?= e($post['video_path']) ?>" controls preload="metadata"></video></div>
  <?php endif; ?>

  <?php if (!empty($post['link'])): ?>
    <div class="post-foot">
      <a class="post-link" href="<?= e($post['link']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external') ?> Lees meer</a>
    </div>
  <?php endif; ?>
</article>
