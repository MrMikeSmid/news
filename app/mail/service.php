<?php

declare(strict_types=1);

require_once __DIR__ . '/mime.php';
require_once __DIR__ . '/imap.php';
require_once __DIR__ . '/pop3.php';

function mail_accounts(bool $activeOnly = true): array
{
    $sql = 'SELECT * FROM mail_accounts' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY label';
    return db()->query($sql)->fetchAll();
}

function mail_client_opts(array $account): array
{
    return [
        'host' => $account['host'],
        'port' => (int) $account['port'],
        'security' => $account['security'],
        'verify_cert' => (bool) $account['verify_cert'],
        'timeout' => 20,
    ];
}

/** @return ImapClient|Pop3Client */
function mail_connect(array $account): object
{
    $client = $account['protocol'] === 'pop3' ? new Pop3Client(mail_client_opts($account)) : new ImapClient(mail_client_opts($account));
    $client->connect();
    $client->login($account['username'], decrypt_secret($account['password_enc']));
    return $client;
}

function mail_select_accounts(?string $which): array
{
    $accounts = mail_accounts(true);
    if ($which === null || $which === '') {
        return $accounts;
    }
    $matched = array_values(array_filter($accounts, fn ($a) => (string) $a['id'] === $which || strcasecmp($a['label'], $which) === 0));
    if (!$matched) {
        throw new InvalidArgumentException('Onbekend of niet-actief mailaccount: ' . $which);
    }
    return $matched;
}

function mail_test_account(array $account): string
{
    $client = mail_connect($account);
    try {
        if ($client instanceof Pop3Client) {
            return 'Verbonden. ' . $client->count() . ' berichten in de mailbox.';
        }
        $count = $client->select($account['folder'] ?: 'INBOX');
        return 'Verbonden. ' . $count . ' berichten in "' . ($account['folder'] ?: 'INBOX') . '".';
    } finally {
        $client instanceof Pop3Client ? $client->quit() : $client->logout();
    }
}

function mail_list_folders(?string $accountSel): array
{
    $out = [];
    foreach (mail_select_accounts($accountSel) as $account) {
        if ($account['protocol'] === 'pop3') {
            $out[] = ['account' => $account['label'], 'folders' => ['INBOX']];
            continue;
        }
        $client = mail_connect($account);
        try {
            $out[] = ['account' => $account['label'], 'folders' => array_column($client->folders(), 'name')];
        } finally {
            $client->logout();
        }
    }
    return $out;
}

function mail_message_view(array $account, string $folder, array $raw, ?DateTimeImmutable $date, array $flags, int $uid, bool $withBody, int $maxChars): array
{
    $parsed = mime_parse((string) ($raw['raw'] ?? $raw[0] ?? ''));
    $h = $parsed['headers'];
    $body = mime_body_text($parsed);
    $truncated = false;
    if (mb_strlen($body) > $maxChars) {
        $body = mb_substr($body, 0, $maxChars);
        $truncated = true;
    }
    $lower = array_map('strtolower', $flags);
    $precedence = strtolower(mime_header($h, 'precedence'));
    $first = static fn (array $list): array => $list[0] ?? ['name' => '', 'address' => ''];

    $message = [
        'account' => $account['label'],
        'folder' => $folder,
        'uid' => $uid,
        'message_id' => mime_header($h, 'message-id'),
        'date' => $date ? $date->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('c') : null,
        'from' => $first(mime_addresses(mime_header($h, 'from'))),
        'to' => mime_addresses(mime_header($h, 'to')),
        'cc' => mime_addresses(mime_header($h, 'cc')),
        'reply_to' => mime_addresses(mime_header($h, 'reply-to')),
        'subject' => mime_decode_header(mime_header($h, 'subject')),
        'in_reply_to' => mime_header($h, 'in-reply-to'),
        'references' => mime_header($h, 'references'),
        'flags' => [
            'seen' => in_array('\\seen', $lower, true),
            'answered' => in_array('\\answered', $lower, true),
            'flagged' => in_array('\\flagged', $lower, true),
        ],
        'is_bulk' => mime_header($h, 'list-unsubscribe') !== '' || in_array($precedence, ['bulk', 'list', 'junk'], true) || stripos(mime_header($h, 'auto-submitted'), 'auto-') === 0,
        'attachments' => $parsed['attachments'],
    ];
    if ($withBody) {
        $message['body'] = $body;
        $message['body_truncated'] = $truncated;
    }
    return $message;
}

/**
 * Ontvangen mail in een tijdvenster. Per account worden fouten apart gemeld zodat één kapotte
 * mailbox de rest niet blokkeert.
 */
function mail_fetch(array $args): array
{
    $since = parse_dt((string) ($args['since'] ?? ''));
    $until = isset($args['until']) && $args['until'] !== '' ? parse_dt((string) $args['until']) : now_local();
    $limit = max(1, min(300, (int) ($args['limit'] ?? 100)));
    $withBody = !array_key_exists('include_body', $args) || (bool) $args['include_body'];
    $maxChars = max(200, min(20000, (int) ($args['max_body_chars'] ?? 4000)));
    $folderOverride = isset($args['folder']) && $args['folder'] !== '' ? (string) $args['folder'] : null;

    $messages = [];
    $errors = [];
    foreach (mail_select_accounts($args['account'] ?? null) as $account) {
        try {
            $messages = array_merge($messages, $account['protocol'] === 'pop3'
                ? mail_fetch_pop3($account, $since, $until, $limit, $withBody, $maxChars)
                : mail_fetch_imap($account, $folderOverride ?: ($account['folder'] ?: 'INBOX'), $since, $until, $limit, $withBody, $maxChars));
        } catch (Throwable $e) {
            $errors[] = ['account' => $account['label'], 'error' => $e->getMessage()];
        }
    }
    usort($messages, fn ($a, $b) => strcmp((string) $a['date'], (string) $b['date']));

    return [
        'since' => $since->format('c'),
        'until' => $until->format('c'),
        'count' => count($messages),
        'messages' => array_slice($messages, 0, $limit),
        'errors' => $errors,
    ];
}

function mail_fetch_imap(array $account, string $folder, DateTimeImmutable $since, DateTimeImmutable $until, int $limit, bool $withBody, int $maxChars): array
{
    $client = mail_connect($account);
    try {
        $client->select($folder);
        $uids = $client->searchSince($since);
        $overview = $uids ? $client->overview($uids) : [];
        $hits = array_filter($overview, fn ($m) => $m['date'] && $m['date'] >= $since && $m['date'] <= $until);
        uasort($hits, fn ($a, $b) => $a['date'] <=> $b['date']);
        $out = [];
        foreach (array_slice($hits, 0, $limit, true) as $uid => $meta) {
            $full = $client->fetchMessage((int) $uid);
            $out[] = mail_message_view($account, $folder, $full, $meta['date'], $meta['flags'], (int) $uid, $withBody, $maxChars);
        }
        return $out;
    } finally {
        $client->logout();
    }
}

function mail_fetch_pop3(array $account, DateTimeImmutable $since, DateTimeImmutable $until, int $limit, bool $withBody, int $maxChars): array
{
    $client = mail_connect($account);
    try {
        $total = $client->count();
        $sizes = $total > 0 ? $client->sizes() : [];
        $out = [];
        $olderStreak = 0;
        $scanned = 0;
        for ($n = $total; $n >= 1 && $scanned < 400; $n--, $scanned++) {
            $headers = mime_headers(mime_split($client->top($n, 0))[0]);
            try {
                $date = new DateTimeImmutable(mime_header($headers, 'date'));
            } catch (Throwable) {
                continue;
            }
            if ($date < $since) {
                if (++$olderStreak >= 8) {
                    break;
                }
                continue;
            }
            $olderStreak = 0;
            if ($date > $until) {
                continue;
            }
            $raw = ($sizes[$n] ?? 0) > 500000 ? $client->top($n, 400) : $client->retrieve($n);
            $out[] = mail_message_view($account, 'INBOX', [$raw], $date, [], $n, $withBody, $maxChars);
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    } finally {
        $client->quit();
    }
}
