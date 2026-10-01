<?php

declare(strict_types=1);

require_once __DIR__ . '/reports.php';
require_once __DIR__ . '/news.php';
require_once __DIR__ . '/mail/service.php';
require_once __DIR__ . '/calendar/service.php';
require_once __DIR__ . '/controllers/ApiController.php';

const MCP_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

function mcp_tools(): array
{
    $str = static fn (string $d): array => ['type' => 'string', 'description' => $d];
    $slot = ['type' => 'string', 'enum' => ['morning', 'evening'], 'description' => 'morning = ochtendverslag (07:45), evening = avondverslag (21:00).'];
    $iso = static fn (string $d): array => ['type' => 'string', 'description' => $d . ' ISO 8601, bij voorkeur met tijdzone (bijv. 2026-10-01T07:45:00+02:00).'];
    $schema = static fn (array $props, array $required = []): array => ['type' => 'object', 'properties' => (object) $props, 'required' => $required];

    return [
        ['name' => 'get_briefing_plan', 'description' => 'Geeft per verslagmoment (ochtend 07:45 / avond 21:00) welke tijdvensters je voor mail, nieuws, agenda en taken moet gebruiken, plus de ingestelde bronnen/accounts en wat er al is opgeslagen. Roep dit eerst aan.',
            'inputSchema' => $schema(['slot' => $slot])],
        ['name' => 'mail_list_accounts', 'description' => 'Lijst van gekoppelde (actieve) mailaccounts.', 'inputSchema' => $schema([])],
        ['name' => 'mail_list_folders', 'description' => 'Mappen van IMAP-accounts (bijv. voor de map met verzonden mail).', 'inputSchema' => $schema(['account' => $str('Naam of id van het account; leeg = alle.')])],
        ['name' => 'mail_fetch', 'description' => 'Leest (alleen lezen) ontvangen mail in een tijdvenster, over alle actieve accounts. Geeft per mail afzender, ontvangers, datum, onderwerp, volledige tekst, bijlagen, vlaggen (gelezen/beantwoord) en of het bulk/nieuwsbrief is. Gebruik in_reply_to/answered om te bepalen of iemand op een reactie wacht.',
            'inputSchema' => $schema([
                'since' => $iso('Begin van het venster.'),
                'until' => $iso('Einde van het venster (standaard nu).'),
                'account' => $str('Naam of id van één account; leeg = alle.'),
                'folder' => $str('Andere map dan de ingestelde, bijv. "Sent Messages".'),
                'limit' => ['type' => 'integer', 'description' => 'Maximaal aantal mails (standaard 100, max 300).'],
                'include_body' => ['type' => 'boolean', 'description' => 'Tekst meesturen (standaard true).'],
                'max_body_chars' => ['type' => 'integer', 'description' => 'Maximale lengte per mailtekst (standaard 4000).'],
            ], ['since'])],
        ['name' => 'calendar_list_calendars', 'description' => "Lijst van agenda's per gekoppeld agenda-account.", 'inputSchema' => $schema(['account' => $str('Naam of id; leeg = alle.')])],
        ['name' => 'calendar_events', 'description' => 'Agenda-afspraken in een tijdvenster (herhalingen zijn uitgeklapt, tijden in Europe/Amsterdam).',
            'inputSchema' => $schema([
                'from' => $iso('Begin van het venster.'),
                'to' => $iso('Einde van het venster (exclusief).'),
                'account' => $str('Naam of id; leeg = alle.'),
                'calendar' => $str('Naam van één agenda; leeg = alle (of het ingestelde filter).'),
            ], ['from', 'to'])],
        ['name' => 'news_list_sources', 'description' => 'De nieuwsbronnen (naam, URL, notities) waaruit je nieuws moet halen. Zoek zelf naar items die in het venster zijn gepubliceerd.', 'inputSchema' => $schema([])],
        ['name' => 'fetch_url', 'description' => 'Haalt een webpagina of RSS/Atom-feed op. Feed: lijst items met titel, url, publicatietijd en samenvatting. Pagina: tekst en de belangrijkste links.',
            'inputSchema' => $schema(['url' => $str('http(s)-URL.'), 'max_chars' => ['type' => 'integer', 'description' => 'Maximale tekstlengte (standaard 8000).']], ['url'])],
        ['name' => 'post_report_section', 'description' => 'Slaat één onderwerp van een verslag op in het dashboard (vervangt het vorige voor dezelfde datum+slot+kind). Schrijf summary in gewone Nederlandse taal; **vet** en regels met "- " mogen. items zijn optionele rijen onder de samenvatting.',
            'inputSchema' => $schema([
                'slot' => $slot,
                'kind' => ['type' => 'string', 'enum' => ['calendar', 'tasks', 'mail', 'news'], 'description' => 'Onderwerp.'],
                'date' => $str('YYYY-MM-DD van het verslag (standaard vandaag).'),
                'summary' => $str('Verslagtekst in menselijke taal.'),
                'items' => ['type' => 'array', 'description' => 'Optionele rijen.', 'items' => ['type' => 'object', 'properties' => (object) [
                    'title' => $str('Hoofdregel (bij mail: afzender + onderwerp).'),
                    'subtitle' => $str('Tweede regel (bij mail: van wie / bij agenda: locatie).'),
                    'time' => $str('Korte tijdsaanduiding, bijv. "09:30" of "gisteren 21:14".'),
                    'text' => $str('Korte toelichting.'),
                    'url' => $str('Link (http/https).'),
                    'badge' => $str('Label, bijv. "wacht op reactie".'),
                    'tone' => ['type' => 'string', 'enum' => ['success', 'warn', 'danger', 'accent', 'neutral']],
                ]]],
                'period_from' => $iso('Begin van de verwerkte periode.'),
                'period_to' => $iso('Einde van de verwerkte periode.'),
            ], ['slot', 'kind', 'summary'])],
    ];
}

function mcp_call_tool(string $name, array $args): array
{
    switch ($name) {
        case 'get_briefing_plan':
            $slot = in_array($args['slot'] ?? '', ['morning', 'evening'], true) ? $args['slot'] : detect_slot();
            $plan = briefing_plan($slot);
            $report = find_report($plan['report_date'], $slot);
            return $plan + [
                'mail_accounts' => array_map(fn ($a) => ['label' => $a['label'], 'protocol' => $a['protocol']], mail_accounts()),
                'calendar_accounts' => array_column(calendar_accounts(), 'label'),
                'news_sources' => news_sources(),
                'already_posted' => $report ? array_keys(report_sections((int) $report['id'])) : [],
                'dashboard' => absolute_url('rapport/' . $plan['report_date'] . '/' . $slot),
            ];
        case 'mail_list_accounts':
            return ['accounts' => array_map(fn ($a) => ['id' => (int) $a['id'], 'label' => $a['label'], 'protocol' => $a['protocol'], 'username' => $a['username'], 'folder' => $a['folder']], mail_accounts())];
        case 'mail_list_folders':
            return ['accounts' => mail_list_folders($args['account'] ?? null)];
        case 'mail_fetch':
            return mail_fetch($args);
        case 'calendar_list_calendars':
            return ['accounts' => calendar_list($args['account'] ?? null)];
        case 'calendar_events':
            return calendar_events($args);
        case 'news_list_sources':
            return ['sources' => news_sources()];
        case 'fetch_url':
            return fetch_url((string) ($args['url'] ?? ''), (int) ($args['max_chars'] ?? 8000));
        case 'post_report_section':
            return ['ok' => true] + save_section_from_input($args);
    }
    throw new BadMethodCallException('Onbekende tool: ' . $name);
}

function mcp_rpc(array $message): ?array
{
    $id = $message['id'] ?? null;
    $method = (string) ($message['method'] ?? '');
    $isNotification = !array_key_exists('id', $message);
    $reply = static fn (array $payload): array => ['jsonrpc' => '2.0', 'id' => $id] + $payload;

    if ($isNotification) {
        return null;
    }

    switch ($method) {
        case 'initialize':
            $requested = (string) ($message['params']['protocolVersion'] ?? '');
            return $reply(['result' => [
                'protocolVersion' => in_array($requested, MCP_VERSIONS, true) ? $requested : MCP_VERSIONS[0],
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'dagstart', 'version' => '1.0.0'],
                'instructions' => 'Dagstart levert data voor twee dagelijkse verslagen (ochtend 07:45, avond 21:00). Begin met get_briefing_plan, haal mail/agenda/nieuws op voor de genoemde vensters en sla elk onderwerp op met post_report_section.',
            ]]);
        case 'ping':
            return $reply(['result' => (object) []]);
        case 'tools/list':
            return $reply(['result' => ['tools' => mcp_tools()]]);
        case 'tools/call':
            $name = (string) ($message['params']['name'] ?? '');
            $args = $message['params']['arguments'] ?? [];
            if (!in_array($name, array_column(mcp_tools(), 'name'), true)) {
                return $reply(['error' => ['code' => -32602, 'message' => 'Onbekende tool: ' . $name]]);
            }
            try {
                $data = mcp_call_tool($name, is_array($args) ? $args : []);
                return $reply(['result' => ['content' => [['type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)]], 'isError' => false]]);
            } catch (Throwable $e) {
                return $reply(['result' => ['content' => [['type' => 'text', 'text' => 'Fout: ' . $e->getMessage()]], 'isError' => true]]);
            }
        case 'resources/list':
            return $reply(['result' => ['resources' => []]]);
        case 'prompts/list':
            return $reply(['result' => ['prompts' => []]]);
    }
    return $reply(['error' => ['code' => -32601, 'message' => 'Methode niet ondersteund: ' . $method]]);
}

function mcp_handle(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!agent_authorized()) {
        header('WWW-Authenticate: Bearer realm="dagstart"');
        json_out(['error' => 'Ongeldige of ontbrekende token.'], 401);
    }
    if ($method !== 'POST') {
        header('Allow: POST');
        json_out(['error' => 'Gebruik POST (JSON-RPC).'], 405);
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        json_out(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Ongeldige JSON.']], 400);
    }

    $isBatch = $input !== [] && array_keys($input) === range(0, count($input) - 1);
    $responses = [];
    foreach ($isBatch ? $input : [$input] as $message) {
        if (is_array($message)) {
            $r = mcp_rpc($message);
            if ($r !== null) {
                $responses[] = $r;
            }
        }
    }

    if (!$responses) {
        http_response_code(202);
        exit;
    }
    json_out($isBatch ? $responses : $responses[0]);
}
