<?php

declare(strict_types=1);

function dashboard_page(?string $date = null, ?string $slot = null): void
{
    if (!can_view()) {
        render('no_access', ['title' => 'Geen toegang'], 'auth_layout');
        return;
    }

    $reports = list_reports(14);
    $report = null;
    if ($date !== null && $slot !== null) {
        $report = find_report($date, $slot);
        if (!$report) {
            abort(404, 'Dit verslag bestaat niet.');
        }
    } elseif ($reports) {
        $report = $reports[0];
    }

    render('dashboard', [
        'title' => 'Verslag',
        'reports' => $reports,
        'report' => $report,
        'sections' => $report ? report_sections((int) $report['id']) : [],
        'nextSlot' => next_slot_info(),
    ]);
}

function next_slot_info(): array
{
    global $config;
    $now = now_local();
    foreach (['morning', 'evening'] as $slot) {
        $at = at_time($now, $config['slots'][$slot]);
        if ($at > $now) {
            return ['slot' => $slot, 'at' => $at];
        }
    }
    return ['slot' => 'morning', 'at' => at_time($now->modify('+1 day'), $config['slots']['morning'])];
}
