<?php

declare(strict_types=1);

function fetch_all_active_sources(): array
{
    $statement = db()->query("SELECT * FROM sources WHERE type = 'rss' AND is_active = 1");
    $results = [];
    foreach ($statement->fetchAll() as $source) {
        $results[$source['id']] = fetch_source($source);
    }
    return $results;
}

function fetch_source(array $source): array
{
    $imported = 0;
    $error = null;

    try {
        $xml = fetch_feed_body($source['url']);
        $dom = new DOMXPath(load_feed_dom($xml));
        $dom->registerNamespace('atom', 'http://www.w3.org/2005/Atom');
        $dom->registerNamespace('media', 'http://search.yahoo.com/mrss/');
        $dom->registerNamespace('content', 'http://purl.org/rss/1.0/modules/content/');

        $nodes = $dom->query('//item');
        if ($nodes->length === 0) {
            $nodes = $dom->query('//atom:entry');
        }

        foreach ($nodes as $node) {
            $item = parse_feed_item($dom, $node);
            if ($item === null) {
                continue;
            }
            save_feed_post((int) $source['id'], $item);
            $imported++;
        }

        db()->prepare('UPDATE sources SET last_fetched_at = :now, last_error = NULL WHERE id = :id')
            ->execute(['now' => gmdate('Y-m-d H:i:s'), 'id' => $source['id']]);
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        db()->prepare('UPDATE sources SET last_fetched_at = :now, last_error = :error WHERE id = :id')
            ->execute(['now' => gmdate('Y-m-d H:i:s'), 'error' => $error, 'id' => $source['id']]);
    }

    return ['imported' => $imported, 'error' => $error];
}

function fetch_feed_body(string $url): string
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new RuntimeException('Ongeldige feed-URL.');
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Homebase RSS Reader/1.0',
        ]);
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlError !== '') {
            throw new RuntimeException('Kon feed niet ophalen: ' . $curlError);
        }
        if ($httpCode >= 400) {
            throw new RuntimeException('Feed gaf HTTP-status ' . $httpCode);
        }
        return $body;
    }

    $context = stream_context_create(['http' => ['timeout' => 20, 'user_agent' => 'Homebase RSS Reader/1.0']]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        throw new RuntimeException('Kon feed niet ophalen.');
    }
    return $body;
}

function load_feed_dom(string $xml): DOMDocument
{
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        throw new RuntimeException('Kon feed niet lezen (ongeldige XML).');
    }
    return $dom;
}

function parse_feed_item(DOMXPath $dom, DOMElement $node): ?array
{
    $title = trim(feed_text($dom, $node, 'title'));
    $link = feed_link($dom, $node);
    $guid = trim(feed_text($dom, $node, 'guid') ?: feed_text($dom, $node, 'atom:id') ?: $link ?: $title);

    if ($title === '' && $link === '') {
        return null;
    }

    $description = feed_text($dom, $node, 'description')
        ?: feed_text($dom, $node, 'content:encoded')
        ?: feed_text($dom, $node, 'atom:summary')
        ?: feed_text($dom, $node, 'atom:content')
        ?: '';

    $publishedRaw = feed_text($dom, $node, 'pubDate') ?: feed_text($dom, $node, 'atom:published') ?: feed_text($dom, $node, 'atom:updated');
    $timestamp = $publishedRaw ? strtotime($publishedRaw) : false;
    $published = $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : gmdate('Y-m-d H:i:s');

    [$imagePath, $videoPath, $mediaType] = feed_media($dom, $node, $description);

    $plainBody = trim(strip_tags(html_entity_decode($description, ENT_QUOTES, 'UTF-8')));
    $plainBody = preg_replace('/\s+/', ' ', $plainBody) ?? $plainBody;
    if (mb_strlen($plainBody) > 600) {
        $plainBody = mb_substr($plainBody, 0, 600) . '…';
    }

    return [
        'guid' => mb_substr($guid !== '' ? $guid : uniqid('item_', true), 0, 500),
        'title' => mb_substr($title !== '' ? $title : 'Zonder titel', 0, 500),
        'body' => $plainBody,
        'link' => $link,
        'image_path' => $imagePath,
        'video_path' => $videoPath,
        'media_type' => $mediaType,
        'published_at' => $published,
    ];
}

function feed_text(DOMXPath $dom, DOMElement $node, string $tag): string
{
    $result = $dom->query($tag, $node);
    if ($result && $result->length > 0) {
        return (string) $result->item(0)->textContent;
    }
    return '';
}

function feed_link(DOMXPath $dom, DOMElement $node): string
{
    $result = $dom->query('link', $node);
    if ($result && $result->length > 0) {
        $text = trim((string) $result->item(0)->textContent);
        if ($text !== '') {
            return $text;
        }
    }
    $atomLinks = $dom->query('atom:link', $node);
    foreach ($atomLinks as $atomLink) {
        $href = $atomLink->getAttribute('href');
        if ($href !== '') {
            return $href;
        }
    }
    return '';
}

function feed_media(DOMXPath $dom, DOMElement $node, string $description): array
{
    $videoExtensions = '/\.(mp4|webm|mov|m4v)(\?.*)?$/i';

    $enclosures = $dom->query('enclosure', $node);
    foreach ($enclosures as $enclosure) {
        $url = $enclosure->getAttribute('url');
        $type = $enclosure->getAttribute('type');
        if ($url === '') {
            continue;
        }
        if (str_starts_with($type, 'video/') || preg_match($videoExtensions, $url)) {
            return [null, $url, 'video'];
        }
        if (str_starts_with($type, 'image/') || preg_match('/\.(jpe?g|png|gif|webp)(\?.*)?$/i', $url)) {
            return [$url, null, 'image'];
        }
    }

    $mediaContents = $dom->query('media:content', $node);
    foreach ($mediaContents as $media) {
        $url = $media->getAttribute('url');
        $medium = $media->getAttribute('medium');
        $type = $media->getAttribute('type');
        if ($url === '') {
            continue;
        }
        if ($medium === 'video' || str_starts_with($type, 'video/') || preg_match($videoExtensions, $url)) {
            return [null, $url, 'video'];
        }
        return [$url, null, 'image'];
    }

    $thumbnails = $dom->query('media:thumbnail', $node);
    if ($thumbnails->length > 0) {
        $url = $thumbnails->item(0)->getAttribute('url');
        if ($url !== '') {
            return [$url, null, 'image'];
        }
    }

    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $description, $matches)) {
        return [$matches[1], null, 'image'];
    }

    return [null, null, 'link'];
}

function save_feed_post(int $sourceId, array $item): void
{
    $statement = db()->prepare('INSERT INTO posts (source_id, guid, title, body, link, image_path, video_path, media_type, published_at)
        VALUES (:source_id, :guid, :title, :body, :link, :image_path, :video_path, :media_type, :published_at)
        ON CONFLICT(source_id, guid) DO UPDATE SET
            title = excluded.title,
            body = excluded.body,
            link = excluded.link,
            image_path = excluded.image_path,
            video_path = excluded.video_path,
            media_type = excluded.media_type');

    $statement->execute([
        'source_id' => $sourceId,
        'guid' => $item['guid'],
        'title' => $item['title'],
        'body' => $item['body'],
        'link' => $item['link'],
        'image_path' => $item['image_path'],
        'video_path' => $item['video_path'],
        'media_type' => $item['media_type'],
        'published_at' => $item['published_at'],
    ]);
}
