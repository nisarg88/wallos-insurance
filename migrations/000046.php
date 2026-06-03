<?php
// Migration 000046: Add insurances table for policy tracking
// Covers: policy details, coverage amounts, portal credentials, renewal tracking

$db->exec("
CREATE TABLE IF NOT EXISTS insurances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    insurance_type TEXT NOT NULL DEFAULT 'other',
    logo TEXT,
    policy_number TEXT,
    insurer_name TEXT,
    coverage_type TEXT,
    coverage_amount REAL DEFAULT 0,
    sum_assured REAL DEFAULT 0,
    premium REAL DEFAULT 0,
    currency_id INTEGER DEFAULT 1,
    cycle INTEGER DEFAULT 4,
    frequency INTEGER DEFAULT 1,
    renewal_date DATE,
    start_date INTEGER,
    payment_method_id INTEGER,
    payer_user_id INTEGER,
    notify INTEGER DEFAULT 1,
    notify_days_before INTEGER DEFAULT 30,
    portal_url TEXT,
    portal_username TEXT,
    portal_password TEXT,
    nominee TEXT,
    beneficiary TEXT,
    notes TEXT,
    url TEXT,
    auto_renew INTEGER DEFAULT 1,
    inactive INTEGER DEFAULT 0,
    user_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id),
    FOREIGN KEY (payer_user_id) REFERENCES household(id),
    FOREIGN KEY (user_id) REFERENCES user(id)
)");

$db->exec("CREATE INDEX IF NOT EXISTS idx_insurances_user ON insurances(user_id)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_insurances_renewal ON insurances(renewal_date)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_insurances_type ON insurances(insurance_type)");