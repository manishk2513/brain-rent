<?php
// database/seed_test_users.php — Seed test users for local login
require_once __DIR__ . '/../config/db.php';

$db = Database::getInstance();

function ensureUser(Database $db, string $email, string $password, string $userType, string $fullName): int
{
    $existing = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    if ($existing) {
        $db->execute(
            "UPDATE users SET password_hash = ?, user_type = ?, full_name = ?, is_active = 1, is_email_verified = 1 WHERE id = ?",
            [$hash, $userType, $fullName, $existing['id']]
        );
        return (int) $existing['id'];
    }

    $userId = $db->insertGetId(
        "INSERT INTO users (full_name, email, password_hash, user_type, is_active, is_email_verified)
         VALUES (?, ?, ?, ?, 1, 1)",
        [$fullName, $email, $hash, $userType]
    );

    return (int) $userId;
}

function ensureExpertWallet(Database $db, int $userId): void
{
    $existing = $db->fetchOne("SELECT id FROM expert_wallet WHERE expert_user_id = ?", [$userId]);
    if (!$existing) {
        $db->execute("INSERT INTO expert_wallet (expert_user_id) VALUES (?)", [$userId]);
    }
}

function ensureExpertProfile(Database $db, int $userId): void
{
    $existing = $db->fetchOne("SELECT id FROM expert_profiles WHERE user_id = ?", [$userId]);
    if ($existing) {
        return;
    }

    $db->execute(
        "INSERT INTO expert_profiles
            (user_id, headline, expertise_areas, experience_years, current_role_name, company, rate_per_session, currency, session_duration_minutes, max_response_hours, is_available, is_verified)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)",
        [
            $userId,
            'Test Expert',
            '["General"]',
            1,
            'Tutor',
            'BrainRent',
            10.00,
            'USD',
            10,
            24,
        ]
    );
}

$clientId = ensureUser($db, 'client123', 'client123', 'client', 'Client 123');
$expertId = ensureUser($db, 'expert123', 'expert123', 'expert', 'Expert 123');

ensureExpertWallet($db, $expertId);
ensureExpertProfile($db, $expertId);

echo "Seeded test users:\n";
echo "- client123 / client123 (client)\n";
echo "- expert123 / expert123 (expert)\n";
