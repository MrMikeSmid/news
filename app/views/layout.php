<?php
/** @var string $content */
/** @var string $title */
$siteName = $config['app_name'];
$guest = !owner_logged_in();
$navItems = [
    ['path'=>'', 'label'=>'Start', 'icon'=>'dashboard', 'match'=>'/'],
    ['path'=>'mail', 'label'=>'Mail', 'icon'=>'mail', 'match'=>'/mail'],
    ['path'=>'mail/accounts', 'label'=>'Accounts', 'icon'=>'settings', 'match'=>'/mail/accounts'],
    ['path'=>'connections', 'label'=>'Chat', 'icon'=>'link', 'match'=>'/connections'],
];
$activeNav = static function(array $item): bool {
    $path=request_path();
    if($item['path']==='mail' && str_starts_with($path,'/mail/accounts')) return false;
    return $path===$item['match'] || ($item['match']!=='/' && str_starts_with($path,$item['match'].'/'));
};
$notice=pull_flash();
?><!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#FAF9F6" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#21201C" media="(prefers-color-scheme: dark)">
<title><?= e($title ?? 'Start') ?> · <?= e($siteName) ?></title>
<link rel="icon" href="<?=e(asset('icons/brand.svg'))?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<?php if($guest): ?>
<?= $content ?>
<?php else: ?>
<div class="layout">
  <aside class="sidebar">
    <div class="brand">
      <span class="brand-mark" aria-hidden="true">B</span>
      <span><?= e($siteName) ?></span>
    </div>
    <nav class="nav" aria-label="Hoofdnavigatie">
      <?php foreach($navItems as $item): ?>
      <a class="nav-link<?= $activeNav($item)?' active':'' ?>" href="<?=e(url($item['path']))?>" <?=$activeNav($item)?'aria-current="page"':''?>><?=icon($item['icon'])?><span><?=e($item['label'])?></span></a>
      <?php endforeach; ?>
    </nav>
    <div class="user-box">
      <a class="nav-link" href="<?=e(url('security'))?>"><?=icon('lock')?>Beveiliging</a>
      <form method="post" action="<?=e(url('logout'))?>"><?=csrf_field()?><button class="nav-link logout-button" type="submit"><?=icon('logout')?>Uitloggen</button></form>
    </div>
  </aside>
  <main class="main">
    <header class="topbar">
      <div class="brand">
        <span class="brand-mark" aria-hidden="true">B</span>
        <span><?= e($siteName) ?></span>
      </div>
      <form method="post" action="<?=e(url('logout'))?>"><?=csrf_field()?><button class="topbar-logout" aria-label="Uitloggen" type="submit"><?=icon('logout')?></button></form>
    </header>
    <div id="page-content" class="page">
      <?php if($notice):?><div class="alert <?=$notice['error']?'alert-error':'alert-success'?>" role="status"><?=icon($notice['error']?'alert':'check')?><?=e($notice['message'])?></div><?php endif;?>
      <?= $content ?>
    </div>
  </main>
  <nav class="bottom-nav" aria-label="Hoofdnavigatie mobiel">
    <?php foreach($navItems as $item):?><a class="nav-link <?=$activeNav($item)?'active':''?>" href="<?=e(url($item['path']))?>" <?=$activeNav($item)?'aria-current="page"':''?>><?=icon($item['icon'])?><span><?=e($item['label'])?></span></a><?php endforeach;?>
  </nav>
</div>
<?php endif; ?>
<script src="<?=e(asset('js/app.js'))?>"></script>
</body>
</html>
