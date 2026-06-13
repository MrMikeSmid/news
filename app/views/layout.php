<?php $isAdmin = $adminLayout ?? false; ?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sportweek ’t Zand onderzoekt of er genoeg animo is voor een sportieve week in het dorp.">
    <title><?= e($title ?? '') ?> | Sportweek ’t Zand</title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <script defer src="<?= e(asset('js/app.js')) ?>"></script>
</head>
<body class="<?= $isAdmin ? 'admin-body' : '' ?>">
<a class="skip-link" href="#inhoud">Ga naar inhoud</a>
<header class="site-header">
    <div class="container nav-wrap">
        <a class="brand" href="<?= e(url()) ?>" aria-label="Sportweek ’t Zand home">
            <span class="brand-mark">SZ</span>
            <span>Sportweek <strong>’t Zand</strong><small>Van idee naar beweging</small></span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-nav"><span></span><span></span><span></span><span class="sr-only">Menu</span></button>
        <nav id="main-nav" class="main-nav" aria-label="Hoofdnavigatie">
            <?php if ($isAdmin && admin_user()): ?>
                <a href="<?= e(url('admin')) ?>">Dashboard</a>
                <a href="<?= e(url('admin/submissions/interest')) ?>">Interesse</a>
                <a href="<?= e(url('admin/submissions/volunteers')) ?>">Vrijwilligers</a>
                <a href="<?= e(url('admin/submissions/sponsors')) ?>">Sponsoren</a>
                <a href="<?= e(url('admin/submissions/ideas')) ?>">Ideeën</a>
                <form method="post" action="<?= e(url('admin/logout')) ?>"><?= csrf_field() ?><button class="link-button">Uitloggen</button></form>
            <?php else: ?>
                <a href="<?= e(url()) ?>">Home</a>
                <a href="<?= e(url('#concept')) ?>">Concept</a>
                <a href="<?= e(url('#activiteiten')) ?>">Activiteiten</a>
                <a href="<?= e(url('interesse')) ?>">Interesse</a>
                <a href="<?= e(url('vrijwilligers')) ?>">Vrijwilligers</a>
                <a href="<?= e(url('sponsoren')) ?>">Sponsoren</a>
                <a href="<?= e(url('idee')) ?>">Idee insturen</a>
                <a href="<?= e(url('contact')) ?>">Contact</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main id="inhoud">
    <?php if ($message = flash('success')): ?><div class="container flash success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($message = flash('error')): ?><div class="container flash error"><?= e($message) ?></div><?php endif; ?>
    <?= $content ?>
</main>
<?php if (!$isAdmin): ?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div><a class="brand" href="<?= e(url()) ?>"><span class="brand-mark">SZ</span><span>Sportweek <strong>’t Zand</strong></span></a><p>Een lokaal initiatief om animo te peilen.</p></div>
        <div><h2>Pagina’s</h2><a href="<?= e(url('privacy')) ?>">Privacy</a><a href="<?= e(url('contact')) ?>">Contact</a><a href="<?= e(url('admin/login')) ?>">Beheer</a></div>
        <div><h2>Volg het initiatief</h2>
            <?php global $config; foreach ($config['socials'] as $label => $socialUrl): ?>
                <a href="<?= e($socialUrl ?: '#') ?>"<?= $socialUrl ? ' target="_blank" rel="noopener"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="container footer-bottom">© <?= date('Y') ?> Sportweek ’t Zand · We onderzoeken samen wat mogelijk is.</div>
</footer>
<?php endif; ?>
</body>
</html>
