<?php

declare(strict_types=1);

/** Integratietest via HTTP. Vereist draaiende app (BASE) en nepservers (zie tests/run.sh). */
require __DIR__ . '/lib.php';

$base = rtrim(getenv('BASE') ?: 'http://localhost:8890', '/');

final class Browser
{
    public string $jar;

    public function __construct()
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'jar');
    }

    public function req(string $method, string $url, array $form = [], array $headers = [], ?string $raw = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($raw !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $raw);
        } elseif ($form) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
        }
        $out = (string) curl_exec($ch);
        $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $head = substr($out, 0, $size);
        return ['status' => $status, 'head' => $head, 'body' => substr($out, $size), 'location' => preg_match('/^Location: (.+)$/mi', $head, $m) ? trim($m[1]) : null];
    }

    /** Volgt redirects en geeft de eindpagina. */
    public function follow(string $method, string $url, array $form = []): array
    {
        $r = $this->req($method, $url, $form);
        for ($i = 0; $i < 5 && $r['location']; $i++) {
            $r = $this->req('GET', str_starts_with($r['location'], 'http') ? $r['location'] : preg_replace('#^(https?://[^/]+).*$#', '$1', $url) . $r['location']);
        }
        return $r;
    }

    public static function token(string $html): string
    {
        return preg_match('/name="_token" value="([^"]+)"/', $html, $m) ? $m[1] : '';
    }
}

function mcp(Browser $b, string $url, array $payload, array $headers = []): array
{
    $r = $b->req('POST', $url, [], array_merge(['Content-Type: application/json'], $headers), json_encode($payload));
    return ['status' => $r['status'], 'json' => json_decode($r['body'], true), 'raw' => $r['body']];
}

function tool(Browser $b, string $url, string $name, array $args = []): array
{
    $r = mcp($b, $url, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => $args]]);
    $text = $r['json']['result']['content'][0]['text'] ?? '';
    return ['error' => $r['json']['result']['isError'] ?? true, 'data' => json_decode($text, true), 'text' => $text];
}

$admin = new Browser();
$viewer = new Browser();
$agent = new Browser();

echo "Eerste keer: geen toegang en setup\n";
$r = $viewer->req('GET', $base . '/');
check($r['status'] === 200 && str_contains($r['body'], 'Open je sleutel-link'), 'zonder sleutel: geen toegang tot verslagen');
$r = $admin->req('GET', $base . '/login');
check($r['status'] === 302 && str_ends_with((string) $r['location'], '/setup'), 'login zonder account stuurt naar setup', (string) $r['location']);
$page = $admin->req('GET', $base . '/setup');
$token = Browser::token($page['body']);
$r = $admin->req('POST', $base . '/setup', ['_token' => $token, 'username' => 'mike', 'password' => 'kort', 'password_confirmation' => 'kort']);
check(str_contains($r['body'], 'minimaal 10 tekens'), 'te kort wachtwoord geweigerd');
$r = $admin->req('POST', $base . '/setup', ['_token' => $token, 'username' => 'mike', 'password' => 'sterk-wachtwoord-1', 'password_confirmation' => 'sterk-wachtwoord-1']);
check($r['status'] === 302 && str_ends_with((string) $r['location'], '/settings'), 'account aangemaakt en ingelogd');
$r = $viewer->req('GET', $base . '/setup');
check($r['status'] === 302 && str_ends_with((string) $r['location'], '/login'), 'setup is daarna dicht');

echo "\nSleutel-link en agent-token\n";
$settings = $admin->req('GET', $base . '/settings');
preg_match('/id="view-link" value="([^"]+)"/', $settings['body'], $m);
$viewLink = html_entity_decode($m[1] ?? '');
preg_match('/id="agent-token" value="([^"]+)"/', $settings['body'], $m);
$agentToken = $m[1] ?? '';
$viewKey = preg_match('/\?k=(\w+)/', $viewLink, $m) ? $m[1] : '';
check($viewKey !== '' && $agentToken !== '' && $viewKey !== $agentToken, 'sleutel-link en token zijn verschillend', $viewLink);
$r = $viewer->req('GET', $base . '/?k=verkeerd');
check($r['status'] === 200 && str_contains($r['body'], 'Open je sleutel-link'), 'verkeerde sleutel geeft geen toegang');
$r = $viewer->req('GET', $base . '/?k=' . $viewKey);
check($r['status'] === 302 && !str_contains((string) $r['location'], 'k='), 'juiste sleutel zet cookie en stuurt door naar schone URL', (string) $r['location']);
check(str_contains($r['head'], 'dagstart_key=') && stripos($r['head'], 'HttpOnly') !== false, 'cookie is HttpOnly');
$r = $viewer->req('GET', $base . '/');
check($r['status'] === 200 && str_contains($r['body'], 'Je eerste verslag komt eraan'), 'met sleutel: dashboard zichtbaar (nog leeg)');
$r = $viewer->req('GET', $base . '/settings');
check($r['status'] === 302 && str_ends_with((string) $r['location'], '/login'), 'sleutel geeft GEEN toegang tot instellingen');
$r = $viewer->req('POST', $base . '/login', ['_token' => 'x', 'username' => 'mike', 'password' => 'sterk-wachtwoord-1']);
check($r['status'] === 419, 'inloggen via sleutel/zonder csrf kan niet');

echo "\nMCP-beveiliging en protocol\n";
$mcpUrl = $base . '/mcp';
$r = mcp($agent, $mcpUrl, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
check($r['status'] === 401, 'MCP zonder token: 401');
$r = mcp($agent, $mcpUrl . '?token=' . $viewKey, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);
check($r['status'] === 401, 'dashboard-sleutel werkt niet als agent-token');
$r = $agent->req('GET', $mcpUrl . '?token=' . $agentToken);
check($r['status'] === 405, 'GET op /mcp: 405');
$mcpUrl .= '?token=' . $agentToken;
$r = mcp($agent, $mcpUrl, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => (object) [], 'clientInfo' => ['name' => 't', 'version' => '1']]]);
check(($r['json']['result']['protocolVersion'] ?? '') === '2025-06-18' && ($r['json']['result']['serverInfo']['name'] ?? '') === 'dagstart', 'initialize');
$r = $agent->req('POST', $mcpUrl, [], ['Content-Type: application/json'], json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']));
check($r['status'] === 202, 'notification: 202');
$r = mcp($agent, $base . '/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], ['Authorization: Bearer ' . $agentToken]);
$names = array_column($r['json']['result']['tools'] ?? [], 'name');
check(count($names) === 9 && in_array('mail_fetch', $names, true) && in_array('post_report_section', $names, true), 'tools/list met Bearer-header: 9 tools', implode(',', $names));
$r = mcp($agent, $mcpUrl, [['jsonrpc' => '2.0', 'id' => 'a', 'method' => 'ping'], ['jsonrpc' => '2.0', 'id' => 'b', 'method' => 'nope']]);
check(count($r['json'] ?? []) === 2 && ($r['json'][1]['error']['code'] ?? 0) === -32601, 'batch + onbekende methode');

echo "\nInstellingen via de app: mail, agenda, nieuws\n";
$post = function (string $path, array $data) use ($admin, $base): array {
    $t = Browser::token($admin->req('GET', $base . '/settings')['body']);
    return $admin->follow('POST', $base . $path, ['_token' => $t] + $data);
};
$mailBase = ['label' => 'x', 'protocol' => 'imap', 'host' => 'localhost', 'port' => '9993', 'security' => 'ssl', 'accept_all_certs' => '1', 'username' => 'me@fixture.test', 'password' => 'fixture-pass', 'folder' => 'INBOX', 'smtp_host' => 'smtp.fixture.test', 'smtp_port' => '587', 'smtp_security' => 'starttls', 'smtp_accept_all_certs' => '1', 'smtp_username' => 'me@fixture.test', 'smtp_password' => 'smtp-geheim'];
$r = $post('/settings/mail', ['label' => 'iCloud (test)'] + $mailBase);
check(str_contains($r['body'], 'Account toegevoegd') && str_contains($r['body'], 'iCloud (test)'), 'IMAP-account toevoegen');
$r = $post('/settings/mail', ['label' => 'Oude POP3', 'protocol' => 'pop3', 'port' => '9995'] + $mailBase);
check(str_contains($r['body'], 'Oude POP3') && str_contains($r['body'], 'certificaten niet gecontroleerd'), 'POP3-account toevoegen, alle-certs-badge zichtbaar');
$r = $post('/settings/mail', ['label' => 'x', 'host' => 'bad host!'] + $mailBase);
check(str_contains($r['body'], 'servernaam is ongeldig'), 'validatie servernaam');
$page = $admin->req('GET', $base . '/settings/mail');
check(!str_contains($page['body'], 'fixture-pass') && !str_contains($page['body'], 'smtp-geheim'), 'wachtwoorden worden nooit teruggetoond');
preg_match_all('#/settings/mail/(\d+)/test#', $page['body'], $ids);
$r = $post('/settings/mail/' . $ids[1][0] . '/test', []);
check(str_contains($r['body'], 'Verbonden. 5 berichten'), 'knop "Test" voor mail', strip_tags(substr($r['body'], strpos($r['body'], 'alert'), 300)));
$row = new PDO('sqlite:' . getenv('DAGSTART_DB'));
$enc = (string) $row->query('SELECT password_enc FROM mail_accounts LIMIT 1')->fetchColumn();
check($enc !== '' && !str_contains($enc, 'fixture-pass'), 'wachtwoord staat versleuteld in de database');

$r = $post('/settings/calendar', ['label' => 'iCloud agenda', 'server_url' => 'http://localhost:9880', 'username' => 'me@icloud.test', 'password' => 'app-pass', 'calendar_filter' => '']);
check(str_contains($r['body'], 'Agenda toegevoegd'), 'agenda toevoegen');
$page = $admin->req('GET', $base . '/settings/calendar');
preg_match('#/settings/calendar/(\d+)/test#', $page['body'], $cid);
$r = $post('/settings/calendar/' . $cid[1] . '/test', []);
check(str_contains(html_entity_decode($r['body'], ENT_QUOTES), "Agenda's: Thuis, Werk"), 'knop "Test" voor agenda');

$r = $post('/settings/news', ['name' => 'Fixture nieuws', 'url' => 'http://localhost:9880/feed.xml', 'notes' => 'alleen koppen']);
check(str_contains($r['body'], 'Bron toegevoegd') && str_contains($r['body'], 'Fixture nieuws'), 'nieuwsbron toevoegen');
$r = $post('/settings/news', ['name' => 'Fout', 'url' => 'javascript:alert(1)']);
check(str_contains($r['body'], 'geldige http(s)-URL'), 'javascript:-URL geweigerd');

echo "\nMCP-tools via HTTP\n";
$t = tool($agent, $mcpUrl, 'get_briefing_plan', ['slot' => 'morning']);
$plan = $t['data'];
check(!$t['error'] && $plan['slot'] === 'morning' && $plan['sections']['mail']['from'] !== '' && count($plan['mail_accounts']) === 2 && $plan['calendar_accounts'] === ['iCloud agenda'] && $plan['news_sources'][0]['name'] === 'Fixture nieuws', 'get_briefing_plan');
$from = new DateTimeImmutable($plan['sections']['mail']['from']);
check($from->format('H:i') === '21:00' && $from->format('Y-m-d') === (new DateTimeImmutable($plan['report_date']))->modify('-1 day')->format('Y-m-d'), 'ochtend: mailvenster begint gisteren 21:00', $plan['sections']['mail']['from']);
$ev = tool($agent, $mcpUrl, 'get_briefing_plan', ['slot' => 'evening'])['data'];
check((new DateTimeImmutable($ev['sections']['mail']['from']))->format('H:i') === '07:45' && (new DateTimeImmutable($ev['sections']['calendar']['from']))->format('Y-m-d') === (new DateTimeImmutable($ev['report_date']))->modify('+1 day')->format('Y-m-d'), 'avond: mail vanaf 07:45, agenda van morgen');

$since = (new DateTimeImmutable('now', new DateTimeZone('Europe/Amsterdam')))->modify('-24 hours')->format('c');
$t = tool($agent, $mcpUrl, 'mail_fetch', ['since' => $since]);
check(!$t['error'] && $t['data']['count'] === 8 && $t['data']['errors'] === [], 'mail_fetch: 2 accounts x 4 mails', $t['text']);
check(str_contains($t['text'], 'Jan de Vries') && str_contains($t['text'], 'offerte.pdf'), 'mail_fetch bevat afzender en bijlage');
$t = tool($agent, $mcpUrl, 'mail_fetch', ['since' => 'geen datum']);
check($t['error'] === true && str_contains($t['text'], 'Ongeldige datum'), 'mail_fetch: nette fout bij ongeldige datum');
$t = tool($agent, $mcpUrl, 'mail_list_folders', ['account' => 'iCloud (test)']);
check(in_array('Sent Messages', $t['data']['accounts'][0]['folders'] ?? [], true), 'mail_list_folders');

$today = (new DateTimeImmutable('today', new DateTimeZone('Europe/Amsterdam')));
$t = tool($agent, $mcpUrl, 'calendar_events', ['from' => $today->format('c'), 'to' => $today->modify('+1 day')->format('c')]);
check(!$t['error'] && $t['data']['count'] === 4, 'calendar_events: 4 afspraken vandaag', $t['text']);

$t = tool($agent, $mcpUrl, 'news_list_sources');
check(($t['data']['sources'][0]['notes'] ?? '') === 'alleen koppen', 'news_list_sources');
$t = tool($agent, $mcpUrl, 'fetch_url', ['url' => 'http://localhost:9880/feed.xml']);
check(!$t['error'] && $t['data']['kind'] === 'feed' && count($t['data']['items']) === 2 && $t['data']['items'][0]['title'] === 'Nieuwe & verse koppen' && $t['data']['items'][0]['summary'] === 'Het nieuws van vandaag.', 'fetch_url: RSS-feed met items', $t['text']);
$t = tool($agent, $mcpUrl, 'fetch_url', ['url' => 'http://localhost:9880/page.html']);
check($t['data']['kind'] === 'html' && $t['data']['title'] === 'Voorpagina' && str_contains($t['data']['text'], 'Kop van de dag') && !str_contains($t['data']['text'], 'evil') && count($t['data']['links']) === 1, 'fetch_url: HTML naar tekst + links');
$t = tool($agent, $mcpUrl, 'fetch_url', ['url' => 'http://localhost:9880/redirect']);
check(($t['data']['kind'] ?? '') === 'feed', 'fetch_url volgt redirects');
$t = tool($agent, $mcpUrl, 'fetch_url', ['url' => 'file:///etc/passwd']);
check($t['error'] === true, 'fetch_url weigert file://');

echo "\nVerslagen opslaan en tonen\n";
$date = $plan['report_date'];
$t = tool($agent, $mcpUrl, 'post_report_section', ['slot' => 'morning', 'kind' => 'mail', 'summary' => "Er kwamen **4 mails** binnen.\n\n- Jan de Vries wacht op reactie\n- Nieuwsbrief genegeerd <script>alert(1)</script>", 'items' => [
    ['title' => 'Jan de Vries — Offerte bekijken?', 'subtitle' => 'jan@bedrijf.test', 'time' => 'gisteren 21:14', 'badge' => 'wacht op reactie', 'tone' => 'warn', 'url' => 'https://example.com/m1'],
    ['title' => 'Kwaadaardig', 'url' => 'javascript:alert(1)', 'badge' => 'x', 'tone' => 'hacker'],
], 'period_from' => $plan['sections']['mail']['from'], 'period_to' => $plan['sections']['mail']['to']]);
check(!$t['error'] && $t['data']['ok'] === true, 'post_report_section (mail)', $t['text']);
$r = $agent->req('POST', $base . '/api/sections', [], ['Content-Type: application/json', 'Authorization: Bearer ' . $agentToken], json_encode(['slot' => 'morning', 'kind' => 'tasks', 'summary' => 'Vandaag 2 taken.', 'items' => [['title' => 'Belasting betalen', 'badge' => 'achterstallig', 'tone' => 'danger'], ['title' => 'Bellen met Marie', 'time' => '14:00']]]));
check($r['status'] === 200 && (json_decode($r['body'], true)['ok'] ?? false), 'POST /api/sections (Hubs ai kan direct rapporteren)', $r['body']);
$r = $agent->req('POST', $base . '/api/sections', [], ['Content-Type: application/json', 'Authorization: Bearer ' . $agentToken], json_encode(['slot' => 'morning', 'kind' => 'bestaatniet', 'summary' => 'x']));
check($r['status'] === 422, 'ongeldig onderwerp: 422');
$r = $agent->req('POST', $base . '/api/sections', [], ['Content-Type: application/json'], '{}');
check($r['status'] === 401, '/api/sections zonder token: 401');
tool($agent, $mcpUrl, 'post_report_section', ['slot' => 'evening', 'kind' => 'calendar', 'summary' => 'Morgen niets bijzonders.']);

$r = $viewer->req('GET', $base . '/');
$html = $r['body'];
check(str_contains($html, 'Ochtendverslag') || str_contains($html, 'Avondverslag'), 'dashboard toont een verslag');
$r = $viewer->req('GET', $base . '/rapport/' . $date . '/morning');
$html = $r['body'];
check($r['status'] === 200 && str_contains($html, 'Ochtendverslag'), 'ochtendverslag per URL');
check(str_contains($html, '<strong>4 mails</strong>') && str_contains($html, '<li>Jan de Vries wacht op reactie</li>'), 'samenvatting met vet en lijst');
check(!str_contains($html, '<script>alert(1)</script>') && str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'HTML in verslag wordt ge-escaped');
check(!str_contains($html, 'javascript:alert') && str_contains($html, 'href="https://example.com/m1"'), 'alleen http(s)-links');
check(str_contains($html, 'wacht op reactie') && str_contains($html, 'badge-warn') && str_contains($html, 'badge-danger') && str_contains($html, 'achterstallig'), 'badges met toon');
foreach (['Agenda', 'Taken', 'E-mail', 'Nieuws'] as $kind) {
    check(str_contains($html, '<h2>' . $kind . '</h2>'), 'venster "' . $kind . '"');
}
check(substr_count($html, 'Nog niet ontvangen') === 2, 'ontbrekende onderwerpen tonen "Nog niet ontvangen"');
$r = $viewer->req('GET', $base . '/rapport/' . $date . '/evening');
check($r['status'] === 200 && str_contains($r['body'], 'Avondverslag') && str_contains($r['body'], 'Morgen niets bijzonders'), 'avondverslag per URL');
$r = $viewer->req('GET', $base . '/rapport/2020-01-01/morning');
check($r['status'] === 404, 'onbekend verslag: 404');

echo "\nAfsluiten\n";
$r = $admin->req('GET', $base . '/manifest.json');
check($r['status'] === 200 && (json_decode($r['body'], true)['display'] ?? '') === 'standalone', 'manifest.json');
$r = $admin->req('GET', $base . '/sw.js');
check($r['status'] === 200 && str_contains($r['body'], 'dagstart-v1'), 'service worker');
$t = Browser::token($admin->req('GET', $base . '/settings')['body']);
$admin->req('POST', $base . '/settings/regenerate/view', ['_token' => $t]);
$r = $viewer->req('GET', $base . '/');
check(str_contains($r['body'], 'Open je sleutel-link'), 'nieuwe sleutel maakt de oude link ongeldig');

finish();
