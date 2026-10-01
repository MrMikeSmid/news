<?php

declare(strict_types=1);

function ical_unescape(string $value): string
{
    return str_replace(['\\n', '\\N', '\\,', '\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], $value);
}

/** @return array<int,array{name:string,params:array,value:string}> */
function ical_lines(string $ics): array
{
    $ics = preg_replace('/\r?\n[ \t]/', '', $ics) ?? $ics;
    $out = [];
    foreach (preg_split('/\r?\n/', $ics) ?: [] as $line) {
        if ($line === '') {
            continue;
        }
        $inQuote = false;
        $split = null;
        for ($i = 0, $n = strlen($line); $i < $n; $i++) {
            if ($line[$i] === '"') {
                $inQuote = !$inQuote;
            } elseif ($line[$i] === ':' && !$inQuote) {
                $split = $i;
                break;
            }
        }
        if ($split === null) {
            continue;
        }
        $left = substr($line, 0, $split);
        $segments = explode(';', $left);
        $name = strtoupper(array_shift($segments));
        $params = [];
        foreach ($segments as $segment) {
            if (str_contains($segment, '=')) {
                [$k, $v] = explode('=', $segment, 2);
                $params[strtoupper($k)] = trim($v, '"');
            }
        }
        $out[] = ['name' => $name, 'params' => $params, 'value' => substr($line, $split + 1)];
    }
    return $out;
}

/** @return array<int,array> ruwe VEVENT's */
function ical_events(string $ics): array
{
    $events = [];
    $current = null;
    foreach (ical_lines($ics) as $line) {
        if ($line['name'] === 'BEGIN' && $line['value'] === 'VEVENT') {
            $current = ['exdates' => [], 'props' => []];
        } elseif ($line['name'] === 'END' && $line['value'] === 'VEVENT' && $current !== null) {
            $events[] = $current;
            $current = null;
        } elseif ($current !== null) {
            if ($line['name'] === 'EXDATE') {
                foreach (explode(',', $line['value']) as $v) {
                    $current['exdates'][] = ['params' => $line['params'], 'value' => trim($v)];
                }
            } else {
                $current['props'][$line['name']] ??= $line;
            }
        }
    }
    return $events;
}

/** @return array{0:DateTimeImmutable,1:bool} [moment, hele dag] */
function ical_moment(array $prop, DateTimeZone $local): array
{
    $value = trim($prop['value']);
    if (($prop['params']['VALUE'] ?? '') === 'DATE' || preg_match('/^\d{8}$/', $value)) {
        return [new DateTimeImmutable(substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2) . ' 00:00:00', $local), true];
    }
    $format = str_ends_with($value, 'Z') ? 'Ymd\THis\Z' : 'Ymd\THis';
    if (str_ends_with($value, 'Z')) {
        $tz = new DateTimeZone('UTC');
    } else {
        try {
            $tz = new DateTimeZone($prop['params']['TZID'] ?? $local->getName());
        } catch (Throwable) {
            $tz = $local;
        }
    }
    $dt = DateTimeImmutable::createFromFormat($format, $value, $tz);
    if (!$dt) {
        throw new InvalidArgumentException('Onbekende datum in agenda: ' . $value);
    }
    return [$dt, false];
}

function ical_duration(string $value): ?DateInterval
{
    try {
        return new DateInterval(ltrim($value, '+-'));
    } catch (Throwable) {
        return null;
    }
}

function ical_rrule(string $value): array
{
    $rule = [];
    foreach (explode(';', $value) as $part) {
        if (str_contains($part, '=')) {
            [$k, $v] = explode('=', $part, 2);
            $rule[strtoupper($k)] = $v;
        }
    }
    return $rule;
}

/**
 * Alle voorkomens van één VEVENT binnen [from, to). Geeft een lijst met
 * ['start','end','all_day'] terug (in de lokale tijdzone).
 */
function ical_occurrences(array $event, DateTimeImmutable $from, DateTimeImmutable $to, DateTimeZone $local): array
{
    $p = $event['props'];
    if (!isset($p['DTSTART'])) {
        return [];
    }
    [$start, $allDay] = ical_moment($p['DTSTART'], $local);
    if (isset($p['DTEND'])) {
        [$end] = ical_moment($p['DTEND'], $local);
    } elseif (isset($p['DURATION']) && ($iv = ical_duration($p['DURATION']['value']))) {
        $end = $start->add($iv);
    } else {
        $end = $allDay ? $start->modify('+1 day') : $start;
    }
    $length = $end->getTimestamp() - $start->getTimestamp();

    $overlaps = static fn (DateTimeImmutable $s, DateTimeImmutable $e): bool => $e > $from ? $s < $to : ($s == $e && $s >= $from && $s < $to);
    $emit = static function (DateTimeImmutable $s) use ($length, $local, $allDay): array {
        $e = $s->modify('+' . $length . ' seconds');
        return ['start' => $s->setTimezone($local), 'end' => $e->setTimezone($local), 'all_day' => $allDay];
    };

    if (!isset($p['RRULE']) || isset($p['RECURRENCE-ID'])) {
        $occ = $emit($start);
        return $overlaps($occ['start'], $occ['end']) ? [$occ] : [];
    }

    $rule = ical_rrule($p['RRULE']['value']);
    $freq = $rule['FREQ'] ?? '';
    $interval = max(1, (int) ($rule['INTERVAL'] ?? 1));
    $count = isset($rule['COUNT']) ? (int) $rule['COUNT'] : null;
    $until = null;
    if (isset($rule['UNTIL'])) {
        try {
            [$until] = ical_moment(['params' => [], 'value' => $rule['UNTIL']], $local);
            if (preg_match('/^\d{8}$/', $rule['UNTIL'])) {
                $until = $until->modify('+1 day');
            }
        } catch (Throwable) {
            $until = null;
        }
    }
    $excluded = [];
    foreach ($event['exdates'] as $ex) {
        try {
            [$m] = ical_moment($ex, $local);
            $excluded[$allDay ? $m->format('Y-m-d') : (string) $m->getTimestamp()] = true;
        } catch (Throwable) {
        }
    }
    $dayCodes = ['SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6];
    $startTz = $start->getTimezone();
    $time = $start->format('H:i:s');

    $candidates = static function (int $k) use ($freq, $interval, $rule, $start, $dayCodes, $time, $startTz): array {
        $at = static fn (DateTimeImmutable $day): DateTimeImmutable => new DateTimeImmutable($day->format('Y-m-d') . ' ' . $time, $startTz);
        $dates = [];
        switch ($freq) {
            case 'DAILY':
                $dates[] = $start->modify('+' . ($k * $interval) . ' days');
                break;
            case 'WEEKLY':
                $weekStart = $start->modify('-' . (((int) $start->format('N')) - 1) . ' days')->modify('+' . ($k * $interval) . ' weeks');
                $days = isset($rule['BYDAY']) ? array_map(fn ($d) => $dayCodes[substr($d, -2)] ?? -1, explode(',', $rule['BYDAY'])) : [(int) $start->format('w')];
                sort($days);
                foreach ($days as $d) {
                    if ($d >= 0) {
                        $dates[] = $weekStart->modify('+' . (($d + 6) % 7) . ' days');
                    }
                }
                break;
            case 'MONTHLY':
                $first = $start->modify('first day of this month')->modify('+' . ($k * $interval) . ' months');
                if (isset($rule['BYDAY']) && preg_match('/^(-?\d+)?([A-Z]{2})$/', explode(',', $rule['BYDAY'])[0], $m) && isset($dayCodes[$m[2]])) {
                    $ord = $m[1] !== '' ? (int) $m[1] : 1;
                    $names = ['SU' => 'sunday', 'MO' => 'monday', 'TU' => 'tuesday', 'WE' => 'wednesday', 'TH' => 'thursday', 'FR' => 'friday', 'SA' => 'saturday'];
                    $ords = [1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth', 5 => 'fifth'];
                    $dates[] = $ord > 0
                        ? $first->modify(($ords[$ord] ?? 'first') . ' ' . $names[$m[2]] . ' of this month')
                        : $first->modify('last ' . $names[$m[2]] . ' of this month');
                } else {
                    foreach (isset($rule['BYMONTHDAY']) ? array_map('intval', explode(',', $rule['BYMONTHDAY'])) : [(int) $start->format('j')] as $dom) {
                        if ($dom >= 1 && $dom <= (int) $first->format('t')) {
                            $dates[] = $first->modify('+' . ($dom - 1) . ' days');
                        }
                    }
                }
                break;
            case 'YEARLY':
                $year = (int) $start->format('Y') + $k * $interval;
                $month = isset($rule['BYMONTH']) ? (int) explode(',', $rule['BYMONTH'])[0] : (int) $start->format('n');
                $dom = isset($rule['BYMONTHDAY']) ? (int) explode(',', $rule['BYMONTHDAY'])[0] : (int) $start->format('j');
                if (checkdate($month, $dom, $year)) {
                    $dates[] = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $dom), $startTz);
                }
                break;
        }
        return array_map($at, $dates);
    };

    if (!in_array($freq, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)) {
        $occ = $emit($start);
        return $overlaps($occ['start'], $occ['end']) ? [$occ] : [];
    }

    $out = [];
    $produced = 0;
    for ($k = 0; $k < 5000; $k++) {
        $batch = $candidates($k);
        $batchPast = 0;
        foreach ($batch as $s) {
            if ($s < $start) {
                continue;
            }
            if (($until && $s > $until) || ($count !== null && $produced >= $count)) {
                return $out;
            }
            $produced++;
            $key = $allDay ? $s->format('Y-m-d') : (string) $s->getTimestamp();
            if (isset($excluded[$key])) {
                continue;
            }
            $occ = $emit($s);
            if ($occ['start'] >= $to) {
                $batchPast++;
            } elseif ($overlaps($occ['start'], $occ['end'])) {
                $out[] = $occ;
            }
        }
        if ($batch && $batchPast === count($batch)) {
            break;
        }
    }
    return $out;
}

function ical_event_view(array $event, array $occ, string $calendar): array
{
    $p = $event['props'];
    $text = static fn (string $k): string => isset($p[$k]) ? trim(ical_unescape($p[$k]['value'])) : '';
    return [
        'calendar' => $calendar,
        'uid' => $text('UID'),
        'title' => $text('SUMMARY') ?: '(zonder titel)',
        'start' => $occ['start']->format('c'),
        'end' => $occ['end']->format('c'),
        'all_day' => $occ['all_day'],
        'location' => $text('LOCATION'),
        'description' => mb_substr($text('DESCRIPTION'), 0, 600),
        'status' => strtolower($text('STATUS')),
    ];
}
