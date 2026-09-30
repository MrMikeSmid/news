<?php

declare(strict_types=1);

function storage_path(string $name): string
{
    return (getenv('APP_STORAGE_PATH') ?: dirname(__DIR__) . '/storage') . '/' . $name;
}

function mail_db(): PDO
{
    static $db;
    if ($db instanceof PDO) return $db;
    $dir = dirname(storage_path('mail.sqlite'));
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Opslag is niet beschikbaar.');
    $db = new PDO('sqlite:' . storage_path('mail.sqlite'), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000;');
    $db->exec('CREATE TABLE IF NOT EXISTS mail_admin (id INTEGER PRIMARY KEY CHECK(id=1), username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL);
      CREATE TABLE IF NOT EXISTS mail_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT NOT NULL, email TEXT NOT NULL, from_name TEXT NOT NULL, smtp_host TEXT NOT NULL, smtp_port INTEGER NOT NULL, smtp_security TEXT NOT NULL, smtp_user TEXT NOT NULL, smtp_password TEXT NOT NULL, imap_host TEXT NOT NULL, imap_port INTEGER NOT NULL, imap_security TEXT NOT NULL, imap_user TEXT NOT NULL, imap_password TEXT NOT NULL, sent_folder TEXT NOT NULL, trash_folder TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
      CREATE TABLE IF NOT EXISTS mail_drafts (id INTEGER PRIMARY KEY AUTOINCREMENT, account_id INTEGER NOT NULL REFERENCES mail_accounts(id) ON DELETE CASCADE, recipients TEXT NOT NULL, cc TEXT NOT NULL, bcc TEXT NOT NULL, subject TEXT NOT NULL, body TEXT NOT NULL, reply_id TEXT, refs TEXT, attachments TEXT NOT NULL DEFAULT "[]", status TEXT NOT NULL DEFAULT "draft", sent_copy INTEGER NOT NULL DEFAULT 0, revision INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
      CREATE TABLE IF NOT EXISTS mail_settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
      CREATE TABLE IF NOT EXISTS mail_tokens (hash TEXT PRIMARY KEY, name TEXT NOT NULL, scope TEXT NOT NULL, expires INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
      CREATE TABLE IF NOT EXISTS mail_oauth_clients (id TEXT PRIMARY KEY, name TEXT NOT NULL, redirects TEXT NOT NULL);
      CREATE TABLE IF NOT EXISTS mail_oauth_codes (hash TEXT PRIMARY KEY, client_id TEXT NOT NULL, redirect TEXT NOT NULL, challenge TEXT NOT NULL, scope TEXT NOT NULL, expires INTEGER NOT NULL);
      CREATE TABLE IF NOT EXISTS mail_refresh_tokens (hash TEXT PRIMARY KEY, client_id TEXT NOT NULL, scope TEXT NOT NULL, expires INTEGER NOT NULL);
      CREATE TABLE IF NOT EXISTS mail_login_attempts (key TEXT PRIMARY KEY, attempts INTEGER NOT NULL, reset_at INTEGER NOT NULL);
      CREATE TABLE IF NOT EXISTS mail_audit (id INTEGER PRIMARY KEY AUTOINCREMENT, action TEXT NOT NULL, account_id INTEGER, item_id TEXT, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);');
    @chmod(storage_path('mail.sqlite'), 0600);
    return $db;
}

function query(string $sql, array $values = []): PDOStatement
{
    $statement = mail_db()->prepare($sql);
    $statement->execute($values);
    return $statement;
}

function mail_setting(string $key, string $default = ''): string
{
    $value = query('SELECT value FROM mail_settings WHERE key=?', [$key])->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function save_mail_setting(string $key, string $value): void
{
    query('INSERT INTO mail_settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value', [$key, $value]);
}

function secret_file(string $name, int $bytes): string
{
    $path = storage_path($name);
    $handle = fopen($path, 'c+b');
    if (!$handle || !flock($handle, LOCK_EX)) throw new RuntimeException('Kon beveiligde opslag niet openen.');
    try {
        chmod($path, 0600);
        $value = stream_get_contents($handle);
        if ($value === '') {
            $value = base64_encode(random_bytes($bytes));
            rewind($handle);
            if (fwrite($handle, $value) !== strlen($value) || !fflush($handle)) throw new RuntimeException('Kon beveiligde opslag niet schrijven.');
            chmod($path, 0600);
        }
        return $value;
    } finally { flock($handle, LOCK_UN); fclose($handle); }
}

function mail_key(): string
{
    $key = base64_decode(getenv('APP_ENCRYPTION_KEY') ?: secret_file('mail.key', SODIUM_CRYPTO_SECRETBOX_KEYBYTES), true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) throw new RuntimeException('De encryptiesleutel is ongeldig.');
    return $key;
}

function seal(string $value): string
{
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return base64_encode($nonce . sodium_crypto_secretbox($value, $nonce, mail_key()));
}

function unseal(string $value): string
{
    $raw = base64_decode($value, true);
    if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) throw new RuntimeException('Opgeslagen wachtwoord is niet leesbaar.');
    $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), mail_key());
    if ($plain === false) throw new RuntimeException('Opgeslagen wachtwoord is niet leesbaar. Controleer de encryptiesleutel.');
    return $plain;
}

function audit_mail(string $action, ?int $accountId = null, ?string $itemId = null): void
{
    query('INSERT INTO mail_audit(action,account_id,item_id) VALUES(?,?,?)', [$action, $accountId, $itemId]);
}
