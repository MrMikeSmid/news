<?php

declare(strict_types=1);

function admin_login(): void
{
    if (admin_user()) {
        redirect('admin');
    }
    $errors = [];
    if (is_post()) {
        verify_csrf();
        if (!attempt_login(clean('username'), (string) ($_POST['password'] ?? ''))) {
            $errors['login'] = 'De gebruikersnaam of het wachtwoord klopt niet.';
        } else {
            redirect('admin');
        }
    }
    render('admin/login', ['title' => 'Beheer inloggen', 'errors' => $errors, 'adminLayout' => true]);
}

function admin_dashboard(): void
{
    require_admin();
    render('admin/dashboard', ['title' => 'Dashboard', 'counts' => submission_counts(), 'adminLayout' => true]);
}

function admin_submissions(string $type): void
{
    require_admin();
    $tables = admin_table_config();
    if (!isset($tables[$type])) {
        abort(404, 'Dit overzicht bestaat niet.');
    }
    $config = $tables[$type];
    $rows = db()->query('SELECT * FROM ' . $config['table'] . ' ORDER BY created_at DESC')->fetchAll();
    render('admin/submissions', [
        'title' => $config['title'], 'type' => $type, 'columns' => $config['columns'],
        'rows' => $rows, 'adminLayout' => true,
    ]);
}

function admin_sponsor_update(int $id): void
{
    require_admin();
    verify_csrf();
    $statuses = ['Nieuw', 'Benaderd', 'Geïnteresseerd', 'Bevestigd', 'Afgehaakt'];
    $status = clean('status');
    if (!in_array($status, $statuses, true)) {
        abort(422, 'Ongeldige sponsorstatus.');
    }
    $statement = db()->prepare('UPDATE sponsor_submissions SET status = :status, admin_notes = :notes WHERE id = :id');
    $statement->execute(['status' => $status, 'notes' => clean('admin_notes'), 'id' => $id]);
    flash('success', 'De sponsorgegevens zijn bijgewerkt.');
    redirect('admin/submissions/sponsors');
}

function admin_export(string $type): never
{
    require_admin();
    $tables = admin_table_config();
    if (!isset($tables[$type])) {
        abort(404, 'Dit exporttype bestaat niet.');
    }
    $config = $tables[$type];
    $rows = db()->query('SELECT * FROM ' . $config['table'] . ' ORDER BY created_at DESC')->fetchAll();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $type . '-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'wb');
    fwrite($output, "\xEF\xBB\xBF");
    if ($rows) {
        fputcsv($output, array_keys($rows[0]), ';');
        foreach ($rows as $row) {
            fputcsv($output, $row, ';');
        }
    }
    fclose($output);
    exit;
}

function admin_table_config(): array
{
    return [
        'interest' => ['table' => 'interest_submissions', 'title' => 'Interesse-aanmeldingen', 'columns' => [
            'created_at' => 'Datum', 'name' => 'Naam', 'email' => 'E-mail', 'phone' => 'Telefoon',
            'age_category' => 'Leeftijd', 'participation_choice' => 'Keuze', 'expected_people' => 'Personen', 'comment' => 'Opmerking',
        ]],
        'volunteers' => ['table' => 'volunteer_submissions', 'title' => 'Vrijwilligers', 'columns' => [
            'created_at' => 'Datum', 'name' => 'Naam', 'email' => 'E-mail', 'phone' => 'Telefoon',
            'help_type' => 'Hulp bij', 'availability' => 'Beschikbaarheid',
        ]],
        'sponsors' => ['table' => 'sponsor_submissions', 'title' => 'Sponsoren', 'columns' => [
            'created_at' => 'Datum', 'company_name' => 'Bedrijf', 'contact_name' => 'Contactpersoon',
            'email' => 'E-mail', 'phone' => 'Telefoon', 'contribution_type' => 'Bijdrage',
            'status' => 'Status', 'admin_notes' => 'Notities',
        ]],
        'ideas' => ['table' => 'idea_submissions', 'title' => 'Ideeën', 'columns' => [
            'created_at' => 'Datum', 'name' => 'Naam', 'activity_idea' => 'Idee',
            'target_group' => 'Doelgroep', 'required_material' => 'Materiaal', 'comment' => 'Opmerking',
        ]],
    ];
}
