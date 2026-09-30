<?php
/** @var string $content */
/** @var string $title */
$siteName = $config['app_name'];
$isStart = request_path() === '/';
?><!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#FAF9F6" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#21201C" media="(prefers-color-scheme: dark)">
<title><?= e($title ?? 'Start') ?> · <?= e($siteName) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="brand">
      <span class="brand-mark" aria-hidden="true">B</span>
      <span><?= e($siteName) ?></span>
    </div>
    <nav class="nav" aria-label="Hoofdnavigatie">
      <a class="nav-link<?= $isStart ? ' active' : '' ?>" href="<?= e(url()) ?>"<?= $isStart ? ' aria-current="page"' : '' ?>>
        <?= icon('dashboard') ?><span>Start</span>
      </a>
    </nav>
  </aside>
  <main class="main">
    <header class="topbar">
      <div class="brand">
        <span class="brand-mark" aria-hidden="true">B</span>
        <span><?= e($siteName) ?></span>
      </div>
    </header>
    <div id="page-content" class="page">
      <?= $content ?>
    </div>
  </main>
  <nav class="bottom-nav" aria-label="Hoofdnavigatie mobiel">
    <a class="nav-link<?= $isStart ? ' active' : '' ?>" href="<?= e(url()) ?>"<?= $isStart ? ' aria-current="page"' : '' ?>>
      <?= icon('dashboard') ?><span>Start</span>
    </a>
  </nav>
</div>
</body>
</html>
