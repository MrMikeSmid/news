<?php
/** @var string $content */
/** @var string $title */
$siteName = $config['app_name'];
?><!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Start') ?> · <?= e($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,500;8..60,600;8..60,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<div class="app-shell">
  <div class="backdrop" id="backdrop"></div>
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <span class="brand-mark">H</span>
      <span><?= e($siteName) ?></span>
    </div>
    <nav class="nav" aria-label="Hoofdnavigatie">
      <a class="nav-item<?= request_path() === '/' ? ' active' : '' ?>" href="<?= e(url()) ?>"<?= request_path() === '/' ? ' aria-current="page"' : '' ?>>
        <?= icon('wall') ?><span>Start</span>
      </a>
    </nav>
  </aside>
  <main class="main">
    <div class="topbar">
      <button class="btn btn-ghost btn-icon" id="menu-toggle" aria-label="Menu" aria-controls="sidebar" aria-expanded="false"><?= icon('menu') ?></button>
      <span class="brand" style="padding:0"><span class="brand-mark" style="width:26px;height:26px;font-size:13px">H</span><?= e($siteName) ?></span>
      <span style="width:38px"></span>
    </div>
    <div class="content">
      <?= $content ?>
    </div>
  </main>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
