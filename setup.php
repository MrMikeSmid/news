<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
        $errors[] = 'Gebruik een gebruikersnaam van minimaal 3 tekens zonder spaties.';
    }
    if (strlen($password) < 12) {
        $errors[] = 'Gebruik een wachtwoord van minimaal 12 tekens.';
    }
    if ($password !== $confirmation) {
        $errors[] = 'De wachtwoorden komen niet overeen.';
    }

    if (!$errors) {
        $schema = file_get_contents(__DIR__ . '/database/schema.sql');
        db()->exec($schema);
        $count = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ($count === 0) {
            $statement = db()->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:username, :hash)');
            $statement->execute(['username' => $username, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
            $success = true;
        } else {
            $errors[] = 'Er bestaat al een beheeraccount. Setup heeft geen wijzigingen aangebracht.';
        }
    }
}
?>
<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Installatie | Sportweek ’t Zand</title><link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>"></head><body>
<main class="message-page"><div class="login-card setup-card"><span class="brand-mark">SZ</span><h1>Sportweek ’t Zand installeren</h1>
<?php if ($success): ?><div class="form-alert success"><strong>Installatie voltooid.</strong><p>De database en het beheeraccount zijn aangemaakt. Verwijder of hernoem nu <code>setup.php</code>.</p></div><a class="button primary wide" href="<?= e(url('admin/login')) ?>">Naar beheer →</a>
<?php else: ?><p>Maak de SQLite-tabellen en het eerste beheeraccount aan.</p><?php foreach($errors as $error): ?><div class="form-alert"><?= e($error) ?></div><?php endforeach; ?><form method="post"><?= csrf_field() ?><label>Gebruikersnaam<input name="username" required value="<?= old('username') ?>"></label><label>Wachtwoord (minimaal 12 tekens)<input type="password" name="password" required></label><label>Herhaal wachtwoord<input type="password" name="password_confirmation" required></label><button class="button primary wide">Installatie starten →</button></form><?php endif; ?>
</div></main></body></html>
