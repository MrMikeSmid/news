<?php

declare(strict_types=1);

function api_sections(): void
{
    if (!agent_authorized()) {
        json_out(['error' => 'Ongeldige of ontbrekende token.'], 401);
    }
    if (!is_post()) {
        json_out(['error' => 'Gebruik POST.'], 405);
    }
    $data = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($data)) {
        json_out(['error' => 'Body moet JSON zijn.'], 400);
    }
    try {
        json_out(['ok' => true] + save_section_from_input($data));
    } catch (InvalidArgumentException $e) {
        json_out(['error' => $e->getMessage()], 422);
    }
}

function save_section_from_input(array $data): array
{
    $plan = briefing_plan(in_array(($data['slot'] ?? ''), ['morning', 'evening'], true) ? $data['slot'] : detect_slot());
    return upsert_section(
        (string) ($data['date'] ?? $plan['report_date']),
        (string) ($data['slot'] ?? $plan['slot']),
        (string) ($data['kind'] ?? ''),
        (string) ($data['summary'] ?? ''),
        $data['items'] ?? [],
        isset($data['period_from']) ? (string) $data['period_from'] : null,
        isset($data['period_to']) ? (string) $data['period_to'] : null
    );
}
