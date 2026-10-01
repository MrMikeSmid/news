<?php

declare(strict_types=1);

require __DIR__ . '/lib.php';
test_boot();
require_once dirname(__DIR__) . '/app/calendar/service.php';

function add_cal(string $label, string $url, int $verify, string $pass = 'app-pass', string $filter = ''): void
{
    db()->prepare('INSERT INTO calendar_accounts (label, server_url, verify_cert, username, password_enc, calendar_filter) VALUES (:l, :u, :v, :user, :p, :f)')
        ->execute(['l' => $label, 'u' => $url, 'v' => $verify, 'user' => 'me@icloud.test', 'p' => encrypt_secret($pass), 'f' => $filter]);
}

add_cal('http', 'http://localhost:9880', 1);
add_cal('tls-all-certs', 'https://localhost:9881', 0);

echo "Verbinding en ontdekken van agenda's\n";
foreach (calendar_accounts() as $a) {
    try {
        $m = calendar_test_account($a);
        check(str_contains($m, 'Thuis') && str_contains($m, 'Werk') && !str_contains($m, 'Herinneringen'), $a['label'] . ': ' . $m);
    } catch (Throwable $e) {
        check(false, $a['label'], $e->getMessage());
    }
}

echo "\nCertificaat en wachtwoord\n";
add_cal('strict', 'https://localhost:9881', 1);
$strict = array_values(array_filter(calendar_accounts(), fn ($a) => $a['label'] === 'strict'))[0];
try {
    calendar_test_account($strict);
    check(false, 'zelfondertekend cert geweigerd');
} catch (Throwable $e) {
    check(str_contains($e->getMessage(), 'Alle certificaten accepteren'), 'zelfondertekend cert geweigerd met hint', $e->getMessage());
}
add_cal('fout', 'http://localhost:9880', 1, 'verkeerd');
$bad = array_values(array_filter(calendar_accounts(), fn ($a) => $a['label'] === 'fout'))[0];
try {
    calendar_test_account($bad);
    check(false, 'fout wachtwoord geeft fout');
} catch (Throwable $e) {
    check(str_contains($e->getMessage(), 'Inloggen op de agenda mislukt'), 'fout wachtwoord', $e->getMessage());
}
db()->exec("DELETE FROM calendar_accounts WHERE label IN ('strict','fout','tls-all-certs')");

echo "\nAfspraken van vandaag\n";
$today = now_local()->setTime(0, 0);
$r = calendar_events(['from' => $today->format('c'), 'to' => $today->modify('+1 day')->format('c')]);
check($r['errors'] === [], 'geen fouten', json_encode($r['errors']));
$titles = array_column($r['events'], 'title');
check($titles[0] === 'Verjaardag Opa' && count($titles) === 4 && $titles[3] === 'Sprintreview', 'vier afspraken (hele dag eerst, daarna op tijd)', implode(' | ', $titles));
$byTitle = array_column($r['events'], null, 'title');
check(($byTitle['Verjaardag Opa']['all_day'] ?? false) === true, 'hele dag herkend');
check(str_contains($byTitle['Tandarts']['start'] ?? '', 'T09:30:00') && ($byTitle['Tandarts']['location'] ?? '') === 'Praktijk Zuid', 'TZID-tijd 09:30 + locatie');
check(str_contains($byTitle['Dagelijkse standup']['start'] ?? '', 'T08:00:00'), 'herhalende afspraak vandaag om 08:00');
$sprintHour = (int) substr($byTitle['Sprintreview']['start'] ?? '', 11, 2);
check(in_array($sprintHour, [15, 16], true) && ($byTitle['Sprintreview']['description'] ?? '') === 'Demo voor het team, met taart' && ($byTitle['Sprintreview']['calendar'] ?? '') === 'Werk', 'UTC omgerekend naar Amsterdam (' . $sprintHour . ':00), beschrijving ge-unescaped');

echo "\nHerhaling, uitzondering en filter\n";
$r = calendar_events(['from' => $today->modify('-12 days')->format('c'), 'to' => $today->modify('+1 day')->format('c'), 'calendar' => 'Thuis']);
$stand = array_values(array_filter($r['events'], fn ($e) => $e['title'] === 'Dagelijkse standup'));
check(count($stand) === 10, '11 dagen sinds de start, 1 EXDATE eruit = 10', (string) count($stand));
check(!array_filter($stand, fn ($e) => str_starts_with($e['start'], $today->modify('-9 days')->format('Y-m-d'))), 'EXDATE-dag ontbreekt');
check(!array_filter($r['events'], fn ($e) => $e['calendar'] === 'Werk'), 'calendar-filter werkt');
$list = calendar_list();
check($list[0]['calendars'] === ['Thuis', 'Werk'], "agenda's lijst", json_encode($list));

$tomorrow = calendar_events(['from' => $today->modify('+1 day')->format('c'), 'to' => $today->modify('+2 days')->format('c')]);
check(array_column($tomorrow['events'], 'title') === ['Dagelijkse standup'], 'morgen: alleen de herhaling', json_encode(array_column($tomorrow['events'], 'title')));

finish();
