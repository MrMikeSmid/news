<?php

declare(strict_types=1);

require_once __DIR__ . '/mail/mime.php';

function news_sources(bool $activeOnly = true): array
{
    $rows = db()->query('SELECT id, name, url, notes, is_active FROM news_sources' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY name')->fetchAll();
    return array_map(fn ($r) => ['name' => $r['name'], 'url' => $r['url'], 'notes' => $r['notes'] ?? ''], $rows);
}

/** Resolveert een host en weigert interne adressen (tegen SSRF); geeft de gekozen IP terug. */
function fetch_resolve_public(string $host): string
{
    $host = trim($host, '[]');
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips = [$host];
    } else {
        $ips = gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $rec) {
            $ips[] = $rec['ipv6'];
        }
    }
    if (!$ips) {
        throw new RuntimeException('Kon de host niet opzoeken: ' . $host);
    }
    $allowPrivate = getenv('DAGSTART_ALLOW_PRIVATE_FETCH') === '1';
    foreach ($ips as $ip) {
        if (!$allowPrivate && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new RuntimeException('Interne adressen zijn niet toegestaan.');
        }
    }
    return $ips[0];
}

function fetch_url(string $url, int $maxChars = 8000): array
{
    $maxChars = max(500, min(30000, $maxChars));
    $final = $url;
    $body = '';
    $status = 0;
    $contentType = '';

    for ($hop = 0; $hop < 5; $hop++) {
        $parts = parse_url($final);
        if (!$parts || !isset($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            throw new InvalidArgumentException('Alleen http(s)-URL\'s zijn toegestaan.');
        }
        $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);
        $ip = fetch_resolve_public($parts['host']);

        $buffer = '';
        $ch = curl_init($final);
        curl_setopt_array($ch, [
            CURLOPT_RESOLVE => [$parts['host'] . ':' . $port . ':' . $ip],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; Dagstart/1.0)',
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,application/rss+xml,application/atom+xml,application/xml,text/xml;q=0.9,*/*;q=0.5', 'Accept-Language: nl,en;q=0.7'],
            CURLOPT_ENCODING => '',
            CURLOPT_WRITEFUNCTION => function ($ch, $chunk) use (&$buffer) {
                $buffer .= $chunk;
                return strlen($buffer) > 2000000 ? 0 : strlen($chunk);
            },
        ]);
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $error = curl_error($ch);
        curl_close($ch);

        if ($status === 0 && $buffer === '') {
            throw new RuntimeException('Ophalen mislukt: ' . ($error ?: 'geen antwoord'));
        }
        if (in_array($status, [301, 302, 303, 307, 308], true) && $location) {
            $final = $location;
            continue;
        }
        $body = $buffer;
        break;
    }

    $result = ['url' => $url, 'final_url' => $final, 'status' => $status, 'content_type' => $contentType];

    if (preg_match('/<(rss|feed|rdf:RDF)\b/i', substr($body, 0, 2000))) {
        return $result + ['kind' => 'feed'] + news_parse_feed($body);
    }
    if (stripos($contentType, 'json') !== false) {
        return $result + ['kind' => 'json', 'text' => mb_substr($body, 0, $maxChars)];
    }
    if (stripos($contentType, 'html') !== false || preg_match('/<html|<body|<!doctype html/i', substr($body, 0, 2000))) {
        $title = preg_match('#<title[^>]*>(.*?)</title>#is', $body, $m) ? trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
        $text = mime_html_to_text(mime_to_utf8($body, preg_match('/charset=([\w-]+)/i', $contentType . ' ' . substr($body, 0, 2000), $c) ? $c[1] : ''));
        return $result + ['kind' => 'html', 'title' => $title, 'text' => mb_substr($text, 0, $maxChars), 'links' => news_extract_links($body, $final)];
    }
    return $result + ['kind' => 'text', 'text' => mb_substr(mime_to_utf8($body, ''), 0, $maxChars)];
}

function news_extract_links(string $html, string $base): array
{
    $links = [];
    if (!preg_match_all('~<a\b[^>]*\bhref=["\']([^"\'#][^"\']*)["\'][^>]*>(.*?)</a>~is', $html, $all, PREG_SET_ORDER)) {
        return [];
    }
    $parts = parse_url($base);
    $root = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
    foreach ($all as $m) {
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        if (mb_strlen($text) < 12) {
            continue;
        }
        $href = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (str_starts_with($href, '//')) {
            $href = ($parts['scheme'] ?? 'https') . ':' . $href;
        } elseif (str_starts_with($href, '/')) {
            $href = $root . $href;
        } elseif (!preg_match('#^https?://#i', $href)) {
            continue;
        }
        $links[$href] = ['text' => mb_substr($text, 0, 200), 'url' => $href];
        if (count($links) >= 80) {
            break;
        }
    }
    return array_values($links);
}

function news_parse_feed(string $xml): array
{
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$ok) {
        throw new RuntimeException('De feed kon niet worden gelezen (ongeldige XML).');
    }
    $x = new DOMXPath($dom);
    $text = static function (DOMNode $node, array $names) use ($x): string {
        foreach ($names as $name) {
            $r = $x->query('./*[local-name()="' . $name . '"]', $node);
            if ($r && $r->length && trim((string) $r->item(0)->textContent) !== '') {
                return trim((string) $r->item(0)->textContent);
            }
        }
        return '';
    };
    $items = [];
    foreach ($x->query('//*[local-name()="item" or local-name()="entry"]') ?: [] as $node) {
        $link = $text($node, ['link']);
        if ($link === '') {
            foreach ($x->query('./*[local-name()="link"]', $node) as $l) {
                if ($l->getAttribute('href') !== '') {
                    $link = $l->getAttribute('href');
                    break;
                }
            }
        }
        $published = $text($node, ['pubDate', 'published', 'updated', 'date']);
        $ts = $published !== '' ? strtotime($published) : false;
        $summary = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($text($node, ['description', 'summary', 'encoded', 'content'])), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        $items[] = [
            'title' => html_entity_decode($text($node, ['title']), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'url' => $link,
            'published' => $ts ? date('c', $ts) : null,
            'summary' => mb_substr($summary, 0, 400),
        ];
        if (count($items) >= 60) {
            break;
        }
    }
    return ['items' => $items];
}
