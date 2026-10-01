<?php

declare(strict_types=1);

require_once __DIR__ . '/ical.php';

class CalDavClient
{
    public function __construct(private string $serverUrl, private string $user, private string $password, private bool $verify = true)
    {
    }

    /** @return array{status:int,body:string,url:string} */
    private function request(string $method, string $url, string $body = '', array $headers = []): array
    {
        for ($hop = 0; $hop < 6; $hop++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERPWD => $this->user . ':' . $this->password,
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => $this->verify,
                CURLOPT_SSL_VERIFYHOST => $this->verify ? 2 : 0,
                CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/xml; charset=utf-8', 'User-Agent: Dagstart/1.0'], $headers),
                CURLOPT_POSTFIELDS => $body,
            ]);
            $response = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $location = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);

            if ($response === false) {
                throw new RuntimeException('Agenda-server niet bereikbaar: ' . $error . (stripos($error, 'certificate') !== false ? ' (zet "Alle certificaten accepteren" aan bij een zelfondertekend certificaat)' : ''));
            }
            if (in_array($status, [301, 302, 307, 308], true) && $location) {
                $url = $location;
                continue;
            }
            if ($status === 401) {
                throw new RuntimeException('Inloggen op de agenda mislukt (controleer gebruikersnaam en app-specifiek wachtwoord).');
            }
            return ['status' => $status, 'body' => (string) $response, 'url' => $url];
        }
        throw new RuntimeException('Te veel doorverwijzingen van de agenda-server.');
    }

    private function resolve(string $base, string $href): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $parts = parse_url($base);
        $root = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (str_starts_with($href, '/')) {
            return $root . $href;
        }
        return $root . rtrim(dirname($parts['path'] ?? '/'), '/') . '/' . $href;
    }

    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$ok) {
            throw new RuntimeException('Ongeldig antwoord van de agenda-server.');
        }
        return new DOMXPath($dom);
    }

    private function firstHref(string $xml, string $container): ?string
    {
        $x = $this->xpath($xml);
        $n = $x->query('//*[local-name()="' . $container . '"]/*[local-name()="href"]');
        return $n && $n->length ? trim((string) $n->item(0)->textContent) : null;
    }

    private function propfind(string $url, string $props, int $depth): array
    {
        return $this->request('PROPFIND', $url, '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav"><d:prop>' . $props . '</d:prop></d:propfind>', ['Depth: ' . $depth]);
    }

    /** @return array<int,array{name:string,url:string}> */
    public function calendars(): array
    {
        $base = rtrim($this->serverUrl, '/');
        $r = $this->propfind($base . '/.well-known/caldav', '<d:current-user-principal/>', 0);
        if ($r['status'] >= 400) {
            $r = $this->propfind($base . '/', '<d:current-user-principal/>', 0);
        }
        $principalHref = $r['status'] < 400 ? $this->firstHref($r['body'], 'current-user-principal') : null;
        if (!$principalHref) {
            throw new RuntimeException('Kon de agenda-account niet vinden (HTTP ' . $r['status'] . ').');
        }
        $principal = $this->resolve($r['url'], $principalHref);

        $r = $this->propfind($principal, '<c:calendar-home-set/>', 0);
        $homeHref = $this->firstHref($r['body'], 'calendar-home-set');
        if (!$homeHref) {
            throw new RuntimeException('Geen agenda-map gevonden voor dit account.');
        }
        $home = $this->resolve($principal, $homeHref);

        $r = $this->propfind($home, '<d:displayname/><d:resourcetype/><c:supported-calendar-component-set/>', 1);
        $x = $this->xpath($r['body']);
        $out = [];
        foreach ($x->query('//*[local-name()="response"]') ?: [] as $resp) {
            if (!$x->query('.//*[local-name()="resourcetype"]/*[local-name()="calendar"]', $resp)->length) {
                continue;
            }
            $comps = $x->query('.//*[local-name()="supported-calendar-component-set"]/*[local-name()="comp"]', $resp);
            if ($comps->length) {
                $names = [];
                foreach ($comps as $c) {
                    $names[] = $c->getAttribute('name');
                }
                if (!in_array('VEVENT', $names, true)) {
                    continue;
                }
            }
            $href = (string) $x->evaluate('string(.//*[local-name()="href"][1])', $resp);
            $name = trim((string) $x->evaluate('string(.//*[local-name()="displayname"][1])', $resp));
            $out[] = ['name' => $name !== '' ? $name : basename(rtrim($href, '/')), 'url' => $this->resolve($home, $href)];
        }
        return $out;
    }

    /** @return array<int,array> afspraken binnen [from, to), gesorteerd */
    public function events(DateTimeImmutable $from, DateTimeImmutable $to, ?array $nameFilter = null): array
    {
        $local = $from->getTimezone();
        $fmt = static fn (DateTimeImmutable $d): string => $d->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $body = '<?xml version="1.0" encoding="utf-8"?><c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav"><d:prop><c:calendar-data><c:expand start="' . $fmt($from) . '" end="' . $fmt($to) . '"/></c:calendar-data></d:prop>'
            . '<c:filter><c:comp-filter name="VCALENDAR"><c:comp-filter name="VEVENT"><c:time-range start="' . $fmt($from) . '" end="' . $fmt($to) . '"/></c:comp-filter></c:comp-filter></c:filter></c:calendar-query>';

        $events = [];
        $seen = [];
        foreach ($this->calendars() as $calendar) {
            if ($nameFilter && !in_array(mb_strtolower($calendar['name']), $nameFilter, true)) {
                continue;
            }
            $r = $this->request('REPORT', $calendar['url'], $body, ['Depth: 1']);
            if ($r['status'] >= 400) {
                throw new RuntimeException('Agenda "' . $calendar['name'] . '" lezen mislukt (HTTP ' . $r['status'] . ').');
            }
            $x = $this->xpath($r['body']);
            foreach ($x->query('//*[local-name()="calendar-data"]') ?: [] as $node) {
                foreach (ical_events((string) $node->textContent) as $raw) {
                    foreach (ical_occurrences($raw, $from, $to, $local) as $occ) {
                        $view = ical_event_view($raw, $occ, $calendar['name']);
                        $key = $view['uid'] . '|' . $view['start'];
                        if (isset($seen[$key]) || $view['status'] === 'cancelled') {
                            continue;
                        }
                        $seen[$key] = true;
                        $events[] = $view;
                    }
                }
            }
        }
        usort($events, fn ($a, $b) => [!$a['all_day'], $a['start']] <=> [!$b['all_day'], $b['start']]);
        return $events;
    }
}
