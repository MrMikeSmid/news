<?php

declare(strict_types=1);

function account_get(int $id, bool $activeOnly = false): array
{
    $account = query('SELECT * FROM mail_accounts WHERE id=?', [$id])->fetch();
    if (!$account || ($activeOnly && !$account['active'])) throw new InvalidArgumentException('Mailaccount bestaat niet of is gepauzeerd.');
    return $account;
}
function public_account(array $account): array
{
    return array_intersect_key($account, array_flip(['id', 'label', 'email', 'from_name', 'active', 'incoming_protocol']));
}
function account_list(): array { return query('SELECT * FROM mail_accounts ORDER BY label,id')->fetchAll(); }
function mail_host(string $host): string
{
    $host = trim($host);
    if (strlen($host) > 253 || !preg_match('/^[a-zA-Z0-9](?:[a-zA-Z0-9.-]*[a-zA-Z0-9])?$/D', $host)) throw new InvalidArgumentException('Gebruik een geldige servernaam, zonder protocol of pad.');
    return $host;
}
function mailbox_name(string $folder): string
{
    if ($folder === '' || strlen($folder) > 200 || preg_match('/[\x00-\x1f\x7f{}]/', $folder)) throw new InvalidArgumentException('Ongeldige mapnaam.');
    return $folder;
}
function account_save(?int $id, array $data): int
{
    $old = $id ? account_get($id) : null;
    $required = ['label','email','smtp_host','smtp_user','imap_host','imap_user'];
    foreach ($required as $key) if (trim((string)($data[$key] ?? '')) === '') throw new InvalidArgumentException('Vul alle verplichte velden in.');
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $data['from_name'] ?? '')) throw new InvalidArgumentException('Gebruik een geldig afzenderadres en een naam zonder regeleinden.');
    $data['incoming_protocol']=$data['incoming_protocol']??$old['incoming_protocol']??'imap';
    if(!in_array($data['incoming_protocol'],['imap','pop3'],true))throw new InvalidArgumentException('Kies IMAP of POP3.');
    foreach(['smtp','imap'] as $protocol)$data[$protocol.'_verify_cert']=!empty($data[$protocol.'_verify_cert']??($old[$protocol.'_verify_cert']??true))?1:0;
    $data['smtp_host'] = mail_host($data['smtp_host']);
    $data['imap_host'] = mail_host($data['imap_host']);
    foreach (['smtp', 'imap'] as $protocol) {
        $port = filter_var($data[$protocol.'_port'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range'=>1,'max_range'=>65535]]);
        if (!$port) throw new InvalidArgumentException('Een poort moet tussen 1 en 65535 liggen.');
        $data[$protocol.'_port'] = $port;
        if (!in_array($data[$protocol.'_security'] ?? '', ['tls','ssl'], true)) throw new InvalidArgumentException('Kies STARTTLS of SSL/TLS.');
        $password = (string) ($data[$protocol.'_password'] ?? '');
        if ($password === '' && !$old) throw new InvalidArgumentException('Vul de wachtwoorden voor versturen en ontvangen in.');
        $data[$protocol.'_password'] = $password !== '' ? seal($password) : $old[$protocol.'_password'];
    }
    foreach (['sent_folder','trash_folder'] as $key) $data[$key] = mailbox_name($data[$key] ?? '');
    foreach (['label','from_name','smtp_user','imap_user'] as $key) {
        if (strlen($data[$key] ?? '') > 200 || preg_match('/[\x00-\x1f]/', $data[$key] ?? '')) throw new InvalidArgumentException('Een naam of gebruikersnaam is te lang of bevat ongeldige tekens.');
    }
    $keys = ['label','email','from_name','smtp_host','smtp_port','smtp_security','smtp_user','smtp_password','imap_host','imap_port','imap_security','imap_user','imap_password','sent_folder','trash_folder','active','incoming_protocol','smtp_verify_cert','imap_verify_cert'];
    $data['active'] = empty($data['active']) ? 0 : 1;
    $values = array_map(fn($key) => $data[$key], $keys);
    if ($id) query('UPDATE mail_accounts SET '.implode(',', array_map(fn($key)=>$key.'=?', $keys)).' WHERE id=?', [...$values, $id]);
    else { query('INSERT INTO mail_accounts('.implode(',', $keys).') VALUES('.implode(',', array_fill(0,count($keys),'?')).')', $values); $id=(int)mail_db()->lastInsertId(); }
    audit_mail('account_saved', $id);
    return $id;
}
function mail_requirements(): array
{
    $missing = [];
    foreach (['pdo_sqlite','sodium','mbstring','openssl'] as $extension) if (!extension_loaded($extension)) $missing[]=$extension;
    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) $missing[]='PHPMailer (composer install)';
    if (!extension_loaded('imap') && query("SELECT COUNT(*) FROM mail_accounts WHERE active=1 AND incoming_protocol='imap'")->fetchColumn()) $missing[]='imap (voor IMAP-accounts)';
    if (!class_exists(\ZBateson\MailMimeParser\Message::class)) $missing[]='MailMimeParser (composer install)';
    return $missing;
}
