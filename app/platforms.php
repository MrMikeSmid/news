<?php

declare(strict_types=1);

/**
 * title/media: none | optional | required. link: inline (aan de tekst toegevoegd) | none.
 * url_len: aantal tekens dat een URL telt (X telt elke link als 23).
 */
function platforms(): array
{
    return [
        'x' => ['name' => 'X (Twitter)', 'color' => '#111111', 'title' => 'none', 'body_max' => 280, 'media' => 'optional', 'link' => 'inline', 'url_len' => 23, 'hashtag_max' => null, 'subreddit' => false, 'note' => 'Max 280 tekens, elke link telt als 23.'],
        'facebook' => ['name' => 'Facebook', 'color' => '#1877F2', 'title' => 'none', 'body_max' => 63206, 'media' => 'optional', 'link' => 'inline', 'url_len' => null, 'hashtag_max' => null, 'subreddit' => false, 'note' => 'Korte posts (onder 80 tekens) scoren het best.'],
        'linkedin' => ['name' => 'LinkedIn', 'color' => '#0A66C2', 'title' => 'none', 'body_max' => 3000, 'media' => 'optional', 'link' => 'inline', 'url_len' => null, 'hashtag_max' => null, 'subreddit' => false, 'note' => 'Max 3000 tekens.'],
        'reddit' => ['name' => 'Reddit', 'color' => '#FF4500', 'title' => 'required', 'title_max' => 300, 'body_max' => 40000, 'media' => 'optional', 'link' => 'inline', 'url_len' => null, 'hashtag_max' => null, 'subreddit' => true, 'note' => 'Titel (max 300) en subreddit zijn verplicht.'],
        'truthsocial' => ['name' => 'Truth Social', 'color' => '#5448EE', 'title' => 'none', 'body_max' => 500, 'media' => 'optional', 'link' => 'inline', 'url_len' => null, 'hashtag_max' => null, 'subreddit' => false, 'note' => 'Max 500 tekens.'],
        'bluesky' => ['name' => 'Bluesky', 'color' => '#0085FF', 'title' => 'none', 'body_max' => 300, 'media' => 'optional', 'link' => 'inline', 'url_len' => null, 'hashtag_max' => null, 'subreddit' => false, 'note' => 'Max 300 tekens.'],
        'threads' => ['name' => 'Threads', 'color' => '#333333', 'title' => 'none', 'body_max' => 500, 'media' => 'optional', 'link' => 'inline', 'url_len' => null, 'hashtag_max' => null, 'subreddit' => false, 'note' => 'Max 500 tekens.'],
        'mastodon' => ['name' => 'Mastodon', 'color' => '#6364FF', 'title' => 'none', 'body_max' => 500, 'media' => 'optional', 'link' => 'inline', 'url_len' => 23, 'hashtag_max' => null, 'subreddit' => false, 'note' => 'Max 500 tekens, elke link telt als 23.'],
        'instagram' => ['name' => 'Instagram', 'color' => '#E1306C', 'title' => 'none', 'body_max' => 2200, 'media' => 'required', 'link' => 'none', 'url_len' => null, 'hashtag_max' => 30, 'subreddit' => false, 'note' => 'Afbeelding verplicht, max 2200 tekens en 30 hashtags. Links in bijschriften zijn niet klikbaar.'],
    ];
}

function platform(string $key): ?array
{
    return platforms()[$key] ?? null;
}

function compose_text(array $platform, string $body, string $link): string
{
    $text = trim($body);
    if ($link !== '' && $platform['link'] === 'inline') {
        $text = trim($text . "\n\n" . $link);
    }
    return $text;
}

function text_length(array $platform, string $text): int
{
    if (!empty($platform['url_len'])) {
        $text = preg_replace('~https?://\S+~i', str_repeat('x', (int) $platform['url_len']), $text) ?? $text;
    }
    return mb_strlen($text);
}

function validate_for_platform(array $platform, string $title, string $body, string $link, bool $hasImage, string $subreddit): array
{
    $errors = [];
    $text = compose_text($platform, $body, $link);
    $length = text_length($platform, $text);

    if ($text === '' && !$hasImage) {
        $errors[] = 'Tekst is verplicht.';
    }
    if ($length > $platform['body_max']) {
        $errors[] = 'Tekst is ' . ($length - $platform['body_max']) . ' tekens te lang (' . $length . '/' . $platform['body_max'] . ').';
    }
    if ($platform['title'] === 'required' && $title === '') {
        $errors[] = 'Titel is verplicht.';
    }
    if (!empty($platform['title_max']) && mb_strlen($title) > $platform['title_max']) {
        $errors[] = 'Titel is te lang (max ' . $platform['title_max'] . ').';
    }
    if ($platform['media'] === 'required' && !$hasImage) {
        $errors[] = 'Een afbeelding is verplicht.';
    }
    if ($platform['subreddit'] && !preg_match('/^[A-Za-z0-9_]{2,21}$/', $subreddit)) {
        $errors[] = 'Geef een geldige subreddit op (zonder r/).';
    }
    if (!empty($platform['hashtag_max']) && preg_match_all('/#\w+/u', $text) > $platform['hashtag_max']) {
        $errors[] = 'Maximaal ' . $platform['hashtag_max'] . ' hashtags.';
    }
    return $errors;
}
