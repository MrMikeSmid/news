<?php

declare(strict_types=1);

function home(): void
{
    render('home', ['title' => 'Komt er een Sportweek in ’t Zand?', 'counts' => submission_counts()]);
}

function form_page(string $type): void
{
    $views = [
        'interest' => ['interest', 'Interesse doorgeven'],
        'volunteer' => ['volunteer', 'Help je mee?'],
        'sponsor' => ['sponsor', 'Sponsor worden'],
        'idea' => ['idea', 'Idee insturen'],
    ];
    if (!isset($views[$type])) {
        abort(404, 'Deze pagina bestaat niet.');
    }
    render($views[$type][0], ['title' => $views[$type][1], 'errors' => []]);
}

function submit_interest(): void
{
    verify_csrf();
    $required = ['name', 'email', 'age_category', 'participation_choice', 'consent'];
    $errors = required_errors($required);
    if (!validate_email(clean('email'))) {
        $errors['email'] = 'Vul een geldig e-mailadres in.';
    }
    $people = filter_input(INPUT_POST, 'expected_people', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
    if ($people === false) {
        $errors['expected_people'] = 'Vul een aantal tussen 0 en 100 in.';
    }
    if ($errors) {
        render('interest', ['title' => 'Interesse doorgeven', 'errors' => $errors]);
        return;
    }
    $statement = db()->prepare('INSERT INTO interest_submissions
        (name, email, phone, age_category, participation_choice, expected_people, comment, consent)
        VALUES (:name, :email, :phone, :age, :choice, :people, :comment, 1)');
    $statement->execute([
        'name' => clean('name'), 'email' => clean('email'), 'phone' => clean('phone'),
        'age' => clean('age_category'), 'choice' => clean('participation_choice'),
        'people' => (int) $people, 'comment' => clean('comment'),
    ]);
    redirect('bedankt?type=interesse');
}

function submit_volunteer(): void
{
    verify_csrf();
    $errors = required_errors(['name', 'email', 'phone', 'help_type', 'consent']);
    if (!validate_email(clean('email'))) {
        $errors['email'] = 'Vul een geldig e-mailadres in.';
    }
    if ($errors) {
        render('volunteer', ['title' => 'Help je mee?', 'errors' => $errors]);
        return;
    }
    $statement = db()->prepare('INSERT INTO volunteer_submissions
        (name, email, phone, help_type, availability, consent)
        VALUES (:name, :email, :phone, :type, :availability, 1)');
    $statement->execute([
        'name' => clean('name'), 'email' => clean('email'), 'phone' => clean('phone'),
        'type' => clean('help_type'), 'availability' => clean('availability'),
    ]);
    redirect('bedankt?type=vrijwilliger');
}

function submit_sponsor(): void
{
    verify_csrf();
    $errors = required_errors(['company_name', 'contact_name', 'email', 'phone', 'contribution_type', 'consent']);
    if (!validate_email(clean('email'))) {
        $errors['email'] = 'Vul een geldig e-mailadres in.';
    }
    if ($errors) {
        render('sponsor', ['title' => 'Sponsor worden', 'errors' => $errors]);
        return;
    }
    $statement = db()->prepare('INSERT INTO sponsor_submissions
        (company_name, contact_name, email, phone, website, contribution_type, comment, consent)
        VALUES (:company, :contact, :email, :phone, :website, :type, :comment, 1)');
    $statement->execute([
        'company' => clean('company_name'), 'contact' => clean('contact_name'),
        'email' => clean('email'), 'phone' => clean('phone'), 'website' => clean('website'),
        'type' => clean('contribution_type'), 'comment' => clean('comment'),
    ]);
    redirect('bedankt?type=sponsor');
}

function submit_idea(): void
{
    verify_csrf();
    $errors = required_errors(['activity_idea', 'target_group', 'consent']);
    if ($errors) {
        render('idea', ['title' => 'Idee insturen', 'errors' => $errors]);
        return;
    }
    $statement = db()->prepare('INSERT INTO idea_submissions
        (name, activity_idea, target_group, required_material, comment, consent)
        VALUES (:name, :idea, :target, :material, :comment, 1)');
    $statement->execute([
        'name' => clean('name'), 'idea' => clean('activity_idea'), 'target' => clean('target_group'),
        'material' => clean('required_material'), 'comment' => clean('comment'),
    ]);
    redirect('bedankt?type=idee');
}

function required_errors(array $fields): array
{
    $errors = [];
    foreach ($fields as $field) {
        if (clean($field) === '') {
            $errors[$field] = 'Dit veld is verplicht.';
        }
    }
    return $errors;
}
