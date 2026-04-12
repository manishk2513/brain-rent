<?php
// database/patch_expert_profiles_columns.php
// Add missing expert_profiles columns for older databases.
// Usage: php database/patch_expert_profiles_columns.php

require_once __DIR__ . '/../config/db.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$table = 'expert_profiles';
$exists = $db->fetchOne(
    "SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?",
    [DB_NAME, $table]
);

if (!$exists) {
    echo "Table '{$table}' not found. Run php database/setup_database.php\n";
    exit(1);
}

$columns = $db->fetchAll("SHOW COLUMNS FROM {$table}");
$existing = [];
foreach ($columns as $col) {
    $existing[strtolower($col['Field'])] = true;
}

$desired = [
    'headline' => "VARCHAR(255) NULL",
    'qualification' => "VARCHAR(255) NULL",
    'domain' => "VARCHAR(150) NULL",
    'skills' => "TEXT NULL",
    'expertise_areas' => "TEXT NULL",
    'experience_years' => "INT NULL",
    'current_role_name' => "VARCHAR(200) NULL",
    'company' => "VARCHAR(200) NULL",
    'linkedin_url' => "VARCHAR(500) NULL",
    'portfolio_url' => "VARCHAR(500) NULL",
    'rate_per_session' => "DECIMAL(10,2) NOT NULL DEFAULT 0",
    'currency' => "VARCHAR(3) DEFAULT 'USD'",
    'session_duration_minutes' => "INT DEFAULT 10",
    'max_response_hours' => "INT DEFAULT 48",
    'is_available' => "TINYINT(1) DEFAULT 1",
    'max_active_requests' => "INT DEFAULT 5",
    'total_sessions' => "INT DEFAULT 0",
    'total_earnings' => "DECIMAL(12,2) DEFAULT 0",
    'average_rating' => "DECIMAL(3,2) DEFAULT 0",
    'total_reviews' => "INT DEFAULT 0",
    'is_verified' => "TINYINT(1) DEFAULT 0",
    'verification_docs' => "VARCHAR(500) NULL",
    'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
];

$added = 0;
foreach ($desired as $name => $definition) {
    if (isset($existing[strtolower($name)])) {
        continue;
    }
    $sql = "ALTER TABLE {$table} ADD COLUMN {$name} {$definition}";
    $conn->exec($sql);
    $added++;
    echo "Added column: {$name}\n";
}

if ($added === 0) {
    echo "No changes needed. Columns are up to date.\n";
} else {
    echo "Done. Added {$added} column(s).\n";
}
