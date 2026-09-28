<?php
/** @var string $content */
/** @var string $title */
$user = admin_user();
$siteName = database_is_ready() ? setting('site_name', 'Homebase') : 'Homebase';
$currentPath = request_path();
$navItems = [
    ['path' => 'wall', 'label' => 'Wall', 'icon' => 'wall'],
    ['path' => 'sources', 'label' => 'Bronnen', 'icon' => 'rss'],
    ['path' => 'blogs', 'label' => 'Blogs', 'icon' => 'newspaper'],
    ['path' => 'settings', 'label' => 'Instellingen', 'icon' => 'settings'],
];
?><!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Homebase') ?> · <?= e($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<?php if ($user): ?>
<div class="app-shell">
  <div class="backdrop" id="backdrop"></div>
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <span class="brand-mark">H</span>
      <span><?= e($siteName) ?></span>
    </div>
    <nav class="nav">
      <?php foreach ($navItems as $item): ?>
      <a class="nav-item<?= $currentPath === '/' . $item['path'] ? ' active' : '' ?>" href="<?= e(url($item['path'])) ?>">
        <?= icon($item['icon']) ?><span><?= e($item['label']) ?></span>
      </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
      <div class="nav-item" style="cursor:default"><?= icon('sparkle') ?><span><?= e($user['username']) ?></span></div>
      <form method="post" action="<?= e(url('logout')) ?>">
        <?= csrf_field() ?>
        <button class="nav-item" style="width:100%;border:none;background:transparent;cursor:pointer;text-align:left"><?= icon('logout') ?><span>Uitloggen</span></button>
      </form>
    </div>
  </aside>
  <div class="main">
    <div class="topbar">
      <button class="btn btn-ghost btn-icon" id="menu-toggle" aria-label="Menu"><?= icon('menu') ?></button>
      <span class="brand" style="padding:0"><span class="brand-mark" style="width:26px;height:26px;font-size:13px">H</span><?= e($siteName) ?></span>
      <span style="width:38px"></span>
    </div>
    <div class="content">
      <?php if ($success = flash('success')): ?><div class="alert alert-success"><?= icon('check') ?><?= e($success) ?></div><?php endif; ?>
      <?php if ($error = flash('error')): ?><div class="alert alert-error"><?= icon('x') ?><?= e($error) ?></div><?php endif; ?>
      <?= $content ?>
    </div>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php else: ?>
<?= $content ?>
<?php endif; ?>
</body>
</html>
