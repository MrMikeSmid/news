<?php

declare(strict_types=1);

function normalize_items(mixed $items): array
{
    if (!is_array($items)) {
        return [];
    }
    $tones = ['success', 'warn', 'danger', 'accent', 'neutral'];
    $out = [];
    foreach (array_slice(array_values($items), 0, 200) as $item) {
        if (!is_array($item)) {
            $item = ['title' => (string) $item];
        }
        $row = [];
        foreach (['title', 'subtitle', 'time', 'text', 'badge'] as $field) {
            $value = trim((string) ($item[$field] ?? ''));
            if ($value !== '') {
                $row[$field] = mb_substr($value, 0, $field === 'text' ? 2000 : 300);
            }
        }
        $link = safe_url($item['url'] ?? null);
        if ($link) {
            $row['url'] = $link;
        }
        $tone = (string) ($item['tone'] ?? '');
        if (isset($row['badge'])) {
            $row['tone'] = in_array($tone, $tones, true) ? $tone : 'neutral';
        }
        if ($row) {
            $out[] = $row;
        }
    }
    return $out;
}

/** Slaat één onderwerp van één verslag op (maakt het verslag aan als dat nog niet bestaat). */
function upsert_section(string $date, string $slot, string $kind, string $summary, mixed $items, ?string $from = null, ?string $to = null): array
{
    global $config;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
        throw new InvalidArgumentException('date moet YYYY-MM-DD zijn.');
    }
    if (!isset($config['slots'][$slot])) {
        throw new InvalidArgumentException('slot moet "morning" of "evening" zijn.');
    }
    if (!isset($config['kinds'][$kind])) {
        throw new InvalidArgumentException('kind moet een van deze zijn: ' . implode(', ', array_keys($config['kinds'])) . '.');
    }
    $summary = mb_substr(trim($summary), 0, 20000);
    $now = now_local()->format('c');
    $pdo = db();

    $pdo->prepare('INSERT INTO reports (report_date, slot, created_at, updated_at) VALUES (:d, :s, :n, :n)
        ON CONFLICT(report_date, slot) DO UPDATE SET updated_at = excluded.updated_at')->execute(['d' => $date, 's' => $slot, 'n' => $now]);
    $reportId = (int) $pdo->query('SELECT id FROM reports WHERE report_date = ' . $pdo->quote($date) . ' AND slot = ' . $pdo->quote($slot))->fetchColumn();

    $pdo->prepare('INSERT INTO report_sections (report_id, kind, summary, items_json, period_from, period_to, created_at, updated_at)
        VALUES (:r, :k, :summary, :items, :pf, :pt, :n, :n)
        ON CONFLICT(report_id, kind) DO UPDATE SET summary = excluded.summary, items_json = excluded.items_json,
            period_from = excluded.period_from, period_to = excluded.period_to, updated_at = excluded.updated_at')->execute([
        'r' => $reportId, 'k' => $kind, 'summary' => $summary,
        'items' => json_encode(normalize_items($items), JSON_UNESCAPED_UNICODE),
        'pf' => $from, 'pt' => $to, 'n' => $now,
    ]);

    return ['report_id' => $reportId, 'date' => $date, 'slot' => $slot, 'kind' => $kind, 'url' => absolute_url('rapport/' . $date . '/' . $slot)];
}

function list_reports(int $limit = 14): array
{
    $statement = db()->prepare('SELECT * FROM reports ORDER BY report_date DESC, CASE slot WHEN \'evening\' THEN 1 ELSE 0 END DESC LIMIT :l');
    $statement->bindValue('l', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll();
}

function find_report(string $date, string $slot): ?array
{
    $statement = db()->prepare('SELECT * FROM reports WHERE report_date = :d AND slot = :s');
    $statement->execute(['d' => $date, 's' => $slot]);
    return $statement->fetch() ?: null;
}

function report_sections(int $reportId): array
{
    $statement = db()->prepare('SELECT * FROM report_sections WHERE report_id = :id');
    $statement->execute(['id' => $reportId]);
    $sections = [];
    foreach ($statement->fetchAll() as $row) {
        $row['items'] = json_decode($row['items_json'], true) ?: [];
        $sections[$row['kind']] = $row;
    }
    return $sections;
}
