<?php

declare(strict_types=1);

function slot_label(string $slot): string
{
    return $slot === 'morning' ? 'Ochtendverslag' : 'Avondverslag';
}

function detect_slot(?DateTimeImmutable $now = null): string
{
    $now ??= now_local();
    return (int) $now->format('G') < 15 ? 'morning' : 'evening';
}

function at_time(DateTimeImmutable $day, string $hhmm): DateTimeImmutable
{
    [$h, $m] = array_map('intval', explode(':', $hhmm));
    return $day->setTime($h, $m, 0);
}

/**
 * Tijdvensters per onderwerp volgens de afspraak:
 *  ochtend: mail/nieuws vanaf gisteravond 21:00 tot nu, agenda + taken van vandaag
 *  avond:   mail/nieuws vanaf vanochtend 07:45 tot nu, agenda van morgen, nog openstaande taken
 */
function briefing_plan(string $slot, ?DateTimeImmutable $now = null): array
{
    global $config;
    $now ??= now_local();
    $morningAt = $config['slots']['morning'];
    $eveningAt = $config['slots']['evening'];
    $today = $now->setTime(0, 0, 0);
    $iso = static fn (DateTimeImmutable $d): string => $d->format('c');

    if ($slot === 'morning') {
        $from = at_time($today->modify('-1 day'), $eveningAt);
        $calFrom = $today;
        $calTo = $today->modify('+1 day');
        $tasks = 'Taken die vandaag gedaan moeten worden (vandaag verschuldigd, plus achterstallig).';
        $calLabel = 'vandaag';
    } else {
        $from = at_time($today, $morningAt);
        $calFrom = $today->modify('+1 day');
        $calTo = $today->modify('+2 days');
        $tasks = 'Taken die nog openstaan.';
        $calLabel = 'morgen';
    }

    return [
        'slot' => $slot,
        'slot_label' => slot_label($slot),
        'report_date' => $today->format('Y-m-d'),
        'now' => $iso($now),
        'timezone' => $config['timezone'],
        'sections' => [
            'mail' => ['from' => $iso($from), 'to' => $iso($now), 'instruction' => 'Alle ontvangen mail in dit venster, in gewone taal samengevat met duidelijke afzenders; geef per mail aan of iemand op een reactie wacht.'],
            'news' => ['from' => $iso($from), 'to' => $iso($now), 'instruction' => 'Nieuws uit de ingestelde bronnen dat in dit venster is gepubliceerd.'],
            'calendar' => ['from' => $iso($calFrom), 'to' => $iso($calTo), 'instruction' => 'Agendapunten voor ' . $calLabel . '.'],
            'tasks' => ['from' => null, 'to' => null, 'instruction' => $tasks . ' Haal ze uit de Hubs ai-app.'],
        ],
        'report_with' => 'post_report_section (MCP) of POST ' . absolute_url('api/sections'),
    ];
}
