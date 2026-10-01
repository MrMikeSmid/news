<?php

declare(strict_types=1);

require __DIR__ . '/lib.php';
test_boot();
require_once dirname(__DIR__) . '/app/mail/service.php';

function add_account(string $label, string $protocol, int $port, string $security, int $verify, string $pass = 'fixture-pass'): void
{
    db()->prepare('INSERT INTO mail_accounts (label, protocol, host, port, security, verify_cert, username, password_enc, folder)
        VALUES (:l, :p, :h, :port, :s, :v, :u, :pw, :f)')->execute([
        'l' => $label, 'p' => $protocol, 'h' => 'localhost', 'port' => $port, 's' => $security, 'v' => $verify,
        'u' => 'me@fixture.test', 'pw' => encrypt_secret($pass), 'f' => 'INBOX',
    ]);
}

echo "Versleuteling\n";
check(decrypt_secret(encrypt_secret('geheim €')) === 'geheim €', 'secret versleutelen en terugdraaien');

add_account('imap-ssl', 'imap', 9993, 'ssl', 0);
add_account('imap-starttls', 'imap', 9143, 'starttls', 0);
add_account('pop3-ssl', 'pop3', 9995, 'ssl', 0);
add_account('pop3-stls', 'pop3', 9110, 'starttls', 0);

echo "\nVerbindingstest per account\n";
foreach (mail_accounts() as $a) {
    try {
        $msg = mail_test_account($a);
        check(str_contains($msg, 'Verbonden') && str_contains($msg, '5 berichten'), $a['label'] . ': ' . $msg);
    } catch (Throwable $e) {
        check(false, $a['label'], $e->getMessage());
    }
}

echo "\nCertificaatcontrole\n";
add_account('strict', 'imap', 9993, 'ssl', 1);
$strict = array_values(array_filter(mail_accounts(), fn ($a) => $a['label'] === 'strict'))[0];
try {
    mail_test_account($strict);
    check(false, 'zelfondertekend cert wordt geweigerd bij verify_cert=1');
} catch (Throwable $e) {
    check(true, 'zelfondertekend cert wordt geweigerd bij verify_cert=1', $e->getMessage());
    check(str_contains($e->getMessage(), 'Alle certificaten accepteren'), 'foutmelding wijst naar de instelling');
}

echo "\nFout wachtwoord\n";
add_account('fout', 'imap', 9993, 'ssl', 0, 'verkeerd');
$bad = array_values(array_filter(mail_accounts(), fn ($a) => $a['label'] === 'fout'))[0];
try {
    mail_test_account($bad);
    check(false, 'fout wachtwoord geeft fout');
} catch (Throwable $e) {
    check(str_contains($e->getMessage(), 'Inloggen mislukt'), 'fout wachtwoord geeft duidelijke fout', $e->getMessage());
}
db()->exec("DELETE FROM mail_accounts WHERE label IN ('strict','fout')");

echo "\nMail ophalen (laatste 24 uur)\n";
$since = now_local()->modify('-24 hours')->format('c');
foreach (['imap-ssl', 'imap-starttls', 'pop3-ssl', 'pop3-stls'] as $label) {
    $r = mail_fetch(['since' => $since, 'account' => $label]);
    $subjects = array_column($r['messages'], 'subject');
    check($r['errors'] === [], $label . ': geen fouten', json_encode($r['errors']));
    check(count($r['messages']) === 4, $label . ': 4 berichten in venster (oude mail eruit)', (string) count($r['messages']));
    check(!in_array('Oude mail', $subjects, true), $label . ': oude mail genegeerd');
    check(in_array("Caf\u{e9} afspraak", $subjects, true), $label . ': iso-8859-15 onderwerp/inhoud', implode('|', $subjects));
    $offer = array_values(array_filter($r['messages'], fn ($m) => str_starts_with($m['subject'], 'Offerte')))[0] ?? null;
    check($offer && $offer['from']['name'] === 'Jan de Vries' && $offer['from']['address'] === 'jan@bedrijf.test', $label . ': afzender naam + adres');
    check($offer && $offer['attachments'] === ['offerte.pdf'], $label . ': bijlage herkend');
    check($offer && str_contains($offer['body'], 'offerte bekijken'), $label . ': tekst gelezen');
    $news = array_values(array_filter($r['messages'], fn ($m) => str_starts_with($m['subject'], 'Nieuwsbrief')))[0] ?? null;
    check($news && $news['is_bulk'] === true && str_contains($news['body'], 'Alles 30% korting nu') && !str_contains($news['body'], 'x()'), $label . ': html naar tekst + bulk-vlag');
    if (str_starts_with($label, 'imap')) {
        $re = array_values(array_filter($r['messages'], fn ($m) => str_starts_with($m['subject'], 'Re:')))[0] ?? null;
        check($re && $re['flags']['answered'] && $re['flags']['seen'], $label . ': vlaggen (beantwoord/gelezen)');
        check($offer && !$offer['flags']['seen'], $label . ': ongelezen vlag');
    }
    check(strcmp($r['messages'][0]['date'], $r['messages'][3]['date']) < 0, $label . ': oplopend op datum');
}

echo "\nVenster met until + lijst met mappen\n";
$r = mail_fetch(['since' => now_local()->modify('-12 hours')->format('c'), 'until' => now_local()->modify('-3 hours')->format('c'), 'account' => 'imap-ssl']);
check(count($r['messages']) === 2, 'venster -12u..-3u geeft 2 berichten', (string) count($r['messages']));
$f = mail_list_folders('imap-ssl');
check($f[0]['folders'] === ['INBOX', 'Sent Messages'], 'mappen', json_encode($f));
$all = mail_fetch(['since' => $since, 'include_body' => false]);
check(count($all['messages']) === 16 && !isset($all['messages'][0]['body']), 'alle accounts samen, zonder body', (string) count($all['messages']));

finish();
