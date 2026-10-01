CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT
);

CREATE TABLE IF NOT EXISTS mail_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    label TEXT NOT NULL,
    protocol TEXT NOT NULL DEFAULT 'imap',
    host TEXT NOT NULL,
    port INTEGER NOT NULL DEFAULT 993,
    security TEXT NOT NULL DEFAULT 'ssl',
    verify_cert INTEGER NOT NULL DEFAULT 1,
    username TEXT NOT NULL,
    password_enc TEXT NOT NULL,
    folder TEXT NOT NULL DEFAULT 'INBOX',
    smtp_host TEXT,
    smtp_port INTEGER,
    smtp_security TEXT,
    smtp_verify_cert INTEGER NOT NULL DEFAULT 1,
    smtp_username TEXT,
    smtp_password_enc TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS calendar_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    label TEXT NOT NULL,
    server_url TEXT NOT NULL DEFAULT 'https://caldav.icloud.com',
    verify_cert INTEGER NOT NULL DEFAULT 1,
    username TEXT NOT NULL,
    password_enc TEXT NOT NULL,
    calendar_filter TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS news_sources (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    url TEXT NOT NULL,
    notes TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_date TEXT NOT NULL,
    slot TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE (report_date, slot)
);

CREATE TABLE IF NOT EXISTS report_sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id INTEGER NOT NULL REFERENCES reports(id) ON DELETE CASCADE,
    kind TEXT NOT NULL,
    summary TEXT NOT NULL DEFAULT '',
    items_json TEXT NOT NULL DEFAULT '[]',
    period_from TEXT,
    period_to TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE (report_id, kind)
);
