CREATE TABLE IF NOT EXISTS interest_submissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT,
    age_category TEXT NOT NULL,
    participation_choice TEXT NOT NULL CHECK (participation_choice IN ('yes', 'team', 'maybe', 'no')),
    expected_people INTEGER NOT NULL DEFAULT 1,
    comment TEXT,
    consent INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS volunteer_submissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT NOT NULL,
    help_type TEXT NOT NULL,
    availability TEXT,
    consent INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS sponsor_submissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    company_name TEXT NOT NULL,
    contact_name TEXT NOT NULL,
    email TEXT NOT NULL,
    phone TEXT NOT NULL,
    website TEXT,
    contribution_type TEXT NOT NULL,
    comment TEXT,
    status TEXT NOT NULL DEFAULT 'Nieuw',
    admin_notes TEXT NOT NULL DEFAULT '',
    consent INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS idea_submissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    name TEXT,
    activity_idea TEXT NOT NULL,
    target_group TEXT NOT NULL,
    required_material TEXT,
    comment TEXT,
    consent INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS admin_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key TEXT NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_interest_created ON interest_submissions(created_at);
CREATE INDEX IF NOT EXISTS idx_sponsor_status ON sponsor_submissions(status);
