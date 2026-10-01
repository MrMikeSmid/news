<?php
/** @var string $content */
$admin = admin_user();
$currentPath = request_path();
$nav = [['/', 'Verslag', 'dashboard', ['/', '/rapport']]];
if ($admin) {
    $nav[] = ['/settings', 'Instellingen', 'settings', ['/settings']];
} else {
    $nav[] = ['/login', 'Inloggen', 'login', ['/login']];
}
$isActive = static function (array $item) use ($currentPath): bool {
    foreach ($item[3] as $prefix) {
        if ($prefix === '/' ? $currentPath === '/' : ($currentPath === $prefix || str_starts_with($currentPath, $prefix . '/'))) {
            return true;
        }
    }
    return false;
};
?><!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#FAF9F6">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<title><?= e($title ?? 'Dagstart') ?> · Dagstart</title>
<link rel="manifest" href="<?= e(url('manifest.json')) ?>">
<link rel="icon" href="<?= e(asset('icons/icon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(asset('icons/apple-touch-icon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <div class="brand"><span class="logo">D</span><span class="brand-name">Dagstart</span></div>
    <nav class="nav">
      <?php foreach ($nav as $item): ?>
        <a class="nav-link<?= $isActive($item) ? ' active' : '' ?>" href="<?= e(url($item[0])) ?>"><?= icon($item[2]) ?><span><?= e($item[1]) ?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="spacer"></div>
    <?php if ($admin): ?>
    <div class="user-box">
      <div class="user-name"><?= e($admin['username']) ?></div>
      <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?>
        <button class="nav-link nav-button" type="submit"><?= icon('logout') ?><span>Uitloggen</span></button>
      </form>
    </div>
    <?php endif; ?>
  </aside>
  <div class="main">
    <div class="topbar">
      <div class="brand"><span class="logo logo-sm">D</span><span class="brand-name">Dagstart</span></div>
      <?php if ($admin): ?>
      <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?>
        <button class="icon-btn" type="submit" aria-label="Uitloggen"><?= icon('logout') ?></button>
      </form>
      <?php endif; ?>
    </div>
    <div id="page-content" class="page">
      <?php if ($success = flash('success')): ?><div class="alert alert-success"><?= icon('check') ?><span><?= e($success) ?></span></div><?php endif; ?>
      <?php if ($error = flash('error')): ?><div class="alert alert-error"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
      <?= $content ?>
    </div>
  </div>
  <nav class="bottom-nav">
    <?php foreach ($nav as $item): ?>
      <a class="<?= $isActive($item) ? 'active' : '' ?>" href="<?= e(url($item[0])) ?>"><?= icon($item[2]) ?><span><?= e($item[1]) ?></span></a>
    <?php endforeach; ?>
  </nav>
</div>
<script>window.APP_BASE = <?= json_encode(url('')) ?>;</script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
