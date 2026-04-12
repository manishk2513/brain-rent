<?php
// =============================================
// config/auth.php  —  Session & Auth Helpers
// =============================================

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Helpers ----

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/pages/auth.php?tab=login');
        exit;
    }
}

function currentUser(): ?array
{
    if (!isLoggedIn())
        return null;
    $db = Database::getInstance();
    return $db->fetchOne(
        "SELECT id, full_name, email, user_type, profile_photo FROM users WHERE id = ? AND is_active = 1",
        [$_SESSION['user_id']]
    );
}

function currentUserId(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function ensurePendingExpertProfilesTable(Database $db): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $db->execute(
        "CREATE TABLE IF NOT EXISTS pending_expert_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            desired_user_type ENUM('expert','both') NOT NULL DEFAULT 'expert',
            headline VARCHAR(255),
            qualification VARCHAR(255),
            domain VARCHAR(150),
            skills TEXT,
            expertise_areas TEXT,
            experience_years INT,
            current_role_name VARCHAR(200),
            company VARCHAR(200),
            linkedin_url VARCHAR(500),
            portfolio_url VARCHAR(500),
            rate_per_session DECIMAL(10,2) NOT NULL DEFAULT 0,
            currency VARCHAR(3) DEFAULT 'USD',
            session_duration_minutes INT DEFAULT 10,
            max_response_hours INT DEFAULT 48,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            admin_note TEXT,
            reviewed_by INT NULL,
            reviewed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_pep_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_pep_admin FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_pep_status_created (status, created_at),
            INDEX idx_pep_user_status (user_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $ensured = true;
}

function ensureTemporaryPaymentsTable(Database $db): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $db->execute(
        "CREATE TABLE IF NOT EXISTS temporary_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            client_id INT NOT NULL,
            preferred_expert_id INT NOT NULL,
            gateway ENUM('stripe','razorpay') NOT NULL DEFAULT 'razorpay',
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'USD',
            status ENUM('initiated','paid','failed') NOT NULL DEFAULT 'paid',
            transaction_ref VARCHAR(191),
            paid_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_temp_pay_request FOREIGN KEY (request_id) REFERENCES thinking_requests(id) ON DELETE CASCADE,
            CONSTRAINT fk_temp_pay_client FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_temp_pay_expert FOREIGN KEY (preferred_expert_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_temp_pay_request (request_id),
            INDEX idx_temp_pay_client (client_id),
            INDEX idx_temp_pay_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $ensured = true;
}

function isExpertVerified(int $userId): bool
{
    if (isset($_SESSION['expert_verified']) && (int) ($_SESSION['user_id'] ?? 0) === $userId && (int) $_SESSION['expert_verified'] === 1) {
        return true;
    }

    $db = Database::getInstance();
    $row = $db->fetchOne("SELECT is_verified FROM expert_profiles WHERE user_id = ?", [$userId]);
    $verified = !empty($row) && (int) $row['is_verified'] === 1;

    if ((int) ($_SESSION['user_id'] ?? 0) === $userId) {
        $_SESSION['expert_verified'] = $verified ? 1 : 0;
    }

    return $verified;
}

function requireExpert(): void
{
    requireLogin();
    $user = currentUser();
    if (!$user || !in_array($user['user_type'], ['expert', 'both'])) {
        header('Location: ' . APP_URL . '/pages/dashboard-client.php');
        exit;
    }
    if (!isExpertVerified($user['id'])) {
        header('Location: ' . APP_URL . '/pages/expert-pending.php');
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();
    $user = currentUser();
    if (!$user || $user['user_type'] !== 'admin') {
        header('Location: ' . APP_URL . '/pages/index.php');
        exit;
    }
}

// ---- Registration ----

function registerUser(array $data): array
{
    $db = Database::getInstance();
    $userType = strtolower(trim($data['user_type'] ?? 'client'));
    if (!in_array($userType, ['client', 'expert', 'both'], true)) {
        $userType = 'client';
    }
    $email = strtolower(trim($data['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email address'];
    }

    $phone = trim((string) ($data['phone'] ?? ''));
    $phone = $phone === '' ? null : $phone;
    $country = trim((string) ($data['country'] ?? ''));
    $country = $country === '' ? null : $country;

    // Check duplicate email
    $existing = $db->fetchOne(
        "SELECT id FROM users WHERE email = ?",
        [$email]
    );
    if ($existing) {
        return ['success' => false, 'error' => 'Email already registered'];
    }

    $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

    $conn = $db->getConnection();

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare(
            "INSERT INTO users (full_name, email, password_hash, user_type, country, phone)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            htmlspecialchars(trim($data['full_name'] ?? '')),
            $email,
            $hash,
            $userType,
            $country,
            $phone,
        ]);
        $id = (int) $conn->lastInsertId();

        if (!$id) {
            $conn->rollBack();
            return ['success' => false, 'error' => 'Registration failed'];
        }

        if (in_array($userType, ['expert', 'both'])) {
            ensurePendingExpertProfilesTable($db);

            $expert = $data['expert'] ?? [];
            $normalizeText = static function ($value): ?string {
                $value = trim((string) $value);
                return $value === '' ? null : $value;
            };

            $normalizeNumber = static function ($value): string {
                $value = trim((string) $value);
                if ($value === '') {
                    return '';
                }
                $value = preg_replace('/[^0-9.]/', '', $value);
                return $value ?? '';
            };

            $skillsRaw = $normalizeText($expert['skills'] ?? '');
            $skillsList = $skillsRaw
                ? array_values(array_filter(array_map('trim', preg_split('/[\n,]+/', $skillsRaw))))
                : [];
            $expertiseJson = $skillsList ? json_encode($skillsList) : null;

            $rateRaw = $normalizeNumber($expert['rate_per_session'] ?? null);
            $rate = is_numeric($rateRaw) ? (float) $rateRaw : 0.0;
            $sessionRaw = $normalizeNumber($expert['session_duration_minutes'] ?? null);
            $sessionMinutes = is_numeric($sessionRaw)
                ? (int) $sessionRaw
                : 10;
            $maxResponseRaw = $normalizeNumber($expert['max_response_hours'] ?? null);
            $maxResponseHours = is_numeric($maxResponseRaw)
                ? (int) $maxResponseRaw
                : 48;
            $experienceRaw = $normalizeNumber($expert['experience_years'] ?? null);
            $experienceYears = $experienceRaw !== ''
                ? (int) $experienceRaw
                : null;
            $currency = strtoupper(trim((string) ($expert['currency'] ?? 'USD')));
            $currency = $currency === '' ? 'USD' : $currency;
            $currency = substr($currency, 0, 3);

            $stmt = $conn->prepare(
                "INSERT INTO pending_expert_profiles
                    (user_id, desired_user_type, headline, qualification, domain, skills, expertise_areas, experience_years,
                     current_role_name, company, linkedin_url, portfolio_url, rate_per_session, currency,
                     session_duration_minutes, max_response_hours, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
            );
            $stmt->execute([
                $id,
                $userType,
                $normalizeText($expert['headline'] ?? null),
                $normalizeText($expert['qualification'] ?? null),
                $normalizeText($expert['domain'] ?? null),
                $skillsRaw,
                $expertiseJson,
                $experienceYears,
                $normalizeText($expert['current_role'] ?? null),
                $normalizeText($expert['company'] ?? null),
                $normalizeText($expert['linkedin_url'] ?? null),
                $normalizeText($expert['portfolio_url'] ?? null),
                $rate,
                $currency,
                $sessionMinutes,
                $maxResponseHours,
            ]);
        }

        $conn->commit();
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log('Registration failed: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Registration failed'];
    }

    return ['success' => true, 'user_id' => $id];
}

// ---- Login ----

function loginUser(string $email, string $password): array
{
    $db = Database::getInstance();
    $user = $db->fetchOne(
        "SELECT u.id, u.password_hash, u.full_name, u.user_type, u.is_active, u.is_email_verified,
                IFNULL(ep.is_verified, 0) AS expert_verified
         FROM users u
         LEFT JOIN expert_profiles ep ON ep.user_id = u.id
         WHERE u.email = ?",
        [strtolower(trim($email))]
    );

    if (!$user || !$user['is_active']) {
        return ['success' => false, 'error' => 'Invalid credentials'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid credentials'];
    }

    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['expert_verified'] = (int) ($user['expert_verified'] ?? 0);

    return ['success' => true, 'user' => $user];
}

// ---- Logout ----

function logoutUser(): void
{
    $_SESSION = [];
    session_destroy();
}

// ---- CSRF ----

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool
{
    return isset($_SESSION['csrf_token']) &&
        hash_equals($_SESSION['csrf_token'], $token);
}

// ---- JSON response helper ----

function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
