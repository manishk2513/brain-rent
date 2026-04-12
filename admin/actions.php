<?php
// admin/actions.php — Admin actions for content management
require_once __DIR__ . '/../config/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/admin/index.php');
    exit;
}

if (!verifyCsrf($_POST['csrf'] ?? '')) {
    header('Location: ' . APP_URL . '/admin/index.php?status=error&message=Invalid+CSRF');
    exit;
}

$entity = $_POST['entity'] ?? '';
$action = $_POST['action'] ?? '';
$id = (int) ($_POST['id'] ?? 0);
$redirect = $_POST['redirect'] ?? APP_URL . '/admin/index.php';

$tableMap = [
    'notes' => ['table' => 'notes', 'fileCols' => ['file_path']],
    'libraries' => ['table' => 'libraries', 'fileCols' => ['file_path', 'cover_image']],
    'videos' => ['table' => 'problem_solving_videos', 'fileCols' => ['video_path', 'thumbnail']],
    'users' => ['table' => 'users', 'fileCols' => []],
    'experts' => ['table' => 'expert_profiles', 'fileCols' => []],
];

if (!$id || !isset($tableMap[$entity])) {
    header('Location: ' . $redirect . '?status=error&message=Invalid+request');
    exit;
}

$db = Database::getInstance();
ensurePendingExpertProfilesTable($db);
$adminUserId = currentUserId();
$table = $tableMap[$entity]['table'];
$fileCols = $tableMap[$entity]['fileCols'];

function appendStatus(string $url, string $status, string $message): string
{
    $separator = strpos($url, '?') === false ? '?' : '&';
    return $url . $separator . 'status=' . rawurlencode($status) . '&message=' . rawurlencode($message);
}

if ($action === 'toggle_active') {
    $db->execute("UPDATE {$table} SET is_active = IF(is_active=1,0,1) WHERE id = ?", [$id]);
    header('Location: ' . appendStatus($redirect, 'success', 'Status updated'));
    exit;
}

if ($action === 'verify_expert') {
    $user = $db->fetchOne("SELECT id, user_type FROM users WHERE id = ?", [$id]);
    if (!$user || !in_array($user['user_type'], ['expert', 'both'])) {
        header('Location: ' . appendStatus($redirect, 'error', 'User is not an expert'));
        exit;
    }

    $pending = $db->fetchOne(
        "SELECT * FROM pending_expert_profiles
         WHERE user_id = ? AND status = 'pending'
         ORDER BY created_at DESC
         LIMIT 1",
        [$id]
    );

    $profile = $db->fetchOne("SELECT id FROM expert_profiles WHERE user_id = ?", [$id]);

    if (!$pending && !$profile) {
        header('Location: ' . appendStatus($redirect, 'error', 'Expert profile not found'));
        exit;
    }

    $conn = $db->getConnection();
    try {
        $conn->beginTransaction();

        if ($pending) {
            $expertValues = [
                $pending['headline'] ?? null,
                $pending['qualification'] ?? null,
                $pending['domain'] ?? null,
                $pending['skills'] ?? null,
                $pending['expertise_areas'] ?? null,
                $pending['experience_years'] ?? null,
                $pending['current_role_name'] ?? null,
                $pending['company'] ?? null,
                $pending['linkedin_url'] ?? null,
                $pending['portfolio_url'] ?? null,
                (float) ($pending['rate_per_session'] ?? 0),
                $pending['currency'] ?? 'USD',
                (int) ($pending['session_duration_minutes'] ?? 10),
                (int) ($pending['max_response_hours'] ?? 48),
            ];

            if ($profile) {
                $stmt = $conn->prepare(
                    "UPDATE expert_profiles
                     SET headline = ?, qualification = ?, domain = ?, skills = ?, expertise_areas = ?,
                         experience_years = ?, current_role_name = ?, company = ?, linkedin_url = ?, portfolio_url = ?,
                         rate_per_session = ?, currency = ?, session_duration_minutes = ?, max_response_hours = ?,
                         is_verified = 1, is_available = 1, verification_docs = NULL
                     WHERE user_id = ?"
                );
                $stmt->execute(array_merge($expertValues, [$id]));
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO expert_profiles
                        (user_id, headline, qualification, domain, skills, expertise_areas,
                         experience_years, current_role_name, company, linkedin_url, portfolio_url,
                         rate_per_session, currency, session_duration_minutes, max_response_hours,
                         is_available, is_verified)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)"
                );
                $stmt->execute(array_merge([$id], $expertValues));
            }

            $stmt = $conn->prepare(
                "UPDATE pending_expert_profiles
                 SET status = 'approved', admin_note = NULL, reviewed_by = ?, reviewed_at = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([$adminUserId, (int) $pending['id']]);

            $desiredType = in_array($pending['desired_user_type'] ?? '', ['expert', 'both'], true)
                ? $pending['desired_user_type']
                : 'expert';

            $stmt = $conn->prepare("UPDATE users SET user_type = ?, is_active = 1 WHERE id = ?");
            $stmt->execute([$desiredType, $id]);
        } else {
            $stmt = $conn->prepare("UPDATE expert_profiles SET is_verified = 1, is_available = 1, verification_docs = NULL WHERE user_id = ?");
            $stmt->execute([$id]);

            $stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
            $stmt->execute([$id]);
        }

        $wallet = $db->fetchOne("SELECT id FROM expert_wallet WHERE expert_user_id = ?", [$id]);
        if (!$wallet) {
            $stmt = $conn->prepare("INSERT INTO expert_wallet (expert_user_id) VALUES (?)");
            $stmt->execute([$id]);
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollBack();
        header('Location: ' . appendStatus($redirect, 'error', 'Could not approve expert profile'));
        exit;
    }

    $db->execute(
        "INSERT INTO notifications (user_id, type, title, message, link)
         VALUES (?, 'expert_approved', 'Expert profile approved', 'Your expert profile has been approved. You can now access the expert dashboard.', ?)",
        [$id, APP_URL . '/pages/dashboard-expert.php']
    );

    header('Location: ' . appendStatus($redirect, 'success', 'Expert verified'));
    exit;
}

if ($action === 'reject_expert') {
    $user = $db->fetchOne("SELECT id, user_type FROM users WHERE id = ?", [$id]);
    if (!$user || !in_array($user['user_type'], ['expert', 'both'])) {
        header('Location: ' . appendStatus($redirect, 'error', 'User is not an expert'));
        exit;
    }

    $reason = trim((string) ($_POST['reason'] ?? ''));
    if ($reason === '') {
        $reason = 'Please update your profile details and resubmit for review.';
    }

    $pending = $db->fetchOne(
        "SELECT id FROM pending_expert_profiles
         WHERE user_id = ? AND status = 'pending'
         ORDER BY created_at DESC
         LIMIT 1",
        [$id]
    );

    if ($pending) {
        $db->execute(
            "UPDATE pending_expert_profiles
             SET status = 'rejected', admin_note = ?, reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ?",
            [substr($reason, 0, 1000), $adminUserId, (int) $pending['id']]
        );
    } else {
        $profile = $db->fetchOne("SELECT id FROM expert_profiles WHERE user_id = ?", [$id]);
        if (!$profile) {
            header('Location: ' . appendStatus($redirect, 'error', 'Expert profile not found'));
            exit;
        }

        $note = 'REJECTED: ' . date('Y-m-d H:i:s') . ' :: ' . substr($reason, 0, 500);
        $db->execute(
            "UPDATE expert_profiles
             SET is_verified = 0,
                 is_available = 0,
                 verification_docs = ?
             WHERE user_id = ?",
            [$note, $id]
        );
    }

    $db->execute(
        "INSERT INTO notifications (user_id, type, title, message, link)
         VALUES (?, 'expert_rejected', 'Expert profile needs changes', ?, ?)",
        [$id, 'Your expert profile was not approved yet. Reason: ' . $reason, APP_URL . '/pages/expert-pending.php']
    );

    header('Location: ' . appendStatus($redirect, 'success', 'Expert profile rejected with reason'));
    exit;
}

if ($action === 'delete') {
    if ($entity === 'users') {
        header('Location: ' . appendStatus($redirect, 'error', 'Users cannot be deleted'));
        exit;
    }

    $row = $db->fetchOne("SELECT * FROM {$table} WHERE id = ?", [$id]);
    if (!$row) {
        header('Location: ' . appendStatus($redirect, 'error', 'Record not found'));
        exit;
    }

    $db->execute("DELETE FROM {$table} WHERE id = ?", [$id]);

    foreach ($fileCols as $col) {
        if (empty($row[$col])) {
            continue;
        }

        $value = $row[$col];
        $filePath = resolveUploadedFilePath($value);

        if (is_string($filePath) && file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    header('Location: ' . appendStatus($redirect, 'success', 'Record deleted'));
    exit;
}

header('Location: ' . appendStatus($redirect, 'error', 'Unknown action'));
exit;
