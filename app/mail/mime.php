<?php

declare(strict_types=1);

function mime_split(string $raw): array
{
    if (preg_match('/\r?\n\r?\n/', $raw, $m, PREG_OFFSET_CAPTURE)) {
        return [substr($raw, 0, $m[0][1]), substr($raw, $m[0][1] + strlen($m[0][0]))];
    }
    return [$raw, ''];
}

function mime_headers(string $block): array
{
    $headers = [];
    $block = preg_replace('/\r?\n[ \t]+/', ' ', $block) ?? $block;
    foreach (preg_split('/\r?\n/', $block) ?: [] as $line) {
        if (!str_contains($line, ':')) {
            continue;
        }
        [$name, $value] = explode(':', $line, 2);
        $headers[strtolower(trim($name))][] = trim($value);
    }
    return $headers;
}

function mime_header(array $headers, string $name): string
{
    return $headers[strtolower($name)][0] ?? '';
}

function mime_decode_header(string $value): string
{
    if (!str_contains($value, '=?')) {
        return $value;
    }
    $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
    return $decoded !== false && $decoded !== '' ? $decoded : $value;
}

function mime_to_utf8(string $text, string $charset): string
{
    $charset = strtolower(trim($charset, "\"' "));
    if ($charset === '' || $charset === 'utf-8' || $charset === 'utf8' || $charset === 'us-ascii') {
        return mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
    }
    $converted = @iconv($charset, 'UTF-8//IGNORE', $text);
    if ($converted !== false) {
        return $converted;
    }
    return mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
}

function mime_addresses(string $value): array
{
    $value = mime_decode_header($value);
    $parts = [];
    $buffer = '';
    $quoted = false;
    $angle = false;
    foreach (str_split($value) as $char) {
        if ($char === '"') {
            $quoted = !$quoted;
        } elseif (!$quoted && $char === '<') {
            $angle = true;
        } elseif (!$quoted && $char === '>') {
            $angle = false;
        }
        if ($char === ',' && !$quoted && !$angle) {
            $parts[] = $buffer;
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }
    $parts[] = $buffer;

    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        if (preg_match('/^(.*?)<([^>]+)>\s*$/s', $part, $m)) {
            $out[] = ['name' => trim($m[1], " \t\"'"), 'address' => trim($m[2])];
        } else {
            $out[] = ['name' => '', 'address' => trim($part, " \t\"'")];
        }
    }
    return $out;
}

function mime_params(string $value): array
{
    $params = [];
    foreach (explode(';', $value) as $i => $piece) {
        if ($i === 0 || !str_contains($piece, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $piece, 2);
        $params[strtolower(trim($k))] = trim(trim($v), '"');
    }
    return $params;
}

function mime_html_to_text(string $html): string
{
    $html = preg_replace('#<(script|style|head)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
    $html = preg_replace('#<br\s*/?>|</(p|div|tr|li|h[1-6]|table)>#i', "\n", $html) ?? $html;
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/[ \t\xC2\xA0]+/", ' ', $text) ?? $text;
    $text = preg_replace("/\s*\n\s*/", "\n", $text) ?? $text;
    return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
}

/** @return array{headers:array,text:string,html:string,attachments:array} */
function mime_parse(string $raw): array
{
    [$headerBlock, $body] = mime_split($raw);
    $headers = mime_headers($headerBlock);
    $result = ['headers' => $headers, 'text' => '', 'html' => '', 'attachments' => []];
    mime_walk($headers, $body, $result, 0);
    return $result;
}

function mime_walk(array $headers, string $body, array &$result, int $depth): void
{
    $typeHeader = mime_header($headers, 'content-type') ?: 'text/plain; charset=us-ascii';
    $type = strtolower(trim(explode(';', $typeHeader)[0]));
    $params = mime_params($typeHeader);

    if (str_starts_with($type, 'multipart/') && isset($params['boundary']) && $depth < 8) {
        $boundary = '--' . $params['boundary'];
        $segments = explode($boundary, $body);
        array_shift($segments);
        foreach ($segments as $segment) {
            if (str_starts_with($segment, '--')) {
                break;
            }
            $segment = preg_replace('/^\r?\n/', '', $segment) ?? $segment;
            [$h, $b] = mime_split($segment);
            mime_walk(mime_headers($h), $b, $result, $depth + 1);
        }
        return;
    }

    $disposition = mime_header($headers, 'content-disposition');
    $dispParams = mime_params($disposition);
    $name = $dispParams['filename'] ?? $params['name'] ?? '';
    $isAttachment = stripos($disposition, 'attachment') === 0 || ($name !== '' && !str_starts_with($type, 'text/'));

    if ($isAttachment || (!str_starts_with($type, 'text/') && $type !== 'message/rfc822')) {
        $result['attachments'][] = mime_decode_header($name) ?: $type;
        return;
    }
    if ($type === 'message/rfc822') {
        $result['attachments'][] = ($name ?: 'doorgestuurd bericht');
        return;
    }

    $encoding = strtolower(mime_header($headers, 'content-transfer-encoding'));
    $decoded = match (true) {
        $encoding === 'base64' => (string) base64_decode($body),
        $encoding === 'quoted-printable' => quoted_printable_decode($body),
        default => $body,
    };
    $text = mime_to_utf8($decoded, $params['charset'] ?? '');

    if ($type === 'text/html') {
        $result['html'] .= ($result['html'] ? "\n" : '') . $text;
    } else {
        $result['text'] .= ($result['text'] ? "\n" : '') . $text;
    }
}

function mime_body_text(array $parsed): string
{
    $text = trim($parsed['text']);
    if ($text === '' && $parsed['html'] !== '') {
        $text = mime_html_to_text($parsed['html']);
    }
    return $text;
}
