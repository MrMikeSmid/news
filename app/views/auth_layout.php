<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#FAF9F6">
<title><?= e($title ?? 'Dagstart') ?> · Dagstart</title>
<link rel="icon" href="<?= e(asset('icons/icon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <div class="brand brand-center"><span class="logo logo-lg">D</span><span class="brand-name">Dagstart</span></div>
    <?php if ($success = flash('success')): ?><div class="alert alert-success"><?= icon('check') ?><span><?= e($success) ?></span></div><?php endif; ?>
    <?php if ($error = flash('error')): ?><div class="alert alert-error"><?= icon('alert') ?><span><?= e($error) ?></span></div><?php endif; ?>
    <?= $content ?>
  </div>
</div>
</body>
</html>
