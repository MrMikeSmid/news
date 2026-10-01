<?php

declare(strict_types=1);

require_once __DIR__ . '/caldav.php';

function calendar_accounts(bool $activeOnly = true): array
{
    return db()->query('SELECT * FROM calendar_accounts' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY label')->fetchAll();
}

function calendar_client(array $account): CalDavClient
{
    return new CalDavClient($account['server_url'], $account['username'], decrypt_secret($account['password_enc']), (bool) $account['verify_cert']);
}

function calendar_test_account(array $account): string
{
    $names = array_column(calendar_client($account)->calendars(), 'name');
    return 'Verbonden. Agenda\'s: ' . ($names ? implode(', ', $names) : '(geen gevonden)') . '.';
}

function calendar_list(?string $accountSel = null): array
{
    $out = [];
    foreach (calendar_select($accountSel) as $account) {
        try {
            $out[] = ['account' => $account['label'], 'calendars' => array_column(calendar_client($account)->calendars(), 'name')];
        } catch (Throwable $e) {
            $out[] = ['account' => $account['label'], 'error' => $e->getMessage()];
        }
    }
    return $out;
}

function calendar_select(?string $which): array
{
    $accounts = calendar_accounts(true);
    if ($which === null || $which === '') {
        return $accounts;
    }
    $matched = array_values(array_filter($accounts, fn ($a) => (string) $a['id'] === $which || strcasecmp($a['label'], $which) === 0));
    if (!$matched) {
        throw new InvalidArgumentException('Onbekend of niet-actief agenda-account: ' . $which);
    }
    return $matched;
}

function calendar_events(array $args): array
{
    $from = parse_dt((string) ($args['from'] ?? ''));
    $to = parse_dt((string) ($args['to'] ?? ''));
    if ($to <= $from) {
        throw new InvalidArgumentException('"to" moet na "from" liggen.');
    }
    $only = isset($args['calendar']) && $args['calendar'] !== '' ? [mb_strtolower((string) $args['calendar'])] : null;

    $events = [];
    $errors = [];
    foreach (calendar_select($args['account'] ?? null) as $account) {
        $filter = $only ?? (trim((string) $account['calendar_filter']) !== ''
            ? array_map(fn ($n) => mb_strtolower(trim($n)), explode(',', (string) $account['calendar_filter'])) : null);
        try {
            foreach (calendar_client($account)->events($from, $to, $filter) as $event) {
                $events[] = ['account' => $account['label']] + $event;
            }
        } catch (Throwable $e) {
            $errors[] = ['account' => $account['label'], 'error' => $e->getMessage()];
        }
    }
    usort($events, fn ($a, $b) => [!$a['all_day'], $a['start']] <=> [!$b['all_day'], $b['start']]);
    return ['from' => $from->format('c'), 'to' => $to->format('c'), 'count' => count($events), 'events' => $events, 'errors' => $errors];
}
